/**
 * Pure layout helpers for the document viewer (DPD-013). No DOM access, so
 * they can be tested with `node --test`.
 */

/** 'landscape' when the page is wider than tall, else 'portrait'. */
export function orientationOf( width, height ) {
	return Number( width ) > Number( height ) ? 'landscape' : 'portrait';
}

/**
 * Size of one page so a spread (step 2) or a single page (step 1) fits inside
 * the stage. `ratio` is page width / page height. `maxPageW` (optional) is the
 * widest a page may be drawn, e.g. the width of a stored image, so pages are
 * never scaled above their source. Sizes are whole pixels, at least 1.
 */
export function fitPage( { stageW, stageH, ratio, step = 1, maxPageW = Infinity } ) {
	const r = Number( ratio ) > 0 ? Number( ratio ) : 0.75;
	const across = step === 2 ? 2 : 1;
	let pageW = Math.min( Math.max( 0, Number( stageW ) ) / across, Math.max( 0, Number( stageH ) ) * r );
	if ( Number.isFinite( maxPageW ) && maxPageW > 0 ) {
		pageW = Math.min( pageW, maxPageW );
	}
	pageW = Math.max( 1, Math.floor( pageW ) );
	return { pageW, pageH: Math.max( 1, Math.floor( pageW / r ) ) };
}

/**
 * Pages worth having drawn: the visible view plus `ahead` views either side.
 * Returns an inclusive 1-based { from, to } range, clamped to the document.
 */
export function renderWindow( page, pages, step, ahead ) {
	const first = Math.max( 1, Math.min( Math.floor( Number( page ) ) || 1, pages ) );
	const span = Math.max( 1, step ) * Math.max( 0, ahead );
	return {
		from: Math.max( 1, first - span ),
		to: Math.min( pages, first + Math.max( 1, step ) - 1 + span ),
	};
}

/**
 * Pixel density to draw a canvas at: the device ratio, capped by the tier, and
 * lowered further so one canvas never exceeds `maxPixels` (0 or Infinity = no
 * pixel cap). Never below 1 unless the pixel cap forces it lower.
 */
export function canvasScale( { pageW, pageH, dpr = 1, dprCap = 2, maxPixels = Infinity } ) {
	let scale = Math.max( 0.1, Math.min( Number( dpr ) || 1, dprCap ) );
	const area = Math.max( 1, pageW ) * Math.max( 1, pageH );
	if ( Number.isFinite( maxPixels ) && maxPixels > 0 && area * scale * scale > maxPixels ) {
		scale = Math.sqrt( maxPixels / area );
	}
	// Round down, so the pixel cap is never exceeded by rounding.
	return Math.floor( scale * 1000 ) / 1000;
}

/**
 * Whether a portrait document is shown as a spread (two pages side by side) in a stage of this size
 * (DPD-024). True when it has 2 or more pages, the page is portrait, and the stage is wide enough that
 * each page of the spread is as large as one page would be on its own: stageW >= 2 * stageH * ratio.
 * So a phone in portrait shows one page, a phone in landscape two, and a nearly square foldable screen
 * one larger page. Landscape documents and one-page documents never spread.
 */
export function spreadFits( { stageW, stageH, ratio, pages } ) {
	const r = Number( ratio );
	if ( ! ( Number( pages ) >= 2 ) || ! ( r > 0 ) || orientationOf( r, 1 ) !== 'portrait' ) {
		return false;
	}
	return Number( stageW ) >= 2 * Number( stageH ) * r;
}
