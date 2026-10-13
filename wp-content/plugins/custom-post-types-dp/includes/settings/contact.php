<?php
/**
 * Contact settings (Settings > Contact).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default contact values.
 *
 * @return array<string,string>
 */
function cptdp_contact_defaults(): array {
	return array(
		'email'        => '',
		'whatsapp_url' => '',
		'linkedin_url' => '',
	);
}

/**
 * Get saved contact values.
 *
 * Callers must escape on output (esc_url, esc_html, antispambot).
 *
 * @param string|null $key Optional single key: email, whatsapp_url, linkedin_url.
 * @return array<string,string>|string
 */
function cptdp_get_contact( ?string $key = null ) {
	$saved = get_option( 'cptdp_contact', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$value = array_merge( cptdp_contact_defaults(), array_intersect_key( $saved, cptdp_contact_defaults() ) );

	if ( null === $key ) {
		return $value;
	}

	return $value[ $key ] ?? '';
}

/**
 * Register the setting, section and fields.
 */
function cptdp_contact_register_settings(): void {
	register_setting(
		'cptdp_contact',
		'cptdp_contact',
		array(
			'type'              => 'object',
			'default'           => cptdp_contact_defaults(),
			'sanitize_callback' => 'cptdp_contact_sanitize',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'email'        => array( 'type' => 'string' ),
						'whatsapp_url' => array( 'type' => 'string' ),
						'linkedin_url' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
			),
		)
	);

	add_settings_section(
		'cptdp_contact_section',
		esc_html__( 'Contact', 'custom-post-types-dp' ),
		'cptdp_contact_render_section',
		'cptdp-contact'
	);

	$fields = array(
		'email'        => array(
			'label'       => esc_html__( 'Email', 'custom-post-types-dp' ),
			'type'        => 'email',
			'placeholder' => 'you@example.com',
		),
		'whatsapp_url' => array(
			'label'       => esc_html__( 'WhatsApp URL', 'custom-post-types-dp' ),
			'type'        => 'url',
			'placeholder' => 'https://wa.me/<number>',
		),
		'linkedin_url' => array(
			'label'       => esc_html__( 'LinkedIn URL', 'custom-post-types-dp' ),
			'type'        => 'url',
			'placeholder' => 'https://www.linkedin.com/in/<handle>',
		),
	);

	foreach ( $fields as $key => $field ) {
		add_settings_field(
			'cptdp_contact_' . $key,
			$field['label'],
			'cptdp_contact_render_field',
			'cptdp-contact',
			'cptdp_contact_section',
			array(
				'key'         => $key,
				'type'        => $field['type'],
				'placeholder' => $field['placeholder'],
				'label_for'   => 'cptdp_contact_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'cptdp_contact_register_settings' );
add_action( 'rest_api_init', 'cptdp_contact_register_settings' );

/**
 * Add Settings > Contact.
 */
function cptdp_contact_add_menu(): void {
	add_options_page(
		esc_html__( 'Contact', 'custom-post-types-dp' ),
		esc_html__( 'Contact', 'custom-post-types-dp' ),
		'manage_options',
		'cptdp-contact',
		'cptdp_contact_render_page'
	);
}
add_action( 'admin_menu', 'cptdp_contact_add_menu' );

/**
 * Render the settings page.
 */
function cptdp_contact_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdp_contact' );
			do_settings_sections( 'cptdp-contact' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Render the section intro.
 */
function cptdp_contact_render_section(): void {
	echo '<p>' . esc_html__( 'Contact and external links used across the site. All fields are optional.', 'custom-post-types-dp' ) . '</p>';
}

/**
 * Render one field.
 *
 * @param array<string,string> $args Field args.
 */
function cptdp_contact_render_field( array $args ): void {
	printf(
		'<input type="%1$s" id="%2$s" name="cptdp_contact[%3$s]" value="%4$s" placeholder="%5$s" class="regular-text" />',
		esc_attr( $args['type'] ),
		esc_attr( $args['label_for'] ),
		esc_attr( $args['key'] ),
		esc_attr( cptdp_get_contact( $args['key'] ) ),
		esc_attr( $args['placeholder'] )
	);
}

/**
 * Check a URL is https and its host is (a subdomain of) an allowed domain.
 *
 * @param string   $url     Sanitised URL.
 * @param string[] $domains Allowed domains.
 */
function cptdp_contact_host_allowed( string $url, array $domains ): bool {
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! is_string( $host ) ) {
		return false;
	}

	$host = strtolower( $host );

	foreach ( $domains as $domain ) {
		if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Sanitise submitted values. Invalid values are blanked with an error notice.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,string>
 */
function cptdp_contact_sanitize( $input ): array {
	$clean = cptdp_contact_defaults();

	if ( ! is_array( $input ) ) {
		return $clean;
	}

	$email = isset( $input['email'] ) && is_string( $input['email'] ) ? trim( $input['email'] ) : '';
	if ( '' !== $email ) {
		$email = sanitize_email( $email );
		if ( '' === $email || ! is_email( $email ) ) {
			add_settings_error( 'cptdp_contact', 'cptdp_contact_email', esc_html__( 'Invalid email address.', 'custom-post-types-dp' ) );
			$email = '';
		}
	}
	$clean['email'] = $email;

	$urls = array(
		'whatsapp_url' => array(
			'label'   => esc_html__( 'WhatsApp URL', 'custom-post-types-dp' ),
			'domains' => array( 'wa.me', 'whatsapp.com' ),
		),
		'linkedin_url' => array(
			'label'   => esc_html__( 'LinkedIn URL', 'custom-post-types-dp' ),
			'domains' => array( 'linkedin.com' ),
		),
	);

	foreach ( $urls as $key => $config ) {
		$url = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? trim( $input[ $key ] ) : '';
		if ( '' !== $url ) {
			$url = esc_url_raw( $url, array( 'https' ) );
			if ( '' === $url || ! cptdp_contact_host_allowed( $url, $config['domains'] ) ) {
				add_settings_error(
					'cptdp_contact',
					'cptdp_contact_' . $key,
					/* translators: %s: field label. */
					sprintf( esc_html__( 'Invalid %s. Use a full https:// link on the expected site.', 'custom-post-types-dp' ), $config['label'] )
				);
				$url = '';
			}
		}
		$clean[ $key ] = $url;
	}

	return $clean;
}
