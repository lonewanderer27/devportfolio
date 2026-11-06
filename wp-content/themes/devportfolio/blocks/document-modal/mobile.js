import { normalisePage, mobileFooterParts } from './pager.js';
import { unitOf, wording, positionText } from './mode.js';
import { applyDownload, guardDisabledDownload } from './download.js';
import { createMobileViewer } from './mobile-viewer.js';
import { SIDEWAYS_QUERY } from './routing.js';
import { createSheetController } from './sheet-controller.js';

export function createMobileModal( { dialog, units = {}, strings = {}, pdfjs = {}, limits = {}, selectableText = false, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( '.documents-mobile__title' ),
		close: $( '.documents-mobile__close' ),
		download: $( '.documents-mobile__download' ),
		pages: $( '.documents-mobile__pages' ),
		details: $( '.documents-mobile__details' ),
		position: $( '.documents-mobile__position' ),
		bar: $( '.documents-mobile__bar' ),
		footer: $( '.documents-mobile__footer' ),
		stage: $( '.documents-mobile__stage' ),
		status: $( '.documents-mobile__state' ),
	};
	const state = { id: 0, page: 1, pages: 0, step: 1 };
	// The bottom sheets (Pages, DPD-020; Details, DPD-023): each one's code is loaded with import() on its first
	// tap, one is open at a time, and while open it owns one history entry (sheet-controller.js, sheet-back.js)
	// so Back closes only the sheet. The controller is created here, at start, never inside a lazy import.
	const sheets = createSheetController( { dialog } );
	let openDoc = null;
	// A phone held sideways: the footer makes way for the page, and its buttons become round buttons (DPD-024).
	const landscape = window.matchMedia( SIDEWAYS_QUERY );
	let words = wording( 'page', units );
	const emit = ( name ) =>
		dialog.dispatchEvent( new CustomEvent( name, { bubbles: true, detail: { ...state } } ) );
	const drawFooter = () => {
		const parts = mobileFooterParts( state.page, state.pages, state.step );
		state.page = parts.page;
		els.position.textContent = parts.label;
		els.bar.hidden = state.pages < 2;
		els.bar.firstElementChild.style.width = `${ parts.percent }%`;
		els.bar.setAttribute( 'aria-valuenow', String( Math.round( parts.percent ) ) );
		const last = Math.min( state.pages, parts.page + state.step - 1 );
		els.bar.setAttribute(
			'aria-valuetext',
			state.pages > 0 ? positionText( words, parts.page, last, state.pages ) : ''
		);
		// One page (or none): nothing to browse, so the whole footer and the progress bar are not shown (DPD-024).
		const footerGone = state.pages < 2 || landscape.matches;
		els.footer.hidden = footerGone;
		els.pages.hidden = state.pages < 2;
		// The footer's Details and Pages buttons live on as round buttons while the footer is gone.
		viewer.setChrome( { info: footerGone, pages: landscape.matches && state.pages > 1 } );
	};

	// A page turn made inside the viewer (swipe, edge tap, edge button, key): only the footer follows.
	const turned = ( page ) => {
		state.page = normalisePage( page, state.pages, state.step );
		drawFooter();
		emit( 'documents:mobile-change' );
	};

	/** The viewer knows the real page count and whether two pages fit once it has the pages and a size. */
	const setGeometry = ( { pages, step } ) => {
		state.pages = pages;
		state.step = step;
		state.page = pages > 0 ? normalisePage( state.page, pages, step ) : 1;
		drawFooter();
		emit( 'documents:mobile-change' );
		return state.page;
	};

	sheets.register( 'pages', {
		closeOnRotate: true,
		create: async ( { dialog: host, onClose: closed } ) => {
			const mod = await import( './pages-sheet.js' );
			return mod.createPagesSheet( {
				dialog: host,
				strings,
				onSelect: ( page ) => {
					sheets.close();
					setPage( page );
				},
				onClose: closed,
			} );
		},
	} );
	// The Details sheet keeps its place when the phone is rotated: it only re-lays out (CSS).
	sheets.register( 'details', {
		create: async ( { dialog: host, onClose: closed } ) => {
			const mod = await import( './details-sheet.js' );
			return mod.createDetailsSheet( { dialog: host, strings, units, onClose: closed } );
		},
	} );

	const openPages = () => {
		if ( openDoc && state.pages >= 2 ) {
			sheets.open( 'pages', { doc: openDoc, page: state.page, pages: state.pages, step: state.step, opener: document.activeElement } );
		}
	};

	const openDetails = () => {
		if ( openDoc ) {
			sheets.open( 'details', { doc: openDoc, opener: document.activeElement } );
		}
	};

	const viewer = createMobileViewer( {
		stage: els.stage,
		status: els.status,
		pdfjs,
		limits,
		units,
		strings,
		selectableText,
		onGeometry: setGeometry,
		onTurn: turned,
		onPages: () => emit( 'documents:mobile-pages' ),
		onDetails: () => emit( 'documents:mobile-details' ),
	} );
	landscape.addEventListener( 'change', () => {
		sheets.closeOnRotate();
		drawFooter();
	} );
	dialog.addEventListener( 'documents:mobile-pages', openPages );
	dialog.addEventListener( 'documents:mobile-details', openDetails );


	const setPage = ( page ) => {
		state.page = normalisePage( page, state.pages, state.step );
		drawFooter();
		emit( 'documents:mobile-change' );
		viewer.goTo( state.page );
	};

	guardDisabledDownload( els.download );
	els.close.addEventListener( 'click', () => dialog.close() );
	els.pages.addEventListener( 'click', () => emit( 'documents:mobile-pages' ) );
	els.details.addEventListener( 'click', () => emit( 'documents:mobile-details' ) );
	dialog.addEventListener( 'close', () => {
		// The viewer closed with the sheet open: its history entry is still there; view.js removes it too.
		const extra = sheets.release();
		sheets.reset();
		openDoc = null;
		viewer.destroy();
		if ( onClose ) {
			onClose( extra );
		}
	} );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey ) {
			return;
		}
		if ( event.target && event.target.closest && event.target.closest( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}
		// A sheet is on top of the viewer: the page keys do nothing behind it.
		if ( sheets.isAnyOpen() ) {
			return;
		}
		// The native workbook grid and Word scroller (DPD-030, DPD-031) own the arrow, zoom and 0 keys while it has the focus.
		if ( event.target && event.target.closest && event.target.closest( '.documents-mobile__scroll' ) ) {
			return;
		}
		if ( ! event.shiftKey && ( event.key === 'ArrowLeft' || event.key === 'ArrowRight' ) ) {
			event.preventDefault();
			viewer.turn( event.key === 'ArrowRight' ? 1 : -1 );
			return;
		}
		// Zoom keys: the non-gesture way to zoom (accessibility). `+` is Shift and `=` on most keyboards.
		if ( event.key === '+' || event.key === '=' ) {
			event.preventDefault();
			viewer.zoomStep( 1 );
		} else if ( event.key === '-' || event.key === '_' ) {
			event.preventDefault();
			viewer.zoomStep( -1 );
		} else if ( event.key === '0' && ! event.shiftKey ) {
			event.preventDefault();
			viewer.resetZoom();
		}
	} );

	return {
		show( doc, page = 1 ) {
			state.id = doc.id;
			openDoc = doc;
			sheets.reset();
			words = wording( unitOf( doc ), units );
			state.pages = ( Array.isArray( doc.images ) && doc.images.length ) || doc.pages || 0;
			state.step = 1;
			state.page = normalisePage( page, state.pages, 1 );
			els.title.textContent = doc.title;
			applyDownload( els.download, doc );
			drawFooter();
			dialog.showModal();
			els.close.focus();
			emit( 'documents:mobile-change' );
			viewer.show( doc, state.page );
		},
		close() {
			if ( dialog.open ) {
				dialog.close();
			}
		},
		isOpen: () => dialog.open,
		state: () => ( { ...state } ),
		setPage,
	};
}
