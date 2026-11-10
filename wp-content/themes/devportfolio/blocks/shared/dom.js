/** Tiny DOM builders shared by the viewers (no state, no dependencies). */
const SVG = 'http://www.w3.org/2000/svg';

/** `<tag class="..." attr="...">`. */
export function el( tag, className, attrs = {} ) {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	Object.entries( attrs ).forEach( ( [ key, value ] ) => node.setAttribute( key, value ) );
	return node;
}

/**
 * A 24x24 line icon made of one or more path strings (decorative: `aria-hidden`). The class is
 * `<prefix>__icon`; use iconIn() to pick another viewer prefix (the Documents default is kept).
 */
export function svgIcon( ...paths ) {
	return iconIn( 'documents-modal', ...paths );
}

/** The same icon as svgIcon(), with the class `${ prefix }__icon`. */
export function iconIn( prefix, ...paths ) {
	const svg = document.createElementNS( SVG, 'svg' );
	svg.setAttribute( 'class', `${ prefix }__icon` );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'aria-hidden', 'true' );
	svg.setAttribute( 'focusable', 'false' );
	paths.filter( Boolean ).forEach( ( data ) => {
		const path = document.createElementNS( SVG, 'path' );
		path.setAttribute( 'd', data );
		svg.appendChild( path );
	} );
	return svg;
}
