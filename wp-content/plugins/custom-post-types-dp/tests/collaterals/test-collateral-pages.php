<?php
/**
 * Collateral page images, names and thumbnails (DPC-023).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group collateral
 * @group pages
 */
class CPTDP_Test_Collateral_Pages extends CPTDP_Pages_Test_Case {

	/**
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Token of the browser run the tests act as.
	 */
	private string $run = '';

	public function set_up(): void {
		parent::set_up();

		$this->run = '';

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Force the PDF server tooling on or off.
	 *
	 * @param bool $available Whether the server can convert PDFs.
	 */
	private function pdf_server( bool $available ): void {
		remove_all_filters( 'cptdp_pages_server_available' );
		add_filter(
			'cptdp_pages_server_available',
			static function () use ( $available ): bool {
				return $available;
			}
		);
	}

	private function cron_scheduled( int $id ): bool {
		return false !== wp_next_scheduled( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
	}

	private function route( int $id, string $base = 'collaterals' ): string {
		return '/cptdp/v1/' . $base . '/' . $id . '/pages';
	}

	private function post( string $route, string $body = '', string $type = 'image/jpeg' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', $type );
		$request->set_body( $body );

		if ( '' !== $this->run && ! str_ends_with( $route, '/start' ) ) {
			$request->set_header( 'X-CPTDP-Run', $this->run );
		}

		return $this->server->dispatch( $request );
	}

	private function get( string $route ): WP_REST_Response {
		return $this->server->dispatch( new WP_REST_Request( 'GET', $route ) );
	}

	private function browser_mode(): void {
		update_option( 'cptdp_documents', array( 'pages_mode' => 'browser' ) );
	}

	// --- Registry ------------------------------------------------------------------------------

	public function test_the_registry_lists_documents_and_collaterals(): void {
		$this->assertSame( array( 'document', 'collateral' ), CPTDP_Page_Store::post_types() );
	}

	public function test_each_type_reads_its_own_file_meta_and_other_types_none(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 'cptdp_document_file', CPTDP_Page_Store::file_meta_key( $this->make_document() ) );
		$this->assertSame( 'cptdp_collateral_file', CPTDP_Page_Store::file_meta_key( $this->make_collateral() ) );
		$this->assertSame( '', CPTDP_Page_Store::file_meta_key( $page ) );
		$this->assertFalse( CPTDP_Page_Store::supports( self::factory()->post->create() ) );
		$this->assertFalse( CPTDP_Page_Store::supports( self::factory()->post->create( array( 'post_type' => 'video' ) ) ) );
		$this->assertFalse( CPTDP_Page_Store::supports( 0 ) );
	}

	// --- Hooks ---------------------------------------------------------------------------------

	public function test_adding_the_collateral_file_queues_the_server(): void {
		$this->pdf_server( true );
		$id = $this->make_collateral();

		update_post_meta( $id, 'cptdp_collateral_file', $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_without_server_tooling_a_pdf_collateral_needs_the_browser(): void {
		$this->pdf_server( false );
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_removing_the_collateral_file_clears_everything(): void {
		$this->pdf_server( true );
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );

		delete_post_meta( $id, 'cptdp_collateral_file' );

		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $id ) );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_the_same_meta_key_on_another_type_does_nothing(): void {
		$this->pdf_server( true );
		$id = self::factory()->post->create();

		update_post_meta( $id, 'cptdp_collateral_file', $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_saving_a_collateral_runs_ensure(): void {
		$this->pdf_server( true );
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NONE );
		delete_post_meta( $id, CPTDP_Page_Store::META_SOURCE );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'Saved again' ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	// --- PDF collateral ------------------------------------------------------------------------

	public function test_a_pdf_collateral_gets_pages_and_thumbnails_from_the_server(): void {
		$this->require_server_tooling();
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_collateral( $attachment );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$pages = cptdp_get_collateral_pages( $id );
		$this->assertNotNull( $pages );
		$this->assertSame( 3, $pages['count'] );
		$this->assertCount( 3, $pages['pages'] );
		$this->assertCount( 3, $pages['thumbs'] );
		$this->assertGreaterThan( 0, $pages['ratio'] );
		$this->assertMatchesRegularExpression( '/^' . $attachment . '-[0-9a-f]{8}-' . $id . '$/', CPTDP_Page_Store::get_manifest( $id )['dir'] );
		$this->assertSame( 3, (int) get_post_meta( $id, 'cptdp_total_pages', true ) );
	}

	public function test_the_two_apis_answer_only_for_their_own_type(): void {
		$document   = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$collateral = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $document, 2 );
		$this->fake_pages( $collateral, 2 );

		$this->assertNull( cptdp_get_collateral_pages( $document ) );
		$this->assertNotNull( cptdp_get_collateral_pages( $collateral ) );
		$this->assertNotNull( cptdp_get_document_pages( $document ) );
	}

	public function test_a_pdf_collateral_can_be_made_in_the_browser(): void {
		$this->browser_mode();
		$this->acting_as( 'administrator' );
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ) );

		$start = $this->post( $this->route( $id ) . '/start' );
		$this->assertSame( 200, $start->get_status() );
		$this->run = (string) $start->get_data()['run'];

		$this->assertSame( 200, $this->post( $this->route( $id ) . '/1', $this->image_bytes() )->get_status() );
		$this->assertSame( 200, $this->post( $this->route( $id ) . '/2', $this->image_bytes() )->get_status() );

		$finish = $this->post(
			$this->route( $id ) . '/finish',
			(string) wp_json_encode(
				array(
					'count'       => 2,
					'ext'         => 'jpg',
					'fingerprint' => CPTDP_Page_Store::get_source( $id )['fingerprint'],
				)
			),
			'application/json'
		);

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, $finish->get_data()['status'] );
		$this->assertSame( 2, cptdp_get_collateral_pages( $id )['count'] );
		$this->assertSame( 200, $this->get( $this->route( $id ) )->get_status() );
	}

