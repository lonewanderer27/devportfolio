import { FIT_MODES, preferences } from '../preferences.js';
import { find } from './find.js';

/**
 * The mobile fit button: cycles the picture through fit (the whole video, bars where it does not fill the screen),
 * zoom (fills the screen, cropping the edges) and stretch (fills it, distorting). The choice is kept for the page
 * session; the player's frame carries it as `data-fit`, which the stylesheet turns into object-fit.
 */
export function createFit( { dialog, prefix, player, strings } ) {
	const button = find( dialog, prefix, 'fit' );
	if ( ! button ) {
		return null;
	}
	const names = {
		fit: strings.fit || 'Fit to screen',
		zoom: strings.zoom || 'Zoom to fill',
		stretch: strings.stretch || 'Stretch to fill',
	};

	const draw = () => {
		player.root.dataset.fit = preferences.fit;
		button.dataset.fit = preferences.fit;
		button.setAttribute( 'aria-label', names[ preferences.fit ] );
	};
	button.addEventListener( 'click', () => {
		preferences.fit = FIT_MODES[ ( FIT_MODES.indexOf( preferences.fit ) + 1 ) % FIT_MODES.length ];
		draw();
	} );
	player.on( 'mount', draw );
	draw();
	return { button };
}
