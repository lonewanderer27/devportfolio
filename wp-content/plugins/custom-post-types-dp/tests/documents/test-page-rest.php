<?php
/**
 * Admin-browser upload routes.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 */
class CPTDP_Test_Page_REST extends CPTDP_Pages_Test_Case {

	/**
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Token of the browser run the tests are acting as (set by ready_document()).
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

		parent::tear_down();
	}

	private function base( int $id ): string {
		return '/cptdp/v1/documents/' . $id . '/pages';
	}

	/**
	 * @param string $route  Route.
	 * @param string $body   Raw body.
	 * @param string $type   Content type.
	 */
	private function post( string $route, string $body = '', string $type = 'image/jpeg' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', $type );
		$request->set_body( $body );

		if ( '' !== $this->run && ! str_ends_with( $route, '/start' ) ) {
			$request->set_header( 'X-CPTDP-Run', $this->run );
		}

		return $this->server->dispatch( $request );
	}

	private function finish( int $id, array $payload ): WP_REST_Response {
		return $this->post( $this->base( $id ) . '/finish', (string) wp_json_encode( $payload ), 'application/json' );
	}

	private function ready_document( int $pages = 2 ): int {
		$this->acting_as( 'administrator' );
		$id = $this->make_document( $this->make_pdf_attachment( $pages ) );
		$this->run = (string) $this->post( $this->base( $id ) . '/start' )->get_data()['run'];

		return $id;
	}

	public function test_routes_are_registered_and_there_is_no_public_route(): void {
		$routes = $this->server->get_routes( 'cptdp/v1' );

		$this->assertArrayHasKey( '/cptdp/v1/documents/(?P<id>\d+)/pages', $routes );
		$this->assertArrayHasKey( '/cptdp/v1/documents/(?P<id>\d+)/pages/start', $routes );
		$this->assertArrayHasKey( '/cptdp/v1/documents/(?P<id>\d+)/pages/finish', $routes );
		$this->assertArrayHasKey( '/cptdp/v1/documents/(?P<id>\d+)/pages/retry', $routes );
		$this->assertArrayHasKey( '/cptdp/v1/documents/(?P<id>\d+)/pages/(?P<n>\d+)', $routes );
		unset( $routes['/cptdp/v1'] );
		$this->assertCount( 5, $routes );

		foreach ( $routes as $handlers ) {
			foreach ( $handlers as $handler ) {
				$this->assertNotSame( '__return_true', $handler['permission_callback'] );
			}
		}
	}

