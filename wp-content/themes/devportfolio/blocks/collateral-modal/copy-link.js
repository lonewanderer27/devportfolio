/**
 * The Copy link button of the collateral sidebar (DPC-011, D10). The logic is shared with the Videos modal
 * (DPV-026, shared/copy-link.js); this file only fixes the parameter.
 */
import { linkFor as sharedLinkFor, copyText } from '../shared/copy-link.js';

export const SLUG_PARAM = 'collateral_slug';

/** The link to one collateral: origin and path plus `collateral_slug` only. */
export function linkFor( location, slug ) {
	return sharedLinkFor( location, slug, SLUG_PARAM );
}

export { copyText };
