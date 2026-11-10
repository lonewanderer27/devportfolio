/**
 * Pure gesture maths for the touch viewer (DPD-021 / DPD-024). No DOM access, so it can be tested
 * with `node --test`. The renderer (mobile-viewer.js) feeds it pointer measurements.
 */

/** Share of the stage width, on each side, where a tap turns the page. */
export const EDGE_ZONE = 0.2;
/** Pixels at the very screen edge that never count as a tap (the browser's own edge-swipe Back lives there). */
export const DEAD_STRIP = 8;
export const ZOOM_MIN = 1;
export const ZOOM_MAX = 4;
export const ZOOM_STEP = 0.5;
export const DOUBLE_TAP_ZOOM = 2.5;
/** The window (ms) in which a second tap completes a double-tap; a single tap waits this long before it acts. */
export const DOUBLE_TAP_WINDOW = 300;

const clamp = ( value, min, max ) => Math.min( max, Math.max( min, value ) );

/**
 * What a finished touch was: a tap in the left or right edge zone, a tap in the centre, or nothing
 * (it moved or lasted too long). `x` is relative to the stage, `w` the stage width.
 */
export function classifyTap( { x, w, t0, t1, moved } ) {
	if ( ! ( w > 0 ) || moved >= 10 || t1 - t0 > 300 ) {
		return null;
	}
	if ( x < DEAD_STRIP || x > w - DEAD_STRIP ) {
		return null;
	}
	if ( x <= w * EDGE_ZONE ) {
		return 'edge-prev';
	}
	if ( x >= w * ( 1 - EDGE_ZONE ) ) {
		return 'edge-next';
	}
	return 'centre';
}

/**
 * -1 (swipe right: previous), 1 (swipe left: next) or 0. The travel must be long enough (or quick
 * enough) and clearly horizontal, so a vertical drag never turns a page.
 */
export function swipeDirection( { dx, dy, dt } ) {
	const ax = Math.abs( dx );
	const ay = Math.abs( dy );
	if ( ax <= ay * 1.5 ) {
		return 0;
	}
	const fast = dt > 0 && ax >= 24 && ax / dt >= 0.4;
	if ( ax < 48 && ! fast ) {
		return 0;
	}
	return dx < 0 ? 1 : -1;
}

/** 'down' or 'up' for a clearly vertical drag of at least 24px, else 'none' (it drives the help button). */
export function verticalIntent( { dx = 0, dy } ) {
	if ( Math.abs( dy ) < 24 || Math.abs( dy ) <= Math.abs( dx ) ) {
		return 'none';
	}
	return dy > 0 ? 'down' : 'up';
}

/** Scale after a pinch: the starting scale times how much the two fingers moved apart. */
export function pinchScale( startDistance, distance, startScale ) {
	if ( ! ( startDistance > 0 ) || ! ( distance > 0 ) ) {
		return clamp( startScale, ZOOM_MIN, ZOOM_MAX );
	}
	return clamp( startScale * ( distance / startDistance ), ZOOM_MIN, ZOOM_MAX );
}

/** Keyboard zoom: one step in or out, kept on the range. */
export function stepZoom( scale, direction ) {
	return clamp( Math.round( ( scale + direction * ZOOM_STEP ) * 100 ) / 100, ZOOM_MIN, ZOOM_MAX );
}

/** Double-tap: 1x goes to 2.5x, anything zoomed goes back to 1x. */
export function doubleTapTarget( scale ) {
	return scale > ZOOM_MIN + 0.05 ? ZOOM_MIN : DOUBLE_TAP_ZOOM;
}

/** Two taps count as a double-tap when they are quick and close together. */
export function isDoubleTap( previous, next ) {
	if ( ! previous || ! next ) {
		return false;
	}
	return next.t - previous.t <= DOUBLE_TAP_WINDOW && Math.hypot( next.x - previous.x, next.y - previous.y ) <= 30;
}

/**
 * The pan (translate) that keeps the point under (cx, cy) fixed when the scale changes. The
 * transform is `translate(x, y) scale(s)` with the origin at the top left of the stage.
 */
export function zoomAround( { x, y, scale, nextScale, cx, cy } ) {
	const ratio = scale > 0 ? nextScale / scale : 1;
	return { x: cx - ( cx - x ) * ratio, y: cy - ( cy - y ) * ratio };
}

/** Keeps a zoomed stage covering its frame: no empty space past the edges. */
export function clampPan( { x, y, scale, w, h } ) {
	return {
		x: clamp( x, Math.min( 0, w - w * scale ), 0 ),
		y: clamp( y, Math.min( 0, h - h * scale ), 0 ),
	};
}

/** Distance between two points (a pinch's two fingers). */
export function distance( a, b ) {
	return Math.hypot( a.x - b.x, a.y - b.y );
}

/** `Zoom 150%` style percentage for the live region. */
export function zoomPercent( scale ) {
	return Math.round( scale * 100 );
}

/**
 * Which way a single tap on the book turns (Tap to Flip, DPC-012): -1 for the left half (previous view), 1 for
 * the right half (next view), 0 inside the dead strip at either edge (the phone's edge gesture, DEAD_STRIP).
 */
export function tapSide( x, width ) {
	if ( ! ( width > 0 ) || x < DEAD_STRIP || x > width - DEAD_STRIP ) {
		return 0;
	}
	return x < width / 2 ? -1 : 1;
}

/**
 * Whether the "Tap to Flip" hint shows (DPC-012, D7): a touch screen, a two-page view, nothing turned yet in this
 * opening, and the pages not zoomed (a tap then does not flip, as a swipe does not while zoomed).
 */
export function shouldShowTapHint( { coarse = false, step = 1, flipped = false, zoomed = false } = {} ) {
	return Boolean( coarse ) && step === 2 && ! flipped && ! zoomed;
}
