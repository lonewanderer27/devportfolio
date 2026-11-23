/**
 * Pure rules for the Home testimonials (DPH-022, DPH-023). No DOM here, so node --test can run it.
 */

export const PERIOD = 6000;
export const SWIPE_MIN = 48;

/**
 * Next index in a ring.
 *
 * @param {number} index     Current index.
 * @param {number} count     How many slides.
 * @param {number} direction 1 for next, -1 for previous.
 * @return {number}
 */
export function stepIndex( index, count, direction ) {
	if ( count < 1 ) {
		return 0;
	}
	return ( ( ( index + direction ) % count ) + count ) % count;
}

/**
 * Which way a finger stroke turns the quote: -1 previous, 0 nothing, 1 next. Mostly vertical or short strokes do nothing.
 *
 * @param {number} dx Horizontal travel in px (negative = left).
 * @param {number} dy Vertical travel in px.
 * @return {number}
 */
export function swipeDirection( dx, dy ) {
	if ( Math.abs( dx ) < SWIPE_MIN || Math.abs( dx ) < Math.abs( dy ) * 1.5 ) {
		return 0;
	}
	return dx < 0 ? 1 : -1;
}

/**
 * Whether the automatic rotation may run now.
 *
 * @param {{count:number,reducedMotion:boolean,hover:boolean,focus:boolean,hidden:boolean}} s State.
 * @return {boolean}
 */
export function shouldRun( s ) {
	return s.count > 1 && ! s.reducedMotion && ! s.hover && ! s.focus && ! s.hidden;
}
