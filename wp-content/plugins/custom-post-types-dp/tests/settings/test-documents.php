<?php
/**
 * Documents settings tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Documents_Settings extends CPTDP_Test_Case {

	public function test_defaults_hide_the_book_title(): void {
		$this->assertSame(
			array(
				'show_book_title' => false,
				'pages_mode'      => 'auto',
				'trim_bottom'     => true,
				'selectable_text' => false,
			),
			cptdp_get_documents_settings()
		);
		$this->assertFalse( cptdp_get_documents_settings( 'show_book_title' ) );
		$this->assertFalse( cptdp_get_documents_settings( 'unknown' ) );
	}

	public function test_saved_value_is_read_and_unknown_keys_dropped(): void {
		update_option(
			'cptdp_documents',
			array(
				'show_book_title' => true,
				'evil'            => 'x',
			)
		);

		$this->assertSame(
			array(
				'show_book_title' => true,
				'pages_mode'      => 'auto',
				'trim_bottom'     => true,
				'selectable_text' => false,
			),
			cptdp_get_documents_settings()
		);
		$this->assertTrue( cptdp_get_documents_settings( 'show_book_title' ) );
	}

	public function test_corrupt_option_falls_back_to_defaults(): void {
		update_option( 'cptdp_documents', 'garbage' );

		$this->assertFalse( cptdp_get_documents_settings( 'show_book_title' ) );
	}

	public function test_sanitize(): void {
		$this->assertTrue( cptdp_documents_sanitize( array( 'show_book_title' => '1' ) )['show_book_title'] );
		$this->assertFalse( cptdp_documents_sanitize( array( 'show_book_title' => '0' ) )['show_book_title'] );
		$this->assertSame( cptdp_documents_defaults(), cptdp_documents_sanitize( array() ) );
		$this->assertSame( cptdp_documents_defaults(), cptdp_documents_sanitize( 'x' ) );
	}

	public function test_settings_page_requires_manage_options_and_registers_under_settings(): void {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		cptdp_documents_add_menu();

		$slugs = isset( $submenu['options-general.php'] ) ? wp_list_pluck( $submenu['options-general.php'], 2 ) : array();
		$this->assertContains( 'cptdp-documents', $slugs );
	}

	public function test_checkbox_reflects_the_saved_value_and_is_off_by_default(): void {
		ob_start();
		cptdp_documents_render_show_book_title();
		$this->assertStringNotContainsString( 'checked', ob_get_clean() );

		update_option( 'cptdp_documents', array( 'show_book_title' => true ) );
		ob_start();
		cptdp_documents_render_show_book_title();
		$this->assertStringContainsString( 'checked', ob_get_clean() );
	}

	public function test_pages_mode_defaults_to_auto_and_only_allows_known_values(): void {
		$this->assertSame( array( 'auto', 'server', 'browser' ), cptdp_documents_pages_modes() );
		$this->assertSame( 'auto', cptdp_get_documents_settings( 'pages_mode' ) );
		$this->assertSame( 'browser', cptdp_documents_sanitize( array( 'pages_mode' => 'browser' ) )['pages_mode'] );
		$this->assertSame( 'auto', cptdp_documents_sanitize( array( 'pages_mode' => 'nope' ) )['pages_mode'] );
	}

	public function test_pages_mode_select_lists_every_mode_and_marks_the_saved_one(): void {
		update_option( 'cptdp_documents', array( 'pages_mode' => 'server' ) );

		ob_start();
		cptdp_documents_render_pages_mode();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="cptdp_documents[pages_mode]"', $html );
		$this->assertSame( 3, substr_count( $html, '<option' ) );
		$this->assertMatchesRegularExpression( '/value="server"\s+selected/', $html );
	}

	public function test_trim_bottom_defaults_on_and_is_sanitised(): void {
		$this->assertTrue( cptdp_get_documents_settings( 'trim_bottom' ) );
		$this->assertTrue( cptdp_documents_sanitize( array( 'trim_bottom' => '1' ) )['trim_bottom'] );
		$this->assertFalse( cptdp_documents_sanitize( array( 'trim_bottom' => '0' ) )['trim_bottom'] );
		$_POST['option_page'] = 'cptdp_documents';
		$this->assertFalse( cptdp_documents_sanitize( array( 'show_book_title' => '1' ) )['trim_bottom'], 'On the settings form an unticked checkbox is not submitted.' );
		unset( $_POST['option_page'] );

		update_option( 'cptdp_documents', array( 'trim_bottom' => false ) );
		$this->assertFalse( cptdp_get_documents_settings( 'trim_bottom' ) );
	}

	public function test_trim_bottom_checkbox_reflects_the_saved_value(): void {
		ob_start();
		cptdp_documents_render_trim_bottom();
		$on = ob_get_clean();
		$this->assertStringContainsString( 'name="cptdp_documents[trim_bottom]"', $on );
		$this->assertMatchesRegularExpression( '/<input[^>]*type="checkbox"[^>]*checked=/', $on );

		update_option( 'cptdp_documents', array( 'trim_bottom' => false ) );
		ob_start();
		cptdp_documents_render_trim_bottom();
		$off = ob_get_clean();
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*checked=/', $off );
	}

	public function test_selectable_text_is_off_by_default_and_is_sanitised(): void {
		$this->assertFalse( cptdp_get_documents_settings( 'selectable_text' ) );
		$this->assertTrue( cptdp_documents_sanitize( array( 'selectable_text' => '1' ) )['selectable_text'] );
		$this->assertFalse( cptdp_documents_sanitize( array( 'selectable_text' => '0' ) )['selectable_text'] );
		$_POST['option_page'] = 'cptdp_documents';
		$this->assertFalse( cptdp_documents_sanitize( array( 'pages_mode' => 'auto' ) )['selectable_text'], 'On the settings form an unticked checkbox is not submitted.' );
		unset( $_POST['option_page'] );

		update_option( 'cptdp_documents', array( 'selectable_text' => true ) );
		$this->assertTrue( cptdp_get_documents_settings( 'selectable_text' ) );
	}

	public function test_selectable_text_checkbox_warns_that_it_can_be_laggy(): void {
		ob_start();
		cptdp_documents_render_selectable_text();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="cptdp_documents[selectable_text]"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*checked=/', $html );
		$this->assertStringContainsString( 'This can make the viewer laggy.', $html );
		$this->assertStringContainsString( 'pdf.js', $html );
		$this->assertStringContainsString( 'fall back to the images', $html );

		update_option( 'cptdp_documents', array( 'selectable_text' => true ) );
		ob_start();
		cptdp_documents_render_selectable_text();
		$this->assertMatchesRegularExpression( '/<input[^>]*checked=/', ob_get_clean() );
	}

	public function tear_down(): void {
		unset( $_POST['option_page'] );

		parent::tear_down();
	}

	public function test_a_partial_update_keeps_the_settings_it_does_not_mention(): void {
		update_option(
			'cptdp_documents',
			array(
				'show_book_title' => true,
				'pages_mode'      => 'browser',
				'trim_bottom'     => false,
				'selectable_text' => true,
			)
		);

		$this->assertSame(
			array(
				'show_book_title' => true,
				'pages_mode'      => 'server',
				'trim_bottom'     => false,
				'selectable_text' => true,
			),
			cptdp_documents_sanitize( array( 'pages_mode' => 'server' ) )
		);
		$this->assertFalse( cptdp_documents_sanitize( array( 'selectable_text' => '0' ) )['selectable_text'] );
		$this->assertFalse( cptdp_documents_sanitize( array( 'selectable_text' => '0' ) )['trim_bottom'], 'Still off: not mentioned, not reset.' );
	}

	public function test_an_empty_update_changes_nothing_outside_the_form(): void {
		update_option( 'cptdp_documents', array( 'trim_bottom' => false, 'pages_mode' => 'server' ) );

		$this->assertSame( cptdp_get_documents_settings(), cptdp_documents_sanitize( array() ) );
	}

	public function test_a_rest_put_with_one_key_does_not_reset_the_others(): void {
		$this->acting_as( 'administrator' );
		update_option( 'cptdp_documents', array( 'trim_bottom' => false, 'selectable_text' => true ) );
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'cptdp_documents' => array( 'pages_mode' => 'server' ) ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$saved = cptdp_get_documents_settings();
		$this->assertSame( 'server', $saved['pages_mode'] );
		$this->assertFalse( $saved['trim_bottom'] );
		$this->assertTrue( $saved['selectable_text'] );
	}

	public function test_the_settings_form_still_reads_unticked_boxes_as_off(): void {
		update_option( 'cptdp_documents', array( 'trim_bottom' => true, 'selectable_text' => true, 'show_book_title' => true ) );
		$_POST['option_page'] = 'cptdp_documents';

		$clean = cptdp_documents_sanitize( array( 'pages_mode' => 'auto' ) );

		$this->assertFalse( $clean['trim_bottom'] );
		$this->assertFalse( $clean['selectable_text'] );
		$this->assertFalse( $clean['show_book_title'] );
	}
}
