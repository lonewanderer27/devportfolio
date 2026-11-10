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
				'show_book_title'    => false,
				'pages_mode'         => 'auto',
				'trim_bottom'        => true,
				'featured_from_page' => true,
				'native_pdf'         => false,
				'native_pptx'        => false,
				'native_xlsx'        => false,
				'native_docx'        => false,
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
				'show_book_title'    => true,
				'pages_mode'         => 'auto',
				'trim_bottom'        => true,
				'featured_from_page' => true,
				'native_pdf'         => false,
				'native_pptx'        => false,
				'native_xlsx'        => false,
				'native_docx'        => false,
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

	public function test_the_native_viewer_formats_are_off_by_default_and_sanitised(): void {
		$this->assertSame( array( 'pdf', 'pptx', 'xlsx', 'docx' ), cptdp_documents_native_formats() );

		foreach ( array( 'native_pdf', 'native_pptx', 'native_xlsx', 'native_docx' ) as $key ) {
			$this->assertFalse( cptdp_get_documents_settings( $key ), $key );
			$this->assertTrue( cptdp_documents_sanitize( array( $key => '1' ) )[ $key ], $key );
			$this->assertFalse( cptdp_documents_sanitize( array( $key => '0' ) )[ $key ], $key );
		}

		$_POST['option_page'] = 'cptdp_documents';
		$clean                = cptdp_documents_sanitize( array( 'pages_mode' => 'auto' ) );
		$this->assertFalse( $clean['native_pdf'], 'On the settings form an unticked checkbox is not submitted.' );
		$this->assertFalse( $clean['native_pptx'] );
		$this->assertFalse( $clean['native_docx'] );
		unset( $_POST['option_page'] );

		update_option( 'cptdp_documents', array( 'native_pdf' => true ) );
		$this->assertTrue( cptdp_get_documents_settings( 'native_pdf' ) );
		$this->assertFalse( cptdp_get_documents_settings( 'native_pptx' ), 'Each format is its own switch.' );
	}

	public function test_the_old_selectable_text_setting_is_still_the_pdf_checkbox(): void {
		update_option( 'cptdp_documents', array( 'selectable_text' => true, 'trim_bottom' => false ) );

		$this->assertTrue( cptdp_get_documents_settings( 'native_pdf' ), 'An existing site keeps its choice.' );
		$this->assertTrue( cptdp_get_documents_settings( 'selectable_text' ), 'The old key reads the new one.' );
		$this->assertArrayNotHasKey( 'selectable_text', cptdp_get_documents_settings() );
		$this->assertTrue( cptdp_documents_sanitize( array( 'pages_mode' => 'server' ) )['native_pdf'], 'A save keeps it, under the new name.' );

		// A caller that still sends the old name (REST, code) changes the PDF checkbox.
		$this->assertFalse( cptdp_documents_sanitize( array( 'selectable_text' => '0' ) )['native_pdf'] );
		update_option( 'cptdp_documents', array() );
		$this->assertTrue( cptdp_documents_sanitize( array( 'selectable_text' => '1' ) )['native_pdf'] );

		// The new name wins when both are sent or stored.
		update_option( 'cptdp_documents', array( 'native_pdf' => false, 'selectable_text' => true ) );
		$this->assertFalse( cptdp_get_documents_settings( 'native_pdf' ) );
	}

	public function test_one_native_viewer_section_lists_a_checkbox_per_format_with_the_performance_warning(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wp_settings_sections, $wp_settings_fields;
		$wp_settings_sections = array();
		$wp_settings_fields   = array();
		cptdp_documents_register_sections();

		$this->assertArrayHasKey( 'cptdp_documents_native_section', $wp_settings_sections['cptdp-documents'], 'One section for the native viewer.' );
		$this->assertSame( 'Native viewer', $wp_settings_sections['cptdp-documents']['cptdp_documents_native_section']['title'] );
		$this->assertCount( 1, $wp_settings_fields['cptdp-documents']['cptdp_documents_native_section'], 'One field holding the checkboxes.' );
		$this->assertArrayNotHasKey( 'cptdp_documents_selectable_text', $wp_settings_fields['cptdp-documents']['cptdp_documents_section'], 'The old rows are gone.' );
		$this->assertArrayNotHasKey( 'cptdp_documents_browser_preview', $wp_settings_fields['cptdp-documents']['cptdp_documents_section'] );

		$intro = $this->capture( 'cptdp_documents_render_native_section' );
		$this->assertStringContainsString( 'expense of performance', $intro );
		$this->assertStringContainsString( 'text can be selected and copied', $intro );
		$this->assertStringContainsString( 'stored page images are shown instead', $intro );
		$this->assertStringContainsString( 'Nothing is sent to anyone', $intro );

		$html = $this->capture( 'cptdp_documents_render_native_formats' );
		$this->assertStringContainsString( 'id="cptdp-native-viewer"', $html, 'The warning links here.' );
		$this->assertStringContainsString( 'name="cptdp_documents[native_pdf]"', $html );
		$this->assertStringContainsString( 'name="cptdp_documents[native_pptx]"', $html );
		$this->assertStringContainsString( 'pdf.js', $html );
		$this->assertStringContainsString( 'LibreOffice', $html );
		$this->assertStringContainsString( 'name="cptdp_documents[native_xlsx]"', $html );
		$this->assertStringContainsString( 'name="cptdp_documents[native_docx]"', $html );
		$this->assertSame( 4, substr_count( $html, 'type="checkbox"' ) );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*checked=/', $html );

		update_option( 'cptdp_documents', array( 'native_pdf' => true ) );
		$on = $this->capture( 'cptdp_documents_render_native_formats' );
		$this->assertSame( 1, preg_match_all( '/<input[^>]*checked=/', $on ), 'Only the PDF box is ticked.' );
		$this->assertMatchesRegularExpression( '/native_pdf\]"[^>]*checked=/', $on );
	}

	public function tear_down(): void {
		unset( $_POST['option_page'] );

		parent::tear_down();
	}

	public function test_conversion_has_its_own_section_between_the_documents_and_native_viewer_sections(): void {
		global $wp_settings_sections, $wp_settings_fields;

		require_once ABSPATH . 'wp-admin/includes/template.php';
		$wp_settings_sections = array();
		$wp_settings_fields   = array();
		cptdp_documents_register_sections();

		$this->assertSame(
			array( 'cptdp_documents_section', 'cptdp_documents_page_images', 'cptdp_documents_conversion_section', 'cptdp_documents_native_section' ),
			array_keys( $wp_settings_sections['cptdp-documents'] ),
			'Documents, then the shared Page images, then Document conversion, then Native viewer.'
		);
		$this->assertSame( 'Document conversion', $wp_settings_sections['cptdp-documents']['cptdp_documents_conversion_section']['title'] );
		$this->assertSame(
			array( 'cptdp_documents_conversion_presentations', 'cptdp_documents_conversion_spreadsheets', 'cptdp_documents_conversion_word' ),
			array_keys( $wp_settings_fields['cptdp-documents']['cptdp_documents_conversion_section'] ),
			'One field per document family.'
		);
		$this->assertArrayNotHasKey( 'cptdp_documents_pptx_status', $wp_settings_fields['cptdp-documents']['cptdp_documents_section'], 'The status rows moved.' );
		$this->assertArrayNotHasKey( 'cptdp_documents_xlsx_status', $wp_settings_fields['cptdp-documents']['cptdp_documents_section'] );
		$this->assertSame( array( 'presentations', 'spreadsheets', 'word' ), array_keys( cptdp_documents_conversion_formats() ) );
	}

	public function test_what_is_true_of_every_format_is_said_once_and_each_row_has_only_its_own_detail(): void {
		$sentence = 'The result may differ from the original, so a PDF export is still the best option.';
		$intro    = $this->capture( 'cptdp_documents_render_conversion_section' );
		$rows     = '';

		foreach ( array_keys( cptdp_documents_conversion_formats() ) as $family ) {
			$rows .= $this->capture(
				static function () use ( $family ): void {
					cptdp_documents_render_conversion_status( array( 'family' => $family ) );
				}
			);
		}

		$this->assertSame( 1, substr_count( $intro . $rows, $sentence ), 'Said once, in the intro.' );
		$this->assertStringContainsString( $sentence, $intro );
		$this->assertStringContainsString( 'visitors never trigger it', $intro );

		$this->assertStringContainsString( 'One page per slide', $rows );
		$this->assertStringContainsString( 'animations and transitions are not exported', $rows );
		$this->assertStringContainsString( 'One page per sheet', $rows );
		$this->assertStringContainsString( 'formulas are not recalculated', $rows );
		$this->assertStringContainsString( 'tracked changes', $rows );
	}

	public function test_a_row_shows_available_with_the_version_or_unavailable_with_the_reason(): void {
		$render = function ( string $family ): string {
			return $this->capture(
				static function () use ( $family ): void {
					cptdp_documents_render_conversion_status( array( 'family' => $family ) );
				}
			);
		};

		add_filter( 'cptdp_pptx_available', '__return_false' );
		$off = $render( 'spreadsheets' );
		$this->assertStringContainsString( 'Unavailable', $off );
		$this->assertStringContainsString( 'Excel', $off, 'The reason is the family\'s own.' );
		$this->assertStringContainsString( 'One page per sheet', $off, 'The detail line stays.' );
		$this->assertStringContainsString( 'PowerPoint', $render( 'presentations' ) );

		remove_all_filters( 'cptdp_pptx_available' );
		add_filter( 'cptdp_pptx_available', '__return_true' );
		add_filter( 'cptdp_xlsx_available', '__return_true' );
		$this->assertStringContainsString( 'Available', $render( 'spreadsheets' ) );

		$this->assertSame( '', $render( 'nothing' ), 'An unknown family renders nothing.' );
	}

	public function test_a_partial_update_keeps_the_settings_it_does_not_mention(): void {
		update_option(
			'cptdp_documents',
			array(
				'show_book_title' => true,
				'pages_mode'      => 'browser',
				'trim_bottom'     => false,
				'native_pdf'      => true,
			)
		);

		$this->assertSame(
			array(
				'show_book_title'    => true,
				'pages_mode'         => 'server',
				'trim_bottom'        => false,
				'featured_from_page' => true,
				'native_pdf'         => true,
				'native_pptx'        => false,
				'native_xlsx'        => false,
				'native_docx'        => false,
			),
			cptdp_documents_sanitize( array( 'pages_mode' => 'server' ) )
		);
		$this->assertFalse( cptdp_documents_sanitize( array( 'native_pdf' => '0' ) )['native_pdf'] );
		$this->assertFalse( cptdp_documents_sanitize( array( 'native_pdf' => '0' ) )['trim_bottom'], 'Still off: not mentioned, not reset.' );
	}

	public function test_an_empty_update_changes_nothing_outside_the_form(): void {
		update_option( 'cptdp_documents', array( 'trim_bottom' => false, 'pages_mode' => 'server' ) );

		$this->assertSame( cptdp_get_documents_settings(), cptdp_documents_sanitize( array() ) );
	}

	public function test_a_rest_put_with_one_key_does_not_reset_the_others(): void {
		$this->acting_as( 'administrator' );
		update_option( 'cptdp_documents', array( 'trim_bottom' => false, 'native_pdf' => true ) );
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'cptdp_documents' => array( 'pages_mode' => 'server' ) ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$saved = cptdp_get_documents_settings();
		$this->assertSame( 'server', $saved['pages_mode'] );
		$this->assertFalse( $saved['trim_bottom'] );
		$this->assertTrue( $saved['native_pdf'] );
	}

	public function test_the_settings_form_still_reads_unticked_boxes_as_off(): void {
		update_option( 'cptdp_documents', array( 'trim_bottom' => true, 'native_pdf' => true, 'native_pptx' => true, 'show_book_title' => true ) );
		$_POST['option_page'] = 'cptdp_documents';

		$clean = cptdp_documents_sanitize( array( 'pages_mode' => 'auto' ) );

		$this->assertFalse( $clean['trim_bottom'] );
		$this->assertFalse( $clean['native_pdf'] );
		$this->assertFalse( $clean['native_pptx'] );
		$this->assertFalse( $clean['show_book_title'] );
	}

	// --- Page images, shared by Documents and Brand Collaterals (DPC-023) ----------------------------

	public function test_the_page_image_settings_have_their_own_section_named_for_both_pages(): void {
		global $wp_settings_sections, $wp_settings_fields;

		require_once ABSPATH . 'wp-admin/includes/template.php';
		$wp_settings_sections = array();
		$wp_settings_fields   = array();
		cptdp_documents_register_sections();

		$title = $wp_settings_sections['cptdp-documents']['cptdp_documents_page_images']['title'];
		$this->assertStringContainsString( 'Documents', $title );
		$this->assertStringContainsString( 'Brand Collaterals', $title );

		$this->assertSame(
			array( 'cptdp_documents_pages_mode', 'cptdp_documents_trim_bottom', 'cptdp_documents_featured_from_page' ),
			array_keys( $wp_settings_fields['cptdp-documents']['cptdp_documents_page_images'] )
		);
		$this->assertSame( array( 'cptdp_documents_show_book_title' ), array_keys( $wp_settings_fields['cptdp-documents']['cptdp_documents_section'] ), 'Only the book title stays in Documents.' );

		$intro = $this->capture( 'cptdp_documents_render_page_images_section' );
		$this->assertStringContainsString( 'id="cptdp-page-images"', $intro, 'The Page images boxes link here.' );
		$this->assertStringContainsString( 'Documents page and the Brand Collaterals page', $intro );
		$this->assertStringContainsString( 'Options for the Documents page only.', $this->capture( 'cptdp_documents_render_section' ) );
		$this->assertStringContainsString( 'document or collateral edit screen', $this->capture( 'cptdp_documents_render_pages_mode' ) );
		$this->assertStringContainsString( 'document or collateral', $this->capture( 'cptdp_documents_render_trim_bottom' ) );
	}

	public function test_the_featured_image_setting_is_on_by_default_and_a_checkbox(): void {
		$this->assertTrue( cptdp_get_documents_settings( 'featured_from_page' ) );
		$this->assertContains( 'featured_from_page', cptdp_documents_checkbox_keys() );

		$html = $this->capture( 'cptdp_documents_render_featured_from_page' );
		$this->assertStringContainsString( 'name="cptdp_documents[featured_from_page]"', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*checked=/', $html );
		$this->assertStringContainsString( 'never replaced', $html );

		update_option( 'cptdp_documents', array( 'featured_from_page' => false ) );
		$this->assertFalse( cptdp_get_documents_settings( 'featured_from_page' ) );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*checked=/', $this->capture( 'cptdp_documents_render_featured_from_page' ) );
	}

	public function test_the_featured_image_setting_is_in_rest_and_kept_by_partial_updates(): void {
		$this->acting_as( 'administrator' );
		update_option( 'cptdp_documents', array( 'featured_from_page' => false ) );
		do_action( 'rest_api_init' );

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'featured_from_page', $settings['cptdp_documents']['show_in_rest']['schema']['properties'] );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'cptdp_documents' => array( 'pages_mode' => 'server' ) ) ) );

		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertFalse( cptdp_get_documents_settings( 'featured_from_page' ), 'Not mentioned, not reset.' );

		$_POST['option_page'] = 'cptdp_documents';
		update_option( 'cptdp_documents', array( 'featured_from_page' => true ) );
		$this->assertFalse( cptdp_documents_sanitize( array( 'pages_mode' => 'auto' ) )['featured_from_page'], 'The form reads an unticked box as off.' );
	}
}
