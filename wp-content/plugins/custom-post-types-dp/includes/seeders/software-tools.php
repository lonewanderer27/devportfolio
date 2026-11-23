<?php
/**
 * Software Tools seeder.
 *
 * Creates one published `software_tool` per row of data/seeders/software-tools.csv.
 * The `icon` column names a file in data/seeders/icons/ (empty = no icon). The icon is
 * sideloaded and set as the tool's featured image (its thumbnail). An existing tool with
 * no featured image gets its icon; a featured image already set is never replaced.
 * Run: wp cptdp seed software-tools [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

const CPTDP_SEED_ICON_TYPES = array( 'image/png', 'image/webp', 'image/jpeg' );

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
		'columns'   => array( 'order', 'title', 'site_url', 'description', 'icon' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_software_tools' );

/**
 * Create the Software Tools listed in the CSV.
 *
 * @param bool   $dry_run  When true, report what would be created and write nothing.
 * @param string $csv_path Absolute path to the CSV. Icons are read from the `icons` folder beside it.
 * @return array{created: string[], skipped: string[], updated: string[]}|WP_Error Titles by outcome, or an error.
 */
function cptdp_seed_software_tools( bool $dry_run, string $csv_path ) {
	if ( ! post_type_exists( 'software_tool' ) ) {
		return new WP_Error( 'cptdp_seed_no_post_type', 'The software_tool post type is not registered.' );
	}

	$rows = cptdp_seed_read_csv( $csv_path, array( 'order', 'title', 'site_url', 'description', 'icon' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$icon_dir = dirname( $csv_path ) . '/icons/';

	foreach ( $rows as $index => $row ) {
		if ( ! ctype_digit( $row['order'] ) ) {
			return new WP_Error( 'cptdp_seed_csv_row', sprintf( 'Invalid order "%1$s" for "%2$s" (row %3$d).', $row['order'], $row['title'], $index + 1 ) );
		}

		if ( '' !== $row['icon'] ) {
			$icon = cptdp_seed_check_icon( $icon_dir, $row['icon'] );
			if ( is_wp_error( $icon ) ) {
				return new WP_Error( $icon->get_error_code(), sprintf( '%1$s (row %2$d, "%3$s").', $icon->get_error_message(), $index + 1, $row['title'] ) );
			}
		}
	}

	$result = array(
		'created' => array(),
		'skipped' => array(),
		'updated' => array(),
	);

	foreach ( $rows as $row ) {
		$icon_file = '' === $row['icon'] ? '' : $icon_dir . $row['icon'];
		$existing  = cptdp_seed_find_post_id( 'software_tool', $row['title'] );

		if ( $existing ) {
			if ( '' !== $icon_file && ! has_post_thumbnail( $existing ) ) {
				if ( ! $dry_run ) {
					$attached = cptdp_seed_attach_icon( $existing, $row['title'], $row['icon'], $icon_file );
					if ( is_wp_error( $attached ) ) {
						return $attached;
					}
				}
				$result['updated'][] = $row['title'];
			} else {
				$result['skipped'][] = $row['title'];
			}
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

			if ( '' !== $icon_file ) {
				$attached = cptdp_seed_attach_icon( $post_id, $row['title'], $row['icon'], $icon_file );
				if ( is_wp_error( $attached ) ) {
					return $attached;
				}
			}
		}

		$result['created'][] = $row['title'];
	}

	return $result;
}

/**
 * Check that an icon file exists in the icons folder and is an image WordPress accepts.
 *
 * @param string $icon_dir Icons folder, with a trailing slash.
 * @param string $name     File name from the CSV.
 * @return string|WP_Error The file name, or an error.
 */
function cptdp_seed_check_icon( string $icon_dir, string $name ) {
	if ( basename( $name ) !== $name || ! is_file( $icon_dir . $name ) ) {
		return new WP_Error( 'cptdp_seed_icon_missing', sprintf( 'Icon file "%s" was not found.', $name ) );
	}

	$check = wp_check_filetype_and_ext( $icon_dir . $name, $name );
	if ( ! in_array( $check['type'], CPTDP_SEED_ICON_TYPES, true ) ) {
		return new WP_Error( 'cptdp_seed_icon_type', sprintf( 'Icon file "%s" is not a PNG, WebP or JPEG image.', $name ) );
	}

	return $name;
}

/**
 * Set a tool's featured image to its icon, reusing an attachment already made from the same file.
 *
 * @param int    $post_id   Tool ID.
 * @param string $title     Tool title (for the attachment title and alt text).
 * @param string $file_name Icon file name, stored as the attachment's source.
 * @param string $path      Absolute path of the bundled icon. It is copied, never moved.
 * @return int|WP_Error Attachment ID, or an error.
 */
function cptdp_seed_attach_icon( int $post_id, string $title, string $file_name, string $path ) {
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_cptdp_icon_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $file_name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);

	$attachment_id = $found ? (int) $found[0] : 0;

	if ( ! $attachment_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( $file_name );
		if ( ! $tmp || ! copy( $path, $tmp ) ) {
			return new WP_Error( 'cptdp_seed_icon_copy', sprintf( 'Could not copy icon "%s".', $file_name ) );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $file_name,
				'tmp_name' => $tmp,
			),
			0,
			/* translators: %s: software tool name. */
			sprintf( __( '%s icon', 'custom-post-types-dp' ), $title )
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		update_post_meta( $attachment_id, '_cptdp_icon_source', $file_name );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf( /* translators: %s: software tool name. */ __( '%s logo', 'custom-post-types-dp' ), $title ) );
	}

	set_post_thumbnail( $post_id, $attachment_id );

	return $attachment_id;
}

/**
 * ID of the first post of a type with this title (any status, Trash included), or 0.
 *
 * @param string $post_type Post type.
 * @param string $title     Title.
 */
function cptdp_seed_find_post_id( string $post_type, string $title ): int {
	$found = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'title'            => $title,
			'fields'           => 'ids',
			'numberposts'      => 1,
			'suppress_filters' => true,
		)
	);

	return $found ? (int) $found[0] : 0;
}
