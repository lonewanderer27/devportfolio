import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHistoryEvents } from '../history-events.js';

// A window stand-in that records its listeners and can fire a popstate at them in order.
function fakeWindow() {
	const listeners = [];
	return {
		listeners,
		addEventListener( type, fn, capture ) {
			listeners.push( { type, fn, capture } );
		},
		fire() {
			const event = { native: 0, stopImmediatePropagation() { this.native++; } };
			for ( const l of listeners ) {
				l.fn( event );
			}
			return event;
		},
	};
}

test( 'one capture-phase window listener, however many handlers', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	events.install();
	events.addEventListener( 'popstate', () => {} );
	events.addEventListener( 'popstate', () => {}, true );
	assert.equal( win.listeners.length, 1 );
	assert.equal( win.listeners[ 0 ].type, 'popstate' );
	assert.equal( win.listeners[ 0 ].capture, true );
	assert.equal( events.count(), 2 );
} );

test( 'handlers run in the order they were added', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	const order = [];
	events.addEventListener( 'popstate', () => order.push( 'sheet' ) );
	events.addEventListener( 'popstate', () => order.push( 'modal' ) );
	win.fire();
	assert.deepEqual( order, [ 'sheet', 'modal' ] );
} );

test( 'stopImmediatePropagation stops the later handlers and still stops the native event', () => {
	const win = fakeWindow();
	const events = createHistoryEvents( win );
	const order = [];
	events.addEventListener( 'popstate', ( event ) => {
		order.push( 'first' );
		event.stopImmediatePropagation();
	} );
	events.addEventListener( 'popstate', () => order.push( 'second' ) );
	const event = win.fire();
	assert.deepEqual( order, [ 'first' ] );
	assert.equal( event.native, 1 );
} );

test( 'only popstate is carried, and no target means no window listener', () => {
	const events = createHistoryEvents( null );
	assert.throws( () => events.addEventListener( 'click', () => {} ) );
	events.addEventListener( 'popstate', () => {} );
	assert.equal( events.count(), 1 );
} );
