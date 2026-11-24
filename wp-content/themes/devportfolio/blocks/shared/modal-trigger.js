const queued = {};

/**
 * Remembers a click on a trigger that arrived before the modal's view.js was loaded (shared/lazy-modal.js). The
 * next bindOpen() for the same attribute replays it; a later click replaces an earlier one.
 *
 * @param {string}  attribute Attribute name, e.g. 'data-document-id'.
 * @param {Element} trigger   The clicked trigger.
 */
export function queueOpen( attribute, trigger ) {
	queued[ attribute ] = trigger;
}

/**
 * Opens a modal from a click on any element that carries a `data-*-id` attribute (DPC-024, DPD-009).
 *
 * One delegated click listener on `scope` serves every trigger of a page: the Documents cover and shelf
 * button (`data-document-id`) and the collateral title button (`data-collateral-id`). The page keeps its own
 * open function, so the dialog state stays where it is.
 *
 * A click queued with queueOpen() before the modal's view.js was loaded (DPB-NNN F1) is replayed once, after
 * the calling module has finished running.
 *
 * @param {EventTarget} scope     Element that receives the clicks (normally `document`).
 * @param {string}      attribute Attribute name, e.g. 'data-document-id'.
 * @param {(value: string, trigger: Element) => void} open Called with the attribute value and the trigger.
 * @param {Function}    defer     Runs the replay later (queueMicrotask; injected by tests).
 */
export function bindOpen( scope, attribute, open, defer = queueMicrotask ) {
	scope.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest ? event.target.closest( `[${ attribute }]` ) : null;
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		open( trigger.getAttribute( attribute ), trigger );
	} );

	const queuedTrigger = queued[ attribute ];
	if ( queuedTrigger ) {
		delete queued[ attribute ];
		defer( () => open( queuedTrigger.getAttribute( attribute ), queuedTrigger ) );
	}
}
