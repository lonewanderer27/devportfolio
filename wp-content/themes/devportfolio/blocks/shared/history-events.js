/**
 * One capture-phase `popstate` listener on window for every viewer module (DPB-NNN F1).
 *
 * Back and Forward must reach the modals and their sheets before anything that is registered later: WordPress's
 * Interactivity Router answers every popstate and reloads the page for a URL it has not cached
 * (`?document_slug=...`). Since the viewers load on first use, their own listeners are added late; this module is
 * imported by each modal's boot.js, so its one window listener is added while the page's scripts run, and every
 * handler added later runs inside it, in the order it was added. A handler that calls
 * `event.stopImmediatePropagation()` stops the handlers after it as well as every later window listener, exactly
 * as when each module had its own listener.
 */

/**
 * @param {EventTarget|null} target Normally `window`; null where there is none (node --test).
 * @return {{install: Function, addEventListener: Function, count: Function}}
 */
export function createHistoryEvents( target ) {
	const handlers = [];
	let installed = false;

	const dispatch = ( event ) => {
		let stopped = false;
		const stop = event.stopImmediatePropagation;
		event.stopImmediatePropagation = function () {
			stopped = true;
			if ( stop ) {
				stop.call( event );
			}
		};
		for ( const handler of handlers.slice() ) {
			handler( event );
			if ( stopped ) {
				return;
			}
		}
	};

	const api = {
		/** Adds the one window listener (once). */
		install() {
			if ( ! installed && target ) {
				target.addEventListener( 'popstate', dispatch, true );
				installed = true;
			}
		},
		/**
		 * Same call shape as `window.addEventListener( 'popstate', handler, true )`; always the capture phase.
		 *
		 * @param {string}   type    Must be 'popstate'.
		 * @param {Function} handler Called with the PopStateEvent.
		 */
		addEventListener( type, handler ) {
			if ( 'popstate' !== type ) {
				throw new Error( 'historyEvents only carries popstate' );
			}
			api.install();
			handlers.push( handler );
		},
		/** Number of handlers (for tests). */
		count: () => handlers.length,
	};
	return api;
}

export const historyEvents = createHistoryEvents( typeof window === 'undefined' ? null : window );
