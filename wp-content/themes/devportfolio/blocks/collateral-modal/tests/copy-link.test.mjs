import { test } from 'node:test';
import assert from 'node:assert/strict';
import { linkFor, copyText } from '../copy-link.js';

test( 'linkFor keeps origin and path, drops the category and the hash, encodes the slug', () => {
	const here = { origin: 'https://site.test', pathname: '/collaterals/', search: '?category=flyer', hash: '#x' };
	assert.equal( linkFor( here, 'flyer one' ), 'https://site.test/collaterals/?collateral_slug=flyer+one' );
	assert.equal( linkFor( here, 'a&b' ), 'https://site.test/collaterals/?collateral_slug=a%26b' );
} );

test( 'copyText uses the Clipboard API when it works', async () => {
	const written = [];
	const result = await copyText( 'x', {
		clipboard: { writeText: async ( t ) => written.push( t ) },
		execCopy: () => assert.fail( 'not needed' ),
		select: () => assert.fail( 'not needed' ),
	} );
	assert.equal( result, 'copied' );
	assert.deepEqual( written, [ 'x' ] );
} );

test( 'copyText falls back to execCommand when the clipboard rejects', async () => {
	const result = await copyText( 'x', {
		clipboard: { writeText: async () => Promise.reject( new Error( 'denied' ) ) },
		execCopy: () => true,
		select: () => assert.fail( 'not needed' ),
	} );
	assert.equal( result, 'copied' );
} );

test( 'copyText reports failed and selects the text when both fail', async () => {
	let selected = 0;
	const result = await copyText( 'x', {
		clipboard: undefined,
		execCopy: () => false,
		select: () => selected++,
	} );
	assert.equal( result, 'failed' );
	assert.equal( selected, 1 );
} );

test( 'a thrown execCommand is handled like a failure', async () => {
	let selected = 0;
	const result = await copyText( 'x', {
		clipboard: { writeText: async () => Promise.reject( new Error( 'x' ) ) },
		execCopy: () => {
			throw new Error( 'unsupported' );
		},
		select: () => selected++,
	} );
	assert.equal( result, 'failed' );
	assert.equal( selected, 1 );
} );
