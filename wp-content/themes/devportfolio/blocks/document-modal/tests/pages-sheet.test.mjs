import test from 'node:test';
import assert from 'node:assert/strict';
import {
	expandNames,
	thumbUrls,
	unitKind,
	pieceName,
	unitWord,
	pagesModel,
	visibleTiles,
	renderCount,
	CHUNK,
	WINDOW_FROM,
	createSheetMemory,
} from '../pages-sheet.js';

const strings = { pageLabel: 'Page {n}', slideLabel: 'Slide {n}', sheetLabel: 'Sheet {n}' };

test( 'ranges expand to one name per page and hold until the next range', () => {
	assert.deepEqual( expandNames( [ [ 1, 'Intro' ], [ 3, 'Plan' ] ], 5 ), [ 'Intro', 'Intro', 'Plan', 'Plan', 'Plan' ] );
} );

test( 'pages before the first range have no name, and an empty range clears the name', () => {
	assert.deepEqual( expandNames( [ [ 2, 'A' ], [ 4, '' ] ], 5 ), [ '', 'A', 'A', '', '' ] );
} );

test( 'ranges outside the document or malformed are ignored', () => {
	assert.deepEqual( expandNames( [ [ 9, 'Late' ], [ 0, 'Zero' ], [ 'x', 'Bad' ], [ 2 ] ], 3 ), [ '', '', '' ] );
} );

test( 'a plain list of names is accepted and trimmed', () => {
	assert.deepEqual( expandNames( [ ' Summary ', 'Flights' ], 3 ), [ 'Summary', 'Flights', '' ] );
} );

test( 'no names or no pages gives empty names of the right length', () => {
	assert.deepEqual( expandNames( undefined, 2 ), [ '', '' ] );
	assert.deepEqual( expandNames( [ [ 1, 'A' ] ], 0 ), [] );
} );

test( 'thumbnail URLs are built from the descriptor, one per page, 1-based and padded', () => {
	const urls = thumbUrls( { base: 'https://x.test/p/', ext: '.webp', count: 3, width: 240, height: 320 }, 3 );
	assert.deepEqual( urls, [ 'https://x.test/p/t-001.webp', 'https://x.test/p/t-002.webp', 'https://x.test/p/t-003.webp' ] );
	assert.equal( thumbUrls( { base: 'https://x.test/p/', ext: '.webp', count: 1200 }, 1200 )[ 1199 ], 'https://x.test/p/t-1200.webp' );
} );

test( 'a plain URL list is used only when complete', () => {
	assert.deepEqual( thumbUrls( [ 'a', 'b' ], 2 ), [ 'a', 'b' ] );
	assert.deepEqual( thumbUrls( [ 'a' ], 2 ), [] );
	assert.deepEqual( thumbUrls( [ 'a', '' ], 2 ), [] );
} );

test( 'a descriptor for another page count, or nothing, gives no thumbnails', () => {
	assert.deepEqual( thumbUrls( { base: 'https://x.test/p/', count: 2 }, 3 ), [] );
	assert.deepEqual( thumbUrls( null, 3 ), [] );
	assert.deepEqual( thumbUrls( undefined, 3 ), [] );
	assert.deepEqual( thumbUrls( { base: '', count: 3 }, 3 ), [] );
} );

test( 'the unit follows the file kind', () => {
	assert.equal( unitKind( { kind: 'pptx' } ), 'slide' );
	assert.equal( unitKind( { kind: 'xlsx' } ), 'sheet' );
	assert.equal( unitKind( { kind: 'ods' } ), 'sheet' );
	assert.equal( unitKind( { kind: 'pdf' } ), 'page' );
	assert.equal( unitKind( { kind: 'docx' } ), 'page' );
	assert.equal( unitKind( null ), 'page' );
} );

