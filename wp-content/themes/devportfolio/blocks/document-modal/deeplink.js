/**
 * Deeplink helpers for the document modal (DPD-032). Pure: they take and return
 * URL strings, so they run under `node --test`.
 *
 * The open document lives in the URL as `?document_slug=<slug>`, next to `?category=`.
 * Every other argument and the hash are kept.
 */

// `document` is reserved by WordPress as the document CPT query var.
export const PARAM = 'document_slug';

const BASE = 'https://example.invalid';

const parse = ( href ) => new URL( href, BASE );

/** Gives back `href` as it was given: absolute stays absolute, a path stays a path. */
const format = ( url, href ) => ( /^[a-z][a-z0-9+.-]*:/i.test( href ) ? url.toString() : url.pathname + url.search + url.hash );

/**
 * The slug in the URL, or '' when there is none.
 *
 * @param {string} href URL or path.
 * @return {string}
 */
export function slugFromUrl( href ) {
	try {
		return parse( href ).searchParams.get( PARAM ) || '';
	} catch ( e ) {
		return '';
	}
}

/**
 * The URL with `?document_slug=<slug>` set; an empty slug removes it.
 *
 * @param {string} href URL or path.
 * @param {string} slug Document slug.
 * @return {string}
 */
export function urlWithSlug( href, slug ) {
	const url = parse( href );
	if ( slug ) {
		url.searchParams.set( PARAM, slug );
	} else {
		url.searchParams.delete( PARAM );
	}
	return format( url, href );
}

/**
 * The URL without `?document_slug`.
 *
 * @param {string} href URL or path.
 * @return {string}
 */
export const urlWithoutSlug = ( href ) => urlWithSlug( href, '' );
