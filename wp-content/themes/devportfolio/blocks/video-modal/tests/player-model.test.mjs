/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatTime, timeLabel, ariaValueText, clampTime, clampVolume, progressPercent, seekForKey, afterEnd, PLAYBACK_RATES } from '../player-model.js';

test( 'formatTime gives m:ss and h:mm:ss', () => {
	assert.equal( formatTime( 0 ), '0:00' );
	assert.equal( formatTime( 8 ), '0:08' );
	assert.equal( formatTime( 28 ), '0:28' );
	assert.equal( formatTime( 59.9 ), '0:59' );
	assert.equal( formatTime( 65 ), '1:05' );
	assert.equal( formatTime( 3600 ), '1:00:00' );
	assert.equal( formatTime( 3661 ), '1:01:01' );
} );

test( 'formatTime treats missing, negative and non-finite values as 0:00', () => {
	assert.equal( formatTime( NaN ), '0:00' );
	assert.equal( formatTime( Infinity ), '0:00' );
	assert.equal( formatTime( -3 ), '0:00' );
	assert.equal( formatTime( undefined ), '0:00' );
} );

test( 'timeLabel and ariaValueText use the story wording', () => {
	assert.equal( timeLabel( 8, 28 ), '0:08 / 0:28' );
	assert.equal( ariaValueText( 8, 28 ), '0:08 of 0:28' );
	assert.equal( timeLabel( 0, NaN ), '0:00 / 0:00' );
} );

test( 'clampTime keeps the time inside the duration', () => {
	assert.equal( clampTime( -1, 28 ), 0 );
	assert.equal( clampTime( 30, 28 ), 28 );
	assert.equal( clampTime( 10, 28 ), 10 );
	assert.equal( clampTime( 10, NaN ), 0 );
	assert.equal( clampTime( 10, 0 ), 0 );
} );

test( 'progressPercent gives the fill position', () => {
	assert.equal( progressPercent( 14, 28 ), 50 );
	assert.equal( progressPercent( 0, 28 ), 0 );
	assert.equal( progressPercent( 40, 28 ), 100 );
	assert.equal( progressPercent( 5, NaN ), 0 );
} );

test( 'seekForKey moves five seconds and clamps at the ends', () => {
	assert.equal( seekForKey( 'ArrowRight', 10, 28 ), 15 );
	assert.equal( seekForKey( 'ArrowLeft', 10, 28 ), 5 );
	assert.equal( seekForKey( 'ArrowLeft', 3, 28 ), 0 );
	assert.equal( seekForKey( 'ArrowRight', 25, 28 ), 28 );
} );

test( 'seekForKey ignores other keys and an unknown duration', () => {
	assert.equal( seekForKey( 'a', 10, 28 ), null );
	assert.equal( seekForKey( 'ArrowRight', 10, NaN ), null );
	assert.equal( seekForKey( 'ArrowRight', 10, 0 ), null );
} );

test( 'clampVolume keeps 0 to 1 and treats missing values as full', () => {
	assert.equal( clampVolume( -0.2 ), 0 );
	assert.equal( clampVolume( 1.4 ), 1 );
	assert.equal( clampVolume( 0.35 ), 0.35 );
	assert.equal( clampVolume( NaN ), 1 );
} );

test( 'PLAYBACK_RATES has the built-in player steps', () => {
	assert.deepEqual( PLAYBACK_RATES, [ 0.5, 0.75, 1, 1.25, 1.5, 2 ] );
} );

test( 'afterEnd replays once, then stops', () => {
	assert.equal( afterEnd( 0 ), 'replay' );
	assert.equal( afterEnd( 1 ), 'stop' );
	assert.equal( afterEnd( 2 ), 'stop' );
} );
