<?php
/**
 * Server-side PDF to page image conversion (Imagick, then Ghostscript).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts the PDF of a document into one image per page.
 */
class CPTDP_Page_Converter {

	const MAX_PAGES        = 300;
	const SECONDS_PER_PAGE = 20;
	const SECONDS_TOTAL    = 120;
	const WIDTH_PORTRAIT   = 1400;
	const WIDTH_LANDSCAPE  = 1800;
	const QUALITY          = 82;
	const GS_DPI           = 170;
	const SHEET_WIDTH      = 1800; // Uniform page for spreadsheets (A4 landscape ratio), DPB-027.
	const SHEET_HEIGHT     = 1273;
	const SHEET_MAX_ZOOM   = 2.0;

	/**
	 * The page limit of a server conversion (filterable so tests can use a small PDF).
	 */
	public static function max_pages(): int {
		/**
		 * Filters the page limit of a server conversion.
		 *
		 * @param int $max Default 300.
		 */
		return max( 1, (int) apply_filters( 'cptdp_pages_max_pages', self::MAX_PAGES ) );
	}

	/**
	 * Whether the server can convert PDFs.
	 *
	 * Filterable (`cptdp_pages_server_available`) so tests and hosts can force it.
	 */
	public static function is_available(): bool {
		$available = null !== self::engine();

		/**
		 * Filters whether server-side page conversion is available.
		 *
		 * @param bool $available Whether Imagick (with PDF) or Ghostscript is usable.
		 */
		return (bool) apply_filters( 'cptdp_pages_server_available', $available );
	}

	/**
	 * The engine that would be used: `imagick`, `ghostscript` or null.
	 */
	public static function engine(): ?string {
		if ( self::imagick_can_read_pdf() ) {
			return 'imagick';
		}

		return null !== self::ghostscript_binary() ? 'ghostscript' : null;
	}

	/**
	 * Why conversion is unavailable, for the admin.
	 */
	public static function unavailable_reason(): string {
		return __( 'This server has neither Imagick with PDF support nor Ghostscript.', 'custom-post-types-dp' );
	}

	/**
	 * Whether Imagick is loaded and can read PDF.
	 */
	public static function imagick_can_read_pdf(): bool {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		return in_array( 'PDF', Imagick::queryFormats( 'PDF' ), true );
	}

