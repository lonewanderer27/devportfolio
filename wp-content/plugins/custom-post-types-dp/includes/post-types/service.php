<?php
/**
 * Service custom post type.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Service post type.
 */
function cptdp_register_service(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Services', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Service', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Services', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Service', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Service', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Service', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Service', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Service', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Services', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Services', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Services', 'custom-post-types-dp' ),
		'parent_item_colon'     => esc_html__( 'Parent Service:', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No services found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No services found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Service Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter services list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Services list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Services list', 'custom-post-types-dp' ),
	);

	register_post_type(
		'service',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 20,
			'menu_icon'     => 'dashicons-hammer',
			'hierarchical'  => true,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'services',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_service' );

/**
 * Force the classic editor for Services. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_service_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'service' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_service_classic_editor', 10, 2 );
