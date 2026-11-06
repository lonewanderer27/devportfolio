import test from 'node:test';
import assert from 'node:assert/strict';
import { viewerMode, unitOf, wording, pieceLabel, positionText, canDrawOffice, canShowOnMobile, nativeAllowedOnMobile, isNativeGrid, isNativeScroll, hintKeyFor } from '../mode.js';

const images = [ 'a.webp', 'b.webp' ];

test( 'the native viewer takes over from stored images when the server allows it', () => {
	assert.equal( viewerMode( { images, nativeUrl: '/deck.pptx', viewerUrl: 'x.pdf' } ), 'native' );
	assert.equal( viewerMode( { images: [], nativeUrl: '/deck.pptx', viewerUrl: 'x.pdf' } ), 'native' );
	assert.equal( viewerMode( { nativeUrl: '/deck.pptx' } ), 'native' );
} );

test( 'without a native url the stored images win', () => {
	assert.equal( viewerMode( { images, nativeUrl: '', viewerUrl: 'x.pdf' } ), 'images' );
	assert.equal( viewerMode( { images } ), 'images' );
} );

test( 'pdf is the fallback when there are no images and no native url', () => {
	assert.equal( viewerMode( { images: [], nativeUrl: '', viewerUrl: 'x.pdf' } ), 'pdf' );
	assert.equal( viewerMode( { viewerUrl: 'x.pdf' } ), 'pdf' );
} );

test( 'nothing to show is unavailable', () => {
	assert.equal( viewerMode( { images: [], nativeUrl: '', viewerUrl: '' } ), 'unavailable' );
	assert.equal( viewerMode( {} ), 'unavailable' );
	assert.equal( viewerMode( null ), 'unavailable' );
	assert.equal( viewerMode( undefined ), 'unavailable' );
	assert.equal( viewerMode( 'x' ), 'unavailable' );
} );

test( 'junk values never select a mode', () => {
	assert.equal( viewerMode( { images: 'a.webp', nativeUrl: 5, viewerUrl: [] } ), 'unavailable' );
	assert.equal( viewerMode( { images: [], nativeUrl: null, viewerUrl: null } ), 'unavailable' );
} );

test( 'an odp (no native url from the server) can only have images or be unavailable', () => {
	assert.equal( viewerMode( { kind: 'odp', images: [], nativeUrl: '', viewerUrl: '' } ), 'unavailable' );
	assert.equal( viewerMode( { kind: 'odp', images } ), 'images' );
} );

test( 'presentations are counted in slides, everything else in pages', () => {
	[ 'pptx', 'ppt', 'odp' ].forEach( ( kind ) => assert.equal( unitOf( { kind } ), 'slide', kind ) );
	[ 'pdf', 'other', '', undefined ].forEach( ( kind ) => assert.equal( unitOf( { kind } ), 'page', String( kind ) ) );
	assert.equal( unitOf( null ), 'page' );
	assert.equal( unitOf( {} ), 'page' );
} );

test( 'wording uses the server strings and falls back to English', () => {
	assert.deepEqual( wording( 'slide', null ), { one: 'Slide', many: 'Slides', prev: 'Previous slide', next: 'Next slide', nav: 'Slides' } );
	assert.equal( wording( 'page', {} ).prev, 'Previous page' );
	assert.equal( wording( 'slide', { slide: { one: 'Diapositive', many: '' } } ).one, 'Diapositive' );
	assert.equal( wording( 'slide', { slide: { one: 'Diapositive', many: '' } } ).many, 'Slides', 'An empty string is not used.' );
	assert.equal( wording( 'slide', { slide: 'nope' } ).one, 'Slide' );
	assert.equal( wording( 'whatever', null ).one, 'Page' );
} );

test( 'labels for one piece and for the progress bar', () => {
	const pages = wording( 'page', null );
	const slides = wording( 'slide', null );

	assert.equal( pieceLabel( pages, 3, 12 ), 'Page 3 of 12' );
	assert.equal( pieceLabel( slides, 3, 12 ), 'Slide 3 of 12' );
	assert.equal( positionText( pages, 5, 5, 24 ), 'Page 5 of 24' );
	assert.equal( positionText( pages, 11, 12, 24 ), 'Pages 11–12 of 24' );
	assert.equal( positionText( slides, 5, 5, 12 ), 'Slide 5 of 12' );
	assert.equal( positionText( slides, 1, 2, 12 ), 'Slides 1–2 of 12' );
	assert.equal( positionText( pages, 7, undefined, 7 ), 'Page 7 of 7' );
} );

test( 'the native viewer needs WebAssembly, workers and a 2D canvas', () => {
	const ok = { webassembly: true, worker: true, canvas2d: true };

	assert.equal( canDrawOffice( ok ), true );
	assert.equal( canDrawOffice( { ...ok, webassembly: false } ), false );
	assert.equal( canDrawOffice( { ...ok, worker: false } ), false );
	assert.equal( canDrawOffice( { ...ok, canvas2d: false } ), false );
	assert.equal( canDrawOffice( {} ), false );
	assert.equal( canDrawOffice( null ), false );
	assert.equal( canDrawOffice( { webassembly: 'yes', worker: 1, canvas2d: {} } ), false, 'Only real booleans count.' );
} );

