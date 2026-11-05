/**
 * Document modal (DPD-009 to DPD-012).
 *
 * Any element with data-document-id opens the shared <dialog> on screens wider
 * than 700px. Fills header and footer from the JSON payload; the body is left
 * to DPD-013 (listen for `documents:modal-change` on the dialog).
 */
import {
	stepFor,
	normalisePage,
	pagerItems,
	visiblePages,
	pagerState,
	turnPage,
	indicatorParts,
} from './pager.js';

const root = document.querySelector( '.documents-modal-root' );
const dialog = root ? root.querySelector( '.documents-modal' ) : null;
const dataEl = root ? root.querySelector( '.documents-modal__data' ) : null;

if ( dialog && dataEl ) {
	let documents = {};
	try {
		documents = JSON.parse( dataEl.textContent ) || {};
	} catch ( e ) {
		documents = {};
	}

	const desktop = window.matchMedia( '(min-width: 701px)' );
	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		title: $( '.documents-modal__title' ),
		desc: $( '.documents-modal__desc' ),
		tags: $( '.documents-modal__tags' ),
		format: $( '.documents-modal__format' ),
		download: $( '.documents-modal__download' ),
		close: $( '.documents-modal__close' ),
		pager: $( '.documents-modal__pager' ),
		indicator: $( '.documents-modal__indicator' ),
		count: $( '.documents-modal__count' ),
		bar: $( '.documents-modal__bar' ),
		legend: $( '.documents-modal__legend' ),
		prev: $( '.documents-modal__arrow--prev' ),
		next: $( '.documents-modal__arrow--next' ),
	};
	const state = { id: 0, page: 1, step: 1, pages: 0 };
	let opener = null;

	const emit = () =>
		dialog.dispatchEvent( new CustomEvent( 'documents:modal-change', { detail: { ...state } } ) );

	const makeButton = ( className, text, attrs ) => {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = className;
		button.textContent = text;
		Object.entries( attrs ).forEach( ( [ key, value ] ) => button.setAttribute( key, value ) );
		return button;
	};

	const drawFooter = () => {
		const { pages, step } = state;

		// Page arrows (DPD-014): dimmed on the first and last view; both dimmed with one page.
		const arrows = pages < 1 ? { backOff: true, nextOff: true } : pagerState( normalisePage( state.page, pages, step ), pages, step );
		els.prev.setAttribute( 'aria-disabled', String( arrows.backOff ) );
		els.next.setAttribute( 'aria-disabled', String( arrows.nextOff ) );

		if ( pages < 1 ) {
			els.pager.hidden = true;
			els.indicator.hidden = true;
			return;
		}

		state.page = normalisePage( state.page, pages, step );
		const { first, last, both, percent } = indicatorParts( state.page, pages, step );

		els.count.textContent = both
			? `Pages ${ first }–${ last } of ${ pages }`
			: `Page ${ first } of ${ pages }`;
		els.legend.textContent = pages > 1 ? 'Two-page spread' : 'Single page';
		els.bar.firstElementChild.style.width = `${ percent }%`;
		els.bar.setAttribute( 'aria-valuenow', String( Math.round( percent ) ) );
		els.indicator.hidden = false;

		els.pager.replaceChildren();
		if ( pages === 1 ) {
			els.pager.hidden = true;
			return;
		}

		const { backOff, nextOff } = pagerState( state.page, pages, step );
		const visible = visiblePages( state.page, pages, step );
		els.pager.append(
			makeButton( 'documents-modal__pg documents-modal__pg--nav', '‹ Back', {
				'data-turn': '-1',
				'aria-disabled': String( backOff ),
			} ),
			...pagerItems( pages, state.page, step ).map( ( item ) => {
				if ( item === '…' ) {
					const gap = document.createElement( 'span' );
					gap.className = 'documents-modal__gap';
					gap.setAttribute( 'aria-hidden', 'true' );
					gap.textContent = '···';
					return gap;
				}
				const on = visible.includes( item );
				const attrs = { 'data-page': String( item ), 'aria-label': `Page ${ item }` };
				if ( on ) {
					attrs[ 'aria-current' ] = 'page';
				}
				return makeButton(
					'documents-modal__pg documents-modal__pg--num' + ( on ? ' is-on' : '' ),
					String( item ),
					attrs
				);
			} ),
			makeButton( 'documents-modal__pg documents-modal__pg--nav', 'Next ›', {
				'data-turn': '1',
				'aria-disabled': String( nextOff ),
			} )
		);
		els.pager.hidden = false;
	};

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

		const link = els.download;
		link.removeAttribute( 'href' );
		link.removeAttribute( 'download' );
		link.removeAttribute( 'target' );
		link.removeAttribute( 'rel' );
		link.removeAttribute( 'aria-disabled' );
		link.setAttribute( 'aria-label', `Download ${ doc.title }` );
		if ( doc.downloadMode === 'file' ) {
			link.href = doc.downloadUrl;
			link.setAttribute( 'download', '' );
		} else if ( doc.downloadMode === 'embed' ) {
			link.href = doc.downloadUrl;
			link.target = '_blank';
			link.rel = 'noopener';
		} else {
			link.setAttribute( 'aria-disabled', 'true' );
		}
	};

	const setPage = ( page ) => {
		state.page = normalisePage( page, state.pages, state.step );
		drawFooter();
		emit();
	};

	const open = ( id, trigger ) => {
		const doc = documents[ id ];
		if ( ! doc ) {
			return;
		}
		opener = trigger;
		state.id = doc.id;
		state.pages = doc.pages;
		state.step = stepFor( doc.pages );
		state.page = 1;

		drawHeader( doc );
		drawFooter();
		document.documentElement.style.overflow = 'hidden';
		dialog.showModal();
		els.close.focus();
		emit();
	};

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest ? event.target.closest( '[data-document-id]' ) : null;
		if ( ! trigger || ! desktop.matches ) {
			return;
		}
		event.preventDefault();
		open( trigger.getAttribute( 'data-document-id' ), trigger );
	} );

	// A click on the dimmed backdrop lands on the dialog element itself.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	els.close.addEventListener( 'click', () => dialog.close() );

	dialog.addEventListener( 'close', () => {
		document.documentElement.style.overflow = '';
		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}
		opener = null;
	} );

	els.download.addEventListener( 'click', ( event ) => {
		if ( els.download.getAttribute( 'aria-disabled' ) === 'true' ) {
			event.preventDefault();
		}
	} );

	[ els.prev, els.next ].forEach( ( arrow ) =>
		arrow.addEventListener( 'click', () => {
			if ( arrow.getAttribute( 'aria-disabled' ) !== 'true' ) {
				setPage( turnPage( state.page, state.pages, state.step, arrow === els.next ? 1 : -1 ) );
			}
		} )
	);

	els.pager.addEventListener( 'click', ( event ) => {
		const turn = event.target.closest( '[data-turn]' );
		const num = event.target.closest( '[data-page]' );
		if ( turn ) {
			if ( turn.getAttribute( 'aria-disabled' ) !== 'true' ) {
				setPage( turnPage( state.page, state.pages, state.step, Number( turn.dataset.turn ) ) );
			}
		} else if ( num ) {
			setPage( Number( num.dataset.page ) );
		}
	} );

	// Shrinking to 700px or less closes the desktop modal (mobile is DPD-017).
	desktop.addEventListener( 'change', ( event ) => {
		if ( ! event.matches && dialog.open ) {
			dialog.close();
		}
	} );
}
