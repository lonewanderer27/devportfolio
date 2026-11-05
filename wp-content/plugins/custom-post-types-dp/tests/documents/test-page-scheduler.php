<?php
/**
 * Save-time page generation: hooks, the server-or-browser decision, cron, column and notice.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 */
class CPTDP_Test_Page_Scheduler extends CPTDP_Pages_Test_Case {

	/**
	 * Force the server tooling on or off for the decision.
	 *
	 * @param bool $available Whether the server can convert.
	 */
	private function server( bool $available ): void {
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

	public function test_adding_the_file_meta_schedules_the_server_when_it_can(): void {
		$this->server( true );
		$id = $this->make_document();

		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 3 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_updating_the_file_meta_schedules_again(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NONE );

		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_saving_a_document_reaches_ensure_at_a_late_priority(): void {
		$this->assertSame( 99, has_action( 'save_post_document', array( CPTDP_Page_Scheduler::class, 'on_save_post' ) ) );

		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NONE );
		delete_post_meta( $id, CPTDP_Page_Store::META_SOURCE );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'Renamed' ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ), 'A legacy document gets its images when saved.' );
	}

	public function test_several_hooks_for_one_save_do_the_work_once(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$first = CPTDP_Page_Scheduler::ensure( $id );
		$next  = wp_next_scheduled( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$again = CPTDP_Page_Scheduler::ensure( $id );

		$this->assertSame( 'pending', $first );
		$this->assertSame( 'pending', $again );
		$this->assertSame( $next, wp_next_scheduled( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) ) );
		$this->assertCount( 1, array_filter( _get_cron_array() ?: array(), static function ( $events ) use ( $id ): bool {
			return isset( $events[ CPTDP_Page_Scheduler::CRON_HOOK ][ md5( serialize( array( $id ) ) ) ] );
		} ) );
	}

	public function test_auto_mode_uses_the_browser_when_the_server_cannot(): void {
		$this->server( false );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_server_mode_fails_when_the_server_cannot(): void {
		$this->server( false );
		update_option( 'cptdp_documents', array( 'pages_mode' => 'server' ) );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Store::get_status( $id ) );
		$this->assertNotSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_browser_mode_never_schedules_the_server(): void {
		$this->server( true );
		update_option( 'cptdp_documents', array( 'pages_mode' => 'browser' ) );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		$this->assertSame( CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::get_status( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_changing_the_pdf_clears_the_stale_images(): void {
		$this->server( true );
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 3 );
		$old = CPTDP_Page_Store::folder_for( $id );
		$this->assertDirectoryExists( $old );

		update_post_meta( $id, 'cptdp_document_file', $this->make_pdf_attachment( 2 ) );

		$this->assertDirectoryDoesNotExist( $old );
		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertSame( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_removing_the_pdf_resets_everything(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );

		delete_post_meta( $id, 'cptdp_document_file' );

		$this->assertSame( CPTDP_Page_Store::STATUS_NONE, CPTDP_Page_Store::get_status( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
	}

	public function test_valid_images_are_left_alone_unless_forced(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$this->fake_pages( $id, 3 );

		$this->assertSame( 'ready', CPTDP_Page_Scheduler::ensure( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ) );
		$this->assertNotNull( cptdp_get_document_pages( $id ) );

		$this->assertSame( 'pending', CPTDP_Page_Scheduler::ensure( $id, true ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
		$this->assertNull( cptdp_get_document_pages( $id ) );
	}

	public function test_a_page_total_mismatch_is_reported_without_regenerating(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 4 ), 4 );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$this->fake_pages( $id, 4 );

		update_post_meta( $id, 'cptdp_total_pages', 9 );
		$this->assertSame( 'failed', CPTDP_Page_Scheduler::ensure( $id ) );
		$this->assertFalse( $this->cron_scheduled( $id ), 'Images exist, so nothing is regenerated.' );

		update_post_meta( $id, 'cptdp_total_pages', 4 );
		$this->assertSame( 'ready', CPTDP_Page_Scheduler::ensure( $id ) );
	}

	public function test_drafts_are_included_but_auto_drafts_and_revisions_are_skipped(): void {
		$this->server( true );
		$attachment = $this->make_pdf_attachment( 2 );

		$draft = self::factory()->post->create( array( 'post_type' => 'document', 'post_status' => 'draft' ) );
		update_post_meta( $draft, 'cptdp_document_file', $attachment );
		$this->assertSame( 'pending', CPTDP_Page_Store::get_status( $draft ) );

		$auto = self::factory()->post->create( array( 'post_type' => 'document', 'post_status' => 'auto-draft' ) );
		update_post_meta( $auto, 'cptdp_document_file', $attachment );
		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $auto ) );

		$trash = self::factory()->post->create( array( 'post_type' => 'document', 'post_status' => 'trash' ) );
		update_post_meta( $trash, 'cptdp_document_file', $attachment );
		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $trash ) );

		$revision = self::factory()->post->create( array( 'post_type' => 'revision', 'post_parent' => $draft, 'post_status' => 'inherit' ) );
		CPTDP_Page_Scheduler::on_save_post( $revision, get_post( $revision ) );
		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $revision ) );
	}

	public function test_other_post_types_are_ignored(): void {
		$post = self::factory()->post->create();
		update_post_meta( $post, 'cptdp_document_file', $this->make_pdf_attachment( 2 ) );

		$this->assertSame( 'none', CPTDP_Page_Store::get_status( $post ) );
	}

	public function test_editing_the_attachment_rechecks_the_documents_that_use_it(): void {
		$this->server( true );
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 2 );

		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 4 ) );
		wp_update_post( array( 'ID' => $attachment, 'post_title' => 'Replaced' ) );

		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertSame( 'pending', CPTDP_Page_Store::get_status( $id ) );
	}

	public function test_cron_job_converts_and_records_the_result(): void {
		$this->require_server_tooling();
		remove_all_filters( 'cptdp_pages_server_available' );
		$id = $this->make_document( $this->make_pdf_attachment( 3 ) );
		$this->assertSame( 'pending', CPTDP_Page_Store::get_status( $id ) );

		do_action( CPTDP_Page_Scheduler::CRON_HOOK, $id );

		$this->assertSame( 'ready', CPTDP_Page_Store::get_status( $id ) );
		$this->assertSame( 3, cptdp_get_document_pages( $id )['count'] );
	}

	public function test_a_locked_document_is_not_converted_twice(): void {
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		set_transient( 'cptdp_pages_lock_' . $id, 1, 60 );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_PENDING );

		$this->assertSame( 'pending', CPTDP_Page_Scheduler::convert_now( $id ) );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ), 'Not converted while locked.' );
		$this->assertTrue( $this->cron_scheduled( $id ), 'A held lock must not drop the job.' );
	}

	public function test_generate_on_server_ignores_the_mode(): void {
		$this->server( true );
		update_option( 'cptdp_documents', array( 'pages_mode' => 'browser' ) );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );

		$this->assertSame( 'pending', CPTDP_Page_Scheduler::generate_on_server( $id, true ) );
		$this->assertTrue( $this->cron_scheduled( $id ) );
	}

	public function test_documents_with_pdf_lists_only_documents_with_a_pdf(): void {
		$with    = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$without = $this->make_document();
		$image   = $this->make_document( $this->make_attachment( 'image/png' ) );

		$ids = CPTDP_Page_Scheduler::documents_with_pdf();

		$this->assertContains( $with, $ids );
		$this->assertNotContains( $without, $ids );
		$this->assertNotContains( $image, $ids );
	}

	public function test_the_pages_column_shows_each_status(): void {
		$none = $this->make_document();
		$this->assertStringContainsString( '&mdash;', $this->capture( static function () use ( $none ): void {
			cptdp_document_column_content( 'cptdp_pages_status', $none );
		} ) );

		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		CPTDP_Page_Store::set_status( $id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER );
		$html = $this->capture( static function () use ( $id ): void {
			cptdp_document_column_content( 'cptdp_pages_status', $id );
		} );
		$this->assertStringContainsString( 'Needs your browser', $html );
		$this->assertStringContainsString( '<a ', $html );

		$this->fake_pages( $id, 2 );
		$html = $this->capture( static function () use ( $id ): void {
			cptdp_document_column_content( 'cptdp_pages_status', $id );
		} );
		$this->assertStringContainsString( 'Ready, 2 pages', $html );
	}

	public function test_the_list_notice_counts_documents_needing_the_browser(): void {
		set_current_screen( 'edit-document' );
		$this->acting_as( 'administrator' );

		$this->assertSame( '', $this->capture( 'cptdp_document_pages_list_notice' ) );

		$this->server( false );
		$this->make_document( $this->make_pdf_attachment( 2 ) );
		$this->make_document( $this->make_pdf_attachment( 2 ) );

		$html = $this->capture( 'cptdp_document_pages_list_notice' );
		$this->assertStringContainsString( '2 documents need page images', $html );
		set_current_screen( 'front' );
	}

	public function test_the_meta_box_renders_each_state(): void {
		$none = self::factory()->post->create_and_get( array( 'post_type' => 'document' ) );
		$this->assertStringContainsString( 'Select a PDF', $this->capture( static function () use ( $none ): void {
			cptdp_document_pages_render_box( $none );
		} ) );

		$this->server( false );
		$id   = $this->make_document( $this->make_pdf_attachment( 2 ) );
		$html = $this->capture( static function () use ( $id ): void {
			cptdp_document_pages_render_box( get_post( $id ) );
		} );
		$this->assertStringContainsString( 'id="cptdp-pages-box"', $html );
		$this->assertStringContainsString( 'data-status="needs_browser"', $html );
		$this->assertStringContainsString( 'cptdp-pages-regenerate', $html );
	}

	public function test_the_pages_mode_setting_is_sanitised(): void {
		$this->assertSame( 'auto', cptdp_get_documents_settings( 'pages_mode' ) );

		update_option( 'cptdp_documents', array( 'pages_mode' => 'browser' ) );
		$this->assertSame( 'browser', cptdp_get_documents_settings( 'pages_mode' ) );

		update_option( 'cptdp_documents', array( 'pages_mode' => 'bogus' ) );
		$this->assertSame( 'auto', cptdp_get_documents_settings( 'pages_mode' ) );

		$this->assertSame( 'server', cptdp_documents_sanitize( array( 'pages_mode' => 'server' ) )['pages_mode'] );
		$this->assertSame( 'auto', cptdp_documents_sanitize( array( 'pages_mode' => '<script>' ) )['pages_mode'] );
		$this->assertSame( 'auto', cptdp_documents_sanitize( array() )['pages_mode'] );
	}

	public function test_a_conversion_for_a_replaced_pdf_is_discarded(): void {
		$this->require_server_tooling();
		remove_all_filters( 'cptdp_pages_server_available' );
		$attachment = $this->make_pdf_attachment( 3 );
		$id         = $this->make_document( $attachment );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );

		// A conversion of the old PDF finishes...
		$result     = CPTDP_Page_Converter::convert( $id );
		$old_folder = CPTDP_Page_Store::base_dir() . '/' . $result['dir'];
		$this->assertDirectoryExists( $old_folder );

		// ...but the PDF was replaced while it ran, and ensure() already handled the new file.
		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 5 ) );
		CPTDP_Page_Scheduler::ensure( $id );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );

		$status = CPTDP_Page_Scheduler::record_conversion( $id, $result );

		$this->assertSame( 'pending', $status );
		$this->assertNull( CPTDP_Page_Store::get_manifest( $id ), 'Old pages are not attached to the new PDF.' );
		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertDirectoryDoesNotExist( $old_folder, 'The old output is deleted.' );
		$this->assertTrue( $this->cron_scheduled( $id ), 'The new PDF is queued again.' );
	}

	public function test_a_failed_conversion_for_a_replaced_pdf_does_not_mark_the_new_pdf_failed(): void {
		$this->server( true );
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$token      = CPTDP_Page_Store::get_source( $id )['token'];
		$this->overwrite_attachment_file( $attachment, $this->pdf_bytes( 4 ) );
		CPTDP_Page_Scheduler::ensure( $id );

		$status = CPTDP_Page_Scheduler::record_conversion( $id, new WP_Error( 'cptdp_conversion_failed', 'boom' ), $token );

		$this->assertSame( 'pending', $status );
		$this->assertSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_a_locked_document_is_tried_again_in_a_minute(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		set_transient( 'cptdp_pages_lock_' . $id, 1, 60 );

		$this->assertSame( 'pending', CPTDP_Page_Scheduler::convert_now( $id ) );

		$next = wp_next_scheduled( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		$this->assertNotFalse( $next, 'The event is not dropped.' );
		$this->assertGreaterThanOrEqual( time() + 55, $next );
		$this->assertLessThanOrEqual( time() + 65, $next );
	}

	public function test_retries_are_bounded_and_end_with_a_hand_over(): void {
		$this->server( true );
		$id = $this->make_document( $this->make_pdf_attachment( 2 ) );
		set_transient( 'cptdp_pages_lock_' . $id, 1, 600 );
		set_transient( 'cptdp_pages_retries_' . $id, CPTDP_Page_Scheduler::MAX_LOCK_RETRIES, HOUR_IN_SECONDS );
		wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );

		CPTDP_Page_Scheduler::convert_now( $id );

		$this->assertFalse( $this->cron_scheduled( $id ), 'No endless retries.' );
		$this->assertSame( 'needs_browser', CPTDP_Page_Store::get_status( $id ) );
		$this->assertNotSame( '', CPTDP_Page_Store::get_error( $id ) );
	}

	public function test_replacing_the_file_of_an_attachment_rechecks_its_documents(): void {
		$this->server( true );
		$attachment = $this->make_pdf_attachment( 2 );
		$id         = $this->make_document( $attachment );
		$this->fake_pages( $id, 2 );
		$this->assertIsArray( cptdp_get_document_pages( $id ) );

		// Replace Media style: new bytes and `update_attached_file()` with the new path.
		$upload = wp_upload_dir();
		$new    = $upload['path'] . '/replacement-' . wp_generate_uuid4() . '.pdf';
		file_put_contents( $new, $this->pdf_bytes( 4 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		update_attached_file( $attachment, $new );

		$this->assertNull( cptdp_get_document_pages( $id ) );
		$this->assertSame( 'pending', CPTDP_Page_Store::get_status( $id ) );
		wp_delete_file( $new );
	}

	public function test_uploads_and_the_old_file_filter_cost_nothing(): void {
		$this->assertFalse( has_filter( 'update_attached_file', array( CPTDP_Page_Scheduler::class, 'on_attached_file' ) ) );
		$this->assertSame( 10, has_action( 'updated_post_meta', array( CPTDP_Page_Scheduler::class, 'on_attached_file_meta' ) ) );
		$this->assertFalse( has_action( 'added_post_meta', array( CPTDP_Page_Scheduler::class, 'on_attached_file_meta' ) ), 'A new upload cannot be in a document yet.' );
	}
}
