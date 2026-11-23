/**
 * Pure rules for the two rotations on the Home "Selected work" cards (DPH-013, DPH-017).
 *
 * No DOM here: view.js reads the page and applies what these functions return.
 */

/** Documents change every 3.5 s, videos every 4 s, so the two cards are never in step (DPH-013 AC4). */
export const BOOK_PERIOD = 3500;
export const FRAME_PERIOD = 4000;

/** Position in a cycle of `count` items, 0 for the active one, counting forward: 0 .. count - 1. */
function forwardOffset( index, active, count ) {
	return ( ( ( index - active ) % count ) + count ) % count;
}

/**
 * Where a book sits in the fan: 'front', 'right' (next in line), 'left' (the one that was in front last) or 'hidden'
 * (waiting out of sight). Advancing moves every book one step: right -> front -> left -> hidden -> right.
 *
 * @param {number} index  Book position in the list.
 * @param {number} active Position of the front book.
 * @param {number} count  Number of books (at least 1).
 * @return {'front'|'right'|'left'|'hidden'}
 */
export function bookRole( index, active, count ) {
	const offset = forwardOffset( index, active, count );
	if ( offset === 0 ) {
		return 'front';
	}
	if ( offset === 1 ) {
		return 'right';
	}
	if ( count >= 3 && offset === count - 1 ) {
		return 'left';
	}
	return 'hidden';
}

/**
 * Signed distance from the active frame: 0, then -1 / +1 for the neighbours, and so on, taking the short way round.
 *
 * @param {number} index  Frame position in the list.
 * @param {number} active Position of the playing frame.
 * @param {number} count  Number of frames (at least 1).
 * @return {number}
 */
export function frameOffset( index, active, count ) {
	const offset = forwardOffset( index, active, count );
	return offset > count / 2 ? offset - count : offset;
}

/**
 * What a film frame is: 'active' (middle, playing), 'prev' (last played, left), 'next' (right) or, further away,
 * 'far-prev' / 'far-next' (out of sight, parked on the side they will slide in from).
 *
 * @param {number} index  Frame position in the list.
 * @param {number} active Position of the playing frame.
 * @param {number} count  Number of frames (at least 1).
 * @return {'active'|'prev'|'next'|'far-prev'|'far-next'}
 */
export function frameRole( index, active, count ) {
	const offset = frameOffset( index, active, count );
	if ( offset === 0 ) {
		return 'active';
	}
	if ( offset === -1 ) {
		return 'prev';
	}
	if ( offset === 1 ) {
		return 'next';
	}
	return offset < 0 ? 'far-prev' : 'far-next';
}

/**
 * Moves a clock forward by `dt` ms, but only while it is running (paused = hover, focus, off screen, a viewer open).
 *
 * @param {number}  elapsed Milliseconds counted so far in this step.
 * @param {number}  dt      Milliseconds since the last frame.
 * @param {boolean} running Whether the clock may move.
 * @param {number}  period  Length of one step in ms.
 * @return {{elapsed:number,advanced:boolean}} `advanced` is true when a step has just ended; `elapsed` restarts from the overshoot.
 */
export function tick( elapsed, dt, running, period ) {
	if ( ! running ) {
		return { elapsed, advanced: false };
	}
	const next = elapsed + dt;
	if ( next >= period ) {
		return { elapsed: next - period, advanced: true };
	}
	return { elapsed: next, advanced: false };
}
