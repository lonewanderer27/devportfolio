/**
 * Interests (DPB-017): show every <time data-cptdp-local> in the viewer's own time zone, in the same form as the
 * visitor's entry: "Tue 24 Nov 2026, 09:00 (Asia/Manila)". The server wrote the site-zone text (what scripts-off sees);
 * `datetime` holds the UTC instant, which is what is stored.
 */
( function () {
	var lang = document.documentElement.lang || undefined;
	var zone;
	var format;
	try {
		zone = new Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
		format = new Intl.DateTimeFormat( lang, {
			weekday: 'short',
			day: 'numeric',
			month: 'short',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
			hourCycle: 'h23',
		} );
	} catch ( error ) {
		return;
	}

	document.querySelectorAll( 'time[data-cptdp-local]' ).forEach( function ( node ) {
		var iso = node.getAttribute( 'datetime' );
		var date = new Date( iso );
		if ( isNaN( date.getTime() ) ) {
			return;
		}
		var part = {};
		format.formatToParts( date ).forEach( function ( item ) {
			part[ item.type ] = item.value;
		} );
		node.textContent = part.weekday + ' ' + part.day + ' ' + part.month + ' ' + part.year + ', ' + part.hour + ':' + part.minute + ' (' + zone + ')';
		node.title = iso + ' (UTC)';
	} );
}() );
