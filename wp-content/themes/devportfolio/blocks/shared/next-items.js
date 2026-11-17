/**
 * The Next list of a modal (DPC-010, DPV-015). Pure: runs under `node --test`.
 *
 * Ids after the current one in the grid's order, wrapping, restricted to the active category filter
 * (`slug`, '' for none). `sameCategoryFirst` puts the ids that share a category with the open item first.
 */

/**
 * @param {string[]} order     Ids in the grid's order.
 * @param {Object}   items     Payload items keyed by id; `categories` lists the category slugs of each.
 * @param {string}   currentId Id of the open item.
 * @param {string}   slug      Active category slug, or ''.
 * @param {number}   count     How many to return at most.
 * @param {{sameCategoryFirst?: boolean, minimum?: number}} [options] `minimum`: fewer matches than this
 *        return [] (default `count`, which is what Collaterals rely on).
 * @return {string[]}
 */
export function nextItems( order, items, currentId, slug, count, { sameCategoryFirst = false, minimum = count } = {} ) {
	const matches = ( id ) =>
		id !== currentId && ( slug === '' || ( items[ id ]?.categories ?? [] ).includes( slug ) );

	if ( order.filter( matches ).length < minimum ) {
		return [];
	}

	// From the open item's position, forward and wrapping; an unknown current id starts before the first.
	const start = order.indexOf( currentId );
	const wrapped = [];
	for ( let step = 1; step <= order.length; step++ ) {
		const id = order[ ( start + step + order.length ) % order.length ];
		if ( matches( id ) && ! wrapped.includes( id ) ) {
			wrapped.push( id );
		}
	}

	if ( ! sameCategoryFirst ) {
		return wrapped.slice( 0, count );
	}

	const mine = items[ currentId ]?.categories ?? [];
	const shares = ( id ) => ( items[ id ]?.categories ?? [] ).some( ( category ) => mine.includes( category ) );
	return [ ...wrapped.filter( shares ), ...wrapped.filter( ( id ) => ! shares( id ) ) ].slice( 0, count );
}
