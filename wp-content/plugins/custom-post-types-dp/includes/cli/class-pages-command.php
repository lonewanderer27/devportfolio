<?php
/**
 * `wp cptdp pages` command.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate, clear and inspect the page images of document PDFs.
 */
class CPTDP_Pages_Command {

	/**
	 * Generate page images on the server (Imagick or Ghostscript; LibreOffice first for PPTX, PPT and ODP).
	 *
	 * Backfills documents saved before page images existed, or regenerates them.
	 * Documents whose images are already valid are skipped unless --force is given.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document ID.
	 *
	 * [--all]
	 * : Every document that has a PDF.
	 *
	 * [--force]
	 * : Regenerate even when the images are valid.
	 *
	 * [--sync]
	 * : Convert now instead of scheduling WP-Cron jobs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages generate --all --sync
	 *     wp cptdp pages generate 42 --force --sync
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function generate( array $args, array $assoc_args ): void {
		if ( ! CPTDP_Page_Converter::is_available() ) {
			WP_CLI::error( CPTDP_Page_Converter::unavailable_reason() );
		}

		if ( ! CPTDP_Pptx_Converter::is_available() ) {
			WP_CLI::warning( CPTDP_Pptx_Converter::unavailable_reason() . ' PowerPoint and OpenDocument presentations will end as failed.' );
		}

		if ( CPTDP_Pptx_Converter::is_available() && ! CPTDP_Pptx_Converter::is_available( CPTDP_Page_Store::KIND_XLSX ) ) {
			WP_CLI::warning( CPTDP_Xlsx_Converter::unavailable_reason() . ' Excel and OpenDocument spreadsheets will end as failed.' );
		}

		if ( CPTDP_Pptx_Converter::is_available() && ! CPTDP_Pptx_Converter::is_available( CPTDP_Page_Store::KIND_DOCX ) ) {
			WP_CLI::warning( CPTDP_Docx_Converter::unavailable_reason( CPTDP_Page_Store::KIND_DOCX ) . ' Word and OpenDocument text files will end as failed.' );
		}

		$force = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$sync  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'sync', false );
		$ids   = $this->target_ids( $args, $assoc_args );

		// Scheduling must not ping wp-cron once per document: do it once, after the loop.
		add_filter( 'cptdp_pages_spawn_cron', '__return_false', 99 );

		foreach ( $ids as $id ) {
			$status = CPTDP_Page_Scheduler::generate_on_server( $id, $force, $sync );
			$error  = CPTDP_Page_Store::get_error( $id );

			WP_CLI::log( sprintf( '%d: %s%s', $id, $status, '' !== $error ? ' (' . $error . ')' : '' ) );
		}

		remove_filter( 'cptdp_pages_spawn_cron', '__return_false', 99 );

		if ( ! $sync && $ids && apply_filters( 'cptdp_pages_spawn_cron', true ) && function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		WP_CLI::success( sprintf( '%d document(s) processed%s.', count( $ids ), $sync ? '' : ', conversions scheduled with WP-Cron' ) );
	}

	/**
	 * Delete generated page images and their status.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document ID.
	 *
	 * [--all]
	 * : Every document that has page images or a status.
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function clear( array $args, array $assoc_args ): void {
		$ids = $this->target_ids( $args, $assoc_args, true );

		foreach ( $ids as $id ) {
			CPTDP_Page_Store::clear( $id, true );
			wp_clear_scheduled_hook( CPTDP_Page_Scheduler::CRON_HOOK, array( $id ) );
		}

		WP_CLI::success( sprintf( 'Cleared %d document(s).', count( $ids ) ) );
	}

	/**
	 * Recompute the empty bottom margin of stored page images.
	 *
	 * Looks at the images already on disk (no conversion) and stores the new crop
	 * the viewer applies. Useful after changing the analysis thresholds.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document ID.
	 *
	 * [--all]
	 * : Every document that has valid page images.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages recrop --all
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function recrop( array $args, array $assoc_args ): void {
		$ids  = $this->target_ids( $args, $assoc_args );
		$rows = array();

		foreach ( $ids as $id ) {
			$crop = CPTDP_Page_Store::recrop( $id );

			if ( null === $crop ) {
				continue;
			}

			$rows[] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'crop'  => self::percent( $crop ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'title', 'crop' ) );
		WP_CLI::success( sprintf( '%d document(s) recropped.', count( $rows ) ) );
	}

	/**
	 * Show the page image status of documents.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document ID. Default: every document with a PDF.
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function status( array $args, array $assoc_args ): void {
		$ids  = $this->target_ids( $args, array_merge( $assoc_args, array( 'all' => true ) ) );
		$rows = array();

		foreach ( $ids as $id ) {
			$manifest = CPTDP_Page_Store::get_manifest( $id );

			$rows[] = array(
				'id'       => $id,
				'title'    => get_the_title( $id ),
				'kind'     => CPTDP_Page_Store::source_kind( $id ),
				'status'   => CPTDP_Page_Store::get_status( $id ),
				'valid'    => CPTDP_Page_Store::is_valid( $id ) ? 'yes' : 'no',
				'pages'    => null !== $manifest ? (int) $manifest['count'] : 0,
				'producer' => null !== $manifest ? (string) ( $manifest['producer'] ?? '' ) : '',
				'crop'     => null !== $manifest ? self::percent( CPTDP_Page_Store::stored_crop( $manifest ) ) : '',
				'error'    => CPTDP_Page_Store::get_error( $id ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'title', 'kind', 'status', 'valid', 'pages', 'producer', 'crop', 'error' ) );
	}

	/**
	 * A crop as a percentage for the tables.
	 *
	 * @param float $crop Crop 0..1.
	 */
	private static function percent( float $crop ): string {
		return number_format( $crop * 100, 1 ) . '%';
	}

	/**
	 * Resolve the documents a command acts on.
	 *
	 * @param string[]             $args        Positional args.
	 * @param array<string, mixed> $assoc_args  Flags.
	 * @param bool                 $any_status  For `clear --all`: include documents without a PDF but with images.
	 * @return int[]
	 */
	private function target_ids( array $args, array $assoc_args, bool $any_status = false ): array {
		if ( isset( $args[0] ) ) {
			$id = absint( $args[0] );

			if ( 'document' !== get_post_type( $id ) ) {
				WP_CLI::error( "{$id} is not a document." );
			}

			return array( $id );
		}

		if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
			WP_CLI::error( 'Give a document ID, or --all.' );
		}

		if ( $any_status ) {
			return CPTDP_Page_Scheduler::document_ids();
		}

		return CPTDP_Page_Scheduler::documents_with_pdf();
	}
}

WP_CLI::add_command( 'cptdp pages', 'CPTDP_Pages_Command' );
