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
