import test from 'node:test';
import assert from 'node:assert/strict';
import { ratioValue } from '../popout.js';

test( 'each ratio slug gives width / height', () => {
	assert.equal( ratioValue( '9x16' ), 9 / 16 );
	assert.equal( ratioValue( '1x1' ), 1 );
	assert.equal( ratioValue( '16x9' ), 16 / 9 );
	assert.equal( ratioValue( '4x3' ), 4 / 3 );
} );

test( 'a missing or unknown slug is the tile shape, 9:16', () => {
	assert.equal( ratioValue( '' ), 9 / 16 );
	assert.equal( ratioValue( 'banana' ), 9 / 16 );
	assert.equal( ratioValue( undefined ), 9 / 16 );
} );
