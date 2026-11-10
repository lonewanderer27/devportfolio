<?php
/**
 * Collaterals seeder.
 *
 * Creates one published `collateral` per row of data/seeders/collaterals.csv,
 * with its content (the description; the type has no excerpt), one Collateral Category term, optional
 * Software Tools, paper size, page count, statistic rows and highlight flag.
 * The category terms and the software tools must exist (see the
 * `collateral-categories` and `software-tools` seeders). Uploaded files
 * (`cptdp_collateral_file`) and thumbnails are not seeded.
 * Run: wp cptdp seed collaterals [--dry-run]
 *
 * The file name starts with "z-" so the loader requires it, and `--all` runs it,
 * after `software-tools` (seeders run in file order).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_collaterals( array $seeders ): array {
	$seeders['collaterals'] = array(
		'label'     => esc_html__( 'Collaterals', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_collaterals',
		'csv'       => CPTDP_PATH . 'data/seeders/collaterals.csv',
		'post_type' => 'collateral',
		'columns'   => array( 'order', 'title', 'description', 'category', 'software_tools', 'paper_size', 'total_pages', 'statistics', 'highlight' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_collaterals' );

/**
 * Parse a statistics cell: `label:value|label:value`, split on the first colon.
 *
 * @param string $cell CSV cell.
 * @return array<int, array{label: string, value: string}>|null Rows, or null when a pair is malformed.
 */
function cptdp_seed_collaterals_parse_statistics( string $cell ): ?array {
	$rows = array();

	foreach ( array_filter( explode( '|', $cell ), 'strlen' ) as $pair ) {
		$parts = explode( ':', $pair, 2 );
		if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) ) {
			return null;
		}

		$rows[] = array(
			'label' => trim( $parts[0] ),
			'value' => trim( $parts[1] ),
		);
	}

	return $rows;
}

/**
 * Create the Collaterals listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_collaterals( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'collateral' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The collateral post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'description', 'category', 'software_tools', 'paper_size', 'total_pages', 'statistics', 'highlight' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$statistics = array();
	foreach ( $rows as $index => $row ) {
		$errors = array();

		if ( ! ctype_digit( $row['order'] ) ) {
			$errors[] = sprintf( 'Invalid order "%s"', $row['order'] );
		}

		if ( ! ctype_digit( $row['total_pages'] ) ) {
			$errors[] = sprintf( 'Invalid total_pages "%s"', $row['total_pages'] );
		}

		if ( ! in_array( $row['highlight'], array( '', '0', '1' ), true ) ) {
			$errors[] = sprintf( 'Invalid highlight "%s"', $row['highlight'] );
		}

		if ( '' === $row['category'] || sanitize_title( $row['category'] ) !== $row['category'] ) {
			$errors[] = sprintf( 'Invalid category "%s"', $row['category'] );
		}

		$statistics[ $index ] = cptdp_seed_collaterals_parse_statistics( $row['statistics'] );
		if ( null === $statistics[ $index ] ) {
			$errors[] = sprintf( 'Invalid statistics "%s"', $row['statistics'] );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( '%1$s for "%2$s" (row %3$d).', implode( '; ', $errors ), $row['title'], $index + 1 ) );
		}
	}

	// Slug => term ID. A missing term must not be created by the assignment below.
	$category_ids = array();
	foreach ( array_unique( wp_list_pluck( $rows, 'category' ) ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'collateral_category' );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'cptdp_seed_term_missing', sprintf( 'Collateral Category term "%s" does not exist. Run: wp cptdp seed collateral-categories', $slug ) );
		}

		$category_ids[ $slug ] = (int) $term->term_id;
	}

	// Software tool title => post ID, for every title used.
	$tool_ids = array();
	foreach ( $rows as $row ) {
		foreach ( array_filter( explode( '|', $row['software_tools'] ) ) as $tool_title ) {
			$tool_title = trim( $tool_title );
			if ( isset( $tool_ids[ $tool_title ] ) ) {
				continue;
			}

			$found = get_posts(
				array(
					'post_type'      => 'software_tool',
					'post_status'    => 'publish',
					'title'          => $tool_title,
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);

			if ( empty( $found ) ) {
				return new WP_Error( 'cptdp_seed_tool_missing', sprintf( 'Software tool "%s" does not exist. Run: wp cptdp seed software-tools', $tool_title ) );
			}

			$tool_ids[ $tool_title ] = (int) $found[0];
		}
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $index => $row ) {
		if ( cptdp_seed_title_exists( 'collateral', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$tools = array();
			foreach ( array_filter( explode( '|', $row['software_tools'] ) ) as $tool_title ) {
				$tools[] = $tool_ids[ trim( $tool_title ) ];
			}

			$meta = array(
				'cptdp_paper_size'  => $row['paper_size'],
				'cptdp_total_pages' => (int) $row['total_pages'],
				'cptdp_highlight'   => '1' === $row['highlight'],
			);
			if ( ! empty( $tools ) ) {
				$meta['cptdp_software_tools'] = $tools;
			}
			if ( ! empty( $statistics[ $index ] ) ) {
				$meta['cptdp_post_statistics'] = $statistics[ $index ];
			}

			// Without a privileged user kses would store "&" in a title as "&amp;",
			// so the title would no longer match its CSV row on the next run.
			$kses_active = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
			kses_remove_filters();

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'collateral',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_content' => $row['description'],
					'menu_order'   => (int) $row['order'],
					'meta_input'   => $meta,
				),
				true
			);

			if ( $kses_active ) {
				kses_init_filters();
			}

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$set = wp_set_object_terms( $post_id, array( $category_ids[ $row['category'] ] ), 'collateral_category' );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
