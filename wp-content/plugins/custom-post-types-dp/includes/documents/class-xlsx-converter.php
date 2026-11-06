<?php
/**
 * Spreadsheet (XLSX, XLS, ODS) checks and LibreOffice Calc settings (DPB-027).
 *
 * The conversion itself runs in CPTDP_Pptx_Converter::to_pdf(), which is shared by every
 * LibreOffice file kind (one soffice at a time, a throwaway profile, a timeout). This class
 * holds what is specific to spreadsheets: the content checks, the Calc export filter and the
 * profile settings that stop Calc from updating links or running macros.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates spreadsheets and configures Calc for a safe conversion.
 */
class CPTDP_Xlsx_Converter {

	const MAX_BYTES = 26214400; // 25 MB (filter `cptdp_xlsx_max_bytes`).

	const ODS_MIMETYPE = 'application/vnd.oasis.opendocument.spreadsheet';

	/**
	 * Relationship types refused on top of CPTDP_Pptx_Converter::EXTERNAL_REL_TYPES (data connections).
	 */
	const EXTRA_REL_TYPES = array( '/connections' );

	/**
	 * ODS elements whose `xlink:href` must stay inside the package.
	 */
	const ODS_LINKED_ELEMENTS = 'draw:(?:object|object-ole|plugin)|table:(?:table|cell-range-source)';

	/**
	 * Whether LibreOffice Calc (not just Impress) is installed next to the soffice binary.
	 */
	public static function calc_installed(): bool {
		static $found = array();

		$binary = CPTDP_Pptx_Converter::binary();

		if ( null === $binary ) {
			return false;
		}

		if ( ! isset( $found[ $binary ] ) ) {
			$real             = realpath( $binary );
			$found[ $binary ] = array() !== glob( dirname( false !== $real ? $real : $binary ) . '/libsclo.*' );
		}

		return $found[ $binary ];
	}

	/**
	 * Why spreadsheet conversion is unavailable, for the admin.
	 */
	public static function unavailable_reason(): string {
		if ( null === CPTDP_Pptx_Converter::binary() ) {
			return __( 'This server cannot convert Excel files (LibreOffice is not installed). Export the workbook to PDF in Excel and upload the PDF instead.', 'custom-post-types-dp' );
		}

		if ( ! CPTDP_Page_Converter::is_available() ) {
			return __( 'This server cannot draw PDF pages (no Imagick or Ghostscript). Export the workbook to PDF in Excel and upload the PDF instead.', 'custom-post-types-dp' );
		}

		return __( 'This server cannot convert Excel files (LibreOffice Calc is not installed). Export the workbook to PDF in Excel and upload the PDF instead.', 'custom-post-types-dp' );
	}

	/**
	 * Largest spreadsheet accepted, in bytes (filter `cptdp_xlsx_max_bytes`).
	 */
	public static function max_bytes(): int {
		return max( 1024, (int) apply_filters( 'cptdp_xlsx_max_bytes', self::MAX_BYTES ) );
	}

	/**
	 * The PDF export filter with its options: one page per sheet by default
	 * (filter `cptdp_xlsx_single_page_sheets`, false follows each sheet's print setup).
	 */
	public static function export_filter(): string {
		if ( ! apply_filters( 'cptdp_xlsx_single_page_sheets', true ) ) {
			return 'pdf:calc_pdf_Export';
		}

		return 'pdf:calc_pdf_Export:{"SinglePageSheets":{"type":"boolean","value":"true"}}';
	}