	/**
	 * Path of a runnable Ghostscript binary, or null.
	 */
	public static function ghostscript_binary(): ?string {
		if ( ! function_exists( 'proc_open' ) ) {
			return null;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		if ( in_array( 'proc_open', $disabled, true ) ) {
			return null;
		}

		foreach ( array( '/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs', '/opt/local/bin/gs' ) as $candidate ) {
			if ( is_file( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Convert the PDF of a document.
	 *
	 * Writes the images into the document's folder (replacing anything there) and
	 * returns the manifest fields, including the `crop_bottom` found by
	 * CPTDP_Page_Analyzer. Does not save the manifest.
	 *
	 * @param int $post_id Document ID.
	 * @return array{count:int,width:int,height:int,ext:string,dir:string,producer:string,crop_bottom:float,source_token:string}|WP_Error
	 */
	public static function convert( int $post_id ) {
		$source = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $source ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ) );
		}

		// A collateral's image is one page, resized by the WordPress image editor (DPC-023).
		if ( CPTDP_Page_Store::is_image_kind( $source['kind'] ) ) {
			return self::convert_image_file( $post_id, $source );
		}

		// A presentation is turned into a temporary PDF first (LibreOffice); everything after
		// that is the same code as for an uploaded PDF. The temporary files are always removed.
		if ( CPTDP_Page_Store::is_office_kind( $source['kind'] ) ) {
			if ( ! CPTDP_Pptx_Converter::is_available( $source['kind'] ) ) {
				return new WP_Error( 'cptdp_unavailable', CPTDP_Pptx_Converter::unavailable_reason( $source['kind'] ) );
			}

			$valid = CPTDP_Pptx_Converter::inspect( $source['path'], $source['kind'] );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$temporary = CPTDP_Pptx_Converter::to_pdf( $source['path'], $source['kind'] );

			if ( is_wp_error( $temporary ) ) {
				return $temporary;
			}

			try {
				return self::convert_pdf_file( $post_id, $temporary['pdf'], $source );
			} finally {
				CPTDP_Pptx_Converter::cleanup( $temporary['dir'] );
			}
		}

		return self::convert_pdf_file( $post_id, $source['path'], $source );
	}

	/**
	 * Convert a PDF file into the document's page images.
	 *
	 * @param int                                                                $post_id  Document ID.
	 * @param string                                                             $pdf_path The PDF (the document's own file, or the temporary PDF of a presentation).
	 * @param array{id:int,path:string,fingerprint:string,token:string,kind:string} $source   The document's source.
	 * @return array{count:int,width:int,height:int,ext:string,dir:string,producer:string,crop_bottom:float,source_token:string}|WP_Error
	 */
	private static function convert_pdf_file( int $post_id, string $pdf_path, array $source ) {
		// The header must be the very first thing in the file. (The PDF spec tolerates junk before
		// it, but then a file could be a PostScript/EPS/MVG polyglot that merely mentions "%PDF-".
		// A real PDF that fails this check goes to the admin's browser, which can read it.)
		$head = (string) file_get_contents( $pdf_path, false, null, 0, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( '%PDF-' !== $head ) {
			return new WP_Error( 'cptdp_not_a_pdf', __( 'The file is not a valid PDF.', 'custom-post-types-dp' ) );
		}

		$engine = self::engine();

		if ( null === $engine || ! self::is_available() ) {
			return new WP_Error( 'cptdp_unavailable', self::unavailable_reason() );
		}

		$folder = self::prepare_folder( $post_id );

		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		$uniform = CPTDP_Page_Store::is_spreadsheet_kind( $source['kind'] );

		$result = 'imagick' === $engine ? self::convert_with_imagick( $pdf_path, $folder, $uniform ) : self::convert_with_ghostscript( $pdf_path, $folder );

		// ImageMagick may list PDF yet refuse to read it (a policy.xml rule): retry with Ghostscript.
		if ( is_wp_error( $result ) && 'imagick' === $engine && $result->get_error_data( 'cptdp_conversion_failed' ) === 'engine' && null !== self::ghostscript_binary() ) {
			CPTDP_Page_Store::delete_dir( $folder );
			wp_mkdir_p( $folder );
			$result = self::convert_with_ghostscript( $pdf_path, $folder );
		}

		if ( is_wp_error( $result ) ) {
			CPTDP_Page_Store::delete_dir( $folder );

			return $result;
		}

		// Ghostscript sets (and Imagick sets whose thumbnails failed) get them from the stored images.
		if ( empty( $result['thumb_width'] ) ) {
			$thumb = CPTDP_Page_Thumbs::make_for_folder( $folder, (string) $result['ext'], (int) $result['count'] );

			if ( null !== $thumb ) {
				$result['thumb_width']  = $thumb['width'];
				$result['thumb_height'] = $thumb['height'];
			}
		}

		// Page names (DPB-030), never a reason to fail a conversion.
		$names = self::page_names( $pdf_path, $source, (int) $result['count'] );

		if ( $names ) {
			$result['page_names'] = $names;
		}

		// Which file these images were made from: the caller drops them if it was replaced meanwhile.
		$result['source_token'] = $source['token'];
		$result['dir']          = basename( $folder );
		$result['producer']     = 'server';

		// A slide has a fixed shape, so presentations are never trimmed (decision 9 of DPB-026).
		$result['crop_bottom'] = CPTDP_Page_Store::is_office_kind( $source['kind'] )
			? 0.0
			: CPTDP_Page_Analyzer::analyze_folder( $folder, (string) $result['ext'], (int) $result['count'] );

		return $result;
	}

	/**
	 * The post's set folder, emptied and created.
	 *
	 * @param int $post_id Document or collateral ID.
	 * @return string|WP_Error Absolute folder path.
	 */
	private static function prepare_folder( int $post_id ) {
		$folder = CPTDP_Page_Store::folder_for( $post_id );

		if ( '' === $folder ) {
			return new WP_Error( 'cptdp_no_pdf', __( 'The document has no PDF file.', 'custom-post-types-dp' ) );
		}

		CPTDP_Page_Store::delete_dir( $folder );

		if ( ! wp_mkdir_p( $folder ) ) {
			return new WP_Error( 'cptdp_conversion_failed', __( 'Could not create the page images folder.', 'custom-post-types-dp' ) );
		}

		return $folder;
	}

	/**
	 * Turn a collateral's image into a one-page set.
	 *
	 * @param int                                                                $post_id Collateral ID.
	 * @param array{id:int,path:string,fingerprint:string,token:string,kind:string} $source  Its source.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function convert_image_file( int $post_id, array $source ) {
		if ( ! CPTDP_Image_Page_Converter::is_available() ) {
			return new WP_Error( 'cptdp_unavailable', CPTDP_Image_Page_Converter::unavailable_reason() );
		}

		$folder = self::prepare_folder( $post_id );

		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		$result = CPTDP_Image_Page_Converter::convert( $source, $folder );

		if ( is_wp_error( $result ) ) {
			CPTDP_Page_Store::delete_dir( $folder );

			return $result;
		}

		$result['source_token'] = $source['token'];
		$result['dir']          = basename( $folder );

		return $result;
	}

	/**
	 * Names of the pages as ranges (DPB-030): PDF and Word from the outline, Excel from the sheet names.
	 * PowerPoint and `.xls` have none.
	 *
	 * @param string                                                             $pdf_path PDF that was converted (for Word, the temporary one).
	 * @param array{id:int,path:string,fingerprint:string,token:string,kind:string} $source   The document's source.
	 * @param int                                                                $count    Pages made.
	 * @return array<int,array{0:int,1:string}>
	 */
	public static function page_names( string $pdf_path, array $source, int $count ): array {
		try {
			$kind = $source['kind'];

			if ( CPTDP_Page_Store::is_presentation_kind( $kind ) ) {
				return array();
			}

			if ( CPTDP_Page_Store::is_spreadsheet_kind( $kind ) ) {
				$sheets = CPTDP_Xlsx_Converter::sheet_names( $source['path'], $kind );

				// Trusted only when it matches the pages one to one.
				if ( count( $sheets ) !== $count ) {
					return array();
				}

				$ranges = array();

				foreach ( $sheets as $i => $name ) {
					if ( '' !== $name ) {
						$ranges[] = array( $i + 1, $name );
					}
				}

				return CPTDP_Page_Outline::sanitize_ranges( $ranges, $count );
			}

			return CPTDP_Page_Outline::ranges( $pdf_path, $count );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * Convert with Imagick, one page at a time.
	 *
	 * @param string $pdf    PDF path.
	 * @param string $folder  Output folder.
	 * @param bool   $uniform Place every page on one fixed landscape canvas (spreadsheet sheets differ in size).
	 * @return array{count:int,width:int,height:int,ext:string}|WP_Error
	 */
	private static function convert_with_imagick( string $pdf, string $folder, bool $uniform = false ) {
		try {
			Imagick::setResourceLimit( Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			Imagick::setResourceLimit( Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024 );
			Imagick::setResourceLimit( Imagick::RESOURCETYPE_TIME, self::SECONDS_TOTAL );

			/**
			 * Filters the path Imagick reads (a test seam; do not change in production).
			 *
			 * @param string $pdf PDF path.
			 */
			$pdf = (string) apply_filters( 'cptdp_pages_imagick_source', $pdf );

			// `PDF:` forces the PDF coder, so a file cannot be sniffed into another format.
			$probe = new Imagick();
			$probe->pingImage( 'PDF:' . $pdf );
			$count = $probe->getNumberImages();
			$probe->clear();

			if ( $count < 1 ) {
				return new WP_Error( 'cptdp_conversion_failed', __( 'The PDF has no pages.', 'custom-post-types-dp' ) );
			}

			if ( $count > self::max_pages() ) {
				return self::too_many_pages();
			}

			$ext     = in_array( 'WEBP', Imagick::queryFormats( 'WEBP' ), true ) ? 'webp' : 'jpg';
			$started = microtime( true );
			$width   = 0;
			$height  = 0;
			$target  = self::WIDTH_PORTRAIT;

			// Small thumbnails (DPB-031) come from the page already in memory; any failure drops them all.
			$thumbs_ok = CPTDP_Page_Thumbs::is_available();
			$thumb_dim = array( 'width' => 0, 'height' => 0 );

			for ( $i = 0; $i < $count; $i++ ) {
				if ( microtime( true ) - $started > self::SECONDS_TOTAL ) {
					return new WP_Error( 'cptdp_conversion_failed', __( 'Converting the PDF took too long.', 'custom-post-types-dp' ) );
				}

				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@set_time_limit( self::SECONDS_PER_PAGE + 10 );

				if ( $uniform ) {
					$page = self::read_sheet_page( $pdf, $i );
				} else {
					$page = self::read_page( $pdf, $i, 150 );

					if ( 0 === $i ) {
						$target = $page->getImageWidth() > $page->getImageHeight() ? self::WIDTH_LANDSCAPE : self::WIDTH_PORTRAIT;
					}
				}

				if ( ! $uniform && $page->getImageWidth() > $target ) {
					$page->thumbnailImage( $target, 0 );
				}

				if ( $thumbs_ok ) {
					$thumb = CPTDP_Page_Thumbs::write( $page, $folder . '/' . CPTDP_Page_Thumbs::filename( $i + 1, $ext ), $ext );

					if ( null === $thumb ) {
						$thumbs_ok = false;
					} elseif ( 0 === $i ) {
						$thumb_dim = $thumb;
					}
				}

				$page->setImageFormat( 'webp' === $ext ? 'webp' : 'jpeg' );
				$page->setImageCompressionQuality( self::QUALITY );
				$page->stripImage();

				if ( 0 === $i ) {
					$width  = $page->getImageWidth();
					$height = $page->getImageHeight();
				}

				$page->writeImage( $folder . '/' . CPTDP_Page_Store::page_filename( $i + 1, $ext ) );
				$page->clear();
			}
		} catch ( Throwable $e ) {
			// Marked "engine": the engine itself failed (not a limit), so Ghostscript is worth a try.
			return new WP_Error( 'cptdp_conversion_failed', __( 'The PDF could not be converted.', 'custom-post-types-dp' ) . ' ' . $e->getMessage(), 'engine' );
		}

		if ( ! $thumbs_ok ) {
			CPTDP_Page_Thumbs::delete( $folder, $ext, $count );
		}

		$result = array(
			'count'  => $count,
			'width'  => $width,
			'height' => $height,
			'ext'    => $ext,
		);

		if ( $thumbs_ok ) {
			$result['thumb_width']  = $thumb_dim['width'];
			$result['thumb_height'] = $thumb_dim['height'];
		}

		return $result;
	}

	/**
	 * Draw one PDF page, flattened onto white, in sRGB.
	 *
	 * @param string $pdf   PDF path.
	 * @param int    $index Page index.
	 * @param float  $dpi   Resolution.
	 */
	private static function read_page( string $pdf, int $index, float $dpi ): Imagick {
		$page = new Imagick();
		$page->setResolution( $dpi, $dpi );
		$page->readImage( 'PDF:' . $pdf . '[' . $index . ']' );
		$page->setImageBackgroundColor( 'white' );
		$page->setImageAlphaChannel( Imagick::ALPHACHANNEL_REMOVE );
		$flat = $page->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		$page->clear();
		$flat->transformImageColorspace( Imagick::COLORSPACE_SRGB );

		return $flat;
	}

	/**
	 * Draw one spreadsheet page on the shared white canvas, top left like a sheet.
	 *
	 * Sheets come out of Calc at the size they need (one page per sheet), so they differ wildly. The page
	 * size is read without drawing it, then the page is drawn once at the resolution that makes it fit the
	 * canvas (sharper for a small sheet, up to twice its size; smaller for a big one). Every page then has
	 * the same shape, which the book needs.
	 *
	 * @param string $pdf   PDF path.
	 * @param int    $index Page index.
	 */
	private static function read_sheet_page( string $pdf, int $index ): Imagick {
		$probe = new Imagick();
		$probe->setResolution( 72, 72 );
		$probe->pingImage( 'PDF:' . $pdf . '[' . $index . ']' );
		$points_w = max( 1, $probe->getImageWidth() );
		$points_h = max( 1, $probe->getImageHeight() );
		$probe->clear();

		// Points to pixels: 72 dpi is one pixel per point, so the dpi that fits the canvas is 72 times the scale.
		$dpi  = min( self::SHEET_MAX_ZOOM * 150, 72 * min( self::SHEET_WIDTH / $points_w, self::SHEET_HEIGHT / $points_h ) );
		$page = self::read_page( $pdf, $index, $dpi );

		if ( $page->getImageWidth() > self::SHEET_WIDTH || $page->getImageHeight() > self::SHEET_HEIGHT ) {
			$page->thumbnailImage( self::SHEET_WIDTH, self::SHEET_HEIGHT, true );
		}

		$page->setImageBackgroundColor( 'white' );
		$page->extentImage( self::SHEET_WIDTH, self::SHEET_HEIGHT, 0, 0 );

		return $page;
	}

	/**
	 * The error for a PDF over the page limit (same wording for both engines).
	 */
	private static function too_many_pages(): WP_Error {
		return new WP_Error(
			'cptdp_conversion_failed',
			/* translators: %d: page limit. */
			sprintf( __( 'The PDF has more than %d pages.', 'custom-post-types-dp' ), self::max_pages() )
		);
	}

	/**
	 * Convert with Ghostscript (JPEG output).
	 *
	 * @param string $pdf    PDF path.
	 * @param string $folder Output folder.
	 * @return array{count:int,width:int,height:int,ext:string}|WP_Error
	 */
	private static function convert_with_ghostscript( string $pdf, string $folder ) {
		$binary = self::ghostscript_binary();

		if ( null === $binary ) {
			return new WP_Error( 'cptdp_unavailable', self::unavailable_reason() );
		}

		$command = array(
			$binary,
			'-q',
			'-dSAFER',
			'-dBATCH',
			'-dNOPAUSE',
			'-sDEVICE=jpeg',
			'-dJPEGQ=' . self::QUALITY,
			'-dTextAlphaBits=4',
			'-dGraphicsAlphaBits=4',
			'-r' . self::GS_DPI,
			'-dLastPage=' . ( self::max_pages() + 1 ), // One more than allowed, so a longer PDF is refused instead of truncated.
			'-sOutputFile=' . $folder . '/p-%03d.jpg',
			$pdf,
		);

		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );

		if ( ! is_resource( $process ) ) {
			return new WP_Error( 'cptdp_conversion_failed', __( 'Ghostscript could not be started.', 'custom-post-types-dp' ) );
		}

		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$started = microtime( true );
		$error   = '';

		do {
			// Drain both pipes while it runs: a full pipe (about 64 KB of warnings) would stall Ghostscript.
			self::drain( $pipes[1] );
			$error .= self::drain( $pipes[2], 4096 - strlen( $error ) );

			$status = proc_get_status( $process );

			if ( ! $status['running'] ) {
				break;
			}

			if ( microtime( true ) - $started > self::SECONDS_TOTAL ) {
				proc_terminate( $process, 9 );
				fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				proc_close( $process );

				return new WP_Error( 'cptdp_conversion_failed', __( 'Converting the PDF took too long.', 'custom-post-types-dp' ) );
			}

			usleep( 100000 );
		} while ( true );

		$error = trim( $error . self::drain( $pipes[2], 4096 - strlen( $error ) ) );
		self::drain( $pipes[1] );
		fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$exit = proc_close( $process );
		$exit = isset( $status['exitcode'] ) && $status['exitcode'] >= 0 ? (int) $status['exitcode'] : $exit;

		$files = glob( $folder . '/p-*.jpg' );
		$count = false === $files ? 0 : count( $files );

		if ( $count > self::max_pages() ) {
			return self::too_many_pages();
		}

		if ( 0 !== $exit || $count < 1 ) {
			return new WP_Error( 'cptdp_conversion_failed', __( 'The PDF could not be converted.', 'custom-post-types-dp' ) . ( '' !== $error ? ' ' . mb_substr( $error, 0, 120 ) : '' ) );
		}

		$size = getimagesize( $folder . '/' . CPTDP_Page_Store::page_filename( 1, 'jpg' ) );

		return array(
			'count'  => $count,
			'width'  => $size ? (int) $size[0] : 0,
			'height' => $size ? (int) $size[1] : 0,
			'ext'    => 'jpg',
		);
	}
	/**
	 * Read whatever is waiting on a non-blocking pipe.
	 *
	 * @param resource $pipe  Pipe.
	 * @param int      $keep  Return at most this many bytes (the rest is read and dropped).
	 * @return string
	 */
	public static function drain( $pipe, int $keep = 0 ): string {
		$kept = '';

		while ( true ) {
			$chunk = fread( $pipe, 8192 );

			if ( false === $chunk || '' === $chunk ) {
				break;
			}

			if ( strlen( $kept ) < $keep ) {
				$kept .= substr( $chunk, 0, $keep - strlen( $kept ) );
			}
		}

		return $kept;
	}
}
