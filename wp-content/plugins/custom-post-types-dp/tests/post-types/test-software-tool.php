<?php
/**
 * Software Tool post type tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group software-tool
 */
class CPTDP_Test_Software_Tool extends CPTDP_CPT_Test_Case {

	protected function post_type(): string {
		return 'software_tool';
	}

	protected function expected(): array {
		return array(
			'slug'          => 'software-tools',
			'menu_position' => 24,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'taxonomies'    => array(),
			'columns'       => array( 'thumbnail', 'cptdp_site_url', 'menu_order' ),
			'default_order' => true,
		);
	}

	/**
	 * Save the Site URL meta box for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Fields.
	 * @param bool                 $nonce   Include a valid nonce.
	 */
	private function save( int $post_id, array $fields, bool $nonce = true ): void {
		if ( $nonce ) {
			$fields['cptdp_site_url_nonce'] = wp_create_nonce( 'cptdp_site_url' );
		}

		$this->submit( 'cptdp_software_tool_save_meta', $post_id, $fields );
	}

	private function make_tool(): int {
		return self::factory()->post->create( array( 'post_type' => 'software_tool' ) );
	}

	public function test_site_url_meta_is_registered(): void {
		$registered = get_registered_meta_keys( 'post', 'software_tool' );

		$this->assertArrayHasKey( 'cptdp_site_url', $registered );
		$this->assertSame( 'string', $registered['cptdp_site_url']['type'] );
		$this->assertSame( '', $registered['cptdp_site_url']['default'] );
		$this->assertTrue( $registered['cptdp_site_url']['single'] );
		$this->assertTrue( $registered['cptdp_site_url']['show_in_rest'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function url_cases(): array {
		return CPTDP_Media_CPT_Test_Case::url_cases();
	}

	/**
	 * @dataProvider url_cases
	 *
	 * @param mixed  $input    Raw value.
	 * @param string $expected Expected value.
	 */
	public function test_sanitize_url( $input, string $expected ): void {
		$this->assertSame( $expected, cptdp_software_tool_sanitize_url( $input ) );
	}

	public function test_site_url_is_sanitized_on_update(): void {
		$id = $this->make_tool();

		update_post_meta( $id, 'cptdp_site_url', 'javascript:alert(1)' );
		$this->assertSame( '', get_post_meta( $id, 'cptdp_site_url', true ) );

		update_post_meta( $id, 'cptdp_site_url', 'https://example.com' );
		$this->assertSame( 'https://example.com', get_post_meta( $id, 'cptdp_site_url', true ) );
	}

	public function test_meta_box_is_registered_and_renders_value(): void {
		$id = $this->make_tool();
		update_post_meta( $id, 'cptdp_site_url', 'https://example.com' );

		do_action( 'add_meta_boxes_software_tool', get_post( $id ) );
		$this->assertArrayHasKey( 'cptdp_software_tool_site_url', $GLOBALS['wp_meta_boxes']['software_tool']['normal']['high'] );

		$html = $this->capture( fn() => cptdp_software_tool_render_meta_box( get_post( $id ) ) );
		$this->assertStringContainsString( 'name="cptdp_site_url_nonce"', $html );
		$this->assertStringContainsString( 'value="https://example.com"', $html );
	}

	public function test_save_stores_and_clears_url(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_tool();

		$this->save( $id, array( 'cptdp_site_url' => 'https://example.com' ) );
		$this->assertSame( 'https://example.com', get_post_meta( $id, 'cptdp_site_url', true ) );

		$this->save( $id, array( 'cptdp_site_url' => '' ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_site_url' ) );
	}

	public function test_save_rejects_unsafe_url(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_tool();

		$this->save( $id, array( 'cptdp_site_url' => 'javascript:alert(1)' ) );

		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_site_url' ) );
	}

	public function test_save_ignores_missing_or_bad_nonce(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_tool();

		$this->save( $id, array( 'cptdp_site_url' => 'https://example.com' ), false );
		$this->save( $id, array( 'cptdp_site_url' => 'https://example.com', 'cptdp_site_url_nonce' => 'bogus' ), false );

		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_site_url' ) );
	}

	public function test_save_ignores_user_without_edit_cap(): void {
		$id = $this->make_tool();
		$this->acting_as( 'subscriber' );

		$this->save( $id, array( 'cptdp_site_url' => 'https://example.com' ) );

		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_site_url' ) );
	}

	public function test_site_url_column(): void {
		$id = $this->make_tool();

		$this->assertSame( '&mdash;', $this->render_column( 'cptdp_site_url', $id ) );

		update_post_meta( $id, 'cptdp_site_url', 'https://example.com/?a=1&b=2' );
		$html = $this->render_column( 'cptdp_site_url', $id );

		$this->assertStringContainsString( 'href="https://example.com/?a=1&#038;b=2"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
	}

	public function test_rest_meta_access(): void {
		$GLOBALS['wp_rest_server'] = null;
		$id                        = $this->make_tool();

		$update = function () use ( $id ): WP_REST_Response {
			$request = new WP_REST_Request( 'POST', '/wp/v2/software_tool/' . $id );
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'meta' => array( 'cptdp_site_url' => 'javascript:alert(1)' ) ) ) );

			return rest_do_request( $request );
		};

		$this->acting_as( 'editor' );
		$this->assertSame( 200, $update()->get_status() );
		$this->assertSame( '', get_post_meta( $id, 'cptdp_site_url', true ) );

		$this->acting_as( 'subscriber' );
		$this->assertSame( 403, $update()->get_status() );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $update()->get_status() );
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}
}
