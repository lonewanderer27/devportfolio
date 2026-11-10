/**
 * The mobile Details sheet (DPD-023): a bottom sheet over the dimmed viewer with what the desktop header shows,
 * plus the category, the format and the page count, and a full-width Download button. It replaces the small
 * popover the round Info button used to open.
 *
 * The first half is pure (`detailsModel()`), so the content rules run under `node --test`. The second half
 * builds the DOM once on the shared shell (bottom-sheet.js) and only rewrites its text when it is reopened.
 * mobile.js loads this module with `import()` on the first tap of Details (through sheet-controller.js).
 */
import { el, svgIcon } from '../shared/dom.js';
import { createBottomSheet } from '../shared/bottom-sheet.js';
import { applyDownload, guardDisabledDownload } from '../shared/download.js';
import { unitOf, wording } from '../shared/mode.js';

const clean = ( value ) => ( typeof value === 'string' ? value.trim() : '' );

/**
 * What the sheet shows for a document. Anything missing is left out (no empty pill, no empty row).
 *
 * @param {Object} args { doc, strings, units }
 * @return {{title:string,category:string,description:string,tools:string[],rows:Array<{key:string,label:string,value:string}>}}
 */
export function detailsModel( { doc, strings = {}, units = {} } ) {
	const d = doc && typeof doc === 'object' ? doc : {};
	const formatLabel = typeof strings.formatRow === 'string' && strings.formatRow !== '' ? strings.formatRow : 'Format';
	const rows = [];
	const fileType = clean( d.fileType );
	if ( fileType ) {
		rows.push( { key: 'format', label: formatLabel, value: fileType } );
	}
	const pages = Math.floor( Number( d.pages ) );
	if ( pages > 0 ) {
		// "Pages", or "Slides" for a presentation: the same wording as the cover and the modal pill.
		rows.push( { key: 'pages', label: wording( unitOf( d ), units ).many, value: String( pages ) } );
	}
	return {
		title: clean( d.title ),
		category: clean( d.category ),
		description: clean( d.description ),
		tools: Array.isArray( d.tools ) ? d.tools.map( clean ).filter( Boolean ) : [],
		rows,
	};
}

const DOWNLOAD_ICON = 'M12 4v11M7 11l5 5 5-5M5 20h14';
const CLOSE_ICON = 'M6 6l12 12M18 6L6 18';

/**
 * Builds the sheet inside the mobile dialog.
 *
 * @param {Object}   args
 * @param {Element}  args.dialog  The mobile dialog.
 * @param {Object}   args.strings `data-mobile-strings`.
 * @param {Object}   args.units   `data-units`.
 * @param {Function} args.onClose Called when the visitor closes the sheet (not on `close( true )`).
 * @return {{open:Function,close:Function,isOpen:Function,reset:Function}}
 */
export function createDetailsSheet( { dialog, strings = {}, units = {}, onClose } ) {
	const text = ( key, fallback ) => ( typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback );
	const titleId = 'documents-mobile-details-title';

	const shell = createBottomSheet( {
		dialog,
		titleId,
		handleLabel: text( 'closeDetails', 'Close details' ),
		modifier: 'documents-mobile__sheet--details',
		onDismiss: () => close( false ),
	} );
	const { sheet, head, body } = shell;

	const title = el( 'h3', 'documents-mobile__details-title', { id: titleId } );
	const closeBtn = el( 'button', 'documents-mobile__sheet-close', { type: 'button', 'aria-label': text( 'closeDetails', 'Close details' ), 'data-no-drag': '' } );
	closeBtn.appendChild( svgIcon( CLOSE_ICON ) );
	head.append( title, closeBtn );

	// The content is in two groups so that, held sideways, the text scrolls on the left while the rows and the
	// Download button stay on the right (CSS only: the markup is the same in both orientations).
	const main = el( 'div', 'documents-mobile__details-main' );
	const category = el( 'span', 'documents-mobile__details-category' );
	const description = el( 'p', 'documents-mobile__details-desc' );
	const tools = el( 'ul', 'documents-mobile__details-tools' );
	main.append( category, description, tools );
	const rows = el( 'dl', 'documents-mobile__details-rows' );
	body.append( main, rows );

	const foot = el( 'div', 'documents-mobile__sheet-foot' );
	const download = el( 'a', 'documents-mobile__details-download', { role: 'button' } );
	download.appendChild( svgIcon( DOWNLOAD_ICON ) );
	const downloadText = el( 'span' );
	downloadText.textContent = text( 'download', 'Download' );
	download.appendChild( downloadText );
	foot.appendChild( download );
	sheet.appendChild( foot );
	guardDisabledDownload( download );

	const fill = ( doc ) => {
		const model = detailsModel( { doc, strings, units } );
		title.textContent = model.title;
		category.textContent = model.category;
		category.hidden = model.category === '';
		description.textContent = model.description;
		description.hidden = model.description === '';
		tools.replaceChildren(
			...model.tools.map( ( name ) => {
				const item = el( 'li', 'documents-mobile__details-tool' );
				item.textContent = name;
				return item;
			} )
		);
		tools.hidden = model.tools.length === 0;
		rows.replaceChildren(
			...model.rows.map( ( row ) => {
				const wrap = el( 'div', 'documents-mobile__details-row', { 'data-row': row.key } );
				const label = el( 'dt' );
				label.textContent = row.label;
				const value = el( 'dd' );
				value.textContent = row.value;
				wrap.append( label, value );
				return wrap;
			} )
		);
		rows.hidden = model.rows.length === 0;
		applyDownload( download, doc );
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

	return {
		/**
		 * @param {Object} args { doc, opener }
		 */
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
		/** Another document opens, or the viewer closes. */
		reset() {
			close( true );
		},
	};
}
