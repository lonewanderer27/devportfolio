import test from 'node:test';
import assert from 'node:assert/strict';
import { tapSide, shouldShowTapHint, isDoubleTap, DOUBLE_TAP_WINDOW, DEAD_STRIP } from '../gestures.js';

test( 'a tap in the left half turns back, the right half forward', () => {
	assert.equal( tapSide( 100, 800 ), -1 );
	assert.equal( tapSide( 700, 800 ), 1 );
} );

test( 'the phone edge dead strip turns nothing, and an empty box turns nothing', () => {
	assert.equal( tapSide( DEAD_STRIP - 1, 800 ), 0 );
	assert.equal( tapSide( 800 - DEAD_STRIP + 1, 800 ), 0 );
	assert.equal( tapSide( 100, 0 ), 0 );
} );

test( 'the double-tap window is one constant shared with isDoubleTap', () => {
	assert.equal( DOUBLE_TAP_WINDOW, 300 );
	assert.equal( isDoubleTap( { t: 0, x: 10, y: 10 }, { t: DOUBLE_TAP_WINDOW, x: 10, y: 10 } ), true );
	assert.equal( isDoubleTap( { t: 0, x: 10, y: 10 }, { t: DOUBLE_TAP_WINDOW + 1, x: 10, y: 10 } ), false );
} );

test( 'the Tap to Flip hint shows only on touch, in a two-page view, unflipped and not zoomed', () => {
	assert.equal( shouldShowTapHint( { coarse: true, step: 2 } ), true );
	assert.equal( shouldShowTapHint( { coarse: false, step: 2 } ), false, 'a mouse never sees it' );
	assert.equal( shouldShowTapHint( { coarse: true, step: 1 } ), false, 'a single page has nothing to flip to' );
	assert.equal( shouldShowTapHint( { coarse: true, step: 2, flipped: true } ), false, 'gone after the first flip' );
	assert.equal( shouldShowTapHint( { coarse: true, step: 2, zoomed: true } ), false, 'a zoomed page does not flip' );
	assert.equal( shouldShowTapHint(), false );
} );
