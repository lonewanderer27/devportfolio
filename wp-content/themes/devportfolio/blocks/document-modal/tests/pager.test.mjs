import test from 'node:test';
import assert from 'node:assert/strict';
import {
	stepFor,
	normalisePage,
	pagerItems,
	visiblePages,
	pagerState,
	turnPage,
	indicatorParts,
	mobileFooterParts,
} from '../pager.js';

const show = ( pages, page ) => pagerItems( pages, page, stepFor( pages ) ).join( ' ' );

test( 'step is 2 for multi-page documents, 1 for a single page', () => {
	assert.equal( stepFor( 1 ), 1 );
	assert.equal( stepFor( 3 ), 2 );
} );

test( 'landscape documents are always one page at a time (DPD-013)', () => {
	assert.equal( stepFor( 1, 'landscape' ), 1 );
	assert.equal( stepFor( 3, 'landscape' ), 1 );
	assert.equal( stepFor( 24, 'landscape' ), 1 );
} );

test( 'portrait is the default orientation, so existing callers keep the two-page view', () => {
	assert.equal( stepFor( 4, 'portrait' ), 2 );
	assert.equal( stepFor( 4, undefined ), 2 );
	assert.equal( stepFor( 1, 'portrait' ), 1 );
	assert.equal( stepFor( 0 ), 1 );
} );

test( 'a 4-page portrait document turns 1-2, 3-4 and back', () => {
	const step = stepFor( 4 );
	assert.equal( turnPage( 1, 4, step, 1 ), 3 );
	assert.equal( turnPage( 3, 4, step, 1 ), 3, 'Next is dimmed on the last view' );
	assert.equal( turnPage( 3, 4, step, -1 ), 1 );
	assert.deepEqual( visiblePages( 3, 4, step ), [ 3, 4 ] );
} );

test( 'a 3-page portrait document ends on page 3 alone, with an empty right side', () => {
	const step = stepFor( 3 );
	assert.equal( turnPage( 1, 3, step, 1 ), 3 );
	assert.deepEqual( visiblePages( 3, 3, step ), [ 3 ] );
	assert.deepEqual( pagerState( 3, 3, step ), { backOff: false, nextOff: true } );
	assert.equal( indicatorParts( 3, 3, step ).both, false );
} );

test( 'a landscape document of 3 pages turns one page at a time', () => {
	const step = stepFor( 3, 'landscape' );
	assert.equal( turnPage( 1, 3, step, 1 ), 2 );
	assert.equal( turnPage( 2, 3, step, 1 ), 3 );
	assert.equal( turnPage( 3, 3, step, 1 ), 3 );
	assert.deepEqual( visiblePages( 2, 3, step ), [ 2 ] );
} );

test( 'normalisePage clamps and starts two-page views on an odd page', () => {
	assert.equal( normalisePage( 12, 24, 2 ), 11 );
	assert.equal( normalisePage( 11, 24, 2 ), 11 );
	assert.equal( normalisePage( 0, 24, 2 ), 1 );
	assert.equal( normalisePage( 99, 24, 2 ), 23 );
	assert.equal( normalisePage( 'x', 24, 2 ), 1 );
	assert.equal( normalisePage( 4, 24, 1 ), 4 );
} );

test( '24 pages on 11-12', () => {
	assert.equal( show( 24, 11 ), '1 … 9 10 11 12 13 14 … 24' );
	assert.deepEqual( visiblePages( 12, 24, 2 ), [ 11, 12 ] );
} );

test( 'short documents show every page without gaps', () => {
	assert.equal( show( 3, 1 ), '1 2 3' );
	assert.equal( show( 9, 5 ), '1 2 3 4 5 6 7 8 9' );
	assert.equal( show( 1, 1 ), '1' );
} );

test( '10 pages at the edges', () => {
	assert.equal( show( 10, 1 ), '1 2 3 4 … 10' );
	assert.equal( show( 10, 9 ), '1 … 7 8 9 10' );
} );

test( 'a gap of exactly one page shows that page', () => {
	assert.equal( show( 24, 5 ), '1 2 3 4 5 6 7 8 … 24' );
	assert.equal( show( 24, 7 ), '1 … 5 6 7 8 9 10 … 24' );
} );

test( 'single-page view uses a window of 3 on each side', () => {
	assert.equal( pagerItems( 24, 12, 1 ).join( ' ' ), '1 … 9 10 11 12 13 14 15 … 24' );
} );

test( 'back and next are off at the ends', () => {
	assert.deepEqual( pagerState( 1, 24, 2 ), { backOff: true, nextOff: false } );
	assert.deepEqual( pagerState( 23, 24, 2 ), { backOff: false, nextOff: true } );
	assert.deepEqual( pagerState( 1, 1, 1 ), { backOff: true, nextOff: true } );
	assert.deepEqual( pagerState( 3, 3, 2 ), { backOff: false, nextOff: true } );
} );

test( 'turnPage moves by the step and stops at the ends', () => {
	assert.equal( turnPage( 11, 24, 2, 1 ), 13 );
	assert.equal( turnPage( 11, 24, 2, -1 ), 9 );
	assert.equal( turnPage( 1, 24, 2, -1 ), 1 );
	assert.equal( turnPage( 23, 24, 2, 1 ), 23 );
	assert.equal( turnPage( 5, 24, 1, 1 ), 6 );
} );

test( 'indicator parts', () => {
	assert.deepEqual( indicatorParts( 11, 24, 2 ), { first: 11, last: 12, both: true, percent: 50 } );
	assert.deepEqual( indicatorParts( 5, 24, 1 ), { first: 5, last: 5, both: false, percent: 20.83 } );
	assert.deepEqual( indicatorParts( 3, 3, 2 ), { first: 3, last: 3, both: false, percent: 100 } );
	assert.deepEqual( indicatorParts( 1, 1, 1 ), { first: 1, last: 1, both: false, percent: 100 } );
} );

test( 'mobile footer shows one-page progress and clamps safely', () => {
	assert.deepEqual( mobileFooterParts( 1, 24 ), { page: 1, label: '1 / 24', percent: 4.17 } );
	assert.deepEqual( mobileFooterParts( 2, 24 ), { page: 2, label: '2 / 24', percent: 8.33 } );
	assert.deepEqual( mobileFooterParts( 24, 24 ), { page: 24, label: '24 / 24', percent: 100 } );
	assert.deepEqual( mobileFooterParts( 1, 1 ), { page: 1, label: '1 / 1', percent: 100 } );
	assert.deepEqual( mobileFooterParts( 5, 0 ), { page: 1, label: '', percent: 0 } );
	assert.deepEqual( mobileFooterParts( 99, 24 ), { page: 24, label: '24 / 24', percent: 100 } );
	assert.equal( mobileFooterParts( 0, 24 ).page, 1 );
	assert.equal( mobileFooterParts( -3, 24 ).page, 1 );
	assert.equal( mobileFooterParts( 2.7, 24 ).page, 2 );
	assert.deepEqual( mobileFooterParts( '3', '24' ), { page: 3, label: '3 / 24', percent: 12.5 } );
	assert.deepEqual( mobileFooterParts( 3, undefined ), { page: 1, label: '', percent: 0 } );
	assert.equal( turnPage( 24, 24, 1, 1 ), 24 );
	assert.equal( turnPage( 1, 24, 1, -1 ), 1 );
} );
