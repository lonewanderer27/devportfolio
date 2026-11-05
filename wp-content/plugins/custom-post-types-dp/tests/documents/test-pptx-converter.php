<?php
/**
 * Presentation (PPTX, PPT, ODP) validation and conversion (DPB-026).
 *
 * Conversion tests skip when LibreOffice is not installed.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Pptx_Converter extends CPTDP_Pages_Test_Case {

	private function fixture( string $kind ): string {
		return __DIR__ . '/../fixtures/sample.' . $kind;
	}

	/**
	 * Temporary files written by write(), removed in tear_down().
	 *
	 * @var string[]
	 */
	private array $written = array();

	public function tear_down(): void {
		foreach ( $this->written as $file ) {
			wp_delete_file( $file );
		}
		$this->written = array();

		parent::tear_down();
	}

	private function write( string $bytes, string $kind ): string {
		$base = wp_tempnam( 'cptdp-test-' );
		$file = $base . '.' . $kind;
		rename( $base, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		file_put_contents( $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$this->written[] = $file;

		return $file;
	}

	/**
	 * Entries of a minimal valid PPTX package.
	 *
	 * @param array<string,string> $extra Extra or replaced entries.
	 * @return array<string,string>
	 */
	private function pptx_entries( array $extra = array() ): array {
		return array_merge(
			array(
				'[Content_Types].xml'  => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>',
				'ppt/presentation.xml' => '<p:presentation xmlns:p="x"/>',
			),
			$extra
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function odp_entries( array $extra = array() ): array {
		return array_merge(
			array(
				'mimetype'    => 'application/vnd.oasis.opendocument.presentation',
				'content.xml' => '<office:document-content xmlns:draw="d" xmlns:xlink="x"/>',
			),
			$extra
		);
	}

	private function assertRefused( string $code, string $bytes, string $kind ): void {
		$file = $this->write( $bytes, $kind );
		$result = CPTDP_Pptx_Converter::inspect( $file, $kind );
		wp_delete_file( $file );

		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	private function temp_folders(): array {
		return (array) glob( trailingslashit( get_temp_dir() ) . 'cptdp-pptx-*', GLOB_ONLYDIR );
	}

	public function test_the_real_fixtures_pass_inspection(): void {
		foreach ( array( 'pptx', 'ppt', 'odp' ) as $kind ) {
			$this->assertTrue( CPTDP_Pptx_Converter::inspect( $this->fixture( $kind ), $kind ), $kind );
		}
	}

	public function test_a_minimal_package_passes_and_other_kinds_are_refused(): void {
		$file = $this->write( $this->zip_bytes( $this->pptx_entries() ), 'pptx' );
		$this->assertTrue( CPTDP_Pptx_Converter::inspect( $file, 'pptx' ) );
		$this->assertWPError( CPTDP_Pptx_Converter::inspect( $file, 'pdf' ) );
		$this->assertWPError( CPTDP_Pptx_Converter::inspect( $file, 'odp' ), 'A pptx is not an odp.' );
		wp_delete_file( $file );
	}

	public function test_pptx_refusals(): void {
		$this->assertRefused( 'cptdp_pptx_invalid', 'just some text', 'pptx' );
		$this->assertRefused( 'cptdp_pptx_invalid', $this->zip_bytes( array( 'hello.txt' => 'x' ) ), 'pptx' );
		$this->assertRefused( 'cptdp_pptx_invalid', $this->zip_bytes( array( '[Content_Types].xml' => '<Types/>' ) ), 'pptx' );
		$this->assertRefused( 'cptdp_pptx_macros', $this->zip_bytes( $this->pptx_entries( array( '[Content_Types].xml' => '<Types><Override ContentType="application/vnd.ms-powerpoint.presentation.macroEnabled.main+xml"/></Types>' ) ) ), 'pptx' );
		$this->assertRefused( 'cptdp_pptx_macros', $this->zip_bytes( $this->pptx_entries( array( 'ppt/vbaProject.bin' => 'x' ) ) ), 'pptx' );
	}

	public function test_pptx_external_objects_are_refused_but_hyperlinks_and_pictures_are_not(): void {
		$rels = static function ( string $type, string $mode ): string {
			return '<Relationships xmlns="r"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $type . '" Target="https://evil.example/x" TargetMode="' . $mode . '"/></Relationships>';
		};

		foreach ( array( 'oleObject', 'package', 'frame', 'externalLink' ) as $type ) {
			$this->assertRefused( 'cptdp_pptx_external', $this->zip_bytes( $this->pptx_entries( array( 'ppt/slides/_rels/slide1.xml.rels' => $rels( $type, 'External' ) ) ) ), 'pptx' );
		}

		$ok = $this->zip_bytes( $this->pptx_entries( array( 'ppt/slides/_rels/slide1.xml.rels' => $rels( 'hyperlink', 'External' ) . $rels( 'image', 'External' ) . $rels( 'oleObject', 'Internal' ) ) ) );
		$file = $this->write( $ok, 'pptx' );
		$this->assertTrue( CPTDP_Pptx_Converter::inspect( $file, 'pptx' ) );
		wp_delete_file( $file );
	}

	public function test_zip_bombs_are_refused(): void {
		$many = array();
		for ( $i = 0; $i <= CPTDP_Pptx_Converter::MAX_ENTRIES; $i++ ) {
			$many[ 'ppt/media/' . $i . '.xml' ] = 'x';
		}
		$this->assertRefused( 'cptdp_pptx_invalid', $this->zip_bytes( $this->pptx_entries( $many ) ), 'pptx' );

		// A small file that expands about a thousand times.
		$file = wp_tempnam( 'cptdp-bomb' );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $this->pptx_entries() as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$zip->addFromString( 'ppt/media/big.bin', str_repeat( "\0", 3 * MB_IN_BYTES ) );
		$zip->close();
		$bytes = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $file );

		$this->assertRefused( 'cptdp_pptx_bomb', $bytes, 'pptx' );
	}

	public function test_a_file_over_the_size_limit_is_refused(): void {
		add_filter(
			'cptdp_pptx_max_bytes',
			static function (): int {
				return 2048;
			}
		);

		$this->assertRefused( 'cptdp_pptx_too_large', str_repeat( 'x', 5000 ), 'pptx' );
		$this->assertRefused( 'cptdp_pptx_too_large', str_repeat( 'x', 5000 ), 'ppt' );
	}

	public function test_odp_checks(): void {
		$file = $this->write( $this->zip_bytes( $this->odp_entries() ), 'odp' );
		$this->assertTrue( CPTDP_Pptx_Converter::inspect( $file, 'odp' ) );
		wp_delete_file( $file );

		$this->assertRefused( 'cptdp_pptx_invalid', $this->zip_bytes( $this->odp_entries( array( 'mimetype' => 'application/vnd.oasis.opendocument.text' ) ) ), 'odp' );
		$this->assertRefused( 'cptdp_pptx_invalid', $this->zip_bytes( array( 'content.xml' => '<x/>', 'mimetype' => 'application/vnd.oasis.opendocument.presentation' ) ), 'odp' ); // mimetype must be first.
		$this->assertRefused( 'cptdp_pptx_macros', $this->zip_bytes( $this->odp_entries( array( 'Basic/script.xml' => '<x/>' ) ) ), 'odp' );
		$this->assertRefused( 'cptdp_pptx_macros', $this->zip_bytes( $this->odp_entries( array( 'Scripts/python/a.py' => 'x' ) ) ), 'odp' );
		$this->assertRefused( 'cptdp_pptx_external', $this->zip_bytes( $this->odp_entries( array( 'content.xml' => '<office:document-content xmlns:draw="d" xmlns:xlink="x"><draw:object xlink:href="https://evil.example/o"/></office:document-content>' ) ) ), 'odp' );
	}

	public function test_odf_external_object_detection(): void {
		$this->assertTrue( CPTDP_Pptx_Converter::odf_has_external_object( '<draw:object xlink:href="file:///etc/passwd"/>' ) );
		$this->assertTrue( CPTDP_Pptx_Converter::odf_has_external_object( '<draw:object-ole xlink:href="/etc/passwd"/>' ) );
		$this->assertTrue( CPTDP_Pptx_Converter::odf_has_external_object( '<draw:plugin xlink:href="../../x"/>' ) );
		$this->assertFalse( CPTDP_Pptx_Converter::odf_has_external_object( '<draw:object xlink:href="./Object 1"/>' ) );
		$this->assertFalse( CPTDP_Pptx_Converter::odf_has_external_object( '<draw:image xlink:href="https://example.org/a.png"/>' ), 'Pictures are not objects.' );
		$this->assertFalse( CPTDP_Pptx_Converter::odf_has_external_object( '' ) );
	}

	public function test_ppt_checks(): void {
		$ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 4096 );

		$file = $this->write( $ole, 'ppt' );
		$this->assertTrue( CPTDP_Pptx_Converter::inspect( $file, 'ppt' ) );
		wp_delete_file( $file );

		$this->assertRefused( 'cptdp_pptx_invalid', 'PK' . str_repeat( 'x', 2000 ), 'ppt' );
		$this->assertRefused( 'cptdp_pptx_macros', $ole . "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0_\0C\0U\0R\0" . str_repeat( "\0", 100 ), 'ppt' );

		// The name straddling the one megabyte read boundary is still found.
		$padding = str_repeat( "\0", 1048576 - 8 - 10 );
		$this->assertRefused( 'cptdp_pptx_macros', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . $padding . "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0" . str_repeat( "\0", 100 ), 'ppt' );
	}

	public function test_binary_and_availability_can_be_overridden(): void {
		$stub = $this->write( "#!/bin/sh\nexit 0\n", 'sh' );
		chmod( $stub, 0755 );
		add_filter(
			'cptdp_pptx_soffice_path',
			static function () use ( $stub ): array {
				return array( $stub );
			}
		);

		$this->assertSame( $stub, CPTDP_Pptx_Converter::binary() );

		add_filter( 'cptdp_pptx_available', '__return_false' );
		$this->assertFalse( CPTDP_Pptx_Converter::is_available() );

		wp_delete_file( $stub );
	}

	public function test_a_missing_binary_means_unavailable_and_the_reason_recommends_a_pdf(): void {
		add_filter( 'cptdp_pptx_soffice_path', '__return_empty_array' );

		$this->assertNull( CPTDP_Pptx_Converter::binary() );
		$this->assertFalse( CPTDP_Pptx_Converter::is_available() );
		$this->assertStringContainsString( 'upload the PDF', CPTDP_Pptx_Converter::unavailable_reason() );
		$this->assertSame( '', CPTDP_Pptx_Converter::version() );

		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->assertWPError( CPTDP_Page_Converter::convert( $id ) );
		$this->assertSame( 'cptdp_unavailable', CPTDP_Page_Converter::convert( $id )->get_error_code() );
	}

	/**
	 * @dataProvider kinds
	 */
	public function test_each_format_converts_to_page_images(string $kind): void {
		$this->require_libreoffice();
		$before = $this->temp_folders();
		$id     = $this->make_document( $this->make_presentation_attachment( $kind ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertNotWPError( $result );
		$this->assertSame( 3, $result['count'] );
		$this->assertSame( 'server', $result['producer'] );
		$this->assertGreaterThan( $result['height'], $result['width'], 'slides are landscape' );
		$this->assertSame( 0.0, $result['crop_bottom'], 'Presentations are never trimmed.' );
		$this->assertSame( $before, $this->temp_folders(), 'The temporary PDF and profile are removed.' );

		$folder = CPTDP_Page_Store::folder_for( $id );
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->assertFileExists( $folder . '/' . CPTDP_Page_Store::page_filename( $i, $result['ext'] ) );
		}
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function kinds(): array {
		return array(
			'pptx' => array( 'pptx' ),
			'ppt'  => array( 'ppt' ),
			'odp'  => array( 'odp' ),
		);
	}

	public function test_conversion_removes_its_temporary_files_when_it_fails(): void {
		$this->require_libreoffice();
		$before = $this->temp_folders();
		// Valid package, but not something LibreOffice can open as a presentation.
		$bytes = $this->zip_bytes( $this->pptx_entries() );
		$id    = $this->make_document( $this->make_presentation_attachment( 'pptx', $bytes ) );

		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( $before, $this->temp_folders() );
		$this->assertStringContainsString( 'PDF', $result->get_error_message() );
	}

	public function test_a_held_site_lock_answers_busy(): void {
		$this->require_libreoffice();
		set_transient( CPTDP_Pptx_Converter::LOCK, 1, 60 );

		$result = CPTDP_Pptx_Converter::to_pdf( $this->fixture( 'pptx' ), 'pptx' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_pptx_busy', $result->get_error_code() );
		delete_transient( CPTDP_Pptx_Converter::LOCK );
	}

	public function test_a_conversion_that_takes_too_long_is_stopped(): void {
		$stub = $this->write( "#!/bin/sh\nsleep 20\n", 'sh' );
		chmod( $stub, 0755 );
		add_filter(
			'cptdp_pptx_soffice_path',
			static function () use ( $stub ): array {
				return array( $stub );
			}
		);
		add_filter(
			'cptdp_pptx_timeout',
			static function (): int {
				return 1;
			}
		);
		$before = $this->temp_folders();

		$started = microtime( true );
		$result  = CPTDP_Pptx_Converter::to_pdf( $this->fixture( 'pptx' ), 'pptx' );

		$this->assertLessThan( 10, microtime( true ) - $started );
		$this->assertWPError( $result );
		$this->assertStringContainsString( 'too long', $result->get_error_message() );
		$this->assertSame( $before, $this->temp_folders() );
		$this->assertFalse( get_transient( CPTDP_Pptx_Converter::LOCK ), 'The lock is released.' );
		wp_delete_file( $stub );
	}

	public function test_cleanup_only_touches_its_own_folders(): void {
		$keep = trailingslashit( get_temp_dir() ) . 'not-ours-' . wp_generate_password( 6, false );
		mkdir( $keep ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		CPTDP_Pptx_Converter::cleanup( $keep );
		$this->assertDirectoryExists( $keep );
		rmdir( $keep ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

		$own = trailingslashit( get_temp_dir() ) . 'cptdp-pptx-' . wp_generate_password( 6, false );
		mkdir( $own ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		file_put_contents( $own . '/x.txt', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		CPTDP_Pptx_Converter::cleanup( $own );
		$this->assertDirectoryDoesNotExist( $own );
	}
}
