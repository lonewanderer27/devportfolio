<?php
/**
 * Document Categories seeder.
 *
 * Creates every `document_category` term of data/seeders/document-categories.csv
 * that does not exist yet. An existing term, matched by slug or by name, is
 * skipped and never modified except to fill its empty singular name. Terms
 * have no Trash, so an explicit run recreates a term deleted in wp-admin.
 * Run: wp cptdp seed document-categories [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_document_categories( array $seeders ): array {
	$seeders['document-categories'] = array(
		'label'    => esc_html__( 'Document Categories', 'custom-post-types-dp' ),
		'callback' => 'cptdp_seed_document_category_terms',
		'csv'      => CPTDP_PATH . 'data/seeders/document-categories.csv',
		'taxonomy' => 'document_category',
		'columns'  => array( 'title', 'slug', 'singular' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_document_categories' );

/**
 * Create the Document Category terms listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[], updated: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_document_category_terms( bool $dry_run, string $csv_path ) {
	if ( ! taxonomy_exists( 'document_category' ) ) {
		return new WP_Error( 'cptdp_seed_no_taxonomy', 'The document_category taxonomy is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'title', 'slug', 'singular' ) );
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
		'updated' => array(),
	);

	foreach ( $rows as $row ) {
		$term = get_term_by( 'slug', $row['slug'], 'document_category' );
		if ( ! $term ) {
			$term = get_term_by( 'name', $row['title'], 'document_category' );
		}

		if ( $term ) {
			$current = trim( (string) get_term_meta( $term->term_id, 'cptdp_singular_name', true ) );
			if ( '' === $current ) {
				if ( ! $dry_run ) {
					update_term_meta( $term->term_id, 'cptdp_singular_name', $row['singular'] );
				}
				$result['updated'][] = $row['title'];
			} else {
				$result['skipped'][] = $row['title'];
			}
			continue;
		}

		if ( ! $dry_run ) {
			$inserted = wp_insert_term( $row['title'], 'document_category', array( 'slug' => $row['slug'] ) );

			if ( is_wp_error( $inserted ) ) {
				return $inserted;
			}
			update_term_meta( $inserted['term_id'], 'cptdp_singular_name', $row['singular'] );
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
