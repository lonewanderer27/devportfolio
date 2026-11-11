/**
 * The viewer status line (DPC-025): pending, unavailable and loading.
 * Run with: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/shared 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createStatus } from '../status.js';

function fakeElement( props = {} ) {
	return {
		hidden: false,
		dataset: {},
		attributes: {},
		src: '',
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		removeAttribute( name ) {
			if ( name === 'src' ) {
				this.src = '';
			}
			delete this.attributes[ name ];
		},
		...props,
	};
}

function setup() {
	const parts = {
		'.documents-modal__state-text': fakeElement(),
		'.documents-modal__state-hint': fakeElement(),
		'.documents-modal__spinner': fakeElement(),
		'.documents-modal__state-image': fakeElement(),
	};
	const status = fakeElement( {
		querySelector: ( selector ) => parts[ selector ] || null,
	} );
	status.dataset.loading = 'Loading pages…';
	status.dataset.unavailable = 'This collateral can’t be previewed.';
	status.dataset.pending = 'Preview is being prepared. You can still download the file.';
	status.dataset.hint = 'Use Download to open it.';
	status.dataset.hintSheet = 'Download the file to open it in Excel.';
	status.dataset.loadingImage = 'https://example.test/collateral-loading.svg';
	const stage = fakeElement();
	const view = createStatus( { stage, status, prefix: 'documents-modal' } );
	return { view, status, stage, parts };
}

test( 'loading shows the spinner and no image', () => {
	const { view, status, stage, parts } = setup();
	view.set( 'loading' );

	assert.equal( status.hidden, false );
	assert.equal( status.dataset.kind, 'loading' );
	assert.equal( parts[ '.documents-modal__state-text' ].textContent, 'Loading pages…' );
	assert.equal( parts[ '.documents-modal__state-hint' ].textContent, '' );
	assert.equal( parts[ '.documents-modal__spinner' ].hidden, false );
	assert.equal( parts[ '.documents-modal__state-image' ].hidden, true );
	assert.equal( stage.attributes[ 'aria-busy' ], 'true' );
} );

test( 'unavailable clears the image and the busy state and adds the hint', () => {
	const { view, status, stage, parts } = setup();
	view.set( 'unavailable' );

	assert.equal( status.dataset.kind, 'unavailable' );
	assert.equal( parts[ '.documents-modal__state-text' ].textContent, 'This collateral can’t be previewed.' );
	assert.equal( parts[ '.documents-modal__state-hint' ].textContent, 'Use Download to open it.' );
	assert.equal( parts[ '.documents-modal__state-image' ].hidden, true );
	assert.equal( stage.attributes[ 'aria-busy' ], 'false' );
} );

test( 'pending shows its own text and the loading image, with no spinner', () => {
	const { view, status, stage, parts } = setup();
	view.setHintKey( 'hintSheet' );
	view.set( 'pending' );

	assert.equal( status.dataset.kind, 'pending' );
	assert.equal( parts[ '.documents-modal__state-text' ].textContent, 'Preview is being prepared. You can still download the file.' );
	assert.equal( parts[ '.documents-modal__state-hint' ].textContent, '' );
	assert.equal( parts[ '.documents-modal__spinner' ].hidden, true );
	assert.equal( parts[ '.documents-modal__state-image' ].hidden, false );
	assert.equal( parts[ '.documents-modal__state-image' ].src, 'https://example.test/collateral-loading.svg' );
	assert.equal( stage.attributes[ 'aria-busy' ], 'true' );
} );

test( 'hiding clears the text and the image', () => {
	const { view, status, stage, parts } = setup();
	view.set( 'pending' );
	view.set( null );

	assert.equal( status.hidden, true );
	assert.equal( status.dataset.kind, '' );
	assert.equal( parts[ '.documents-modal__state-text' ].textContent, '' );
	assert.equal( parts[ '.documents-modal__state-image' ].hidden, true );
	assert.equal( stage.attributes[ 'aria-busy' ], 'false' );
} );
