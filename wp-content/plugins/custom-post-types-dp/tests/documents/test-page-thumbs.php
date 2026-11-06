<?php
/**
 * Small page thumbnails: writing, storage, backfill, admin picture and browser upload (DPB-031).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group page-thumbs
 */
class CPTDP_Test_Page_Thumbs extends CPTDP_Pages_Test_Case {

	/**
	 * @var WP_REST_Server
	 */
	private $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	private function require_imagick(): void {
		if ( ! CPTDP_Page_Thumbs::is_available() ) {
			$this->markTestSkipped( 'Imagick is not available.' );
		}
	}

	private function converted( int $pages = 3, int $width = 595, int $height = 842 ): int {
		$this->require_server_tooling();
		$this->require_imagick();
		$id = $this->make_document( $this->make_pdf_attachment( $pages, $width, $height ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		return $id;
	}

	public function test_a_conversion_writes_one_small_thumbnail_per_page(): void {
		$id       = $this->converted( 3 );
		$manifest = CPTDP_Page_Store::get_manifest( $id );
		$dir      = CPTDP_Page_Store::folder_for( $id );

		$this->assertGreaterThan( 0, $manifest['thumb_width'] );
		$this->assertLessThanOrEqual( CPTDP_Page_Thumbs::WIDTH, $manifest['thumb_width'] );
		$this->assertGreaterThan( $manifest['thumb_width'], $manifest['thumb_height'], 'A portrait page.' );

		for ( $i = 1; $i <= 3; $i++ ) {
			$file = $dir . '/' . CPTDP_Page_Thumbs::filename( $i, $manifest['ext'] );
			$this->assertFileExists( $file );
			$this->assertLessThanOrEqual( CPTDP_Page_Thumbs::MAX_BYTES, filesize( $file ), 'Small by design.' );
			$this->assertSame( $manifest['thumb_width'], getimagesize( $file )[0] );
		}

		$this->assertFileDoesNotExist( $dir . '/' . CPTDP_Page_Thumbs::filename( 4, $manifest['ext'] ) );
	}

	public function test_the_thumbnail_is_made_from_the_uncropped_page(): void {
		$id = $this->converted( 1 );
		$m  = CPTDP_Page_Store::get_manifest( $id );

		// Same shape as the stored page, whatever the Trim bottom setting or crop is.
		$this->assertEqualsWithDelta( $m['width'] / $m['height'], $m['thumb_width'] / $m['thumb_height'], 0.02 );
	}

	public function test_a_landscape_page_gets_the_same_width(): void {
		$id = $this->converted( 1, 842, 595 );
		$m  = CPTDP_Page_Store::get_manifest( $id );

		$this->assertLessThanOrEqual( CPTDP_Page_Thumbs::WIDTH, $m['thumb_width'] );
		$this->assertGreaterThan( $m['thumb_height'], $m['thumb_width'] );
	}

	public function test_the_api_returns_the_thumbnail_urls_in_page_order(): void {
		$id    = $this->converted( 3 );
		$pages = cptdp_get_document_pages( $id );

		$this->assertCount( 3, $pages['thumbs'] );
		$this->assertStringEndsWith( '/t-001.' . CPTDP_Page_Store::get_manifest( $id )['ext'], $pages['thumbs'][0] );
		$this->assertStringEndsWith( '/t-003.' . CPTDP_Page_Store::get_manifest( $id )['ext'], $pages['thumbs'][2] );
		$this->assertSame( dirname( $pages['pages'][0] ), dirname( $pages['thumbs'][0] ), 'Beside the page images.' );
		$this->assertGreaterThan( 0, $pages['thumb_width'] );
		$this->assertGreaterThan( 0, $pages['thumb_height'] );
	}

	public function test_a_missing_thumbnail_means_none_are_offered(): void {
		$id       = $this->converted( 3 );
		$manifest = CPTDP_Page_Store::get_manifest( $id );
		wp_delete_file( CPTDP_Page_Store::folder_for( $id ) . '/' . CPTDP_Page_Thumbs::filename( 3, $manifest['ext'] ) );

		$pages = cptdp_get_document_pages( $id );
		$this->assertSame( array(), $pages['thumbs'], 'A half set is never used.' );
		$this->assertSame( 0, $pages['thumb_width'] );
		$this->assertCount( 3, $pages['pages'], 'The page images are untouched.' );
	}

	public function test_a_set_without_thumbnails_is_valid_and_has_none(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );

		$this->assertTrue( CPTDP_Page_Store::is_valid( $id ) );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['thumbs'] );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['names'] );
	}

	public function test_clearing_a_set_removes_its_thumbnails(): void {
		$id  = $this->converted( 2 );
		$dir = CPTDP_Page_Store::folder_for( $id );

		CPTDP_Page_Store::clear( $id, true );

		$this->assertDirectoryDoesNotExist( $dir );
	}

	public function test_thumbnails_are_made_from_stored_images_without_converting(): void {
		$id       = $this->converted( 3 );
		$manifest = CPTDP_Page_Store::get_manifest( $id );
		$dir      = CPTDP_Page_Store::folder_for( $id );

		CPTDP_Page_Thumbs::delete( $dir, $manifest['ext'], 3 );
		unset( $manifest['thumb_width'], $manifest['thumb_height'] );
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['thumbs'] );

		// The converter is not asked: switch it off to prove the backfill never calls it.
		add_filter( 'cptdp_pages_engine', '__return_empty_string' );
		add_filter( 'cptdp_pptx_available', '__return_false' );

		$this->assertSame( 'made', CPTDP_Page_Store::make_thumbs( $id ) );
		$this->assertCount( 3, cptdp_get_document_pages( $id )['thumbs'] );
		$this->assertSame( 'skipped', CPTDP_Page_Store::make_thumbs( $id ), 'A complete set is left alone.' );
		$this->assertSame( 'made', CPTDP_Page_Store::make_thumbs( $id, true ), 'Unless forced.' );
	}

	public function test_the_backfill_says_when_there_is_nothing_to_do(): void {
		$this->require_imagick();

		$this->assertSame( 'none', CPTDP_Page_Store::make_thumbs( $this->make_document() ) );
	}

	public function test_a_broken_page_image_leaves_no_partial_set(): void {
		$this->require_imagick();
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 ); // The "images" are one byte of junk.

		$this->assertSame( 'failed', CPTDP_Page_Store::make_thumbs( $id ) );
		$this->assertFileDoesNotExist( CPTDP_Page_Store::folder_for( $id ) . '/t-001.webp' );
		$this->assertSame( array(), cptdp_get_document_pages( $id )['thumbs'] );
	}

	// --- The admin picture ---------------------------------------------------------------------

	public function test_the_admin_picture_is_page_one_of_the_thumbnails(): void {
		$id    = $this->converted( 2 );
		$thumb = cptdp_document_admin_thumb( $id );

		$this->assertTrue( $thumb['small'] );
		$this->assertSame( cptdp_get_document_pages( $id )['thumbs'][0], $thumb['url'], 'The same file the Pages sheet shows for page 1.' );
	}

	public function test_a_set_made_before_thumbnails_falls_back_to_the_first_page_image(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );
		$thumb = cptdp_document_admin_thumb( $id );

		$this->assertFalse( $thumb['small'] );
		$this->assertSame( cptdp_get_document_pages( $id )['pages'][0], $thumb['url'] );
	}

	public function test_without_pages_there_is_no_admin_picture(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertNull( cptdp_document_admin_thumb( $id ) );
		$this->assertSame( '', cptdp_document_admin_thumb_html( $id ) );
	}

	public function test_the_list_column_shows_the_picture_lazily_or_a_dash(): void {
		$id = $this->converted( 2 );

		ob_start();
		cptdp_document_column_content( 'thumbnail', $id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<img ', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'decoding="async"', $html );
		$this->assertStringContainsString( 'fetchpriority="low"', $html );
		$this->assertStringContainsString( 'alt=""', $html );
		$this->assertMatchesRegularExpression( '/ width="50" height="\d+"/', $html, 'The box is reserved.' );
		$this->assertStringContainsString( esc_url( cptdp_get_document_pages( $id )['thumbs'][0] ), $html );

		$bare = $this->make_document();
		ob_start();
		cptdp_document_column_content( 'thumbnail', $bare );
		$this->assertSame( '&mdash;', ob_get_clean() );
	}

	public function test_a_featured_image_wins_over_the_page_picture(): void {
		$id    = $this->converted( 2 );
		$image = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );

		if ( ! $image ) {
			$this->markTestSkipped( 'No test image available.' );
		}

		set_post_thumbnail( $id, $image );

		ob_start();
		cptdp_document_column_content( 'thumbnail', $id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'canola', $html );
		$this->assertStringNotContainsString( '/cptdp-pages/', $html );
	}

	public function test_the_page_images_box_shows_the_picture_only_when_ready(): void {
		$id   = $this->converted( 2 );
		$post = get_post( $id );

		ob_start();
		cptdp_document_pages_render_box( $post );
		$this->assertStringContainsString( '/t-001.', (string) ob_get_clean() );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );
		ob_start();
		cptdp_document_pages_render_box( $post );
		$this->assertStringNotContainsString( '/t-001.', (string) ob_get_clean() );
	}

	// --- The admin browser upload --------------------------------------------------------------

	private function post_to( string $route, string $body, array $params = array(), string $type = 'image/jpeg', string $run = '' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', $type );
		$request->set_body( $body );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		if ( '' !== $run ) {
			$request->set_header( 'X-CPTDP-Run', $run );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * @return array{0:int,1:string} Document ID and the run token.
	 */
	private function browser_run( int $pages = 2 ): array {
		$this->acting_as( 'administrator' );
		$id    = $this->make_document( $this->make_pdf_attachment( $pages ) );
		$start = $this->post_to( '/cptdp/v1/documents/' . $id . '/pages/start', '' );

		return array( $id, (string) $start->get_data()['run'] );
	}

	public function test_the_browser_uploads_pages_and_thumbnails_and_the_set_keeps_them(): void {
		list( $id, $run ) = $this->browser_run( 2 );
		$base             = '/cptdp/v1/documents/' . $id . '/pages';

		for ( $n = 1; $n <= 2; $n++ ) {
			$this->assertSame( 200, $this->post_to( $base . '/' . $n, $this->image_bytes( 'jpeg', 400, 560 ), array(), 'image/jpeg', $run )->get_status() );
			$thumb = $this->post_to( $base . '/' . $n, $this->image_bytes( 'jpeg', 120, 168 ), array( 'thumb' => 1 ), 'image/jpeg', $run );
			$this->assertSame( 200, $thumb->get_status() );
		}

		$finish = $this->post_to( $base . '/finish', (string) wp_json_encode( array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => CPTDP_Page_Store::get_source( $id )['fingerprint'], 'cropBottom' => 0, 'pageNames' => array( array( 1, 'One' ), array( 2, 'Two' ) ) ) ), array(), 'application/json', $run );
		$this->assertSame( 200, $finish->get_status() );

		$manifest = CPTDP_Page_Store::get_manifest( $id );
		$this->assertSame( 120, $manifest['thumb_width'] );
		$this->assertSame( 168, $manifest['thumb_height'] );
		$this->assertSame( array( 'One', 'Two' ), cptdp_get_document_pages( $id )['names'] );
		$this->assertCount( 2, cptdp_get_document_pages( $id )['thumbs'] );
	}

	public function test_an_oversized_thumbnail_is_refused(): void {
		list( $id, $run ) = $this->browser_run( 1 );
		$base             = '/cptdp/v1/documents/' . $id . '/pages';

		$this->post_to( $base . '/1', $this->image_bytes( 'jpeg', 400, 560 ), array(), 'image/jpeg', $run );
		$response = $this->post_to( $base . '/1', $this->image_bytes( 'jpeg', 600, 840 ), array( 'thumb' => 1 ), 'image/jpeg', $run );

		$this->assertSame( 413, $response->get_status() );
		$this->assertFileDoesNotExist( CPTDP_Page_Store::folder_for( $id ) . '/t-001.jpg' );
	}

	public function test_a_set_with_a_missing_thumbnail_ends_without_thumbnails_not_half_of_them(): void {
		list( $id, $run ) = $this->browser_run( 2 );
		$base             = '/cptdp/v1/documents/' . $id . '/pages';

		$this->post_to( $base . '/1', $this->image_bytes( 'jpeg', 400, 560 ), array(), 'image/jpeg', $run );
		$this->post_to( $base . '/2', $this->image_bytes( 'jpeg', 400, 560 ), array(), 'image/jpeg', $run );
		$this->post_to( $base . '/1', $this->image_bytes( 'jpeg', 120, 168 ), array( 'thumb' => 1 ), 'image/jpeg', $run );

		$this->post_to( $base . '/finish', (string) wp_json_encode( array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => CPTDP_Page_Store::get_source( $id )['fingerprint'] ) ), array(), 'application/json', $run );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $id ) );

		// With Imagick the server fills the gap from the stored pages; without it the set has none.
		$thumbs = cptdp_get_document_pages( $id )['thumbs'];
		$this->assertContains( count( $thumbs ), array( 0, 2 ), 'Never one of two.' );
	}

	public function test_finishing_drops_stray_thumbnails_past_the_page_count(): void {
		list( $id, $run ) = $this->browser_run( 1 );
		$base             = '/cptdp/v1/documents/' . $id . '/pages';
		$folder           = CPTDP_Page_Store::folder_for( $id );

		$this->post_to( $base . '/1', $this->image_bytes( 'jpeg', 400, 560 ), array(), 'image/jpeg', $run );
		file_put_contents( $folder . '/t-005.jpg', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		$this->post_to( $base . '/finish', (string) wp_json_encode( array( 'count' => 1, 'ext' => 'jpg', 'fingerprint' => CPTDP_Page_Store::get_source( $id )['fingerprint'] ) ), array(), 'application/json', $run );

		$this->assertFileDoesNotExist( $folder . '/t-005.jpg' );
	}
}
