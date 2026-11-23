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
		$path   = $this->temp_csv( "order,title,site_url,description,icon\n10,Slack,https://slack.com,Chat,\nabc,Zoom,https://zoom.com,Video,\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}

	public function test_invalid_url_is_dropped_by_meta_sanitizer(): void {
		$path = $this->temp_csv( "order,title,site_url,description,icon\n10,Bad URL,javascript:alert(1),Nope,\n" );
		$this->run_seeder( false, $path );

		$this->assertSame( '', get_post_meta( $this->items()[0]->ID, 'cptdp_site_url', true ) );
	}

	/**
	 * Write a CSV and an icons folder beside it, with a real PNG for each icon name.
	 *
	 * @param string   $rows  CSV text with a header row.
	 * @param string[] $icons Icon file names to create.
	 * @return string CSV path.
	 */
	private function icon_csv( string $rows, array $icons = array( 'slack.png' ) ): string {
		$dir = wp_tempnam( 'cptdp-icons' );
		unlink( $dir );
		mkdir( $dir . '/icons', 0755, true );

		$image = imagecreatetruecolor( 128, 128 );
		foreach ( $icons as $name ) {
			imagepng( $image, $dir . '/icons/' . $name );
			$this->temp_files[] = $dir . '/icons/' . $name;
		}
		imagedestroy( $image );

		$csv = $dir . '/tools.csv';
		file_put_contents( $csv, $rows ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = $csv;

		return $csv;
	}

	private function slack_row(): string {
		return "order,title,site_url,description,icon\n10,Slack,https://slack.com,Chat,slack.png\n";
	}

	public function test_every_shipped_row_names_an_existing_icon(): void {
		foreach ( $this->csv_rows() as $row ) {
			$this->assertNotSame( '', $row['icon'], $row['title'] . ' needs an icon.' );
			$this->assertFileExists( dirname( $this->csv_path() ) . '/icons/' . $row['icon'], $row['title'] );
		}
	}

	public function test_new_tool_gets_its_icon_as_featured_image(): void {
		$path = $this->icon_csv( $this->slack_row() );
		$this->run_seeder( false, $path );

		$id = $this->items()[0]->ID;
		$this->assertTrue( has_post_thumbnail( $id ) );

		$attachment = get_post_thumbnail_id( $id );
		$this->assertSame( 'slack.png', get_post_meta( $attachment, '_cptdp_icon_source', true ) );
		$this->assertSame( 'Slack logo', get_post_meta( $attachment, '_wp_attachment_image_alt', true ) );
	}

	public function test_existing_tool_without_a_thumbnail_is_filled(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'software_tool',
				'post_title' => 'Slack',
			)
		);

		$result = $this->run_seeder( false, $this->icon_csv( $this->slack_row() ) );

		$this->assertSame( array( 'Slack' ), $result['updated'] );
		$this->assertTrue( has_post_thumbnail( $id ) );
	}

	public function test_a_thumbnail_already_set_is_never_replaced(): void {
		$id    = self::factory()->post->create(
			array(
				'post_type'  => 'software_tool',
				'post_title' => 'Slack',
			)
		);
		$mine = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		set_post_thumbnail( $id, $mine );

		$result = $this->run_seeder( false, $this->icon_csv( $this->slack_row() ) );

		$this->assertSame( array( 'Slack' ), $result['skipped'] );
		$this->assertSame( $mine, get_post_thumbnail_id( $id ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$result = $this->run_seeder( true, $this->icon_csv( $this->slack_row() ) );

		$this->assertSame( array( 'Slack' ), $result['created'] );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_second_run_is_a_no_op(): void {
		$path = $this->icon_csv( $this->slack_row() );
		$this->run_seeder( false, $path );
		$result = $this->run_seeder( false, $path );

		$this->assertSame( array( 'Slack' ), $result['skipped'] );
		$this->assertSame( array(), $result['updated'] );
		$this->assertSame( 1, $this->count_items() );
	}

	public function test_the_same_icon_file_is_reused_not_duplicated(): void {
		$path = $this->icon_csv( "order,title,site_url,description,icon\n10,Slack,https://slack.com,Chat,slack.png\n20,Slack Huddle,https://slack.com,Calls,slack.png\n" );
		$this->run_seeder( false, $path );

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => '_cptdp_icon_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'slack.png', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);

		$this->assertCount( 1, $attachments );
	}

	public function test_missing_icon_file_errors_before_writing(): void {
		$path   = $this->icon_csv( "order,title,site_url,description,icon\n10,Slack,https://slack.com,Chat,slack.png\n20,Zoom,https://zoom.us,Video,nope.png\n" );
		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_icon_missing', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items(), 'Validation must run before any insert.' );
	}

	public function test_icon_that_is_not_an_image_is_rejected(): void {
		$path = $this->icon_csv( $this->slack_row(), array() );
		file_put_contents( dirname( $path ) . '/icons/slack.png', 'not an image' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = dirname( $path ) . '/icons/slack.png';

		$result = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_icon_type', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_icon_size_is_registered(): void {
		$sizes = wp_get_registered_image_subsizes();

		$this->assertArrayHasKey( 'cptdp-tool-icon', $sizes );
		$this->assertSame( 128, $sizes['cptdp-tool-icon']['width'] );
		$this->assertSame( 128, $sizes['cptdp-tool-icon']['height'] );
	}
}
