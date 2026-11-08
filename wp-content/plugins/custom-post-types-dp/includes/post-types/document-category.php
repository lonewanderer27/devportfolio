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

	register_term_meta(
		'document_category',
		'cptdp_singular_name',
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
			'auth_callback'     => 'cptdp_document_category_meta_auth',
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document_category' );

/**
 * Authorise the document category singular name term meta.
 *
 * @return bool
 */
function cptdp_document_category_meta_auth(): bool {
	return current_user_can( 'manage_categories' );
}

/**
 * Return the singular display name for a term, falling back to its name.
 *
 * @param WP_Term|int $term Term object or ID.
 * @return string
 */
function cptdp_get_term_singular_name( $term ): string {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term );
	}

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$singular = trim( (string) get_term_meta( $term->term_id, 'cptdp_singular_name', true ) );

	return '' !== $singular ? $singular : $term->name;
}

/**
 * Render the Singular name field on the Add Document Category form.
 */
function cptdp_document_category_add_form_fields(): void {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	?>
	<div class="form-field">
		<label for="cptdp_singular_name"><?php esc_html_e( 'Singular name', 'custom-post-types-dp' ); ?></label>
		<input type="text" name="cptdp_singular_name" id="cptdp_singular_name" value="" />
		<p><?php esc_html_e( 'Shown above each document\'s cover, for example "Operations Doc". Leave empty to use the category name.', 'custom-post-types-dp' ); ?></p>
		<?php wp_nonce_field( 'cptdp_document_category_singular_name', 'cptdp_document_category_singular_nonce' ); ?>
	</div>
	<?php
}
add_action( 'document_category_add_form_fields', 'cptdp_document_category_add_form_fields' );

/**
 * Render the Singular name field on the Edit Document Category form.
 *
 * @param WP_Term $term Term being edited.
 */
function cptdp_document_category_edit_form_fields( WP_Term $term ): void {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	?>
	<tr class="form-field">
		<th scope="row"><label for="cptdp_singular_name"><?php esc_html_e( 'Singular name', 'custom-post-types-dp' ); ?></label></th>
		<td>
			<input type="text" name="cptdp_singular_name" id="cptdp_singular_name" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, 'cptdp_singular_name', true ) ); ?>" />
			<p class="description"><?php esc_html_e( 'Shown above each document\'s cover, for example "Operations Doc". Leave empty to use the category name.', 'custom-post-types-dp' ); ?></p>
			<?php wp_nonce_field( 'cptdp_document_category_singular_name', 'cptdp_document_category_singular_nonce' ); ?>
		</td>
	</tr>
	<?php
}
add_action( 'document_category_edit_form_fields', 'cptdp_document_category_edit_form_fields' );

/**
 * Save the Singular name field for a document category.
 *
 * @param int $term_id Term ID.
 */
function cptdp_save_document_category_singular_name( int $term_id ): void {
	if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['cptdp_document_category_singular_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['cptdp_document_category_singular_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'cptdp_document_category_singular_name' ) ) {
		return;
	}

	$value = isset( $_POST['cptdp_singular_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cptdp_singular_name'] ) ) : '';
	if ( '' === trim( $value ) ) {
		delete_term_meta( $term_id, 'cptdp_singular_name' );
		return;
	}

	update_term_meta( $term_id, 'cptdp_singular_name', $value );
}
add_action( 'created_document_category', 'cptdp_save_document_category_singular_name' );
add_action( 'edited_document_category', 'cptdp_save_document_category_singular_name' );

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
