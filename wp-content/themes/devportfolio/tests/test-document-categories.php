<?php
/**
 * Tests for the document category pills (DPD-002, DPD-003).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Categories extends WP_UnitTestCase {

	/**
	 * Theme directory.
	 *
	 * @return string
	 */
	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// Terms are wiped and meta unregistered between tests; make sure the plugin's taxonomy exists.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Create a document_category term.
	 *
	 * @param string $name Term name.
	 * @return void
	 */
	private function make_term( string $name ): void {
		$result = wp_insert_term( $name, 'document_category' );
		$this->assertNotWPError( $result );
	}

	/**
	 * Render the block on the Documents archive. go_to() rebuilds $_GET from the URL,
	 * so a category set by the test is restored afterwards.
	 *
	 * @return string
	 */
	private function render_block_on_archive(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'document' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}
		return do_blocks( '<!-- wp:devportfolio/document-categories /-->' );
	}

	/**
	 * Render the block on the Documents archive and return its pills.
	 *
	 * @return array<int, array{label:string,filter:string,pressed:string}>
	 */
	private function pills(): array {
		$html = $this->render_block_on_archive();

		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		$out = array();
		foreach ( $doc->getElementsByTagName( 'button' ) as $button ) {
			$out[] = array(
				'label'   => $button->textContent,
				'filter'  => $button->getAttribute( 'data-filter' ),
				'pressed' => $button->getAttribute( 'aria-pressed' ),
			);
		}
		return $out;
	}

	public function tear_down(): void {
		unset( $_GET['category'] );
		parent::tear_down();
	}

	public function test_url_category_marks_that_pill_pressed(): void {
		$this->make_term( 'Reports' );
		$this->make_term( 'Executive Docs' );
		$_GET['category'] = 'reports';

		$pressed = array_column( $this->pills(), 'pressed', 'filter' );

		$this->assertSame( 'true', $pressed['reports'] );
		$this->assertSame( 'false', $pressed[''] );
		$this->assertSame( 'false', $pressed['executive-docs'] );
	}

	/**
	 * @dataProvider bad_category_values
	 */
	public function test_bad_url_category_falls_back_to_all( string $value ): void {
		$this->make_term( 'Reports' );
		$_GET['category'] = $value;

		$pressed = array_column( $this->pills(), 'pressed', 'filter' );

		$this->assertSame( 'true', $pressed[''] );
		$this->assertSame( 'false', $pressed['reports'] );
	}

	public function bad_category_values(): array {
		return array(
			'unknown slug' => array( 'nope' ),
			'empty'        => array( '' ),
			'all literal'  => array( 'all' ),
			'markup'       => array( '<script>alert(1)</script>' ),
		);
	}

	public function test_url_category_value_is_never_echoed(): void {
		$this->make_term( 'Reports' );
		$_GET['category'] = '<script>alert(1)</script>';

		$html = $this->render_block_on_archive();

		$this->assertStringNotContainsString( 'alert(1)', $html );
	}

	public function test_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) );
	}

	public function test_all_is_first_and_present_without_terms(): void {
		$pills = $this->pills();

		$this->assertCount( 1, $pills );
		$this->assertSame( 'All', $pills[0]['label'] );
		$this->assertSame( '', $pills[0]['filter'] );
		$this->assertSame( 'true', $pills[0]['pressed'] );
	}

	public function test_all_is_first_with_terms(): void {
		$this->make_term( 'Reports' );
		$this->make_term( 'Executive Docs' );

		$pills = $this->pills();

		$this->assertCount( 3, $pills );
		$this->assertSame( 'All', $pills[0]['label'] );
	}

	public function test_terms_follow_seeded_name_order_with_term_name_labels(): void {
		// Created out of order to prove ordering is not insertion order.
		foreach ( array( 'Reports', 'Presentation Decks', 'Executive Docs', 'Operations Docs' ) as $name ) {
			$this->make_term( $name );
		}

		$labels = wp_list_pluck( $this->pills(), 'label' );

		$this->assertSame(
			array( 'All', 'Executive Docs', 'Operations Docs', 'Presentation Decks', 'Reports' ),
			$labels
		);
	}

	public function test_filter_value_is_term_slug(): void {
		$this->make_term( 'Executive Docs' );

		$pills = $this->pills();

		$this->assertSame( 'executive-docs', $pills[1]['filter'] );
	}

	public function test_only_all_is_pressed_by_default(): void {
		$this->make_term( 'Reports' );
		$this->make_term( 'Executive Docs' );

		$pressed = wp_list_pluck( $this->pills(), 'pressed' );

		$this->assertSame( array( 'true', 'false', 'false' ), $pressed );
	}

	public function test_empty_terms_are_listed(): void {
		$this->make_term( 'Reports' );

		$this->assertSame( array( 'All', 'Reports' ), wp_list_pluck( $this->pills(), 'label' ) );
	}

	public function test_term_name_is_escaped(): void {
		$this->make_term( 'R&D <b>x</b>' );

		$html = $this->render_block_on_archive();

		// Core may strip the tags on save; either way no raw markup or bare ampersand may reach the page.
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertMatchesRegularExpression( '/R&amp;D/', $html );
	}

	public function test_pills_are_buttons_in_a_labelled_group(): void {
		$html = $this->render_block_on_archive();

		$this->assertStringContainsString( 'role="group"', $html );
		$this->assertStringContainsString( 'aria-label="Document categories"', $html );
		$this->assertStringContainsString( 'type="button"', $html );
	}

	public function test_template_places_block_under_title_inside_content_container(): void {
		$markup = (string) file_get_contents( $this->theme_dir() . '/templates/archive-document.html' );

		$title     = strpos( $markup, '<h3 class="wp-block-heading">Documents</h3>' );
		$container = strpos( $markup, 'documents-page__content' );
		$block     = strpos( $markup, '<!-- wp:devportfolio/document-categories /-->' );
		$close     = strpos( $markup, '</div>', $block );

		$this->assertNotFalse( $title );
		$this->assertNotFalse( $block );
		$this->assertLessThan( $container, $title );
		$this->assertLessThan( $block, $container );
		$this->assertNotFalse( $close );
	}

	/**
	 * Block stylesheet.
	 *
	 * @return string
	 */
	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/style.css' );
	}

	/**
	 * Declarations of the first rule whose selector contains $selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Selector fragment.
	 * @return string
	 */
	private function rule( string $css, string $selector ): string {
		$this->assertSame( 1, preg_match( '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $m ), "Rule {$selector} missing." );
		return $m[1];
	}

	public function test_pill_style_is_outlined_and_fully_rounded(): void {
		$rule = $this->rule( $this->css(), '.documents-categories__pill' );

		$this->assertStringContainsString( 'border: 1.5px solid var(--wp--preset--color--contrast)', $rule );
		$this->assertStringContainsString( 'border-radius: 9999px', $rule );
	}

	public function test_active_pill_is_filled_dark_with_light_text(): void {
		$rule = $this->rule( $this->css(), '.documents-categories__pill[aria-pressed="true"]' );

		$this->assertStringContainsString( 'background: var(--wp--preset--color--contrast)', $rule );
		$this->assertStringContainsString( 'color: var(--wp--preset--color--base)', $rule );
	}

	public function test_desktop_group_wraps(): void {
		$rule = $this->rule( $this->css(), '.wp-block-devportfolio-document-categories' );

		$this->assertStringContainsString( 'flex-wrap: wrap', $rule );
	}

	public function test_mobile_group_is_a_sticky_single_scrolling_row(): void {
		$css = $this->css();
		$this->assertSame( 1, preg_match( '/@media \(max-width: 820px\)\s*\{(.*)\}\s*$/s', $css, $m ), 'Mobile media query missing.' );

		$rule = $this->rule( $m[1], '.wp-block-devportfolio-document-categories' );
		$this->assertStringContainsString( 'position: sticky', $rule );
		$this->assertStringContainsString( 'overflow-x: auto', $rule );
		$this->assertStringContainsString( 'flex-wrap: nowrap', $rule );
	}

	public function test_view_script_is_declared_and_present(): void {
		$json = json_decode( (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/block.json' ), true );

		$this->assertSame( 'file:./view.js', $json['viewScript'] );
		$this->assertFileExists( $this->theme_dir() . '/blocks/document-categories/view.js' );
	}
}
