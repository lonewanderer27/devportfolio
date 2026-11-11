/**
 * The desktop footer and page state of a book modal (DPD-013, DPD-014, DPC-013): the pager (Back, page
 * numbers, Next), the progress line, the page arrows and the keys. One implementation for the Documents
 * modal and the Collaterals modal; the class prefix, the event name and the words are arguments.
 *
 * The state `{ id, page, step, pages }` lives here. The viewer never owns the current page: `setGeometry()`
 * is the viewer's `onGeometry` (it returns the page to show), `syncFromViewer()` its `onFlip`, and `turn()`
 * its `onSwipe`. The viewer is attached after creation (`attach( viewer )`), because it needs these callbacks.
 */
import { normalisePage, pagerItems, visiblePages, pagerState, turnPage, indicatorParts } from './pager.js';
import { positionText, wording } from './mode.js';
import { shouldShowTapHint } from './gestures.js';

/**
 * The footer as data, so it can be tested without a DOM (pure). `pages < 2` hides the footer, the pager, the
 * progress line and the arrows. Otherwise: the arrows' disabled state, the progress line's percent and
 * `aria-valuetext`, and one item per pager control (`nav` for Back/Next, `gap` for the ellipsis, `page` for a
 * page number, `on` when that page is shown in the current view).
 */
export function footerModel( state, words ) {
	const { pages, step } = state;
	const page = normalisePage( state.page, pages, step );
	const arrows = pagerState( page, pages, step );
	const model = {
		hidden: pages < 2,
		prev: { hidden: pages < 2, disabled: arrows.backOff },
		next: { hidden: pages < 2, disabled: arrows.nextOff },
		page,
		bar: null,
		items: [],
	};
	if ( model.hidden ) {
		return model;
	}
	const { first, last, both, percent } = indicatorParts( page, pages, step );
	model.bar = {
		percent,
		valuenow: String( Math.round( percent ) ),
		valuetext: positionText( words, first, both ? last : first, pages ),
	};
	const visible = visiblePages( page, pages, step );
	model.items = pagerItems( pages, page, step ).map( ( item ) => {
		if ( item === '…' ) {
			return { type: 'gap' };
		}
		return { type: 'page', page: item, on: visible.includes( item ), label: `${ words.one } ${ item }` };
	} );
	return model;
}

/**
 * @param {object}   options
 * @param {object}   options.els        { footer, pager, indicator, bar, prev, next } elements of the modal.
 * @param {string}   options.prefix     Class prefix ('documents-modal' or 'collaterals-modal').
 * @param {EventTarget} options.target  Where `eventName` is dispatched (the dialog).
 * @param {string}   options.eventName  e.g. 'documents:modal-change'; detail is a copy of the state.
 * @param {string}   [options.ignore]   Extra selector: a key event inside it keeps its own arrow keys.
 * @param {Element}  [options.els.tap]  The "Tap to Flip" hint (DPC-012), shown by the rules of shouldShowTapHint().
 */
