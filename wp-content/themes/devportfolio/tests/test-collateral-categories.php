<?php
/**
 * Tests for the taxonomy-aware category filter on the Collaterals archive.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Categories extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	private function render(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'collateral' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}
		return do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"collateral_category","label":"Collateral categories"} /-->' );
	}

	public function tear_down(): void {
		unset( $_GET['category'] );
		parent::tear_down();
	}

	public function test_lists_collateral_terms_in_name_order_with_all_first(): void {
		wp_insert_term( 'Logo', 'collateral_category' );
		wp_insert_term( 'Flyer', 'collateral_category' );
		$html = $this->render();
		$this->assertStringContainsString( 'data-filter=""', $html );
		$this->assertLessThan( strpos( $html, '>Logo<' ), strpos( $html, '>Flyer<' ) );
		$this->assertStringContainsString( 'data-filter="flyer"', $html );
		$this->assertStringContainsString( 'data-filter="logo"', $html );
	}

	public function test_empty_terms_are_listed_and_document_terms_do_not_leak(): void {
		wp_insert_term( 'Reports', 'document_category' );
		wp_insert_term( 'Empty', 'collateral_category' );
		$html = $this->render();
		$this->assertStringContainsString( '>Empty<', $html );
		$this->assertStringNotContainsString( '>Reports<', $html );
	}

	public function test_url_category_marks_that_pill_pressed_and_unknown_falls_back(): void {
		wp_insert_term( 'Logo', 'collateral_category' );
		$_GET['category'] = 'logo';
		$html = $this->render();
		$this->assertStringContainsString( 'data-filter="logo" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">Logo<', $html );

		$_GET['category'] = '<script>';
		$html = $this->render();
		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_group_is_labelled_and_unknown_taxonomy_renders_nothing(): void {
		$this->assertStringContainsString( 'aria-label="Collateral categories"', $this->render() );
		$this->assertSame( '', do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"nope"} /-->' ) );
	}

	public function test_default_attributes_still_render_document_categories(): void {
		wp_insert_term( 'Reports', 'document_category' );
		$this->assertStringContainsString( 'aria-label="Document categories"', do_blocks( '<!-- wp:devportfolio/document-categories /-->' ) );
		$this->assertStringContainsString( 'data-filter="reports"', do_blocks( '<!-- wp:devportfolio/document-categories /-->' ) );
	}
}
