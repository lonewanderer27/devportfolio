/**
 * Long-press on a touch screen (DPV-035): hold a thumbnail for HOLD_MS and the pop-out opens; lift the
 * finger and it closes. A quick tap is untouched (the click still opens the full-screen viewer). Only the
 * mobile routing gets it (shared/routing.js), and only a touch pointer.
 */
import { createRouting } from '../shared/routing.js';

export const HOLD_MS = 450;
export const MOVE_TOLERANCE = 10;

/**
 * Whether a finger has moved far enough to cancel the press.
 *
 * @param {number} dx Horizontal distance from where it landed, px.
 * @param {number} dy Vertical distance, px.
 * @return {boolean}
 */
export function movedTooFar( dx, dy ) {
	return Math.hypot( dx, dy ) > MOVE_TOLERANCE;
}

/**
 * Whether a pointer may start a press.
 *
 * @param {{pointerType:string,isPrimary:boolean,mobile:boolean}} env Pointer and screen.
 * @return {boolean}
 */
export function canLongPress( env ) {
	return 'touch' === env.pointerType && Boolean( env.isPrimary ) && Boolean( env.mobile );
}

/**
 * @param {HTMLElement} grid    The `[data-videos-grid]` element.
 * @param {{open:Function,close:Function,isOpen:Function}} popout From createPopout().
 * @param {{isMobile:Function}} [routing] Injectable for tests.
 */
export function attachLongPress( grid, popout, routing = createRouting() ) {
	let press = null;
	let swallowClick = false;
	let swallowTimer = 0;

	function cancel() {
		if ( press ) {
			window.clearTimeout( press.timer );
			press.item.classList.remove( 'is-pressing' );
			press = null;
		}
	}

	function fire() {
		if ( ! press ) {
			return;
		}
		const item = press.item;
		window.clearTimeout( press.timer );
		press = null;
		swallowClick = true;
		if ( navigator.vibrate ) {
			navigator.vibrate( 10 );
		}
		popout.open( item ); // removes is-pressing when the card is shown
	}

	grid.addEventListener( 'pointerdown', function ( event ) {
		if ( press || ! canLongPress( { pointerType: event.pointerType, isPrimary: event.isPrimary, mobile: routing.isMobile() } ) ) {
			return;
		}
		const item = event.target.closest ? event.target.closest( '.videos-item' ) : null;
		if ( ! item ) {
			return;
		}
		window.clearTimeout( swallowTimer );
		swallowClick = false;
		press = { item: item, x: event.clientX, y: event.clientY, timer: window.setTimeout( fire, HOLD_MS ) };
		item.classList.add( 'is-pressing' );
	} );

	grid.addEventListener( 'pointermove', function ( event ) {
		if ( press && movedTooFar( event.clientX - press.x, event.clientY - press.y ) ) {
			cancel();
		}
	} );

	function release() {
		cancel();
		if ( popout.isOpen() ) {
			popout.close();
			// The click that follows a long press is not a tap: swallow it, then forget it.
			swallowTimer = window.setTimeout( function () {
				swallowClick = false;
			}, 600 );
		}
	}
	grid.addEventListener( 'pointerup', release );
	grid.addEventListener( 'pointercancel', release );

	// A click after a fired press must not open the full-screen viewer.
	grid.addEventListener(
		'click',
		function ( event ) {
			if ( swallowClick ) {
				swallowClick = false;
				event.preventDefault();
				event.stopPropagation();
			}
		},
		true
	);

	// No save-image / copy menu on a thumbnail.
	grid.addEventListener( 'contextmenu', function ( event ) {
		event.preventDefault();
	} );

	// Once the card is open the finger must not pan the page (touch-action cannot change mid-touch).
	grid.addEventListener(
		'touchmove',
		function ( event ) {
			if ( popout.isOpen() && event.cancelable ) {
				event.preventDefault();
			}
		},
		{ passive: false }
	);
}
