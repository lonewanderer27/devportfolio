/**
 * Collateral modal (DPC-007 to DPC-011, DPC-020 to DPC-022).
 *
 * A click on `[data-collateral-id]` (the grid's title button) opens the desktop or mobile dialog for the
 * viewport. The sidebar is filled from the JSON payload with textContent only. Open, close, Back/Forward
 * and the swap to a Next collateral follow the Documents modal (document-modal/view.js), with the
 * parameter `collateral_slug`: a click pushes one history entry, a swap replaces it (one Back always closes).
 * The body (DPC-012) and footer (DPC-013) are the shared book viewer and pager controller (shared/viewer.js,
 * shared/pager-controller.js) with the `collaterals-modal` prefix. The desktop dialog emits
 * `collaterals:modal-change` (id, page, step, pages) from the controller.
 */
import { createRouting } from '../shared/routing.js';
import { createViewer } from '../shared/viewer.js';
import { createPagerController } from '../shared/pager-controller.js';
import { mergeLimits } from '../shared/capability.js';
import { unitOf, wording } from '../shared/mode.js';
import { bindOpen } from '../shared/modal-trigger.js';
import { createDeeplink, hasSheetState } from '../shared/deeplink.js';
import { createMobileModal } from '../shared/mobile.js';
import { applyDownload, guardDisabledDownload } from '../shared/download.js';
import { nextTwo } from './next.js';
import { linkFor, copyText } from './copy-link.js';

const root = document.querySelector( '.collaterals-modal-root' );
const dialog = root ? root.querySelector( '.collaterals-modal' ) : null;
const mobileDialog = root ? root.querySelector( '.collaterals-mobile' ) : null;
const dataEl = root ? root.querySelector( '.collaterals-modal__data' ) : null;

const readJson = ( text ) => {
	try {
		const value = JSON.parse( text );
		return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	} catch ( e ) {
		return {};
	}
};

