/**
 * The mobile video viewer (DPV-011, DPV-019 to DPV-023): fills `dialog.videos-mobile`, plays through the shared
 * engine (player.js) with the controls the settings allow (controls/), reads touches through mobile-gestures.js
 * and owns the details sheet. It knows nothing about the URL: view.js decides what opening, changing and
 * closing do to the history.
 *
 * Changing video is a slide: the visible video and its overlays follow the finger, the next (or previous)
 * video's poster comes up from the edge (`peek`), and a release past a third of the screen, or a flick, completes
 * the slide. Otherwise the video springs back. The new video is mounted once it has left the screen.
 */
import { createSheetController } from '../shared/sheet-controller.js';
import { DOUBLE_TAP_WINDOW } from '../shared/gestures.js';
import { el } from '../shared/dom.js';
import { createPlayer } from './player.js';
import { mountControls } from './controls/index.js';
import {
	TAP_MAX_MOVE,
	TOP_EDGE,
	classifyStroke,
	tapAction,
	dragOffset,
	peekOffset,
	shouldCommit,
} from './mobile-gestures.js';
import { neighbours } from './neighbours.js';
import { SEEK_STEP } from './player-model.js';
import { preferences } from './preferences.js';

const WIDE_RATIOS = [ '16x9', '4x3' ];
const SHAPES = { '9x16': 'tall', '1x1': 'square' }; // any other ratio is 'wide'
const FLASH_MS = 700;
const SLIDE_MS = 240;
const SLIDE_EASE = 'cubic-bezier(0.2, 0.8, 0.2, 1)';
const PEEK_MAX_MS = 600; // the peek leaves at the incoming video's first frame, or after this

/**
 * @param {Object}   args
 * @param {Element}  args.dialog    The mobile dialog.
 * @param {number[]} args.order     Ids in the grid's order.
 * @param {Object}   args.items     Payload items by id.
 * @param {Object}   args.strings   `data-mobile-strings`.
 * @param {{loop?:boolean,doubleTapSkip?:boolean,unmuteOnPlay?:boolean,scrub?:boolean,controls?:string[]}} args.options `data-mobile-options` (Settings → Videos).
 * @param {Function} args.getFilter Returns the active category slug ('' = all).
 * @param {Function} args.onGo      Called with a video id once the slide has brought the visitor there (▲ ▼, swipe).
 * @param {Function} args.onClose   Called when the visitor asks to close (✕, swipe down from the top edge).
 */
