import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toIso, parseIso, monthOf, shiftMonth, monthInRange, isSelectable, weekdayIndex, daysInMonth, monthGrid } from '../calendar.js';

const FIRST = '2026-10-06';
const LAST = '2027-10-05';

test( 'toIso pads and parseIso reads it back', () => {
	assert.equal( toIso( 2026, 3, 4 ), '2026-03-04' );
	assert.deepEqual( parseIso( '2026-03-04' ), { y: 2026, m: 3, d: 4 } );
	assert.equal( parseIso( '2026-3-4' ), null );
	assert.equal( parseIso( '' ), null );
	assert.equal( parseIso( undefined ), null );
} );

test( 'monthOf reads the month of a date', () => {
	assert.deepEqual( monthOf( '2026-10-06' ), { y: 2026, m: 10 } );
	assert.equal( monthOf( 'x' ), null );
} );

test( 'shiftMonth crosses year ends both ways', () => {
	assert.deepEqual( shiftMonth( { y: 2026, m: 12 }, 1 ), { y: 2027, m: 1 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 1 }, -1 ), { y: 2025, m: 12 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 10 }, 0 ), { y: 2026, m: 10 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 10 }, 12 ), { y: 2027, m: 10 } );
} );

test( 'monthInRange holds from the first month to the last month', () => {
	assert.equal( monthInRange( { y: 2026, m: 9 }, FIRST, LAST ), false );
	assert.equal( monthInRange( { y: 2026, m: 10 }, FIRST, LAST ), true );
	assert.equal( monthInRange( { y: 2027, m: 10 }, FIRST, LAST ), true );
	assert.equal( monthInRange( { y: 2027, m: 11 }, FIRST, LAST ), false );
} );

test( 'a day is selectable from the first to the last day, both included', () => {
	assert.equal( isSelectable( '2026-10-05', FIRST, LAST ), false );
	assert.equal( isSelectable( '2026-10-06', FIRST, LAST ), true );
	assert.equal( isSelectable( '2027-10-05', FIRST, LAST ), true );
	assert.equal( isSelectable( '2027-10-06', FIRST, LAST ), false );
} );

test( 'weekdays start on Monday and months have the right length', () => {
	assert.equal( weekdayIndex( 2026, 10, 5 ), 0 ); // Monday.
	assert.equal( weekdayIndex( 2026, 10, 6 ), 1 ); // Tuesday, the wireframe's example.
	assert.equal( weekdayIndex( 2026, 10, 11 ), 6 ); // Sunday.
	assert.equal( daysInMonth( 2026, 2 ), 28 );
	assert.equal( daysInMonth( 2028, 2 ), 29 );
	assert.equal( daysInMonth( 2026, 10 ), 31 );
} );

test( 'monthGrid pads to whole weeks and marks the bookable days', () => {
	const weeks = monthGrid( { y: 2026, m: 10 }, FIRST, LAST );

	assert.equal( weeks.length, 5 );
	weeks.forEach( ( week ) => assert.equal( week.length, 7 ) );
	assert.deepEqual( weeks[ 0 ].slice( 0, 3 ), [ null, null, null ] ); // 1 Oct 2026 is a Thursday.
	assert.equal( weeks[ 0 ][ 3 ].iso, '2026-10-01' );

	const days = weeks.flat().filter( Boolean );
	assert.equal( days.length, 31 );
	assert.equal( days.find( ( cell ) => cell.iso === '2026-10-05' ).selectable, false );
	assert.equal( days.find( ( cell ) => cell.iso === '2026-10-06' ).selectable, true );
	assert.equal( days.filter( ( cell ) => cell.selectable ).length, 26 );
} );

test( 'a month fully in the past has no selectable day', () => {
	const days = monthGrid( { y: 2026, m: 9 }, FIRST, LAST ).flat().filter( Boolean );

	assert.equal( days.filter( ( cell ) => cell.selectable ).length, 0 );
} );
