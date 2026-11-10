/**
 * Brand Collaterals masonry (DPC-006, DPC-014).
 *
 * Positions each visible item with the pure layout in masonry.js (absolute
 * positioning; the CSS multi-column fallback stays until this runs), filters the
 * items on `devportfolio:category-change` (dispatched by document-categories/view.js,
 * synchronously, inside the View Transition), re-lays out when the width changes,
 * and shows the blank page for an image that fails to load.
 *
 * Contract: the grid is `[data-collaterals-grid]`; its items are direct children
 * `.collaterals-item` with `data-collateral-categories` (space-separated slugs) and
 * `data-ratio`. Nothing here runs a requestAnimationFrame inside the category handler:
 * the new layout must already be in place when the View Transition takes its new snapshot.
 */
import { layout } from './masonry.js';

const FILTER_EVENT = 'devportfolio:category-change';

/**
 * Slug of the pill that is pressed on the page, '' for All (or no filter block).
 *
 * @return {string}
 */
function pressedSlug() {
	const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
	return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
}

/**
 * Attaches the masonry behaviour to one grid.
 *
 * @param {HTMLElement} grid The `[data-collaterals-grid]` element.
 * @return {{filter: function(string): void, relayout: function(): void}}
 */
function createGrid( grid ) {
	const items = Array.prototype.filter.call( grid.children, function ( el ) {
		return el.classList.contains( 'collaterals-item' );
	} );

	let lastWidth = -1;

	// Shows the items of `slug` ('' shows all). Only sets `hidden`; positions come from relayout().
	function filter( slug ) {
		items.forEach( function ( item ) {
			const slugs = ( item.getAttribute( 'data-collateral-categories' ) || '' ).split( /\s+/ );
			item.hidden = '' !== slug && -1 === slugs.indexOf( slug );
		} );
	}

	// Places the visible items and sizes the grid. The gap is the CSS custom property, read once per layout.
	function relayout() {
		const visible = items.filter( function ( item ) {
			return ! item.hidden;
		} );
		const gap = parseFloat( window.getComputedStyle( grid ).getPropertyValue( '--collaterals-gap' ) ) || 0;
		const ratios = visible.map( function ( item ) {
			return parseFloat( item.getAttribute( 'data-ratio' ) );
		} );
		const result = layout( grid.clientWidth, gap, ratios );

		visible.forEach( function ( item, index ) {
			const position = result.positions[ index ];
			item.style.width = position.w + 'px';
			item.style.transform = 'translate(' + position.x + 'px, ' + position.y + 'px)';
		} );
		grid.style.height = result.height + 'px';
		lastWidth = grid.clientWidth;
	}

	// Switches the CSS from the multi-column fallback to absolute positioning.
	grid.setAttribute( 'data-masonry', '' );

	// A picture that fails to load is hidden, so the numbered blank page under it shows (never a broken image).
	grid.addEventListener(
		'error',
		function ( event ) {
			const target = event.target;
			if ( target instanceof HTMLImageElement && target.classList.contains( 'collaterals-item__img' ) ) {
				target.hidden = true;
			}
		},
		true
	);
	grid.querySelectorAll( 'img.collaterals-item__img' ).forEach( function ( img ) {
		if ( img.complete && 0 === img.naturalWidth ) {
			img.hidden = true;
		}
	} );

	// Re-lay out when the content width changes. Only a width change matters: the height set by relayout()
	// also resizes the grid, and that must not start another pass.
	let frame = 0;
	const observer = new ResizeObserver( function ( entries ) {
		const width = entries[ entries.length - 1 ].contentRect.width;
		if ( width === lastWidth ) {
			return;
		}
		window.cancelAnimationFrame( frame );
		frame = window.requestAnimationFrame( relayout );
	} );
	observer.observe( grid );

	return { filter: filter, relayout: relayout };
}

const grids = Array.prototype.map.call( document.querySelectorAll( '[data-collaterals-grid]' ), createGrid );

if ( grids.length ) {
	// Start from the pill the server pressed, so the first layout is already right.
	const initial = pressedSlug();
	grids.forEach( function ( grid ) {
		grid.filter( initial );
		grid.relayout();
	} );

	// Synchronous on purpose: the View Transition's new snapshot must already show the new layout.
	document.addEventListener( FILTER_EVENT, function ( event ) {
		const slug = event.detail && event.detail.slug ? event.detail.slug : '';
		grids.forEach( function ( grid ) {
			grid.filter( slug );
			grid.relayout();
		} );
	} );
}
