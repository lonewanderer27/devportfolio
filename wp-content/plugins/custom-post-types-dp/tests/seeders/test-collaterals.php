<?php
/**
 * Collaterals seeder tests.
 *
 * Generic seeder behaviour comes from CPTDP_Seeder_Test_Case.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group collateral
 * @group seeder
 */
class CPTDP_Test_Seeder_Collaterals extends CPTDP_Seeder_Test_Case {

	/**
	 * Terms are wiped between tests; the collaterals seeder needs the categories
	 * and the software tools to exist.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->assertIsArray( cptdp_run_seeder( 'collateral-categories' ) );
		$this->assertIsArray( cptdp_run_seeder( 'software-tools' ) );
	}

	protected function seeder_name(): string {
		return 'collaterals';
	}

	private function find( string $title ): WP_Post {
		$found = get_posts(
			array(
				'post_type'   => 'collateral',
				'post_status' => 'any',
				'title'       => $title,
			)
		);

		$this->assertCount( 1, $found, $title );

		return $found[0];
	}

	/**
	 * A CSV with the real header and one row.
	 *
	 * @param string $row Row without the header.
	 */
	private function csv_with( string $row ): string {
		return $this->temp_csv( implode( ',', $this->seeder()['columns'] ) . "\n" . $row . "\n" );
	}

	public function test_csv_has_ascending_order(): void {
		$previous = -1;

		foreach ( $this->csv_rows() as $row ) {
			$this->assertGreaterThan( $previous, (int) $row['order'], $row['title'] );
			$previous = (int) $row['order'];
		}
	}

	public function test_csv_meets_the_acceptance_data(): void {
		$rows  = $this->csv_rows();
		$pages = array_map( 'intval', wp_list_pluck( $rows, 'total_pages' ) );

		$seeder = cptdp_get_seeders()['collateral-categories'];
		$known  = wp_list_pluck( cptdp_seed_read_csv( $seeder['csv'], $seeder['columns'] ), 'slug' );
		$this->assertEqualSets( $known, array_unique( wp_list_pluck( $rows, 'category' ) ), 'Every category needs a collateral.' );

		$this->assertContains( 24, $pages, 'A 24-page item.' );
		$this->assertContains( 1, $pages, 'A single page item.' );

		$portrait = array_filter(
			$rows,
			static function ( $row ) {
				return (int) $row['total_pages'] >= 2 && (int) $row['total_pages'] <= 23 && false === stripos( $row['paper_size'], 'landscape' );
			}
		);
		$this->assertNotEmpty( $portrait, 'A multi-page portrait item.' );

		$landscape = array_filter(
			$rows,
			static function ( $row ) {
				return false !== stripos( $row['paper_size'], 'landscape' );
			}
		);
		$this->assertNotEmpty( $landscape, 'A landscape item.' );
	}

