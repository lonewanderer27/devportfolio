/**
 * Pure helpers that decide how the modal shows a document and what it calls its pieces
 * (DPD-026). No DOM access, so they can be tested with `node --test`.
 *
 * Modes, in the order they win:
 *  - `native`      the plugin's Native viewer setting is on for a .pptx or .xlsx: the bundled viewer draws
 *                  the original file in the visitor's browser (slides on a canvas with selectable text, or a
 *                  scrollable spreadsheet grid). The stored images stay its fallback (viewer.js shows them
 *                  when it cannot draw the file).
 *  - `images`      stored page images exist (a PDF, or converted slides): the open book.
 *  - `pdf`         no images, but the browser can render the PDF itself (pdf.js fallback).
 *  - `unavailable` nothing to show: "can't be previewed", Download still works.
 */

const PRESENTATIONS = [ 'pptx', 'ppt', 'odp' ];
const SPREADSHEETS = [ 'xlsx', 'xls', 'ods' ];

/** Whether a payload entry is a spreadsheet (DPD-027). Its pieces are still called pages. */
export function isSpreadsheet( doc ) {
	return !! doc && SPREADSHEETS.includes( doc.kind );
}

/** The mode for a payload entry. */
export function viewerMode( doc ) {
	if ( ! doc || typeof doc !== 'object' ) {
		return 'unavailable';
	}
	if ( typeof doc.nativeUrl === 'string' && doc.nativeUrl !== '' ) {
		return 'native';
	}
	if ( Array.isArray( doc.images ) && doc.images.length > 0 ) {
		return 'images';
	}
	if ( typeof doc.viewerUrl === 'string' && doc.viewerUrl !== '' ) {
		return 'pdf';
	}
	return 'unavailable';
}

/** `slide` for presentations, `page` for everything else. */
export function unitOf( doc ) {
	return doc && PRESENTATIONS.includes( doc.kind ) ? 'slide' : 'page';
}

const FALLBACK = {
	page: { one: 'Page', many: 'Pages', prev: 'Previous page', next: 'Next page', nav: 'Pages' },
	slide: { one: 'Slide', many: 'Slides', prev: 'Previous slide', next: 'Next slide', nav: 'Slides' },
};

/** The wording for a unit, from the server's `data-units` (translated) or the English defaults. */
export function wording( unit, strings ) {
	const key = unit === 'slide' ? 'slide' : 'page';
	const given = strings && typeof strings === 'object' && strings[ key ] && typeof strings[ key ] === 'object' ? strings[ key ] : {};
	const result = {};
	Object.keys( FALLBACK[ key ] ).forEach( ( name ) => {
		result[ name ] = typeof given[ name ] === 'string' && given[ name ] !== '' ? given[ name ] : FALLBACK[ key ][ name ];
	} );
	return result;
}

/** "Page 3 of 12" / "Slide 3 of 12" (the label of one page element). */
export function pieceLabel( words, index, count ) {
	return `${ words.one } ${ index } of ${ count }`;
}

/** "Page 5 of 24", "Pages 11–12 of 24", "Slide 5 of 12" (the progress bar's accessible value). */
export function positionText( words, first, last, count ) {
	return first === last || last === undefined
		? `${ words.one } ${ first } of ${ count }`
		: `${ words.many } ${ first }–${ last } of ${ count }`;
}

/**
 * Whether this browser can run the bundled Office viewers (PPTX and XLSX): WebAssembly (the parser),
 * workers (it parses off the main thread) and a 2D canvas. `env` is read once by the caller so this
 * stays testable with plain objects.
 */
export function canDrawOffice( env ) {
	return !! env && env.webassembly === true && env.worker === true && env.canvas2d === true;
}

/** The name the presentation viewer used before spreadsheets joined it. */
export const canDrawPresentations = canDrawOffice;
