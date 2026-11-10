<?php
/**
 * A collateral's raster image as a one-page set (DPC-023).
 *
 * A JPEG, PNG, WebP or GIF collateral becomes `p-001.<ext>` and its thumbnail `t-001.<ext>`,
 * made on the server with WordPress's own image editor (Imagick or GD, whichever exists).
 * There is no browser path and no bottom trim. SVG is never handled here.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns an image file into one page image and its thumbnail.
 */
class CPTDP_Image_Page_Converter {

	/**
	 * Largest image accepted, in pixels (width x height), checked from the header before decoding.
	 */
	const MAX_PIXELS = 40000000;

	/**
	 * Output extension of each mime type the set may be saved as.
	 */
	const OUTPUT_EXTS = array(
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
	);

	/**
	 * The pixel limit (filterable so small hosts and tests can lower it).
	 */
	public static function max_pixels(): int {
		/**
		 * Filters the largest image (width x height) turned into a page.
		 *
		 * @param int $max Default 40 000 000.
		 */
		return max( 1, (int) apply_filters( 'cptdp_image_max_pixels', self::MAX_PIXELS ) );
	}

	/**
	 * Whether the server has an image editor at all.
	 */
	public static function is_available(): bool {
		return wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) );
	}

	/**
	 * Why images cannot be processed, for the admin.
	 */
	public static function unavailable_reason(): string {
		return __( 'This server cannot process images.', 'custom-post-types-dp' );
	}

	/**
	 * Convert the image into `$folder` (which exists and is empty).
	 *
	 * @param array{id:int,path:string,fingerprint:string,token:string,kind:string} $source The collateral's source.
	 * @param string                                                             $folder Output folder.
	 * @return array{count:int,width:int,height:int,ext:string,crop_bottom:float,producer:string,thumb_width?:int,thumb_height?:int}|WP_Error
	 */
	public static function convert( array $source, string $folder ) {
		$mime = (string) get_post_mime_type( $source['id'] );
		$size = wp_getimagesize( $source['path'] );

		// By content: a real image of an allowed type, the type it claims to be, and not a decompression bomb.
		if (
			! is_array( $size ) || empty( $size[0] ) || empty( $size[1] )
			|| ! in_array( (string) ( $size['mime'] ?? '' ), CPTDP_Page_Store::IMAGE_MIMES, true )
			|| (string) $size['mime'] !== $mime
		) {
			return new WP_Error( 'cptdp_not_an_image', __( 'The file is not a valid JPEG, PNG, WebP or GIF image.', 'custom-post-types-dp' ) );
		}

		if ( (int) $size[0] * (int) $size[1] > self::max_pixels() ) {
			return new WP_Error( 'cptdp_not_an_image', __( 'The image is too large to turn into a page.', 'custom-post-types-dp' ) );
		}

		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
			return new WP_Error( 'cptdp_unavailable', self::unavailable_reason() );
		}

		$out_mime = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'image/webp' : 'image/jpeg';
		$ext      = self::OUTPUT_EXTS[ $out_mime ];

		// Same widths as a converted PDF page, never upscaled, so a small logo keeps its size.
		$target = (int) $size[1] >= (int) $size[0] ? CPTDP_Page_Converter::WIDTH_PORTRAIT : CPTDP_Page_Converter::WIDTH_LANDSCAPE;
		$page   = self::save_resized( $source['path'], $folder . '/' . CPTDP_Page_Store::page_filename( 1, $ext ), $target, CPTDP_Page_Converter::QUALITY, $out_mime );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$result = array(
			'count'       => 1,
			'width'       => $page['width'],
			'height'      => $page['height'],
			'ext'         => $ext,
			'crop_bottom' => 0.0,
			'producer'    => 'server',
		);

		// The thumbnail is made from the saved page. A set without one is valid (DPB-031).
		$thumb_path = $folder . '/' . CPTDP_Page_Thumbs::filename( 1, $ext );
		$thumb      = self::save_resized( $folder . '/' . CPTDP_Page_Store::page_filename( 1, $ext ), $thumb_path, CPTDP_Page_Thumbs::WIDTH, CPTDP_Page_Thumbs::QUALITY, $out_mime );

		if ( is_wp_error( $thumb ) ) {
			if ( is_file( $thumb_path ) ) {
				wp_delete_file( $thumb_path );
			}
		} else {
			$result['thumb_width']  = $thumb['width'];
			$result['thumb_height'] = $thumb['height'];
		}

		return $result;
	}

	/**
	 * Open an image, shrink it to at most `$max_width` wide, and save it at exactly `$path`.
	 *
	 * @param string $from      Image to read.
	 * @param string $path      File to write.
	 * @param int    $max_width Largest width.
	 * @param int    $quality   Quality 1-100.
	 * @param string $mime      Output mime type (one of OUTPUT_EXTS).
	 * @return array{width:int,height:int}|WP_Error
	 */
	private static function save_resized( string $from, string $path, int $max_width, int $quality, string $mime ) {
		$editor = wp_get_image_editor( $from );

		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'cptdp_not_an_image', __( 'The image could not be read.', 'custom-post-types-dp' ) );
		}

		$current = $editor->get_size();

		// resize() refuses to keep the size, so only call it when the image is wider than the target.
		if ( (int) $current['width'] > $max_width ) {
			$resized = $editor->resize( $max_width, null, false );

			if ( is_wp_error( $resized ) ) {
				return new WP_Error( 'cptdp_conversion_failed', __( 'The image could not be resized.', 'custom-post-types-dp' ) );
			}
		}

		$editor->set_quality( $quality );
		$saved = $editor->save( $path, $mime );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return new WP_Error( 'cptdp_conversion_failed', __( 'The page image could not be saved.', 'custom-post-types-dp' ) );
		}

		// A site filter (`image_editor_output_format`) may have changed the format: only WebP or JPEG at the expected name is kept.
		$saved_path = wp_normalize_path( (string) $saved['path'] );

		if ( wp_normalize_path( $path ) !== $saved_path || ( $saved['mime-type'] ?? '' ) !== $mime ) {
			if ( is_file( $saved_path ) && dirname( $saved_path ) === dirname( wp_normalize_path( $path ) ) ) {
				wp_delete_file( $saved_path );
			}

			return new WP_Error( 'cptdp_conversion_failed', __( 'The page image could not be saved as WebP or JPEG.', 'custom-post-types-dp' ) );
		}

		return array(
			'width'  => (int) $saved['width'],
			'height' => (int) $saved['height'],
		);
	}
}
