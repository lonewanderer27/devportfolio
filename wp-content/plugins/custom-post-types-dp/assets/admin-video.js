/* Video edit screen: media picker. */
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

					$input.val( attachment.id );
					$name.text( attachment.filename || attachment.title );
					$remove.show();
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();

			$input.val( '' );
			$name.text( '' );
			$remove.hide();
		} );
	} );
}( jQuery ) );
