import test from 'node:test';
import assert from 'node:assert/strict';
import { FADE_MS, pointInside, motionEnvironment, HOVER_DELAY_MS, LOOP_SECONDS, canPreview, shouldLoopBack, progress } from '../preview.js';

const ok = { hover: true, reducedMotion: false, saveData: false, src: 'a.mp4' };

test( 'constants match the story', () => {
	assert.equal( HOVER_DELAY_MS, 800 );
	assert.equal( LOOP_SECONDS, 6 );
} );

test( 'canPreview needs hover, motion, no data saver and a source', () => {
	assert.equal( canPreview( ok ), true );
	assert.equal( canPreview( { ...ok, hover: false } ), false );
	assert.equal( canPreview( { ...ok, reducedMotion: true } ), false );
	assert.equal( canPreview( { ...ok, saveData: true } ), false );
	assert.equal( canPreview( { ...ok, src: '' } ), false );
} );

test( 'a stored clip never loops back in script, a full file does at 6 s', () => {
	assert.equal( shouldLoopBack( 7, true ), false );
	assert.equal( shouldLoopBack( 5.99, false ), false );
	assert.equal( shouldLoopBack( 6, false ), true );
} );

test( 'progress is clamped to 0..1', () => {
	assert.equal( progress( 0 ), 0 );
	assert.equal( progress( 3 ), 0.5 );
	assert.equal( progress( 6 ), 1 );
	assert.equal( progress( 9 ), 1 );
	assert.equal( progress( -1 ), 0 );
	assert.equal( progress( NaN ), 0 );
} );

test( 'the pointer is inside the grid on its edges and in its gaps, outside beyond them', () => {
	const rect = { left: 10, right: 110, top: 20, bottom: 220 };
	assert.equal( pointInside( rect, 60, 120 ), true );
	assert.equal( pointInside( rect, 10, 20 ), true );
	assert.equal( pointInside( rect, 110, 220 ), true );
	assert.equal( pointInside( rect, 9, 120 ), false );
	assert.equal( pointInside( rect, 60, 221 ), false );
} );

test( 'the previous video fades out for 150 ms', () => {
	assert.equal( FADE_MS, 150 );
} );

test( 'motionEnvironment reads reduced motion, data saver and the source', () => {
	// Node 24 defines navigator as a getter, so it is replaced with defineProperty and restored.
	const original = Object.getOwnPropertyDescriptor( globalThis, 'navigator' );
	const windowBefore = globalThis.window;
	globalThis.window = { matchMedia: () => ( { matches: true } ) };
	Object.defineProperty( globalThis, 'navigator', { value: { connection: { saveData: true } }, configurable: true } );
	try {
		assert.deepEqual( motionEnvironment( 'a.mp4' ), { reducedMotion: true, saveData: true, src: 'a.mp4' } );
		assert.deepEqual( motionEnvironment( undefined ), { reducedMotion: true, saveData: true, src: '' } );
	} finally {
		globalThis.window = windowBefore;
		if ( original ) {
			Object.defineProperty( globalThis, 'navigator', original );
		} else {
			delete globalThis.navigator;
		}
	}
} );
