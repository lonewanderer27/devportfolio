/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { classifyStroke, tapAction, dragOffset, peekOffset, shouldCommit } from '../mobile-gestures.js';

test( 'a short, quick touch is a tap; a slow one is nothing', () => {
	assert.equal( classifyStroke( { x0: 100, y0: 300, x1: 104, y1: 303, dt: 120 } ), 'tap' );
	assert.equal( classifyStroke( { x0: 100, y0: 300, x1: 104, y1: 303, dt: 400 } ), 'none' );
} );

test( 'a swipe up is next', () => {
	assert.equal( classifyStroke( { x0: 200, y0: 500, x1: 205, y1: 400, dt: 300 } ), 'next' );
} );

test( 'a swipe down from the middle is previous', () => {
	assert.equal( classifyStroke( { x0: 200, y0: 300, x1: 200, y1: 420, dt: 300 } ), 'prev' );
} );

test( 'a swipe down that starts in the top 64px closes the viewer', () => {
	assert.equal( classifyStroke( { x0: 200, y0: 20, x1: 200, y1: 140, dt: 300 } ), 'close' );
	assert.equal( classifyStroke( { x0: 200, y0: 64, x1: 200, y1: 200, dt: 300 } ), 'close' );
	assert.equal( classifyStroke( { x0: 200, y0: 65, x1: 200, y1: 200, dt: 300 } ), 'prev' );
} );

test( 'a short slow swipe is nothing; a short fast one is previous', () => {
	assert.equal( classifyStroke( { x0: 0, y0: 300, x1: 0, y1: 330, dt: 600 } ), 'none' );
	assert.equal( classifyStroke( { x0: 0, y0: 300, x1: 0, y1: 330, dt: 50 } ), 'prev' );
} );

test( 'a mostly sideways stroke is nothing', () => {
	assert.equal( classifyStroke( { x0: 0, y0: 300, x1: 120, y1: 400, dt: 200 } ), 'none' );
} );

test( 'a single tap waits for a possible second tap', () => {
	assert.deepEqual( tapAction( { x: 300, width: 400, now: 1000, last: null } ), { kind: 'toggle-later' } );
} );

test( 'a second tap in the right half skips forward 5 seconds, the left half back', () => {
	assert.deepEqual( tapAction( { x: 300, width: 400, now: 1200, last: { x: 290, t: 1000 } } ), { kind: 'skip', seconds: 5 } );
	assert.deepEqual( tapAction( { x: 100, width: 400, now: 1200, last: { x: 110, t: 1000 } } ), { kind: 'skip', seconds: -5 } );
} );

test( 'a second tap in the edge strip does nothing', () => {
	assert.deepEqual( tapAction( { x: 4, width: 400, now: 1200, last: { x: 4, t: 1000 } } ), { kind: 'none' } );
} );

test( 'after the window a tap is a new single tap', () => {
	assert.deepEqual( tapAction( { x: 300, width: 400, now: 1400, last: { x: 300, t: 1000 } } ), { kind: 'toggle-later' } );
} );

test( 'with double-tap skip off a tap toggles at once, even inside the window', () => {
	assert.deepEqual( tapAction( { x: 300, width: 400, now: 1200, last: { x: 300, t: 1000 }, skip: false } ), { kind: 'toggle-now' } );
} );

test( 'a drag moves the video with the finger; past the last video it moves a third as far', () => {
	assert.equal( dragOffset( -120, true ), -120 );
	assert.equal( dragOffset( -90, false ), -27 );
} );

test( 'the incoming video sits one screen below for next and above for prev', () => {
	assert.equal( peekOffset( 'next', 0, 800 ), 800 );
	assert.equal( peekOffset( 'prev', 0, 800 ), -800 );
	assert.equal( peekOffset( 'next', -200, 800 ), 600 );
} );

test( 'a release past a third of the screen commits; a slow short drag springs back', () => {
	assert.equal( shouldCommit( { dy: -300, dt: 900, height: 800, kind: 'next', hasTarget: true } ), true );
	assert.equal( shouldCommit( { dy: -60, dt: 600, height: 800, kind: 'next', hasTarget: true } ), false );
} );

test( 'a fast flick commits even when short, only towards the neighbour and only when there is one', () => {
	assert.equal( shouldCommit( { dy: -60, dt: 100, height: 800, kind: 'next', hasTarget: true } ), true );
	assert.equal( shouldCommit( { dy: 60, dt: 100, height: 800, kind: 'next', hasTarget: true } ), false );
	assert.equal( shouldCommit( { dy: -400, dt: 100, height: 800, kind: 'next', hasTarget: false } ), false );
} );
