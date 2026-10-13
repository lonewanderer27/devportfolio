<?php
/**
 * Collateral post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group collateral
 */
class CPTDP_Test_Collateral extends CPTDP_Media_CPT_Test_Case {

	protected function post_type(): string {
		return 'collateral';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'collaterals',
			'menu_position' => 27,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'collateral_category' ),
			'columns'       => array( 'thumbnail', 'cptdp_paper_size', 'cptdp_total_pages', 'cptdp_highlight', 'menu_order' ),
			'default_order' => true,
		);
	}

	protected function expected_meta(): array {
		return array(
			'cptdp_collateral_file' => array( 'integer', 0 ),
			'cptdp_embed_url'       => array( 'string', '' ),
			'cptdp_paper_size'      => array( 'string', '' ),
			'cptdp_total_pages'     => array( 'integer', 0 ),
			'cptdp_software_tools'  => array( 'array', array() ),
			'cptdp_highlight'       => array( 'boolean', false ),
			'cptdp_post_statistics' => array( 'array', array() ),
		);
	}

	protected function url_meta_keys(): array {
		return array( 'cptdp_embed_url' );
	}

	protected function valid_fields(): array {
		return array(
			'cptdp_embed_url'       => 'https://example.com/embed',
			'cptdp_paper_size'      => 'A4',
			'cptdp_total_pages'     => '2',
			'cptdp_highlight'       => '1',
			'cptdp_post_statistics' => array( array( 'label' => 'Prints', 'value' => '500' ) ),
		);
	}

	protected function expected_saved_meta(): array {
		return array(
			'cptdp_embed_url'       => 'https://example.com/embed',
			'cptdp_paper_size'      => 'A4',
			'cptdp_total_pages'     => 2,
			'cptdp_highlight'       => 1,
			'cptdp_post_statistics' => array( array( 'label' => 'Prints', 'value' => '500' ) ),
		);
	}

	public function test_sanitize_file_rejects_video_audio_and_non_attachments(): void {
		$pdf   = $this->make_attachment( 'application/pdf' );
		$image = $this->make_attachment( 'image/png' );
		$video = $this->make_attachment( 'video/mp4' );
		$audio = $this->make_attachment( 'audio/mpeg' );
		$post  = self::factory()->post->create();

		$this->assertSame( $pdf, cptdp_collateral_sanitize_file( $pdf ) );
		$this->assertSame( $image, cptdp_collateral_sanitize_file( $image ) );
		$this->assertSame( 0, cptdp_collateral_sanitize_file( $video ) );
		$this->assertSame( 0, cptdp_collateral_sanitize_file( $audio ) );
		$this->assertSame( 0, cptdp_collateral_sanitize_file( $post ) );
		$this->assertSame( 0, cptdp_collateral_sanitize_file( 0 ) );
		$this->assertSame( 0, cptdp_collateral_sanitize_file( 'abc' ) );
	}

	public function test_sanitize_software_tools(): void {
		$tool  = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );
		$other = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), cptdp_collateral_sanitize_software_tools( 'nope' ) );
		$this->assertSame(
			array( $tool, $other ),
			cptdp_collateral_sanitize_software_tools( array( $tool, (string) $tool, $page, 0, 999999, $other ) )
		);
	}

	public function test_save_stores_and_clears_file(): void {
		$this->acting_as( 'editor' );
		$id  = $this->make_post();
		$pdf = $this->make_attachment( 'application/pdf' );

		$this->save( $id, array( 'cptdp_collateral_file' => (string) $pdf ) );
		$this->assertEquals( $pdf, get_post_meta( $id, 'cptdp_collateral_file', true ) );

		$this->save( $id, array( 'cptdp_collateral_file' => (string) $this->make_attachment( 'audio/mpeg' ) ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_collateral_file' ) );
	}

	public function test_save_stores_software_tools(): void {
		$this->acting_as( 'editor' );
		$id   = $this->make_post();
		$tool = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );

		$this->save( $id, array( 'cptdp_software_tools' => array( (string) $tool, 'junk' ) ) );
		$this->assertEquals( array( $tool ), get_post_meta( $id, 'cptdp_software_tools', true ) );

		$this->save( $id, array() );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_software_tools' ) );
	}

	public function test_meta_box_registration_includes_software_tools(): void {
		do_action( 'add_meta_boxes_collateral', get_post( $this->make_post() ) );

		$this->assertArrayHasKey(
			'cptdp_collateral_software_tools',
			$GLOBALS['wp_meta_boxes']['collateral']['side']['default']
		);
	}

	public function test_software_tools_box_lists_published_tools(): void {
		$id = $this->make_post();

		$this->assertStringContainsString(
			'No software tools yet.',
			$this->capture( fn() => cptdp_collateral_render_software_tools_box( get_post( $id ) ) )
		);

		$tool = self::factory()->post->create( array( 'post_type' => 'software_tool', 'post_title' => 'InDesign' ) );

		$html = $this->capture( fn() => cptdp_collateral_render_software_tools_box( get_post( $id ) ) );
		$this->assertStringContainsString( 'InDesign', $html );
		$this->assertStringContainsString( 'value="' . $tool . '"', $html );
	}

	public function test_columns_render_meta(): void {
		$id = $this->make_post();

		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_paper_size', $id ) );
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_total_pages', $id ) );

		update_post_meta( $id, 'cptdp_paper_size', 'A4' );
		update_post_meta( $id, 'cptdp_total_pages', 2 );

		$this->assertSame( 'A4', $this->render_column( 'cptdp_paper_size', $id ) );
		$this->assertSame( '2', $this->render_column( 'cptdp_total_pages', $id ) );
	}

	public function test_admin_script_enqueued_only_on_collateral_screens(): void {
		set_current_screen( 'collateral' );
		cptdp_collateral_enqueue_admin( 'post.php' );
		$this->assertTrue( wp_script_is( 'cptdp-admin-collateral', 'enqueued' ) );

		wp_dequeue_script( 'cptdp-admin-collateral' );

		set_current_screen( 'post' );
		cptdp_collateral_enqueue_admin( 'post.php' );
		$this->assertFalse( wp_script_is( 'cptdp-admin-collateral', 'enqueued' ) );
	}
}
