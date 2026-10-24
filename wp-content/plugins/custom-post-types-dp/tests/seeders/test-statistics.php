<?php
/**
 * Statistics seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group statistic
 * @group seeder
 */
class CPTDP_Test_Seeder_Statistics extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'statistics';
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'statistic',
					'post_status' => 'any',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
			$this->assertSame( $row['value'], $found[0]->post_content, $row['title'] );
		}
	}

	public function test_inline_html_in_value_is_kept(): void {
		$path = $this->temp_csv( "order,title,value\n1,Bold,<b>+5 yrs</b>\n" );
		$this->run_seeder( false, $path );

		$this->assertSame( '<b>+5 yrs</b>', $this->items()[0]->post_content );
	}

	public function test_listing_follows_csv_order(): void {
		$this->run_seeder();

		$this->assertSame(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_existing_item_keeps_its_value(): void {
		$first = $this->csv_rows()[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'    => 'statistic',
				'post_title'   => $first,
				'post_content' => 'Custom',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'Custom', get_post( $id )->post_content );
	}

	public function test_row_with_non_numeric_order_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,value\n1,Clients,+40\nabc,Reply,24 hr\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}

	public function test_row_with_empty_value_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,value\n1,Clients,+40\n2,Reply,\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}
}
