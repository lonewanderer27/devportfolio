/**
 * Client render gate for the document viewer (DPD-013).
 *
 * Rendering a PDF in the visitor's browser (pdf.js) is the heavy fallback used
 * only when there are no stored page images. This decides whether a device may
 * do it. The decision is a pure function of plain data, so it is tested with
 * `node --test`; readEnvironment() is the one function that touches the browser.
 *
 * pdf.js 4.10.38 (the build the plugin ships) uses Promise.withResolvers and
 * AbortSignal.any without polyfills, so those are required features and the
 * version floors below are the first releases that have both:
 * Chrome/Edge 119, Firefox 124, Safari 17.4.
 */

export const FAILURE_KEY = 'documents-viewer-client-failed';

export const DEFAULT_LIMITS = {
	minVersions: { chrome: 119, edge: 119, firefox: 124, safari: 17.4 },
	minDeviceMemoryGB: 2,
	slowConnections: [ 'slow-2g', '2g', '3g' ],
	maxPdfBytes: { mobile: 25 * 1024 * 1024, desktop: 80 * 1024 * 1024 },
	maxPages: { mobile: 60, desktop: 300 },
	failureTtlDays: 7,
	mobileMaxViewport: 1024,
	firstPageTimeoutMs: 15000,
	nativeTimeoutMs: 30000,
	budgets: {
		full: { dprCap: 2, ahead: 2, maxCanvases: 12, maxPixels: 0 },
		light: { dprCap: 1.5, ahead: 1, maxCanvases: 6, maxPixels: 8000000 },
	},
};

const isObject = ( value ) => value !== null && typeof value === 'object' && ! Array.isArray( value );

/** Overrides merged over the defaults, two levels deep; unknown shapes are ignored. */
export function mergeLimits( overrides, defaults = DEFAULT_LIMITS ) {
	const result = {};
	Object.keys( defaults ).forEach( ( key ) => {
		const base = defaults[ key ];
		const over = isObject( overrides ) ? overrides[ key ] : undefined;
		if ( isObject( base ) ) {
			result[ key ] = isObject( over ) ? mergeLimits( over, base ) : base;
		} else if ( Array.isArray( base ) ) {
			result[ key ] = Array.isArray( over ) ? over : base;
		} else {
			result[ key ] = typeof over === typeof base && over !== null ? over : base;
		}
	} );
	return result;
}

