<?php
/**
 * Collateral custom post type.
 *
 * Attaches the `collateral_category` taxonomy.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Collateral post type.
 */
function cptdp_register_collateral(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Collaterals', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Collateral', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Collaterals', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Collateral', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Collateral', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Collateral', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Collateral', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Collateral', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Collaterals', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Collaterals', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Collaterals', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No collaterals found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No collaterals found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Collateral Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter collaterals list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Collaterals list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Collaterals list', 'custom-post-types-dp' ),
		'featured_image'        => esc_html__( 'Thumbnail', 'custom-post-types-dp' ),
		'set_featured_image'    => esc_html__( 'Set thumbnail', 'custom-post-types-dp' ),
		'remove_featured_image' => esc_html__( 'Remove thumbnail', 'custom-post-types-dp' ),
		'use_featured_image'    => esc_html__( 'Use as thumbnail', 'custom-post-types-dp' ),
	);

	register_post_type(
		'collateral',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 27,
			'menu_icon'     => 'dashicons-portfolio',
			'hierarchical'  => false,
			// custom-fields exposes registered meta in REST; its meta box is removed below.
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'collateral_category' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'collaterals',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_collateral', 15 );

/**
 * Force the classic editor for Collaterals. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_collateral_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'collateral' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_collateral_classic_editor', 10, 2 );

/**
 * Add Thumbnail and Order columns after Title.
 *
 * The Category column comes from `show_admin_column`.
 *
 * @param array $columns Existing columns.
 */
function cptdp_collateral_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['thumbnail']  = esc_html__( 'Thumbnail', 'custom-post-types-dp' );
			$new['menu_order'] = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_collateral_posts_columns', 'cptdp_collateral_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_collateral_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'thumbnail':
			$thumb = get_the_post_thumbnail( $post_id, array( 50, 50 ) );
			echo $thumb ? wp_kses_post( $thumb ) : '&mdash;';
			break;

		case 'menu_order':
			echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
			break;
	}
}
add_action( 'manage_collateral_posts_custom_column', 'cptdp_collateral_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_collateral_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-collateral_sortable_columns', 'cptdp_collateral_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_collateral_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'collateral' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_collateral_default_order' );

/**
 * Column widths on the Collaterals list screen.
 */
function cptdp_collateral_admin_css(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-collateral' !== $screen->id ) {
		return;
	}

	echo '<style>.column-thumbnail{width:60px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_collateral_admin_css' );

/**
 * Sanitize a URL: http(s) only, empty string when invalid.
 *
 * @param mixed $url Raw value.
 */
function cptdp_collateral_sanitize_url( $url ): string {
	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
}

/**
 * Sanitize a collateral file attachment ID.
 *
 * Must be an existing attachment that is not a video or audio file. Documents
 * and images are allowed.
 *
 * @param mixed $id Raw value.
 * @return int Attachment ID, or 0 when invalid.
 */
function cptdp_collateral_sanitize_file( $id ): int {
	$id = absint( $id );

	if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
		return 0;
	}

	$mime = (string) get_post_mime_type( $id );

	foreach ( array( 'video/', 'audio/' ) as $prefix ) {
		if ( str_starts_with( $mime, $prefix ) ) {
			return 0;
		}
	}

	return $id;
}

/**
 * Sanitize Software Tools: unique IDs of existing `software_tool` posts.
 *
 * @param mixed $ids Raw value.
 * @return int[]
 */
function cptdp_collateral_sanitize_software_tools( $ids ): array {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	return array_values(
		array_filter(
			$ids,
			static function ( int $id ): bool {
				return 'software_tool' === get_post_type( $id );
			}
		)
	);
}

/**
 * Register Collateral meta.
 */
function cptdp_register_collateral_meta(): void {
	$auth = static function (): bool {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'collateral',
		'cptdp_collateral_file',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_collateral_sanitize_file',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'collateral',
		'cptdp_embed_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_collateral_sanitize_url',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'collateral',
		'cptdp_paper_size',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'collateral',
		'cptdp_total_pages',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'collateral',
		'cptdp_software_tools',
		array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'sanitize_callback' => 'cptdp_collateral_sanitize_software_tools',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'collateral',
		'cptdp_highlight',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => $auth,
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_collateral_meta', 16 );
