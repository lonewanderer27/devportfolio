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
}
