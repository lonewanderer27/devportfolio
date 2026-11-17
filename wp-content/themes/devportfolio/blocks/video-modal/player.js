/**
 * The video player inside the desktop video modal (DPV-017 body, DPV-018 control bar, DPV-029 seeking).
 *
 * `createPlayer( dialog, strings )` returns { mount( item, gesture ), unload() }. `mount` builds the
 * uploaded-file player (native <video> without controls, custom bar) or the embed iframe; `gesture` is
 * true when a click opened or moved to the video (sound allowed), false for a deeplink or Back/Forward.
 * The mute, volume and rate choices live in module variables for the page only (memory, not storage).
 */
import { createStatus } from '../shared/status.js';
import { timeLabel, ariaValueText, progressPercent, clampVolume, seekForKey, afterEnd, PLAYBACK_RATES } from './player-model.js';

const IDLE_MS = 3000;
const INTERACTIVE = 'button, input, a, textarea, select';

let preferMuted = false;
let preferVolume = 1;
let preferRate = 1;

export function createPlayer( dialog, strings = {} ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const stage = $( '.videos-modal__stage' );
	const playerEl = $( '.videos-modal__video' );
	const statusEl = $( '.videos-modal__state' );
	const status = createStatus( { stage, status: statusEl, prefix: 'videos-modal' } );
	const playPause = $( '.videos-modal__playpause' );
	const bigPlay = $( '.videos-modal__bigplay' );
	const replay = $( '.videos-modal__replay' );
	const scrub = $( '.videos-modal__scrub' );
	const time = $( '.videos-modal__time' );
	const mute = $( '.videos-modal__mute' );
	const volume = $( '.videos-modal__volume' );
	const volumeBtn = $( '.videos-modal__volbtn' );
	const volumePop = $( '.videos-modal__popover--volume' );
	const rateBtn = $( '.videos-modal__ratebtn' );
	const ratePop = $( '.videos-modal__popover--rate' );
	const rateOptions = Array.from( dialog.querySelectorAll( '.videos-modal__rateopt' ) );
	const fullscreen = $( '.videos-modal__fullscreen' );

	const fullscreenTarget = () => {
		// The wrapper goes fullscreen so the control bar goes with the video.
		if ( playerEl.requestFullscreen ) {
			return playerEl;
		}
		return null;
	};

	const syncFullscreen = () => {
		const on = Boolean( document.fullscreenElement );
		fullscreen.setAttribute( 'aria-label', on ? strings.exitFullscreen || 'Exit full screen' : strings.fullscreen || 'Full screen' );
	};

	let video = null;
	let autoReplays = 0;
	let idleTimer = 0;
	let dragging = false;

	const syncVolume = () => {
		if ( ! video ) {
			return;
		}
		const level = video.muted ? 0 : video.volume;
		volume.value = String( level );
		// The slider's fill is the --v percentage (0 to 100) read by the track's gradient in style.css.
		volume.style.setProperty( '--v', String( Math.round( level * 100 ) ) );
	};

	const volumeItem = { pop: volumePop, trigger: volumeBtn };
	const rateItem = { pop: ratePop, trigger: rateBtn };
	const popovers = [ volumeItem, rateItem ];

	const setPopover = ( item, open ) => {
		item.pop.hidden = ! open;
		item.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};

	/** Closes every bar popover except `keep` (volume and speed never open together). */
	const closePopovers = ( keep = null ) => {
		popovers.forEach( ( item ) => {
			if ( item !== keep ) {
				setPopover( item, false );
			}
		} );
	};

	const openPopover = ( item ) => {
		const open = item.pop.hidden;
		closePopovers( item );
		setPopover( item, open );
	};

	const syncRate = () => {
		rateBtn.textContent = `${ preferRate }×`;
		rateBtn.setAttribute( 'aria-label', `${ rateBtn.dataset.label || 'Playback speed' }, ${ preferRate }×` );
		rateOptions.forEach( ( option ) => {
			option.setAttribute( 'aria-pressed', Number( option.dataset.rate ) === preferRate ? 'true' : 'false' );
		} );
	};

	const setState = ( state ) => {
		playerEl.dataset.state = state;
	};

	const wake = () => {
		playerEl.classList.remove( 'is-idle' );
		window.clearTimeout( idleTimer );
		if ( video && ! video.paused && ! video.ended ) {
			idleTimer = window.setTimeout( () => playerEl.classList.add( 'is-idle' ), IDLE_MS );
		}
	};

	const syncMute = () => {
		const label = video && video.muted ? strings.unmute || 'Unmute' : strings.mute || 'Mute';
		mute.setAttribute( 'aria-label', label );
		mute.setAttribute( 'title', label );
		mute.setAttribute( 'aria-pressed', video && video.muted ? 'true' : 'false' );
	};

	const syncTime = () => {
		if ( ! video ) {
			return;
		}
		const duration = Number.isFinite( video.duration ) ? video.duration : 0;
		time.textContent = timeLabel( video.currentTime, duration );
		scrub.setAttribute( 'aria-valuetext', ariaValueText( video.currentTime, duration ) );
		scrub.style.setProperty( '--p', `${ progressPercent( video.currentTime, duration ) }%` );
		if ( ! dragging ) {
			scrub.value = String( video.currentTime );
		}
	};

	/** A user-started play is a new session: the automatic replay counter starts again. */
	const userPlay = () => {
		autoReplays = 0;
		video.play().catch( () => setState( 'paused' ) );
	};

	const toggle = () => {
		if ( ! video ) {
			return;
		}
		if ( video.paused ) {
			userPlay();
		} else {
			video.pause();
		}
	};

	const start = ( gesture ) => {
		video.muted = preferMuted || ! gesture;
		video.play().catch( () => {
			// Sound was blocked: start muted. This is not a choice, so preferMuted stays as it was.
			video.muted = true;
			video.play().catch( () => setState( 'paused' ) );
		} );
	};

	const unload = () => {
		window.clearTimeout( idleTimer );
		playerEl.classList.remove( 'is-idle' );
		if ( video ) {
			video.pause();
			video.removeAttribute( 'src' );
			video.load();
			video = null;
		}
		stage.replaceChildren();
		status.set( null );
		setState( 'paused' );
	};

	const mountFile = ( item, gesture ) => {
		video = document.createElement( 'video' );
		video.playsInline = true;
		video.preload = 'auto';
		video.setAttribute( 'aria-label', ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title ) );
		if ( item.poster ) {
			video.poster = item.poster;
		}
		video.src = item.fileUrl;
		stage.append( video );

		status.set( 'loading' );
		video.addEventListener( 'canplay', () => status.set( null ) );
		video.addEventListener( 'error', () => {
			status.set( 'unavailable' );
			setState( 'paused' );
		} );
		video.addEventListener( 'loadedmetadata', () => {
			const duration = Number.isFinite( video.duration ) ? video.duration : 0;
			scrub.max = String( duration );
			syncTime();
		} );
		video.addEventListener( 'timeupdate', syncTime );
		video.addEventListener( 'play', () => {
			setState( 'playing' );
			playPause.setAttribute( 'aria-label', strings.pause || 'Pause' );
			wake();
		} );
		video.addEventListener( 'pause', () => {
			if ( video && ! video.ended ) {
				setState( 'paused' );
			}
			playPause.setAttribute( 'aria-label', strings.play || 'Play' );
			window.clearTimeout( idleTimer );
			playerEl.classList.remove( 'is-idle' );
		} );
		video.addEventListener( 'volumechange', () => {
			syncMute();
			syncVolume();
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

		setState( 'paused' );
		video.volume = preferVolume;
		video.playbackRate = preferRate;
		syncRate();
		fullscreen.hidden = ! ( playerEl.requestFullscreen || video.webkitEnterFullscreen );
		syncMute();
		syncVolume();
		syncTime();
		start( gesture );
	};

	const mountEmbed = ( item, gesture ) => {
		setState( 'embed' );
		const frame = document.createElement( 'iframe' );
		frame.title = ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title );
		frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
		frame.allowFullscreen = true;
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.src = gesture ? `${ item.embedSrc }${ item.embedSrc.includes( '?' ) ? '&' : '?' }autoplay=1` : item.embedSrc;
		stage.append( frame );
	};

	const mount = ( item, gesture ) => {
		unload();
		if ( item.fileUrl ) {
			mountFile( item, gesture );
		} else if ( item.embedSrc ) {
			mountEmbed( item, gesture );
		} else {
			setState( 'embed' );
			status.set( 'unavailable' );
		}
	};

	// Wiring that does not depend on a mounted video is added once.
	playPause.addEventListener( 'click', () => {
		wake();
		toggle();
	} );
	bigPlay.addEventListener( 'click', () => {
		wake();
		toggle();
	} );
	replay.addEventListener( 'click', () => {
		if ( video ) {
			video.currentTime = 0;
			userPlay();
		}
	} );
	mute.addEventListener( 'click', () => {
		if ( video ) {
			preferMuted = ! video.muted;
			video.muted = preferMuted;
		}
	} );
	playerEl.addEventListener( 'click', ( event ) => {
		// The video itself toggles; the bar and the centre button handle their own clicks.
		if ( video && event.target === video ) {
			toggle();
		}
	} );
	scrub.addEventListener( 'pointerdown', () => {
		dragging = true;
	} );
	window.addEventListener( 'pointerup', () => {
		if ( dragging ) {
			dragging = false;
			syncTime();
		}
	} );
	scrub.addEventListener( 'input', () => {
		if ( video ) {
			video.currentTime = Number( scrub.value );
			syncTime();
		}
	} );
	scrub.addEventListener( 'keydown', ( event ) => {
		if ( ! video ) {
			return;
		}
		const next = seekForKey( event.key, video.currentTime, video.duration );
		if ( next !== null ) {
			event.preventDefault();
			video.currentTime = next;
			syncTime();
		}
	} );
	playerEl.addEventListener( 'pointermove', wake );
	playerEl.addEventListener( 'focusin', wake );
	dialog.addEventListener( 'keydown', ( event ) => {
		const open = popovers.find( ( item ) => ! item.pop.hidden );
		if ( open && event.key === 'Escape' ) {
			// Escape closes an open popover first; the dialog stays open.
			event.preventDefault();
			closePopovers();
			open.trigger.focus();
			return;
		}
		if ( ! video ) {
			return;
		}
		if ( event.key === ' ' && ! event.target.closest( INTERACTIVE ) ) {
			event.preventDefault();
			wake();
			toggle();
		}
	} );

	volume.addEventListener( 'input', () => {
		if ( ! video ) {
			return;
		}
		preferVolume = clampVolume( Number( volume.value ) );
		video.volume = preferVolume;
		// Moving the slider off zero unmutes; dropping it to zero mutes (D19).
		preferMuted = preferVolume === 0;
		video.muted = preferMuted;
	} );
	volumeBtn.addEventListener( 'click', () => openPopover( volumeItem ) );
	rateBtn.addEventListener( 'click', () => openPopover( rateItem ) );
	rateOptions.forEach( ( option ) => {
		option.addEventListener( 'click', () => {
			preferRate = PLAYBACK_RATES.includes( Number( option.dataset.rate ) ) ? Number( option.dataset.rate ) : 1;
			if ( video ) {
				video.playbackRate = preferRate;
			}
			syncRate();
			closePopovers();
			rateBtn.focus();
		} );
	} );
	// A press outside an open popover closes it; its own trigger toggles it.
	document.addEventListener( 'pointerdown', ( event ) => {
		popovers.forEach( ( item ) => {
			if ( ! item.pop.hidden && ! item.pop.contains( event.target ) && ! item.trigger.contains( event.target ) ) {
				setPopover( item, false );
			}
		} );
	} );
	fullscreen.addEventListener( 'click', () => {
		if ( document.fullscreenElement ) {
			document.exitFullscreen();
			return;
		}
		const target = fullscreenTarget();
		if ( target ) {
			target.requestFullscreen().catch( () => {} );
		} else if ( video && video.webkitEnterFullscreen ) {
			video.webkitEnterFullscreen();
		}
	} );
	document.addEventListener( 'fullscreenchange', syncFullscreen );
	syncFullscreen();

	return { mount, unload };
}
