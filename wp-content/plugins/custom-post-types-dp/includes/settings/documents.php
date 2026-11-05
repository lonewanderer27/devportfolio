<?php
/**
 * Documents settings (Settings > Documents).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed values of the "Generate page images" setting.
 *
 * @return string[]
 */
function cptdp_documents_pages_modes(): array {
	return array( 'auto', 'server', 'browser' );
}

/**
 * Default Documents values.
 *
 * @return array<string,bool|string>
 */
function cptdp_documents_defaults(): array {
	return array(
		'show_book_title' => false,
		'pages_mode'      => 'auto',
		'trim_bottom'     => true,
		'selectable_text' => false,
	);
}

/**
 * Get the saved Documents settings.
 *
 * @param string|null $key Optional single key: show_book_title, pages_mode, trim_bottom or selectable_text.
 * @return array<string,bool|string>|bool|string
 */
function cptdp_get_documents_settings( ?string $key = null ) {
	$saved = get_option( 'cptdp_documents', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$value = array_merge( cptdp_documents_defaults(), array_intersect_key( $saved, cptdp_documents_defaults() ) );

	$value['show_book_title'] = rest_sanitize_boolean( $value['show_book_title'] );
	$value['pages_mode']      = in_array( $value['pages_mode'], cptdp_documents_pages_modes(), true ) ? $value['pages_mode'] : 'auto';
	$value['trim_bottom']     = rest_sanitize_boolean( $value['trim_bottom'] );
	$value['selectable_text'] = rest_sanitize_boolean( $value['selectable_text'] );

	if ( null === $key ) {
		return $value;
	}

	return $value[ $key ] ?? false;
}

/**
 * Register the setting, section and fields.
 */
function cptdp_documents_register_settings(): void {
	register_setting(
		'cptdp_documents',
		'cptdp_documents',
		array(
			'type'              => 'object',
			'default'           => cptdp_documents_defaults(),
			'sanitize_callback' => 'cptdp_documents_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'show_book_title' => array( 'type' => 'boolean' ),
						'pages_mode'      => array(
							'type' => 'string',
							'enum' => cptdp_documents_pages_modes(),
						),
						'trim_bottom'     => array( 'type' => 'boolean' ),
						'selectable_text' => array( 'type' => 'boolean' ),
					),
					'additionalProperties' => false,
				),
			),
		)
	);

}
add_action( 'admin_init', 'cptdp_documents_register_settings' );
add_action( 'rest_api_init', 'cptdp_documents_register_settings' );

/**
 * Register the section and fields of the Documents settings page.
 *
 * wp-admin only: the settings API functions are not loaded on REST requests.
 */
function cptdp_documents_register_sections(): void {
	add_settings_section(
		'cptdp_documents_section',
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		'cptdp_documents_render_section',
		'cptdp-documents'
	);

	add_settings_field(
		'cptdp_documents_show_book_title',
		esc_html__( 'Book title', 'custom-post-types-dp' ),
		'cptdp_documents_render_show_book_title',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_show_book_title' )
	);

	add_settings_field(
		'cptdp_documents_pages_mode',
		esc_html__( 'Generate page images', 'custom-post-types-dp' ),
		'cptdp_documents_render_pages_mode',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_pages_mode' )
	);

	add_settings_field(
		'cptdp_documents_trim_bottom',
		esc_html__( 'Page margins', 'custom-post-types-dp' ),
		'cptdp_documents_render_trim_bottom',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_trim_bottom' )
	);

	add_settings_field(
		'cptdp_documents_selectable_text',
		esc_html__( 'Selectable text', 'custom-post-types-dp' ),
		'cptdp_documents_render_selectable_text',
		'cptdp-documents',
		'cptdp_documents_section',
		array( 'label_for' => 'cptdp_documents_selectable_text' )
	);
}
add_action( 'admin_init', 'cptdp_documents_register_sections' );

/**
 * Add Settings > Documents.
 */
function cptdp_documents_add_menu(): void {
	add_options_page(
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		esc_html__( 'Documents', 'custom-post-types-dp' ),
		'manage_options',
		'cptdp-documents',
		'cptdp_documents_render_page'
	);
}
add_action( 'admin_menu', 'cptdp_documents_add_menu' );

/**
 * Render the settings page.
 */
