/**
 * Brand Collaterals modal entry (DPB-NNN F1): the only script this block prints. It loads view.js on first use; see
 * shared/lazy-modal.js for when that is.
 */
import { createLazyModal } from '../shared/lazy-modal.js';

createLazyModal( {
	root: '.collaterals-modal-root',
	attribute: 'data-collateral-id',
	param: 'collateral_slug',
	styles: [ 'devportfolio-viewer-css', 'devportfolio-collateral-modal-style-css' ],
	idle: '[data-collaterals-grid]',
	load: () => import( './view.js' ),
} );
