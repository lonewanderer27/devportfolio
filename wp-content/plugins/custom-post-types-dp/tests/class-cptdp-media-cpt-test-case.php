<?php
/**
 * Shared tests for the Video, Document and Collateral modules.
 *
 * @package CustomPostTypesDP
 */

/**
 * Meta registration, sanitizers, REST access and meta box saving for the
 * post types that share the file / URL / statistics / highlight pattern.
 */
abstract class CPTDP_Media_CPT_Test_Case extends CPTDP_CPT_Test_Case {

	/**
	 * Expected registered meta: key => array( type, default ).
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	abstract protected function expected_meta(): array;

	/**
	 * Meta keys holding http(s) URLs.
	 *
	 * @return string[]
	 */
	abstract protected function url_meta_keys(): array;

	/**
	 * Valid meta box fields (without the nonce).
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function valid_fields(): array;

	/**
	 * Meta expected to be stored after saving `valid_fields()`.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function expected_saved_meta(): array;

	/**
	 * Name of the nonce field.
	 */
	protected function nonce_field(): string {
		return 'cptdp_' . $this->post_type() . '_nonce';
	}

	/**
	 * Name of the nonce action.
	 */
	protected function nonce_action(): string {
		return 'cptdp_' . $this->post_type();
	}

	/**
	 * Save handler under test.
	 */
	protected function save_handler(): string {
		return 'cptdp_' . $this->post_type() . '_save_meta';
	}

	/**
	 * Call a module function by suffix, e.g. `sanitize_url`.
	 *
	 * @param string $suffix Function name after `cptdp_{type}_`.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	protected function call( string $suffix, ...$args ) {
		return call_user_func( 'cptdp_' . $this->post_type() . '_' . $suffix, ...$args );
	}

	/**
	 * Create a post of this type.
	 *
	 * @param array<string, mixed> $args Extra post args.
	 */
	protected function make_post( array $args = array() ): int {
		return self::factory()->post->create( array_merge( array( 'post_type' => $this->post_type() ), $args ) );
	}

	/**
	 * Submit the meta box form for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Fields.
	 * @param bool                 $nonce   Whether to include a valid nonce.
	 */
	protected function save( int $post_id, array $fields, bool $nonce = true ): void {
		if ( $nonce ) {
			$fields[ $this->nonce_field() ] = wp_create_nonce( $this->nonce_action() );
		}

		$this->submit( $this->save_handler(), $post_id, $fields );
	}

	/**
	 * URL sanitizer cases: input, expected.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function url_cases(): array {
		return array(
			'https'          => array( 'https://example.com/a?b=1', 'https://example.com/a?b=1' ),
			'http'           => array( 'http://example.com', 'http://example.com' ),
			'trimmed'        => array( "  https://example.com/x \n", 'https://example.com/x' ),
			'javascript'     => array( 'javascript:alert(1)', '' ),
			'ftp'            => array( 'ftp://example.com/file', '' ),
			'data'           => array( 'data:text/html,hi', '' ),
			'empty'          => array( '', '' ),
			'null'           => array( null, '' ),
		);
	}

	/**
	 * Statistics sanitizer cases: input, expected.
	 *
	 * @return array<string, array{0: mixed, 1: array}>
	 */
	public static function statistics_cases(): array {
		return array(
			'not an array'      => array( 'Views', array() ),
			'empty array'       => array( array(), array() ),
			'valid row'         => array(
				array( array( 'label' => 'Views', 'value' => '1K' ) ),
				array( array( 'label' => 'Views', 'value' => '1K' ) ),
			),
			'strips tags'       => array(
				array( array( 'label' => '<b>Views</b>', 'value' => '<script>x</script>1K' ) ),
				array( array( 'label' => 'Views', 'value' => '1K' ) ),
			),
			'trims'             => array(
				array( array( 'label' => '  Views ', 'value' => ' 1K ' ) ),
				array( array( 'label' => 'Views', 'value' => '1K' ) ),
			),
			'drops empty rows'  => array(
				array(
					array( 'label' => '', 'value' => '' ),
					array( 'label' => 'Views', 'value' => '1K' ),
				),
				array( array( 'label' => 'Views', 'value' => '1K' ) ),
			),
			'keeps half rows'   => array(
				array( array( 'label' => 'Views' ) ),
				array( array( 'label' => 'Views', 'value' => '' ) ),
			),
			'drops non arrays'  => array(
				array( 'junk', 5, array( 'label' => 'A', 'value' => 'B' ) ),
				array( array( 'label' => 'A', 'value' => 'B' ) ),
			),
			'reindexes'         => array(
				array( 5 => array( 'label' => 'A', 'value' => 'B' ) ),
				array( array( 'label' => 'A', 'value' => 'B' ) ),
			),
			'drops extra keys'  => array(
				array( array( 'label' => 'A', 'value' => 'B', 'extra' => 'C' ) ),
				array( array( 'label' => 'A', 'value' => 'B' ) ),
			),
		);
	}

