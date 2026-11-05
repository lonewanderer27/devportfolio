/* Document edit screen: media picker and Post Statistics repeater. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#cptdp_document_file' );
		var $name = $( '#cptdp-document-file-name' );
		var $remove = $( '#cptdp-document-file-remove' );
		var $warning = $( '#cptdp-pptx-warning' );

		/* Picking or uploading a presentation shows the "may be imperfect, export to PDF" warning at
		   once (before saving); picking anything else, or removing the file, hides it again. The
		   HTML comes from the server (escaped, and without the settings link for users who cannot use it). */
		function presentationKind( attachment ) {
			var name = String( ( attachment && ( attachment.filename || attachment.url ) ) || '' ).toLowerCase().split( '?' )[ 0 ];
			var match = /\.(pptx|ppt|odp|xlsx|xls|ods)$/.exec( name );

			return match ? match[ 1 ] : '';
		}

		function showPresentationWarning( attachment ) {
			var kind = presentationKind( attachment );
			var html = kind && cptdpDocument.pptxWarnings ? cptdpDocument.pptxWarnings[ kind ] : '';

			$warning.html( html || '' ).prop( 'hidden', ! html );
		}

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
					showPresentationWarning( attachment );
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();

			$input.val( '' );
			$name.text( '' );
			$remove.hide();
			showPresentationWarning( null );
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
