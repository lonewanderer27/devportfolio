<?php
/**
 * Job Roles seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group job-role
 * @group seeder
 */
class CPTDP_Test_Seeder_Job_Roles extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'job-roles';
	}

	public function test_the_four_default_roles_are_created_in_order(): void {
		$this->run_seeder();

		$this->assertSame(
			array( 'Virtual Assistant', 'Social Media Manager', 'Digital Marketer', 'Web Developer' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_virtual_assistant_is_the_only_highlight(): void {
		$this->run_seeder();

		$marked = array();
		foreach ( $this->items() as $item ) {
			if ( get_post_meta( $item->ID, 'cptdp_highlight', true ) ) {
				$marked[] = $item->post_title;
			}
		}

		$this->assertSame( array( 'Virtual Assistant' ), $marked );
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'job_role',
					'post_status' => 'any',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
		}
	}

	public function test_row_with_a_bad_highlight_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\n1,A,1\n2,B,yes\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_two_highlights_error_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\n1,A,1\n2,B,1\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_row_with_non_numeric_order_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,highlight\nabc,A,0\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 0, $this->count_items() );
	}
}
