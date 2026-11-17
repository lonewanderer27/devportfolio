/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 * No DOM: the dialog, the player and `document` are small fakes.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { CONTROLS, DESKTOP_CONTROLS, mountControls } from '../controls/index.js';
import { createPopovers } from '../controls/popover.js';

const fakeEl = () => ( { textContent: '', hidden: false, setAttribute() {}, addEventListener() {}, style: { setProperty() {} }, dataset: {} } );

const player = {
	on: () => () => {},
	video: () => null,
	root: {},
	toggle() {},
	wake() {},
	userPlay() {},
};

globalThis.document ??= {};
globalThis.document.addEventListener = () => {};
globalThis.document.fullscreenElement = null;

test( 'mountControls mounts nothing when the markup has none of the elements', () => {
	const dialog = { querySelector: () => null, querySelectorAll: () => [], addEventListener() {} };
	assert.deepEqual( mountControls( { dialog, prefix: 'x', player, keys: Object.keys( CONTROLS ) } ), [] );
} );

test( 'an unknown control key is ignored', () => {
	const dialog = { querySelector: () => null, querySelectorAll: () => [], addEventListener() {} };
	assert.deepEqual( mountControls( { dialog, prefix: 'x', player, keys: [ 'nope' ] } ), [] );
} );

test( 'the registry holds the nine control keys in order', () => {
	assert.deepEqual( Object.keys( CONTROLS ), [ 'bigplay', 'playpause', 'replay', 'progress', 'time', 'mute', 'volume', 'speed', 'fullscreen', 'fit' ] );
} );

test( 'the desktop set is a subset of the registry and has no mute button', () => {
	DESKTOP_CONTROLS.forEach( ( key ) => assert.ok( Object.prototype.hasOwnProperty.call( CONTROLS, key ) ) );
	assert.equal( DESKTOP_CONTROLS.includes( 'mute' ), false );
} );

test( 'a control mounts only when its element is in the markup', () => {
	const dialog = {
		querySelector: ( selector ) => ( selector === '.x__time' ? fakeEl() : null ),
		querySelectorAll: () => [],
		addEventListener() {},
	};
	const mounted = mountControls( { dialog, prefix: 'x', player, keys: [ 'time', 'mute' ] } );
	assert.equal( mounted.length, 1 );
} );

test( 'opening one popover closes the other', () => {
	const dialog = { addEventListener() {} };
	const popovers = createPopovers( dialog );
	const make = () => ( {
		pop: { hidden: true, contains: () => false },
		trigger: { setAttribute() {}, contains: () => false },
	} );
	const volume = make();
	const rate = make();
	popovers.register( volume );
	popovers.register( rate );

	popovers.toggle( volume );
	assert.equal( volume.pop.hidden, false );

	popovers.toggle( rate );
	assert.equal( volume.pop.hidden, true );
	assert.equal( rate.pop.hidden, false );
} );
