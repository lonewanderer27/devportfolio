<?php
/**
 * Seed Software Tools from bin/software-tools.csv.
 *
 * Run: ddev wp eval-file wp-content/plugins/custom-post-types-dp/bin/seed-software-tools.php [dry-run]
 *
 * Creates one published `software_tool` per CSV row. A tool whose title already
 * exists (any status, including Trash) is skipped and never modified, so manual
 * edits and deletions win and the script is safe to re-run.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cptdp_seed_software_tools_read_csv' ) ) {
	/**
	 * Read and validate the seed CSV.
	 *
	 * @param string $csv_path Absolute path to the CSV.
	 * @return array<int, array<string, string>>|WP_Error Rows keyed by header, or an error.
	 */
	function cptdp_seed_software_tools_read_csv( string $csv_path ) {
		$header = array( 'order', 'title', 'site_url', 'description' );

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

		$rows = array();
		$line = 1;

		while ( false !== ( $fields = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			++$line;

			if ( array( null ) === $fields ) {
				continue; // Blank line.
			}

			if ( count( $fields ) !== count( $header ) || '' === trim( $fields[1] ) || ! ctype_digit( trim( $fields[0] ) ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'cptdp_seed_csv_row', "Invalid CSV row on line {$line}: needs a numeric order and a title." );
			}

			$rows[] = array_combine( $header, array_map( 'trim', $fields ) );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $rows;
	}
}

if ( ! function_exists( 'cptdp_seed_software_tools' ) ) {
	/**
	 * Create the Software Tools listed in the CSV.
	 *
	 * @param bool   $dry_run  When true, report what would be created and write nothing.
	 * @param string $csv_path CSV path; defaults to bin/software-tools.csv.
	 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
	 */
	function cptdp_seed_software_tools( bool $dry_run = false, string $csv_path = '' ) {
		if ( '' === $csv_path ) {
			$csv_path = __DIR__ . '/software-tools.csv';
		}

		if ( ! post_type_exists( 'software_tool' ) ) {
			return new WP_Error( 'cptdp_seed_no_post_type', 'The software_tool post type is not registered.' );
		}

		$rows = cptdp_seed_software_tools_read_csv( $csv_path );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$result = array(
			'created' => array(),
			'skipped' => array(),
		);

		foreach ( $rows as $row ) {
			$existing = get_posts(
				array(
					'post_type'      => 'software_tool',
					'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
					'title'          => $row['title'],
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $existing ) ) {
				$result['skipped'][] = $row['title'];
				continue;
			}

			if ( ! $dry_run ) {
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'software_tool',
						'post_status'  => 'publish',
						'post_title'   => $row['title'],
						'post_content' => $row['description'],
						'menu_order'   => (int) $row['order'],
						'meta_input'   => array( 'cptdp_site_url' => $row['site_url'] ),
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
			}

			$result['created'][] = $row['title'];
		}

		return $result;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$cptdp_dry_run = isset( $args[0] ) && 'dry-run' === $args[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$cptdp_seeded  = cptdp_seed_software_tools( $cptdp_dry_run );

	if ( is_wp_error( $cptdp_seeded ) ) {
		WP_CLI::error( $cptdp_seeded->get_error_message() );
	}

	foreach ( $cptdp_seeded['created'] as $cptdp_title ) {
		WP_CLI::log( ( $cptdp_dry_run ? 'would create ' : 'created      ' ) . $cptdp_title );
	}
	foreach ( $cptdp_seeded['skipped'] as $cptdp_title ) {
		WP_CLI::log( 'skipped      ' . $cptdp_title );
	}

	WP_CLI::success(
		sprintf(
			'%1$d %2$s, %3$d skipped%4$s',
			count( $cptdp_seeded['created'] ),
			$cptdp_dry_run ? 'would be created' : 'created',
			count( $cptdp_seeded['skipped'] ),
			$cptdp_dry_run ? ' (dry run, nothing written)' : ''
		)
	);
}