function cptdp_documents_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdp_documents' );
			do_settings_sections( 'cptdp-documents' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Render the section intro.
 */
function cptdp_documents_render_section(): void {
	echo '<p>' . esc_html__( 'Options for the Documents page.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the "hide book title" checkbox.
 */
function cptdp_documents_render_show_book_title(): void {
	printf(
		'<label><input type="checkbox" id="cptdp_documents_show_book_title" name="cptdp_documents[show_book_title]" value="1" %s /> %s</label><p class="description">%s</p>',
		checked( cptdp_get_documents_settings( 'show_book_title' ), true, false ),
		esc_html__( 'Show the title under each book', 'custom-post-types-dp' ),
		esc_html__( 'When off, the title is only on the cover (screen readers still read it). Off by default.', 'custom-post-types-dp' )
	);
}

/**
 * Render the "Generate page images" select.
 */
function cptdp_documents_render_pages_mode(): void {
	$current = (string) cptdp_get_documents_settings( 'pages_mode' );
	$labels  = array(
		'auto'    => esc_html__( 'Automatic (server when it can, otherwise your browser)', 'custom-post-types-dp' ),
		'server'  => esc_html__( 'Server only', 'custom-post-types-dp' ),
		'browser' => esc_html__( 'Browser only (generated from the document edit screen)', 'custom-post-types-dp' ),
	);

	echo '<select id="cptdp_documents_pages_mode" name="cptdp_documents[pages_mode]">';
	foreach ( cptdp_documents_pages_modes() as $mode ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $mode ),
			selected( $current, $mode, false ),
			esc_html( $labels[ $mode ] )
		);
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Page images are made when a document with a PDF is saved, so visitors see them straight away. Automatic uses the server (Imagick or Ghostscript) when available and falls back to the browser of whoever is editing the document.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render the "Trim empty space" checkbox.
 */
function cptdp_documents_render_trim_bottom(): void {
	printf(
		'<label><input type="checkbox" id="cptdp_documents_trim_bottom" name="cptdp_documents[trim_bottom]" value="1" %s /> %s</label><p class="description">%s</p>',
		checked( cptdp_get_documents_settings( 'trim_bottom' ), true, false ),
		esc_html__( 'Trim empty space at the bottom of pages', 'custom-post-types-dp' ),
		esc_html__( 'When a document\'s pages all end well above their bottom edge, the viewer hides that empty strip (the page images are not changed, and printed footers are kept). The strip is measured when the images are made; switching this off or on takes effect straight away.', 'custom-post-types-dp' )
	);
}

/**
 * Render the "Selectable text" checkbox.
 */
function cptdp_documents_render_selectable_text(): void {
	printf(
		'<label><input type="checkbox" id="cptdp_documents_selectable_text" name="cptdp_documents[selectable_text]" value="1" %s /> %s</label><p class="description">%s</p><p class="description"><strong>%s</strong> %s</p>',
		checked( cptdp_get_documents_settings( 'selectable_text' ), true, false ),
		esc_html__( 'Let visitors select and copy text from document pages', 'custom-post-types-dp' ),
		esc_html__( 'The page images cannot be selected, so with this on the viewer draws PDF pages with the browser\'s own PDF engine (pdf.js) and lays real, selectable text over them.', 'custom-post-types-dp' ),
		esc_html__( 'This can make the viewer laggy.', 'custom-post-types-dp' ),
		esc_html__( 'Pages take longer to appear and to turn, large documents and phones or older computers feel it most, the stored images are not used, and dragging a page corner to turn it is switched off so that dragging selects text. Documents that cannot be drawn this way fall back to the images, without selectable text. Off by default.', 'custom-post-types-dp' )
	);
}

/**
 * Whether the value being saved comes from the Settings > Documents form.
 *
 * `options.php` always posts `option_page`. A browser form cannot send an unticked
 * checkbox, so there a missing checkbox means "off". Anything else (a partial
 * `PUT /wp/v2/settings`, `update_option()` from code) only names the keys it changes.
 */
function cptdp_documents_is_form_post(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check of which screen posted; options.php has already verified the nonce.
	return isset( $_POST['option_page'] ) && 'cptdp_documents' === sanitize_key( wp_unslash( $_POST['option_page'] ) );
}

/**
 * Sanitise submitted values.
 *
 * From the settings form every field is read as submitted (unticked = off). Otherwise
 * the keys that are not part of the input keep their current saved value, so a
 * partial update never resets the other settings.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,bool|string>
 */
function cptdp_documents_sanitize( $input ): array {
	$form    = cptdp_documents_is_form_post();
	$current = $form ? cptdp_documents_defaults() : cptdp_get_documents_settings();
	$clean   = $current;

	if ( ! is_array( $input ) || array() === $input ) {
		return $form ? cptdp_documents_defaults() : $current;
	}

	foreach ( array( 'show_book_title', 'trim_bottom', 'selectable_text' ) as $key ) {
		if ( $form ) {
			// Unticked checkboxes are not submitted, so a submitted form without them means "off".
			$clean[ $key ] = isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
		} elseif ( array_key_exists( $key, $input ) ) {
			$clean[ $key ] = rest_sanitize_boolean( $input[ $key ] );
		}
	}

	if ( array_key_exists( 'pages_mode', $input ) || $form ) {
		$mode                = isset( $input['pages_mode'] ) ? sanitize_key( (string) $input['pages_mode'] ) : 'auto';
		$clean['pages_mode'] = in_array( $mode, cptdp_documents_pages_modes(), true ) ? $mode : 'auto';
	}

	return $clean;
}
