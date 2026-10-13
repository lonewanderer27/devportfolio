<?php
/**
 * Video custom post type.
 *
 * Attaches the `video_category` and `aspect_ratio` taxonomies. Callers must
 * escape any meta on output (esc_url, esc_html).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Video post type.
 */
function cptdp_register_video(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Videos', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Video', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Videos', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Video', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Video', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Video', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Video', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Video', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Videos', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Videos', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Videos', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No videos found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No videos found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Video Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter videos list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Videos list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Videos list', 'custom-post-types-dp' ),
		'featured_image'        => esc_html__( 'Thumbnail', 'custom-post-types-dp' ),
		'set_featured_image'    => esc_html__( 'Set thumbnail', 'custom-post-types-dp' ),
		'remove_featured_image' => esc_html__( 'Remove thumbnail', 'custom-post-types-dp' ),
		'use_featured_image'    => esc_html__( 'Use as thumbnail', 'custom-post-types-dp' ),
	);

	register_post_type(
		'video',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 22,
			'menu_icon'     => 'dashicons-video-alt3',
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'taxonomies'    => array( 'video_category', 'aspect_ratio' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'videos',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_video', 15 );

/**
 * Force the classic editor for Videos. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_video_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'video' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_video_classic_editor', 10, 2 );

/**
 * Add Thumbnail and Order columns after Title.
 *
 * Category and Aspect Ratio columns come from `show_admin_column`.
 *
 * @param array $columns Existing columns.
 */
function cptdp_video_columns( array $columns ): array {
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
add_filter( 'manage_video_posts_columns', 'cptdp_video_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_video_column_content( string $column, int $post_id ): void {
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
add_action( 'manage_video_posts_custom_column', 'cptdp_video_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_video_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-video_sortable_columns', 'cptdp_video_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_video_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'video' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_video_default_order' );

/**
 * Column widths on the Videos list screen.
 */
function cptdp_video_admin_css(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-video' !== $screen->id ) {
		return;
	}

	echo '<style>.column-thumbnail{width:60px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_video_admin_css' );
