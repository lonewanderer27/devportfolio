<?php
/**
 * Video Category taxonomy.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Video Category taxonomy.
 *
 * Registered with no object type. The Video post type (separate PR)
 * attaches it via its `taxonomies` argument.
 */
function cptdp_register_video_category(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Video Categories', 'taxonomy general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Video Category', 'taxonomy singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Video Categories', 'admin menu', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Video Categories', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Video Category', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Video Category', 'custom-post-types-dp' ),
		'update_item'           => esc_html__( 'Update Video Category', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Video Category', 'custom-post-types-dp' ),
		'new_item_name'         => esc_html__( 'New Video Category Name', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Video Categories', 'custom-post-types-dp' ),
		'popular_items'         => esc_html__( 'Popular Video Categories', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No video categories found.', 'custom-post-types-dp' ),
		'back_to_items'         => esc_html__( '&larr; Go to Video Categories', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Video Categories list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Video Categories list navigation', 'custom-post-types-dp' ),
	);

	register_taxonomy(
		'video_category',
		array(),
		array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => 'video-category',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_video_category' );

/**
 * Add a top-level Video Categories menu item.
 *
 * Temporary: skipped once the Video post type exists (separate PR);
 * core then lists the taxonomy under the Video menu.
 */
function cptdp_video_category_menu(): void {
	if ( post_type_exists( 'video' ) ) {
		return;
	}

	add_menu_page(
		esc_html__( 'Video Categories', 'custom-post-types-dp' ),
		esc_html__( 'Video Categories', 'custom-post-types-dp' ),
		'manage_categories',
		'edit-tags.php?taxonomy=video_category',
		'',
		'dashicons-category',
		23
	);
}
add_action( 'admin_menu', 'cptdp_video_category_menu' );

/**
 * Keep the top-level menu highlighted on Video Category screens.
 *
 * @param string $parent_file Current parent menu file.
 */
function cptdp_video_category_parent_file( string $parent_file ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

	if ( 'video_category' === $taxonomy && ! post_type_exists( 'video' ) ) {
		return 'edit-tags.php?taxonomy=video_category';
	}

	return $parent_file;
}
add_filter( 'parent_file', 'cptdp_video_category_parent_file' );
