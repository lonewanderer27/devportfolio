<?php
/**
 * `wp cptdp pages` command.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate, clear and inspect the page images of documents and collaterals.
 *
 * Every subcommand takes one post ID (a document or a collateral), or `--all`, which
 * `--post_type=document|collateral` narrows to one type.
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
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has a file.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
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

		WP_CLI::success( sprintf( '%d post(s) processed%s.', count( $ids ), $sync ? '' : ', conversions scheduled with WP-Cron' ) );
	}

	/**
	 * Delete generated page images and their status.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has page images or a status.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
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

		WP_CLI::success( sprintf( 'Cleared %d post(s).', count( $ids ) ) );
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
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has valid page images.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
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
		WP_CLI::success( sprintf( '%d post(s) recropped.', count( $rows ) ) );
	}

	/**
	 * Make the small page thumbnails of stored sets, without converting again.
	 *
	 * Resizes the page images already on disk (no LibreOffice, Ghostscript or browser), one document
	 * at a time. Documents that already have thumbnails are skipped unless --force is given.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has valid page images.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
	 *
	 * [--force]
	 * : Make them again even when the set has them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages thumbs --all
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function thumbs( array $args, array $assoc_args ): void {
		if ( ! CPTDP_Page_Thumbs::is_available() ) {
			WP_CLI::error( 'Imagick is needed to make thumbnails.' );
		}

		$force = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$ids   = $this->target_ids( $args, $assoc_args );

		foreach ( $ids as $id ) {
			WP_CLI::log( sprintf( '%d: %s', $id, CPTDP_Page_Store::make_thumbs( $id, $force ) ) );
		}

		WP_CLI::success( sprintf( '%d post(s) processed.', count( $ids ) ) );
	}

	/**
	 * Read page names again from the stored files, without converting.
	 *
	 * PDF outlines and Excel sheet names. Word documents need `generate <id> --force` once (their converted
	 * PDF is not kept); presentations have no names.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has valid page images.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages names --all
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function names( array $args, array $assoc_args ): void {
		$ids = $this->target_ids( $args, $assoc_args );

		foreach ( $ids as $id ) {
			WP_CLI::log( sprintf( '%d: %s', $id, CPTDP_Page_Store::refresh_names( $id ) ) );
		}

		WP_CLI::success( sprintf( '%d post(s) processed.', count( $ids ) ) );
	}

	/**
	 * Show the page image status of documents.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document or collateral ID. Default: every document and collateral with a file.
	 *
	 * [--post_type=<type>]
	 * : Only `document` or `collateral`.
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
				'type'     => (string) get_post_type( $id ),
				'title'    => get_the_title( $id ),
				'kind'     => CPTDP_Page_Store::source_kind( $id ),
				'status'   => CPTDP_Page_Store::get_status( $id ),
				'valid'    => CPTDP_Page_Store::is_valid( $id ) ? 'yes' : 'no',
				'pages'    => null !== $manifest ? (int) $manifest['count'] : 0,
				'producer' => null !== $manifest ? (string) ( $manifest['producer'] ?? '' ) : '',
				'crop'     => null !== $manifest ? self::percent( CPTDP_Page_Store::stored_crop( $manifest ) ) : '',
				'names'    => null !== $manifest ? count( array_filter( CPTDP_Page_Outline::expand( CPTDP_Page_Outline::sanitize_ranges( $manifest['page_names'] ?? array(), (int) $manifest['count'] ), (int) $manifest['count'] ) ) ) : 0,
				'thumbs'   => null !== $manifest && (int) ( $manifest['thumb_width'] ?? 0 ) > 0 ? (int) $manifest['count'] : 0,
				'featured' => '' !== CPTDP_Page_Featured::owner( $id ) ? CPTDP_Page_Featured::owner( $id ) : '-',
				'error'    => CPTDP_Page_Store::get_error( $id ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'type', 'title', 'kind', 'status', 'valid', 'pages', 'producer', 'crop', 'names', 'thumbs', 'featured', 'error' ) );
	}

	/**
	 * Use page 1 as the featured image of posts that have none (sets made before this existed).
	 *
	 * Never touches a featured image an editor chose. Posts whose generated image was made from the current page images are skipped unless --force is given.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Document or collateral ID.
	 *
	 * [--all]
	 * : Every document and collateral that has valid page images.
	 *
	 * [--post_type=<type>]
	 * : With --all, only `document` or `collateral`.
	 *
	 * [--force]
	 * : Make the generated image again.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp pages featured --all --post_type=document
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function featured( array $args, array $assoc_args ): void {
		if ( ! CPTDP_Page_Featured::enabled() ) {
			WP_CLI::error( 'Settings > Documents > Page images > Featured image is off.' );
		}

		$force = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$ids   = $this->target_ids( $args, $assoc_args );

		foreach ( $ids as $id ) {
			$outcome = CPTDP_Page_Featured::sync( $id, $force );
			$error   = 'failed' === $outcome ? CPTDP_Page_Store::get_error( $id ) : '';

			WP_CLI::log( sprintf( '%d: %s%s', $id, $outcome, '' !== $error ? ' (' . $error . ')' : '' ) );
		}

		WP_CLI::success( sprintf( '%d post(s) processed.', count( $ids ) ) );
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
	 * Resolve the posts a command acts on.
	 *
	 * @param string[]             $args        Positional args.
	 * @param array<string, mixed> $assoc_args  Flags.
	 * @param bool                 $any_status  For `clear --all`: include posts without a file but with images.
	 * @return int[]
	 */
	private function target_ids( array $args, array $assoc_args, bool $any_status = false ): array {
		if ( isset( $args[0] ) ) {
			$id = absint( $args[0] );

			if ( ! CPTDP_Page_Store::supports( $id ) ) {
				WP_CLI::error( "{$id} has no page images (not a document or collateral)." );
			}

			return array( $id );
		}

		if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
			WP_CLI::error( 'Give a document or collateral ID, or --all.' );
		}

		$types = array();
		$type  = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', '' );

		if ( '' !== $type ) {
			if ( ! in_array( $type, CPTDP_Page_Store::post_types(), true ) ) {
				WP_CLI::error( sprintf( 'Unknown --post_type "%s" (use %s).', $type, implode( ' or ', CPTDP_Page_Store::post_types() ) ) );
			}

			$types = array( $type );
		}

		return $any_status ? CPTDP_Page_Scheduler::post_ids( 'any', $types ) : CPTDP_Page_Scheduler::posts_with_source( $types );
	}
}

WP_CLI::add_command( 'cptdp pages', 'CPTDP_Pages_Command' );
