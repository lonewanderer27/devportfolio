<?php
/**
 * Tests for the reusable software tags block (DPD-016).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Software_Tags extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// The per-request memo store (DPC-024) starts empty in every test.
		if ( function_exists( 'devportfolio_memo_flush' ) ) {
			devportfolio_memo_flush();
		}

		// Meta is unregistered between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/software-tags' ) ) {
			devportfolio_register_blocks();
		}
	}

	/**
	 * Create a post of $type using software tools named $tools.
	 *
	 * @param string   $type  Post type.
	 * @param string[] $tools Tool titles.
	 * @return int Post ID.
	 */
	private function make_post( string $type, array $tools ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_title'  => 'Item',
				'post_status' => 'publish',
			)
		);
		$ids     = array();
		foreach ( $tools as $name ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'software_tool',
					'post_title'  => $name,
					'post_status' => 'publish',
				)
			);
		}
		if ( $ids ) {
			update_post_meta( $post_id, 'cptdp_software_tools', $ids );
		}

		return $post_id;
	}

	private function render( int $post_id, array $attributes = array() ): string {
		return ( new WP_Block(
			array(
				'blockName' => 'devportfolio/software-tags',
				'attrs'     => $attributes,
			),
			array( 'postId' => $post_id )
		) )->render();
	}

	private function xpath( string $html ): DOMXPath {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		return new DOMXPath( $dom );
	}

	private function css(): string {
		$css = file_get_contents( $this->theme_dir() . '/blocks/software-tags/style.css' );
		$this->assertNotFalse( $css );
		return trim( preg_replace( '/\s+/', ' ', preg_replace( '#/\*.*?\*/#s', '', $css ) ) );
	}

	private function rule( string $selector ): string {
		if ( preg_match( '/(?:^|[{}] )' . preg_quote( $selector, '/' ) . ' \{([^{}]*)\}/', $this->css(), $m ) ) {
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

	public function test_block_is_registered_and_uses_the_post_context(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'devportfolio/software-tags' );

		$this->assertNotNull( $type );
		$this->assertContains( 'postId', $type->uses_context );
	}

	public function test_helper_returns_published_tool_names_for_any_post_type(): void {
		foreach ( array( 'document', 'video', 'collateral' ) as $type ) {
			$id = $this->make_post( $type, array( 'Figma', 'Excel' ) );
			$this->assertSame( array( 'Figma', 'Excel' ), devportfolio_software_tool_names( $id ), $type );
		}
	}

	public function test_helper_skips_unpublished_and_non_tool_posts(): void {
		$id    = $this->make_post( 'document', array( 'Figma' ) );
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'software_tool',
				'post_title'  => 'Secret',
				'post_status' => 'draft',
			)
		);
		$page  = self::factory()->post->create();
		update_post_meta( $id, 'cptdp_software_tools', array_merge( get_post_meta( $id, 'cptdp_software_tools', true ), array( $draft, $page ) ) );

		$this->assertSame( array( 'Figma' ), devportfolio_software_tool_names( $id ) );
		$this->assertSame( array(), devportfolio_software_tool_names( 0 ) );
	}

	public function test_nothing_renders_without_tools(): void {
		$this->assertSame( '', trim( $this->render( $this->make_post( 'document', array() ) ) ) );
		$this->assertSame( '', trim( $this->render( 0 ) ) );
	}

	public function test_renders_for_documents_videos_and_collaterals(): void {
		foreach ( array( 'document', 'video', 'collateral' ) as $type ) {
			$xpath = $this->xpath( $this->render( $this->make_post( $type, array( 'Figma', 'Excel' ) ) ) );
			$this->assertSame( 2, $xpath->query( '//ul[contains(@class,"software-tags")]/li' )->length, $type );
		}
	}

	public function test_two_or_fewer_tags_have_no_more_tag_and_no_extra_class(): void {
		$xpath = $this->xpath( $this->render( $this->make_post( 'document', array( 'Figma', 'Excel' ) ) ) );

		$this->assertSame( 0, $xpath->query( '//li[contains(@class,"software-tags__more")]' )->length );
		$this->assertSame( 0, $xpath->query( '//li[contains(@class,"software-tags__item--extra")]' )->length );
	}

	public function test_more_than_two_tags_render_all_flag_the_extras_and_count_the_rest(): void {
		$xpath = $this->xpath( $this->render( $this->make_post( 'document', array( 'Figma', 'Excel', 'Canva', 'Slack' ) ) ) );

		$this->assertSame( 5, $xpath->query( '//ul/li' )->length, 'Four tags and the +N tag.' );
		$this->assertSame( 2, $xpath->query( '//li[contains(@class,"software-tags__item--extra")]' )->length );

		$more = $xpath->query( '//li[contains(@class,"software-tags__more")]' );
		$this->assertSame( 1, $more->length );
		$this->assertSame( '+2', $more->item( 0 )->textContent );
		$this->assertSame( 'Canva, Slack', $more->item( 0 )->getAttribute( 'title' ) );
	}

	public function test_limit_attribute_changes_how_many_tags_stay_on_mobile(): void {
		$xpath = $this->xpath( $this->render( $this->make_post( 'document', array( 'A', 'B', 'C', 'D' ) ), array( 'limit' => 3 ) ) );

		$this->assertSame( 1, $xpath->query( '//li[contains(@class,"software-tags__item--extra")]' )->length );
		$this->assertSame( '+1', $xpath->query( '//li[contains(@class,"software-tags__more")]' )->item( 0 )->textContent );
	}

	public function test_output_is_escaped(): void {
		$html = $this->render( $this->make_post( 'document', array( '<script>alert(1)</script>', 'A & B' ) ) );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'A &amp; B', $html );
	}

	public function test_tags_are_dot_separated_text_not_pills(): void {
		$dot = $this->rule( '.software-tags li + li::before' );

		$this->assertStringContainsString( 'border-radius: 50%', $dot );
		$this->assertStringContainsString( 'var(--st-grad)', $dot );
		$this->assertStringContainsString( 'linear-gradient', $this->rule( '.software-tags' ) );
		$this->assertStringNotContainsString( 'border:', $this->rule( '.software-tags' ) );
	}

	public function test_size_can_be_set_by_the_parent(): void {
		$this->assertStringContainsString( 'font-size: var(--software-tags-size, 13.5px)', $this->rule( '.software-tags' ) );
		$this->assertStringContainsString( 'var(--software-tags-size, 12.5px)', $this->media( '(max-width: 820px)' ) );
	}

	public function test_more_tag_and_extra_hiding_only_apply_at_480_and_below(): void {
		$mobile = $this->media( '(max-width: 480px)' );

		$this->assertStringContainsString( '.software-tags .software-tags__item--extra { display: none; }', $mobile );
		$this->assertStringContainsString( '.software-tags .software-tags__more { display: list-item; }', $mobile );
		$this->assertStringContainsString( 'display: none', $this->rule( '.software-tags .software-tags__more' ) );
	}
}
