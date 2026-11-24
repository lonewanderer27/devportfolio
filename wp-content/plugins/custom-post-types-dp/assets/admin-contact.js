/* Settings > Contact: media picker for the Home page portrait. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_contact_portrait_id' );
		var $preview = $( '#cptdp-portrait-preview' );
		var $remove = $( '#cptdp-portrait-remove' );

		$( '#cptdp_contact_portrait_select' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: cptdpContact.selectTitle,
					button: { text: cptdpContact.selectButton },
					library: { type: [ 'image/png', 'image/webp' ] },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var sizes = attachment.sizes || {};
					var shown = sizes.medium || sizes.full || { url: attachment.url };

					$input.val( attachment.id );
					$preview.attr( 'src', shown.url ).show();
					$remove.show();
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();

			$input.val( '0' );
			$preview.attr( 'src', '' ).hide();
			$remove.hide();
		} );
	} );
}( jQuery ) );
