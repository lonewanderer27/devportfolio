<?php
/**
 * Document custom post type.
 *
 * Attaches the `document_category` taxonomy.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Document post type.
 */
function cptdp_register_document(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Documents', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Document', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Documents', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Document', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Document', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Document', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Document', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Document', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Documents', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Documents', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Documents', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No documents found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No documents found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Document Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter documents list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Documents list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Documents list', 'custom-post-types-dp' ),
		'featured_image'        => esc_html__( 'Thumbnail', 'custom-post-types-dp' ),
		'set_featured_image'    => esc_html__( 'Set thumbnail', 'custom-post-types-dp' ),
		'remove_featured_image' => esc_html__( 'Remove thumbnail', 'custom-post-types-dp' ),
		'use_featured_image'    => esc_html__( 'Use as thumbnail', 'custom-post-types-dp' ),
	);

	register_post_type(
		'document',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 25,
			'menu_icon'     => 'dashicons-media-document',
			'hierarchical'  => false,
			// custom-fields exposes registered meta in REST; its meta box is removed below.
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'document_category' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'documents',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document', 15 );

/**
 * Force the classic editor for Documents. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_document_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'document' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_document_classic_editor', 10, 2 );
