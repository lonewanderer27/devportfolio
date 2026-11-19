import test from 'node:test';
import assert from 'node:assert/strict';
import { matchesCategory, visibleCount } from '../grid.js';

test( 'All matches everything', () => {
	assert.equal( matchesCategory( 'a b', '' ), true );
	assert.equal( matchesCategory( '', '' ), true );
} );

test( 'a slug matches only its items', () => {
	assert.equal( matchesCategory( 'short-form', 'short-form' ), true );
	assert.equal( matchesCategory( 'short-form video-edits', 'video-edits' ), true );
	assert.equal( matchesCategory( 'short-form', 'video-edits' ), false );
	assert.equal( matchesCategory( '', 'video-edits' ), false );
} );

test( 'visibleCount counts the items a category shows', () => {
	const attrs = [ 'a', 'a b', '', 'c' ];
	assert.equal( visibleCount( attrs, '' ), 4 );
	assert.equal( visibleCount( attrs, 'a' ), 2 );
	assert.equal( visibleCount( attrs, 'zzz' ), 0 );
	assert.equal( visibleCount( [], '' ), 0 );
} );
