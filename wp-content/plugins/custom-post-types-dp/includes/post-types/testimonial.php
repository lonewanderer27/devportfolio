<?php
/**
 * Testimonial custom post type (DPH-022).
 *
 * Title = client name, content = the quote (plain text), meta `cptdp_company` = the client's company.
 * Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Testimonial post type and its meta.
 */
function cptdp_register_testimonial(): void {
	$labels = array(
		'name'               => esc_html_x( 'Testimonials', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'      => esc_html_x( 'Testimonial', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'          => esc_html_x( 'Testimonials', 'admin menu', 'custom-post-types-dp' ),
		'add_new_item'       => esc_html__( 'Add New Testimonial', 'custom-post-types-dp' ),
		'new_item'           => esc_html__( 'New Testimonial', 'custom-post-types-dp' ),
		'edit_item'          => esc_html__( 'Edit Testimonial', 'custom-post-types-dp' ),
		'view_item'          => esc_html__( 'View Testimonial', 'custom-post-types-dp' ),
		'all_items'          => esc_html__( 'All Testimonials', 'custom-post-types-dp' ),
		'search_items'       => esc_html__( 'Search Testimonials', 'custom-post-types-dp' ),
		'not_found'          => esc_html__( 'No testimonials found.', 'custom-post-types-dp' ),
		'not_found_in_trash' => esc_html__( 'No testimonials found in Trash.', 'custom-post-types-dp' ),
		'archives'           => esc_html__( 'Testimonial Archives', 'custom-post-types-dp' ),
	);

	register_post_type(
		'testimonial',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 29,
			'menu_icon'     => 'dashicons-format-quote',
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'testimonials',
				'with_front' => false,
			),
		)
	);

	register_post_meta(
		'testimonial',
		'cptdp_company',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_testimonial' );

/**
 * Force the classic editor for Testimonials. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_testimonial_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'testimonial' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_testimonial_classic_editor', 10, 2 );

/**
 * Add the Company meta box.
 */
function cptdp_testimonial_add_meta_box(): void {
	add_meta_box(
		'cptdp_testimonial_company',
		esc_html__( 'Company', 'custom-post-types-dp' ),
		'cptdp_testimonial_render_meta_box',
		'testimonial',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_testimonial', 'cptdp_testimonial_add_meta_box' );

/**
 * Render the Company meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_testimonial_render_meta_box( WP_Post $post ): void {
	$value = (string) get_post_meta( $post->ID, 'cptdp_company', true );

	wp_nonce_field( 'cptdp_company', 'cptdp_company_nonce' );
	?>
	<p>
		<label class="screen-reader-text" for="cptdp_company"><?php esc_html_e( 'Company', 'custom-post-types-dp' ); ?></label>
		<input type="text" id="cptdp_company" name="cptdp_company" class="widefat" value="<?php echo esc_attr( $value ); ?>">
	</p>
	<p class="description"><?php esc_html_e( 'The title is the client name and the editor holds the quote (plain text).', 'custom-post-types-dp' ); ?></p>
	<?php
}

/**
 * Save the Company meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_testimonial_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_company_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_company_nonce'] ) ), 'cptdp_company' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$company = isset( $_POST['cptdp_company'] ) ? sanitize_text_field( wp_unslash( $_POST['cptdp_company'] ) ) : '';

	if ( '' === $company ) {
		delete_post_meta( $post_id, 'cptdp_company' );
	} else {
		update_post_meta( $post_id, 'cptdp_company', $company );
	}
}
add_action( 'save_post_testimonial', 'cptdp_testimonial_save_meta' );

/**
 * Add Company and Order columns after Title.
 *
 * @param array $columns Existing columns.
 */
function cptdp_testimonial_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['cptdp_company'] = esc_html__( 'Company', 'custom-post-types-dp' );
			$new['menu_order']    = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_testimonial_posts_columns', 'cptdp_testimonial_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_testimonial_column_content( string $column, int $post_id ): void {
	if ( 'cptdp_company' === $column ) {
		$company = (string) get_post_meta( $post_id, 'cptdp_company', true );
		echo '' === $company ? '&mdash;' : esc_html( $company );
	} elseif ( 'menu_order' === $column ) {
		echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_testimonial_posts_custom_column', 'cptdp_testimonial_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_testimonial_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-testimonial_sortable_columns', 'cptdp_testimonial_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_testimonial_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'testimonial' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_testimonial_default_order' );
