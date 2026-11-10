/**
 * The mobile Details sheet of a collateral (DPC-018): a bottom sheet over the dimmed viewer with the sidebar's
 * content (category and size pills, title, description, Made with, Results) and a full-width Download with a
 * Copy link button under it. The shell (dim layer, handle, drag to dismiss, Esc, history) is shared with the
 * Documents sheets (shared/bottom-sheet.js); mobile.js loads this module with import() on the first tap of Details
 * (through shared/sheet-controller.js), so it is registered by name like the Pages sheet.
 *
 * The first half is pure (`collateralDetailsModel()`), so the content rules run under `node --test`. A section is
 * left out when it has no data (no empty pill, no empty list, no empty Results).
 */
import { el, svgIcon } from '../shared/dom.js';
import { createBottomSheet } from '../shared/bottom-sheet.js';
import { applyDownload, guardDisabledDownload } from '../shared/download.js';
import { linkFor, copyText } from './copy-link.js';

const clean = ( value ) => ( typeof value === 'string' ? value.trim() : '' );

/**
 * What the sheet shows for one collateral (the payload item). Pure.
 *
 * @param {Object} item    The payload item (title, category, pill, description, tools, stats, slug, downloadMode, downloadUrl).
 * @param {Object} strings `data-mobile-strings`: `results` and `madeWith` are the section captions.
 * @return {{title:string,category:string,pill:string,description:string,tools:string[],stats:Array<{label:string,value:string}>,resultsLabel:string,madeLabel:string,sections:{pills:boolean,description:boolean,tools:boolean,stats:boolean}}}
 */
export function collateralDetailsModel( item, strings = {} ) {
	const d = item && typeof item === 'object' ? item : {};
	const s = strings && typeof strings === 'object' ? strings : {};
	const tools = Array.isArray( d.tools ) ? d.tools.map( clean ).filter( Boolean ) : [];
	const stats = Array.isArray( d.stats )
		? d.stats.map( ( row ) => ( { label: clean( row && row.label ), value: clean( row && row.value ) } ) ).filter( ( row ) => row.label !== '' || row.value !== '' )
		: [];
	const model = {
		title: clean( d.title ),
		category: clean( d.category ),
		pill: clean( d.pill ),
		description: clean( d.description ),
		tools,
		stats,
		resultsLabel: clean( s.results ) || 'Results',
		madeLabel: clean( s.madeWith ) || 'Made with',
	};
	model.sections = {
		pills: model.category !== '' || model.pill !== '',
		description: model.description !== '',
		tools: tools.length > 0,
		stats: stats.length > 0,
	};
	return model;
}

const DOWNLOAD_ICON = 'M12 4v11M7 11l5 5 5-5M5 20h14';
const CLOSE_ICON = 'M6 6l12 12M18 6L6 18';

/**
 * Builds the sheet inside the mobile dialog (once; reopening rewrites the text only).
 *
 * @param {Object}   args
 * @param {Element}  args.dialog  The mobile dialog.
 * @param {Object}   args.strings `data-mobile-strings`.
 * @param {Function} args.onClose Called when the visitor closes the sheet (not on `close( true )`).
 * @param {string}   [args.prefix='collaterals-mobile'] The mobile class prefix.
 * @return {{open:Function,close:Function,isOpen:Function,reset:Function}}
 */
