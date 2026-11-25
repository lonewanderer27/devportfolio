/**
 * Documents modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.documents-modal-root',
	attribute: 'data-document-id',
	param: 'document_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-document-modal-style-2-css' ],
	idle: '[data-documents-shelf]',
	load: () => import( './view.js' ),
} );
