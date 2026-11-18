import { find } from './find.js';

/** The large play glyph / button. On mobile it is a non-interactive span (CSS), so it only shows the paused state. */
export function createBigPlay( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'bigplay' );
	if ( ! button ) {
		return null;
	}
	button.addEventListener( 'click', () => {
		player.wake();
		player.toggle();
	} );
	return { button };
}