	/**
	 * @dataProvider url_cases
	 *
	 * @param mixed  $input    Raw value.
	 * @param string $expected Expected sanitized value.
	 */
	public function test_sanitize_url( $input, string $expected ): void {
		$this->assertSame( $expected, $this->call( 'sanitize_url', $input ) );
	}

	/**
	 * @dataProvider statistics_cases
	 *
	 * @param mixed $input    Raw value.
	 * @param array $expected Expected sanitized rows.
	 */
	public function test_sanitize_statistics( $input, array $expected ): void {
		$this->assertSame( $expected, $this->call( 'sanitize_statistics', $input ) );
	}

	public function test_meta_is_registered(): void {
		$registered = get_registered_meta_keys( 'post', $this->post_type() );

		foreach ( $this->expected_meta() as $key => $expected ) {
			$this->assertArrayHasKey( $key, $registered, "Meta not registered: {$key}" );
			$this->assertSame( $expected[0], $registered[ $key ]['type'], "Type of {$key}" );
			$this->assertSame( $expected[1], $registered[ $key ]['default'], "Default of {$key}" );
			$this->assertTrue( $registered[ $key ]['single'], "Single {$key}" );
			$this->assertNotEmpty( $registered[ $key ]['show_in_rest'], "REST {$key}" );
		}
	}

	public function test_url_meta_is_sanitized_on_update(): void {
		$id = $this->make_post();

		foreach ( $this->url_meta_keys() as $key ) {
			update_post_meta( $id, $key, 'javascript:alert(1)' );
			$this->assertSame( '', get_post_meta( $id, $key, true ), $key );

			update_post_meta( $id, $key, ' https://example.com/ok ' );
			$this->assertSame( 'https://example.com/ok', get_post_meta( $id, $key, true ), $key );
		}
	}

	public function test_statistics_meta_is_sanitized_on_update(): void {
		$id = $this->make_post();

		update_post_meta( $id, 'cptdp_post_statistics', array( array( 'label' => '<i>A</i>', 'value' => 'B' ), 'junk' ) );

		$this->assertSame( array( array( 'label' => 'A', 'value' => 'B' ) ), get_post_meta( $id, 'cptdp_post_statistics', true ) );
	}

	public function test_meta_auth_requires_edit_posts(): void {
		$id = $this->make_post();

		foreach ( array_keys( $this->expected_meta() ) as $key ) {
			$this->acting_as( 'subscriber' );
			$this->assertFalse( current_user_can( 'edit_post_meta', $id, $key ), "Subscriber {$key}" );

			$this->acting_as( 'editor' );
			$this->assertTrue( current_user_can( 'edit_post_meta', $id, $key ), "Editor {$key}" );
		}
	}

	public function test_meta_box_registration(): void {
		$post = get_post( $this->make_post() );

		add_meta_box( 'postcustom', 'Custom Fields', '__return_null', $this->post_type(), 'normal' );
		do_action( 'add_meta_boxes_' . $this->post_type(), $post );

		$boxes = $GLOBALS['wp_meta_boxes'][ $this->post_type() ];

		$this->assertFalse( $boxes['normal']['default']['postcustom'] );
		$this->assertArrayHasKey( 'cptdp_' . $this->post_type() . '_details', $boxes['normal']['high'] );
		$this->assertArrayHasKey( 'cptdp_' . $this->post_type() . '_post_statistics', $boxes['normal']['default'] );
		$this->assertArrayHasKey( 'cptdp_' . $this->post_type() . '_highlight', $boxes['side']['default'] );
	}

	public function test_meta_box_details_renders_nonce_and_saved_values(): void {
		$id = $this->make_post();
		$this->acting_as( 'editor' );
		$this->save( $id, $this->valid_fields() );

		$html = $this->capture(
			function () use ( $id ): void {
				$this->call( 'render_details_box', get_post( $id ) );
			}
		);

		$this->assertStringContainsString( 'name="' . $this->nonce_field() . '"', $html );
		$this->assertStringContainsString( 'https://example.com/embed', $html );
	}

	public function test_statistics_box_renders_rows_and_template(): void {
		$id = $this->make_post();
		update_post_meta( $id, 'cptdp_post_statistics', array( array( 'label' => 'Views', 'value' => '1K' ) ) );

		$html = $this->capture(
			function () use ( $id ): void {
				$this->call( 'render_statistics_box', get_post( $id ) );
			}
		);

		$this->assertStringContainsString( 'name="cptdp_post_statistics[0][label]"', $html );
		$this->assertStringContainsString( 'value="Views"', $html );
		$this->assertStringContainsString( 'cptdp_post_statistics[__INDEX__][label]', $html );
	}