test( 'piece names use the strings, with English defaults', () => {
	assert.equal( pieceName( strings, 'slide', 4 ), 'Slide 4' );
	assert.equal( pieceName( {}, 'sheet', 2 ), 'Sheet 2' );
	assert.equal( pieceName( { pageLabel: 'Seite {n}' }, 'page', 7 ), 'Seite 7' );
	assert.equal( unitWord( strings, 'page' ), 'Page' );
	assert.equal( unitWord( { pageLabel: 'Seite {n}' }, 'page' ), 'Seite' );
} );

test( 'the model has one entry per page, 1-based, with the stored name or the unit word', () => {
	const model = pagesModel( { pages: 4, page: 3, names: [ [ 1, 'Trip summary' ], [ 2, 'Flights' ], [ 3, 'Hotels' ], [ 4, 'Transfers' ] ], strings } );
	assert.equal( model.length, 4 );
	assert.deepEqual( model.map( ( entry ) => entry.n ), [ 1, 2, 3, 4 ] );
	assert.deepEqual( model.map( ( entry ) => entry.name ), [ 'Trip summary', 'Flights', 'Hotels', 'Transfers' ] );
	assert.equal( model[ 2 ].label, 'Page 3, Hotels' );
	assert.ok( model.every( ( entry ) => entry.named ) );
} );

test( 'a page without a name shows the unit word and never the number as text', () => {
	const model = pagesModel( { pages: 3, page: 1, names: [], unit: 'slide', strings } );
	assert.deepEqual( model.map( ( entry ) => entry.name ), [ 'Slide', 'Slide', 'Slide' ] );
	assert.ok( model.every( ( entry ) => ! /\d/.test( entry.name ) ) );
	assert.equal( model[ 1 ].label, 'Slide 2' );
	assert.ok( model.every( ( entry ) => ! entry.named ) );
} );

test( 'a single page is current alone', () => {
	const model = pagesModel( { pages: 5, step: 1, page: 4, strings } );
	assert.deepEqual( model.filter( ( entry ) => entry.current ).map( ( entry ) => entry.n ), [ 4 ] );
} );

test( 'both pages of a spread are current', () => {
	const model = pagesModel( { pages: 8, step: 2, page: 4, strings } );
	// A spread starts on an odd page, so page 4 shows 3 and 4.
	assert.deepEqual( model.filter( ( entry ) => entry.current ).map( ( entry ) => entry.n ), [ 3, 4 ] );
} );

test( 'the last page of an odd spread is current alone', () => {
	const model = pagesModel( { pages: 5, step: 2, page: 5, strings } );
	assert.deepEqual( model.filter( ( entry ) => entry.current ).map( ( entry ) => entry.n ), [ 5 ] );
} );

test( 'thumbnails are optional in the model', () => {
	const none = pagesModel( { pages: 2, page: 1, strings } );
	assert.deepEqual( none.map( ( entry ) => entry.thumb ), [ '', '' ] );
	const some = pagesModel( { pages: 2, page: 1, thumbs: { base: 'https://x.test/p/', ext: '.webp', count: 2 }, strings } );
	assert.deepEqual( some.map( ( entry ) => entry.thumb ), [ 'https://x.test/p/t-001.webp', 'https://x.test/p/t-002.webp' ] );
} );

test( 'no pages gives an empty model', () => {
	assert.deepEqual( pagesModel( { pages: 0, page: 1 } ), [] );
} );

test( 'only the visible rows plus one ahead are due', () => {
	// 3 columns, tiles 1-9 visible (rows 1-3), one row ahead: tiles 1-12.
	assert.deepEqual( visibleTiles( { first: 1, last: 9, total: 300 } ), [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ] );
} );

test( 'a window in the middle widens to whole rows and never reaches the pages above it', () => {
	const due = visibleTiles( { first: 149, last: 157, total: 300 } );
	assert.equal( due[ 0 ], 148 );
	assert.equal( due[ due.length - 1 ], 162 );
	assert.ok( ! due.includes( 1 ) );
	assert.ok( due.every( ( n ) => n >= 148 ) );
} );

