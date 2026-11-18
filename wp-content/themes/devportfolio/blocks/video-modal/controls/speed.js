import { PLAYBACK_RATES } from '../player-model.js';
import { preferences } from '../preferences.js';
import { find } from './find.js';

/** The playback speed button (shows the rate) with its option popover. */
export function createSpeed( { dialog, prefix, player, popovers } ) {
	const button = find( dialog, prefix, 'ratebtn' );
	const pop = find( dialog, prefix, 'popover--rate' );
	if ( ! button || ! pop ) {
		return null;
	}
	const options = Array.from( pop.querySelectorAll( `.${ prefix }__rateopt` ) );
	const name = button.dataset.label || 'Playback speed';
	const item = { pop, trigger: button };
	popovers.register( item );

	const draw = () => {
		button.textContent = `${ preferences.rate }×`;
		button.setAttribute( 'aria-label', `${ name }, ${ preferences.rate }×` );
		options.forEach( ( option ) => option.setAttribute( 'aria-pressed', String( Number( option.dataset.rate ) === preferences.rate ) ) );
	};
	options.forEach( ( option ) => {
		option.addEventListener( 'click', () => {
			const rate = Number( option.dataset.rate );
			preferences.rate = PLAYBACK_RATES.includes( rate ) ? rate : 1;
			const video = player.video();
			if ( video ) {
				video.playbackRate = preferences.rate;
			}
			draw();
			popovers.closeAll();
			button.focus();
		} );
	} );
	button.addEventListener( 'click', () => popovers.toggle( item ) );
	player.on( 'mount', draw );
	draw();
	return { button };
}
