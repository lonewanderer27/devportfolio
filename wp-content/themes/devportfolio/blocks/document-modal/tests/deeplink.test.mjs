import test from 'node:test';
import assert from 'node:assert/strict';
import { slugFromUrl, urlWithSlug, urlWithoutSlug } from '../deeplink.js';

test( 'reads the slug, or an empty string', () => {
	assert.equal( slugFromUrl( 'https://x.test/documents/?document_slug=q3-report' ), 'q3-report' );
	assert.equal( slugFromUrl( '/documents/?category=reports&document_slug=q3-report#top' ), 'q3-report' );
	assert.equal( slugFromUrl( 'https://x.test/documents/' ), '' );
	assert.equal( slugFromUrl( 'https://x.test/documents/?document_slug=' ), '' );
	assert.equal( slugFromUrl( '' ), '' );
} );

test( 'sets the slug and keeps the other arguments and the hash', () => {
	assert.equal(
		urlWithSlug( 'https://x.test/documents/?category=reports#shelf', 'q3-report' ),
		'https://x.test/documents/?category=reports&document_slug=q3-report#shelf'
	);
	assert.equal( urlWithSlug( '/documents/', 'a' ), '/documents/?document_slug=a' );
} );

test( 'replaces an existing slug', () => {
	assert.equal( urlWithSlug( '/documents/?document_slug=a', 'b' ), '/documents/?document_slug=b' );
} );

test( 'removes the slug and leaves nothing behind when it was the only argument', () => {
	assert.equal( urlWithoutSlug( 'https://x.test/documents/?document_slug=a' ), 'https://x.test/documents/' );
	assert.equal( urlWithoutSlug( '/documents/?category=r&document_slug=a#h' ), '/documents/?category=r#h' );
	assert.equal( urlWithoutSlug( '/documents/' ), '/documents/' );
} );

test( 'encodes an odd slug and reads it back', () => {
	const url = urlWithSlug( '/documents/', 'a b&c' );
	assert.equal( slugFromUrl( url ), 'a b&c' );
} );
