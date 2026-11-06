/**
 * The bottom sheet shell of the mobile modal (DPD-020, DPD-023): everything a sheet needs that is not its content.
 *
 * It builds the dim layer and the sheet inside the mobile dialog (a grab handle, a header row and a scrolling body,
 * which the caller fills), makes the viewer behind it inert while it is open, and handles the ways of dismissing it
 * that every sheet shares: a tap on the dim layer, Esc, a tap on the handle, and a drag down on the handle or the
 * header row. The shell never closes itself: each of those asks `onDismiss`, so the content can save what it needs
 * (the Pages sheet remembers its scroll position) before calling `hide()`.
 *
 * The drag maths are pure and tested with `node --test` (tests/bottom-sheet.test.mjs).
 */
import { el } from './dom.js';

/** Dragging the sheet down: farther than this (px) closes it; a quick flick closes it from a shorter drag. */
export const DRAG_CLOSE_DISTANCE = 96;
export const DRAG_FLICK_DISTANCE = 24;
export const DRAG_FLICK_SPEED = 0.6; // px per millisecond
export const DRAG_TAP_SLOP = 6; // movement (px) that still counts as a tap

/**
 * What to do when the handle is let go after a drag.
 *
 * @param {Object} args { dy: distance dragged down (px), speed: last speed down (px/ms) }
 * @return {'close'|'snap'} Close the sheet, or spring back.
 */
export function dragOutcome( { dy = 0, speed = 0 } = {} ) {
	if ( dy >= DRAG_CLOSE_DISTANCE ) {
		return 'close';
	}
	return dy >= DRAG_FLICK_DISTANCE && speed >= DRAG_FLICK_SPEED ? 'close' : 'snap';
}

/** A drag only moves the sheet down: up is held at 0. */
export function dragOffset( dy ) {
	return Math.max( 0, Number( dy ) || 0 );
}

const reduced = () => typeof window.matchMedia === 'function' && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

/**
 * Builds the shell inside the mobile dialog (hidden until `show()`).
 *
 * Controls inside the header that must keep their own click (view toggle, close button) carry `data-no-drag`, so
 * pressing them never starts a drag.
 *
 * @param {Object}   args
 * @param {Element}  args.dialog      The mobile dialog.
 * @param {string}   args.titleId     Unique id of the title element the caller puts in the header (`aria-labelledby`).
 * @param {string}   args.handleLabel Accessible name of the grab handle ("Close pages").
 * @param {string}   args.modifier    Extra class for the sheet (`documents-mobile__sheet--pages`).
 * @param {Function} args.onDismiss   Called when the visitor asks to close it (dim, Esc, handle, drag).
 * @return {{sheet:Element,head:Element,body:Element,grab:Element,show:Function,hide:Function,isOpen:Function}}
 */
