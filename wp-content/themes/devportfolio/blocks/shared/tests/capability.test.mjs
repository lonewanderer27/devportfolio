import test from 'node:test';
import assert from 'node:assert/strict';
import {
	DEFAULT_LIMITS,
	FAILURE_KEY,
	mergeLimits,
	parseBrowser,
	clientRenderTier,
	budgetFor,
	isMobileClass,
	readFailureFlag,
	markFailure,
	clearFailure,
} from '../capability.js';

const ALL_FEATURES = {
	dynamicImport: true,
	moduleWorker: true,
	canvas2d: true,
	readableStream: true,
	structuredClone: true,
	fetch: true,
	abortController: true,
	resizeObserver: true,
	promiseWithResolvers: true,
	abortSignalAny: true,
};

/** A capable desktop Chrome; tests override one thing at a time. */
const desktop = ( over = {} ) => ( {
	features: { ...ALL_FEATURES },
	browser: { name: 'chrome', version: 130 },
	connection: { saveData: false, effectiveType: '4g' },
	deviceMemory: 8,
	hardwareConcurrency: 8,
	coarsePointer: false,
	touch: false,
	viewportWidth: 1440,
	failedBefore: false,
	...over,
} );

const phone = ( over = {} ) => desktop( { coarsePointer: true, touch: true, viewportWidth: 820, deviceMemory: 4, hardwareConcurrency: 6, ...over } );

const tier = ( env, doc = {}, limits = DEFAULT_LIMITS ) => clientRenderTier( env, limits, doc );

test( 'a capable desktop gets the full tier', () => {
	assert.deepEqual( tier( desktop() ), { tier: 'full', reason: 'ok' } );
} );

test( 'a capable phone-class device gets the light tier', () => {
	assert.deepEqual( tier( phone() ), { tier: 'light', reason: 'mobile-class' } );
} );

test( 'touch on a narrow viewport is mobile-class, touch on a wide one is not', () => {
	assert.equal( isMobileClass( desktop( { touch: true, viewportWidth: 800 } ) ), true );
	assert.equal( isMobileClass( desktop( { touch: true, viewportWidth: 1280 } ) ), false );
	assert.equal( isMobileClass( desktop( { coarsePointer: true, viewportWidth: 1920 } ) ), true );
	assert.equal( isMobileClass( desktop( { touch: false, viewportWidth: 600 } ) ), false );
} );

test( 'each missing feature refuses and names the feature', () => {
	for ( const feature of Object.keys( ALL_FEATURES ) ) {
		const env = desktop( { features: { ...ALL_FEATURES, [ feature ]: false } } );
		assert.deepEqual( tier( env ), { tier: 'refused', reason: `missing-feature:${ feature }` }, feature );
	}
} );

test( 'a missing feature map refuses', () => {
	assert.equal( tier( desktop( { features: undefined } ) ).tier, 'refused' );
} );

test( 'version floors: just below refuses, at the floor allows', () => {
	const { minVersions } = DEFAULT_LIMITS;
	for ( const name of Object.keys( minVersions ) ) {
		const floor = minVersions[ name ];
		assert.equal( tier( desktop( { browser: { name, version: floor } } ) ).tier, 'full', `${ name } at floor` );
		const below = tier( desktop( { browser: { name, version: floor - 0.1 } } ) );
		assert.equal( below.tier, 'refused', `${ name } below floor` );
		assert.match( below.reason, /^browser-too-old:/ );
	}
} );

test( 'pdf.js 4.10 needs Chrome 119, Firefox 124 and Safari 17.4', () => {
	assert.deepEqual( DEFAULT_LIMITS.minVersions, { chrome: 119, edge: 119, firefox: 124, safari: 17.4 } );
} );

test( 'an unknown browser is allowed when the features are there', () => {
	assert.equal( tier( desktop( { browser: null } ) ).tier, 'full' );
	assert.equal( tier( desktop( { browser: { name: 'opera', version: 1 } } ) ).tier, 'full' );
} );

