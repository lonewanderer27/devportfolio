import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextTwo } from '../next.js';

const items = {
	a: { categories: [ 'flyer' ] },
	b: { categories: [ 'logo' ] },
	c: { categories: [ 'flyer' ] },
	d: { categories: [ 'flyer', 'logo' ] },
};
const order = [ 'a', 'b', 'c', 'd' ];

test( 'two after the current one', () => {
	assert.deepEqual( nextTwo( order, items, 'a', '' ), [ 'b', 'c' ] );
} );

test( 'wraps from the last to the first', () => {
	assert.deepEqual( nextTwo( order, items, 'd', '' ), [ 'a', 'b' ] );
	assert.deepEqual( nextTwo( order, items, 'c', '' ), [ 'd', 'a' ] );
} );

test( 'the filter keeps only its category', () => {
	assert.deepEqual( nextTwo( order, items, 'a', 'logo' ), [ 'b', 'd' ] );
} );

test( 'a current collateral outside the filter still gets the next two in the filter', () => {
	assert.deepEqual( nextTwo( order, items, 'b', 'flyer' ), [ 'c', 'd' ] );
} );

test( 'exactly two candidates', () => {
	assert.deepEqual( nextTwo( [ 'a', 'b', 'c' ], items, 'a', '' ), [ 'b', 'c' ] );
} );

test( 'fewer than two other collaterals gives nothing', () => {
	assert.deepEqual( nextTwo( [ 'a', 'b' ], items, 'a', '' ), [] );
	assert.deepEqual( nextTwo( [ 'a' ], items, 'a', '' ), [] );
	assert.deepEqual( nextTwo( [], items, 'a', '' ), [] );
	assert.deepEqual( nextTwo( order, items, 'a', 'logo' ), [ 'b', 'd' ] );
	assert.deepEqual( nextTwo( order, items, 'a', 'nothing' ), [] );
} );

test( 'an unknown current id starts from the first', () => {
	assert.deepEqual( nextTwo( order, items, 'zzz', '' ), [ 'a', 'b' ] );
} );

test( 'does not mutate its input', () => {
	const copy = JSON.stringify( [ order, items ] );
	nextTwo( order, items, 'd', 'flyer' );
	assert.equal( JSON.stringify( [ order, items ] ), copy );
} );