	/**
	 * Names of the visible sheets, in order (DPB-030): what the Pages list shows for a workbook.
	 *
	 * Hidden and very hidden sheets are skipped (they are not drawn, so they are not pages). Only
	 * the one XML entry that lists the sheets is read, as a stream, without extracting the archive.
	 * `.xls` (a binary file) has no cheap reader and gives no names. Any problem gives an empty list.
	 *
	 * @param string $path File path.
	 * @param string $kind `xlsx`, `xls` or `ods`.
	 * @return string[]
	 */
	public static function sheet_names( string $path, string $kind ): array {
		if ( ! class_exists( 'XMLReader' ) || ! class_exists( 'ZipArchive' ) || ! is_file( $path ) || ! in_array( $kind, array( CPTDP_Page_Store::KIND_XLSX, CPTDP_Page_Store::KIND_ODS ), true ) ) {
			return array();
		}

		$entry = CPTDP_Page_Store::KIND_XLSX === $kind ? 'xl/workbook.xml' : 'content.xml';
		$zip   = new ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array();
		}

		$stat = $zip->statName( $entry );
		$zip->close();

		if ( ! is_array( $stat ) || $stat['size'] > 512 * 1024 * 1024 ) {
			return array();
		}

		$names    = array();
		$previous = libxml_use_internal_errors( true );
		$reader   = new XMLReader();

		try {
			if ( ! $reader->open( 'zip://' . $path . '#' . $entry, null, LIBXML_NONET | LIBXML_NOCDATA ) ) {
				return array();
			}

			$nodes       = 0;
			$sheet_depth = -1;
			$started     = microtime( true );
			$office      = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
			$table_ns    = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
			$more        = $reader->read();

			while ( $more ) {
				if ( ++$nodes > 2000000 || microtime( true ) - $started > 10 ) {
					return array();
				}

				if ( XMLReader::ELEMENT !== $reader->nodeType ) {
					$more = $reader->read();
					continue;
				}

				if ( CPTDP_Page_Store::KIND_XLSX === $kind ) {
					if ( 'sheet' === $reader->localName ) {
						$state = (string) $reader->getAttribute( 'state' );

						if ( '' === $state || 'visible' === $state ) {
							$names[] = CPTDP_Page_Outline::clean_title( (string) $reader->getAttribute( 'name' ) );
						}
					}

					$more = $reader->read();
					continue;
				}

				// ODS: the tables that are direct children of office:spreadsheet; their cells are skipped.
				if ( 'spreadsheet' === $reader->localName && $office === $reader->namespaceURI ) {
					$sheet_depth = $reader->depth;
				} elseif ( 'table' === $reader->localName && $table_ns === $reader->namespaceURI && $sheet_depth >= 0 && $reader->depth === $sheet_depth + 1 ) {
					if ( 'false' !== (string) $reader->getAttributeNs( 'display', $table_ns ) ) {
						$names[] = CPTDP_Page_Outline::clean_title( (string) $reader->getAttributeNs( 'name', $table_ns ) );
					}

					$more = $reader->next();
					continue;
				}

				$more = $reader->read();
			}
		} catch ( Throwable $e ) {
			$names = array();
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		return $names;
	}

