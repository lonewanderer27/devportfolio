import { normalisePage, mobileFooterParts } from './pager.js';
import { unitOf, wording, positionText } from './mode.js';
import { applyDownload, guardDisabledDownload } from './download.js';
import { createMobileViewer } from './mobile-viewer.js';
import { SIDEWAYS_QUERY } from './routing.js';
import { createSheetController } from './sheet-controller.js';

/**
 * The mobile dialog body (DPD-017 to DPD-024; DPC-015 to DPC-019). Shared by the Documents and the Collaterals
 * modals: the markup classes come from `prefix` (`documents-mobile` or `collaterals-mobile`), the viewer's
 * classes from `viewPrefix`, the events from `eventPrefix`, and `loadDetails()` returns the modal's own Details sheet
 * factory (a lazy import in its own folder, so it resolves there).
 */
export function createMobileModal( { dialog, prefix = 'documents-mobile', viewPrefix = 'documents-modal', eventPrefix = 'documents', loadDetails = null, units = {}, strings = {}, pdfjs = {}, limits = {}, selectableText = false, singlePage = false, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( `.${ prefix }__title` ),
		close: $( `.${ prefix }__close` ),
		download: $( `.${ prefix }__download` ),
		pages: $( `.${ prefix }__pages` ),
		details: $( `.${ prefix }__details` ),
		position: $( `.${ prefix }__position` ),
		bar: $( `.${ prefix }__bar` ),
		footer: $( `.${ prefix }__footer` ),
		stage: $( `.${ prefix }__stage` ),
		status: $( `.${ prefix }__state` ),
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
		emit( `${ eventPrefix }:mobile-change` );
	};

	/** The viewer knows the real page count and whether two pages fit once it has the pages and a size. */
	const setGeometry = ( { pages, step } ) => {
		state.pages = pages;
		state.step = step;
		state.page = pages > 0 ? normalisePage( state.page, pages, step ) : 1;
		drawFooter();
		emit( `${ eventPrefix }:mobile-change` );
		return state.page;
	};

	sheets.register( 'pages', {
		closeOnRotate: true,
		create: async ( { dialog: host, onClose: closed } ) => {
			const mod = await import( './pages-sheet.js' );
			return mod.createPagesSheet( {
				dialog: host,
				prefix,
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
			// The modal's own Details content (the document or collateral sheet), loaded on the first tap.
			const mod = await loadDetails();
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
		prefix,
		viewPrefix,
		stage: els.stage,
		status: els.status,
		pdfjs,
		limits,
		units,
		strings,
		selectableText,
		singlePage,
		onGeometry: setGeometry,
		onTurn: turned,
		onPages: () => emit( `${ eventPrefix }:mobile-pages` ),
		onDetails: () => emit( `${ eventPrefix }:mobile-details` ),
	} );
	landscape.addEventListener( 'change', () => {
		sheets.closeOnRotate();
		drawFooter();
	} );
	dialog.addEventListener( `${ eventPrefix }:mobile-pages`, openPages );
	dialog.addEventListener( `${ eventPrefix }:mobile-details`, openDetails );


	const setPage = ( page ) => {
		state.page = normalisePage( page, state.pages, state.step );
		drawFooter();
		emit( `${ eventPrefix }:mobile-change` );
		viewer.goTo( state.page );
	};

	guardDisabledDownload( els.download );
	els.close.addEventListener( 'click', () => dialog.close() );
	els.pages.addEventListener( 'click', () => emit( `${ eventPrefix }:mobile-pages` ) );
	els.details.addEventListener( 'click', () => emit( `${ eventPrefix }:mobile-details` ) );
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
		if ( event.target && event.target.closest && event.target.closest( `.${ prefix }__scroll` ) ) {
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
			// A pending or unavailable preview has no pages to browse (DPC-025): no footer, no progress bar,
			// and the round details button takes the footer's place from the first paint.
			const noPages = doc.previewState === 'pending' || doc.previewState === 'unavailable';
			state.pages = noPages ? 0 : ( ( Array.isArray( doc.images ) && doc.images.length ) || doc.pages || 0 );
			state.step = 1;
			state.page = normalisePage( page, state.pages, 1 );
			els.title.textContent = doc.title;
			applyDownload( els.download, doc );
			drawFooter();
			dialog.showModal();
			els.close.focus();
			emit( `${ eventPrefix }:mobile-change` );
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
