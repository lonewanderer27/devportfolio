<?php
/**
 * Tests for the helpers shared between Documents and Brand Collaterals (DPC-024).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Shared_Extraction extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	public function set_up(): void {
		parent::set_up();

		// Meta is unregistered and terms wiped between tests; make sure the plugin's types exist.
		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		// WordPress restores the hooks after every test, so the memo's invalidation hooks are added again here.
		devportfolio_memo_add_hooks();
		devportfolio_memo_flush();
	}

	private function make_post( string $post_type, array $args = array(), array $meta = array() ): int {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => 'Item',
				),
				$args
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	// --- Page count label ---------------------------------------------------

	public function test_page_count_label_pages(): void {
		$this->assertSame( '1 p', devportfolio_page_count_label( 1 ) );
		$this->assertSame( '24 pp', devportfolio_page_count_label( 24 ) );
		$this->assertSame( '12 pp', devportfolio_page_count_label( '12' ) );
		$this->assertSame( '', devportfolio_page_count_label( 0 ) );
		$this->assertSame( '', devportfolio_page_count_label( -3 ) );
		$this->assertSame( '', devportfolio_page_count_label( '' ) );
		$this->assertSame( '', devportfolio_page_count_label( 'many' ) );
		$this->assertSame( '', devportfolio_page_count_label( null ) );
	}

	public function test_page_count_label_slides(): void {
		$this->assertSame( '1 slide', devportfolio_page_count_label( 1, 'pptx' ) );
		$this->assertSame( '12 slides', devportfolio_page_count_label( 12, 'pptx' ) );
		$this->assertSame( '', devportfolio_page_count_label( 0, 'odp' ) );
		$this->assertSame( '3 pp', devportfolio_page_count_label( 3, 'pdf' ) );
	}

	public function test_format_label_still_joins_type_and_count(): void {
		$this->assertSame( 'PDF · 24 pp', devportfolio_document_format_label( 'pdf', 24 ) );
		$this->assertSame( 'PDF · 1 p', devportfolio_document_format_label( 'pdf', 1 ) );
		$this->assertSame( 'PPTX · 12 slides', devportfolio_document_format_label( 'pptx', 12, 'pptx' ) );
		$this->assertSame( 'PDF', devportfolio_document_format_label( 'pdf', 0 ) );
		$this->assertSame( '24 pp', devportfolio_document_format_label( '', 24 ) );
	}

	public function test_collateral_pill_and_meta_use_the_same_count(): void {
		$this->assertSame( 'A4 · 2 pp', devportfolio_collateral_pill( 'A4', 2 ) );
		$this->assertSame( 'A4 · 1 p', devportfolio_collateral_pill( 'A4', 1 ) );

		$post_id = $this->make_post( 'collateral', array(), array( 'cptdp_total_pages' => 4, 'cptdp_paper_size' => 'A5' ) );
		$this->assertSame( 'A5 · 4 pp', devportfolio_collateral_meta_line( $post_id ) );
	}

	// --- Payload base ------------------------------------------------------

	public function test_document_payload_keeps_its_key_order(): void {
		$tool = $this->make_post( 'software_tool', array( 'post_title' => 'Figma' ) );
		$doc  = $this->make_post(
			'document',
			array( 'post_title' => 'Plan & Budget' ),
			array( 'cptdp_software_tools' => array( $tool ), 'cptdp_total_pages' => 3, 'cptdp_filetype' => 'pdf' )
		);

		$this->assertSame(
			array(
				'id',
				'slug',
				'title',
				'description',
				'tools',
				'category',
				'fileType',
				'format',
				'kind',
				'pages',
				'downloadMode',
				'downloadUrl',
				'images',
				'imageWidth',
				'cropBottom',
				'ratio',
				'viewerUrl',
				'nativeUrl',
				'pdfBytes',
			),
			array_slice( array_keys( devportfolio_document_modal_payload( $doc ) ), 0, 19 )
		);

		$payload = devportfolio_document_modal_payload( $doc );
		$this->assertSame( 'Plan & Budget', $payload['title'] );
		$this->assertSame( array( 'Figma' ), $payload['tools'] );
	}

	public function test_media_payload_base_has_the_shared_keys_in_order(): void {
		$doc  = $this->make_post( 'document', array( 'post_title' => 'Deck' ) );
		$base = devportfolio_media_payload_base( $doc, 'document_category', 'Deck' );

		$this->assertSame( array( 'id', 'slug', 'title', 'description', 'tools', 'category' ), array_keys( $base ) );
		$this->assertSame( $doc, $base['id'] );
		$this->assertSame( 'Deck', $base['title'] );
	}

	public function test_collateral_payload_carries_the_shared_keys(): void {
		$item = $this->make_post( 'collateral', array( 'post_title' => 'Flyer' ), array( 'cptdp_paper_size' => 'A4' ) );
		$data = devportfolio_collateral_modal_payload( $item );

		foreach ( array( 'id', 'slug', 'title', 'description', 'tools', 'category', 'categories', 'pill', 'stats', 'downloadMode', 'downloadUrl', 'thumb' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, $key );
		}
		$this->assertSame( 'Flyer', $data['title'] );
	}

	// --- Per-request memo --------------------------------------------------

	public function test_memo_runs_the_builder_once_per_request(): void {
		$calls = 0;
		$build = static function () use ( &$calls ) {
			++$calls;
			return 'value';
		};

		$this->assertSame( 'value', devportfolio_memo( 'test-group', 7, $build ) );
		$this->assertSame( 'value', devportfolio_memo( 'test-group', 7, $build ) );
		$this->assertSame( 1, $calls );

		// A different post ID is a different value.
		devportfolio_memo( 'test-group', 8, $build );
		$this->assertSame( 2, $calls );
	}

	public function test_tool_names_follow_a_tool_title_change(): void {
		$tool = $this->make_post( 'software_tool', array( 'post_title' => 'Sketch' ) );
		$doc  = $this->make_post( 'document', array(), array( 'cptdp_software_tools' => array( $tool ) ) );

		$this->assertSame( array( 'Sketch' ), devportfolio_software_tool_names( $doc ) );

		wp_update_post(
			array(
				'ID'         => $tool,
				'post_title' => 'Sketch Pro',
			)
		);

		$this->assertSame( array( 'Sketch Pro' ), devportfolio_software_tool_names( $doc ) );
	}

	public function test_published_ids_lists_published_posts_and_follows_a_new_publish(): void {
		$first = $this->make_post( 'collateral', array( 'post_title' => 'A' ) );
		$this->assertSame( array( $first ), devportfolio_published_ids( 'collateral' ) );

		$draft = self::factory()->post->create( array( 'post_type' => 'collateral', 'post_status' => 'draft' ) );
		$this->assertSame( array( $first ), devportfolio_published_ids( 'collateral' ) );

		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'publish',
			)
		);
		$this->assertEqualsCanonicalizing( array( $first, $draft ), devportfolio_published_ids( 'collateral' ) );
	}

	public function test_published_ids_keeps_the_order_asked_for(): void {
		$b = $this->make_post( 'collateral', array( 'post_title' => 'Bravo', 'menu_order' => 1 ) );
		$a = $this->make_post( 'collateral', array( 'post_title' => 'Alpha', 'menu_order' => 2 ) );

		$ordered = devportfolio_published_ids(
			'collateral',
			array(
				'orderby' => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		$this->assertSame( array( $b, $a ), $ordered );
	}
}
