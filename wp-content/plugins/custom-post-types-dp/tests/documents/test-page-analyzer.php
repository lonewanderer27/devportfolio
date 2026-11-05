<?php
/**
 * Bottom margin analysis, the crop stored in the manifest and how the API uses it.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 */
class CPTDP_Test_Page_Analyzer extends CPTDP_Pages_Test_Case {

	/**
	 * Write a page image: white, with a dark block from 5% down to `$content_ends` of the height.
	 *
	 * @param string $path         File path.
	 * @param float  $content_ends Where the content stops, as a share of the height (1.0 = reaches the bottom).
	 * @param int    $background   Grey level of the background, 255 = pure white.
	 * @param array  $fill         RGB of a full-bleed background instead of grey (empty = use $background).
	 * @param string $format       jpeg or webp.
	 */
	private function write_page( string $path, float $content_ends, int $background = 255, array $fill = array(), string $format = 'jpeg' ): void {
		$width  = 600;
		$height = 800;
		$image  = imagecreatetruecolor( $width, $height );
		$bg     = array() !== $fill ? imagecolorallocate( $image, $fill[0], $fill[1], $fill[2] ) : imagecolorallocate( $image, $background, $background, $background );
		imagefill( $image, 0, 0, $bg );

		if ( $content_ends > 0 ) {
			$black = imagecolorallocate( $image, 20, 20, 20 );
			imagefilledrectangle( $image, 60, (int) ( $height * 0.05 ), $width - 60, (int) ( $height * $content_ends ) - 1, $black );
		}

		wp_mkdir_p( dirname( $path ) );
		'webp' === $format ? imagewebp( $image, $path, 90 ) : imagejpeg( $image, $path, 92 );
	}

	/**
	 * A folder of pages whose content ends at the given shares of the height.
	 *
	 * @param float[] $ends   Content end per page.
	 * @param string  $format Image format.
	 * @return string Folder.
	 */
	private function pages_folder( array $ends, string $format = 'jpeg' ): string {
		$dir = trailingslashit( get_temp_dir() ) . 'cptdp-analyzer-' . wp_generate_uuid4();
		$ext = 'webp' === $format ? 'webp' : 'jpg';

		foreach ( $ends as $i => $end ) {
			$this->write_page( $dir . '/' . CPTDP_Page_Store::page_filename( $i + 1, $ext ), $end, 255, array(), $format );
		}

		return $dir;
	}

