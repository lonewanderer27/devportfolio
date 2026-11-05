/**
 * Document viewer (DPD-013): shows a document's pages as an open book with
 * StPageFlip inside the modal stage.
 *
 * Page images come from one of two sources behind the same interface:
 *  - imageSource: the stored page images the server made when the document was
 *    saved (DPB-025). The normal case; pdf.js is never downloaded.
 *  - pdfSource: pdf.js renders the PDF to canvases in the visitor's browser.
 *    The heavy fallback, allowed only when the client render gate says so.
 * If neither works the stage says the document can't be previewed.
 *
 * A third way (DPD-026): a .pptx carries `nativeUrl` when Settings > Documents > Native viewer >
 * PowerPoint is on; the bundled @silurus/ooxml viewer (WebAssembly parser, canvas renderer,
 * vendor/ooxml/) then draws the original file here, in the visitor's browser, with selectable text,
 * and the modal's pager, arrows and keys turn the slides. It is loaded with import() only for such a
 * document and sends nothing anywhere; when it cannot draw the file the stored slides are shown.
 * viewerMode() picks.
 *
 * The viewer never owns the current page: view.js does. The viewer reports
 * geometry (page count and step) once it knows them and page turns made on the
 * book (drag, corner click), and view.js tells it where to go.
 */
import { stepFor, normalisePage } from './pager.js';
import { viewerMode, unitOf, wording, pieceLabel, canDrawOffice, isSpreadsheet } from './mode.js';
import { fitPage, orientationOf, renderWindow, canvasScale } from './layout.js';
import { clientRenderTier, budgetFor, readEnvironment, markFailure, clearFailure, safeStorage, isBenignRenderError } from './capability.js';

const FLIP_SRC = new URL( './vendor/page-flip.browser.js', import.meta.url ).href;

/** pdf.js errors that mean "this file is bad", not "this browser can't render". */
const DOCUMENT_ERRORS = [ 'PasswordException', 'InvalidPDFException', 'MissingPDFException', 'UnexpectedResponseException', 'FormatError' ];

/**
 * The bundled PPTX viewer (vendor/ooxml/, @silurus/ooxml); imported lazily, only for a document the native
 * viewer draws. Its entry file and WebAssembly file keep the same name in every release, so both carry the
 * library version in the query: a long browser cache can then never serve old code for a new release.
 * Keep PPTX_VERSION equal to the version in vendor/ooxml/README.md (a test compares them).
 */
const OOXML_VERSION = '0.89.7';
const PPTX_VERSION = OOXML_VERSION;
const PPTX_LIB = new URL( `./vendor/ooxml/pptx.mjs?v=${ PPTX_VERSION }`, import.meta.url ).href;
const PPTX_WASM = new URL( `./vendor/ooxml/pptx_parser_bg.wasm?v=${ PPTX_VERSION }`, import.meta.url ).href;
// The spreadsheet viewer (DPD-027) is part of the same package and version: its entry, its worker and its
// WebAssembly file are the files without a content hash in their name.
const XLSX_LIB = new URL( `./vendor/ooxml/xlsx.mjs?v=${ OOXML_VERSION }`, import.meta.url ).href;
const XLSX_WASM = new URL( `./vendor/ooxml/xlsx_parser_bg.wasm?v=${ OOXML_VERSION }`, import.meta.url ).href;
const DOCX_LIB = new URL( `./vendor/ooxml/docx.mjs?v=${ OOXML_VERSION }`, import.meta.url ).href;
const DOCX_WASM = new URL( `./vendor/ooxml/docx_parser_bg.wasm?v=${ OOXML_VERSION }`, import.meta.url ).href;

/** What the bundled viewer needs from the browser. */
function readNativeEnvironment() {
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

let flipLibrary = null;

/** Loads StPageFlip once, from the theme, with a plain script tag (it is a UMD file). */
function loadFlipLibrary() {
	if ( window.St && window.St.PageFlip ) {
		return Promise.resolve( window.St.PageFlip );
	}
	if ( ! flipLibrary ) {
		flipLibrary = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = FLIP_SRC;
			script.async = true;
			script.onload = () => {
				if ( window.St && window.St.PageFlip ) {
					resolve( window.St.PageFlip );
				} else {
					flipLibrary = null;
					reject( new Error( 'StPageFlip did not load' ) );
				}
			};
			script.onerror = () => {
				flipLibrary = null;
				reject( new Error( 'StPageFlip failed to load' ) );
			};
			document.head.appendChild( script );
		} );
	}
	return flipLibrary;
}

function withTimeout( promise, ms, onTimeout ) {
	return new Promise( ( resolve, reject ) => {
		const timer = setTimeout( () => {
			if ( onTimeout ) {
				onTimeout();
			}
			reject( new Error( 'timeout' ) );
		}, ms );
		promise.then(
			( value ) => {
				clearTimeout( timer );
				resolve( value );
			},
			( error ) => {
				clearTimeout( timer );
				reject( error );
			}
		);
	} );
}