export function createPagerController( { els, prefix, target, eventName, ignore = '' } ) {
	const state = { id: 0, page: 1, step: 1, pages: 0 };
	let words = wording( 'page', {} );
	let viewer = null;
	// Tap to Flip (DPC-012, D7): the hint stays until the first flip, tap or swipe of this opening.
	let flipped = false;
	const coarse = () => typeof window.matchMedia === 'function' && window.matchMedia( '(pointer: coarse)' ).matches;

	const emit = () => target.dispatchEvent( new CustomEvent( eventName, { detail: { ...state } } ) );

	const makeButton = ( className, text, attrs ) => {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = className;
		button.textContent = text;
		Object.entries( attrs ).forEach( ( [ key, value ] ) => button.setAttribute( key, value ) );
		return button;
	};

	const drawFooter = () => {
		const model = footerModel( state, words );
		if ( els.tap ) {
			els.tap.hidden = model.hidden || ! shouldShowTapHint( {
				coarse: coarse(),
				step: state.step,
				flipped,
				zoomed: Boolean( viewer && viewer.isZoomed && viewer.isZoomed() ),
			} );
		}

		// Page arrows (DPD-014): dimmed on the first and last view; hidden with one page.
		els.prev.setAttribute( 'aria-disabled', String( model.prev.disabled ) );
		els.next.setAttribute( 'aria-disabled', String( model.next.disabled ) );
		els.prev.hidden = model.prev.hidden;
		els.next.hidden = model.next.hidden;

		// One page (or none): there is nothing to page through, so the footer is not shown at all.
		if ( model.hidden ) {
			els.footer.hidden = true;
			els.pager.hidden = true;
			els.indicator.hidden = true;
			return;
		}
		state.page = model.page;

		// The progress line has no visible text: the position is its accessible value.
		els.bar.setAttribute( 'aria-valuetext', model.bar.valuetext );
		els.bar.firstElementChild.style.width = `${ model.bar.percent }%`;
		els.bar.setAttribute( 'aria-valuenow', model.bar.valuenow );
		els.indicator.hidden = false;
		els.footer.hidden = false;

		// Rebuilding the pager removes the button that was just clicked; without this the focus falls to
		// the page and the arrow keys stop reaching the dialog. Put it back on the same control.
		const focused = els.pager.contains( document.activeElement ) ? document.activeElement : null;
		const focusKey = focused ? [ 'data-turn', 'data-page' ].map( ( name ) => [ name, focused.getAttribute( name ) ] ).find( ( pair ) => pair[ 1 ] !== null ) : null;

		els.pager.replaceChildren();

		const { backOff, nextOff } = pagerState( model.page, state.pages, state.step );
		els.pager.append(
			makeButton( `${ prefix }__pg ${ prefix }__pg--nav`, '‹ Back', {
				'data-turn': '-1',
				'aria-disabled': String( backOff ),
			} ),
			...model.items.map( ( item ) => {
				if ( item.type === 'gap' ) {
					const gap = document.createElement( 'span' );
					gap.className = `${ prefix }__gap`;
					gap.setAttribute( 'aria-hidden', 'true' );
					gap.textContent = '···';
					return gap;
				}
				const attrs = { 'data-page': String( item.page ), 'aria-label': item.label };
				if ( item.on ) {
					attrs[ 'aria-current' ] = 'page';
				}
				return makeButton(
					`${ prefix }__pg ${ prefix }__pg--num` + ( item.on ? ' is-on' : '' ),
					String( item.page ),
					attrs
				);
			} ),
			makeButton( `${ prefix }__pg ${ prefix }__pg--nav`, 'Next ›', {
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

	/** Page changes made inside the book (drag, corner click, tap) come back here. */
	const syncFromViewer = ( page ) => {
		const next = normalisePage( page, state.pages, state.step );
		flipped = true;
		if ( next !== state.page ) {
			state.page = next;
			drawFooter();
			emit();
		}
	};

	/** The viewer knows the real page count and orientation once it has the pages; returns the page to show. */
	const setGeometry = ( { pages, step } ) => {
		state.pages = pages;
		state.step = step;
		state.page = pages > 0 ? normalisePage( state.page, pages, step ) : 1;
		drawFooter();
		emit();
		return state.page;
	};

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
		flipped = true;
		const next = turnPage( state.page, state.pages, state.step, direction );
		if ( next !== state.page ) {
			setPage( next, true );
		}
	};

	/**
	 * Shows the footer for a newly opened document, before the dialog is shown (so the arrows are right on first
	 * paint). `words` names the pieces (page, slide or sheet); the arrow and pager labels follow it.
	 */
	const open = ( id, { pages, step, page = 1, words: nextWords, flipped: wasFlipped = false } ) => {
		state.id = id;
		flipped = wasFlipped;
		words = nextWords;
		els.prev.setAttribute( 'aria-label', words.prev );
		els.next.setAttribute( 'aria-label', words.next );
		els.pager.setAttribute( 'aria-label', words.nav );
		state.pages = pages;
		state.step = step;
		state.page = normalisePage( page, pages, step );
		drawFooter();
	};

	const setWords = ( next ) => {
		words = next;
	};

	const attach = ( next ) => {
		viewer = next;
	};

	[ els.prev, els.next ].forEach( ( arrow ) =>
		arrow.addEventListener( 'click', () => {
			if ( arrow.getAttribute( 'aria-disabled' ) !== 'true' ) {
				turn( arrow === els.next ? 1 : -1 );
			}
		} )
	);

	// Left and Right turn the page, unless the key belongs to a form control (or the element in `ignore`).
	target.addEventListener( 'keydown', ( event ) => {
		if ( event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
			return;
		}
		if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
			return;
		}
		const node = event.target;
		const selector = ignore ? `input, textarea, select, [contenteditable], ${ ignore }` : 'input, textarea, select, [contenteditable]';
		if ( node && node.closest && node.closest( selector ) ) {
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
		} else if ( num && viewer && ! viewer.isTurning() ) {
			setPage( Number( num.dataset.page ) );
		}
	} );

	/** A single tap on the book (touch, two-page view): flips one view, as the Next and Back controls do. */
	const tap = ( direction ) => {
		flipped = true;
		turn( direction );
	};

	return { state, drawFooter, syncFromViewer, setGeometry, setPage, turn, tap, open, setWords, attach, emit };
}
