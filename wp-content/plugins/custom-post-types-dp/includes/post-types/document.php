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
 * Add Thumbnail, File Type, Pages, Highlight and Order columns after Title.
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
			$new['thumbnail']         = esc_html__( 'Thumbnail', 'custom-post-types-dp' );
			$new['cptdp_filetype']    = esc_html__( 'File Type', 'custom-post-types-dp' );
			$new['cptdp_total_pages'] = esc_html__( 'Pages', 'custom-post-types-dp' );
			$new['cptdp_highlight']   = esc_html__( 'Highlight', 'custom-post-types-dp' );
			$new['menu_order']        = esc_html__( 'Order', 'custom-post-types-dp' );
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

		case 'cptdp_filetype':
			$filetype = (string) get_post_meta( $post_id, 'cptdp_filetype', true );
			echo '' !== $filetype ? esc_html( $filetype ) : '&mdash;';
			break;

		case 'cptdp_total_pages':
			$pages = (int) get_post_meta( $post_id, 'cptdp_total_pages', true );
			echo $pages > 0 ? esc_html( (string) $pages ) : '&mdash;';
			break;

		case 'cptdp_highlight':
			echo get_post_meta( $post_id, 'cptdp_highlight', true ) ? '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Highlight', 'custom-post-types-dp' ) . '"></span>' : '&mdash;';
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

	echo '<style>.column-thumbnail{width:60px}.column-cptdp_filetype,.column-cptdp_total_pages,.column-cptdp_highlight{width:80px}.column-menu_order{width:70px}</style>';
}
add_action( 'admin_head-edit.php', 'cptdp_document_admin_css' );

/**
 * Sanitize a URL: http(s) only, empty string when invalid.
 *
 * @param mixed $url Raw value.
 */
function cptdp_document_sanitize_url( $url ): string {
	return esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
}

/**
 * Sanitize a document file attachment ID.
 *
 * Must be an existing attachment that is not a video or audio file. Documents
 * and images are allowed.
 *
 * @param mixed $id Raw value.
 * @return int Attachment ID, or 0 when invalid.
 */
function cptdp_document_sanitize_file( $id ): int {
	$id = absint( $id );

	if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
		return 0;
	}

	$mime = (string) get_post_mime_type( $id );

	foreach ( array( 'video/', 'audio/' ) as $prefix ) {
		if ( str_starts_with( $mime, $prefix ) ) {
			return 0;
		}
	}

	return $id;
}

/**
 * Sanitize Post Statistics: rows of `label` and `value` text.
 *
 * Drops non-array rows and rows with both fields empty, then reindexes.
 *
 * @param mixed $rows Raw value.
 * @return array<int, array{label: string, value: string}>
 */