export function createMobileVideo( { dialog, order, items, strings, options = {}, getFilter, onGo, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const frame = $( '.videos-mobile__video' );
	const stage = $( '.videos-mobile__stage' );
	const rail = $( '.videos-mobile__rail' );
	const up = $( '.videos-mobile__up' );
	const down = $( '.videos-mobile__down' );
	const titleEl = $( '.videos-mobile__title' );
	const titleText = $( '.videos-mobile__title-text' );
	const detailsEl = $( '.videos-mobile__details' );
	const hint = $( '.videos-mobile__hint' );
	const flashEl = $( '.videos-mobile__flash' );

	// The incoming video during a slide: its poster, a screen away until the slide brings it in.
	const peek = el( 'div', 'videos-mobile__peek', { 'aria-hidden': 'true' } );
	peek.hidden = true;
	frame.before( peek );

	const player = createPlayer( dialog, strings, {
		prefix: 'videos-mobile',
		clickToggles: false,
		loop: options.loop !== false,
		unmuteOnPlay: options.unmuteOnPlay === true,
		quietLoading: true,
	} );
	mountControls( { dialog, prefix: 'videos-mobile', player, strings, keys: options.controls || [], seek: options.scrub === true } );

	// Created here, at start, never inside a lazy import: its history listener must precede the Router's.
	const sheets = createSheetController( { dialog } );
	sheets.register( 'details', {
		closeOnRotate: false,
		create: async ( { dialog: host, onClose: closed } ) => {
			const { createDetailsSheet } = await import( './details-sheet.js' );
			return createDetailsSheet( { dialog: host, strings, onClose: closed } );
		},
	} );

	let current = null;
	let lastTap = null; // { x, t } of the previous tap
	let tapTimer = 0;
	let flashTimer = 0;
	let slideTimer = 0;
	let peekTimer = 0;
	let stopWatch = null; // unsubscribes the wait for the incoming video's first frame
	let busy = false; // a slide is running
	let stroke = null; // { x0, y0, t0, id } of the touch in progress
	let drag = null; // { kind, target, dy } once a vertical drag has started

	const near = () => neighbours( order, items, current.id, getFilter() );
	const frameHeight = () => frame.clientHeight;
	const reducedMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	const drawRail = () => {
		const { prev, next, count } = near();
		rail.hidden = count < 2;
		up.setAttribute( 'aria-disabled', String( prev === null ) );
		down.setAttribute( 'aria-disabled', String( next === null ) );
	};

	/** While a drag or slide runs, the controls and overlays fade out; they fade back in once it has settled. */
	const setSliding = ( on ) => dialog.toggleAttribute( 'data-sliding', on );

	/**
	 * Moves the video (the stage only: the controls stay on screen) to `offset`, and the peek to `peekAt` (px).
	 * `animate` slides them.
	 */
	const place = ( offset, peekAt, animate ) => {
		const transition = animate ? `transform ${ SLIDE_MS }ms ${ SLIDE_EASE }` : 'none';
		stage.style.transition = transition;
		peek.style.transition = transition;
		stage.style.transform = offset ? `translateY(${ offset }px)` : '';
		peek.style.transform = peekAt ? `translateY(${ peekAt }px)` : '';
	};

	const showPeek = ( item ) => {
		peek.replaceChildren();
		peek.dataset.fit = preferences.fit; // the peek is framed like the video that will replace it
		if ( item.poster ) {
			peek.append( el( 'img', null, { src: item.poster, alt: '' } ) );
		}
		peek.hidden = false;
	};

	/** Loads the posters either side now, so a slide never starts with an empty peek. */
	const preloadNeighbours = () => {
		const { prev, next } = near();
		[ prev, next ].forEach( ( id ) => {
			if ( id !== null && items[ id ].poster ) {
				const image = new Image();
				image.src = items[ id ].poster;
			}
		} );
	};

	/** Removes the peek and stops waiting for the incoming video. */
	const dropPeek = () => {
		window.clearTimeout( peekTimer );
		peekTimer = 0;
		if ( stopWatch ) {
			stopWatch();
			stopWatch = null;
		}
		peek.hidden = true;
	};

	/**
	 * The incoming video is in place under the peek; the peek leaves when that video shows its first frame (the
	 * time update), or after PEEK_MAX_MS, so there is no gap between the poster and the video.
	 */
	const retirePeek = () => {
		stopWatch = player.on( 'time', dropPeek );
		peekTimer = window.setTimeout( dropPeek, PEEK_MAX_MS );
	};

	/** Ends the slide's movement: `done` runs once it has finished (at once with reduced motion). */
	const settle = ( offset, peekAt, done ) => {
		const instant = reducedMotion();
		place( offset, peekAt, ! instant );
		if ( instant ) {
			done();
			return;
		}
		slideTimer = window.setTimeout( () => {
			slideTimer = 0;
			done();
		}, SLIDE_MS + 20 );
	};

	/** Stops any slide and puts the viewer at rest (a new video, closing). */
	const rest = () => {
		window.clearTimeout( slideTimer );
		slideTimer = 0;
		busy = false;
		drag = null;
		place( 0, 0, false );
		setSliding( false );
	};

	/** Slides out to the neighbour `id` (`kind`: 'next' or 'prev') from the offset `from`, then goes to it. */
	const slide = ( kind, id, from ) => {
		const height = frameHeight();
		busy = true;
		setSliding( true );
		showPeek( items[ id ] );
		place( from, peekOffset( kind, from, height ), false );
		void frame.offsetHeight; // start the slide from where the finger left it
		settle( kind === 'next' ? -height : height, 0, () => {
			rest();
			if ( dialog.open ) {
				onGo( id );
				retirePeek();
			} else {
				dropPeek();
			}
		} );
	};

	/** Brings the video back: the visible one to rest, the peeked poster away again. */
	const springBack = ( kind, hasTarget ) => {
		const height = frameHeight();
		busy = true;
		settle( 0, hasTarget ? ( kind === 'next' ? height : -height ) : 0, () => {
			rest();
			dropPeek();
		} );
	};

	const cancelTap = () => {
		window.clearTimeout( tapTimer );
		lastTap = null;
	};

	const flash = ( kind ) => {
		flashEl.dataset.kind = kind;
		flashEl.classList.remove( 'is-on' );
		void flashEl.offsetWidth; // restart the animation
		flashEl.classList.add( 'is-on' );
		window.clearTimeout( flashTimer );
		flashTimer = window.setTimeout( () => flashEl.classList.remove( 'is-on' ), FLASH_MS );
	};

	const onTap = ( x ) => {
		hint.hidden = true;
		player.wake();
		const action = tapAction( { x, width: stage.clientWidth, now: Date.now(), last: lastTap, skip: options.doubleTapSkip !== false } );
		window.clearTimeout( tapTimer );
		if ( action.kind === 'skip' ) {
			lastTap = null;
			player.skip( action.seconds );
			flash( action.seconds > 0 ? 'forward' : 'back' );
		} else if ( action.kind === 'toggle-now' ) {
			player.toggle();
		} else if ( action.kind === 'toggle-later' ) {
			lastTap = { x, t: Date.now() };
			tapTimer = window.setTimeout( () => {
				lastTap = null;
				player.toggle();
			}, DOUBLE_TAP_WINDOW );
		} else {
			lastTap = null;
		}
	};

	/** @param {'prev'|'next'} which The rail's ▲ (prev) or ▼ (next). */
	const goTo = ( which ) => {
		if ( ! current || busy || sheets.isAnyOpen() ) {
			return;
		}
		const id = near()[ which ];
		if ( id !== null ) {
			slide( which, id, 0 );
		}
	};

	// Touches on the stage only; every control is a sibling painted above it (D5).
	stage.addEventListener( 'pointerdown', ( event ) => {
		if ( busy ) {
			return;
		}
		stroke = { x0: event.clientX, y0: event.clientY, t0: event.timeStamp, id: event.pointerId };
	} );

	stage.addEventListener( 'pointermove', ( event ) => {
		if ( ! stroke || stroke.id !== event.pointerId || sheets.isAnyOpen() ) {
			return;
		}
		const dx = event.clientX - stroke.x0;
		const dy = event.clientY - stroke.y0;
		if ( ! drag ) {
			// Not a drag until the finger has moved, mostly up or down.
			if ( Math.hypot( dx, dy ) < TAP_MAX_MOVE || Math.abs( dy ) <= Math.abs( dx ) * 1.5 ) {
				return;
			}
			// A swipe down from the top edge closes the viewer on release (see below), so it does not slide.
			if ( dy > 0 && stroke.y0 <= TOP_EDGE ) {
				return;
			}
			const kind = dy < 0 ? 'next' : 'prev';
			const target = near()[ kind ];
			drag = { kind, target, dy: 0 };
			hint.hidden = true;
			setSliding( true );
			stage.setPointerCapture( event.pointerId );
			if ( target !== null ) {
				showPeek( items[ target ] );
			}
		}
		const hasTarget = drag.target !== null;
		const offset = dragOffset( dy, hasTarget );
		drag.dy = offset;
		place( offset, hasTarget ? peekOffset( drag.kind, offset, frameHeight() ) : 0, false );
	} );

	stage.addEventListener( 'pointerup', ( event ) => {
		const start = stroke;
		stroke = null;
		if ( ! start || start.id !== event.pointerId ) {
			return;
		}
		const dt = event.timeStamp - start.t0;
		const kind = classifyStroke( { x0: start.x0, y0: start.y0, x1: event.clientX, y1: event.clientY, dt } );

		if ( drag ) {
			const { kind: dragKind, target, dy } = drag;
			drag = null;
			if ( shouldCommit( { dy, dt, height: frameHeight(), kind: dragKind, hasTarget: target !== null } ) ) {
				slide( dragKind, target, dy );
			} else {
				springBack( dragKind, target !== null );
			}
			return;
		}
		if ( sheets.isAnyOpen() ) {
			return;
		}
		if ( kind === 'tap' ) {
			onTap( event.clientX - stage.getBoundingClientRect().left );
		} else if ( kind === 'next' || kind === 'prev' ) {
			// A flick that never moved the video (no pointer move was delivered) still changes video.
			hint.hidden = true;
			goTo( kind );
		} else if ( kind === 'close' ) {
			onClose();
		}
	} );

	stage.addEventListener( 'pointercancel', () => {
		stroke = null;
		if ( drag ) {
			const { kind, target } = drag;
			drag = null;
			springBack( kind, target !== null );
		}
	} );

	$( '.videos-mobile__close' ).addEventListener( 'click', () => onClose() );
	up.addEventListener( 'click', () => goTo( 'prev' ) );
	down.addEventListener( 'click', () => goTo( 'next' ) );
	const openDetails = () => {
		if ( current ) {
			hint.hidden = true;
			sheets.open( 'details', { doc: current, opener: document.activeElement } );
		}
	};
	titleEl.addEventListener( 'click', openDetails );
	detailsEl.addEventListener( 'click', openDetails );

	// Space, the progress keys and Escape on a popover belong to the engine and the controls (D14).
	const KEYS = {
		ArrowUp: () => goTo( 'prev' ),
		ArrowDown: () => goTo( 'next' ),
		ArrowLeft: () => player.skip( -SEEK_STEP ),
		ArrowRight: () => player.skip( SEEK_STEP ),
	};
	dialog.addEventListener( 'keydown', ( event ) => {
		const run = KEYS[ event.key ];
		if ( run && ! sheets.isAnyOpen() && ! event.target.closest( 'button, a, input, select, textarea' ) ) {
			event.preventDefault();
			run();
		}
	} );

	return {
		/** Fills the viewer for `item` and plays it. The caller shows the dialog (`showModal()`) the first time. */
		show( item, { gesture } ) {
			current = item;
			cancelTap();
			sheets.reset();
			rest();
			frame.dataset.shape = SHAPES[ item.ratio ] || 'wide';
			titleText.textContent = item.title;
			hint.hidden = ! WIDE_RATIOS.includes( item.ratio );
			drawRail();
			preloadNeighbours();
			player.mount( item, gesture );
			player.wake();
		},
		unload() {
			cancelTap();
			rest();
			dropPeek();
			player.unload();
		},
		/** History entries a sheet still holds (see collateral-modal/view.js `finishClose( extra )`). */
		release: () => sheets.release(),
	};
}
