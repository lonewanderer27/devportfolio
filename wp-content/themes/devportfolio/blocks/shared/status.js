/**
 * The loading / "can't be previewed" message inside a viewer stage (the `role="status"` element in
 * render.php). One implementation for the desktop book and the mobile viewer.
 *
 * `pending` (DPC-025) is a collateral whose page images are still being made: its own text and the
 * sample loading image, no spinner. It is only reachable when the payload sets it, so the Documents
 * viewer keeps its current behaviour.
 */
export function createStatus( { stage, status, prefix = 'documents-modal' } ) {
	const textEl = status.querySelector( `.${ prefix }__state-text` );
	const hintEl = status.querySelector( `.${ prefix }__state-hint` );
	const spinnerEl = status.querySelector( `.${ prefix }__spinner` );
	const imageEl = status.querySelector( `.${ prefix }__state-image` );
	let hintKey = 'hint';
	return {
		/** Which `data-hint*` string explains an unavailable document (see hintKeyFor() in mode.js). */
		setHintKey( key ) {
			hintKey = key || 'hint';
		},
		/** 'loading', 'pending', 'unavailable' or null (hidden). */
		set( kind ) {
			if ( ! kind ) {
				// Hide first, then clear, so nothing is announced as it goes away.
				status.hidden = true;
			}
			status.dataset.kind = kind || '';
			const pending = kind === 'pending';
			const image = pending && status.dataset.loadingImage;
			textEl.textContent = kind === 'loading' ? status.dataset.loading
				: kind === 'pending' ? status.dataset.pending
					: kind === 'unavailable' ? status.dataset.unavailable
						: '';
			hintEl.textContent = kind === 'unavailable' ? ( status.dataset[ hintKey ] || status.dataset.hint ) : '';
			if ( spinnerEl ) {
				spinnerEl.hidden = pending;
			}
			if ( imageEl ) {
				imageEl.hidden = ! image;
				if ( image ) {
					imageEl.src = image;
				} else {
					imageEl.removeAttribute( 'src' );
				}
			}
			stage.setAttribute( 'aria-busy', kind === 'loading' || pending ? 'true' : 'false' );
			if ( kind ) {
				// The text is in place before the region appears, so a screen reader announces it.
				status.hidden = false;
			}
		},
	};
}
