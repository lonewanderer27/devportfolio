/**
 * The mobile Pages sheet (DPD-020): a bottom sheet over the dimmed viewer with a Gallery view (3 columns of
 * page thumbnails) and a List view (one row per page).
 *
 * The first half of this file is pure (no DOM access), so it runs under `node --test`: names expanded from
 * ranges, thumbnail URLs from a descriptor, the current pages, which thumbnails are due and how many tiles
 * are built. The second half builds the DOM. mobile.js loads this module with `import()` on the first tap of
 * Pages, builds the sheet once per document and only moves the `current` markers when it is reopened.
 *
 * Thumbnails (made by the plugin, DPB-031) are fetched only when a tile is visible (plus about one row
 * ahead), never in the List view, never with data saver on, and never for the pages above the one the sheet
 * opens at.
 */
import { el, svgIcon } from './dom.js';
import { createBottomSheet } from './bottom-sheet.js';
import { visiblePages } from './pager.js';

/** From this many pages the tiles and rows are built in chunks as the sheet scrolls. */
export const WINDOW_FROM = 150;
/** Tiles or rows added per chunk. */
export const CHUNK = 60;
/** Columns of the gallery. */
export const COLUMNS = 3;
/** Rows of thumbnails fetched beyond the visible ones. */
export const ROWS_AHEAD = 1;

const PAD = 3;

/**
 * One name per page from the payload's `pageNames`: ranges `[ [ first_page, title ], ... ]` (a name holds from
 * its first page until the next range; pages before the first range have none) or already a plain list.
 * Always an array of `pages` strings.
 *
 * @param {Array}  names Ranges or a list of strings.
 * @param {number} pages Page count.
 * @return {string[]}
 */
export function expandNames( names, pages ) {
	const count = Math.max( 0, Math.floor( Number( pages ) ) || 0 );
	const out = new Array( count ).fill( '' );
	if ( ! Array.isArray( names ) || count === 0 ) {
		return out;
	}
	if ( names.every( ( item ) => typeof item === 'string' ) ) {
		names.slice( 0, count ).forEach( ( name, i ) => {
			out[ i ] = name.trim();
		} );
		return out;
	}
	const ranges = names
		.filter( ( item ) => Array.isArray( item ) && Number.isFinite( Number( item[ 0 ] ) ) && typeof item[ 1 ] === 'string' )
		.map( ( item ) => [ Math.floor( Number( item[ 0 ] ) ), item[ 1 ].trim() ] )
		.filter( ( item ) => item[ 0 ] >= 1 && item[ 0 ] <= count )
		.sort( ( a, b ) => a[ 0 ] - b[ 0 ] );
	ranges.forEach( ( [ first, title ], i ) => {
		const last = i + 1 < ranges.length ? ranges[ i + 1 ][ 0 ] - 1 : count;
		for ( let page = first; page <= last; page++ ) {
			out[ page - 1 ] = title;
		}
	} );
	return out;
}

/**
 * One thumbnail URL per page, or an empty list when there are none (or not for every page, so a half set is
 * never used). `thumbs` is the payload descriptor `{ base, ext, count }` (page n is `base + 't-' + pad( n ) + ext`)
 * or a plain list of URLs.
 *
 * @param {Object|Array} thumbs Descriptor or list.
 * @param {number}       pages  Page count.
 * @return {string[]}
 */
export function thumbUrls( thumbs, pages ) {
	const count = Math.max( 0, Math.floor( Number( pages ) ) || 0 );
	if ( count === 0 || ! thumbs ) {
		return [];
	}
	if ( Array.isArray( thumbs ) ) {
		return thumbs.length === count && thumbs.every( ( url ) => typeof url === 'string' && url !== '' ) ? thumbs.slice() : [];
	}
	if ( typeof thumbs === 'object' && typeof thumbs.base === 'string' && thumbs.base !== '' && Number( thumbs.count ) === count ) {
		const ext = typeof thumbs.ext === 'string' ? thumbs.ext : '.webp';
		return Array.from( { length: count }, ( _, i ) => `${ thumbs.base }t-${ String( i + 1 ).padStart( PAD, '0' ) }${ ext }` );
	}
	return [];
}

