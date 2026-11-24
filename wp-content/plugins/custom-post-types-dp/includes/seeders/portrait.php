<?php
/**
 * Portrait seeder (DPH-001).
 *
 * Puts the bundled cut-out (data/seeders/portrait/<file>) in the Media Library and stores it as the Home hero portrait
 * in Settings > Contact, unless a portrait is already set. Run: wp cptdp seed portrait [--dry-run]
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the seeder.
 *
 * @param array<string, array<string, mixed>> $seeders Registered seeders.
 */
function cptdp_register_seeder_portrait( array $seeders ): array {
	$seeders['portrait'] = array(
		'label'    => esc_html__( 'Home portrait', 'custom-post-types-dp' ),
		'callback' => 'cptdp_seed_portrait',
		'csv'      => CPTDP_PATH . 'data/seeders/portrait.csv',
		'columns'  => array( 'file' ),
	);

	return $seeders;
}
add_filter( 'cptdp_seeders', 'cptdp_register_seeder_portrait' );

/**
 * Set the default portrait.
 *
 * @param bool   $dry_run  When true, report what would be done and write nothing.
 * @param string $csv_path Absolute path to the CSV.
 * @return array{created: string[], skipped: string[]}|WP_Error Names by outcome, or an error.
 */
function cptdp_seed_portrait( bool $dry_run, string $csv_path ) {
	$rows = cptdp_seed_read_csv( $csv_path, array( 'file' ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	if ( 1 !== count( $rows ) ) {
		return new WP_Error( 'cptdp_seed_csv_row', 'The portrait CSV must have exactly one row.' );
	}

	$name = $rows[0]['file'];
	$path = CPTDP_PATH . 'data/seeders/portrait/' . $name;

	if ( basename( $name ) !== $name || ! is_file( $path ) ) {
		return new WP_Error( 'cptdp_seed_portrait_missing', sprintf( 'Portrait file "%s" was not found.', $name ) );
	}

	$check = wp_check_filetype_and_ext( $path, $name );
	if ( ! in_array( $check['type'], array( 'image/png', 'image/webp' ), true ) ) {
		return new WP_Error( 'cptdp_seed_portrait_type', sprintf( 'Portrait file "%s" is not a PNG or WebP image.', $name ) );
	}

	$result  = array(
		'created' => array(),
		'skipped' => array(),
	);
	$current = (int) cptdp_get_contact( 'portrait_id' );

	if ( $current && 'attachment' === get_post_type( $current ) ) {
		$result['skipped'][] = $name;
		return $result;
	}

	if ( ! $dry_run ) {
		$attachment_id = cptdp_seed_portrait_attachment( $name, $path );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$saved = get_option( 'cptdp_contact', array() );
		update_option( 'cptdp_contact', array_merge( is_array( $saved ) ? $saved : array(), array( 'portrait_id' => $attachment_id ) ) );
	}

	$result['created'][] = $name;

	return $result;
}

/**
 * The Media Library attachment of the bundled portrait: reuse the one made earlier, else sideload a copy.
 *
 * @param string $file_name File name, stored as the attachment's source.
 * @param string $path      Absolute path of the bundled file. It is copied, never moved.
 * @return int|WP_Error Attachment ID, or an error.
 */
function cptdp_seed_portrait_attachment( string $file_name, string $path ) {
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_cptdp_portrait_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $file_name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);

	if ( $found ) {
		return (int) $found[0];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( $file_name );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return new WP_Error( 'cptdp_seed_portrait_copy', sprintf( 'Could not copy portrait "%s".', $file_name ) );
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $file_name,
			'tmp_name' => $tmp,
		),
		0,
		__( 'Home portrait', 'custom-post-types-dp' )
	);

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_cptdp_portrait_source', $file_name );

	return $attachment_id;
}
