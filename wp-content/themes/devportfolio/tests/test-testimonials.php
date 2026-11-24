<?php
/**
 * Tests for the Home testimonials block (DPH-022, DPH-023).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Testimonials extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/testimonials' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	/**
	 * Create a testimonial.
	 *
	 * @param string $name    Client name (title).
	 * @param string $quote   Quote (post content).
	 * @param string $company Company meta.
	 * @param int    $order   Menu order.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make( string $name, string $quote, string $company = '', int $order = 0, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'testimonial',
				'post_title'   => $name,
				'post_content' => $quote,
				'menu_order'   => $order,
				'post_status'  => $status,
			)
		);

		if ( '' !== $company ) {
			update_post_meta( $id, 'cptdp_company', $company );
		}

		return $id;
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/testimonials /-->' );
	}

	public function test_renders_nothing_without_a_published_testimonial(): void {
		$this->make( 'Draft Person', 'A draft quote.', '', 0, 'draft' );
		$this->make( 'Empty Quote', '   ', '', 0 );

		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_renders_every_quote_and_only_the_first_is_active(): void {
		$this->make( 'Ann Lee', 'First quote.', 'Lee Co', 1 );
		$this->make( 'Ben Ross', 'Second quote.', 'Ross Ltd', 2 );
		$this->make( 'Cal Dunn', 'Third quote.', 'Dunn Pty', 3 );

		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, 'data-testimonials-slide' ) );
		$this->assertSame( 1, substr_count( $html, 'is-active' ) );
		$this->assertMatchesRegularExpression( '/is-active" data-testimonials-slide>\s*<blockquote class="testimonials__quote"><p>First quote\./', $html );
		$this->assertStringContainsString( '<span class="testimonials__name">Ben Ross</span>', $html );
		$this->assertStringContainsString( '<span class="testimonials__company">Ross Ltd</span>', $html );
	}

	public function test_order_is_menu_order_then_title(): void {
		$this->make( 'Zed', 'Q z.', '', 1 );
		$this->make( 'Bea', 'Q b.', '', 2 );
		$this->make( 'Abe', 'Q a.', '', 1 );

		$html = $this->render();

		$this->assertLessThan( strpos( $html, 'Zed' ), strpos( $html, 'Abe' ) );
		$this->assertLessThan( strpos( $html, 'Bea' ), strpos( $html, 'Zed' ) );
	}

	public function test_heading_and_anchor_are_in_place(): void {
		$this->make( 'Ann Lee', 'A quote.', '', 1 );
		$html = $this->render();

		$this->assertStringContainsString( 'id="testimonials"', $html );
		$this->assertStringContainsString( '<h2 id="testimonials-heading" class="testimonials__heading home-reveal">What clients say</h2>', $html );
		$this->assertStringContainsString( 'aria-labelledby="testimonials-heading"', $html );
	}

	public function test_dots_show_for_two_or_more_quotes(): void {
		$this->make( 'Ann Lee', 'A quote.', '', 1 );
		$this->make( 'Ben Ross', 'B quote.', '', 2 );
		$this->make( 'Cal Dunn', 'C quote.', '', 3 );
		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, 'class="testimonials__dot"' ) );
		$this->assertStringContainsString( 'aria-label="Show testimonial 1 of 3"', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="true"' ) );
		$this->assertMatchesRegularExpression( '/data-testimonials-dots hidden>/', $html );
	}

	public function test_one_quote_has_no_dots(): void {
		$this->make( 'Ann Lee', 'A quote.', '', 1 );

		$this->assertStringNotContainsString( 'testimonials__dot', $this->render() );
	}

	public function test_no_photo_and_no_link_yet(): void {
		$this->make( 'Ann Lee', 'A quote.', '', 1 );
		$html = $this->render();

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( 'Read more', $html );
	}

	public function test_output_is_escaped(): void {
		$this->make( 'A & B', '<script>alert(1)</script>', '<b>Acme</b>', 1 );
		$html = $this->render();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'A &amp; B', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'Acme', $html );
	}

	public function test_block_is_registered(): void {
		$this->assertArrayHasKey( 'devportfolio/testimonials', WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	public function test_stylesheet_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/testimonials/style.css' );

		$this->assertStringContainsString( 'font-style: italic', $css );
		$this->assertMatchesRegularExpression( '/\.testimonials__dot \{[^}]*width: 44px;[^}]*height: 44px;/s', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: reduce)', $css );
		$this->assertStringContainsString( 'touch-action: pan-y', $css );
	}

	public function test_script_contract(): void {
		$dir = $this->theme_dir() . '/blocks/testimonials/';
		$js  = (string) file_get_contents( $dir . 'view.js' );

		foreach ( array( 'PERIOD', 'pointerType', 'focus-visible', 'visibilitychange', "from './rotation.js'" ) as $needle ) {
			$this->assertStringContainsString( $needle, $js, $needle );
		}
		$this->assertStringContainsString( 'export const PERIOD = 6000;', (string) file_get_contents( $dir . 'rotation.js' ) );
	}
}