/** `slide` for presentations, `sheet` for workbooks, `page` for everything else (the wording of a row without a name). */
export function unitKind( doc ) {
	const kind = doc && doc.kind;
	if ( kind === 'pptx' || kind === 'ppt' || kind === 'odp' ) {
		return 'slide';
	}
	if ( kind === 'xlsx' || kind === 'xls' || kind === 'ods' ) {
		return 'sheet';
	}
	return 'page';
}

const DEFAULTS = { page: 'Page {n}', slide: 'Slide {n}', sheet: 'Sheet {n}' };

/** "Page 3" / "Slide 3" / "Sheet 3" from the strings, with the English defaults. */
export function pieceName( strings, unit, n ) {
	const key = `${ unit }Label`;
	const template = strings && typeof strings[ key ] === 'string' && strings[ key ].includes( '{n}' ) ? strings[ key ] : DEFAULTS[ unit ] || DEFAULTS.page;
	return template.replace( '{n}', String( n ) );
}

/** The unit word without a number ("Page"): what a row without a name shows, so the number is never repeated as text. */
export function unitWord( strings, unit ) {
	return pieceName( strings, unit, '' ).trim();
}

/**
 * The model of the sheet: one entry per page.
 *
 * `name` is what the list shows (the stored name, else the unit word); `label` is the accessible name
 * ("Page 3, Hotels" or "Page 3"); `current` is true for every page on screen (both pages of a spread).
 *
 * @param {Object} args { pages, step, page, names, thumbs, unit, strings }
 * @return {Array<{n:number,name:string,named:boolean,current:boolean,label:string,thumb:string}>}
 */
export function pagesModel( { pages, step = 1, page = 1, names = [], thumbs = null, unit = 'page', strings = {} } ) {
	const count = Math.max( 0, Math.floor( Number( pages ) ) || 0 );
	if ( count === 0 ) {
		return [];
	}
	const shown = new Set( visiblePages( page, count, step === 2 ? 2 : 1 ) );
	const list = expandNames( names, count );
	const urls = thumbUrls( thumbs, count );
	const word = unitWord( strings, unit );
	return list.map( ( title, i ) => {
		const n = i + 1;
		const piece = pieceName( strings, unit, n );
		return {
			n,
			name: title || word,
			named: title !== '',
			current: shown.has( n ),
			label: title ? `${ piece }, ${ title }` : piece,
			thumb: urls[ i ] || '',
		};
	} );
}

/**
 * Which tiles are due now: the visible ones (`first` to `last`, page numbers) widened to whole rows plus
 * `rowsAhead` rows, clipped to the document. Nothing in the List view or with data saver on.
 *
 * @param {Object} args { view, first, last, columns, rowsAhead, total, saveData }
 * @return {number[]}
 */
export function visibleTiles( { view = 'grid', first = 1, last = 1, columns = COLUMNS, rowsAhead = ROWS_AHEAD, total = 0, saveData = false } ) {
	if ( view !== 'grid' || saveData || total < 1 || last < first ) {
		return [];
	}
	const cols = Math.max( 1, columns );
	const startRow = Math.floor( ( Math.max( 1, first ) - 1 ) / cols );
	const endRow = Math.floor( ( Math.min( total, last ) - 1 ) / cols ) + Math.max( 0, rowsAhead );
	const from = startRow * cols + 1;
	const to = Math.min( total, ( endRow + 1 ) * cols );
	return Array.from( { length: Math.max( 0, to - from + 1 ) }, ( _, i ) => from + i );
}

/**
 * How many tiles (or rows) to build. Short documents build everything; long ones build chunks of `CHUNK`,
 * enough to reach page `needed` (the page the sheet opens at) and never fewer than `built` (what is already
 * there).
 */
