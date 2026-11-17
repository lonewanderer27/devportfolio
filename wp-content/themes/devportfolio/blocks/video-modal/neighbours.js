/**
 * The previous and next video inside the active category filter (DPV-020). Pure: runs under `node --test`.
 *
 * Same filter rule as shared/next-items.js (`slug` '' = all; an item matches when its `categories` has the
 * slug), except that the open video always counts, so a deeplink to a video outside the pressed filter still
 * has a position. No wrapping: the first video has no previous, the last has no next.
 *
 * @param {number[]} order     Ids in the grid's order.
 * @param {Object}   items     Payload items keyed by id.
 * @param {number}   currentId Id of the open video.
 * @param {string}   slug      Active category slug, or ''.
 * @return {{prev:?number,next:?number,index:number,count:number}}
 */
export function neighbours( order, items, currentId, slug ) {
	const inFilter = ( id ) => id === currentId || slug === '' || ( items[ id ]?.categories ?? [] ).includes( slug );
	const list = order.filter( inFilter );
	const index = list.indexOf( currentId );
	return {
		prev: index > 0 ? list[ index - 1 ] : null,
		next: index >= 0 && index < list.length - 1 ? list[ index + 1 ] : null,
		index,
		count: list.length,
	};
}
