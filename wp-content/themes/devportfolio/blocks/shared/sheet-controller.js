/**
 * The mobile modal's bottom sheets as a set (DPD-020, DPD-023): which sheets exist, loading each one's code on
 * its first use, allowing one open at a time, and the single history entry that lets the phone's Back button
 * close only the sheet (sheet-back.js). mobile.js knows nothing about any particular sheet: it registers them
 * and forwards events.
 *
 * The history listener is created HERE, when mobile.js builds the controller at start, never inside a lazy
 * `import()`: it has to be registered ahead of WordPress's Interactivity Router (see sheet-back.js).
 */
import { createSheetHistory } from './sheet-back.js';

/**
 * @param {Object}  args
 * @param {Element} args.dialog The mobile dialog.
 * @return {{register:Function,open:Function,close:Function,closeOnRotate:Function,isAnyOpen:Function,activeName:Function,reset:Function,release:Function}}
 */
export function createSheetController( { dialog } ) {
	const defs = new Map();
	const instances = new Map();
	let active = null; // { name, inst }
	let loading = false;

	const history = createSheetHistory( {
		isOpen: () => !! active && active.inst.isOpen(),
		onBack: () => close( { fromHistory: true } ),
	} );

	/** The sheet was dismissed by the visitor (it has hidden itself): drop its history entry. */
	const dismissed = () => {
		if ( active ) {
			active = null;
			history.pop();
		}
	};

	/** Closes the open sheet from outside (a choice, Back, rotation); the entry it added is popped unless history itself closed it. */
	const close = ( { fromHistory = false } = {} ) => {
		if ( ! active || ! active.inst.isOpen() ) {
			return;
		}
		const { inst } = active;
		active = null;
		inst.close( true );
		if ( fromHistory ) {
			history.release();
		} else {
			history.pop();
		}
	};

	return {
		/**
		 * @param {string}   name
		 * @param {Object}   def
		 * @param {Function} def.create        `( { dialog, onClose } ) => sheet` (may be async, usually `import()`); the sheet has `open( args ) => boolean`, `close( silent )`, `isOpen()`, `reset()` and calls `onClose` when the visitor dismisses it.
		 * @param {boolean}  def.closeOnRotate Close it when the phone is rotated (the Pages sheet), else it re-lays out in place.
		 */
		register( name, { create, closeOnRotate = false } ) {
			defs.set( name, { create, closeOnRotate } );
		},
		/** Opens a sheet (loading its code the first time). False when another is open or loading. */
		async open( name, args ) {
			const def = defs.get( name );
			if ( ! def || loading || ( active && active.inst.isOpen() ) ) {
				return false;
			}
			loading = true;
			try {
				let inst = instances.get( name );
				if ( ! inst ) {
					inst = await def.create( { dialog, onClose: dismissed } );
					instances.set( name, inst );
				}
				if ( dialog.open && inst.open( args ) ) {
					active = { name, inst };
					history.push();
					return true;
				}
			} catch ( e ) {
				instances.delete( name );
			} finally {
				loading = false;
			}
			return false;
		},
		close,
		/** The phone was rotated: close the sheet unless it re-lays out in place. */
		closeOnRotate() {
			if ( active && defs.get( active.name ).closeOnRotate ) {
				close();
			}
		},
		isAnyOpen: () => !! active && active.inst.isOpen(),
		activeName: () => ( active && active.inst.isOpen() ? active.name : '' ),
		/** Another document opens: every built sheet drops its content. */
		reset() {
			active = null;
			instances.forEach( ( inst ) => inst.reset() );
		},
		/** The viewer closed: 1 when a history entry of a sheet is left behind, else 0. */
		release() {
			return history.release();
		},
	};
}