export function renderCount( total, needed = 1, built = 0 ) {
	if ( total <= WINDOW_FROM ) {
		return total;
	}
	return Math.min( total, Math.max( built, Math.ceil( Math.max( 1, needed ) / CHUNK ) * CHUNK ) );
}

/**
 * What the sheet remembers about each document while the page stays open: the view the visitor last used and
 * where each view was scrolled to, so reopening the sheet picks up where they left. Kept in memory only (it
 * lives as long as the sheet does), never stored.
 *
 * @return {{save:Function,viewFor:Function,scrollFor:Function,builtFor:Function,has:Function,forget:Function}}
 */
export function createSheetMemory() {
	const map = new Map();
	const entry = ( id ) => {
		if ( ! map.has( id ) ) {
			map.set( id, { view: 'grid', scroll: {}, built: { grid: 0, list: 0 } } );
		}
		return map.get( id );
	};
	const top = ( value ) => ( Number.isFinite( Number( value ) ) && Number( value ) > 0 ? Math.round( Number( value ) ) : 0 );
	return {
		/** Remembers the view in use, its scroll position and how many tiles or rows were built. */
		save( id, { view, scrollTop = 0, built = 0 } ) {
			const kind = view === 'list' ? 'list' : 'grid';
			const e = entry( id );
			e.view = kind;
			e.scroll[ kind ] = top( scrollTop );
			e.built[ kind ] = Math.max( e.built[ kind ], Math.max( 0, Math.floor( Number( built ) ) || 0 ) );
		},
		/** The view last used for a document: Gallery when it has none. */
		viewFor( id ) {
			return map.has( id ) ? map.get( id ).view : 'grid';
		},
		/** The last scroll position of a view, or null when that view was never scrolled (centre on the current page then). */
		scrollFor( id, view ) {
			const e = map.get( id );
			return e && typeof e.scroll[ view ] === 'number' ? e.scroll[ view ] : null;
		},
		/** How many tiles or rows were built, so a long document can be rebuilt as far as the visitor had scrolled. */
		builtFor( id, view ) {
			const e = map.get( id );
			return e ? e.built[ view === 'list' ? 'list' : 'grid' ] : 0;
		},
		has: ( id ) => map.has( id ),
		forget( id ) {
			map.delete( id );
		},
	};
}

/** Whether the visitor asked for less data. */
const saveDataOn = () => !! ( typeof navigator !== 'undefined' && navigator.connection && navigator.connection.saveData );

/**
 * Builds the sheet inside the mobile dialog.
 *
 * @param {Object}   args
 * @param {Element}  args.dialog   The mobile dialog.
 * @param {Object}   args.strings  `data-mobile-strings`.
 * @param {Function} args.onSelect Called with the chosen page number.
 * @param {Function} args.onClose  Called when the visitor closes the sheet (not on `close( true )`).
 * @return {{open:Function,close:Function,isOpen:Function,reset:Function}}
 */
