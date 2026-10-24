<?php
/**
 * Statistics seeder.
 *
 * Creates one published `statistic` per row of data/seeders/statistics.csv.
 * Run: wp cptdp seed statistics [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_statistics( array $seeders ): array {
	$seeders['statistics'] = array(
		'label'     => esc_html__( 'Statistics', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_statistics',
		'csv'       => CPTDP_PATH . 'data/seeders/statistics.csv',
		'post_type' => 'statistic',
		'columns'   => array( 'order', 'title', 'value' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_statistics' );

/**
 * Create the Statistics listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_statistics( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'statistic' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The statistic post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'value' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	foreach ( $rows as $index => $row ) {
		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $index + 1 ) );
		}

		if ( '' === $row['value'] ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Empty value for "%1$s" (row %2$d).', $row['title'], $index + 1 ) );
		}
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'statistic', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'statistic',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_content' => $row['value'],
					'menu_order'   => (int) $row['order'],
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
