<?php
/**
 * Video custom post type.
 *
 * Attaches the `video_category` and `aspect_ratio` taxonomies. Meta keys:
 * `cptdp_video_file` (attachment ID), `cptdp_embed_url`, `cptdp_post_url`,
 * `cptdp_length` (seconds), `cptdp_software_tools` (software_tool IDs),
 * `cptdp_highlight`, `cptdp_post_statistics` (rows of label/value strings). Callers must escape meta on output (esc_url, esc_html).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Video post type.
 */
function cptdp_register_video(): void {
	$labels = array(
		'name'                  => esc_html_x( 'Videos', 'post type general name', 'custom-post-types-dp' ),
		'singular_name'         => esc_html_x( 'Video', 'post type singular name', 'custom-post-types-dp' ),
		'menu_name'             => esc_html_x( 'Videos', 'admin menu', 'custom-post-types-dp' ),
		'name_admin_bar'        => esc_html_x( 'Video', 'add new on admin bar', 'custom-post-types-dp' ),
		'add_new'               => esc_html__( 'Add New', 'custom-post-types-dp' ),
		'add_new_item'          => esc_html__( 'Add New Video', 'custom-post-types-dp' ),
		'new_item'              => esc_html__( 'New Video', 'custom-post-types-dp' ),
		'edit_item'             => esc_html__( 'Edit Video', 'custom-post-types-dp' ),
		'view_item'             => esc_html__( 'View Video', 'custom-post-types-dp' ),
		'view_items'            => esc_html__( 'View Videos', 'custom-post-types-dp' ),
		'all_items'             => esc_html__( 'All Videos', 'custom-post-types-dp' ),
		'search_items'          => esc_html__( 'Search Videos', 'custom-post-types-dp' ),
		'not_found'             => esc_html__( 'No videos found.', 'custom-post-types-dp' ),
		'not_found_in_trash'    => esc_html__( 'No videos found in Trash.', 'custom-post-types-dp' ),
		'archives'              => esc_html__( 'Video Archives', 'custom-post-types-dp' ),
		'filter_items_list'     => esc_html__( 'Filter videos list', 'custom-post-types-dp' ),
		'items_list_navigation' => esc_html__( 'Videos list navigation', 'custom-post-types-dp' ),
		'items_list'            => esc_html__( 'Videos list', 'custom-post-types-dp' ),
		'featured_image'        => esc_html__( 'Thumbnail', 'custom-post-types-dp' ),
		'set_featured_image'    => esc_html__( 'Set thumbnail', 'custom-post-types-dp' ),
		'remove_featured_image' => esc_html__( 'Remove thumbnail', 'custom-post-types-dp' ),
		'use_featured_image'    => esc_html__( 'Use as thumbnail', 'custom-post-types-dp' ),
	);

	register_post_type(
		'video',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 22,
			'menu_icon'     => 'dashicons-video-alt3',
			'hierarchical'  => false,
			// custom-fields exposes registered meta in REST; its meta box is removed below.
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'video_category', 'aspect_ratio' ),
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'videos',
				'with_front' => false,
			),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_video', 15 );

/**
 * Force the classic editor for Videos. REST stays enabled.
 *
 * @param bool   $use_block_editor Whether the block editor is used.
 * @param string $post_type        Post type being edited.
 */
function cptdp_video_classic_editor( bool $use_block_editor, string $post_type ): bool {
	return 'video' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'cptdp_video_classic_editor', 10, 2 );

/**
 * Add Thumbnail and Order columns after Title.
 *
 * Category and Aspect Ratio columns come from `show_admin_column`.
 *
 * @param array $columns Existing columns.
 */
function cptdp_video_columns( array $columns ): array {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['thumbnail']        = esc_html__( 'Thumbnail', 'custom-post-types-dp' );
			$new['cptdp_length']     = esc_html__( 'Length', 'custom-post-types-dp' );
			$new['cptdp_highlight']  = esc_html__( 'Highlight', 'custom-post-types-dp' );
			$new['menu_order']       = esc_html__( 'Order', 'custom-post-types-dp' );
		}
	}

	return $new;
}
add_filter( 'manage_video_posts_columns', 'cptdp_video_columns' );

