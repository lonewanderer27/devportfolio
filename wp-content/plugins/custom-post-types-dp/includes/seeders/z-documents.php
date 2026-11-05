<?php
/**
 * Documents seeder.
 *
 * Creates one published `document` per row of data/seeders/documents.csv, with
 * its excerpt (the description), file type, page count, author, one
 * Document Category term and optional Software Tools. The category terms and the
 * software tools must exist (see the `document-categories` and `software-tools`
 * seeders). Uploaded files (`cptdp_document_file`) are not seeded.
 * Run: wp cptdp seed documents [--dry-run]
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
function cptdp_register_seeder_documents( array $seeders ): array {
	$seeders['documents'] = array(
		'label'     => esc_html__( 'Documents', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_documents',
		'csv'       => CPTDP_PATH . 'data/seeders/documents.csv',
		'post_type' => 'document',
		'columns'   => array( 'order', 'title', 'description', 'category', 'software_tools', 'filetype', 'total_pages', 'author' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_documents' );

/**
 * Create the Documents listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_documents( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'document' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The document post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'description', 'category', 'software_tools', 'filetype', 'total_pages', 'author' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	foreach ( $rows as $index => $row ) {
		$errors = array();

		if ( ! ctype_digit( $row['order'] ) ) {
			$errors[] = sprintf( 'Invalid order "%s"', $row['order'] );
		}

		if ( ! ctype_digit( $row['total_pages'] ) ) {
			$errors[] = sprintf( 'Invalid total_pages "%s"', $row['total_pages'] );
		}

		if ( '' === $row['category'] || sanitize_title( $row['category'] ) !== $row['category'] ) {
			$errors[] = sprintf( 'Invalid category "%s"', $row['category'] );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( '%1$s for "%2$s" (row %3$d).', implode( '; ', $errors ), $row['title'], $index + 1 ) );
		}
	}

	// Slug => term ID. A missing term must not be created by the assignment below.
	$category_ids = array();
	foreach ( array_unique( wp_list_pluck( $rows, 'category' ) ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'document_category' );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'cptdp_seed_term_missing', sprintf( 'Document Category term "%s" does not exist.', $slug ) );
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

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'document', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			$tools = array();
			foreach ( array_filter( explode( '|', $row['software_tools'] ) ) as $tool_title ) {
				$tools[] = $tool_ids[ trim( $tool_title ) ];
			}

			$meta = array(
				'cptdp_filetype'    => $row['filetype'],
				'cptdp_total_pages' => (int) $row['total_pages'],
				'cptdp_author'      => $row['author'],
			);
			if ( ! empty( $tools ) ) {
				$meta['cptdp_software_tools'] = $tools;
			}

			// Without a privileged user kses would store "&" in a title as "&amp;",
			// so the title would no longer match its CSV row on the next run.
			$kses_active = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
			kses_remove_filters();

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'document',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_excerpt' => $row['description'],
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

			$set = wp_set_object_terms( $post_id, array( $category_ids[ $row['category'] ] ), 'document_category' );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
