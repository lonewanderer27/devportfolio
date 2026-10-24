<?php
/**
 * Services seeder.
 *
 * Creates one published `service` per row of data/seeders/services.csv, with
 * the parent/child hierarchy. `parent` is the exact title of an earlier
 * top-level row, empty for a top-level service.
 * Run: wp cptdp seed services [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_services( array $seeders ): array {
	$seeders['services'] = array(
		'label'     => esc_html__( 'Services', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_services',
		'csv'       => CPTDP_PATH . 'data/seeders/services.csv',
		'post_type' => 'service',
		'columns'   => array( 'order', 'title', 'parent', 'content' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_services' );

/**
 * ID of the top-level service with this exact title, in any status, Trash included.
 *
 * @param string $title Exact title.
 * @return int ID, or 0 when none exists.
 */
function cptdp_seed_service_id_by_title( string $title ): int {
	$found = get_posts(
		array(
			'post_type'      => 'service',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'post_parent'    => 0,
			'title'          => $title,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return empty( $found ) ? 0 : (int) $found[0];
}

/**
 * Create the Services listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_services( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'service' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The service post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'parent', 'content' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	// Title => parent title of each row seen so far, to validate hierarchy.
	$seen = array();

	foreach ( $rows as $index => $row ) {
		$line = $index + 1;

		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $line ) );
		}

		if ( isset( $seen[ $row['title'] ] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Duplicate title "%1$s" (row %2$d).', $row['title'], $line ) );
		}

		if ( '' !== $row['parent'] ) {
			if ( ! isset( $seen[ $row['parent'] ] ) ) {
				return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Parent "%1$s" of "%2$s" must be an earlier row (row %3$d).', $row['parent'], $row['title'], $line ) );
			}

			if ( '' !== $seen[ $row['parent'] ] ) {
				return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Parent "%1$s" of "%2$s" is not top-level; only two levels are supported (row %3$d).', $row['parent'], $row['title'], $line ) );
			}
		}

		$seen[ $row['title'] ] = $row['parent'];
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	// Title => ID of each top-level service created or found, for children.
	$parent_ids = array();

	foreach ( $rows as $row ) {
		$exists = cptdp_seed_title_exists( 'service', $row['title'] );

		if ( '' === $row['parent'] && ! $dry_run ) {
			$parent_ids[ $row['title'] ] = $exists ? cptdp_seed_service_id_by_title( $row['title'] ) : 0;
		}

		if ( $exists ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$parent_id = 0;

			if ( '' !== $row['parent'] ) {
				$parent_id = $parent_ids[ $row['parent'] ] ?? 0;

				if ( 0 === $parent_id ) {
					return new WP_Error( 'cptdp_seed_parent_missing', sprintf( 'Parent "%1$s" of "%2$s" does not exist.', $row['parent'], $row['title'] ) );
				}
			}

			// Without a privileged user kses would store "&" in a title as "&amp;",
			// so the title would no longer match its CSV row on the next run.
			$kses_active = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
			kses_remove_filters();

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'service',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_content' => $row['content'],
					'post_parent'  => $parent_id,
					'menu_order'   => (int) $row['order'],
				),
				true
			);

			if ( $kses_active ) {
				kses_init_filters();
			}

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( '' === $row['parent'] ) {
				$parent_ids[ $row['title'] ] = (int) $post_id;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
