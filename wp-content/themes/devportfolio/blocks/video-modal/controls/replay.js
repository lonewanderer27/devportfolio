import { find } from './find.js';

/** The Replay button shown at the end of a video that does not loop. */
export function createReplay( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'replay' );
	if ( ! button ) {
		return null;
	}
	button.addEventListener( 'click', () => {
		const video = player.video();
		if ( video ) {
			video.currentTime = 0;
			player.userPlay();
		}
	} );
	return { button };
}