export function createDetailsSheet( { dialog, strings = {}, onClose, prefix = 'collaterals-mobile' } ) {
	const text = ( key, fallback ) => ( typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback );
	const titleId = `${ prefix }-details-title`;

	const shell = createBottomSheet( {
		dialog,
		titleId,
		handleLabel: text( 'closeDetails', 'Close details' ),
		modifier: `${ prefix }__sheet--details`,
		onDismiss: () => close( false ),
		prefix,
	} );
	const { sheet, head, body } = shell;

	const title = el( 'h3', `${ prefix }__details-title`, { id: titleId } );
	const closeBtn = el( 'button', `${ prefix }__sheet-close`, { type: 'button', 'aria-label': text( 'closeDetails', 'Close details' ), 'data-no-drag': '' } );
	closeBtn.appendChild( svgIcon( CLOSE_ICON ) );
	head.append( title, closeBtn );

	// Two groups, as the Documents sheet: held sideways the text scrolls on the left, the rows on the right (CSS only).
	const main = el( 'div', `${ prefix }__details-main` );
	const pills = el( 'p', `${ prefix }__details-pills` );
	const category = el( 'span', `${ prefix }__details-category` );
	const size = el( 'span', `${ prefix }__details-size` );
	pills.append( category, size );
	const description = el( 'p', `${ prefix }__details-desc` );
	const made = el( 'section', `${ prefix }__details-made` );
	const madeCaption = el( 'h4', `${ prefix }__details-caption` );
	const tools = el( 'ul', `${ prefix }__details-tools` );
	made.append( madeCaption, tools );
	main.append( pills, description, made );

	const results = el( 'section', `${ prefix }__details-results` );
	const resultsCaption = el( 'h4', `${ prefix }__details-caption` );
	const rows = el( 'dl', `${ prefix }__details-rows` );
	results.append( resultsCaption, rows );
	body.append( main, results );

	const foot = el( 'div', `${ prefix }__sheet-foot` );
	const download = el( 'a', `${ prefix }__details-download`, { role: 'button' } );
	download.appendChild( svgIcon( DOWNLOAD_ICON ) );
	const downloadText = el( 'span' );
	downloadText.textContent = text( 'download', 'Download' );
	download.appendChild( downloadText );
	const copy = el( 'button', `${ prefix }__details-copy`, { type: 'button' } );
	const field = el( 'input', `${ prefix }__details-field`, { type: 'text', readonly: '', tabindex: '-1', 'aria-label': text( 'copyLink', 'Copy link' ) } );
	field.hidden = true;
	const live = el( 'span', `${ prefix }__details-live`, { role: 'status', 'aria-live': 'polite' } );
	foot.append( download, copy, field, live );
	sheet.appendChild( foot );
	guardDisabledDownload( download );

	const copyLabel = text( 'copyLink', 'Copy link' );
	let current = null;
	let copyTimer = 0;

	const fill = ( item ) => {
		const model = collateralDetailsModel( item, strings );
		current = item;
		title.textContent = model.title;
		category.textContent = model.category;
		category.hidden = model.category === '';
		size.textContent = model.pill;
		size.hidden = model.pill === '';
		pills.hidden = ! model.sections.pills;
		description.textContent = model.description;
		description.hidden = ! model.sections.description;
		madeCaption.textContent = model.madeLabel;
		tools.replaceChildren(
			...model.tools.map( ( name ) => {
				const item = el( 'li', `${ prefix }__details-tool` );
				item.textContent = name;
				return item;
			} )
		);
		made.hidden = ! model.sections.tools;
		resultsCaption.textContent = model.resultsLabel;
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
		results.hidden = ! model.sections.stats;
		applyDownload( download, item );
		copy.textContent = copyLabel;
		field.hidden = true;
		live.textContent = '';
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

	// Copy link: the same rule as the sidebar (copy-link.js), with the same fallbacks.
	copy.addEventListener( 'click', async () => {
		if ( ! current ) {
			return;
		}
		const link = linkFor( window.location, current.slug );
		const result = await copyText( link, {
			clipboard: navigator.clipboard,
			execCopy: ( value ) => {
				field.hidden = false;
				field.value = value;
				field.select();
				const ok = document.execCommand( 'copy' );
				field.hidden = true;
				return ok;
			},
			select: () => {
				field.hidden = false;
				field.value = link;
				field.focus();
				field.select();
				copy.textContent = text( 'copyHint', copyLabel );
				live.textContent = text( 'copyHint', '' );
			},
		} );
		if ( result === 'copied' ) {
			copy.textContent = text( 'copied', copyLabel );
			live.textContent = text( 'copied', '' );
			window.clearTimeout( copyTimer );
			copyTimer = window.setTimeout( () => {
				copy.textContent = copyLabel;
				live.textContent = '';
			}, 2000 );
		}
	} );

	return {
		/** @param {Object} args { doc (the payload item), opener } */
		open( { doc, opener } ) {
			if ( ! doc ) {
				return false;
			}
			fill( doc );
			shell.show( { opener, focus: closeBtn } );
			return true;
		},
		close,
		isOpen: () => shell.isOpen(),
		/** Another collateral opens, or the viewer closes. */
		reset() {
			close( true );
		},
	};
}
