/**
 * Deeplink helpers for the document modal (DPD-032). Pure: they take and return
 * URL strings, so they run under `node --test`.
 *
 * The open document lives in the URL as `?document_slug=<slug>`, next to `?category=`.
 * Every other argument and the hash are kept.
 */

const BASE = 'https://example.invalid';

const parse = ( href ) => new URL( href, BASE );

/** Gives back `href` as it was given: absolute stays absolute, a path stays a path. */
const format = ( url, href ) => ( /^[a-z][a-z0-9+.-]*:/i.test( href ) ? url.toString() : url.pathname + url.search + url.hash );

/**
 * The URL helpers for one query parameter. Documents use `document_slug` (`document` is reserved by
 * WordPress as the document CPT query var); Brand Collaterals use `collateral_slug` (DPC-020).
 *
 * @param {string} param Query parameter name.
 */
export function createDeeplink( param ) {
	const api = {
		/**
		 * The slug in the URL, or '' when there is none.
		 *
		 * @param {string} href URL or path.
		 * @return {string}
		 */
		slugFromUrl( href ) {
			try {
				return parse( href ).searchParams.get( param ) || '';
			} catch ( e ) {
				return '';
			}
		},

		/**
		 * The URL with the parameter set; an empty slug removes it.
		 *
		 * @param {string} href URL or path.
		 * @param {string} slug Slug.
		 * @return {string}
		 */
		urlWithSlug( href, slug ) {
			const url = parse( href );
			if ( slug ) {
				url.searchParams.set( param, slug );
			} else {
				url.searchParams.delete( param );
			}
			return format( url, href );
		},

		/**
		 * The URL without the parameter.
		 *
		 * @param {string} href URL or path.
		 * @return {string}
		 */
		urlWithoutSlug: ( href ) => api.urlWithSlug( href, '' ),
	};
	return api;
}

const documents = createDeeplink( 'document_slug' );

/** Documents: `?document_slug=<slug>` (DPD-032). */
export const PARAM = 'document_slug';
export const slugFromUrl = documents.slugFromUrl;
export const urlWithSlug = documents.urlWithSlug;
export const urlWithoutSlug = documents.urlWithoutSlug;

/**
 * History marker of the mobile Pages sheet (DPD-020). Opening the sheet adds one history entry with the
 * same URL and this flag in its state, so Back closes only the sheet; a second Back closes the viewer.
 */
export const SHEET_KEY = 'documentsSheet';

/** The history state with the sheet marker set (other keys are kept). */
export function withSheetState( state ) {
	return { ...( state && typeof state === 'object' ? state : {} ), [ SHEET_KEY ]: true };
}

/** Whether a history state carries the sheet marker. */
export function hasSheetState( state ) {
	return !! state && typeof state === 'object' && state[ SHEET_KEY ] === true;
}

/** The history state without the sheet marker (null when nothing else is left). */
export function withoutSheetState( state ) {
	if ( ! state || typeof state !== 'object' ) {
		return null;
	}
	const rest = { ...state };
	delete rest[ SHEET_KEY ];
	return Object.keys( rest ).length ? rest : null;
}
