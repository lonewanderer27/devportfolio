/**
 * The bundled Word viewer, shared by the desktop modal (viewer.js, DPD-028) and the mobile viewer
 * (mobile-viewer.js, DPD-031).
 *
 * The library (vendor/ooxml/, @silurus/ooxml) is imported lazily, only for a .docx the native viewer draws.
 * Its entry file and WebAssembly file keep the same name in every release, so both carry the library
 * version in the query (see native-pptx.js).
 *
 * `openDocx()` is the one place that sets the options, so the privacy rules (the viewer reads the file
 * from this site and requests nothing else, no hyperlinks, comments or tracked changes) and the resource
 * limits cannot differ between desktop and mobile. The library's scroller brings the scrolling, zoom and
 * text selection, so the modal's page turner is not used: the document is one piece.
 */
import { OOXML_VERSION } from './native-pptx.js';
import { withTimeout } from './page-source.js';

export const DOCX_LIB = new URL( `./vendor/ooxml/docx.mjs?v=${ OOXML_VERSION }`, import.meta.url ).href;
export const DOCX_WASM = new URL( `./vendor/ooxml/docx_parser_bg.wasm?v=${ OOXML_VERSION }`, import.meta.url ).href;

/**
 * Loads the viewer library, creates a scroller in `host` and loads the document.
 *
 * @param {object}   options
 * @param {Element}  options.host      Empty element the scroller fills.
 * @param {object}   options.doc       Payload entry (`nativeUrl`).
 * @param {object}   options.limits    Merged limits (`nativeTimeoutMs`).
 * @param {Function} options.alive     () => bool, false once the document was closed or replaced.
 * @param {Function} options.onError   ( error ) A page that cannot be drawn.
 * @param {Function} options.onCreate  ( instance ) Called before the file is loaded, so the caller can
 *                                     free the workers if it closes mid-load.
 * @return {Promise<object|null>} The loaded viewer, or null when the document was closed meanwhile.
 */
export async function openDocx( { host, doc, limits, alive, onError, onCreate } ) {
	const lib = await withTimeout( import( DOCX_LIB ), limits.nativeTimeoutMs );
	if ( ! alive() ) {
		return null;
	}

	const instance = new lib.DocxScrollViewer( host, {
		wasmUrl: DOCX_WASM,
		useGoogleFonts: false,
		enableTextSelection: true,
		enableElementSelection: false,
		comments: false,
		enableHyperlinks: false,
		showTrackedChanges: false,
		pageShadow: false,
		refitOnResize: true,
		workerTimeoutMs: limits.nativeTimeoutMs,
		resourceLimits: {
			maxArchiveEntries: 5000,
			maxTotalInflatedBytes: 1024 * 1024 * 1024,
			maxArchiveEntryBytes: 100 * 1024 * 1024,
		},
		onError,
	} );
	onCreate( instance );

	await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
	if ( ! alive() ) {
		return null;
	}

	// Open at a readable width rather than the library's default (DPD-028 decision 12). Best effort:
	// the scroller already refits on resize, so a missing or failing call only keeps its own default.
	try {
		if ( typeof instance.fitWidth === 'function' ) {
			instance.fitWidth();
		}
	} catch ( e ) {
		// Keep the library's default scale.
	}
	return instance;
}
