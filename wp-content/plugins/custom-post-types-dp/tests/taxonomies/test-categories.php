<?php
/**
 * Category taxonomy tests (video, document, collateral).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group taxonomies
 */
class CPTDP_Test_Categories extends CPTDP_Test_Case {

	/**
	 * Taxonomy, object type, rewrite slug, fallback menu callback, menu position.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int}>
	 */
	public static function taxonomies(): array {
		return array(
			'video'      => array( 'video_category', 'video', 'video-category', 'cptdp_video_category_menu', 'cptdp_register_video', 23 ),
			'document'   => array( 'document_category', 'document', 'document-category', 'cptdp_document_category_menu', 'cptdp_register_document', 26 ),
			'collateral' => array( 'collateral_category', 'collateral', 'collateral-category', 'cptdp_collateral_category_menu', 'cptdp_register_collateral', 28 ),
		);
	}

	/**
	 * @dataProvider taxonomies
	 */
	public function test_taxonomy_is_registered( string $taxonomy, string $post_type, string $slug ): void {
		$object = get_taxonomy( $taxonomy );

		$this->assertInstanceOf( WP_Taxonomy::class, $object );
		$this->assertFalse( $object->hierarchical );
		$this->assertTrue( $object->public );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_admin_column );
		$this->assertTrue( $object->show_in_rest );
		$this->assertSame( $taxonomy, $object->query_var );
		$this->assertSame( $slug, $object->rewrite['slug'] );
		$this->assertFalse( $object->rewrite['with_front'] );
		$this->assertContains( $post_type, $object->object_type );
	}

	/**
	 * @dataProvider taxonomies
	 */
	public function test_terms_can_be_assigned( string $taxonomy, string $post_type ): void {
		$id = self::factory()->post->create( array( 'post_type' => $post_type ) );

		wp_set_object_terms( $id, array( 'Branding' ), $taxonomy );

		$this->assertSame( array( 'Branding' ), wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'names' ) ) );
	}

	/**
	 * The temporary top-level menu only appears when the post type is missing.
	 *
	 * @dataProvider taxonomies
	 */
	public function test_fallback_menu_is_skipped_when_post_type_exists( string $taxonomy, string $post_type, string $slug, string $menu_callback ): void {
		$GLOBALS['menu'] = array();

		$menu_callback();

		$this->assertSame( array(), $GLOBALS['menu'] );
	}

	/**
	 * @dataProvider taxonomies
	 */
	public function test_fallback_menu_appears_without_post_type( string $taxonomy, string $post_type, string $slug, string $menu_callback, string $register_callback, int $position ): void {
		unregister_post_type( $post_type );

		try {
			$GLOBALS['menu'] = array();
			$menu_callback();

			$this->assertArrayHasKey( $position, $GLOBALS['menu'] );
			$this->assertSame( 'edit-tags.php?taxonomy=' . $taxonomy, $GLOBALS['menu'][ $position ][2] );
			$this->assertSame( 'manage_categories', $GLOBALS['menu'][ $position ][1] );
		} finally {
			$register_callback();
		}

		$this->assertTrue( post_type_exists( $post_type ) );
	}

	/**
	 * @dataProvider taxonomies
	 */
	public function test_parent_file_filter( string $taxonomy, string $post_type, string $slug, string $menu_callback, string $register_callback ): void {
		$_GET['taxonomy'] = $taxonomy;
		$filter           = str_replace( '_menu', '_parent_file', $menu_callback );

		$this->assertSame( 'edit.php?post_type=' . $post_type, $filter( 'edit.php?post_type=' . $post_type ) );

		unregister_post_type( $post_type );

		try {
			$this->assertSame( 'edit-tags.php?taxonomy=' . $taxonomy, $filter( 'anything' ) );

			$_GET['taxonomy'] = 'category';
			$this->assertSame( 'anything', $filter( 'anything' ) );
		} finally {
			$register_callback();
		}
	}
}
