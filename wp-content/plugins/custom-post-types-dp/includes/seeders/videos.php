<?php
/**
 * Videos seeder.
 *
 * Creates one published `video` per row of data/seeders/videos.csv, with embed
 * and post URLs, length, highlight flag and an Aspect Ratio term. Uploaded
 * files (`cptdp_video_file`) are not seeded.
 * Run: wp cptdp seed videos [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_videos( array $seeders ): array {
	$seeders['videos'] = array(
		'label'     => esc_html__( 'Videos', 'custom-post-types-dp' ),
		'callback'  => 'cptdp_seed_videos',
		'csv'       => CPTDP_PATH . 'data/seeders/videos.csv',
		'post_type' => 'video',
		'columns'   => array( 'order', 'title', 'embed_url', 'post_url', 'length', 'aspect_ratio', 'highlight', 'description' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_videos' );

/**
 * Create the Videos listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_videos( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'video' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The video post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'embed_url', 'post_url', 'length', 'aspect_ratio', 'highlight', 'description' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$embed_prefixes = array( 'https://www.youtube.com/embed/', 'https://player.vimeo.com/video/' );
	$ratios         = cptdp_aspect_ratio_terms();

	foreach ( $rows as $index => $row ) {
		$line   = $index + 1;
		$errors = array();

		if ( ! ctype_digit( $row['order'] ) ) {
			$errors[] = sprintf( 'Invalid order "%s"', $row['order'] );
		}

		if ( ! ctype_digit( $row['length'] ) ) {
			$errors[] = sprintf( 'Invalid length "%s"', $row['length'] );
		}

		if ( ! in_array( $row['highlight'], array( '0', '1' ), true ) ) {
			$errors[] = sprintf( 'Invalid highlight "%s" (use 0 or 1)', $row['highlight'] );
		}

		if ( 0 !== strpos( $row['post_url'], 'https://' ) ) {
			$errors[] = sprintf( 'post_url "%s" must be https', $row['post_url'] );
		}

		$embed_ok = false;
		foreach ( $embed_prefixes as $prefix ) {
			if ( 0 === strpos( $row['embed_url'], $prefix ) ) {
				$embed_ok = true;
			}
		}
		if ( ! $embed_ok ) {
			$errors[] = sprintf( 'embed_url "%s" must be a YouTube or Vimeo embed URL', $row['embed_url'] );
		}

		if ( ! in_array( $row['aspect_ratio'], $ratios, true ) ) {
			$errors[] = sprintf( 'Unknown aspect_ratio "%s"', $row['aspect_ratio'] );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( '%1$s for "%2$s" (row %3$d).', implode( '; ', $errors ), $row['title'], $line ) );
		}
	}

	// Slug => term ID. A missing term must not be created by the assignment below.
	$term_ids = array();
	foreach ( array_unique( wp_list_pluck( $rows, 'aspect_ratio' ) ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'aspect_ratio' );

		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'cptdp_seed_term_missing', sprintf( 'Aspect Ratio term "%s" does not exist.', $slug ) );
		}

		$term_ids[ $slug ] = (int) $term->term_id;
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
	);

	foreach ( $rows as $row ) {
		if ( cptdp_seed_title_exists( 'video', $row['title'] ) ) {
			$result['skipped'][] = $row['title'];
			continue;
		}

		if ( ! $dry_run ) {
			// Without a privileged user kses would store "&" in a title as "&amp;",
			// so the title would no longer match its CSV row on the next run.
			$kses_active = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
			kses_remove_filters();

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'video',
					'post_status'  => 'publish',
					'post_title'   => $row['title'],
					'post_content' => $row['description'],
					'menu_order'   => (int) $row['order'],
					'meta_input'   => array(
						'cptdp_embed_url' => $row['embed_url'],
						'cptdp_post_url'  => $row['post_url'],
						'cptdp_length'    => (int) $row['length'],
						'cptdp_highlight' => '1' === $row['highlight'],
					),
				),
				true
			);

			if ( $kses_active ) {
				kses_init_filters();
			}

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$set = wp_set_object_terms( $post_id, array( $term_ids[ $row['aspect_ratio'] ] ), 'aspect_ratio' );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}
