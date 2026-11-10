/**
 * Collateral modal (DPC-007 to DPC-011, DPC-020 to DPC-022).
 *
 * A click on `[data-collateral-id]` (the grid's title button) opens the desktop or mobile dialog for the
 * viewport. The sidebar is filled from the JSON payload with textContent only. Open, close, Back/Forward
 * and the swap to a Next collateral follow the Documents modal (document-modal/view.js), with the
 * parameter `collateral_slug`: a click pushes one history entry, a swap replaces it (one Back always closes).
 * The body (DPC-012) and footer (DPC-013) listen for `collaterals:modal-change` on the dialog.
 */
import { createRouting } from '../document-modal/routing.js';
import { createDeeplink } from '../document-modal/deeplink.js';
import { applyDownload, guardDisabledDownload } from '../document-modal/download.js';
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
	};
	const copyLabel = els.copy.textContent;
	const mobile = {
		title: mobileDialog.querySelector( '.collaterals-mobile__title' ),
		close: mobileDialog.querySelector( '.collaterals-mobile__close' ),
	};

	let currentId = 0;
	let opener = null;
	let pushed = false;
	let closingFromHistory = false;
	let swapping = false;
	let copyTimer = 0;

	const isOpen = () => dialog.open || mobileDialog.open;
	const shownDialog = () => ( dialog.open ? dialog : mobileDialog.open ? mobileDialog : null );
	const wantedDialog = () => ( routing.isDesktop() ? dialog : mobileDialog );

	/** Announces the change on the dialog that is on screen (DPC-012 listens for it). */
	const emit = () => {
		const target = wantedDialog();
		const name = target === dialog ? 'collaterals:modal-change' : 'collaterals:mobile-change';
		target.dispatchEvent( new CustomEvent( name, { detail: { id: currentId, page: 1 } } ) );
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

	/** Fills the sidebar and the mobile title from one payload item. textContent only. */
	const fill = ( item ) => {
		els.pillCat.textContent = item.category;
		els.pillCat.hidden = ! item.category;
		els.pillSize.textContent = item.pill;
		els.pillSize.hidden = ! item.pill;
		els.pills.hidden = ! item.category && ! item.pill;

		els.title.textContent = item.title;
		mobile.title.textContent = item.title;
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

	const finishClose = () => {
		// The other dialog is taking over (a swap on resize): nothing to close for the visitor.
		if ( swapping ) {
			swapping = false;
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
			window.history.back();
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
		if ( ! dialog.open || ! mobileDialog.open ) {
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
		const target = wantedDialog();
		target.showModal();
		( target === dialog ? els.close : mobile.close ).focus();
		enforceSingleDialog();
		emit();
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
		( wantedDialog() === dialog ? els.title : mobile.title ).focus();
		emit();
	};

	/** The dialog changes between desktop and mobile in place (same collateral, page and URL). */
	const swapDialogs = () => {
		enforceSingleDialog();
		const shown = shownDialog();
		if ( ! shown || ( shown === dialog ) === routing.isDesktop() ) {
			return;
		}
		const run = () => {
			swapping = true;
			shown.close();
			const next = wantedDialog();
			next.showModal();
			( next === dialog ? els.close : mobile.close ).focus();
			emit();
		};
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

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest ? event.target.closest( '[data-collateral-id]' ) : null;
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		show( Number( trigger.getAttribute( 'data-collateral-id' ) ), { push: true, trigger } );
	} );

	// A click on the dimmed backdrop lands on the dialog itself (the mobile dialog has none).
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	els.close.addEventListener( 'click', closeFromButton );
	mobile.close.addEventListener( 'click', closeFromButton );
	dialog.addEventListener( 'close', finishClose );
	mobileDialog.addEventListener( 'close', finishClose );

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
	// popstate and reloads the page for a URL it has not cached (see document-modal/view.js).
	window.addEventListener(
		'popstate',
		( event ) => {
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
