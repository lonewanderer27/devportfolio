/**
 * The bundled Excel viewer, shared by the desktop modal (viewer.js, DPD-027) and the mobile viewer
 * (mobile-viewer.js, DPD-030).
 *
 * The library (vendor/ooxml/, @silurus/ooxml) is imported lazily, only for a workbook the native viewer
 * draws. Its entry file and WebAssembly file keep the same name in every release, so both carry the
 * library version in the query (see native-pptx.js).
 *
 * `openXlsx()` is the one place that sets the options, so the privacy rules (the viewer reads the file
 * from this site and requests nothing else, hidden sheets are not drawn) cannot differ between desktop
 * and mobile. The library's own grid brings the scrolling, zoom, sheet tabs and cell selection, so the
 * modal's page turner is not used: the workbook is one piece.
 */
import { OOXML_VERSION } from './native-pptx.js';
import { withTimeout } from './page-source.js';

export const XLSX_LIB = new URL( `./vendor/ooxml/xlsx.mjs?v=${ OOXML_VERSION }`, import.meta.url ).href;
export const XLSX_WASM = new URL( `./vendor/ooxml/xlsx_parser_bg.wasm?v=${ OOXML_VERSION }`, import.meta.url ).href;

/**
 * Loads the viewer library, creates a grid in `host` and loads the workbook.
 *
 * @param {object}   options
 * @param {Element}  options.host      Empty element the grid fills.
 * @param {object}   options.doc       Payload entry (`nativeUrl`).
 * @param {object}   options.limits    Merged limits (`nativeTimeoutMs`, `nativeMaxCells`, `nativeMaxRows`).
 * @param {Function} options.alive     () => bool, false once the document was closed or replaced.
 * @param {Function} options.onError   ( error ) A sheet that cannot be drawn.
 * @param {Function} options.onCreate  ( instance ) Called before the file is loaded, so the caller can
 *                                     free the workers if it closes mid-load.
 * @return {Promise<object|null>} The loaded viewer, or null when the document was closed meanwhile.
 */
export async function openXlsx( { host, doc, limits, alive, onError, onCreate } ) {
	const lib = await withTimeout( import( XLSX_LIB ), limits.nativeTimeoutMs );
	if ( ! alive() ) {
		return null;
	}

	const instance = new lib.XlsxViewer( host, {
		wasmUrl: XLSX_WASM,
		useGoogleFonts: false,
		enableHyperlinks: false,
		enableElementSelection: false,
		comments: false,
		hiddenSheetMode: 'skip',
		showZoomSlider: true,
		workerTimeoutMs: limits.nativeTimeoutMs,
		xlsxWorksheetLimits: { maxCells: limits.nativeMaxCells, maxRows: limits.nativeMaxRows },
		onError,
	} );
	onCreate( instance );

	await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
	return alive() ? instance : null;
}
