/* Video edit screen: media picker and Post Statistics repeater. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_video_file' );
		var $name = $( '#cptdp-video-file-name' );
		var $remove = $( '#cptdp-video-file-remove' );

		$( '#cptdp-video-file-select' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: cptdpVideo.selectTitle,
					button: { text: cptdpVideo.selectButton },
					library: { type: 'video' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var big = bigNote( attachment.filesizeInBytes );

					// A big file: say so and let Dave back out (it stays in Media, it is just not used here).
					if ( big && ! window.confirm( big + '\n\n' + ( cptdpVideo.bigConfirm || '' ) ) ) {
						return;
					}

					$( '#cptdp-video-size-note' ).text( big );

					$input.val( attachment.id );
					$name.text( attachment.filename || attachment.title );
					$remove.show();
					makePoster( attachment.id, attachment.url );
				} );
			}

			frame.open();
		} );

		// Manual preview clip (DPV-040): the media picker sets the attachment ID; Remove sets 0 (saved as "none").
		var $pick = $( '#cptdp_video_preview_pick' );
		var $pickName = $( '#cptdp-preview-pick-name' );
		var $pickRemove = $( '#cptdp-preview-pick-remove' );

		$( '#cptdp-preview-pick-select' ).on( 'click', function ( event ) {
			event.preventDefault();
			var frame = wp.media( {
				title: cptdpVideo.previewTitle,
				button: { text: cptdpVideo.selectButton },
				library: { type: 'video' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var clip = frame.state().get( 'selection' ).first().toJSON();
				$pick.val( clip.id );
				$pickName.text( clip.filename || clip.title || '' );
				$pickRemove.show();
			} );
			frame.open();
		} );

		$pickRemove.on( 'click', function ( event ) {
			event.preventDefault();
			$pick.val( '0' );
			$pickName.text( '' );
			$pickRemove.hide();
		} );

		function bigNote( bytes ) {
			if ( ! bytes || ! cptdpVideo.warnBytes || bytes < cptdpVideo.warnBytes || ! cptdpVideo.bigNote ) { return ''; }

			var size = bytes >= 1048576 ? ( bytes / 1048576 ).toFixed( 0 ) + ' MB' : Math.round( bytes / 1024 ) + ' KB';

			return cptdpVideo.bigNote.replace( '%s', size );
		}

		$remove.on( 'click', function ( event ) {
			event.preventDefault();
			$( '#cptdp-video-size-note' ).text( '' );

			latest = null;

			if ( window.cptdpVideoPreview ) { window.cptdpVideoPreview.cancel(); }

			$input.val( '' );
			$name.text( '' );
			$remove.hide();
		} );

		// DPV-041: draw a frame of the chosen video and send it as the thumbnail.
		var running = 0;
		var latest = null;

		// D15: the thumbnail frame first, then the preview clip (never both at once).
		function startPreview() {
			if ( latest && window.cptdpVideoPreview ) {
				window.cptdpVideoPreview.start( latest.fileId, latest.url );
			}
		}

		$( '<p class="description" id="cptdp-video-poster-note" hidden></p>' ).insertAfter( '#cptdp-video-file-name' );

		function say( text ) {
			$( '#cptdp-video-poster-note' ).text( text || '' ).prop( 'hidden', ! text );
		}

		function fit( w, h, max ) {
			var scale = Math.min( 1, max / Math.max( w, h ) );

			return { w: Math.round( w * scale ), h: Math.round( h * scale ) };
		}

		function capture( url ) {
			return new Promise( function ( resolve, reject ) {
				var video = document.createElement( 'video' );
				var timer = setTimeout( function () { reject( new Error( 'timeout' ) ); }, 20000 );

				function fail( e ) { clearTimeout( timer ); reject( e || new Error( 'video' ) ); }

				video.muted = true;
				video.preload = 'auto';
				video.playsInline = true;
				video.addEventListener( 'error', fail );
				video.addEventListener( 'loadedmetadata', function () {
					if ( ! video.videoWidth || ! video.videoHeight ) { return fail(); }
					video.currentTime = Math.min( 1, ( video.duration || 0 ) / 2 );
				} );
				video.addEventListener( 'seeked', function () {
					var size = fit( video.videoWidth, video.videoHeight, 1280 );
					var canvas = document.createElement( 'canvas' );

					canvas.width = size.w;
					canvas.height = size.h;
					canvas.getContext( '2d' ).drawImage( video, 0, 0, size.w, size.h );

					try {
						canvas.toBlob( function ( blob ) {
							clearTimeout( timer );
							blob ? resolve( blob ) : reject( new Error( 'blob' ) );
						}, 'image/jpeg', 0.82 );
					} catch ( e ) {
						fail( e ); // a tainted canvas (file on another origin)
					}
				} );
				video.src = url;
			} );
		}

		function makePoster( fileId, url ) {
			latest = { fileId: fileId, url: url };

			if ( ! cptdpVideo.posterUrl || ! url ) { return; }

			if ( running ) { return; } // The run in progress starts the preview for `latest` when it ends.

			running++;
			say( '' );

			capture( url ).then( function ( blob ) {
				return fetch( cptdpVideo.posterUrl + '?file=' + encodeURIComponent( fileId ), {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': cptdpVideo.nonce, 'Content-Type': blob.type },
					body: blob
				} );
			} ).then( function ( response ) {
				if ( ! response.ok ) { throw new Error( 'upload' ); }
				return response.json();
			} ).then( function ( data ) {
				if ( data && ! data.kept && data.id && window.wp && wp.media && wp.media.featuredImage ) {
					wp.media.featuredImage.set( data.id ); // keeps the form's _thumbnail_id in step
				}
			} ).catch( function () {
				say( cptdpVideo.failed );
			} ).then( function () { running--; startPreview(); } );
		}

		if ( cptdpVideo.needsPoster ) {
			makePoster( cptdpVideo.fileId, cptdpVideo.fileUrl );
		} else if ( cptdpVideo.previewNeeded ) {
			latest = { fileId: cptdpVideo.fileId, url: cptdpVideo.fileUrl };
			startPreview();
		}

		// Post Statistics repeater.
		var $rows = $( '#cptdp-stat-rows' );
		var template = document.getElementById( 'cptdp-stat-template' );

		function reindex() {
			$rows.children( '.cptdp-stat-row' ).each( function ( index ) {
				$( this ).find( 'input' ).each( function () {
					this.name = this.name.replace( /cptdp_post_statistics\[[^\]]*\]/, 'cptdp_post_statistics[' + index + ']' );
				} );
			} );
		}

		$( '#cptdp-stat-add' ).on( 'click', function () {
			var html = template.innerHTML;

			$rows.append( html );
			reindex();
			$rows.children().last().find( 'input' ).first().trigger( 'focus' );
		} );

		$rows.on( 'click', '.cptdp-stat-remove', function () {
			$( this ).closest( '.cptdp-stat-row' ).remove();
			reindex();
		} );

		$( '#post' ).on( 'submit', reindex );
	} );
}( jQuery ) );
