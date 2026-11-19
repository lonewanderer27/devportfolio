/**
 * Loads the metadata (not the file) of the one video that will probably open next (DPV-040).
 *
 * `set( item )` replaces the previous probe; an item with no file, or data saver, probes nothing. `release()`
 * drops the probe so the browser stops fetching.
 */
export function createNextPreloader() {
	let probe = null;

	const release = () => {
		if ( probe ) {
			probe.removeAttribute( 'src' );
			probe.load();
			probe = null;
		}
	};

	return {
		set( item ) {
			release();
			if ( ! item || ! item.fileUrl || ( typeof navigator !== 'undefined' && navigator.connection && navigator.connection.saveData ) ) {
				return;
			}
			probe = document.createElement( 'video' );
			probe.preload = 'metadata';
			probe.muted = true;
			probe.src = item.fileUrl;
		},
		release,
	};
}