	public function test_a_page_with_content_at_the_bottom_has_no_blank_strip(): void {
		$dir = $this->pages_folder( array( 1.0 ) );

		$this->assertEqualsWithDelta( 0.0, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-001.jpg' ), 0.01 );
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 1 ) );
	}

	public function test_a_page_with_twenty_percent_blank_gives_about_seventeen_and_a_half(): void {
		$dir = $this->pages_folder( array( 0.8 ) );

		$this->assertEqualsWithDelta( 0.2, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-001.jpg' ), 0.015 );
		$this->assertEqualsWithDelta( 0.175, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 1 ), 0.015 );
	}

	public function test_webp_pages_are_analysed_too(): void {
		if ( ! function_exists( 'imagewebp' ) ) {
			$this->markTestSkipped( 'GD has no WebP support.' );
		}

		$dir = $this->pages_folder( array( 0.8 ), 'webp' );

		$this->assertEqualsWithDelta( 0.175, CPTDP_Page_Analyzer::analyze_folder( $dir, 'webp', 1 ), 0.015 );
	}

	public function test_the_page_with_the_least_blank_space_decides_for_the_whole_document(): void {
		// Blank space: 20%, 40%, 30% and 22%. The smallest (20%) minus the kept margin wins.
		$dir = $this->pages_folder( array( 0.8, 0.6, 0.7, 0.78 ) );

		$this->assertEqualsWithDelta( 0.175, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 4 ), 0.015 );
	}

	public function test_one_full_page_among_blank_ones_means_no_crop(): void {
		$dir = $this->pages_folder( array( 0.5, 0.5, 0.97, 0.5 ) );

		$this->assertSame( 0.0, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 4 ) );
	}

	public function test_a_full_bleed_colour_page_is_never_cropped(): void {
		$dir = trailingslashit( get_temp_dir() ) . 'cptdp-analyzer-' . wp_generate_uuid4();
		$this->write_page( $dir . '/p-001.jpg', 0.0, 255, array( 200, 220, 255 ) );

		$this->assertEqualsWithDelta( 0.0, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-001.jpg' ), 0.001 );
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 1 ) );
	}

	public function test_a_crop_under_two_percent_is_not_applied(): void {
		// 4% blank minus the 2.5% margin is 1.5%: under the 2% minimum.
		$dir = $this->pages_folder( array( 0.96 ) );

		$this->assertSame( 0.0, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 1 ) );
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::document_crop( array( 0.04 ) ) );
		$this->assertSame( 0.0225, CPTDP_Page_Analyzer::document_crop( array( 0.0475 ) ) );
	}

	public function test_the_crop_is_capped_at_a_quarter_of_the_page(): void {
		$dir = $this->pages_folder( array( 0.3, 0.1 ) );

		$this->assertSame( CPTDP_Page_Analyzer::MAX_CROP, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 2 ) );
		$this->assertSame( 0.25, CPTDP_Page_Analyzer::document_crop( array( 0.9 ) ) );
	}

	public function test_a_blank_page_is_capped_by_the_scan_and_does_not_lower_the_minimum(): void {
		$dir = $this->pages_folder( array( 0.0, 0.8 ) );

		$this->assertEqualsWithDelta( CPTDP_Page_Analyzer::SCAN_FRACTION, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-001.jpg' ), 0.001 );
		$this->assertEqualsWithDelta( 0.175, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 2 ), 0.015 );
	}

	public function test_noise_near_white_counts_as_blank_but_grey_is_content(): void {
		$dir = trailingslashit( get_temp_dir() ) . 'cptdp-analyzer-' . wp_generate_uuid4();
		wp_mkdir_p( $dir );

		// 250 is within 8 of white: blank all the way up to the scan limit.
		$this->write_page( $dir . '/p-001.jpg', 0.0, 250 );
		$this->assertEqualsWithDelta( CPTDP_Page_Analyzer::SCAN_FRACTION, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-001.jpg' ), 0.001 );

		// 235 is a visible grey: content in every row.
		$this->write_page( $dir . '/p-002.jpg', 0.0, 235 );
		$this->assertEqualsWithDelta( 0.0, CPTDP_Page_Analyzer::blank_fraction( $dir . '/p-002.jpg' ), 0.001 );
	}

	public function test_a_page_that_cannot_be_read_gives_no_crop(): void {
		$dir = $this->pages_folder( array( 0.5 ) );

		$this->assertNull( CPTDP_Page_Analyzer::blank_fraction( $dir . '/missing.jpg' ) );
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::analyze_folder( $dir, 'jpg', 2 ), 'Page 2 does not exist.' );
	}

	public function test_document_crop_of_nothing_is_zero(): void {
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::document_crop( array() ) );
		$this->assertSame( 0.0, CPTDP_Page_Analyzer::fraction_from_rows( 5, 0 ) );
		$this->assertSame( CPTDP_Page_Analyzer::SCAN_FRACTION, CPTDP_Page_Analyzer::fraction_from_rows( 999, 100 ) );
	}

	public function test_imagick_and_gd_agree(): void {
		if ( ! class_exists( 'Imagick' ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
			$this->markTestSkipped( 'Needs both Imagick and GD.' );
		}

		$dir  = $this->pages_folder( array( 0.8 ) );
		$call = static function ( string $method ) use ( $dir ) {
			$reflection = new ReflectionMethod( CPTDP_Page_Analyzer::class, $method );
			$reflection->setAccessible( true );

			return $reflection->invoke( null, $dir . '/p-001.jpg' );
		};

		$imagick = $call( 'rows_with_imagick' );
		$gd      = $call( 'rows_with_gd' );

		$this->assertNotNull( $imagick );
		$this->assertNotNull( $gd );
		$this->assertEqualsWithDelta(
			CPTDP_Page_Analyzer::fraction_from_rows( $imagick['blank'], $imagick['height'] ),
			CPTDP_Page_Analyzer::fraction_from_rows( $gd['blank'], $gd['height'] ),
			0.01
		);
	}

	public function test_the_browser_generator_uses_the_same_thresholds(): void {
		$js = (string) file_get_contents( CPTDP_PATH . 'assets/admin-document-pages.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$expected = array(
			'ANALYSIS_WIDTH'  => CPTDP_Page_Analyzer::ANALYSIS_WIDTH,
			'BLANK_TOLERANCE' => CPTDP_Page_Analyzer::BLANK_TOLERANCE,
			'BLANK_ROW_RATIO' => CPTDP_Page_Analyzer::BLANK_ROW_RATIO,
			'SCAN_FRACTION'   => CPTDP_Page_Analyzer::SCAN_FRACTION,
			'KEEP_MARGIN'     => CPTDP_Page_Analyzer::KEEP_MARGIN,
			'MIN_CROP'        => CPTDP_Page_Analyzer::MIN_CROP,
			'MAX_CROP'        => CPTDP_Page_Analyzer::MAX_CROP,
		);

		foreach ( $expected as $name => $value ) {
			$this->assertSame( 1, preg_match( '/var ' . $name . ' = ([0-9.]+);/', $js, $match ), "$name is declared in the admin script." );
			$this->assertEqualsWithDelta( $value, (float) $match[1], 0.0000001, "$name matches the PHP constant." );
		}
	}

	// The crop in the manifest and the API.

	/**
	 * A document with real page images and a manifest.
	 *
	 * @param float[] $ends Content end per page.
	 * @return int Document ID.
	 */
	private function document_with_images( array $ends ): int {
		$id     = $this->make_document( $this->make_pdf_attachment( count( $ends ) ) );
		$folder = CPTDP_Page_Store::folder_for( $id );

		foreach ( $ends as $i => $end ) {
			$this->write_page( $folder . '/' . CPTDP_Page_Store::page_filename( $i + 1, 'jpg' ), $end );
		}

		CPTDP_Page_Store::save_manifest(
			$id,
			array(
				'count'  => count( $ends ),
				'width'  => 600,
				'height' => 800,
				'ext'    => 'jpg',
				'dir'    => basename( $folder ),
			)
		);

		return $id;
	}

	private function store_crop( int $id, $crop ): void {
		$manifest                = CPTDP_Page_Store::get_manifest( $id );
		$manifest['crop_bottom'] = $crop;
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );
	}

	public function test_the_api_returns_the_crop_and_a_ratio_that_accounts_for_it(): void {
		$id = $this->document_with_images( array( 1.0, 1.0 ) );
		$this->store_crop( $id, 0.2 );

		$pages = cptdp_get_document_pages( $id );

		$this->assertSame( 0.2, $pages['crop_bottom'] );
		$this->assertEqualsWithDelta( 600 / ( 800 * 0.8 ), $pages['ratio'], 0.0001 );
		$this->assertSame( 800, $pages['height'], 'The files themselves are not cropped.' );
	}

	public function test_without_a_stored_crop_the_ratio_is_the_natural_one(): void {
		$id    = $this->document_with_images( array( 1.0 ) );
		$pages = cptdp_get_document_pages( $id );

		$this->assertSame( 0.0, $pages['crop_bottom'] );
		$this->assertEqualsWithDelta( 0.75, $pages['ratio'], 0.0001 );
	}

	public function test_turning_the_setting_off_gives_no_crop_without_regenerating(): void {
		$id = $this->document_with_images( array( 1.0 ) );
		$this->store_crop( $id, 0.2 );

		update_option( 'cptdp_documents', array( 'trim_bottom' => false ) );
		$off = cptdp_get_document_pages( $id );
		$this->assertSame( 0.0, $off['crop_bottom'] );
		$this->assertEqualsWithDelta( 0.75, $off['ratio'], 0.0001 );
		$this->assertSame( 0.2, CPTDP_Page_Store::stored_crop( (array) CPTDP_Page_Store::get_manifest( $id ) ), 'The measured value is kept.' );

		update_option( 'cptdp_documents', array( 'trim_bottom' => true ) );
		$this->assertSame( 0.2, cptdp_get_document_pages( $id )['crop_bottom'] );
	}

	public function test_an_invalid_stored_crop_is_ignored(): void {
		foreach ( array( 0.9, -0.1, 'abc', null, 0.2500001 ) as $bad ) {
			$this->assertSame( 0.0, CPTDP_Page_Store::clamp_crop( $bad ), var_export( $bad, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		$this->assertSame( 0.25, CPTDP_Page_Store::clamp_crop( 0.25 ) );
		$this->assertSame( 0.1235, CPTDP_Page_Store::clamp_crop( '0.12345' ) );
	}

	public function test_recrop_recomputes_from_the_stored_images_without_converting(): void {
		$id = $this->document_with_images( array( 0.8, 0.9 ) );
		$this->assertEqualsWithDelta( 0.075, (float) CPTDP_Page_Store::recrop( $id ), 0.015, 'The page with 10% blank decides.' );

		$folder = CPTDP_Page_Store::folder_for( $id );
		$this->write_page( $folder . '/p-002.jpg', 0.5 );
		$this->assertEqualsWithDelta( 0.175, (float) CPTDP_Page_Store::recrop( $id ), 0.015, 'Now the 20% page decides.' );
		$this->assertEqualsWithDelta( 0.175, CPTDP_Page_Store::stored_crop( (array) CPTDP_Page_Store::get_manifest( $id ) ), 0.015 );
	}

	public function test_recrop_without_valid_images_does_nothing(): void {
		$this->assertNull( CPTDP_Page_Store::recrop( $this->make_document() ) );
		$this->assertNull( CPTDP_Page_Store::recrop( $this->make_document( $this->make_pdf_attachment( 1 ) ) ) );
	}

	public function test_the_server_conversion_stores_the_crop_it_found(): void {
		$this->require_server_tooling();

		// Page 1: a block in the upper part (a lot of blank space below). Page 2: the same plus a footer mark 2% from the bottom.
		$streams = array(
			'0.2 0.4 0.8 rg 40 500 515 300 re f',
			'0.2 0.4 0.8 rg 40 500 515 300 re f 0 0 0 rg 250 18 100 8 re f',
		);
		$id      = $this->make_document( $this->make_pdf_attachment( 2, 595, 842, $this->pdf_bytes( 2, 595, 842, $streams ) ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 0.0, $result['crop_bottom'], 'A footer on any page keeps the whole bottom.' );
	}

	public function test_the_server_conversion_crops_documents_that_end_high(): void {
		$this->require_server_tooling();

		$streams = array( '0.2 0.4 0.8 rg 40 500 515 300 re f', '0.2 0.4 0.8 rg 40 560 515 240 re f' );
		$id      = $this->make_document( $this->make_pdf_attachment( 2, 595, 842, $this->pdf_bytes( 2, 595, 842, $streams ) ) );

		$status = CPTDP_Page_Scheduler::convert_now( $id );
		$pages  = cptdp_get_document_pages( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, $status );
		$this->assertSame( CPTDP_Page_Analyzer::MAX_CROP, $pages['crop_bottom'], 'Over 59% blank on both pages: capped at a quarter.' );
		$this->assertEqualsWithDelta( $pages['width'] / ( $pages['height'] * 0.75 ), $pages['ratio'], 0.001 );
	}
}