function cptdp_document_sanitize_statistics( $rows ): array {
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
 * Register Document meta.
 */
function cptdp_register_document_meta(): void {
	$auth = static function (): bool {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'document',
		'cptdp_document_file',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_document_sanitize_file',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'document',
		'cptdp_embed_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'cptdp_document_sanitize_url',
			'auth_callback'     => $auth,
		)
	);

	foreach ( array( 'cptdp_filetype', 'cptdp_author' ) as $key ) {
		register_post_meta(
			'document',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
	}

	register_post_meta(
		'document',
		'cptdp_total_pages',
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
		'document',
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
		'document',
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
			'sanitize_callback' => 'cptdp_document_sanitize_statistics',
			'auth_callback'     => $auth,
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_document_meta', 16 );

/**
 * Add the Document meta boxes.
 */
function cptdp_document_add_meta_boxes(): void {
	remove_meta_box( 'postcustom', 'document', 'normal' );

	add_meta_box(
		'cptdp_document_details',
		esc_html__( 'Document', 'custom-post-types-dp' ),
		'cptdp_document_render_details_box',
		'document',
		'normal',
		'high'
	);

	add_meta_box(
		'cptdp_document_post_statistics',
		esc_html__( 'Post Statistics', 'custom-post-types-dp' ),
		'cptdp_document_render_statistics_box',
		'document',
		'normal',
		'default'
	);

	add_meta_box(
		'cptdp_document_highlight',
		esc_html__( 'Highlight', 'custom-post-types-dp' ),
		'cptdp_document_render_highlight_box',
		'document',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_document', 'cptdp_document_add_meta_boxes' );

/**
 * Render the Document details meta box (file, embed URL, file type, author, pages).
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_document_render_details_box( WP_Post $post ): void {
	$file_id   = cptdp_document_sanitize_file( get_post_meta( $post->ID, 'cptdp_document_file', true ) );
	$file_name = $file_id ? wp_basename( (string) get_attached_file( $file_id ) ) : '';
	$embed_url = (string) get_post_meta( $post->ID, 'cptdp_embed_url', true );
	$filetype  = (string) get_post_meta( $post->ID, 'cptdp_filetype', true );
	$author    = (string) get_post_meta( $post->ID, 'cptdp_author', true );
	$pages     = (int) get_post_meta( $post->ID, 'cptdp_total_pages', true );

	wp_nonce_field( 'cptdp_document', 'cptdp_document_nonce' );
	?>
	<p>
		<strong><?php esc_html_e( 'Document File', 'custom-post-types-dp' ); ?></strong><br>
		<input type="hidden" id="cptdp_document_file" name="cptdp_document_file" value="<?php echo esc_attr( (string) $file_id ); ?>">
		<button type="button" class="button" id="cptdp-document-file-select"><?php esc_html_e( 'Select document', 'custom-post-types-dp' ); ?></button>
		<span id="cptdp-document-file-name"><?php echo esc_html( $file_name ); ?></span>
		<a href="#" id="cptdp-document-file-remove" <?php echo $file_id ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></a>
	</p>
	<p>
		<label for="cptdp_embed_url"><strong><?php esc_html_e( 'Embed URL', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="url" id="cptdp_embed_url" name="cptdp_embed_url" class="widefat" placeholder="https://" value="<?php echo esc_attr( $embed_url ); ?>">
	</p>
	<p>
		<label for="cptdp_filetype"><strong><?php esc_html_e( 'File Type', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="text" id="cptdp_filetype" name="cptdp_filetype" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. PDF', 'custom-post-types-dp' ); ?>" value="<?php echo esc_attr( $filetype ); ?>">
	</p>
	<p>
		<label for="cptdp_author"><strong><?php esc_html_e( 'Author', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="text" id="cptdp_author" name="cptdp_author" class="regular-text" value="<?php echo esc_attr( $author ); ?>">
	</p>
	<p>
		<label for="cptdp_total_pages"><strong><?php esc_html_e( 'Total Pages', 'custom-post-types-dp' ); ?></strong></label><br>
		<input type="number" id="cptdp_total_pages" name="cptdp_total_pages" min="0" step="1" value="<?php echo $pages > 0 ? esc_attr( (string) $pages ) : ''; ?>">
	</p>
	<?php
}

/**
 * Render one Post Statistics row.
 *
 * @param string $index Row index, or a placeholder for the template row.
 * @param string $label Label.
 * @param string $value Value.
 */
function cptdp_document_render_statistics_row( string $index, string $label, string $value ): void {
	?>
	<div class="cptdp-stat-row" style="display:flex;gap:8px;margin-bottom:8px">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][label]" placeholder="<?php esc_attr_e( 'e.g. Downloads', 'custom-post-types-dp' ); ?>" value="<?php echo esc_attr( $label ); ?>">
		<input type="text" class="regular-text" name="cptdp_post_statistics[<?php echo esc_attr( $index ); ?>][value]" placeholder="<?php esc_attr_e( 'e.g. 1.2K', 'custom-post-types-dp' ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<button type="button" class="button cptdp-stat-remove"><?php esc_html_e( 'Remove', 'custom-post-types-dp' ); ?></button>
	</div>
	<?php
}

/**
 * Render the Post Statistics meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_document_render_statistics_box( WP_Post $post ): void {
	$rows = cptdp_document_sanitize_statistics( get_post_meta( $post->ID, 'cptdp_post_statistics', true ) );
	?>
	<div id="cptdp-stat-rows">
		<?php
		foreach ( $rows as $i => $row ) {
			cptdp_document_render_statistics_row( (string) $i, $row['label'], $row['value'] );
		}
		?>
	</div>
	<template id="cptdp-stat-template">
		<?php cptdp_document_render_statistics_row( '__INDEX__', '', '' ); ?>
	</template>
	<p><button type="button" class="button" id="cptdp-stat-add"><?php esc_html_e( 'Add statistic', 'custom-post-types-dp' ); ?></button></p>
	<?php
}

/**
 * Render the Highlight meta box.
 *
 * @param WP_Post $post Post being edited.
 */
function cptdp_document_render_highlight_box( WP_Post $post ): void {
	?>
	<label>
		<input type="checkbox" name="cptdp_highlight" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ) ); ?>>
		<?php esc_html_e( 'Mark as highlight (Selected work)', 'custom-post-types-dp' ); ?>
	</label>
	<?php
}

/**
 * Save the Document meta.
 *
 * @param int $post_id Post ID.
 */
function cptdp_document_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cptdp_document_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cptdp_document_nonce'] ) ), 'cptdp_document' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$file = isset( $_POST['cptdp_document_file'] ) ? cptdp_document_sanitize_file( wp_unslash( $_POST['cptdp_document_file'] ) ) : 0;
	$file ? update_post_meta( $post_id, 'cptdp_document_file', $file ) : delete_post_meta( $post_id, 'cptdp_document_file' );

	$embed = isset( $_POST['cptdp_embed_url'] ) ? cptdp_document_sanitize_url( wp_unslash( $_POST['cptdp_embed_url'] ) ) : '';
	'' !== $embed ? update_post_meta( $post_id, 'cptdp_embed_url', $embed ) : delete_post_meta( $post_id, 'cptdp_embed_url' );

	foreach ( array( 'cptdp_filetype', 'cptdp_author' ) as $key ) {
		$text = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		'' !== $text ? update_post_meta( $post_id, $key, $text ) : delete_post_meta( $post_id, $key );
	}

	$pages = isset( $_POST['cptdp_total_pages'] ) ? absint( wp_unslash( $_POST['cptdp_total_pages'] ) ) : 0;
	$pages > 0 ? update_post_meta( $post_id, 'cptdp_total_pages', $pages ) : delete_post_meta( $post_id, 'cptdp_total_pages' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by callback.
	$stats = isset( $_POST['cptdp_post_statistics'] ) ? cptdp_document_sanitize_statistics( wp_unslash( $_POST['cptdp_post_statistics'] ) ) : array();
	$stats ? update_post_meta( $post_id, 'cptdp_post_statistics', $stats ) : delete_post_meta( $post_id, 'cptdp_post_statistics' );

	empty( $_POST['cptdp_highlight'] ) ? delete_post_meta( $post_id, 'cptdp_highlight' ) : update_post_meta( $post_id, 'cptdp_highlight', 1 );
}
add_action( 'save_post_document', 'cptdp_document_save_meta' );

/**
 * Enqueue the Document admin script on Document edit screens.
 *
 * @param string $hook Current admin page.
 */
function cptdp_document_enqueue_admin( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'document' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'cptdp-admin-document',
		CPTDP_URL . 'assets/admin-document.js',
		array( 'jquery' ),
		CPTDP_VERSION,
		true
	);
	wp_localize_script(
		'cptdp-admin-document',
		'cptdpDocument',
		array(
			'selectTitle'  => esc_html__( 'Select document', 'custom-post-types-dp' ),
			'selectButton' => esc_html__( 'Use this document', 'custom-post-types-dp' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cptdp_document_enqueue_admin' );
