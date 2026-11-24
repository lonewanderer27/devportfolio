<?php
/**
 * Job Role post type tests (DPH-003).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group job-role
 */
class CPTDP_Test_Job_Role extends CPTDP_Test_Case {

	private function make_role( string $title, int $order = 0, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'job_role',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);
	}

	private function is_highlight( int $id ): bool {
		return (bool) get_post_meta( $id, 'cptdp_highlight', true );
	}

	public function test_registration(): void {
		$object = get_post_type_object( 'job_role' );

		$this->assertInstanceOf( WP_Post_Type::class, $object );
		$this->assertFalse( $object->public, 'No front-end URL.' );
		$this->assertFalse( $object->has_archive );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_in_rest );
		$this->assertSame( 24, $object->menu_position );
		$this->assertTrue( post_type_supports( 'job_role', 'title' ) );
		$this->assertTrue( post_type_supports( 'job_role', 'page-attributes' ) );
		$this->assertFalse( post_type_supports( 'job_role', 'editor' ) );
		$this->assertSame( 'Job Roles', $object->labels->name );
		$this->assertSame( 'Job Role', $object->labels->singular_name );
	}

	public function test_highlight_meta_is_registered_for_rest(): void {
		$this->assertTrue( registered_meta_key_exists( 'post', 'cptdp_highlight', 'job_role' ) );
	}

	public function test_marking_a_role_clears_the_previous_highlight(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );

		update_post_meta( $first, 'cptdp_highlight', 1 );
		$this->assertTrue( $this->is_highlight( $first ) );

		update_post_meta( $second, 'cptdp_highlight', 1 );

		$this->assertTrue( $this->is_highlight( $second ) );
		$this->assertFalse( $this->is_highlight( $first ), 'Only one role can be the highlight.' );
	}

	public function test_a_draft_or_trashed_highlight_is_also_cleared(): void {
		$draft = $this->make_role( 'Draft', 1, 'draft' );
		$new   = $this->make_role( 'New', 2 );

		update_post_meta( $draft, 'cptdp_highlight', 1 );
		update_post_meta( $new, 'cptdp_highlight', 1 );

		$this->assertFalse( $this->is_highlight( $draft ) );
	}

	public function test_unmarking_leaves_no_highlight_and_touches_nothing_else(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		delete_post_meta( $first, 'cptdp_highlight' );

		$this->assertFalse( $this->is_highlight( $first ) );
		$this->assertFalse( $this->is_highlight( $second ) );
	}

	public function test_a_false_value_does_not_clear_the_current_highlight(): void {
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		update_post_meta( $second, 'cptdp_highlight', false );

		$this->assertTrue( $this->is_highlight( $first ) );
	}

	public function test_other_post_types_do_not_affect_job_roles(): void {
		$role = $this->make_role( 'A', 1 );
		update_post_meta( $role, 'cptdp_highlight', 1 );

		$document = self::factory()->post->create( array( 'post_type' => 'document' ) );
		update_post_meta( $document, 'cptdp_highlight', 1 );

		$this->assertTrue( $this->is_highlight( $role ) );
		$this->assertTrue( $this->is_highlight( $document ) );
	}

	public function test_rest_update_obeys_the_single_highlight_rule(): void {
		$this->acting_as( 'administrator' );
		$first  = $this->make_role( 'A', 1 );
		$second = $this->make_role( 'B', 2 );
		update_post_meta( $first, 'cptdp_highlight', 1 );

		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/job_role/' . $second );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meta' => array( 'cptdp_highlight' => true ) ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->is_highlight( $second ) );
		$this->assertFalse( $this->is_highlight( $first ) );

		$GLOBALS['wp_rest_server'] = null;
	}

	public function test_meta_box_saves_and_clears_the_highlight(): void {
		$this->acting_as( 'administrator' );
		$id = $this->make_role( 'A', 1 );

		$_POST = array(
			'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ),
			'cptdp_highlight'                => '1',
		);
		cptdp_job_role_save_meta( $id );
		$this->assertTrue( $this->is_highlight( $id ) );

		$_POST = array( 'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ) );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ) );
	}

	public function test_meta_box_ignores_a_missing_nonce_and_a_user_who_cannot_edit(): void {
		$id = $this->make_role( 'A', 1 );

		$_POST = array( 'cptdp_highlight' => '1' );
		$this->acting_as( 'administrator' );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ), 'No nonce, no write.' );

		$_POST = array(
			'cptdp_job_role_highlight_nonce' => wp_create_nonce( 'cptdp_job_role_highlight' ),
			'cptdp_highlight'                => '1',
		);
		$this->acting_as( 'subscriber' );
		cptdp_job_role_save_meta( $id );
		$this->assertFalse( $this->is_highlight( $id ), 'A subscriber cannot edit.' );
	}

	public function test_columns_follow_title(): void {
		$columns = cptdp_job_role_columns( array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );

		$this->assertSame( array( 'cb', 'title', 'cptdp_highlight', 'menu_order', 'date' ), array_keys( $columns ) );
	}
}
