<?php
/**
 * Tests for the mobile video viewer: markup per Settings > Videos switch, the data it reads, the script and CSS
 * contracts (DPV-011, DPV-019 to DPV-023).
 *
 * Markup, scripts and CSS are checked as text, since there is no browser runner (see the browser check in the plan).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Modal_Mobile extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		if ( function_exists( 'devportfolio_memo_add_hooks' ) ) {
			devportfolio_memo_add_hooks();
		}

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/video-modal' ) ) {
			devportfolio_register_blocks();
		}

		// The block renders nothing without a published video.
		self::factory()->post->create(
			array(
				'post_type'   => 'video',
				'post_title'  => 'Sunset Reel',
				'post_status' => 'publish',
			)
		);
	}

	public function tear_down(): void {
		delete_option( 'cptdp_videos' );
		parent::tear_down();
	}

	private function render_modal(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/video-modal /-->' ) );
	}

	/** The mobile dialog's markup only. */
	private function mobile_html( string $html ): string {
		$start = strpos( $html, '<dialog class="videos-mobile"' );
		$end   = strpos( $html, '</dialog>', (int) $start );
		return false === $start || false === $end ? '' : substr( $html, $start, $end - $start );
	}

	/** A data attribute of the block root, decoded. */
	private function data_attribute( string $html, string $name ): array {
		preg_match( '/' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html, $m );
		$json = html_entity_decode( $m[1] ?? '', ENT_QUOTES );
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	/** A file of the theme, by its path from the theme root. */
	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/' . $relative );
	}

	/** Options that turn one switch on or off, with everything else at the default. */
	private function with_options( array $options ): void {
		update_option( 'cptdp_videos', $options );
	}

	// ---------------------------------------------------------------------------------------------
	// Markup
	// ---------------------------------------------------------------------------------------------

	public function test_renders_second_dialog(): void {
		$html = $this->render_modal();

		$this->assertSame( 1, substr_count( $html, '<dialog class="videos-mobile"' ) );
		$this->assertSame( 1, substr_count( $html, '<dialog class="videos-modal"' ) );
	}

	public function test_four_overlay_controls_and_no_header(): void {
		$mobile = $this->mobile_html( $this->render_modal() );

		foreach ( array( 'videos-mobile__close', 'videos-mobile__up', 'videos-mobile__down', 'videos-mobile__title', 'videos-mobile__details' ) as $class ) {
			$this->assertStringContainsString( $class, $mobile );
		}
		$this->assertStringNotContainsString( 'videos-mobile__download', $mobile );
		$this->assertStringNotContainsString( 'videos-mobile__header', $mobile );
		$this->assertStringNotContainsString( '<track', $mobile );
	}

	public function test_default_controls(): void {
		$mobile = $this->mobile_html( $this->render_modal() );

		$this->assertStringContainsString( 'videos-mobile__bigplay', $mobile );
		$this->assertStringContainsString( 'videos-mobile__mutebtn', $mobile );
		$this->assertStringContainsString( 'videos-mobile__extras', $mobile );
		$this->assertMatchesRegularExpression( '/<input class="videos-mobile__scrub"[^>]*\binert\b/', $mobile );

		foreach ( array( 'videos-mobile__time', 'videos-mobile__volbtn', 'videos-mobile__ratebtn', 'videos-mobile__fullscreen', 'videos-mobile__replay', 'videos-mobile__playpause' ) as $class ) {
			$this->assertStringNotContainsString( $class, $mobile );
		}
	}

	/**
	 * @return array<string,array{0:array,1:string,2:bool}> options, the class that must be (or not be) there, present.
	 */
	public function switch_provider(): array {
		return array(
			'time on'            => array( array( 'time' => true ), 'videos-mobile__time', true ),
			'volume on'          => array( array( 'volume' => true ), 'id="videos-mobile-volume"', true ),
			'speed on'           => array( array( 'speed' => true ), 'videos-mobile__ratebtn', true ),
			'fit off'            => array( array( 'fit' => false ), 'videos-mobile__fit', false ),
			'mute off'           => array( array( 'mute' => false, 'fit' => false ), 'videos-mobile__extras', false ),
			'loop off'           => array( array( 'loop' => false ), 'videos-mobile__replay', true ),
			'scrub on'           => array( array( 'scrub' => true ), '<input class="videos-mobile__scrub" type="range" min="0" max="0" step="any" value="0" aria-label="Seek">', true ),
			'scrub on, progress' => array( array( 'scrub' => true, 'progress' => false ), 'videos-mobile__scrub', true ),
		);
	}

	/**
	 * @dataProvider switch_provider
	 */
	public function test_each_switch_renders_its_control( array $options, string $needle, bool $present ): void {
		$this->with_options( $options );
		$mobile = $this->mobile_html( $this->render_modal() );

		if ( $present ) {
			$this->assertStringContainsString( $needle, $mobile );
		} else {
			$this->assertStringNotContainsString( $needle, $mobile );
		}
	}

	public function test_volume_speed_and_fit_have_their_parts(): void {
		$this->with_options( array( 'speed' => true, 'volume' => true, 'fit' => true ) );
		$mobile = $this->mobile_html( $this->render_modal() );

		$this->assertSame( 6, substr_count( $mobile, 'videos-mobile__rateopt' ) );
		$this->assertStringContainsString( 'videos-mobile__fit', $mobile );
	}

	public function test_controls_list_matches_markup(): void {
		$map = array(
			'bigplay'    => 'videos-mobile__bigplay',
			'replay'     => 'videos-mobile__replay',
			'progress'   => 'videos-mobile__scrub',
			'time'       => 'videos-mobile__time',
			'mute'       => 'videos-mobile__mutebtn',
			'volume'     => 'videos-mobile__volbtn',
			'speed'      => 'videos-mobile__ratebtn',
			'fit'        => 'videos-mobile__fit',
		);
		$sets = array(
			array(),
			array( 'time' => true, 'volume' => true, 'speed' => true, 'fit' => true, 'loop' => false, 'scrub' => true ),
			array( 'mute' => false, 'progress' => false ),
		);

		foreach ( $sets as $options ) {
			$this->with_options( $options );
			$html     = $this->render_modal();
			$mobile   = $this->mobile_html( $html );
			$controls = $this->data_attribute( $html, 'data-mobile-options' )['controls'] ?? array();

			foreach ( $map as $key => $class ) {
				if ( in_array( $key, $controls, true ) ) {
					$this->assertStringContainsString( $class, $mobile, "$key listed but not rendered" );
				} else {
					$this->assertStringNotContainsString( $class, $mobile, "$key rendered but not listed" );
				}
			}
		}
	}

	public function test_close_is_the_first_control_and_labelled(): void {
		$mobile = $this->mobile_html( $this->render_modal() );

		$close = strpos( $mobile, 'videos-mobile__close' );
		$rail  = strpos( $mobile, 'videos-mobile__rail' );
		$this->assertNotFalse( $close );
		$this->assertLessThan( $rail, $close );
		$this->assertStringContainsString( 'aria-label="Close video"', $mobile );
	}

	public function test_title_and_details_open_a_dialog_and_the_rail_has_aria_disabled(): void {
		$mobile = $this->mobile_html( $this->render_modal() );

		$this->assertMatchesRegularExpression( '/<button class="videos-mobile__title"[^>]*aria-haspopup="dialog"/', $mobile );
		$this->assertMatchesRegularExpression( '/<button class="videos-mobile__details[^"]*"[^>]*aria-haspopup="dialog"/', $mobile );
		$this->assertMatchesRegularExpression( '/videos-mobile__up[^>]*aria-disabled/', $mobile );
		$this->assertMatchesRegularExpression( '/videos-mobile__down[^>]*aria-disabled/', $mobile );
	}

	public function test_rotate_hint_text_is_hidden_until_needed(): void {
		$mobile = $this->mobile_html( $this->render_modal() );

		$this->assertMatchesRegularExpression( '/<span class="videos-mobile__pill videos-mobile__hint" hidden>.*Rotate for full screen/s', $mobile );
	}

	// ---------------------------------------------------------------------------------------------
	// Data
	// ---------------------------------------------------------------------------------------------

	public function test_mobile_strings_are_present(): void {
		$strings = $this->data_attribute( $this->render_modal(), 'data-mobile-strings' );

		foreach ( array( 'playerFor', 'fit', 'zoom', 'stretch', 'results', 'madeWith', 'openOriginal', 'handle' ) as $key ) {
			$this->assertArrayHasKey( $key, $strings );
		}
	}

	public function test_data_mobile_options_by_default(): void {
		$options = $this->data_attribute( $this->render_modal(), 'data-mobile-options' );

		$this->assertSame(
			array(
				'loop'          => true,
				'doubleTapSkip' => true,
				'unmuteOnPlay'  => false,
				'scrub'         => false,
				'controls'      => array( 'bigplay', 'progress', 'mute', 'fit' ),
			),
			$options
		);
	}

	public function test_mute_off_means_unmute_on_play_and_no_mute_control(): void {
		$this->with_options( array( 'mute' => false ) );
		$options = $this->data_attribute( $this->render_modal(), 'data-mobile-options' );

		$this->assertTrue( $options['unmuteOnPlay'] );
		$this->assertNotContains( 'mute', $options['controls'] );
	}

	public function test_the_volume_slider_replaces_the_mute_button(): void {
		// Mute is on by default; with the slider on, the slider does the muting and the button is not rendered.
		$this->with_options( array( 'volume' => true ) );
		$html   = $this->render_modal();
		$mobile = $this->mobile_html( $html );

		$this->assertStringContainsString( 'videos-mobile__volbtn', $mobile );
		$this->assertStringNotContainsString( 'videos-mobile__mutebtn', $mobile );
		$this->assertNotContains( 'mute', $this->data_attribute( $html, 'data-mobile-options' )['controls'] );
	}

	public function test_double_tap_skip_off_is_passed_to_the_script(): void {
		$this->with_options( array( 'double_tap_skip' => false ) );
		$options = $this->data_attribute( $this->render_modal(), 'data-mobile-options' );

		$this->assertFalse( $options['doubleTapSkip'] );
	}

	public function test_theme_defaults_match_the_plugin_switches(): void {
		$theme  = devportfolio_video_mobile_options();
		$plugin = cptdp_get_videos_settings();

		$this->assertSame( array_keys( cptdp_videos_mobile_options() ), array_keys( $theme ) );
		foreach ( array_keys( cptdp_videos_mobile_options() ) as $key ) {
			$this->assertSame( (bool) $plugin[ $key ], $theme[ $key ], $key );
		}
	}

	// ---------------------------------------------------------------------------------------------
	// Script and style contracts
	// ---------------------------------------------------------------------------------------------

	public function test_engine_and_controls_are_separate(): void {
		$index = $this->read( 'blocks/video-modal/controls/index.js' );
		foreach ( array( 'bigplay', 'playpause', 'replay', 'progress', 'time', 'mute', 'volume', 'speed', 'fullscreen', 'fit' ) as $key ) {
			$this->assertFileExists( $this->theme_dir() . "/blocks/video-modal/controls/$key.js" );
			$this->assertStringContainsString( "'./$key.js'", $index );
		}

		$player = $this->read( 'blocks/video-modal/player.js' );
		// 'fullscreen' stays out of this list: the engine's embed iframe allows it (`allow`), a button does not.
		foreach ( array( 'scrub', 'volbtn', 'ratebtn', 'popover' ) as $word ) {
			$this->assertStringNotContainsString( $word, $player );
		}
		$this->assertStringContainsString( "prefix = 'videos-modal'", $player );
		$this->assertStringContainsString( 'clickToggles', $player );
		$this->assertStringContainsString( 'unmuteOnPlay', $player );

		$mobile = $this->read( 'blocks/video-modal/mobile.js' );
		$this->assertStringContainsString( './controls/index.js', $mobile );
		$this->assertStringContainsString( './player.js', $mobile );
		$this->assertDoesNotMatchRegularExpression( "#from '\./controls/(?!index\.js)[a-z]+\.js'#", $mobile );

		$view = $this->read( 'blocks/video-modal/view.js' );
		$this->assertStringContainsString( 'mountControls(', $view );
		$this->assertStringContainsString( 'DESKTOP_CONTROLS', $view );
	}

	public function test_view_script_contract(): void {
		$view = $this->read( 'blocks/video-modal/view.js' );

		$this->assertStringContainsString( 'createMobileVideo', $view );
		$this->assertStringContainsString( 'createRouting', $view );
		$this->assertSame( 1, substr_count( $view, "'popstate'" ) );
		$this->assertMatchesRegularExpression( "/\\},\\n\\t\\ttrue\\n\\t\\);/", $view, 'popstate is a capture-phase listener' );
		$this->assertStringNotContainsString( 'DOMContentLoaded', $view );
	}

	public function test_mobile_script_contract(): void {
		$mobile = $this->read( 'blocks/video-modal/mobile.js' );

		$this->assertStringContainsString( 'createSheetController', $mobile );
		$this->assertStringNotContainsString( 'DOMContentLoaded', $mobile );
		$this->assertStringNotContainsString( 'localStorage', $mobile );
		$this->assertSame( 1, preg_match_all( '/\bimport\s*\(/', $mobile ) );
		$this->assertStringContainsString( "import( './details-sheet.js' )", $mobile );
	}

	public function test_css_contract(): void {
		$css = $this->read( 'blocks/video-modal/style.css' );
		$this->assertStringContainsString( '@media (min-width: 701px)', $css );

		$routing = $this->read( 'blocks/shared/routing.js' );
		preg_match( "/SIDEWAYS_QUERY = '([^']+)'/", $routing, $m );
		$this->assertNotEmpty( $m[1] ?? '' );
		$this->assertStringContainsString( $m[1], $css );

		$this->assertStringContainsString( '.videos-mobile__scrim', $css );
		$this->assertStringContainsString( '--wp--custom--modal--scrim', $css );
		$this->assertStringContainsString( 'prefers-reduced-motion', $css );

		$stripped = preg_replace( '#/\*.*?\*/#s', '', $css );
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $stripped, $rules, PREG_SET_ORDER );
		foreach ( $rules as $rule ) {
			if ( str_starts_with( trim( $rule[1] ), '.videos-mobile' ) ) {
				$this->assertStringNotContainsString( '--grad', $rule[2], trim( $rule[1] ) );
			}
		}
	}

	public function test_scrim_token(): void {
		$theme = json_decode( $this->read( 'theme.json' ), true );

		$this->assertIsArray( $theme );
		$this->assertNotEmpty( $theme['settings']['custom']['modal']['scrim'] ?? '' );
	}

	public function test_sheet_shell_css_lists_the_videos_mobile_selectors(): void {
		$css = $this->read( 'blocks/document-modal/style.css' );

		$this->assertStringContainsString( '.videos-mobile__sheet', $css );
		$this->assertStringContainsString( '.videos-mobile__dim', $css );
	}

	public function test_viewer_style_loads_on_the_video_archive(): void {
		$this->go_to( get_post_type_archive_link( 'video' ) );
		devportfolio_enqueue_page_styles();

		$this->assertTrue( wp_style_is( 'devportfolio-viewer', 'enqueued' ) );
		wp_dequeue_style( 'devportfolio-viewer' );
	}
}
