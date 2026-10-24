<?php
/**
 * Services seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group service
 * @group seeder
 */
class CPTDP_Test_Seeder_Services extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'services';
	}

	private function find( string $title ): WP_Post {
		$found = get_posts(
			array(
				'post_type'   => 'service',
				'post_status' => 'any',
				'title'       => $title,
			)
		);

		$this->assertCount( 1, $found, $title );

		return $found[0];
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$post = $this->find( $row['title'] );

			$this->assertSame( (int) $row['order'], (int) $post->menu_order, $row['title'] );
			$this->assertSame( $row['content'], $post->post_content, $row['title'] );
		}
	}

	public function test_children_are_attached_to_their_parent(): void {
		$this->run_seeder();

		$top      = 0;
		$children = 0;

		foreach ( $this->csv_rows() as $row ) {
			$post = $this->find( $row['title'] );

			if ( '' === $row['parent'] ) {
				++$top;
				$this->assertSame( 0, (int) $post->post_parent, $row['title'] );
			} else {
				++$children;
				$this->assertSame( $this->find( $row['parent'] )->ID, (int) $post->post_parent, $row['title'] );
			}
		}

		$this->assertSame( 3, $top );
		$this->assertSame( 13, $children );
	}

	public function test_siblings_follow_csv_order(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			if ( '' !== $row['parent'] ) {
				continue;
			}

			$parent = $this->find( $row['title'] );
			$expect = array();

			foreach ( $this->csv_rows() as $child ) {
				if ( $child['parent'] === $row['title'] ) {
					$expect[] = $child['title'];
				}
			}

			$actual = get_posts(
				array(
					'post_type'   => 'service',
					'post_parent'    => $parent->ID,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'posts_per_page' => -1,
				)
			);

			$this->assertSame( $expect, wp_list_pluck( $actual, 'post_title' ), $row['title'] );
		}
	}

	public function test_existing_parent_keeps_its_value_and_gets_the_children(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'service',
				'post_title'   => 'Digital Marketing',
				'post_content' => 'Custom',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'Custom', get_post( $id )->post_content );
		$this->assertCount( 1, get_posts( array( 'post_type' => 'service', 'title' => 'Digital Marketing', 'post_status' => 'any' ) ) );
		$this->assertSame( $id, (int) $this->find( 'Ad analytics' )->post_parent );
	}

	public function test_unresolvable_parent_of_a_new_child_errors(): void {
		// "Web Creation / Management" exists only as a child, so it is no top-level parent.
		$other = self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Other' ) );
		self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_title'  => 'Web Creation / Management',
				'post_parent' => $other,
			)
		);

		$result = $this->run_seeder();

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_parent_missing', $result->get_error_code() );
		$this->assertSame( 0, (int) $this->find( 'Social Media Marketing' )->post_parent );
	}

	public function test_invalid_rows_error_before_writing(): void {
		$cases = array(
			'non-numeric order' => "order,title,parent,content\n1,A,,x\nabc,B,A,\n",
			'unknown parent'    => "order,title,parent,content\n1,A,,x\n1,B,Nope,\n",
			'parent after child' => "order,title,parent,content\n1,B,A,\n1,A,,x\n",
			'duplicate title'   => "order,title,parent,content\n1,A,,x\n2,A,,y\n",
			'grandchild'        => "order,title,parent,content\n1,A,,x\n1,B,A,\n1,C,B,\n",
		);

		foreach ( $cases as $label => $contents ) {
			$result = $this->run_seeder( false, $this->temp_csv( $contents ) );

			$this->assertWPError( $result, $label );
			$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code(), $label );
			$this->assertSame( 0, $this->count_items(), $label );
		}
	}
}
