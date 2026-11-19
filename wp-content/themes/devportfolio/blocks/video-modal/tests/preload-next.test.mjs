import test from 'node:test';
import assert from 'node:assert/strict';

// Stub the DOM the module touches: each created <video> records its preload, src and load/remove calls.
const made = [];
globalThis.document = {
	createElement: () => {
		const fake = {
			preload: '',
			src: '',
			muted: false,
			removed: false,
			loads: 0,
			load() {
				this.loads += 1;
			},
			removeAttribute( name ) {
				if ( name === 'src' ) {
					this.removed = true;
					this.src = '';
				}
			},
		};
		made.push( fake );
		return fake;
	},
};

const { createNextPreloader } = await import( '../preload-next.js' );

test( 'set creates one metadata-only probe for the next file', () => {
	made.length = 0;
	const p = createNextPreloader();
	p.set( { fileUrl: 'x.mp4' } );
	assert.equal( made.length, 1 );
	assert.equal( made[ 0 ].preload, 'metadata' );
	assert.equal( made[ 0 ].src, 'x.mp4' );
	assert.equal( made[ 0 ].muted, true );
} );

test( 'a second set releases the first probe and creates another', () => {
	made.length = 0;
	const p = createNextPreloader();
	p.set( { fileUrl: 'a.mp4' } );
	p.set( { fileUrl: 'b.mp4' } );
	assert.equal( made.length, 2 );
	assert.equal( made[ 0 ].removed, true );
	assert.equal( made[ 0 ].loads, 1 );
	assert.equal( made[ 1 ].src, 'b.mp4' );
} );

test( 'no item or no file creates no probe', () => {
	made.length = 0;
	const p = createNextPreloader();
	p.set( null );
	p.set( { fileUrl: '' } );
	p.set( { embedSrc: 'https://www.youtube.com/embed/x' } );
	assert.equal( made.length, 0 );
} );

test( 'data saver creates no probe', () => {
	made.length = 0;
	Object.defineProperty( globalThis, 'navigator', { value: { connection: { saveData: true } }, configurable: true } );
	const p = createNextPreloader();
	p.set( { fileUrl: 'x.mp4' } );
	assert.equal( made.length, 0 );
	delete globalThis.navigator;
} );

test( 'release twice is safe and drops the probe', () => {
	made.length = 0;
	const p = createNextPreloader();
	p.set( { fileUrl: 'x.mp4' } );
	p.release();
	p.release();
	assert.equal( made[ 0 ].removed, true );
	assert.equal( made[ 0 ].loads, 1 );
} );
