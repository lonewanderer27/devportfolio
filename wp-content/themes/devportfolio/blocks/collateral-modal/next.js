/**
 * The Next collaterals list (DPC-010, D7): the two after the current one. The logic is shared with the
 * Videos modal (DPV-026, shared/next-items.js).
 */
import { nextItems } from '../shared/next-items.js';

/**
 * @param {string[]} order     Collateral ids in the grid's order.
 * @param {Object}   items     Payload items keyed by id.
 * @param {string}   currentId Id of the open collateral.
 * @param {string}   slug      Active category slug, or ''.
 * @return {string[]} Up to two ids; [] when fewer than two other collaterals match.
 */
export function nextTwo( order, items, currentId, slug ) {
	return nextItems( order, items, currentId, slug, 2 );
}
