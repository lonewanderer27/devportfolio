import { test } from 'node:test';
import assert from 'node:assert/strict';
import { timeLabel, fraction } from '../time.js';

test( 'timeLabel counts up in m:ss', () => {
	assert.equal( timeLabel( 0, 40 ), '0:00' );
	assert.equal( timeLabel( 999, 40 ), '0:00' );
	assert.equal( timeLabel( 1000, 40 ), '0:01' );
	assert.equal( timeLabel( 3999, 40 ), '0:03' );
	assert.equal( timeLabel( 65000, 300 ), '1:05' );
} );

test( 'timeLabel never passes the clip length', () => {
	assert.equal( timeLabel( 3999, 2 ), '0:02' );
	assert.equal( timeLabel( 4000, 3.9 ), '0:03' );
} );

test( 'timeLabel ignores an unknown length and a negative time', () => {
	assert.equal( timeLabel( 3000, 0 ), '0:03' );
	assert.equal( timeLabel( -500, 0 ), '0:00' );
} );

test( 'fraction is elapsed over period, kept between 0 and 1', () => {
	assert.equal( fraction( 0, 4000 ), 0 );
	assert.equal( fraction( 1000, 4000 ), 0.25 );
	assert.equal( fraction( 5000, 4000 ), 1 );
	assert.equal( fraction( -1, 4000 ), 0 );
	assert.equal( fraction( 10, 0 ), 0 );
} );