test( 'data saver and slow connections refuse; a missing connection API does not', () => {
	assert.deepEqual( tier( desktop( { connection: { saveData: true, effectiveType: '4g' } } ) ), { tier: 'refused', reason: 'save-data' } );
	for ( const effectiveType of [ 'slow-2g', '2g', '3g' ] ) {
		assert.deepEqual( tier( desktop( { connection: { saveData: false, effectiveType } } ) ), { tier: 'refused', reason: 'slow-network' } );
	}
	assert.equal( tier( desktop( { connection: { saveData: false, effectiveType: '4g' } } ) ).tier, 'full' );
	assert.equal( tier( desktop( { connection: null } ) ).tier, 'full' );
} );

test( 'device memory: 2 GB refuses, 4 GB allows, unknown allows', () => {
	assert.deepEqual( tier( desktop( { deviceMemory: 2 } ) ), { tier: 'refused', reason: 'low-memory' } );
	assert.equal( tier( desktop( { deviceMemory: 0.5 } ) ).tier, 'refused' );
	assert.equal( tier( desktop( { deviceMemory: 4 } ) ).tier, 'full' );
	assert.equal( tier( desktop( { deviceMemory: null } ) ).tier, 'full' );
} );

test( 'document size is judged against the device class', () => {
	const mb = 1024 * 1024;
	assert.equal( tier( phone(), { pdfBytes: 26 * mb } ).reason, 'pdf-too-large' );
	assert.equal( tier( phone(), { pdfBytes: 24 * mb } ).tier, 'light' );
	assert.equal( tier( desktop(), { pdfBytes: 26 * mb } ).tier, 'full' );
	assert.equal( tier( desktop(), { pdfBytes: 81 * mb } ).reason, 'pdf-too-large' );
	assert.equal( tier( phone(), { pages: 61 } ).reason, 'too-many-pages' );
	assert.equal( tier( phone(), { pages: 60 } ).tier, 'light' );
	assert.equal( tier( desktop(), { pages: 301 } ).reason, 'too-many-pages' );
	assert.equal( tier( desktop(), { pages: 300 } ).tier, 'full' );
} );

test( 'unknown document size is not a reason to refuse', () => {
	assert.equal( tier( phone(), { pdfBytes: 0, pages: 0 } ).tier, 'light' );
	assert.equal( tier( phone(), {} ).tier, 'light' );
} );

test( 'a browser that failed before is refused', () => {
	assert.deepEqual( tier( desktop( { failedBefore: true } ) ), { tier: 'refused', reason: 'failed-before' } );
} );

test( 'a weak cpu caps a desktop at the light tier', () => {
	assert.deepEqual( tier( desktop( { hardwareConcurrency: 2 } ) ), { tier: 'light', reason: 'low-cpu' } );
	assert.equal( tier( desktop( { hardwareConcurrency: 4 } ) ).tier, 'full' );
	assert.equal( tier( desktop( { hardwareConcurrency: null } ) ).tier, 'full' );
} );

test( 'the first matching rule wins and its reason is reported', () => {
	const env = phone( {
		features: { ...ALL_FEATURES, abortSignalAny: false },
		browser: { name: 'chrome', version: 90 },
		connection: { saveData: true, effectiveType: '2g' },
		deviceMemory: 1,
		failedBefore: true,
	} );
	assert.equal( tier( env, { pages: 999 } ).reason, 'missing-feature:abortSignalAny' );
	env.features = { ...ALL_FEATURES };
	assert.match( tier( env ).reason, /^browser-too-old/ );
	env.browser = { name: 'chrome', version: 130 };
	assert.equal( tier( env ).reason, 'save-data' );
	env.connection = { saveData: false, effectiveType: '4g' };
	assert.equal( tier( env ).reason, 'low-memory' );
	env.deviceMemory = 8;
	assert.equal( tier( env, { pages: 999 } ).reason, 'too-many-pages' );
	assert.equal( tier( env ).reason, 'failed-before' );
} );

