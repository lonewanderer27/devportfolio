<?php
/**
 * Document page store tests (manifest, validity, status, cleanup, public API).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 */
class CPTDP_Test_Page_Store extends CPTDP_Pages_Test_Case {

	public function test_no_pdf_means_no_pages(): void {
		$id = $this->make_document();

		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertNull( CPTDP_Page_Store::get_source( $id ) );
		$this->assertSame( '', cptdp_document_pdf_url( $id ) );
	}

	public function test_a_non_pdf_attachment_is_not_a_source(): void {
		$id = $this->make_document( $this->make_attachment( 'image/png' ) );

		$this->assertNull( CPTDP_Page_Store::get_source( $id ) );
		$this->assertSame( '', cptdp_document_pdf_url( $id ) );
	}

	public function test_pdf_url_is_returned_for_a_pdf(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertStringEndsWith( '.pdf', cptdp_document_pdf_url( $id ) );
	}

	public function test_pdfjs_urls_point_to_shipped_files(): void {
		$urls = cptdp_document_pdfjs_urls();

		$this->assertSame( array( 'module', 'worker' ), array_keys( $urls ) );
		$this->assertStringEndsWith( 'assets/vendor/pdfjs/pdf.min.mjs', $urls['module'] );
		$this->assertFileExists( CPTDP_PATH . 'assets/vendor/pdfjs/pdf.min.mjs' );
		$this->assertFileExists( CPTDP_PATH . 'assets/vendor/pdfjs/pdf.worker.min.mjs' );
		$this->assertFileExists( CPTDP_PATH . 'assets/vendor/pdfjs/LICENSE' );
	}

	public function test_valid_manifest_returns_pages_and_ratio(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		$this->fake_pages( $id, 3 );

		$pages = cptdp_get_document_pages( $id );

		$this->assertIsArray( $pages );
		$this->assertSame( 3, $pages['count'] );
		$this->assertCount( 3, $pages['pages'] );
		$this->assertStringEndsWith( '/p-001.webp', $pages['pages'][0] );
		$this->assertStringEndsWith( '/p-003.webp', $pages['pages'][2] );
		$this->assertSame( 1400, $pages['width'] );
		$this->assertEqualsWithDelta( 1400 / 1980, $pages['ratio'], 0.001 );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_never_made_means_none(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_invalid_when_the_pdf_content_changes(): void {
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 3 );
		$this->assertNotNull( cptdp_get_document_pages( $id ) );

		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 5 ) );

		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_touching_the_file_without_changing_it_stays_valid(): void {
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 3 );

		touch( (string) get_attached_file( $attachment ), time() + 100 );
		clearstatcache();

