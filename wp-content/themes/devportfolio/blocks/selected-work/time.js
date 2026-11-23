/**
 * Small pure helpers for the film strip's running time and progress bar (DPH-016).
 */

/**
 * The running time shown top right of the playing frame: m:ss, counting up, never past the clip's length.
 *
 * @param {number} elapsedMs     Milliseconds since the frame became active.
 * @param {number} lengthSeconds Clip length in seconds, 0 or less when unknown.
 * @return {string}
 */
export function timeLabel( elapsedMs, lengthSeconds ) {
	let seconds = Math.max( 0, Math.floor( elapsedMs / 1000 ) );
	if ( lengthSeconds > 0 ) {
		seconds = Math.min( seconds, Math.floor( lengthSeconds ) );
	}
	return `${ Math.floor( seconds / 60 ) }:${ String( seconds % 60 ).padStart( 2, '0' ) }`;
}

/**
 * How full the progress bar is: elapsed / period, kept between 0 and 1.
 *
 * @param {number} elapsedMs Milliseconds since the frame became active.
 * @param {number} periodMs  Length of the step in ms.
 * @return {number}
 */
export function fraction( elapsedMs, periodMs ) {
	if ( periodMs <= 0 ) {
		return 0;
	}
	return Math.min( 1, Math.max( 0, elapsedMs / periodMs ) );
}
