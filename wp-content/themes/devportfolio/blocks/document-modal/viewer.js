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
import { viewerMode, unitOf, wording, pieceLabel, canDrawOffice, hintKeyFor } from './mode.js';
import { fitPage, orientationOf, canvasScale, spreadFits } from './layout.js';
import { swipeDirection } from './gestures.js';
import { createTouchZoom } from './touch-zoom.js';
import { createStatus } from './status.js';
import { svgIcon } from './dom.js';
import { budgetFor, clearFailure, safeStorage, isBenignRenderError } from './capability.js';
import { readNativeEnvironment, openPptx } from './native-pptx.js';
import { openXlsx } from './native-xlsx.js';
import { openDocx } from './native-docx.js';
import { withTimeout, rememberFailure, acquireSource, buildPageEls, mountWindow } from './page-source.js';

const FLIP_SRC = new URL( './vendor/page-flip.browser.js', import.meta.url ).href;

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
export function createViewer( { stage, status, pdfjs, limits, units = null, selectableText = false, onGeometry, onFlip, onSwipe = null } ) {
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	// Touch devices wider than 700px (a rotated phone, an unfolded foldable) get the desktop dialog: the book is
	// then driven by swipe and pinch instead of the mouse, and one or two pages follow the space (DPD-024).
	const coarse = window.matchMedia( '(pointer: coarse)' );
	// The book is not a live region: dozens of page labels would be announced at once.
	// Loading and "can't be previewed" are announced by the status element (role="status").
	stage.removeAttribute( 'aria-live' );
	const statusView = createStatus( { stage, status } );
	const setStatus = ( kind ) => statusView.set( kind );

	let session = 0;
	let unit = 'page';
	let words = wording( 'page', units );
	let pptx = null;
	let docx = null;
	let pptxBusy = false;
	let pptxCount = 0;
	let grid = null;
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

		const zoomIn = document.createElement( 'button' );
		zoomIn.type = 'button';
		zoomIn.className = 'documents-modal__zoom-button';
		zoomIn.dataset.zoom = 'in';
		zoomIn.setAttribute( 'aria-label', 'Zoom in' );
		zoomIn.title = 'Zoom in';
		zoomIn.appendChild( svgIcon( 'M12 5v14M5 12h14' ) );

		const zoomOut = document.createElement( 'button' );
		zoomOut.type = 'button';
		zoomOut.className = 'documents-modal__zoom-button';
		zoomOut.dataset.zoom = 'out';
		zoomOut.setAttribute( 'aria-label', 'Zoom out' );
		zoomOut.title = 'Zoom out';
		zoomOut.appendChild( svgIcon( 'M5 12h14' ) );

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
		pageEls = buildPageEls( count, ( index, total ) => pieceLabel( words, index, total ) );
		// StPageFlip bends a 'soft' page like paper.
		pageEls.forEach( ( page ) => {
			page.dataset.density = 'soft';
		} );
	};

	const ensureWindow = ( page ) => {
		if ( ! source || ! layout ) {
			return Promise.resolve();
		}
		return mountWindow( { source, layout, pageEls, page, step, ahead: budget.ahead } );
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
		touchZoom.reset();
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
			showPageCorners: ! reducedMotion.matches && ! source.textLayer && ! coarse.matches,
			useMouseEvents: ! source.textLayer && ! coarse.matches,
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
			touchZoom.reset();
			draw( page );
			onFlip( page );
		} );
		builtFor = size;
		return true;
	};

	/** One or two pages: by the document alone for a mouse, by the space available on a touch device (DPD-024). */
	const stepForStage = ( src ) => {
		const room = measure();
		if ( coarse.matches && room.w >= 40 && room.h >= 40 ) {
			return spreadFits( { stageW: room.w, stageH: room.h, ratio: src.ratio, pages: src.count } ) ? 2 : 1;
		}
		return stepFor( src.count, orientationOf( src.ratio, 1 ) );
	};

	// Touch on the book (coarse pointers only): swipe turns, pinch and double-tap zoom, a drag pans a zoomed book.
	// StPageFlip's own mouse and corner handling is off there (see buildBook), so nothing turns twice.
	const touchZoom = createTouchZoom( {
		box: () => ( container ? { w: container.offsetWidth, h: container.offsetHeight } : { w: 1, h: 1 } ),
		apply: ( zoom ) => {
			if ( container ) {
				container.style.transformOrigin = '0 0';
				container.style.transform = zoom.scale === 1 ? '' : `translate(${ zoom.x }px, ${ zoom.y }px) scale(${ zoom.scale })`;
			}
		},
	} );
	const bindTouch = () => {
		const pointers = new Map();
		let gesture = null; // 'press' | 'pinch' | 'pan'
		// Position relative to the book's untransformed top left corner (only for pointer positions, never sizes:
		// the modal's opening animation moves the rect).
		const local = ( event ) => {
			const rect = stage.getClientRects()[ 0 ] || { left: 0, top: 0 };
			return {
				x: event.clientX - rect.left - ( container ? container.offsetLeft : 0 ),
				y: event.clientY - rect.top - ( container ? container.offsetTop : 0 ),
			};
		};
		const [ first, second ] = [ () => [ ...pointers.values() ][ 0 ], () => [ ...pointers.values() ][ 1 ] ];
		stage.addEventListener( 'pointerdown', ( event ) => {
			if ( ! coarse.matches || ! source || ! flip || event.pointerType === 'mouse' ) {
				return;
			}
			pointers.set( event.pointerId, { ...local( event ), sx: event.clientX, sy: event.clientY, cx: event.clientX, cy: event.clientY, t: event.timeStamp } );
			if ( pointers.size === 2 ) {
				gesture = 'pinch';
				touchZoom.startPinch( first(), second() );
			} else if ( pointers.size === 1 ) {
				gesture = 'press';
				touchZoom.startPan();
			}
		} );
		stage.addEventListener( 'pointermove', ( event ) => {
			const entry = pointers.get( event.pointerId );
			if ( ! entry || ! gesture ) {
				return;
			}
			Object.assign( entry, local( event ), { cx: event.clientX, cy: event.clientY } );
			if ( gesture === 'pinch' && pointers.size === 2 ) {
				touchZoom.movePinch( first(), second() );
			} else if ( pointers.size === 1 && touchZoom.isZoomed() ) {
				gesture = 'pan';
				touchZoom.movePan( event.clientX - entry.sx, event.clientY - entry.sy );
			}
		} );
		const end = ( event, cancelled ) => {
			const entry = pointers.get( event.pointerId );
			if ( ! entry ) {
				return;
			}
			pointers.delete( event.pointerId );
			const done = gesture;
			touchZoom.endPinch();
			if ( pointers.size > 0 ) {
				// One finger of a pinch lifted: carry on as a pan from where the other one is.
				const rest = first();
				rest.sx = rest.cx;
				rest.sy = rest.cy;
				gesture = 'press';
				touchZoom.startPan();
				return;
			}
			gesture = null;
			if ( cancelled || done !== 'press' ) {
				return;
			}
			const dx = event.clientX - entry.sx;
			const dy = event.clientY - entry.sy;
			const direction = touchZoom.isZoomed() ? 0 : swipeDirection( { dx, dy, dt: event.timeStamp - entry.t } );
			if ( direction !== 0 ) {
				if ( onSwipe ) {
					onSwipe( direction );
				}
				return;
			}
			if ( Math.hypot( dx, dy ) < 10 ) {
				touchZoom.tap( { t: event.timeStamp, x: entry.sx, y: entry.sy, zx: entry.x, zy: entry.y } );
			}
		};
		stage.addEventListener( 'pointerup', ( event ) => end( event, false ) );
		stage.addEventListener( 'pointercancel', ( event ) => end( event, true ) );
	};
	bindTouch();

	// The book fades out while the screen is turning and back in once it has settled (no fade with reduced motion).
	let fading = false;
	const fadeBook = ( hidden ) => {
		fading = hidden;
		if ( ! container || reducedMotion.matches ) {
			return;
		}
		container.style.transition = hidden ? 'opacity 120ms ease-out' : 'opacity 260ms ease-in';
		container.style.opacity = hidden ? '0' : '';
	};

	const onResize = () => {
		window.clearTimeout( resizeTimer );
		const mine = session;
		if ( source && container ) {
			const moving = measure();
			if ( Math.abs( moving.w - builtFor.w ) >= 2 || Math.abs( moving.h - builtFor.h ) >= 2 ) {
				fadeBook( true );
			}
		}
		resizeTimer = window.setTimeout( async () => {
			if ( ! source || mine !== session ) {
				return;
			}
			const size = measure();
			if ( Math.abs( size.w - builtFor.w ) < 2 && Math.abs( size.h - builtFor.h ) < 2 ) {
				fadeBook( false );
				return;
			}
			try {
				const PageFlip = await loadFlipLibrary();
				if ( ! source || mine !== session ) {
					return;
				}
				if ( coarse.matches ) {
					const nextStep = stepForStage( source );
					if ( nextStep !== step ) {
						step = nextStep;
						current = onGeometry( { pages: source.count, step } );
					}
				}
				// Canvases were drawn for the old size: draw them again at the new one.
				source.mounted().forEach( ( index ) => source.release( index, pageEls[ index - 1 ] ) );
				if ( buildBook( PageFlip, false ) ) {
					// The new book starts invisible and fades in once its pages are drawn.
					if ( fading && ! reducedMotion.matches ) {
						container.style.transition = 'none';
						container.style.opacity = '0';
					}
					await Promise.race( [ ensureWindow( current ), new Promise( ( resolve ) => window.setTimeout( resolve, 1500 ) ) ] );
					if ( mine === session && container ) {
						// Let the invisible state paint first, so the fade is seen.
						await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
						fadeBook( false );
					}
				}
			} catch ( error ) {
				fail( error, mine );
			} finally {
				fading = false;
			}
		}, 250 );
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
			// The viewer is created and loaded by the shared mount (native-pptx.js), which holds the options.
			const instance = await openPptx( {
				canvas,
				doc,
				limits,
				size: measure(),
				alive,
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
				// From here reset() can destroy it, so closing mid-load cannot leave a worker running.
				onCreate: ( created ) => {
					pptx = created;
				},
			} );
			if ( ! instance || ! alive() ) {
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
			// The viewer is created and loaded by the shared mount (native-xlsx.js), which holds the options.
			const instance = await openXlsx( {
				host: wrap,
				doc,
				limits,
				alive,
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
				// From here reset() can destroy it, so closing mid-load cannot leave a worker running.
				onCreate: ( created ) => {
					grid = created;
				},
			} );
			if ( ! instance || ! alive() ) {
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
			const instance = await openDocx( {
				host: wrap,
				doc,
				limits,
				alive,
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
				// From here reset() can free it, so closing mid-load cannot leave a worker running.
				onCreate: ( created ) => {
					docx = created;
				},
			} );
			if ( ! instance || ! alive() ) {
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
			statusView.setHintKey( hintKeyFor( doc ) );
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
				const got = await acquireSource( doc, alive, { pdfjs, limits, selectableText } );
				if ( ! alive() ) {
					if ( got ) {
						got.source.destroy();
					}
					return;
				}
				if ( got ) {
					next = got.source;
					if ( got.budget ) {
						nextBudget = got.budget;
					}
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

				step = stepForStage( source );
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
