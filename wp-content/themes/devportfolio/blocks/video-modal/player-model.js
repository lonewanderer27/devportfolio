/**
 * Time text, seek maths and the replay rule for the video player (DPV-017, DPV-018). Pure: no DOM.
 * `formatTime()` matches devportfolio_video_length_label(): m:ss, h:mm:ss from one hour.
 */
export const SEEK_STEP = 5;
export const MAX_AUTO_REPLAYS = 1;
export const PLAYBACK_RATES = [ 0.5, 0.75, 1, 1.25, 1.5, 2 ];

/** A volume from 0 to 1. A missing or non-finite value is full volume. */
export function clampVolume( value ) {
	if ( ! Number.isFinite( value ) ) {
		return 1;
	}
	return Math.min( Math.max( value, 0 ), 1 );
}

/** m:ss, or h:mm:ss from one hour. A missing, negative or non-finite value is 0:00. */
export function formatTime( seconds ) {
	const total = Number.isFinite( seconds ) && seconds > 0 ? Math.floor( seconds ) : 0;
	const hours = Math.floor( total / 3600 );
	const minutes = Math.floor( ( total % 3600 ) / 60 );
	const secs = String( total % 60 ).padStart( 2, '0' );
	if ( hours > 0 ) {
		return `${ hours }:${ String( minutes ).padStart( 2, '0' ) }:${ secs }`;
	}
	return `${ minutes }:${ secs }`;
}

/** "0:08 / 0:28" for the time readout. */
export function timeLabel( time, duration ) {
	return `${ formatTime( time ) } / ${ formatTime( duration ) }`;
}

/** "0:08 of 0:28" for the slider's aria-valuetext. */
export function ariaValueText( time, duration ) {
	return `${ formatTime( time ) } of ${ formatTime( duration ) }`;
}

/** Keeps a time inside [0, duration]. An unknown duration gives 0. */
export function clampTime( time, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 || ! Number.isFinite( time ) ) {
		return 0;
	}
	return Math.min( Math.max( time, 0 ), duration );
}

/** Progress as a percentage 0 to 100 for the CSS `--p` variable. */
export function progressPercent( time, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 ) {
		return 0;
	}
	return ( clampTime( time, duration ) / duration ) * 100;
}

/**
 * The new time for an arrow key on the scrub bar, or null when the key is not an arrow or the
 * duration is not known yet (so the default key handling is left alone).
 */
export function seekForKey( key, current, duration ) {
	if ( ! Number.isFinite( duration ) || duration <= 0 ) {
		return null;
	}
	if ( key === 'ArrowLeft' ) {
		return clampTime( current - SEEK_STEP, duration );
	}
	if ( key === 'ArrowRight' ) {
		return clampTime( current + SEEK_STEP, duration );
	}
	return null;
}

/** 'replay' while the automatic replays of this session are used up to MAX_AUTO_REPLAYS, else 'stop'. */
export function afterEnd( autoReplays ) {
	return autoReplays < MAX_AUTO_REPLAYS ? 'replay' : 'stop';
}
