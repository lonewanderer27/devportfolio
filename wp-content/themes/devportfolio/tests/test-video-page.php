<?php
/**
 * Tests for the Videos archive shell (DPV-000, DPV-001).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Video_Page extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function template(): string {
		return (string) file_get_contents( $this->theme_dir() . '/templates/archive-video.html' );
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
	 * Render the categories block exactly as the template places it, on the video archive.
	 */
	private function render_filter(): string {
		$category = $_GET['category'] ?? null;
		$this->go_to( get_post_type_archive_link( 'video' ) );
		if ( null !== $category ) {
			$_GET['category'] = $category;
		}

		return do_blocks( '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );
	}

	public function test_template_file_exists(): void {
		$this->assertFileExists( $this->theme_dir() . '/templates/archive-video.html' );
	}

	public function test_title_is_an_h3_with_the_documents_title_class(): void {
		$this->assertStringContainsString(
			'<h3 class="wp-block-heading documents-page__title">Videos</h3>',
			$this->template()
		);
		$this->assertStringContainsString( '<!-- wp:heading {"level":3,"className":"documents-page__title"} -->', $this->template() );
	}

	public function test_template_has_the_shared_page_shell(): void {
		$markup = $this->template();
		$this->assertStringContainsString( 'template-part {"slug":"header"', $markup );
		$this->assertStringContainsString( 'template-part {"slug":"footer"', $markup );
		$this->assertStringContainsString( 'class="wp-block-group documents-page videos-page"', $markup );
		$this->assertStringContainsString( '"layout":{"type":"constrained"}', $markup );
		$this->assertStringContainsString( 'class="wp-block-group documents-page__content"', $markup );
	}

	public function test_template_order_is_title_then_content_then_categories_block(): void {
		$markup = $this->template();
		$title   = strpos( $markup, 'documents-page__title">Videos</h3>' );
		$content = strpos( $markup, 'class="wp-block-group documents-page__content"' );
		$block   = strpos( $markup, '<!-- wp:devportfolio/document-categories {"taxonomy":"video_category","label":"Video categories"} /-->' );

		$this->assertNotFalse( $title );
		$this->assertNotFalse( $content );
		$this->assertNotFalse( $block );
		$this->assertLessThan( $content, $title );
		$this->assertLessThan( $block, $content );
	}

	public function test_template_has_no_intro_line_and_no_hire_me_bar(): void {
		$markup = $this->template();
		$this->assertStringNotContainsStringIgnoringCase( 'hire me', $markup );
		$this->assertStringNotContainsString( 'wp:paragraph', $markup );
		$this->assertStringNotContainsString( 'wp:button', $markup );
		$this->assertStringNotContainsString( 'wp:buttons', $markup );
	}

	public function test_template_has_no_document_or_collateral_blocks_yet(): void {
		$markup = $this->template();
		$this->assertStringNotContainsString( 'document-shelf', $markup );
		$this->assertStringNotContainsString( 'document-modal', $markup );
		$this->assertStringNotContainsString( 'collateral-grid', $markup );
		$this->assertStringNotContainsString( 'collateral-modal', $markup );
	}

	public function test_page_styles_have_no_videos_page_rules(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/assets/css/page.css' );
		$this->assertStringNotContainsString( 'videos-page', $css );
	}

	public function test_page_renders_title_and_filter_with_no_video(): void {
		wp_insert_term( 'Short Form', 'video_category' );
		wp_insert_term( 'AI Generated', 'video_category' );
		wp_insert_term( 'Video Edits', 'video_category' );

		$html = $this->render_filter();

		$this->assertStringContainsString( 'aria-label="Video categories"', $html );
		$this->assertStringContainsString( 'role="group"', $html );
		// All + 3 terms = 4 pills.
		$this->assertSame( 4, substr_count( $html, 'data-filter="' ) );
		$this->assertStringNotContainsString( 'Notice', $html );
		$this->assertStringNotContainsString( 'Warning', $html );
	}

	public function test_page_renders_with_no_terms_and_no_video(): void {
		$html = $this->render_filter();

		$this->assertSame( 1, substr_count( $html, 'data-filter="' ) );
		$this->assertStringContainsString( 'data-filter="" aria-pressed="true"', $html );
	}

	public function test_page_with_a_published_video_still_renders_the_filter(): void {
		$term = wp_insert_term( 'Short Form', 'video_category' );
		$id   = self::factory()->post->create(
			array(
				'post_type'   => 'video',
				'post_status' => 'publish',
				'post_title'  => 'Test video',
			)
		);
		wp_set_object_terms( $id, array( (int) $term['term_id'] ), 'video_category' );

		$html = $this->render_filter();

		$this->assertSame( 2, substr_count( $html, 'data-filter="' ) );
		$this->assertStringContainsString( 'data-filter="short-form"', $html );
	}
}