	public function test_logged_out_requests_are_refused(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->post( $this->base( $id ) . '/start' )->get_status() );
		$this->assertSame( 401, $this->post( $this->base( $id ) . '/1', $this->image_bytes() )->get_status() );
		$this->assertSame( 401, $this->finish( $id, array( 'count' => 1 ) )->get_status() );
		$this->assertSame( 401, $this->server->dispatch( new WP_REST_Request( 'GET', $this->base( $id ) ) )->get_status() );
	}

	public function test_users_without_edit_rights_are_refused(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->post( $this->base( $id ) . '/start' )->get_status() );
		$this->assertSame( 403, $this->post( $this->base( $id ) . '/1', $this->image_bytes() )->get_status() );
	}

	public function test_a_non_document_is_not_found(): void {
		$this->acting_as( 'administrator' );
		$post = self::factory()->post->create();

		$this->assertSame( 404, $this->post( $this->base( $post ) . '/start' )->get_status() );
	}

	public function test_start_clears_old_images_and_marks_needs_browser(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );

		$response = $this->post( $this->base( $id ) . '/start' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'needs_browser', $response->get_data()['status'] );
		$this->assertNotSame( '', $response->get_data()['fingerprint'] );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_start_without_a_pdf_is_a_bad_request(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_document();

		$this->assertSame( 400, $this->post( $this->base( $id ) . '/start' )->get_status() );
	}

	public function test_upload_stores_a_real_image_with_a_server_chosen_name(): void {
		$id = $this->ready_document();

		$response = $this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFileExists( CPTDP_Page_Store::folder_for( $id ) . '/p-001.jpg' );
	}

	public function test_upload_accepts_webp(): void {
		$id = $this->ready_document();

		$this->assertSame( 200, $this->post( $this->base( $id ) . '/2', $this->image_bytes( 'webp' ), 'image/webp' )->get_status() );
		$this->assertFileExists( CPTDP_Page_Store::folder_for( $id ) . '/p-002.webp' );
	}

	public function test_upload_rejects_things_that_are_not_images(): void {
		$id = $this->ready_document();

		$this->assertSame( 415, $this->post( $this->base( $id ) . '/1', '<?php echo 1;', 'image/webp' )->get_status() );
		$this->assertSame( 413, $this->post( $this->base( $id ) . '/1', '' )->get_status() );
		$this->assertSame( 415, $this->post( $this->base( $id ) . '/1', $this->png_bytes(), 'image/png' )->get_status(), 'PNG is not accepted.' );
		$this->assertFileDoesNotExist( CPTDP_Page_Store::folder_for( $id ) . '/p-001.jpg' );
	}

	public function test_upload_rejects_oversize_and_huge_dimensions(): void {
		$id = $this->ready_document();

		$this->assertSame( 413, $this->post( $this->base( $id ) . '/1', str_repeat( 'a', CPTDP_Page_REST::MAX_BYTES + 1 ) )->get_status() );
		$this->assertSame( 413, $this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 3200, 40 ) )->get_status() );
	}

	public function test_upload_rejects_bad_page_numbers(): void {
		$id = $this->ready_document();

		$this->assertSame( 400, $this->post( $this->base( $id ) . '/0', $this->image_bytes() )->get_status() );
		$this->assertSame( 400, $this->post( $this->base( $id ) . '/999', $this->image_bytes() )->get_status() );
	}

	public function test_finish_validates_and_writes_a_browser_manifest(): void {
		$id    = $this->ready_document( 2 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg', 40, 56 ) );

		$response = $this->finish( $id, array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print ) );

		$this->assertSame( 'ready', $response->get_data()['status'] );
		$this->assertSame( 2, $response->get_data()['count'] );
		$this->assertSame( 'browser', $response->get_data()['producer'] );

		$pages = cptdp_get_document_pages( $id );
		$this->assertSame( 2, $pages['count'] );
		$this->assertSame( 40, $pages['width'], 'Dimensions come from the file, not from the browser.' );
		$this->assertSame( 56, $pages['height'] );
	}

	/**
	 * Finish a two-page browser run with a given `cropBottom` and return the stored crop.
	 *
	 * @param mixed $crop Value sent by the browser (omitted when null).
	 */
	private function finish_with_crop( $crop ): float {
		$id    = $this->ready_document( 2 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg', 40, 56 ) );

		$payload = array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print );
		if ( null !== $crop ) {
			$payload['cropBottom'] = $crop;
		}
		$this->assertSame( 'ready', $this->finish( $id, $payload )->get_data()['status'] );

		return CPTDP_Page_Store::stored_crop( (array) CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_finish_stores_a_valid_crop_bottom_from_the_browser(): void {
		$this->assertSame( 0.1234, $this->finish_with_crop( 0.1234 ) );
		$this->assertSame( 0.25, $this->finish_with_crop( 0.25 ) );
		$this->assertSame( 0.0, $this->finish_with_crop( 0 ) );
	}

	public function test_finish_stores_zero_for_an_invalid_or_missing_crop_bottom(): void {
		foreach ( array( 0.4, 1, -0.2, 'abc', array( 0.1 ), null ) as $bad ) {
			$this->assertSame( 0.0, $this->finish_with_crop( $bad ), wp_json_encode( $bad ) );
		}
	}

	public function test_the_crop_from_the_browser_reaches_the_api(): void {
		$id    = $this->ready_document( 2 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->finish( $id, array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print, 'cropBottom' => 0.2 ) );

		$pages = cptdp_get_document_pages( $id );

		$this->assertSame( 0.2, $pages['crop_bottom'] );
		$this->assertEqualsWithDelta( 40 / ( 56 * 0.8 ), $pages['ratio'], 0.001 );
	}

	public function test_finish_fails_and_cleans_up_when_a_page_is_missing(): void {
		$id    = $this->ready_document( 3 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes() );
		$this->post( $this->base( $id ) . '/3', $this->image_bytes() );

		$response = $this->finish( $id, array( 'count' => 3, 'ext' => 'jpg', 'fingerprint' => $print ) );

		$this->assertSame( 'failed', $response->get_data()['status'] );
		$this->assertStringContainsString( '2', $response->get_data()['error'] );
		$this->assertDirectoryDoesNotExist( CPTDP_Page_Store::folder_for( $id ) );
		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_finish_fails_when_the_pdf_changed_meanwhile(): void {
		$id = $this->ready_document( 1 );
		$this->post( $this->base( $id ) . '/1', $this->image_bytes() );

		$response = $this->finish( $id, array( 'count' => 1, 'ext' => 'jpg', 'fingerprint' => 'old-fingerprint' ) );

		$this->assertSame( 'failed', $response->get_data()['status'] );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_finish_rejects_a_count_out_of_range_and_a_bad_extension(): void {
		$id    = $this->ready_document( 1 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];

		$this->assertSame( 'failed', $this->finish( $id, array( 'count' => 0, 'ext' => 'jpg', 'fingerprint' => $print ) )->get_data()['status'] );
		$this->assertSame( 'failed', $this->finish( $id, array( 'count' => 1, 'ext' => 'php', 'fingerprint' => $print ) )->get_data()['status'] );
		$this->assertSame( 'failed', $this->finish( $id, array( 'count' => 999, 'ext' => 'jpg', 'fingerprint' => $print ) )->get_data()['status'] );
	}

	public function test_finish_with_a_page_total_mismatch_is_failed_with_a_message(): void {
		$this->acting_as( 'administrator' );
		$id        = $this->make_document( $this->make_pdf_attachment( 2 ), 5 );
		$this->run = (string) $this->post( $this->base( $id ) . '/start' )->get_data()['run'];
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes() );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes() );

		$data = $this->finish( $id, array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print ) )->get_data();

		$this->assertSame( 'failed', $data['status'] );
		$this->assertStringContainsString( '5', $data['error'] );
		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_finish_records_a_browser_side_error(): void {
		$id   = $this->ready_document( 2 );
		$data = $this->finish( $id, array( 'error' => '<b>The PDF could not be read</b>' ) )->get_data();

		$this->assertSame( 'failed', $data['status'] );
		$this->assertSame( 'The PDF could not be read', $data['error'] );
	}

	public function test_get_returns_the_current_state(): void {
		$id = $this->ready_document( 1 );

		$data = $this->server->dispatch( new WP_REST_Request( 'GET', $this->base( $id ) ) )->get_data();

		$this->assertSame( 'needs_browser', $data['status'] );
		$this->assertSame( 0, $data['count'] );
	}

	private function png_bytes(): string {
		$image = imagecreatetruecolor( 10, 10 );
		ob_start();
		imagepng( $image );

		return (string) ob_get_clean();
	}

	public function test_a_second_tab_cannot_start_while_a_run_is_active(): void {
		$id = $this->ready_document();

		$this->run = '';
		$second    = $this->post( $this->base( $id ) . '/start' );

		$this->assertSame( 409, $second->get_status() );
		$this->assertSame( 'cptdp_run_active', $second->get_data()['code'] );
	}

	public function test_an_explicit_regenerate_takes_over_and_the_old_tab_is_refused(): void {
		$id    = $this->ready_document();
		$first = $this->run;
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg' ) );

		$this->run = '';
		$taken     = $this->post( $this->base( $id ) . '/start', (string) wp_json_encode( array( 'force' => true ) ), 'application/json' );
		$this->assertSame( 200, $taken->get_status() );
		$this->assertNotSame( $first, $taken->get_data()['run'] );

		$this->run = $first;
		$this->assertSame( 409, $this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg' ) )->get_status() );
		$this->assertSame( 'cptdp_run_mismatch', $this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg' ) )->get_data()['code'] );
		$this->assertSame( 409, $this->finish( $id, array( 'count' => 1, 'ext' => 'jpg', 'fingerprint' => 'x' ) )->get_status() );
	}

	public function test_uploads_with_a_wrong_or_missing_token_are_refused_while_a_run_is_active(): void {
		$id = $this->ready_document();

		$this->run = 'wrong';
		$this->assertSame( 409, $this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg' ) )->get_status() );

		$this->run = '';
		$this->assertSame( 409, $this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg' ) )->get_status() );
		$this->assertFileDoesNotExist( CPTDP_Page_Store::folder_for( $id ) . '/p-001.jpg' );
	}

	public function test_finishing_releases_the_run_so_a_new_one_can_start(): void {
		$id    = $this->ready_document( 1 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->finish( $id, array( 'count' => 1, 'ext' => 'jpg', 'fingerprint' => $print ) );

		$this->run = '';
		$this->assertSame( 200, $this->post( $this->base( $id ) . '/start' )->get_status() );
	}

	public function test_finish_removes_leftover_pages_beyond_the_count(): void {
		$id    = $this->ready_document( 2 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/5', $this->image_bytes( 'jpeg', 40, 56 ) );
		$folder = CPTDP_Page_Store::folder_for( $id );
		file_put_contents( $folder . '/p-001.webp', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		$this->assertSame( 'ready', $this->finish( $id, array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print ) )->get_data()['status'] );

		$this->assertFileExists( $folder . '/p-002.jpg' );
		$this->assertFileDoesNotExist( $folder . '/p-005.jpg' );
		$this->assertFileDoesNotExist( $folder . '/p-001.webp' );
	}

	public function test_finish_rejects_pages_that_are_not_the_same_shape(): void {
		$id    = $this->ready_document( 2 );
		$print = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->post( $this->base( $id ) . '/1', $this->image_bytes( 'jpeg', 40, 56 ) );
		$this->post( $this->base( $id ) . '/2', $this->image_bytes( 'jpeg', 56, 40 ) );

		$data = $this->finish( $id, array( 'count' => 2, 'ext' => 'jpg', 'fingerprint' => $print ) )->get_data();

		$this->assertSame( 'failed', $data['status'] );
		$this->assertStringContainsString( 'Page 2', $data['error'] );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
	}
}
