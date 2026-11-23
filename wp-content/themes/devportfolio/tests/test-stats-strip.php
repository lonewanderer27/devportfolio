<?php
/**
 * Tests for the Home stats strip (DPH-005).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Stats_Strip extends WP_UnitTestCase {

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

	/**
	 * Create a statistic.
	 *
	 * @param string $title   Label.
	 * @param string $value   Value (post content).
	 * @param int    $order   Menu order.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make_stat( string $title, string $value, int $order, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'statistic',
				'post_title'   => $title,
				'post_content' => $value,
				'menu_order'   => $order,
				'post_status'  => $status,
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/stats-strip /-->' );
	}

	/**
	 * Value and label of each rendered item.
	 *
	 * @param string $html Rendered block.
	 * @return array<int, array{0:string,1:string}>
	 */
	private function items( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$items = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//li[contains(@class,"stats-strip__item")]' ) as $li ) {
			$items[] = array(
				trim( $li->getElementsByTagName( 'b' )->item( 0 )->textContent ),
				trim( $li->getElementsByTagName( 'span' )->item( 0 )->textContent ),
			);
		}

		return $items;
	}

	public function test_three_statistics_render_in_menu_order(): void {
		// Created out of order on purpose.
		$this->make_stat( 'Typical reply time', '24 hr', 3 );
		$this->make_stat( 'Experience', '+5 yrs', 1 );
		$this->make_stat( 'Happy clients', '+40', 2 );

		$this->assertSame(
			array(
				array( '+5 yrs', 'Experience' ),
				array( '+40', 'Happy clients' ),
				array( '24 hr', 'Typical reply time' ),
			),
			$this->items( $this->render() )
		);
	}

	public function test_markup_in_the_value_is_stripped(): void {
		$this->make_stat( 'Experience', '<b>+5 yrs</b>', 1 );
		$html = $this->render();

		$this->assertSame( array( array( '+5 yrs', 'Experience' ) ), $this->items( $html ) );
		$this->assertStringNotContainsString( '<b>+5', $html );
		$this->assertStringNotContainsString( '&lt;b', $html );
	}

	public function test_only_the_first_three_render(): void {
		foreach ( array( 1, 2, 3, 4 ) as $n ) {
			$this->make_stat( 'Stat ' . $n, (string) $n, $n );
		}

		$this->assertCount( 3, $this->items( $this->render() ) );
	}

	public function test_drafts_and_empty_values_are_skipped(): void {
		$this->make_stat( 'Draft', '99', 1, 'draft' );
		$this->make_stat( 'Empty', '', 2 );
		$this->make_stat( 'Real', '7', 3 );

		$this->assertSame( array( array( '7', 'Real' ) ), $this->items( $this->render() ) );
	}

	public function test_nothing_renders_without_statistics(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_the_numbers_are_not_interactive(): void {
		$this->make_stat( 'Experience', '+5 yrs', 1 );
		$html = $this->render();

		foreach ( array( '<a ', '<button', 'onclick', 'tabindex' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html );
		}
	}

	public function test_css_contract(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/blocks/stats-strip/style.css' );

		$this->assertStringContainsString( 'grid-template-columns: repeat(3, minmax(0, 1fr))', $css );
		$this->assertStringContainsString( 'font-weight: 200', $css );

		// The three columns never change on a smaller screen.
		$this->assertStringNotContainsString( 'grid-template-columns', substr( $css, (int) strpos( $css, '@media' ) ) );
	}
}
