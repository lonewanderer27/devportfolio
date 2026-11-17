/**
 * Run: ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/video-modal 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { videoDetailsModel } from '../details-sheet.js';

const full = {
	title: '  Launch film  ',
	category: ' Short Form ',
	shape: '9:16',
	description: ' A short cut. ',
	tools: [ ' Premiere ', '', 'Runway' ],
	stats: [ { label: 'Views', value: '1.2k' }, { label: '', value: '' }, { label: 'Likes', value: '' } ],
	postUrl: 'https://example.com/p',
};

test( 'a full item gives trimmed text', () => {
	const m = videoDetailsModel( full, {} );
	assert.equal( m.title, 'Launch film' );
	assert.equal( m.category, 'Short Form' );
	assert.equal( m.shape, '9:16' );
	assert.equal( m.description, 'A short cut.' );
} );

test( 'tools without empty names, stats without rows that have neither label nor value', () => {
	const m = videoDetailsModel( full, {} );
	assert.deepEqual( m.tools, [ 'Premiere', 'Runway' ] );
	assert.deepEqual( m.stats, [ { label: 'Views', value: '1.2k' }, { label: 'Likes', value: '' } ] );
} );

test( 'only http and https post links are kept', () => {
	assert.equal( videoDetailsModel( full, {} ).postUrl, 'https://example.com/p' );
	assert.equal( videoDetailsModel( { postUrl: 'javascript:alert(1)' }, {} ).postUrl, '' );
	assert.equal( videoDetailsModel( { postUrl: '' }, {} ).postUrl, '' );
} );

test( 'labels fall back to the English defaults', () => {
	const m = videoDetailsModel( full, {} );
	assert.equal( m.resultsLabel, 'Results' );
	assert.equal( m.madeLabel, 'Made with' );
	assert.equal( m.originalLabel, 'Open original post' );
} );

test( 'labels come from the strings when given', () => {
	const m = videoDetailsModel( full, { results: 'Resultaten', madeWith: 'Gemaakt met', openOriginal: 'Open' } );
	assert.equal( m.resultsLabel, 'Resultaten' );
	assert.equal( m.madeLabel, 'Gemaakt met' );
	assert.equal( m.originalLabel, 'Open' );
} );

test( 'the footer labels default to Copy link and Copied, and come from the strings', () => {
	assert.equal( videoDetailsModel( full, {} ).copyLabel, 'Copy link' );
	assert.equal( videoDetailsModel( full, {} ).copiedLabel, 'Copied' );
	assert.equal( videoDetailsModel( full, { copy: 'Kopieer', copied: 'Gekopieerd' } ).copyLabel, 'Kopieer' );
} );

test( 'never throws on missing or odd input', () => {
	const empty = videoDetailsModel( null, null );
	assert.equal( empty.title, '' );
	assert.deepEqual( empty.tools, [] );
	assert.deepEqual( empty.stats, [] );
	assert.equal( videoDetailsModel( undefined ).postUrl, '' );
} );
