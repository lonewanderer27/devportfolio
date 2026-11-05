<?php
/**
 * Tests for the document book cover (DPD-005, DPD-006, DPD-008).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Document_Book extends WP_UnitTestCase {

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

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-book' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Create a document with optional meta and category names.
	 *
	 * @param array $args { title, status, meta: array, categories: string[] }.
	 * @return int Post ID.
	 */
	private function make_document( array $args = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'document',
				'post_title'  => $args['title'] ?? 'Quarterly Report',
				'post_status' => $args['status'] ?? 'publish',
			)
		);
		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		if ( ! empty( $args['categories'] ) ) {
			foreach ( $args['categories'] as $name ) {
				if ( ! term_exists( $name, 'document_category' ) ) {
					wp_insert_term( $name, 'document_category' );
				}
			}
			wp_set_object_terms( $post_id, $args['categories'], 'document_category' );
		}
		return $post_id;
	}

	/**
	 * Render the block for a post through block context.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_book( int $post_id ): string {
		$block = new WP_Block(
			array(
				'blockName'    => 'devportfolio/document-book',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'postId' => $post_id )
		);
		return $block->render();
	}

	/**
	 * Read a theme file.
	 *
	 * @param string $relative Path inside the theme.
	 * @return string
	 */
	private function read( string $relative ): string {
		$contents = file_get_contents( $this->theme_dir() . '/' . $relative );
		$this->assertNotFalse( $contents, $relative );
		return $contents;
	}

	/**
	 * Strip comments and collapse whitespace of the block stylesheet.
	 *
	 * @return string
	 */
	private function css(): string {
		$css = preg_replace( '#/\*.*?\*/#s', '', $this->read( 'blocks/document-book/style.css' ) );
		return trim( preg_replace( '/\s+/', ' ', $css ) );
	}

	/**
	 * Body of the first rule whose selector is exactly $selector, at any nesting level.
	 *
	 * @param string $selector Selector text.
	 * @return string Rule body, '' when missing.
	 */
	private function rule( string $selector ): string {
		if ( preg_match( '/(?:^|[{}] )' . preg_quote( $selector, '/' ) . ' \{([^{}]*)\}/', $this->css(), $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Inner CSS of the first @media block whose query is exactly $query.
	 *
	 * @param string $query Media query text.
	 * @return string
	 */
	private function media( string $query ): string {
		if ( preg_match( '/@media ' . preg_quote( $query, '/' ) . ' \{((?:[^{}]|\{[^{}]*\})*)\}/', $this->css(), $m ) ) {
			return $m[1];
		}
		return '';
	}

	public function test_format_label_matrix(): void {
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'PDF', 24 ) );
		$this->assertSame( 'PDF · 1 p', devportfolio_document_format_label( 'PDF', 1 ) );
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'pdf', 24 ) );
		$this->assertSame( 'XLSX · 24 pp', devportfolio_document_format_label( ' xlsx ', '24' ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', 0 ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', '' ) );
		$this->assertSame( '24 pp', devportfolio_document_format_label( '', 24 ) );
		$this->assertSame( '1 p', devportfolio_document_format_label( '', '1' ) );
		$this->assertSame( '', devportfolio_document_format_label( '', 0 ) );
		$this->assertSame( '', devportfolio_document_format_label( null, null ) );
	}

	public function test_format_label_ignores_invalid_page_counts(): void {
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', -3 ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', 'abc' ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', '2.5' ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'PDF', array( 4 ) ) );
		$this->assertSame( '', devportfolio_document_format_label( array( 'PDF' ), 0 ) );
	}

	public function test_cover_styles_are_the_six_in_order(): void {
		$this->assertSame(
			array( 'midnight', 'pinstripe', 'horizon', 'split-sky', 'dot-grid', 'two-tone' ),
			array_keys( devportfolio_cover_styles() )
		);
	}

	public function test_sanitize_cover_style_whitelist(): void {
		foreach ( array_keys( devportfolio_cover_styles() ) as $slug ) {
			$this->assertSame( $slug, devportfolio_sanitize_cover_style( $slug ) );
		}
		$this->assertSame( 'dot-grid', devportfolio_sanitize_cover_style( ' Dot-Grid ' ) );
		foreach ( array( 'unknown', '', '<script>', array( 'horizon' ), null, 5 ) as $bad ) {
			$this->assertSame( 'midnight', devportfolio_sanitize_cover_style( $bad ) );
		}
	}

	public function test_block_is_registered(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/document-book' ) );
	}

	public function test_cover_elements_are_in_order(): void {
		$id   = $this->make_document(
			array(
				'meta'       => array(
					'cptdp_filetype'    => 'pdf',
					'cptdp_total_pages' => 24,
				),
				'categories' => array( 'Reports' ),
			)
		);
		$html = $this->render_book( $id );

		$positions = array();
		foreach ( array( 'documents-book__cat', 'documents-book__ttl', 'documents-book__format', 'documents-book__owner' ) as $class ) {
			$pos = strpos( $html, $class );
			$this->assertNotFalse( $pos, $class );
			$positions[] = $pos;
		}
		$sorted = $positions;
		sort( $sorted );
		$this->assertSame( $sorted, $positions );
		$this->assertStringContainsString( '>Reports<', $html );
		$this->assertStringContainsString( '>Quarterly Report<', $html );
	}

	public function test_format_and_owner_share_the_footer_line(): void {
		$id   = $this->make_document(
			array(
				'meta' => array(
					'cptdp_filetype'    => 'pdf',
					'cptdp_total_pages' => 24,
				),
			)
		);
		$html = $this->render_book( $id );

		$this->assertMatchesRegularExpression(
			'#<span class="documents-book__ft">\s*<span class="documents-book__format">PDF · 24 pp</span>\s*<span class="documents-book__owner">DAVE</span>\s*</span>#',
			$html
		);

		$one = $this->make_document(
			array(
				'meta' => array(
					'cptdp_filetype'    => 'docx',
					'cptdp_total_pages' => 1,
				),
			)
		);
		$this->assertStringContainsString( '>DOCX · 1 p<', $this->render_book( $one ) );
	}

	public function test_cover_has_no_status_tag_description_or_tags(): void {
		$id   = $this->make_document(
			array(
				'meta' => array(
					'cptdp_filetype'  => 'pdf',
					'cptdp_highlight' => 1,
				),
			)
		);
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'A long description that belongs under the cover.',
				'post_excerpt' => 'Excerpt',
			)
		);
		$html = $this->render_book( $id );

		$this->assertStringNotContainsStringIgnoringCase( 'open', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'request', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'locked', $html );
		$this->assertStringNotContainsString( 'tag', $html );
		$this->assertStringNotContainsString( 'description', $html );
		$this->assertStringNotContainsString( 'long description', $html );
		$this->assertStringNotContainsString( 'Excerpt', $html );
	}

	public function test_output_is_escaped(): void {
		$id   = $this->make_document(
			array(
				'title'      => 'R&D <b>x</b>',
				'categories' => array( 'Tom <i>& Jerry</i>' ),
			)
		);
		$html = $this->render_book( $id );

		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringNotContainsString( '<i>', $html );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
	}

	public function test_missing_category_and_page_data(): void {
		$id   = $this->make_document();
		$html = $this->render_book( $id );

		$this->assertMatchesRegularExpression( '#<span class="documents-book__cat"></span>#', $html );
		$this->assertMatchesRegularExpression( '#<span class="documents-book__format"></span>#', $html );
		$this->assertStringNotContainsString( '·', $html );
	}

	public function test_first_category_in_name_order_is_shown(): void {
		$id   = $this->make_document( array( 'categories' => array( 'Reports', 'Executive Docs' ) ) );
		$html = $this->render_book( $id );
		$this->assertStringContainsString( '>Executive Docs<', $html );
		$this->assertStringNotContainsString( '>Reports<', $html );
	}

	public function test_non_document_and_missing_posts_render_nothing(): void {
		$post = self::factory()->post->create( array( 'post_title' => 'Plain post' ) );
		$this->assertSame( '', trim( $this->render_book( $post ) ) );
		$this->assertSame( '', trim( $this->render_book( 0 ) ) );
		$this->assertSame( '', trim( $this->render_book( 999999 ) ) );
	}

	public function test_draft_document_still_renders(): void {
		$id = $this->make_document( array( 'status' => 'draft' ) );
		$this->assertStringContainsString( 'documents-book__ttl', $this->render_book( $id ) );
	}

	public function test_token_block_matches_the_design_system(): void {
		$base = $this->rule( '.documents-book' );
		$this->assertNotSame( '', $base );
		$expected = array(
			'--book-ink'         => '#000000',
			'--book-ink-2'       => '#2c2c2e',
			'--book-surface'     => '#ffffff',
			'--book-surface-alt' => '#f2f2f7',
			'--book-line'        => '#d1d1d6',
			'--book-subtle'      => '#6a6a6f',
			'--book-g1'          => '26 214 253',
			'--book-g2'          => '29 98 240',
			'--book-shadow'      => 'rgb(12 27 30 / 0.22)',
			'--book-edge'        => 'rgb(0 0 0 / 0.1)',
			'--book-ease'        => 'cubic-bezier(0.2, 0.8, 0.2, 1)',
			'--book-dur'         => '260ms',
		);
		foreach ( $expected as $token => $value ) {
			$this->assertStringContainsString( $token . ': ' . $value . ';', $base, $token );
		}
	}

	public function test_pattern_layer_is_rendered_and_hidden_from_assistive_tech(): void {
		$html = $this->render_book( $this->make_document() );
		$this->assertStringContainsString( '<i class="documents-book__pat" aria-hidden="true"></i>', $html );
		$this->assertLessThan( strpos( $html, 'documents-book__cat' ), strpos( $html, 'documents-book__pat' ), 'pattern layer comes first' );
	}

	public function test_title_ornament_and_no_serif_font(): void {
		$ornament = $this->rule( '.documents-book__ttl::after' );
		$this->assertStringContainsString( 'height: 2px', $ornament );
		$this->assertStringContainsString( 'width: 34px', $ornament );
		$this->assertStringContainsString( 'background: currentColor', $ornament );
		$this->assertStringNotContainsStringIgnoringCase( 'georgia', $this->css() );
		$this->assertStringNotContainsStringIgnoringCase( 'serif', $this->css() );
	}
}
