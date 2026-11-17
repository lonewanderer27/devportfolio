<?php
/**
 * Tests for the desktop video modal: helpers, payload and deeplink redirect (DPV-009 to DPV-016, DPV-024).
 *
 * Markup and scripts are checked as text or with DOMDocument, since there is no browser runner.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Modal extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// The test library restores hooks after every test: re-add the memo's clearing hooks (a no-op when present).
		if ( function_exists( 'devportfolio_memo_add_hooks' ) ) {
			devportfolio_memo_add_hooks();
		}

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/video-modal' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Term ID of a term, created when missing.
	 */
	private function term( string $name, string $taxonomy ): int {
		$found = term_exists( $name, $taxonomy );
		if ( $found ) {
			return (int) ( is_array( $found ) ? $found['term_id'] : $found );
		}
		return (int) wp_insert_term( $name, $taxonomy )['term_id'];
	}

	/**
	 * A video attachment of the given mime type.
	 */
	private function attachment( string $mime = 'video/mp4' ): int {
		return (int) self::factory()->attachment->create(
			array(
				'post_mime_type' => $mime,
				'file'           => 'sample.mp4',
			)
		);
	}

	/**
	 * Create a video.
	 *
	 * @param array $args {
	 *     title, status, order, category, ratio (slug), file (bool), embed (url), post_url (string), length (int),
	 *     stats (array of [label, value]).
	 * }
	 * @return int Post ID.
	 */
	private function make_video( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'video',
				'post_title'   => $args['title'] ?? 'Sunset Reel',
				'post_status'  => $args['status'] ?? 'publish',
				'menu_order'   => $args['order'] ?? 0,
				'post_content' => '',
			)
		);

		if ( isset( $args['category'] ) ) {
			wp_set_object_terms( $post_id, array( $this->term( $args['category'], 'video_category' ) ), 'video_category' );
		}
		if ( isset( $args['ratio'] ) ) {
			wp_set_object_terms( $post_id, array( $this->term( $args['ratio'], 'aspect_ratio' ) ), 'aspect_ratio' );
		}
		if ( ! empty( $args['file'] ) ) {
			update_post_meta( $post_id, 'cptdp_video_file', $this->attachment() );
		}
		if ( isset( $args['embed'] ) ) {
			update_post_meta( $post_id, 'cptdp_embed_url', $args['embed'] );
		}
		if ( isset( $args['post_url'] ) ) {
			update_post_meta( $post_id, 'cptdp_post_url', $args['post_url'] );
		}
		if ( isset( $args['length'] ) ) {
			update_post_meta( $post_id, 'cptdp_length', $args['length'] );
		}
		if ( isset( $args['stats'] ) ) {
			update_post_meta( $post_id, 'cptdp_post_statistics', array_map( fn( $row ) => array( 'label' => $row[0], 'value' => $row[1] ), $args['stats'] ) );
		}

		return $post_id;
	}

	// ---------------------------------------------------------------------------------------------
	// DPV-015 length, DPV-012 shape, DPV-010 embed.
	// ---------------------------------------------------------------------------------------------

	public function test_length_label_formats_minutes_and_hours(): void {
		$this->assertSame( '', devportfolio_video_length_label( 0 ) );
		$this->assertSame( '0:28', devportfolio_video_length_label( 28 ) );
		$this->assertSame( '1:15', devportfolio_video_length_label( 75 ) );
		$this->assertSame( '59:59', devportfolio_video_length_label( 3599 ) );
		$this->assertSame( '1:00:00', devportfolio_video_length_label( 3600 ) );
		$this->assertSame( '1:02:05', devportfolio_video_length_label( 3725 ) );
		$this->assertSame( '', devportfolio_video_length_label( 'abc' ) );
	}

	public function test_shape_label_maps_known_slugs_only(): void {
		$this->assertSame( '9:16', devportfolio_video_shape_label( '9x16' ) );
		$this->assertSame( '1:1', devportfolio_video_shape_label( '1x1' ) );
		$this->assertSame( '16:9', devportfolio_video_shape_label( '16x9' ) );
		$this->assertSame( '4:3', devportfolio_video_shape_label( '4x3' ) );
		$this->assertSame( '', devportfolio_video_shape_label( 'foo' ) );
	}

	public function test_embed_src_accepts_youtube_and_vimeo_pages_only(): void {
		$yt = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1';
		$this->assertSame( $yt, devportfolio_video_embed_src( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
		$this->assertSame( $yt, devportfolio_video_embed_src( 'https://youtu.be/dQw4w9WgXcQ' ) );
		$this->assertSame( $yt, devportfolio_video_embed_src( 'https://www.youtube.com/shorts/dQw4w9WgXcQ' ) );
		$this->assertSame( 'https://player.vimeo.com/video/76979871?playsinline=1', devportfolio_video_embed_src( 'https://vimeo.com/76979871' ) );

		$this->assertSame( '', devportfolio_video_embed_src( 'https://evil.test/watch?v=dQw4w9WgXcQ' ) );
		$this->assertSame( '', devportfolio_video_embed_src( 'javascript:alert(1)' ) );
		$this->assertSame( '', devportfolio_video_embed_src( '' ) );
		$this->assertSame( '', devportfolio_video_embed_src( 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Payload (DPV-009 to DPV-016).
	// ---------------------------------------------------------------------------------------------

	public function test_payload_has_every_key_the_script_reads(): void {
		$id      = $this->make_video( array( 'category' => 'Short Form', 'ratio' => '9x16', 'length' => 28 ) );
		$payload = devportfolio_video_modal_payload( $id );

		foreach ( array( 'id', 'slug', 'title', 'description', 'tools', 'category', 'categories', 'ratio', 'pane', 'shape', 'stats', 'length', 'fileUrl', 'embedSrc', 'postUrl', 'poster' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, $key );
		}
		$this->assertSame( 'Sunset Reel', $payload['title'] );
		$this->assertSame( array( 'short-form' ), $payload['categories'] );
	}

	public function test_pane_is_tall_only_for_9x16(): void {
		$this->assertSame( 'tall', devportfolio_video_modal_payload( $this->make_video( array( 'ratio' => '9x16' ) ) )['pane'] );
		$this->assertSame( 'wide', devportfolio_video_modal_payload( $this->make_video( array( 'ratio' => '1x1' ) ) )['pane'] );
		$this->assertSame( 'wide', devportfolio_video_modal_payload( $this->make_video( array( 'ratio' => '16x9' ) ) )['pane'] );
		$this->assertSame( 'wide', devportfolio_video_modal_payload( $this->make_video( array( 'ratio' => '4x3' ) ) )['pane'] );
		$this->assertSame( 'wide', devportfolio_video_modal_payload( $this->make_video() )['pane'] );
	}

	public function test_shape_length_and_stats_follow_the_saved_data(): void {
		$id      = $this->make_video(
			array(
				'ratio'  => '9x16',
				'length' => 28,
				'stats'  => array( array( 'Views', '1.2M' ), array( 'Likes', '40k' ) ),
			)
		);
		$payload = devportfolio_video_modal_payload( $id );

		$this->assertSame( '9:16', $payload['shape'] );
		$this->assertSame( '0:28', $payload['length'] );
		$this->assertSame(
			array(
				array( 'label' => 'Views', 'value' => '1.2M' ),
				array( 'label' => 'Likes', 'value' => '40k' ),
			),
			$payload['stats']
		);
	}

	public function test_non_video_post_has_no_payload(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$this->assertNull( devportfolio_video_modal_payload( $post_id ) );
	}

	public function test_post_url_must_be_http_or_https(): void {
		$this->assertSame( '', devportfolio_video_modal_payload( $this->make_video( array( 'post_url' => 'javascript:alert(1)' ) ) )['postUrl'] );
		$this->assertSame( '', devportfolio_video_modal_payload( $this->make_video( array( 'post_url' => '' ) ) )['postUrl'] );
		$this->assertSame(
			'https://example.test/post/1',
			devportfolio_video_modal_payload( $this->make_video( array( 'post_url' => 'https://example.test/post/1' ) ) )['postUrl']
		);
	}

	public function test_uploaded_file_wins_over_the_embed(): void {
		$id      = $this->make_video( array( 'file' => true, 'embed' => 'https://youtu.be/dQw4w9WgXcQ' ) );
		$payload = devportfolio_video_modal_payload( $id );

		$this->assertNotSame( '', $payload['fileUrl'] );
		$this->assertSame( '', $payload['embedSrc'] );
	}

	public function test_embed_is_used_when_there_is_no_file(): void {
		$id      = $this->make_video( array( 'embed' => 'https://youtu.be/dQw4w9WgXcQ' ) );
		$payload = devportfolio_video_modal_payload( $id );

		$this->assertSame( '', $payload['fileUrl'] );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1', $payload['embedSrc'] );
	}

	// ---------------------------------------------------------------------------------------------
	// DPV-024 deeplink redirect.
	// ---------------------------------------------------------------------------------------------

	public function test_published_video_redirects_to_the_deeplink(): void {
		$id = $this->make_video( array( 'title' => 'Sunset Reel' ) );
		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			add_query_arg( 'video_slug', 'sunset-reel', get_post_type_archive_link( 'video' ) ),
			devportfolio_video_single_redirect_url()
		);
	}

	public function test_draft_video_is_not_redirected(): void {
		$id = $this->make_video( array( 'status' => 'draft' ) );
		$this->go_to( get_permalink( $id ) );

		$this->assertSame( '', devportfolio_video_single_redirect_url() );
	}

	// ---------------------------------------------------------------------------------------------
	// Render, template, CSS and script contracts (DPV-009, DPV-015, DPV-016).
	// ---------------------------------------------------------------------------------------------

	private function render_modal(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/video-modal /-->' ) );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/video-modal/style.css' );
	}

	private function js(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/video-modal/view.js' );
	}

	public function test_render_is_empty_without_videos(): void {
		$this->assertSame( '', $this->render_modal() );
	}

	public function test_render_outputs_the_dialog_with_payload_in_grid_order(): void {
		$second = $this->make_video( array( 'title' => 'Beta Reel', 'order' => 2 ) );
		$first  = $this->make_video( array( 'title' => 'Alpha Reel', 'order' => 1 ) );
		$third  = $this->make_video( array( 'title' => 'Gamma Reel', 'order' => 3 ) );

		$html = $this->render_modal();

		$this->assertStringContainsString( 'dialog class="videos-modal"', $html );
		$this->assertStringContainsString( 'class="videos-modal__data"', $html );
		$this->assertStringContainsString( '>Results<', $html );
		$this->assertStringContainsString( '>Made with<', $html );
		$this->assertStringContainsString( '>Next up<', $html );
		$this->assertStringContainsString( 'videos-modal__close', $html );
		$this->assertStringContainsString( 'videos-modal__copy', $html );
		$this->assertMatchesRegularExpression( '#class="videos-modal__original"[^>]* hidden#', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'download', $html );

		preg_match( '#<script type="application/json" class="videos-modal__data">(.*?)</script>#s', $html, $m );
		$data = json_decode( $m[1], true );
		$this->assertSame( array( $first, $second, $third ), $data['order'] );
	}

	public function test_script_payload_stays_valid_json_when_a_title_contains_script_close(): void {
		$id = $this->make_video( array( 'title' => 'Clip &lt;/script&gt; Reel' ) );

		$html = $this->render_modal();

		$this->assertSame( 1, substr_count( $html, '</script>' ), 'Only the closing tag of the payload script may appear.' );
		preg_match( '#<script type="application/json" class="videos-modal__data">(.*?)</script>#s', $html, $m );
		$data = json_decode( $m[1], true );
		$this->assertNotNull( $data, 'The payload must decode.' );
		$this->assertSame( 'Clip </script> Reel', $data['items'][ (string) $id ]['title'] );
	}

	public function test_template_places_the_modal_once_after_the_grid(): void {
		$template = (string) file_get_contents( $this->theme_dir() . '/templates/archive-video.html' );

		$this->assertSame( 1, substr_count( $template, 'wp:devportfolio/video-modal' ) );
		$this->assertGreaterThan(
			strpos( $template, 'wp:devportfolio/video-grid' ),
			strpos( $template, 'wp:devportfolio/video-modal' )
		);
	}

	public function test_functions_register_the_block(): void {
		$functions = (string) file_get_contents( $this->theme_dir() . '/functions.php' );
		$this->assertStringContainsString( "register_block_type( __DIR__ . '/blocks/video-modal' );", $functions );
	}

	public function test_css_has_the_pane_sizes_and_breakpoints(): void {
		$css = $this->css();

		$this->assertStringContainsString( '.videos-modal__panel', $css );
		$this->assertStringContainsString( 'minmax( 0, 1fr ) var( --vm-side )', $css );
		$this->assertStringContainsString( '( 100dvh - 48px ) * 9 / 16', $css );
		$this->assertStringContainsString( 'height: calc( 100dvh - 48px )', $css );
		$this->assertStringContainsString( 'repeat( 3, minmax( 0, 1fr ) )', $css );
		$this->assertStringContainsString( 'aspect-ratio: 9 / 16', $css );
		$this->assertStringContainsString( 'rgb( 0 0 0 / 0.45 )', $css );
		$this->assertStringContainsString( '@media (max-width: 700px), (pointer: coarse) and (orientation: landscape) and (max-height: 520px)', $css );
		$this->assertStringContainsString( '.videos-modal__stage > video', $css );
		$this->assertMatchesRegularExpression( '/\.videos-modal__stage > video,\s*\.videos-modal__stage > iframe \{[^}]*object-fit: contain/', $css );
	}

	public function test_css_has_no_collateral_prefix_or_serif_font(): void {
		$css = $this->css();

		$this->assertStringNotContainsString( 'collaterals', $css );
		$this->assertStringNotContainsString( 'Georgia', $css );
	}

	public function test_script_imports_the_shared_modules_and_keeps_the_deeplink_rules(): void {
		$js = $this->js();

		$this->assertStringContainsString( "import { nextItems } from '../shared/next-items.js';", $js );
		$this->assertStringContainsString( "import { linkFor, copyText } from '../shared/copy-link.js';", $js );
		$this->assertStringContainsString( "import { createRouting } from '../shared/routing.js';", $js );
		$this->assertStringContainsString( "import { bindOpen } from '../shared/modal-trigger.js';", $js );
		$this->assertStringContainsString( 'sameCategoryFirst: true', $js );
		$this->assertStringContainsString( "bindOpen( document, 'data-video-id'", $js );
		$this->assertStringContainsString( "video.removeAttribute( 'src' )", (string) file_get_contents( $this->theme_dir() . '/blocks/video-modal/player.js' ) );
		$this->assertStringContainsString( 'replaceState', $js );
		$this->assertStringContainsString( "'video_slug'", $js );
		$this->assertStringNotContainsString( 'innerHTML', $js );
	}

	// ---------------------------------------------------------------------------------------------
	// DPV-017 / DPV-018 / DPV-029: the custom player (markup, strings, brand styles, script).
	// ---------------------------------------------------------------------------------------------

	public function test_player_markup_has_no_captions_and_the_status_line(): void {
		$this->make_video( array( 'file' => true ) );

		$html = $this->render_modal();

		$this->assertStringContainsString( 'videos-modal__bar', $html );
		$this->assertStringContainsString( 'videos-modal__scrub', $html );
		$this->assertStringContainsString( 'videos-modal__bigplay', $html );
		$this->assertStringContainsString( 'videos-modal__state', $html );
		$this->assertStringNotContainsString( '<track', $html );
		$this->assertStringNotContainsString( 'captions', $html );
		$this->assertStringNotContainsString( 'controls', $html );
		$this->assertStringNotContainsString( 'videos-modal__nopreview', $html );
	}

	public function test_player_markup_has_the_volume_rate_and_fullscreen_controls(): void {
		$this->make_video( array( 'file' => true ) );

		$html = $this->render_modal();

		$this->assertStringContainsString( 'videos-modal__volume', $html );
		$this->assertStringContainsString( 'videos-modal__fullscreen', $html );
		$this->assertStringContainsString( 'videos-modal__rate', $html );
		preg_match( '#<select class="videos-modal__rate"[^>]*>(.*?)</select>#s', $html, $m );
		$this->assertNotEmpty( $m );
		preg_match_all( '#<option value="([^"]+)"( selected)?>#', $m[1], $options, PREG_SET_ORDER );
		$this->assertSame( array( '0.5', '0.75', '1', '1.25', '1.5', '2' ), array_column( $options, 1 ) );
		$selected = array_filter( $options, fn( $o ) => ! empty( $o[2] ) );
		$this->assertSame( array( '1' ), array_column( $selected, 1 ) );
	}

	public function test_player_data_state_starts_paused(): void {
		$this->make_video( array( 'file' => true ) );

		$this->assertStringContainsString( 'class="videos-modal__video" data-state="paused"', $this->render_modal() );
	}

	public function test_player_strings_have_the_control_labels(): void {
		$strings = devportfolio_video_modal_strings();

		foreach ( array( 'play', 'pause', 'mute', 'unmute' ) as $key ) {
			$this->assertArrayHasKey( $key, $strings );
		}
	}

	public function test_player_styles_use_the_gradient_only_for_progress_and_the_frame(): void {
		$css = $this->css();

		// Every rule that uses the gradient as a value: only the playing frame and the progress fills.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css ); // Comments are not selectors.
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER );
		$users = array();
		foreach ( $rules as $rule ) {
			if ( str_contains( $rule[2], 'var( --vm-grad )' ) ) {
				$users[] = trim( $rule[1] );
			}
		}
		sort( $users );

		$this->assertSame(
			array(
				'.videos-modal__scrub::-moz-range-track',
				'.videos-modal__scrub::-webkit-slider-runnable-track',
				'.videos-modal__video::after',
				'.videos-modal__volume::-moz-range-progress',
				'.videos-modal__volume::-webkit-slider-runnable-track',
			),
			$users
		);
	}

	public function test_player_styles_follow_the_design_system_motion_and_focus(): void {
		$css = $this->css();

		$this->assertStringContainsString( 'cubic-bezier( 0.2, 0.8, 0.2, 1 )', $css );
		$this->assertStringNotContainsString( 'transition: all', $css );
		$this->assertStringContainsString( '.videos-modal__bar button:focus-visible', $css );
		$this->assertStringContainsString( 'var( --vm-focus )', $css );
		$this->assertStringContainsString( '--vm-focus: var( --wp--custom--modal--focus', $css );
	}

	public function test_theme_json_has_the_gradient_and_focus_tokens(): void {
		$json = json_decode( (string) file_get_contents( $this->theme_dir() . '/theme.json' ), true );

		$this->assertSame( 'rgb(29 98 240)', $json['settings']['custom']['modal']['focus'] );
		$this->assertStringContainsString( 'linear-gradient', $json['settings']['custom']['modal']['grad'] );
	}

	public function test_play_button_has_a_pause_icon_the_state_shows(): void {
		$this->make_video( array( 'file' => true ) );

		$html = $this->render_modal();

		$this->assertStringContainsString( 'videos-modal__icon--play', $html );
		$this->assertStringContainsString( 'videos-modal__icon--pause', $html );
		$this->assertStringContainsString( '[data-state="playing"] .videos-modal__icon--pause', $this->css() );
	}

	public function test_fills_draw_the_gradient_as_a_sized_background_layer(): void {
		// A gradient is an image, not a colour stop: it must be a background layer with a size.
		$css = $this->css();

		$this->assertSame( 3, substr_count( $css, 'background: var( --vm-grad ) 0 0 /' ) );
		$this->assertStringNotContainsString( 'linear-gradient( to right, var( --vm-grad )', $css );
	}

	public function test_player_js_has_no_native_controls_or_captions(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/video-modal/player.js' );

		$this->assertStringNotContainsString( '.controls', $js );
		$this->assertStringNotContainsString( '<track', $js );
		$this->assertStringNotContainsString( "createElement( 'track' )", $js );
	}
}
