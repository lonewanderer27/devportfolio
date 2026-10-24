<?php
/**
 * Seeder registry and shared helpers.
 *
 * Seeders live in includes/seeders/ and register themselves through the
 * `cptdp_seeders` filter. Their data lives in data/seeders/<name>.csv. Loaded
 * on every request (the helpers are pure functions) so tests can use them; the
 * `wp cptdp seed` command itself is loaded only under WP-CLI.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered seeders, keyed by name, in registration order.
 *
 * Each entry: `label` (string), `callback` (callable( bool $dry_run, string $csv_path )),
 * `csv` (absolute path), `post_type` (string) or, for term seeders, `taxonomy` (string),
 * `columns` (string[], the exact CSV header).
 * A callback returns `array{created: string[], skipped: string[]}` or a WP_Error.
 *
 * @return array<string, array<string, mixed>>
 */
function cptdp_get_seeders(): array {
	$seeders = apply_filters( 'cptdp_seeders', array() );

	return is_array( $seeders ) ? $seeders : array();
}

/**
 * Run one registered seeder.
 *
 * @param string $name    Seeder name.
 * @param bool   $dry_run Report only, write nothing.
 * @return array{created: string[], skipped: string[]}|WP_Error
 */
function cptdp_run_seeder( string $name, bool $dry_run = false ) {
	$seeders = cptdp_get_seeders();

	if ( ! isset( $seeders[ $name ] ) ) {
		return new WP_Error( 'cptdp_seed_unknown', "Unknown seeder: {$name}" );
	}

	return call_user_func( $seeders[ $name ]['callback'], $dry_run, $seeders[ $name ]['csv'] );
}

/**
 * Read and validate a seed CSV.
 *
 * The whole file is validated before any row is returned, so a caller never
 * writes anything from a bad file. Every row needs the header's column count
 * and a non-empty `title`.
 *
 * @param string   $csv_path Absolute path to the CSV.
 * @param string[] $header   Exact expected header row.
 * @return array<int, array<string, string>>|WP_Error Rows keyed by header, or an error.
 */
function cptdp_seed_read_csv( string $csv_path, array $header ) {
	if ( ! is_readable( $csv_path ) ) {
		return new WP_Error( 'cptdp_seed_csv_missing', "CSV not found or unreadable: {$csv_path}" );
	}

	$handle = fopen( $csv_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $handle ) {
		return new WP_Error( 'cptdp_seed_csv_missing', "CSV could not be opened: {$csv_path}" );
	}

	$first = fgetcsv( $handle, 0, ',', '"', '' );
	if ( $first !== $header ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'cptdp_seed_csv_header', 'CSV header must be exactly: ' . implode( ',', $header ) );
	}

	$title_index = array_search( 'title', $header, true );
	$rows        = array();
	$line        = 1;

	while ( false !== ( $fields = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		++$line;

		if ( array( null ) === $fields ) {
			continue; // Blank line.
		}

		if ( count( $fields ) !== count( $header ) || ( false !== $title_index && '' === trim( $fields[ $title_index ] ) ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'cptdp_seed_csv_row', "Invalid CSV row on line {$line}: wrong column count or empty title." );
		}

		$rows[] = array_combine( $header, array_map( 'trim', $fields ) );
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	return $rows;
}

/**
 * Whether a post of this type and exact title exists in any status, Trash
 * included. Seeders skip such titles so manual edits and deletions win.
 *
 * @param string $post_type Post type.
 * @param string $title     Exact title.
 */
function cptdp_seed_title_exists( string $post_type, string $title ): bool {
	$found = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'title'          => $title,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return ! empty( $found );
}
