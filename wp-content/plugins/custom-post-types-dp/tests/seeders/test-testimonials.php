<?php
/**
 * Testimonials seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group testimonial
 * @group seeder
 */
class CPTDP_Test_Seeder_Testimonials extends CPTDP_Seeder_Test_Case {

	protected function seeder_name(): string {
		return 'testimonials';
	}

	public function test_csv_has_three_sample_rows_marked_as_samples(): void {
		$rows = $this->csv_rows();

		$this->assertCount( 3, $rows );
		foreach ( $rows as $row ) {
			$this->assertStringStartsWith( 'Sample testimonial.', $row['quote'], $row['title'] );
			$this->assertNotSame( '', $row['company'], $row['title'] );
		}
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'testimonial',
					'post_status' => 'publish',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
			$this->assertSame( $row['quote'], $found[0]->post_content, $row['title'] );
			$this->assertSame( $row['company'], get_post_meta( $found[0]->ID, 'cptdp_company', true ), $row['title'] );
		}
	}

	public function test_listing_follows_csv_order(): void {
		$this->run_seeder();

		$this->assertSame(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			wp_list_pluck( $this->items(), 'post_title' )
		);
	}

	public function test_existing_testimonial_keeps_its_company(): void {
		$first = $this->csv_rows()[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'  => 'testimonial',
				'post_title' => $first,
			)
		);
		update_post_meta( $id, 'cptdp_company', 'Real Company' );

		$this->run_seeder();

		$this->assertSame( 'Real Company', get_post_meta( $id, 'cptdp_company', true ) );
	}

	public function test_row_with_non_numeric_order_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,company,quote\n10,A,Co,Quote\nabc,B,Co,Quote\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}
}
