<?php
/**
 * Document spreadsheets (XLSX, XLS, ODS): the editor warning and the native viewer URL (DPB-027).
 *
 * The files are converted to page images by the server (LibreOffice Calc, see CPTDP_Pptx_Converter
 * and CPTDP_Xlsx_Converter). A spreadsheet is a poor fit for pages, so editors are warned when they
 * pick one and told that a PDF export is best.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The warning shown to an editor for a spreadsheet file, as HTML for the current user.
 *
 * For `xlsx` it ends with a link to the Native viewer setting (or a status line when it is on), only
 * for users who can open the settings page. `xls` and `ods` get the warning without that sentence.
 *
 * @param string $kind `xlsx`, `xls` or `ods`.
 * @return string Safe HTML, '' for any other kind.
 */
function cptdp_xlsx_warning( string $kind ): string {
	if ( ! CPTDP_Page_Store::is_spreadsheet_kind( $kind ) ) {
		return '';
	}

	if ( CPTDP_Page_Store::KIND_ODS === $kind ) {
		$first = '<strong>' . esc_html__( 'OpenDocument spreadsheets are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> '
			. esc_html__( '(each sheet becomes one page, so very wide or long sheets are shrunk, and some formatting and charts can differ). For the best result, export the spreadsheet to PDF in LibreOffice (File → Export as PDF) and upload that PDF instead.', 'custom-post-types-dp' );
	} else {
		$first = '<strong>' . esc_html__( 'Excel files are converted automatically and the result may be imperfect', 'custom-post-types-dp' ) . '</strong> '
			. esc_html__( '(each sheet becomes one page, so very wide or long sheets are shrunk, and some formatting, charts and formulas can differ). For the best result, export the workbook to PDF in Excel (File → Save As → PDF) and upload that PDF instead.', 'custom-post-types-dp' );
	}

	return cptdp_office_warning_html(
		$first . ' ' . esc_html__( 'Everything on the visible sheets is published.', 'custom-post-types-dp' ),
		$kind,
		CPTDP_Page_Store::KIND_XLSX === $kind
			? array(
				'on'     => __( 'The native viewer is on for Excel files: visitors see this workbook drawn in their own browser, where they can scroll, zoom and copy cells (the converted pages are the fallback). They download the whole file, including hidden sheets.', 'custom-post-types-dp' ),
				'before' => __( 'Can\'t install LibreOffice, or want visitors to scroll and zoom the sheets? You can', 'custom-post-types-dp' ),
				'link'   => __( 'let the native viewer draw Excel files', 'custom-post-types-dp' ),
				'after'  => __( 'instead (visitors then see the workbook drawn in their own browser, which is slower to open and downloads the whole file, hidden sheets included; nothing is sent to anyone).', 'custom-post-types-dp' ),
			)
			: array()
	);
}

if ( ! function_exists( 'cptdp_document_native_xlsx_url' ) ) :
	/**
	 * URL of the `.xlsx` the native viewer should draw in the visitor's browser, or '' (see cptdp_native_office_url(); 15 MB).
	 *
	 * @param int $post_id Document ID.
	 */
	function cptdp_document_native_xlsx_url( int $post_id ): string {
		return cptdp_native_office_url( $post_id, CPTDP_Page_Store::KIND_XLSX, 'cptdp_native_xlsx_max_bytes', 15 * MB_IN_BYTES );
	}
endif;
