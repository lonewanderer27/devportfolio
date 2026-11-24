<?php
/**
 * Tests for the Home sections opening animation (DPH-032).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Home_Motion extends WP_UnitTestCase {

	private const MEDIA = '@media screen and (prefers-reduced-motion: no-preference) {';

	private const HIDDEN = '.has-home-reveal .home-reveal:not(.is-revealing):not(.is-revealed):not(:focus-within):not(.is-modal > *)';

	private const REVEALING = '.has-home-reveal .home-reveal.is-revealing:not(.is-modal > *)';

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/stats-strip' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	private function file( string $relative ): string {
		$path = $this->theme_dir() . '/' . $relative;
		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}

	/**
	 * The motion stylesheet without its comments, so prose never satisfies or breaks an assertion.
	 */
	private function css(): string {
		return (string) preg_replace( '#/\*.*?\*/#s', '', $this->file( 'assets/css/home-motion.css' ) );
	}

	/**
	 * The declarations of a rule that starts with `$selector {`.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Exact selector text.
	 * @return string The declarations, or '' when the rule is not there.
	 */
	private function rule( string $css, string $selector ): string {
		if ( preg_match( '/(?:^|[{}\/])\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $m ) ) {
			return $m[1];
		}

		return '';
	}

	private function keyframes(): string {
		$this->assertSame( 1, preg_match( '/@keyframes home-reveal \{(.*?)\n\t\}\n/s', $this->css(), $m ), 'The home-reveal keyframes are missing.' );

		return $m[1];
	}

	// ---------------------------------------------------------------------------------------------
	// Tokens and stylesheet.
	// ---------------------------------------------------------------------------------------------

	public function test_the_motion_tokens_are_defined_once_in_theme_json(): void {
		$json = json_decode( $this->file( 'theme.json' ), true );

		$this->assertSame(
			array(
				'ease'            => 'cubic-bezier(.2, .8, .2, 1)',
				'reveal-duration' => '520ms',
				'reveal-distance' => '14px',
				'reveal-stagger'  => '80ms',
			),
			$json['settings']['custom']['motion']
		);
	}

	public function test_every_rule_sits_inside_one_screen_no_preference_query(): void {
		$css   = $this->css();
		$start = strpos( $css, self::MEDIA );

		$this->assertNotFalse( $start, 'The reduced-motion query is missing.' );
		$this->assertSame( '', trim( substr( $css, 0, $start ) ), 'No rule may sit before the query.' );
		$this->assertSame( 1, substr_count( $css, '@media' ), 'One query holds every rule.' );
		$this->assertStringEndsWith( '}', rtrim( $css ) );
	}

	public function test_the_hidden_start_state_needs_the_script_class_and_spares_focus_and_the_phone_modal(): void {
		$css  = $this->css();
		$rule = $this->rule( $css, self::HIDDEN );

		$this->assertStringContainsString( 'opacity: 0;', $rule );
		$this->assertStringContainsString( 'translate: 0 var(--wp--custom--motion--reveal-distance, 14px);', $rule );

		// No rule may hide a marked element without the class the script adds.
		$this->assertSame( 0, preg_match( '/(?:^|[{}])\s*\.home-reveal/', $css ) );
		$this->assertSame( 2, substr_count( $css, '.has-home-reveal .home-reveal' ) );
	}

	public function test_the_animation_reads_the_tokens_and_the_stagger_index(): void {
		$rule = $this->rule( $this->css(), self::REVEALING );

		$this->assertStringContainsString(
			'animation: home-reveal var(--wp--custom--motion--reveal-duration, 520ms) var(--wp--custom--motion--ease, cubic-bezier(.2, .8, .2, 1)) calc(var(--home-reveal-index, 0) * var(--wp--custom--motion--reveal-stagger, 80ms)) both;',
			$rule
		);
	}

	public function test_only_opacity_and_translate_change(): void {
		$frames = $this->keyframes();

		preg_match_all( '/([a-z-]+)\s*:/', $frames, $props );
		$this->assertSame( array( 'opacity', 'translate' ), array_values( array_unique( $props[1] ) ) );
		$this->assertStringContainsString( "from {\n\t\t\topacity: 0;\n\t\t\ttranslate: 0 var(--wp--custom--motion--reveal-distance, 14px);", $frames );
		$this->assertStringContainsString( "to {\n\t\t\topacity: 1;\n\t\t\ttranslate: none;", $frames );

		$css = $this->css();
		foreach ( array( 'transform', 'will-change', 'visibility', 'display', 'filter', 'height', 'margin', 'top' ) as $prop ) {
			$this->assertSame( 0, preg_match( '/(?:^|[\s;{])' . $prop . '\s*:/', $css ), "{$prop} must not change." );
		}
	}

	public function test_the_phone_contact_card_keeps_its_own_modal_animation(): void {
		$this->assertStringEndsWith( ':not(.is-modal > *)', self::HIDDEN );
		$this->assertStringEndsWith( ':not(.is-modal > *)', self::REVEALING );
		$this->assertStringContainsString( "\t<div class=\"contact__card home-reveal\" data-contact-card>", $this->file( 'blocks/contact/render.php' ) );
		$this->assertStringContainsString( ".contact.is-modal .contact__card {\n\t\tanimation: contact-modal-in", $this->file( 'blocks/contact/style.css' ) );
	}

	public function test_the_stylesheet_is_enqueued_on_the_front_page_only(): void {
		$this->assertStringContainsString( "add_action( 'wp_enqueue_scripts', 'devportfolio_enqueue_home_motion' );", $this->file( 'functions.php' ) );

		wp_dequeue_style( 'devportfolio-home-motion' );
		wp_deregister_style( 'devportfolio-home-motion' );
		$this->go_to( get_post_type_archive_link( 'document' ) );
		devportfolio_enqueue_home_motion();
		$this->assertFalse( wp_style_is( 'devportfolio-home-motion', 'enqueued' ), 'Not on an archive.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		devportfolio_enqueue_home_motion();
		$this->assertTrue( wp_style_is( 'devportfolio-home-motion', 'enqueued' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Script.
	// ---------------------------------------------------------------------------------------------

	public function test_the_script_is_printed_inline_at_the_top_of_the_head_on_the_front_page_only(): void {
		$this->assertStringContainsString( "add_action( 'wp_head', 'devportfolio_print_home_reveal_script', 1 );", $this->file( 'functions.php' ) );

		$this->go_to( home_url( '/?p=' . self::factory()->post->create() ) );
		ob_start();
		devportfolio_print_home_reveal_script();
		$this->assertSame( '', ob_get_clean(), 'Not the front page.' );

		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		ob_start();
		devportfolio_print_home_reveal_script();
		$out = (string) ob_get_clean();

		// Core may put a type attribute first when the theme has no html5 script support, so match the id anywhere.
		$this->assertMatchesRegularExpression( '/<script[^>]* id="devportfolio-home-reveal"/', $out );
		$this->assertStringContainsString( "const READY = 'has-home-reveal';", $out );
		$this->assertStringNotContainsString( ' src=', $out );
	}

	public function test_the_script_only_hides_with_intersection_observer_and_without_reduced_motion(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		foreach (
			array(
				"const READY = 'has-home-reveal';",
				"const TARGET = '.home-reveal';",
				'const THRESHOLD = 0.15;',
				'const STAGGER_CAP = 5;',
				'const GIVE_UP_MS = 3000;',
				"'IntersectionObserver' in window",
				"window.matchMedia( '(prefers-reduced-motion: no-preference)' ).matches",
				'root.classList.add( READY );',
				'} catch ( error ) {',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $js );
		}

		// The class is added only after both checks passed.
		$this->assertLessThan( strpos( $js, 'root.classList.add( READY );' ), strpos( $js, '(prefers-reduced-motion: no-preference)' ) );
	}

	public function test_the_script_reveals_each_element_once_and_cleans_up(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		foreach (
			array(
				'{ threshold: THRESHOLD }',
				'observer.unobserve( entry.target );',
				"el.style.setProperty( '--home-reveal-index', String( Math.min( index, STAGGER_CAP ) ) );",
				"el.classList.add( 'is-revealing' );",
				"el.classList.remove( 'is-revealing' );",
				"el.classList.add( 'is-revealed' );",
				"el.style.removeProperty( '--home-reveal-index' );",
				"el.matches( ':focus-within' )",
				"'animationend'",
				"'animationcancel'",
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $js );
		}
	}

	public function test_the_script_starts_early_and_steps_aside_for_a_view_transition(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		$this->assertStringContainsString( "window.addEventListener( 'pagereveal', ( event ) => {", $js );
		$this->assertStringContainsString( 'if ( event.viewTransition ) {', $js );
		$this->assertStringContainsString( "document.addEventListener( 'readystatechange', onState );", $js );
		$this->assertStringNotContainsString( "addEventListener( 'DOMContentLoaded'", $js );
	}

	public function test_the_script_stores_nothing_and_is_a_classic_script(): void {
		$js = $this->file( 'assets/js/home-reveal.js' );

		$this->assertStringNotContainsString( 'localStorage', $js );
		$this->assertStringNotContainsString( 'sessionStorage', $js );
		$this->assertStringNotContainsString( 'will-change', $js );
		$this->assertSame( 0, preg_match( '/^\s*(import|export)\s/m', $js ) );
	}

	// ---------------------------------------------------------------------------------------------
	// What is marked, and what never is.
	// ---------------------------------------------------------------------------------------------

	public function test_the_template_marks_the_three_headings_and_the_intro(): void {
		$tpl = $this->file( 'templates/front-page.html' );

		$this->assertSame( 4, substr_count( $tpl, '"className":"home-reveal"' ) );
		$this->assertSame( 3, substr_count( $tpl, 'class="wp-block-heading home-reveal"' ) );
		foreach ( array( 'What I do', 'Selected work', 'Software tools' ) as $heading ) {
			$this->assertStringContainsString( '<h2 class="wp-block-heading home-reveal" style="font-size:clamp(26px, 3.4vw, 34px);font-weight:800">' . $heading . '</h2>', $tpl );
		}
		$this->assertStringContainsString( '<p class="has-mid-color has-text-color has-medium-font-size home-reveal">', $tpl );
	}

	public function test_the_blocks_mark_their_items(): void {
		$marks = array(
			'blocks/stats-strip/render.php'    => array( '<li class="stats-strip__item home-reveal">' ),
			'blocks/service-list/render.php'   => array( '<li class="service-list__row home-reveal">' ),
			'blocks/selected-work/render.php'  => array(
				'<article class="work-card work-card--documents home-reveal">',
				'<article class="work-card work-card--videos home-reveal">',
				'<article class="work-card work-card--collaterals home-reveal">',
			),
			'blocks/testimonials/render.php'   => array(
				'<h2 id="testimonials-heading" class="testimonials__heading home-reveal">',
				'<div class="testimonials__stage home-reveal" data-testimonials-stage aria-live="off">',
				'<div class="testimonials__dots home-reveal" role="group"',
			),
			'blocks/software-tools/render.php' => array( "array( 'class' => 'software-tools home-reveal' )" ),
			'blocks/contact/render.php'        => array(
				'<h2 id="contact-heading" class="contact__heading home-reveal">',
				'<p class="contact__lead home-reveal">',
				'<button type="button" class="contact__open home-reveal" data-contact-open>',
				'<div class="contact__card home-reveal" data-contact-card>',
			),
			'blocks/site-footer/render.php'    => array( "array( 'class' => 'site-footer home-reveal' )" ),
		);

		foreach ( $marks as $file => $needles ) {
			$src = $this->file( $file );
			foreach ( $needles as $needle ) {
				$this->assertStringContainsString( $needle, $src, "{$file}: {$needle}" );
			}
			$this->assertSame( count( $needles ), substr_count( $src, 'home-reveal' ), "{$file} marks something else too." );
		}
	}

	public function test_no_wrapper_that_holds_a_dialog_a_fixed_or_a_sticky_element_is_marked(): void {
		$tpl = $this->file( 'templates/front-page.html' );

		$this->assertStringContainsString( '<main class="wp-block-group documents-page home-page">', $tpl );
		foreach ( array( 'services', 'selected-work', 'software-tools' ) as $id ) {
			$this->assertStringContainsString( '<div id="' . $id . '" class="wp-block-group home-section"', $tpl );
		}
		$this->assertStringNotContainsString( '"className":"home-section home-reveal"', $tpl );
		$this->assertStringContainsString( "'class' => 'contact home-section',", $this->file( 'blocks/contact/render.php' ) );
		$this->assertStringContainsString( "'class' => 'testimonials home-section',", $this->file( 'blocks/testimonials/render.php' ) );
		$this->assertStringContainsString( "get_block_wrapper_attributes( array( 'class' => 'work' ) )", $this->file( 'blocks/selected-work/render.php' ) );

		foreach (
			array(
				'blocks/hero/render.php',
				'blocks/hero/style.css',
				'blocks/document-modal/render.php',
				'blocks/collateral-modal/render.php',
				'blocks/video-modal/render.php',
				'parts/header.html',
				'parts/footer.html',
			) as $file
		) {
			$this->assertStringNotContainsString( 'home-reveal', $this->file( $file ), "{$file} must not be marked." );
		}
	}

	public function test_the_hero_keeps_its_own_entrance(): void {
		$this->assertStringContainsString( '@media (min-width: 621px) and (prefers-reduced-motion: no-preference) {', $this->file( 'blocks/hero/style.css' ) );
	}

	public function test_rendered_blocks_carry_the_mark(): void {
		self::factory()->post->create(
			array(
				'post_type'    => 'statistic',
				'post_title'   => 'Clients',
				'post_content' => '40+',
				'menu_order'   => 1,
				'post_status'  => 'publish',
			)
		);
		devportfolio_memo_flush();

		$this->assertStringContainsString( '<li class="stats-strip__item home-reveal">', do_blocks( '<!-- wp:devportfolio/stats-strip /-->' ) );
		$this->assertStringContainsString( 'site-footer home-reveal', do_blocks( '<!-- wp:devportfolio/site-footer /-->' ) );
	}
}