/** { name, version } from client hints or a user agent string, or null when unknown. */
export function parseBrowser( userAgent, brands ) {
	if ( Array.isArray( brands ) && brands.length ) {
		const known = { 'Google Chrome': 'chrome', 'Microsoft Edge': 'edge', Chromium: 'chrome' };
		for ( const brand of brands ) {
			if ( known[ brand.brand ] ) {
				return { name: known[ brand.brand ], version: Number( brand.version ) || 0 };
			}
		}
	}
	const ua = String( userAgent || '' );
	let match = ua.match( /(?:Edg|EdgA|EdgiOS)\/(\d+)/ );
	if ( match ) {
		return { name: 'edge', version: Number( match[ 1 ] ) };
	}
	match = ua.match( /(?:Firefox|FxiOS)\/(\d+)/ );
	if ( match ) {
		return { name: 'firefox', version: Number( match[ 1 ] ) };
	}
	match = ua.match( /(?:Chrome|CriOS)\/(\d+)/ );
	if ( match ) {
		return { name: 'chrome', version: Number( match[ 1 ] ) };
	}
	// Safari, and every browser on iOS, report the engine version as "Version/x.y".
	match = ua.match( /Version\/(\d+)(?:\.(\d+))?.*Safari\//i );
	if ( match ) {
		return { name: 'safari', version: Number( match[ 1 ] + '.' + ( match[ 2 ] || '0' ) ) };
	}
	// iOS WebViews: fall back to the OS version, which is the WebKit version there.
	match = ua.match( /(?:iPhone|iPad|iPod).*OS (\d+)_(\d+)/ );
	if ( match ) {
		return { name: 'safari', version: Number( match[ 1 ] + '.' + match[ 2 ] ) };
	}
	return null;
}

const REQUIRED_FEATURES = [
	'dynamicImport',
	'moduleWorker',
	'canvas2d',
	'readableStream',
	'structuredClone',
	'fetch',
	'abortController',
	'resizeObserver',
	'promiseWithResolvers',
	'abortSignalAny',
];

const workerProbes = new WeakMap();

/**
 * True when the browser reads the `type` option of the Worker constructor (module workers).
 * The probe spawns a worker, so the answer is cached per window and the blob URL is revoked.
 */
function probeModuleWorker( win ) {
	if ( workerProbes.has( win ) ) {
		return workerProbes.get( win );
	}
	let seen = false;
	let url = '';
	try {
		const options = {
			get type() {
				seen = true;
				return 'module';
			},
		};
		url = win.URL.createObjectURL( new win.Blob( [ '' ], { type: 'text/javascript' } ) );
		const probe = new win.Worker( url, options );
		probe.terminate();
	} catch ( e ) {
		// The option may have been read before the failure; keep what was seen.
	}
	try {
		if ( url ) {
			win.URL.revokeObjectURL( url );
		}
	} catch ( e ) {
		// Nothing to revoke.
	}
	workerProbes.set( win, seen );
	return seen;
}

/** window.localStorage, or null when even reading it throws (blocked site data, sandboxed frames). */
export function safeStorage( win = window ) {
	try {
		return win.localStorage || null;
	} catch ( e ) {
		return null;
	}
}

/**
 * Errors thrown because we tore the work down ourselves (a page left the render window,
 * the document was closed, pdf.js was destroyed mid-render). They are never a failure of the browser.
 */
export function isBenignRenderError( error ) {
	if ( ! error ) {
		return false;
	}
	if ( [ 'AbortException', 'AbortError', 'RenderingCancelledException' ].includes( error.name ) ) {
		return true;
	}
	return /worker was destroyed|transport destroyed|loading aborted|rendering cancelled/i.test( String( error.message || '' ) );
}

/** Reads the facts the gate needs from the browser. */
export function readEnvironment( win = window, ttlDays = DEFAULT_LIMITS.failureTtlDays ) {
	const nav = win.navigator || {};
	const doc = win.document;
	const connection = nav.connection || nav.mozConnection || nav.webkitConnection || null;

	let canvas2d = false;
	try {
		canvas2d = !! doc.createElement( 'canvas' ).getContext( '2d' );
	} catch ( e ) {
		canvas2d = false;
	}

	// A module worker is supported when the constructor reads the `type` option.
	const moduleWorker = probeModuleWorker( win );

	const matches = ( query ) => {
		try {
			return !! win.matchMedia( query ).matches;
		} catch ( e ) {
			return false;
		}
	};

	let failedBefore = false;
	try {
		failedBefore = readFailureFlag( safeStorage( win ), Date.now(), ttlDays );
	} catch ( e ) {
		failedBefore = false;
	}

	return {
		features: {
			dynamicImport: 'noModule' in ( win.HTMLScriptElement ? win.HTMLScriptElement.prototype : {} ),
			moduleWorker,
			canvas2d,
			readableStream: typeof win.ReadableStream === 'function',
			structuredClone: typeof win.structuredClone === 'function',
			fetch: typeof win.fetch === 'function',
			abortController: typeof win.AbortController === 'function',
			resizeObserver: typeof win.ResizeObserver === 'function',
			promiseWithResolvers: typeof win.Promise === 'function' && typeof win.Promise.withResolvers === 'function',
			abortSignalAny: !! win.AbortSignal && typeof win.AbortSignal.any === 'function',
		},
		browser: parseBrowser( nav.userAgent, nav.userAgentData && nav.userAgentData.brands ),
		connection: connection ? { saveData: !! connection.saveData, effectiveType: String( connection.effectiveType || '' ) } : null,
		deviceMemory: typeof nav.deviceMemory === 'number' ? nav.deviceMemory : null,
		hardwareConcurrency: typeof nav.hardwareConcurrency === 'number' ? nav.hardwareConcurrency : null,
		coarsePointer: matches( '(pointer: coarse)' ),
		touch: Number( nav.maxTouchPoints ) > 0,
		viewportWidth: Number( win.innerWidth ) || 0,
		failedBefore,
	};
}

/** True for phones and tablets: a coarse pointer, or touch on a narrow viewport. */
export function isMobileClass( env, limits = DEFAULT_LIMITS ) {
	return !! env.coarsePointer || ( !! env.touch && env.viewportWidth > 0 && env.viewportWidth < limits.mobileMaxViewport );
}

/**
 * Decides whether this device may render the PDF in the browser.
 * Returns { tier: 'full' | 'light' | 'refused', reason }; the first rule that
 * matches wins and `reason` names it.
 *
 * @param {object} env    From readEnvironment().
 * @param {object} limits Merged limits (see mergeLimits()).
 * @param {object} doc    { pdfBytes, pages } from the payload; 0 or missing = unknown.
 */
export function clientRenderTier( env, limits = DEFAULT_LIMITS, doc = {} ) {
	const refuse = ( reason ) => ( { tier: 'refused', reason } );

	for ( const feature of REQUIRED_FEATURES ) {
		if ( ! env.features || ! env.features[ feature ] ) {
			return refuse( `missing-feature:${ feature }` );
		}
	}

	if ( env.browser ) {
		const floor = limits.minVersions[ env.browser.name ];
		if ( typeof floor === 'number' && env.browser.version < floor ) {
			return refuse( `browser-too-old:${ env.browser.name }-${ env.browser.version }` );
		}
	}

	if ( env.connection ) {
		if ( env.connection.saveData ) {
			return refuse( 'save-data' );
		}
		if ( limits.slowConnections.includes( env.connection.effectiveType ) ) {
			return refuse( 'slow-network' );
		}
	}

	if ( typeof env.deviceMemory === 'number' && env.deviceMemory <= limits.minDeviceMemoryGB ) {
		return refuse( 'low-memory' );
	}

	const mobile = isMobileClass( env, limits );
	const kind = mobile ? 'mobile' : 'desktop';
	if ( doc.pdfBytes > 0 && doc.pdfBytes > limits.maxPdfBytes[ kind ] ) {
		return refuse( 'pdf-too-large' );
	}
	if ( doc.pages > 0 && doc.pages > limits.maxPages[ kind ] ) {
		return refuse( 'too-many-pages' );
	}

	if ( env.failedBefore ) {
		return refuse( 'failed-before' );
	}

	const weakCpu = typeof env.hardwareConcurrency === 'number' && env.hardwareConcurrency <= 2;
	if ( mobile || weakCpu ) {
		return { tier: 'light', reason: mobile ? 'mobile-class' : 'low-cpu' };
	}
	return { tier: 'full', reason: 'ok' };
}

/** Render budget (window, pixel density and canvas caps) for a tier. */
export function budgetFor( tier, limits = DEFAULT_LIMITS ) {
	return tier === 'light' ? limits.budgets.light : limits.budgets.full;
}

/** True while a recent failure is remembered; an expired flag is removed. */
export function readFailureFlag( storage, now, ttlDays ) {
	try {
		const raw = storage.getItem( FAILURE_KEY );
		if ( raw === null || raw === undefined ) {
			return false;
		}
		const at = Number( raw );
		if ( Number.isFinite( at ) && now - at < ttlDays * 86400000 && now >= at ) {
			return true;
		}
		storage.removeItem( FAILURE_KEY );
	} catch ( e ) {
		// Storage can be blocked; behave as if there were no flag.
	}
	return false;
}

export function markFailure( storage, now ) {
	try {
		storage.setItem( FAILURE_KEY, String( now ) );
	} catch ( e ) {
		// Ignore: without storage the flag simply does not exist.
	}
}

export function clearFailure( storage ) {
	try {
		storage.removeItem( FAILURE_KEY );
	} catch ( e ) {
		// Ignore.
	}
}
