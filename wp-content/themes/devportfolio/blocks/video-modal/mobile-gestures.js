/**
 * Touch maths of the mobile video viewer (DPV-020, DPV-023). Pure: runs under `node --test`.
 */
import { tapSide, DOUBLE_TAP_WINDOW } from '../shared/gestures.js';
import { SEEK_STEP } from './player-model.js';

export const TOP_EDGE = 64; // a swipe down that starts this close to the top closes the viewer
export const TAP_MAX_MOVE = 10;
export const TAP_MAX_TIME = 300;
export const SWIPE_MIN = 48;
export const SWIPE_FAST_MIN = 24;
export const SWIPE_FAST_SPEED = 0.4; // px per ms

/**
 * What a finished touch was.
 *
 * @param {{x0:number,y0:number,x1:number,y1:number,dt:number}} stroke Start and end (px, screen) and duration (ms).
 * @return {'tap'|'next'|'prev'|'close'|'none'}
 */
export function classifyStroke( { x0, y0, x1, y1, dt } ) {
	const dx = x1 - x0;
	const dy = y1 - y0;
	const ax = Math.abs( dx );
	const ay = Math.abs( dy );
	if ( ax < TAP_MAX_MOVE && ay < TAP_MAX_MOVE ) {
		return dt <= TAP_MAX_TIME ? 'tap' : 'none';
	}
	if ( ay <= ax * 1.5 ) {
		return 'none';
	}
	const fast = dt > 0 && ay >= SWIPE_FAST_MIN && ay / dt >= SWIPE_FAST_SPEED;
	if ( ay < SWIPE_MIN && ! fast ) {
		return 'none';
	}
	if ( dy < 0 ) {
		return 'next';
	}
	return y0 <= TOP_EDGE ? 'close' : 'prev';
}

/**
 * What a tap does.
 *
 * - `skip` off: toggle play/pause at once.
 * - second tap inside the window: skip 5 seconds by the side of this tap (nothing in the edge strips);
 * - otherwise: toggle once the window has passed without a second tap.
 *
 * @param {{x:number,width:number,now:number,last:?{x:number,t:number},skip?:boolean}} args `last` is the previous tap.
 * @return {{kind:'toggle-now'}|{kind:'toggle-later'}|{kind:'skip',seconds:number}|{kind:'none'}}
 */
export function tapAction( { x, width, now, last, skip = true } ) {
	if ( ! skip ) {
		return { kind: 'toggle-now' };
	}
	if ( last && now - last.t <= DOUBLE_TAP_WINDOW ) {
		const side = tapSide( x, width );
		return side === 0 ? { kind: 'none' } : { kind: 'skip', seconds: side * SEEK_STEP };
	}
	return { kind: 'toggle-later' };
}

export const SLIDE_RESIST = 0.3; // a drag with no video that way moves only this share of the finger's distance
export const COMMIT_FRACTION = 1 / 3; // a release past this share of the screen completes the slide

/**
 * Where the visible video sits while the finger drags it.
 *
 * @param {number}  dy        Finger movement since the drag started (px, negative = up).
 * @param {boolean} hasTarget Whether a video lies that way.
 * @return {number}
 */
export function dragOffset( dy, hasTarget ) {
	return hasTarget ? dy : dy * SLIDE_RESIST;
}

/**
 * Where the incoming video's poster sits: one screen below the visible video for `next`, above it for `prev`.
 *
 * @param {'next'|'prev'} kind
 * @param {number}        offset The visible video's offset (px).
 * @param {number}        height Screen height (px).
 * @return {number}
 */
export function peekOffset( kind, offset, height ) {
	return offset + ( kind === 'next' ? height : -height );
}

/**
 * Whether a released drag moves on to the neighbour: the finger ended on the side it started (`kind`) and either
 * passed a third of the screen or flicked (fast enough, at least SWIPE_MIN_FLICK px). A slow short drag springs back.
 *
 * @param {{dy:number,dt:number,height:number,kind:'next'|'prev',hasTarget:boolean}} args dt in ms.
 * @return {boolean}
 */
export function shouldCommit( { dy, dt, height, kind, hasTarget } ) {
	const sameSide = dy < 0 ? kind === 'next' : kind === 'prev';
	const flick = dt > 0 && Math.abs( dy ) >= SWIPE_FAST_MIN && Math.abs( dy ) / dt >= SWIPE_FAST_SPEED;
	return hasTarget && sameSide && ( Math.abs( dy ) > height * COMMIT_FRACTION || flick );
}
