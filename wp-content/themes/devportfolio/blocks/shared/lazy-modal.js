/**
 * Loads a modal's viewer on first use (DPB-NNN F1).
 *
 * Each modal block's boot.js calls createLazyModal(). Nothing heavy is fetched while the page loads: the modal's
 * view.js (and its whole import graph) is imported when a deeplink is in the URL, on the first sign of intent
 * (pointer over, focus or touch on a trigger), on a click, or, on the pages whose main content opens it (the
 * shelf and the grids), when the browser is idle after `load`. Its stylesheets are printed as
 * `<link rel="preload" as="style">` (see devportfolio_defer_viewer_style()), so they never block the first paint;
 * the view is imported only once they apply, so a dialog never opens unstyled.
 *
 * A click that arrives before the view is loaded is handed to modal-trigger.js (queueOpen) and replayed by the
 * view's own bindOpen(), so the dialog, the deeplink push and the focus return behave as for any later click.
 */
import { historyEvents } from './history-events.js';
import { createDeeplink } from './deeplink.js';
import { queueOpen } from './modal-trigger.js';

export const STYLE_TIMEOUT_MS = 8000;
export const IDLE_TIMEOUT_MS = 4000;
export const INTENT_EVENTS = [ 'pointerover', 'focusin', 'touchstart' ];

/**
 * Resolves when the stylesheet `link` applies (or failed, or after the timeout). A missing link (the style was
 * printed inline, or is not on this page) resolves at once. A `preload` link is turned into a stylesheet here, so
 * the wait never depends on the inline onload.
 *
 * @param {HTMLLinkElement|null} link     The `<link>` element.
 * @param {Function}             setTimer setTimeout.
 * @return {Promise<void>}
 */
export function styleReady( link, setTimer = setTimeout ) {
	return new Promise( ( resolve ) => {
		if ( ! link || ( link.rel === 'stylesheet' && link.sheet ) ) {
			resolve();
			return;
		}
		const onLoad = () => {
			if ( link.rel === 'stylesheet' && link.sheet ) {
				link.removeEventListener( 'load', onLoad );
				resolve();
			}
		};
		link.addEventListener( 'load', onLoad );
		link.addEventListener( 'error', () => resolve(), { once: true } );
		setTimer( resolve, STYLE_TIMEOUT_MS );
		if ( link.rel === 'preload' ) {
			link.rel = 'stylesheet';
		}
	} );
}

/**
 * @param {Object}   config
 * @param {string}   config.root      Selector of the modal block's root (nothing happens without it).
 * @param {string}   config.attribute Trigger attribute, e.g. 'data-document-id'.
 * @param {string}   config.param     Deeplink query parameter, e.g. 'document_slug'.
 * @param {string[]} config.styles    Ids of the `<link>` elements the view needs (`<handle>-css`).
 * @param {string}   config.idle      Selector that, when present, makes the view load when the browser is idle.
 * @param {Function} config.load      `() => import( './view.js' )`.
 * @param {Object}   env              Injected for tests: { win, doc, events }.
 * @return {{load: Function, isLoaded: Function}|null}
 */
export function createLazyModal( config, env = {} ) {
	const win = env.win || window;
	const doc = env.doc || document;
	const events = env.events || historyEvents;
	const { root, attribute, param, styles, idle, load } = config;

	events.install();
	if ( ! doc.querySelector( root ) ) {
		return null;
	}

	const deeplink = createDeeplink( param );
	const triggerOf = ( event ) => ( event.target && event.target.closest ? event.target.closest( `[${ attribute }]` ) : null );
	let loading = null;
	let loaded = false;

	const run = () => {
		if ( ! loading ) {
			loading = Promise.all( styles.map( ( id ) => styleReady( doc.getElementById( id ), win.setTimeout ? win.setTimeout.bind( win ) : setTimeout ) ) )
				.then( () => load() )
				.then( () => {
					loaded = true;
				} )
				.catch( ( error ) => {
					// A failed fetch (offline): the next intent or click tries again.
					loading = null;
					throw error;
				} );
		}
		return loading;
	};

	const onIntent = ( event ) => {
		if ( ! loaded && triggerOf( event ) ) {
			run().catch( () => {} );
		}
	};
	INTENT_EVENTS.forEach( ( type ) => doc.addEventListener( type, onIntent, { passive: true } ) );

	doc.addEventListener( 'click', ( event ) => {
		if ( loaded ) {
			return;
		}
		const trigger = triggerOf( event );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		queueOpen( attribute, trigger );
		trigger.setAttribute( 'aria-busy', 'true' );
		run()
			.catch( () => {} )
			.then( () => trigger.removeAttribute( 'aria-busy' ) );
	} );

	// Back or Forward onto this modal's deeplink before the view is loaded: keep it from the router and load; the
	// view opens the slug in the URL when it runs. Once loaded, the view's own handler (added later) decides.
	events.addEventListener(
		'popstate',
		( event ) => {
			if ( ! loaded && deeplink.slugFromUrl( win.location.href ) ) {
				event.stopImmediatePropagation();
				run().catch( () => {} );
			}
		},
		true
	);

	if ( deeplink.slugFromUrl( win.location.href ) ) {
		run().catch( () => {} );
	} else if ( idle && doc.querySelector( idle ) && ! ( win.navigator && win.navigator.connection && win.navigator.connection.saveData ) ) {
		const whenIdle = () => {
			if ( win.requestIdleCallback ) {
				win.requestIdleCallback( () => run().catch( () => {} ), { timeout: IDLE_TIMEOUT_MS } );
			} else {
				win.setTimeout( () => run().catch( () => {} ), 1 );
			}
		};
		if ( doc.readyState === 'complete' ) {
			whenIdle();
		} else {
			win.addEventListener( 'load', whenIdle, { once: true } );
		}
	}

	return { load: run, isLoaded: () => loaded };
}
