import { timeLabel } from '../player-model.js';
import { find } from './find.js';

export function createTime( { dialog, prefix, player } ) {
	const label = find( dialog, prefix, 'time' );
	if ( ! label ) {
		return null;
	}
	const draw = () => {
		const video = player.video();
		const duration = video && Number.isFinite( video.duration ) ? video.duration : 0;
		label.textContent = timeLabel( video ? video.currentTime : 0, duration );
	};
	[ 'mount', 'meta', 'time' ].forEach( ( type ) => player.on( type, draw ) );
	return { label };
}
