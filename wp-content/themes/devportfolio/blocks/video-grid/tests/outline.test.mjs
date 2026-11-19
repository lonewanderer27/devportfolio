import test from 'node:test';
import assert from 'node:assert/strict';
import { outlineBox, OUTLINE_PAD, NO_OUTLINE } from '../outline.js';

test( 'the box wraps the tile with the pad on every side', () => {
	const tile = { offsetLeft: 100, offsetTop: 40, offsetWidth: 120, offsetHeight: 213 };
	assert.deepEqual( outlineBox( tile ), { x: 97, y: 37, w: 126, h: 219 } );
	assert.deepEqual( outlineBox( tile, 0 ), { x: 100, y: 40, w: 120, h: 213 } );
	assert.equal( OUTLINE_PAD, 3 );
} );

test( 'the empty outline is inert', () => {
	assert.equal( NO_OUTLINE.show( {} ), null );
	assert.equal( NO_OUTLINE.busy(), false );
	assert.doesNotThrow( () => { NO_OUTLINE.hide(); NO_OUTLINE.refresh(); } );
} );
