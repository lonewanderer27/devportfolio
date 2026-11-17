import { clampVolume } from '../player-model.js';
import { preferences } from '../preferences.js';
import { find } from './find.js';

/** The volume button with its slider popover. A slider at 0 is the mute; moving it off 0 unmutes. */
export function createVolume( { dialog, prefix, player, popovers } ) {
	const button = find( dialog, prefix, 'volbtn' );
	const pop = find( dialog, prefix, 'popover--volume' );
	const slider = find( dialog, prefix, 'volume' );
	if ( ! button || ! pop || ! slider ) {
		return null;
	}
	const item = { pop, trigger: button };
	popovers.register( item );

	const draw = () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		const level = video.muted ? 0 : video.volume;
		slider.value = String( level );
		// The slider's fill is the --v percentage (0 to 100) read by its track in style.css.
		slider.style.setProperty( '--v', String( Math.round( level * 100 ) ) );
		button.dataset.muted = String( video.muted );
	};
	slider.addEventListener( 'input', () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		preferences.volume = clampVolume( Number( slider.value ) );
		preferences.muted = preferences.volume === 0;
		video.volume = preferences.volume;
		video.muted = preferences.muted;
	} );
	button.addEventListener( 'click', () => popovers.toggle( item ) );
	[ 'mount', 'volume' ].forEach( ( type ) => player.on( type, draw ) );
	draw();
	return { button };
}
