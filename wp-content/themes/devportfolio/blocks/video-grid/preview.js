/**
 * Desktop hover preview of the Videos grid (DPV-007).
 *
 * The pointer must rest on a tile for HOVER_DELAY_MS; then a muted <video> is created inside the tile
 * (nothing is downloaded before that), plays from 0:00 and loops every LOOP_SECONDS. Keyboard focus,
 * touch, reduced motion and data saver never start it. Only one tile plays at a time.
 */

export const HOVER_DELAY_MS = 800;
export const LOOP_SECONDS = 6;
export const HOVER_QUERY = '(hover: hover) and (pointer: fine)';

// Set on <body> while a preview plays; style.css veils the rest of the page behind it (lo-fi B2).
const PAGE_CLASS = 'is-videos-previewing';

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

function cancelPending() {
	if ( pending ) {
		window.clearTimeout( pending.timer );
		pending = null;
	}
}

function environment( item ) {
	const connection = navigator.connection;
	return {
		hover: window.matchMedia( HOVER_QUERY ).matches,
		reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
		saveData: Boolean( connection && connection.saveData ),
		src: item.getAttribute( 'data-preview-src' ) || '',
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
		item.classList.add( 'is-previewing' );
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

/**
 * Cancels a pending start and stops a playing preview, back to the poster.
 *
 * @param {HTMLElement} item The `.videos-item` tile.
 */
export function stopPreview( item ) {
	if ( pending && pending.item === item ) {
		cancelPending();
	}

	const video = item.querySelector( '.videos-item__video' );
	if ( video ) {
		video.pause();
		video.currentTime = 0;
	}
	item.classList.remove( 'is-previewing' );
	item.style.removeProperty( '--p' );

	if ( active === item ) {
		active = null;
		document.body.classList.remove( PAGE_CLASS );
	}
}

function startPreview( item ) {
	if ( active && active !== item ) {
		stopPreview( active );
	}
	active = item;
	document.body.classList.add( PAGE_CLASS );

	const played = videoOf( item ).play();
	if ( played && 'function' === typeof played.catch ) {
		// Autoplay refused or the file cannot play: stay on the poster.
		played.catch( function () {
			stopPreview( item );
		} );
	}
}

/**
 * Wires the hover preview to a grid. Pointer enter/leave only: focus is never listened to.
 *
 * @param {HTMLElement} grid The `[data-videos-grid]` element.
 */
export function attachPreviews( grid ) {
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
			if ( ! canPreview( environment( item ) ) ) {
				cancelPending();
				if ( active ) {
					stopPreview( active );
				}
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

	// Moving between tiles, or into a gap, keeps the playing one and the veil; leaving the grid ends both.
	grid.addEventListener( 'pointerleave', function ( event ) {
		if ( event.target !== grid ) {
			return;
		}
		cancelPending();
		if ( active ) {
			stopPreview( active );
		}
	} );

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			cancelPending();
			if ( active ) {
				stopPreview( active );
			}
		}
	} );
}
