import test from 'node:test';
import assert from 'node:assert/strict';
import {
	dragOutcome,
	dragOffset,
	DRAG_CLOSE_DISTANCE,
	DRAG_FLICK_DISTANCE,
	DRAG_FLICK_SPEED,
} from '../bottom-sheet.js';

test( 'dragging the handle past the distance closes the sheet', () => {
	assert.equal( dragOutcome( { dy: DRAG_CLOSE_DISTANCE, speed: 0 } ), 'close' );
	assert.equal( dragOutcome( { dy: DRAG_CLOSE_DISTANCE + 200, speed: 0 } ), 'close' );
} );

test( 'a short slow drag springs back', () => {
	assert.equal( dragOutcome( { dy: DRAG_CLOSE_DISTANCE - 1, speed: 0.1 } ), 'snap' );
	assert.equal( dragOutcome( { dy: 5, speed: 0 } ), 'snap' );
	assert.equal( dragOutcome( {} ), 'snap' );
} );

test( 'a quick flick closes the sheet from a short drag, but not from almost none', () => {
	assert.equal( dragOutcome( { dy: DRAG_FLICK_DISTANCE, speed: DRAG_FLICK_SPEED } ), 'close' );
	assert.equal( dragOutcome( { dy: DRAG_FLICK_DISTANCE - 1, speed: 5 } ), 'snap', 'A twitch is not a flick.' );
	assert.equal( dragOutcome( { dy: 60, speed: DRAG_FLICK_SPEED - 0.1 } ), 'snap' );
	assert.equal( dragOutcome( { dy: 60, speed: -2 } ), 'snap', 'Moving up never closes.' );
} );

test( 'the sheet only follows the finger downwards', () => {
	assert.equal( dragOffset( 40 ), 40 );
	assert.equal( dragOffset( -40 ), 0 );
	assert.equal( dragOffset( 'x' ), 0 );
} );
