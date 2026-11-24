/**
 * Home hero: the "Scroll down" hint leaves for the rest of the visit once the visitor scrolls.
 *
 * Scroll events that fire while the page loads (for example the browser restoring the scroll position on a
 * refresh) are ignored, so a refresh shows the hint again.
 */
( function () {
	var hint = document.querySelector( '.hero__scroll' );

	if ( ! hint ) {
		return;
	}

	var ready = false;

	function check() {
		if ( ready && window.scrollY > 0 ) {
			hint.hidden = true;
			window.removeEventListener( 'scroll', check );
		}
	}

	window.addEventListener( 'load', function () {
		window.setTimeout( function () {
			ready = true;
		}, 0 );
	} );
	window.addEventListener( 'scroll', check, { passive: true } );
}() );