test( 'limits can be overridden, and bad overrides are ignored', () => {
	const limits = mergeLimits( { maxPages: { mobile: 10 }, minDeviceMemoryGB: 4, slowConnections: [ '2g' ], bogus: 1 } );
	assert.equal( limits.maxPages.mobile, 10 );
	assert.equal( limits.maxPages.desktop, 300, 'untouched keys keep their default' );
	assert.equal( limits.minDeviceMemoryGB, 4 );
	assert.deepEqual( limits.slowConnections, [ '2g' ] );
	assert.equal( 'bogus' in limits, false );
	assert.equal( clientRenderTier( phone( { deviceMemory: 8 } ), limits, { pages: 11 } ).reason, 'too-many-pages' );
	assert.equal( clientRenderTier( desktop( { deviceMemory: 4 } ), limits ).reason, 'low-memory' );

	const bad = mergeLimits( { maxPages: 'many', minDeviceMemoryGB: 'lots', budgets: null } );
	assert.deepEqual( bad, DEFAULT_LIMITS );
	assert.deepEqual( mergeLimits( null ), DEFAULT_LIMITS );
	assert.deepEqual( mergeLimits( [] ), DEFAULT_LIMITS );
} );

test( 'tier budgets', () => {
	assert.deepEqual( budgetFor( 'full' ), { dprCap: 2, ahead: 2, maxCanvases: 12, maxPixels: 0 } );
	assert.deepEqual( budgetFor( 'light' ), { dprCap: 1.5, ahead: 1, maxCanvases: 6, maxPixels: 8000000 } );
	// A two-page view's window never needs more canvases than the budget allows.
	for ( const name of [ 'full', 'light' ] ) {
		const b = budgetFor( name );
		assert.ok( ( 1 + 2 * b.ahead ) * 2 <= b.maxCanvases, name );
	}
} );

