<?php
/**
 * Software Tools seeder.
 *
 * Creates one published `software_tool` per row of data/seeders/software-tools.csv.
 * Run: wp cptdp seed software-tools [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_software_tools( array $seeders ): array {
	$seeders['software-tools'] = array(
		'label'     => esc_html__( 'Software Tools', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_software_tools',
		'csv'       => CPTDP_PATH . 'data/seeders/software-tools.csv',
		'post_type' => 'software_tool',
		'columns'   => array( 'order', 'title', 'site_url', 'description' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_software_tools' );

/**
 * Create the Software Tools listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_software_tools( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'software_tool' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The software_tool post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'site_url', 'description' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	foreach ( $rows as $index => $row ) {
		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $index + 1 ) );
		}
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'software_tool', $row['title'] ) ) {
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
