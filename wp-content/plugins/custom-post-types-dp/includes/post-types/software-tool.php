<?php
/**
 * Software Tool custom post type.
 *
 * Meta `cptdp_site_url` holds the tool's site URL. Callers must escape on
 * output (esc_url).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Software Tool post type and its meta.
 */
function cptdp_register_software_tool(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Software Tools', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Software Tool', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Software Tools', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Software Tool', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Software Tool', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Software Tool', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Software Tool', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Software Tool', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Software Tools', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Software Tools', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Software Tools', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No software tools found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No software tools found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Software Tool Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter software tools list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Software tools list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Software tools list', 'custom-post-types-dp' ),
		'featured_image'        => esc_html__( 'Thumbnail', 'custom-post-types-dp' ),
		'set_featured_image'    => esc_html__( 'Set thumbnail', 'custom-post-types-dp' ),
		'remove_featured_image' => esc_html__( 'Remove thumbnail', 'custom-post-types-dp' ),
		'use_featured_image'    => esc_html__( 'Use as thumbnail', 'custom-post-types-dp' ),
	);

	register_post_type(
		'software_tool',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 24,
			'menu_icon'     => 'dashicons-admin-tools',
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'software-tools',
				'with_front' => false,
			),
		)
	);

	register_post_meta(
		'software_tool',
		'cptdp_site_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_software_tool_sanitize_url',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_software_tool' );

/**
 * Force the classic editor for Software Tools. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_software_tool_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'software_tool' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_software_tool_classic_editor', 10, 2 );

/**
 * Sanitize a site URL: http(s) only, empty string when invalid.
 *
 * @param mixed $url Raw value.
 */
function cptdp_software_tool_sanitize_url( $url ): string {
	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
}

/**
 * Add the Site URL meta box.
 */
function cptdp_software_tool_add_meta_box(): void {
	add_meta_box(
		'cptdp_software_tool_site_url',
		esc_html__( 'Site URL', 'custom-post-types-dp' ),
		'cptdp_software_tool_render_meta_box',
		'software_tool',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_software_tool', 'cptdp_software_tool_add_meta_box' );

/**
 * Render the Site URL meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_software_tool_render_meta_box( WP_Post $post ): void {
	$value = (string) get_post_meta( $post->ID, 'cptdp_site_url', true );

	wp_nonce_field( 'cptdp_site_url', 'cptdp_site_url_nonce' );
	?>
	<p>
		<label class="screen-reader-text" for="cptdp_site_url"><?php esc_html_e( 'Site URL', 'custom-post-types-dp' ); ?></label>
		<input type="url" id="cptdp_site_url" name="cptdp_site_url" class="widefat" placeholder="https://example.com" value="<?php echo esc_attr( $value ); ?>">
	</p>
	<?php
}

/**
 * Save the Site URL meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_software_tool_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_site_url_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_site_url_nonce'] ) ), 'cptdp_site_url' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$url = isset( $_POST['cptdp_site_url'] )
		? cptdp_software_tool_sanitize_url( wp_unslash( $_POST['cptdp_site_url'] ) )
		: '';

	if ( '' === $url ) {
		delete_post_meta( $post_id, 'cptdp_site_url' );
	} else {
		update_post_meta( $post_id, 'cptdp_site_url', $url );
	}
}
add_action( 'save_post_software_tool', 'cptdp_software_tool_save_meta' );

/**
 * Add Thumbnail, Site URL and Order columns after Title.
 *
 * @param array $columns Existing columns.
 */
function cptdp_software_tool_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['thumbnail']      = esc_html__( 'Thumbnail', 'custom-post-types-dp' );
			$new['cptdp_site_url'] = esc_html__( 'Site URL', 'custom-post-types-dp' );
			$new['menu_order']     = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_software_tool_posts_columns', 'cptdp_software_tool_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_software_tool_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'thumbnail':
			$thumb = get_the_post_thumbnail( $post_id, array( 50, 50 ) );
			echo $thumb ? wp_kses_post( $thumb ) : '&mdash;';
			break;

		case 'cptdp_site_url':
			$url = (string) get_post_meta( $post_id, 'cptdp_site_url', true );
			if ( '' === $url ) {
				echo '&mdash;';
				break;
			}
			printf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $url ),
				esc_html( $url )
			);
			break;

		case 'menu_order':
			echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
			break;
	}
}
add_action( 'manage_software_tool_posts_custom_column', 'cptdp_software_tool_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_software_tool_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-software_tool_sortable_columns', 'cptdp_software_tool_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_software_tool_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'software_tool' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_software_tool_default_order' );

/**
 * Column widths on the Software Tools list screen.
 */
function cptdp_software_tool_admin_css(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-software_tool' !== $screen->id ) {
		return;
	}

	echo '<style>.column-thumbnail{width:60px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_software_tool_admin_css' );
