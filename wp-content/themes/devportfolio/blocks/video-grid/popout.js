/**
 * The long-press pop-out (DPV-036): a centred card in the video's own shape that grows out of the pressed
 * thumbnail, plays the muted 6 s loop (still poster with reduced motion, data saver or no file), blurs the page
 * and shrinks back on release. It has no buttons and is hidden from assistive technology: the thumbnail's
 * button still opens the full viewer.
 *
 * The card itself is animated (element.animate), not a view transition: a transition draws the picture in an
 * overlay that can end up under the grid, while the live card always stacks above it (z-index 40).
 */
import { canPreview, shouldLoopBack, progress, motionEnvironment } from './preview.js';
import { frameOf, flyTo, flightDuration } from '../shared/flight.js';

const BODY_CLASS = 'is-videos-popout';
const RATIOS = { '9x16': 9 / 16, '1x1': 1, '16x9': 16 / 9, '4x3': 4 / 3 };
// Static markup (no data): the same speaker-off icon the tiles carry.
const MUTE_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg>';

/**
 * The card's width / height for a `data-ratio` slug (9:16 when missing or unknown, like the tile).
 *
 * @param {string} slug 9x16, 1x1, 16x9 or 4x3.
 * @return {number}
 */
export function ratioValue( slug ) {
	return RATIOS[ slug ] || RATIOS[ '9x16' ];
}

function node( tag, className ) {
	const el = document.createElement( tag );
	el.className = className;
	return el;
}

/**
 * @param {HTMLElement} grid The `[data-videos-grid]` element (its `data-hint` is the release hint).
 */
export function createPopout( grid ) {
	let root = null;
	let card = null;
	let media = null;
	let img = null;
	let titleEl = null;
	let bar = null;
	let video = null;
	let current = null;
	let flight = null;

	function build() {
		if ( root ) {
			return;
		}
		root = node( 'div', 'videos-popout' );
		root.hidden = true;
		root.setAttribute( 'aria-hidden', 'true' );
		card = node( 'div', 'videos-popout__card' );
		const head = node( 'div', 'videos-popout__head' );
		titleEl = node( 'span', 'videos-popout__title' );
		head.append( titleEl );
		media = node( 'div', 'videos-popout__media' );
		img = node( 'img', 'videos-popout__img' );
		img.alt = '';
		bar = node( 'span', 'videos-popout__bar' );
		const mute = node( 'span', 'videos-popout__mute' );
		mute.innerHTML = MUTE_SVG;
		media.append( img, bar, mute );
		card.append( head, media );
		const hint = node( 'p', 'videos-popout__hint' );
		hint.textContent = grid.getAttribute( 'data-hint' ) || '';
		root.append( card, hint );
		document.body.append( root );
	}

	function play( item ) {
		const src = item.getAttribute( 'data-preview-src' ) || '';
		if ( ! canPreview( { hover: true, ...motionEnvironment( src ) } ) ) {
			return; // the still poster stays
		}
		const isClip = item.hasAttribute( 'data-preview-clip' );
		video = node( 'video', 'videos-popout__video' );
		video.muted = true;
		video.defaultMuted = true;
		video.playsInline = true;
		video.loop = isClip;
		video.preload = 'none';
		video.disablePictureInPicture = true;
		video.src = src;
		video.addEventListener( 'playing', function () {
			root.classList.add( 'is-playing' );
		} );
		video.addEventListener( 'timeupdate', function () {
			if ( shouldLoopBack( video.currentTime, isClip ) ) {
				video.currentTime = 0;
			}
			bar.style.setProperty( '--p', String( progress( video.currentTime ) ) );
		} );
		media.insertBefore( video, bar );
		const played = video.play();
		if ( played && 'function' === typeof played.catch ) {
			played.catch( function () {} );
		}
	}

	function stop() {
		if ( video ) {
			video.pause();
			video.remove();
			video = null;
		}
		if ( root ) {
			root.classList.remove( 'is-playing' );
		}
		if ( bar ) {
			bar.style.removeProperty( '--p' );
		}
	}

	function hide() {
		root.hidden = true;
		document.body.classList.remove( BODY_CLASS );
	}

	// Stops a running flight. The card then sits at its layout place (no transform), the place the next measure reads.
	function stopFlight() {
		if ( flight ) {
			flight.cancel();
			flight = null;
		}
	}

	function finish() {
		hide();
		card.style.cssText = '';
	}

	function open( item ) {
		if ( current ) {
			return;
		}
		build();
		const button = item.querySelector( '.videos-item__open' );
		const poster = item.querySelector( '.videos-item__img' );
		const src = poster && ! poster.hidden ? poster.currentSrc || poster.src : '';
		titleEl.textContent = button ? button.getAttribute( 'aria-label' ) || '' : '';
		img.hidden = '' === src;
		if ( src ) {
			img.src = src;
		} else {
			img.removeAttribute( 'src' );
		}
		root.style.setProperty( '--ratio', String( ratioValue( item.getAttribute( 'data-ratio' ) || '' ) ) );
		current = { item: item };
		item.classList.remove( 'is-pressing' );
		root.hidden = false;
		document.body.classList.add( BODY_CLASS );

		// Starts exactly the size and place of the grid item, then grows to the card's own size.
		stopFlight();
		const place = card.getBoundingClientRect();
		card.style.width = `${ place.width }px`;
		card.style.height = `${ place.height }px`;
		flight = flyTo( card, frameOf( item.getBoundingClientRect(), place ), frameOf( place, place ), {
			duration: flightDuration(),
			radius: [ '4px', '14px' ],
			onDone: () => {
				card.style.cssText = '';
			},
		} );
		play( item );
	}

	function close() {
		if ( ! current ) {
			return;
		}
		const item = current.item;
		current = null;
		stop();
		// Continue from where the card is now (it may still be growing), then shrink into the tile.
		const now = card.getBoundingClientRect();
		const from = { transform: getComputedStyle( card ).transform, width: `${ now.width }px`, height: `${ now.height }px` };
		stopFlight();
		if ( item.hidden ) {
			finish();
			return;
		}
		const place = card.getBoundingClientRect();
		card.style.width = `${ place.width }px`;
		card.style.height = `${ place.height }px`;
		flight = flyTo( card, from, frameOf( item.getBoundingClientRect(), place ), {
			duration: flightDuration(),
			radius: [ '14px', '4px' ],
			onDone: finish,
		} );
	}

	// Leaving the page or the tab closes it at once.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden && current ) {
			current = null;
			stop();
			stopFlight();
			finish();
		}
	} );

	return { open: open, close: close, isOpen: () => null !== current };
}
