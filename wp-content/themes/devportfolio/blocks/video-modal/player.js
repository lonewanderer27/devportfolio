/**
 * The video player engine of the Videos modals (DPV-017 body, DPV-018, DPV-029): the <video> or embed, autoplay
 * and the muted fallback, loop or replay-once, the playing/paused/idle state and a small event API. It has no
 * buttons: every control is a module in controls/ that binds to this API (see controls/index.js).
 *
 * `createPlayer( dialog, strings, options )`; the elements are found by class prefix (`videos-modal` or
 * `videos-mobile`). `mount( item, gesture )`: `gesture` is true when a click opened or moved to the video (sound
 * allowed), false for a deeplink or Back/Forward.
 *
 * Events (`player.on( type, fn )`, returns an unsubscribe): `mount`, `unmount`, `meta`, `time`, `play`, `pause`,
 * `ended`, `volume`.
 */
import { createStatus } from '../shared/status.js';
import { afterEnd, clampTime } from './player-model.js';
import { preferences } from './preferences.js';

const IDLE_MS = 3000;
const INTERACTIVE = 'button, input, a, textarea, select';
// <video> event -> engine event, for the ones that are only forwarded.
const FORWARDED = { loadedmetadata: 'meta', timeupdate: 'time', play: 'play', pause: 'pause', volumechange: 'volume' };

/**
 * @param {Element} dialog
 * @param {Object}  strings
 * @param {Object}  [options]
 * @param {string}  [options.prefix='videos-modal']
 * @param {boolean} [options.clickToggles=true] A click on the <video> toggles playback (desktop). The mobile viewer reads taps itself.
 * @param {boolean} [options.loop=false]        Loop instead of replaying once.
 * @param {boolean} [options.unmuteOnPlay=false] A visitor-started play turns the sound on (no mute button on screen).
 * @param {boolean} [options.quietLoading=false] No loading text and no paused glyph while the file loads: the poster (or
 *        the peek, in the mobile viewer) stays in view until the video plays.
 */
