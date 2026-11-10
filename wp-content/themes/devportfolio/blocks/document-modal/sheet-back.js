/**
 * History entry of the mobile Pages sheet (DPD-020). While the sheet is open it owns one history entry (same
 * URL, marked in the state), so the phone's Back button closes only the sheet and a second Back closes the
 * viewer. Kept apart from mobile.js, which knows nothing about history; view.js removes a leftover entry when
 * the viewer itself closes with the sheet open.
 */
import { withSheetState, hasSheetState } from '../shared/deeplink.js';

/**
 * @param {Object}   args
 * @param {Function} args.isOpen Whether the sheet is open.
 * @param {Function} args.onBack Called when Back (or Forward) leaves the sheet's entry: close the sheet.
 * @return {{push:Function,pop:Function,release:Function,pushed:Function}}
 */
export function createSheetHistory( { isOpen, onBack } ) {
	let entry = false;
	let expectPop = false;

	// Registered in the capture phase so it runs first, and it stops the event for the moves the sheet owns. The URL
	// does not change when the sheet opens or closes, but WordPress's Interactivity Router (loaded for the Navigation
	// block) answers every popstate: for a URL it has not cached, here `?document_slug=...`, it reloads the whole
	// page, which flickered the modal and re-opened it from scratch the first time a page was chosen.
	window.addEventListener(
		'popstate',
		( event ) => {
			if ( expectPop ) {
				// The pop that our own pop() caused.
				expectPop = false;
				event.stopImmediatePropagation();
				return;
			}
			if ( isOpen() && ! hasSheetState( event.state ) ) {
				entry = false;
				event.stopImmediatePropagation();
				onBack();
			}
		},
		true
	);

	return {
		/** Adds the entry (same URL) when the sheet opens. */
		push() {
			window.history.pushState( withSheetState( window.history.state ), '' );
			entry = true;
		},
		/** The sheet closed by itself (tap, Esc, a choice): pop the entry it added. */
		pop() {
			if ( entry ) {
				entry = false;
				expectPop = true;
				window.history.back();
			}
		},
		/** The viewer closed with the sheet open: forget the entry and say whether one is left behind (0 or 1). */
		release() {
			const left = entry ? 1 : 0;
			entry = false;
			return left;
		},
		pushed: () => entry,
	};
}
