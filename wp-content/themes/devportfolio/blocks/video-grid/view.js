/**
 * Videos grid (DPV-006 to DPV-008): category filter, poster fallback and hover preview.
 *
 * Contract: the grid is `[data-videos-grid]`; its items are direct children `.videos-item` with
 * `data-video-categories`. The filter runs on `devportfolio:category-change` (dispatched by
 * document-categories/view.js inside the View Transition), synchronously: no requestAnimationFrame here.
 */
import { matchesCategory } from './grid.js';
import { attachPreviews, stopPreview } from './preview.js';

const FILTER_EVENT = 'devportfolio:category-change';

// Slug of the pressed category pill, '' for All (or no filter block).
function pressedSlug() {
	const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
	return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
}

function init() {
	const grid = document.querySelector( '[data-videos-grid]' );
	if ( ! grid ) {
		return;
	}

	const items = Array.prototype.filter.call( grid.children, function ( el ) {
		return el.classList.contains( 'videos-item' );
	} );

	function filter( slug ) {
		items.forEach( function ( item ) {
			const hidden = ! matchesCategory( item.getAttribute( 'data-video-categories' ) || '', slug );
			item.hidden = hidden;
			if ( hidden ) {
				stopPreview( item );
			}
		} );
	}

	// A poster that fails to load leaves the plain blank tile.
	items.forEach( function ( item ) {
		const img = item.querySelector( '.videos-item__img' );
		if ( img ) {
			img.addEventListener( 'error', function () {
				img.hidden = true;
			} );
		}
	} );

	document.addEventListener( FILTER_EVENT, function ( event ) {
		filter( ( event.detail && event.detail.slug ) || '' );
	} );

	filter( pressedSlug() );
	attachPreviews( grid );
}

init();
