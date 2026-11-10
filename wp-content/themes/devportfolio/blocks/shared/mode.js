/**
 * Pure helpers that decide how the modal shows a document and what it calls its pieces
 * (DPD-026). No DOM access, so they can be tested with `node --test`.
 *
 * Modes, in the order they win:
 *  - `native`      the plugin's Native viewer setting is on for a .pptx, .xlsx or .docx: the bundled viewer
 *                  draws the original file in the visitor's browser (slides on a canvas with selectable text,
 *                  a scrollable spreadsheet grid, or a scrolling page flow with selectable text). The stored images stay its fallback (viewer.js shows them
 *                  when it cannot draw the file).
 *  - `images`      stored page images exist (a PDF, or converted slides): the open book.
 *  - `pdf`         no images, but the browser can render the PDF itself (pdf.js fallback).
 *  - `unavailable` nothing to show: "can't be previewed", Download still works.
 */

const PRESENTATIONS = [ 'pptx', 'ppt', 'odp' ];
const SPREADSHEETS = [ 'xlsx', 'xls', 'ods' ];
const WORD = [ 'docx', 'doc', 'odt' ];

/** Whether a payload entry is a spreadsheet (DPD-027). Its pieces are still called pages. */
export function isSpreadsheet( doc ) {
	return !! doc && SPREADSHEETS.includes( doc.kind );
}

/** Whether a payload entry is a word-processing document. */
export function isWord( doc ) {
	return !! doc && WORD.includes( doc.kind );
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
 * Whether this browser can run the bundled Office viewers (PPTX, XLSX and DOCX): WebAssembly (the parser),
 * workers (it parses off the main thread) and a 2D canvas. `env` is read once by the caller so this
 * stays testable with plain objects.
 */
export function canDrawOffice( env ) {
	return !! env && env.webassembly === true && env.worker === true && env.canvas2d === true;
}

/**
 * Whether the mobile body draws this document: every kind the modal knows. PDFs (DPD-021), presentations
 * (DPD-029), workbooks (DPD-030) and word documents (DPD-031) are drawn from their stored pages, or from the
 * original file when a native viewer applies. Only an unknown kind says "can't be previewed". This is the
 * one gate to widen.
 */
export function canShowOnMobile( doc ) {
	// `image` is a collateral's raster file: one stored page, drawn like the PDF pages (DPC-023).
	return !! doc && ( doc.kind === 'pdf' || doc.kind === 'image' || unitOf( doc ) === 'slide' || isSpreadsheet( doc ) || isWord( doc ) );
}

/**
 * Whether the mobile body may try a native viewer: a .pptx (slides on a canvas), a .xlsx (the library's
 * grid) or a .docx (its scrolling page flow) that carries a `nativeUrl`. Same limits as desktop (the
 * plugin's size ceiling and the timeout), no mobile-only cap. `.ppt`, `.odp`, `.xls`, `.ods`, `.doc` and
 * `.odt` never qualify: the library cannot read them.
 */
export function nativeAllowedOnMobile( doc ) {
	return !! doc && ( doc.kind === 'pptx' || doc.kind === 'xlsx' || doc.kind === 'docx' ) && typeof doc.nativeUrl === 'string' && doc.nativeUrl !== '';
}

/**
 * Whether the native viewer draws this document as a workbook grid: the grid has sheet tabs at the bottom
 * the round buttons must stay clear of.
 */
export function isNativeGrid( doc ) {
	return !! doc && doc.kind === 'xlsx' && nativeAllowedOnMobile( doc ) && viewerMode( doc ) === 'native';
}

/**
 * Whether the native viewer brings its own scrolling, zoom and selection (a workbook grid or a .docx
 * scroller): for the modal it is one piece (no page turner, no footer) and the page gestures are not bound
 * to it.
 */
export function isNativeScroll( doc ) {
	return !! doc && ( doc.kind === 'xlsx' || doc.kind === 'docx' ) && nativeAllowedOnMobile( doc ) && viewerMode( doc ) === 'native';
}

/**
 * Which `data-hint*` string of the status element explains an unavailable document: presentations,
 * workbooks and word documents each name their own program; everything else gets the generic one.
 */
export function hintKeyFor( doc ) {
	if ( unitOf( doc ) === 'slide' ) {
		return 'hintSlide';
	}
	if ( isSpreadsheet( doc ) ) {
		return 'hintSheet';
	}
	return isWord( doc ) ? 'hintDoc' : 'hint';
}
