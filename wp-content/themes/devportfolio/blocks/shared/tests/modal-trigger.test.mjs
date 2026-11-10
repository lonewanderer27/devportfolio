import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bindOpen } from '../modal-trigger.js';

// A tiny stand-in for the DOM: a listener list and an event whose target has closest().
function fakeScope() {
	const listeners = [];
	return {
		addEventListener( type, fn ) { listeners.push( { type, fn } ); },
		fire( type, target ) {
			const event = { target, prevented: false, preventDefault() { this.prevented = true; } };
			for ( const l of listeners.filter( ( x ) => x.type === type ) ) l.fn( event );
			return event;
		},
		count: () => listeners.length,
	};
}

function element( attrs, parent = null ) {
	return {
		attrs,
		parent,
		getAttribute( name ) { return this.attrs[ name ] ?? null; },
		closest( selector ) {
			const name = /^\[(.+)\]$/.exec( selector )[ 1 ];
			for ( let node = this; node; node = node.parent ) {
				if ( Object.prototype.hasOwnProperty.call( node.attrs, name ) ) return node;
			}
			return null;
		},
	};
}

test( 'one click listener is added for the scope, whatever the number of triggers', () => {
	const scope = fakeScope();
	bindOpen( scope, 'data-document-id', () => {} );
	assert.equal( scope.count(), 1 );
} );

test( 'a click on a trigger calls open with its attribute value and the trigger', () => {
	const scope = fakeScope();
	const calls = [];
	bindOpen( scope, 'data-document-id', ( id, trigger ) => calls.push( [ id, trigger ] ) );
	const button = element( { 'data-document-id': '42' } );
	const event = scope.fire( 'click', element( {}, button ) );
	assert.deepEqual( calls, [ [ '42', button ] ] );
	assert.equal( event.prevented, true );
} );

test( 'a click outside any trigger does nothing', () => {
	const scope = fakeScope();
	const calls = [];
	bindOpen( scope, 'data-collateral-id', ( id ) => calls.push( id ) );
	const event = scope.fire( 'click', element( { class: 'plain' } ) );
	assert.deepEqual( calls, [] );
	assert.equal( event.prevented, false );
} );

test( 'each attribute only answers its own triggers', () => {
	const scope = fakeScope();
	const calls = [];
	bindOpen( scope, 'data-collateral-id', ( id ) => calls.push( [ 'collateral', id ] ) );
	bindOpen( scope, 'data-document-id', ( id ) => calls.push( [ 'document', id ] ) );
	scope.fire( 'click', element( { 'data-collateral-id': '7' } ) );
	scope.fire( 'click', element( { 'data-document-id': '9' } ) );
	assert.deepEqual( calls, [ [ 'collateral', '7' ], [ 'document', '9' ] ] );
} );

test( 'a target without closest() is ignored rather than throwing', () => {
	const scope = fakeScope();
	const calls = [];
	bindOpen( scope, 'data-document-id', ( id ) => calls.push( id ) );
	scope.fire( 'click', { getAttribute() { return null; } } );
	assert.deepEqual( calls, [] );
} );
