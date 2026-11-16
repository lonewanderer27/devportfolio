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
