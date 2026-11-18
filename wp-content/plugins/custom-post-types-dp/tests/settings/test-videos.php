<?php
/**
 * Videos settings tests (Settings > Videos, the mobile video viewer's switches).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Settings_Videos extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_settings_errors'] = array();
	}

	public function tear_down(): void {
		unset( $_POST['option_page'], $GLOBALS['wp_settings_errors'] );
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/** The saved option as the settings form would post it: `option_page` plus the ticked boxes. */
	private function post_form( array $ticked ): array {
		$_POST['option_page'] = 'cptdp_videos';
		$input                = array();
		foreach ( $ticked as $key ) {
			$input[ $key ] = '1';
		}
		return cptdp_videos_sanitize( $input );
	}

	public function test_defaults_match_the_table(): void {
		$expected = array(
			'mute'            => true,
			'progress'        => true,
			'scrub'           => false,
			'time'            => false,
			'volume'          => false,
			'speed'           => false,
			'fit'             => true,
			'loop'            => true,
			'double_tap_skip' => true,
		);

		$this->assertSame( $expected, cptdp_get_videos_settings() );
	}

	public function test_saved_values_merge_over_the_defaults_and_unknown_keys_are_dropped(): void {
		update_option( 'cptdp_videos', array( 'speed' => true, 'evil' => true ) );

		$settings = cptdp_get_videos_settings();

		$this->assertTrue( $settings['speed'] );
		$this->assertTrue( $settings['mute'] );
		$this->assertArrayNotHasKey( 'evil', $settings );
		$this->assertIsBool( cptdp_get_videos_settings( 'mute' ) );
	}

	public function test_scrub_implies_progress_even_when_progress_is_saved_off(): void {
		update_option( 'cptdp_videos', array( 'scrub' => true, 'progress' => false ) );

		$this->assertTrue( cptdp_get_videos_settings( 'progress' ) );
	}

	public function test_form_with_only_progress_ticked_turns_every_other_switch_off(): void {
		$clean = $this->post_form( array( 'progress' ) );

		$this->assertTrue( $clean['progress'] );
		$this->assertFalse( $clean['mute'] );
		$this->assertFalse( $clean['loop'] );
		$this->assertFalse( $clean['double_tap_skip'] );
	}

	public function test_form_with_nothing_ticked_saves_every_key_off_not_the_defaults(): void {
		$clean = $this->post_form( array() );

		$this->assertSame( array_fill_keys( array_keys( cptdp_videos_defaults() ), false ), $clean );
	}

	public function test_form_with_scrub_ticked_saves_progress_on(): void {
		$clean = $this->post_form( array( 'scrub' ) );

		$this->assertTrue( $clean['scrub'] );
		$this->assertTrue( $clean['progress'] );
	}

	public function test_partial_rest_update_keeps_the_other_saved_values(): void {
		update_option( 'cptdp_videos', array( 'mute' => false, 'time' => true ) );

		$clean = cptdp_videos_sanitize( array( 'speed' => true ) );

		$this->assertTrue( $clean['speed'] );
		$this->assertFalse( $clean['mute'] );
		$this->assertTrue( $clean['time'] );
		$this->assertTrue( $clean['loop'] );
	}

	public function test_rest_schema_lists_the_nine_keys_and_allows_no_others(): void {
		unset( $GLOBALS['wp_rest_server'] );
		cptdp_videos_register_settings();

		$schema = get_registered_settings()['cptdp_videos']['show_in_rest']['schema'];

		$this->assertSame( 'object', $schema['type'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( array_keys( cptdp_videos_mobile_options() ), array_keys( $schema['properties'] ) );
		$this->assertCount( 9, $schema['properties'] );
	}

	public function test_non_admin_cannot_read_the_settings_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		cptdp_videos_render_page();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test_settings_page_renders_one_checkbox_per_key_under_two_sections(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		cptdp_videos_register_sections();

		ob_start();
		cptdp_videos_render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Mobile video controls', $html );
		$this->assertStringContainsString( 'Behaviour', $html );
		foreach ( array_keys( cptdp_videos_mobile_options() ) as $key ) {
			$this->assertStringContainsString( 'name="cptdp_videos[' . $key . ']"', $html );
		}
		$this->assertSame( 9, substr_count( $html, 'type="checkbox"' ) );
	}

	public function test_volume_help_says_it_does_nothing_on_iphone(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		cptdp_videos_register_sections();

		ob_start();
		cptdp_videos_render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'iPhone', $html );
	}

	public function test_registered_menu_page_is_under_settings(): void {
		global $submenu;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		cptdp_videos_add_menu();

		$found = false;
		foreach ( $submenu['options-general.php'] ?? array() as $item ) {
			$found = $found || 'cptdp-videos' === $item[2];
		}
		$this->assertTrue( $found );
	}
}
