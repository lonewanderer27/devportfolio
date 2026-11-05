/* Document edit screen: generate page images in this browser with pdf.js and upload them.
 *
 * Starts by itself when the status is "needs_browser" (the server could not, or the mode is
 * browser only). "Regenerate in this browser" forces a run. While the server is working
 * ("pending") the box polls the status until it changes.
 */
( function () {
	'use strict';

	var cfg = window.cptdpDocumentPages;
	var box = document.getElementById( 'cptdp-pages-box' );

	if ( ! cfg || ! box ) {
		return;
	}

	var els = {
		status: document.getElementById( 'cptdp-pages-status' ),
		error: document.getElementById( 'cptdp-pages-error' ),
		progress: document.getElementById( 'cptdp-pages-progress' ),
		label: document.getElementById( 'cptdp-pages-progress-label' ),
		button: document.getElementById( 'cptdp-pages-regenerate' )
	};
	var running = false;
	var runToken = '';

	// Empty-bottom analysis. Keep these values identical to the constants of
	// CPTDP_Page_Analyzer (includes/documents/class-page-analyzer.php): a PHP test compares them.
	var ANALYSIS_WIDTH = 300;
	var BLANK_TOLERANCE = 8;
	var BLANK_ROW_RATIO = 0.998;
	var SCAN_FRACTION = 0.3;
	var KEEP_MARGIN = 0.025;
	var MIN_CROP = 0.02;
	var MAX_CROP = 0.25;

	function headers( extra ) {
		var h = { 'X-WP-Nonce': cfg.nonce };
		if ( runToken ) {
			h[ 'X-CPTDP-Run' ] = runToken;
		}
		Object.keys( extra || {} ).forEach( function ( k ) {
			h[ k ] = extra[ k ];
		} );
		return h;
	}

	function request( url, options ) {
		return fetch( url, Object.assign( { credentials: 'same-origin' }, options ) ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! res.ok ) {
					throw new Error( ( data && data.message ) || cfg.i18n.uploadError );
				}
				return data;
			} );
		} );
	}

	function showState( state ) {
		var text = cfg.i18n.needs;

		if ( 'ready' === state.status ) {
			text = cfg.i18n.ready + ( state.count ? ', ' + state.count : '' );
		} else if ( 'pending' === state.status ) {
			text = cfg.i18n.pending;
		} else if ( 'failed' === state.status ) {
			text = cfg.i18n.failed;
		}

		els.status.textContent = text;
		box.setAttribute( 'data-status', state.status );
		els.error.textContent = state.error || '';
		els.error.hidden = ! state.error;
	}

	function progress( page, total ) {
		els.progress.hidden = false;
		els.label.hidden = false;
		els.progress.max = total;
		els.progress.value = page;
		els.label.textContent = cfg.i18n.progress.replace( '%1$d', page ).replace( '%2$d', total );
	}

	function toBlob( canvas ) {
		return new Promise( function ( resolve ) {
			canvas.toBlob( function ( blob ) {
				if ( blob && 'image/webp' === blob.type ) {
					resolve( blob );
					return;
				}
				canvas.toBlob( resolve, 'image/jpeg', cfg.quality );
			}, 'image/webp', cfg.quality );
		} );
	}

	/* Share of the page height that is empty at the bottom (0..SCAN_FRACTION), read from a
	   downscaled copy so 300 pages stay quick. Same rule as the server: a row is blank when
	   at least BLANK_ROW_RATIO of its pixels are within BLANK_TOLERANCE of white. */
	function blankFraction( canvas ) {
		var width = Math.min( ANALYSIS_WIDTH, canvas.width );
		var height = Math.max( 1, Math.round( canvas.height * width / canvas.width ) );
		var small = document.createElement( 'canvas' );
		small.width = width;
		small.height = height;
		var ctx = small.getContext( '2d', { willReadFrequently: true } );
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, width, height );
		ctx.imageSmoothingQuality = 'high';
		ctx.drawImage( canvas, 0, 0, width, height );

		var scan = Math.max( 1, Math.ceil( height * SCAN_FRACTION ) );
		var data = ctx.getImageData( 0, height - scan, width, scan ).data;
		var limit = 255 - BLANK_TOLERANCE;
		var blank = 0;

		for ( var row = scan - 1; row >= 0; row-- ) {
			var content = 0;

			for ( var x = 0; x < width; x++ ) {
				var i = ( row * width + x ) * 4;

				if ( data[ i ] < limit || data[ i + 1 ] < limit || data[ i + 2 ] < limit ) {
					content++;
				}
			}

			if ( content > width * ( 1 - BLANK_ROW_RATIO ) ) {
				break;
			}

			blank++;
		}

		small.width = 0;

		return Math.min( SCAN_FRACTION, blank / height );
	}

	/* The crop for the whole document: the smallest blank fraction of any page, less the kept margin. */
	function documentCrop( smallestBlank ) {
		var crop = smallestBlank - KEEP_MARGIN;

		if ( crop < MIN_CROP ) {
			return 0;
		}

		return Math.round( Math.min( MAX_CROP, crop ) * 10000 ) / 10000;
	}

	function upload( page, blob ) {
		var url = cfg.uploadUrl.replace( '__PAGE__', String( page ) );
		var attempt = function ( left ) {
			return request( url, { method: 'POST', headers: headers( { 'Content-Type': blob.type } ), body: blob } ).catch( function ( error ) {
				if ( left > 0 ) {
					return attempt( left - 1 );
				}
				throw error;
			} );
		};
		return attempt( 2 );
	}

	function finish( payload ) {
		return request( cfg.finishUrl, {
			method: 'POST',
			headers: headers( { 'Content-Type': 'application/json' } ),
			body: JSON.stringify( payload )
		} );
	}

	/* Another tab owns this document's run: say so and watch the status instead of fighting it. */
	function otherTab() {
		running = false;
		runToken = '';
		els.button.disabled = false;
		els.progress.hidden = true;
		els.label.hidden = true;
		els.status.textContent = cfg.i18n.anotherTab;
		poll( 30 );
	}

	/* force: an explicit click on Regenerate takes over; the automatic start never does. */
	function run( force ) {
		if ( running ) {
			return;
		}
		running = true;
		els.button.disabled = true;
		els.status.textContent = cfg.i18n.working;
		els.error.hidden = true;

		var ext = 'webp';
		var count = 0;
		var pdf = null;
		var smallestBlank = SCAN_FRACTION;
		var aborted = false;

		runToken = '';
		request( cfg.startUrl, { method: 'POST', headers: headers( { 'Content-Type': 'application/json' } ), body: JSON.stringify( { force: true === force } ) } )
			.then( function ( started ) {
				runToken = started && started.run ? started.run : '';
				return import( cfg.pdfjs.module );
			} )
			.then( function ( pdfjs ) {
				pdfjs.GlobalWorkerOptions.workerSrc = cfg.pdfjs.worker;
				return pdfjs.getDocument( { url: cfg.pdfUrl, isEvalSupported: false } ).promise;
			} )
			.then( function ( doc ) {
				pdf = doc;
				count = doc.numPages;

				if ( count > cfg.maxPages ) {
					throw new Error( cfg.i18n.tooMany );
				}

				var target = 0;
				var chain = Promise.resolve();

				for ( var n = 1; n <= count; n++ ) {
					( function ( page ) {
						chain = chain.then( function () {
							return pdf.getPage( page ).then( function ( p ) {
								var base = p.getViewport( { scale: 1 } );

								if ( 1 === page ) {
									target = base.width > base.height ? cfg.widths.landscape : cfg.widths.portrait;
								}

								var viewport = p.getViewport( { scale: target / base.width } );
								var canvas = document.createElement( 'canvas' );
								canvas.width = Math.round( viewport.width );
								canvas.height = Math.round( viewport.height );

								return p.render( { canvasContext: canvas.getContext( '2d' ), viewport: viewport, background: '#ffffff' } ).promise
									.then( function () {
										return toBlob( canvas );
									} )
									.then( function ( blob ) {
										if ( 'image/jpeg' === blob.type ) {
											ext = 'jpg';
										}
										smallestBlank = Math.min( smallestBlank, blankFraction( canvas ) );
										canvas.width = 0;
										p.cleanup();
										return upload( page, blob );
									} )
									.then( function () {
										progress( page, count );
									} );
							} );
						} );
					} )( n );
				}

				return chain;
			} )
			.then( function () {
				return finish( { count: count, ext: ext, fingerprint: cfg.fingerprint, cropBottom: documentCrop( smallestBlank ) } );
			} )
			.then( showState )
			.catch( function ( error ) {
				if ( error && ( 'cptdp_run_active' === error.code || 'cptdp_run_mismatch' === error.code ) ) {
					aborted = true;
					return;
				}
				var message = error && error.message ? error.message : cfg.i18n.loadError;
				return finish( { error: message } ).then( showState ).catch( function () {
					showState( { status: 'failed', error: message } );
				} );
			} )
			.then( function () {
				if ( pdf ) {
					pdf.destroy();
				}
				if ( aborted ) {
					otherTab();
					return;
				}
				running = false;
				els.button.disabled = false;
				els.progress.hidden = true;
				els.label.hidden = true;
			} );
	}

	function poll( tries ) {
		if ( tries < 1 ) {
			return;
		}
		window.setTimeout( function () {
			request( cfg.stateUrl, { method: 'GET', headers: headers() } )
				.then( function ( state ) {
					showState( state );
					if ( 'pending' === state.status ) {
						poll( tries - 1 );
					} else if ( 'needs_browser' === state.status ) {
						run();
					}
				} )
				.catch( function () {
					poll( tries - 1 );
				} );
		}, 4000 );
	}

	els.button.addEventListener( 'click', function () {
		run( true );
	} );

	if ( 'needs_browser' === cfg.status ) {
		run( false );
	} else if ( 'pending' === cfg.status ) {
		poll( 30 );
	}
}() );