if ( dialog && mobileDialog && dataEl ) {
	const { order = [], items = {} } = readJson( dataEl.textContent );
	const strings = readJson( root.getAttribute( 'data-strings' ) );
	const deeplink = createDeeplink( 'collateral_slug' );
	// One definition of which dialog a screen gets (routing.js); the CSS repeats its queries.
	const routing = createRouting();
	const reduceMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	const idBySlug = {};
	order.forEach( ( id ) => {
		const item = items[ id ];
		if ( item && item.slug ) {
			idBySlug[ item.slug ] = id;
		}
	} );

	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		pillCat: $( '.collaterals-modal__pill--cat' ),
		pillSize: $( '.collaterals-modal__pill--size' ),
		pills: $( '.collaterals-modal__pills' ),
		title: $( '.collaterals-modal__title' ),
		desc: $( '.collaterals-modal__desc' ),
		results: $( '.collaterals-modal__results' ),
		stats: $( '.collaterals-modal__stats' ),
		tools: $( '.collaterals-modal__tools' ),
		toolList: $( '.collaterals-modal__toollist' ),
		next: $( '.collaterals-modal__next' ),
		nextList: $( '.collaterals-modal__nextlist' ),
		download: $( '.collaterals-modal__download' ),
		copy: $( '.collaterals-modal__copy' ),
		copyField: $( '.collaterals-modal__copyfield' ),
		live: $( '.collaterals-modal__live' ),
		close: $( '.collaterals-modal__close' ),
		prev: $( '.collaterals-modal__arrow--prev' ),
		next: $( '.collaterals-modal__arrow--next' ),
		stage: $( '.collaterals-modal__stage' ),
		status: $( '.collaterals-modal__state' ),
		tap: $( '.collaterals-modal__tap' ),
		footer: $( '.collaterals-modal__footer' ),
		pager: $( '.collaterals-modal__pager' ),
		indicator: $( '.collaterals-modal__indicator' ),
		bar: $( '.collaterals-modal__footer .collaterals-modal__bar' ),
	};
	const units = readJson( root.getAttribute( 'data-units' ) );
	const copyLabel = els.copy.textContent;

	// The footer, pager, arrows and keys: the controller shared with the Documents modal (DPC-013). It owns the page state.
	const pager = createPagerController( {
		els: { footer: els.footer, pager: els.pager, indicator: els.indicator, bar: els.bar, prev: els.prev, next: els.next, tap: els.tap },
		prefix: 'collaterals-modal',
		target: dialog,
		eventName: 'collaterals:modal-change',
		ignore: '.collaterals-modal__grid',
	} );
	// The book (DPC-012): stored page images first, pdf.js behind the same render gate as Documents.
	const viewer = createViewer( {
		prefix: 'collaterals-modal',
		stage: els.stage,
		status: els.status,
		units,
		pdfjs: readJson( root.getAttribute( 'data-pdfjs' ) ),
		limits: mergeLimits( readJson( root.getAttribute( 'data-limits' ) ) ),
		selectableText: root.getAttribute( 'data-selectable-text' ) === '1',
		singlePage: true,
		onGeometry: pager.setGeometry,
		onFlip: pager.syncFromViewer,
		onSwipe: ( direction ) => pager.turn( direction ),
		onTap: ( direction ) => pager.tap( direction ),
	} );
	pager.attach( viewer );
	// The mobile dialog (DPC-015 to DPC-019): the shared mobile body (mobile.js), with the collateral's own markup
	// prefix, events and Details sheet. It owns its page state; this file only opens it and closes it.
	const mobile = createMobileModal( {
		dialog: mobileDialog,
		prefix: 'collaterals-mobile',
		viewPrefix: 'collaterals-modal',
		eventPrefix: 'collaterals',
		loadDetails: () => import( './details-sheet.js' ),
		units,
		strings: readJson( root.getAttribute( 'data-mobile-strings' ) ),
		pdfjs: readJson( root.getAttribute( 'data-pdfjs' ) ),
		limits: mergeLimits( readJson( root.getAttribute( 'data-limits' ) ) ),
		selectableText: root.getAttribute( 'data-selectable-text' ) === '1',
		singlePage: true,
		onClose: ( extra ) => finishClose( extra ),
	} );

	let currentId = 0;
	let opener = null;
	let pushed = false;
	let closingFromHistory = false;
	let swapping = false;
	let skipNextPop = false;
	let swapQueued = false;
	let copyTimer = 0;

	const isOpen = () => dialog.open || mobile.isOpen();
	const shownDialog = () => ( dialog.open ? dialog : mobile.isOpen() ? mobileDialog : null );
	const wantedDialog = () => ( routing.isDesktop() ? dialog : mobileDialog );

	/** Announces the change on the desktop dialog (DPC-012 listens for it). The mobile modal emits its own events. */
	const emit = () => {
		if ( wantedDialog() === dialog ) {
			pager.emit();
		}
	};

	/**
	 * Sets the footer and the book's pages for one collateral before its dialog is shown (so the arrows are right
	 * on first paint): the stored pages, else the typed page count. Collaterals always show one page at a time.
	 */
	const openPages = ( id, item, { page = 1, flipped = false } = {} ) => {
		const stored = Array.isArray( item.images ) ? item.images.length : 0;
		const pages = stored || Number( item.pages ) || 0;
		const step = 1;
		pager.open( Number( id ), { pages, step, page, words: wording( unitOf( item ), units ), flipped } );
	};

	/** The category the grid is filtered by (the pressed pill), '' for All. Read on open and swap only. */
	const activeFilter = () => {
		const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
		return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
	};

	const renderNext = () => {
		const ids = nextTwo( order, items, currentId, activeFilter() );
		els.nextList.replaceChildren(
			...ids.map( ( id ) => {
				const item = items[ id ];
				const li = document.createElement( 'li' );
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'collaterals-modal__nextitem';
				button.setAttribute( 'data-collateral-next', String( id ) );
				button.setAttribute( 'aria-label', ( strings.open || 'Open %s' ).replace( '%s', item.title ) );
				button.style.setProperty( '--cm-ratio', String( item.thumb.ratio || 0.7071 ) );

				const tile = document.createElement( 'span' );
				tile.className = 'collaterals-modal__nexttile';
				const blank = document.createElement( 'span' );
				blank.className = 'collaterals-modal__blank';
				blank.setAttribute( 'aria-hidden', 'true' );
				blank.textContent = '1';
				tile.append( blank );

				if ( item.thumb.src ) {
					const img = document.createElement( 'img' );
					img.className = 'collaterals-modal__nextimg';
					img.src = item.thumb.src;
					if ( item.thumb.srcset ) {
						img.srcset = item.thumb.srcset;
					}
					img.sizes = '160px';
					img.alt = '';
					img.loading = 'lazy';
					img.decoding = 'async';
					// A failed image shows the numbered blank, as in the grid.
					img.addEventListener( 'error', () => img.remove() );
					tile.append( img );
				}

				button.append( tile );
				li.append( button );
				return li;
			} )
		);
		els.next.hidden = ids.length === 0;
	};

	/** Fills the desktop sidebar from one payload item. textContent only (the mobile modal fills its own header). */
	const fill = ( item ) => {
		els.pillCat.textContent = item.category;
		els.pillCat.hidden = ! item.category;
		els.pillSize.textContent = item.pill;
		els.pillSize.hidden = ! item.pill;
		els.pills.hidden = ! item.category && ! item.pill;

		els.title.textContent = item.title;
		els.desc.textContent = item.description;
		els.desc.hidden = ! item.description;

		els.results.hidden = ! item.stats.length;
		els.stats.replaceChildren(
			...item.stats.map( ( stat ) => {
				const row = document.createElement( 'div' );
				const value = document.createElement( 'dd' );
				value.textContent = stat.value;
				const label = document.createElement( 'dt' );
				label.textContent = stat.label;
				row.append( value, label );
				return row;
			} )
		);

		els.tools.hidden = ! item.tools.length;
		els.toolList.replaceChildren(
			...item.tools.map( ( name ) => {
				const li = document.createElement( 'li' );
				li.textContent = name;
				return li;
			} )
		);

		applyDownload( els.download, { title: item.title, downloadMode: item.downloadMode, downloadUrl: item.downloadUrl } );
		els.copy.textContent = copyLabel;
		els.copyField.classList.remove( 'is-shown' );
		renderNext();
	};

	const current = () => items[ currentId ];

	/** Focus goes back to the grid button of the collateral that is shown now (D9), else the opener. */
	const focusTarget = () => {
		const button = document.querySelector( `.collaterals-item__open[data-collateral-id="${ currentId }"]` );
		if ( button && ! button.closest( '[hidden]' ) ) {
			return button;
		}
		return opener && document.contains( opener ) ? opener : null;
	};

	/**
	 * A dialog closed (desktop, or the mobile modal's close with `extra` sheet entries still in the history).
	 * Mirrors document-modal/view.js: a swap only reconciles, a sheet's entry is removed with the modal's.
	 */
	const finishClose = ( extra = 0 ) => {
		if ( swapping ) {
			swapping = false;
			if ( extra > 0 ) {
				// The sheet's entry has the same URL as the modal's: step back onto it; the other dialog is open.
				window.history.go( -extra );
			}
			return;
		}
		document.documentElement.style.overflow = '';
		const target = focusTarget();
		opener = null;
		if ( closingFromHistory ) {
			closingFromHistory = false;
			pushed = false;
		} else if ( pushed ) {
			pushed = false;
			// Also removes the Details or Pages sheet's entry when one was left open.
			window.history.go( -( 1 + extra ) );
		} else if ( extra > 0 ) {
			skipNextPop = true;
			window.history.go( -extra );
		} else if ( deeplink.slugFromUrl( window.location.href ) ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		}
		if ( target ) {
			target.focus();
		}
	};

	const closeOpen = () => {
		const shown = shownDialog();
		if ( shown ) {
			shown.close();
		}
	};

	/** Never both dialogs at once: the one that does not belong on this screen is closed (view.js of Documents, same rule). */
	const enforceSingleDialog = () => {
		if ( ! dialog.open || ! mobile.isOpen() ) {
			return;
		}
		swapping = true;
		if ( routing.isDesktop() ) {
			mobileDialog.close();
		} else {
			dialog.close();
		}
	};

	const show = ( id, { push, trigger } ) => {
		const item = items[ id ];
		if ( ! item || isOpen() ) {
			return;
		}
		currentId = Number( id );
		if ( push && item.slug ) {
			window.history.pushState( { collateralSlug: item.slug }, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
			pushed = true;
		}
		opener = trigger;
		document.documentElement.style.overflow = 'hidden';
		fill( item );
		if ( wantedDialog() === dialog ) {
			openPages( id, item );
			dialog.showModal();
			els.close.focus();
			enforceSingleDialog();
			emit();
			viewer.show( item, 1 );
			return;
		}
		// The mobile modal opens on its first page and announces its own state (mobile.js).
		mobile.show( item, 1 );
		enforceSingleDialog();
	};

	/** Next collateral (D8): the sidebar and URL change, the modal stays open and the entry is replaced. */
	const swapTo = ( id ) => {
		const item = items[ id ];
		if ( ! item ) {
			return;
		}
		currentId = Number( id );
		fill( item );
		window.history.replaceState( window.history.state, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
		if ( wantedDialog() !== dialog ) {
			mobile.show( item, 1 );
			return;
		}
		els.title.focus();
		// A Next collateral starts on its first page, with the Tap to Flip hint off (DPC-012, D7).
		openPages( id, item, { flipped: true } );
		emit();
		viewer.show( item, 1 );
	};

	/** The dialog changes between desktop and mobile in place (same collateral, page and URL). */
	const swapDialogs = () => {
		// One swap at a time: a second media change while the first one is queued or still closing must not swap
		// again from the dialog that is closing (its page has already collapsed; the first swap has the right page).
		if ( swapping || swapQueued ) {
			return;
		}
		enforceSingleDialog();
		const shown = shownDialog();
		if ( ! shown || ( shown === dialog ) === routing.isDesktop() ) {
			return;
		}
		// The page is kept across the swap (DPC-019), read now: the desktop book can collapse while the viewport changes.
		const page = shown === dialog ? pager.state.page : mobile.state().page;
		const run = () => {
			swapping = true;
			swapQueued = false;
			shown.close();
			if ( wantedDialog() === dialog ) {
				fill( current() );
				openPages( currentId, current(), { page } );
				dialog.showModal();
				els.close.focus();
				emit();
				viewer.show( current(), page );
				return;
			}
			mobile.show( current(), page );
		};
		swapQueued = true;
		if ( ! document.startViewTransition || reduceMotion() ) {
			run();
			return;
		}
		const html = document.documentElement;
		html.setAttribute( 'data-collaterals-swapping', '' );
		const transition = document.startViewTransition( run );
		transition.finished.finally( () => {
			html.removeAttribute( 'data-collaterals-swapping' );
		} );
	};

	const closeFromButton = () => closeOpen();

	bindOpen( document, 'data-collateral-id', ( id, trigger ) => show( Number( id ), { push: true, trigger } ) );

	// A click on the dimmed backdrop lands on the dialog itself (the mobile dialog has none).
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	els.close.addEventListener( 'click', closeFromButton );
	// The book stops with its dialog (a swap to the mobile dialog stops it too, DPC-012).
	dialog.addEventListener( 'close', () => viewer.destroy() );
	dialog.addEventListener( 'close', () => finishClose() );

	els.nextList.addEventListener( 'click', ( event ) => {
		const button = event.target.closest ? event.target.closest( '[data-collateral-next]' ) : null;
		if ( button ) {
			swapTo( Number( button.getAttribute( 'data-collateral-next' ) ) );
		}
	} );

	guardDisabledDownload( els.download );

	els.copy.addEventListener( 'click', async () => {
		const item = current();
		if ( ! item ) {
			return;
		}
		const result = await copyText( linkFor( window.location, item.slug ), {
			clipboard: navigator.clipboard,
			execCopy: ( text ) => {
				els.copyField.value = text;
				els.copyField.classList.add( 'is-shown' );
				els.copyField.select();
				const ok = document.execCommand( 'copy' );
				els.copyField.classList.remove( 'is-shown' );
				return ok;
			},
			select: () => {
				els.copyField.value = linkFor( window.location, item.slug );
				els.copyField.classList.add( 'is-shown' );
				els.copyField.focus();
				els.copyField.select();
				// The hint is shown on the button (the status line is screen-reader only) and announced too.
				els.copy.textContent = strings.copyHint || copyLabel;
				els.live.textContent = strings.copyHint || '';
			},
		} );
		if ( result === 'copied' ) {
			els.copy.textContent = strings.copied || copyLabel;
			els.live.textContent = strings.copied || '';
			window.clearTimeout( copyTimer );
			copyTimer = window.setTimeout( () => {
				els.copy.textContent = copyLabel;
				els.live.textContent = '';
			}, 2000 );
		}
	} );

	// Back and Forward open or close the modal to match the URL, without adding entries. Registered in the capture
	// phase at module top level and stopping the event whenever it acts: the Interactivity Router answers every
	// popstate and reloads the page for a URL it has not cached (see document-modal/view.js). A sheet's own entry
	// (shared/sheet-back.js) has the same URL; the sheet listener runs first and this one leaves it alone.
	window.addEventListener(
		'popstate',
		( event ) => {
			if ( skipNextPop ) {
				skipNextPop = false;
				if ( deeplink.slugFromUrl( window.location.href ) && ! isOpen() ) {
					window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
				}
				event.stopImmediatePropagation();
				return;
			}
			if ( swapping ) {
				event.stopImmediatePropagation();
				return;
			}
			const slug = deeplink.slugFromUrl( window.location.href );
			if ( ! slug && isOpen() ) {
				event.stopImmediatePropagation();
				closingFromHistory = true;
				closeOpen();
			} else if ( slug && ! isOpen() && idBySlug[ slug ] ) {
				event.stopImmediatePropagation();
				pushed = true;
				show( idBySlug[ slug ], { push: false, trigger: null } );
			} else if ( slug && isOpen() && hasSheetState( event.state ) ) {
				// Forward onto a sheet's old entry: same URL, nothing to do (and nothing for the router to reload).
				event.stopImmediatePropagation();
			}
		},
		true
	);

	// First load with a link to a collateral: open it. A slug that is not a published collateral is dropped.
	const linked = deeplink.slugFromUrl( window.location.href );
	if ( linked ) {
		if ( ! idBySlug[ linked ] ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		} else {
			const id = idBySlug[ linked ];
			show( id, {
				push: false,
				trigger: document.querySelector( `.collaterals-item__open[data-collateral-id="${ id }"]` ),
			} );
		}
	}

	routing.onChange( swapDialogs );
}
