import test from 'node:test';
import assert from 'node:assert/strict';
import { createTouchZoom, ZOOMED } from '../touch-zoom.js';
import { hintKeyFor } from '../mode.js';

const make = ( w = 400, h = 600 ) => {
	const applied = [];
	const zoom = createTouchZoom( { box: () => ( { w, h } ), apply: ( z ) => applied.push( { ...z } ) } );
	return { zoom, applied };
};

test( 'starts at 1x and reset reports whether anything changed', () => {
	const { zoom, applied } = make();
	assert.deepEqual( zoom.get(), { scale: 1, x: 0, y: 0 } );
	assert.equal( zoom.isZoomed(), false );
	assert.equal( zoom.reset(), false );
	assert.equal( applied.length, 0, 'nothing applied when nothing changed' );
} );

test( 'keyboard steps zoom around the centre and stop at the limits', () => {
	const { zoom } = make();
	assert.equal( zoom.step( 1 ), true );
	assert.equal( zoom.get().scale, 1.5 );
	assert.equal( zoom.isZoomed(), true );
	assert.equal( zoom.step( -1 ), true );
	assert.equal( zoom.get().scale, 1 );
	assert.equal( zoom.step( -1 ), false, 'already at 1x' );
	for ( let i = 0; i < 10; i++ ) {
		zoom.step( 1 );
	}
	assert.equal( zoom.get().scale, 4 );
	assert.equal( zoom.step( 1 ), false );
	assert.equal( zoom.reset(), true );
	assert.equal( zoom.isZoomed(), false );
} );

test( 'a double tap zooms to 2.5x around the tap and a second one zooms out', () => {
	const { zoom } = make();
	assert.equal( zoom.tap( { t: 100, x: 200, y: 300 } ), false );
	assert.equal( zoom.tap( { t: 250, x: 205, y: 302 } ), true );
	assert.equal( zoom.get().scale, 2.5 );
	assert.equal( zoom.tap( { t: 1000, x: 10, y: 10 } ), false );
	assert.equal( zoom.tap( { t: 1150, x: 12, y: 11 } ), true );
	assert.equal( zoom.get().scale, 1 );
	zoom.tap( { t: 2000, x: 0, y: 0 } );
	zoom.forgetTap();
	assert.equal( zoom.tap( { t: 2100, x: 0, y: 0 } ), false, 'a forgotten tap starts over' );
} );

test( 'a pinch scales with the fingers and a pan stays inside the page', () => {
	const { zoom } = make();
	zoom.startPinch( { x: 150, y: 300 }, { x: 250, y: 300 } );
	zoom.movePinch( { x: 100, y: 300 }, { x: 300, y: 300 } );
	assert.equal( zoom.get().scale, 2 );
	assert.ok( zoom.get().x <= 0 && zoom.get().y <= 0 );
	zoom.endPinch();
	zoom.startPan();
	zoom.movePan( 5000, 5000 );
	assert.deepEqual( { x: zoom.get().x, y: zoom.get().y }, { x: 0, y: 0 }, 'cannot pan past the top left' );
	zoom.movePan( -5000, -5000 );
	assert.deepEqual( { x: zoom.get().x, y: zoom.get().y }, { x: -400, y: -600 }, 'nor past the bottom right' );
	zoom.movePinch( { x: 0, y: 0 }, { x: 1, y: 1 } );
	assert.ok( ZOOMED > 1 );
} );

test( 'which hint explains an unavailable document', () => {
	assert.equal( hintKeyFor( { kind: 'pptx' } ), 'hintSlide' );
	assert.equal( hintKeyFor( { kind: 'xlsx' } ), 'hintSheet' );
	assert.equal( hintKeyFor( { kind: 'docx' } ), 'hintDoc' );
	assert.equal( hintKeyFor( { kind: 'odt' } ), 'hintDoc' );
	assert.equal( hintKeyFor( { kind: 'pdf' } ), 'hint' );
	assert.equal( hintKeyFor( null ), 'hint' );
} );
