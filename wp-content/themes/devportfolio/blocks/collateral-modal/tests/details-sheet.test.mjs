import { test } from 'node:test';
import assert from 'node:assert/strict';
import { collateralDetailsModel } from '../details-sheet.js';

const STRINGS = { results: 'Results', madeWith: 'Made with' };

const full = {
	title: '  Spring flyer  ',
	category: 'Flyer',
	pill: 'A4 Landscape · 2 pp',
	description: 'The spring offer.',
	tools: [ 'Figma', '', ' InDesign ' ],
	stats: [ { label: 'Reach', value: '12k' }, { label: '', value: '' } ],
};

test( 'every section shows when the collateral has its data, and the title is trimmed', () => {
	const model = collateralDetailsModel( full, STRINGS );
	assert.equal( model.title, 'Spring flyer' );
	assert.deepEqual( model.tools, [ 'Figma', 'InDesign' ] );
	assert.deepEqual( model.stats, [ { label: 'Reach', value: '12k' } ] );
	assert.deepEqual( model.sections, { pills: true, description: true, tools: true, stats: true } );
	assert.equal( model.resultsLabel, 'Results' );
	assert.equal( model.madeLabel, 'Made with' );
} );

test( 'a collateral with no stats and no tools hides those sections, never shows an empty one', () => {
	const model = collateralDetailsModel( { ...full, tools: [], stats: [] }, STRINGS );
	assert.equal( model.sections.tools, false );
	assert.equal( model.sections.stats, false );
	assert.deepEqual( model.tools, [] );
	assert.deepEqual( model.stats, [] );
} );

test( 'missing fields are empty strings, never undefined', () => {
	const model = collateralDetailsModel( { title: 'Only a title' } );
	assert.equal( model.category, '' );
	assert.equal( model.pill, '' );
	assert.equal( model.description, '' );
	assert.deepEqual( model.tools, [] );
	assert.deepEqual( model.stats, [] );
	assert.deepEqual( model.sections, { pills: false, description: false, tools: false, stats: false } );
} );

test( 'the pills section shows for the category alone or the size alone', () => {
	assert.equal( collateralDetailsModel( { category: 'Flyer' } ).sections.pills, true );
	assert.equal( collateralDetailsModel( { pill: 'A4' } ).sections.pills, true );
	assert.equal( collateralDetailsModel( {} ).sections.pills, false );
} );

test( 'the section captions can be translated, and fall back to English', () => {
	const model = collateralDetailsModel( full, { results: 'Resultaten', madeWith: 'Gemaakt met' } );
	assert.equal( model.resultsLabel, 'Resultaten' );
	assert.equal( model.madeLabel, 'Gemaakt met' );
	assert.equal( collateralDetailsModel( full, null ).resultsLabel, 'Results' );
} );

test( 'a stat row with only a value still shows; a row with neither is dropped', () => {
	const model = collateralDetailsModel( { stats: [ { label: '', value: '3' }, null ] }, STRINGS );
	assert.deepEqual( model.stats, [ { label: '', value: '3' } ] );
} );
