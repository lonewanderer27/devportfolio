<?php
/**
 * Tests for the Brand Collaterals grid and item (DPC-006, DPC-014).
 *
 * Markup is checked with DOMDocument. The CSS and scripts are checked as text, since there is no browser runner.
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Collateral_Grid extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/collateral-grid' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Term ID of a term, created when missing.
	 */
	private function term( string $name, string $taxonomy ): int {
		$found = term_exists( $name, $taxonomy );
		if ( $found ) {
			return (int) ( is_array( $found ) ? $found['term_id'] : $found );
		}
		return (int) wp_insert_term( $name, $taxonomy )['term_id'];
	}

	/**
	 * Create a collateral.
	 *
	 * @param array $args { title, status, order, category, paper, pages }.
	 * @return int Post ID.
	 */
	private function make_collateral( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'collateral',
				'post_title'   => $args['title'] ?? 'Product Launch Flyer',
				'post_status'  => $args['status'] ?? 'publish',
				'menu_order'   => $args['order'] ?? 0,
				'post_content' => '',
			)
		);

		if ( isset( $args['category'] ) ) {
			wp_set_object_terms( $post_id, array( $this->term( $args['category'], 'collateral_category' ) ), 'collateral_category' );
		}
		if ( isset( $args['paper'] ) ) {
			update_post_meta( $post_id, 'cptdp_paper_size', $args['paper'] );
		}
		if ( isset( $args['pages'] ) ) {
			update_post_meta( $post_id, 'cptdp_total_pages', $args['pages'] );
		}

		return $post_id;
	}

	private function render_grid(): string {
		return trim( do_blocks( '<!-- wp:devportfolio/collateral-grid /-->' ) );
	}

	private function xpath( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	/**
	 * Parsed `@media (...) { ... }` block bodies, matched by their opening line.
	 */
	private function media_block( string $css, string $query ): string {
		$pattern = '/@media ' . preg_quote( $query, '/' ) . '\s*\{(.*?)\n\}/s';
		return preg_match( $pattern, $css, $match ) ? $match[1] : '';
	}

	/**
	 * The declarations of the first `selector { ... }` block, whitespace collapsed.
	 */
	private function rule( string $selector ): string {
		$css = preg_replace( '#/\*.*?\*/#s', '', $this->css() );
		$css = trim( preg_replace( '/\s+/', ' ', (string) $css ) );

		if ( preg_match( '/(?:^|[{}] )' . preg_quote( $selector, '/' ) . ' \{([^{}]*)\}/', $css, $match ) ) {
			return $match[1];
		}

		return '';
	}

	// ---- Query ----

	public function test_nothing_is_rendered_without_published_collaterals(): void {
		$this->make_collateral( array( 'status' => 'draft' ) );
		self::factory()->post->create( array( 'post_type' => 'document', 'post_status' => 'publish', 'post_title' => 'A document' ) );

		$this->assertSame( '', $this->render_grid() );
	}

	public function test_items_follow_menu_order_then_title_and_drafts_are_excluded(): void {
		$zeta  = $this->make_collateral( array( 'title' => 'Zeta', 'order' => 0 ) );
		$alpha = $this->make_collateral( array( 'title' => 'Alpha', 'order' => 0 ) );
		$first = $this->make_collateral( array( 'title' => 'Mid', 'order' => -1 ) );
		$this->make_collateral( array( 'title' => 'Hidden', 'status' => 'draft', 'order' => -5 ) );

		$xpath = $this->xpath( $this->render_grid() );
		$ids   = array_map(
			static function ( DOMElement $button ): string {
				return $button->getAttribute( 'data-collateral-id' );
			},
			iterator_to_array( $xpath->query( '//button[@data-collateral-id]' ) )
		);

		$this->assertSame( array( (string) $first, (string) $alpha, (string) $zeta ), $ids );
	}

	// ---- Item structure ----

	public function test_items_are_direct_children_with_categories_and_a_transition_name(): void {
		$id = $this->make_collateral( array( 'category' => 'Flyer' ) );

		$xpath = $this->xpath( $this->render_grid() );
		$item  = $xpath->query( '//*[@data-collaterals-grid]/article[contains(concat(" ", normalize-space(@class), " "), " collaterals-item ")]' )->item( 0 );

		$this->assertInstanceOf( DOMElement::class, $item );
		$this->assertSame( 'flyer', $item->getAttribute( 'data-collateral-categories' ) );
		$this->assertStringContainsString( 'view-transition-name:collaterals-item-' . $id, $item->getAttribute( 'style' ) );
		$this->assertNotSame( '', $item->getAttribute( 'data-ratio' ) );
	}

	public function test_each_item_has_exactly_one_button_that_is_a_direct_child_of_the_item(): void {
		$this->make_collateral( array( 'title' => 'One' ) );
		$this->make_collateral( array( 'title' => 'Two' ) );

		$xpath = $this->xpath( $this->render_grid() );

		$this->assertSame( 2, $xpath->query( '//button' )->length );
		$this->assertSame( 2, $xpath->query( '//button[@data-collateral-id]' )->length );
		$this->assertSame( 2, $xpath->query( '//article/button[@data-collateral-id]' )->length );
		$this->assertSame( 2, $xpath->query( '//button[contains(concat(" ", normalize-space(@class), " "), " collaterals-item__open ")]' )->length );

		// The button is not inside the band: the band blurs (a filter is a containing block) and the
		// button has to cover the whole item.
		$this->assertSame( 0, $xpath->query( '//*[contains(@class, "collaterals-item__info")]//button' )->length );

		// The title keeps its heading, as text.
		$this->assertSame( 2, $xpath->query( '//h3[contains(concat(" ", normalize-space(@class), " "), " collaterals-item__title ")]' )->length );
	}

	public function test_aria_label_names_the_item_with_its_details(): void {
		$id = $this->make_collateral(
			array(
				'title'    => 'Product Launch Flyer',
				'category' => 'Flyer',
				'paper'    => 'A4',
				'pages'    => 2,
			)
		);

		$button = $this->xpath( $this->render_grid() )->query( '//button[@data-collateral-id="' . $id . '"]' )->item( 0 );

		$this->assertSame( 'Open Product Launch Flyer, Flyer · A4 · 2 pp', $button->getAttribute( 'aria-label' ) );
	}

	public function test_aria_label_is_the_title_alone_without_details(): void {
		$id = $this->make_collateral( array( 'title' => 'Plain Sheet' ) );

		$button = $this->xpath( $this->render_grid() )->query( '//button[@data-collateral-id="' . $id . '"]' )->item( 0 );

		$this->assertSame( 'Open Plain Sheet', $button->getAttribute( 'aria-label' ) );
		$this->assertSame( 0, $this->xpath( $this->render_grid() )->query( '//p[contains(@class, "collaterals-item__meta")]' )->length );
	}

	public function test_title_is_escaped_as_text(): void {
		$this->make_collateral( array( 'title' => 'Tom & Jerry "Quoted" <i>x</i>' ) );

		$xpath = $this->xpath( $this->render_grid() );
		$title = $xpath->query( '//h3[contains(@class, "collaterals-item__title")]' )->item( 0 );

		// The markup text is the title as typed: the tags are text, not elements.
		$this->assertStringContainsString( 'Tom & Jerry', $title->textContent );
		$this->assertStringContainsString( '<i>x</i>', $title->textContent );
		$this->assertSame( 0, $xpath->query( '//h3[contains(@class, "collaterals-item__title")]//i' )->length );
	}

	// ---- Meta line ----

	public function test_meta_line_joins_category_paper_and_pages(): void {
		$flyer = $this->make_collateral( array( 'category' => 'Flyer', 'paper' => 'A4', 'pages' => 2 ) );
		$logo  = $this->make_collateral( array( 'category' => 'Logo', 'paper' => 'A4', 'pages' => 1 ) );

		$this->assertSame( 'Flyer · A4 · 2 pp', devportfolio_collateral_meta_line( $flyer ) );
		$this->assertSame( 'Logo · A4 · 1 p', devportfolio_collateral_meta_line( $logo ) );
	}

	public function test_a_missing_part_is_left_out(): void {
		$no_category = $this->make_collateral( array( 'paper' => 'A4', 'pages' => 2 ) );
		$no_paper    = $this->make_collateral( array( 'category' => 'Flyer', 'pages' => 2 ) );
		$no_pages    = $this->make_collateral( array( 'category' => 'Flyer', 'paper' => 'A4', 'pages' => 'abc' ) );
		$zero_pages  = $this->make_collateral( array( 'category' => 'Flyer', 'paper' => 'A4', 'pages' => 0 ) );
		$nothing     = $this->make_collateral();

		$this->assertSame( 'A4 · 2 pp', devportfolio_collateral_meta_line( $no_category ) );
		$this->assertSame( 'Flyer · 2 pp', devportfolio_collateral_meta_line( $no_paper ) );
		$this->assertSame( 'Flyer · A4', devportfolio_collateral_meta_line( $no_pages ) );
		$this->assertSame( 'Flyer · A4', devportfolio_collateral_meta_line( $zero_pages ) );
		$this->assertSame( '', devportfolio_collateral_meta_line( $nothing ) );
	}

	public function test_with_nothing_to_show_no_meta_paragraph_is_printed(): void {
		$this->make_collateral( array( 'title' => 'Bare' ) );

		$this->assertSame( 0, $this->xpath( $this->render_grid() )->query( '//p[contains(@class, "collaterals-item__meta")]' )->length );
	}

	public function test_the_first_category_by_name_wins(): void {
		$id = $this->make_collateral();
		wp_set_object_terms( $id, array( $this->term( 'Zeta Kind', 'collateral_category' ), $this->term( 'Alpha Kind', 'collateral_category' ) ), 'collateral_category' );

		$this->assertSame( 'Alpha Kind', devportfolio_collateral_meta_line( $id ) );
	}

	public function test_a_document_category_never_shows(): void {
		$id = $this->make_collateral();
		wp_set_object_terms( $id, array( $this->term( 'Reports', 'document_category' ) ), 'document_category' );

		$this->assertSame( '', devportfolio_collateral_meta_line( $id ) );
	}

	// ---- Preview, pure ----

	private function stored( array $overrides = array() ): array {
		return array_merge(
			array(
				'pages'        => array( 'https://example.test/p-001.webp' ),
				'width'        => 1400,
				'height'       => 1980,
				'thumbs'       => array( 'https://example.test/t-001.webp' ),
				'thumb_width'  => 240,
				'thumb_height' => 339,
			),
			$overrides
		);
	}

	public function test_a_set_with_thumbnails_uses_the_thumbnail_with_page_one_as_the_larger_candidate(): void {
		$preview = devportfolio_collateral_preview_from( $this->stored(), 0, 'A4' );

		$this->assertSame( 'page', $preview['kind'] );
		$this->assertSame( 'https://example.test/t-001.webp', $preview['src'] );
		$this->assertSame( 'https://example.test/t-001.webp 240w, https://example.test/p-001.webp 1400w', $preview['srcset'] );
		$this->assertSame( 1400, $preview['width'] );
		$this->assertSame( 1980, $preview['height'] );
		$this->assertSame( round( 1400 / 1980, 4 ), $preview['ratio'] );
	}

	public function test_a_set_without_thumbnails_uses_page_one(): void {
		$preview = devportfolio_collateral_preview_from( $this->stored( array( 'thumbs' => array() ) ), 0, 'A4' );

		$this->assertSame( 'page', $preview['kind'] );
		$this->assertSame( 'https://example.test/p-001.webp', $preview['src'] );
		$this->assertSame( 'https://example.test/p-001.webp 1400w', $preview['srcset'] );
	}

	public function test_an_unusable_set_falls_through_to_the_blank_page(): void {
		$cases = array(
			null,
			array(),
			$this->stored( array( 'pages' => array() ) ),
			$this->stored( array( 'width' => 0 ) ),
		);
		foreach ( $cases as $stored ) {
			$preview = devportfolio_collateral_preview_from( $stored, 0, 'A4' );
			$this->assertSame( 'blank', $preview['kind'] );
		}
	}

	public function test_blank_ratio_depends_on_landscape_in_the_paper_size(): void {
		$this->assertSame( 0.7071, devportfolio_collateral_preview_from( null, 0, 'A4' )['ratio'] );
		$this->assertSame( 1.4142, devportfolio_collateral_preview_from( null, 0, 'A4 Landscape' )['ratio'] );
		$this->assertSame( 1.4142, devportfolio_collateral_preview_from( null, 0, 'a3 landscape' )['ratio'] );
		$this->assertSame( 0.7071, devportfolio_collateral_preview_from( null, 0, '' )['ratio'] );
	}

	// ---- Preview, rendered ----

	public function test_a_featured_image_is_drawn_at_its_own_ratio(): void {
		$id    = $this->make_collateral( array( 'title' => 'With Image' ) );
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		set_post_thumbnail( $id, $image );

		$src   = wp_get_attachment_image_src( $image, 'full' );
		$ratio = number_format( $src[1] / $src[2], 4, '.', '' );

		$html = $this->render_grid();
		$item = $this->xpath( $html )->query( '//article' )->item( 0 );

		$this->assertSame( $ratio, $item->getAttribute( 'data-ratio' ) );

		$img = $this->xpath( $html )->query( '//img[contains(@class, "collaterals-item__img")]' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $img );
		$this->assertSame( '', $img->getAttribute( 'alt' ) );
		$this->assertSame( 'lazy', $img->getAttribute( 'loading' ) );
	}

	public function test_without_a_file_or_image_the_numbered_blank_shows_at_the_portrait_ratio(): void {
		$this->make_collateral( array( 'title' => 'Empty', 'paper' => 'A4' ) );

		$html = $this->render_grid();
		$xpath = $this->xpath( $html );

		$this->assertSame( 0, $xpath->query( '//img' )->length );
		$blank = $xpath->query( '//span[contains(@class, "collaterals-item__blank")]' )->item( 0 );
		$this->assertSame( '1', $blank->textContent );
		$this->assertSame( 'true', $blank->getAttribute( 'aria-hidden' ) );
		$this->assertSame( '0.7071', $xpath->query( '//article' )->item( 0 )->getAttribute( 'data-ratio' ) );
	}

	public function test_the_blank_is_always_in_the_markup_even_under_an_image(): void {
		$id    = $this->make_collateral( array( 'title' => 'Covered' ) );
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		set_post_thumbnail( $id, $image );

		$xpath = $this->xpath( $this->render_grid() );

		$this->assertSame( 1, $xpath->query( '//span[contains(@class, "collaterals-item__blank") and @aria-hidden="true"]' )->length );
	}

	// ---- Template and registration ----

	public function test_the_grid_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/collateral-grid' ) );
	}

	public function test_template_places_the_grid_after_the_categories_inside_the_content(): void {
		$markup = (string) file_get_contents( $this->theme_dir() . '/templates/archive-collateral.html' );

		$this->assertStringContainsString( 'documents-page collaterals-page', $markup );
		$this->assertStringContainsString( '<!-- wp:devportfolio/collateral-grid /-->', $markup );
		$this->assertLessThan(
			strpos( $markup, '<!-- wp:devportfolio/collateral-grid /-->' ),
			strpos( $markup, 'wp:devportfolio/document-categories' )
		);
		$this->assertLessThan(
			strpos( $markup, '<!-- wp:devportfolio/collateral-grid /-->' ),
			strpos( $markup, 'documents-page__content' ) + 1
		);
		$this->assertStringNotContainsString( 'wp:devportfolio/document-shelf', $markup );
		$this->assertStringNotContainsString( 'wp:devportfolio/document-modal', $markup );
	}

	// ---- CSS structure (parsed from the files) ----

	private function css(): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/collateral-grid/style.css' );
	}

	public function test_the_info_is_hidden_only_on_mouse_devices_and_revealed_by_hover_and_focus(): void {
		$hover = $this->media_block( $this->css(), '(hover: hover)' );

		$this->assertStringContainsString( '.collaterals-item__info', $hover );
		$this->assertStringContainsString( 'opacity: 0', $hover );
		$this->assertStringContainsString( '.collaterals-item:hover .collaterals-item__info', $hover );
		$this->assertStringContainsString( '.collaterals-item:focus-within .collaterals-item__info', $hover );

		// The info is faded out only there: no other rule hides it, so touch screens always show it.
		$this->assertSame( 1, preg_match_all( '/opacity:\s*0[;\s]/', $this->css() ) );
	}

	public function test_the_open_button_covers_the_whole_item(): void {
		$open = $this->rule( '.collaterals-item__open' );

		$this->assertStringContainsString( 'position: absolute', $open );
		$this->assertStringContainsString( 'inset: 0', $open );
		$this->assertStringNotContainsString( '.collaterals-item__open::after', preg_replace( '#/\*.*?\*/#s', '', $this->css() ) );
	}

	public function test_the_info_band_blurs_what_is_behind_it(): void {
		$info = $this->rule( '.collaterals-item__info' );

		$this->assertStringContainsString( 'background: var(--wp--custom--glass--tint)', $info );
		$this->assertStringContainsString( '-webkit-backdrop-filter: var(--wp--custom--glass--filter)', $info );
		$this->assertStringContainsString( 'backdrop-filter: var(--wp--custom--glass--filter)', $info );
	}

	public function test_the_blur_is_dropped_when_the_browser_asks_for_less_transparency_or_more_contrast(): void {
		$css   = $this->css();
		$block = $this->media_block( $css, '(prefers-reduced-transparency: reduce), (prefers-contrast: more)' );

		$this->assertStringContainsString( 'background: var(--wp--preset--color--base)', $block );
		$this->assertStringContainsString( 'backdrop-filter: none', $block );
	}

	public function test_the_blur_is_dropped_in_forced_colors_and_without_backdrop_filter_support(): void {
		$css = $this->css();

		$forced = $this->media_block( $css, '(forced-colors: active)' );
		$this->assertStringContainsString( 'background: Canvas', $forced );
		$this->assertStringContainsString( 'backdrop-filter: none', $forced );

		$this->assertMatchesRegularExpression(
			'/@supports not \(\(backdrop-filter: blur\(1px\)\) or \(-webkit-backdrop-filter: blur\(1px\)\)\)\s*\{\s*\.collaterals-item__info\s*\{[^}]*background: var\(--wp--preset--color--base\)/',
			$css
		);
	}

	public function test_the_masonry_items_are_absolutely_positioned(): void {
		$this->assertMatchesRegularExpression( '/\.collaterals-grid\[data-masonry\] > \.collaterals-item\s*\{[^}]*position:\s*absolute/', $this->css() );
	}

	public function test_the_gap_is_12px_and_8px_on_phones(): void {
		$css = $this->css();
		$this->assertMatchesRegularExpression( '/\.collaterals-grid\s*\{[^}]*--collaterals-gap:\s*12px/', $css );
		$this->assertStringContainsString( '--collaterals-gap: 8px', $this->media_block( $css, '(max-width: 700px)' ) );
	}

	public function test_items_have_a_view_transition_class_and_reduced_motion_turns_the_animation_off(): void {
		$css = $this->css();
		$this->assertStringContainsString( '::view-transition-group(*.collaterals-item)', $css );
		$this->assertStringContainsString( 'animation: none', $this->media_block( $css, '(prefers-reduced-motion: reduce)' ) );
		$this->assertMatchesRegularExpression( '/\.collaterals-item\s*\{[^}]*view-transition-class:\s*collaterals-item/', $css );
	}

	// ---- Scripts (text contracts) ----

	public function test_the_grid_script_imports_the_maths_and_listens_for_the_filter_event(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/collateral-grid/view.js' );

		$this->assertStringContainsString( "from './masonry.js'", $js );
		$this->assertStringContainsString( "'devportfolio:category-change'", $js );
		$this->assertStringContainsString( 'new ResizeObserver', $js );
	}

	public function test_the_category_handler_does_not_wait_for_a_frame(): void {
		$js      = (string) file_get_contents( $this->theme_dir() . '/blocks/collateral-grid/view.js' );
		$handler = substr( $js, (int) strpos( $js, 'document.addEventListener( FILTER_EVENT' ) );

		$this->assertNotSame( '', $handler );
		$this->assertStringNotContainsString( 'requestAnimationFrame', $handler );
	}

	public function test_the_filter_block_dispatches_the_event_inside_apply(): void {
		$js = (string) file_get_contents( $this->theme_dir() . '/blocks/document-categories/view.js' );

		$apply    = strpos( $js, 'function apply( slug )' );
		$dispatch = strpos( $js, "'devportfolio:category-change'" );
		$next     = strpos( $js, 'function syncTrigger' );

		$this->assertNotFalse( $apply );
		$this->assertNotFalse( $dispatch );
		$this->assertGreaterThan( $apply, $dispatch );
		$this->assertLessThan( $next, $dispatch );
	}
}
