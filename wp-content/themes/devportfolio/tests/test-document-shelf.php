<?php
/**
 * Tests for the documents shelf (DPD-016).
 *
 * The CSS is parsed from blocks/document-shelf/style.css; there is no browser runner.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Shelf extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-shelf' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Create a document.
	 *
	 * @param array $args { title, status, order, excerpt, categories: string[], tools: string[] }.
	 * @return int Post ID.
	 */
	private function make_document( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'document',
				'post_title'   => $args['title'] ?? 'Quarterly Report',
				'post_status'  => $args['status'] ?? 'publish',
				'menu_order'   => $args['order'] ?? 0,
				'post_excerpt' => $args['excerpt'] ?? 'A short description.',
				'post_content' => '',
			)
		);

		if ( ! empty( $args['categories'] ) ) {
			foreach ( $args['categories'] as $name ) {
				if ( ! term_exists( $name, 'document_category' ) ) {
					wp_insert_term( $name, 'document_category' );
				}
			}
			wp_set_object_terms( $post_id, $args['categories'], 'document_category' );
		}

		if ( ! empty( $args['tools'] ) ) {
			$ids = array();
			foreach ( $args['tools'] as $name ) {
				$ids[] = self::factory()->post->create(
					array(
						'post_type'   => 'software_tool',
						'post_title'  => $name,
						'post_status' => 'publish',
					)
				);
			}
			update_post_meta( $post_id, 'cptdp_software_tools', $ids );
		}

		return $post_id;
	}

	private function render(): string {
		return do_blocks( '<!-- wp:devportfolio/document-shelf /-->' );
	}

	/**
	 * Parse the rendered shelf.
	 *
	 * @return DOMXPath
	 */
	private function xpath(): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $this->render() . '</body>' );
		libxml_clear_errors();
		return new DOMXPath( $dom );
	}

	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	private function css(): string {
		$css = preg_replace( '#/\*.*?\*/#s', '', $this->read( 'blocks/document-shelf/style.css' ) );
		return trim( preg_replace( '/\s+/', ' ', $css ) );
	}

	private function page_css(): string {
		$css = preg_replace( '#/\*.*?\*/#s', '', $this->read( 'assets/css/page.css' ) );
		return trim( preg_replace( '/\s+/', ' ', $css ) );
	}

	private function rule( string $selector ): string {
		if ( preg_match( '/(?:^|[{}] )' . preg_quote( $selector, '/' ) . ' \{([^{}]*)\}/', $this->css(), $m ) ) {
			return $m[1];
		}
		return '';
	}

	private function page_rule( string $selector ): string {
		if ( preg_match( '/(?:^|[{}] )' . preg_quote( $selector, '/' ) . ' \{([^{}]*)\}/', $this->page_css(), $m ) ) {
			return $m[1];
		}
		return '';
	}

	private function media( string $query ): string {
		if ( preg_match( '/@media ' . preg_quote( $query, '/' ) . ' \{((?:[^{}]|\{[^{}]*\})*)\}/', $this->css(), $m ) ) {
			return $m[1];
		}
		return '';
	}

	// ----- Markup --------------------------------------------------------------------------

	public function test_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-shelf' ) );
	}

	public function test_nothing_renders_without_documents(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_shelf_carries_the_contract_attribute_and_items_are_direct_children(): void {
		$this->make_document( array( 'title' => 'One' ) );
		$this->make_document( array( 'title' => 'Two' ) );

		$xpath = $this->xpath();

		$this->assertSame( 1, $xpath->query( '//div[@data-documents-shelf]' )->length );
		$this->assertSame( 2, $xpath->query( '//div[@data-documents-shelf]/article[@data-document-categories]' )->length );
		$this->assertSame( 2, $xpath->query( '//article' )->length, 'Every article is a direct child of the shelf.' );
	}

	public function test_only_published_documents_in_menu_order_then_title(): void {
		$this->make_document( array( 'title' => 'Zeta', 'order' => 10 ) );
		$this->make_document( array( 'title' => 'Bravo', 'order' => 20 ) );
		$this->make_document( array( 'title' => 'Alpha', 'order' => 20 ) );
		$this->make_document( array( 'title' => 'Hidden draft', 'status' => 'draft', 'order' => 1 ) );

		$titles = array();
		foreach ( $this->xpath()->query( '//button[@data-document-id]' ) as $button ) {
			$titles[] = $button->textContent;
		}

		$this->assertSame( array( 'Zeta', 'Alpha', 'Bravo' ), $titles );
	}

	public function test_each_item_has_the_cover_one_open_button_and_the_id(): void {
		$id = $this->make_document( array( 'title' => 'Handbook' ) );

		$xpath = $this->xpath();

		$this->assertSame( 1, $xpath->query( '//article//div[contains(@class,"documents-book")]' )->length );
		$buttons = $xpath->query( '//article//button[@data-document-id]' );
		$this->assertSame( 1, $buttons->length );
		$this->assertSame( (string) $id, $buttons->item( 0 )->getAttribute( 'data-document-id' ) );
		$this->assertSame( 'button', $buttons->item( 0 )->getAttribute( 'type' ) );
	}

	public function test_info_order_is_cover_then_title_description_tags(): void {
		$this->make_document( array( 'tools' => array( 'Figma' ) ) );

		$xpath = $this->xpath();
		$info  = $xpath->query( '//article/*' );

		$this->assertStringContainsString( 'documents-book', $info->item( 0 )->getAttribute( 'class' ) );
		$this->assertStringContainsString( 'documents-item__info', $info->item( 1 )->getAttribute( 'class' ) );

		$children = array();
		foreach ( $xpath->query( '//div[contains(@class,"documents-item__info")]/*' ) as $child ) {
			$children[] = $child->nodeName;
		}
		$this->assertSame( array( 'h3', 'p', 'ul' ), $children );
	}

	public function test_categories_attribute_lists_the_term_slugs(): void {
		$this->make_document( array( 'categories' => array( 'Reports', 'Executive Docs' ) ) );
		$this->make_document( array( 'title' => 'Uncategorised' ) );

		$values = array();
		foreach ( $this->xpath()->query( '//article' ) as $article ) {
			$values[] = $article->getAttribute( 'data-document-categories' );
		}
		sort( $values );

		$this->assertSame( '', $values[0] );
		$this->assertEqualSets( array( 'executive-docs', 'reports' ), explode( ' ', $values[1] ) );
	}

	public function test_description_comes_from_the_excerpt_and_is_omitted_when_empty(): void {
		$this->make_document( array( 'title' => 'With', 'excerpt' => 'Short words.' ) );
		$this->make_document( array( 'title' => 'Without', 'excerpt' => '' ) );

		$xpath = $this->xpath();

		$this->assertSame( 'Short words.', $xpath->query( '//p[contains(@class,"documents-item__desc")]' )->item( 0 )->textContent );
		$this->assertSame( 1, $xpath->query( '//p[contains(@class,"documents-item__desc")]' )->length );
	}

	public function test_no_tag_list_without_tools(): void {
		$this->make_document();

		$this->assertSame( 0, $this->xpath()->query( '//ul' )->length );
	}

	public function test_tags_are_rendered_by_the_software_tags_block(): void {
		$this->make_document( array( 'tools' => array( 'Figma', 'Excel', 'Canva' ) ) );

		$xpath = $this->xpath();

		$this->assertSame( 1, $xpath->query( '//div[contains(@class,"documents-item__info")]/ul[contains(@class,"software-tags")]' )->length );
		$this->assertSame( 4, $xpath->query( '//ul[contains(@class,"software-tags")]/li' )->length, 'Three tags and the +N tag.' );
	}

	public function test_output_is_escaped(): void {
		$this->make_document( array( 'title' => 'A <b>bold</b> & "quoted"', 'excerpt' => '<script>alert(1)</script>Text' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( '<b>bold</b>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_item_helper_returns_null_for_non_documents(): void {
		$this->assertNull( devportfolio_document_shelf_item( self::factory()->post->create() ) );
		$this->assertNull( devportfolio_document_shelf_item( 0 ) );
	}

	// ----- CSS -----------------------------------------------------------------------------

	public function test_grid_fits_as_many_columns_as_the_width_allows(): void {
		$rule = $this->rule( '.documents-shelf' );

		$this->assertStringContainsString( 'display: grid', $rule );
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr))', $rule );
	}

	public function test_narrow_screens_allow_smaller_books_so_a_phone_gets_two_columns(): void {
		$tablet = $this->media( '(max-width: 820px)' );

		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(min(100%, 170px), 1fr))', $tablet );
		// Phones: the floor is just under half the width, so two columns fit and a third never does (360px and 375px).
		$this->assertStringContainsString( 'grid-template-columns: repeat(auto-fill, minmax(max(110px, calc(50% - 16px)), 1fr))', $this->media( '(max-width: 480px)' ) );
	}

	public function test_page_is_wider_than_the_content_width_with_side_margins(): void {
		$this->assertStringContainsString( 'padding-inline: clamp(0px, 3vw, 32px)', $this->page_rule( '.documents-page' ) );
		$this->assertStringContainsString( '--wp--style--global--content-size: 1440px', $this->page_rule( '.documents-page' ) );
	}

	public function test_no_fixed_column_counts_remain(): void {
		$this->assertDoesNotMatchRegularExpression( '/repeat\(\d/', $this->css() );
	}

	public function test_dot_grid_sits_behind_the_shelf_full_width(): void {
		$before = $this->rule( '.documents-shelf::before' );

		$this->assertStringContainsString( 'radial-gradient', $before );
		$this->assertStringContainsString( 'z-index: -1', $before );
		$this->assertStringContainsString( 'left: -100vw', $before );
		$this->assertStringContainsString( 'pointer-events: none', $before );
		$this->assertStringContainsString( 'overflow-x: clip', $this->page_rule( '.documents-page' ) );
	}

	public function test_hover_and_pressed_lift_the_cover_from_the_item(): void {
		$hover = $this->media( '(hover: hover)' );

		$this->assertStringContainsString( '.documents-item:hover .documents-book', $hover );
		$this->assertStringContainsString( 'translateY(-6px) rotate(-1deg)', $hover );
		$this->assertStringContainsString( 'scale(0.985)', $this->rule( '.documents-item:active .documents-book' ) );
	}

	public function test_items_rise_in_and_reduced_motion_disables_it(): void {
		$this->assertStringContainsString( 'documents-item-rise 360ms', $this->rule( '.documents-item' ) );
		$this->assertStringContainsString( 'animation: none', $this->media( '(prefers-reduced-motion: reduce)' ) );
	}

	public function test_every_item_has_its_own_view_transition_name(): void {
		$first  = $this->make_document( array( 'title' => 'One' ) );
		$second = $this->make_document( array( 'title' => 'Two' ) );

		$html = $this->render();

		$this->assertStringContainsString( 'view-transition-name:documents-item-' . $first, $html );
		$this->assertStringContainsString( 'view-transition-name:documents-item-' . $second, $html );
	}

	public function test_view_transition_styles_animate_books_and_planks(): void {
		$css = $this->css();

		$this->assertStringContainsString( 'view-transition-class: documents-item', $this->rule( '.documents-item' ) );
		$this->assertStringContainsString( 'view-transition-class: documents-item', $this->rule( '.documents-shelf__plank' ) );
		$this->assertStringContainsString( '::view-transition-group(*.documents-item) { animation-duration: 0.4s;', $css );
		$this->assertStringContainsString( 'animation: none', $this->rule( '.documents-shelf[data-view-transitions] .documents-item' ) );
		$this->assertStringContainsString( '::view-transition-new(*.documents-item)', $this->media( '(prefers-reduced-motion: reduce)' ) );
	}

	public function test_script_runs_the_filter_in_a_view_transition_and_names_the_planks(): void {
		$js = $this->read( 'blocks/document-categories/view.js' );

		$this->assertStringContainsString( 'document.startViewTransition( function', $js );
		$this->assertStringContainsString( "'documents-plank-' + row", $js );
		$this->assertStringContainsString( "'data-view-transitions'", $js );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $js );
	}

	public function test_titles_are_hidden_by_default(): void {
		$this->make_document( array( 'title' => 'Handbook' ) );

		$this->assertStringContainsString( 'documents-shelf--hide-titles', $this->render() );
	}

	public function test_setting_shows_the_title(): void {
		$this->make_document( array( 'title' => 'Handbook' ) );
		update_option( 'cptdp_documents', array( 'show_book_title' => true ) );

		$this->assertStringNotContainsString( 'documents-shelf--hide-titles', $this->render() );
	}

	public function test_hidden_title_keeps_the_open_button_and_its_name(): void {
		$id = $this->make_document( array( 'title' => 'Handbook' ) );

		$xpath = $this->xpath();

		$this->assertSame( 1, $xpath->query( '//div[contains(@class,"documents-shelf--hide-titles")]' )->length );
		$buttons = $xpath->query( '//button[@data-document-id="' . $id . '"]' );
		$this->assertSame( 1, $buttons->length );
		$this->assertStringContainsString( 'Handbook', $buttons->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 1, $xpath->query( '//p[contains(@class,"documents-item__desc")]' )->length, 'Description stays.' );
	}

	public function test_hide_titles_css_keeps_the_button_clickable(): void {
		$this->assertStringContainsString( 'font-size: 0', $this->rule( '.documents-shelf--hide-titles .documents-item__open' ) );
		$this->assertStringNotContainsString( 'display: none', $this->rule( '.documents-shelf--hide-titles .documents-item__open' ) );
		$this->assertStringNotContainsString( 'position', $this->rule( '.documents-shelf--hide-titles .documents-item__title' ) );
	}

	public function test_open_button_names_the_document_and_format(): void {
		$this->make_document( array( 'title' => 'Handbook' ) );

		$label = $this->xpath()->query( '//button[@data-document-id]' )->item( 0 )->getAttribute( 'aria-label' );

		$this->assertStringContainsString( 'Handbook', $label );
	}

	public function test_plank_rule(): void {
		$rule = $this->rule( '.documents-shelf__plank' );

		$this->assertStringContainsString( 'grid-column: 1 / -1', $rule );
		$this->assertStringContainsString( 'height: 14px', $rule );
		$this->assertStringContainsString( 'linear-gradient', $rule );
		$this->assertStringContainsString( 'box-shadow', $rule );
		$this->assertStringContainsString( 'height: 11px', $this->media( '(max-width: 820px)' ) );
	}

	public function test_description_clamp_applies_only_at_480_and_below(): void {
		$mobile = $this->media( '(max-width: 480px)' );

		$this->assertStringContainsString( '-webkit-line-clamp: 2', $mobile );

		$this->assertStringNotContainsString( 'line-clamp', $this->rule( '.documents-item__desc' ) );
		$this->assertStringNotContainsString( 'line-clamp', $this->media( '(max-width: 820px)' ) );
	}

	public function test_hidden_items_stay_hidden(): void {
		$this->assertStringContainsString( 'display: none', $this->rule( '.documents-item[hidden]' ) );
	}

	public function test_open_button_covers_the_whole_item(): void {
		$this->assertStringContainsString( 'position: relative', $this->rule( '.documents-item' ) );

		$after = $this->rule( '.documents-item__open::after' );
		$this->assertStringContainsString( 'position: absolute', $after );
		$this->assertStringContainsString( 'inset: 0', $after );
	}

	public function test_keyboard_focus_is_visible_on_the_item(): void {
		$this->assertStringContainsString( 'outline: 2px solid', $this->rule( '.documents-item:has(.documents-item__open:focus-visible)' ) );
	}

	// ----- Template ------------------------------------------------------------------------

	public function test_template_places_the_shelf_after_the_categories_and_no_fixture_remains(): void {
		$template = $this->read( 'templates/archive-document.html' );

		$this->assertStringContainsString( '<!-- wp:devportfolio/document-shelf /-->', $template );
		$this->assertLessThan(
			strpos( $template, 'wp:devportfolio/document-shelf' ),
			strpos( $template, 'wp:devportfolio/document-categories' )
		);
		$this->assertStringNotContainsString( 'TEMP', $template );
		$this->assertStringNotContainsString( 'Placeholder:', $template );
	}
}
