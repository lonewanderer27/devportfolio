import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createLazyModal, styleReady, INTENT_EVENTS } from '../lazy-modal.js';
import { createHistoryEvents } from '../history-events.js';

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function fakeLink( rel, sheet = null ) {
	const listeners = {};
	return {
		rel,
		sheet,
		addEventListener( type, fn ) {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
		removeEventListener( type, fn ) {
			listeners[ type ] = ( listeners[ type ] || [] ).filter( ( x ) => x !== fn );
		},
		fire( type ) {
			( listeners[ type ] || [] ).slice().forEach( ( fn ) => fn() );
		},
	};
}

function trigger( attribute ) {
	const attrs = { [ attribute ]: '7' };
	const el = {
		attrs,
		getAttribute: ( name ) => attrs[ name ] ?? null,
		setAttribute: ( name, value ) => {
			attrs[ name ] = value;
		},
		removeAttribute: ( name ) => {
			delete attrs[ name ];
		},
		closest: ( selector ) => ( selector === `[${ attribute }]` ? el : null ),
	};
	return el;
}

function setup( { href = 'https://example.test/', root = true, idleTarget = false, saveData = false, readyState = 'complete' } = {} ) {
	const listeners = [];
	const winListeners = [];
	const idleCalls = [];
	const doc = {
		readyState,
		querySelector: ( selector ) => {
			if ( selector === '.root' ) {
				return root ? {} : null;
			}
			return selector === '[data-shelf]' && idleTarget ? {} : null;
		},
		getElementById: () => null,
		addEventListener: ( type, fn ) => listeners.push( { type, fn } ),
	};
	const win = {
		location: { href },
		navigator: { connection: { saveData } },
		setTimeout,
		requestIdleCallback: ( fn ) => idleCalls.push( fn ),
		addEventListener: ( type, fn ) => winListeners.push( { type, fn } ),
	};
	const popWin = { listeners: [], addEventListener( type, fn ) { this.listeners.push( fn ); } };
	const events = createHistoryEvents( popWin );
	let loads = 0;
	const lazy = createLazyModal(
		{ root: '.root', attribute: 'data-thing-id', param: 'thing_slug', styles: [ 'a-css' ], idle: '[data-shelf]', load: async () => { loads++; } },
		{ win, doc, events }
	);
	const fire = ( type, event ) => listeners.filter( ( l ) => l.type === type ).forEach( ( l ) => l.fn( event ) );
	const pop = () => {
		const event = { stopped: false, stopImmediatePropagation() { this.stopped = true; } };
		popWin.listeners.forEach( ( fn ) => fn( event ) );
		return event;
	};
	return { lazy, listeners, winListeners, idleCalls, fire, pop, win, loads: () => loads };
}

test( 'styleReady resolves at once without a link and for an applied stylesheet', async () => {
	await styleReady( null );
	await styleReady( fakeLink( 'stylesheet', {} ) );
} );

test( 'styleReady turns a preload link into a stylesheet and waits for it to apply', async () => {
	const link = fakeLink( 'preload' );
	let done = false;
	const ready = styleReady( link, () => {} ).then( () => {
		done = true;
	} );
	assert.equal( link.rel, 'stylesheet' );
	await tick();
	assert.equal( done, false );
	link.sheet = {};
	link.fire( 'load' );
	await ready;
	assert.equal( done, true );
} );

test( 'without the modal root nothing is bound', () => {
	const { lazy, listeners } = setup( { root: false } );
	assert.equal( lazy, null );
	assert.equal( listeners.length, 0 );
} );

test( 'a deeplink in the URL loads the view at once', async () => {
	const { loads } = setup( { href: 'https://example.test/?thing_slug=hello' } );
	await tick();
	assert.equal( loads(), 1 );
} );

test( 'without a deeplink the view loads on the first intent, once', async () => {
	const { fire, loads } = setup();
	await tick();
	assert.equal( loads(), 0 );
	fire( INTENT_EVENTS[ 0 ], { target: { closest: () => null } } );
	await tick();
	assert.equal( loads(), 0, 'not over a trigger' );
	const t = trigger( 'data-thing-id' );
	fire( 'pointerover', { target: t } );
	fire( 'focusin', { target: t } );
	await tick();
	assert.equal( loads(), 1 );
} );

test( 'a click before the view is loaded is kept and loads it; after that the boot leaves clicks alone', async () => {
	const { fire, loads, lazy } = setup();
	const t = trigger( 'data-thing-id' );
	const first = { target: t, prevented: false, preventDefault() { this.prevented = true; } };
	fire( 'click', first );
	assert.equal( first.prevented, true );
	assert.equal( t.attrs[ 'aria-busy' ], 'true' );
	await tick();
	assert.equal( loads(), 1 );
	assert.equal( lazy.isLoaded(), true );
	assert.equal( t.attrs[ 'aria-busy' ], undefined );
	const second = { target: t, prevented: false, preventDefault() { this.prevented = true; } };
	fire( 'click', second );
	assert.equal( second.prevented, false, 'the view handles it' );
} );

test( 'idle loading only on a page with the idle target and without data saver', async () => {
	assert.equal( setup( { idleTarget: true } ).idleCalls.length, 1 );
	assert.equal( setup( { idleTarget: false } ).idleCalls.length, 0 );
	assert.equal( setup( { idleTarget: true, saveData: true } ).idleCalls.length, 0 );
	const waiting = setup( { idleTarget: true, readyState: 'interactive' } );
	assert.equal( waiting.idleCalls.length, 0 );
	assert.equal( waiting.winListeners[ 0 ].type, 'load' );
} );

test( 'Back or Forward onto the deeplink before loading is stopped and loads the view', async () => {
	const s = setup();
	s.win.location.href = 'https://example.test/?thing_slug=hello';
	const event = s.pop();
	assert.equal( event.stopped, true );
	await tick();
	assert.equal( s.loads(), 1 );
	s.win.location.href = 'https://example.test/';
	assert.equal( s.pop().stopped, false, 'loaded: the view decides' );
} );
