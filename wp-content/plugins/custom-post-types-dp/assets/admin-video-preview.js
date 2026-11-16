/* Video edit screen: make the 6 second preview clip in the browser (DPV-041).
 *
 * Path B: Mediabunny (WebCodecs) cuts the first 6 s into a muted H.264 MP4. Path A (fallback): MediaRecorder
 * records a canvas while the video plays. The clip is sent to cptdp/v1/videos/<id>/preview; the form never
 * posts it. window.cptdpVideoPreview = { start( fileId, url ), cancel() }.
 */
( function () {
	'use strict';

	// The localized data is printed with admin-video.js, after this dependency has run: read it when needed.
	function cfgNow() { return window.cptdpVideo || {}; }
	function textNow() { return cfgNow().previewText || {}; }
	var CLIP_SECONDS = 6;
	var MAX_SIDE = 720;
	var current = null; // The run in progress: { cancelled, cancel }.
	var els = null;

	function ui() {
		if ( els ) { return els; }

		var anchor = document.getElementById( 'cptdp-video-file-name' );
		var host = anchor && anchor.closest ? anchor.closest( 'p' ) : null;

		if ( ! host ) { return null; }

		var p = document.createElement( 'p' );
		var span = document.createElement( 'span' );
		var bar = document.createElement( 'progress' );

		p.id = 'cptdp-video-preview';
		p.className = 'description';
		p.setAttribute( 'aria-live', 'polite' );
		p.hidden = true;
		bar.max = 100;
		bar.hidden = true;
		p.appendChild( span );
		p.appendChild( document.createTextNode( ' ' ) );
		p.appendChild( bar );
		host.parentNode.insertBefore( p, host.nextSibling );

		els = { p: p, span: span, bar: bar };

		return els;
	}

	function show( message, percent ) {
		var e = ui();

		if ( ! e ) { return; }

		e.p.hidden = ! message;
		e.span.textContent = message || '';
		e.bar.hidden = typeof percent !== 'number';

		if ( typeof percent === 'number' ) { e.bar.value = Math.max( 0, Math.min( 100, percent ) ); }
	}

	function warnLeaving( event ) {
		event.preventDefault();
		event.returnValue = textNow().leave || '';

		return event.returnValue;
	}

	// Longest side at most MAX_SIDE, never upscaled, even numbers (H.264 needs them).
	function fit( w, h ) {
		var scale = Math.min( 1, MAX_SIDE / Math.max( w, h ) );

		return { w: Math.max( 2, 2 * Math.round( ( w * scale ) / 2 ) ), h: Math.max( 2, 2 * Math.round( ( h * scale ) / 2 ) ) };
	}

	// Path B.
	function viaMediabunny( url, progress, run ) {
		var MB = window.Mediabunny;

		if ( ! MB || ! window.VideoEncoder || ! window.VideoDecoder ) {
			return Promise.reject( new Error( 'unsupported' ) );
		}

		var input = new MB.Input( { source: new MB.UrlSource( url ), formats: MB.ALL_FORMATS } );
		var output = new MB.Output( { format: new MB.Mp4OutputFormat(), target: new MB.BufferTarget() } );
		var conversion = null;

		run.cancel = function () {
			if ( conversion ) { conversion.cancel().catch( function () {} ); }
			input.dispose();
		};

		return input.getPrimaryVideoTrack().then( function ( track ) {
			if ( ! track ) { throw new Error( 'unsupported' ); }

			var size = fit( track.displayWidth, track.displayHeight );

			return MB.Conversion.init( {
				input: input,
				output: output,
				trim: { start: 0, end: CLIP_SECONDS },
				video: { width: size.w, height: size.h, fit: 'fill', codec: 'avc', quality: new MB.Quality( { bitrate: 1e6 } ) },
				audio: { discard: true }
			} );
		} ).then( function ( c ) {
			conversion = c;

			if ( ! c.isValid ) { throw new Error( 'unsupported' ); }

			c.onProgress = function ( p ) { progress( p * 100 ); };

			return c.execute();
		} ).then( function () {
			input.dispose();

			if ( ! output.target.buffer ) { throw new Error( 'empty' ); }

			return new Blob( [ output.target.buffer ], { type: 'video/mp4' } );
		}, function ( e ) {
			input.dispose();
			throw e;
		} );
	}

	// Path A: real time, about 6 s.
	function viaRecorder( url, progress, run ) {
		return new Promise( function ( resolve, reject ) {
			var types = [ 'video/mp4;codecs=avc1', 'video/webm;codecs=vp9', 'video/webm' ];
			var type = '';

			if ( ! window.MediaRecorder || ! HTMLCanvasElement.prototype.captureStream ) {
				return reject( new Error( 'unsupported' ) );
			}

			types.forEach( function ( t ) { if ( ! type && MediaRecorder.isTypeSupported( t ) ) { type = t; } } );

			if ( ! type ) { return reject( new Error( 'unsupported' ) ); }

			var video = document.createElement( 'video' );
			var canvas = document.createElement( 'canvas' );
			var ctx = canvas.getContext( '2d' );
			var chunks = [];
			var recorder = null;
			var limit = CLIP_SECONDS;
			var frame = 0;
			var done = false;

			function finish( error ) {
				if ( done ) { return; }
				done = true;
				cancelAnimationFrame( frame );
				video.pause();
				video.removeAttribute( 'src' );

				if ( error ) {
					if ( recorder && recorder.state !== 'inactive' ) { recorder.onstop = null; recorder.stop(); }
					return reject( error );
				}

				recorder.onstop = function () { resolve( new Blob( chunks, { type: type.split( ';' )[ 0 ] } ) ); };
				recorder.stop();
			}

			run.cancel = function () { finish( new Error( 'cancelled' ) ); };

			video.muted = true;
			video.playsInline = true;
			video.preload = 'auto';
			video.addEventListener( 'error', function () { finish( new Error( 'video' ) ); } );
			video.addEventListener( 'ended', function () { finish(); } );
			video.addEventListener( 'loadedmetadata', function () {
				if ( ! video.videoWidth || ! video.videoHeight ) { return finish( new Error( 'video' ) ); }

				var size = fit( video.videoWidth, video.videoHeight );

				canvas.width = size.w;
				canvas.height = size.h;
				limit = Math.min( CLIP_SECONDS, video.duration || CLIP_SECONDS );

				try {
					ctx.drawImage( video, 0, 0, size.w, size.h );
					ctx.getImageData( 0, 0, 1, 1 ); // Throws on a tainted canvas (file on another origin).
					recorder = new MediaRecorder( canvas.captureStream( 24 ), { mimeType: type, videoBitsPerSecond: 1e6 } );
				} catch ( e ) {
					return finish( new Error( 'unsupported' ) );
				}

				recorder.ondataavailable = function ( e ) { if ( e.data && e.data.size ) { chunks.push( e.data ); } };
				recorder.start( 250 );

				( function draw() {
					ctx.drawImage( video, 0, 0, size.w, size.h );
					progress( ( video.currentTime / limit ) * 100 );

					if ( video.currentTime >= limit ) { return finish(); }

					frame = requestAnimationFrame( draw );
				}() );

				video.play().catch( function () { finish( new Error( 'video' ) ); } );
			} );
			video.src = url;
		} );
	}

	function upload( fileId, blob ) {
		var cfg = cfgNow();

		if ( blob.size > ( cfg.previewMax || 0 ) ) { return Promise.reject( new Error( 'large' ) ); }

		return fetch( cfg.previewUrl + '?file=' + encodeURIComponent( fileId ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': blob.type },
			body: blob
		} ).then( function ( response ) {
			if ( ! response.ok ) { throw new Error( 'upload' ); }

			return response.json();
		} );
	}

	function cancel() {
		if ( current ) {
			current.cancelled = true;
			if ( current.cancel ) { current.cancel(); }
			current = null;
			window.removeEventListener( 'beforeunload', warnLeaving );
		}
	}

	function start( fileId, url ) {
		var cfg = cfgNow();
		var text = textNow();

		if ( ! cfg.previewUrl || ! fileId || ! url ) { return; }

		cancel();

		var run = { cancelled: false, cancel: null };

		current = run;
		window.addEventListener( 'beforeunload', warnLeaving );
		show( text.making, 0 );

		function progress( percent ) {
			if ( ! run.cancelled ) { show( text.making + ' ' + Math.round( percent ) + '%', percent ); }
		}

		viaMediabunny( url, progress, run ).catch( function () {
			if ( run.cancelled ) { throw new Error( 'cancelled' ); }

			progress( 0 );

			return viaRecorder( url, progress, run );
		} ).then( function ( blob ) {
			if ( run.cancelled ) { throw new Error( 'cancelled' ); }

			return upload( fileId, blob ).then( function ( data ) {
				show( data && data.kept ? text.kept : text.ready + ' (' + ( blob.size / 1048576 ).toFixed( 1 ) + ' MB)' );
			} );
		} ).catch( function ( e ) {
			if ( run.cancelled ) { return; }

			show( e && 'unsupported' === e.message ? text.unsupported : text.failed );
		} ).then( function () {
			if ( current === run ) {
				current = null;
				window.removeEventListener( 'beforeunload', warnLeaving );
			}
		} );
	}

	window.cptdpVideoPreview = {
		start: start,
		cancel: function () { cancel(); show( '' ); }
	};
}() );
