/**
 * Desktop hover preview of the Videos grid (DPV-007).
 *
 * The pointer must rest on a tile for HOVER_DELAY_MS; then a muted <video> is created inside the tile
 * (nothing is downloaded before that), plays from 0:00 and loops every LOOP_SECONDS. Keyboard focus,
 * touch, reduced motion and data saver never start it. Only one tile plays at a time.
 */
import { NO_OUTLINE, createOutline } from './outline.js';

export const HOVER_DELAY_MS = 800;
export const LOOP_SECONDS = 6;
export const HOVER_QUERY = '(hover: hover) and (pointer: fine)';
export const FADE_MS = 150;

// Set on <body> while a preview plays; style.css veils the rest of the page behind it (lo-fi B2).
const PAGE_CLASS = 'is-videos-previewing';
// Set for two frames when the veil must go at once (a click), see style.css.
const INSTANT_CLASS = 'is-videos-instant';

/**
 * Whether a preview may start.
 *
 * @param {{hover:boolean,reducedMotion:boolean,saveData:boolean,src:string}} env Current conditions.
 * @return {boolean}
 */
export function canPreview( env ) {
	return Boolean( env.hover ) && ! env.reducedMotion && ! env.saveData && '' !== ( env.src || '' );
}

/**
 * Whether a full-length file (not the stored clip) has played its 6 seconds and must restart.
 *
 * @param {number}  currentTime Seconds played.
 * @param {boolean} isClip      True when the source is the stored 6 s clip, which loops by itself.
 * @return {boolean}
 */
export function shouldLoopBack( currentTime, isClip ) {
	return ! isClip && currentTime >= LOOP_SECONDS;
}

/**
 * Whether a point is inside a rect: the grid test for "did the pointer really leave?". It uses the pointer's
 * coordinates, not `:hover`, because while a view transition runs the browser reports the pointer over the
 * transition's overlay, so `:hover` says the pointer left a grid it is still over.
 *
 * @param {{left:number,right:number,top:number,bottom:number}} rect Grid box.
 * @param {number} x Pointer clientX.
 * @param {number} y Pointer clientY.
 * @return {boolean}
 */
export function pointInside( rect, x, y ) {
	return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
}

/**
 * Progress bar fill, 0 to 1.
 *
 * @param {number} currentTime Seconds played.
 * @return {number}
 */
export function progress( currentTime ) {
	const value = currentTime / LOOP_SECONDS;
	if ( ! ( value > 0 ) ) {
		return 0;
	}
	return value > 1 ? 1 : value;
}

// The one tile waiting out HOVER_DELAY_MS, as { item, timer }, and the tile that is playing.
let pending = null;
let active = null;
let outline = NO_OUTLINE;
let grid = null;
// Tiles whose video is fading out: item -> timer.
const fading = new Map();

function cancelPending() {
	if ( pending ) {
		window.clearTimeout( pending.timer );
		pending = null;
	}
}

/**
 * Reduced motion and data saver, read at each event (the visitor can change them live).
 *
 * @param {string} src Preview source ('' when the tile has none).
 * @return {{reducedMotion:boolean,saveData:boolean,src:string}}
 */
export function motionEnvironment( src ) {
	const connection = navigator.connection;
	return {
		reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
		saveData: Boolean( connection && connection.saveData ),
		src: src || '',
	};
}

function environment( item ) {
	return {
		hover: window.matchMedia( HOVER_QUERY ).matches,
		...motionEnvironment( item.getAttribute( 'data-preview-src' ) ),
	};
}

// The tile's <video>, created on first use and kept.
function videoOf( item ) {
	let video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		return video;
	}

	const isClip = item.hasAttribute( 'data-preview-clip' );
	video = document.createElement( 'video' );
	video.className = 'videos-item__video';
	video.muted = true;
	video.defaultMuted = true;
	video.playsInline = true;
	video.loop = isClip;
	video.preload = 'auto';
	video.disablePictureInPicture = true;
	video.tabIndex = -1;
	video.setAttribute( 'aria-hidden', 'true' );
	video.src = item.getAttribute( 'data-preview-src' );

	video.addEventListener( 'playing', function () {
		// Only the playing tile shows its video; a video that starts after the pointer moved on stays hidden.
		if ( active === item ) {
			item.classList.add( 'is-previewing' );
		}
	} );
	video.addEventListener( 'timeupdate', function () {
		if ( shouldLoopBack( video.currentTime, isClip ) ) {
			video.currentTime = 0;
		}
		item.style.setProperty( '--p', String( progress( video.currentTime ) ) );
	} );

	item.insertBefore( video, item.querySelector( '.videos-item__bar' ) );
	return video;
}

// After the fade (or at once): pause, rewind, back to the poster.
function finishFade( item ) {
	const timer = fading.get( item );
	if ( timer ) {
		window.clearTimeout( timer );
		fading.delete( item );
	}
	item.classList.remove( 'is-fading', 'is-previewing', 'is-highlighted' );
	item.style.removeProperty( '--p' );
	const video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		video.pause();
		video.currentTime = 0;
	}
}

/**
 * Cancels a pending start and stops one tile's preview, back to the poster. The video, its bar and its
 * mute badge fade out over FADE_MS unless `instant`. This does not touch the page blur or the outline.
 *
 * @param {HTMLElement} item    The `.videos-item` tile.
 * @param {{instant?:boolean}} [options] `instant` skips the fade.
 */