/** Loads one image; resolves with it, or rejects if it cannot be loaded. */
function loadImage( url ) {
	return new Promise( ( resolve, reject ) => {
		const img = new Image();
		img.decoding = 'async';
		img.onload = () => resolve( img );
		img.onerror = () => reject( new Error( 'image failed' ) );
		img.src = url;
	} );
}

const sheetOf = ( pageEl ) => pageEl.querySelector( '.documents-modal__sheet' );

/** Stored page images: one <img> per page, drawn when the page enters the window. */
function imageSource( urls, ratio, imageWidth, preloaded, crop ) {
	return {
		kind: 'images',
		count: urls.length,
		ratio,
		// Share of each image's height hidden at the bottom (empty space, see the plugin's page analysis).
		crop: crop > 0 ? crop : 0,
		maxPageW: imageWidth > 0 ? imageWidth : Infinity,
		mount( index, pageEl ) {
			const sheet = sheetOf( pageEl );
			if ( sheet.firstChild ) {
				return Promise.resolve();
			}
			const img = preloaded[ index ] || new Image();
			img.alt = '';
			img.decoding = 'async';
			img.draggable = false;
			// A page whose image cannot be loaded is marked, instead of staying a silent blank sheet.
			img.onerror = () => pageEl.classList.add( 'documents-modal__page--broken' );
			if ( ! img.src ) {
				img.src = urls[ index - 1 ];
			}
			sheet.replaceChildren( img );
			return Promise.resolve();
		},
		// Images are never released and need no redraw on resize (the same interface as the pdf source).
		mounted: () => [],
		release() {},
		destroy() {},
	};
}

/** pdf.js pages: a canvas per page, drawn at the page's display size and released when far away. */
function pdfSource( pdf, ratio, TextLayer ) {
	const drawn = new Map();

	const release = ( index, pageEl ) => {
		const entry = drawn.get( index );
		if ( ! entry ) {
			return;
		}
		entry.cancelled = true;
		try {
			if ( entry.task ) {
				entry.task.cancel();
			}
			if ( entry.text ) {
				entry.text.cancel();
			}
		} catch ( e ) {
			// Already finished.
		}
		if ( entry.canvas ) {
			entry.canvas.width = 0;
			entry.canvas.height = 0;
		}
		if ( pageEl ) {
			sheetOf( pageEl ).replaceChildren();
		}
		drawn.delete( index );
	};

	/** Draws one page into its sheet. Throws on a real failure; mount() classifies teardown errors. */
	const draw = async ( index, pageEl, ctx, entry ) => {
		const page = await pdf.getPage( index );
		if ( entry.cancelled ) {
			return;
		}
		const base = page.getViewport( { scale: 1 } );
		const fit = Math.min( ctx.pageW / base.width, ctx.pageH / base.height );
		const viewport = page.getViewport( { scale: fit * ctx.scale } );
		const canvas = document.createElement( 'canvas' );
		canvas.width = Math.max( 1, Math.floor( viewport.width ) );
		canvas.height = Math.max( 1, Math.floor( viewport.height ) );
		// Beats StPageFlip's own "canvas fills its parent" rule, so a page whose
		// shape differs from page 1 is letterboxed instead of stretched.
		canvas.style.cssText = `position:static;width:${ Math.floor( base.width * fit ) }px;height:${ Math.floor( base.height * fit ) }px`;
		const context = canvas.getContext( '2d' );
		if ( ! context ) {
			throw new Error( 'canvas unavailable' );
		}
		entry.canvas = canvas;
		entry.task = page.render( { canvasContext: context, viewport } );
		await entry.task.promise;
		if ( entry.cancelled ) {
			return;
		}
		let content = canvas;
		if ( TextLayer ) {
			// Real text over the canvas, at the page's display size (pdf.js sizes it from --scale-factor).
			content = document.createElement( 'div' );
			content.className = 'documents-modal__pdf-page';
			content.style.width = canvas.style.width;
			content.style.height = canvas.style.height;
			const layer = document.createElement( 'div' );
			layer.className = 'documents-modal__text';
			layer.style.setProperty( '--scale-factor', String( fit ) );
			content.append( canvas, layer );
			entry.text = new TextLayer( {
				textContentSource: page.streamTextContent(),
				container: layer,
				viewport: page.getViewport( { scale: fit } ),
			} );
			await entry.text.render();
			if ( entry.cancelled ) {
				return;
			}
		}
		sheetOf( pageEl ).replaceChildren( content );
		page.cleanup();
	};

	return {
		kind: 'pdf',
		// True when pages carry a real text layer: the book then leaves the mouse to the text.
		textLayer: Boolean( TextLayer ),
		count: pdf.numPages,
		ratio,
		maxPageW: Infinity,
		mount( index, pageEl, ctx ) {
			if ( drawn.has( index ) ) {
				return drawn.get( index ).done;
			}
			const entry = { cancelled: false, task: null, text: null, canvas: null, done: null };
			drawn.set( index, entry );
			entry.done = draw( index, pageEl, ctx, entry ).catch( ( error ) => {
				// Torn down by us (page released, document closed, pdf.js destroyed mid-render): not a failure.
				if ( entry.cancelled || isBenignRenderError( error ) ) {
					return;
				}
				throw error;
			} );
			return entry.done;
		},
		release,
		has: ( index ) => drawn.has( index ),
		mounted: () => [ ...drawn.keys() ],
		destroy() {
			[ ...drawn.keys() ].forEach( ( index ) => release( index, null ) );
			pdf.destroy();
		},
	};
}

