/**
 * The Copy link button of a modal sidebar (DPC-011, DPV-016, DPV-026). Pure apart from the injected
 * dependencies, so it runs under `node --test`.
 */

/**
 * The link to one item: origin and path plus the slug parameter only (no category, no hash).
 *
 * @param {{origin: string, pathname: string}} location window.location (or a stand-in).
 * @param {string} slug Item slug.
 * @param {string} param Query parameter that holds the slug, e.g. 'collateral_slug' or 'video_slug'.
 * @return {string}
 */
export function linkFor( location, slug, param ) {
	const url = new URL( location.origin + location.pathname );
	url.search = '';
	url.hash = '';
	url.searchParams.set( param, slug );
	return url.toString();
}

/**
 * Copies text: the Clipboard API first, then `execCommand('copy')` on a selected field. When both fail the
 * text is selected for the visitor to copy by hand, and the result is `failed`.
 *
 * @param {string} text Text to copy.
 * @param {{clipboard?: {writeText: Function}, execCopy: Function, select: Function}} deps
 *        clipboard: navigator.clipboard; execCopy(text) returns true when the copy worked;
 *        select() shows and selects the text for the visitor.
 * @return {Promise<'copied'|'failed'>}
 */
export async function copyText( text, { clipboard, execCopy, select } ) {
	try {
		if ( clipboard && typeof clipboard.writeText === 'function' ) {
			await clipboard.writeText( text );
			return 'copied';
		}
	} catch ( e ) {
		// Blocked or not allowed here: try the next way.
	}

	try {
		if ( execCopy( text ) ) {
			return 'copied';
		}
	} catch ( e ) {
		// Fall through to the selection.
	}

	select();
	return 'failed';
}
