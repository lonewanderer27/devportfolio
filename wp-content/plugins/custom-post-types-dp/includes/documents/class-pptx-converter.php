<?php
/**
 * Presentation (PPTX, PPT, ODP) to PDF conversion with LibreOffice (DPB-026).
 *
 * The PDF it makes is only a temporary step: CPTDP_Page_Converter turns it into page
 * images with the same code that handles uploaded PDFs, and the temporary files are
 * always deleted. Server only: no browser can read these files.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates presentations and converts them to a temporary PDF.
 */
class CPTDP_Pptx_Converter {

	const SECONDS_TOTAL   = 120;
	const MAX_BYTES       = 104857600; // 100 MB.
	const MAX_ENTRIES     = 5000;
	const MAX_UNCOMPRESSED = 1073741824; // 1 GB.
	const MAX_RATIO       = 200;
	const MAX_XML_BYTES   = 20971520; // 20 MB read from one XML part.
	const LOCK            = 'cptdp_pptx_lock';

	const ODP_MIMETYPE = 'application/vnd.oasis.opendocument.presentation';

	/**
	 * Relationship types that pull in external objects: refused when they point outside the package.
	 */
	const EXTERNAL_REL_TYPES = array( '/oleobject', '/package', '/frame', '/externallink' );

	/**
	 * Path of a runnable LibreOffice binary, or null.
	 *
	 * Overridable with the `CPTDP_SOFFICE_PATH` constant and the `cptdp_pptx_soffice_path` filter.
	 */
	public static function binary(): ?string {
		if ( ! function_exists( 'proc_open' ) ) {
			return null;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		if ( in_array( 'proc_open', $disabled, true ) ) {
			return null;
		}

		$candidates = array( '/usr/bin/soffice', '/usr/bin/libreoffice', '/usr/local/bin/soffice', '/usr/local/bin/libreoffice', '/opt/homebrew/bin/soffice', '/Applications/LibreOffice.app/Contents/MacOS/soffice' );

		foreach ( (array) glob( '/opt/libreoffice*/program/soffice' ) as $found ) {
			if ( is_string( $found ) ) {
				$candidates[] = $found;
			}
		}

		if ( defined( 'CPTDP_SOFFICE_PATH' ) && is_string( CPTDP_SOFFICE_PATH ) && '' !== CPTDP_SOFFICE_PATH ) {
			array_unshift( $candidates, CPTDP_SOFFICE_PATH );
		}

		/**
		 * Filters the LibreOffice candidates (full paths), most preferred first.
		 *
		 * @param string[] $candidates Paths.
		 */
		$candidates = (array) apply_filters( 'cptdp_pptx_soffice_path', $candidates );

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate && is_file( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Whether the server can convert presentations: LibreOffice plus a way to draw the PDF.
	 *
	 * Filterable (`cptdp_pptx_available`) so tests and hosts can force it.
	 */
	public static function is_available(): bool {
		$available = null !== self::binary() && CPTDP_Page_Converter::is_available();

		/**
		 * Filters whether presentation conversion is available.
		 *
		 * @param bool $available LibreOffice found and the PDF to image step available.
		 */
		return (bool) apply_filters( 'cptdp_pptx_available', $available );
	}

	/**
	 * Why conversion is unavailable, for the admin (ends with the PDF recommendation).
	 */
	public static function unavailable_reason(): string {
		if ( null === self::binary() ) {
			return __( 'This server cannot convert PowerPoint files (LibreOffice is not installed). Export the presentation to PDF in PowerPoint and upload the PDF instead.', 'custom-post-types-dp' );
		}

		return __( 'This server cannot draw PDF pages (no Imagick or Ghostscript). Export the presentation to PDF in PowerPoint and upload the PDF instead.', 'custom-post-types-dp' );
	}

	/**
	 * The installed LibreOffice version line (cached for a day), or '' when it cannot be read.
	 */
	public static function version(): string {
		$binary = self::binary();

		if ( null === $binary ) {
			return '';
		}

		$cached = get_transient( 'cptdp_pptx_version' );

		if ( is_array( $cached ) && ( $cached['binary'] ?? '' ) === $binary ) {
			return (string) $cached['version'];
		}

		$out     = '';
		$process = proc_open( array( $binary, '--version' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, self::environment( sys_get_temp_dir() ) );

		if ( is_resource( $process ) ) {
			stream_set_timeout( $pipes[1], 15 );
			$out = (string) stream_get_contents( $pipes[1], 512 );
			fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			proc_close( $process );
		}

		$version = '';

		if ( 1 === preg_match( '/LibreOffice\s+([0-9][0-9.]*)/', $out, $m ) ) {
			$version = $m[1];
		}

		if ( '' !== $version ) {
			set_transient(
				'cptdp_pptx_version',
				array(
					'binary'  => $binary,
					'version' => $version,
				),
				DAY_IN_SECONDS
			);
		}

		return $version;
	}

	/**
	 * Largest file accepted, in bytes (filter `cptdp_pptx_max_bytes`).
	 */
	public static function max_bytes(): int {
		return max( 1024, (int) apply_filters( 'cptdp_pptx_max_bytes', self::MAX_BYTES ) );
	}

	/**
	 * Check a presentation file before LibreOffice reads it. Decided by content, not by name.
	 *
	 * @param string $path File path.
	 * @param string $kind `pptx`, `ppt` or `odp`.
	 * @return true|WP_Error
	 */
	public static function inspect( string $path, string $kind ) {
		if ( ! CPTDP_Page_Store::is_presentation_kind( $kind ) ) {
			return self::invalid();
		}

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::invalid();
		}

		$size = (int) filesize( $path );

		if ( $size < 1 || $size > self::max_bytes() ) {
			return new WP_Error(
				'cptdp_pptx_too_large',
				/* translators: %s: size limit, e.g. 100 MB. */
				sprintf( __( 'The presentation is larger than %s. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ), size_format( self::max_bytes() ) )
			);
		}

		$head = (string) file_get_contents( $path, false, null, 0, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( CPTDP_Page_Store::KIND_PPT === $kind ) {
			return self::inspect_ppt( $path, $head );
		}

		if ( 0 !== strpos( $head, "PK\x03\x04" ) ) {
			return self::invalid();
		}

		return CPTDP_Page_Store::KIND_PPTX === $kind ? self::inspect_pptx( $path ) : self::inspect_odp( $path );
	}

	/**
	 * Legacy binary PowerPoint: an OLE2 file, refused when it carries a VBA project.
	 *
	 * The OLE directory names its storages in UTF-16, so the check is a bounded scan of the
	 * file for the name `_VBA_PROJECT` (no OLE parser needed). A slide that merely contains
	 * that exact text would be refused too, which is acceptable for an old format.
	 *
	 * @param string $path File path.
	 * @param string $head First bytes of the file.
	 * @return true|WP_Error
	 */
	private static function inspect_ppt( string $path, string $head ) {
		if ( "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" !== $head ) {
			return self::invalid();
		}

		$needle = "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0";
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return self::invalid();
		}

		$tail = '';

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

			if ( false === $chunk || '' === $chunk ) {
				break;
			}

			if ( false !== strpos( $tail . $chunk, $needle ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

				return self::macros();
			}

			$tail = substr( $chunk, -strlen( $needle ) );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return true;
	}

	/**
	 * PPTX: a zip with the PresentationML parts, within the zip limits, without macros or external objects.
	 *
	 * @param string $path File path.
	 * @return true|WP_Error
	 */
	private static function inspect_pptx( string $path ) {
		$zip = self::open_zip( $path );

		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$names = self::zip_names( $zip );

		if ( ! in_array( '[Content_Types].xml', $names, true ) || ! in_array( 'ppt/presentation.xml', $names, true ) ) {
			$zip->close();

			return self::invalid();
		}

		$types = (string) $zip->getFromName( '[Content_Types].xml', self::MAX_XML_BYTES );

		if ( in_array( 'ppt/vbaProject.bin', $names, true ) || false !== stripos( $types, 'macroEnabled' ) || false !== stripos( $types, 'vbaProject' ) ) {
			$zip->close();

			return self::macros();
		}

		foreach ( $names as $name ) {
			if ( '.rels' !== substr( $name, -5 ) ) {
				continue;
			}

			if ( self::rels_have_external_object( (string) $zip->getFromName( $name, self::MAX_XML_BYTES ) ) ) {
				$zip->close();

				return self::external();
			}
		}

		$zip->close();

		return true;
	}

	/**
	 * ODP: a zip whose first entry `mimetype` says presentation, without macro folders or external objects.
	 *
	 * @param string $path File path.
	 * @return true|WP_Error
	 */
	private static function inspect_odp( string $path ) {
		$zip = self::open_zip( $path );

		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$names = self::zip_names( $zip );

		if ( ( $names[0] ?? '' ) !== 'mimetype' || trim( (string) $zip->getFromName( 'mimetype', 256 ) ) !== self::ODP_MIMETYPE || ! in_array( 'content.xml', $names, true ) ) {
			$zip->close();

			return self::invalid();
		}

		foreach ( $names as $name ) {
			if ( 0 === strpos( $name, 'Basic/' ) || 0 === strpos( $name, 'Scripts/' ) ) {
				$zip->close();

				return self::macros();
			}
		}

		$content = (string) $zip->getFromName( 'content.xml', self::MAX_XML_BYTES );
		$zip->close();

		if ( self::odf_has_external_object( $content ) ) {
			return self::external();
		}

		return true;
	}

	/**
	 * Open a zip and apply the bomb guards: entry count, total size, per-entry ratio.
	 *
	 * @param string $path File path.
	 * @return ZipArchive|WP_Error
	 */
	private static function open_zip( string $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cptdp_pptx_no_zip', __( 'This server cannot read presentation files (the PHP zip extension is missing). Export the presentation to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
			return self::invalid();
		}

		if ( $zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES ) {
			$zip->close();

			return self::invalid();
		}

		$total = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );

			if ( ! is_array( $stat ) ) {
				$zip->close();

				return self::invalid();
			}

			$total += (int) $stat['size'];
			$comp   = (int) $stat['comp_size'];

			if ( $total > self::MAX_UNCOMPRESSED || ( $comp > 0 && (int) $stat['size'] / $comp > self::MAX_RATIO ) || ( 0 === $comp && (int) $stat['size'] > 1048576 ) ) {
				$zip->close();

				return new WP_Error( 'cptdp_pptx_bomb', __( 'The presentation file is not safe to convert. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
			}
		}

		return $zip;
	}

	/**
	 * Entry names of an open zip, in archive order.
	 *
	 * @param ZipArchive $zip Open archive.
	 * @return string[]
	 */
	private static function zip_names( ZipArchive $zip ): array {
		$names = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );

			if ( is_string( $name ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Whether a `.rels` part has an external relationship of an object type (OLE, package, frame, link).
	 *
	 * @param string $xml Relationships XML.
	 */
	public static function rels_have_external_object( string $xml ): bool {
		if ( 0 === preg_match_all( '/<Relationship\b[^>]*>/i', $xml, $tags ) ) {
			return false;
		}

		foreach ( $tags[0] as $tag ) {
			if ( 1 !== preg_match( '/TargetMode\s*=\s*"External"/i', $tag ) || 1 !== preg_match( '/Type\s*=\s*"([^"]*)"/i', $tag, $type ) ) {
				continue;
			}

			$type = strtolower( $type[1] );

			foreach ( self::EXTERNAL_REL_TYPES as $needle ) {
				if ( false !== strpos( $type, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether ODF content has an embedded-object element that points outside the package.
	 *
	 * @param string $xml content.xml.
	 */
	public static function odf_has_external_object( string $xml ): bool {
		if ( 0 === preg_match_all( '/<draw:(?:object|object-ole|plugin)\b[^>]*>/i', $xml, $tags ) ) {
			return false;
		}

		foreach ( $tags[0] as $tag ) {
			if ( 1 !== preg_match( '/xlink:href\s*=\s*"([^"]*)"/i', $tag, $href ) ) {
				continue;
			}

			$target = trim( html_entity_decode( $href[1] ) );

			// Inside the package: `./Object 1` or `Object 1`. Anything with a scheme, an absolute or a parent path is outside.
			if ( 1 === preg_match( '#^([a-z][a-z0-9+.-]*:|/|\\\\|\.\./)#i', $target ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert a presentation to a temporary PDF.
	 *
	 * The caller must call cleanup() on the returned `dir` when it is done with the PDF.
	 *
	 * @param string $path File path (already inspected).
	 * @param string $kind `pptx`, `ppt` or `odp`.
	 * @return array{pdf:string,dir:string}|WP_Error
	 */
	public static function to_pdf( string $path, string $kind ) {
		$binary = self::binary();

		if ( null === $binary ) {
			return new WP_Error( 'cptdp_unavailable', self::unavailable_reason() );
		}

		if ( false !== get_transient( self::LOCK ) ) {
			return new WP_Error( 'cptdp_pptx_busy', __( 'Another presentation is being converted.', 'custom-post-types-dp' ) );
		}

		$dir = trailingslashit( get_temp_dir() ) . 'cptdp-pptx-' . wp_generate_password( 12, false );

		if ( ! mkdir( $dir, 0700 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			return new WP_Error( 'cptdp_conversion_failed', __( 'Could not create a temporary folder for the conversion.', 'custom-post-types-dp' ) );
		}

		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$result = self::run( $binary, $path, $kind, $dir );
		} finally {
			delete_transient( self::LOCK );
		}

		if ( is_wp_error( $result ) ) {
			self::cleanup( $dir );

			return $result;
		}

		return array(
			'pdf' => $result,
			'dir' => $dir,
		);
	}

	/**
	 * Run LibreOffice in a fresh profile and return the PDF path.
	 *
	 * @param string $binary soffice path.
	 * @param string $path   Source file.
	 * @param string $kind   File kind.
	 * @param string $dir    Temporary folder.
	 * @return string|WP_Error
	 */
	private static function run( string $binary, string $path, string $kind, string $dir ) {
		// A fixed name with the right extension: LibreOffice picks the import filter from it,
		// and an odd attachment name can never reach the command line.
		$input = $dir . '/input.' . $kind;
		$out   = $dir . '/out';

		if ( ! copy( $path, $input ) || ! mkdir( $out, 0700 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			return new WP_Error( 'cptdp_conversion_failed', __( 'Could not prepare the presentation for conversion.', 'custom-post-types-dp' ) );
		}

		$command = array(
			$binary,
			'--headless',
			'--norestore',
			'--nologo',
			'--nodefault',
			'--nolockcheck',
			'-env:UserInstallation=file://' . $dir . '/profile',
			'--convert-to',
			'pdf:impress_pdf_Export',
			'--outdir',
			$out,
			$input,
		);

		/**
		 * Filters the conversion timeout in seconds.
		 *
		 * @param int $seconds Default 120.
		 */
		$timeout = max( 1, (int) apply_filters( 'cptdp_pptx_timeout', self::SECONDS_TOTAL ) );

		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, $dir, self::environment( $dir ) );

		if ( ! is_resource( $process ) ) {
			return new WP_Error( 'cptdp_conversion_failed', __( 'LibreOffice could not be started.', 'custom-post-types-dp' ) );
		}

		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$started = microtime( true );
		$error   = '';
		$status  = array();

		do {
			CPTDP_Page_Converter::drain( $pipes[1], 0 );
			$error .= CPTDP_Page_Converter::drain( $pipes[2], 2048 - strlen( $error ) );

			$status = proc_get_status( $process );

			if ( ! $status['running'] ) {
				break;
			}

			if ( microtime( true ) - $started > $timeout ) {
				proc_terminate( $process, 9 );
				fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				proc_close( $process );

				return new WP_Error( 'cptdp_conversion_failed', __( 'Converting the presentation took too long. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
			}

			usleep( 100000 );
		} while ( true );

		$error = trim( $error . CPTDP_Page_Converter::drain( $pipes[2], 2048 - strlen( $error ) ) );
		CPTDP_Page_Converter::drain( $pipes[1], 0 );
		fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$exit = proc_close( $process );
		$exit = isset( $status['exitcode'] ) && $status['exitcode'] >= 0 ? (int) $status['exitcode'] : $exit;

		$pdf = $out . '/input.pdf';

		if ( 0 !== $exit || ! is_file( $pdf ) || (int) filesize( $pdf ) < 100 || '%PDF-' !== (string) file_get_contents( $pdf, false, null, 0, 5 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return new WP_Error(
				'cptdp_conversion_failed',
				__( 'The presentation could not be converted. Export it to PDF in PowerPoint and upload the PDF instead.', 'custom-post-types-dp' ) . ( '' !== $error ? ' ' . mb_substr( $error, 0, 120 ) : '' )
			);
		}

		return $pdf;
	}

	/**
	 * A minimal environment for LibreOffice: a writable HOME, no inherited secrets.
	 *
	 * @param string $home Writable folder.
	 * @return array<string,string>
	 */
	private static function environment( string $home ): array {
		return array(
			'PATH'   => '/usr/local/bin:/usr/bin:/bin',
			'HOME'   => $home,
			'TMPDIR' => $home,
			'LANG'   => 'C.UTF-8',
		);
	}

	/**
	 * Delete a temporary folder made by to_pdf(). Only folders named by this class are touched.
	 *
	 * @param string $dir Folder path.
	 */
	public static function cleanup( string $dir ): void {
		$dir = untrailingslashit( wp_normalize_path( $dir ) );

		if ( '' === $dir || 0 !== strpos( basename( $dir ), 'cptdp-pptx-' ) || ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}

		self::remove_tree( $dir );
	}

	/**
	 * Recursively remove a folder (the caller has checked the path).
	 *
	 * @param string $dir Folder path.
	 */
	private static function remove_tree( string $dir ): void {
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry || ! is_string( $entry ) ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::remove_tree( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Temporary folders left behind by a crashed run (older than a day).
	 *
	 * @return string[]
	 */
	public static function stray_folders(): array {
		$found = array();

		foreach ( (array) glob( trailingslashit( get_temp_dir() ) . 'cptdp-pptx-*', GLOB_ONLYDIR ) as $dir ) {
			if ( is_string( $dir ) && ! is_link( $dir ) && filemtime( $dir ) < time() - DAY_IN_SECONDS ) {
				$found[] = $dir;
			}
		}

		return $found;
	}

	/**
	 * Error: not a valid presentation of the expected kind.
	 */
	private static function invalid(): WP_Error {
		return new WP_Error( 'cptdp_pptx_invalid', __( 'The file is not a valid presentation. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	/**
	 * Error: the presentation contains macros.
	 */
	private static function macros(): WP_Error {
		return new WP_Error( 'cptdp_pptx_macros', __( 'The presentation contains macros and will not be converted. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}

	/**
	 * Error: the presentation links to external objects.
	 */
	private static function external(): WP_Error {
		return new WP_Error( 'cptdp_pptx_external', __( 'This presentation links to external content and cannot be converted safely. Export it to PDF and upload the PDF instead.', 'custom-post-types-dp' ) );
	}
}
