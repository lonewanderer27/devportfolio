import { test } from 'node:test';
import assert from 'node:assert/strict';
import { localMs, formatInstant, formatClock } from '../time.js';

test( 'localMs is the wall clock in the browser zone', () => {
	assert.equal( localMs( '2026-10-06', '10:30' ), new Date( 2026, 9, 6, 10, 30 ).getTime() );
	assert.equal( localMs( '2026-10-06', '16:30' ) - localMs( '2026-10-06', '09:00' ), 7.5 * 3600000 );
} );

test( 'formatInstant words a moment in a friendly 12-hour form', () => {
	assert.equal( formatInstant( new Date( 2026, 9, 6, 16, 30 ).getTime(), 'en-AU' ), 'Tue 6 Oct, 4:30 pm' );
	assert.equal( formatInstant( new Date( 2026, 0, 1, 0, 5 ).getTime(), 'en-AU' ), 'Thu 1 Jan, 12:05 am' );
} );

test( 'formatClock gives the time pills as 12-hour clock times', () => {
	assert.equal( formatClock( '09:00', 'en-AU' ), '9:00 am' );
	assert.equal( formatClock( '16:30', 'en-AU' ), '4:30 pm' );
} );