/**
 * Render custom column cells.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cptdp_video_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'thumbnail':
			$thumb = get_the_post_thumbnail( $post_id, array( 50, 50 ) );
			echo $thumb ? wp_kses_post( $thumb ) : '&mdash;';
			break;

		case 'cptdp_length':
			$length = (int) get_post_meta( $post_id, 'cptdp_length', true );
			echo $length > 0 ? esc_html( cptdp_video_format_length( $length ) ) : '&mdash;';
			break;

		case 'cptdp_highlight':
			echo get_post_meta( $post_id, 'cptdp_highlight', true ) ? '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Highlight', 'custom-post-types-dp' ) . '"></span>' : '&mdash;';
			break;

		case 'menu_order':
			echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
			break;
	}
}
add_action( 'manage_video_posts_custom_column', 'cptdp_video_column_content', 10, 2 );

/**
 * Make Order sortable.
 *
 * @param array $columns Sortable columns.
 */
function cptdp_video_sortable_columns( array $columns ): array {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-video_sortable_columns', 'cptdp_video_sortable_columns' );

/**
 * Default the admin list to Order ascending.
 *
 * @param WP_Query $query Query being run.
 */
function cptdp_video_default_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'video' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'cptdp_video_default_order' );

/**
 * Column widths on the Videos list screen.
 */
function cptdp_video_admin_css(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-video' !== $screen->id ) {
		return;
	}

	echo '<style>.column-thumbnail{width:60px}.column-cptdp_length,.column-cptdp_highlight{width:80px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_video_admin_css' );

/**
 * Sanitize a URL: http(s) only, empty string when invalid.
 *
 * @param mixed $url Raw value.
 */
function cptdp_video_sanitize_url( $url ): string {
	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
}

/**
 * Sanitize a video file attachment ID: must be an existing video attachment.
 *
 * @param mixed $id Raw value.
 * @return int Attachment ID, or 0 when invalid.
 */
function cptdp_video_sanitize_file( $id ): int {
	$id = absint( $id );

	return ( $id && wp_attachment_is( 'video', $id ) ) ? $id : 0;
}

/**
 * Sanitize the Software Tools list: unique software_tool post IDs only.
 *
 * @param mixed $ids Raw value.
 * @return int[]
 */
function cptdp_video_sanitize_software_tools( $ids ): array {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	return array_values(
		array_filter(
			$ids,
			static function ( int $id ): bool {
				return 'software_tool' === get_post_type( $id );
			}
		)
	);
}

/**
 * Sanitize Post Statistics: rows of `label` and `value` text.
 *
 * Drops non-array rows and rows with both fields empty, then reindexes.
 *
 * @param mixed $rows Raw value.
 * @return array<int, array{label: string, value: string}>
 */
function cptdp_video_sanitize_statistics( $rows ): array {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$clean = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
		$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';

		if ( '' === $label && '' === $value ) {
			continue;
		}

		$clean[] = array(
			'label' => $label,
			'value' => $value,
		);
	}

	return $clean;
}

/**
 * Format seconds as m:ss or h:mm:ss.
 *
 * @param int $seconds Length in seconds.
 */
function cptdp_video_format_length( int $seconds ): string {
	$hours   = intdiv( $seconds, 3600 );
	$minutes = intdiv( $seconds % 3600, 60 );
	$secs    = $seconds % 60;

	return $hours > 0
		? sprintf( '%d:%02d:%02d', $hours, $minutes, $secs )
		: sprintf( '%d:%02d', $minutes, $secs );
}

/**
 * Register Video meta.
 */
function cptdp_register_video_meta(): void {
	$auth = static function (): bool {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'video',
		'cptdp_video_file',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_video_sanitize_file',
			'auth_callback'     => $auth,
		)
	);

	foreach ( array( 'cptdp_embed_url', 'cptdp_post_url' ) as $key ) {
		register_post_meta(
			'video',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'cptdp_video_sanitize_url',
				'auth_callback'     => $auth,
			)
		);
	}

	register_post_meta(
		'video',
		'cptdp_length',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'video',
		'cptdp_highlight',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'video',
		'cptdp_software_tools',
		array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'sanitize_callback' => 'cptdp_video_sanitize_software_tools',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'video',
		'cptdp_post_statistics',
		array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'label' => array( 'type' => 'string' ),
							'value' => array( 'type' => 'string' ),
						),
						'additionalProperties' => false,
					),
				),
			),
			'sanitize_callback' => 'cptdp_video_sanitize_statistics',
			'auth_callback'     => $auth,
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_video_meta', 16 );

