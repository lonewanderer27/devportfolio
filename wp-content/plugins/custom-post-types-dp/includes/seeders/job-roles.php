<?php
/**
 * Job Roles seeder.
 *
 * Creates one published `job_role` per row of data/seeders/job-roles.csv; the row with highlight 1 is the top role.
 * Run: wp cptdp seed job-roles [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_job_roles( array $seeders ): array {
	$seeders['job-roles'] = array(
		'label'     => esc_html__( 'Job Roles', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_job_roles',
		'csv'       => CPTDP_PATH . 'data/seeders/job-roles.csv',
		'post_type' => 'job_role',
		'columns'   => array( 'order', 'title', 'highlight' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_job_roles' );

/**
 * Create the Job Roles listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_job_roles( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'job_role' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The job_role post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'highlight' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$highlights = 0;
	foreach ( $rows as $index => $row ) {
		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $index + 1 ) );
		}

		if ( ! in_array( $row['highlight'], array( '0', '1' ), true ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Highlight must be 0 or 1 for "%1$s" (row %2$d).', $row['title'], $index + 1 ) );
		}

		$highlights += (int) $row['highlight'];
	}

	if ( $highlights > 1 ) {
		return new WP_Error( 'cptdp_seed_csv_row', 'Only one job role can be the highlight.' );
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'job_role', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'job_role',
					'post_status' => 'publish',
					'post_title'  => $row['title'],
					'menu_order'  => (int) $row['order'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( '1' === $row['highlight'] ) {
				update_post_meta( $post_id, 'cptdp_highlight', 1 );
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