export function createPagesSheet( { dialog, strings = {}, onSelect, onClose, prefix = 'documents-mobile' } ) {
	const text = ( key, fallback ) => ( typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback );
	// The shell (dim layer, sheet, handle, drag to dismiss, inert viewer, Esc) is shared with the Details sheet.
	const shell = createBottomSheet( {
		dialog,
		titleId: `${ prefix }-pages-title`,
		handleLabel: text( 'closePages', 'Close pages' ),
		modifier: `${ prefix }__sheet--pages`,
		onDismiss: () => close( false ),
		prefix,
	} );
	const { sheet, head, body } = shell;

	const title = el( 'h3', `${ prefix }__sheet-title`, { id: `${ prefix }-pages-title` } );
	title.textContent = text( 'pagesTitle', 'Pages' );
	const toggle = el( 'div', `${ prefix }__sheet-toggle`, { role: 'group', 'aria-label': text( 'pagesLabel', 'Pages' ) } );
	const galleryBtn = el( 'button', `${ prefix }__sheet-view`, { type: 'button', 'aria-pressed': 'true', 'aria-label': text( 'galleryView', 'Gallery view' ), 'data-view': 'grid', 'data-no-drag': '' } );
	galleryBtn.appendChild( svgIcon( 'M3.5 5.5h17v13h-17z', 'M8 10.5a1.3 1.3 0 1 0 0-.01', 'M4 17l5-5 4 4 3-3 4 4' ) );
	const listBtn = el( 'button', `${ prefix }__sheet-view`, { type: 'button', 'aria-pressed': 'false', 'aria-label': text( 'listView', 'List view' ), 'data-view': 'list', 'data-no-drag': '' } );
	listBtn.appendChild( svgIcon( 'M9 6h11M9 12h11M9 18h11', 'M4.5 6h.01M4.5 12h.01M4.5 18h.01' ) );
	toggle.append( galleryBtn, listBtn );
	head.append( title, toggle );

	const grid = el( 'div', `${ prefix }__pgr` );
	const list = el( 'div', `${ prefix }__rows` );
	list.hidden = true;
	body.append( grid, list );

	const IO = typeof IntersectionObserver === 'function' ? IntersectionObserver : null;
	const memory = createSheetMemory();
	const state = { open: false, view: 'grid', saveData: false, cache: null, imageObserver: null, growObserver: null };

	/** The built tiles and rows of one document; kept while the document stays the same. */
	const newCache = ( id, model, doc ) => ( {
		id,
		model,
		doc,
		tiles: [],
		rows: [],
		builtTiles: 0,
		builtRows: 0,
		sentinels: [],
	} );

	const makeTile = ( entry, hasThumbs ) => {
		const tile = el( 'button', `${ prefix }__tile`, { type: 'button', 'data-page': String( entry.n ), 'aria-label': entry.label } );
		if ( ! hasThumbs ) {
			tile.classList.add( `${ prefix }__tile--blank` );
		} else {
			tile.dataset.src = entry.thumb;
			tile.dataset.state = '';
		}
		const chip = el( 'span', `${ prefix }__tile-n`, { 'aria-hidden': 'true' } );
		chip.textContent = String( entry.n );
		tile.appendChild( chip );
		return tile;
	};

	const makeRow = ( entry ) => {
		const row = el( 'button', `${ prefix }__prow`, { type: 'button', 'data-page': String( entry.n ), 'aria-label': entry.label } );
		const square = el( 'span', `${ prefix }__prow-n`, { 'aria-hidden': 'true' } );
		square.textContent = String( entry.n );
		const name = el( 'span', `${ prefix }__prow-name` );
		const label = el( 'span', `${ prefix }__prow-text` );
		label.textContent = entry.name;
		const mark = el( 'span', `${ prefix }__prow-cur` );
		mark.textContent = ` · ${ text( 'current', 'current' ) }`;
		name.append( label, mark );
		row.append( square, name );
		return row;
	};

	const applyCurrent = ( cache ) => {
		const shown = new Set( cache.model.filter( ( entry ) => entry.current ).map( ( entry ) => entry.n ) );
		const apply = ( node, n ) => {
			const on = shown.has( n );
			if ( node.classList.contains( 'is-current' ) !== on ) {
				node.classList.toggle( 'is-current', on );
			}
			if ( on ) {
				node.setAttribute( 'aria-current', 'page' );
			} else {
				node.removeAttribute( 'aria-current' );
			}
		};
		cache.tiles.forEach( ( tile, i ) => apply( tile, i + 1 ) );
		cache.rows.forEach( ( row, i ) => apply( row, i + 1 ) );
	};

	/** Adds tiles (or rows) up to `target`, with a sentinel behind them while more are to come. */
	const grow = ( cache, kind, target ) => {
		const isGrid = kind === 'grid';
		const parent = isGrid ? grid : list;
		const built = isGrid ? cache.builtTiles : cache.builtRows;
		const total = cache.model.length;
		const upTo = Math.min( total, target );
		const hasThumbs = cache.model.some( ( entry ) => entry.thumb !== '' );
		const existing = parent.querySelector( `.${ prefix }__sentinel` );
		if ( existing ) {
			existing.remove();
		}
		for ( let i = built; i < upTo; i++ ) {
			const node = isGrid ? makeTile( cache.model[ i ], hasThumbs && cache.model[ i ].thumb !== '' ) : makeRow( cache.model[ i ] );
			( isGrid ? cache.tiles : cache.rows ).push( node );
			parent.appendChild( node );
		}
		if ( isGrid ) {
			cache.builtTiles = upTo;
		} else {
			cache.builtRows = upTo;
		}
		if ( upTo < total ) {
			const sentinel = el( 'div', `${ prefix }__sentinel`, { 'aria-hidden': 'true', 'data-kind': kind } );
			parent.appendChild( sentinel );
			if ( state.growObserver ) {
				state.growObserver.observe( sentinel );
			}
		}
		applyCurrent( cache );
	};

	const loadTile = ( tile ) => {
		if ( state.saveData || tile.dataset.state !== '' || ! tile.dataset.src ) {
			return;
		}
		const cache = state.cache;
		const img = el( 'img', `${ prefix }__thumb`, { alt: '', decoding: 'async', fetchpriority: 'low', draggable: 'false' } );
		// The descriptor carries the thumbnail's size, so the image reserves its box (no layout shift).
		const size = cache && cache.doc && cache.doc.thumbs && ! Array.isArray( cache.doc.thumbs ) ? cache.doc.thumbs : {};
		if ( Number( size.width ) > 0 ) {
			img.setAttribute( 'width', String( Math.floor( size.width ) ) );
		}
		if ( Number( size.height ) > 0 ) {
			img.setAttribute( 'height', String( Math.floor( size.height ) ) );
		}
		tile.dataset.state = 'loading';
		img.addEventListener( 'load', () => {
			tile.dataset.state = 'done';
			tile.classList.add( 'is-loaded' );
		} );
		img.addEventListener( 'error', () => {
			img.remove();
			tile.dataset.state = 'failed';
			tile.classList.add( `${ prefix }__tile--blank` );
		} );
		img.src = tile.dataset.src;
		tile.prepend( img );
	};

	/** Without IntersectionObserver: the tiles whose rows are in view, by geometry. */
	const scanTiles = () => {
		const cache = state.cache;
		if ( ! cache || ! state.open || state.view !== 'grid' || cache.tiles.length === 0 ) {
			return;
		}
		const top = body.scrollTop;
		const bottom = top + body.clientHeight;
		let first = 0;
		let last = 0;
		cache.tiles.forEach( ( tile, i ) => {
			const y = tile.offsetTop;
			if ( y + tile.offsetHeight >= top && y <= bottom ) {
				first = first || i + 1;
				last = i + 1;
			}
		} );
		if ( first ) {
			visibleTiles( { view: 'grid', first, last, total: cache.model.length, saveData: state.saveData } ).forEach( ( n ) => loadTile( cache.tiles[ n - 1 ] ) );
		}
	};

	const rowPixels = () => {
		const tile = state.cache && state.cache.tiles[ 0 ];
		return tile ? Math.ceil( tile.getBoundingClientRect().height + 14 ) : 140;
	};

	const stopObserving = () => {
		if ( state.imageObserver ) {
			state.imageObserver.disconnect();
			state.imageObserver = null;
		}
		if ( state.growObserver ) {
			state.growObserver.disconnect();
			state.growObserver = null;
		}
		body.removeEventListener( 'scroll', scanTiles );
	};

	/** Cancels thumbnail requests that have not finished; those tiles can load again next time. */
	const cancelPending = () => {
		if ( ! state.cache ) {
			return;
		}
		state.cache.tiles.forEach( ( tile ) => {
			if ( tile.dataset.state === 'loading' ) {
				const img = tile.querySelector( 'img' );
				if ( img ) {
					img.removeAttribute( 'src' );
					img.remove();
				}
				tile.dataset.state = '';
			}
		} );
	};

	const startObserving = () => {
		stopObserving();
		const cache = state.cache;
		if ( ! cache ) {
			return;
		}
		if ( IO ) {
			// Growing the tiles (long documents) is needed in both views; images only in the Gallery.
			state.growObserver = new IO(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( ! entry.isIntersecting ) {
							return;
						}
						const kind = entry.target.getAttribute( 'data-kind' );
						entry.target.remove();
						grow( cache, kind, ( kind === 'grid' ? cache.builtTiles : cache.builtRows ) + CHUNK );
						startImages();
					} );
				},
				{ root: body, rootMargin: `0px 0px ${ rowPixels() }px 0px` }
			);
			body.querySelectorAll( `.${ prefix }__sentinel` ).forEach( ( node ) => state.growObserver.observe( node ) );
		}
		startImages();
	};

	const startImages = () => {
		const cache = state.cache;
		if ( ! cache || state.view !== 'grid' || state.saveData || ! cache.model.some( ( entry ) => entry.thumb !== '' ) ) {
			return;
		}
		if ( ! IO ) {
			body.addEventListener( 'scroll', scanTiles, { passive: true } );
			scanTiles();
			return;
		}
		if ( state.imageObserver ) {
			state.imageObserver.disconnect();
		}
		state.imageObserver = new IO(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						loadTile( entry.target );
						state.imageObserver.unobserve( entry.target );
					}
				} );
			},
			{ root: body, rootMargin: `0px 0px ${ rowPixels() * ROWS_AHEAD }px 0px` }
		);
		cache.tiles.forEach( ( tile ) => {
			if ( tile.dataset.state === '' && tile.dataset.src ) {
				state.imageObserver.observe( tile );
			}
		} );
	};

	const centreOnCurrent = () => {
		const cache = state.cache;
		if ( ! cache ) {
			return;
		}
		const nodes = state.view === 'grid' ? cache.tiles : cache.rows;
		const node = nodes.find( ( item ) => item.classList.contains( 'is-current' ) );
		if ( ! node ) {
			body.scrollTop = 0;
			return;
		}
		body.scrollTop = Math.max( 0, node.offsetTop - Math.max( 0, ( body.clientHeight - node.offsetHeight ) / 2 ) );
	};

	/** Writes the view and scroll position of the open sheet into the memory of its document. */
	const remember = () => {
		const cache = state.cache;
		if ( ! state.open || ! cache ) {
			return;
		}
		memory.save( cache.id, {
			view: state.view,
			scrollTop: body.scrollTop,
			built: state.view === 'grid' ? cache.builtTiles : cache.builtRows,
		} );
	};

	/**
	 * Shows a view. `top` is a remembered scroll position (pixels); without one the view is centred on the
	 * current page. Observing starts only after the position is set, so pages above it are never fetched.
	 */
	const setView = ( view, top = null ) => {
		state.view = view;
		galleryBtn.setAttribute( 'aria-pressed', String( view === 'grid' ) );
		listBtn.setAttribute( 'aria-pressed', String( view === 'list' ) );
		grid.hidden = view !== 'grid';
		list.hidden = view !== 'list';
		const cache = state.cache;
		if ( ! cache ) {
			return;
		}
		if ( view === 'list' ) {
			// The list has no images: stop and cancel any thumbnail still on its way.
			if ( state.imageObserver ) {
				state.imageObserver.disconnect();
				state.imageObserver = null;
			}
			cancelPending();
			const wanted = memory.builtFor( cache.id, 'list' );
			if ( cache.builtRows === 0 || wanted > cache.builtRows ) {
				grow( cache, 'list', Math.max( renderCount( cache.model.length, currentFirst( cache ), cache.builtRows ), wanted ) );
			}
		}
		if ( top !== null ) {
			body.scrollTop = top;
		} else {
			centreOnCurrent();
		}
		if ( view === 'grid' ) {
			// Observe only after the scroll position is set, so the pages above the current one are never fetched.
			startObserving();
		} else if ( state.growObserver ) {
			body.querySelectorAll( `.${ prefix }__sentinel` ).forEach( ( node ) => state.growObserver.observe( node ) );
		}
	};

	const currentFirst = ( cache ) => {
		const entry = cache.model.find( ( item ) => item.current );
		return entry ? entry.n : 1;
	};

	const close = ( silent = false ) => {
		if ( ! state.open ) {
			return;
		}
		remember(); // Before the sheet is hidden: a hidden scroll area forgets where it was.
		state.open = false;
		stopObserving();
		cancelPending();
		shell.hide( silent );
		if ( ! silent && onClose ) {
			onClose();
		}
	};

	toggle.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-view]' );
		if ( button && button.getAttribute( 'data-view' ) !== state.view ) {
			// Leave the view where it is, and enter the other one where it was last (or at the current page).
			remember();
			const next = button.getAttribute( 'data-view' ) === 'list' ? 'list' : 'grid';
			setView( next, state.cache ? memory.scrollFor( state.cache.id, next ) : null );
		}
	} );
	// One delegated listener for every tile and row.
	const choose = ( event ) => {
		const target = event.target.closest( '[data-page]' );
		if ( target && onSelect ) {
			onSelect( Number( target.getAttribute( 'data-page' ) ) );
		}
	};
	grid.addEventListener( 'click', choose );
	list.addEventListener( 'click', choose );
	return {
		/**
		 * Shows the sheet in the view this document was last shown in (Gallery the first time), scrolled to where it was left.
		 *
		 * @param {Object} args { doc, page, pages, step, opener }
		 */
		open( { doc, page, pages, step, opener } ) {
			const unit = unitKind( doc );
			const model = pagesModel( { pages, step, page, names: doc.pageNames, thumbs: doc.thumbs, unit, strings } );
			if ( model.length === 0 ) {
				return false;
			}
			state.saveData = saveDataOn();
			const same = state.cache && state.cache.id === doc.id && state.cache.model.length === model.length;
			if ( same ) {
				// Built once: only the current markers move.
				state.cache.model = model;
				applyCurrent( state.cache );
			} else {
				grid.replaceChildren();
				list.replaceChildren();
				state.cache = newCache( doc.id, model, doc );
			}
			const cache = state.cache;
			const ratio = Number( doc.ratio ) > 0 ? Number( doc.ratio ) : 0;
			sheet.style.setProperty( '--pg-ratio', ratio > 0 ? String( ratio ) : '0.7576' );
			sheet.style.setProperty( '--pg-crop', String( doc.cropBottom > 0 ? doc.cropBottom : 0 ) );
			// Tiles up to the page the sheet opens at (a long document is built in chunks).
			// A long document is rebuilt as far as the visitor had scrolled it last time.
			grow( cache, 'grid', Math.max( renderCount( model.length, Math.max( ...model.filter( ( entry ) => entry.current ).map( ( entry ) => entry.n ), 1 ), cache.builtTiles ), memory.builtFor( cache.id, 'grid' ) ) );
			if ( cache.builtRows > 0 ) {
				grow( cache, 'list', Math.max( cache.builtRows, renderCount( model.length, currentFirst( cache ), cache.builtRows ) ) );
			}
			state.open = true;
			shell.show( { opener } );
			// The view this document was last shown in, scrolled to where it was left (first time: Gallery at the current page).
			const view = memory.viewFor( cache.id );
			setView( view, memory.scrollFor( cache.id, view ) );
			( view === 'list' ? listBtn : galleryBtn ).focus();
			return true;
		},
		close,
		isOpen: () => state.open,
		/** Drops the built tiles (another document opens, or the viewer closes). */
		reset() {
			close( true );
			state.cache = null;
			grid.replaceChildren();
			list.replaceChildren();
		},
	};
}
