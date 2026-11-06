<?php
/**
 * Word-processing validation and LibreOffice Writer settings.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates DOCX, DOC and ODT files before Writer conversion.
 */
class CPTDP_Docx_Converter {

	const MAX_BYTES   = 26214400;
	const ODT_MIMETYPE = 'application/vnd.oasis.opendocument.text';

	public static function writer_installed(): bool {
		static $found = array();
		$binary = CPTDP_Pptx_Converter::binary();

		if ( null === $binary ) {
			return false;
		}

		if ( ! isset( $found[ $binary ] ) ) {
			$real = realpath( $binary );
			$found[ $binary ] = array() !== glob( dirname( false !== $real ? $real : $binary ) . '/libswlo.*' );
		}

		return $found[ $binary ];
	}

	public static function max_bytes(): int {
		return max( 1024, (int) apply_filters( 'cptdp_docx_max_bytes', self::MAX_BYTES ) );
	}

	public static function export_filter(): string {
		// Bookmarks from the headings are what gives the Pages list its names (DPB-030).
		return 'pdf:writer_pdf_Export:{"ExportBookmarks":{"type":"boolean","value":"true"}}';
	}

	public static function write_profile( string $profile ): void {
		$user = $profile . '/user';

		if ( ! wp_mkdir_p( $user ) ) {
			return;
		}

		$items = array(
			array( 'org.openoffice.Office.Writer/Content/Update', 'Link', 'int', '2' ),
			array( 'org.openoffice.Office.Writer/Content/Update', 'Fields', 'boolean', 'false' ),
			array( 'org.openoffice.Office.Common/Security/Scripting', 'MacroSecurityLevel', 'int', '3' ),
			array( 'org.openoffice.Office.Common/Security/Scripting', 'DisableMacrosExecution', 'boolean', 'true' ),
		);
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<oor:items xmlns:oor="http://openoffice.org/2001/registry" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n";

		foreach ( $items as $item ) {
			$xml .= '<item oor:path="/' . $item[0] . '"><prop oor:name="' . $item[1] . '" oor:op="fuse"><value>' . $item[3] . '</value></prop></item>' . "\n";
		}

		$xml .= '</oor:items>' . "\n";
		file_put_contents( $user . '/registrymodifications.xcu', $xml ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
	}

	public static function unavailable_reason( string $kind = '' ): string {
		if ( null === CPTDP_Pptx_Converter::binary() ) {
			return __( 'This server cannot convert Word files (LibreOffice is not installed). Export the document to PDF in Word and upload the PDF instead.', 'custom-post-types-dp' );
		}

		if ( ! CPTDP_Page_Converter::is_available() ) {
			return __( 'This server cannot draw PDF pages (no Imagick or Ghostscript). Export the document to PDF in Word and upload the PDF instead.', 'custom-post-types-dp' );
		}

		return __( 'This server cannot convert Word files (LibreOffice Writer is not installed). Export the document to PDF and upload the PDF instead.', 'custom-post-types-dp' );
	}

	public static function inspect( string $path, string $kind ) {
		if ( ! CPTDP_Page_Store::is_word_kind( $kind ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::invalid();
		}

		$size = (int) filesize( $path );
		if ( $size < 1 || $size > self::max_bytes() ) {
			return new WP_Error( 'cptdp_docx_too_large', sprintf( __( 'The word-processing file is larger than %s. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ), size_format( self::max_bytes() ) ) );
		}

		$head = (string) file_get_contents( $path, false, null, 0, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( CPTDP_Page_Store::KIND_DOC === $kind ) {
			$status = CPTDP_Pptx_Converter::ole_status( $path, $head );
			if ( 'macros' === $status || ( 'ok' === $status && self::contains_macro_storage( $path ) ) ) {
				return self::macros();
			}
			return 'ok' === $status ? true : self::invalid();
		}

		if ( 0 !== strpos( $head, "PK\x03\x04" ) ) {
			return self::invalid();
		}

		$zip = CPTDP_Pptx_Converter::open_zip( $path );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		$names = CPTDP_Pptx_Converter::zip_names( $zip );

		if ( CPTDP_Page_Store::KIND_ODT === $kind ) {
			$valid = ( $names[0] ?? '' ) === 'mimetype'
				&& trim( (string) $zip->getFromName( 'mimetype', 256 ) ) === self::ODT_MIMETYPE
				&& in_array( 'content.xml', $names, true );
			if ( ! $valid ) {
				$zip->close();
				return self::invalid();
			}
			foreach ( $names as $name ) {
				if ( 0 === strpos( $name, 'Basic/' ) || 0 === strpos( $name, 'Scripts/' ) ) {
					$zip->close();
					return self::macros();
				}
			}
			$content = (string) $zip->getFromName( 'content.xml', CPTDP_Pptx_Converter::MAX_XML_BYTES );
			$zip->close();
			return CPTDP_Pptx_Converter::odf_has_external_object( $content, 'draw:(?:object|object-ole|plugin)|text:(?:section-source|bibliography-source)' ) ? self::external() : true;
		}

		$types = (string) $zip->getFromName( '[Content_Types].xml', CPTDP_Pptx_Converter::MAX_XML_BYTES );
		if ( ! in_array( '[Content_Types].xml', $names, true ) || ! in_array( 'word/document.xml', $names, true ) ) {
			$zip->close();
			return self::invalid();
		}
		if ( in_array( 'word/vbaProject.bin', $names, true ) || false !== stripos( $types, 'macroEnabled' ) || false !== stripos( $types, 'vbaProject' ) ) {
			$zip->close();
			return self::macros();
		}

		foreach ( $names as $name ) {
			if ( '.rels' === substr( $name, -5 ) && CPTDP_Pptx_Converter::rels_have_external_object( (string) $zip->getFromName( $name, CPTDP_Pptx_Converter::MAX_XML_BYTES ), array( '/attachedtemplate', '/subdocument', '/mailmergesource' ) ) ) {
				$zip->close();
				return self::external();
			}
		}

		foreach ( $names as $name ) {
			if ( preg_match( '#^word/(?:document|header|footer)[^/]*\.xml$#', $name ) ) {
				$xml = (string) $zip->getFromName( $name, CPTDP_Pptx_Converter::MAX_XML_BYTES );
				if ( preg_match( '#<(?:w:instrText|w:fldSimple)\b[^>]*>.*?\b(?:INCLUDETEXT|INCLUDEPICTURE|DDEAUTO?|LINK)\b#is', $xml ) ) {
					$zip->close();
					return self::external();
				}
			}
		}
		$settings = (string) $zip->getFromName( 'word/settings.xml', CPTDP_Pptx_Converter::MAX_XML_BYTES );
		$zip->close();
		return false !== stripos( $settings, 'mailMerge' ) ? self::external() : true;
	}

	private static function contains_macro_storage( string $path ): bool {
		$contents = (string) file_get_contents( $path, false, null, 0, 10485760 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false !== stripos( $contents, 'Macros' );
	}

	private static function invalid(): WP_Error {
		return new WP_Error( 'cptdp_docx_invalid', __( 'The file is not a valid word-processing document. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	private static function macros(): WP_Error {
		return new WP_Error( 'cptdp_docx_macros', __( 'The word-processing file contains macros and will not be converted. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	private static function external(): WP_Error {
		return new WP_Error( 'cptdp_docx_external', __( 'This word-processing file links to external content and cannot be converted safely. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}
}
