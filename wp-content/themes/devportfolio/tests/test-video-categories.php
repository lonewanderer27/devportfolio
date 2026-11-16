<?php
/**
 * Tests for the category filter on the Videos archive (DPV-002, DPV-003).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Categories extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}
		do_action( 'cptdp_register_post_types' );
		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-categories' ) ) {
			devportfolio_register_blocks();
		}
	}

	public function tear_down(): void {
		unset( $_GET['category'] );
		parent::tear_down();
	}

	/**
	 * Create the three story categories in a deliberately scrambled order, so only name order can sort them.
	 */
	private function create_terms(): void {
		wp_insert_term( 'Video Edits', 'video_category' );
		wp_insert_term( 'AI Generated', 'video_category' );
		wp_insert_term( 'Short Form', 'video_category' );
	}

	private function render(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'video' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}

		return do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );
	}

	/**
	 * data-filter values of the pills, in document order.
	 *
	 * @return string[]
	 */
	private function filters( string $html ): array {
		preg_match_all( '/data-filter="([^"]*)"/', $html, $matches );

		return $matches[1];
	}

	public function test_lists_video_terms_in_name_order_with_all_first(): void {
		$this->create_terms();

		$this->assertSame( array( '', 'ai-generated', 'short-form', 'video-edits' ), $this->filters( $this->render() ) );
	}

	public function test_all_is_pressed_by_default(): void {
		$this->create_terms();
		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'data-filter="short-form" aria-pressed="false"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">All<', $html );
	}

	public function test_empty_terms_are_listed_and_other_taxonomies_do_not_leak(): void {
		wp_insert_term( 'Reports', 'document_category' );
		wp_insert_term( 'Flyer', 'collateral_category' );
		wp_insert_term( 'Empty Category', 'video_category' );

		$html = $this->render();

		$this->assertStringContainsString( '>Empty Category<', $html );
		$this->assertStringNotContainsString( '>Reports<', $html );
		$this->assertStringNotContainsString( '>Flyer<', $html );
	}

	public function test_url_category_marks_that_pill_pressed(): void {
		$this->create_terms();
		$_GET['category'] = 'short-form';

		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="short-form" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'data-filter="" aria-pressed="false"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">Short Form<', $html );
	}

	/**
	 * @dataProvider unknown_categories
	 */
	public function test_unknown_category_falls_back_to_all( string $value ): void {
		$this->create_terms();
		wp_insert_term( 'Reports', 'document_category' );
		$_GET['category'] = $value;

		$html = $this->render();

		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
		$this->assertStringContainsString( 'documents-categories__trigger-label">All<', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function unknown_categories(): array {
		return array(
			'unknown slug'                => array( 'nope' ),
			'empty'                       => array( '' ),
			'slug of another taxonomy'    => array( 'reports' ),
			'markup'                      => array( '<script>' ),
		);
	}

	public function test_group_is_labelled_video_categories(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'aria-label="Video categories"', $html );
		$this->assertStringContainsString( 'role="group"', $html );
	}

	public function test_view_script_keeps_the_filter_contract_the_video_grid_will_use(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/view.js' );

		$this->assertStringContainsString( 'devportfolio:category-change', $js );
		$this->assertStringContainsString( 'pushState', $js );
		$this->assertStringContainsString( 'popstate', $js );
		$this->assertStringContainsString( 'startViewTransition', $js );
	}
}
