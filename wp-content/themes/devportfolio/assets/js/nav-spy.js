/**
 * Home page: the main navigation shows which section is on screen.
 *
 * Exactly one item is active: Services, Testimonials or Contact while that
 * section crosses the line just under the sticky header, Home otherwise.
 * The server marks Home on the front page; this only moves that mark.
 */
const SECTIONS = [ 'services', 'testimonials', 'contact' ];

const nav = document.querySelector( 'header .wp-block-navigation' );
const items = nav ? [ ...nav.querySelectorAll( '.wp-block-navigation-item' ) ] : [];

const itemFor = ( id ) =>
	items.find( ( li ) => li.querySelector( 'a' )?.hash === `#${ id }` );
const home = items.find( ( li ) => {
	const a = li.querySelector( 'a' );
	return a && ! a.hash && a.pathname === location.pathname;
} );

const targets = SECTIONS.map( ( id ) => ( { el: document.getElementById( id ), li: itemFor( id ) } ) ).filter(
	( t ) => t.el && t.li
);

if ( home && targets.length ) {
	const headerBottom = () =>
		document.querySelector( 'header.wp-block-template-part' )?.getBoundingClientRect().bottom ?? 0;

	const activeItem = () => {
		const line = headerBottom() + 1;
		const atEnd = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
		if ( atEnd ) {
			return targets[ targets.length - 1 ].li;
		}
		const hit = targets.find( ( { el } ) => {
			const r = el.getBoundingClientRect();
			return r.top <= line && r.bottom > line;
		} );
		return hit ? hit.li : home;
	};

	let current = null;
	const update = () => {
		const next = activeItem();
		if ( next === current ) {
			return;
		}
		current = next;
		items.forEach( ( li ) => {
			const on = li === next;
			li.classList.toggle( 'is-current', on );
			const a = li.querySelector( 'a' );
			if ( on ) {
				a?.setAttribute( 'aria-current', 'location' );
			} else {
				a?.removeAttribute( 'aria-current' );
			}
		} );
	};

	let queued = false;
	const schedule = () => {
		if ( queued ) {
			return;
		}
		queued = true;
		requestAnimationFrame( () => {
			queued = false;
			update();
		} );
	};

	addEventListener( 'scroll', schedule, { passive: true } );
	addEventListener( 'resize', schedule );
	update();
}
