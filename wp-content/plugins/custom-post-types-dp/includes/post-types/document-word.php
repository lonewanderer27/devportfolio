<?php
/**
 * Document word-processing files (DOCX, DOC, ODT).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The warning shown when a word-processing file is selected.
 *
 * @param string $kind File kind.
 * @return string Safe HTML, or an empty string for other kinds.
 */
function cptdp_docx_warning( string $kind ): string {
	if ( ! CPTDP_Page_Store::is_word_kind( $kind ) ) {
		return '';
	}

	$first = CPTDP_Page_Store::KIND_ODT === $kind
		? '<strong>' . esc_html__( 'OpenDocument text files are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> ' . esc_html__( '(fonts, page breaks, fields and complex layouts can differ). For the best result, export the document to PDF in LibreOffice (File → Export as PDF) and upload that PDF instead.', 'custom-post-types-dp' )
		: '<strong>' . esc_html__( 'Word files are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> ' . esc_html__( '(fonts, page breaks, fields, tracked changes and complex layouts can differ, and the page count may change). For the best result, export the document to PDF (File → Save As → PDF) and upload that PDF instead.', 'custom-post-types-dp' );

	return cptdp_office_warning_html(
		$first,
		$kind,
		CPTDP_Page_Store::KIND_DOCX === $kind
			? array(
				'on'     => __( 'The native viewer is on for Word files: visitors see selectable text drawn in their own browser; the converted pages are the fallback. The whole file, including comments, tracked changes and hidden text, is downloaded.', 'custom-post-types-dp' ),
				'before' => __( 'Want selectable text and a closer match to the original? You can', 'custom-post-types-dp' ),
				'link'   => __( 'let the native viewer draw Word files', 'custom-post-types-dp' ),
				'after'  => __( 'instead (it is slower to open and visitors download the whole file; nothing is sent to anyone).', 'custom-post-types-dp' ),
			)
			: array()
	);
}

/**
 * URL of the `.docx` the native viewer should draw, or an empty string.
 *
 * @param int $post_id Document ID.
 */
function cptdp_document_native_docx_url( int $post_id ): string {
	return cptdp_native_office_url( $post_id, CPTDP_Page_Store::KIND_DOCX, 'cptdp_native_docx_max_bytes', 10 * MB_IN_BYTES );
}

/**
 * Remember a word-file change for the one-time admin notice.
 *
 * @param int|int[] $meta_id Meta ID(s).
 * @param int       $post_id Post ID.
 * @param string    $meta_key Meta key.
 * @param mixed     $meta_value New value.
 */
function cptdp_docx_remember_change( $meta_id, $post_id, $meta_key, $meta_value = null ): void {
	unset( $meta_id );

	if ( 'cptdp_document_file' !== $meta_key || ! is_user_logged_in() || 'document' !== get_post_type( (int) $post_id ) ) {
		return;
	}

	$kind = CPTDP_Page_Store::kind_of_attachment( (int) $meta_value );

	if ( CPTDP_Page_Store::is_word_kind( $kind ) ) {
		set_transient( 'cptdp_docx_notice_' . get_current_user_id() . '_' . (int) $post_id, $kind, HOUR_IN_SECONDS );
	}
}
add_action( 'added_post_meta', 'cptdp_docx_remember_change', 10, 4 );
add_action( 'updated_post_meta', 'cptdp_docx_remember_change', 10, 4 );

/**
 * Show the one-time warning after saving.
 */
function cptdp_docx_after_save_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'document' !== $screen->post_type || 'post' !== $screen->base || ! is_user_logged_in() ) {
		return;
	}

	$post_id = (int) get_the_ID();
	$key     = 'cptdp_docx_notice_' . get_current_user_id() . '_' . $post_id;
	$kind    = get_transient( $key );

	if ( ! is_string( $kind ) || '' === $kind ) {
		return;
	}

	delete_transient( $key );
	echo cptdp_office_warning( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'admin_notices', 'cptdp_docx_after_save_notice' );
