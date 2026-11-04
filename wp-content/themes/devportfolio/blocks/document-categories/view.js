/**
 * Document category pills: filter the documents shelf in place (DPD-003).
 *
 * Contract: the shelf is `[data-documents-shelf]`; each filterable item carries
 * `data-document-categories` (space-separated term slugs); planks are
 * `[data-shelf-plank]` elements owned by this script. A pill's `data-filter` is
 * a term slug, or empty for "All".
 */
( function () {
	'use strict';

	/**
	 * Show only items in `slug` ("" shows all), then rebuild the planks.
	 *
	 * @param {HTMLElement} shelf Shelf element.
	 * @param {string}      slug  Term slug, or '' for All.
	 */
	function filterShelf( shelf, slug ) {
		shelf.querySelectorAll( '[data-document-categories]' ).forEach( function ( item ) {
			var slugs = ( item.getAttribute( 'data-document-categories' ) || '' ).split( /\s+/ );
			item.hidden = '' !== slug && -1 === slugs.indexOf( slug );
		} );
		renderPlanks( shelf );
	}

	/**
	 * Replace the planks with one after every Nth visible item (N = grid
	 * columns) and after the last visible item.
	 *
	 * @param {HTMLElement} shelf Shelf element.
	 */
	function renderPlanks( shelf ) {
		shelf.querySelectorAll( '[data-shelf-plank]' ).forEach( function ( plank ) {
			plank.remove();
		} );

		var visible = Array.prototype.filter.call(
			shelf.querySelectorAll( '[data-document-categories]' ),
			function ( item ) {
				return ! item.hidden;
			}
		);
		var columns = window.getComputedStyle( shelf ).gridTemplateColumns;
		var perRow = columns && 'none' !== columns ? columns.split( ' ' ).length : visible.length;

		visible.forEach( function ( item, index ) {
			if ( ( index + 1 ) % perRow === 0 || index === visible.length - 1 ) {
				var plank = document.createElement( 'div' );
				plank.className = 'documents-shelf__plank';
				plank.setAttribute( 'data-shelf-plank', '' );
				item.after( plank );
			}
		} );
	}

	function init( group ) {
		var shelf = document.querySelector( '[data-documents-shelf]' );
		var resizeTimer;

		group.addEventListener( 'click', function ( event ) {
			var pill = event.target.closest( '.documents-categories__pill' );
			if ( ! pill || 'true' === pill.getAttribute( 'aria-pressed' ) ) {
				return;
			}
			group.querySelectorAll( '.documents-categories__pill' ).forEach( function ( other ) {
				other.setAttribute( 'aria-pressed', other === pill ? 'true' : 'false' );
			} );
			if ( shelf ) {
				filterShelf( shelf, pill.getAttribute( 'data-filter' ) || '' );
			}
			pill.scrollIntoView( { inline: 'nearest', block: 'nearest' } );
		} );

		if ( shelf ) {
			window.addEventListener( 'resize', function () {
				window.clearTimeout( resizeTimer );
				resizeTimer = window.setTimeout( function () {
					renderPlanks( shelf );
				}, 150 );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wp-block-devportfolio-document-categories' ).forEach( init );
	} );
}() );
