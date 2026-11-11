/**
 * The mobile viewer (DPD-021, DPD-024): the body of the full-screen mobile modal.
 *
 * It draws a PDF one page at a time, or two side by side when the document is portrait and the space
 * allows it (spreadFits(), re-checked on every resize, so rotation, folding and split screen re-lay
 * out in place). Pages come from the same sources as the desktop book (page-source.js): stored page
 * images first, pdf.js as the fallback behind the same capability gate, and pdf.js with a real text
 * layer when Settings > Documents > Native viewer > PDF is on. Only the page-turn differs: a slide that
 * follows the finger instead of StPageFlip, whose mouse model fights touch drag, pinch and pan.
 *
 * Touch: swipe, tap the outer 20% of either side, double-tap (centre) or pinch to zoom, drag to pan a
 * zoomed page. Keys: left and right arrows turn (mobile.js), + - 0 zoom (accessibility). PDFs,
 * presentations (DPD-029), workbooks (DPD-030) and word documents (DPD-031); only an unknown kind shows
 * "can't be previewed".
 *
 * A .pptx with Native viewer > PowerPoint on is drawn from the original file on one canvas (native-pptx.js,
 * the same mount as the desktop modal, same limits) with selectable text; the turn is then a redraw in
 * place, not a slide, and the converted slides are the fallback.
 *
 * The viewer never owns the page shown in the footer: it reports geometry once and page turns made by
 * gesture (`onTurn`), and mobile.js tells it where to go (`goTo`).
 *
 * A .xlsx with Native viewer > Excel on is drawn by the library's own grid (native-xlsx.js, same limits): one
 * piece with its own scrolling, zoom and sheet tabs, so none of the page gestures or the footer apply (DPD-030).
 * A .docx with Native viewer > Word on is drawn the same way by the library's scrolling page flow
 * (native-docx.js): one piece, its own scrolling, selection and zoom (DPD-031).
 */
import { fitPage, orientationOf, canvasScale, spreadFits } from './layout.js';
import { budgetFor, clearFailure, safeStorage, isBenignRenderError } from './capability.js';
import { acquireSource, rememberFailure, withTimeout, buildPageEls, mountWindow } from './page-source.js';
import { normalisePage, turnPage } from './pager.js';
import { viewerMode, unitOf, wording, pieceLabel, positionText, canShowOnMobile, nativeAllowedOnMobile, isNativeScroll, canDrawOffice, hintKeyFor } from './mode.js';
import { readNativeEnvironment, openPptx } from './native-pptx.js';
import { openXlsx } from './native-xlsx.js';
import { openDocx } from './native-docx.js';
import { classifyTap, swipeDirection, verticalIntent, zoomPercent, ZOOM_MIN, ZOOM_MAX } from './gestures.js';
import { createTouchZoom } from './touch-zoom.js';
import { createStatus } from './status.js';
import { el, svgIcon } from './dom.js';

// The Word scroller's zoom range (the same limits as the desktop zoom buttons in viewer.js).
const NATIVE_ZOOM_MIN = 0.1;
const NATIVE_ZOOM_MAX = 4;

/**
 * @param {object}   options
 * @param {Element}  options.stage       .documents-mobile__stage (empty; everything is built inside it).
 * @param {Element}  options.status      .documents-mobile__state (loading / can't preview message).
 * @param {object}   options.pdfjs       { module, worker } URLs of the plugin's pdf.js, or {}.
 * @param {object}   options.limits      Merged gate limits.
 * @param {object}   options.units       data-units strings.
 * @param {object}   options.strings     data-mobile-strings.
 * @param {boolean}  options.selectableText  Native viewer > PDF: pdf.js with a real text layer.
 * @param {Function} options.onGeometry  ({ pages, step }) => page to show. pages 0 = nothing to show.
 * @param {Function} options.onTurn      ( page ) A page turn made by gesture, button or key.
 * @param {Function} options.onPages     Called when the round Pages button is tapped.
 * @param {Function} options.onDetails   Called when the round Info button is tapped (mobile.js opens the Details sheet).
 */
