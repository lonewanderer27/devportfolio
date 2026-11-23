import { test } from 'node:test';
import assert from 'node:assert/strict';
import { BOOK_PERIOD, FRAME_PERIOD, bookRole, frameOffset, frameRole, tick } from '../rotation.js';

const roles = ( fn, active, count ) => Array.from( { length: count }, ( _, i ) => fn( i, active, count ) );

test( 'the two periods differ, so the cards are never in step', () => {
	assert.equal( BOOK_PERIOD, 3500 );
	assert.equal( FRAME_PERIOD, 4000 );
	assert.notEqual( BOOK_PERIOD, FRAME_PERIOD );
} );

test( 'four books: front, right, hidden, left', () => {
	assert.deepEqual( roles( bookRole, 0, 4 ), [ 'front', 'right', 'hidden', 'left' ] );
} );

test( 'advancing sends the front book to the left and brings the next one to the front', () => {
	assert.deepEqual( roles( bookRole, 1, 4 ), [ 'left', 'front', 'right', 'hidden' ] );
	assert.deepEqual( roles( bookRole, 2, 4 ), [ 'hidden', 'left', 'front', 'right' ] );
	assert.deepEqual( roles( bookRole, 3, 4 ), [ 'right', 'hidden', 'left', 'front' ] );
} );

test( 'fewer than four books still make a fan', () => {
	assert.deepEqual( roles( bookRole, 0, 3 ), [ 'front', 'right', 'left' ] );
	assert.deepEqual( roles( bookRole, 0, 2 ), [ 'front', 'right' ] );
	assert.deepEqual( roles( bookRole, 0, 1 ), [ 'front' ] );
} );

test( 'five frames: active in the middle, one each side, two parked', () => {
	assert.deepEqual( roles( frameRole, 0, 5 ), [ 'active', 'next', 'far-next', 'far-prev', 'prev' ] );
} );

test( 'advancing makes the active frame the last played one on the left', () => {
	assert.deepEqual( roles( frameRole, 1, 5 ), [ 'prev', 'active', 'next', 'far-next', 'far-prev' ] );
	assert.deepEqual( roles( frameRole, 4, 5 ), [ 'next', 'far-next', 'far-prev', 'prev', 'active' ] );
} );

test( 'small strips', () => {
	assert.deepEqual( roles( frameRole, 0, 3 ), [ 'active', 'next', 'prev' ] );
	assert.deepEqual( roles( frameRole, 0, 2 ), [ 'active', 'next' ] );
	assert.deepEqual( roles( frameRole, 0, 1 ), [ 'active' ] );
} );

test( 'frameOffset takes the short way round', () => {
	assert.equal( frameOffset( 4, 0, 5 ), -1 );
	assert.equal( frameOffset( 3, 0, 5 ), -2 );
	assert.equal( frameOffset( 2, 0, 5 ), 2 );
} );

test( 'tick counts only while running', () => {
	assert.deepEqual( tick( 100, 16, true, 3500 ), { elapsed: 116, advanced: false } );
	assert.deepEqual( tick( 100, 16, false, 3500 ), { elapsed: 100, advanced: false } );
} );

test( 'tick advances once at the end of the period and keeps the overshoot', () => {
	assert.deepEqual( tick( 3490, 20, true, 3500 ), { elapsed: 10, advanced: true } );
	assert.deepEqual( tick( 3480, 20, true, 3500 ), { elapsed: 0, advanced: true } );
} );
