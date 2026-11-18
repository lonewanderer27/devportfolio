/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { neighbours } from '../neighbours.js';

const order = [ 1, 2, 3, 4 ];
const items = {
	1: { categories: [ 'a' ] },
	2: { categories: [ 'a', 'b' ] },
	3: { categories: [ 'b' ] },
	4: { categories: [ 'a' ] },
};

test( 'all videos: the neighbours in grid order', () => {
	assert.deepEqual( neighbours( order, items, 2, '' ), { prev: 1, next: 3, index: 1, count: 4 } );
} );

test( 'the first and last video have no neighbour on that side (no wrap)', () => {
	assert.deepEqual( neighbours( order, items, 1, '' ), { prev: null, next: 2, index: 0, count: 4 } );
	assert.deepEqual( neighbours( order, items, 4, '' ), { prev: 3, next: null, index: 3, count: 4 } );
} );

test( 'a filter skips the videos outside it', () => {
	assert.deepEqual( neighbours( order, items, 2, 'a' ), { prev: 1, next: 4, index: 1, count: 3 } );
} );

test( 'the open video counts even when it is outside the filter', () => {
	assert.deepEqual( neighbours( order, items, 3, 'a' ), { prev: 2, next: 4, index: 2, count: 4 } );
} );

test( 'a filter with only the open video has no neighbours', () => {
	assert.deepEqual( neighbours( order, items, 2, 'zzz' ), { prev: null, next: null, index: 0, count: 1 } );
} );

test( 'an item without categories does not throw and does not match a slug', () => {
	const sparse = { ...items, 4: {} };
	assert.deepEqual( neighbours( order, sparse, 2, 'a' ), { prev: 1, next: null, index: 1, count: 2 } );
} );
