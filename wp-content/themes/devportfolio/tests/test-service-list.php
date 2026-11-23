<?php
/**
 * Tests for the Home service rows (DPH-007, DPH-008, DPH-009).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Service_List extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/service-list' ) ) {
			devportfolio_register_blocks();
		}

		devportfolio_memo_flush();
	}

	/**
	 * Create a service.
	 *
	 * @param string $title   Title.
	 * @param int    $order   Menu order.
	 * @param int    $parent  Parent service ID, 0 for a top-level service.
	 * @param string $content Benefit line.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make_service( string $title, int $order = 0, int $parent = 0, string $content = '', string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'service',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent,
				'menu_order'   => $order,
				'post_status'  => $status,
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/service-list /-->' );
	}

	private function xpath( string $html ): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		return new DOMXPath( $dom );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/service-list/style.css' );
	}

	/**
	 * Body of the first rule for an exact selector.
	 *
	 * @param string $css      CSS.
	 * @param string $selector Selector text before the brace.
	 * @return string
	 */
	private function rule( string $css, string $selector ): string {
		$this->assertSame( 1, preg_match( '/(?:^|\})\s*' . preg_quote( $selector, '/' ) . ' \{([^}]*)\}/', $css, $m ), "Rule {$selector} missing." );

		return $m[1];
	}

	public function test_rows_are_the_top_level_services_only_in_order(): void {
		$b = $this->make_service( 'Service B', 2 );
		$a = $this->make_service( 'Service A', 1 );
		$this->make_service( 'Child Of A', 1, $a );

		$x    = $this->xpath( $this->render() );
		$rows = $x->query( '//li[contains(@class,"service-list__row")]' );

		$this->assertSame( 2, $rows->length );
		$this->assertSame( 'Service A', trim( $x->query( './/h3', $rows->item( 0 ) )->item( 0 )->textContent ) );
		$this->assertSame( 'Service B', trim( $x->query( './/h3', $rows->item( 1 ) )->item( 0 )->textContent ) );
		$this->assertSame( '01', trim( $x->query( './/*[contains(@class,"service-list__number")]', $rows->item( 0 ) )->item( 0 )->textContent ) );
		$this->assertSame( '02', trim( $x->query( './/*[contains(@class,"service-list__number")]', $rows->item( 1 ) )->item( 0 )->textContent ) );
		$this->assertNotSame( 0, $b );
	}

	public function test_row_shows_title_benefit_and_tags_in_order(): void {
		$parent = $this->make_service( 'Social Media Marketing', 1, 0, 'Strategic content that builds your audience.' );
		$this->make_service( 'Scheduled posting', 2, $parent );
		$this->make_service( 'Analytics', 1, $parent );

		$x = $this->xpath( $this->render() );

		$this->assertSame( 'Social Media Marketing', trim( $x->query( '//h3' )->item( 0 )->textContent ) );
		$this->assertSame( 'Strategic content that builds your audience.', trim( $x->query( '//p[contains(@class,"service-list__benefit")]' )->item( 0 )->textContent ) );

		$tags = array();
		foreach ( $x->query( '//li[contains(@class,"service-list__tag")]' ) as $li ) {
			$tags[] = trim( $li->textContent );
		}

		$this->assertSame( array( 'Analytics', 'Scheduled posting' ), $tags );
	}

	public function test_tags_are_chips_that_are_not_links_or_buttons(): void {
		$parent = $this->make_service( 'Parent', 1 );
		$this->make_service( 'Tag', 1, $parent );

		$x = $this->xpath( $this->render() );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]//a' )->length );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]//button' )->length );

		// Chip per the lo-fi wireframe: 1px light outline, 12px radius, grey text, white fill; no dots between them.
		$css  = $this->css();
		$chip = $this->rule( $css, '.service-list__tag' );
		$this->assertStringContainsString( 'border: 1px solid var(--wp--preset--color--line)', $chip );
		$this->assertStringContainsString( 'border-radius: 12px', $chip );
		$this->assertStringContainsString( 'background: var(--wp--preset--color--base)', $chip );
		$this->assertStringNotContainsString( '.service-list__tag + .service-list__tag::before', $css );
	}

	public function test_the_whole_row_highlights_on_hover(): void {
		$css = $this->css();

		// The highlight is on the row itself (the li), so it covers the number, title, benefit and chips.
		$this->assertStringContainsString( 'background: var(--wp--preset--color--fill)', $this->rule( $css, '.service-list__row:hover' ) );
		$this->assertStringContainsString( 'transition: background-color', $this->rule( $css, '.service-list__row' ) );
	}

	public function test_service_rows_have_no_links(): void {
		$parent = $this->make_service( 'Digital Marketing', 1 );
		$this->make_service( 'Ad analytics', 1, $parent );

		$html = $this->render();
		$x    = $this->xpath( $html );

		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__row")]//a' )->length );
		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( 'service-list__link', $this->css() );
		$this->assertStringNotContainsString( 'service-list__link', $html );
	}

	public function test_no_arrow_and_no_lines(): void {
		$this->make_service( 'Parent', 1 );
		$html = $this->render();

		foreach ( array( '→', '›', '»', '<svg' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html );
		}

		$css = $this->css();
		$this->assertStringNotContainsString( 'border-bottom', $css );
		$this->assertStringNotContainsString( 'border-top', $css );
		// Only the chips have an outline; the rows and the list have none.
		$without_chips = preg_replace( '/\.service-list__tag \{[^}]*\}/', '', $css );
		$this->assertDoesNotMatchRegularExpression( '/\bborder\s*:/', (string) $without_chips );
	}

	public function test_drafts_are_skipped_and_a_row_without_children_has_no_tag_list(): void {
		$this->make_service( 'Draft parent', 1, 0, '', 'draft' );
		$parent = $this->make_service( 'Live parent', 2 );
		$this->make_service( 'Draft child', 1, $parent, '', 'draft' );

		$x = $this->xpath( $this->render() );

		$this->assertSame( 1, $x->query( '//li[contains(@class,"service-list__row")]' )->length );
		$this->assertSame( 0, $x->query( '//ul[contains(@class,"service-list__tags")]' )->length );
		$this->assertSame( 0, $x->query( '//li[contains(@class,"service-list__tag")]' )->length );
	}

	public function test_output_is_escaped(): void {
		$id = $this->make_service( 'Tom & Jerry', 1, 0, 'Plain <script>alert(1)</script> text' );

		$html = $this->render();

		$this->assertStringContainsString( esc_html( get_the_title( $id ) ), $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_nothing_renders_without_services(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_phone_rules(): void {
		$css   = $this->css();
		$start = strpos( $css, '@media (max-width: 640px)' );

		$this->assertNotFalse( $start );
		$phone = substr( $css, (int) $start );
		$this->assertMatchesRegularExpression( '/\.service-list__tags \{[^}]*display: none;/', $phone );
	}

	public function test_seeded_content_matches_the_stories(): void {
		$path = WP_PLUGIN_DIR . '/custom-post-types-dp/data/seeders/services.csv';
		if ( ! is_file( $path ) ) {
			$this->markTestSkipped( 'services.csv not found.' );
		}

		$handle = fopen( $path, 'r' );
		fgetcsv( $handle, 0, ',', '"', '' ); // Header: order,title,parent,content.
		$parents  = array();
		$children = array();
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( '' === $row[2] ) {
				$parents[ $row[1] ] = $row[3];
			} else {
				$children[ $row[2] ][] = $row[1];
			}
		}
		fclose( $handle );

		$this->assertSame(
			array(
				'Social Media Marketing'    => 'Strategic content that builds your audience.',
				'Digital Marketing'         => 'Marketing support that turns attention into customers.',
				'Web Creation / Management' => 'Websites built, updated and kept running.',
			),
			$parents
		);
		$this->assertSame( array( 'Analytics', 'Scheduled posting', 'Content calendar', 'Content creation', 'Short-form videos', 'AI-generated videos' ), $children['Social Media Marketing'] );
		$this->assertSame( array( 'Ad analytics', 'Brand collaterals', 'Digital creation', 'Product mockups' ), $children['Digital Marketing'] );
		$this->assertSame( array( 'Websites & portfolios', 'GoDaddy', 'WordPress' ), $children['Web Creation / Management'] );
	}
}
