/**
 * Masonry maths (DPC-014). Run with:
 *   ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/collateral-grid 'node --test tests/*.test.mjs'
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { FALLBACK_RATIO, MIN_COLUMNS, columnCount, layout, minTileWidth } from '../masonry.js';

// The padding and gap the CSS gives each viewport (phones: 700px and below).
function contentWidth( viewport ) {
	const phone = viewport <= 700;
	const padding = phone ? 16 : 36;
	const gap = phone ? 8 : 12;
	return { width: Math.min( viewport - 2 * padding, 1328 ), gap };
}

// Seeded PRNG (mulberry32) so the random cases are the same on every run.
function random( seed ) {
	let s = seed >>> 0;
	return () => {
		s = ( s + 0x6d2b79f5 ) >>> 0;
		let t = s;
		t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
		t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
		return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
	};
}

function randomRatios( count, seed ) {
	const next = random( seed );
	return Array.from( { length: count }, () => 0.4 + next() * 1.6 );
}

function overlaps( a, b ) {
	return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
}

test( 'the layout table in DPC-014 §1', async ( t ) => {
	const rows = [
		{ viewport: 320, columns: 2, tile: 140 },
		{ viewport: 390, columns: 2, tile: 175 },
		{ viewport: 600, columns: 3, tile: 184 },
		{ viewport: 768, columns: 4, tile: 165 },
		{ viewport: 1024, columns: 4, tile: 229 },
		{ viewport: 1440, columns: 5, tile: 256 },
		{ viewport: 1920, columns: 5, tile: 256 },
	];
	for ( const row of rows ) {
		await t.test( `viewport ${ row.viewport }`, () => {
			const { width, gap } = contentWidth( row.viewport );
			const result = layout( width, gap, [ 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 ] );
			assert.equal( result.columns, row.columns );
			assert.ok( Math.abs( result.tileWidth - row.tile ) <= 0.5, `tile ${ result.tileWidth } vs ${ row.tile }` );
		} );
	}
} );

test( 'minimum tile width steps at 420, 700 and 1000', () => {
	assert.equal( minTileWidth( 420 ), 150 );
	assert.equal( minTileWidth( 421 ), 160 );
	assert.equal( minTileWidth( 700 ), 160 );
	assert.equal( minTileWidth( 701 ), 190 );
	assert.equal( minTileWidth( 1000 ), 190 );
	assert.equal( minTileWidth( 1001 ), 220 );
} );

test( 'there are never fewer than two columns', () => {
	assert.equal( columnCount( 200, 8 ), MIN_COLUMNS );
	const result = layout( 200, 8, [ 1, 1 ] );
	assert.equal( result.columns, 2 );
	assert.ok( result.positions.every( ( p ) => p.h > 0 ) );
} );

test( 'a tile goes into the shortest column, and ties go left', () => {
	// Two columns of 100px tiles, gap 0: [0.5] is 200px tall, the next two are 100px.
	const result = layout( 200, 0, [ 0.5, 1, 1, 1 ] );
	assert.equal( result.columns, 2 );
	assert.deepEqual( result.positions[ 0 ], { x: 0, y: 0, w: 100, h: 200 } );
	assert.deepEqual( result.positions[ 1 ], { x: 100, y: 0, w: 100, h: 100 } );
	// Column 1 (100px tall) is shorter than column 0 (200px): the third tile goes under the second.
	assert.deepEqual( result.positions[ 2 ], { x: 100, y: 100, w: 100, h: 100 } );
	// Columns are now 200 and 200: a tie, so the fourth tile goes to the leftmost.
	assert.deepEqual( result.positions[ 3 ], { x: 0, y: 200, w: 100, h: 100 } );
} );

test( 'tile height is width divided by ratio', () => {
	const result = layout( 300, 0, [ 2, 0.5 ] );
	assert.equal( result.positions[ 0 ].h, result.tileWidth / 2 );
	assert.equal( result.positions[ 1 ].h, result.tileWidth / 0.5 );
} );

test( 'no two tiles overlap, tiles stay inside the width, columns start at 0 and keep the gap', () => {
	for ( const width of [ 288, 358, 568, 952, 1328 ] ) {
		const gap = width > 700 ? 12 : 8;
		const ratios = randomRatios( 200, width );
		const result = layout( width, gap, ratios );
		const tiles = result.positions;

		for ( let i = 0; i < tiles.length; i++ ) {
			assert.ok( tiles[ i ].x >= 0 && tiles[ i ].x + tiles[ i ].w <= width + 1e-9, `tile ${ i } outside at ${ width }` );
			for ( let j = i + 1; j < tiles.length; j++ ) {
				assert.equal( overlaps( tiles[ i ], tiles[ j ] ), false, `tiles ${ i } and ${ j } overlap at ${ width }` );
			}
		}

		const columns = new Map();
		for ( const tile of tiles ) {
			if ( ! columns.has( tile.x ) ) {
				columns.set( tile.x, [] );
			}
			columns.get( tile.x ).push( tile );
		}
		assert.equal( columns.size, result.columns );
		for ( const column of columns.values() ) {
			column.sort( ( a, b ) => a.y - b.y );
			assert.equal( column[ 0 ].y, 0, 'a column starts at y = 0' );
			for ( let k = 1; k < column.length; k++ ) {
				const prev = column[ k - 1 ];
				assert.ok( Math.abs( column[ k ].y - ( prev.y + prev.h ) - gap ) < 1e-6, 'gap between tiles in a column' );
			}
		}
	}
} );

test( 'positions follow the order of the ratios', () => {
	const ratios = [ 0.7, 1.4, 0.7 ];
	const result = layout( 400, 8, ratios );
	ratios.forEach( ( ratio, i ) => {
		assert.ok( Math.abs( result.positions[ i ].h - result.positions[ i ].w / ratio ) < 1e-9 );
	} );
} );

test( 'a bad ratio uses the A4 fallback, and width 0 does not throw', () => {
	for ( const bad of [ 0, Number.NaN, -1, Infinity ] ) {
		const result = layout( 300, 0, [ bad ] );
		assert.ok( Math.abs( result.positions[ 0 ].h - result.tileWidth / FALLBACK_RATIO ) < 1e-9 );
	}

	const empty = layout( 0, 8, [ 1, 0.5 ] );
	assert.equal( empty.columns, MIN_COLUMNS );
	assert.equal( empty.tileWidth, 0 );
	assert.equal( empty.height, 0 );
	assert.equal( empty.positions.length, 2 );
} );

test( 'no tiles means height 0', () => {
	assert.equal( layout( 500, 8, [] ).height, 0 );
} );

test( 'height is the tallest column without its last gap', () => {
	const result = layout( 200, 10, [ 1, 1, 1 ] );
	// Tile width 95; tile 0 and 1 side by side, tile 2 in column 0 under tile 0.
	assert.equal( result.tileWidth, 95 );
	assert.equal( result.height, 95 * 2 + 10 );
} );
