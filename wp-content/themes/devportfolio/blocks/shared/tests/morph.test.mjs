import test from 'node:test';
import assert from 'node:assert/strict';
import { canMorph, morph, morphEnv, isMorphing, settleMorph, MORPH_NAME } from '../morph.js';

const el = ( name = '' ) => ( { style: { viewTransitionName: name } } );

function fakeDoc( { supported = true } = {} ) {
	const classes = new Set();
	const doc = {
		documentElement: { classList: { add: ( c ) => classes.add( c ), remove: ( c ) => classes.delete( c ) } },
		classes,
		skipped: 0,
	};
	if ( supported ) {
		doc.startViewTransition = ( callback ) => {
			callback();
			return { finished: new Promise( () => {} ), skipTransition: () => { doc.skipped += 1; } };
		};
	}
	return doc;
}

const win = ( reduced = false ) => ( { matchMedia: () => ( { matches: reduced } ) } );

test( 'canMorph needs support and no reduced motion', () => {
	assert.equal( canMorph( { supported: true, reducedMotion: false } ), true );
	assert.equal( canMorph( { supported: false, reducedMotion: false } ), false );
	assert.equal( canMorph( { supported: true, reducedMotion: true } ), false );
} );

test( 'morphEnv reads the document and the media query', () => {
	assert.deepEqual( morphEnv( fakeDoc(), win( true ) ), { supported: true, reducedMotion: true } );
	assert.deepEqual( morphEnv( fakeDoc( { supported: false } ), win() ), { supported: false, reducedMotion: false } );
} );

test( 'unsupported or reduced motion: update runs once, nothing is named', () => {
	for ( const [ doc, w ] of [ [ fakeDoc( { supported: false } ), win() ], [ fakeDoc(), win( true ) ] ] ) {
		const from = el();
		let calls = 0;
		const result = morph( { from, update: () => { calls += 1; }, doc, win: w } );
		assert.equal( calls, 1 );
		assert.equal( result.animated, false );
		assert.equal( from.style.viewTransitionName, '' );
		assert.equal( isMorphing(), false );
	}
} );

test( 'names: from is cleared before update, to is named after it', () => {
	const doc = fakeDoc();
	const from = el( 'videos-item-7' );
	const to = el();
	let fromDuring = null;
	let toDuring = null;
	const result = morph( {
		from,
		to,
		doc,
		win: win(),
		update: () => {
			fromDuring = from.style.viewTransitionName;
			toDuring = to.style.viewTransitionName;
		},
	} );
	assert.equal( result.animated, true );
	assert.equal( fromDuring, 'videos-item-7' ); // restored to its own name before the new state
	assert.equal( toDuring, '' );
	assert.equal( to.style.viewTransitionName, MORPH_NAME );
	assert.equal( doc.classes.has( 'is-morphing' ), true );
	assert.equal( isMorphing(), true );
	settleMorph();
	assert.equal( to.style.viewTransitionName, '' );
	assert.equal( doc.classes.has( 'is-morphing' ), false );
	assert.equal( isMorphing(), false );
} );

test( 'one element can be both from and to (the outline)', () => {
	const doc = fakeDoc();
	const outline = el();
	morph( { from: outline, to: outline, name: 'videos-outline', className: 'is-morphing-outline', doc, win: win(), update() {} } );
	assert.equal( outline.style.viewTransitionName, 'videos-outline' );
	assert.equal( doc.classes.has( 'is-morphing-outline' ), true );
	settleMorph();
	assert.equal( outline.style.viewTransitionName, '' );
} );

test( 'starting a morph settles the running one', () => {
	const doc = fakeDoc();
	const a = el();
	morph( { to: a, doc, win: win(), update() {} } );
	assert.equal( a.style.viewTransitionName, MORPH_NAME );
	const b = el();
	morph( { to: b, doc, win: win(), update() {} } );
	assert.equal( a.style.viewTransitionName, '' );
	assert.equal( doc.skipped, 1 );
	assert.equal( b.style.viewTransitionName, MORPH_NAME );
	settleMorph();
} );

test( 'settleMorph is false when nothing runs', () => {
	assert.equal( settleMorph(), false );
} );

test( 'settling before the browser calls the callback still runs the change once', () => {
	const doc = fakeDoc();
	// The browser holds the callback back (the transition has not started its update yet).
	let pendingCallback = null;
	doc.startViewTransition = ( callback ) => {
		pendingCallback = callback;
		return { finished: new Promise( () => {} ), ready: Promise.reject( new Error( 'skipped' ) ), skipTransition: () => { doc.skipped += 1; } };
	};
	const to = el();
	let calls = 0;
	morph( { to, doc, win: win(), update: () => { calls += 1; } } );
	assert.equal( calls, 0 );
	assert.equal( settleMorph(), true );
	assert.equal( calls, 1, 'the end state is reached when the morph is settled' );
	assert.equal( to.style.viewTransitionName, '' );
	pendingCallback(); // a late callback from the browser must not run the change again
	assert.equal( calls, 1 );
} );
