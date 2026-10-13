<?php
/**
 * Document Category taxonomy.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Document Category taxonomy.
 *
 * Registered with no object type. The Document post type
 * attaches it via its `taxonomies` argument.
 */
function cptdp_register_document_category(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Document Categories', 'taxonomy general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Document Category', 'taxonomy singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Document Categories', 'admin menu', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Document Categories', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Document Category', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Document Category', 'custom-post-types-dp' ),
		'update_item'           => esc_html__( 'Update Document Category', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Document Category', 'custom-post-types-dp' ),
		'new_item_name'         => esc_html__( 'New Document Category Name', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Document Categories', 'custom-post-types-dp' ),
		'popular_items'         => esc_html__( 'Popular Document Categories', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No document categories found.', 'custom-post-types-dp' ),
		'back_to_items'         => esc_html__( '&larr; Go to Document Categories', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Document Categories list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Document Categories list navigation', 'custom-post-types-dp' ),
	);

	register_taxonomy(
		'document_category',
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
				'slug'       => 'document-category',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document_category' );

/**
 * Add a top-level Document Categories menu item.
 *
 * Temporary: skipped once the Document post type exists;
 * core then lists the taxonomy under the Documents menu.
 */
function cptdp_document_category_menu(): void {
	if ( post_type_exists( 'document' ) ) {
		return;
	}

	add_menu_page(
		esc_html__( 'Document Categories', 'custom-post-types-dp' ),
		esc_html__( 'Document Categories', 'custom-post-types-dp' ),
		'manage_categories',
		'edit-tags.php?taxonomy=document_category',
		'',
		'dashicons-category',
		26
	);
}
add_action( 'admin_menu', 'cptdp_document_category_menu' );

/**
 * Keep the top-level menu highlighted on Document Category screens.
 *
 * @param string $parent_file Current parent menu file.
 */
function cptdp_document_category_parent_file( string $parent_file ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

	if ( 'document_category' === $taxonomy && ! post_type_exists( 'document' ) ) {
		return 'edit-tags.php?taxonomy=document_category';
	}

	return $parent_file;
}
add_filter( 'parent_file', 'cptdp_document_category_parent_file' );
