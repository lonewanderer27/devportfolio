import test from 'node:test';
import assert from 'node:assert/strict';

// sheet-back.js listens on window and uses history: a small stand-in is enough for the controller's own rules.
const calls = { push: 0, back: 0, listeners: [] };
globalThis.window = {
	addEventListener: ( type, fn ) => calls.listeners.push( [ type, fn ] ),
	history: {
		state: null,
		pushState: () => {
			calls.push++;
		},
		back: () => {
			calls.back++;
		},
	},
};
const { createSheetController } = await import( '../sheet-controller.js' );

/** A sheet that behaves like the real ones: close( silent ) hides it, and only a visitor close calls onClose. */
const fakeSheet = ( onClose ) => {
	let open = false;
	return {
		open: () => {
			open = true;
			return true;
		},
		close: ( silent ) => {
			if ( ! open ) {
				return;
			}
			open = false;
			if ( ! silent ) {
				onClose();
			}
		},
		isOpen: () => open,
		reset: () => {
			open = false;
		},
	};
};

const setup = () => {
	calls.push = 0;
	calls.back = 0;
	const dialog = { open: true };
	const controller = createSheetController( { dialog } );
	const made = {};
	[ 'pages', 'details' ].forEach( ( name ) => {
		controller.register( name, {
			closeOnRotate: name === 'pages',
			create: async ( { onClose } ) => {
				made[ name ] = ( made[ name ] || 0 ) + 1;
				made[ `${ name }Sheet` ] = fakeSheet( onClose );
				return made[ `${ name }Sheet` ];
			},
		} );
	} );
	return { controller, made, dialog };
};

test( 'the history listener is registered when the controller is created', () => {
	const before = calls.listeners.length;
	setup();
	assert.equal( calls.listeners.length, before + 1 );
	assert.equal( calls.listeners[ calls.listeners.length - 1 ][ 0 ], 'popstate' );
} );

test( 'a sheet is built once, on its first open', async () => {
	const { controller, made } = setup();
	assert.equal( made.pages, undefined );
	assert.equal( await controller.open( 'pages', {} ), true );
	controller.close();
	assert.equal( await controller.open( 'pages', {} ), true );
	assert.equal( made.pages, 1 );
} );

test( 'only one sheet is open at a time', async () => {
	const { controller } = setup();
	assert.equal( await controller.open( 'details', {} ), true );
	assert.equal( await controller.open( 'pages', {} ), false );
	assert.equal( controller.activeName(), 'details' );
} );

test( 'a second tap while a sheet is still loading does nothing', async () => {
	const { controller } = setup();
	const first = controller.open( 'details', {} );
	const second = controller.open( 'details', {} );
	assert.equal( await second, false );
	assert.equal( await first, true );
	assert.equal( calls.push, 1, 'One history entry.' );
} );

test( 'opening adds one history entry; a close by the controller pops it', async () => {
	const { controller } = setup();
	await controller.open( 'details', {} );
	assert.equal( calls.push, 1 );
	controller.close();
	assert.equal( calls.back, 1 );
	assert.equal( controller.isAnyOpen(), false );
} );

test( 'a close by the visitor (tap, drag, Esc) also pops the entry', async () => {
	const { controller, made } = setup();
	await controller.open( 'pages', {} );
	made.pagesSheet.close( false );
	assert.equal( calls.back, 1 );
	assert.equal( controller.isAnyOpen(), false );
} );

test( 'a close that history itself caused only releases the entry', async () => {
	const { controller } = setup();
	await controller.open( 'details', {} );
	controller.close( { fromHistory: true } );
	assert.equal( calls.back, 0, 'No second history.back().' );
	assert.equal( controller.release(), 0, 'Nothing is left behind.' );
} );

test( 'closing the viewer with a sheet open reports the leftover entry', async () => {
	const { controller } = setup();
	await controller.open( 'details', {} );
	assert.equal( controller.release(), 1 );
	controller.reset();
	assert.equal( controller.isAnyOpen(), false );
} );

test( 'rotation closes the Pages sheet but leaves the Details sheet open', async () => {
	const { controller } = setup();
	await controller.open( 'details', {} );
	controller.closeOnRotate();
	assert.equal( controller.isAnyOpen(), true );
	controller.close();
	await controller.open( 'pages', {} );
	controller.closeOnRotate();
	assert.equal( controller.isAnyOpen(), false );
} );

test( 'nothing opens once the dialog is closed', async () => {
	const { controller, dialog } = setup();
	dialog.open = false;
	assert.equal( await controller.open( 'details', {} ), false );
	assert.equal( calls.push, 0 );
} );

test( 'an unknown sheet is refused', async () => {
	const { controller } = setup();
	assert.equal( await controller.open( 'nope', {} ), false );
} );
