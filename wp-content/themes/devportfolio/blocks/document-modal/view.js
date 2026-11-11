/**
 * Document modal (DPD-009 to DPD-015).
 *
 * Any element with data-document-id opens the desktop or mobile <dialog> for
 * the current viewport. Fills header and footer from the JSON payload; the body shows the
 * pages as an open book (DPD-013, viewer.js). This file owns the current page
 * (`state`): the pager, the arrows, the keyboard and the book itself all end in
 * the shared pager controller (setPage), and `documents:modal-change` is dispatched on the dialog after
 * every open and page change.
 */
import { stepFor } from '../shared/pager.js';
import { mergeLimits } from '../shared/capability.js';
import { createViewer } from '../shared/viewer.js';
import { viewerMode, unitOf, wording } from '../shared/mode.js';
import { createPagerController } from '../shared/pager-controller.js';
import { slugFromUrl, urlWithSlug, urlWithoutSlug, hasSheetState } from '../shared/deeplink.js';
import { createMobileModal } from '../shared/mobile.js';
import { createRouting } from '../shared/routing.js';
import { bindOpen } from '../shared/modal-trigger.js';
import { applyDownload, guardDisabledDownload } from '../shared/download.js';

const root = document.querySelector( '.documents-modal-root' );
const dialog = root ? root.querySelector( '.documents-modal' ) : null;
const mobileDialog = root ? root.querySelector( '.documents-mobile' ) : null;
const dataEl = root ? root.querySelector( '.documents-modal__data' ) : null;

