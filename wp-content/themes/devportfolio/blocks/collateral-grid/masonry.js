/**
 * Masonry layout maths for the Brand Collaterals wall (DPC-014).
 *
 * Pure: no DOM. The grid script (view.js) feeds it the content width, the gap
 * and the known ratio of each visible tile, and writes the result to the page.
 *
 * Each tile goes into the shortest column (ties: the leftmost), so columns end
 * at different heights. A tile's height is its width divided by its ratio.
 */

export const MIN_COLUMNS = 2;

// Ratio used for a tile whose ratio is not a usable number: an A4 page (height / width = √2).
export const FALLBACK_RATIO = 1 / Math.SQRT2;

/**
 * Smallest tile width for a content width (the table in DPC-014 §1).
 *
 * @param {number} width Content-area width in px.
 * @return {number}
 */
export function minTileWidth( width ) {
	if ( width <= 420 ) {
		return 150;
	}
	if ( width <= 700 ) {
		return 160;
	}
	if ( width <= 1000 ) {
		return 190;
	}
	return 220;
}

/**
 * How many columns fit at the minimum tile width, never fewer than MIN_COLUMNS.
 *
 * @param {number} width Content-area width in px.
 * @param {number} gap   Gap between tiles in px.
 * @return {number}
 */
export function columnCount( width, gap ) {
	const min = minTileWidth( width );
	return Math.max( MIN_COLUMNS, Math.floor( ( width + gap ) / ( min + gap ) ) );
}

/**
 * @param {number} ratio Width / height.
 * @return {number} The ratio, or the fallback when it is not a finite number above 0.
 */
function usableRatio( ratio ) {
	return Number.isFinite( ratio ) && ratio > 0 ? ratio : FALLBACK_RATIO;
}

/**
 * Whether an item belongs in the view of `slug`. '' (All) matches every item.
 *
 * @param {string} categories The item's `data-collateral-categories` (space-separated slugs).
 * @param {string} slug       The pressed pill's slug, '' for All.
 * @return {boolean}
 */
export function matchesCategory( categories, slug ) {
	return '' === slug || ( categories || '' ).split( /\s+/ ).includes( slug );
}

/**
 * How many items a category shows, in the order given (used for the empty line).
 *
 * @param {string[]} categories One `data-collateral-categories` value per item.
 * @param {string}   slug       The pressed pill's slug, '' for All.
 * @return {number}
 */
export function visibleCount( categories, slug ) {
	return categories.filter( ( value ) => matchesCategory( value, slug ) ).length;
}

/**
 * Places tiles in columns.
 *
 * @param {number}   width  Content-area width in px. 0 or less gives an empty layout.
 * @param {number}   gap    Gap between tiles (and between rows in a column) in px.
 * @param {number[]} ratios Width / height of each tile, in order.
 * @return {{columns:number, tileWidth:number, height:number, positions:Array<{x:number,y:number,w:number,h:number}>}}
 *         One position per ratio, in the same order.
 */
export function layout( width, gap, ratios ) {
	if ( ! ( width > 0 ) ) {
		return {
			columns: MIN_COLUMNS,
			tileWidth: 0,
			height: 0,
			positions: ratios.map( () => ( { x: 0, y: 0, w: 0, h: 0 } ) ),
		};
	}

	const columns = columnCount( width, gap );
	const tileWidth = Math.max( 0, ( width - ( columns - 1 ) * gap ) / columns );
	// Bottom of each column, including the gap after its last tile.
	const bottoms = new Array( columns ).fill( 0 );
	const positions = ratios.map( ( ratio ) => {
		const h = tileWidth / usableRatio( ratio );

		let column = 0;
		for ( let c = 1; c < columns; c++ ) {
			if ( bottoms[ c ] < bottoms[ column ] ) {
				column = c;
			}
		}

		const position = { x: column * ( tileWidth + gap ), y: bottoms[ column ], w: tileWidth, h };
		bottoms[ column ] += h + gap;
		return position;
	} );

	const height = ratios.length ? Math.max( ...bottoms ) - gap : 0;

	return { columns, tileWidth, height, positions };
}
