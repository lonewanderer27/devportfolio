/**
 * The bundled PowerPoint viewer, shared by the desktop book (viewer.js, DPD-026) and the mobile viewer
 * (mobile-viewer.js, DPD-029).
 *
 * The viewer (vendor/ooxml/, @silurus/ooxml) is imported lazily, only for a document the native viewer
 * draws. Its entry file and WebAssembly file keep the same name in every release, so both carry the
 * library version in the query: a long browser cache can then never serve old code for a new release.
 * Keep OOXML_VERSION equal to the version in vendor/ooxml/README.md (a test compares them).
 *
 * `openPptx()` is the one place that sets the options, so the privacy rules (the viewer reads the file
 * from this site and requests nothing else) cannot differ between desktop and mobile.
 */
import { fitPage } from './layout.js';
import { withTimeout } from './page-source.js';

export const OOXML_VERSION = '0.89.7';
export const PPTX_VERSION = OOXML_VERSION;
export const PPTX_LIB = new URL( `./vendor/ooxml/pptx.mjs?v=${ PPTX_VERSION }`, import.meta.url ).href;
export const PPTX_WASM = new URL( `./vendor/ooxml/pptx_parser_bg.wasm?v=${ PPTX_VERSION }`, import.meta.url ).href;

/** What the bundled viewer needs from the browser. */
export function readNativeEnvironment() {
	let canvas2d = false;
	try {
		canvas2d = !! document.createElement( 'canvas' ).getContext( '2d' );
	} catch ( e ) {
		canvas2d = false;
	}
	return {
		webassembly: typeof WebAssembly === 'object' && typeof WebAssembly.instantiate === 'function',
		worker: typeof Worker === 'function',
		canvas2d,
	};
}

/**
 * Loads the viewer library, creates a viewer on `canvas` and loads the deck.
 *
 * @param {object}   options
 * @param {Element}  options.canvas
 * @param {object}   options.doc            Payload entry (`nativeUrl`).
 * @param {object}   options.limits         Merged limits (`nativeTimeoutMs`).
 * @param {object}   options.size           { w, h } of the stage, to pick the drawing size.
 * @param {Function} options.alive          () => bool, false once the document was closed or replaced.
 * @param {Function} options.onSlideChange  ( index, total )
 * @param {Function} options.onError        ( error ) A slide that cannot be drawn.
 * @param {Function} options.onCreate       ( instance ) Called before the file is loaded, so the caller can
 *                                          free the workers if it closes mid-load.
 * @return {Promise<object|null>} The loaded viewer, or null when the document was closed during the import.
 */
export async function openPptx( { canvas, doc, limits, size, alive, onSlideChange, onError, onCreate } ) {
	const lib = await withTimeout( import( PPTX_LIB ), limits.nativeTimeoutMs );
	if ( ! alive() ) {
		return null;
	}

	// Draw at the size it will be shown (times the device ratio below), not larger: a 4K canvas costs
	// about four times the memory of a full-HD one. Slides are mostly 16:9, which this assumes.
	const room = fitPage( { stageW: size.w, stageH: Math.max( 40, size.h ), ratio: 16 / 9 } );
	const instance = new lib.PptxViewer( canvas, {
		wasmUrl: PPTX_WASM,
		width: Math.max( 640, Math.min( 1920, room.pageW ) ),
		dpr: Math.min( 2, window.devicePixelRatio || 1 ),
		enableTextSelection: true,
		enableHyperlinks: false,
		enableMediaPlayback: false,
		enableElementSelection: false,
		useGoogleFonts: false,
		workerTimeoutMs: limits.nativeTimeoutMs,
		onSlideChange,
		onError,
	} );
	onCreate( instance );

	await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
	return instance;
}
