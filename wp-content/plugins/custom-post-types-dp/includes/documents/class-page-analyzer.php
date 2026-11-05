<?php
/**
 * Finds the empty strip at the bottom of generated page images.
 *
 * The viewer crops that strip at display time (the image files stay whole), so
 * pages that end well above their bottom edge do not show a band of white. The
 * analysis gives ONE value per document, because every page of a book must keep
 * the same size.
 *
 * Keep the thresholds identical to assets/admin-document-pages.js, which runs
 * the same analysis in the admin's browser (a test compares the two).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bottom margin analysis of page images.
 */
class CPTDP_Page_Analyzer {

	/** Width the page is scaled down to before its rows are read (keeps 300 pages fast). */
	const ANALYSIS_WIDTH = 300;

	/** A pixel is blank when every channel is at least 255 minus this (survives WebP/JPEG noise). */
	const BLANK_TOLERANCE = 8;

	/** A row is blank when at least this share of its pixels is blank. */
	const BLANK_ROW_RATIO = 0.998;

	/** Only the lower part of the page is read: crops never exceed MAX_CROP anyway. */
	const SCAN_FRACTION = 0.30;

	/** Empty space left in place below the content, as a share of the page height. */
	const KEEP_MARGIN = 0.025;

	/** A crop smaller than this share of the height is not worth applying. */
	const MIN_CROP = 0.02;

	/** The largest share of the page height that may be cropped. */
	const MAX_CROP = 0.25;

	/**
	 * Whether the server can read page images for analysis (Imagick or GD with WebP/JPEG).
	 */
	public static function is_available(): bool {
		return class_exists( 'Imagick' ) || function_exists( 'imagecreatefromjpeg' );
	}

	/**
	 * Share of a page's height that is blank at the bottom, 0..SCAN_FRACTION.
	 *
	 * @param string $file Image path (WebP or JPEG).
	 * @return float|null Null when the image cannot be read.
	 */
	public static function blank_fraction( string $file ): ?float {
		if ( ! is_file( $file ) ) {
			return null;
		}

		$rows = class_exists( 'Imagick' ) ? self::rows_with_imagick( $file ) : self::rows_with_gd( $file );

		if ( null === $rows && class_exists( 'Imagick' ) ) {
			$rows = self::rows_with_gd( $file );
		}

		if ( null === $rows ) {
			return null;
		}

		return self::fraction_from_rows( $rows['blank'], $rows['height'] );
	}

	/**
	 * Blank rows as a share of the page height.
	 *
	 * @param int $blank  Blank rows counted from the bottom (within the scan).
	 * @param int $height Height of the analysed copy.
	 */
	public static function fraction_from_rows( int $blank, int $height ): float {
		if ( $height < 1 ) {
			return 0.0;
		}

		return min( self::SCAN_FRACTION, $blank / $height );
	}

	/**
	 * The crop for a whole document from its pages' blank fractions.
	 *
	 * The smallest blank fraction wins (every page keeps the same size), less a
	 * kept margin. Under MIN_CROP nothing is cropped; the result is capped at MAX_CROP.
	 *
	 * @param float[] $fractions Blank fraction of every page.
	 */
	public static function document_crop( array $fractions ): float {
		if ( array() === $fractions ) {
			return 0.0;
		}

		$crop = min( array_map( 'floatval', $fractions ) ) - self::KEEP_MARGIN;

		if ( $crop < self::MIN_CROP ) {
			return 0.0;
		}

		return round( min( self::MAX_CROP, $crop ), 4 );
	}

	/**
	 * Analyse the images of a folder.
	 *
	 * @param string $dir   Absolute folder.
	 * @param string $ext   Image extension without dot.
	 * @param int    $count Number of pages.
	 * @return float Crop 0..MAX_CROP, 0 when any page cannot be read.
	 */
	public static function analyze_folder( string $dir, string $ext, int $count ): float {
		$fractions = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$fraction = self::blank_fraction( trailingslashit( $dir ) . CPTDP_Page_Store::page_filename( $i, $ext ) );

			if ( null === $fraction ) {
				return 0.0;
			}

			$fractions[] = $fraction;

			if ( $fraction < self::KEEP_MARGIN + self::MIN_CROP ) {
				// Already too little to crop and no page can raise the minimum: stop early.
				return 0.0;
			}
		}

		return self::document_crop( $fractions );
	}

	/**
	 * Read the bottom of the image with Imagick.
	 *
	 * @param string $file Image path.
	 * @return array{blank:int,height:int}|null
	 */
	private static function rows_with_imagick( string $file ): ?array {
		try {
			$image = new Imagick( $file );
			$image->setImageBackgroundColor( 'white' );
			$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_REMOVE );

			if ( $image->getImageWidth() > self::ANALYSIS_WIDTH ) {
				$image->thumbnailImage( self::ANALYSIS_WIDTH, 0 );
			}

			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();
			$scan   = max( 1, (int) ceil( $height * self::SCAN_FRACTION ) );
			$top    = $height - $scan;
			$pixels = $image->exportImagePixels( 0, $top, $width, $scan, 'RGB', Imagick::PIXEL_CHAR );
			$image->clear();

			$limit = 255 - self::BLANK_TOLERANCE;
			$blank = 0;

			for ( $row = $scan - 1; $row >= 0; $row-- ) {
				$offset  = $row * $width * 3;
				$content = 0;

				for ( $x = 0; $x < $width * 3; $x += 3 ) {
					if ( $pixels[ $offset + $x ] < $limit || $pixels[ $offset + $x + 1 ] < $limit || $pixels[ $offset + $x + 2 ] < $limit ) {
						++$content;
					}
				}

				if ( $content > $width * ( 1 - self::BLANK_ROW_RATIO ) ) {
					break;
				}

				++$blank;
			}

			return array(
				'blank'  => $blank,
				'height' => $height,
			);
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Read the bottom of the image with GD (when Imagick is missing).
	 *
	 * @param string $file Image path.
	 * @return array{blank:int,height:int}|null
	 */
	private static function rows_with_gd( string $file ): ?array {
		$info = getimagesize( $file );

		if ( ! $info ) {
			return null;
		}

		$image = false;

		if ( 'image/webp' === $info['mime'] && function_exists( 'imagecreatefromwebp' ) ) {
			$image = imagecreatefromwebp( $file );
		} elseif ( 'image/jpeg' === $info['mime'] && function_exists( 'imagecreatefromjpeg' ) ) {
			$image = imagecreatefromjpeg( $file );
		}

		if ( ! $image ) {
			return null;
		}

		if ( imagesx( $image ) > self::ANALYSIS_WIDTH ) {
			$scaled = imagescale( $image, self::ANALYSIS_WIDTH, -1, IMG_BILINEAR_FIXED );

			if ( $scaled ) {
				$image = $scaled;
			}
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		$scan   = max( 1, (int) ceil( $height * self::SCAN_FRACTION ) );
		$limit  = 255 - self::BLANK_TOLERANCE;
		$blank  = 0;

		for ( $row = $height - 1; $row >= $height - $scan; $row-- ) {
			$content = 0;

			for ( $x = 0; $x < $width; $x++ ) {
				$rgb = imagecolorat( $image, $x, $row );

				if ( ( ( $rgb >> 16 ) & 0xFF ) < $limit || ( ( $rgb >> 8 ) & 0xFF ) < $limit || ( $rgb & 0xFF ) < $limit ) {
					++$content;
				}
			}

			if ( $content > $width * ( 1 - self::BLANK_ROW_RATIO ) ) {
				break;
			}

			++$blank;
		}

		return array(
			'blank'  => $blank,
			'height' => $height,
		);
	}
}
