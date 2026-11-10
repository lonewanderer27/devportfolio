<?php
/**
 * Featured image made from page 1 (DPC-023), for documents and collaterals.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group pages
 * @group featured
 */
class CPTDP_Test_Page_Featured extends CPTDP_Pages_Test_Case {

	public function set_up(): void {
		parent::set_up();

		// Conversions are not needed: the sets are written by real_set().
		add_filter( 'cptdp_pages_server_available', '__return_true' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function types(): array {
		return array(
			'document'   => array( 'document' ),
			'collateral' => array( 'collateral' ),
		);
	}

	/**
	 * A published post of the type, with a PDF.
	 *
	 * @param string $type Post type.
	 */
	private function make( string $type ): int {
		$pdf = $this->make_pdf_attachment( 2 );

		return 'collateral' === $type ? $this->make_collateral( $pdf ) : $this->make_document( $pdf );
	}

	/**
	 * Write a ready set of real JPEG pages for the post's current file (what a conversion would leave).
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Pages.
	 */
	private function real_set( int $post_id, int $count = 2 ): void {
		$folder = CPTDP_Page_Store::folder_for( $post_id );
		wp_mkdir_p( $folder );

		for ( $i = 1; $i <= $count; $i++ ) {
			file_put_contents( $folder . '/' . CPTDP_Page_Store::page_filename( $i, 'jpg' ), $this->image_bytes( 'jpeg', 400, 560 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}

		CPTDP_Page_Store::save_manifest(
			$post_id,
			array(
				'count'  => $count,
				'width'  => 400,
				'height' => 560,
				'ext'    => 'jpg',
				'dir'    => basename( $folder ),
			)
		);
	}

	private function editor_image( int $post_id ): int {
		return (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post_id );
	}

	private function featured_off(): void {
		update_option( 'cptdp_documents', array( 'featured_from_page' => false ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_a_ready_set_makes_page_one_the_featured_image( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );

		$thumb = (int) get_post_thumbnail_id( $id );
		$page  = CPTDP_Page_Store::base_dir() . '/' . CPTDP_Page_Store::get_manifest( $id )['dir'] . '/p-001.jpg';

		$this->assertGreaterThan( 0, $thumb );
		$this->assertSame( $thumb, (int) get_post_meta( $id, CPTDP_Page_Featured::META, true ) );
		$this->assertSame( $id, (int) get_post_meta( $thumb, CPTDP_Page_Featured::META_FOR, true ) );
		$this->assertSame( $id, (int) get_post_field( 'post_parent', $thumb ) );
		$this->assertStringContainsString( '-page-1', basename( (string) get_attached_file( $thumb ) ) );
		$this->assertSame( md5_file( $page ), md5_file( (string) get_attached_file( $thumb ) ), 'A copy of page 1.' );
		$this->assertSame( 'generated', CPTDP_Page_Featured::owner( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $id ) );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_a_second_save_of_the_same_set_keeps_the_image( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );
		$first = (int) get_post_thumbnail_id( $id );

		$this->assertSame( 'kept', CPTDP_Page_Featured::sync( $id ) );
		$this->assertSame( $first, (int) get_post_thumbnail_id( $id ) );

		$this->assertSame( 'created', CPTDP_Page_Featured::sync( $id, true ), '--force makes it again.' );
		$this->assertNotSame( $first, (int) get_post_thumbnail_id( $id ) );
		$this->assertNull( get_post( $first ), 'The old one is deleted.' );
	}

	/**
	 * @dataProvider types
	 */
	public function test_an_image_the_editor_chose_is_never_replaced( string $type ): void {
		$id    = $this->make( $type );
		$image = $this->editor_image( $id );
		set_post_thumbnail( $id, $image );

		$this->real_set( $id );

		$this->assertSame( $image, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( 0, (int) get_post_meta( $id, CPTDP_Page_Featured::META, true ) );
		$this->assertSame( 'editor', CPTDP_Page_Featured::owner( $id ) );
		$this->assertSame( 'kept', CPTDP_Page_Featured::sync( $id, true ) );
		$this->assertSame( $image, (int) get_post_thumbnail_id( $id ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_the_setting_switches_it_off_and_keeps_what_was_made( string $type ): void {
		$this->featured_off();
		$off = $this->make( $type );
		$this->real_set( $off );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $off ) );

		delete_option( 'cptdp_documents' );
		$id = $this->make( $type );
		$this->real_set( $id );
		$made = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $made );

		$this->featured_off();
		$this->assertSame( 'skipped', CPTDP_Page_Featured::sync( $id, true ) );
		$this->assertSame( $made, (int) get_post_thumbnail_id( $id ), 'Off keeps the images already made.' );
	}

	/**
	 * @dataProvider types
	 */
	public function test_a_new_file_swaps_the_image_without_a_blank_in_between( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );
		$old = (int) get_post_thumbnail_id( $id );

		update_post_meta( $id, CPTDP_Page_Store::file_meta_key( $id ), $this->make_pdf_attachment( 3 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertSame( $old, (int) get_post_thumbnail_id( $id ), 'The old image stays until the new set is ready.' );

		$this->real_set( $id, 3 );

		$new = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $new );
		$this->assertNotSame( $old, $new );
		$this->assertNull( get_post( $old ) );
		$this->assertSame( $new, (int) get_post_meta( $id, CPTDP_Page_Featured::META, true ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_removing_the_file_deletes_our_image_but_never_the_editors( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );
		$ours = (int) get_post_thumbnail_id( $id );

		delete_post_meta( $id, CPTDP_Page_Store::file_meta_key( $id ) );

		$this->assertNull( get_post( $ours ) );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $id ) );

		$other = $this->make( $type );
		$image = $this->editor_image( $other );
		set_post_thumbnail( $other, $image );

		delete_post_meta( $other, CPTDP_Page_Store::file_meta_key( $other ) );

		$this->assertNotNull( get_post( $image ) );
		$this->assertSame( $image, (int) get_post_thumbnail_id( $other ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_deleting_the_post_deletes_our_image( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );
		$ours = (int) get_post_thumbnail_id( $id );

		wp_delete_post( $id, true );

		$this->assertNull( get_post( $ours ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_an_editor_deleting_our_image_lets_the_next_sync_make_one( string $type ): void {
		$id = $this->make( $type );
		$this->real_set( $id );
		$ours = (int) get_post_thumbnail_id( $id );

		wp_delete_attachment( $ours, true );

		$this->assertSame( 0, (int) get_post_meta( $id, CPTDP_Page_Featured::META, true ) );
		$this->assertSame( 'created', CPTDP_Page_Featured::sync( $id ) );
		$this->assertGreaterThan( 0, (int) get_post_thumbnail_id( $id ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_a_failure_is_reported_and_the_pages_stay_ready( string $type ): void {
		add_filter(
			'wp_handle_sideload_prefilter',
			static function ( array $file ): array {
				$file['error'] = 'Refused by the test.';

				return $file;
			}
		);
		$id = $this->make( $type );
		$this->real_set( $id );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $id ) );
		$this->assertStringStartsWith( 'Featured image: ', CPTDP_Page_Store::get_error( $id ) );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $id ) );
	}

	public function test_a_browser_made_set_gets_a_featured_image_too(): void {
		update_option( 'cptdp_documents', array( 'pages_mode' => 'browser' ) );
		$this->acting_as( 'administrator' );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$id   = $this->make_collateral( $this->make_pdf_attachment( 1 ) );
		$base = '/cptdp/v1/' . CPTDP_Page_REST::route_for( $id );

		$start = new WP_REST_Request( 'POST', $base . '/start' );
		$run   = (string) $wp_rest_server->dispatch( $start )->get_data()['run'];

		$page = new WP_REST_Request( 'POST', $base . '/1' );
		$page->set_header( 'Content-Type', 'image/jpeg' );
		$page->set_header( 'X-CPTDP-Run', $run );
		$page->set_body( $this->image_bytes( 'jpeg', 400, 560 ) );
		$this->assertSame( 200, $wp_rest_server->dispatch( $page )->get_status() );

		$finish = new WP_REST_Request( 'POST', $base . '/finish' );
		$finish->set_header( 'Content-Type', 'application/json' );
		$finish->set_header( 'X-CPTDP-Run', $run );
		$finish->set_body( (string) wp_json_encode( array( 'count' => 1, 'ext' => 'jpg', 'fingerprint' => CPTDP_Page_Store::get_source( $id )['fingerprint'] ) ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_READY, $wp_rest_server->dispatch( $finish )->get_data()['status'] );
		$this->assertSame( 'generated', CPTDP_Page_Featured::owner( $id ) );
	}

	/**
	 * @dataProvider types
	 */
	public function test_the_box_links_administrators_to_the_shared_settings( string $type ): void {
		$id = $this->make( $type );

		$this->acting_as( 'administrator' );
		ob_start();
		cptdp_document_pages_render_box( get_post( $id ) );
		$admin = (string) ob_get_clean();

		$this->assertStringContainsString( 'options-general.php?page=cptdp-documents#cptdp-page-images', $admin );
		$this->assertStringContainsString( 'Page image settings (shared with Documents/Brand Collaterals)', $admin );

		$this->acting_as( 'editor' );
		ob_start();
		cptdp_document_pages_render_box( get_post( $id ) );
		$this->assertStringNotContainsString( '#cptdp-page-images', (string) ob_get_clean() );
	}
}
