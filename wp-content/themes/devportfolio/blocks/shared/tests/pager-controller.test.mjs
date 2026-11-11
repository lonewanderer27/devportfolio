import test from 'node:test';
import assert from 'node:assert/strict';
import { footerModel } from '../pager-controller.js';
import { wording } from '../mode.js';

const words = wording( 'page', {} );

test( 'one page or none hides the whole footer and the arrows', () => {
	for ( const pages of [ 0, 1 ] ) {
		const model = footerModel( { id: 1, page: 1, step: 1, pages }, words );
		assert.equal( model.hidden, true );
		assert.equal( model.bar, null );
		assert.deepEqual( model.items, [] );
		assert.equal( model.prev.hidden, true );
		assert.equal( model.next.hidden, true );
	}
} );

test( 'two pages in a portrait spread: one spread of 1-2, Back off, Next on', () => {
	const model = footerModel( { id: 1, page: 1, step: 2, pages: 2 }, words );
	assert.equal( model.hidden, false );
	assert.equal( model.prev.disabled, true );
	assert.equal( model.next.disabled, true, 'the spread shows both pages, so Next is off' );
	assert.deepEqual( model.items.map( ( item ) => item.page ), [ 1, 2 ] );
	assert.equal( model.items.every( ( item ) => item.on ), true );
} );

test( 'a 24-page spread at page 3 reads Pages 3–4 of 24 and fills 3 and 4', () => {
	const model = footerModel( { id: 1, page: 3, step: 2, pages: 24 }, words );
	assert.equal( model.bar.valuetext, 'Pages 3–4 of 24' );
	assert.equal( model.bar.valuenow, '17' );
	const on = model.items.filter( ( item ) => item.on ).map( ( item ) => item.page );
	assert.deepEqual( on, [ 3, 4 ] );
	assert.equal( model.prev.disabled, false );
	assert.equal( model.next.disabled, false );
} );

test( 'the first and last views switch the arrows off', () => {
	const first = footerModel( { id: 1, page: 1, step: 1, pages: 5 }, words );
	assert.equal( first.prev.disabled, true );
	assert.equal( first.next.disabled, false );
	const last = footerModel( { id: 1, page: 5, step: 1, pages: 5 }, words );
	assert.equal( last.prev.disabled, false );
	assert.equal( last.next.disabled, true );
	assert.equal( last.bar.valuetext, 'Page 5 of 5' );
} );

test( 'up to nine pages show every number with no gaps', () => {
	const model = footerModel( { id: 1, page: 4, step: 1, pages: 9 }, words );
	assert.deepEqual( model.items.map( ( item ) => item.page ), [ 1, 2, 3, 4, 5, 6, 7, 8, 9 ] );
	assert.equal( model.items.some( ( item ) => item.type === 'gap' ), false );
} );

test( 'more than nine pages show a window with gaps around the current view', () => {
	const model = footerModel( { id: 1, page: 12, step: 1, pages: 24 }, words );
	const numbers = model.items.filter( ( item ) => item.type === 'page' ).map( ( item ) => item.page );
	assert.equal( numbers[ 0 ], 1, 'the first page is always there' );
	assert.equal( numbers[ numbers.length - 1 ], 24, 'and the last' );
	assert.ok( model.items.some( ( item ) => item.type === 'gap' ), 'the far pages are gaps' );
	assert.ok( numbers.includes( 12 ) && numbers.includes( 9 ) && numbers.includes( 15 ) );
	assert.equal( model.items.filter( ( item ) => item.on ).length, 1 );
} );

test( 'a page label names the piece with the words given (slides)', () => {
	const slides = wording( 'slide', {} );
	const model = footerModel( { id: 1, page: 2, step: 1, pages: 3 }, slides );
	assert.equal( model.items[ 1 ].label, `${ slides.one } 2` );
	assert.equal( model.bar.valuetext, `${ slides.one } 2 of 3` );
} );

test( 'the progress percent follows the last visible page', () => {
	const model = footerModel( { id: 1, page: 1, step: 1, pages: 4 }, words );
	assert.equal( model.bar.percent, 25 );
} );
