/**
 * Which dialog a screen gets: the single definition (DPD-017, DPD-024). view.js (which dialog to open and
 * when to swap), mobile.js (landscape footer) and style.css (which dialog is hidden) all follow it; a test
 * checks that the CSS repeats these exact queries, so the three can never disagree.
 *
 * Mobile dialog: 700px wide or less, or a touch screen held sideways (short and wide).
 * Desktop dialog: everything else.
 */
export const WIDE_QUERY = '(min-width: 701px)';
export const SIDEWAYS_QUERY = '(pointer: coarse) and (orientation: landscape) and (max-height: 520px)';

/** @param {Function} matchMedia window.matchMedia (injectable for tests) */
export function createRouting( matchMedia = window.matchMedia.bind( window ) ) {
	const wide = matchMedia( WIDE_QUERY );
	const sideways = matchMedia( SIDEWAYS_QUERY );
	return {
		/** True when the desktop dialog is the right one right now. Exactly one of desktop and mobile is. */
		isDesktop: () => Boolean( wide.matches ) && ! sideways.matches,
		isMobile() {
			return ! this.isDesktop();
		},
		isSideways: () => Boolean( sideways.matches ),
		/** Calls back whenever the answer may have changed. */
		onChange( callback ) {
			wide.addEventListener( 'change', callback );
			sideways.addEventListener( 'change', callback );
		},
	};
}
