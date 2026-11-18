import { find } from './find.js';

/**
 * Full screen for the player wrapper (so the controls go with the video); iPhone Safari only allows the
 * <video> element itself, so that is the fallback. The button is hidden where neither exists.
 */
export function createFullscreen( { dialog, prefix, player, strings } ) {
	const button = find( dialog, prefix, 'fullscreen' );
	if ( ! button ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		button.hidden = ! ( player.root.requestFullscreen || ( video && video.webkitEnterFullscreen ) );
		button.setAttribute( 'aria-label', document.fullscreenElement ? strings.exitFullscreen || 'Exit full screen' : strings.fullscreen || 'Full screen' );
	};
	button.addEventListener( 'click', () => {
		if ( document.fullscreenElement ) {
			document.exitFullscreen();
			return;
		}
		const video = player.video();
		if ( player.root.requestFullscreen ) {
			player.root.requestFullscreen().catch( () => {} );
		} else if ( video && video.webkitEnterFullscreen ) {
			video.webkitEnterFullscreen();
		}
	} );
	player.on( 'mount', draw );
	document.addEventListener( 'fullscreenchange', draw );
	draw();
	return { button };
}
