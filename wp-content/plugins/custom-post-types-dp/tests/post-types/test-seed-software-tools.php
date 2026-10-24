<?php
/**
 * Software Tool seeder tests.
 *
 * @package CustomPostTypesDP
 */

require_once dirname( __DIR__, 2 ) . '/bin/seed-software-tools.php';

/**
 * @group software-tool
 * @group seeder
 */
class CPTDP_Test_Seed_Software_Tools extends CPTDP_Test_Case {

	/**
	 * Temp files to remove after each test.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	public function tear_down(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	private function csv_path(): string {
		return dirname( __DIR__, 2 ) . '/bin/software-tools.csv';
	}

	/**
	 * Rows of the shipped CSV, keyed by header.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function csv_rows(): array {
		$this->assertFileExists( $this->csv_path() );

		$rows = cptdp_seed_software_tools_read_csv( $this->csv_path() );
		$this->assertIsArray( $rows );

		return $rows;
	}

	/**
	 * Write a temp CSV.
	 *
	 * @param string $contents File contents.
	 */
	private function temp_csv( string $contents ): string {
		$path = wp_tempnam( 'cptdp-seed.csv' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = $path;

		return $path;
	}

	/**
	 * Tools ordered the way the admin list and archive show them.
	 *
	 * @return WP_Post[]
	 */
	private function tools(): array {
		return get_posts(
			array(
				'post_type'      => 'software_tool',
				'post_status'    => 'any',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);
	}

	private function count_tools(): int {
		return count( $this->tools() );
	}

	public function test_csv_is_well_formed(): void {
		$rows   = $this->csv_rows();
		$titles = wp_list_pluck( $rows, 'title' );

		$this->assertNotEmpty( $rows );
		$this->assertSame( array_unique( $titles ), $titles, 'Titles must be unique.' );

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

	public function test_seeds_one_published_tool_per_csv_row(): void {
		$rows   = $this->csv_rows();
		$result = cptdp_seed_software_tools( false, $this->csv_path() );

		$this->assertIsArray( $result );
		$this->assertCount( count( $rows ), $result['created'] );
		$this->assertSame( array(), $result['skipped'] );
		$this->assertSame( count( $rows ), $this->count_tools() );

		foreach ( $rows as $row ) {
			$found = get_posts(
				array(
					'post_type'   => 'software_tool',
					'post_status' => 'any',
					'title'       => $row['title'],
				)
			);

			$this->assertCount( 1, $found, $row['title'] );
			$this->assertSame( 'publish', $found[0]->post_status, $row['title'] );
			$this->assertSame( (int) $row['order'], (int) $found[0]->menu_order, $row['title'] );
			$this->assertSame( $row['description'], $found[0]->post_content, $row['title'] );
			$this->assertSame( $row['site_url'], get_post_meta( $found[0]->ID, 'cptdp_site_url', true ), $row['title'] );
		}
	}

	public function test_listing_follows_csv_order(): void {
		cptdp_seed_software_tools( false, $this->csv_path() );

		$this->assertSame(
			wp_list_pluck( $this->csv_rows(), 'title' ),
			wp_list_pluck( $this->tools(), 'post_title' )
		);
	}

	public function test_second_run_is_idempotent(): void {
		$rows = $this->csv_rows();
		cptdp_seed_software_tools( false, $this->csv_path() );

		$result = cptdp_seed_software_tools( false, $this->csv_path() );

		$this->assertSame( array(), $result['created'] );
		$this->assertCount( count( $rows ), $result['skipped'] );
		$this->assertSame( count( $rows ), $this->count_tools() );
	}

	public function test_existing_tool_is_not_modified(): void {
		$rows  = $this->csv_rows();
		$first = $rows[0];

		$id = self::factory()->post->create(
			array(
				'post_type'    => 'software_tool',
				'post_status'  => 'draft',
				'post_title'   => $first['title'],
				'post_content' => 'Custom text',
				'menu_order'   => 999,
			)
		);
		update_post_meta( $id, 'cptdp_site_url', 'https://custom.example.com' );

		$result = cptdp_seed_software_tools( false, $this->csv_path() );

		$this->assertContains( $first['title'], $result['skipped'] );
		$this->assertCount( count( $rows ), $this->tools() );

		$post = get_post( $id );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 'Custom text', $post->post_content );
		$this->assertSame( 999, (int) $post->menu_order );
		$this->assertSame( 'https://custom.example.com', get_post_meta( $id, 'cptdp_site_url', true ) );
	}

	public function test_trashed_tool_is_not_recreated(): void {
		$first = $this->csv_rows()[0];

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'software_tool',
				'post_title'  => $first['title'],
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $id );

		$result = cptdp_seed_software_tools( false, $this->csv_path() );

		$this->assertContains( $first['title'], $result['skipped'] );
		$this->assertSame( 'trash', get_post_status( $id ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$rows   = $this->csv_rows();
		$result = cptdp_seed_software_tools( true, $this->csv_path() );

		$this->assertCount( count( $rows ), $result['created'] );
		$this->assertSame( 0, $this->count_tools() );
	}

	public function test_missing_csv_errors(): void {
		$result = cptdp_seed_software_tools( false, '/nonexistent/software-tools.csv' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
		$this->assertSame( 0, $this->count_tools() );
	}

	public function test_wrong_header_errors(): void {
		$path   = $this->temp_csv( "name,url\nSlack,https://slack.com\n" );
		$result = cptdp_seed_software_tools( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_header', $result->get_error_code() );
		$this->assertSame( 0, $this->count_tools() );
	}

	public function test_row_with_empty_title_errors_before_writing(): void {
		$path   = $this->temp_csv( "order,title,site_url,description\n10,Slack,https://slack.com,Chat\n20,,https://example.com,No title\n" );
		$result = cptdp_seed_software_tools( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_tools(), 'Validation must run before any insert.' );
	}

	public function test_invalid_url_is_dropped_by_meta_sanitizer(): void {
		$path = $this->temp_csv( "order,title,site_url,description\n10,Bad URL,javascript:alert(1),Nope\n" );
		cptdp_seed_software_tools( false, $path );

		$tool = $this->tools()[0];
		$this->assertSame( '', get_post_meta( $tool->ID, 'cptdp_site_url', true ) );
	}
}