export function createPlayer( dialog, strings = {}, { prefix = 'videos-modal', clickToggles = true, loop = false, unmuteOnPlay = false, quietLoading = false } = {} ) {
	const $ = ( name ) => dialog.querySelector( `.${ prefix }__${ name }` );
	const stage = $( 'stage' );
	const root = $( 'video' );
	const status = createStatus( { stage, status: $( 'state' ), prefix } );
	const retryButton = $( 'retry' );
	const watchLink = $( 'watch' );
	const title = ( item ) => ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title );

	let video = null;
	let autoReplays = 0;
	let idleTimer = 0;
	let current = null;
	let failed = false;
	const listeners = {};

	const on = ( type, fn ) => {
		( listeners[ type ] ||= [] ).push( fn );
		return () => {
			listeners[ type ] = listeners[ type ].filter( ( f ) => f !== fn );
		};
	};
	const emit = ( type ) => ( listeners[ type ] || [] ).forEach( ( fn ) => fn() );
	// Once a video failed, only the next mount clears it: a play() that rejects later must not bring the controls back.
	const setState = ( state ) => {
		if ( failed && state !== 'error' ) {
			return;
		}
		root.dataset.state = state;
	};

	/** A video that cannot play: the message, "Try again" when there is something to load, the original post when known. */
	const fail = ( item ) => {
		failed = true;
		status.set( 'unavailable' );
		setState( 'error' );
		if ( retryButton ) {
			retryButton.hidden = ! ( item.fileUrl || item.embedSrc );
		}
		if ( watchLink ) {
			watchLink.hidden = ! item.postUrl;
			if ( item.postUrl ) {
				watchLink.href = item.postUrl;
			}
		}
	};

	/** The controls fade 3 seconds after the last pointer move, touch or focus while playing. */
	const wake = () => {
		root.classList.remove( 'is-idle' );
		window.clearTimeout( idleTimer );
		if ( video && ! video.paused && ! video.ended ) {
			idleTimer = window.setTimeout( () => root.classList.add( 'is-idle' ), IDLE_MS );
		}
	};

	/** A visitor-started play is a new session: the automatic replay counter starts again. */
	const userPlay = () => {
		if ( ! video ) {
			return;
		}
		autoReplays = 0;
		if ( unmuteOnPlay ) {
			preferences.muted = false;
			video.muted = false;
		}
		video.play().catch( () => setState( 'paused' ) );
	};

	const toggle = () => {
		if ( video ) {
			video.paused ? userPlay() : video.pause();
		}
	};

	/** Skips `seconds` (negative = back), kept inside the video. */
	const skip = ( seconds ) => {
		if ( video && Number.isFinite( video.duration ) ) {
			video.currentTime = clampTime( video.currentTime + seconds, video.duration );
			emit( 'time' );
		}
	};

	const start = () => {
		video.play().catch( () => {
			// Sound was blocked: start muted. This is not a choice, so the remembered mute stays as it was.
			video.muted = true;
			video.play().catch( () => setState( 'paused' ) );
		} );
	};

	const unload = () => {
		failed = false;
		window.clearTimeout( idleTimer );
		root.classList.remove( 'is-idle' );
		emit( 'unmount' );
		if ( video ) {
			video.pause();
			video.removeAttribute( 'src' );
			video.load();
			video = null;
		}
		stage.replaceChildren();
		status.set( null );
		setState( 'paused' );
		if ( retryButton ) {
			retryButton.hidden = true;
		}
		if ( watchLink ) {
			watchLink.hidden = true;
		}
	};

	const mountFile = ( item, gesture ) => {
		video = document.createElement( 'video' );
		video.playsInline = true;
		video.preload = 'auto';
		video.loop = loop;
		video.setAttribute( 'aria-label', title( item ) );
		if ( item.poster ) {
			video.poster = item.poster;
		}
		video.src = item.fileUrl;
		stage.append( video );

		if ( ! quietLoading ) {
			status.set( 'loading' );
		}
		video.addEventListener( 'canplay', () => status.set( null ) );
		video.addEventListener( 'error', () => {
			fail( item );
		} );
		video.addEventListener( 'play', () => {
			setState( 'playing' );
			wake();
		} );
		video.addEventListener( 'pause', () => {
			if ( ! video.ended ) {
				setState( 'paused' );
			}
			window.clearTimeout( idleTimer );
			root.classList.remove( 'is-idle' );
		} );
		video.addEventListener( 'ended', () => {
			if ( afterEnd( autoReplays ) === 'replay' ) {
				autoReplays += 1;
				video.currentTime = 0;
				video.play().catch( () => setState( 'ended' ) );
			} else {
				setState( 'ended' );
			}
		} );
		Object.entries( FORWARDED ).forEach( ( [ domEvent, type ] ) => video.addEventListener( domEvent, () => emit( type ) ) );

		setState( quietLoading ? 'loading' : 'paused' );
		video.muted = preferences.muted || ! gesture;
		video.volume = preferences.volume;
		video.playbackRate = preferences.rate;
		emit( 'mount' );
		start();
	};

	const mountEmbed = ( item, gesture ) => {
		if ( navigator.onLine === false ) {
			fail( item );
			return;
		}
		setState( 'embed' );
		const frame = document.createElement( 'iframe' );
		frame.title = title( item );
		frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
		frame.allowFullscreen = true;
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.src = gesture ? `${ item.embedSrc }${ item.embedSrc.includes( '?' ) ? '&' : '?' }autoplay=1` : item.embedSrc;
		stage.append( frame );
		emit( 'mount' );
	};

	const mount = ( item, gesture ) => {
		unload();
		current = item;
		if ( item.fileUrl ) {
			mountFile( item, gesture );
		} else if ( item.embedSrc ) {
			mountEmbed( item, gesture );
		} else {
			fail( item );
		}
	};

	if ( clickToggles ) {
		root.addEventListener( 'click', ( event ) => {
			// The video itself toggles; the controls handle their own clicks.
			if ( video && event.target === video ) {
				toggle();
			}
		} );
	}
	root.addEventListener( 'pointermove', wake );
	root.addEventListener( 'pointerdown', wake );
	root.addEventListener( 'focusin', wake );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( video && event.key === ' ' && ! event.target.closest( INTERACTIVE ) ) {
			event.preventDefault();
			wake();
			toggle();
		}
	} );

	if ( retryButton ) {
		retryButton.addEventListener( 'click', () => {
			if ( current ) {
				// The click is a gesture: sound is allowed, as for the first open.
				mount( current, true );
				root.tabIndex = -1;
				root.focus( { preventScroll: true } );
			}
		} );
	}

	return { mount, unload, toggle, userPlay, skip, wake, on, video: () => video, root };
}
