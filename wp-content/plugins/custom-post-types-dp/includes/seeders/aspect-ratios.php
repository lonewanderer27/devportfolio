<?php
/**
 * Aspect Ratios seeder.
 *
 * Creates every `aspect_ratio` term of data/seeders/aspect-ratios.csv that
 * does not exist yet. Terms have no Trash, so a term deleted in wp-admin is
 * recreated by an explicit run (the point: restore on demand). The automatic
 * once-per-install seeding in includes/post-types/aspect-ratio.php is
 * separate and untouched; this seeder neither reads nor writes its option.
 * Run: wp cptdp seed aspect-ratios [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_aspect_ratios( array $seeders ): array {
	$seeders['aspect-ratios'] = array(
		'label'    => esc_html__( 'Aspect Ratios', 'custom-post-types-dp' ),
		'callback' => 'cptdp_seed_aspect_ratio_terms',
		'csv'      => CPTDP_PATH . 'data/seeders/aspect-ratios.csv',
		'taxonomy' => 'aspect_ratio',
		'columns'  => array( 'title', 'slug' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_aspect_ratios' );

/**
 * Create the Aspect Ratio terms listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_aspect_ratio_terms( bool $dry_run, string $csv_path ) {
	if ( ! taxonomy_exists( 'aspect_ratio' ) ) {
		return new WP_Error( 'cptdp_seed_no_taxonomy', 'The aspect_ratio taxonomy is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'title', 'slug' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$allowed = cptdp_aspect_ratio_terms();
	$seen    = array();

	foreach ( $rows as $index => $row ) {
		$line = $index + 1;

		if ( ! in_array( $row['title'], $allowed, true ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Unknown aspect ratio "%1$s" (row %2$d).', $row['title'], $line ) );
		}

		if ( $row['slug'] !== $row['title'] ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Slug "%1$s" must equal title "%2$s" (row %3$d).', $row['slug'], $row['title'], $line ) );
		}

		if ( isset( $seen[ $row['title'] ] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Duplicate title "%1$s" (row %2$d).', $row['title'], $line ) );
		}

		$seen[ $row['title'] ] = true;
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( term_exists( $row['slug'], 'aspect_ratio' ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$inserted = wp_insert_term( $row['title'], 'aspect_ratio', array( 'slug' => $row['slug'] ) );

			if ( is_wp_error( $inserted ) ) {
				return $inserted;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
