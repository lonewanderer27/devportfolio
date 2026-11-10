import test from 'node:test';
import assert from 'node:assert/strict';
import { createDeeplink } from '../../shared/deeplink.js';

const link = createDeeplink( 'collateral_slug' );

test( 'reads the collateral slug and ignores the document parameter', () => {
	assert.equal( link.slugFromUrl( 'https://x.test/collaterals/?collateral_slug=flyer-a' ), 'flyer-a' );
	assert.equal( link.slugFromUrl( '/collaterals/?category=flyer&collateral_slug=flyer-a#top' ), 'flyer-a' );
	assert.equal( link.slugFromUrl( '/collaterals/?document_slug=doc' ), '' );
	assert.equal( link.slugFromUrl( '/collaterals/' ), '' );
	assert.equal( link.slugFromUrl( '' ), '' );
} );

test( 'sets the slug and keeps the other arguments and the hash', () => {
	assert.equal(
		link.urlWithSlug( '/collaterals/?category=flyer#grid', 'logo-a' ),
		'/collaterals/?category=flyer&collateral_slug=logo-a#grid'
	);
	assert.equal(
		link.urlWithSlug( 'https://x.test/collaterals/', 'logo-a' ),
		'https://x.test/collaterals/?collateral_slug=logo-a'
	);
} );

test( 'an empty slug removes the parameter only', () => {
	assert.equal(
		link.urlWithoutSlug( '/collaterals/?category=flyer&collateral_slug=logo-a#grid' ),
		'/collaterals/?category=flyer#grid'
	);
	assert.equal( link.urlWithoutSlug( '/collaterals/' ), '/collaterals/' );
} );

test( 'two factories do not share a parameter', () => {
	const doc = createDeeplink( 'document_slug' );
	assert.equal( doc.slugFromUrl( '/x/?collateral_slug=a&document_slug=b' ), 'b' );
	assert.equal( link.slugFromUrl( '/x/?collateral_slug=a&document_slug=b' ), 'a' );
} );