export function createBottomSheet( { dialog, titleId, handleLabel = 'Close', modifier = '', onDismiss } ) {
	const panel = dialog.querySelector( '.documents-mobile__panel' );
	const dim = el( 'div', 'documents-mobile__dim' );
	dim.hidden = true;
	const sheet = el( 'div', `documents-mobile__sheet${ modifier ? ` ${ modifier }` : '' }`, { role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId } );
	sheet.hidden = true;
	const grab = el( 'button', 'documents-mobile__grab', { type: 'button', 'aria-label': handleLabel } );
	const head = el( 'div', 'documents-mobile__sheet-head' );
	const body = el( 'div', 'documents-mobile__sheet-body' );
	sheet.append( grab, head, body );
	dialog.append( dim, sheet );

	const state = { open: false, opener: null };
	const dismiss = () => {
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const clearDrag = () => {
		sheet.style.transform = '';
		sheet.style.transition = '';
		dim.style.opacity = '';
		dim.style.transition = '';
	};

	/**
	 * Shows the sheet and makes the viewer behind it inert.
	 *
	 * @param {Object} args { opener: the control to give the focus back to, focus: the control to focus now }
	 */
	const show = ( { opener = null, focus = null } = {} ) => {
		state.open = true;
		state.opener = opener;
		if ( panel ) {
			panel.inert = true;
		}
		clearDrag();
		dim.hidden = false;
		sheet.hidden = false;
		if ( focus && typeof focus.focus === 'function' ) {
			focus.focus();
		}
	};

	/** Hides the sheet. Silent (no focus return) when the viewer or the document is going away. */
	const hide = ( silent = false ) => {
		if ( ! state.open ) {
			return false;
		}
		state.open = false;
		sheet.hidden = true;
		dim.hidden = true;
		clearDrag();
		if ( panel ) {
			panel.inert = false;
		}
		const opener = state.opener;
		state.opener = null;
		if ( opener && opener.isConnected && typeof opener.focus === 'function' && ! silent ) {
			opener.focus();
		}
		return true;
	};

	dim.addEventListener( 'click', dismiss );

	/**
	 * Drag the handle (or the header row) down to dismiss. A tap still closes it; so do Enter and Space on the
	 * handle. The sheet follows the finger, then closes past the distance (or on a flick) or springs back.
	 */
	let drag = null;
	let swallowClick = false;
	const moveSheet = ( dy ) => {
		sheet.style.transition = 'none';
		sheet.style.transform = `translateY(${ dragOffset( dy ) }px)`;
		dim.style.transition = 'none';
		dim.style.opacity = String( Math.max( 0.2, 1 - dragOffset( dy ) / Math.max( 1, sheet.offsetHeight ) ) );
	};
	const finishDrag = ( outcome ) => {
		if ( outcome === 'close' ) {
			if ( reduced() ) {
				clearDrag();
				dismiss();
				return;
			}
			sheet.style.transition = 'transform 180ms ease-in';
			dim.style.transition = 'opacity 180ms ease-in';
			sheet.style.transform = 'translateY(100%)';
			dim.style.opacity = '0';
			let done = false;
			const end = () => {
				if ( done ) {
					return;
				}
				done = true;
				clearDrag();
				dismiss();
			};
			sheet.addEventListener( 'transitionend', end, { once: true } );
			window.setTimeout( end, 260 );
			return;
		}
		// Back to where it was.
		if ( reduced() ) {
			clearDrag();
			return;
		}
		sheet.style.transition = 'transform 160ms ease-out';
		dim.style.transition = 'opacity 160ms ease-out';
		sheet.style.transform = 'translateY(0)';
		dim.style.opacity = '';
		window.setTimeout( clearDrag, 200 );
	};
	const startDrag = ( event ) => {
		if ( ! state.open || ( event.pointerType === 'mouse' && event.button !== 0 ) || event.target.closest( '[data-no-drag]' ) ) {
			return;
		}
		drag = { id: event.pointerId, y0: event.clientY, t0: event.timeStamp, y: event.clientY, t: event.timeStamp, speed: 0, moved: false, el: event.currentTarget };
		try {
			drag.el.setPointerCapture( event.pointerId );
		} catch ( e ) {
			// A capture failure only means the drag ends if the finger leaves the handle.
		}
	};
	const moveDrag = ( event ) => {
		if ( ! drag || event.pointerId !== drag.id ) {
			return;
		}
		const dy = event.clientY - drag.y0;
		if ( ! drag.moved && Math.abs( dy ) < DRAG_TAP_SLOP ) {
			return;
		}
		drag.moved = true;
		const dt = Math.max( 1, event.timeStamp - drag.t );
		drag.speed = ( event.clientY - drag.y ) / dt;
		drag.y = event.clientY;
		drag.t = event.timeStamp;
		moveSheet( dy );
	};
	const endDrag = ( event, cancelled = false ) => {
		if ( ! drag || event.pointerId !== drag.id ) {
			return;
		}
		const finished = drag;
		drag = null;
		try {
			finished.el.releasePointerCapture( event.pointerId );
		} catch ( e ) {
			// Already released.
		}
		if ( ! finished.moved ) {
			return; // A tap: the click that follows closes the sheet.
		}
		swallowClick = true; // The click a drag ends with must not close the sheet a second time.
		window.setTimeout( () => {
			swallowClick = false;
		}, 0 );
		finishDrag( cancelled ? 'snap' : dragOutcome( { dy: event.clientY - finished.y0, speed: finished.speed } ) );
	};
	[ grab, head ].forEach( ( node ) => {
		node.addEventListener( 'pointerdown', startDrag );
		node.addEventListener( 'pointermove', moveDrag );
		node.addEventListener( 'pointerup', ( event ) => endDrag( event ) );
		node.addEventListener( 'pointercancel', ( event ) => endDrag( event, true ) );
	} );
	grab.addEventListener( 'click', () => {
		if ( ! swallowClick ) {
			dismiss();
		}
	} );
	sheet.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			event.stopPropagation();
			dismiss();
		}
	} );
	// The dialog's own Esc closes the viewer; while the sheet is open it closes the sheet first.
	dialog.addEventListener( 'cancel', ( event ) => {
		if ( state.open ) {
			event.preventDefault();
			dismiss();
		}
	} );

	return { sheet, head, body, grab, show, hide, isOpen: () => state.open };
}
