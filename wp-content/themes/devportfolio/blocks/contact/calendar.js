/**
 * Calendar maths for the Contact scheduler (DPH-025). Pure: dates are ISO strings "YYYY-MM-DD", months are
 * `{ y, m }` with m = 1 to 12. No clock and no time zone: "today", the first and the last bookable day all come
 * from the server, so string comparison is date comparison.
 */
const pad = ( n ) => String( n ).padStart( 2, '0' );

export function toIso( y, m, d ) {
	return `${ y }-${ pad( m ) }-${ pad( d ) }`;
}

/** `{ y, m, d }` or null for anything that is not "YYYY-MM-DD". */
export function parseIso( iso ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( iso || '' );
	return match ? { y: Number( match[ 1 ] ), m: Number( match[ 2 ] ), d: Number( match[ 3 ] ) } : null;
}

/** The month a date is in, or null. */
export function monthOf( iso ) {
	const parts = parseIso( iso );
	return parts ? { y: parts.y, m: parts.m } : null;
}

export function shiftMonth( { y, m }, delta ) {
	const index = y * 12 + ( m - 1 ) + delta;
	return { y: Math.floor( index / 12 ), m: ( index % 12 ) + 1 };
}

/** Whether any day of the month can be in the bookable range. */
export function monthInRange( { y, m }, first, last ) {
	const key = `${ y }-${ pad( m ) }`;
	return key >= first.slice( 0, 7 ) && key <= last.slice( 0, 7 );
}

export function isSelectable( iso, first, last ) {
	return iso >= first && iso <= last;
}

/** 0 for Monday to 6 for Sunday. */
export function weekdayIndex( y, m, d ) {
	return ( new Date( Date.UTC( y, m - 1, d ) ).getUTCDay() + 6 ) % 7;
}

export function daysInMonth( y, m ) {
	return new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
}

/**
 * The month as weeks (Monday first) of 7 cells: `null` for the padding, else `{ iso, day, selectable }`.
 */
export function monthGrid( { y, m }, first, last ) {
	const cells = [];
	for ( let i = 0; i < weekdayIndex( y, m, 1 ); i++ ) {
		cells.push( null );
	}
	for ( let d = 1; d <= daysInMonth( y, m ); d++ ) {
		const iso = toIso( y, m, d );
		cells.push( { iso, day: d, selectable: isSelectable( iso, first, last ) } );
	}
	while ( cells.length % 7 !== 0 ) {
		cells.push( null );
	}

	const weeks = [];
	for ( let i = 0; i < cells.length; i += 7 ) {
		weeks.push( cells.slice( i, i + 7 ) );
	}
	return weeks;
}
