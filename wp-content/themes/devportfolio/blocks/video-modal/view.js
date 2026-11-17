/**
 * Video modal (DPV-009 to DPV-016), desktop.
 *
 * A click on `[data-video-id]` (the grid's tile button) opens the dialog on a 701px+ screen. The sidebar is
 * filled from the JSON payload with textContent only. The deeplink is `video_slug`: a click pushes one
 * history entry, a Next up swap replaces it, closing steps back (as collateral-modal/view.js). The mobile
 * viewer is DPV-011: on a mobile screen nothing opens, and a resize to mobile closes the dialog.
 */
import { createRouting } from '../shared/routing.js';
import { bindOpen } from '../shared/modal-trigger.js';
import { createDeeplink, hasSheetState } from '../shared/deeplink.js';
import { nextItems } from '../shared/next-items.js';
import { linkFor, copyText } from '../shared/copy-link.js';

const SLUG_PARAM = 'video_slug';

const root = document.querySelector( '.videos-modal-root' );
const dialog = root ? root.querySelector( '.videos-modal' ) : null;
const dataEl = root ? root.querySelector( '.videos-modal__data' ) : null;

const readJson = ( text ) => {
	try {
		const value = JSON.parse( text );
		return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	} catch ( e ) {
		return {};
	}
};