	public function test_highlight_box_reflects_meta(): void {
		$id = $this->make_post();

		$render = function () use ( $id ): string {
			return $this->capture(
				function () use ( $id ): void {
					$this->call( 'render_highlight_box', get_post( $id ) );
				}
			);
		};

		$this->assertDoesNotMatchRegularExpression( '/checked/', $render() );

		update_post_meta( $id, 'cptdp_highlight', 1 );
		$this->assertMatchesRegularExpression( "/checked='checked'/", $render() );
	}

	public function test_save_persists_valid_fields(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, $this->valid_fields() );

		foreach ( $this->expected_saved_meta() as $key => $expected ) {
			$this->assertEquals( $expected, get_post_meta( $id, $key, true ), $key );
		}
	}

	public function test_save_removes_meta_when_fields_are_empty(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, $this->valid_fields() );
		$this->save( $id, array() );

		foreach ( array_keys( $this->expected_saved_meta() ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), "{$key} should be deleted" );
		}
	}

	public function test_save_ignores_missing_nonce(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, $this->valid_fields(), false );

		foreach ( array_keys( $this->expected_saved_meta() ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), $key );
		}
	}

	public function test_save_ignores_bad_nonce(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, array_merge( $this->valid_fields(), array( $this->nonce_field() => 'bogus' ) ), false );

		foreach ( array_keys( $this->expected_saved_meta() ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), $key );
		}
	}

	public function test_save_ignores_user_without_edit_cap(): void {
		$id = $this->make_post();
		$this->acting_as( 'subscriber' );

		$this->save( $id, $this->valid_fields() );

		foreach ( array_keys( $this->expected_saved_meta() ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), $key );
		}
	}

	public function test_save_sanitizes_urls(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$fields = $this->valid_fields();
		foreach ( $this->url_meta_keys() as $key ) {
			$fields[ $key ] = 'javascript:alert(1)';
		}

		$this->save( $id, $fields );

		foreach ( $this->url_meta_keys() as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), $key );
		}
	}

	public function test_save_highlight_flag_toggles(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, array( 'cptdp_highlight' => '1' ) );
		$this->assertEquals( 1, get_post_meta( $id, 'cptdp_highlight', true ) );

		$this->save( $id, array() );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_highlight' ) );
	}

	public function test_save_drops_empty_statistics_rows(): void {
		$this->acting_as( 'editor' );
		$id = $this->make_post();

		$this->save( $id, array( 'cptdp_post_statistics' => array( array( 'label' => '', 'value' => '' ) ) ) );

		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_post_statistics' ) );
	}

	public function test_rest_updates_meta_as_editor(): void {
		$id = $this->make_post();
		$this->acting_as( 'editor' );

		$response = $this->rest_update(
			$id,
			array(
				'cptdp_highlight'       => true,
				'cptdp_post_statistics' => array( array( 'label' => 'Views', 'value' => '1K' ) ),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals( 1, get_post_meta( $id, 'cptdp_highlight', true ) );
		$this->assertSame( array( array( 'label' => 'Views', 'value' => '1K' ) ), get_post_meta( $id, 'cptdp_post_statistics', true ) );
	}

	public function test_rest_sanitizes_urls(): void {
		$id = $this->make_post();
		$this->acting_as( 'editor' );

		foreach ( $this->url_meta_keys() as $key ) {
			$this->rest_update( $id, array( $key => 'javascript:alert(1)' ) );
			$this->assertSame( '', get_post_meta( $id, $key, true ), $key );

			$this->rest_update( $id, array( $key => 'https://example.com/ok' ) );
			$this->assertSame( 'https://example.com/ok', get_post_meta( $id, $key, true ), $key );
		}
	}

	public function test_rest_rejects_extra_statistics_properties(): void {
		$id = $this->make_post();
		$this->acting_as( 'editor' );

		$response = $this->rest_update(
			$id,
			array( 'cptdp_post_statistics' => array( array( 'label' => 'A', 'value' => 'B', 'extra' => 'C' ) ) )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $id, 'cptdp_post_statistics' ) );
	}

	public function test_rest_denies_subscriber(): void {
		$id = $this->make_post();
		$this->acting_as( 'subscriber' );

		$this->assertSame( 403, $this->rest_update( $id, array( 'cptdp_highlight' => true ) )->get_status() );
	}

	public function test_rest_denies_anonymous(): void {
		$id = $this->make_post();
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->rest_update( $id, array( 'cptdp_highlight' => true ) )->get_status() );
	}

	/**
	 * Update a post's meta through the REST API.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta    Meta values.
	 */
	protected function rest_update( int $post_id, array $meta ): WP_REST_Response {
		$GLOBALS['wp_rest_server'] = null;

		$object  = get_post_type_object( $this->post_type() );
		$base    = $object->rest_base ? $object->rest_base : $object->name;
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $base . '/' . $post_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meta' => $meta ) ) );

		return rest_do_request( $request );
	}

	/**
	 * Reset the REST server after each test.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}
}
