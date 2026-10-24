<?php
/**
 * Aspect Ratios seeder tests.
 *
 * Terms are not posts, so this does not extend CPTDP_Seeder_Test_Case (its
 * generic tests assume titles, statuses and Trash).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group aspect-ratio
 * @group seeder
 */
class CPTDP_Test_Seeder_Aspect_Ratios extends CPTDP_Test_Case {

	/**
	 * Temp files to remove after each test.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	/**
	 * Terms are wiped after bootstrap while options survive; start with no terms
	 * and the seeded flag unset, so only the seeder under test creates terms.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->delete_all_terms();
		delete_option( 'cptdp_aspect_ratio_seeded' );
	}

	public function tear_down(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	private function delete_all_terms(): void {
		// WordPress refuses to delete a taxonomy's default term (here 16x9).
		delete_option( 'default_term_aspect_ratio' );

		$terms = get_terms(
			array(
				'taxonomy'   => 'aspect_ratio',
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'aspect_ratio' );
		}
	}

	/**
	 * @return string[] Term slugs, sorted.
	 */
	private function slugs(): array {
		$slugs = get_terms(
			array(
				'taxonomy'   => 'aspect_ratio',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		sort( $slugs );

		return $slugs;
	}

	private function seeder(): array {
		$seeders = cptdp_get_seeders();
		$this->assertArrayHasKey( 'aspect-ratios', $seeders );

		return $seeders['aspect-ratios'];
	}

	private function run_seeder( bool $dry_run = false, ?string $csv_path = null ) {
		return call_user_func( $this->seeder()['callback'], $dry_run, $csv_path ?? $this->seeder()['csv'] );
	}

	private function temp_csv( string $contents ): string {
		$path = wp_tempnam( 'cptdp-seed.csv' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = $path;

		return $path;
	}

	public function test_seeder_is_registered_with_a_complete_entry(): void {
		$seeder = $this->seeder();

		foreach ( array( 'label', 'callback', 'csv', 'taxonomy', 'columns' ) as $key ) {
			$this->assertArrayHasKey( $key, $seeder, $key );
		}
		$this->assertIsCallable( $seeder['callback'] );
		$this->assertSame( 'aspect_ratio', $seeder['taxonomy'] );
		$this->assertContains( 'title', $seeder['columns'] );
		$this->assertTrue( taxonomy_exists( $seeder['taxonomy'] ) );
		$this->assertStringContainsString( '/data/seeders/', $seeder['csv'] );
		$this->assertFileExists( $seeder['csv'] );
	}

	public function test_registers_before_videos(): void {
		$names = array_keys( cptdp_get_seeders() );

		$this->assertLessThan( array_search( 'videos', $names, true ), array_search( 'aspect-ratios', $names, true ) );
	}

	public function test_csv_matches_the_fixed_term_list(): void {
		$rows = cptdp_seed_read_csv( $this->seeder()['csv'], $this->seeder()['columns'] );

		$this->assertIsArray( $rows );
		$this->assertSame( cptdp_aspect_ratio_terms(), wp_list_pluck( $rows, 'title' ) );

		foreach ( $rows as $row ) {
			$this->assertSame( $row['title'], $row['slug'] );
		}
	}

	public function test_creates_every_term_with_name_and_slug(): void {
		$result = $this->run_seeder();

		$this->assertSame( cptdp_aspect_ratio_terms(), $result['created'] );
		$this->assertSame( array(), $result['skipped'] );

		foreach ( cptdp_aspect_ratio_terms() as $name ) {
			$term = get_term_by( 'slug', $name, 'aspect_ratio' );
			$this->assertInstanceOf( WP_Term::class, $term, $name );
			$this->assertSame( $name, $term->name );
		}
	}

	public function test_dry_run_writes_nothing(): void {
		$result = $this->run_seeder( true );

		$this->assertCount( 4, $result['created'] );
		$this->assertSame( array(), $this->slugs() );
	}

	public function test_second_run_is_idempotent(): void {
		$this->run_seeder();
		$second = $this->run_seeder();

		$this->assertSame( array(), $second['created'] );
		$this->assertCount( 4, $second['skipped'] );
		$this->assertCount( 4, $this->slugs() );
	}

	public function test_restores_a_deleted_term_without_touching_the_others(): void {
		$this->run_seeder();
		$keep = get_term_by( 'slug', '1x1', 'aspect_ratio' )->term_id;
		wp_delete_term( get_term_by( 'slug', '4x3', 'aspect_ratio' )->term_id, 'aspect_ratio' );

		$result = $this->run_seeder();

		$this->assertSame( array( '4x3' ), $result['created'] );
		$this->assertCount( 3, $result['skipped'] );
		$this->assertSame( $keep, get_term_by( 'slug', '1x1', 'aspect_ratio' )->term_id );
		$this->assertCount( 4, $this->slugs() );
	}

	public function test_does_not_touch_the_seeded_option(): void {
		$this->run_seeder();
		$this->assertFalse( get_option( 'cptdp_aspect_ratio_seeded' ) );

		update_option( 'cptdp_aspect_ratio_seeded', '1' );
		$this->run_seeder();
		$this->assertSame( '1', get_option( 'cptdp_aspect_ratio_seeded' ) );
	}

	public function test_existing_term_is_not_modified(): void {
		$ids = wp_insert_term( 'Square', 'aspect_ratio', array( 'slug' => '1x1' ) );

		$result = $this->run_seeder();

		$this->assertContains( '1x1', $result['skipped'] );
		$this->assertSame( 'Square', get_term( $ids['term_id'], 'aspect_ratio' )->name );
		$this->assertCount( 4, $this->slugs() );
	}

	public function test_missing_csv_errors(): void {
		$result = $this->run_seeder( false, '/nonexistent/aspect-ratios.csv' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
	}

	public function test_wrong_header_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "name,slug\n16x9,16x9\n" ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_header', $result->get_error_code() );
	}

	public function test_row_with_empty_title_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "title,slug\n,16x9\n" ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
	}

	/**
	 * @dataProvider invalid_csvs
	 */
	public function test_invalid_row_errors_before_writing( string $rows ): void {
		$result = $this->run_seeder( false, $this->temp_csv( "title,slug\n" . $rows ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
		$this->assertSame( array(), $this->slugs(), 'Validation must run before any insert.' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function invalid_csvs(): array {
		return array(
			'title not in the fixed list' => array( "16x9,16x9\n2x1,2x1\n" ),
			'slug differs from title'     => array( "16x9,16x9\n9x16,vertical\n" ),
			'duplicate title'             => array( "16x9,16x9\n16x9,16x9\n" ),
		);
	}

	public function test_missing_term_is_restored_so_videos_can_seed(): void {
		$this->run_seeder();
		$this->assertIsArray( cptdp_run_seeder( 'video-categories' ) );
		wp_delete_term( get_term_by( 'slug', '9x16', 'aspect_ratio' )->term_id, 'aspect_ratio' );

		$blocked = cptdp_run_seeder( 'videos' );
		$this->assertWPError( $blocked );
		$this->assertSame( 'cptdp_seed_term_missing', $blocked->get_error_code() );

		$this->assertIsArray( cptdp_run_seeder( 'aspect-ratios' ) );
		$result = cptdp_run_seeder( 'videos' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result['created'] );
	}
}
