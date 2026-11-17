/**
 * The mobile Details sheet of a video (DPV-021): a bottom sheet over the dimmed viewer with the details pane's
 * content (category and shape pills, title, description, Made with, Results) and a link to the original post.
 * The shell (dim layer, handle, drag to dismiss, Esc, history) is shared (shared/bottom-sheet.js); mobile.js
 * loads this module with import() on the first tap, through shared/sheet-controller.js. Template:
 * collateral-modal/details-sheet.js.
 *
 * `videoDetailsModel()` is pure, so the content rules run under `node --test`. A section is left out when it
 * has no data. All text is written with textContent.
 */
import { el, svgIcon } from '../shared/dom.js';
import { createBottomSheet } from '../shared/bottom-sheet.js';
import { linkFor, copyText } from '../shared/copy-link.js';

const CLOSE_ICON = 'M6 6l12 12M18 6L6 18';
const clean = ( value ) => ( typeof value === 'string' ? value.trim() : '' );

/**
 * What the sheet shows for one video (the payload item). Pure.
 *
 * @param {Object} item    title, category, shape, description, tools, stats, postUrl.
 * @param {Object} strings `data-mobile-strings`: `results`, `madeWith`, `openOriginal`.
 */
export function videoDetailsModel( item, strings = {} ) {
	const d = item && typeof item === 'object' ? item : {};
	const s = strings && typeof strings === 'object' ? strings : {};
	const postUrl = clean( d.postUrl );
	return {
		title: clean( d.title ),
		category: clean( d.category ),
		shape: clean( d.shape ),
		description: clean( d.description ),
		tools: Array.isArray( d.tools ) ? d.tools.map( clean ).filter( Boolean ) : [],
		stats: Array.isArray( d.stats )
			? d.stats.map( ( row ) => ( { label: clean( row && row.label ), value: clean( row && row.value ) } ) ).filter( ( row ) => row.label !== '' || row.value !== '' )
			: [],
		postUrl: /^https?:\/\//i.test( postUrl ) ? postUrl : '',
		resultsLabel: clean( s.results ) || 'Results',
		madeLabel: clean( s.madeWith ) || 'Made with',
		originalLabel: clean( s.openOriginal ) || 'Open original post',
		copyLabel: clean( s.copy ) || 'Copy link',
		copiedLabel: clean( s.copied ) || 'Copied',
	};
}

/**
 * Builds the sheet inside the mobile dialog (once; reopening rewrites the text only).
 *
 * @param {Object}   args
 * @param {Element}  args.dialog  The mobile dialog.
 * @param {Object}   args.strings `data-mobile-strings`.
 * @param {Function} args.onClose Called when the visitor closes the sheet (not on `close( true )`).
 * @param {string}   [args.prefix='videos-mobile']
 * @return {{open:Function,close:Function,isOpen:Function,reset:Function}}
 */