	public function test_a_route_only_answers_for_its_own_type(): void {
		$this->acting_as( 'administrator' );
		$document   = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$collateral = $this->make_collateral( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( 404, $this->get( $this->route( $collateral, 'documents' ) )->get_status() );
		$this->assertSame( 404, $this->get( $this->route( $document, 'collaterals' ) )->get_status() );
		$this->assertSame( 404, $this->post( $this->route( $document, 'collaterals' ) . '/start' )->get_status() );
		$this->assertSame( 200, $this->get( $this->route( $document, 'documents' ) )->get_status() );
		$this->assertSame( 'Not found.', $this->get( $this->route( $document, 'collaterals' ) )->get_data()['message'] );
	}

	// --- Images --------------------------------------------------------------------------------

	public function test_a_portrait_image_becomes_one_page_and_is_never_upscaled(): void {
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 1080, 1350 ), 24 );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ), 'Server only, queued.' );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$pages    = cptdp_get_collateral_pages( $id );
		$manifest = CPTDP_Page_Store::get_manifest( $id );

		$this->assertSame( 1, $pages['count'] );
		$this->assertSame( 1080, $pages['width'], 'Smaller than 1400: kept as it is.' );
		$this->assertSame( 1350, $pages['height'] );
		$this->assertContains( $manifest['ext'], array( 'webp', 'jpg' ) );
		$this->assertMatchesRegularExpression( '#/p-001\.(webp|jpg)$#', $pages['pages'][0] );
		$this->assertCount( 1, $pages['thumbs'] );
		$this->assertSame( CPTDP_Page_Thumbs::WIDTH, $pages['thumb_width'] );
		$this->assertSame( 0.0, $pages['crop_bottom'] );
		$this->assertSame( array(), $pages['names'] );
		$this->assertSame( 1, (int) get_post_meta( $id, 'cptdp_total_pages', true ), 'The count follows the file.' );
		$this->assertSame( 'image', $manifest['source_kind'] );
		$this->assertSame( 'image-editor', $manifest['converted_via'] );
		$this->assertSame( 'server', $manifest['producer'] );
		$this->assertSame( 'Ready, 1 page (from an image)', cptdp_document_pages_status_label( 'ready', 1, 'image' ) );
	}

	public function test_a_wide_image_is_capped_at_the_landscape_width(): void {
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 2000, 700 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$pages = cptdp_get_collateral_pages( $id );
		$this->assertSame( CPTDP_Page_Converter::WIDTH_LANDSCAPE, $pages['width'] );
		$this->assertGreaterThan( 1, $pages['ratio'] );
	}

	public function test_an_image_is_never_trimmed_even_with_trimming_on(): void {
		$id = $this->make_collateral( $this->make_image_attachment( 'jpeg', 600, 800 ) );
		CPTDP_Page_Scheduler::convert_now( $id );

		$manifest                = CPTDP_Page_Store::get_manifest( $id );
		$manifest['crop_bottom'] = 0.2;
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );

		$this->assertSame( 0.0, cptdp_get_collateral_pages( $id )['crop_bottom'] );
		$this->assertSame( 0.0, CPTDP_Page_Store::recrop( $id ) );
	}

	public function test_a_file_that_only_claims_to_be_an_image_fails_and_leaves_no_folder(): void {
		$id     = $this->make_collateral( $this->make_image_attachment( 'png', 10, 10, 'this is not a png' ) );
		$result = CPTDP_Page_Converter::convert( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_not_an_image', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( CPTDP_Page_Store::folder_for( $id ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Scheduler::convert_now( $id ) );
		$this->assertDirectoryDoesNotExist( CPTDP_Page_Store::folder_for( $id ) );
	}

	public function test_an_image_over_the_pixel_cap_fails(): void {
		add_filter(
			'cptdp_image_max_pixels',
			static function (): int {
				return 100;
			}
		);
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 20, 20 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Scheduler::convert_now( $id ) );
		$this->assertStringContainsString( 'too large', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_an_svg_gives_no_pages_and_the_box_says_why(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
		$id  = $this->make_collateral( $this->make_image_attachment( 'svg', 10, 10, $svg ) );

		$this->assertSame( '', CPTDP_Page_Store::source_kind( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $id ) );

		ob_start();
		cptdp_document_pages_render_box( get_post( $id ) );
		$this->assertStringContainsString( 'SVG files are not turned into page images', (string) ob_get_clean() );
	}

	public function test_an_image_is_never_a_source_of_a_document(): void {
		$attachment = $this->make_image_attachment( 'png', 100, 100 );
		$id         = $this->make_document();
		add_post_meta( $id, 'cptdp_document_file', $attachment ); // Bypasses the document sanitiser on purpose.

		$this->assertSame( 'image', CPTDP_Page_Store::kind_of_attachment( $attachment ) );
		$this->assertSame( 0, CPTDP_Page_Store::source_attachment_id( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Scheduler::ensure( $id ) );
	}

	public function test_an_image_is_made_by_the_server_only(): void {
		$this->browser_mode();
		$this->acting_as( 'administrator' );
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 100, 100 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ), 'The browser mode does not apply.' );

		foreach ( array( '/start', '/1', '/finish' ) as $suffix ) {
			$response = $this->post( $this->route( $id ) . $suffix, '/1' === $suffix ? $this->image_bytes() : '' );
			$this->assertSame( 400, $response->get_status(), $suffix );
			$this->assertSame( 'cptdp_server_only', $response->get_data()['code'], $suffix );
		}

		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$retry = $this->post( $this->route( $id ) . '/retry' );
		$this->assertSame( 200, $retry->get_status() );
		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, $retry->get_data()['status'] );
		$this->assertTrue( $this->cron_scheduled( $id ) );

		ob_start();
		cptdp_document_pages_render_box( get_post( $id ) );
		$box = (string) ob_get_clean();
		$this->assertStringContainsString( '>Regenerate</button>', $box );
		$this->assertStringContainsString( 'visitors see the collateral straight away', $box );
	}

	public function test_without_an_image_editor_an_image_fails_with_a_reason(): void {
		add_filter( 'wp_image_editors', '__return_empty_array' );
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 100, 100 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
		$this->assertSame( 'This server cannot process images.', CPTDP_Page_Store::get_error( $id ) );
	}

	// --- Names, page count, fingerprint ---------------------------------------------------------

	public function test_bookmarks_name_the_pages_of_a_collateral(): void {
		$this->require_server_tooling();
		$bytes = $this->outline_pdf( 3, array( array( 'Cover', 1 ), array( 'Inside', 2 ) ) );
		$id    = $this->make_collateral( $this->make_pdf_attachment( 3, 595, 842, $bytes ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::convert_now( $id ) );

		$pages = cptdp_get_collateral_pages( $id );
		$this->assertSame( array( 'Cover', 'Inside', 'Inside' ), $pages['names'] );
		$this->assertSame( array( array( 1, 'Cover' ), array( 2, 'Inside' ) ), $pages['name_ranges'] );
	}

	public function test_a_pdf_without_bookmarks_has_no_names(): void {
		$this->require_server_tooling();
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );

		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( array(), cptdp_get_collateral_pages( $id )['names'] );
	}

	public function test_a_collateral_count_follows_its_file_while_a_document_keeps_the_check(): void {
		$collateral = $this->make_collateral( $this->make_pdf_attachment( 3 ), 24 );
		$document   = $this->make_document( $this->make_pdf_attachment( 3 ), 24 );
		$this->fake_pages( $collateral, 3 );
		$this->fake_pages( $document, 3 );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $collateral ) );
		$this->assertSame( 3, (int) get_post_meta( $collateral, 'cptdp_total_pages', true ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $document ) );
		$this->assertSame( 24, (int) get_post_meta( $document, 'cptdp_total_pages', true ) );

		// A typed count on the collateral form is corrected on the next save, not marked failed.
		update_post_meta( $collateral, 'cptdp_total_pages', 7 );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Scheduler::ensure( $collateral ) );
		$this->assertSame( 3, (int) get_post_meta( $collateral, 'cptdp_total_pages', true ) );
	}

	public function test_the_payload_is_only_valid_while_the_file_fingerprint_matches(): void {
		$this->pdf_server( true );
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_collateral( $attachment );
		$this->fake_pages( $id, 2 );
		$this->assertNotNull( cptdp_get_collateral_pages( $id ) );

		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 3 ) ); // A different size, so a different fingerprint.

		$this->assertNull( cptdp_get_collateral_pages( $id ), 'Stale before any regeneration.' );
		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Scheduler::ensure( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	// --- No public conversion -------------------------------------------------------------------

	public function test_visitors_and_subscribers_cannot_use_the_routes(): void {
		$id    = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$calls = array(
			array( 'GET', '' ),
			array( 'POST', '/start' ),
			array( 'POST', '/1' ),
			array( 'POST', '/finish' ),
			array( 'POST', '/retry' ),
		);

		foreach ( array( 0 => 401, 1 => 403 ) as $subscriber => $expected ) {
			$subscriber ? $this->acting_as( 'subscriber' ) : wp_set_current_user( 0 );

			foreach ( $calls as $call ) {
				$status = 'GET' === $call[0] ? $this->get( $this->route( $id ) )->get_status() : $this->post( $this->route( $id ) . $call[1], $this->image_bytes() )->get_status();
				$this->assertSame( $expected, $status, $call[0] . ' ' . $call[1] );
			}
		}
	}

	public function test_viewing_the_site_never_converts_or_writes(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$before = get_post_meta( $id );

		$this->go_to( get_post_type_archive_link( 'collateral' ) );
		$this->go_to( (string) get_permalink( $id ) );
		cptdp_get_collateral_pages( $id );

		$this->assertFalse( $this->cron_scheduled( $id ) );
		$this->assertSame( $before, get_post_meta( $id ) );
	}

	public function test_no_route_is_public(): void {
		foreach ( $this->server->get_routes( 'cptdp/v1' ) as $route => $handlers ) {
			if ( '/cptdp/v1' === $route ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				$this->assertNotSame( '__return_true', $handler['permission_callback'], $route );
			}
		}

		$this->assertArrayHasKey( '/cptdp/v1/collaterals/(?P<id>\d+)/pages/finish', $this->server->get_routes( 'cptdp/v1' ) );
	}

	// --- Lifecycle -------------------------------------------------------------------------------

	public function test_replacing_the_file_clears_the_old_folder(): void {
		$this->pdf_server( true );
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );
		$old = CPTDP_Page_Store::base_dir() . '/' . CPTDP_Page_Store::get_manifest( $id )['dir'];

		update_post_meta( $id, 'cptdp_collateral_file', $this->make_pdf_attachment( 3 ) );

		$this->assertDirectoryDoesNotExist( $old );
		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_deleting_the_collateral_deletes_its_folder(): void {
		$id = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );
		$folder = CPTDP_Page_Store::folder_for( $id );

		wp_delete_post( $id, true );

		$this->assertDirectoryDoesNotExist( $folder );
	}

	public function test_deleting_the_attachment_clears_every_post_using_it(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$document   = $this->make_document( $attachment );
		$collateral = $this->make_collateral( $attachment );
		$this->fake_pages( $document, 2 );
		$this->fake_pages( $collateral, 2 );

		$this->assertEqualsCanonicalizing( array( $document, $collateral ), CPTDP_Page_Store::posts_using( $attachment ) );

		wp_delete_attachment( $attachment, true );

		$this->assertNull( CPTDP_Page_Store::get_manifest( $document ) );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $collateral ) );
	}

	public function test_two_collaterals_sharing_a_pdf_keep_their_own_folders(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$first      = $this->make_collateral( $attachment );
		$second     = $this->make_collateral( $attachment );
		$this->fake_pages( $first, 2 );
		$this->fake_pages( $second, 2 );

		$this->assertNotSame( CPTDP_Page_Store::folder_for( $first ), CPTDP_Page_Store::folder_for( $second ) );

		CPTDP_Page_Store::clear( $first, true );

		$this->assertNotNull( cptdp_get_collateral_pages( $second ) );
	}

	// --- Admin -----------------------------------------------------------------------------------

	private function column( string $column, int $id ): string {
		ob_start();
		cptdp_collateral_column_content( $column, $id );

		return (string) ob_get_clean();
	}

	public function test_the_list_shows_the_page_status(): void {
		$this->pdf_server( false );
		$none  = $this->make_collateral();
		$ready = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$needs = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $ready, 2 );

		$this->assertArrayHasKey( 'cptdp_pages_status', cptdp_collateral_columns( array( 'title' => 'Title' ) ) );
		$this->assertSame( '&mdash;', $this->column( 'cptdp_pages_status', $none ) );
		$this->assertStringContainsString( 'Ready, 2 pages', $this->column( 'cptdp_pages_status', $ready ) );
		$this->assertStringContainsString( '<a href=', $this->column( 'cptdp_pages_status', $needs ), 'Needs your browser links to the editor.' );
	}

	public function test_the_thumbnail_cell_shows_page_one_or_the_featured_image(): void {
		update_option( 'cptdp_documents', array( 'featured_from_page' => false ) );
		$id = $this->make_collateral( $this->make_image_attachment( 'png', 600, 800 ) );
		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertStringContainsString( esc_url( cptdp_get_collateral_pages( $id )['thumbs'][0] ), $this->column( 'thumbnail', $id ) );
		$this->assertSame( '&mdash;', $this->column( 'thumbnail', $this->make_collateral() ) );

		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		set_post_thumbnail( $id, $image );

		$this->assertStringContainsString( 'canola', $this->column( 'thumbnail', $id ) );
		$this->assertStringNotContainsString( '/cptdp-pages/', $this->column( 'thumbnail', $id ) );
	}

	public function test_the_page_images_box_is_on_the_collateral_screen(): void {
		$GLOBALS['wp_meta_boxes'] = array();
		$id                       = $this->make_collateral();

		do_action( 'add_meta_boxes', 'collateral', get_post( $id ) );

		$this->assertArrayHasKey( 'cptdp_document_pages', $GLOBALS['wp_meta_boxes']['collateral']['side']['default'] );

		ob_start();
		cptdp_document_pages_render_box( get_post( $id ) );
		$this->assertStringContainsString( 'collateral file', (string) ob_get_clean() );
	}

	public function test_the_edit_screen_script_talks_to_the_collateral_routes(): void {
		$this->acting_as( 'administrator' );
		$id              = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$GLOBALS['post'] = get_post( $id );
		set_current_screen( 'collateral' );

		cptdp_document_pages_enqueue_admin( 'post.php' );

		$data = (string) wp_scripts()->get_data( 'cptdp-admin-document-pages', 'data' );
		$this->assertStringContainsString( '/cptdp/v1/collaterals/' . $id . '/pages', $data );
		$this->assertStringNotContainsString( '/documents/' . $id, $data );

		set_current_screen( 'front' );
		wp_dequeue_script( 'cptdp-admin-document-pages' );
		wp_deregister_script( 'cptdp-admin-document-pages' );
	}

	public function test_the_list_notice_counts_collaterals_that_need_the_browser(): void {
		$this->pdf_server( false );
		$this->acting_as( 'administrator' );
		$this->make_collateral( $this->make_pdf_attachment( 2 ) );
		set_current_screen( 'edit-collateral' );

		ob_start();
		cptdp_document_pages_list_notice();
		$notice = (string) ob_get_clean();

		set_current_screen( 'front' );
		$this->assertStringContainsString( '1 collateral needs page images', $notice );
	}

	// --- CLI listing -----------------------------------------------------------------------------

	public function test_the_cli_listing_can_be_narrowed_to_one_type(): void {
		$document   = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$collateral = $this->make_collateral( $this->make_pdf_attachment( 2 ) );
		$bare       = $this->make_collateral();

		$this->assertEqualsCanonicalizing( array( $document, $collateral ), CPTDP_Page_Scheduler::posts_with_source() );
		$this->assertSame( array( $collateral ), CPTDP_Page_Scheduler::posts_with_source( array( 'collateral' ) ) );
		$this->assertSame( array( $collateral, $bare ), CPTDP_Page_Scheduler::post_ids( 'any', array( 'collateral' ) ) );
		$this->assertSame( array(), CPTDP_Page_Scheduler::post_ids( 'any', array( 'page' ) ), 'Unsupported types give nothing.' );
	}
}
