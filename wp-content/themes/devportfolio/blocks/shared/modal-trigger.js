/**
 * Opens a modal from a click on any element that carries a `data-*-id` attribute (DPC-024, DPD-009).
 *
 * One delegated click listener on `scope` serves every trigger of a page: the Documents cover and shelf
 * button (`data-document-id`) and the collateral title button (`data-collateral-id`). The page keeps its own
 * open function, so the dialog state stays where it is.
 *
 * @param {EventTarget} scope     Element that receives the clicks (normally `document`).
 * @param {string}      attribute Attribute name, e.g. 'data-document-id'.
 * @param {(value: string, trigger: Element) => void} open Called with the attribute value and the trigger.
 */
export function bindOpen( scope, attribute, open ) {
	scope.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest ? event.target.closest( `[${ attribute }]` ) : null;
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		open( trigger.getAttribute( attribute ), trigger );
	} );
}
