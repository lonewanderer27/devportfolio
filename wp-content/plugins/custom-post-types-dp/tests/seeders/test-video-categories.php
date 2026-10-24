<?php
/**
 * Video Categories seeder tests.
 *
 * Terms are not posts, so this does not extend CPTDP_Seeder_Test_Case (its
 * generic tests assume titles, statuses and Trash).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group video-category
 * @group seeder
 */
class CPTDP_Test_Seeder_Video_Categories extends CPTDP_Test_Case {

	/**
	 * Temp files to remove after each test.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	/**
	 * Start from no terms, so only the seeder under test creates them.
	 */
	public function set_up(): void {
		parent::set_up();

		$terms = get_terms(
			array(
				'taxonomy'   => 'video_category',
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'video_category' );
		}
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

	/**
	 * @return string[] Term slugs, sorted.
	 */
	private function slugs(): array {
		$slugs = get_terms(
			array(
				'taxonomy'   => 'video_category',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		sort( $slugs );

		return $slugs;
	}

	private function seeder(): array {
		$seeders = cptdp_get_seeders();
		$this->assertArrayHasKey( 'video-categories', $seeders );

		return $seeders['video-categories'];
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
		$this->assertSame( 'video_category', $seeder['taxonomy'] );
		$this->assertContains( 'title', $seeder['columns'] );
		$this->assertTrue( taxonomy_exists( $seeder['taxonomy'] ) );
		$this->assertStringContainsString( '/data/seeders/', $seeder['csv'] );
		$this->assertFileExists( $seeder['csv'] );
	}

	public function test_registry_order_is_ratios_then_categories_then_videos(): void {
		$names = array_keys( cptdp_get_seeders() );

		$ratios     = array_search( 'aspect-ratios', $names, true );
		$categories = array_search( 'video-categories', $names, true );
		$videos     = array_search( 'videos', $names, true );

		$this->assertLessThan( $categories, $ratios );
		$this->assertLessThan( $videos, $categories );
	}

	public function test_csv_is_well_formed(): void {
		$rows = cptdp_seed_read_csv( $this->seeder()['csv'], $this->seeder()['columns'] );

		$this->assertIsArray( $rows );
		$this->assertSame( array( 'Content Creation', 'Short Form', 'AI-Generated' ), wp_list_pluck( $rows, 'title' ) );
		$this->assertSame( array( 'content-creation', 'short-form', 'ai-generated' ), wp_list_pluck( $rows, 'slug' ) );

		foreach ( $rows as $row ) {
			$this->assertSame( sanitize_title( $row['slug'] ), $row['slug'] );
		}
	}

	public function test_creates_every_term_with_name_and_slug(): void {
		$result = $this->run_seeder();

		$this->assertSame( array( 'Content Creation', 'Short Form', 'AI-Generated' ), $result['created'] );
		$this->assertSame( array(), $result['skipped'] );

		$term = get_term_by( 'slug', 'ai-generated', 'video_category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'AI-Generated', $term->name );
	}

	public function test_dry_run_writes_nothing(): void {
		$result = $this->run_seeder( true );

		$this->assertCount( 3, $result['created'] );
		$this->assertSame( array(), $this->slugs() );
	}

	public function test_second_run_is_idempotent(): void {
		$this->run_seeder();
		$second = $this->run_seeder();

		$this->assertSame( array(), $second['created'] );
		$this->assertCount( 3, $second['skipped'] );
		$this->assertCount( 3, $this->slugs() );
	}

	public function test_matches_the_existing_hand_made_short_form_term(): void {
		$existing = wp_insert_term( 'Short Form', 'video_category', array( 'slug' => 'short-form' ) );

		$result = $this->run_seeder();

		$this->assertSame( array( 'Short Form' ), $result['skipped'] );
		$this->assertSame( array( 'Content Creation', 'AI-Generated' ), $result['created'] );
		$this->assertSame( array( 'ai-generated', 'content-creation', 'short-form' ), $this->slugs() );
		$this->assertSame( $existing['term_id'], get_term_by( 'slug', 'short-form', 'video_category' )->term_id );
	}

	public function test_matches_an_existing_term_by_name_when_the_slug_differs(): void {
		wp_insert_term( 'Short Form', 'video_category', array( 'slug' => 'shorts' ) );

		$result = $this->run_seeder();

		$this->assertContains( 'Short Form', $result['skipped'] );
		$this->assertSame( array( 'ai-generated', 'content-creation', 'shorts' ), $this->slugs() );
	}

	public function test_existing_term_is_not_modified(): void {
		$existing = wp_insert_term(
			'Short Form',
			'video_category',
			array(
				'slug'        => 'short-form',
				'description' => 'Custom text',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'Custom text', get_term( $existing['term_id'], 'video_category' )->description );
	}

	public function test_restores_a_deleted_term_without_touching_the_others(): void {
		$this->run_seeder();
		$keep = get_term_by( 'slug', 'short-form', 'video_category' )->term_id;
		wp_delete_term( get_term_by( 'slug', 'ai-generated', 'video_category' )->term_id, 'video_category' );

		$result = $this->run_seeder();

		$this->assertSame( array( 'AI-Generated' ), $result['created'] );
		$this->assertSame( $keep, get_term_by( 'slug', 'short-form', 'video_category' )->term_id );
		$this->assertCount( 3, $this->slugs() );
	}

	public function test_missing_csv_errors(): void {
		$result = $this->run_seeder( false, '/nonexistent/video-categories.csv' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
	}

	public function test_wrong_header_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "name,slug\nShort Form,short-form\n" ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_header', $result->get_error_code() );
	}

	public function test_row_with_empty_title_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "title,slug\n,short-form\n" ) );

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
			'empty slug'         => array( "Short Form,short-form\nOther,\n" ),
			'unsanitized slug'   => array( "Short Form,short-form\nOther,Not A Slug\n" ),
			'duplicate title'    => array( "Short Form,short-form\nShort Form,other\n" ),
			'duplicate slug'     => array( "Short Form,short-form\nOther,short-form\n" ),
		);
	}
}
