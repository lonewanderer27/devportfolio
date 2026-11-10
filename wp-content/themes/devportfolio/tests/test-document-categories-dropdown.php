<?php
/**
 * Tests for the mobile document category dropdown (DPD-041).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Categories_Dropdown extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function make_term( string $name ): void {
		$this->assertNotWPError( wp_insert_term( $name, 'document_category' ) );
	}

	private function render(): DOMXPath {
		$html = do_blocks( '<!-- wp:devportfolio/document-categories /-->' );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		return new DOMXPath( $doc );
	}

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/style.css' );
	}

	private function js(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/view.js' );
	}

	public function test_trigger_comes_first_and_shows_all_by_default(): void {
		$html    = do_blocks( '<!-- wp:devportfolio/document-categories /-->' );
		$xpath   = $this->render();
		$trigger = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger ')]" )->item( 0 );
		$list    = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__list ')]" )->item( 0 );

		$this->assertNotNull( $trigger );
		$this->assertNotNull( $list );
		$this->assertLessThan( strpos( $html, 'documents-categories__list' ), strpos( $html, 'documents-categories__trigger' ) );
		$this->assertSame( 'All', trim( $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger-label ')]", $trigger )->item( 0 )->textContent ) );
		$this->assertSame( 'false', $trigger->getAttribute( 'aria-expanded' ) );
		$this->assertSame( '', $trigger->getAttribute( 'class' ) === '' ? '' : ( str_contains( ' ' . $trigger->getAttribute( 'class' ) . ' ', ' is-active ' ) ? 'is-active' : '' ) );
	}

	public function test_trigger_shows_the_active_category_name(): void {
		$this->make_term( 'Reports' );
		$this->make_term( 'Executive Docs' );
		$_GET['category'] = 'reports';

		$xpath   = $this->render();
		$trigger = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger ')]" )->item( 0 );

		$this->assertStringContainsString( 'is-active', $trigger->getAttribute( 'class' ) );
		$this->assertSame( 'Reports', trim( $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger-label ')]", $trigger )->item( 0 )->textContent ) );
	}

	/**
	 * @dataProvider bad_categories
	 */
	public function test_trigger_falls_back_to_all_for_a_bad_category( string $value ): void {
		$this->make_term( 'Reports' );
		$_GET['category'] = $value;
		$xpath            = $this->render();
		$label            = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger-label ')]" )->item( 0 );

		$this->assertSame( 'All', trim( $label->textContent ) );
	}

	public function bad_categories(): array {
		return array( array( 'nope' ), array( '' ), array( 'all' ), array( '<script>alert(1)</script>' ) );
	}

	public function test_trigger_label_is_escaped(): void {
		$this->make_term( 'R&D <b>x</b>' );
		$_GET['category'] = 'r-d-x';
		$html             = do_blocks( '<!-- wp:devportfolio/document-categories /-->' );

		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringContainsString( 'R&amp;D', $html );
	}

	public function test_trigger_controls_the_list_and_rows_keep_the_pill_contract(): void {
		$this->make_term( 'Reports' );
		$this->make_term( 'Executive Docs' );
		$xpath   = $this->render();
		$trigger = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger ')]" )->item( 0 );
		$list    = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__list ')]" )->item( 0 );
		$pills   = $xpath->query( ".//button[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__pill ')]", $list );

		$this->assertSame( $list->getAttribute( 'id' ), $trigger->getAttribute( 'aria-controls' ) );
		$this->assertSame( 'All', trim( $pills->item( 0 )->textContent ) );
		$this->assertSame( 'Executive Docs', trim( $pills->item( 1 )->textContent ) );
		$this->assertSame( 'Reports', trim( $pills->item( 2 )->textContent ) );
		$this->assertSame( '', $pills->item( 0 )->getAttribute( 'data-filter' ) );
	}

	public function test_trigger_has_a_screen_reader_prefix_and_hidden_arrow(): void {
		$xpath   = $this->render();
		$trigger = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger ')]" )->item( 0 );
		$prefix  = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__trigger-prefix ')]", $trigger )->item( 0 );
		$arrow   = $xpath->query( './/svg', $trigger )->item( 0 );

		$this->assertSame( 'Category:', trim( $prefix->textContent ) );
		$this->assertSame( 'true', $arrow->getAttribute( 'aria-hidden' ) );
	}

	public function test_mobile_dropdown_has_a_visible_category_label_beside_the_trigger(): void {
		$xpath = $this->render();
		$label = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' documents-categories__label ')]" )->item( 0 );

		$this->assertSame( 'Category:', trim( $label->textContent ) );
		$this->assertSame( 'true', $label->getAttribute( 'aria-hidden' ) );
		$this->assertStringContainsString( 'display: inline', $this->css() );
	}

	public function test_group_role_and_label_are_unchanged(): void {
		$xpath = $this->render();
		$group = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-devportfolio-document-categories ')]" )->item( 0 );

		$this->assertSame( 'group', $group->getAttribute( 'role' ) );
		$this->assertSame( 'Document categories', $group->getAttribute( 'aria-label' ) );
	}

	public function test_two_blocks_get_different_list_ids(): void {
		$html = do_blocks( '<!-- wp:devportfolio/document-categories /--><!-- wp:devportfolio/document-categories /-->' );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		$ids = array();
		foreach ( $doc->getElementsByTagName( 'div' ) as $div ) {
			if ( str_contains( ' ' . $div->getAttribute( 'class' ) . ' ', ' documents-categories__list ' ) ) {
				$ids[] = $div->getAttribute( 'id' );
			}
		}
		$this->assertCount( 2, array_unique( $ids ) );
	}

	public function test_phone_css_replaces_the_strip_with_a_dropdown(): void {
		$css = $this->css();

		$this->assertStringContainsString( '.documents-categories__trigger {', $css );
		$this->assertStringContainsString( '.documents-categories__list {', $css );
		$this->assertMatchesRegularExpression( '/@media \(max-width: 820px\)\s*\{.*\}\s*$/s', $css );
		$this->assertStringNotContainsString( 'overflow-x: auto', $css );
		$this->assertStringContainsString( 'min-height: 44px', $css );
		$this->assertStringContainsString( 'overscroll-behavior: contain', $css );
		$this->assertStringContainsString( 'prefers-reduced-motion: no-preference', $css );
	}

	public function test_script_contains_dropdown_behaviour_without_adding_a_history_listener(): void {
		$js = $this->js();

		$this->assertStringContainsString( '.documents-categories__trigger', $js );
		$this->assertStringContainsString( "aria-expanded", $js );
		$this->assertStringContainsString( "window.matchMedia( '(max-width: 820px)' )", $js );
		$this->assertStringContainsString( "'Escape'", $js );
		$this->assertStringContainsString( "'ArrowDown'", $js );
		$this->assertStringContainsString( "'pointerdown'", $js );
		$this->assertStringContainsString( "'focusout'", $js );
		$this->assertStringContainsString( 'function setOpen', $js );
		$this->assertStringContainsString( 'function syncTrigger', $js );
		$this->assertSame( 1, substr_count( $js, "'popstate'" ) );
		$this->assertMatchesRegularExpression( '/if \( ! mobile\.matches \) \{\s+pill\.scrollIntoView/s', $js );
	}
}
