/* Post Statistics repeater, shared by the Document, Collateral and Video edit screens. */
( function ( $ ) {
	'use strict';

	$( function () {
		var $rows = $( '#cptdp-stat-rows' );
		var template = document.getElementById( 'cptdp-stat-template' );

		if ( ! $rows.length || ! template ) {
			return;
		}

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
