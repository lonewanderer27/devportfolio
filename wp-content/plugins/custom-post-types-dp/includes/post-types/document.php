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

/**
 * Add Thumbnail and Order columns after Title.
 *
 * The Category column comes from `show_admin_column`.
 *
 * @param array $columns Existing columns.
 */
function cptdp_document_columns( array $columns ): array {
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
add_filter( 'manage_document_posts_columns', 'cptdp_document_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_document_column_content( string $column, int $post_id ): void {
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
add_action( 'manage_document_posts_custom_column', 'cptdp_document_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_document_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-document_sortable_columns', 'cptdp_document_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_document_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'document' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_document_default_order' );

/**
 * Column widths on the Documents list screen.
 */
function cptdp_document_admin_css(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-document' !== $screen->id ) {
		return;
	}

	echo '<style>.column-thumbnail{width:60px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_document_admin_css' );
