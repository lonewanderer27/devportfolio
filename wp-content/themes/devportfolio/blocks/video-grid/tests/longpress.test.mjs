import test from 'node:test';
import assert from 'node:assert/strict';
import { HOLD_MS, MOVE_TOLERANCE, movedTooFar, canLongPress } from '../longpress.js';

test( 'the numbers match the story', () => {
	assert.equal( HOLD_MS, 450 );
	assert.equal( MOVE_TOLERANCE, 10 );
} );

test( 'movement up to 10 px keeps the press, more cancels it', () => {
	assert.equal( movedTooFar( 0, 0 ), false );
	assert.equal( movedTooFar( 10, 0 ), false );
	assert.equal( movedTooFar( 6, 8 ), false ); // exactly 10
	assert.equal( movedTooFar( 7, 8 ), true );
	assert.equal( movedTooFar( 0, -11 ), true );
} );

test( 'only the first touch finger on the mobile routing may press', () => {
	const ok = { pointerType: 'touch', isPrimary: true, mobile: true };
	assert.equal( canLongPress( ok ), true );
	assert.equal( canLongPress( { ...ok, pointerType: 'mouse' } ), false );
	assert.equal( canLongPress( { ...ok, pointerType: 'pen' } ), false );
	assert.equal( canLongPress( { ...ok, isPrimary: false } ), false );
	assert.equal( canLongPress( { ...ok, mobile: false } ), false );
} );