test( 'browser parsing: client hints, then the user agent', () => {
	assert.deepEqual( parseBrowser( '', [ { brand: 'Not A;Brand', version: '99' }, { brand: 'Google Chrome', version: '131' } ] ), { name: 'chrome', version: 131 } );
	assert.deepEqual( parseBrowser( '', [ { brand: 'Microsoft Edge', version: '120' } ] ), { name: 'edge', version: 120 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/118.0.0.0 Safari/537.36' ), { name: 'chrome', version: 118 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 Chrome/120.0 Safari/537.36 Edg/120.0.2210.91' ), { name: 'edge', version: 120 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0' ), { name: 'firefox', version: 124 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15' ), { name: 'safari', version: 17.4 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Mobile/15E148 Safari/604.1' ), { name: 'safari', version: 16.3 } );
	assert.deepEqual( parseBrowser( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0 Mobile/15E148 Safari/604.1' ), { name: 'chrome', version: 126 } );
	assert.equal( parseBrowser( 'curl/8' ), null );
	assert.equal( parseBrowser( undefined ), null );
} );

// --- Failure flag ----------------------------------------------------------

const fakeStorage = () => {
	const data = new Map();
	return {
		getItem: ( key ) => ( data.has( key ) ? data.get( key ) : null ),
		setItem: ( key, value ) => data.set( key, String( value ) ),
		removeItem: ( key ) => data.delete( key ),
		data,
	};
};
const DAY = 86400000;

test( 'failure flag: set, read, expire and clear', () => {
	const storage = fakeStorage();
	const now = 1_700_000_000_000;
	assert.equal( readFailureFlag( storage, now, 7 ), false );

	markFailure( storage, now );
	assert.equal( storage.data.has( FAILURE_KEY ), true );
	assert.equal( readFailureFlag( storage, now + 6 * DAY, 7 ), true );

	assert.equal( readFailureFlag( storage, now + 8 * DAY, 7 ), false, 'expired after 7 days' );
	assert.equal( storage.data.has( FAILURE_KEY ), false, 'an expired flag is removed' );

	markFailure( storage, now );
	clearFailure( storage );
	assert.equal( readFailureFlag( storage, now, 7 ), false );
} );

test( 'failure flag: garbage or a future timestamp is not a failure', () => {
	const storage = fakeStorage();
	storage.setItem( FAILURE_KEY, 'yes' );
	assert.equal( readFailureFlag( storage, 1_700_000_000_000, 7 ), false );
	storage.setItem( FAILURE_KEY, String( 1_700_000_000_000 + DAY ) );
	assert.equal( readFailureFlag( storage, 1_700_000_000_000, 7 ), false );
} );

test( 'blocked storage is tolerated', () => {
	const blocked = {
		getItem() {
			throw new Error( 'blocked' );
		},
		setItem() {
			throw new Error( 'blocked' );
		},
		removeItem() {
			throw new Error( 'blocked' );
		},
	};
	assert.equal( readFailureFlag( blocked, Date.now(), 7 ), false );
	assert.doesNotThrow( () => markFailure( blocked, Date.now() ) );
	assert.doesNotThrow( () => clearFailure( blocked ) );
	assert.equal( readFailureFlag( undefined, Date.now(), 7 ), false );
} );

// --- Teardown errors, storage access and the worker probe (review fixes) ---------------

import { isBenignRenderError, safeStorage, readEnvironment } from '../capability.js';

test( 'isBenignRenderError: our own teardown is not a failure', () => {
	assert.equal( isBenignRenderError( { name: 'AbortException' } ), true );
	assert.equal( isBenignRenderError( { name: 'AbortError' } ), true );
	assert.equal( isBenignRenderError( { name: 'RenderingCancelledException' } ), true );
	assert.equal( isBenignRenderError( new Error( 'Worker was destroyed' ) ), true );
	assert.equal( isBenignRenderError( new Error( 'Transport destroyed' ) ), true );
	assert.equal( isBenignRenderError( new Error( 'Loading aborted' ) ), true );
} );

test( 'isBenignRenderError: real failures and empty values are not benign', () => {
	assert.equal( isBenignRenderError( new Error( 'canvas unavailable' ) ), false );
	assert.equal( isBenignRenderError( { name: 'InvalidPDFException' } ), false );
	assert.equal( isBenignRenderError( { name: 'UnknownErrorException', message: 'boom' } ), false );
	assert.equal( isBenignRenderError( null ), false );
	assert.equal( isBenignRenderError( undefined ), false );
	assert.equal( isBenignRenderError( {} ), false );
} );

test( 'safeStorage: returns the storage, or null when merely reading it throws', () => {
	const storage = { getItem() {} };
	assert.equal( safeStorage( { localStorage: storage } ), storage );
	assert.equal( safeStorage( {} ), null );
	const blocked = {
		get localStorage() {
			throw new DOMException( 'denied', 'SecurityError' );
		},
	};
	assert.equal( safeStorage( blocked ), null );
} );

test( 'failure flag helpers tolerate a null storage', () => {
	assert.equal( readFailureFlag( null, Date.now(), 7 ), false );
	assert.doesNotThrow( () => markFailure( null, Date.now() ) );
	assert.doesNotThrow( () => clearFailure( null ) );
} );

/** A minimal window for readEnvironment, counting worker probes and blob URLs. */
function fakeWindow( { blockedStorage = false } = {} ) {
	const calls = { workers: 0, created: [], revoked: [] };
	const win = {
		calls,
		navigator: { userAgent: 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36' },
		document: { createElement: () => ( { getContext: () => ( {} ) } ) },
		HTMLScriptElement: { prototype: { noModule: true } },
		Blob: class {},
		URL: {
			createObjectURL() {
				const url = `blob:test/${ calls.created.length }`;
				calls.created.push( url );
				return url;
			},
			revokeObjectURL( url ) {
				calls.revoked.push( url );
			},
		},
		Worker: class {
			constructor( url, options ) {
				calls.workers++;
				void options.type;
			}
			terminate() {}
		},
		matchMedia: () => ( { matches: false } ),
		ReadableStream() {},
		structuredClone() {},
		fetch() {},
		AbortController() {},
		ResizeObserver() {},
	};
	Object.defineProperty( win, 'localStorage', {
		get() {
			if ( blockedStorage ) {
				throw new DOMException( 'denied', 'SecurityError' );
			}
			return { getItem: () => null, removeItem() {} };
		},
	} );
	return win;
}

test( 'readEnvironment: the module worker probe runs once per window and revokes its blob URL', () => {
	const win = fakeWindow();
	const first = readEnvironment( win );
	const second = readEnvironment( win );
	assert.equal( first.features.moduleWorker, true );
	assert.equal( second.features.moduleWorker, true );
	assert.equal( win.calls.workers, 1, 'the worker is spawned once, not on every open' );
	assert.deepEqual( win.calls.revoked, win.calls.created, 'every blob URL is revoked' );
} );

test( 'readEnvironment: blocked site data means no failure flag, not an exception', () => {
	const env = readEnvironment( fakeWindow( { blockedStorage: true } ) );
	assert.equal( env.failedBefore, false );
} );
