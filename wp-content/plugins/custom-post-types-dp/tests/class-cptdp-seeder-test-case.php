<?php
/**
 * Base test case for seeders.
 *
 * @package CustomPostTypesDP
 */

/**
 * Behaviour every seeder must keep: registered, CSV well-formed, one item per
 * row, idempotent, never touches existing or trashed items, dry-run writes
 * nothing, bad CSVs abort before any write. A seeder test extends this and
 * adds assertions for how its columns map onto the created posts.
 */
abstract class CPTDP_Seeder_Test_Case extends CPTDP_Test_Case {

	/**
	 * Temp files to remove after each test.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	/**
	 * Registered seeder name, e.g. `software-tools`.
	 */
	abstract protected function seeder_name(): string;

	public function tear_down(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	/**
	 * Registry entry for this seeder.
	 *
	 * @return array<string, mixed>
	 */
	protected function seeder(): array {
		$seeders = cptdp_get_seeders();
		$this->assertArrayHasKey( $this->seeder_name(), $seeders, 'Seeder is not registered.' );

		return $seeders[ $this->seeder_name() ];
	}

	protected function post_type(): string {
		return $this->seeder()['post_type'];
	}

	protected function csv_path(): string {
		return $this->seeder()['csv'];
	}

	/**
	 * Rows of the shipped CSV, keyed by header.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function csv_rows(): array {
		$this->assertFileExists( $this->csv_path() );

		$rows = cptdp_seed_read_csv( $this->csv_path(), $this->seeder()['columns'] );
		$this->assertIsArray( $rows );
		$this->assertNotEmpty( $rows );

		return $rows;
	}

	/**
	 * Run the seeder, optionally against another CSV.
	 *
	 * @param bool        $dry_run  Dry run.
	 * @param string|null $csv_path CSV override.
	 * @return array{created: string[], skipped: string[]}|WP_Error
	 */
	protected function run_seeder( bool $dry_run = false, ?string $csv_path = null ) {
		return call_user_func( $this->seeder()['callback'], $dry_run, $csv_path ?? $this->csv_path() );
	}

	/**
	 * Write a temp CSV.
	 *
	 * @param string $contents File contents.
	 */
	protected function temp_csv( string $contents ): string {
		$path = wp_tempnam( 'cptdp-seed.csv' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = $path;

		return $path;
	}

	/**
	 * Seeded items ordered the way the admin list and archive show them.
	 *
	 * @return WP_Post[]
	 */
	protected function items(): array {
		return get_posts(
			array(
				'post_type'      => $this->post_type(),
				'post_status'    => 'any',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);
	}

	protected function count_items(): int {
		return count( $this->items() );
	}

	public function test_seeder_is_registered_with_a_complete_entry(): void {
		$seeder = $this->seeder();

		foreach ( array( 'label', 'callback', 'csv', 'post_type', 'columns' ) as $key ) {
			$this->assertArrayHasKey( $key, $seeder, $key );
		}
		$this->assertIsCallable( $seeder['callback'] );
		$this->assertContains( 'title', $seeder['columns'] );
		$this->assertTrue( post_type_exists( $seeder['post_type'] ) );
		$this->assertStringContainsString( '/data/seeders/', $seeder['csv'] );
	}

	public function test_csv_is_well_formed(): void {
		$rows   = $this->csv_rows();
		$titles = wp_list_pluck( $rows, 'title' );

		$this->assertSame( array_values( array_unique( $titles ) ), $titles, 'Titles must be unique.' );
	}

	public function test_seeds_one_published_item_per_csv_row(): void {
		$rows   = $this->csv_rows();
		$result = $this->run_seeder();

		$this->assertIsArray( $result );
		$this->assertCount( count( $rows ), $result['created'] );
		$this->assertSame( array(), $result['skipped'] );
		$this->assertSame( count( $rows ), $this->count_items() );

		foreach ( $this->items() as $item ) {
			$this->assertSame( 'publish', $item->post_status, $item->post_title );
		}
	}

	public function test_second_run_is_idempotent(): void {
		$rows = $this->csv_rows();
		$this->run_seeder();

		$result = $this->run_seeder();

		$this->assertSame( array(), $result['created'] );
		$this->assertCount( count( $rows ), $result['skipped'] );
		$this->assertSame( count( $rows ), $this->count_items() );
	}

	public function test_existing_item_is_not_modified(): void {
		$rows  = $this->csv_rows();
		$first = $rows[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'    => $this->post_type(),
				'post_status'  => 'draft',
				'post_title'   => $first,
				'post_content' => 'Custom text',
				'menu_order'   => 999,
			)
		);

		$result = $this->run_seeder();

		$this->assertContains( $first, $result['skipped'] );
		$this->assertCount( count( $rows ), $this->items() );

		$post = get_post( $id );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 'Custom text', $post->post_content );
		$this->assertSame( 999, (int) $post->menu_order );
	}

	public function test_trashed_item_is_not_recreated(): void {
		$first = $this->csv_rows()[0]['title'];

		$id = self::factory()->post->create(
			array(
				'post_type'   => $this->post_type(),
				'post_title'  => $first,
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $id );

		$result = $this->run_seeder();

		$this->assertContains( $first, $result['skipped'] );
		$this->assertSame( 'trash', get_post_status( $id ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$rows   = $this->csv_rows();
		$result = $this->run_seeder( true );

		$this->assertCount( count( $rows ), $result['created'] );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_missing_csv_errors(): void {
		$result = $this->run_seeder( false, '/nonexistent/seed.csv' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_wrong_header_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "name,url\nSlack,https://slack.com\n" ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_header', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_row_with_empty_title_errors(): void {
		$columns = $this->seeder()['columns'];
		$path    = $this->temp_csv( implode( ',', $columns ) . "\n" . implode( ',', array_fill( 0, count( $columns ), '' ) ) . "\n" );
		$result  = $this->run_seeder( false, $path );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( 0, $this->count_items() );
	}

	public function test_run_through_the_registry_matches_a_direct_run(): void {
		$rows   = $this->csv_rows();
		$result = cptdp_run_seeder( $this->seeder_name() );

		$this->assertIsArray( $result );
		$this->assertCount( count( $rows ), $result['created'] );
	}
}