export function stopPreview( item, { instant = false } = {} ) {
	if ( pending && pending.item === item ) {
		cancelPending();
	}
	if ( active === item ) {
		active = null;
	}

	if ( instant || ! item.classList.contains( 'is-previewing' ) ) {
		finishFade( item );
		return;
	}
	item.classList.remove( 'is-previewing' );
	item.classList.add( 'is-fading' );
	fading.set( item, window.setTimeout( function () {
		finishFade( item );
	}, FADE_MS ) );
}

// The page blur. `instant` removes it with no fade (a click, so the modal opens over a clean page).
function setVeil( on, instant ) {
	const body = document.body;
	if ( instant && ! on ) {
		body.classList.add( INSTANT_CLASS );
		body.classList.remove( PAGE_CLASS );
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				body.classList.remove( INSTANT_CLASS );
			} );
		} );
		return;
	}
	body.classList.toggle( PAGE_CLASS, on );
}

// Ends the whole session: video, outline and blur.
function endSession( instant ) {
	cancelPending();
	if ( active ) {
		stopPreview( active, { instant } );
	}
	outline.hide( instant );
	setVeil( false, instant );
}

/**
 * A tile was hidden by the filter or is otherwise gone: stop it, and end the session when it was the playing one.
 *
 * @param {HTMLElement} item The `.videos-item` tile.
 */
export function releasePreview( item ) {
	if ( active === item || ( pending && pending.item === item ) ) {
		endSession( true );
		return;
	}
	stopPreview( item, { instant: true } );
}

/** Re-aligns the outline after the filter moved the tiles. */
export function refreshPreviews() {
	outline.refresh();
}

// A tile with no preview file while the page is already blurred: the outline moves to it and the blur stays on
// (the pointer is still over the grid), but nothing plays. The poster stays, lifted above the veil.
function highlightOnly( item ) {
	const previous = active;
	finishFade( item );
	active = item;
	if ( previous && previous !== item ) {
		stopPreview( previous );
	}
	setVeil( true, false );
	item.classList.add( 'is-highlighted' );
	outline.show( item );
}

function startPreview( item ) {
	const previous = active;
	finishFade( item ); // entering a tile whose video is still fading out
	active = item;
	if ( previous && previous !== item ) {
		stopPreview( previous ); // fades out while the outline slides over
	}
	setVeil( true, false );

	outline.show( item );

	const played = videoOf( item ).play();
	if ( played && 'function' === typeof played.catch ) {
		// Autoplay refused or the file cannot play: stay on the poster.
		played.catch( function () {
			if ( active === item ) {
				endSession( true );
			}
		} );
	}
}

/**
 * Wires the hover preview, the outline and the page blur to a grid. Pointer enter/leave only: focus is never
 * listened to.
 *
 * @param {HTMLElement} gridEl The `[data-videos-grid]` element.
 */
export function attachPreviews( gridEl ) {
	grid = gridEl;
	const outlineEl = grid.querySelector( '.videos-outline' );
	outline = outlineEl ? createOutline( outlineEl ) : NO_OUTLINE;

	grid.addEventListener(
		'pointerenter',
		function ( event ) {
			const item = event.target;
			if ( ! item.classList || ! item.classList.contains( 'videos-item' ) ) {
				return;
			}
			if ( 'mouse' !== event.pointerType && 'pen' !== event.pointerType ) {
				return;
			}
			const env = environment( item );
			if ( ! canPreview( env ) ) {
				// Only the file is missing (the visitor can hover and has no motion or data limits): keep the blur.
				if ( active && canPreview( { ...env, src: 'x' } ) ) {
					cancelPending();
					if ( active !== item ) {
						highlightOnly( item );
					}
					return;
				}
				endSession( false );
				return;
			}
			if ( active === item ) {
				cancelPending();
				return;
			}
			cancelPending();
			if ( active ) {
				// The page is already veiled: moving to the next tile switches at once, with no delay.
				startPreview( item );
				return;
			}
			pending = {
				item: item,
				timer: window.setTimeout( function () {
					pending = null;
					startPreview( item );
				}, HOVER_DELAY_MS ),
			};
		},
		true
	);

	// Moving between tiles, or into a gap, keeps the playing one, the outline and the veil; leaving the grid ends
	// all three. While the outline's view transition runs the browser reports the pointer off the grid and sends no
	// second leave when it really goes, so the position decides, never the event alone: a leave counts only when its
	// own coordinates are outside the grid, and every move while a preview is on or waiting is checked the same way.
	const outside = function ( event ) {
		return ! pointInside( grid.getBoundingClientRect(), event.clientX, event.clientY );
	};
	grid.addEventListener( 'pointerleave', function ( event ) {
		if ( event.target === grid && outside( event ) ) {
			endSession( false );
		}
	} );
	window.addEventListener(
		'pointermove',
		function ( event ) {
			if ( ( active || pending ) && ( 'mouse' === event.pointerType || 'pen' === event.pointerType ) && outside( event ) ) {
				endSession( false );
			}
		},
		true
	);

	// A click opens the modal: everything goes at once, so the modal opens over a clean page (capture phase,
	// before the modal's own listener on the document).
	grid.addEventListener(
		'click',
		function () {
			endSession( true );
		},
		true
	);

	// The grid changes width: keep the outline on the tile.
	if ( 'function' === typeof ResizeObserver ) {
		new ResizeObserver( function () {
			outline.refresh();
		} ).observe( grid );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			endSession( true );
		}
	} );
}
