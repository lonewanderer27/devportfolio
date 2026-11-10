/**
 * The Next collaterals list (DPC-010, D7). Pure: runs under `node --test`.
 *
 * The two collaterals after the current one in the grid's order, wrapping from the last to the first,
 * restricted to the active category filter when there is one (`slug`, '' for none).
 */

/**
 * @param {string[]} order    Collateral ids in the grid's order.
 * @param {Object}   items    Payload items keyed by id; `categories` lists the slugs of each.
 * @param {string}   currentId Id of the open collateral.
 * @param {string}   slug     Active category slug, or ''.
 * @return {string[]} Up to two ids; [] when fewer than two other collaterals match.
 */
export function nextTwo( order, items, currentId, slug ) {
	const matches = ( id ) =>
		id !== currentId && ( slug === '' || ( items[ id ]?.categories ?? [] ).includes( slug ) );

	if ( order.filter( matches ).length < 2 ) {
		return [];
	}

	// From the open collateral's position, forward and wrapping; an unknown current id starts before the first.
	const start = order.indexOf( currentId );
	const picked = [];
	for ( let step = 1; step <= order.length && picked.length < 2; step++ ) {
		const id = order[ ( start + step + order.length ) % order.length ];
		if ( matches( id ) ) {
			picked.push( id );
		}
	}

	return picked;
}
