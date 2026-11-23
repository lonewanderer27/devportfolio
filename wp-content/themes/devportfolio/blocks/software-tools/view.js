/**
 * Home software tools (DPH-024): one tool is in colour at a time.
 *
 * A tap (or Enter, Space: they are buttons) colours that tool and moves the colour off the previous one; the same tool
 * again turns it grey. On a device with hover the CSS colours a tool under the pointer by itself.
 */
function start( list ) {
	list.addEventListener( 'click', ( event ) => {
		const tile = event.target.closest( '.software-tools__tile' );
		if ( ! tile || ! list.contains( tile ) ) {
			return;
		}
		const wasOn = tile.getAttribute( 'aria-pressed' ) === 'true';
		list.querySelectorAll( '.software-tools__tile[aria-pressed="true"]' ).forEach( ( other ) => {
			other.setAttribute( 'aria-pressed', 'false' );
		} );
		if ( ! wasOn ) {
			tile.setAttribute( 'aria-pressed', 'true' );
		}
	} );
}

document.querySelectorAll( '[data-software-tools]' ).forEach( start );
