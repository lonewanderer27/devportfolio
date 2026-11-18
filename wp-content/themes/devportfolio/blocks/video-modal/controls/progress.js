import { ariaValueText, progressPercent, seekForKey } from '../player-model.js';
import { find } from './find.js';

/**
 * The progress range. It always shows how far the video has played (`--p`, `aria-valuetext`); with `seek` it
 * also seeks on drag, click and the arrow keys. Without `seek` (the mobile line) nothing is bound for input.
 */
export function createProgress( { dialog, prefix, player, seek } ) {
	const scrub = find( dialog, prefix, 'scrub' );
	if ( ! scrub ) {
		return null;
	}
	let dragging = false;

	const draw = () => {
		const video = player.video();
		if ( ! video ) {
			return;
		}
		const duration = Number.isFinite( video.duration ) ? video.duration : 0;
		scrub.max = String( duration );
		scrub.setAttribute( 'aria-valuetext', ariaValueText( video.currentTime, duration ) );
		scrub.style.setProperty( '--p', `${ progressPercent( video.currentTime, duration ) }%` );
		if ( ! dragging ) {
			scrub.value = String( video.currentTime );
		}
	};
	[ 'mount', 'meta', 'time' ].forEach( ( type ) => player.on( type, draw ) );

	if ( seek ) {
		scrub.addEventListener( 'pointerdown', () => {
			dragging = true;
		} );
		window.addEventListener( 'pointerup', () => {
			if ( dragging ) {
				dragging = false;
				draw();
			}
		} );
		scrub.addEventListener( 'input', () => {
			const video = player.video();
			if ( video ) {
				video.currentTime = Number( scrub.value );
				draw();
			}
		} );
		scrub.addEventListener( 'keydown', ( event ) => {
			const video = player.video();
			const next = video ? seekForKey( event.key, video.currentTime, video.duration ) : null;
			if ( next !== null ) {
				event.preventDefault();
				video.currentTime = next;
				draw();
			}
		} );
	}
	return { scrub };
}
