<?php
/**
 * Tests for the Home hero (DPH-001, DPH-003, DPH-004).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Hero extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/hero' ) ) {
			devportfolio_register_blocks();
		}

		update_option( 'blogname', 'Dave Portfolio' );
		update_option( 'cptdp_contact', array() );
		devportfolio_memo_flush();
	}

	private function make_role( string $title, int $order, bool $highlight = false, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'job_role',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);

		if ( $highlight ) {
			update_post_meta( $id, 'cptdp_highlight', 1 );
		}

		return $id;
	}

	private function make_portrait( string $alt = '' ): int {
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'portrait',
				'file'           => 'portrait.png',
			)
		);

		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		update_option( 'cptdp_contact', array( 'portrait_id' => $id ) );

		return $id;
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/hero /-->' );
	}

	// DPH-001: the name.

	public function test_name_is_real_text_in_the_h1_split_into_outlined_and_solid(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '#<h1 class="hero__name"><span class="hero__name-first">David</span> <span class="hero__name-rest">John</span></h1>#', $html );
		$this->assertStringNotContainsString( '<img', substr( $html, (int) strpos( $html, '<h1' ), (int) strpos( $html, '</h1>' ) - (int) strpos( $html, '<h1' ) ), 'The name is not an image.' );
	}

	public function test_a_one_word_site_title_has_no_solid_part(): void {
		update_option( 'cptdp_contact', array( 'hero_title' => 'David' ) );

		$html = $this->render();

		$this->assertStringContainsString( '<span class="hero__name-first">David</span></h1>', $html );
		$this->assertStringNotContainsString( 'hero__name-rest', $html );
	}

	public function test_the_site_title_is_escaped(): void {
		update_option( 'cptdp_contact', array( 'hero_title' => 'A <script>x</script> B' ) );

		$this->assertStringNotContainsString( '<script>x', $this->render() );
	}

	// DPH-001: the portrait.

	public function test_portrait_is_the_only_image_with_alt_eager_and_high_priority(): void {
		$this->make_portrait( 'Dave smiling' );
		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<img' ) );
		$this->assertStringContainsString( 'class="hero__portrait-img', $html );
		$this->assertStringContainsString( 'alt="Dave smiling"', $html );
		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'sizes="' . devportfolio_hero_portrait_sizes() . '"', $html );
	}

	public function test_portrait_alt_falls_back_to_the_site_title(): void {
		$this->make_portrait();

		$this->assertStringContainsString( 'alt="Portrait of David John"', $this->render() );
	}

	public function test_without_a_portrait_the_rest_of_the_hero_still_renders(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'hero__portrait', $html );
		$this->assertStringContainsString( 'hero__cta', $html );
	}

	public function test_a_deleted_portrait_file_renders_no_image(): void {
		$id = $this->make_portrait();
		wp_delete_attachment( $id, true );

		$this->assertStringNotContainsString( '<img', $this->render() );
	}

	public function test_portrait_is_preloaded_on_the_front_page_only(): void {
		$id = $this->make_portrait();

		$this->go_to( home_url( '/?p=' . self::factory()->post->create() ) );
		ob_start();
		devportfolio_hero_preload_portrait();
		$this->assertSame( '', ob_get_clean(), 'Not the front page.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		ob_start();
		devportfolio_hero_preload_portrait();
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( 'rel="preload" as="image"', $out );
		$this->assertStringContainsString( 'fetchpriority="high"', $out );
		$this->assertStringContainsString( 'imagesizes="' . devportfolio_hero_portrait_sizes() . '"', $out );
		$this->assertStringContainsString( (string) wp_get_attachment_image_url( $id, 'large' ), $out );
	}

	public function test_nothing_is_preloaded_without_a_portrait(): void {
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );

		ob_start();
		devportfolio_hero_preload_portrait();
		$this->assertSame( '', ob_get_clean() );
	}

	// DPH-003: roles and call to action.

	public function test_highlighted_role_is_on_top_and_the_others_follow_in_order(): void {
		$this->make_role( 'Virtual Assistant', 1 );
		$this->make_role( 'Social Media Manager', 2 );
		$this->make_role( 'Digital Marketer', 3, true );
		$this->make_role( 'Web Developer', 4 );

		$html = $this->render();

		$this->assertStringContainsString( '<p class="hero__role">Digital Marketer</p>', $html );
		$this->assertStringContainsString( '<p class="hero__roles"><span>Virtual Assistant</span> <span>Social Media Manager</span> <span>Web Developer</span></p>', $html );
	}

	public function test_without_a_highlight_the_first_role_by_order_is_on_top(): void {
		$this->make_role( 'Web Developer', 4 );
		$this->make_role( 'Virtual Assistant', 1 );

		$html = $this->render();

		$this->assertStringContainsString( '<p class="hero__role">Virtual Assistant</p>', $html );
		$this->assertStringContainsString( '<p class="hero__roles"><span>Web Developer</span></p>', $html );
	}

	public function test_a_draft_role_is_never_shown(): void {
		$this->make_role( 'Virtual Assistant', 1, true );
		$this->make_role( 'Secret', 2, false, 'draft' );

		$this->assertStringNotContainsString( 'Secret', $this->render() );
	}

	public function test_a_draft_highlight_is_ignored_and_the_first_published_role_leads(): void {
		$this->make_role( 'Hidden', 1, true, 'draft' );
		$this->make_role( 'Virtual Assistant', 2 );

		$this->assertStringContainsString( '<p class="hero__role">Virtual Assistant</p>', $this->render() );
	}

	public function test_a_single_role_has_no_second_line(): void {
		$this->make_role( 'Virtual Assistant', 1, true );

		$html = $this->render();

		$this->assertStringContainsString( 'hero__role"', $html );
		$this->assertStringNotContainsString( 'hero__roles', $html );
	}

	public function test_no_roles_leaves_out_the_role_block_but_keeps_the_button(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<p class="hero__role"', $html );
		$this->assertStringNotContainsString( 'hero__roles', $html );
		$this->assertStringContainsString( 'class="hero__cta" href="#contact"', $html );
	}

	public function test_role_names_are_escaped(): void {
		$this->make_role( '<b>x</b>', 1, true );

		$this->assertStringNotContainsString( '<b>x</b>', $this->render() );
	}

	public function test_the_button_scrolls_to_contact_in_the_same_tab_and_is_the_only_one(): void {
		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, 'hero__cta' ) );
		$this->assertMatchesRegularExpression( '#<a class="hero__cta" href="\#contact">Let’s work together <span aria-hidden="true">↗</span></a>#u', $html );
		$this->assertStringNotContainsString( 'target=', substr( $html, (int) strpos( $html, 'hero__cta' ), 80 ) );
	}

	public function test_scroll_hint_is_decorative(): void {
		$this->assertStringContainsString( '<p class="hero__scroll" aria-hidden="true">Scroll down <span class="hero__scroll-arrow">↓</span></p>', $this->render() );
	}

	// DPH-004: the links.

	public function test_three_pills_with_new_tab_for_linkedin_and_whatsapp_and_mailto_for_email(): void {
		update_option(
			'cptdp_contact',
			array(
				'linkedin_url' => 'https://www.linkedin.com/in/dave',
				'whatsapp_url' => 'https://wa.me/639346973102',
				'email'        => 'dave@example.com',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'href="https://www.linkedin.com/in/dave" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">in</span> LinkedIn</a>', $html );
		$this->assertStringContainsString( 'href="https://wa.me/639346973102" target="_blank" rel="noopener noreferrer">WhatsApp</a>', $html );
		$this->assertStringContainsString( 'href="mailto:dave@example.com">Email me</a>', $html );
		$this->assertSame( 3, substr_count( $html, 'class="hero__pill"' ) );
	}

	public function test_a_link_without_a_value_is_not_shown(): void {
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( 'LinkedIn', $html );
		$this->assertStringNotContainsString( 'WhatsApp', $html );
		$this->assertSame( 1, substr_count( $html, 'class="hero__pill"' ) );
	}

	public function test_no_links_means_no_list(): void {
		$this->assertStringNotContainsString( 'hero__links', $this->render() );
	}

	public function test_link_urls_are_escaped(): void {
		update_option( 'cptdp_contact', array( 'linkedin_url' => 'https://www.linkedin.com/in/a"onmouseover="x' ) );

		$this->assertStringNotContainsString( '"onmouseover="x', $this->render() );
	}

	// CSS contract (no browser runner in the repo).

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/hero/style.css' );
	}

	private function phone_css(): string {
		$css = $this->css();
		$pos = strpos( $css, '@media (max-width: 620px)' );

		$this->assertNotFalse( $pos, 'The phone rules are at 620px and below.' );

		return substr( $css, (int) $pos );
	}

	public function test_css_outlines_the_first_word_with_a_2_5px_stroke_and_scales_the_name(): void {
		$css = $this->css();

		$this->assertStringContainsString( '-webkit-text-stroke: 2.5px', $css );
		$this->assertStringContainsString( 'font-size: min(12vw, 168px)', $css );
		$this->assertStringContainsString( 'white-space: nowrap', $css );
	}

	public function test_css_draws_the_portrait_in_greyscale_25_percent_of_the_name_width_covering_the_name(): void {
		$css = $this->css();

		$this->assertStringContainsString( 'filter: grayscale(1)', $css );
		$this->assertStringContainsString( 'width: 46%', $css );
		$this->assertDoesNotMatchRegularExpression( '/\.hero__portrait-img \{[^}]*max-height/s', $css, 'The image size no longer caps the portrait.' );
		$this->assertMatchesRegularExpression( '/\.hero__stage \{[^}]*right: 0;/s', $css, 'The stage spans the hero column, so the title length does not change the photo.' );
		$this->assertMatchesRegularExpression( '/\.hero__portrait \{[^}]*top: calc\(46px \+ 0.5em\);/s', $css );
	}

	public function test_css_phone_rules_hide_the_name_visually_and_the_extras_and_widen_the_portrait(): void {
		$phone = $this->phone_css();

		$this->assertMatchesRegularExpression( '/\.hero__name \{[^}]*clip-path: inset\(50%\)/s', $phone );
		$this->assertStringNotContainsString( 'display: none', substr( $phone, (int) strpos( $phone, '.hero__name {' ), 200 ), 'The h1 stays in the accessibility tree.' );
		$this->assertMatchesRegularExpression( '/\.hero__links,\s*\.hero__scroll \{\s*display: none;/', $phone );
		$this->assertMatchesRegularExpression( '/\.hero__portrait \{[^}]*width: min\(210px, 56%\);/s', $phone );
		$this->assertMatchesRegularExpression( '/\.hero__cta \{[^}]*display: block;/s', $phone );
	}

	public function test_the_hero_is_the_content_width_and_fills_the_window_below_the_header(): void {
		$css = $this->css();

		$this->assertStringNotContainsString( '100vw', $css, 'The hero follows the content width of the other home sections.' );
		$this->assertStringContainsString( '--hero-h: calc(100dvh - var(--site-header-height, 0px))', $css );
	}

	public function test_css_has_no_other_breakpoint_for_the_phone_layout(): void {
		$this->assertStringContainsString( '@media (max-width: 620px)', $this->css() );
	}

	// Entrance animations (desktop and tablet only, never with reduced motion).

	private function motion_css(): string {
		$css = $this->css();
		$pos = strpos( $css, '@media (min-width: 621px) and (prefers-reduced-motion: no-preference) {' );

		$this->assertNotFalse( $pos, 'The entrance runs from 621px up and only without reduced motion.' );

		return substr( $css, (int) $pos, (int) strpos( $css, '@media (max-width: 1100px)' ) - (int) $pos );
	}

	private function keyframes( string $name ): string {
		$this->assertSame( 1, preg_match( '/@keyframes ' . preg_quote( $name, '/' ) . ' \{(.*?)\n\t\}/s', $this->motion_css(), $m ), "Keyframes {$name} live in the motion query." );

		return $m[1];
	}

	public function test_the_desktop_portrait_has_no_bottom_fade(): void {
		$this->assertSame( 1, preg_match( '/\n\.hero__portrait \{[^}]*\}/s', $this->css(), $m ) );
		$this->assertStringNotContainsString( 'mask-image', $m[0] );
		$this->assertStringContainsString( 'transform: translateX(-50%)', $m[0], 'The portrait stays centred.' );
	}

	public function test_every_hero_animation_lives_in_the_no_preference_query(): void {
		$css    = $this->css();
		$motion = $this->motion_css();

		$this->assertSame( substr_count( $css, 'animation:' ), substr_count( $motion, 'animation:' ), 'No animation outside the motion query.' );
		$this->assertSame( substr_count( $css, '@keyframes' ), substr_count( $motion, '@keyframes' ) );
		$this->assertSame( 4, substr_count( $motion, '@keyframes' ) );
		$this->assertStringNotContainsString( 'animation', $this->phone_css(), 'Phones get no entrance.' );
		$this->assertSame( 1, substr_count( $motion, 'infinite' ), 'Only the scroll arrow loops.' );
	}

	public function test_the_name_drops_in_first_and_the_portrait_rises_after_it(): void {
		$motion = $this->motion_css();

		$this->assertMatchesRegularExpression( '/\.hero__name \{\s*animation: hero-fade-down 0\.9s cubic-bezier\(\.2, \.7, \.2, 1\) 0s both;/', $motion );
		$this->assertMatchesRegularExpression( '/\.hero__portrait \{\s*animation: hero-fade-up 1\.1s cubic-bezier\(\.2, \.7, \.2, 1\) 0\.15s both;/', $motion );

		$down = $this->keyframes( 'hero-fade-down' );
		$this->assertMatchesRegularExpression( '/from \{\s*opacity: 0;\s*transform: translateY\(-48px\);/', $down, 'The name starts above.' );
		$this->assertMatchesRegularExpression( '/to \{\s*opacity: 1;\s*transform: none;/', $down );

		$up = $this->keyframes( 'hero-fade-up' );
		$this->assertMatchesRegularExpression( '/from \{\s*opacity: 0;\s*translate: 0 48px;/', $up, 'The portrait starts below.' );
		$this->assertMatchesRegularExpression( '/to \{\s*opacity: 1;\s*translate: 0 0;/', $up );
		$this->assertStringNotContainsString( 'transform', $up, 'The centring transform is left alone.' );
	}

	public function test_the_corners_fade_in_last_with_opacity_only(): void {
		$this->assertMatchesRegularExpression( '/\.hero__intro,\s*\.hero__links,\s*\.hero__scroll \{\s*animation: hero-fade-in 0\.9s cubic-bezier\(\.2, \.7, \.2, 1\) 0\.45s both;/', $this->motion_css() );

		$fade = $this->keyframes( 'hero-fade-in' );
		$this->assertMatchesRegularExpression( '/from \{\s*opacity: 0;\s*\}\s*to \{\s*opacity: 1;\s*\}/', $fade );
		$this->assertDoesNotMatchRegularExpression( '/transform|translate/', $fade );
	}

	// The preload helper and the image share one sizes string.

	public function test_the_scroll_hint_fades_with_the_bottom_left_group(): void {
		$this->assertMatchesRegularExpression( '/\.hero__intro,\s*\.hero__links,\s*\.hero__scroll \{\s*animation: hero-fade-in/', $this->css() );
	}

	public function test_the_arrow_bobs_down_up_down_inside_the_motion_query(): void {
		$css    = $this->css();
		$motion = strpos( $css, '(prefers-reduced-motion: no-preference)' );

		$this->assertStringContainsString( 'translateY(6px)', $css );
		$this->assertNotFalse( strpos( $css, '@keyframes hero-arrow' ) );
		$this->assertGreaterThan( $motion, strpos( $css, '.hero__scroll-arrow {' ) );
		$this->assertStringNotContainsString( 'infinite;', substr( $css, 0, $motion ) );
	}

	public function test_the_script_hides_the_hint_on_scroll_and_is_registered(): void {
		$script = (string) file_get_contents( $this->theme_dir() . '/blocks/hero/view.js' );
		$block  = (string) file_get_contents( $this->theme_dir() . '/blocks/hero/block.json' );

		$this->assertStringContainsString( 'hint.hidden = true', $script );
		$this->assertStringContainsString( 'window.scrollY > 0', $script );
		$this->assertStringContainsString( '"viewScript": "file:./view.js', $block );
	}

	public function test_sizes_string_is_defined_once(): void {
		$this->assertSame( 1, substr_count( (string) file_get_contents( $this->theme_dir() . '/blocks/hero/render.php' ), 'devportfolio_hero_portrait_sizes()' ) );
		$this->assertStringNotContainsString( '42vw', (string) file_get_contents( $this->theme_dir() . '/blocks/hero/render.php' ) );
	}
}
