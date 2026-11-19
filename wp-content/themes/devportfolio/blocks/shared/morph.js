/**
 * One element transition for the Videos page (DPV-031, DPV-033, DPV-036): a picture, or the hover outline,
 * moves from one element to another with the View Transitions API.
 *
 * `from` holds the name in the OLD state, `to` in the NEW state (they may be the same element). The names
 * exist only while the transition runs, and an element's own inline name is put back afterwards. Without
 * support, or with reduced motion, `update()` just runs. Starting a morph settles the one still running, so
 * an Esc or a new click mid-way jumps to the end and nothing is left named.
 */

export const MORPH_NAME = 'videos-media';

/**
 * @param {Document} doc Document (injectable for tests).
 * @param {Window}   win Window (injectable for tests).
 * @return {{supported:boolean,reducedMotion:boolean}}
 */
export function morphEnv( doc = document, win = window ) {
	return {
		supported: 'function' === typeof doc.startViewTransition,
		reducedMotion: Boolean( win.matchMedia && win.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ),
	};
}

/**
 * @param {{supported:boolean,reducedMotion:boolean}} env From morphEnv().
 * @return {boolean}
 */
export function canMorph( env ) {
	return Boolean( env.supported ) && ! env.reducedMotion;
}

function setName( el, name ) {
	if ( ! el ) {
		return () => {};
	}
	const previous = el.style.viewTransitionName;
	let done = false;
	el.style.viewTransitionName = name;
	return () => {
		if ( ! done ) {
			done = true;
			el.style.viewTransitionName = previous;
		}
	};
}

let running = null;

/** @return {boolean} True while a morph is running. */
export function isMorphing() {
	return null !== running;
}

/**
 * Jumps the running morph to its end state.
 *
 * @return {boolean} True when there was one.
 */
export function settleMorph() {
	if ( ! running ) {
		return false;
	}
	running.settle();
	return true;
}

/**
 * @param {Object}           o
 * @param {Element|null}     [o.from]      Named in the old state.
 * @param {Element|null}     [o.to]        Named in the new state (after update()).
 * @param {Function}         o.update      Changes the DOM.
 * @param {string}           [o.name]      View transition name.
 * @param {string}           [o.className] Class put on <html> while it runs (CSS hooks).
 * @param {Document}         [o.doc]
 * @param {Window}           [o.win]
 * @return {{animated:boolean,finished:Promise<void>,skip?:Function}}
 */
export function morph( { from = null, to = null, update, name = MORPH_NAME, className = 'is-morphing', doc = document, win = window } ) {
	settleMorph();

	if ( ! canMorph( morphEnv( doc, win ) ) ) {
		update();
		return { animated: false, finished: Promise.resolve() };
	}

	const restoreFrom = setName( from, name );
	let restoreTo = () => {};
	let transition = null;
	let settled = false;
	let updated = false;

	// The DOM change runs exactly once. If the browser has not called the callback yet when the morph is settled,
	// it runs here, so the end state is always reached (a pending open is not lost, a pending close still closes).
	const applyUpdate = () => {
		if ( updated ) {
			return;
		}
		updated = true;
		restoreFrom();
		update();
		if ( ! settled ) {
			restoreTo = setName( to, name );
		}
	};

	const run = {
		settle() {
			if ( settled ) {
				return;
			}
			settled = true;
			if ( transition ) {
				transition.skipTransition();
			}
			applyUpdate();
			restoreFrom();
			restoreTo();
			doc.documentElement.classList.remove( className );
			if ( running === run ) {
				running = null;
			}
		},
	};

	running = run;
	doc.documentElement.classList.add( className );
	transition = doc.startViewTransition( applyUpdate );
	// A skipped transition rejects `ready`; nothing waits on it, so it must not surface as an unhandled rejection.
	if ( transition.ready && transition.ready.catch ) {
		transition.ready.catch( () => {} );
	}

	const finished = transition.finished.then( run.settle, run.settle );
	return { animated: true, finished, skip: run.settle };
}
