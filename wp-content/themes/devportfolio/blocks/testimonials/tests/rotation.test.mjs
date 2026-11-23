import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PERIOD, SWIPE_MIN, stepIndex, swipeDirection, shouldRun } from '../rotation.js';

test( 'the period is six seconds', () => {
	assert.equal( PERIOD, 6000 );
} );

test( 'stepIndex wraps both ways', () => {
	assert.equal( stepIndex( 0, 3, 1 ), 1 );
	assert.equal( stepIndex( 2, 3, 1 ), 0 );
	assert.equal( stepIndex( 0, 3, -1 ), 2 );
	assert.equal( stepIndex( 0, 0, 1 ), 0 );
} );

test( 'a left swipe goes next, a right swipe goes back', () => {
	assert.equal( swipeDirection( -SWIPE_MIN, 0 ), 1 );
	assert.equal( swipeDirection( SWIPE_MIN, 0 ), -1 );
} );

test( 'short or mostly vertical strokes do nothing', () => {
	assert.equal( swipeDirection( -( SWIPE_MIN - 1 ), 0 ), 0 );
	assert.equal( swipeDirection( -100, 80 ), 0 );
	assert.equal( swipeDirection( -100, 60 ), 1 );
} );

const base = { count: 3, reducedMotion: false, hover: false, focus: false, hidden: false };

test( 'rotation runs only when nothing stops it', () => {
	assert.equal( shouldRun( base ), true );
	assert.equal( shouldRun( { ...base, hover: true } ), false );
	assert.equal( shouldRun( { ...base, focus: true } ), false );
	assert.equal( shouldRun( { ...base, hidden: true } ), false );
	assert.equal( shouldRun( { ...base, reducedMotion: true } ), false );
	assert.equal( shouldRun( { ...base, count: 1 } ), false );
} );