/**
 * Add the Video meta boxes.
 */
function cptdp_video_add_meta_boxes(): void {
	remove_meta_box( 'postcustom', 'video', 'normal' );

	add_meta_box(
		'cptdp_video_details',
		esc_html__( 'Video', 'custom-post-types-dp' ),
		'cptdp_video_render_details_box',
		'video',
		'normal',
		'high'
	);

	add_meta_box(
		'cptdp_video_software_tools',
		esc_html__( 'Software Tools', 'custom-post-types-dp' ),
		'cptdp_video_render_software_tools_box',
		'video',
		'normal',
		'default'
	);

	add_meta_box(
		'cptdp_video_post_statistics',
		esc_html__( 'Post Statistics', 'custom-post-types-dp' ),
		'cptdp_video_render_statistics_box',
		'video',
		'normal',
		'default'
	);

	add_meta_box(
		'cptdp_video_highlight',
		esc_html__( 'Highlight', 'custom-post-types-dp' ),
		'cptdp_video_render_highlight_box',
		'video',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_video', 'cptdp_video_add_meta_boxes' );

/**
 * Render the Video details meta box (file, URLs, length).
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_video_render_details_box( WP_Post $post ): void {
	$file_id   = (int) get_post_meta( $post->ID, 'cptdp_video_file', true );
	$file_name = $file_id ? wp_basename( (string) get_attached_file( $file_id ) ) : '';
	$embed_url = (string) get_post_meta( $post->ID, 'cptdp_embed_url', true );
	$post_url  = (string) get_post_meta( $post->ID, 'cptdp_post_url', true );
	$length    = (int) get_post_meta( $post->ID, 'cptdp_length', true );

	wp_nonce_field( 'cptdp_video', 'cptdp_video_nonce' );
	?>
	<p>
		<strong><?php esc_html_e( 'Video File', 'custom-post-types-dp' ); ?></strong><br>
		<input type="hidden" id="cptdp_video_file" name="cptdp_video_file" value="<?php echo esc_attr( (string) $file_id ); ?>">
		<button type="button" class="button" id="cptdp-video-file-select"><?php esc_html_e( 'Select video', 'custom-post-types-dp' ); ?></button>
		<span id="cptdp-video-file-name"><?php echo esc_html( $file_name ); ?></span>
		<a href="#" id="cptdp-video-file-remove" <?php echo $file_id ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></a>
	</p>
	<p>
		<label for="cptdp_embed_url"><strong><?php esc_html_e( 'Embed URL', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="url" id="cptdp_embed_url" name="cptdp_embed_url" class="widefat" placeholder="https://www.youtube.com/embed/..." value="<?php echo esc_attr( $embed_url ); ?>">
	</p>
	<p>
		<label for="cptdp_post_url"><strong><?php esc_html_e( 'Original Post URL', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="url" id="cptdp_post_url" name="cptdp_post_url" class="widefat" placeholder="https://" value="<?php echo esc_attr( $post_url ); ?>">
	</p>
	<p>
		<label for="cptdp_length"><strong><?php esc_html_e( 'Length (seconds)', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="number" id="cptdp_length" name="cptdp_length" min="0" step="1" value="<?php echo $length > 0 ? esc_attr( (string) $length ) : ''; ?>">
	</p>
	<?php
}

/**
 * Render the Software Tools meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_video_render_software_tools_box( WP_Post $post ): void {
	$selected = array_map( 'absint', (array) get_post_meta( $post->ID, 'cptdp_software_tools', true ) );
	$tools    = get_posts(
		array(
			'post_type'      => 'software_tool',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	if ( ! $tools ) {
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'No software tools yet.', 'custom-post-types-dp' ),
			esc_url( admin_url( 'post-new.php?post_type=software_tool' ) ),
			esc_html__( 'Add a Software Tool', 'custom-post-types-dp' )
		);
		return;
	}

	foreach ( $tools as $tool ) {
		printf(
			'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="cptdp_software_tools[]" value="%1$d" %2$s> %3$s</label>',
			(int) $tool->ID,
			checked( in_array( $tool->ID, $selected, true ), true, false ),
			esc_html( get_the_title( $tool ) )
		);
	}
}

/**
 * Render one Post Statistics row.
 *
 * @param string $index Row index, or a placeholder for the template row.
 * @param string $label Label.
 * @param string $value Value.
 */
function cptdp_video_render_statistics_row( string $index, string $label, string $value ): void {
	?>
	<div class="cptdp-stat-row" style="display:flex;gap:8px;margin-bottom:8px">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][label]" placeholder="<?php esc_attr_e( 'e.g. Views', 'custom-post-types-dp' ); ?>" value="<?php echo esc_attr( $label ); ?>">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][value]" placeholder="<?php esc_attr_e( 'e.g. 12K', 'custom-post-types-dp' ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<button type="button" class="button cptdp-stat-remove"><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></button>
	</div>
	<?php
}

/**
 * Render the Post Statistics meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_video_render_statistics_box( WP_Post $post ): void {
	$rows = cptdp_video_sanitize_statistics( get_post_meta( $post->ID, 'cptdp_post_statistics', true ) );
	?>
	<div id="cptdp-stat-rows">
		<?php
		foreach ( $rows as $i => $row ) {
			cptdp_video_render_statistics_row( (string) $i, $row['label'], $row['value'] );
		}
		?>
	</div>
	<template id="cptdp-stat-template">
		<?php cptdp_video_render_statistics_row( '__INDEX__', '', '' ); ?>
	</template>
	<p><button type="button" class="button" id="cptdp-stat-add"><?php esc_html_e( 'Add statistic', 'custom-post-types-dp' ); ?></button></p>
	<?php
}

/**
 * Render the Highlight meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_video_render_highlight_box( WP_Post $post ): void {
	?>
	<label>
		<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
		<?php esc_html_e( 'Mark as highlight (Selected work)', 'custom-post-types-dp' ); ?>
	</label>
	<?php
}

/**
 * Save the Video meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_video_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_video_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_video_nonce'] ) ), 'cptdp_video' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$file = isset( $_POST['cptdp_video_file'] ) ? cptdp_video_sanitize_file( wp_unslash( $_POST['cptdp_video_file'] ) ) : 0;
	$file ? update_post_meta( $post_id, 'cptdp_video_file', $file ) : delete_post_meta( $post_id, 'cptdp_video_file' );

	foreach ( array( 'cptdp_embed_url', 'cptdp_post_url' ) as $key ) {
		$url = isset( $_POST[ $key ] ) ? cptdp_video_sanitize_url( wp_unslash( $_POST[ $key ] ) ) : '';
		'' !== $url ? update_post_meta( $post_id, $key, $url ) : delete_post_meta( $post_id, $key );
	}

	$length = isset( $_POST['cptdp_length'] ) ? absint( wp_unslash( $_POST['cptdp_length'] ) ) : 0;
	$length > 0 ? update_post_meta( $post_id, 'cptdp_length', $length ) : delete_post_meta( $post_id, 'cptdp_length' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by callback.
	$tools = isset( $_POST['cptdp_software_tools'] ) ? cptdp_video_sanitize_software_tools( wp_unslash( $_POST['cptdp_software_tools'] ) ) : array();
	$tools ? update_post_meta( $post_id, 'cptdp_software_tools', $tools ) : delete_post_meta( $post_id, 'cptdp_software_tools' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by callback.
	$stats = isset( $_POST['cptdp_post_statistics'] ) ? cptdp_video_sanitize_statistics( wp_unslash( $_POST['cptdp_post_statistics'] ) ) : array();
	$stats ? update_post_meta( $post_id, 'cptdp_post_statistics', $stats ) : delete_post_meta( $post_id, 'cptdp_post_statistics' );

	empty( $_POST['cptdp_highlight'] ) ? delete_post_meta( $post_id, 'cptdp_highlight' ) : update_post_meta( $post_id, 'cptdp_highlight', 1 );
}
add_action( 'save_post_video', 'cptdp_video_save_meta' );

/**
 * Enqueue the Video admin script on Video edit screens.
 *
 * @param string $hook Current admin page.
 */
function cptdp_video_enqueue_admin( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'video' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'cptdp-admin-video',
		CPTDP_URL . 'assets/admin-video.js',
		array( 'jquery' ),
		CPTDP_VERSION,
		true
	);
	wp_localize_script(
		'cptdp-admin-video',
		'cptdpVideo',
		array(
			'selectTitle'  => esc_html__( 'Select video', 'custom-post-types-dp' ),
			'selectButton' => esc_html__( 'Use this video', 'custom-post-types-dp' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cptdp_video_enqueue_admin' );
