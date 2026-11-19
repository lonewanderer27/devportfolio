/**
 * The picture's flight on the Videos page (DPV-033 desktop dialog, DPV-036 pop-out): an element grows out of
 * its grid tile and settles into its own place with element.animate(). Only its transform and size move, so it
 * keeps its own stacking (above the grid) and needs no view transition, which draws in an overlay that can
 * end up under the grid.
 */
export const FLIGHT_MS = 400;
export const FLIGHT_EASE = 'cubic-bezier(0.2, 0.8, 0.2, 1)';

/**
 * The frame an element needs to sit over `rect` while it is laid out at `place`: the offset that puts it there, and
 * the size of `rect`. A frame with `place` as both arguments is the element's own place.
 *
 * The offset depends on how the element's box changes with its size: a box centred in its parent (the pop-out
 * card) keeps its centre, so the centres are aligned (`center`); a box pinned to its top-left corner (the
 * dialog's pane) keeps that corner, so the corners are aligned (`start`).
 *
 * @param {{left:number,top:number,width:number,height:number}} rect  Where it should appear.
 * @param {{left:number,top:number,width:number,height:number}} place Where it is laid out.
 * @param {'center'|'start'} [anchor] What stays put when the size changes.
 * @return {{transform:string,width:string,height:string}}
 */
export function frameOf( rect, place, anchor = 'center' ) {
	const centred = 'center' === anchor;
	const dx = centred ? rect.left + rect.width / 2 - ( place.left + place.width / 2 ) : rect.left - place.left;
	const dy = centred ? rect.top + rect.height / 2 - ( place.top + place.height / 2 ) : rect.top - place.top;
	return { transform: `translate(${ dx }px, ${ dy }px)`, width: `${ rect.width }px`, height: `${ rect.height }px` };
}

/**
 * Plays `el` from frame `from` to frame `to`. `onDone` runs when it lands; a cancelled flight does not call it.
 *
 * @param {Element}  el
 * @param {Object}   from
 * @param {Object}   to
 * @param {Object}   [o]
 * @param {number}   [o.duration]
 * @param {string[]} [o.radius] Border radius at the start and at the end.
 * @param {Function} [o.onDone]
 * @return {Animation}
 */
export function flyTo( el, from, to, { duration = FLIGHT_MS, radius = [ '4px', '14px' ], onDone } = {} ) {
	const anim = el.animate(
		[ { ...from, borderRadius: radius[ 0 ] }, { ...to, borderRadius: radius[ 1 ] } ],
		{ duration, easing: FLIGHT_EASE }
	);
	if ( onDone ) {
		anim.onfinish = onDone;
	}
	return anim;
}

/** @return {number} The flight length: none with reduced motion. */
export function flightDuration() {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 0 : FLIGHT_MS;
}
