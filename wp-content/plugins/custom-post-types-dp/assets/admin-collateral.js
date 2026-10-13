/* Collateral edit screen: media picker and Post Statistics repeater. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_collateral_file' );
		var $name = $( '#cptdp-collateral-file-name' );
		var $remove = $( '#cptdp-collateral-file-remove' );

		$( '#cptdp-collateral-file-select' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: cptdpCollateral.selectTitle,
					button: { text: cptdpCollateral.selectButton },
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
