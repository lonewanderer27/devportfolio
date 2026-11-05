<?php
/**
 * Save-time page image generation: hooks, the server-or-browser decision and the cron job.
 *
 * Every way a document's PDF appears or changes ends in `ensure()`. It never
 * converts during the request: it schedules a WP-Cron job for the server, or
 * marks the document as needing the admin's browser.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates when and by whom page images are generated.
 */
class CPTDP_Page_Scheduler {

	const CRON_HOOK = 'cptdp_convert_document_pages';

	/**
	 * How many times a conversion that found the lock held is tried again (one minute apart).
	 */
	const MAX_LOCK_RETRIES = 10;

	/**
	 * Attach the hooks.
	 */
	public static function init(): void {
		add_action( 'added_post_meta', array( __CLASS__, 'on_file_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_file_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_file_meta' ), 10, 4 );
		add_action( 'save_post_document', array( __CLASS__, 'on_save_post' ), 99, 3 );
		add_action( 'edit_attachment', array( __CLASS__, 'on_attachment_changed' ) );
		add_action( 'attachment_updated', array( __CLASS__, 'on_attachment_changed' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_attached_file_meta' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete_attachment' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );
	}

	/**
	 * The "Generate page images" mode: auto, server or browser.
	 */
	public static function mode(): string {
		return (string) cptdp_get_documents_settings( 'pages_mode' );
	}

	/**
	 * `cptdp_document_file` meta added, updated or deleted.
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $post_id   Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $meta_value Meta value.
	 */
	public static function on_file_meta( $meta_id, $post_id, $meta_key, $meta_value = null ): void {
		unset( $meta_id, $meta_value );

		if ( 'cptdp_document_file' !== $meta_key || 'document' !== get_post_type( (int) $post_id ) ) {
			return;
		}

		self::ensure( (int) $post_id );
	}

	/**
	 * A document was saved (runs after the plugin's own meta box save).
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function on_save_post( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) ) {
			return;
		}

		self::ensure( (int) $post_id );
	}

	/**
	 * An attachment was edited: re-check the documents that use it.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_attachment_changed( $attachment_id ): void {
		foreach ( CPTDP_Page_Store::documents_using( (int) $attachment_id ) as $post_id ) {
			self::ensure( $post_id );
		}
	}

	/**
	 * The file behind an attachment changed (Replace Media and `update_attached_file()`).
	 *
	 * Runs after `_wp_attached_file` is saved, so the documents see the new path. Only on
	 * updates (a new upload cannot be in a document yet), so uploads cost nothing extra.
	 *
	 * @param int    $meta_id       Meta ID.
	 * @param int    $attachment_id Post ID.
	 * @param string $meta_key      Meta key.
	 */
	public static function on_attached_file_meta( $meta_id, $attachment_id, $meta_key ): void {
		unset( $meta_id );

		if ( '_wp_attached_file' !== $meta_key || 'attachment' !== get_post_type( (int) $attachment_id ) ) {
			return;
		}

		self::on_attachment_changed( (int) $attachment_id );
	}

	/**
	 * A document is being deleted for good: remove its images.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_before_delete_post( $post_id ): void {
		if ( 'document' !== get_post_type( (int) $post_id ) ) {
			return;
		}

		CPTDP_Page_Store::clear( (int) $post_id, true );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $post_id ) );
	}

	/**
	 * An attachment is being deleted: remove the sets made from it.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_delete_attachment( $attachment_id ): void {
		foreach ( CPTDP_Page_Store::documents_using( (int) $attachment_id ) as $post_id ) {
			CPTDP_Page_Store::clear( $post_id, true );
		}

		CPTDP_Page_Store::clear_attachment_folders( (int) $attachment_id );
	}

	/**
	 * Make sure a document has valid page images, or that someone is on it.
	 *
	 * Idempotent: several hooks fire for one save and the work happens once.
	 *
	 * @param int  $post_id Document ID.
	 * @param bool $force   Regenerate even if the images are valid.
	 * @return string The resulting status.
	 */
	public static function ensure( int $post_id, bool $force = false ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'document' !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return CPTDP_Page_Store::STATUS_NONE;
		}

		$source = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $source ) {
			CPTDP_Page_Store::clear( $post_id, true );
			wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );

