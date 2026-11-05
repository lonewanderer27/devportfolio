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

		var row = 0;
		visible.forEach( function ( item, index ) {
			if ( ( index + 1 ) % perRow === 0 || index === visible.length - 1 ) {
				var plank = document.createElement( 'div' );
				// One transition name per row so planks animate with the books (see document-shelf/style.css).
				plank.style.viewTransitionName = 'documents-plank-' + row;
				row++;
				plank.className = 'documents-shelf__plank';
				plank.setAttribute( 'data-shelf-plank', '' );
				plank.setAttribute( 'aria-hidden', 'true' );
				item.after( plank );
			}
		} );
	}

	var PARAM = 'category';

	/**
	 * Active slug from the URL, or '' (All) when absent or not a known pill.
	 *
	 * @param {HTMLElement} group Pill group.
	 * @return {string}
	 */
	function slugFromUrl( group ) {
		var slug = new URL( window.location.href ).searchParams.get( PARAM ) || '';
		var known = Array.prototype.some.call(
			group.querySelectorAll( '.documents-categories__pill' ),
			function ( pill ) {
				return '' !== slug && pill.getAttribute( 'data-filter' ) === slug;
			}
		);
		return known ? slug : '';
	}

	/**
	 * URL for a slug: sets or removes ?category, keeping other args and the hash.
	 *
	 * @param {string} slug Term slug, or '' for All.
	 * @return {string}
	 */
	function urlForSlug( slug ) {
		var url = new URL( window.location.href );
		if ( slug ) {
			url.searchParams.set( PARAM, slug );
		} else {
			url.searchParams.delete( PARAM );
		}
		return url.toString();
	}

	function init( group ) {
		var shelf = document.querySelector( '[data-documents-shelf]' );
		var resizeTimer;

		// Tells the shelf CSS that the browser animates the books (it then skips the entrance animation).
		if ( shelf && document.startViewTransition ) {
			shelf.setAttribute( 'data-view-transitions', '' );
		}

		// Make `slug` the single active pill and filter the shelf.
		function apply( slug ) {
			group.querySelectorAll( '.documents-categories__pill' ).forEach( function ( pill ) {
				pill.setAttribute( 'aria-pressed', ( pill.getAttribute( 'data-filter' ) || '' ) === slug ? 'true' : 'false' );
			} );
			if ( shelf ) {
				filterShelf( shelf, slug );
			}
		}

		// Fade the pills between states with a View Transition where supported; otherwise (or with reduced motion) apply instantly.
		function applyAnimated( slug ) {
			var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			if ( ! document.startViewTransition || reduce ) {
				apply( slug );
				return;
			}
			document.startViewTransition( function () {
				apply( slug );
			} );
		}

		// Initial state from the URL; a bad param is cleaned without adding a history entry.
		var initial = slugFromUrl( group );
		if ( new URL( window.location.href ).searchParams.has( PARAM ) && ! initial ) {
			window.history.replaceState( { category: '' }, '', urlForSlug( '' ) );
		}
		apply( initial );

		group.addEventListener( 'click', function ( event ) {
			var pill = event.target.closest( '.documents-categories__pill' );
			if ( ! pill || 'true' === pill.getAttribute( 'aria-pressed' ) ) {
				return;
			}
			var slug = pill.getAttribute( 'data-filter' ) || '';
			applyAnimated( slug );
			window.history.pushState( { category: slug }, '', urlForSlug( slug ) );
			pill.scrollIntoView( { inline: 'nearest', block: 'nearest' } );
		} );

		// Back/Forward step through categories without pushing again.
		window.addEventListener( 'popstate', function () {
			applyAnimated( slugFromUrl( group ) );
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
