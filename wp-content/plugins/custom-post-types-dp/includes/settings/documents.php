<?php
/**
 * Documents settings (Settings > Documents).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default Documents values.
 *
 * @return array<string,bool>
 */
function cptdp_documents_defaults(): array {
	return array(
		'show_book_title' => false,
	);
}

/**
 * Get the saved Documents settings.
 *
 * @param string|null $key Optional single key: show_book_title.
 * @return array<string,bool>|bool
 */
function cptdp_get_documents_settings( ?string $key = null ) {
	$saved = get_option( 'cptdp_documents', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$value = array_merge( cptdp_documents_defaults(), array_intersect_key( $saved, cptdp_documents_defaults() ) );
	$value = array_map( 'rest_sanitize_boolean', $value );

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
					),
					'additionalProperties' => false,
				),
			),
		)
	);

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
}
add_action( 'admin_init', 'cptdp_documents_register_settings' );
add_action( 'rest_api_init', 'cptdp_documents_register_settings' );

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
 * Sanitise submitted values.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,bool>
 */
function cptdp_documents_sanitize( $input ): array {
	$clean = cptdp_documents_defaults();

	if ( ! is_array( $input ) ) {
		return $clean;
	}

	foreach ( array_keys( $clean ) as $key ) {
		$clean[ $key ] = isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
	}

	return $clean;
}
