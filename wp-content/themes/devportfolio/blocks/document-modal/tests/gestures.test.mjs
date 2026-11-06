import test from 'node:test';
import assert from 'node:assert/strict';
import {
	classifyTap,
	swipeDirection,
	verticalIntent,
	pinchScale,
	stepZoom,
	doubleTapTarget,
	isDoubleTap,
	zoomAround,
	clampPan,
	distance,
	zoomPercent,
} from '../gestures.js';

const tap = ( x, extra = {} ) => classifyTap( { x, w: 400, t0: 0, t1: 120, moved: 2, ...extra } );

test( 'taps: the outer 20% turns, the middle is the centre, the very edge is a dead strip', () => {
	assert.equal( tap( 40 ), 'edge-prev' );
	assert.equal( tap( 80 ), 'edge-prev' );
	assert.equal( tap( 200 ), 'centre' );
	assert.equal( tap( 320 ), 'edge-next' );
	assert.equal( tap( 395 ), null, '8px dead strip at the screen edge' );
	assert.equal( tap( 3 ), null );
} );

test( 'taps: a long press or a drag is not a tap', () => {
	assert.equal( tap( 40, { t1: 700 } ), null );
	assert.equal( tap( 40, { moved: 12 } ), null );
	assert.equal( classifyTap( { x: 10, w: 0, t0: 0, t1: 10, moved: 0 } ), null );
} );

test( 'swipe: long and horizontal turns, left is next, right is previous', () => {
	assert.equal( swipeDirection( { dx: -90, dy: 10, dt: 300 } ), 1 );
	assert.equal( swipeDirection( { dx: 90, dy: -10, dt: 300 } ), -1 );
} );

test( 'swipe: short, slow or vertical-ish drags do nothing', () => {
	assert.equal( swipeDirection( { dx: -30, dy: 0, dt: 600 } ), 0 );
	assert.equal( swipeDirection( { dx: -90, dy: 80, dt: 300 } ), 0 );
	assert.equal( swipeDirection( { dx: 5, dy: 120, dt: 200 } ), 0 );
} );

test( 'swipe: a quick flick shorter than 48px still turns', () => {
	assert.equal( swipeDirection( { dx: -30, dy: 2, dt: 50 } ), 1 );
	assert.equal( swipeDirection( { dx: -10, dy: 0, dt: 10 } ), 0, 'under 24px never counts' );
} );

test( 'vertical intent: down, up, or none under 24px or when horizontal', () => {
	assert.equal( verticalIntent( { dy: 40 } ), 'down' );
	assert.equal( verticalIntent( { dy: -40 } ), 'up' );
	assert.equal( verticalIntent( { dy: 10 } ), 'none' );
	assert.equal( verticalIntent( { dx: 80, dy: 30 } ), 'none' );
} );

test( 'pinch: scales by the finger distance and stays on 1-4x', () => {
	assert.equal( pinchScale( 100, 200, 1 ), 2 );
	assert.equal( pinchScale( 100, 50, 1 ), 1 );
	assert.equal( pinchScale( 100, 900, 1 ), 4 );
	assert.equal( pinchScale( 0, 200, 2 ), 2 );
} );

test( 'keyboard zoom: half steps on the range', () => {
	assert.equal( stepZoom( 1, 1 ), 1.5 );
	assert.equal( stepZoom( 1, -1 ), 1 );
	assert.equal( stepZoom( 3.8, 1 ), 4 );
} );

test( 'double tap: in from 1x, back out from anything zoomed', () => {
	assert.equal( doubleTapTarget( 1 ), 2.5 );
	assert.equal( doubleTapTarget( 1.02 ), 2.5 );
	assert.equal( doubleTapTarget( 2.5 ), 1 );
	assert.equal( isDoubleTap( { t: 100, x: 10, y: 10 }, { t: 300, x: 20, y: 15 } ), true );
	assert.equal( isDoubleTap( { t: 100, x: 10, y: 10 }, { t: 500, x: 10, y: 10 } ), false );
	assert.equal( isDoubleTap( { t: 100, x: 0, y: 0 }, { t: 200, x: 90, y: 0 } ), false );
	assert.equal( isDoubleTap( null, { t: 1, x: 0, y: 0 } ), false );
} );

test( 'zoom around a point keeps that point still', () => {
	const { x, y } = zoomAround( { x: 0, y: 0, scale: 1, nextScale: 2, cx: 100, cy: 50 } );
	assert.deepEqual( { x, y }, { x: -100, y: -50 } );
	// the point under (100,50) is the same before and after
	assert.equal( ( 100 - 0 ) / 1, ( 100 - x ) / 2 );
} );

test( 'pan is clamped so the stage stays covered', () => {
	assert.deepEqual( clampPan( { x: 50, y: 50, scale: 2, w: 400, h: 600 } ), { x: 0, y: 0 } );
	assert.deepEqual( clampPan( { x: -900, y: -900, scale: 2, w: 400, h: 600 } ), { x: -400, y: -600 } );
	assert.deepEqual( clampPan( { x: -10, y: -20, scale: 1, w: 400, h: 600 } ), { x: 0, y: 0 } );
} );

test( 'distance and percent', () => {
	assert.equal( distance( { x: 0, y: 0 }, { x: 3, y: 4 } ), 5 );
	assert.equal( zoomPercent( 1.5 ), 150 );
} );
