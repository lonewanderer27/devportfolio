import { test } from 'node:test';
import assert from 'node:assert/strict';
import { linkFor, copyText } from '../copy-link.js';

const here = { origin: 'https://site.test', pathname: '/videos/', search: '?category=a', hash: '#x' };

test( 'linkFor keeps origin and path, drops the query and the hash, encodes the slug', () => {
	assert.equal( linkFor( here, 'flyer one', 'collateral_slug' ), 'https://site.test/videos/?collateral_slug=flyer+one' );
	assert.equal( linkFor( here, 'a&b', 'collateral_slug' ), 'https://site.test/videos/?collateral_slug=a%26b' );
} );

test( 'linkFor writes the given parameter, e.g. video_slug', () => {
	assert.equal( linkFor( { origin: 'https://x.test', pathname: '/videos/' }, 'my-clip', 'video_slug' ), 'https://x.test/videos/?video_slug=my-clip' );
} );

test( 'copyText uses the Clipboard API when it works', async () => {
	let written = '';
	const result = await copyText( 'hello', {
		clipboard: { writeText: async ( text ) => { written = text; } },
		execCopy: () => assert.fail( 'not reached' ),
		select: () => assert.fail( 'not reached' ),
	} );
	assert.equal( result, 'copied' );
	assert.equal( written, 'hello' );
} );

test( 'copyText falls back to execCommand when the clipboard rejects', async () => {
	const result = await copyText( 'hello', {
		clipboard: { writeText: async () => { throw new Error( 'denied' ); } },
		execCopy: () => true,
		select: () => assert.fail( 'not reached' ),
	} );
	assert.equal( result, 'copied' );
} );

test( 'copyText reports failed and selects the text when both fail', async () => {
	let selected = false;
	const result = await copyText( 'hello', {
		clipboard: undefined,
		execCopy: () => false,
		select: () => { selected = true; },
	} );
	assert.equal( result, 'failed' );
	assert.equal( selected, true );
} );

test( 'a thrown execCommand is handled like a failure', async () => {
	let selected = false;
	const result = await copyText( 'hello', {
		clipboard: undefined,
		execCopy: () => { throw new Error( 'nope' ); },
		select: () => { selected = true; },
	} );
	assert.equal( result, 'failed' );
	assert.equal( selected, true );
} );
