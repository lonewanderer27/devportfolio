/** Download link of the modal, shared by the desktop and mobile header (DPD-018). */
export function applyDownload( link, doc ) {
	link.removeAttribute( 'href' );
	link.removeAttribute( 'download' );
	link.removeAttribute( 'target' );
	link.removeAttribute( 'rel' );
	link.removeAttribute( 'aria-disabled' );
	link.setAttribute( 'aria-label', `Download ${ doc.title }` );
	if ( doc.downloadMode === 'file' ) {
		link.href = doc.downloadUrl;
		link.setAttribute( 'download', '' );
	} else if ( doc.downloadMode === 'embed' ) {
		link.href = doc.downloadUrl;
		link.target = '_blank';
		link.rel = 'noopener';
	} else {
		link.setAttribute( 'aria-disabled', 'true' );
	}
}

/** A disabled Download link does nothing when tapped. Call once per link. */
export function guardDisabledDownload( link ) {
	link.addEventListener( 'click', ( event ) => {
		if ( link.getAttribute( 'aria-disabled' ) === 'true' ) {
			event.preventDefault();
		}
	} );
}