/**
 * @param {object}   options
 * @param {Element}  options.stage       .documents-modal__stage (empty; the book is added inside it).
 * @param {Element}  options.status      .documents-modal__state (loading / can't preview message).
 * @param {object}   options.pdfjs       { module, worker } URLs of the plugin's pdf.js, or {}.
 * @param {object}   options.limits      Merged gate limits.
 * @param {boolean}  options.selectableText  Draw pages with pdf.js plus a real text layer (selectable, slower) instead of the stored images.
 * @param {Function} options.onGeometry  ({ pages, step }) => page to show. pages 0 = nothing to show.
 * @param {Function} options.onFlip      ( page ) A page turn made on the book itself.
 */
export function createViewer( { stage, status, pdfjs, limits, units = null, selectableText = false, onGeometry, onFlip } ) {
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	// The book is not a live region: dozens of page labels would be announced at once.
	// Loading and "can't be previewed" are announced by the status element (role="status").
	stage.removeAttribute( 'aria-live' );
	const textEl = status.querySelector( '.documents-modal__state-text' );
	const hintEl = status.querySelector( '.documents-modal__state-hint' );

	let session = 0;
	let unit = 'page';
	let words = wording( 'page', units );
	let pptx = null;
	let docx = null;
	let pptxBusy = false;
	let pptxCount = 0;
	let grid = null;
	let hintKey = 'hint';
	let source = null;
	let budget = null;
	let usedPdf = false;
	let pageEls = [];
	let container = null;
	let flip = null;
	let observer = null;
	let resizeTimer = 0;
	let layout = null;
	let step = 1;
	let current = 1;
	let builtFor = { w: 0, h: 0 };
	let zoomFab = null;
	const ZOOM_MIN = 0.1;
	const ZOOM_MAX = 4;

	const updateZoomButtons = ( viewer ) => {
		if ( ! zoomFab || ! viewer || typeof viewer.getScale !== 'function' ) {
			return;
		}
		const scale = viewer.getScale();
		zoomFab.querySelector( '[data-zoom="out"]' ).disabled = scale <= ZOOM_MIN;
		zoomFab.querySelector( '[data-zoom="in"]' ).disabled = scale >= ZOOM_MAX;
	};

	const showZoomFab = ( viewer ) => {
		const makeZoomIcon = ( pathData ) => {
			const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
			svg.setAttribute( 'class', 'documents-modal__icon' );
			svg.setAttribute( 'viewBox', '0 0 24 24' );
			svg.setAttribute( 'aria-hidden', 'true' );
			svg.setAttribute( 'focusable', 'false' );
			const path = document.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
			path.setAttribute( 'd', pathData );
			svg.appendChild( path );
			return svg;
		};
		const zoomIn = document.createElement( 'button' );
		zoomIn.type = 'button';
		zoomIn.className = 'documents-modal__zoom-button';
		zoomIn.dataset.zoom = 'in';
		zoomIn.setAttribute( 'aria-label', 'Zoom in' );
		zoomIn.title = 'Zoom in';
		zoomIn.appendChild( makeZoomIcon( 'M12 5v14M5 12h14' ) );

		const zoomOut = document.createElement( 'button' );
		zoomOut.type = 'button';
		zoomOut.className = 'documents-modal__zoom-button';
		zoomOut.dataset.zoom = 'out';
		zoomOut.setAttribute( 'aria-label', 'Zoom out' );
		zoomOut.title = 'Zoom out';
		zoomOut.appendChild( makeZoomIcon( 'M5 12h14' ) );

		zoomFab = document.createElement( 'div' );
		zoomFab.className = 'documents-modal__zoom-fab';
		zoomFab.setAttribute( 'aria-label', 'Zoom controls' );
		zoomFab.append( zoomOut, zoomIn );
		stage.appendChild( zoomFab );

		zoomOut.addEventListener( 'click', () => {
			if ( typeof viewer.zoomOut === 'function' ) {
				viewer.zoomOut();
				updateZoomButtons( viewer );
			}
		} );
		zoomIn.addEventListener( 'click', () => {
			if ( typeof viewer.zoomIn === 'function' ) {
				viewer.zoomIn();
				updateZoomButtons( viewer );
			}
		} );
		updateZoomButtons( viewer );
	};

	const hideZoomFab = () => {
		if ( zoomFab ) {
			zoomFab.remove();
			zoomFab = null;
		}
	};

	const setStatus = ( kind ) => {
		if ( ! kind ) {
			// Hide first, then clear, so nothing is announced as it goes away.
			status.hidden = true;
		}
		status.dataset.kind = kind || '';
		textEl.textContent = kind === 'loading' ? status.dataset.loading : kind === 'unavailable' ? status.dataset.unavailable : '';
		hintEl.textContent = kind === 'unavailable' ? ( status.dataset[ hintKey ] || status.dataset.hint ) : '';
		stage.setAttribute( 'aria-busy', kind === 'loading' ? 'true' : 'false' );
		if ( kind ) {
			// The text is in place before the region appears, so a screen reader announces it.
			status.hidden = false;
		}
	};

	const destroyBook = () => {
		window.clearTimeout( resizeTimer );
		if ( flip ) {
			try {
				flip.destroy();
			} catch ( e ) {
				// Already gone.
			}
			flip = null;
		}
		if ( container && container.parentNode ) {
			container.remove();
		}
		container = null;
	};

	/** Frees a native viewer's workers; safe to call twice. */
	const destroyNative = ( viewer ) => {
		try {
			viewer.destroy();
		} catch ( e ) {
			// Already gone.
		}
	};

	const reset = () => {
		hideZoomFab();
		if ( grid ) {
			destroyNative( grid );
			grid = null;
		}
		if ( pptx ) {
			destroyNative( pptx );
			pptx = null;
		}
		if ( docx ) {
			destroyNative( docx );
			docx = null;
		}
		pptxBusy = false;
		pptxCount = 0;
		destroyBook();
		if ( observer ) {
			observer.disconnect();
			observer = null;
		}
		if ( source ) {
			source.destroy();
			source = null;
		}
		pageEls = [];
		layout = null;
		stage.replaceChildren();
	};

	const unavailable = () => {
		reset();
		setStatus( 'unavailable' );
		onGeometry( { pages: 0, step: 1 } );
	};

	/** One white page per document page, kept across rebuilds so drawn pages are not lost. */
	const buildPages = ( count ) => {
		pageEls = Array.from( { length: count }, ( _, i ) => {
			const el = document.createElement( 'div' );
			el.className = 'documents-modal__page';
			el.setAttribute( 'role', 'img' );
			el.setAttribute( 'aria-label', pieceLabel( words, i + 1, count ) );
			el.dataset.density = 'soft';
			const sheet = document.createElement( 'div' );
			sheet.className = 'documents-modal__sheet';
			const number = document.createElement( 'span' );
			number.className = 'documents-modal__pn';
			number.setAttribute( 'aria-hidden', 'true' );
			number.textContent = String( i + 1 );
			el.append( sheet, number );
			return el;
		} );
	};

	const ensureWindow = ( page ) => {
		if ( ! source || ! layout ) {
			return Promise.resolve();
		}
		const { from, to } = renderWindow( page, source.count, step, budget.ahead );
		const jobs = [];
		for ( let index = from; index <= to; index++ ) {
			jobs.push( source.mount( index, pageEls[ index - 1 ], layout ) );
		}
		source.mounted().forEach( ( index ) => {
			if ( index < from || index > to ) {
				source.release( index, pageEls[ index - 1 ] );
			}
		} );
		return Promise.all( jobs );
	};

	/** Failure while drawing pages after the book is up. */
	const rememberFailure = ( error ) => {
		if ( ! DOCUMENT_ERRORS.includes( error && error.name ) && ! isBenignRenderError( error ) ) {
			markFailure( safeStorage(), Date.now() );
		}
	};

	/**
	 * A failure of the book that was open when `mine` was taken. Ignored when another document was
	 * opened or the viewer was closed since (a late rejection must never wipe the current stage or
	 * set the failure flag), and when the error only means we tore the work down ourselves.
	 */
	const fail = ( error, mine = session ) => {
		if ( mine !== session || isBenignRenderError( error ) ) {
			return;
		}
		if ( usedPdf || ( error && error.fromPdf ) ) {
			rememberFailure( error );
		}
		unavailable();
	};

	/** Draws the pages around `page`; a failure is reported against the session that asked. */
	const draw = ( page ) => {
		const mine = session;
		return ensureWindow( page ).catch( ( error ) => fail( error, mine ) );
	};

	// Layout size, not the bounding rect: the rect follows transforms, so while the modal's
	// opening animation was running the book was measured a few pixels too small.
	const measure = () => ( { w: stage.clientWidth, h: stage.clientHeight } );

	/** (Re)creates the StPageFlip book at the size that fits the stage, on the current page. */
	const buildBook = ( PageFlip, hidden ) => {
		const size = measure();
		if ( size.w < 40 || size.h < 40 ) {
			return false;
		}
		destroyBook();

		const { pageW, pageH } = fitPage( { stageW: size.w, stageH: size.h, ratio: source.ratio, step, maxPageW: source.maxPageW } );
		const dprCap = budget.dprCap;
		layout = {
			pageW,
			pageH,
			scale: canvasScale( { pageW, pageH, dpr: window.devicePixelRatio, dprCap, maxPixels: budget.maxPixels } ),
		};

		container = document.createElement( 'div' );
		container.className = 'documents-modal__book';
		container.classList.toggle( 'documents-modal__book--selectable', Boolean( source.textLayer ) );
		if ( source.crop > 0 ) {
			// Stored images only: hide the empty strip at the bottom of every page (the pdf.js path is not cropped).
			container.classList.add( 'documents-modal__book--cropped' );
			container.style.setProperty( '--documents-crop', String( source.crop ) );
		}
		container.style.width = `${ step === 2 ? pageW * 2 : pageW }px`;
		container.style.height = `${ pageH }px`;
		if ( hidden ) {
			container.style.visibility = 'hidden';
		}
		stage.appendChild( container );

		pageEls.forEach( ( el, i ) => {
			el.classList.toggle( 'documents-modal__page--left', step === 2 && i % 2 === 0 );
			el.classList.toggle( 'documents-modal__page--right', step === 2 && i % 2 === 1 );
		} );

		flip = new PageFlip( container, {
			width: pageW,
			height: pageH,
			size: 'fixed',
			autoSize: false,
			// A single page uses the library's portrait mode; a spread needs the landscape mode.
			usePortrait: step === 1,
			showCover: false,
			drawShadow: true,
			maxShadowOpacity: 0.45,
			flippingTime: reducedMotion.matches ? 1 : 600,
			mobileScrollSupport: false,
			// With selectable text the mouse belongs to the text: no dragging or corner hints to turn pages.
			showPageCorners: ! reducedMotion.matches && ! source.textLayer,
			useMouseEvents: ! source.textLayer,
			startPage: normalisePage( current, source.count, step ) - 1,
		} );
		flip.loadFromHTML( pageEls );
		flip.on( 'flip', ( event ) => {
			if ( ! source ) {
				return;
			}
			// The library reports the left page of the new view (0-based); snap it to
			// an odd page in a spread. turnToPage() reports here too, so view.js ignores
			// a page it already has (see syncFromViewer).
			const page = normalisePage( Number( event.data ) + 1, source.count, step );
			current = page;
			draw( page );
			onFlip( page );
		} );
		builtFor = size;
		return true;
	};

	const onResize = () => {
		window.clearTimeout( resizeTimer );
		const mine = session;
		resizeTimer = window.setTimeout( async () => {
			if ( ! source || mine !== session ) {
				return;
			}
			const size = measure();
			if ( Math.abs( size.w - builtFor.w ) < 2 && Math.abs( size.h - builtFor.h ) < 2 ) {
				return;
			}
			try {
				const PageFlip = await loadFlipLibrary();
				if ( ! source || mine !== session ) {
					return;
				}
				// Canvases were drawn for the old size: draw them again at the new one.
				source.mounted().forEach( ( index ) => source.release( index, pageEls[ index - 1 ] ) );
				if ( buildBook( PageFlip, false ) ) {
					await ensureWindow( current );
				}
			} catch ( error ) {
				fail( error, mine );
			}
		}, 150 );
	};

	/** Stored page images, or null when the first one cannot be loaded (then the PDF is tried). */
	const tryImages = async ( doc, alive ) => {
		if ( ! Array.isArray( doc.images ) || ! doc.images.length ) {
			return null;
		}
		try {
			const first = await withTimeout( loadImage( doc.images[ 0 ] ), limits.firstPageTimeoutMs );
			if ( ! alive() ) {
				return null;
			}
			// The server already limits the crop to 0 to 0.25.
			const crop = Number( doc.cropBottom ) || 0;
			// The payload ratio already accounts for the crop; measured from the image it has to be done here.
			const ratio = doc.ratio > 0 ? doc.ratio : first.naturalWidth / Math.max( 1, first.naturalHeight * ( 1 - crop ) );
			const preloaded = { 1: first };
			// The first view must be whole: a spread needs its second page too. A broken one sends us to the PDF fallback.
			if ( stepFor( doc.images.length, orientationOf( ratio, 1 ) ) === 2 ) {
				preloaded[ 2 ] = await withTimeout( loadImage( doc.images[ 1 ] ), limits.firstPageTimeoutMs );
				if ( ! alive() ) {
					return null;
				}
			}
			return imageSource( doc.images, ratio, Number( doc.imageWidth ) || 0, preloaded, crop );
		} catch ( e ) {
			return null;
		}
	};

	/** pdf.js in the browser, or null when the gate refuses this device. Throws on a real failure. */
	const tryPdf = async ( doc, alive ) => {
		if ( ! doc.viewerUrl || ! pdfjs || ! pdfjs.module || ! pdfjs.worker ) {
			return null;
		}
		const verdict = clientRenderTier( readEnvironment( window, limits.failureTtlDays ), limits, { pdfBytes: doc.pdfBytes, pages: doc.pages } );
		if ( verdict.tier === 'refused' ) {
			return null;
		}

		try {
			const lib = await import( pdfjs.module );
			if ( ! alive() ) {
				return null;
			}
			lib.GlobalWorkerOptions.workerSrc = pdfjs.worker;
			const task = lib.getDocument( { url: doc.viewerUrl, isEvalSupported: false } );
			let pdf;
			try {
				pdf = await withTimeout( task.promise, limits.firstPageTimeoutMs, () => task.destroy() );
			} catch ( error ) {
				task.destroy();
				throw error;
			}
			if ( ! alive() ) {
				pdf.destroy();
				return null;
			}
			let viewport;
			try {
				const first = await pdf.getPage( 1 );
				viewport = first.getViewport( { scale: 1 } );
			} catch ( error ) {
				pdf.destroy();
				throw error;
			}
			return { source: pdfSource( pdf, viewport.width / viewport.height, selectableText ? lib.TextLayer : null ), budget: budgetFor( verdict.tier, limits ) };
		} catch ( error ) {
			// Tag it, so a failure of the PDF path (not of the stored images) is remembered.
			throw Object.assign( new Error( String( error && error.message ) ), { name: error && error.name, fromPdf: true } );
		}
	};

	/**
	 * Draws a .pptx in the visitor's browser with the native (bundled) viewer (DPD-026).
	 *
	 * One canvas shows one slide; the modal's own pager, arrows and keys turn them (goTo() below), so
	 * this behaves like the book. Text can be selected and copied; hyperlinks, media, element selection
	 * and web fonts are switched off: the viewer reads the file from this site and requests nothing else.
	 *
	 * @return {Promise<boolean>} True when the deck is showing. False when it could not be drawn (the
	 *                            caller then shows the stored slides, or the "can't be previewed" state).
	 */
	const showPptx = async ( doc, alive ) => {
		if ( ! canDrawOffice( readNativeEnvironment() ) ) {
			return false;
		}

		const mine = session;
		let opened = false;
		const wrap = document.createElement( 'div' );
		wrap.className = 'documents-modal__native';
		wrap.style.visibility = 'hidden';
		const canvas = document.createElement( 'canvas' );
		canvas.className = 'documents-modal__native-canvas';
		canvas.setAttribute( 'role', 'img' );
		canvas.setAttribute( 'aria-label', doc.title || '' );
		wrap.append( canvas );
		stage.replaceChildren( wrap );

		/** The canvas is scaled with CSS to fit the stage. */
		const fit = () => {
			const ratio = canvas.width > 0 && canvas.height > 0 ? canvas.width / canvas.height : 16 / 9;
			const room = fitPage( { stageW: stage.clientWidth, stageH: Math.max( 40, stage.clientHeight ), ratio } );
			canvas.style.width = `${ room.pageW }px`;
			canvas.style.height = `${ room.pageH }px`;
		};

		try {
			const lib = await withTimeout( import( PPTX_LIB ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			// Draw at the size it will be shown (times the device ratio below), not larger: a 4K canvas costs
			// about four times the memory of a full-HD one. Slides are mostly 16:9, which this assumes.
			const size = measure();
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
				onSlideChange: ( index, total ) => {
					if ( mine !== session ) {
						return;
					}
					canvas.setAttribute( 'aria-label', pieceLabel( words, index + 1, total || pptxCount || index + 1 ) );
					if ( index + 1 !== current ) {
						current = index + 1;
						onFlip( current );
					}
				},
				// Before the deck is showing a failure is handled by the awaits below; after it, a slide that
				// cannot be drawn ends the viewer.
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
			} );
			// From here reset() can destroy it, so closing mid-load cannot leave a worker running.
			pptx = instance;

			await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			pptxCount = instance.slideCount;
			step = 1;
			current = onGeometry( { pages: pptxCount, step } );
			if ( current > 1 ) {
				await instance.goToSlide( current - 1 );
			}
			if ( ! alive() ) {
				return false;
			}

			fit();
			wrap.style.visibility = '';
			setStatus( null );
			opened = true;
			showZoomFab( instance );

			observer = new ResizeObserver( fit );
			observer.observe( stage );
			return true;
		} catch ( error ) {
			if ( alive() && ! isBenignRenderError( error ) ) {
				// Leave nothing of the failed attempt behind: the caller shows the stored slides or says it can't preview.
				if ( pptx ) {
					destroyNative( pptx );
					pptx = null;
				}
				pptxBusy = false;
				pptxCount = 0;
				stage.replaceChildren();
			}
			return false;
		}
	};

	/**
	 * Draws a .xlsx in the visitor's browser with the native (bundled) viewer (DPD-027).
	 *
	 * The library's own grid fills the stage: scrolling, zoom, sheet tabs and cell selection with copy.
	 * Hidden sheets are skipped, hyperlinks, comments and web fonts are off, so the viewer reads the file
	 * from this site and requests nothing else. The modal's pager and arrows are not used (the workbook
	 * reports one piece), and Esc still closes the modal.
	 *
	 * @return {Promise<boolean>} True when the workbook is showing. False when it could not be drawn (the
	 *                            caller then shows the stored pages, or the "can't be previewed" state).
	 */
	const showXlsx = async ( doc, alive ) => {
		if ( ! canDrawOffice( readNativeEnvironment() ) ) {
			return false;
		}

		let opened = false;
		const mine = session;
		const wrap = document.createElement( 'div' );
		wrap.className = 'documents-modal__grid';
		wrap.setAttribute( 'role', 'group' );
		wrap.setAttribute( 'aria-label', doc.title || '' );
		wrap.style.visibility = 'hidden';
		stage.replaceChildren( wrap );

		try {
			const lib = await withTimeout( import( XLSX_LIB ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			const instance = new lib.XlsxViewer( wrap, {
				wasmUrl: XLSX_WASM,
				useGoogleFonts: false,
				enableHyperlinks: false,
				enableElementSelection: false,
				comments: false,
				hiddenSheetMode: 'skip',
				showZoomSlider: true,
				workerTimeoutMs: limits.nativeTimeoutMs,
				xlsxWorksheetLimits: { maxCells: limits.nativeMaxCells, maxRows: limits.nativeMaxRows },
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
			} );
			// From here reset() can destroy it, so closing mid-load cannot leave a worker running.
			grid = instance;

			await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			step = 1;
			current = onGeometry( { pages: 1, step } );
			wrap.style.visibility = '';
			setStatus( null );
			opened = true;
			showZoomFab( instance );
			return true;
		} catch ( error ) {
			if ( alive() && ! isBenignRenderError( error ) ) {
				// Leave nothing of the failed attempt behind: the caller shows the stored pages or says it can't preview.
				if ( grid ) {
					destroyNative( grid );
					grid = null;
				}
				stage.replaceChildren();
			}
			return false;
		}
	};

	/**
	 * Draws a .docx in the visitor's browser with the native scrolling viewer.
	 * The continuous viewer owns scrolling and text selection, so the modal
	 * pager and arrows are intentionally reduced to one hidden piece.
	 */
	const showDocx = async ( doc, alive ) => {
		if ( ! canDrawOffice( readNativeEnvironment() ) ) {
			return false;
		}

		const mine = session;
		let opened = false;
		const wrap = document.createElement( 'div' );
		wrap.className = 'documents-modal__native documents-modal__native--doc';
		wrap.setAttribute( 'role', 'region' );
		wrap.setAttribute( 'aria-label', doc.title || '' );
		wrap.style.visibility = 'hidden';
		stage.replaceChildren( wrap );

		try {
			const lib = await withTimeout( import( DOCX_LIB ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			const instance = new lib.DocxScrollViewer( wrap, {
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
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
			} );
			docx = instance;

			await withTimeout( instance.load( doc.nativeUrl ), limits.nativeTimeoutMs );
			if ( ! alive() ) {
				return false;
			}

			step = 1;
			current = onGeometry( { pages: 1, step } );
			wrap.style.visibility = '';
			setStatus( null );
			opened = true;
			showZoomFab( instance );
			return true;
		} catch ( error ) {
			if ( alive() && ! isBenignRenderError( error ) ) {
				if ( docx ) {
					destroyNative( docx );
					docx = null;
				}
				stage.replaceChildren();
			}
			return false;
		}
	};

	return {
		/** Opens a document. Resolves when the book is showing or the document was marked unavailable. */
		async show( doc, page = 1 ) {
			reset();
			const mine = ++session;
			const alive = () => mine === session;
			unit = unitOf( doc );
			hintKey = unit === 'slide' ? 'hintSlide' : ( isSpreadsheet( doc ) ? 'hintSheet' : ( doc.kind === 'docx' ? 'hintDoc' : 'hint' ) );
			words = wording( unit, units );
			current = page;
			usedPdf = false;
			budget = budgetFor( 'full', limits );
			setStatus( 'loading' );

			if ( viewerMode( doc ) === 'native' ) {
				const nativeShow = doc.kind === 'xlsx' ? showXlsx : ( doc.kind === 'docx' ? showDocx : showPptx );
				const drawn = await nativeShow( doc, alive );
				if ( drawn || ! alive() ) {
					return;
				}
				// The native viewer could not draw it: use the stored slides or pages when there are some.
				if ( ! Array.isArray( doc.images ) || ! doc.images.length ) {
					unavailable();
					return;
				}
			}

			// Start fetching StPageFlip now, alongside the first image or pdf.js (a failure is handled where it is awaited).
			loadFlipLibrary().catch( () => {} );

			let next = null;
			try {
				let nextBudget = budget;
				// Selectable text needs real text, which only pdf.js gives: try it first, and fall back to the
				// stored images (not selectable) when it is refused or fails. Otherwise images first, pdf.js as the fallback.
				const pdfFirst = selectableText && Boolean( doc.viewerUrl );
				let pdf = null;

				if ( pdfFirst ) {
					try {
						pdf = await tryPdf( doc, alive );
					} catch ( error ) {
						if ( ! Array.isArray( doc.images ) || ! doc.images.length ) {
							throw error;
						}
						rememberFailure( error );
					}
					if ( alive() && ! pdf ) {
						next = await tryImages( doc, alive );
					}
				} else {
					next = await tryImages( doc, alive );
					if ( alive() && ! next ) {
						pdf = await tryPdf( doc, alive );
					}
				}

				if ( ! alive() ) {
					if ( pdf ) {
						pdf.source.destroy();
					}
					return;
				}
				if ( pdf ) {
					next = pdf.source;
					nextBudget = pdf.budget;
				}
				if ( ! next ) {
					unavailable();
					return;
				}

				const PageFlip = await loadFlipLibrary();
				if ( ! alive() ) {
					next.destroy();
					return;
				}

				// From here this call owns the viewer state.
				source = next;
				budget = nextBudget;
				usedPdf = next.kind === 'pdf';
				next = null;

				step = stepFor( source.count, orientationOf( source.ratio, 1 ) );
				current = onGeometry( { pages: source.count, step } );
				buildPages( source.count );

				// The dialog has only just opened: wait one frame for the stage to have a size.
				if ( ! buildBook( PageFlip, true ) ) {
					await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
					if ( ! alive() ) {
						return;
					}
					if ( ! buildBook( PageFlip, true ) ) {
						throw new Error( 'stage has no size' );
					}
				}

				await withTimeout( ensureWindow( current ), limits.firstPageTimeoutMs );
				if ( ! alive() ) {
					return;
				}
				if ( usedPdf ) {
					clearFailure( safeStorage() );
				}
				container.style.visibility = '';
				setStatus( null );

				observer = new ResizeObserver( onResize );
				observer.observe( stage );
			} catch ( error ) {
				if ( next ) {
					next.destroy();
				}
				if ( alive() ) {
					fail( error );
				}
			}
		},

		/** True while a page is turning, so callers can ignore a second turn. */
		isTurning() {
			if ( grid ) {
				return false;
			}
			if ( pptx ) {
				return pptxBusy;
			}
			// Only while a page is actually moving: the library also reports 'fold_corner' while the pointer rests on a corner.
			return !! flip && [ 'flipping', 'user_fold' ].includes( flip.getState() );
		},

		/** Moves the book to a page. `animate` flips by one view; anything else jumps. */
		goTo( page, { animate = false } = {} ) {
			if ( grid ) {
				// One piece: the workbook scrolls and switches sheets by itself.
				return;
			}
			if ( pptx ) {
				// The slide viewer has no animation; the pager, arrows and keys all end here.
				const mine = session;
				const target = normalisePage( page, pptxCount || 1, 1 );
				if ( pptxCount && target !== current ) {
					current = target;
					pptxBusy = true;
					pptx.goToSlide( target - 1 )
						.catch( ( error ) => fail( error, mine ) )
						.finally( () => {
							if ( mine === session ) {
								pptxBusy = false;
							}
						} );
				}
				return;
			}
			if ( ! source || ! flip ) {
				current = page;
				return;
			}
			const target = normalisePage( page, source.count, step );
			const from = normalisePage( flip.getCurrentPageIndex() + 1, source.count, step );
			current = target;
			if ( target === from ) {
				return;
			}
			try {
				if ( animate && target === from + step ) {
					flip.flipNext( 'top' );
				} else if ( animate && target === from - step ) {
					flip.flipPrev( 'top' );
				} else {
					flip.turnToPage( target - 1 );
				}
			} catch ( error ) {
				fail( error );
				return;
			}
			draw( target );
		},

		/** Closes the viewer and frees pages, canvases and the PDF. */
		destroy() {
			session++;
			reset();
			setStatus( null );
		},
	};
}
