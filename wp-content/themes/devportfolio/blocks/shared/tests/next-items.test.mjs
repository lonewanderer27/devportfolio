import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextItems } from '../next-items.js';

const order = [ '1', '2', '3', '4', '5' ];
const items = {
	'1': { categories: [ 'a' ] },
	'2': { categories: [ 'b' ] },
	'3': { categories: [ 'a' ] },
	'4': { categories: [ 'b' ] },
	'5': { categories: [ 'a', 'b' ] },
};

test( 'the two after the current id, wrapping from the last to the first', () => {
	assert.deepEqual( nextItems( order, items, '1', '', 2 ), [ '2', '3' ] );
	assert.deepEqual( nextItems( order, items, '5', '', 2 ), [ '1', '2' ] );
} );

test( 'an empty list when fewer than the minimum others match', () => {
	assert.deepEqual( nextItems( [ '1', '2' ], items, '1', '', 2 ), [] );
} );

test( 'an unknown current id starts before the first', () => {
	assert.deepEqual( nextItems( order, items, 'x', '', 2 ), [ '1', '2' ] );
} );

test( 'same category first, then the wrapped rest, cut to the count', () => {
	assert.deepEqual( nextItems( order, items, '1', '', 3, { sameCategoryFirst: true } ), [ '3', '5', '2' ] );
	assert.deepEqual( nextItems( order, items, '2', '', 3, { sameCategoryFirst: true } ), [ '4', '5', '3' ] );
} );

test( 'the filter applies before the same-category ordering', () => {
	assert.deepEqual( nextItems( order, items, '1', 'b', 3, { sameCategoryFirst: true } ), [ '5', '2', '4' ] );
} );

test( 'minimum: a shorter list is returned when enough others match, [] when not', () => {
	const short = nextItems( order, items, '1', 'b', 5, { minimum: 1 } );
	assert.equal( short.length, 3 );
	assert.deepEqual( nextItems( order, items, '1', 'b', 5 ), [] );
} );

test( 'never contains the current id and has no duplicates', () => {
	const result = nextItems( order, items, '3', '', 5, { sameCategoryFirst: true } );
	assert.ok( ! result.includes( '3' ) );
	assert.equal( new Set( result ).size, result.length );
} );
