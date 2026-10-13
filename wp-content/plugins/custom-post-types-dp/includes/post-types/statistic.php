<?php
/**
 * Statistic custom post type.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Statistic post type.
 */
function cptdp_register_statistic(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Statistics', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Statistic', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Statistics', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Statistic', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Statistic', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Statistic', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Statistic', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Statistic', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Statistics', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Statistics', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Statistics', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No statistics found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No statistics found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Statistic Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter statistics list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Statistics list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Statistics list', 'custom-post-types-dp' ),
	);

	register_post_type(
		'statistic',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 21,
			'menu_icon'     => 'dashicons-chart-bar',
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'statistics',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_statistic' );

/**
 * Force the classic editor for Statistics. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_statistic_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'statistic' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_statistic_classic_editor', 10, 2 );
