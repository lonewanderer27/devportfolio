<?php
/**
 * Collateral Categories seeder.
 *
 * Creates every `collateral_category` term of data/seeders/collateral-categories.csv
 * that does not exist yet. Existing terms, matched by slug or name, are skipped
 * and never modified. Terms have no Trash, so an explicit run recreates a term
 * deleted in wp-admin.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 * @return array<string, array<string, mixed>>
 */
function cptdp_register_seeder_collateral_categories( array $seeders ): array {
	$seeders['collateral-categories'] = array(
		'label'    => esc_html__( 'Collateral Categories', 'custom-post-types-dp' ),
		'callback' => 'cptdp_seed_collateral_category_terms',
		'csv'      => CPTDP_PATH . 'data/seeders/collateral-categories.csv',
		'taxonomy' => 'collateral_category',
		'columns'  => array( 'title', 'slug' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_collateral_categories' );

/**
 * Create the Collateral Category terms listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_collateral_category_terms( bool $dry_run, string $csv_path ) {
	if ( ! taxonomy_exists( 'collateral_category' ) ) {
		return new WP_Error( 'cptdp_seed_no_taxonomy', 'The collateral_category taxonomy is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'title', 'slug' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$titles = array();
	$slugs  = array();

	foreach ( $rows as $index => $row ) {
		$line = $index + 1;

		if ( '' === $row['slug'] || sanitize_title( $row['slug'] ) !== $row['slug'] ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid slug "%1$s" for "%2$s" (row %3$d).', $row['slug'], $row['title'], $line ) );
		}

		if ( isset( $titles[ $row['title'] ] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Duplicate title "%1$s" (row %2$d).', $row['title'], $line ) );
		}

		if ( isset( $slugs[ $row['slug'] ] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Duplicate slug "%1$s" (row %2$d).', $row['slug'], $line ) );
		}

		$titles[ $row['title'] ] = true;
		$slugs[ $row['slug'] ]   = true;
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( get_term_by( 'slug', $row['slug'], 'collateral_category' ) || get_term_by( 'name', $row['title'], 'collateral_category' ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$inserted = wp_insert_term( $row['title'], 'collateral_category', array( 'slug' => $row['slug'] ) );

			if ( is_wp_error( $inserted ) ) {
				return $inserted;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
