/**
 * Zoom and pan state for a touch viewer: pinch, double-tap, drag-to-pan and keyboard steps, on top of the
 * pure maths in gestures.js. It owns only the numbers; `apply( zoom )` puts them on the page, and `box()`
 * says how big the zoomed thing is (so the pan stays inside it). Shared by the desktop book (coarse
 * pointers) and the mobile viewer.
 */
import {
	pinchScale,
	zoomAround,
	clampPan,
	doubleTapTarget,
	isDoubleTap,
	distance,
	stepZoom,
	ZOOM_MIN,
} from './gestures.js';

/** Above this scale the page counts as zoomed: turning and swiping give way to panning. */
export const ZOOMED = ZOOM_MIN + 0.05;

const IDENTITY = () => ( { scale: 1, x: 0, y: 0 } );
const middle = ( a, b ) => ( { x: ( a.x + b.x ) / 2, y: ( a.y + b.y ) / 2 } );

/**
 * @param {object}   options
 * @param {Function} options.box     () => { w, h } the unzoomed size of the zoomed element.
 * @param {Function} options.apply   ( zoom ) Called after every change with { scale, x, y }.
 */
export function createTouchZoom( { box, apply } ) {
	let zoom = IDENTITY();
	let pinch = null;
	let panFrom = null;
	let lastTap = null;

	const set = ( next ) => {
		zoom = next;
		apply( zoom );
	};
	const clamp = ( x, y, scale ) => {
		const { w, h } = box();
		const pan = clampPan( { x, y, scale, w, h } );
		return { scale, x: pan.x, y: pan.y };
	};

	const api = {
		get: () => zoom,
		isZoomed: () => zoom.scale > ZOOMED,

		/** Back to 1x. Returns false when it already was. */
		reset() {
			if ( zoom.scale === 1 && zoom.x === 0 && zoom.y === 0 ) {
				return false;
			}
			pinch = null;
			panFrom = null;
			set( IDENTITY() );
			return true;
		},

		/** Zooms to `scale` keeping the point (cx, cy) still. */
		zoomAt( scale, cx, cy ) {
			const next = Math.max( ZOOM_MIN, scale );
			const pan = zoomAround( { x: zoom.x, y: zoom.y, scale: zoom.scale, nextScale: next, cx, cy } );
			set( clamp( pan.x, pan.y, next ) );
		},

		/** Keyboard zoom: one step in (+1) or out (-1) around the centre. Returns false at the limit. */
		step( direction ) {
			const next = stepZoom( zoom.scale, direction );
			if ( next === zoom.scale ) {
				return false;
			}
			const { w, h } = box();
			api.zoomAt( next, w / 2, h / 2 );
			return true;
		},

		/** A tap: true when it completes a double-tap (then it also zooms in or out around the point). */
		tap( now ) {
			if ( isDoubleTap( lastTap, now ) ) {
				lastTap = null;
				api.zoomAt( doubleTapTarget( zoom.scale ), now.zx === undefined ? now.x : now.zx, now.zy === undefined ? now.y : now.zy );
				return true;
			}
			lastTap = now;
			return false;
		},
		forgetTap() {
			lastTap = null;
		},

		/** Two fingers down at points a and b (relative to the zoomed element). */
		startPinch( a, b ) {
			pinch = { dist: distance( a, b ), scale: zoom.scale, pan: { x: zoom.x, y: zoom.y }, mid: middle( a, b ) };
		},
		movePinch( a, b ) {
			if ( ! pinch ) {
				return;
			}
			const scale = pinchScale( pinch.dist, distance( a, b ), pinch.scale );
			const mid = middle( a, b );
			const around = zoomAround( { x: pinch.pan.x, y: pinch.pan.y, scale: pinch.scale, nextScale: scale, cx: pinch.mid.x, cy: pinch.mid.y } );
			set( clamp( around.x + ( mid.x - pinch.mid.x ), around.y + ( mid.y - pinch.mid.y ), scale ) );
		},
		endPinch() {
			pinch = null;
		},

		/** One finger drag of a zoomed page: remembers where the pan started, then moves by (dx, dy). */
		startPan() {
			panFrom = { x: zoom.x, y: zoom.y };
		},
		movePan( dx, dy ) {
			if ( ! panFrom ) {
				api.startPan();
			}
			set( clamp( panFrom.x + dx, panFrom.y + dy, zoom.scale ) );
		},
	};
	return api;
}
