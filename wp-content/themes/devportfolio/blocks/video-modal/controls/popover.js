/**
 * The popovers of one viewer. Volume and speed never open together; a press outside an open popover closes
 * it; Escape closes it first (the dialog stays open) and returns focus to its button.
 */
export function createPopovers( dialog ) {
	const items = [];
	const setOpen = ( item, open ) => {
		item.pop.hidden = ! open;
		item.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};
	const closeAll = ( keep = null ) => {
		items.forEach( ( item ) => {
			if ( item !== keep ) {
				setOpen( item, false );
			}
		} );
	};

	document.addEventListener( 'pointerdown', ( event ) => {
		items.forEach( ( item ) => {
			if ( ! item.pop.hidden && ! item.pop.contains( event.target ) && ! item.trigger.contains( event.target ) ) {
				setOpen( item, false );
			}
		} );
	} );
	dialog.addEventListener( 'keydown', ( event ) => {
		const open = items.find( ( item ) => ! item.pop.hidden );
		if ( open && event.key === 'Escape' ) {
			event.preventDefault();
			closeAll();
			open.trigger.focus();
		}
	} );

	return {
		register: ( item ) => items.push( item ),
		toggle( item ) {
			const open = item.pop.hidden;
			closeAll( item );
			setOpen( item, open );
		},
		closeAll,
	};
}
