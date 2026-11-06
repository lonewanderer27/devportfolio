import { normalisePage, turnPage, mobileFooterParts } from './pager.js';
import { unitOf, wording, positionText } from './mode.js';
import { applyDownload, guardDisabledDownload } from './download.js';

export function createMobileModal( { dialog, units = {}, onClose } ) {
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( '.documents-mobile__title' ),
		close: $( '.documents-mobile__close' ),
		download: $( '.documents-mobile__download' ),
		pages: $( '.documents-mobile__pages' ),
		details: $( '.documents-mobile__details' ),
		position: $( '.documents-mobile__position' ),
		bar: $( '.documents-mobile__bar' ),
	};
	const state = { id: 0, page: 1, pages: 0 };
	let words = wording( 'page', units );
	const emit = ( name ) =>
		dialog.dispatchEvent( new CustomEvent( name, { bubbles: true, detail: { ...state } } ) );
	const drawFooter = () => {
		const parts = mobileFooterParts( state.page, state.pages );
		state.page = parts.page;
		els.position.textContent = parts.label;
		els.bar.hidden = state.pages < 2;
		els.bar.firstElementChild.style.width = `${ parts.percent }%`;
		els.bar.setAttribute( 'aria-valuenow', String( Math.round( parts.percent ) ) );
		els.bar.setAttribute(
			'aria-valuetext',
			state.pages > 0 ? positionText( words, parts.page, parts.page, state.pages ) : ''
		);
	};
	const setPage = ( page ) => {
		state.page = normalisePage( page, state.pages, 1 );
		drawFooter();
		emit( 'documents:mobile-change' );
	};

	guardDisabledDownload( els.download );
	els.close.addEventListener( 'click', () => dialog.close() );
	els.pages.addEventListener( 'click', () => emit( 'documents:mobile-pages' ) );
	els.details.addEventListener( 'click', () => emit( 'documents:mobile-details' ) );
	dialog.addEventListener( 'close', () => onClose && onClose() );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
			return;
		}
		if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
			return;
		}
		if ( event.target && event.target.closest && event.target.closest( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}
		event.preventDefault();
		setPage( turnPage( state.page, state.pages, 1, event.key === 'ArrowRight' ? 1 : -1 ) );
	} );

	return {
		show( doc, page = 1 ) {
			state.id = doc.id;
			words = wording( unitOf( doc ), units );
			state.pages = ( Array.isArray( doc.images ) && doc.images.length ) || doc.pages || 0;
			state.page = normalisePage( page, state.pages, 1 );
			els.title.textContent = doc.title;
			applyDownload( els.download, doc );
			drawFooter();
			dialog.showModal();
			els.close.focus();
			emit( 'documents:mobile-change' );
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