test( 'tiles are clipped to the document', () => {
	assert.deepEqual( visibleTiles( { first: 1, last: 2, total: 2 } ), [ 1, 2 ] );
	assert.deepEqual( visibleTiles( { first: 7, last: 10, total: 10 } ), [ 7, 8, 9, 10 ] );
} );

test( 'nothing is due in the list view, with data saver, or with no pages', () => {
	assert.deepEqual( visibleTiles( { view: 'list', first: 1, last: 9, total: 30 } ), [] );
	assert.deepEqual( visibleTiles( { first: 1, last: 9, total: 30, saveData: true } ), [] );
	assert.deepEqual( visibleTiles( { first: 1, last: 9, total: 0 } ), [] );
	assert.deepEqual( visibleTiles( { first: 5, last: 2, total: 30 } ), [] );
} );

test( 'short documents are built whole, long ones in chunks that reach the open page', () => {
	assert.equal( renderCount( WINDOW_FROM, 1 ), WINDOW_FROM );
	assert.equal( renderCount( 24, 20 ), 24 );
	assert.equal( renderCount( 300, 1 ), CHUNK );
	assert.equal( renderCount( 300, 150 ), 180 );
	assert.equal( renderCount( 300, 299 ), 300 );
	// Never fewer than what is already built.
	assert.equal( renderCount( 300, 1, 120 ), 120 );
} );

test( 'a document nobody has opened yet starts in Gallery with nothing remembered', () => {
	const memory = createSheetMemory();
	assert.equal( memory.has( 7 ), false );
	assert.equal( memory.viewFor( 7 ), 'grid' );
	assert.equal( memory.scrollFor( 7, 'grid' ), null );
	assert.equal( memory.scrollFor( 7, 'list' ), null );
	assert.equal( memory.builtFor( 7, 'grid' ), 0 );
} );

test( 'the last view and its scroll position come back for the same document', () => {
	const memory = createSheetMemory();
	memory.save( 7, { view: 'list', scrollTop: 640, built: 60 } );
	assert.equal( memory.viewFor( 7 ), 'list' );
	assert.equal( memory.scrollFor( 7, 'list' ), 640 );
	assert.equal( memory.builtFor( 7, 'list' ), 60 );
	assert.equal( memory.scrollFor( 7, 'grid' ), null, 'The other view was never scrolled: centre on the current page.' );
} );

test( 'each view keeps its own scroll position', () => {
	const memory = createSheetMemory();
	memory.save( 7, { view: 'grid', scrollTop: 300, built: 30 } );
	memory.save( 7, { view: 'list', scrollTop: 900, built: 90 } );
	assert.equal( memory.scrollFor( 7, 'grid' ), 300 );
	assert.equal( memory.scrollFor( 7, 'list' ), 900 );
	assert.equal( memory.viewFor( 7 ), 'list', 'The latest view wins.' );
} );

test( 'documents do not share what is remembered', () => {
	const memory = createSheetMemory();
	memory.save( 1, { view: 'list', scrollTop: 100, built: 10 } );
	assert.equal( memory.viewFor( 2 ), 'grid' );
	assert.equal( memory.scrollFor( 2, 'list' ), null );
	memory.forget( 1 );
	assert.equal( memory.has( 1 ), false );
	assert.equal( memory.viewFor( 1 ), 'grid' );
} );

test( 'the built count only grows and bad values are cleaned', () => {
	const memory = createSheetMemory();
	memory.save( 3, { view: 'grid', scrollTop: 10, built: 120 } );
	memory.save( 3, { view: 'grid', scrollTop: 20, built: 60 } );
	assert.equal( memory.builtFor( 3, 'grid' ), 120, 'A long document is rebuilt as far as it was ever scrolled.' );
	memory.save( 3, { view: 'nonsense', scrollTop: -50, built: 'x' } );
	assert.equal( memory.viewFor( 3 ), 'grid' );
	assert.equal( memory.scrollFor( 3, 'grid' ), 0, 'A negative or invalid position is 0.' );
	memory.save( 3, { view: 'list', scrollTop: NaN } );
	assert.equal( memory.scrollFor( 3, 'list' ), 0 );
} );