if ( dialog && dataEl ) {
	const { order = [], items = {} } = readJson( dataEl.textContent );
	const strings = readJson( root.getAttribute( 'data-strings' ) );
	const deeplink = createDeeplink( SLUG_PARAM );
	const routing = createRouting();

	const idBySlug = {};
	order.forEach( ( id ) => {
		if ( items[ id ] && items[ id ].slug ) {
			idBySlug[ items[ id ].slug ] = id;
		}
	} );

	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		stage: $( '.videos-modal__stage' ),
		nopreview: $( '.videos-modal__nopreview' ),
		pills: $( '.videos-modal__pills' ),
		pillCat: $( '.videos-modal__pill--cat' ),
		pillSize: $( '.videos-modal__pill--size' ),
		title: $( '.videos-modal__title' ),
		desc: $( '.videos-modal__desc' ),
		results: $( '.videos-modal__results' ),
		stats: $( '.videos-modal__stats' ),
		tools: $( '.videos-modal__tools' ),
		toolList: $( '.videos-modal__toollist' ),
		nextSection: $( '.videos-modal__next' ),
		nextList: $( '.videos-modal__nextlist' ),
		original: $( '.videos-modal__original' ),
		copy: $( '.videos-modal__copy' ),
		copyField: $( '.videos-modal__copyfield' ),
		live: $( '.videos-modal__live' ),
		close: $( '.videos-modal__close' ),
	};
	const copyLabel = els.copy.textContent;

	let currentId = 0;
	let opener = null;
	let pushed = false;
	let closingFromHistory = false;
	let copyTimer = 0;

	/** Stops and unloads whatever the stage holds (DPV-009: closing stops and unloads the video). */
	const unload = () => {
		const video = els.stage.querySelector( 'video' );
		if ( video ) {
			video.pause();
			video.removeAttribute( 'src' );
			video.load();
		}
		els.stage.replaceChildren();
	};

	/** The player for one video: the uploaded file, else the embed, else the "can't be previewed" line. */
	const mountPlayer = ( item ) => {
		unload();
		els.nopreview.hidden = Boolean( item.fileUrl || item.embedSrc );
		const label = ( strings.playerFor || 'Video: %s' ).replace( '%s', item.title );
		if ( item.fileUrl ) {
			const video = document.createElement( 'video' );
			video.controls = true;
			video.playsInline = true;
			video.preload = 'metadata';
			video.setAttribute( 'aria-label', label );
			if ( item.poster ) {
				video.poster = item.poster;
			}
			video.src = item.fileUrl;
			els.stage.append( video );
		} else if ( item.embedSrc ) {
			const frame = document.createElement( 'iframe' );
			frame.title = label;
			frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
			frame.allowFullscreen = true;
			frame.referrerPolicy = 'strict-origin-when-cross-origin';
			frame.src = item.embedSrc;
			els.stage.append( frame );
		}
	};

	const activeFilter = () => {
		const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
		return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
	};

	const renderNext = () => {
		// DPV-015: three, same category first, from the active filter's list; one or two still show.
		const ids = nextItems( order, items, currentId, activeFilter(), 3, { sameCategoryFirst: true, minimum: 1 } );
		els.nextList.replaceChildren(
			...ids.map( ( id ) => {
				const item = items[ id ];
				const li = document.createElement( 'li' );
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'videos-modal__nextitem';
				button.setAttribute( 'data-video-next', String( id ) );
				button.setAttribute( 'aria-label', ( strings.open || 'Open %s' ).replace( '%s', item.title ) );
				const tile = document.createElement( 'span' );
				tile.className = 'videos-modal__nexttile';
				if ( item.poster ) {
					const img = document.createElement( 'img' );
					img.className = 'videos-modal__nextimg';
					img.src = item.poster;
					img.alt = '';
					img.loading = 'lazy';
					img.decoding = 'async';
					img.addEventListener( 'error', () => img.remove() );
					tile.append( img );
				}
				if ( item.length ) {
					const length = document.createElement( 'span' );
					length.className = 'videos-modal__nextlen';
					length.textContent = item.length;
					tile.append( length );
				}
				button.append( tile );
				li.append( button );
				return li;
			} )
		);
		els.nextSection.hidden = ids.length === 0;
	};

	const fill = ( item ) => {
		dialog.setAttribute( 'data-pane', item.pane === 'tall' ? 'tall' : 'wide' );
		els.pillCat.textContent = item.category;
		els.pillCat.hidden = ! item.category;
		els.pillSize.textContent = item.shape;
		els.pillSize.hidden = ! item.shape;
		els.pills.hidden = ! item.category && ! item.shape;
		els.title.textContent = item.title;
		els.desc.textContent = item.description;
		els.desc.hidden = ! item.description;

		els.results.hidden = ! item.stats.length;
		els.stats.replaceChildren(
			...item.stats.map( ( stat ) => {
				const row = document.createElement( 'div' );
				const value = document.createElement( 'dd' );
				value.textContent = stat.value;
				const label = document.createElement( 'dt' );
				label.textContent = stat.label;
				row.append( value, label );
				return row;
			} )
		);

		els.tools.hidden = ! item.tools.length;
		els.toolList.replaceChildren(
			...item.tools.map( ( name ) => {
				const li = document.createElement( 'li' );
				li.textContent = name;
				return li;
			} )
		);

		if ( item.postUrl ) {
			els.original.href = item.postUrl;
			els.original.hidden = false;
		} else {
			els.original.removeAttribute( 'href' );
			els.original.hidden = true;
		}
		els.copy.textContent = copyLabel;
		els.live.textContent = '';
		els.copyField.classList.remove( 'is-shown' );
		mountPlayer( item );
		renderNext();
	};

	const focusTarget = () => {
		const button = document.querySelector( `.videos-item__open[data-video-id="${ currentId }"]` );
		if ( button && ! button.closest( '[hidden]' ) ) {
			return button;
		}
		return opener && document.contains( opener ) ? opener : null;
	};

	const finishClose = () => {
		unload();
		document.documentElement.style.overflow = '';
		const target = focusTarget();
		opener = null;
		if ( closingFromHistory ) {
			closingFromHistory = false;
			pushed = false;
		} else if ( pushed ) {
			pushed = false;
			window.history.go( -1 );
		} else if ( deeplink.slugFromUrl( window.location.href ) ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		}
		if ( target ) {
			target.focus();
		}
	};

	const show = ( id, { push, trigger } ) => {
		const item = items[ id ];
		// DPV-011 will add the mobile viewer; until then a mobile screen opens nothing.
		if ( ! item || dialog.open || ! routing.isDesktop() ) {
			return;
		}
		currentId = Number( id );
		if ( push && item.slug ) {
			window.history.pushState( { videoSlug: item.slug }, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
			pushed = true;
		}
		opener = trigger;
		document.documentElement.style.overflow = 'hidden';
		fill( item );
		dialog.showModal();
		els.close.focus();
	};

	/** Next up: the video, sidebar and URL change in place, playback restarts at 0:00 (a new player is mounted). */
	const swapTo = ( id ) => {
		const item = items[ id ];
		if ( ! item ) {
			return;
		}
		currentId = Number( id );
		fill( item );
		window.history.replaceState( window.history.state, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
		els.title.focus();
	};

	bindOpen( document, 'data-video-id', ( id, trigger ) => show( Number( id ), { push: true, trigger } ) );

	// A click on the dimmed backdrop lands on the dialog itself.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	els.close.addEventListener( 'click', () => dialog.close() );
	dialog.addEventListener( 'close', finishClose );

	els.nextList.addEventListener( 'click', ( event ) => {
		const button = event.target.closest ? event.target.closest( '[data-video-next]' ) : null;
		if ( button ) {
			swapTo( Number( button.getAttribute( 'data-video-next' ) ) );
		}
	} );

	els.copy.addEventListener( 'click', async () => {
		const item = items[ currentId ];
		if ( ! item ) {
			return;
		}
		const link = linkFor( window.location, item.slug, SLUG_PARAM );
		const result = await copyText( link, {
			clipboard: navigator.clipboard,
			execCopy: ( text ) => {
				els.copyField.value = text;
				els.copyField.classList.add( 'is-shown' );
				els.copyField.select();
				const ok = document.execCommand( 'copy' );
				els.copyField.classList.remove( 'is-shown' );
				return ok;
			},
			select: () => {
				els.copyField.value = link;
				els.copyField.classList.add( 'is-shown' );
				els.copyField.focus();
				els.copyField.select();
				els.copy.textContent = strings.copyHint || copyLabel;
				els.live.textContent = strings.copyHint || '';
			},
		} );
		if ( result === 'copied' ) {
			els.copy.textContent = strings.copied || copyLabel;
			els.live.textContent = strings.copied || '';
			window.clearTimeout( copyTimer );
			copyTimer = window.setTimeout( () => {
				els.copy.textContent = copyLabel;
				els.live.textContent = '';
			}, 2000 );
		}
	} );

	// Back and Forward open or close the modal to match the URL. Capture phase at module top level and stopping
	// the event whenever it acts, so the Interactivity Router never reloads the page (see collateral-modal/view.js).
	window.addEventListener(
		'popstate',
		( event ) => {
			const slug = deeplink.slugFromUrl( window.location.href );
			if ( ! slug && dialog.open ) {
				event.stopImmediatePropagation();
				closingFromHistory = true;
				dialog.close();
			} else if ( slug && ! dialog.open && idBySlug[ slug ] ) {
				event.stopImmediatePropagation();
				pushed = true;
				show( idBySlug[ slug ], { push: false, trigger: null } );
			} else if ( slug && dialog.open && hasSheetState( event.state ) ) {
				event.stopImmediatePropagation();
			}
		},
		true
	);

	// First load with a link to a video: open it. A slug that is not a published video is dropped.
	const linked = deeplink.slugFromUrl( window.location.href );
	if ( linked ) {
		if ( ! idBySlug[ linked ] ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		} else {
			show( idBySlug[ linked ], {
				push: false,
				trigger: document.querySelector( `.videos-item__open[data-video-id="${ idBySlug[ linked ] }"]` ),
			} );
		}
	}

	// A resize to the mobile screen closes the desktop dialog (the mobile viewer, DPV-011, takes over there).
	routing.onChange( () => {
		if ( dialog.open && ! routing.isDesktop() ) {
			dialog.close();
		}
	} );
}
