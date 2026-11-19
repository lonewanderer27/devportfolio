/**
 * Videos grid (DPV-006 to DPV-008): category filter, poster fallback and hover preview.
 *
 * Contract: the grid is `[data-videos-grid]`; its items are direct children `.videos-item` with
 * `data-video-categories`. The filter runs on `devportfolio:category-change` (dispatched by
 * document-categories/view.js inside the View Transition), synchronously: no requestAnimationFrame here.
 */
import { matchesCategory, visibleCount } from './grid.js';
import { attachPreviews, releasePreview, refreshPreviews } from './preview.js';
import { attachLongPress } from './longpress.js';
import { createPopout } from './popout.js';

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

	// The line after the grid (a sibling, so the grid's own rules never touch it).
	const empty = grid.nextElementSibling && grid.nextElementSibling.hasAttribute( 'data-videos-empty' ) ? grid.nextElementSibling : null;

	function filter( slug ) {
		items.forEach( function ( item ) {
			const hidden = ! matchesCategory( item.getAttribute( 'data-video-categories' ) || '', slug );
			item.hidden = hidden;
			if ( hidden ) {
				releasePreview( item );
			}
		} );
		if ( empty ) {
			// Same tick as the item changes, so a View Transition's new snapshot already shows it.
			empty.hidden = visibleCount( items.map( ( item ) => item.getAttribute( 'data-video-categories' ) || '' ), slug ) !== 0;
		}
		refreshPreviews();
	}

	// The skeleton shows until the poster is in (DPV-038). A poster that fails leaves the plain blank tile
	// and the skeleton stops.
	items.forEach( function ( item ) {
		const img = item.querySelector( '.videos-item__img' );
		if ( ! img ) {
			return;
		}
		if ( img.complete && img.naturalWidth > 0 ) {
			item.classList.add( 'is-loaded' );
		}
		img.addEventListener( 'load', function () {
			item.classList.add( 'is-loaded' );
		} );
		img.addEventListener( 'error', function () {
			img.hidden = true;
			item.classList.add( 'is-failed' );
		} );
	} );

	document.addEventListener( FILTER_EVENT, function ( event ) {
		filter( ( event.detail && event.detail.slug ) || '' );
	} );

	filter( pressedSlug() );
	// The entrance (style.css) plays once, on load; after it a tile the filter shows again must not replay it.
	window.setTimeout( function () {
		grid.setAttribute( 'data-entered', '' );
	}, 500 );

	attachPreviews( grid );
	attachLongPress( grid, createPopout( grid ) );
}

init();