if ( dialog && dataEl ) {
	let documents = {};
	try {
		documents = JSON.parse( dataEl.textContent ) || {};
	} catch ( e ) {
		documents = {};
	}

	// One definition of which dialog a screen gets (routing.js); the CSS repeats its queries.
	const routing = createRouting();
	const desktop = { get matches() { return routing.isDesktop(); } };
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( '.documents-modal__title' ),
		desc: $( '.documents-modal__desc' ),
		tags: $( '.documents-modal__tags' ),
		format: $( '.documents-modal__format' ),
		download: $( '.documents-modal__download' ),
		close: $( '.documents-modal__close' ),
		pager: $( '.documents-modal__pager' ),
		footer: $( '.documents-modal__footer' ),
		indicator: $( '.documents-modal__indicator' ),
		bar: $( '.documents-modal__bar' ),
		prev: $( '.documents-modal__arrow--prev' ),
		next: $( '.documents-modal__arrow--next' ),
		stage: $( '.documents-modal__stage' ),
		status: $( '.documents-modal__state' ),
	};
	// The footer, pager, arrows and keys are one shared controller (DPD-013, DPC-013); it owns the page state.
	const pager = createPagerController( {
		els: { footer: els.footer, pager: els.pager, indicator: els.indicator, bar: els.bar, prev: els.prev, next: els.next },
		prefix: 'documents-modal',
		target: dialog,
		eventName: 'documents:modal-change',
		ignore: '.documents-modal__grid',
	} );
	const state = pager.state;
	let opener = null;
	// Deeplink (DPD-032): the open document is `?document_slug=<slug>`. `pushed` is true when the current
	// history entry was added by this modal, so closing can step back instead of leaving a dead entry;
	// `closingFromHistory` is true while Back/Forward is the reason the dialog closes.
	let pushed = false;
	let closingFromHistory = false;
	let swapping = false;
	const idBySlug = {};
	Object.values( documents ).forEach( ( doc ) => {
		if ( doc && doc.slug ) {
			idBySlug[ doc.slug ] = doc.id;
		}
	} );
	let units = {};

	const readJson = ( name ) => {
		try {
			const value = JSON.parse( root.getAttribute( name ) );
			return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
		} catch ( e ) {
			return {};
		}
	};

	const emit = pager.emit;

	const drawHeader = ( doc ) => {
		els.title.textContent = doc.title;
		els.desc.textContent = doc.description;
		els.desc.hidden = ! doc.description;

		els.tags.replaceChildren(
			...( doc.tools || [] ).map( ( name ) => {
				const li = document.createElement( 'li' );
				li.textContent = name;
				return li;
			} )
		);
		els.tags.hidden = ! ( doc.tools && doc.tools.length );

		els.format.textContent = doc.format;
		els.format.hidden = ! doc.format;

		applyDownload( els.download, doc );
	};

	units = readJson( 'data-units' );

	const viewer = createViewer( {
		prefix: 'documents-modal',
		stage: els.stage,
		status: els.status,
		units,
		pdfjs: readJson( 'data-pdfjs' ),
		limits: mergeLimits( readJson( 'data-limits' ) ),
		selectableText: root.getAttribute( 'data-selectable-text' ) === '1',
		onGeometry: pager.setGeometry,
		onFlip: pager.syncFromViewer,
		// A swipe on a touch screen turns the view like Back / Next (DPD-024).
		onSwipe: ( direction ) => pager.turn( direction ),
	} );
	pager.attach( viewer );

	// A Pages sheet entry left in the history when the viewer closes (DPD-020): `skipNextPop` makes the popstate
	// that removing it causes do nothing but drop a stale `?document_slug` from the URL.
	let skipNextPop = false;

	const finishClose = ( which, extra = 0 ) => {
		if ( which === 'desktop' ) {
			viewer.destroy();
		}
		if ( swapping ) {
			swapping = false;
			if ( extra > 0 ) {
				// The sheet's entry has the same URL as the viewer's: step back onto it; the other dialog is open.
				window.history.go( -extra );
			}
			swap();
			return;
		}
		document.documentElement.style.overflow = '';
		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}
		opener = null;
		if ( closingFromHistory ) {
			closingFromHistory = false;
			pushed = false;
		} else if ( pushed ) {
			pushed = false;
			if ( extra > 0 ) {
				// Also remove the Pages sheet's entry.
				window.history.go( -( 1 + extra ) );
			} else {
				window.history.back();
			}
		} else if ( extra > 0 ) {
			skipNextPop = true;
			window.history.go( -extra );
		} else if ( slugFromUrl( window.location.href ) ) {
			window.history.replaceState( window.history.state, '', urlWithoutSlug( window.location.href ) );
		}
	};

	const mobile = mobileDialog ? createMobileModal( {
		dialog: mobileDialog,
		prefix: 'documents-mobile',
		viewPrefix: 'documents-modal',
		eventPrefix: 'documents',
		loadDetails: () => import( './details-sheet.js' ),
		units,
		strings: readJson( 'data-mobile-strings' ),
		pdfjs: readJson( 'data-pdfjs' ),
		limits: mergeLimits( readJson( 'data-limits' ) ),
		selectableText: root.getAttribute( 'data-selectable-text' ) === '1',
		onClose: ( extra ) => finishClose( 'mobile', extra ),
	} ) : null;

	const isOpen = () => dialog.open || ( mobile !== null && mobile.isOpen() );

	/**
	 * Never both dialogs at once. If it ever happens (a swap interrupted, two media changes in one frame),
	 * the one that does not belong on this screen is closed; `swapping` makes its close handler reconcile
	 * instead of ending the whole session.
	 */
	const enforceSingleDialog = () => {
		if ( ! mobile || ! dialog.open || ! mobile.isOpen() ) {
			return;
		}
		swapping = true;
		if ( desktop.matches ) {
			mobile.close();
		} else {
			dialog.close();
		}
	};
	const closeOpen = () => {
		if ( dialog.open ) {
			dialog.close();
		} else if ( mobile ) {
			mobile.close();
		}
	};

	const openDesktop = ( doc, page = 1 ) => {
		const stored = Array.isArray( doc.images ) ? doc.images.length : 0;
		const pages = stored || doc.pages;
		const step = stepFor( pages, ( stored && doc.ratio > 1 ) || viewerMode( doc ) === 'native' ? 'landscape' : 'portrait' );
		// What the open document's pieces are called: pages, or slides for a presentation (DPD-026).
		pager.open( doc.id, { pages, step, page, words: wording( unitOf( doc ), units ) } );
		drawHeader( doc );
		dialog.showModal();
		els.close.focus();
		emit();
		viewer.show( doc, state.page );
	};

	const open = ( id, trigger, { push = true, page = 1 } = {} ) => {
		const doc = documents[ id ];
		if ( ! doc || isOpen() ) {
			return;
		}
		if ( push && doc.slug ) {
			window.history.pushState( { documentSlug: doc.slug }, '', urlWithSlug( window.location.href, doc.slug ) );
			pushed = true;
		}
		opener = trigger;
		document.documentElement.style.overflow = 'hidden';
		if ( desktop.matches || ! mobile ) {
			openDesktop( doc, page );
		} else {
			mobile.show( doc, page );
		}
		enforceSingleDialog();
	};

	bindOpen( document, 'data-document-id', open );

	// A click on the dimmed backdrop lands on the dialog element itself.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	els.close.addEventListener( 'click', () => dialog.close() );

	dialog.addEventListener( 'close', () => finishClose( 'desktop' ) );

	// Back and Forward open or close the modal to match the URL, without adding entries. Registered in the capture
	// phase and stopping the event whenever it acts on it: WordPress's Interactivity Router (loaded for the
	// Navigation block) answers every popstate and reloads the page for a URL it has not cached (here
	// `?document_slug=...`), which flashed the page on Forward. A move that is not the modal's (a category change,
	// or the modal already in the state the URL describes) still reaches the other listeners.
	window.addEventListener( 'popstate', ( event ) => {
		if ( skipNextPop ) {
			skipNextPop = false;
			if ( slugFromUrl( window.location.href ) && ! isOpen() ) {
				window.history.replaceState( window.history.state, '', urlWithoutSlug( window.location.href ) );
			}
			event.stopImmediatePropagation();
			return;
		}
		if ( swapping ) {
			event.stopImmediatePropagation();
			return;
		}
		const slug = slugFromUrl( window.location.href );
		if ( ! slug && isOpen() ) {
			event.stopImmediatePropagation();
			closingFromHistory = true;
			closeOpen();
		} else if ( slug && ! isOpen() && idBySlug[ slug ] ) {
			event.stopImmediatePropagation();
			open( idBySlug[ slug ], null, { push: false } );
			pushed = true;
		} else if ( slug && isOpen() && hasSheetState( event.state ) ) {
			// Forward onto the Pages sheet's old entry: same URL, nothing to do (and nothing for the router to reload).
			event.stopImmediatePropagation();
		}
	}, true );

	// First load with a link to a document: open it. A slug that is not a published document is dropped.
	const linked = slugFromUrl( window.location.href );
	if ( linked ) {
		if ( ! idBySlug[ linked ] ) {
			window.history.replaceState( window.history.state, '', urlWithoutSlug( window.location.href ) );
		} else {
			// The shelf button gets the focus back on close, as after a click.
			open( idBySlug[ linked ], document.querySelector( `.documents-item__open[data-document-id="${ idBySlug[ linked ] }"]` ), { push: false } );
		}
	}

	guardDisabledDownload( els.download );

	const swap = () => {
		enforceSingleDialog();
		if ( swapping || ! mobile || ! isOpen() ) {
			return;
		}
		const mobileOpen = mobile.isOpen();
		if ( mobileOpen === ! desktop.matches ) {
			return;
		}
		const doc = documents[ mobileOpen ? mobile.state().id : state.id ];
		const page = mobileOpen ? mobile.state().page : state.page;
		const run = () => {
			swapping = true;
			if ( mobileOpen ) {
				mobile.close();
				openDesktop( doc, page );
			} else {
				dialog.close();
				mobile.show( doc, page );
			}
		};
		const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( ! document.startViewTransition || reduce ) {
			run();
			return;
		}
		const html = document.documentElement;
		html.setAttribute( 'data-documents-swapping', '' );
		const transition = document.startViewTransition( run );
		transition.finished.finally( () => {
			html.removeAttribute( 'data-documents-swapping' );
			swap();
		} );
	};
	routing.onChange( swap );
}