		$this->assertNotNull( cptdp_get_document_pages( $id ), 'The content hash fallback keeps the images valid.' );
	}

	public function test_invalid_when_the_document_uses_another_attachment(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		$this->fake_pages( $id, 3 );

		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 3 ) );

		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_page_total_mismatch_invalidates_and_reconcile_reports_it(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 4 ), 6 );
		$this->fake_pages( $id, 4 );

		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
		$this->assertStringContainsString( '4', CPTDP_Page_Store::get_error( $id ) );
		$this->assertStringContainsString( '6', CPTDP_Page_Store::get_error( $id ) );

		update_post_meta( $id, 'cptdp_total_pages', 4 );

		$this->assertNotNull( cptdp_get_document_pages( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_READY, CPTDP_Page_Store::reconcile( $id ) );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_invalid_when_the_first_or_last_file_is_missing(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		$this->fake_pages( $id, 3 );
		$folder = CPTDP_Page_Store::folder_for( $id );

		unlink( $folder . '/p-003.webp' );
		$this->assertNull( cptdp_get_document_pages( $id ), 'last missing' );

		file_put_contents( $folder . '/p-003.webp', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		unlink( $folder . '/p-001.webp' );
		$this->assertNull( cptdp_get_document_pages( $id ), 'first missing' );
	}

	public function test_set_status_validates_and_clears_the_message(): void {
		$id = $this->make_document();

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_FAILED, 'boom' );
		$this->assertSame( 'failed', CPTDP_Page_Store::get_status( $id ) );
		$this->assertSame( 'boom', CPTDP_Page_Store::get_error( $id ) );

		CPTDP_Page_Store::set_status( $id, 'nonsense' );
		$this->assertSame( 'failed', CPTDP_Page_Store::get_status( $id ), 'Unknown statuses are ignored.' );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $id ) );

		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NONE );
		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_clear_removes_files_and_manifest(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );
		$folder = CPTDP_Page_Store::folder_for( $id );
		$this->assertDirectoryExists( $folder );

		CPTDP_Page_Store::clear( $id, true );

		$this->assertDirectoryDoesNotExist( $folder );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_delete_dir_refuses_paths_outside_the_pages_folder(): void {
		$outside = wp_normalize_path( get_temp_dir() ) . 'cptdp-outside-' . wp_generate_uuid4();
		wp_mkdir_p( $outside );
		file_put_contents( $outside . '/keep.txt', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		CPTDP_Page_Store::delete_dir( $outside );
		CPTDP_Page_Store::delete_dir( CPTDP_Page_Store::base_dir() . '/../' . basename( $outside ) );

		$this->assertFileExists( $outside . '/keep.txt' );

		unlink( $outside . '/keep.txt' );
		rmdir( $outside );
	}

	public function test_permanently_deleting_a_document_removes_its_images(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->fake_pages( $id, 2 );
		$folder = CPTDP_Page_Store::folder_for( $id );

		wp_delete_post( $id, true );

		$this->assertDirectoryDoesNotExist( $folder );
	}

	public function test_deleting_the_attachment_removes_the_images_and_status(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 2 );
		$folder = CPTDP_Page_Store::folder_for( $id );

		wp_delete_attachment( $attachment, true );

		$this->assertDirectoryDoesNotExist( $folder );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_page_meta_is_not_exposed_in_rest_and_needs_edit_rights(): void {
		$registered = get_registered_meta_keys( 'post', 'document' );

		foreach ( array( CPTDP_Page_Store::META_MANIFEST, CPTDP_Page_Store::META_STATUS, CPTDP_Page_Store::META_ERROR, CPTDP_Page_Store::META_SOURCE ) as $key ) {
			$this->assertArrayHasKey( $key, $registered, $key );
			$this->assertFalse( $registered[ $key ]['show_in_rest'], $key );
		}

		$id = $this->make_document();
		$this->acting_as( 'subscriber' );
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( (bool) $registered[ CPTDP_Page_Store::META_STATUS ]['auth_callback']( true, CPTDP_Page_Store::META_STATUS, $id ) );
	}

	public function test_two_documents_sharing_one_pdf_each_own_a_folder(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$a          = $this->make_document( $attachment );
		$b          = $this->make_document( $attachment );
		$this->fake_pages( $a, 2 );
		$this->fake_pages( $b, 2 );

		$this->assertNotSame( CPTDP_Page_Store::folder_for( $a ), CPTDP_Page_Store::folder_for( $b ) );
		$this->assertMatchesRegularExpression( '/-' . $a . '$/', CPTDP_Page_Store::folder_for( $a ) );

		CPTDP_Page_Store::clear( $a, true );
		$this->assertNull( cptdp_get_document_pages( $a ) );
		$this->assertIsArray( cptdp_get_document_pages( $b ), 'Clearing one document leaves the other\'s images.' );

		wp_delete_post( $a, true );
		$this->assertIsArray( cptdp_get_document_pages( $b ), 'Deleting one document leaves the other\'s images.' );
		$this->assertFileExists( CPTDP_Page_Store::folder_for( $b ) . '/p-001.webp' );
	}

	public function test_folder_names_are_validated(): void {
		foreach ( array( '12-abcdef01', '12-abcdef01-34' ) as $ok ) {
			$this->assertTrue( CPTDP_Page_Store::is_valid_dir( $ok ), $ok );
		}
		foreach ( array( '.', '..', '', '../x', 'a/b', '12-ABCDEF01', '12-abcdef0', '12-abcdef01-', '12-abcdef01-x', '12-abcdef01/..', "12-abcdef01\n" ) as $bad ) {
			$this->assertFalse( CPTDP_Page_Store::is_valid_dir( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_a_tampered_manifest_dir_never_wipes_other_folders(): void {
		$id      = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$sibling = CPTDP_Page_Store::base_dir() . '/99-deadbeef-1';
		wp_mkdir_p( $sibling );
		file_put_contents( $sibling . '/p-001.webp', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		foreach ( array( '.', '..', '../', '99-deadbeef-1/../..', '/' ) as $dir ) {
			update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, array( 'dir' => $dir, 'count' => 1, 'ext' => 'webp' ) );

			$this->assertNull( CPTDP_Page_Store::get_manifest( $id ), $dir );
			$this->assertNull( cptdp_get_document_pages( $id ) );
			CPTDP_Page_Store::clear( $id, true );
			$this->assertFileExists( $sibling . '/p-001.webp', 'clear() with dir ' . $dir );
		}

		CPTDP_Page_Store::delete_dir( CPTDP_Page_Store::base_dir() );
		CPTDP_Page_Store::delete_dir( CPTDP_Page_Store::base_dir() . '/.' );
		CPTDP_Page_Store::delete_dir( CPTDP_Page_Store::base_dir() . '/99-deadbeef-1/..' );
		$this->assertFileExists( $sibling . '/p-001.webp' );
	}

	public function test_a_manifest_made_from_another_pdf_is_not_saved(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$token      = CPTDP_Page_Store::get_source( $id )['token'];
		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 5 ) );

		$saved = CPTDP_Page_Store::save_manifest( $id, array( 'count' => 2, 'width' => 10, 'height' => 14, 'ext' => 'webp', 'dir' => '1-abcdef01-1', 'source_token' => $token ) );

		$this->assertFalse( $saved );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_a_manifest_from_the_current_pdf_is_saved_without_the_token(): void {
		$id    = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$token = CPTDP_Page_Store::get_source( $id )['token'];
		$folder = CPTDP_Page_Store::folder_for( $id );
		wp_mkdir_p( $folder );
		file_put_contents( $folder . '/p-001.webp', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		file_put_contents( $folder . '/p-002.webp', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		$this->assertTrue( CPTDP_Page_Store::save_manifest( $id, array( 'count' => 2, 'width' => 10, 'height' => 14, 'ext' => 'webp', 'dir' => basename( $folder ), 'source_token' => $token ) ) );
		$this->assertArrayNotHasKey( 'source_token', (array) CPTDP_Page_Store::get_manifest( $id ) );
	}

	public function test_a_touched_but_unchanged_pdf_refreshes_the_fingerprint_once(): void {
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 2 );
		$before = CPTDP_Page_Store::get_manifest( $id )['fingerprint'];

		$path = (string) get_attached_file( $attachment );
		touch( $path, time() + 120 );
		clearstatcache( true, $path );
		$now = CPTDP_Page_Store::get_source( $id )['fingerprint'];
		$this->assertNotSame( $before, $now );

		$this->assertTrue( CPTDP_Page_Store::is_valid( $id ) );
		$this->assertSame( $now, CPTDP_Page_Store::get_manifest( $id )['fingerprint'], 'The new fingerprint is stored, so the PDF is hashed only once.' );

		// Prove the next check does not need the content hash: break the stored hash and it still validates.
		$manifest         = CPTDP_Page_Store::get_manifest( $id );
		$manifest['hash'] = 'not-the-hash';
		update_post_meta( $id, CPTDP_Page_Store::META_MANIFEST, $manifest );
		$this->assertTrue( CPTDP_Page_Store::is_valid( $id ) );
	}
}
