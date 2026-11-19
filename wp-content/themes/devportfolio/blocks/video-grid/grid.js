/**
 * Pure helpers of the Videos grid (DPV-008).
 */

/**
 * Whether an item belongs to the chosen category.
 *
 * @param {string} attr  The item's `data-video-categories` (space-separated slugs).
 * @param {string} slug  The chosen slug, '' for All.
 * @return {boolean}
 */
export function matchesCategory( attr, slug ) {
	if ( '' === slug ) {
		return true;
	}
	return String( attr || '' ).split( /\s+/ ).includes( slug );
}

/**
 * How many items a category shows.
 *
 * @param {string[]} attrs Each item's `data-video-categories`.
 * @param {string}   slug  The chosen slug, '' for All.
 * @return {number}
 */
export function visibleCount( attrs, slug ) {
	return attrs.filter( ( attr ) => matchesCategory( attr, slug ) ).length;
}
