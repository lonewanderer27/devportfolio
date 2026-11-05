<?php
/**
 * Server page conversion tests. Skipped when no tool is available.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 */
class CPTDP_Test_Page_Converter extends CPTDP_Pages_Test_Case {

	public function test_converts_each_page_of_a_portrait_pdf(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 3, $result['count'] );
		$this->assertSame( 'server', $result['producer'] );
		$this->assertContains( $result['ext'], array( 'webp', 'jpg' ) );
		$this->assertLessThan( $result['height'], $result['width'], 'portrait' );
		$this->assertLessThanOrEqual( CPTDP_Page_Converter::WIDTH_PORTRAIT + 2, $result['width'] );

		$folder = CPTDP_Page_Store::folder_for( $id );
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->assertFileExists( $folder . '/' . CPTDP_Page_Store::page_filename( $i, $result['ext'] ) );
		}
		$this->assertFileDoesNotExist( $folder . '/' . CPTDP_Page_Store::page_filename( 4, $result['ext'] ) );
	}

	public function test_landscape_pdf_gives_wide_images(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 1, 842, 595 ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 1, $result['count'] );
		$this->assertGreaterThan( $result['height'], $result['width'], 'landscape' );
	}

	public function test_the_full_save_flow_ends_with_valid_pages(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$pages = cptdp_get_document_pages( $id );
		$this->assertIsArray( $pages );
		$this->assertSame( 2, $pages['count'] );
		$this->assertSame( 'server', CPTDP_Page_Store::get_manifest( $id )['producer'] );
	}

	/**
	 * @dataProvider engines
	 *
	 * @param string $method Private converter method.
	 */
	public function test_each_engine_converts_when_present( string $method ): void {
		if ( 'convert_with_imagick' === $method && ! CPTDP_Page_Converter::imagick_can_read_pdf() ) {
			$this->markTestSkipped( 'Imagick cannot read PDF here.' );
		}
		if ( 'convert_with_ghostscript' === $method && null === CPTDP_Page_Converter::ghostscript_binary() ) {
			$this->markTestSkipped( 'Ghostscript is not available.' );
		}

		$attachment = $this->make_pdf_attachment( 2 );
		$folder     = CPTDP_Page_Store::base_dir() . '/engine-test';
		wp_mkdir_p( $folder );

		$reflection = new ReflectionMethod( CPTDP_Page_Converter::class, $method );
		$reflection->setAccessible( true );
		$result = $reflection->invoke( null, (string) get_attached_file( $attachment ), $folder );

		$this->assertNotWPError( $result );
		$this->assertSame( 2, $result['count'] );
		$this->assertFileExists( $folder . '/' . CPTDP_Page_Store::page_filename( 2, $result['ext'] ) );
	}

	/**
	 * @return array<string,string[]>
	 */
	public function engines(): array {
		return array(
			'imagick'     => array( 'convert_with_imagick' ),
			'ghostscript' => array( 'convert_with_ghostscript' ),
		);
	}

	public function test_unavailable_when_forced_off(): void {
		$this->require_server_tooling();
		add_filter( 'cptdp_pages_server_available', '__return_false' );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertFalse( CPTDP_Page_Converter::is_available() );
		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_unavailable', $result->get_error_code() );
	}

	public function test_no_pdf_is_an_error(): void {
		$id     = $this->make_document();
		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_no_pdf', $result->get_error_code() );
	}

	public function test_a_file_that_is_not_a_pdf_is_refused(): void {
		$id     = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, 'just some text, not a pdf' ) );
		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_not_a_pdf', $result->get_error_code() );
	}

	public function test_a_corrupt_pdf_fails_and_leaves_no_folder(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, "%PDF-1.4\nthis is not really a pdf\n" ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_conversion_failed', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( CPTDP_Page_Store::folder_for( $id ) );
	}

	public function test_a_failing_conversion_in_auto_mode_hands_over_to_the_browser(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, "%PDF-1.4\nbroken\n" ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Scheduler::convert_now( $id ) );
		$this->assertNotSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_a_failing_conversion_in_server_mode_is_failed(): void {
		$this->require_server_tooling();
		update_option( 'cptdp_documents', array( 'pages_mode' => 'server' ) );
		$id = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, "%PDF-1.4\nbroken\n" ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Scheduler::convert_now( $id ) );
	}

	public function test_a_polyglot_that_only_mentions_the_pdf_header_is_refused(): void {
		$bytes = "%!PS-Adobe-3.0 EPSF-3.0\n% not a pdf, but it says %PDF-1.4 later\n" . $this->pdf_bytes( 1 );
		$id    = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, $bytes ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_not_a_pdf', $result->get_error_code() );

		$id = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, "\n  " . $this->pdf_bytes( 1 ) ) );
		$this->assertSame( 'cptdp_not_a_pdf', CPTDP_Page_Converter::convert( $id )->get_error_code(), 'The header must be the first thing in the file.' );
	}

	public function test_ghostscript_takes_over_when_imagick_cannot_read_the_pdf(): void {
		if ( ! CPTDP_Page_Converter::imagick_can_read_pdf() || null === CPTDP_Page_Converter::ghostscript_binary() ) {
			$this->markTestSkipped( 'Needs both Imagick with PDF and Ghostscript.' );
		}

		// Imagick is pointed at a file it cannot read, as a policy.xml rule would make it fail.
		add_filter(
			'cptdp_pages_imagick_source',
			static function (): string {
				return '/nonexistent/denied.pdf';
			}
		);
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 2, $result['count'] );
		$this->assertSame( 'jpg', $result['ext'], 'Ghostscript writes JPEG.' );
		$this->assertFileExists( CPTDP_Page_Store::folder_for( $id ) . '/p-002.jpg' );
	}

	public function test_without_ghostscript_an_imagick_failure_is_reported(): void {
		if ( ! CPTDP_Page_Converter::imagick_can_read_pdf() ) {
			$this->markTestSkipped( 'Imagick cannot read PDF here.' );
		}

		add_filter(
			'cptdp_pages_imagick_source',
			static function (): string {
				return '/nonexistent/denied.pdf';
			}
		);
		$id     = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$result = CPTDP_Page_Converter::convert( $id );

		if ( null !== CPTDP_Page_Converter::ghostscript_binary() ) {
			$this->assertNotWPError( $result, 'Ghostscript exists, so the retry succeeds (covered above).' );
			return;
		}

		$this->assertWPError( $result );
	}

	/**
	 * @dataProvider engines
	 *
	 * @param string $method Private converter method.
	 */
	public function test_a_pdf_over_the_page_limit_is_refused_by_both_engines( string $method ): void {
		if ( 'convert_with_imagick' === $method && ! CPTDP_Page_Converter::imagick_can_read_pdf() ) {
			$this->markTestSkipped( 'Imagick cannot read PDF here.' );
		}
		if ( 'convert_with_ghostscript' === $method && null === CPTDP_Page_Converter::ghostscript_binary() ) {
			$this->markTestSkipped( 'Ghostscript is not available.' );
		}

		add_filter(
			'cptdp_pages_max_pages',
			static function (): int {
				return 2;
			}
		);
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_document( $attachment );
		$folder     = CPTDP_Page_Store::folder_for( $id );
		wp_mkdir_p( $folder );

		$reflection = new ReflectionMethod( CPTDP_Page_Converter::class, $method );
		$reflection->setAccessible( true );
		$result = $reflection->invoke( null, (string) get_attached_file( $attachment ), $folder );

		$this->assertWPError( $result, 'A longer PDF is refused, not silently cut to the limit.' );
		$this->assertSame( 'cptdp_conversion_failed', $result->get_error_code() );
		$this->assertStringContainsString( '2 pages', $result->get_error_message() );
	}

	public function test_a_pdf_at_the_page_limit_still_converts(): void {
		$this->require_server_tooling();
		add_filter(
			'cptdp_pages_max_pages',
			static function (): int {
				return 3;
			}
		);
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 3, $result['count'] );
	}

	public function test_the_result_names_the_pdf_it_was_made_from(): void {
		$this->require_server_tooling();
		$id = $this->make_document( $this->make_pdf_attachment( 1 ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( CPTDP_Page_Store::get_source( $id )['token'], $result['source_token'] );
	}

	public function test_a_noisy_ghostscript_run_does_not_stall(): void {
		if ( null === CPTDP_Page_Converter::ghostscript_binary() ) {
			$this->markTestSkipped( 'Ghostscript is not available.' );
		}

		// A PDF whose page content makes Ghostscript warn a lot: 3000 unknown operators.
		$noise = str_repeat( "1 2 badop\n", 3000 ) . '0.2 0.4 0.8 rg 40 40 100 100 re f';
		$id    = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, $this->pdf_bytes( 1, 595, 842, array( $noise ) ) ) );
		$folder = CPTDP_Page_Store::folder_for( $id );
		wp_mkdir_p( $folder );

		$reflection = new ReflectionMethod( CPTDP_Page_Converter::class, 'convert_with_ghostscript' );
		$reflection->setAccessible( true );
		$started = microtime( true );
		$result  = $reflection->invoke( null, (string) CPTDP_Page_Store::get_source( $id )['path'], $folder );

		$this->assertLessThan( 30, microtime( true ) - $started );
		$this->assertNotWPError( $result );
	}
}
