import test from 'node:test';
import assert from 'node:assert/strict';
import { HOVER_DELAY_MS, LOOP_SECONDS, canPreview, shouldLoopBack, progress } from '../preview.js';

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
