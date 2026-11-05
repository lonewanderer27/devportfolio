<?php
/**
 * Presentations in the save-time pipeline and the admin routes: server only, never the browser (DPB-026).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Page_Presentations extends CPTDP_Pages_Test_Case {

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

	private function conversion( bool $libreoffice ): void {
		remove_all_filters( 'cptdp_pptx_available' );
		remove_all_filters( 'cptdp_pages_server_available' );
		add_filter( 'cptdp_pages_server_available', '__return_true' );
		add_filter( 'cptdp_pptx_available', $libreoffice ? '__return_true' : '__return_false' );
	}

	private function cron_scheduled( int $id ): bool {
		return false !== wp_next_scheduled( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
	}

	private function post( string $route, string $body = '{}' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );

		return $this->server->dispatch( $request );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function modes(): array {
		return array(
			'auto'    => array( 'auto' ),
			'server'  => array( 'server' ),
			'browser' => array( 'browser' ),
		);
	}

	/**
	 * @dataProvider modes
	 */
	public function test_without_libreoffice_a_presentation_fails_with_the_pdf_advice_in_every_mode(string $mode): void {
		$this->conversion( false );
		update_option( 'cptdp_documents', array( 'pages_mode' => $mode ) );

		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
		$this->assertNotSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ) );
		$this->assertStringContainsString( 'upload the PDF', CPTDP_Page_Store::get_error( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	/**
	 * @dataProvider modes
	 */
	public function test_with_libreoffice_a_presentation_is_queued_for_the_server_whatever_the_mode(string $mode): void {
		$this->conversion( true );
		update_option( 'cptdp_documents', array( 'pages_mode' => $mode ) );

		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_pdfs_still_follow_the_mode(): void {
		$this->conversion( false );
		update_option( 'cptdp_documents', array( 'pages_mode' => 'auto' ) );
		remove_all_filters( 'cptdp_pages_server_available' );
		add_filter( 'cptdp_pages_server_available', '__return_false' );

		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ), 'PDF behaviour is unchanged.' );
	}

	public function test_the_full_flow_ends_ready_with_slides(): void {
		$this->require_libreoffice();
		remove_all_filters( 'cptdp_pptx_available' );
		remove_all_filters( 'cptdp_pages_server_available' );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ), 3 );
		$this->assertSame( 'pending', CPTDP_Page_Store::get_status( $id ) );

		do_action( CPTDP_Page_Scheduler::CRON_HOOK, $id );

		$this->assertSame( 'ready', CPTDP_Page_Store::get_status( $id ) );
		$pages = cptdp_get_document_pages( $id );
		$this->assertSame( 3, $pages['count'] );
		$this->assertSame( 0.0, $pages['crop_bottom'] );
		$this->assertSame( 'pptx', CPTDP_Page_Store::get_manifest( $id )['source_kind'] );
	}

	public function test_a_failed_conversion_is_final_even_in_auto_mode(): void {
		$this->require_libreoffice();
		remove_all_filters( 'cptdp_pptx_available' );
		remove_all_filters( 'cptdp_pages_server_available' );
		update_option( 'cptdp_documents', array( 'pages_mode' => 'auto' ) );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx', 'this is not a presentation' ) );

		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
		$this->assertStringContainsString( 'PDF', CPTDP_Page_Store::get_error( $id ) );

		// The same failure for a PDF would have been handed to the browser.
		$pdf = $this->make_document( $this->make_pdf_attachment( 1, 595, 842, 'not a pdf' ) );
		CPTDP_Page_Scheduler::convert_now( $pdf );
		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $pdf ) );
	}

	public function test_another_conversion_in_progress_reschedules_the_job(): void {
		$this->require_libreoffice();
		remove_all_filters( 'cptdp_pptx_available' );
		remove_all_filters( 'cptdp_pages_server_available' );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		set_transient( CPTDP_Pptx_Converter::LOCK, 1, 60 );

		$status = CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, $status );
		$this->assertTrue( $this->cron_scheduled( $id ), 'A busy LibreOffice must not drop the job.' );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
		delete_transient( CPTDP_Pptx_Converter::LOCK );
	}

	public function test_giving_up_after_the_retries_fails_a_presentation_instead_of_using_the_browser(): void {
		$this->conversion( true );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		set_transient( 'cptdp_pages_lock_' . $id, 1, 60 );
		set_transient( 'cptdp_pages_retries_' . $id, CPTDP_Page_Scheduler::MAX_LOCK_RETRIES, 60 );

		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_switching_the_file_between_pdf_and_presentation_regenerates(): void {
		$this->conversion( true );
		$attachment = $this->make_presentation_attachment( 'pptx' );
		$id         = $this->make_document( $attachment );
		$this->assertSame( 'pptx', CPTDP_Page_Store::source_kind( $id ) );

		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 2 ) );

		$this->assertSame( 'pdf', CPTDP_Page_Store::source_kind( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_the_browser_routes_refuse_presentations(): void {
		$this->conversion( true );
		$this->acting_as( 'administrator' );
		$id   = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$base = '/cptdp/v1/documents/' . $id . '/pages';

		foreach ( array( '/start', '/1', '/finish' ) as $suffix ) {
			$response = $this->post( $base . $suffix );

			$this->assertSame( 400, $response->get_status(), $suffix );
			$this->assertSame( 'cptdp_pptx_server_only', $response->get_data()['code'], $suffix );
		}

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ), 'The refusals change nothing.' );
	}

	public function test_the_state_route_reports_the_kind(): void {
		$this->conversion( false );
		$this->acting_as( 'administrator' );
		$id = $this->make_document( $this->make_presentation_attachment( 'odp' ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/cptdp/v1/documents/' . $id . '/pages' ) );

		$this->assertSame( 'odp', $response->get_data()['kind'] );
		$this->assertSame( 'failed', $response->get_data()['status'] );
	}

	public function test_retry_queues_the_server_again(): void {
		$this->conversion( false );
		$this->acting_as( 'administrator' );
		$id = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$this->assertSame( 'failed', CPTDP_Page_Store::get_status( $id ) );

		$this->conversion( true ); // The admin installed LibreOffice.
		$response = $this->post( '/cptdp/v1/documents/' . $id . '/pages/retry' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'pending', $response->get_data()['status'] );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_retry_is_for_editors_only_and_needs_a_file(): void {
		$this->conversion( true );
		$id   = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$none = $this->make_document();
		$url  = '/cptdp/v1/documents/' . $id . '/pages/retry';

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->post( $url )->get_status() );

		$this->acting_as( 'subscriber' );
		$this->assertSame( 403, $this->post( $url )->get_status() );

		$this->acting_as( 'administrator' );
		$this->assertSame( 400, $this->post( '/cptdp/v1/documents/' . $none . '/pages/retry' )->get_status() );
	}

	public function test_the_pages_meta_box_for_a_presentation(): void {
		$this->conversion( false );
		$this->acting_as( 'administrator' );
		$id   = $this->make_document( $this->make_presentation_attachment( 'pptx' ) );
		$html = $this->capture(
			static function () use ( $id ): void {
				cptdp_document_pages_render_box( get_post( $id ) );
			}
		);

		$this->assertStringContainsString( 'data-kind="pptx"', $html );
		$this->assertStringContainsString( 'may be imperfect', $html, 'The warning is part of the box.' );
		$this->assertStringContainsString( '>Regenerate<', str_replace( array( "\n", "\t" ), '', $html ) );
		$this->assertStringNotContainsString( 'Keep this screen open', $html );
	}
}
