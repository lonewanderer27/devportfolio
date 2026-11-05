<?php
/**
 * Behaviour shared by every LibreOffice-converted document kind (presentations, DPB-026, and
 * spreadsheets, DPB-027): the editor warning chrome and the native viewer URL rules.
 *
 * The per-family wording lives in document-presentations.php and document-spreadsheets.php.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL of the Settings > Documents > Native viewer section.
 */
function cptdp_native_viewer_settings_url(): string {
	return admin_url( 'options-general.php?page=cptdp-documents#cptdp-native-viewer' );
}

/**
 * Whether the Native viewer setting is on for a file kind (`pdf`, `pptx`, `xlsx`; any other kind has no switch).
 *
 * @param string $kind File kind.
 */
function cptdp_native_viewer_enabled( string $kind ): bool {
	return in_array( $kind, cptdp_documents_native_formats(), true ) && (bool) cptdp_get_documents_settings( 'native_' . $kind );
}

/**
 * The warning for any Office kind (presentation or spreadsheet), '' for anything else.
 *
 * @param string $kind File kind.
 */
function cptdp_office_warning( string $kind ): string {
	return CPTDP_Page_Store::is_spreadsheet_kind( $kind ) ? cptdp_xlsx_warning( $kind ) : cptdp_pptx_warning( $kind );
}

/**
 * The warnings for every Office kind, keyed by kind (for the media picker script).
 *
 * @return array<string,string>
 */
function cptdp_office_warnings(): array {
	$out = array();

	foreach ( array( 'pptx', 'ppt', 'odp', 'xlsx', 'xls', 'ods' ) as $kind ) {
		$out[ $kind ] = cptdp_office_warning( $kind );
	}

	return $out;
}

/**
 * The warning box: the family's first paragraph, then (for a kind the native viewer can draw, and
 * only for users who can open the settings page) a second paragraph with a link to the setting.
 *
 * @param string               $first  Safe HTML of the first paragraph.
 * @param string               $kind   File kind.
 * @param array<string,string> $native Wording of the native viewer paragraph, or empty for no paragraph: `on` (sentence
 *                                     when the setting is on), `before` and `after` (around the link when it is off), `link`.
 * @return string Safe HTML.
 */
function cptdp_office_warning_html( string $first, string $kind, array $native = array() ): string {
	$html = '<p>' . $first . '</p>';

	if ( array() !== $native && current_user_can( 'manage_options' ) ) {
		$on   = cptdp_native_viewer_enabled( $kind );
		$link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( cptdp_native_viewer_settings_url() ),
			$on ? esc_html__( 'Change this setting', 'custom-post-types-dp' ) : esc_html( $native['link'] )
		);

		$html .= '<p class="cptdp-native-viewer-note">'
			. ( $on ? esc_html( $native['on'] ) . ' ' . $link : esc_html( $native['before'] ) . ' ' . $link . ' ' . esc_html( $native['after'] ) )
			. '</p>';
	}

	return '<div class="notice notice-warning inline cptdp-pptx-warning">' . $html . '</div>';
}

/**
 * The URL of the file the native viewer should draw in the visitor's browser, or ''.
 *
 * Only when the kind's Native viewer setting is on, the document's file is of that kind, and it is
 * within the size limit. It takes over from the converted pages (they stay as the fallback).
 * Never makes a request.
 *
 * @param int    $post_id Document ID.
 * @param string $kind    `pptx` or `xlsx` (the kinds the bundled viewer can read).
 * @param string $filter  Filter that sets the largest file, in bytes.
 * @param int    $bytes   Default size limit.
 */
function cptdp_native_office_url( int $post_id, string $kind, string $filter, int $bytes ): string {
	if ( ! cptdp_native_viewer_enabled( $kind ) ) {
		return '';
	}

	$attachment_id = CPTDP_Page_Store::source_attachment_id( $post_id );

	if ( $attachment_id < 1 || CPTDP_Page_Store::kind_of_attachment( $attachment_id ) !== $kind ) {
		return '';
	}

	$path = (string) get_attached_file( $attachment_id );
	$url  = wp_get_attachment_url( $attachment_id );

	/**
	 * Filters the largest file (bytes) the visitor's browser is asked to draw.
	 *
	 * @param int $bytes Default size limit.
	 */
	$limit = (int) apply_filters( $filter, $bytes );

	if ( ! is_string( $url ) || '' === $url || ! is_file( $path ) || (int) filesize( $path ) > $limit ) {
		return '';
	}

	return $url;
}
