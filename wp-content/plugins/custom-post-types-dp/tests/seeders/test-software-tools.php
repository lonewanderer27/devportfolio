<?php
/**
 * Software Tools seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group software-tool
 * @group seeder
 */
class CPTDP_Test_Seeder_Software_Tools extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'software-tools';
	}

	public function test_csv_is_alphabetical_with_ascending_order_and_https_urls(): void {
		$rows   = $this->csv_rows();
		$titles = wp_list_pluck( $rows, 'title' );

		$sorted = $titles;
		usort( $sorted, 'strcasecmp' );
		$this->assertSame( $sorted, $titles, 'Titles must be alphabetical, case-insensitive.' );

		$previous = -1;
		foreach ( $rows as $row ) {
			$this->assertGreaterThan( $previous, (int) $row['order'], $row['title'] . ' order must ascend.' );
			$previous = (int) $row['order'];

			$this->assertStringStartsWith( 'https://', $row['site_url'], $row['title'] . ' needs an https URL.' );
			$this->assertNotSame( '', $row['description'], $row['title'] . ' needs a description.' );
		}
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'software_tool',
					'post_status' => 'any',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
			$this->assertSame( $row['description'], $found[0]->post_content, $row['title'] );
			$this->assertSame( $row['site_url'], get_post_meta( $found[0]->ID, 'cptdp_site_url', true ), $row['title'] );
		}
	}

	public function test_listing_follows_csv_order(): void {
		$this->run_seeder();

		$this->assertSame(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_existing_item_keeps_its_url(): void {
		$first = $this->csv_rows()[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'  => 'software_tool',
				'post_title' => $first,
			)
		);
		update_post_meta( $id, 'cptdp_site_url', 'https://custom.example.com' );

		$this->run_seeder();

		$this->assertSame( 'https://custom.example.com', get_post_meta( $id, 'cptdp_site_url', true ) );
	}

	public function test_row_with_non_numeric_order_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,site_url,description\n10,Slack,https://slack.com,Chat\nabc,Zoom,https://zoom.com,Video\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}

	public function test_invalid_url_is_dropped_by_meta_sanitizer(): void {
		$path = $this->temp_csv( "order,title,site_url,description\n10,Bad URL,javascript:alert(1),Nope\n" );
		$this->run_seeder( false, $path );

		$this->assertSame( '', get_post_meta( $this->items()[0]->ID, 'cptdp_site_url', true ) );
	}
}
