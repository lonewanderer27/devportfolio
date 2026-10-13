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
			$rows.append( template.innerHTML );
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
