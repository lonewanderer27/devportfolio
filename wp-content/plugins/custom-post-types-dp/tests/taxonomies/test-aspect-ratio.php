<?php
/**
 * Aspect Ratio taxonomy tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group taxonomies
 */
class CPTDP_Test_Aspect_Ratio extends CPTDP_Test_Case {

	/**
	 * The test library wipes terms after bootstrap but keeps options, so the
	 * seeded flag outlives the terms. Seed again for a known state.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( 'cptdp_aspect_ratio_seeded' );
		cptdp_seed_aspect_ratios();
	}

	public function test_taxonomy_is_registered(): void {
		$object = get_taxonomy( 'aspect_ratio' );

		$this->assertInstanceOf( WP_Taxonomy::class, $object );
		$this->assertFalse( $object->hierarchical );
		$this->assertFalse( $object->public );
		$this->assertFalse( $object->publicly_queryable );
		$this->assertFalse( $object->rewrite );
		$this->assertFalse( $object->query_var );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_admin_column );
		$this->assertTrue( $object->show_in_rest );
		$this->assertSame( 'cptdp_aspect_ratio_meta_box', $object->meta_box_cb );
		$this->assertSame( array( 'video' ), $object->object_type );
	}

	public function test_term_list_is_fixed(): void {
		$this->assertSame( array( '16x9', '9x16', '1x1', '4x3' ), cptdp_aspect_ratio_terms() );
	}

	public function test_terms_are_seeded(): void {
		$this->assertEqualSets( cptdp_aspect_ratio_terms(), $this->term_names() );
		$this->assertNotEmpty( get_option( 'cptdp_aspect_ratio_seeded' ) );
	}

	public function test_seeding_is_idempotent(): void {
		delete_option( 'cptdp_aspect_ratio_seeded' );

		cptdp_seed_aspect_ratios();
		cptdp_seed_aspect_ratios();

		$this->assertCount( 4, $this->term_names() );
	}

	public function test_seeding_restores_missing_terms_when_option_is_unset(): void {
		wp_delete_term( get_term_by( 'name', '4x3', 'aspect_ratio' )->term_id, 'aspect_ratio' );
		delete_option( 'cptdp_aspect_ratio_seeded' );

		cptdp_seed_aspect_ratios();

		$this->assertEqualSets( cptdp_aspect_ratio_terms(), $this->term_names() );
	}

	public function test_seeding_does_not_recreate_deleted_terms_once_seeded(): void {
		update_option( 'cptdp_aspect_ratio_seeded', '1' );
		wp_delete_term( get_term_by( 'name', '4x3', 'aspect_ratio' )->term_id, 'aspect_ratio' );

		cptdp_seed_aspect_ratios();

		$this->assertNotContains( '4x3', $this->term_names() );
	}

	public function test_new_video_gets_default_ratio(): void {
		// Core only assigns terms when the current user may assign them.
		$this->acting_as( 'editor' );
		$id = self::factory()->post->create( array( 'post_type' => 'video' ) );

		$this->assertSame( array( '16x9' ), wp_get_object_terms( $id, 'aspect_ratio', array( 'fields' => 'names' ) ) );
	}

	public function test_default_term_option_points_to_16x9(): void {
		$term = get_term_by( 'slug', '16x9', 'aspect_ratio' );

		$this->assertSame( $term->term_id, (int) get_option( 'default_term_aspect_ratio' ) );
	}

	public function test_meta_box_lists_terms_in_fixed_order_and_preselects_default(): void {
		$id   = self::factory()->post->create( array( 'post_type' => 'video' ) );
		$html = $this->capture( fn() => cptdp_aspect_ratio_meta_box( get_post( $id ) ) );

		$this->assertStringContainsString( 'name="tax_input[aspect_ratio]"', $html );

		$positions = array_map(
			static fn( string $name ): int => (int) strpos( $html, 'value="' . $name . '"' ),
			array( '16x9', '9x16', '1x1', '4x3' )
		);
		$sorted    = $positions;
		sort( $sorted );

		$this->assertSame( $sorted, $positions );
		$this->assertNotContains( 0, $positions );
		$this->assertMatchesRegularExpression( "/value=\"16x9\"\s+selected='selected'/", $html );
	}

	public function test_meta_box_selects_current_ratio_and_appends_extra_terms(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'video' ) );
		wp_set_object_terms( $id, array( '4x3' ), 'aspect_ratio' );
		wp_insert_term( '21x9', 'aspect_ratio' );

		$html = $this->capture( fn() => cptdp_aspect_ratio_meta_box( get_post( $id ) ) );

		$this->assertMatchesRegularExpression( "/value=\"4x3\"\s+selected='selected'/", $html );
		$this->assertDoesNotMatchRegularExpression( "/value=\"16x9\"\s+selected/", $html );
		$this->assertGreaterThan( strpos( $html, 'value="4x3"' ), strpos( $html, 'value="21x9"' ) );
	}

	public function test_single_select_submit_assigns_one_term(): void {
		$this->acting_as( 'editor' );

		$id = wp_insert_post(
			array(
				'post_type'   => 'video',
				'post_title'  => 'Clip',
				'post_status' => 'publish',
				'tax_input'   => array( 'aspect_ratio' => '9x16' ),
			)
		);

		$this->assertSame( array( '9x16' ), wp_get_object_terms( $id, 'aspect_ratio', array( 'fields' => 'names' ) ) );
	}

	/**
	 * Names of all aspect ratio terms.
	 *
	 * @return string[]
	 */
	private function term_names(): array {
		return get_terms(
			array(
				'taxonomy'   => 'aspect_ratio',
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);
	}
}
