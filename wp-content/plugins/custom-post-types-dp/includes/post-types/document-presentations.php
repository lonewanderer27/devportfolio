<?php
/**
 * Document presentations (PPTX, PPT, ODP): the editor warning and the native viewer URL (DPB-026).
 *
 * The files are converted to page images by the server (see CPTDP_Pptx_Converter). The conversion is
 * never perfect, so editors are warned when they pick one and told that a PDF export is best.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a presentation kind can be drawn by the native viewer (`.pptx` only: the viewer reads
 * neither the legacy `.ppt` nor `.odp` format).
 *
 * @param string $kind `pptx`, `ppt` or `odp`.
 */
function cptdp_presentation_kind_has_native_viewer( string $kind ): bool {
	return CPTDP_Page_Store::KIND_PPTX === $kind;
}

/**
 * The warning shown to an editor for a presentation file, as HTML for the current user.
 *
 * For `pptx` it ends with a link to the Native viewer setting (or, when that setting is on, a
 * status line), only for users who can open the settings page. `ppt` and `odp` get the warning
 * without that sentence (the native viewer cannot read them).
 *
 * @param string $kind `pptx`, `ppt` or `odp`.
 * @return string Safe HTML, '' for any other kind.
 */
function cptdp_pptx_warning( string $kind ): string {
	if ( ! CPTDP_Page_Store::is_presentation_kind( $kind ) ) {
		return '';
	}

	if ( CPTDP_Page_Store::KIND_ODP === $kind ) {
		$first = '<strong>' . esc_html__( 'OpenDocument presentations are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> '
			. esc_html__( '(fonts, spacing, animations and some effects can differ). For the best result, export the presentation to PDF in LibreOffice (File → Export as PDF) and upload that PDF instead.', 'custom-post-types-dp' );
	} else {
		$first = '<strong>' . esc_html__( 'PowerPoint files are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> '
			. esc_html__( '(fonts, spacing, animations and some effects can differ). For the best result, export the presentation to PDF in PowerPoint (File → Save As → PDF) and upload that PDF instead.', 'custom-post-types-dp' );
	}

	return cptdp_office_warning_html(
		$first,
		$kind,
		cptdp_presentation_kind_has_native_viewer( $kind )
			? array(
				'on'     => __( 'The native viewer is on for PowerPoint files: visitors see this presentation drawn in their own browser, with selectable text (the converted slides are the fallback).', 'custom-post-types-dp' ),
				'before' => __( 'Can\'t install LibreOffice, or want selectable text and a closer match to the original? You can', 'custom-post-types-dp' ),
				'link'   => __( 'let the native viewer draw PowerPoint files', 'custom-post-types-dp' ),
				'after'  => __( 'instead (visitors then see the presentation drawn in their own browser, which is slower to open; nothing is sent to anyone).', 'custom-post-types-dp' ),
			)
			: array()
	);
}

/**
 * The warnings for every presentation kind, keyed by kind (for the media picker script).
 *
 * @return array<string,string>
 */
function cptdp_pptx_warnings(): array {
	$out = array();

	foreach ( array( CPTDP_Page_Store::KIND_PPTX, CPTDP_Page_Store::KIND_PPT, CPTDP_Page_Store::KIND_ODP ) as $kind ) {
		$out[ $kind ] = cptdp_pptx_warning( $kind );
	}

	return $out;
}

if ( ! function_exists( 'cptdp_document_native_pptx_url' ) ) :
	/**
	 * URL of the `.pptx` the native viewer should draw in the visitor's browser, or '' (see cptdp_native_office_url(); 30 MB).
	 *
	 * @param int $post_id Document ID.
	 */
	function cptdp_document_native_pptx_url( int $post_id ): string {
		return cptdp_native_office_url( $post_id, CPTDP_Page_Store::KIND_PPTX, 'cptdp_native_pptx_max_bytes', 30 * MB_IN_BYTES );
	}
endif;

/**
 * Remember, for the editor who just changed the file, that it is a presentation (shown once after saving).
 *
 * @param int|int[] $meta_id    Meta ID(s).
 * @param int       $post_id    Post ID.
 * @param string    $meta_key   Meta key.
 * @param mixed     $meta_value New value.
 */
function cptdp_pptx_remember_change( $meta_id, $post_id, $meta_key, $meta_value = null ): void {
	unset( $meta_id );

	if ( 'cptdp_document_file' !== $meta_key || ! is_user_logged_in() || 'document' !== get_post_type( (int) $post_id ) ) {
		return;
	}

	$kind = CPTDP_Page_Store::kind_of_attachment( (int) $meta_value );

	if ( CPTDP_Page_Store::is_office_kind( $kind ) ) {
		set_transient( 'cptdp_pptx_notice_' . get_current_user_id() . '_' . (int) $post_id, $kind, HOUR_IN_SECONDS );
	}
}
add_action( 'added_post_meta', 'cptdp_pptx_remember_change', 10, 4 );
add_action( 'updated_post_meta', 'cptdp_pptx_remember_change', 10, 4 );

/**
 * Show the one-time warning on the edit screen of a document whose file just changed to a presentation.
 */
function cptdp_pptx_after_save_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'document' !== $screen->post_type || 'post' !== $screen->base || ! is_user_logged_in() ) {
		return;
	}

	$post_id = (int) get_the_ID();
	$key     = 'cptdp_pptx_notice_' . get_current_user_id() . '_' . $post_id;
	$kind    = get_transient( $key );

	if ( ! is_string( $kind ) || '' === $kind ) {
		return;
	}

	delete_transient( $key );

	echo cptdp_office_warning( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
}
add_action( 'admin_notices', 'cptdp_pptx_after_save_notice' );

/**
 * Warning box for the Document File and Page images meta boxes: the saved file's warning, or an
 * empty hidden container the media picker script fills.
 *
 * @param int $post_id Document ID.
 */
function cptdp_pptx_warning_container( int $post_id ): string {
	$kind = CPTDP_Page_Store::source_kind( $post_id );
	$html = cptdp_office_warning( $kind );

	return '<div id="cptdp-pptx-warning" ' . ( '' === $html ? 'hidden' : '' ) . '>' . $html . '</div>';
}