	public function test_columns_map_onto_the_created_posts(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $row ) {
			$post = $this->find( $row['title'] );

			$this->assertSame( (int) $row['order'], (int) $post->menu_order, $row['title'] );
			$this->assertSame( $row['description'], $post->post_content, $row['title'] );
			$this->assertSame( $row['paper_size'], get_post_meta( $post->ID, 'cptdp_paper_size', true ), $row['title'] );
			$this->assertSame( (int) $row['total_pages'], (int) get_post_meta( $post->ID, 'cptdp_total_pages', true ), $row['title'] );
			$this->assertSame( '1' === $row['highlight'], (bool) get_post_meta( $post->ID, 'cptdp_highlight', true ), $row['title'] );
			$this->assertSame( array( $row['category'] ), wp_get_object_terms( $post->ID, 'collateral_category', array( 'fields' => 'slugs' ) ), $row['title'] );

			$names = array_map(
				static function ( $id ) {
					return get_the_title( $id );
				},
				array_filter( (array) get_post_meta( $post->ID, 'cptdp_software_tools', true ) )
			);
			$this->assertSame( array_values( array_filter( explode( '|', $row['software_tools'] ) ) ), $names, $row['title'] );
		}
	}

	public function test_statistics_are_label_value_rows(): void {
		$this->run_seeder();

		$with = array_values(
			array_filter(
				$this->csv_rows(),
				static function ( $row ) {
					return '' !== $row['statistics'];
				}
			)
		);
		$this->assertNotEmpty( $with );
		$this->assertCount( 3, $with );

		$this->assertSame(
			array(
				array(
					'label' => 'Reach',
					'value' => '12k',
				),
				array(
					'label' => 'CTR',
					'value' => '3.4%',
				),
			),
			get_post_meta( $this->find( $with[0]['title'] )->ID, 'cptdp_post_statistics', true )
		);

		foreach ( $this->csv_rows() as $row ) {
			if ( '' === $row['statistics'] ) {
				$this->assertEmpty( get_post_meta( $this->find( $row['title'] )->ID, 'cptdp_post_statistics', true ), $row['title'] );
			}
		}
	}

	public function test_only_the_first_row_is_a_highlight(): void {
		$this->run_seeder();

		foreach ( $this->csv_rows() as $index => $row ) {
			$this->assertSame( 0 === $index, (bool) get_post_meta( $this->find( $row['title'] )->ID, 'cptdp_highlight', true ), $row['title'] );
		}
	}

	public function test_title_with_an_ampersand_survives_and_is_skipped_on_the_next_run(): void {
		$titles = array_filter(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			static function ( $title ) {
				return false !== strpos( $title, '&' );
			}
		);
		$this->assertNotEmpty( $titles, 'The CSV needs a title with "&".' );

		$this->run_seeder();
		$second = $this->run_seeder();

		foreach ( $titles as $title ) {
			$this->assertSame( $title, $this->find( $title )->post_title );
			$this->assertContains( $title, $second['skipped'] );
		}
		$this->assertSame( array(), $second['created'] );
	}

	/**
	 * @dataProvider bad_rows
	 *
	 * @param string $row Row to test.
	 */
	public function test_bad_row_errors_naming_the_row_and_writes_nothing( string $row ): void {
		$result = $this->run_seeder( false, $this->csv_with( $row ) );

		$this->assertWPError( $result );
		$this->assertSame( 0, $this->count_items() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function bad_rows(): array {
		return array(
			'unknown category'            => array( '10,A Flyer,Desc,no-such-category,,A4,1,,0' ),
			'unknown tool'                => array( '10,A Flyer,Desc,flyer,No Such Tool,A4,1,,0' ),
			'statistic without a colon'   => array( '10,A Flyer,Desc,flyer,,A4,1,Reach 12k,0' ),
			'statistic without a label'   => array( '10,A Flyer,Desc,flyer,,A4,1,:12k,0' ),
			'non-numeric pages'           => array( '10,A Flyer,Desc,flyer,,A4,many,,0' ),
			'non-numeric order'           => array( 'first,A Flyer,Desc,flyer,,A4,1,,0' ),
			'highlight other than 0 or 1' => array( '10,A Flyer,Desc,flyer,,A4,1,,yes' ),
		);
	}

	public function test_validation_error_names_the_title_and_row(): void {
		$result = $this->run_seeder( false, $this->csv_with( '10,A Flyer,Desc,flyer,,A4,many,,0' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertStringContainsString( '"A Flyer" (row 1)', $result->get_error_message() );
	}

	public function test_a_bad_later_row_stops_the_run_before_any_write(): void {
		$csv = $this->temp_csv(
			implode( ',', $this->seeder()['columns'] ) . "\n" .
			"10,Good Flyer,Desc,flyer,,A4,1,,0\n" .
			"20,Bad Flyer,Desc,flyer,,A4,1,Reach 12k,0\n"
		);

		$this->assertWPError( $this->run_seeder( false, $csv ) );
		$this->assertSame( 0, $this->count_items() );
	}
}
