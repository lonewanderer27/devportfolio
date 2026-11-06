/**
 * The loading / "can't be previewed" message inside a viewer stage (the `role="status"` element in
 * render.php). One implementation for the desktop book and the mobile viewer.
 */
export function createStatus( { stage, status } ) {
	const textEl = status.querySelector( '.documents-modal__state-text' );
	const hintEl = status.querySelector( '.documents-modal__state-hint' );
	let hintKey = 'hint';
	return {
		/** Which `data-hint*` string explains an unavailable document (see hintKeyFor() in mode.js). */
		setHintKey( key ) {
			hintKey = key || 'hint';
		},
		/** 'loading', 'unavailable' or null (hidden). */
		set( kind ) {
			if ( ! kind ) {
				// Hide first, then clear, so nothing is announced as it goes away.
				status.hidden = true;
			}
			status.dataset.kind = kind || '';
			textEl.textContent = kind === 'loading' ? status.dataset.loading : kind === 'unavailable' ? status.dataset.unavailable : '';
			hintEl.textContent = kind === 'unavailable' ? ( status.dataset[ hintKey ] || status.dataset.hint ) : '';
			stage.setAttribute( 'aria-busy', kind === 'loading' ? 'true' : 'false' );
			if ( kind ) {
				// The text is in place before the region appears, so a screen reader announces it.
				status.hidden = false;
			}
		},
	};
}
