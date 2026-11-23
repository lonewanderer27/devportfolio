/**
 * Home "Selected work" motion (DPH-013, DPH-016, DPH-017).
 *
 * Two independent rotations: the Documents book fan (every 3.5 s) and the Videos film strip (every 4 s). The
 * Collaterals card has no motion, so nothing here touches it. Opening a book or a frame is not done here: their
 * buttons carry `data-document-id` / `data-video-id` and the modal blocks (placed on the same page) listen for them.
 *
 * Each rotation runs only while its card is on screen, not hovered, not holding keyboard focus, the tab is visible
 * and no <dialog> is open (the Home motion pauses while a viewer is open, DPH-014). With reduced motion nothing
 * moves or plays: the first book and the first clip stay in front.
 */
import { BOOK_PERIOD, FRAME_PERIOD, bookRole, frameRole, tick } from './rotation.js';
import { timeLabel, fraction } from './time.js';

const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
const saveData = () => Boolean( navigator.connection && navigator.connection.saveData );
const viewerOpen = () => document.querySelector( 'dialog[open]' ) !== null;

/**
 * What stops a card's motion. Touch is ignored for hover: a tap leaves a "hover" behind that never ends.
 *
 * @param {Element} card The card element.
 * @return {{hover:boolean,focus:boolean,visible:boolean}}
 */
function watch( card ) {
	const state = { hover: false, focus: false, visible: false };
	card.addEventListener( 'pointerenter', ( event ) => {
		state.hover = event.pointerType !== 'touch';
	} );
	card.addEventListener( 'pointerleave', () => {
		state.hover = false;
	} );
	card.addEventListener( 'focusin', ( event ) => {
		state.focus = event.target.matches( ':focus-visible' );
	} );
	card.addEventListener( 'focusout', () => {
		state.focus = false;
	} );
	if ( 'IntersectionObserver' in window ) {
		new IntersectionObserver( ( entries ) => {
			state.visible = entries[ entries.length - 1 ].isIntersecting;
		}, { threshold: 0.25 } ).observe( card );
	} else {
		state.visible = true;
	}
	return state;
}

const isRunning = ( state ) => state.visible && ! state.hover && ! state.focus && ! document.hidden && ! viewerOpen();

/**
 * Calls `onAdvance` at the end of every `period` ms of running time and `onFrame` on every animation frame.
 *
 * @param {{hover:boolean,focus:boolean,visible:boolean}} state    What `watch()` returned.
 * @param {number}                                        period   Length of one step in ms.
 * @param {() => void}                                    onAdvance Called when a step ends.
 * @param {(elapsed:number, running:boolean) => void}     onFrame   Called on every animation frame.
 */
function loop( state, period, onAdvance, onFrame ) {
	let elapsed = 0;
	let last = performance.now();
	const frame = ( now ) => {
		// A long gap (tab in the background) must not skip several steps at once.
		const dt = Math.min( now - last, 250 );
		last = now;
		const running = isRunning( state );
		const result = tick( elapsed, dt, running, period );
		elapsed = result.elapsed;
		if ( result.advanced ) {
			onAdvance();
		}
		onFrame( elapsed, running );
		window.requestAnimationFrame( frame );
	};
	window.requestAnimationFrame( frame );
}

/**
 * Documents: the book fan (DPH-013).
 *
 * @param {Element} card The Documents card.
 */
function startBooks( card ) {
	const books = Array.from( card.querySelectorAll( '.work-docs__item' ) );
	if ( books.length < 2 || reducedMotion.matches ) {
		return;
	}
	let active = 0;
	const apply = () => {
		books.forEach( ( book, index ) => {
			const role = bookRole( index, active, books.length );
			book.dataset.role = role;
			// Only the front book can be reached and clicked.
			book.toggleAttribute( 'inert', role !== 'front' );
		} );
	};
	loop(
		watch( card ),
		BOOK_PERIOD,
		() => {
			active = ( active + 1 ) % books.length;
			apply();
		},
		() => {}
	);
}

/**
 * Videos: the film strip (DPH-016, DPH-017).
 *
 * @param {Element} card The Videos card.
 */
function startStrip( card ) {
	const frames = Array.from( card.querySelectorAll( '.work-strip__frame' ) );
	if ( frames.length === 0 ) {
		return;
	}
	const still = reducedMotion.matches;
	const state = watch( card );
	let active = 0;
	let loaded = null;

	const unload = ( frame ) => {
		const video = frame.querySelector( '.work-strip__video' );
		if ( video ) {
			video.pause();
			video.removeAttribute( 'src' );
			video.load();
			video.remove();
		}
		frame.classList.remove( 'is-playing' );
	};

	const load = ( frame, src ) => {
		const video = document.createElement( 'video' );
		video.className = 'work-strip__video';
		video.muted = true;
		video.loop = true;
		video.playsInline = true;
		video.preload = 'auto';
		video.tabIndex = -1;
		video.setAttribute( 'muted', '' );
		video.setAttribute( 'playsinline', '' );
		video.setAttribute( 'aria-hidden', 'true' );
		video.addEventListener( 'playing', () => frame.classList.add( 'is-playing' ), { once: true } );
		video.src = src;
		frame.querySelector( '.work-strip__art' ).append( video );
	};

	/** Makes the middle frame the only one holding a video, and plays or pauses it. */
	const syncVideo = ( running ) => {
		const frame = frames[ active ];
		if ( loaded && loaded !== frame ) {
			unload( loaded );
			loaded = null;
		}
		const src = frame.dataset.previewSrc;
		if ( saveData() || ! src || ! state.visible ) {
			return;
		}
		if ( loaded !== frame ) {
			load( frame, src );
			loaded = frame;
		}
		const video = frame.querySelector( '.work-strip__video' );
		if ( video && running ) {
			const started = video.play();
			if ( started && started.catch ) {
				started.catch( () => {} );
			}
		} else if ( video ) {
			video.pause();
		}
	};

	const draw = ( elapsed ) => {
		frames.forEach( ( frame, index ) => {
			const isActive = index === active;
			frame.style.setProperty( '--progress', isActive ? fraction( elapsed, FRAME_PERIOD ).toFixed( 4 ) : '0' );
			const time = frame.querySelector( '.work-strip__time' );
			if ( time && isActive ) {
				time.textContent = timeLabel( elapsed, Number( frame.dataset.length ) || 0 );
			}
		} );
	};

	const apply = () => {
		frames.forEach( ( frame, index ) => {
			const role = frameRole( index, active, frames.length );
			frame.dataset.role = role;
			// Only the middle frame can be reached and clicked.
			frame.toggleAttribute( 'inert', role !== 'active' );
		} );
		draw( 0 );
	};

	if ( still ) {
		// Nothing moves or plays: the first clip stays in the middle as a poster.
		draw( 0 );
		return;
	}

	let lastRunning = null;
	let lastVisible = null;
	loop(
		state,
		FRAME_PERIOD,
		() => {
			active = ( active + 1 ) % frames.length;
			apply();
			lastRunning = null;
		},
		( elapsed, running ) => {
			draw( elapsed );
			if ( running !== lastRunning || state.visible !== lastVisible ) {
				lastRunning = running;
				lastVisible = state.visible;
				syncVideo( running );
			}
		}
	);
}

document.querySelectorAll( '.work-card--documents' ).forEach( startBooks );
document.querySelectorAll( '.work-card--videos' ).forEach( startStrip );
