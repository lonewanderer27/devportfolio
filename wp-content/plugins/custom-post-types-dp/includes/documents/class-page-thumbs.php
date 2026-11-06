<?php
/**
 * Small page thumbnails (DPB-031).
 *
 * One tiny image per page (`t-001.webp` beside `p-001.webp`), used by the mobile Pages sheet
 * and the admin Documents list. They are made when the page images are made, from the page
 * already in memory, or later from the stored page images (`wp cptdp pages thumbs`) without
 * converting the document again. A thumbnail never fails a set: any problem leaves the set
 * without thumbnails.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thumbnail files.
 */
class CPTDP_Page_Thumbs {

	/** Width in pixels: three columns at 2x on a 390px phone. */
	const WIDTH = 240;

	const QUALITY     = 65;
	const QUALITY_LOW = 45;
	const MAX_BYTES   = 20480;

	/**
	 * File name of a thumbnail.
	 *
	 * @param int    $page Page number, 1-based.
	 * @param string $ext  Extension without dot.
	 */
	public static function filename( int $page, string $ext ): string {
		return sprintf( 't-%03d.%s', $page, $ext );
	}

	/**
	 * Whether thumbnails can be made on this server.
	 */
	public static function is_available(): bool {
		return class_exists( 'Imagick' );
	}

	/**
	 * Write the thumbnail of a page that is already open.
	 *
	 * @param Imagick $page Page raster (not changed).
	 * @param string  $path Output file.
	 * @param string  $ext  `webp` or `jpg`.
	 * @return array{width:int,height:int}|null Size of the thumbnail, null on failure.
	 */
	public static function write( Imagick $page, string $path, string $ext ): ?array {
		try {
			$thumb = clone $page;
			$width = $thumb->getImageWidth();

			if ( $width > self::WIDTH ) {
				$thumb->resizeImage( self::WIDTH, 0, Imagick::FILTER_LANCZOS, 1 );
				$thumb->unsharpMaskImage( 0, 0.5, 0.6, 0.02 );
			}

			$thumb->setImageFormat( 'webp' === $ext ? 'webp' : 'jpeg' );
			$thumb->setImageCompressionQuality( self::QUALITY );

			if ( 'webp' === $ext ) {
				$thumb->setOption( 'webp:method', '6' );
				$thumb->setOption( 'webp:auto-filter', 'true' );
			}

			$thumb->stripImage();
			$blob = $thumb->getImageBlob();

			// A heavy one (a busy page) is encoded once more, smaller.
			if ( strlen( $blob ) > self::MAX_BYTES ) {
				$thumb->setImageCompressionQuality( self::QUALITY_LOW );
				$blob = $thumb->getImageBlob();
			}

			$size = array(
				'width'  => $thumb->getImageWidth(),
				'height' => $thumb->getImageHeight(),
			);
			$thumb->clear();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
			return false === file_put_contents( $path, $blob ) ? null : $size;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Make the thumbnails of a stored set from its page images (no conversion), one page at a time.
	 *
	 * @param string $dir   Absolute folder of the set.
	 * @param string $ext   Image extension of the set.
	 * @param int    $count Number of pages.
	 * @return array{width:int,height:int}|null Size of the first thumbnail, null when the set could not be completed (partial files are removed).
	 */
	public static function make_for_folder( string $dir, string $ext, int $count ): ?array {
		if ( ! self::is_available() || $count < 1 || ! is_dir( $dir ) ) {
			return null;
		}

		$first = null;

		for ( $i = 1; $i <= $count; $i++ ) {
			$source = $dir . '/' . CPTDP_Page_Store::page_filename( $i, $ext );

			try {
				$page = new Imagick();
				$page->readImage( $source );
				$size = self::write( $page, $dir . '/' . self::filename( $i, $ext ), $ext );
				$page->clear();
			} catch ( Throwable $e ) {
				$size = null;
			}

			if ( null === $size ) {
				self::delete( $dir, $ext, $count );

				return null;
			}

			if ( null === $first ) {
				$first = $size;
			}
		}

		return $first;
	}

	/**
	 * Whether every thumbnail of a set is on disk (the first and last are enough of a check).
	 *
	 * @param string $dir   Absolute folder of the set.
	 * @param string $ext   Image extension.
	 * @param int    $count Number of pages.
	 */
	public static function exist( string $dir, string $ext, int $count ): bool {
		return $count > 0
			&& is_file( $dir . '/' . self::filename( 1, $ext ) )
			&& is_file( $dir . '/' . self::filename( $count, $ext ) );
	}

	/**
	 * Remove the thumbnails of a set.
	 *
	 * @param string $dir   Absolute folder of the set.
	 * @param string $ext   Image extension.
	 * @param int    $count Number of pages.
	 */
	public static function delete( string $dir, string $ext, int $count ): void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$file = $dir . '/' . self::filename( $i, $ext );

			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
