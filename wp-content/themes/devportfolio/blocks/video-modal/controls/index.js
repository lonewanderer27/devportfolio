import { createBigPlay } from './bigplay.js';
import { createPlayPause } from './playpause.js';
import { createReplay } from './replay.js';
import { createProgress } from './progress.js';
import { createTime } from './time.js';
import { createMute } from './mute.js';
import { createVolume } from './volume.js';
import { createSpeed } from './speed.js';
import { createFullscreen } from './fullscreen.js';
import { createFit } from './fit.js';
import { createPopovers } from './popover.js';

/** Every control of the video player, by key. A new button is one file and one line here. */
export const CONTROLS = {
	bigplay: createBigPlay,
	playpause: createPlayPause,
	replay: createReplay,
	progress: createProgress,
	time: createTime,
	mute: createMute,
	volume: createVolume,
	speed: createSpeed,
	fullscreen: createFullscreen,
	fit: createFit,
};

/** What the desktop modal mounts: everything its markup has (it has no separate mute button). */
export const DESKTOP_CONTROLS = [ 'bigplay', 'playpause', 'replay', 'progress', 'time', 'volume', 'speed', 'fullscreen' ];

/**
 * Mounts the named controls on a player. Unknown keys are ignored; a control whose elements are not in the DOM
 * returns null and is dropped, so a viewer only ever has what its markup rendered.
 *
 * @param {Object}   args
 * @param {Element}  args.dialog
 * @param {string}   args.prefix  Class prefix of the viewer.
 * @param {Object}   args.player  The engine (createPlayer).
 * @param {Object}   [args.strings]
 * @param {string[]} [args.keys]  Control keys, in mount order.
 * @param {boolean}  [args.seek]  The progress control may seek (desktop; mobile `scrub`).
 * @return {Object[]} The controls that mounted.
 */
export function mountControls( { dialog, prefix, player, strings = {}, keys = [], seek = false } ) {
	const popovers = createPopovers( dialog );
	return keys
		.filter( ( key ) => Object.prototype.hasOwnProperty.call( CONTROLS, key ) )
		.map( ( key ) => CONTROLS[ key ]( { dialog, prefix, player, strings, seek, popovers } ) )
		.filter( Boolean );
}
