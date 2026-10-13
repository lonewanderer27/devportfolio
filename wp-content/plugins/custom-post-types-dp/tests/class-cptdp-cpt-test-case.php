<?php
/**
 * Shared tests for every custom post type module.
 *
 * @package CustomPostTypesDP
 */

/**
 * Registration, editor, admin column and list order checks, driven by
 * `expected()` from the concrete test class.
 */
abstract class CPTDP_CPT_Test_Case extends CPTDP_Test_Case {

	/**
	 * Post type slug under test.
	 */
	abstract protected function post_type(): string;

	/**
	 * Expected registration data.
	 *
	 * Keys: slug, menu_position, hierarchical, supports (string[]),
	 * taxonomies (string[]), columns (string[] added after title, in order),
	 * default_order (bool).
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function expected(): array;

	/**
	 * Set up the global state the admin-only hooks read.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_meta_boxes'] = array();
	}

	/**
	 * Restore front-end screen state.
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_post_type_is_registered(): void {
		$expected = $this->expected();
		$object   = get_post_type_object( $this->post_type() );

		$this->assertInstanceOf( WP_Post_Type::class, $object );
		$this->assertTrue( $object->public );
		$this->assertTrue( $object->show_ui );
		$this->assertTrue( $object->show_in_rest );
		$this->assertTrue( $object->has_archive );
		$this->assertSame( $expected['hierarchical'], $object->hierarchical );
		$this->assertSame( $expected['menu_position'], $object->menu_position );
		$this->assertSame( $expected['slug'], $object->rewrite['slug'] );
		$this->assertFalse( $object->rewrite['with_front'] );
	}

	public function test_post_type_supports(): void {
		foreach ( $this->expected()['supports'] as $feature ) {
			$this->assertTrue( post_type_supports( $this->post_type(), $feature ), "Missing support: {$feature}" );
		}

		$this->assertFalse( post_type_supports( $this->post_type(), 'comments' ) );
	}

	public function test_taxonomies_are_attached(): void {
		$attached = get_object_taxonomies( $this->post_type() );

		if ( array() === $this->expected()['taxonomies'] ) {
			$this->assertSame( array(), $attached );
		}

		foreach ( $this->expected()['taxonomies'] as $taxonomy ) {
			$this->assertContains( $taxonomy, $attached );
		}
	}

	public function test_classic_editor_is_forced_only_for_this_type(): void {
		$this->assertFalse( apply_filters( 'use_block_editor_for_post_type', true, $this->post_type() ) );
		$this->assertTrue( apply_filters( 'use_block_editor_for_post_type', true, 'post' ) );
	}

	public function test_admin_columns_are_added_after_title(): void {
		$columns = apply_filters(
			'manage_' . $this->post_type() . '_posts_columns',
			array(
				'cb'    => '<input type="checkbox">',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame(
			array_merge( array( 'cb', 'title' ), $this->expected()['columns'], array( 'date' ) ),
			array_keys( $columns )
		);
	}

	public function test_order_column_is_sortable(): void {
		if ( ! in_array( 'menu_order', $this->expected()['columns'], true ) ) {
			$this->markTestSkipped( 'No Order column.' );
		}

		$sortable = apply_filters( 'manage_edit-' . $this->post_type() . '_sortable_columns', array() );

		$this->assertSame( 'menu_order', $sortable['menu_order'] );
	}

	public function test_order_column_renders_menu_order(): void {
		if ( ! in_array( 'menu_order', $this->expected()['columns'], true ) ) {
			$this->markTestSkipped( 'No Order column.' );
		}

		$id = self::factory()->post->create(
			array(
				'post_type'  => $this->post_type(),
				'menu_order' => 7,
			)
		);

		$this->assertSame( '7', $this->render_column( 'menu_order', $id ) );
	}

	public function test_admin_list_defaults_to_menu_order(): void {
		if ( ! $this->expected()['default_order'] ) {
			$this->markTestSkipped( 'No default order hook.' );
		}

		$query = $this->admin_list_query( $this->post_type() );

		$this->assertSame( 'menu_order title', $query->get( 'orderby' ) );
		$this->assertSame( 'ASC', $query->get( 'order' ) );
	}

	public function test_admin_list_keeps_explicit_orderby(): void {
		if ( ! $this->expected()['default_order'] ) {
			$this->markTestSkipped( 'No default order hook.' );
		}

		$_GET['orderby'] = 'title';
		$query           = $this->admin_list_query( $this->post_type() );

		$this->assertNotSame( 'menu_order title', $query->get( 'orderby' ) );
	}

	public function test_default_order_ignores_other_post_types(): void {
		if ( ! $this->expected()['default_order'] ) {
			$this->markTestSkipped( 'No default order hook.' );
		}

		$query = $this->admin_list_query( 'post' );

		$this->assertNotSame( 'menu_order title', $query->get( 'orderby' ) );
	}

	public function test_default_order_ignores_front_end(): void {
		if ( ! $this->expected()['default_order'] ) {
			$this->markTestSkipped( 'No default order hook.' );
		}

		$query = new WP_Query();
		$GLOBALS['wp_the_query'] = $query;
		$query->query( array( 'post_type' => $this->post_type() ) );

		$this->assertNotSame( 'menu_order title', $query->get( 'orderby' ) );
	}

	public function test_archive_resolves(): void {
		$this->set_permalink_structure( '/%postname%/' );

		// Rewrite rules are built at registration time, so register under pretty permalinks.
		do_action( 'cptdp_register_post_types' );
		flush_rewrite_rules();

		$id = self::factory()->post->create( array( 'post_type' => $this->post_type() ) );

		$this->go_to( get_post_type_archive_link( $this->post_type() ) );
		$this->assertTrue( is_post_type_archive( $this->post_type() ) );

		$this->go_to( get_permalink( $id ) );
		$this->assertTrue( is_singular( $this->post_type() ) );
	}

	/**
	 * Run an admin list main query for a post type.
	 *
	 * @param string $post_type Post type.
	 */
	protected function admin_list_query( string $post_type ): WP_Query {
		set_current_screen( 'edit-' . $post_type );

		$query                   = new WP_Query();
		$GLOBALS['wp_the_query'] = $query;
		$query->query( array( 'post_type' => $post_type ) );

		return $query;
	}

	/**
	 * Render a list table column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	protected function render_column( string $column, int $post_id ): string {
		return $this->capture(
			function () use ( $column, $post_id ): void {
				do_action( 'manage_' . $this->post_type() . '_posts_custom_column', $column, $post_id );
			}
		);
	}
}