export function createMobileViewer( { stage, status, prefix = 'documents-mobile', viewPrefix = 'documents-modal', pdfjs, limits, units = null, strings = {}, selectableText = false, singlePage = false, onGeometry, onTurn, onPages = null, onDetails = null } ) {
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const statusView = createStatus( { stage, status, prefix: viewPrefix } );
	const setStatus = ( kind ) => statusView.set( kind );
	const text = ( key, fallback = '' ) => ( typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback );

	let session = 0;
	let words = wording( 'page', units );
	// Which message an empty stage shows (DPC-025): a pending collateral keeps "being prepared".
	let emptyState = 'unavailable';
	let source = null;
	let budget = null;
	let usedPdf = false;
	let landscapeDoc = false;
	// Which round buttons the footer cannot show: the details button when the footer is gone (one page, or
	// landscape), the Pages button in landscape. Set by mobile.js through setChrome().
	let chrome = { info: false, pages: false };
	let pageEls = [];
	// The native PowerPoint viewer when it draws the document: { instance, box, canvas, count } (DPD-029).
	let native = null;
	// The native Excel grid or Word scroller when it draws the document: { instance, box } (DPD-030, DPD-031).
	// It is one piece with its own scrolling and zoom, so no frame, gestures, hints or footer are built for it.
	let scrollView = null;
	let step = 1;
	let current = 1;
	let layout = null;
	let size = { w: 0, h: 0 };
	let builtFor = { w: 0, h: 0 };
	let observer = null;
	let resizeTimer = 0;
	let turning = false;
	let turnTimer = 0;
	let hintGone = false;
	let fabTimers = [];
	let ui = null;
	let zoomEl = null;

	const clearFabTimers = () => {
		fabTimers.forEach( ( timer ) => window.clearTimeout( timer ) );
		fabTimers = [];
	};

	const reset = () => {
		window.clearTimeout( resizeTimer );
		window.clearTimeout( turnTimer );
		clearFabTimers();
		if ( observer ) {
			observer.disconnect();
			observer = null;
		}
		if ( source ) {
			source.destroy();
			source = null;
		}
		native = null;
		if ( scrollView ) {
			try {
				scrollView.instance.destroy();
			} catch ( e ) {
				// Already gone.
			}
			scrollView = null;
		}
		stage.classList.remove( `${ prefix }__stage--scroll`, `${ prefix }__stage--doc` );
		stage.style.removeProperty( '--grid-bar' );
		pageEls = [];
		layout = null;
		ui = null;
		zoomEl = null;
		turning = false;
		hintGone = false;
		touchZoom.reset();
		stage.replaceChildren();
		stage.removeAttribute( 'role' );
		stage.removeAttribute( 'aria-label' );
	};

	const unavailable = () => {
		reset();
		setStatus( emptyState );
		onGeometry( { pages: 0, step: 1 } );
	};

	const announce = ( message ) => {
		if ( ui ) {
			ui.live.textContent = message;
		}
	};

	/* ---- pages ---------------------------------------------------------------------------- */

	const buildPages = ( count ) => {
		pageEls = buildPageEls( count, ( index, total ) => pieceLabel( words, index, total ), viewPrefix );
	};

	const ensureWindow = ( page ) => {
		if ( ! source || ! layout || native ) {
			return Promise.resolve();
		}
		return mountWindow( { source, layout, pageEls, page, step, ahead: budget.ahead } );
	};

	const fail = ( error, mine = session ) => {
		if ( mine !== session || isBenignRenderError( error ) ) {
			return;
		}
		if ( usedPdf || ( error && error.fromPdf ) ) {
			rememberFailure( error );
		}
		unavailable();
	};

	const draw = ( page ) => {
		const mine = session;
		return ensureWindow( page ).catch( ( error ) => fail( error, mine ) );
	};

	const measure = () => ( { w: stage.clientWidth, h: stage.clientHeight } );

	const firstOf = ( page ) => normalisePage( page, source.count, step );
	const hasPrev = () => current > 1;
	const hasNext = () => current + step - 1 < source.count;

	/* ---- view track ----------------------------------------------------------------------- */

	const makeBook = ( first ) => {
		const book = el( 'div', `${ viewPrefix }__book ${ prefix }__book` );
		book.classList.toggle( `${ viewPrefix }__book--selectable`, Boolean( source.textLayer ) );
		if ( source.crop > 0 ) {
			book.classList.add( `${ viewPrefix }__book--cropped` );
			book.style.setProperty( '--book-crop', String( source.crop ) );
		}
		const last = Math.min( source.count, first + step - 1 );
		const across = last - first + 1;
		book.style.width = `${ layout.pageW * across }px`;
		book.style.height = `${ layout.pageH }px`;
		for ( let index = first; index <= last; index++ ) {
			const page = pageEls[ index - 1 ];
			page.style.width = `${ layout.pageW }px`;
			page.style.height = `${ layout.pageH }px`;
			page.classList.toggle( `${ viewPrefix }__page--left`, step === 2 && index === first );
			page.classList.toggle( `${ viewPrefix }__page--right`, step === 2 && index === last && across === 2 );
			book.appendChild( page );
		}
		return book;
	};

	/** Scales the native canvas (drawn once) with CSS to the page the stage allows. */
	const fitNative = () => {
		if ( ! native || ! layout ) {
			return;
		}
		const ratio = native.canvas.width > 0 && native.canvas.height > 0 ? native.canvas.width / native.canvas.height : 16 / 9;
		const room = fitPage( { stageW: size.w, stageH: size.h, ratio } );
		native.canvas.style.width = `${ room.pageW }px`;
		native.canvas.style.height = `${ room.pageH }px`;
	};

	/** Builds the previous, current and next views around `current` and puts the track on the current one. */
	const renderViews = () => {
		// The native canvas is one element: only the middle view holds it, the neighbours stay empty.
		const firsts = native ? [ null, current, null ] : [ hasPrev() ? Math.max( 1, current - step ) : null, current, hasNext() ? current + step : null ];
		const views = firsts.map( ( first, position ) => {
			const view = el( 'div', `${ prefix }__view` );
			view.style.width = `${ size.w }px`;
			view.style.height = `${ size.h }px`;
			view.style.left = `${ position * size.w }px`;
			if ( first !== null ) {
				const wrap = el( 'div', `${ prefix }__zoom` );
				wrap.appendChild( native ? native.box : makeBook( first ) );
				view.appendChild( wrap );
				if ( position === 1 ) {
					zoomEl = wrap;
				}
			}
			return view;
		} );
		fitNative();
		ui.track.style.transition = 'none';
		ui.track.style.width = `${ size.w * 3 }px`;
		ui.track.style.transform = `translateX(${ -size.w }px)`;
		ui.track.replaceChildren( ...views );
		paintZoom();
	};

	const zoomText = ( percent ) => text( 'zoom', 'Zoom {n}%' ).replace( '{n}', String( percent ) );

	/** Puts the current zoom on the current view (after a change, and after the views are rebuilt). */
	const paintZoom = () => {
		const zoom = touchZoom.get();
		if ( zoomEl ) {
			zoomEl.style.transform = zoom.scale === 1 ? '' : `translate(${ zoom.x }px, ${ zoom.y }px) scale(${ zoom.scale })`;
		}
		syncControls();
	};

	// Zoom and pan state (shared with the desktop book's touch handling, touch-zoom.js).
	const touchZoom = createTouchZoom( { box: () => size, apply: paintZoom } );

	const resetZoom = ( { silent = false } = {} ) => {
		if ( touchZoom.reset() && ! silent ) {
			announce( zoomText( 100 ) );
		}
	};

	/* ---- controls: edge hints, hint text, help button ------------------------------------- */

	function dismissHint() {
		hintGone = true;
		if ( ui && ui.hint ) {
			ui.hint.hidden = true;
		}
	}

	function syncControls() {
		if ( ui && scrollView ) {
			// Only the details button: the footer is gone (one piece) and the library has its own zoom and tabs.
			ui.fab.hidden = ! chrome.info;
			ui.fabs.hidden = ui.fab.hidden;
			return;
		}
		if ( ! ui || ! source ) {
			return;
		}
		const zoomed = touchZoom.isZoomed();
		const multi = source.count > 1;
		ui.prev.hidden = ! multi || zoomed || ! hasPrev();
		ui.next.hidden = ! multi || zoomed || ! hasNext();
		ui.hint.hidden = hintGone || zoomed || ( ! multi && ! landscapeDoc );
		ui.fab.hidden = ! chrome.info;
		ui.pagesFab.hidden = ! chrome.pages;
		ui.zoomIn.disabled = touchZoom.get().scale >= ZOOM_MAX;
		ui.zoomOut.disabled = touchZoom.get().scale <= ZOOM_MIN;
		ui.fabs.hidden = ui.fab.hidden && ui.pagesFab.hidden;
	}

	/** One zoom step from a button or a key; false at the limit. */
	function zoomBy( direction ) {
		if ( ! source || ! ui || turning ) {
			return false;
		}
		dismissHint();
		if ( ! touchZoom.step( direction ) ) {
			return false;
		}
		announce( zoomText( zoomPercent( touchZoom.get().scale ) ) );
		return true;
	}

	const setFabAway = ( away ) => {
		clearFabTimers();
		ui.fabs.classList.toggle( 'is-away', away );
		if ( away ) {
			// Never stays hidden: it comes back after a quiet moment even without a swipe up.
			fabTimers.push( window.setTimeout( () => setFabAway( false ), 2000 ) );
		}
	};

	/**
	 * The round Info button and the Pages button (bottom right: the Pages button on top of the Info button).
	 * Shared by the page viewer and the native grid. Neither shows anything itself: they ask mobile.js to open
	 * the Details or Pages sheet.
	 */
	const makeDetails = () => {
		// The round "i" button opens the Details sheet (what the desktop header shows, plus category and Download).
		const fab = el( 'button', `${ prefix }__fab`, { type: 'button', 'aria-label': text( 'details', 'Document details' ), 'aria-haspopup': 'dialog' } );
		fab.appendChild( svgIcon( 'M12 11v6M12 7.5v.01', 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z' ) );
		const pagesFab = el( 'button', `${ prefix }__fab ${ prefix }__fab--pages`, { type: 'button', 'aria-label': text( 'pagesLabel', 'Pages' ), 'aria-haspopup': 'dialog' } );
		pagesFab.appendChild( svgIcon( 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z' ) );

		const fabs = el( 'div', `${ prefix }__fabs` );
		fabs.append( pagesFab, fab );
		fab.addEventListener( 'click', () => {
			dismissHint();
			if ( onDetails ) {
				onDetails();
			}
		} );
		pagesFab.addEventListener( 'click', () => {
			dismissHint();
			if ( onPages ) {
				onPages();
			}
		} );
		return { fab, pagesFab, fabs };
	};

	const buildUi = () => {
		const frame = el( 'div', `${ prefix }__frame` );
		const track = el( 'div', `${ prefix }__track` );
		frame.appendChild( track );

		const prev = el( 'button', `${ prefix }__edge ${ prefix }__edge--prev`, { type: 'button', 'aria-label': words.prev } );
		prev.appendChild( svgIcon( 'M15 5l-7 7 7 7' ) );
		const next = el( 'button', `${ prefix }__edge ${ prefix }__edge--next`, { type: 'button', 'aria-label': words.next } );
		next.appendChild( svgIcon( 'M9 5l7 7-7 7' ) );

		const hint = el( 'div', `${ prefix }__hint`, { 'aria-hidden': 'true' } );
		if ( landscapeDoc ) {
			const rotate = el( 'span', `${ prefix }__pill ${ prefix }__pill--dark` );
			rotate.append( svgIcon( 'M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5' ), document.createTextNode( text( 'rotate', 'Rotate for a bigger view' ) ) );
			const pinch = el( 'span', `${ prefix }__pill` );
			pinch.textContent = text( 'pinch', 'Pinch to zoom' );
			hint.append( rotate, pinch );
		} else {
			// Plain floating text under the document; it goes away at the first action in the body.
			const line = el( 'span', `${ prefix }__hint-text` );
			line.textContent = text( 'hint', 'swipe or tap edges' );
			hint.appendChild( line );
		}

		const { fab, pagesFab, fabs } = makeDetails();

		// Round zoom buttons, bottom left: the same steps as the + and - keys, for people who do not pinch.
		const zoomIn = el( 'button', `${ prefix }__fab`, { type: 'button', 'aria-label': text( 'zoomIn', 'Zoom in' ) } );
		zoomIn.appendChild( svgIcon( 'M12 5v14M5 12h14' ) );
		const zoomOut = el( 'button', `${ prefix }__fab`, { type: 'button', 'aria-label': text( 'zoomOut', 'Zoom out' ) } );
		zoomOut.appendChild( svgIcon( 'M5 12h14' ) );
		const zoomFabs = el( 'div', `${ prefix }__zoomfabs`, { role: 'group', 'aria-label': text( 'zoomControls', 'Zoom controls' ) } );
		zoomFabs.append( zoomIn, zoomOut );

		const live = el( 'div', `${ prefix }__live`, { 'aria-live': 'polite', role: 'status' } );

		stage.append( frame, prev, next, hint, fabs, zoomFabs, live );
		stage.setAttribute( 'role', 'region' );
		stage.setAttribute( 'aria-label', text( 'region', 'Document pages' ) );
		ui = { frame, track, prev, next, hint, fab, pagesFab, fabs, live, zoomIn, zoomOut };

		prev.addEventListener( 'click', () => turn( -1 ) );
		next.addEventListener( 'click', () => turn( 1 ) );
		zoomIn.addEventListener( 'click', () => zoomBy( 1 ) );
		zoomOut.addEventListener( 'click', () => zoomBy( -1 ) );
		bindGestures( frame );
	};

	/* ---- turning -------------------------------------------------------------------------- */

	const finishTurn = ( target ) => {
		current = target;
		resetZoom( { silent: true } );
		renderViews();
		turning = false;
		dismissHint();
		draw( current );
		announce( positionText( words, current, Math.min( source.count, current + step - 1 ), source.count ) );
		onTurn( current );
	};

	/** Moves one view in a direction (-1 / 1) with the slide; ignored at the ends and while sliding. */
	function turn( direction ) {
		if ( ! source || turning ) {
			return false;
		}
		dismissHint();
		if ( touchZoom.isZoomed() ) {
			return false;
		}
		const target = turnPage( current, source.count, step, direction );
		if ( target === current ) {
			return false;
		}
		slideTo( target, direction );
		return true;
	}

	/** Native turn: the canvas is redrawn in place (no slide), then the footer follows. */
	function nativeTurn( target ) {
		const mine = session;
		turning = true;
		resetZoom( { silent: true } );
		native.instance.goToSlide( target - 1 ).then(
			() => {
				if ( mine !== session || ! native ) {
					return;
				}
				current = target;
				turning = false;
				// The library sets the canvas's CSS size to its drawing size on every render: fit it again.
				fitNative();
				dismissHint();
				syncControls();
				announce( positionText( words, current, current, source.count ) );
				onTurn( current );
			},
			( error ) => {
				turning = false;
				fail( error, mine );
			}
		);
	}

	function slideTo( target, direction, fromDrag = false ) {
		if ( native ) {
			nativeTurn( target );
			return;
		}
		turning = true;
		if ( reducedMotion.matches ) {
			finishTurn( target );
			return;
		}
		ui.track.style.transition = fromDrag ? 'transform 160ms ease-out' : 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)';
		ui.track.style.transform = `translateX(${ direction > 0 ? -size.w * 2 : 0 }px)`;
		const mine = session;
		turnTimer = window.setTimeout( () => {
			if ( mine === session && source ) {
				finishTurn( target );
			}
		}, 230 );
	}

	/* ---- gestures ------------------------------------------------------------------------- */

	function bindGestures( frame ) {
		const pointers = new Map();
		let gesture = null; // 'press' | 'drag' | 'pan' | 'pinch'
		// The frame never moves while a gesture runs, so its rect is read once per gesture, not per event.
		let origin = { left: 0, top: 0 };
		const point = ( event ) => ( { x: event.clientX - origin.left, y: event.clientY - origin.top } );
		const fingers = () => [ ...pointers.values() ];

		frame.addEventListener( 'pointerdown', ( event ) => {
			// Any touch in the body counts as having read the hint.
			dismissHint();
			if ( ! source || turning ) {
				return;
			}
				if ( pointers.size === 0 ) {
				origin = frame.getBoundingClientRect();
			}
			const start = point( event );
			pointers.set( event.pointerId, { ...start, sx: start.x, sy: start.y, t: event.timeStamp } );
			if ( pointers.size === 1 ) {
				gesture = 'press';
				touchZoom.startPan();
			} else if ( pointers.size === 2 ) {
				gesture = 'pinch';
				touchZoom.startPinch( ...fingers() );
			}
		} );

		frame.addEventListener( 'pointermove', ( event ) => {
			const entry = pointers.get( event.pointerId );
			if ( ! entry || ! gesture ) {
				return;
			}
			Object.assign( entry, point( event ) );
			if ( gesture === 'pinch' && pointers.size === 2 ) {
				touchZoom.movePinch( ...fingers() );
				dismissHint();
				return;
			}
			if ( pointers.size !== 1 ) {
				return;
			}
			const dx = entry.x - entry.sx;
			const dy = entry.y - entry.sy;
			if ( touchZoom.isZoomed() ) {
				gesture = 'pan';
				touchZoom.movePan( dx, dy );
				return;
			}
			if ( gesture === 'press' && Math.abs( dx ) > 10 && Math.abs( dx ) > Math.abs( dy ) && source.count > 1 ) {
				gesture = 'drag';
				ui.track.style.transition = 'none';
				frame.setPointerCapture && frame.setPointerCapture( event.pointerId );
			}
			if ( gesture === 'drag' && ! native ) {
				// Resistance when there is nothing in that direction.
				const blocked = ( dx > 0 && ! hasPrev() ) || ( dx < 0 && ! hasNext() );
				ui.track.style.transform = `translateX(${ -size.w + dx * ( blocked ? 0.25 : 1 ) }px)`;
			}
		} );

		const finish = ( event, cancelled ) => {
			const entry = pointers.get( event.pointerId );
			if ( ! entry ) {
				return;
			}
			pointers.delete( event.pointerId );
			const done = gesture;
			touchZoom.endPinch();
			if ( pointers.size > 0 ) {
				// One finger of a pinch lifted: carry on as a pan from here.
				const rest = fingers()[ 0 ];
				rest.sx = rest.x;
				rest.sy = rest.y;
				gesture = 'press';
				touchZoom.startPan();
				return;
			}
			gesture = null;
			if ( ! done ) {
				return;
			}
			if ( cancelled ) {
				if ( done === 'drag' ) {
					slideBack();
				}
				return;
			}
			const dx = entry.x - entry.sx;
			const dy = entry.y - entry.sy;

			// Vertical drags move the round buttons out of the way and bring them back.
			const intent = verticalIntent( { dx, dy } );
			if ( intent === 'down' ) {
				setFabAway( true );
			} else if ( intent === 'up' ) {
				clearFabTimers();
				fabTimers.push( window.setTimeout( () => setFabAway( false ), 300 ) );
			}

			if ( done === 'drag' ) {
				const direction = swipeDirection( { dx, dy, dt: event.timeStamp - entry.t } );
				const target = direction !== 0 ? turnPage( current, source.count, step, direction ) : current;
				if ( direction !== 0 && target !== current ) {
					slideTo( target, direction, true );
				} else {
					slideBack();
				}
				return;
			}
			if ( done !== 'press' ) {
				return;
			}
			const kind = classifyTap( { x: entry.sx, w: size.w, t0: entry.t, t1: event.timeStamp, moved: Math.hypot( dx, dy ) } );
			if ( kind === 'edge-prev' || kind === 'edge-next' ) {
				touchZoom.forgetTap();
				turn( kind === 'edge-next' ? 1 : -1 );
			} else if ( kind === 'centre' && touchZoom.tap( { t: event.timeStamp, x: entry.sx, y: entry.sy } ) ) {
				announce( zoomText( zoomPercent( touchZoom.get().scale ) ) );
			}
		};
		frame.addEventListener( 'pointerup', ( event ) => finish( event, false ) );
		frame.addEventListener( 'pointercancel', ( event ) => finish( event, true ) );
	}

	function slideBack() {
		if ( ! ui ) {
			return;
		}
		ui.track.style.transition = reducedMotion.matches ? 'none' : 'transform 160ms ease-out';
		ui.track.style.transform = `translateX(${ -size.w }px)`;
	}

	/* ---- layout and resize ---------------------------------------------------------------- */

	/** Picks the step for this stage and builds the layout; returns false when the stage has no size. */
	const computeLayout = () => {
		size = measure();
		if ( size.w < 40 || size.h < 40 ) {
			return false;
		}
		step = ! native && ! singlePage && spreadFits( { stageW: size.w, stageH: size.h, ratio: source.ratio, pages: source.count } ) ? 2 : 1;
		const { pageW, pageH } = fitPage( { stageW: size.w, stageH: size.h, ratio: source.ratio, step, maxPageW: source.maxPageW } );
		layout = {
			pageW,
			pageH,
			scale: canvasScale( { pageW, pageH, dpr: window.devicePixelRatio, dprCap: budget.dprCap, maxPixels: budget.maxPixels } ),
		};
		builtFor = size;
		return true;
	};

	/**
	 * Fades the pages out while the screen is turning and back in once it has settled and the pages are
	 * redrawn, so a rotation never shows a half-built layout. No fade with reduced motion.
	 */
	const fade = ( hidden ) => {
		if ( ! ui || reducedMotion.matches ) {
			return;
		}
		[ ui.frame, ui.prev, ui.next ].forEach( ( node ) => {
			node.style.transition = hidden ? 'opacity 120ms ease-out' : 'opacity 260ms ease-in';
			node.style.opacity = hidden ? '0' : '';
		} );
	};

	const onResize = () => {
		window.clearTimeout( resizeTimer );
		const mine = session;
		if ( source && ui ) {
			const moving = measure();
			if ( Math.abs( moving.w - builtFor.w ) >= 2 || Math.abs( moving.h - builtFor.h ) >= 2 ) {
				fade( true );
			}
		}
		// Wait until the size stops changing (the rotation has finished) before rebuilding.
		resizeTimer = window.setTimeout( async () => {
			if ( ! source || mine !== session || ! ui ) {
				return;
			}
			const now = measure();
			if ( Math.abs( now.w - builtFor.w ) < 2 && Math.abs( now.h - builtFor.h ) < 2 ) {
				fade( false );
				return;
			}
			const before = step;
			try {
				source.mounted().forEach( ( index ) => source.release( index, pageEls[ index - 1 ] ) );
				if ( ! computeLayout() ) {
					return;
				}
				if ( step !== before ) {
					// A spread always starts on an odd page; the footer learns the new step.
					current = onGeometry( { pages: source.count, step } );
					current = firstOf( current );
				}
				resetZoom( { silent: true } );
				renderViews();
				// Fade in when the pages are drawn, but never wait longer than a moment for a slow one.
				await Promise.race( [ ensureWindow( current ), new Promise( ( resolve ) => window.setTimeout( resolve, 1500 ) ) ] );
			} catch ( error ) {
				fail( error, mine );
			} finally {
				if ( mine === session ) {
					fade( false );
				}
			}
		}, 250 );
	};

	/* ---- native PowerPoint (DPD-029) ------------------------------------------------------ */

	/**
	 * Draws a .pptx from the original file with the bundled viewer (the same mount as the desktop modal).
	 * The canvas is one element inside the normal frame, so gestures, zoom, hint, footer and round buttons
	 * are the ones of the image viewer. Returns false when it cannot be drawn: the caller then shows the
	 * stored slides, or "can't be previewed".
	 */
	const showNative = async ( doc, alive ) => {
		if ( ! canDrawOffice( readNativeEnvironment() ) ) {
			return false;
		}
		const mine = session;
		let opened = false;
		let instance = null;
		const box = el( 'div', `${ prefix }__native` );
		const canvas = el( 'canvas', `${ viewPrefix }__native-canvas`, { role: 'img', 'aria-label': doc.title || '' } );
		box.appendChild( canvas );
		try {
			if ( ! measure().w ) {
				await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
			}
			instance = await openPptx( {
				canvas,
				doc,
				limits,
				size: measure(),
				alive,
				onSlideChange: ( index, total ) => {
					if ( mine === session ) {
						canvas.setAttribute( 'aria-label', pieceLabel( words, index + 1, total || index + 1 ) );
						fitNative();
					}
				},
				onError: ( error ) => {
					if ( opened ) {
						fail( error, mine );
					}
				},
				// From here reset() can free it, so closing mid-load cannot leave a worker running.
				onCreate: ( created ) => {
					instance = created;
					source = {
						count: 0,
						ratio: 16 / 9,
						maxPageW: undefined,
						crop: 0,
						kind: 'native',
						mounted: () => [],
						release: () => {},
						destroy: () => {
							try {
								created.destroy();
							} catch ( e ) {
								// Already gone.
							}
						},
					};
				},
			} );
			if ( ! instance || ! alive() ) {
				return false;
			}
			const count = instance.slideCount;
			if ( ! ( count > 0 ) ) {
				throw new Error( 'empty deck' );
			}
			source.count = count;
			if ( canvas.width > 0 && canvas.height > 0 ) {
				source.ratio = canvas.width / canvas.height;
			}
			native = { instance, box, canvas };
			landscapeDoc = true;
			step = 1;
			buildUi();
			if ( ! computeLayout() ) {
				await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
				if ( ! alive() ) {
					return false;
				}
				if ( ! computeLayout() ) {
					throw new Error( 'stage has no size' );
				}
			}
			current = firstOf( onGeometry( { pages: count, step } ) );
			if ( current > 1 ) {
				await instance.goToSlide( current - 1 );
				if ( ! alive() ) {
					return false;
				}
			}
			renderViews();
			setStatus( null );
			syncControls();
			opened = true;
			observer = new ResizeObserver( onResize );
			observer.observe( stage );
			return true;
		} catch ( error ) {
			if ( alive() ) {
				// Leave nothing of the failed attempt behind: the caller shows the stored slides or says it can't preview.
				reset();
			}
			return false;
		}
	};

	/* ---- native Excel (DPD-030) ----------------------------------------------------------- */

	/**
	 * Draws a .xlsx (the library's own grid) or a .docx (its scrolling page flow) from the original file, the
	 * same mounts as the desktop modal (native-xlsx.js, native-docx.js, same limits). Both bring their own
	 * scrolling, zoom and selection, so each is mounted OUTSIDE the swipe/zoom frame: no page gestures, edge
	 * taps or hints are built, and it reports one piece, so the footer and progress bar stay hidden, as on
	 * desktop. The details button is kept. A workbook's tab bar is lifted clear of it (CSS,
	 * --grid-bar); a document gets round zoom buttons wired to the library's zoom instead.
	 * Returns false when it cannot be drawn: the caller then shows the stored pages, or "can't be previewed".
	 */
	const showScroll = async ( doc, alive ) => {
		if ( ! canDrawOffice( readNativeEnvironment() ) ) {
			return false;
		}
		const isDoc = doc.kind === 'docx';
		const mine = session;
		let opened = false;
		let instance = null;
		const box = el( 'div', `${ prefix }__scroll${ isDoc ? ` ${ prefix }__scroll--doc` : '' }`, { role: 'group', 'aria-label': doc.title || '' } );
		box.style.visibility = 'hidden';
		const live = el( 'div', `${ prefix }__live`, { 'aria-live': 'polite', role: 'status' } );
		const { fab, pagesFab, fabs } = makeDetails();
		pagesFab.hidden = true;
		stage.classList.add( `${ prefix }__stage--scroll` );
		stage.classList.toggle( `${ prefix }__stage--doc`, isDoc );
		stage.append( box, fabs, live );
		ui = { fab, pagesFab, fabs, live };

		/** A document: round zoom buttons call the library's zoom (text stays sharp, selection stays aligned). */
		const buildZoom = () => {
			const zoomIn = el( 'button', `${ prefix }__fab`, { type: 'button', 'aria-label': text( 'zoomIn', 'Zoom in' ) } );
			zoomIn.appendChild( svgIcon( 'M12 5v14M5 12h14' ) );
			const zoomOut = el( 'button', `${ prefix }__fab`, { type: 'button', 'aria-label': text( 'zoomOut', 'Zoom out' ) } );
			zoomOut.appendChild( svgIcon( 'M5 12h14' ) );
			const group = el( 'div', `${ prefix }__zoomfabs`, { role: 'group', 'aria-label': text( 'zoomControls', 'Zoom controls' ) } );
			group.append( zoomIn, zoomOut );
			const sync = () => {
				if ( typeof instance.getScale === 'function' ) {
					const scale = instance.getScale();
					zoomOut.disabled = scale <= NATIVE_ZOOM_MIN;
					zoomIn.disabled = scale >= NATIVE_ZOOM_MAX;
				}
			};
			const stepBy = ( method ) => () => {
				if ( instance && typeof instance[ method ] === 'function' ) {
					instance[ method ]();
					sync();
				}
			};
			zoomIn.addEventListener( 'click', stepBy( 'zoomIn' ) );
			zoomOut.addEventListener( 'click', stepBy( 'zoomOut' ) );
			stage.appendChild( group );
			sync();
		};

		/** A workbook's library tab bar is its only chrome at the bottom: keep the buttons clear of it. */
		const syncBar = () => {
			const strip = box.querySelector( '.xlsx-tab-strip' );
			const bar = strip && strip.parentElement ? strip.parentElement.offsetHeight : 0;
			if ( bar > 0 ) {
				stage.style.setProperty( '--grid-bar', `${ bar }px` );
			}
		};

		try {
			instance = await ( isDoc ? openDocx : openXlsx )( {
				host: box,
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
					scrollView = { instance: created, box };
				},
			} );
			if ( ! instance || ! alive() ) {
				return false;
			}
			step = 1;
			current = onGeometry( { pages: 1, step } );
			box.style.visibility = '';
			setStatus( null );
			syncControls();
			if ( isDoc ) {
				buildZoom();
			} else {
				syncBar();
				observer = new ResizeObserver( syncBar );
				observer.observe( box );
			}
			opened = true;
			return true;
		} catch ( error ) {
			if ( alive() ) {
				// Leave nothing of the failed attempt behind: the caller shows the stored sheets or says it can't preview.
				reset();
			}
			return false;
		}
	};

	/* ---- public --------------------------------------------------------------------------- */

	return {
		/** Shows a document. Resolves when the first view is drawn (or the stage says it can't be previewed). */
		async show( doc, page = 1 ) {
			reset();
			const mine = ++session;
			const alive = () => mine === session;
			const unit = unitOf( doc );
			statusView.setHintKey( hintKeyFor( doc ) );
			emptyState = doc.previewState === 'pending' ? 'pending' : 'unavailable';
			words = wording( unit, units );
			current = page;
			usedPdf = false;
			budget = budgetFor( 'full', limits );

			if ( ! canShowOnMobile( doc ) ) {
				// An unknown kind: nothing to draw.
				unavailable();
				return;
			}
			setStatus( 'loading' );

			if ( nativeAllowedOnMobile( doc ) && viewerMode( doc ) === 'native' ) {
				const drawn = await ( isNativeScroll( doc ) ? showScroll( doc, alive ) : showNative( doc, alive ) );
				if ( drawn || ! alive() ) {
					return;
				}
				// The native viewer could not draw it: use the stored slides when there are some.
				if ( ! Array.isArray( doc.images ) || ! doc.images.length ) {
					unavailable();
					return;
				}
				setStatus( 'loading' );
			}

			let got = null;
			try {
				got = await acquireSource( doc, alive, { pdfjs, limits, selectableText, prefix: viewPrefix } );
				if ( ! alive() ) {
					if ( got ) {
						got.source.destroy();
					}
					return;
				}
				if ( ! got ) {
					unavailable();
					return;
				}
				source = got.source;
				if ( got.budget ) {
					budget = got.budget;
				}
				usedPdf = source.kind === 'pdf';
				landscapeDoc = orientationOf( source.ratio, 1 ) === 'landscape';
				buildPages( source.count );
				buildUi();

				// The dialog has only just opened: wait a frame for the stage to have a size.
				if ( ! computeLayout() ) {
					await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
					if ( ! alive() ) {
						return;
					}
					if ( ! computeLayout() ) {
						throw new Error( 'stage has no size' );
					}
				}
				current = firstOf( onGeometry( { pages: source.count, step } ) );
				renderViews();
				await withTimeout( ensureWindow( current ), limits.firstPageTimeoutMs );
				if ( ! alive() ) {
					return;
				}
				if ( usedPdf ) {
					clearFailure( safeStorage() );
				}
				setStatus( null );
				syncControls();
				observer = new ResizeObserver( onResize );
				observer.observe( stage );
			} catch ( error ) {
				if ( alive() ) {
					if ( got && ! source ) {
						got.source.destroy();
					}
					fail( error );
				}
			}
		},

		/** Turns one view (-1 previous, 1 next). */
		turn,

		/** Which round buttons to show because the footer cannot: `{ info, pages }`. */
		setChrome( next ) {
			chrome = { info: Boolean( next.info ), pages: Boolean( next.pages ) };
			syncControls();
		},

		/** True while a view is sliding, so callers can ignore a second turn. */
		isTurning: () => turning,

		/** Jumps to a page (used by mobile.js for programmatic changes, e.g. a later Pages sheet). */
		goTo( page ) {
			if ( ! source || ! ui ) {
				current = page;
				return;
			}
			const target = firstOf( page );
			if ( target === current ) {
				return;
			}
			window.clearTimeout( turnTimer );
			turning = false;
			resetZoom( { silent: true } );
			if ( native ) {
				nativeTurn( target );
				return;
			}
			current = target;
			renderViews();
			draw( current );
		},

		/** Keyboard zoom: +1 in, -1 out (half steps), centred on the stage. */
		zoomStep( direction ) {
			zoomBy( direction );
		},


		resetZoom: () => resetZoom(),

		/** Closes the viewer and frees pages, canvases and the PDF. */
		destroy() {
			session++;
			reset();
			setStatus( null );
		},
	};
}
