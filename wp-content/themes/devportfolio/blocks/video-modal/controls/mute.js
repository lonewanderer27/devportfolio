import { preferences } from '../preferences.js';
import { find } from './find.js';

/** A sound on/off button (mobile). `aria-pressed` is true while muted. */
export function createMute( { dialog, prefix, player } ) {
	const button = find( dialog, prefix, 'mutebtn' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		const muted = video ? video.muted : preferences.muted;
		button.setAttribute( 'aria-pressed', String( muted ) );
		button.dataset.muted = String( muted );
	};
	button.addEventListener( 'click', () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		preferences.muted = ! video.muted;
		if ( ! preferences.muted && video.volume === 0 ) {
			preferences.volume = 1;
			video.volume = 1;
		}
		video.muted = preferences.muted;
	} );
	[ 'mount', 'volume' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