// --- Spreadsheets (DPD-027) -------------------------------------------------------------------

import { isSpreadsheet } from '../mode.js';

test( 'spreadsheets are recognised by kind and are still counted in pages', () => {
	[ 'xlsx', 'xls', 'ods' ].forEach( ( kind ) => {
		assert.equal( isSpreadsheet( { kind } ), true, kind );
		assert.equal( unitOf( { kind } ), 'page', kind );
	} );
	[ 'pdf', 'pptx', 'other', undefined ].forEach( ( kind ) => assert.equal( isSpreadsheet( { kind } ), false, String( kind ) ) );
	assert.equal( isSpreadsheet( null ), false );
	assert.equal( isSpreadsheet( 'xlsx' ), false );
} );

test( 'a workbook with a native url is drawn natively, and falls back to its pages', () => {
	assert.equal( viewerMode( { kind: 'xlsx', nativeUrl: 'https://x.test/a.xlsx', images: [ 'a.webp' ] } ), 'native' );
	assert.equal( viewerMode( { kind: 'xlsx', nativeUrl: '', images: [ 'a.webp' ] } ), 'images' );
	assert.equal( viewerMode( { kind: 'xls', nativeUrl: '', images: [] } ), 'unavailable' );
} );

test( 'the mobile body draws every known kind: PDFs, presentations, workbooks and word documents (DPD-031)', () => {
	[ 'pdf', 'pptx', 'ppt', 'odp', 'xlsx', 'xls', 'ods', 'docx', 'doc', 'odt' ].forEach( ( kind ) => assert.equal( canShowOnMobile( { kind } ), true, kind ) );
	[ 'other', '' ].forEach( ( kind ) => assert.equal( canShowOnMobile( { kind } ), false, kind ) );
	assert.equal( canShowOnMobile( null ), false );
	assert.equal( hintKeyFor( { kind: 'pptx' } ), 'hintSlide' );
	assert.equal( hintKeyFor( { kind: 'xlsx' } ), 'hintSheet' );
	assert.equal( hintKeyFor( { kind: 'docx' } ), 'hintDoc' );
	assert.equal( unitOf( { kind: 'docx' } ), 'page', 'Converted word pages keep the page wording.' );
	assert.equal( unitOf( { kind: 'xlsx' } ), 'page', 'Converted sheets keep the page wording.' );
} );

test( 'the native viewer is tried on mobile for a .pptx, .xlsx or .docx with a native URL only', () => {
	[ 'pptx', 'xlsx', 'docx' ].forEach( ( kind ) => {
		assert.equal( nativeAllowedOnMobile( { kind, nativeUrl: 'https://x/a' } ), true, kind );
		assert.equal( nativeAllowedOnMobile( { kind, nativeUrl: '' } ), false, kind );
		assert.equal( nativeAllowedOnMobile( { kind } ), false, kind );
	} );
	[ 'ppt', 'odp', 'pdf', 'xls', 'ods', 'doc', 'odt' ].forEach( ( kind ) => assert.equal( nativeAllowedOnMobile( { kind, nativeUrl: 'https://x/a' } ), false, kind ) );
	assert.equal( nativeAllowedOnMobile( null ), false );
} );

test( 'only a .xlsx with a native URL is drawn as a workbook grid (one piece, no footer)', () => {
	assert.equal( isNativeGrid( { kind: 'xlsx', nativeUrl: 'https://x/a.xlsx' } ), true );
	assert.equal( isNativeGrid( { kind: 'xlsx', nativeUrl: '', images: [ 'a' ] } ), false );
	assert.equal( isNativeGrid( { kind: 'xlsx' } ), false );
	[ 'pptx', 'xls', 'ods', 'pdf', 'docx' ].forEach( ( kind ) => assert.equal( isNativeGrid( { kind, nativeUrl: 'https://x/a' } ), false, kind ) );
	assert.equal( isNativeGrid( null ), false );
} );

test( 'a .xlsx or .docx with a native URL is drawn by a scroller of its own (one piece, no footer)', () => {
	[ 'xlsx', 'docx' ].forEach( ( kind ) => {
		assert.equal( isNativeScroll( { kind, nativeUrl: 'https://x/a' } ), true, kind );
		assert.equal( isNativeScroll( { kind, nativeUrl: '', images: [ 'a' ] } ), false, kind );
		assert.equal( isNativeScroll( { kind } ), false, kind );
	} );
	[ 'pptx', 'xls', 'ods', 'pdf', 'doc', 'odt' ].forEach( ( kind ) => assert.equal( isNativeScroll( { kind, nativeUrl: 'https://x/a' } ), false, kind ) );
	assert.equal( isNativeScroll( null ), false );
	assert.equal( isNativeGrid( { kind: 'docx', nativeUrl: 'https://x/a' } ), false, 'Only a workbook has a tab bar.' );
} );