			return CPTDP_Page_Store::STATUS_NONE;
		}

		if ( ! $force ) {
			// Images already made from this PDF: just reconcile the status with the page total.
			$manifest = CPTDP_Page_Store::get_manifest( $post_id );

			if ( null !== $manifest && CPTDP_Page_Store::manifest_matches_source( $post_id, $manifest ) && CPTDP_Page_Store::files_exist( $manifest ) ) {
				return CPTDP_Page_Store::reconcile( $post_id );
			}

			// Someone is already on this exact PDF (pending, needs browser, or failed): leave it.
			$status = CPTDP_Page_Store::get_status( $post_id );

			if (
				in_array( $status, array( CPTDP_Page_Store::STATUS_PENDING, CPTDP_Page_Store::STATUS_NEEDS_BROWSER, CPTDP_Page_Store::STATUS_FAILED ), true )
				&& (string) get_post_meta( $post_id, CPTDP_Page_Store::META_SOURCE, true ) === $source['token']
			) {
				return $status;
			}
		}

		CPTDP_Page_Store::clear( $post_id );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );
		update_post_meta( $post_id, CPTDP_Page_Store::META_SOURCE, $source['token'] );

		// Presentations are converted by the server only (no browser can read them): the mode does
		// not apply, and there is no `needs_browser`. Without LibreOffice the editor is told to upload a PDF.
		if ( CPTDP_Page_Store::is_office_kind( $source['kind'] ) ) {
			if ( ! CPTDP_Pptx_Converter::is_available( $source['kind'] ) ) {
				// A host without LibreOffice that lets the native viewer draw this kind (.pptx, .xlsx) is working as chosen:
				// there are no page images and nothing failed.
				if ( cptdp_native_viewer_enabled( $source['kind'] ) ) {
					CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NONE );

					return CPTDP_Page_Store::STATUS_NONE;
				}

				CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_FAILED, CPTDP_Pptx_Converter::unavailable_reason( $source['kind'] ) );

				return CPTDP_Page_Store::STATUS_FAILED;
			}

			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_PENDING );
			self::schedule( $post_id );

			return CPTDP_Page_Store::STATUS_PENDING;
		}

		$mode = self::mode();

		if ( 'browser' === $mode ) {
			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER );

			return CPTDP_Page_Store::STATUS_NEEDS_BROWSER;
		}

		if ( ! CPTDP_Page_Converter::is_available() ) {
			if ( 'server' === $mode ) {
				CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_FAILED, CPTDP_Page_Converter::unavailable_reason() );

				return CPTDP_Page_Store::STATUS_FAILED;
			}

			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER );

			return CPTDP_Page_Store::STATUS_NEEDS_BROWSER;
		}

		CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_PENDING );
		self::schedule( $post_id );

		return CPTDP_Page_Store::STATUS_PENDING;
	}

	/**
	 * Schedule the server conversion for a document (deduped) and nudge cron.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function schedule( int $post_id ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK, array( $post_id ) );
		}

		/**
		 * Filters whether to ping wp-cron right after scheduling a conversion.
		 *
		 * @param bool $spawn Default true.
		 */
		if ( apply_filters( 'cptdp_pages_spawn_cron', true ) && function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Cron callback.
	 *
	 * @param int $post_id Document ID.
	 */
	public static function run_cron( $post_id ): void {
		self::convert_now( (int) $post_id );
	}

	/**
	 * Convert on the server now, under a lock, and record the outcome.
	 *
	 * In `auto` mode a failure hands the document to the admin's browser; in
	 * `server` mode it ends as failed. If the lock is held (another run is on this
	 * document) the job is tried again a minute later, a bounded number of times.
	 *
	 * @param int $post_id Document ID.
	 * @return string The resulting status.
	 */
	public static function convert_now( int $post_id ): string {
		$source = 'document' === get_post_type( $post_id ) ? CPTDP_Page_Store::get_source( $post_id ) : null;

		if ( null === $source ) {
			return CPTDP_Page_Store::STATUS_NONE;
		}

		$lock = 'cptdp_pages_lock_' . $post_id;

		if ( false !== get_transient( $lock ) ) {
			self::retry_later( $post_id );

			return CPTDP_Page_Store::get_status( $post_id );
		}

		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$result = CPTDP_Page_Converter::convert( $post_id );
		} finally {
			delete_transient( $lock );
		}

		// Another presentation is being converted (one LibreOffice at a time): try again shortly.
		if ( is_wp_error( $result ) && 'cptdp_pptx_busy' === $result->get_error_code() ) {
			self::retry_later( $post_id );

			return CPTDP_Page_Store::get_status( $post_id );
		}

		delete_transient( 'cptdp_pages_retries_' . $post_id );

		return self::record_conversion( $post_id, $result, $source['token'] );
	}

	/**
	 * Store the outcome of a server conversion, unless the PDF changed while it ran.
	 *
	 * @param int                $post_id Document ID.
	 * @param array|WP_Error     $result  Result of CPTDP_Page_Converter::convert().
	 * @param string|null        $token   Source token the conversion started from (when the result has none).
	 * @return string The resulting status.
	 */
	public static function record_conversion( int $post_id, $result, ?string $token = null ): string {
		if ( is_array( $result ) && isset( $result['source_token'] ) ) {
			$token = (string) $result['source_token'];
		}

		$current = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $current || null === $token || $current['token'] !== $token ) {
			// The PDF was replaced (or removed) during the conversion: these images belong to the old file.
			if ( is_array( $result ) && ! empty( $result['dir'] ) ) {
				CPTDP_Page_Store::delete_dir( CPTDP_Page_Store::base_dir() . '/' . $result['dir'] );
			}

			if ( null !== $current && CPTDP_Page_Store::STATUS_PENDING === CPTDP_Page_Store::get_status( $post_id ) ) {
				self::schedule( $post_id );
			}

			return CPTDP_Page_Store::get_status( $post_id );
		}

		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();

			// A presentation can only be converted by the server, so a failure is final (never the browser).
			if ( 'auto' === self::mode() && ! CPTDP_Page_Store::is_office_kind( $current['kind'] ) ) {
				CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER, $message );

				return CPTDP_Page_Store::STATUS_NEEDS_BROWSER;
			}

			CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_FAILED, $message );

			return CPTDP_Page_Store::STATUS_FAILED;
		}

		CPTDP_Page_Store::save_manifest( $post_id, $result );

		return CPTDP_Page_Store::get_status( $post_id );
	}

	/**
	 * A conversion found the lock held: try again in a minute, a few times. After that
	 * the document is handed over like any other failed conversion.
	 *
	 * @param int $post_id Document ID.
	 */
	private static function retry_later( int $post_id ): void {
		$key   = 'cptdp_pages_retries_' . $post_id;
		$tries = (int) get_transient( $key );

		if ( $tries >= self::MAX_LOCK_RETRIES ) {
			delete_transient( $key );
			$message = __( 'The server did not finish converting the file.', 'custom-post-types-dp' );
			'auto' === self::mode() && ! CPTDP_Page_Store::is_office_kind( CPTDP_Page_Store::source_kind( $post_id ) )
				? CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_NEEDS_BROWSER, $message )
				: CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_FAILED, $message );

			return;
		}

		set_transient( $key, $tries + 1, HOUR_IN_SECONDS );

		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Generate on the server regardless of the mode (WP-CLI and tools).
	 *
	 * @param int  $post_id Document ID.
	 * @param bool $force   Regenerate even if valid.
	 * @param bool $sync    Convert now instead of scheduling.
	 * @return string The resulting status.
	 */
	public static function generate_on_server( int $post_id, bool $force = false, bool $sync = false ): string {
		$source = CPTDP_Page_Store::get_source( $post_id );

		if ( null === $source ) {
			return CPTDP_Page_Store::STATUS_NONE;
		}

		if ( ! $force && CPTDP_Page_Store::is_valid( $post_id ) ) {
			return CPTDP_Page_Store::reconcile( $post_id );
		}

		CPTDP_Page_Store::clear( $post_id );
		update_post_meta( $post_id, CPTDP_Page_Store::META_SOURCE, $source['token'] );
		CPTDP_Page_Store::set_status( $post_id, CPTDP_Page_Store::STATUS_PENDING );

		if ( $sync ) {
			return self::convert_now( $post_id );
		}

		self::schedule( $post_id );

		return CPTDP_Page_Store::STATUS_PENDING;
	}

	/**
	 * Documents (any status except trash) that have a PDF.
	 *
	 * @return int[]
	 */
	public static function documents_with_pdf(): array {
		return array_values(
			array_filter(
				self::document_ids( array( 'publish', 'draft', 'pending', 'private', 'future' ) ),
				static function ( int $id ): bool {
					return null !== CPTDP_Page_Store::get_source( $id );
				}
			)
		);
	}

	/**
	 * IDs of documents with the given post status, oldest first.
	 *
	 * @param string|string[] $status Post status or statuses (`any` includes drafts, not trash).
	 * @return int[]
	 */
	public static function document_ids( $status = 'any' ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'document',
					'post_status'    => $status,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			)
		);
	}
}
