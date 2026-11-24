<?php
/**
 * Job Role custom post type (DPH-003).
 *
 * Title = the role name, order = menu order, meta `cptdp_highlight` marks the ONE role the Home hero shows as the
 * top role (marking a role clears the mark on every other role). Not public: no archive, no front-end URL.
 * Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Job Role post type and its meta.
 */
function cptdp_register_job_role(): void {
	$labels = array(
		'name'               => esc_html_x( 'Job Roles', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'      => esc_html_x( 'Job Role', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'          => esc_html_x( 'Job Roles', 'admin menu', 'custom-post-types-dp' ),
		'add_new_item'       => esc_html__( 'Add New Job Role', 'custom-post-types-dp' ),
		'new_item'           => esc_html__( 'New Job Role', 'custom-post-types-dp' ),
		'edit_item'          => esc_html__( 'Edit Job Role', 'custom-post-types-dp' ),
		'view_item'          => esc_html__( 'View Job Role', 'custom-post-types-dp' ),
		'all_items'          => esc_html__( 'All Job Roles', 'custom-post-types-dp' ),
		'search_items'       => esc_html__( 'Search Job Roles', 'custom-post-types-dp' ),
		'not_found'          => esc_html__( 'No job roles found.', 'custom-post-types-dp' ),
		'not_found_in_trash' => esc_html__( 'No job roles found in Trash.', 'custom-post-types-dp' ),
	);

	register_post_type(
		'job_role',
		array(
			'labels'        => $labels,
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'has_archive'   => false,
			'rewrite'       => false,
			'hierarchical'  => false,
			// custom-fields exposes registered meta in REST; its core meta box is removed below.
			'supports'      => array( 'title', 'page-attributes', 'custom-fields' ),
			'menu_position' => 24, // After Services (23), before Testimonials (29).
			'menu_icon'     => 'dashicons-businessperson',
		)
	);

	register_post_meta(
		'job_role',
		'cptdp_highlight',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_job_role' );

/**
 * Force the classic editor for Job Roles. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_job_role_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'job_role' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_job_role_classic_editor', 10, 2 );

/**
 * Only one Job Role may be the highlight: when one is marked, clear the mark on all the others.
 *
 * Runs on the meta hooks so the meta box, REST, WP-CLI and the seeder all obey the same rule.
 *
 * @param int    $meta_id    Meta row ID (unused).
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value just stored.
 */
function cptdp_job_role_single_highlight( $meta_id, $post_id, $meta_key, $meta_value ): void {
	static $running = false;

	if ( $running || 'cptdp_highlight' !== $meta_key || 'job_role' !== get_post_type( $post_id ) || ! rest_sanitize_boolean( $meta_value ) ) {
		return;
	}

	$running = true;

	$others = get_posts(
		array(
			'post_type'      => 'job_role',
			'post_status'    => array_values( get_post_stati() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'post__not_in'   => array( (int) $post_id ),
			'meta_key'       => 'cptdp_highlight', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of rows.
		)
	);

	foreach ( $others as $other_id ) {
		delete_post_meta( $other_id, 'cptdp_highlight' );
	}

	$running = false;
}
add_action( 'added_post_meta', 'cptdp_job_role_single_highlight', 10, 4 );
add_action( 'updated_post_meta', 'cptdp_job_role_single_highlight', 10, 4 );

/**
 * Add the Highlight meta box.
 */
function cptdp_job_role_add_meta_box(): void {
	remove_meta_box( 'postcustom', 'job_role', 'normal' );

	add_meta_box(
		'cptdp_job_role_highlight',
		esc_html__( 'Highlight', 'custom-post-types-dp' ),
		'cptdp_job_role_render_meta_box',
		'job_role',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_job_role', 'cptdp_job_role_add_meta_box' );

/**
 * Render the Highlight meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_job_role_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'cptdp_job_role_highlight', 'cptdp_job_role_highlight_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
			<?php esc_html_e( 'Show as the main role on the Home page', 'custom-post-types-dp' ); ?>
		</label>
	</p>
	<p class="description"><?php esc_html_e( 'Only one role can be the highlight. Ticking this one un-ticks the current one. The other roles are listed on the next line, in order.', 'custom-post-types-dp' ); ?></p>
	<?php
}

/**
 * Save the Highlight meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_job_role_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_job_role_highlight_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_job_role_highlight_nonce'] ) ), 'cptdp_job_role_highlight' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	empty( $_POST['cptdp_highlight'] ) ? delete_post_meta( $post_id, 'cptdp_highlight' ) : update_post_meta( $post_id, 'cptdp_highlight', 1 );
}
add_action( 'save_post_job_role', 'cptdp_job_role_save_meta' );

/**
 * Add Highlight and Order columns after Title.
 *
 * @param array $columns Existing columns.
 */
function cptdp_job_role_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['cptdp_highlight'] = esc_html__( 'Highlight', 'custom-post-types-dp' );
			$new['menu_order']      = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_job_role_posts_columns', 'cptdp_job_role_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_job_role_column_content( string $column, int $post_id ): void {
	if ( 'cptdp_highlight' === $column ) {
		echo get_post_meta( $post_id, 'cptdp_highlight', true ) ? '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Highlight', 'custom-post-types-dp' ) . '"></span>' : '&mdash;';
	} elseif ( 'menu_order' === $column ) {
		echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_job_role_posts_custom_column', 'cptdp_job_role_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_job_role_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-job_role_sortable_columns', 'cptdp_job_role_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_job_role_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'job_role' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_job_role_default_order' );
