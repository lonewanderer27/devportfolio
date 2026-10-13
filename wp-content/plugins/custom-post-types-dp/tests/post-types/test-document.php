<?php
/**
 * Document post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document
 */
class CPTDP_Test_Document extends CPTDP_Media_CPT_Test_Case {

	protected function post_type(): string {
		return 'document';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'documents',
			'menu_position' => 25,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'document_category' ),
			'columns'       => array( 'thumbnail', 'cptdp_filetype', 'cptdp_total_pages', 'cptdp_highlight', 'menu_order' ),
			'default_order' => true,
		);
	}

	protected function expected_meta(): array {
		return array(
			'cptdp_document_file'   => array( 'integer', 0 ),
			'cptdp_embed_url'       => array( 'string', '' ),
			'cptdp_filetype'        => array( 'string', '' ),
			'cptdp_author'          => array( 'string', '' ),
			'cptdp_total_pages'     => array( 'integer', 0 ),
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
			'cptdp_filetype'        => 'PDF',
			'cptdp_author'          => 'Jane Doe',
			'cptdp_total_pages'     => '12',
			'cptdp_highlight'       => '1',
			'cptdp_post_statistics' => array( array( 'label' => 'Downloads', 'value' => '1.2K' ) ),
		);
	}

	protected function expected_saved_meta(): array {
		return array(
			'cptdp_embed_url'       => 'https://example.com/embed',
			'cptdp_filetype'        => 'PDF',
			'cptdp_author'          => 'Jane Doe',
			'cptdp_total_pages'     => 12,
			'cptdp_highlight'       => 1,
			'cptdp_post_statistics' => array( array( 'label' => 'Downloads', 'value' => '1.2K' ) ),
		);
	}

	public function test_sanitize_file_rejects_video_audio_and_non_attachments(): void {
		$pdf   = $this->make_attachment( 'application/pdf' );
		$image = $this->make_attachment( 'image/png' );
		$video = $this->make_attachment( 'video/mp4' );
		$audio = $this->make_attachment( 'audio/mpeg' );
		$post  = self::factory()->post->create();

		$this->assertSame( $pdf, cptdp_document_sanitize_file( $pdf ) );
		$this->assertSame( $pdf, cptdp_document_sanitize_file( (string) $pdf ) );
		$this->assertSame( $image, cptdp_document_sanitize_file( $image ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( $video ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( $audio ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( $post ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( 0 ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( 999999 ) );
		$this->assertSame( 0, cptdp_document_sanitize_file( 'abc' ) );
	}

	public function test_save_stores_and_clears_file(): void {
		$this->acting_as( 'editor' );
		$id  = $this->make_post();
		$pdf = $this->make_attachment( 'application/pdf' );

		$this->save( $id, array( 'cptdp_document_file' => (string) $pdf ) );
		$this->assertEquals( $pdf, get_post_meta( $id, 'cptdp_document_file', true ) );

		$this->save( $id, array( 'cptdp_document_file' => (string) $this->make_attachment( 'video/mp4' ) ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_document_file' ) );
	}

	public function test_save_strips_markup_from_text_fields(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save(
			$id,
			array(
				'cptdp_filetype' => '<b>PDF</b>',
				'cptdp_author'   => '  <script>x</script>Jane  ',
			)
		);

		$this->assertSame( 'PDF', get_post_meta( $id, 'cptdp_filetype', true ) );
		$this->assertSame( 'Jane', get_post_meta( $id, 'cptdp_author', true ) );
	}

	public function test_text_meta_is_sanitized_on_update(): void {
		$id = $this->make_post();

		update_post_meta( $id, 'cptdp_filetype', '<i>DOCX</i>' );

		$this->assertSame( 'DOCX', get_post_meta( $id, 'cptdp_filetype', true ) );
	}

	public function test_columns_render_meta(): void {
		$id = $this->make_post();

		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_filetype', $id ) );
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_total_pages', $id ) );
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_highlight', $id ) );

		update_post_meta( $id, 'cptdp_filetype', 'PDF' );
		update_post_meta( $id, 'cptdp_total_pages', 12 );
		update_post_meta( $id, 'cptdp_highlight', 1 );

		$this->assertSame( 'PDF', $this->render_column( 'cptdp_filetype', $id ) );
		$this->assertSame( '12', $this->render_column( 'cptdp_total_pages', $id ) );
		$this->assertStringContainsString( 'dashicons-star-filled', $this->render_column( 'cptdp_highlight', $id ) );
	}

	public function test_admin_script_enqueued_only_on_document_screens(): void {
		set_current_screen( 'document' );
		cptdp_document_enqueue_admin( 'post.php' );
		$this->assertTrue( wp_script_is( 'cptdp-admin-document', 'enqueued' ) );

		wp_dequeue_script( 'cptdp-admin-document' );

		set_current_screen( 'post' );
		cptdp_document_enqueue_admin( 'post.php' );
		$this->assertFalse( wp_script_is( 'cptdp-admin-document', 'enqueued' ) );
	}
}
