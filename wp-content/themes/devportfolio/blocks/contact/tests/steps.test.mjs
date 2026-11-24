import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validateStep, crumbStates, nextStep, prevStep, summaryRows, LAST_STEP } from '../steps.js';

const strings = {
	slotRequired: 'Pick a time.',
	nameRequired: 'Name please.',
	emailRequired: 'Email please.',
	emailInvalid: 'Bad email.',
	needRequired: 'Choose one.',
	summaryTime: 'Time',
	summaryName: 'Name',
	summaryEmail: 'Email',
	summaryHelp: 'Help with',
	summaryMessage: 'Message',
	noMessage: 'No message',
};

const data = { date: '2026-10-06', time: '10:30', name: ' Jane ', email: ' jane@example.com ', services: [ '12', 'other' ], message: ' Hi ' };

test( 'step 1 needs a day and a time', () => {
	assert.deepEqual( validateStep( 1, data, strings ), {} );
	assert.deepEqual( validateStep( 1, { ...data, time: '' }, strings ), { slot: 'Pick a time.' } );
	assert.deepEqual( validateStep( 1, { ...data, date: '' }, strings ), { slot: 'Pick a time.' } );
} );

test( 'step 2 needs a name and a plausible email', () => {
	assert.deepEqual( validateStep( 2, data, strings ), {} );
	assert.deepEqual( validateStep( 2, { ...data, name: '   ', email: '' }, strings ), { name: 'Name please.', email: 'Email please.' } );
	assert.deepEqual( validateStep( 2, { ...data, email: 'jane@' }, strings ), { email: 'Bad email.' } );
	assert.deepEqual( validateStep( 2, { ...data, email: 'a b@c.d' }, strings ), { email: 'Bad email.' } );
} );

test( 'step 3 needs at least one service and the message is optional', () => {
	assert.deepEqual( validateStep( 3, data, strings ), {} );
	assert.deepEqual( validateStep( 3, { ...data, services: [] }, strings ), { services: 'Choose one.' } );
	assert.deepEqual( validateStep( 3, { ...data, message: '' }, strings ), {} );
} );

test( 'step 4 has nothing to validate', () => {
	assert.deepEqual( validateStep( 4, { ...data, services: [] }, strings ), {} );
} );

test( 'crumbs: done before, current, todo after; all done once sent', () => {
	assert.deepEqual( crumbStates( 1, false ), [ 'current', 'todo', 'todo', 'todo' ] );
	assert.deepEqual( crumbStates( 3, false ), [ 'done', 'done', 'current', 'todo' ] );
	assert.deepEqual( crumbStates( 4, true ), [ 'done', 'done', 'done', 'done' ] );
} );

test( 'next and previous step stay inside 1 to 4', () => {
	assert.equal( LAST_STEP, 4 );
	assert.equal( nextStep( 1, 0 ), 2 );
	assert.equal( nextStep( 4, 0 ), 4 );
	assert.equal( prevStep( 3 ), 2 );
	assert.equal( prevStep( 1 ), 1 );
} );

test( 'while editing from the summary Next returns straight to step 4', () => {
	assert.equal( nextStep( 2, 4 ), 4 );
	assert.equal( nextStep( 1, 4 ), 4 );
	assert.equal( nextStep( 4, 4 ), 4 );
} );

test( 'summaryRows lists five rows, trims, orders the help by the service list and names the edit step', () => {
	const services = [ { id: '12', title: 'Social Media Marketing' }, { id: '13', title: 'Digital Marketing' }, { id: 'other', title: 'Something else' } ];
	const rows = summaryRows( { ...data, services: [ 'other', '12' ] }, services, strings, 'Tue 6 Oct · 10:30 Sydney time' );

	assert.deepEqual( rows.map( ( row ) => row.key ), [ 'time', 'name', 'email', 'help', 'message' ] );
	assert.deepEqual( rows.map( ( row ) => row.step ), [ 1, 2, 2, 3, 3 ] );
	assert.equal( rows[ 0 ].value, 'Tue 6 Oct · 10:30 Sydney time' );
	assert.equal( rows[ 1 ].value, 'Jane' );
	assert.equal( rows[ 2 ].value, 'jane@example.com' );
	assert.equal( rows[ 3 ].value, 'Social Media Marketing, Something else' );
	assert.equal( rows[ 4 ].value, 'Hi' );
} );

test( 'an empty message shows the "no message" wording', () => {
	const rows = summaryRows( { ...data, message: '  ' }, [], strings, 'Tue 6 Oct · 10:30 Sydney time' );

	assert.equal( rows[ 4 ].value, 'No message' );
} );
