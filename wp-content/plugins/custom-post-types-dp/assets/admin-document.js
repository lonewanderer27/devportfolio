/* Document edit screen: media picker. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_document_file' );
		var $name = $( '#cptdp-document-file-name' );
		var $remove = $( '#cptdp-document-file-remove' );

		$( '#cptdp-document-file-select' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: cptdpDocument.selectTitle,
					button: { text: cptdpDocument.selectButton },
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
