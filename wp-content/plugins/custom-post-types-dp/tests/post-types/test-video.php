<?php
/**
 * Video post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group video
 */
class CPTDP_Test_Video extends CPTDP_Media_CPT_Test_Case {

	protected function post_type(): string {
		return 'video';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'videos',
			'menu_position' => 22,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'taxonomies'    => array( 'video_category', 'aspect_ratio' ),
			'columns'       => array( 'thumbnail', 'cptdp_length', 'cptdp_highlight', 'menu_order' ),
			'default_order' => true,
		);
	}

	protected function expected_meta(): array {
		return array(
			'cptdp_video_file'      => array( 'integer', 0 ),
			'cptdp_embed_url'       => array( 'string', '' ),
			'cptdp_post_url'        => array( 'string', '' ),
			'cptdp_length'          => array( 'integer', 0 ),
			'cptdp_highlight'       => array( 'boolean', false ),
			'cptdp_software_tools'  => array( 'array', array() ),
			'cptdp_post_statistics' => array( 'array', array() ),
		);
	}

	protected function url_meta_keys(): array {
		return array( 'cptdp_embed_url', 'cptdp_post_url' );
	}

	protected function valid_fields(): array {
		return array(
			'cptdp_embed_url'       => 'https://example.com/embed',
			'cptdp_post_url'        => 'https://example.com/post',
			'cptdp_length'          => '125',
			'cptdp_highlight'       => '1',
			'cptdp_post_statistics' => array( array( 'label' => 'Views', 'value' => '1K' ) ),
		);
	}

	protected function expected_saved_meta(): array {
		return array(
			'cptdp_embed_url'       => 'https://example.com/embed',
			'cptdp_post_url'        => 'https://example.com/post',
			'cptdp_length'          => 125,
			'cptdp_highlight'       => 1,
			'cptdp_post_statistics' => array( array( 'label' => 'Views', 'value' => '1K' ) ),
		);
	}

	/**
	 * @return array<string, array{0: int, 1: string}>
	 */
	public static function length_cases(): array {
		return array(
			'zero'            => array( 0, '0:00' ),
			'seconds'         => array( 59, '0:59' ),
			'one minute'      => array( 60, '1:00' ),
			'minutes'         => array( 125, '2:05' ),
			'just under hour' => array( 3599, '59:59' ),
			'one hour'        => array( 3600, '1:00:00' ),
			'hours'           => array( 3661, '1:01:01' ),
		);
	}

	/**
	 * @dataProvider length_cases
	 */
	public function test_format_length( int $seconds, string $expected ): void {
		$this->assertSame( $expected, cptdp_video_format_length( $seconds ) );
	}

	public function test_sanitize_file_accepts_only_video_attachments(): void {
		$video = $this->make_attachment( 'video/mp4' );
		$image = $this->make_attachment( 'image/png' );
		$post  = self::factory()->post->create();

		$this->assertSame( $video, cptdp_video_sanitize_file( $video ) );
		$this->assertSame( $video, cptdp_video_sanitize_file( (string) $video ) );
		$this->assertSame( 0, cptdp_video_sanitize_file( $image ) );
		$this->assertSame( 0, cptdp_video_sanitize_file( $post ) );
		$this->assertSame( 0, cptdp_video_sanitize_file( 0 ) );
		$this->assertSame( 0, cptdp_video_sanitize_file( 999999 ) );
		$this->assertSame( 0, cptdp_video_sanitize_file( 'abc' ) );
		$this->assertSame( $video, cptdp_video_sanitize_file( -$video ) );
	}

	public function test_sanitize_software_tools(): void {
		$tool  = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );
		$other = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), cptdp_video_sanitize_software_tools( 'nope' ) );
		$this->assertSame( array(), cptdp_video_sanitize_software_tools( array() ) );
		$this->assertSame(
			array( $tool, $other ),
			cptdp_video_sanitize_software_tools( array( $tool, (string) $tool, $page, 0, 999999, 'abc', $other ) )
		);
	}

	public function test_save_stores_and_clears_file(): void {
		$this->acting_as( 'editor' );
		$id    = $this->make_post();
		$video = $this->make_attachment( 'video/mp4' );

		$this->save( $id, array( 'cptdp_video_file' => (string) $video ) );
		$this->assertEquals( $video, get_post_meta( $id, 'cptdp_video_file', true ) );

		$this->save( $id, array( 'cptdp_video_file' => (string) $this->make_attachment( 'image/png' ) ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_video_file' ) );
	}

	public function test_save_stores_software_tools(): void {
		$this->acting_as( 'editor' );
		$id   = $this->make_post();
		$tool = self::factory()->post->create( array( 'post_type' => 'software_tool' ) );

		$this->save( $id, array( 'cptdp_software_tools' => array( (string) $tool, '0', 'junk' ) ) );
		$this->assertEquals( array( $tool ), get_post_meta( $id, 'cptdp_software_tools', true ) );

		$this->save( $id, array() );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_software_tools' ) );
	}

	public function test_save_clamps_negative_length(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, array( 'cptdp_length' => '-30' ) );

		$this->assertEquals( 30, get_post_meta( $id, 'cptdp_length', true ) );
	}

	public function test_save_is_hooked_to_video_posts_only(): void {
		$this->acting_as( 'editor' );
		$video = $this->make_post();
		$post  = self::factory()->post->create();

		$_POST = array(
			'cptdp_video_nonce' => wp_create_nonce( 'cptdp_video' ),
			'cptdp_highlight'   => '1',
		);

		wp_update_post( array( 'ID' => $video, 'post_title' => 'Updated' ) );
		wp_update_post( array( 'ID' => $post, 'post_title' => 'Updated' ) );

		$this->assertEquals( 1, get_post_meta( $video, 'cptdp_highlight', true ) );
		$this->assertFalse( metadata_exists( 'post', $post, 'cptdp_highlight' ) );
	}

	public function test_length_and_highlight_columns(): void {
		$id = $this->make_post();

		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_length', $id ) );
		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_highlight', $id ) );
		$this->assertSame( '&mdash;', $this->render_column( 'thumbnail', $id ) );

		update_post_meta( $id, 'cptdp_length', 3661 );
		update_post_meta( $id, 'cptdp_highlight', 1 );

		$this->assertSame( '1:01:01', $this->render_column( 'cptdp_length', $id ) );
		$this->assertStringContainsString( 'dashicons-star-filled', $this->render_column( 'cptdp_highlight', $id ) );
	}

	public function test_software_tools_box_lists_published_tools(): void {
		$id = $this->make_post();

		$empty = $this->capture( fn() => cptdp_video_render_software_tools_box( get_post( $id ) ) );
		$this->assertStringContainsString( 'No software tools yet.', $empty );

		$tool = self::factory()->post->create( array( 'post_type' => 'software_tool', 'post_title' => 'Premiere' ) );
		update_post_meta( $id, 'cptdp_software_tools', array( $tool ) );

		$html = $this->capture( fn() => cptdp_video_render_software_tools_box( get_post( $id ) ) );
		$this->assertStringContainsString( 'name="cptdp_software_tools[]"', $html );
		$this->assertStringContainsString( 'Premiere', $html );
		$this->assertMatchesRegularExpression( "/value=\"{$tool}\"\s+checked='checked'/", $html );
	}

	public function test_admin_script_enqueued_only_on_video_screens(): void {
		set_current_screen( 'video' );
		cptdp_video_enqueue_admin( 'post.php' );
		$this->assertTrue( wp_script_is( 'cptdp-admin-video', 'enqueued' ) );

		wp_dequeue_script( 'cptdp-admin-video' );

		cptdp_video_enqueue_admin( 'edit.php' );
		$this->assertFalse( wp_script_is( 'cptdp-admin-video', 'enqueued' ) );

		set_current_screen( 'post' );
		cptdp_video_enqueue_admin( 'post.php' );
		$this->assertFalse( wp_script_is( 'cptdp-admin-video', 'enqueued' ) );
	}
}
