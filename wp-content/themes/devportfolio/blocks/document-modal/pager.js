/**
 * Pure pager helpers for the document modal (DPD-011). No DOM access, so they
 * can be tested with `node --test`.
 */

/** Pages shown at once: two-page view for multi-page documents, else one. */
export function stepFor( pages ) {
	return pages > 1 ? 2 : 1;
}

/** Clamps a page into range; a two-page view always starts on an odd page. */
export function normalisePage( page, pages, step ) {
	let current = Math.max( 1, Math.min( Math.floor( Number( page ) ) || 1, pages ) );
	if ( step === 2 && current % 2 === 0 ) {
		current -= 1;
	}
	return Math.max( 1, current );
}

/** First and last visible page. */
export function visibleRange( page, pages, step ) {
	const first = normalisePage( page, pages, step );
	return { first, last: Math.min( first + step - 1, pages ) };
}

/**
 * Items of the pager: page numbers and '…' gaps.
 * Up to 9 pages: all. Longer: 1, last, and a window of 3 on each side
 * (2 in a two-page view); a gap of exactly one page shows that page.
 */
export function pagerItems( pages, page, step ) {
	if ( pages <= 9 ) {
		return Array.from( { length: pages }, ( _, i ) => i + 1 );
	}
	const { first, last } = visibleRange( page, pages, step );
	const side = step === 2 ? 2 : 3;
	const set = new Set( [ 1, pages ] );
	for ( let p = Math.max( 1, first - side ); p <= Math.min( pages, last + side ); p++ ) {
		set.add( p );
	}
	const sorted = [ ...set ].sort( ( a, b ) => a - b );
	const items = [];
	sorted.forEach( ( p, i ) => {
		if ( i > 0 ) {
			const gap = p - sorted[ i - 1 ];
			if ( gap === 2 ) {
				items.push( p - 1 );
			} else if ( gap > 2 ) {
				items.push( '…' );
			}
		}
		items.push( p );
	} );
	return items;
}

/** Page numbers that are visible (filled). */
export function visiblePages( page, pages, step ) {
	const { first, last } = visibleRange( page, pages, step );
	return Array.from( { length: last - first + 1 }, ( _, i ) => first + i );
}

/** Back / Next availability. */
export function pagerState( page, pages, step ) {
	const { first, last } = visibleRange( page, pages, step );
	return { backOff: first <= 1, nextOff: last >= pages };
}

/** Page after pressing Back (-1) or Next (1), or the same page at the ends. */
export function turnPage( page, pages, step, direction ) {
	const { first } = visibleRange( page, pages, step );
	const { backOff, nextOff } = pagerState( first, pages, step );
	if ( ( direction < 0 && backOff ) || ( direction > 0 && nextOff ) ) {
		return first;
	}
	return normalisePage( first + direction * step, pages, step );
}

/** Indicator text: "Pages 11–12 of 24" or "Page 5 of 24". */
export function indicatorParts( page, pages, step ) {
	const { first, last } = visibleRange( page, pages, step );
	return {
		first,
		last,
		both: last > first,
		percent: Math.round( ( last / pages ) * 10000 ) / 100,
	};
}
