/**
 * Home sections: opening animation (DPH-032).
 *
 * Printed inline at the top of <head> on the front page only (functions.php), so it runs before the first paint and
 * can never fail to load. It adds `has-home-reveal` to <html>; assets/css/home-motion.css hides `.home-reveal`
 * elements only under that class. Each element fades and rises in once, the first time 15% of it is on screen;
 * elements that arrive together are staggered (index capped at STAGGER_CAP). Elements on screen at load animate on
 * the first paint. Nothing repeats when scrolling back.
 *
 * Without IntersectionObserver or with reduced motion the class is never added. A script error, or a page whose
 * parsing takes longer than GIVE_UP_MS, removes it, so nothing stays hidden. Entering through a cross-document view
 * transition (Back to Home from an archive) removes it at once: the page cross-fade and the Selected work title morph
 * are the entrance there.
 */
( () => {
	const root = document.documentElement;
	const READY = 'has-home-reveal';
	const TARGET = '.home-reveal';
	const THRESHOLD = 0.15;
	const STAGGER_CAP = 5;
	const FALLBACK_MS = 2000;
	const GIVE_UP_MS = 3000;

	let observer = null;
	let started = false;

	const stop = () => {
		root.classList.remove( READY );
		if ( observer ) {
			observer.disconnect();
			observer = null;
		}
	};

	try {
		if (
			! ( 'IntersectionObserver' in window ) ||
			! window.matchMedia ||
			! window.matchMedia( '(prefers-reduced-motion: no-preference)' ).matches
		) {
			return;
		}
		root.classList.add( READY );
	} catch ( error ) {
		stop();
		return;
	}

	// Back to Home from an archive in a browser with cross-document view transitions: the new page is captured as it
	// is drawn, so show every section now and let the cross-fade and the title morph be the entrance.
	window.addEventListener( 'pagereveal', ( event ) => {
		if ( event.viewTransition ) {
			stop();
		}
	} );

	// Leave nothing behind: no animation, no index, so the element is not a stacking context any more.
	const finish = ( el ) => {
		el.classList.remove( 'is-revealing' );
		el.classList.add( 'is-revealed' );
		el.style.removeProperty( '--home-reveal-index' );
	};

	const reveal = ( el, index ) => {
		// Focus is inside: it is already shown (CSS), so do not fade it out and in again.
		if ( el.matches( ':focus-within' ) ) {
			finish( el );
			return;
		}

		let done = false;
		const end = ( event ) => {
			if ( done || ( event && event.target !== el ) ) {
				return;
			}
			done = true;
			el.removeEventListener( 'animationend', end );
			el.removeEventListener( 'animationcancel', end );
			finish( el );
		};

		el.addEventListener( 'animationend', end );
		el.addEventListener( 'animationcancel', end );
		el.style.setProperty( '--home-reveal-index', String( Math.min( index, STAGGER_CAP ) ) );
		el.classList.add( 'is-revealing' );
		// The animation may never run (the phone contact modal, an element that is not displayed): finish anyway.
		window.setTimeout( end, FALLBACK_MS );
	};

	const start = () => {
		started = true;
		if ( ! root.classList.contains( READY ) ) {
			return;
		}

		const targets = document.querySelectorAll( TARGET );
		if ( ! targets.length ) {
			stop();
			return;
		}

		observer = new IntersectionObserver(
			( entries ) => {
				let index = 0;
				entries.forEach( ( entry ) => {
					if ( ! observer || ! entry.isIntersecting || entry.intersectionRatio < THRESHOLD ) {
						return;
					}
					observer.unobserve( entry.target );
					reveal( entry.target, index );
					index += 1;
				} );
			},
			{ threshold: THRESHOLD }
		);

		targets.forEach( ( el ) => observer.observe( el ) );
	};

	const boot = () => {
		try {
			start();
		} catch ( error ) {
			stop();
		}
	};

	// A slow blocking script in the page can hold the parser: never keep a section hidden for long.
	window.setTimeout( () => {
		if ( ! started ) {
			stop();
		}
	}, GIVE_UP_MS );

	// Start as soon as the parser has the whole page ("interactive"). The later DOM-ready event also waits for every
	// deferred script and script module on Home, which would keep on-screen sections hidden for longer.
	if ( 'loading' !== document.readyState ) {
		boot();
	} else {
		const onState = () => {
			if ( 'loading' !== document.readyState ) {
				document.removeEventListener( 'readystatechange', onState );
				boot();
			}
		};
		document.addEventListener( 'readystatechange', onState );
	}
} )();
