<?php
/**
 * Tests for the Home software tools block (DPH-024).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Software_Tools_Block extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/software-tools' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	private function make( string $title, int $order = 0, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'software_tool',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/software-tools /-->' );
	}

	/**
	 * Monogram cases.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function monogram_cases(): array {
		return array(
			'two words' => array( 'Slack', 'Sl' ),
			'ChatGPT'   => array( 'ChatGPT', 'Ch' ),
			'Adobe CC'  => array( 'Adobe CC', 'Ad' ),
			'Otter.ai'  => array( 'Otter.ai', 'Ot' ),
			'one letter' => array( 'x', 'X' ),
			'accented'  => array( 'Æther', 'Æt' ),
			'empty'     => array( '', '' ),
		);
	}

	/**
	 * @dataProvider monogram_cases
	 *
	 * @param string $title    Tool name.
	 * @param string $expected Monogram.
	 */
	public function test_monogram(string $title, string $expected): void {
		$this->assertSame( $expected, devportfolio_tool_monogram( $title ) );
	}

	public function test_renders_nothing_without_tools(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_published_tools_only_in_menu_order_then_title(): void {
		$this->make( 'Zoom', 1 );
		$this->make( 'Slack', 2 );
		$this->make( 'Asana', 1 );
		$this->make( 'Draft Tool', 0, 'draft' );

		$html = $this->render();

		$this->assertStringNotContainsString( 'Draft Tool', $html );
		$this->assertLessThan( strpos( $html, 'Zoom' ), strpos( $html, 'Asana' ) );
		$this->assertLessThan( strpos( $html, 'Slack' ), strpos( $html, 'Zoom' ) );
	}

	public function test_one_button_per_tool_not_pressed_and_no_links(): void {
		$this->make( 'Slack', 1 );
		$this->make( 'Zoom', 2 );
		$html = $this->render();

		$this->assertSame( 2, substr_count( $html, '<button type="button" class="software-tools__tile" aria-pressed="false">' ) );
		$this->assertStringContainsString( '<span class="software-tools__name">Slack</span>', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_tool_with_a_featured_image_uses_it_decoratively(): void {
		$id      = $this->make( 'Slack', 1 );
		$image   = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		set_post_thumbnail( $id, $image );

		$html = $this->render();

		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'alt=""', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertMatchesRegularExpression( '/width="\d+"/', $html );
		$this->assertMatchesRegularExpression( '/height="\d+"/', $html );
		$this->assertStringNotContainsString( 'software-tools__icon--text', $html );
	}

	public function test_tool_without_an_image_shows_its_monogram(): void {
		$this->make( 'Slack', 1 );
		$html = $this->render();

		$this->assertStringContainsString( '<span class="software-tools__icon software-tools__icon--text" aria-hidden="true">Sl</span>', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_no_filter_or_hint_markup(): void {
		$this->make( 'Slack', 1 );
		$html = $this->render();

		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringNotContainsString( 'data-filter', $html );
		$this->assertStringNotContainsString( 'aria-expanded', $html );
	}

	public function test_output_is_escaped(): void {
		$this->make( 'A & B <i>', 1 );
		$html = $this->render();

		$this->assertStringContainsString( 'A &amp; B', $html );
		$this->assertStringNotContainsString( '<i>', $html );
	}

	public function test_stylesheet_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/software-tools/style.css' );

		$this->assertStringContainsString( 'grid-template-columns: repeat(3, minmax(0, 1fr))', $css );
		$this->assertStringContainsString( 'repeat(auto-fill, minmax(112px, 1fr))', $css );
		$this->assertStringContainsString( 'gap: 18px 10px', $css );
		$this->assertStringContainsString( '58px', $css );
		$this->assertStringContainsString( '44px', $css );
		$this->assertStringContainsString( 'filter: grayscale(1)', $css );
		$this->assertStringContainsString( 'transition: filter 0.25s', $css );
		$this->assertMatchesRegularExpression( '/@media \(hover: hover\) \{.*translateY\(-3px\)/s', $css );
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\) \{.*transform: none/s', $css );
		$this->assertStringContainsString( 'min-height: 44px', $css );
	}

	public function test_script_contract(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/software-tools/view.js' );

		$this->assertStringContainsString( 'aria-pressed', $js );
		$this->assertStringNotContainsString( 'keydown', $js );
	}
}
