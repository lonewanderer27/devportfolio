import { find } from './find.js';

export function createPlayPause( { dialog, prefix, player, strings } ) {
	const button = find( dialog, prefix, 'playpause' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		button.setAttribute( 'aria-label', video && ! video.paused ? strings.pause || 'Pause' : strings.play || 'Play' );
	};
	button.addEventListener( 'click', () => {
		player.wake();
		player.toggle();
	} );
	[ 'mount', 'play', 'pause' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