	/**
	 * Write the Calc settings of a throwaway profile: never update links, never recalculate
	 * on load (numbers match what the author saw) and macros off.
	 *
	 * @param string $profile Profile folder (made here).
	 */
	public static function write_profile( string $profile ): void {
		$user = $profile . '/user';

		if ( ! wp_mkdir_p( $user ) ) {
			return;
		}

		$items = array(
			array( 'org.openoffice.Office.Calc/Content/Update', 'Link', 'int', '2' ),
			array( 'org.openoffice.Office.Calc/Formula/Load', 'OOXMLRecalcMode', 'int', '1' ),
			array( 'org.openoffice.Office.Calc/Formula/Load', 'ODFRecalcMode', 'int', '1' ),
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

	/**
	 * Check a spreadsheet file before LibreOffice reads it. Decided by content, not by name.
	 *
	 * @param string $path File path.
	 * @param string $kind `xlsx`, `xls` or `ods`.
	 * @return true|WP_Error
	 */
	public static function inspect( string $path, string $kind ) {
		if ( ! CPTDP_Page_Store::is_spreadsheet_kind( $kind ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::invalid();
		}

		$size = (int) filesize( $path );

		if ( $size < 1 || $size > self::max_bytes() ) {
			return new WP_Error(
				'cptdp_xlsx_too_large',
				/* translators: %s: size limit, e.g. 25 MB. */
				sprintf( __( 'The spreadsheet is larger than %s. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ), size_format( self::max_bytes() ) )
			);
		}

		$head = (string) file_get_contents( $path, false, null, 0, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( CPTDP_Page_Store::KIND_XLS === $kind ) {
			$status = CPTDP_Pptx_Converter::ole_status( $path, $head );

			return 'ok' === $status ? true : ( 'macros' === $status ? self::macros() : self::invalid() );
		}

		if ( 0 !== strpos( $head, "PK\x03\x04" ) ) {
			return self::invalid();
		}

		return CPTDP_Page_Store::KIND_XLSX === $kind ? self::inspect_xlsx( $path ) : self::inspect_ods( $path );
	}

	/**
	 * XLSX: a zip with the SpreadsheetML parts, no macros, no external links or data connections.
	 *
	 * @param string $path File path.
	 * @return true|WP_Error
	 */
	private static function inspect_xlsx( string $path ) {
		$zip = CPTDP_Pptx_Converter::open_zip( $path );

		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$names = CPTDP_Pptx_Converter::zip_names( $zip );

		if ( ! in_array( '[Content_Types].xml', $names, true ) || ! in_array( 'xl/workbook.xml', $names, true ) ) {
			$zip->close();

			return self::invalid();
		}

		$types = (string) $zip->getFromName( '[Content_Types].xml', CPTDP_Pptx_Converter::MAX_XML_BYTES );

		if ( in_array( 'xl/vbaProject.bin', $names, true ) || false !== stripos( $types, 'macroEnabled' ) || false !== stripos( $types, 'vbaProject' ) ) {
			$zip->close();

			return self::macros();
		}

		foreach ( $names as $name ) {
			if ( 0 === strpos( $name, 'xl/externalLinks/' ) || 0 === strpos( $name, 'xl/queryTables/' ) || 'xl/connections.xml' === $name ) {
				$zip->close();

				return self::external();
			}

			if ( '.rels' !== substr( $name, -5 ) ) {
				continue;
			}

			if ( CPTDP_Pptx_Converter::rels_have_external_object( (string) $zip->getFromName( $name, CPTDP_Pptx_Converter::MAX_XML_BYTES ), self::EXTRA_REL_TYPES ) ) {
				$zip->close();

				return self::external();
			}
		}

		$zip->close();

		return true;
	}

	/**
	 * ODS: a zip whose first entry `mimetype` says spreadsheet, no macro folders, no external data.
	 *
	 * @param string $path File path.
	 * @return true|WP_Error
	 */
	private static function inspect_ods( string $path ) {
		$zip = CPTDP_Pptx_Converter::open_zip( $path );

		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$names = CPTDP_Pptx_Converter::zip_names( $zip );

		if ( ( $names[0] ?? '' ) !== 'mimetype' || trim( (string) $zip->getFromName( 'mimetype', 256 ) ) !== self::ODS_MIMETYPE || ! in_array( 'content.xml', $names, true ) ) {
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

		if ( CPTDP_Pptx_Converter::odf_has_external_object( $content, self::ODS_LINKED_ELEMENTS ) ) {
			return self::external();
		}

		return true;
	}

	/**
	 * Error: not a valid spreadsheet of the expected kind.
	 */
	private static function invalid(): WP_Error {
		return new WP_Error( 'cptdp_xlsx_invalid', __( 'The file is not a valid spreadsheet. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	/**
	 * Error: the spreadsheet contains macros.
	 */
	private static function macros(): WP_Error {
		return new WP_Error( 'cptdp_xlsx_macros', __( 'The spreadsheet contains macros and will not be converted. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	/**
	 * Error: the spreadsheet links to external data.
	 */
	private static function external(): WP_Error {
		return new WP_Error( 'cptdp_xlsx_external', __( 'This spreadsheet links to external data and cannot be converted safely. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}
}