export function createDetailsSheet( { dialog, strings = {}, onClose, prefix = 'videos-mobile' } ) {
	const titleId = `${ prefix }-details-title`;
	const closeLabel = clean( strings.handle ) || 'Close details';
	const shell = createBottomSheet( {
		dialog,
		titleId,
		handleLabel: closeLabel,
		modifier: `${ prefix }__sheet--details`,
		onDismiss: () => close( false ),
		prefix,
	} );
	const { head, body } = shell;

	const title = el( 'h3', `${ prefix }__details-title`, { id: titleId } );
	const closeBtn = el( 'button', `${ prefix }__sheet-close`, { type: 'button', 'aria-label': closeLabel, 'data-no-drag': '' } );
	closeBtn.appendChild( svgIcon( CLOSE_ICON ) );
	head.append( title, closeBtn );

	/** A captioned section: `{ wrap, caption }`, the list is appended to `wrap` by the caller. */
	const group = ( name ) => {
		const wrap = el( 'section', `${ prefix }__details-${ name }` );
		const caption = el( 'h4', `${ prefix }__details-caption` );
		wrap.append( caption );
		return { wrap, caption };
	};

	const pills = el( 'p', `${ prefix }__details-pills` );
	const category = el( 'span', `${ prefix }__details-category` );
	const shape = el( 'span', `${ prefix }__details-size` );
	pills.append( category, shape );
	const description = el( 'p', `${ prefix }__details-desc` );
	const made = group( 'made' );
	const tools = el( 'ul', `${ prefix }__details-tools` );
	made.wrap.append( tools );
	const results = group( 'results' );
	const rows = el( 'dl', `${ prefix }__details-rows` );
	results.wrap.append( rows );
	const original = el( 'a', `${ prefix }__details-original`, { target: '_blank', rel: 'noopener', role: 'button' } );
	const copy = el( 'button', `${ prefix }__details-copy`, { type: 'button' } );
	// Pinned under the scrolling body: Copy link and Open original post are always in reach.
	const foot = el( 'div', `${ prefix }__sheet-foot` );
	foot.append( original, copy );
	body.append( pills, description, made.wrap, results.wrap );
	shell.sheet.append( foot );
	let current = null;
	let copyTimer = 0;

	const fill = ( item ) => {
		const model = videoDetailsModel( item, strings );
		title.textContent = model.title;
		category.textContent = model.category;
		category.hidden = model.category === '';
		shape.textContent = model.shape;
		shape.hidden = model.shape === '';
		pills.hidden = model.category === '' && model.shape === '';
		description.textContent = model.description;
		description.hidden = model.description === '';
		made.caption.textContent = model.madeLabel;
		tools.replaceChildren(
			...model.tools.map( ( name ) => {
				const li = el( 'li', `${ prefix }__details-tool` );
				li.textContent = name;
				return li;
			} )
		);
		made.wrap.hidden = model.tools.length === 0;
		results.caption.textContent = model.resultsLabel;
		rows.replaceChildren(
			...model.stats.map( ( stat ) => {
				const row = el( 'div', `${ prefix }__details-row` );
				const label = el( 'dt' );
				label.textContent = stat.label;
				const value = el( 'dd' );
				value.textContent = stat.value;
				row.append( label, value );
				return row;
			} )
		);
		results.wrap.hidden = model.stats.length === 0;
		original.textContent = model.originalLabel;
		copy.textContent = model.copyLabel;
		copy.dataset.label = model.copyLabel;
		copy.dataset.copied = model.copiedLabel;
		original.hidden = model.postUrl === '';
		if ( model.postUrl ) {
			original.href = model.postUrl;
		} else {
			original.removeAttribute( 'href' );
		}
		body.scrollTop = 0;
	};

	const close = ( silent = false ) => {
		if ( ! shell.isOpen() ) {
			return;
		}
		shell.hide( silent );
		if ( ! silent && onClose ) {
			onClose();
		}
	};
	closeBtn.addEventListener( 'click', () => close( false ) );

	/** Copies this video's link (the same link as the viewer's history entry); the label says so for two seconds. */
	copy.addEventListener( 'click', async () => {
		if ( ! current ) {
			return;
		}
		const link = linkFor( window.location, current.slug, 'video_slug' );
		const result = await copyText( link, {
			clipboard: navigator.clipboard,
			execCopy: ( text ) => {
				// Inside the dialog: content outside a modal dialog cannot be selected for the copy.
				const field = el( 'textarea', `${ prefix }__copyfield`, { readonly: '', 'aria-hidden': 'true' } );
				field.value = text;
				dialog.append( field );
				field.select();
				const ok = document.execCommand( 'copy' );
				field.remove();
				return ok;
			},
			select: () => {},
		} );
		if ( result === 'copied' ) {
			copy.textContent = copy.dataset.copied;
			window.clearTimeout( copyTimer );
			copyTimer = window.setTimeout( () => {
				copy.textContent = copy.dataset.label;
			}, 2000 );
		}
	} );

	return {
		/** @param {Object} args { doc (the payload item), opener } */
		open( { doc, opener } ) {
			if ( ! doc ) {
				return false;
			}
			current = doc;
			fill( doc );
			shell.show( { opener, focus: closeBtn } );
			return true;
		},
		close,
		isOpen: () => shell.isOpen(),
		/** Another video opens, or the viewer closes. */
		reset() {
			close( true );
		},
	};
}
