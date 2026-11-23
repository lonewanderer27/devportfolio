<?php
/**
 * Testimonials seeder.
 *
 * Creates one published `testimonial` per row of data/seeders/testimonials.csv.
 * The rows are invented SAMPLE placeholders (each quote says "Sample testimonial."):
 * delete them, or replace them with real quotes, before launch. Never run on production.
 * Run: wp cptdp seed testimonials [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_testimonials( array $seeders ): array {
	$seeders['testimonials'] = array(
		'label'     => esc_html__( 'Testimonials (samples)', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_testimonials',
		'csv'       => CPTDP_PATH . 'data/seeders/testimonials.csv',
		'post_type' => 'testimonial',
		'columns'   => array( 'order', 'title', 'company', 'quote' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_testimonials' );

/**
 * Create the Testimonials listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_testimonials( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'testimonial' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The testimonial post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'company', 'quote' ) );
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
		if ( cptdp_seed_title_exists( 'testimonial', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'testimonial',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_content' => $row['quote'],
					'menu_order'   => (int) $row['order'],
					'meta_input'   => array( 'cptdp_company' => $row['company'] ),
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
