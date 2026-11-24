/**
 * Videos modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.videos-modal-root',
	attribute: 'data-video-id',
	param: 'video_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-video-modal-style-css' ],
	idle: '[data-videos-grid]',
	load: () => import( './view.js' ),
} );
