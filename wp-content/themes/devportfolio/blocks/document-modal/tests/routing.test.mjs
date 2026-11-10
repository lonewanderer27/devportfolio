import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRouting, WIDE_QUERY, SIDEWAYS_QUERY } from '../../shared/routing.js';

// A fake matchMedia whose answers are set per query.
const fake = ( answers ) => ( query ) => ( {
	get matches() {
		return Boolean( answers[ query ] );
	},
	addEventListener() {},
} );

const cases = [
	// [wide, sideways, expected dialog]
	[ false, false, 'mobile' ], // a phone, portrait
	[ true, false, 'desktop' ], // desktop, tablet
	[ true, true, 'mobile' ], // a phone held sideways (wider than 700px)
	[ false, true, 'mobile' ], // a narrow sideways phone
];

test( 'exactly one dialog is right for every screen', () => {
	cases.forEach( ( [ wide, sideways, expected ] ) => {
		const routing = createRouting( fake( { [ WIDE_QUERY ]: wide, [ SIDEWAYS_QUERY ]: sideways } ) );
		assert.equal( routing.isDesktop() ? 'desktop' : 'mobile', expected, `${ wide }/${ sideways }` );
		assert.notEqual( routing.isDesktop(), routing.isMobile(), 'never both, never neither' );
	} );
} );

test( 'the stylesheet repeats the same queries, so CSS and script cannot disagree', () => {
	const css = readFileSync( new URL( '../style.css', import.meta.url ), 'utf8' );
	assert.ok( css.includes( `@media (min-width: 701px) and (not (${ SIDEWAYS_QUERY }))` ), 'mobile dialog hidden only when wide and not sideways' );
	assert.ok( css.includes( `@media (max-width: 700px), ${ SIDEWAYS_QUERY }` ), 'desktop dialog hidden when narrow or sideways' );
	assert.equal( WIDE_QUERY, '(min-width: 701px)' );
} );
