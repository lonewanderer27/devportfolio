import test from 'node:test';
import assert from 'node:assert/strict';
import { detailsModel } from '../details-sheet.js';

const units = { page: { many: 'Pages' }, slide: { many: 'Slides' } };
const doc = { title: ' Inbox Clean-up Report ', category: 'Admin & Email', description: 'Cleared 2,000+ emails.', tools: [ 'Gmail', 'Zapier' ], fileType: 'PDF', pages: 24, kind: 'pdf' };

test( 'the model carries the title, category, description and tools', () => {
	const model = detailsModel( { doc, units } );
	assert.equal( model.title, 'Inbox Clean-up Report', 'Surrounding space is trimmed.' );
	assert.equal( model.category, 'Admin & Email' );
	assert.equal( model.description, 'Cleared 2,000+ emails.' );
	assert.deepEqual( model.tools, [ 'Gmail', 'Zapier' ] );
} );

test( 'the info rows are Format then Pages, label first and value last', () => {
	const { rows } = detailsModel( { doc, units } );
	assert.deepEqual( rows, [
		{ key: 'format', label: 'Format', value: 'PDF' },
		{ key: 'pages', label: 'Pages', value: '24' },
	] );
} );

test( 'a presentation says Slides, a converted spreadsheet stays Pages', () => {
	assert.equal( detailsModel( { doc: { ...doc, kind: 'pptx', fileType: 'PPTX', pages: 12 }, units } ).rows[ 1 ].label, 'Slides' );
	assert.equal( detailsModel( { doc: { ...doc, kind: 'xlsx', fileType: 'XLSX', pages: 3 }, units } ).rows[ 1 ].label, 'Pages' );
} );

test( 'a row without a value is left out', () => {
	assert.deepEqual( detailsModel( { doc: { ...doc, fileType: '' }, units } ).rows.map( ( row ) => row.key ), [ 'pages' ] );
	assert.deepEqual( detailsModel( { doc: { ...doc, pages: 0 }, units } ).rows.map( ( row ) => row.key ), [ 'format' ] );
	assert.deepEqual( detailsModel( { doc: { ...doc, pages: 'x', fileType: '  ' }, units } ).rows, [] );
} );

test( 'missing category, description and tools are empty, never undefined', () => {
	const model = detailsModel( { doc: { title: 'A' }, units } );
	assert.equal( model.category, '' );
	assert.equal( model.description, '' );
	assert.deepEqual( model.tools, [] );
	assert.deepEqual( detailsModel( { doc: { tools: [ ' ', 5, 'Docs' ] }, units } ).tools, [ 'Docs' ], 'Blank and non-text tools are dropped.' );
	assert.equal( detailsModel( { doc: null } ).title, '' );
} );

test( 'the Format label can be translated', () => {
	assert.equal( detailsModel( { doc, units, strings: { formatRow: 'Formato' } } ).rows[ 0 ].label, 'Formato' );
} );
