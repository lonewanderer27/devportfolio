import test from 'node:test';
import assert from 'node:assert/strict';
import { orientationOf, fitPage, renderWindow, canvasScale, spreadFits } from '../layout.js';

test( 'orientation: wider than tall is landscape, everything else portrait', () => {
	assert.equal( orientationOf( 792, 612 ), 'landscape' );
	assert.equal( orientationOf( 612, 792 ), 'portrait' );
	assert.equal( orientationOf( 500, 500 ), 'portrait' );
	assert.equal( orientationOf( '800', '600' ), 'landscape' );
} );

test( 'a spread that is height-bound: pages follow the stage height', () => {
	// Letter ratio 0.773; stage 1400 x 600 -> page height 600, width 463.
	const { pageW, pageH } = fitPage( { stageW: 1400, stageH: 600, ratio: 612 / 792, step: 2 } );
	assert.ok( pageH <= 600 && pageH >= 598 );
	assert.equal( pageW, Math.floor( 600 * ( 612 / 792 ) ) );
	assert.ok( pageW * 2 <= 1400 );
} );

test( 'a spread that is width-bound: two pages share the stage width', () => {
	const { pageW, pageH } = fitPage( { stageW: 600, stageH: 1000, ratio: 0.75, step: 2 } );
	assert.equal( pageW, 300 );
	assert.equal( pageH, 400 );
} );

test( 'a single portrait page uses the whole width when it can', () => {
	const { pageW, pageH } = fitPage( { stageW: 500, stageH: 2000, ratio: 0.75, step: 1 } );
	assert.equal( pageW, 500 );
	assert.equal( pageH, 666 );
} );

test( 'a landscape page fills the stage width, or the height if that is smaller', () => {
	const wide = fitPage( { stageW: 1000, stageH: 900, ratio: 1.6, step: 1 } );
	assert.equal( wide.pageW, 1000 );
	assert.equal( wide.pageH, 625 );
	const short = fitPage( { stageW: 1000, stageH: 400, ratio: 1.6, step: 1 } );
	assert.equal( short.pageH, 400 );
	assert.equal( short.pageW, 640 );
} );

test( 'pages are never drawn wider than their source', () => {
	const { pageW, pageH } = fitPage( { stageW: 3000, stageH: 3000, ratio: 0.75, step: 1, maxPageW: 1400 } );
	assert.equal( pageW, 1400 );
	assert.equal( pageH, 1866 );
	assert.equal( fitPage( { stageW: 800, stageH: 800, ratio: 0.75, step: 1, maxPageW: Infinity } ).pageW, 600 );
} );

test( 'a tiny or empty stage still returns a usable size', () => {
	assert.deepEqual( fitPage( { stageW: 0, stageH: 0, ratio: 0.75, step: 2 } ), { pageW: 1, pageH: 1 } );
	const small = fitPage( { stageW: 10, stageH: 10, ratio: 0.75, step: 2 } );
	assert.ok( small.pageW >= 1 && small.pageH >= 1 );
} );

test( 'a missing or invalid ratio falls back to a portrait page', () => {
	const { pageW, pageH } = fitPage( { stageW: 900, stageH: 400, ratio: 0, step: 1 } );
	assert.equal( pageH, Math.floor( pageW / 0.75 ) );
} );

test( 'render window: the view plus views either side, clamped to the document', () => {
	// Two-page view on page 11 of 24, 2 views ahead: pages 3 to 16.
	assert.deepEqual( renderWindow( 11, 24, 2, 2 ), { from: 7, to: 16 } );
	assert.deepEqual( renderWindow( 1, 24, 2, 2 ), { from: 1, to: 6 } );
	assert.deepEqual( renderWindow( 23, 24, 2, 2 ), { from: 19, to: 24 } );
	assert.deepEqual( renderWindow( 5, 24, 1, 1 ), { from: 4, to: 6 } );
	assert.deepEqual( renderWindow( 3, 3, 2, 1 ), { from: 1, to: 3 } );
	assert.deepEqual( renderWindow( 1, 1, 1, 2 ), { from: 1, to: 1 } );
} );

test( 'canvas scale: device ratio capped by the tier', () => {
	assert.equal( canvasScale( { pageW: 400, pageH: 500, dpr: 3, dprCap: 2 } ), 2 );
	assert.equal( canvasScale( { pageW: 400, pageH: 500, dpr: 3, dprCap: 1.5 } ), 1.5 );
	assert.equal( canvasScale( { pageW: 400, pageH: 500, dpr: 1, dprCap: 2 } ), 1 );
	assert.equal( canvasScale( { pageW: 400, pageH: 500 } ), 1 );
} );

test( 'canvas scale is lowered so one canvas stays under the pixel cap', () => {
	// 1000 x 1000 at 2x would be 4 MP; cap at 1 MP -> scale 1.
	assert.equal( canvasScale( { pageW: 1000, pageH: 1000, dpr: 2, dprCap: 2, maxPixels: 1000000 } ), 1 );
	const scale = canvasScale( { pageW: 2000, pageH: 3000, dpr: 2, dprCap: 2, maxPixels: 8000000 } );
	assert.ok( 2000 * 3000 * scale * scale <= 8000000 + 1 );
	assert.ok( scale < 2 );
	// 0 means no pixel cap.
	assert.equal( canvasScale( { pageW: 2000, pageH: 3000, dpr: 2, dprCap: 2, maxPixels: 0 } ), 2 );
} );

test( 'spread: a portrait document spreads only when two pages are as big as one would be', () => {
	const r = 0.7;
	// phone in portrait: needs 700 wide for a 500 tall stage
	assert.equal( spreadFits( { stageW: 390, stageH: 500, ratio: r, pages: 12 } ), false );
	// phone in landscape: 844 x 240 needs 336
	assert.equal( spreadFits( { stageW: 844, stageH: 240, ratio: r, pages: 12 } ), true );
	// near-square foldable: 700 x 640 needs 896, so one larger page
	assert.equal( spreadFits( { stageW: 700, stageH: 640, ratio: r, pages: 12 } ), false );
	// exactly enough
	assert.equal( spreadFits( { stageW: 700, stageH: 500, ratio: r, pages: 4 } ), true );
} );

test( 'spread: never for one page or a landscape document', () => {
	assert.equal( spreadFits( { stageW: 2000, stageH: 200, ratio: 0.7, pages: 1 } ), false );
	assert.equal( spreadFits( { stageW: 2000, stageH: 200, ratio: 1.4, pages: 8 } ), false );
	assert.equal( spreadFits( { stageW: 2000, stageH: 200, ratio: 0, pages: 8 } ), false );
} );
