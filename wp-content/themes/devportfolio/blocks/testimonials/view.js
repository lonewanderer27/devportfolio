/**
 * Home testimonials (DPH-022, DPH-023): the dots, the swipe and the six-second rotation.
 *
 * The rotation stops while the mouse is over the section (touch is ignored: a tap leaves a "hover" behind), while a
 * control inside it has keyboard focus (:focus-visible), and while the tab is hidden. With reduced motion it never
 * starts: quotes change only through the dots or a swipe. The stage is aria-live="off" while it rotates by itself and
 * "polite" once the visitor has changed a quote.
 */
import { PERIOD, stepIndex, swipeDirection, shouldRun } from './rotation.js';

const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function start( root ) {
	const stage = root.querySelector( '[data-testimonials-stage]' );
	const slides = [ ...root.querySelectorAll( '[data-testimonials-slide]' ) ];
	const dotsBox = root.querySelector( '[data-testimonials-dots]' );
	if ( ! stage || slides.length < 2 || ! dotsBox ) {
		return;
	}

	const dots = [ ...dotsBox.querySelectorAll( 'button' ) ];
	const state = { index: 0, hover: false, focus: false };
	let timer = 0;

	dotsBox.hidden = false;

	function show( index ) {
		state.index = index;
		slides.forEach( ( slide, i ) => slide.classList.toggle( 'is-active', i === index ) );
		dots.forEach( ( dot, i ) => {
			if ( i === index ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	}

	function schedule() {
		clearTimeout( timer );
		if ( ! shouldRun( { count: slides.length, reducedMotion: reducedMotion.matches, hover: state.hover, focus: state.focus, hidden: document.hidden } ) ) {
			return;
		}
		timer = setTimeout( () => {
			show( stepIndex( state.index, slides.length, 1 ) );
			schedule();
		}, PERIOD );
	}

	function choose( index ) {
		stage.setAttribute( 'aria-live', 'polite' );
		show( index );
		schedule();
	}

	dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => choose( i ) ) );

	let from = null;
	stage.addEventListener( 'pointerdown', ( event ) => {
		from = event.pointerType === 'touch' ? { x: event.clientX, y: event.clientY } : null;
	} );
	stage.addEventListener( 'pointerup', ( event ) => {
		if ( ! from ) {
			return;
		}
		const direction = swipeDirection( event.clientX - from.x, event.clientY - from.y );
		from = null;
		if ( direction !== 0 ) {
			choose( stepIndex( state.index, slides.length, direction ) );
		}
	} );
	stage.addEventListener( 'pointercancel', () => {
		from = null;
	} );

	root.addEventListener( 'pointerenter', ( event ) => {
		state.hover = event.pointerType !== 'touch';
		schedule();
	} );
	root.addEventListener( 'pointerleave', () => {
		state.hover = false;
		schedule();
	} );
	root.addEventListener( 'focusin', ( event ) => {
		state.focus = event.target.matches( ':focus-visible' );
		schedule();
	} );
	root.addEventListener( 'focusout', () => {
		state.focus = false;
		schedule();
	} );
	document.addEventListener( 'visibilitychange', schedule );
	reducedMotion.addEventListener( 'change', schedule );

	schedule();
}

document.querySelectorAll( '.testimonials' ).forEach( start );
