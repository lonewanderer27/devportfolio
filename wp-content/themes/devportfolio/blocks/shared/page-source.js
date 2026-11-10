/**
 * Page sources shared by the desktop book (viewer.js) and the mobile viewer (mobile-viewer.js).
 *
 * A source turns a document into pages behind one interface (`mount( index, pageEl, ctx )`), with
 * `pageEl` holding a `.documents-modal__sheet`:
 *  - imageSource: the stored page images the server made when the document was saved (DPB-025).
 *  - pdfSource: pdf.js renders the PDF in the visitor's browser (optionally with a real text layer);
 *    allowed only when the client render gate says so.
 * acquireSource() picks one in the order the Native viewer setting asks for.
 */
import { stepFor } from './pager.js';
import { orientationOf, renderWindow } from './layout.js';
import { el } from './dom.js';
import { clientRenderTier, budgetFor, readEnvironment, markFailure, safeStorage, isBenignRenderError } from './capability.js';

/** pdf.js errors that mean "this file is bad", not "this browser can't render". */
const DOCUMENT_ERRORS = [ 'PasswordException', 'InvalidPDFException', 'MissingPDFException', 'UnexpectedResponseException', 'FormatError' ];

export function withTimeout( promise, ms, onTimeout ) {
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
export function loadImage( url ) {
	return new Promise( ( resolve, reject ) => {
		const img = new Image();
		img.decoding = 'async';
		img.onload = () => resolve( img );
		img.onerror = () => reject( new Error( 'image failed' ) );
		img.src = url;
	} );
}

const sheetOf = ( pageEl, prefix = 'documents-modal' ) => pageEl.querySelector( `.${ prefix }__sheet` );

/** Stored page images: one <img> per page, drawn when the page enters the window. */
export function imageSource( urls, ratio, imageWidth, preloaded, crop, prefix = 'documents-modal' ) {
	return {
		kind: 'images',
		count: urls.length,
		ratio,
		// Share of each image's height hidden at the bottom (empty space, see the plugin's page analysis).
		crop: crop > 0 ? crop : 0,
		maxPageW: imageWidth > 0 ? imageWidth : Infinity,
		mount( index, pageEl ) {
			const sheet = sheetOf( pageEl, prefix );
			if ( sheet.firstChild ) {
				return Promise.resolve();
			}
			const img = preloaded[ index ] || new Image();
			img.alt = '';
			img.decoding = 'async';
			img.draggable = false;
			// A page whose image cannot be loaded is marked, instead of staying a silent blank sheet.
			img.onerror = () => pageEl.classList.add( `${ prefix }__page--broken` );
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
export function pdfSource( pdf, ratio, TextLayer, prefix = 'documents-modal' ) {
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
			sheetOf( pageEl, prefix ).replaceChildren();
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
			content.className = `${ prefix }__pdf-page`;
			content.style.width = canvas.style.width;
			content.style.height = canvas.style.height;
			const layer = document.createElement( 'div' );
			layer.className = `${ prefix }__text`;
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
		sheetOf( pageEl, prefix ).replaceChildren( content );
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

/** Remembers a failure of the pdf.js path, unless the file itself is bad or we tore the work down. */
export function rememberFailure( error ) {
	if ( ! DOCUMENT_ERRORS.includes( error && error.name ) && ! isBenignRenderError( error ) ) {
		markFailure( safeStorage(), Date.now() );
	}
}

/** Stored page images, or null when the first one cannot be loaded (then the PDF is tried). */
export async function tryImages( doc, alive, limits, prefix = 'documents-modal' ) {
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
		return imageSource( doc.images, ratio, Number( doc.imageWidth ) || 0, preloaded, crop, prefix );
	} catch ( e ) {
		return null;
	}
}

/** pdf.js in the browser, or null when the gate refuses this device. Throws on a real failure. */
export async function tryPdf( doc, alive, { pdfjs, limits, selectableText, prefix = 'documents-modal' } ) {
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
		return { source: pdfSource( pdf, viewport.width / viewport.height, selectableText ? lib.TextLayer : null, prefix ), budget: budgetFor( verdict.tier, limits ) };
	} catch ( error ) {
		// Tag it, so a failure of the PDF path (not of the stored images) is remembered.
		throw Object.assign( new Error( String( error && error.message ) ), { name: error && error.name, fromPdf: true } );
	}
}

/**
 * Picks the source for a document, in the order desktop and mobile share:
 * selectable text on and pdf.js allowed -> pdf.js with a text layer; stored images; pdf.js as the fallback.
 * Resolves with `{ source, budget }` (`budget` is null for stored images: the caller's default applies)
 * or null when there is nothing to show. Throws on a real failure of the pdf.js path (tagged `fromPdf`).
 * The caller destroys the source when it is no longer wanted.
 */
export async function acquireSource( doc, alive, { pdfjs, limits, selectableText, prefix = 'documents-modal' } ) {
	const options = { pdfjs, limits, selectableText, prefix };
	let pdf = null;
	let images = null;
	// Selectable text needs real text, which only pdf.js gives: try it first, and fall back to the
	// stored images (not selectable) when it is refused or fails. Otherwise images first, pdf.js as the fallback.
	if ( selectableText && Boolean( doc.viewerUrl ) ) {
		try {
			pdf = await tryPdf( doc, alive, options );
		} catch ( error ) {
			if ( ! Array.isArray( doc.images ) || ! doc.images.length ) {
				throw error;
			}
			rememberFailure( error );
		}
		if ( alive() && ! pdf ) {
			images = await tryImages( doc, alive, limits, prefix );
		}
	} else {
		images = await tryImages( doc, alive, limits, prefix );
		if ( alive() && ! images ) {
			pdf = await tryPdf( doc, alive, options );
		}
	}
	if ( pdf ) {
		return pdf;
	}
	return images ? { source: images, budget: null } : null;
}

/**
 * One empty white page element per document page (`.documents-modal__page` > `.documents-modal__sheet`
 * plus the printed number), kept across relayouts so drawn pages are not lost. `label( index, count )`
 * names a page for screen readers.
 */
export function buildPageEls( count, label, prefix = 'documents-modal' ) {
	return Array.from( { length: count }, ( _, i ) => {
		const page = el( 'div', `${ prefix }__page`, { role: 'img', 'aria-label': label( i + 1, count ) } );
		const number = el( 'span', `${ prefix }__pn`, { 'aria-hidden': 'true' } );
		number.textContent = String( i + 1 );
		page.append( el( 'div', `${ prefix }__sheet` ), number );
		return page;
	} );
}

/**
 * Draws the pages around `page` (the visible view plus `ahead` views either side) and releases the ones far
 * away. Resolves when the window is drawn.
 */
export function mountWindow( { source, layout, pageEls, page, step, ahead } ) {
	const { from, to } = renderWindow( page, source.count, step, ahead );
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
}
