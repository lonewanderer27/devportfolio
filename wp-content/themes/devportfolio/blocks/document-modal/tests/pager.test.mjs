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
} from '../pager.js';

const show = ( pages, page ) => pagerItems( pages, page, stepFor( pages ) ).join( ' ' );

test( 'step is 2 for multi-page documents, 1 for a single page', () => {
	assert.equal( stepFor( 1 ), 1 );
	assert.equal( stepFor( 3 ), 2 );
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
