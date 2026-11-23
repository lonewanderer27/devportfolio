/**
 * Times of the Contact scheduler (DPH-025). Pure. The visitor picks a wall-clock day and time in their own time zone,
 * which is the browser's zone; the server converts it to UTC with the same zone and stores it. Nothing here converts
 * between zones: the time is only worded for the visitor.
 */

/** The instant (ms since the epoch) of a wall-clock `date` ("YYYY-MM-DD") and `time` ("HH:MM") in the browser's zone. */
export function localMs( date, time ) {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	const [ hours, minutes ] = time.split( ':' ).map( Number );
	return new Date( y, m - 1, d, hours, minutes ).getTime();
}

/** "Tue 6 Oct, 4:30 pm" for an instant, in the browser's zone. */
export function formatInstant( ms, locale ) {
	const parts = new Intl.DateTimeFormat( locale, {
		weekday: 'short',
		day: 'numeric',
		month: 'short',
		hour: 'numeric',
		minute: '2-digit',
	} ).formatToParts( new Date( ms ) );
	const get = ( type ) => ( parts.find( ( part ) => part.type === type ) || { value: '' } ).value;

	return `${ get( 'weekday' ) } ${ get( 'day' ) } ${ get( 'month' ) }, ${ get( 'hour' ) }:${ get( 'minute' ) } ${ get( 'dayPeriod' ).toLowerCase() }`.trim();
}

/** "4:30 pm" for a clock time ("HH:MM"), with no date, for the time pills. */
export function formatClock( time, locale ) {
	const [ hours, minutes ] = time.split( ':' ).map( Number );
	const parts = new Intl.DateTimeFormat( locale, { hour: 'numeric', minute: '2-digit' } ).formatToParts( new Date( 2000, 0, 1, hours, minutes ) );
	const get = ( type ) => ( parts.find( ( part ) => part.type === type ) || { value: '' } ).value;

	return `${ get( 'hour' ) }:${ get( 'minute' ) } ${ get( 'dayPeriod' ).toLowerCase() }`.trim();
}
