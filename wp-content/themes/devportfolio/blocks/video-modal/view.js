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
import { neighbours } from './neighbours.js';
import { createNextPreloader } from './preload-next.js';
import { mountControls, DESKTOP_CONTROLS } from './controls/index.js';
import { createMobileVideo } from './mobile.js';
import { frameOf, flyTo, flightDuration, FLIGHT_EASE } from '../shared/flight.js';

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
	const preloadNext = createNextPreloader();
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
		preloadNext.set( items[ neighbours( order, items, currentId, activeFilter() ).next ] );
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

	// The visible thumbnail of a video, or null (hidden by the filter, or not in the grid).
	const tileFor = ( id ) => {
		const button = document.querySelector( `.videos-item__open[data-video-id="${ id }"]` );
		const tile = button ? button.closest( '.videos-item' ) : null;
		return tile && ! tile.hidden ? tile : null;
	};

	let fadingOut = false;
	// The desktop picture's flight, { anim, side, closing, end }: settling it lands the dialog at its end state.
	let flight = null;
	const pane = dialog.querySelector( '.videos-modal__video' );
	const side = dialog.querySelector( '.videos-modal__side' );
	const reducedMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	const clearPane = () => {
		pane.style.removeProperty( 'width' );
		pane.style.removeProperty( 'height' );
	};
	const endOpen = () => {
		dialog.classList.remove( 'is-morphing-modal' );
		clearPane();
	};
	const endClose = () => {
		dialog.classList.remove( 'is-morphing-modal', 'is-leaving' );
		clearPane();
		dialog.close();
	};

	/** Jumps a running flight to its end state. @return {boolean} True when there was one. */
	const settleFlight = () => {
		if ( ! flight ) {
			return false;
		}
		const { anim, side: sideAnim, end } = flight;
		flight = null;
		anim.cancel();
		if ( sideAnim ) {
			sideAnim.cancel();
		}
		end();
		return true;
	};

	/**
	 * The pane grows out of its tile (opening) or shrinks back into it (closing), with the sidebar fading beside it
	 * and the backdrop fading by CSS. Everything runs in the dialog's own layer, so it stays above the grid.
	 */
	const flyPane = ( tile, closing ) => {
		settleFlight();
		// Before measuring: `is-flown` keeps the dialog's own entrance from playing (now, and again when the flight
		// ends) until it closes, so the pane is measured at its real place.
		dialog.classList.add( 'is-flown', 'is-morphing-modal' );
		const place = pane.getBoundingClientRect();
		const tileRect = tile.getBoundingClientRect();
		const duration = flightDuration();
		pane.style.width = `${ place.width }px`;
		pane.style.height = `${ place.height }px`;
		if ( closing ) {
			dialog.classList.add( 'is-leaving' );
		}
		const anim = flyTo(
			pane,
			closing ? frameOf( place, place, 'start' ) : frameOf( tileRect, place, 'start' ),
			closing ? frameOf( tileRect, place, 'start' ) : frameOf( place, place, 'start' ),
			{
				duration,
				radius: [ '0px', '0px' ],
				onDone: () => {
					if ( flight && flight.anim === anim ) {
						const { end } = flight;
						flight = null;
						end();
					}
				},
			}
		);
		const sideAnim = side
			? side.animate( [ { opacity: closing ? 1 : 0 }, { opacity: closing ? 0 : 1 } ], { duration, easing: FLIGHT_EASE } )
			: null;
		flight = { anim, side: sideAnim, closing, end: closing ? endClose : endOpen };
	};

	/** Closes the desktop dialog: the pane flies back into its tile, else a quick fade, else at once. */
	const closeDesktop = () => {
		if ( ! dialog.open || fadingOut || ( flight && flight.closing ) ) {
			return;
		}
		if ( reducedMotion() ) {
			settleFlight();
			dialog.close();
			return;
		}
		const tile = tileFor( currentId );
		if ( tile ) {
			flyPane( tile, true );
			return;
		}
		settleFlight();
		fadingOut = true;
		dialog.classList.add( 'is-closing' );
		window.setTimeout( () => {
			fadingOut = false;
			dialog.classList.remove( 'is-closing' );
			if ( dialog.open ) {
				dialog.close();
			}
		}, 160 );
	};

	/** Fills and shows the dialog this screen gets: desktop (side by side) or mobile (full screen). */
	const open = ( item, gesture ) => {
		if ( routing.isDesktop() ) {
			const reveal = () => {
				fill( item, gesture );
				dialog.showModal();
				els.close.focus();
			};
			// A click grows its thumbnail into the pane; a deeplink or Back/Forward has no thumbnail to grow from.
			const tile = gesture && opener ? opener.closest( '.videos-item' ) : null;
			if ( tile && ! reducedMotion() ) {
				reveal();
				flyPane( tile, false );
			} else {
				reveal();
			}
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
			closeDesktop();
		}
	} );
	els.close.addEventListener( 'click', closeDesktop );
	// ← and → open the previous and next video of the active filter. The seek and volume sliders keep the arrows.
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) || event.target.closest( 'input, textarea, select' ) || fadingOut || flight ) {
			return;
		}
		const near = neighbours( order, items, currentId, activeFilter() );
		const id = event.key === 'ArrowLeft' ? near.prev : near.next;
		if ( id !== null ) {
			event.preventDefault();
			swapTo( id );
		}
	} );
	// Esc: a flight still running jumps to its end and the dialog closes at once; otherwise it closes animated.
	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		if ( flight ) {
			settleFlight();
			dialog.close();
		} else {
			closeDesktop();
		}
	} );
	// A new press while a flight runs jumps it to the end (nothing is left half-way).
	window.addEventListener( 'pointerdown', () => settleFlight(), true );
	dialog.addEventListener( 'close', () => {
		preloadNext.release();
		dialog.classList.remove( 'is-flown' );
		player.unload();
		finishClose();
	} );
	mobileDialog.addEventListener( 'close', () => {
		preloadNext.release();
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
				shownDialog() === dialog ? closeDesktop() : mobileDialog.close();
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
		settleFlight();
		const shown = shownDialog();
		if ( ! shown || ( shown === dialog ) === routing.isDesktop() ) {
			return;
		}
		swapping = true;
		shown.close();
		open( items[ currentId ], false );
	} );
}
