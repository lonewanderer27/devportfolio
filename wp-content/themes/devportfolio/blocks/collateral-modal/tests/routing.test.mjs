import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { WIDE_QUERY, SIDEWAYS_QUERY } from '../../document-modal/routing.js';

test( 'the collateral stylesheet repeats the same two queries as routing.js (D3)', () => {
	const css = readFileSync( new URL( '../style.css', import.meta.url ), 'utf8' );
	assert.ok( css.includes( `@media (min-width: 701px) and (not (${ SIDEWAYS_QUERY }))` ), 'mobile dialog hidden only when wide and not sideways' );
	assert.ok( css.includes( `@media (max-width: 700px), ${ SIDEWAYS_QUERY }` ), 'desktop dialog hidden when narrow or sideways' );
	assert.equal( WIDE_QUERY, '(min-width: 701px)' );
} );
