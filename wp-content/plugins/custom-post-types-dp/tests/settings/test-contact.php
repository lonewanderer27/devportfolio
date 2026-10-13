<?php
/**
 * Contact settings tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Contact extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_settings_errors'] = array();
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		unset( $GLOBALS['wp_settings_errors'] );

		parent::tear_down();
	}

	public function test_defaults(): void {
		$this->assertSame(
			array(
				'email'        => '',
				'whatsapp_url' => '',
				'linkedin_url' => '',
			),
			cptdp_get_contact()
		);
		$this->assertSame( '', cptdp_get_contact( 'email' ) );
		$this->assertSame( '', cptdp_get_contact( 'unknown' ) );
	}

	public function test_get_contact_merges_saved_values_and_drops_unknown_keys(): void {
		update_option(
			'cptdp_contact',
			array(
				'email' => 'me@example.com',
				'evil'  => 'x',
			)
		);

		$this->assertSame(
			array(
				'email'        => 'me@example.com',
				'whatsapp_url' => '',
				'linkedin_url' => '',
			),
			cptdp_get_contact()
		);
		$this->assertSame( 'me@example.com', cptdp_get_contact( 'email' ) );
	}

	public function test_get_contact_survives_a_corrupt_option(): void {
		update_option( 'cptdp_contact', 'not-an-array' );

		$this->assertSame( cptdp_contact_defaults(), cptdp_get_contact() );
	}

	public function test_sanitize_non_array_returns_defaults(): void {
		$this->assertSame( cptdp_contact_defaults(), cptdp_contact_sanitize( 'x' ) );
		$this->assertSame( cptdp_contact_defaults(), cptdp_contact_sanitize( null ) );
	}

	public function test_sanitize_keeps_valid_values(): void {
		$clean = cptdp_contact_sanitize(
			array(
				'email'        => '  me@example.com ',
				'whatsapp_url' => 'https://wa.me/639171234567',
				'linkedin_url' => 'https://www.linkedin.com/in/someone',
				'extra'        => 'dropped',
			)
		);

		$this->assertSame(
			array(
				'email'        => 'me@example.com',
				'whatsapp_url' => 'https://wa.me/639171234567',
				'linkedin_url' => 'https://www.linkedin.com/in/someone',
			),
			$clean
		);
		$this->assertSame( array(), get_settings_errors( 'cptdp_contact' ) );
	}

	public function test_sanitize_allows_all_empty(): void {
		$this->assertSame( cptdp_contact_defaults(), cptdp_contact_sanitize( array( 'email' => '', 'whatsapp_url' => '', 'linkedin_url' => '' ) ) );
		$this->assertSame( array(), get_settings_errors( 'cptdp_contact' ) );
	}

	public function test_sanitize_blanks_invalid_email_with_error(): void {
		$clean = cptdp_contact_sanitize( array( 'email' => 'not-an-email' ) );

		$this->assertSame( '', $clean['email'] );
		$this->assertSame( 'cptdp_contact_email', get_settings_errors( 'cptdp_contact' )[0]['code'] );
	}

	public function test_sanitize_rejects_non_string_values(): void {
		$clean = cptdp_contact_sanitize(
			array(
				'email'        => array( 'a@b.co' ),
				'whatsapp_url' => 123,
				'linkedin_url' => array(),
			)
		);

		$this->assertSame( cptdp_contact_defaults(), $clean );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function url_cases(): array {
		return array(
			'whatsapp wa.me'            => array( 'whatsapp_url', 'https://wa.me/123', true ),
			'whatsapp.com'              => array( 'whatsapp_url', 'https://api.whatsapp.com/send?phone=1', true ),
			'whatsapp http'             => array( 'whatsapp_url', 'http://wa.me/123', false ),
			'whatsapp wrong site'       => array( 'whatsapp_url', 'https://example.com/123', false ),
			'whatsapp lookalike'        => array( 'whatsapp_url', 'https://evilwa.me/123', false ),
			'whatsapp suffix attack'    => array( 'whatsapp_url', 'https://wa.me.evil.com/123', false ),
			'linkedin'                  => array( 'linkedin_url', 'https://linkedin.com/in/me', true ),
			'linkedin www'              => array( 'linkedin_url', 'https://www.linkedin.com/in/me', true ),
			'linkedin lookalike'        => array( 'linkedin_url', 'https://notlinkedin.com/in/me', false ),
			'linkedin suffix attack'    => array( 'linkedin_url', 'https://linkedin.com.evil.com/in/me', false ),
			'linkedin javascript'       => array( 'linkedin_url', 'javascript:alert(1)', false ),
			'linkedin ftp'              => array( 'linkedin_url', 'ftp://linkedin.com/in/me', false ),
			'whatsapp on linkedin slot' => array( 'linkedin_url', 'https://wa.me/123', false ),
		);
	}

	/**
	 * @dataProvider url_cases
	 */
	public function test_sanitize_url_fields( string $field, string $url, bool $valid ): void {
		$clean = cptdp_contact_sanitize( array( $field => $url ) );

		if ( $valid ) {
			$this->assertSame( $url, $clean[ $field ] );
			$this->assertSame( array(), get_settings_errors( 'cptdp_contact' ) );
		} else {
			$this->assertSame( '', $clean[ $field ] );
			$this->assertSame( 'cptdp_contact_' . $field, get_settings_errors( 'cptdp_contact' )[0]['code'] );
		}
	}

	public function test_sanitize_upgrades_schemeless_url_to_https(): void {
		$clean = cptdp_contact_sanitize( array( 'linkedin_url' => 'linkedin.com/in/me' ) );

		$this->assertSame( 'https://linkedin.com/in/me', $clean['linkedin_url'] );
	}

	public function test_host_allowed(): void {
		$this->assertTrue( cptdp_contact_host_allowed( 'https://WA.me/1', array( 'wa.me' ) ) );
		$this->assertTrue( cptdp_contact_host_allowed( 'https://a.b.wa.me/1', array( 'wa.me' ) ) );
		$this->assertFalse( cptdp_contact_host_allowed( 'https://xwa.me/1', array( 'wa.me' ) ) );
		$this->assertFalse( cptdp_contact_host_allowed( '/relative', array( 'wa.me' ) ) );
		$this->assertFalse( cptdp_contact_host_allowed( 'https://wa.me', array() ) );
	}

	public function test_setting_is_registered_with_sanitize_callback(): void {
		$this->assertNotFalse( has_action( 'admin_init', 'cptdp_contact_register_settings' ) );
		$this->assertNotFalse( has_action( 'rest_api_init', 'cptdp_contact_register_settings' ) );

		cptdp_contact_register_settings();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'cptdp_contact', $registered );
		$this->assertSame( 'cptdp_contact_sanitize', $registered['cptdp_contact']['sanitize_callback'] );
		$this->assertSame( cptdp_contact_defaults(), $registered['cptdp_contact']['default'] );

		$this->assertSame( cptdp_contact_defaults(), sanitize_option( 'cptdp_contact', array( 'email' => 'bad' ) ) );
	}

	public function test_settings_page_is_added_for_admins_only(): void {
		global $submenu;

		$submenu = array();
		$this->acting_as( 'administrator' );
		cptdp_contact_add_menu();
		$this->assertContains( 'cptdp-contact', wp_list_pluck( $submenu['options-general.php'], 2 ) );

		$submenu = array();
		$this->acting_as( 'editor' );
		cptdp_contact_add_menu();
		$this->assertNotContains( 'cptdp-contact', wp_list_pluck( $submenu['options-general.php'] ?? array(), 2 ) );
	}

	public function test_render_page_requires_manage_options(): void {
		$this->acting_as( 'editor' );

		$this->assertSame( '', $this->capture( 'cptdp_contact_render_page' ) );
	}

	public function test_render_field_escapes_saved_value(): void {
		update_option( 'cptdp_contact', array( 'email' => 'me@example.com' ) );

		$html = $this->capture(
			fn() => cptdp_contact_render_field(
				array(
					'key'         => 'email',
					'type'        => 'email',
					'placeholder' => '"><script>',
					'label_for'   => 'cptdp_contact_email',
				)
			)
		);

		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'name="cptdp_contact[email]"', $html );
		$this->assertStringContainsString( 'value="me@example.com"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_rest_settings_round_trip(): void {
		$this->acting_as( 'administrator' );

		$response = $this->rest_settings(
			array(
				'email'        => 'me@example.com',
				'whatsapp_url' => 'https://evil.example.com/x',
				'linkedin_url' => 'https://www.linkedin.com/in/me',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'me@example.com', cptdp_get_contact( 'email' ) );
		$this->assertSame( '', cptdp_get_contact( 'whatsapp_url' ) );
		$this->assertSame( 'https://www.linkedin.com/in/me', cptdp_get_contact( 'linkedin_url' ) );
	}

	public function test_rest_rejects_unknown_properties(): void {
		$this->acting_as( 'administrator' );

		$this->assertSame( 400, $this->rest_settings( array( 'email' => 'a@b.co', 'extra' => 'x' ) )->get_status() );
	}

	public function test_rest_denies_non_admins(): void {
		$this->acting_as( 'editor' );

		$this->assertSame( 403, $this->rest_settings( array( 'email' => 'a@b.co' ) )->get_status() );
	}

	/**
	 * Post contact settings through the REST settings endpoint.
	 *
	 * @param array<string, mixed> $value Value for the cptdp_contact setting.
	 */
	private function rest_settings( array $value ): WP_REST_Response {
		$GLOBALS['wp_rest_server'] = null;

		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'cptdp_contact' => $value ) ) );

		return rest_do_request( $request );
	}
}
