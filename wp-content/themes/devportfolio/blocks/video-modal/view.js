/**
 * Video modal (DPV-009 to DPV-016, DPV-011 to DPV-023): the desktop dialog and the mobile viewer.
 *
 * A click on `[data-video-id]` (the grid's tile button) opens the desktop dialog on a 701px+ screen, or the
 * mobile viewer (mobile.js) on a screen 700px wide or less or a touch screen held sideways (routing.js). The
 * desktop sidebar is filled from the JSON payload with textContent only. The deeplink is `video_slug`: a click
 * pushes one history entry, a Next up swap replaces it, closing steps back (as collateral-modal/view.js). Crossing
 * the routing boundary with a viewer open swaps to the other dialog in place.
 */
import { createRouting } from '../shared/routing.js';
import { bindOpen } from '../shared/modal-trigger.js';
import { createDeeplink, hasSheetState } from '../shared/deeplink.js';
import { nextItems } from '../shared/next-items.js';
import { linkFor, copyText } from '../shared/copy-link.js';
import { createPlayer } from './player.js';
import { mountControls, DESKTOP_CONTROLS } from './controls/index.js';
import { createMobileVideo } from './mobile.js';

const SLUG_PARAM = 'video_slug';

const root = document.querySelector( '.videos-modal-root' );
const dialog = root ? root.querySelector( '.videos-modal' ) : null;
const dataEl = root ? root.querySelector( '.videos-modal__data' ) : null;
const mobileDialog = root ? root.querySelector( '.videos-mobile' ) : null;

const readJson = ( text ) => {
	try {
		const value = JSON.parse( text );
		return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	} catch ( e ) {
		return {};
	}
};

if ( dialog && mobileDialog && dataEl ) {
	const { order = [], items = {} } = readJson( dataEl.textContent );
	const strings = readJson( root.getAttribute( 'data-strings' ) );
	const deeplink = createDeeplink( SLUG_PARAM );
	const routing = createRouting();
	const player = createPlayer( dialog, strings );
	mountControls( { dialog, prefix: 'videos-modal', player, strings, keys: DESKTOP_CONTROLS, seek: true } );

	const idBySlug = {};
	order.forEach( ( id ) => {
		if ( items[ id ] && items[ id ].slug ) {
			idBySlug[ items[ id ].slug ] = id;
		}
	} );

	const $ = ( selector ) => dialog.querySelector( selector );
	const els = {
		stage: $( '.videos-modal__stage' ),
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
	let swapping = false;
	let skipNextPop = false;
	let copyTimer = 0;

	const activeFilter = () => {
		const pressed = document.querySelector( '.wp-block-devportfolio-document-categories [aria-pressed="true"]' );
		return pressed ? pressed.getAttribute( 'data-filter' ) || '' : '';
	};

	const mobile = createMobileVideo( {
		dialog: mobileDialog,
		order,
		items,
		strings: readJson( root.getAttribute( 'data-mobile-strings' ) ),
		options: readJson( root.getAttribute( 'data-mobile-options' ) ),
		getFilter: activeFilter,
		onGo: ( id ) => goTo( id ),
		onClose: () => mobileDialog.close(),
	} );

	const isOpen = () => dialog.open || mobileDialog.open;
	const shownDialog = () => ( dialog.open ? dialog : mobileDialog.open ? mobileDialog : null );

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

	const fill = ( item, gesture = false ) => {
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
		player.mount( item, gesture );
		renderNext();
	};

	const focusTarget = () => {
		const button = document.querySelector( `.videos-item__open[data-video-id="${ currentId }"]` );
		if ( button && ! button.closest( '[hidden]' ) ) {
			return button;
		}
		return opener && document.contains( opener ) ? opener : null;
	};

	/**
	 * A dialog closed. `extra` is the number of history entries a mobile sheet left open. Mirrors
	 * collateral-modal/view.js: a swap only reconciles, a sheet's entry goes with the viewer's.
	 */
	const finishClose = ( extra = 0 ) => {
		if ( swapping ) {
			// The other dialog is taking over; only the sheet's entry (same URL) is left to remove.
			swapping = false;
			if ( extra > 0 ) {
				skipNextPop = true;
				window.history.go( -extra );
			}
			return;
		}
		document.documentElement.style.overflow = '';
		const target = focusTarget();
		opener = null;
		if ( closingFromHistory ) {
			closingFromHistory = false;
			pushed = false;
		} else if ( pushed ) {
			pushed = false;
			window.history.go( -( 1 + extra ) );
		} else if ( extra > 0 ) {
			skipNextPop = true;
			window.history.go( -extra );
		} else if ( deeplink.slugFromUrl( window.location.href ) ) {
			window.history.replaceState( window.history.state, '', deeplink.urlWithoutSlug( window.location.href ) );
		}
		if ( target ) {
			target.focus();
		}
	};

	const replaceUrl = ( item ) => window.history.replaceState( window.history.state, '', deeplink.urlWithSlug( window.location.href, item.slug ) );

	/** Fills and shows the dialog this screen gets: desktop (side by side) or mobile (full screen). */
	const open = ( item, gesture ) => {
		if ( routing.isDesktop() ) {
			fill( item, gesture );
			dialog.showModal();
			els.close.focus();
			return;
		}
		mobile.show( item, { gesture } );
		mobileDialog.showModal();
		mobileDialog.querySelector( '.videos-mobile__close' ).focus();
	};

	const show = ( id, { push, trigger } ) => {
		const item = items[ id ];
		if ( ! item || isOpen() ) {
			return;
		}
		currentId = Number( id );
		if ( push && item.slug ) {
			window.history.pushState( { videoSlug: item.slug }, '', deeplink.urlWithSlug( window.location.href, item.slug ) );
			pushed = true;
		}
		opener = trigger;
		document.documentElement.style.overflow = 'hidden';
		open( item, push );
	};

	/** The mobile viewer's ▲ ▼ and swipes: another video, the URL replaced, sound allowed (a gesture). */
	const goTo = ( id ) => {
		const item = items[ id ];
		if ( item ) {
			currentId = Number( id );
			replaceUrl( item );
			mobile.show( item, { gesture: true } );
		}
	};

	/** Next up: the video, sidebar and URL change in place, playback restarts at 0:00 (a new player is mounted). */
	const swapTo = ( id ) => {
		const item = items[ id ];
		if ( ! item ) {
			return;
		}
		currentId = Number( id );
		fill( item, true );
		replaceUrl( item );
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
	dialog.addEventListener( 'close', () => {
		player.unload();
		finishClose();
	} );
	mobileDialog.addEventListener( 'close', () => {
		mobile.unload();
		finishClose( mobile.release() );
	} );

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
			if ( skipNextPop ) {
				skipNextPop = false;
				event.stopImmediatePropagation();
				return;
			}
			const slug = deeplink.slugFromUrl( window.location.href );
			if ( ! slug && isOpen() ) {
				event.stopImmediatePropagation();
				closingFromHistory = true;
				shownDialog().close();
			} else if ( slug && ! isOpen() && idBySlug[ slug ] ) {
				event.stopImmediatePropagation();
				pushed = true;
				show( idBySlug[ slug ], { push: false, trigger: null } );
			} else if ( slug && isOpen() && hasSheetState( event.state ) ) {
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

	// Crossing the 700px / sideways boundary with a viewer open: the other dialog takes over, same video and URL.
	routing.onChange( () => {
		const shown = shownDialog();
		if ( ! shown || ( shown === dialog ) === routing.isDesktop() ) {
			return;
		}
		swapping = true;
		shown.close();
		open( items[ currentId ], false );
	} );
}
