/**
 * Document modal (DPD-009 to DPD-015).
 *
 * Any element with data-document-id opens the shared <dialog> on screens wider
 * than 700px. Fills header and footer from the JSON payload; the body shows the
 * pages as an open book (DPD-013, viewer.js). This file owns the current page
 * (`state`): the pager, the arrows, the keyboard and the book itself all end in
 * setPage(), and `documents:modal-change` is dispatched on the dialog after
 * every open and page change.
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
import { mergeLimits } from './capability.js';
import { createViewer } from './viewer.js';
import { viewerMode, unitOf, wording, positionText } from './mode.js';

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
		footer: $( '.documents-modal__footer' ),
		indicator: $( '.documents-modal__indicator' ),
		bar: $( '.documents-modal__bar' ),
		prev: $( '.documents-modal__arrow--prev' ),
		next: $( '.documents-modal__arrow--next' ),
		stage: $( '.documents-modal__stage' ),
		status: $( '.documents-modal__state' ),
	};
	const state = { id: 0, page: 1, step: 1, pages: 0 };
	let opener = null;
	let units = {};
	// What the open document's pieces are called: pages, or slides for a presentation (DPD-026).
	let words = wording( 'page', units );

	const readJson = ( name ) => {
		try {
			const value = JSON.parse( root.getAttribute( name ) );
			return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
		} catch ( e ) {
			return {};
		}
	};

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

		// Page arrows (DPD-014): dimmed on the first and last view; hidden with one page.
		const arrows = pagerState( normalisePage( state.page, pages, step ), pages, step );
		els.prev.setAttribute( 'aria-disabled', String( arrows.backOff ) );
		els.next.setAttribute( 'aria-disabled', String( arrows.nextOff ) );
		// One page (or none): nothing to turn, so the arrows are not shown at all.
		els.prev.hidden = pages < 2;
		els.next.hidden = pages < 2;

		// One page (or none): there is nothing to page through, so the footer is not shown at all.
		if ( pages < 2 ) {
			els.footer.hidden = true;
			els.pager.hidden = true;
			els.indicator.hidden = true;
			return;
		}

		state.page = normalisePage( state.page, pages, step );
		const { first, last, both, percent } = indicatorParts( state.page, pages, step );

		// The progress line has no visible text: the position is its accessible value.
		els.bar.setAttribute( 'aria-valuetext', positionText( words, first, both ? last : first, pages ) );
		els.bar.firstElementChild.style.width = `${ percent }%`;
		els.bar.setAttribute( 'aria-valuenow', String( Math.round( percent ) ) );
		els.indicator.hidden = false;
		els.footer.hidden = false;

		// Rebuilding the pager removes the button that was just clicked; without this the focus falls to
		// the page and the arrow keys stop reaching the dialog. Put it back on the same control.
		const focused = els.pager.contains( document.activeElement ) ? document.activeElement : null;
		const focusKey = focused ? [ 'data-turn', 'data-page' ].map( ( name ) => [ name, focused.getAttribute( name ) ] ).find( ( pair ) => pair[ 1 ] !== null ) : null;

		els.pager.replaceChildren();

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
				const attrs = { 'data-page': String( item ), 'aria-label': `${ words.one } ${ item }` };
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

		if ( focusKey ) {
			const again = els.pager.querySelector( `[${ focusKey[ 0 ] }="${ focusKey[ 1 ] }"]` );
			if ( again ) {
				again.focus();
			}
		}
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

	/** Page changes made inside the book (drag, corner click) come back here. */
	const syncFromViewer = ( page ) => {
		const next = normalisePage( page, state.pages, state.step );
		if ( next !== state.page ) {
			state.page = next;
			drawFooter();
			emit();
		}
	};

	/** The viewer knows the real page count and orientation once it has the pages. */
	const setGeometry = ( { pages, step } ) => {
		state.pages = pages;
		state.step = step;
		state.page = pages > 0 ? normalisePage( state.page, pages, step ) : 1;
		drawFooter();
		emit();
		return state.page;
	};

	units = readJson( 'data-units' );
	words = wording( 'page', units );

	const viewer = createViewer( {
		stage: els.stage,
		status: els.status,
		units,
		pdfjs: readJson( 'data-pdfjs' ),
		limits: mergeLimits( readJson( 'data-limits' ) ),
		selectableText: root.getAttribute( 'data-selectable-text' ) === '1',
		onGeometry: setGeometry,
		onFlip: syncFromViewer,
	} );

	/** Goes to a page; `animate` flips the book one view, otherwise it jumps. */
	const setPage = ( page, animate = false ) => {
		state.page = normalisePage( page, state.pages, state.step );
		drawFooter();
		emit();
		viewer.goTo( state.page, { animate } );
	};

	/** Back (-1) or Next (1), ignored while a page is still turning or at the ends. */
	const turn = ( direction ) => {
		if ( viewer.isTurning() ) {
			return;
		}
		const target = turnPage( state.page, state.pages, state.step, direction );
		if ( target !== state.page ) {
			setPage( target, true );
		}
	};

	const open = ( id, trigger ) => {
		const doc = documents[ id ];
		if ( ! doc ) {
			return;
		}
		opener = trigger;
		state.id = doc.id;
		words = wording( unitOf( doc ), units );
		els.prev.setAttribute( 'aria-label', words.prev );
		els.next.setAttribute( 'aria-label', words.next );
		els.pager.setAttribute( 'aria-label', words.nav );
		// The stored images carry the real count and shape; otherwise guess portrait
		// until the PDF is opened (the viewer reports the truth through setGeometry()).
		const stored = Array.isArray( doc.images ) ? doc.images.length : 0;
		state.pages = stored || doc.pages;
		// Slides are wide: the stored ratio says so, and so does a deck the browser will draw itself.
		state.step = stepFor( state.pages, ( stored && doc.ratio > 1 ) || viewerMode( doc ) === 'native' ? 'landscape' : 'portrait' );
		state.page = 1;

		drawHeader( doc );
		drawFooter();
		document.documentElement.style.overflow = 'hidden';
		dialog.showModal();
		els.close.focus();
		emit();
		viewer.show( doc, state.page );
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
		viewer.destroy();
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
				turn( arrow === els.next ? 1 : -1 );
			}
		} )
	);

	// Left and Right turn the page, unless the key belongs to a form control.
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
			return;
		}
		if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
			return;
		}
		const target = event.target;
		if ( target && target.closest && target.closest( 'input, textarea, select, [contenteditable], .documents-modal__grid' ) ) {
			// Form controls and the spreadsheet grid (arrow keys move cells there) keep their own arrow keys.
			return;
		}
		event.preventDefault();
		turn( event.key === 'ArrowRight' ? 1 : -1 );
	} );

	els.pager.addEventListener( 'click', ( event ) => {
		const turnButton = event.target.closest( '[data-turn]' );
		const num = event.target.closest( '[data-page]' );
		if ( turnButton ) {
			if ( turnButton.getAttribute( 'aria-disabled' ) !== 'true' ) {
				turn( Number( turnButton.dataset.turn ) );
			}
		} else if ( num && ! viewer.isTurning() ) {
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
