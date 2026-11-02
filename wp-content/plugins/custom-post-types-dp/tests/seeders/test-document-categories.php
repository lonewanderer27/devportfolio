<?php
/**
 * Document Categories seeder tests.
 *
 * Terms are not posts, so this does not extend CPTDP_Seeder_Test_Case (its
 * generic tests assume titles, statuses and Trash).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-category
 * @group seeder
 */
class CPTDP_Test_Seeder_Document_Categories extends CPTDP_Test_Case {

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
				'taxonomy'   => 'document_category',
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'document_category' );
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
				'taxonomy'   => 'document_category',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		sort( $slugs );

		return $slugs;
	}

	private function seeder(): array {
		$seeders = cptdp_get_seeders();
		$this->assertArrayHasKey( 'document-categories', $seeders );

		return $seeders['document-categories'];
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
		$this->assertSame( 'document_category', $seeder['taxonomy'] );
		$this->assertContains( 'title', $seeder['columns'] );
		$this->assertTrue( taxonomy_exists( $seeder['taxonomy'] ) );
		$this->assertStringContainsString( '/data/seeders/', $seeder['csv'] );
		$this->assertFileExists( $seeder['csv'] );
	}

	public function test_has_no_dependency_on_other_seeders(): void {
		$this->assertCount( 4, $this->run_seeder( true )['created'] );
	}

	public function test_csv_is_well_formed(): void {
		$rows = cptdp_seed_read_csv( $this->seeder()['csv'], $this->seeder()['columns'] );

		$this->assertIsArray( $rows );
		$this->assertSame( array( 'Executive Docs', 'Operations Docs', 'Presentation Decks', 'Reports' ), wp_list_pluck( $rows, 'title' ) );
		$this->assertSame( array( 'executive-docs', 'operations-docs', 'presentation-decks', 'reports' ), wp_list_pluck( $rows, 'slug' ) );

		foreach ( $rows as $row ) {
			$this->assertSame( sanitize_title( $row['slug'] ), $row['slug'] );
		}
	}

	public function test_creates_every_term_with_name_and_slug(): void {
		$result = $this->run_seeder();

		$this->assertSame( array( 'Executive Docs', 'Operations Docs', 'Presentation Decks', 'Reports' ), $result['created'] );
		$this->assertSame( array(), $result['skipped'] );

		$term = get_term_by( 'slug', 'reports', 'document_category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'Reports', $term->name );
	}

	public function test_terms_list_in_the_requested_display_order(): void {
		$this->run_seeder();

		$terms = get_terms(
			array(
				'taxonomy'   => 'document_category',
				'hide_empty' => false,
			)
		);

		$expected = array( 'Executive Docs', 'Operations Docs', 'Presentation Decks', 'Reports' );
		$this->assertSame( $expected, wp_list_pluck( $terms, 'name' ), 'Default get_terms() order.' );

		$ids = wp_list_pluck( $terms, 'term_id' );
		$asc = $ids;
		sort( $asc );
		$this->assertSame( $asc, $ids, 'Term IDs ascend in CSV order.' );
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

	public function test_matches_the_existing_hand_made_short_form_term(): void {
		$existing = wp_insert_term( 'Executive Docs', 'document_category', array( 'slug' => 'executive-docs' ) );

		$result = $this->run_seeder();

		$this->assertSame( array( 'Executive Docs' ), $result['skipped'] );
		$this->assertSame( array( 'Operations Docs', 'Presentation Decks', 'Reports' ), $result['created'] );
		$this->assertSame( array( 'executive-docs', 'operations-docs', 'presentation-decks', 'reports' ), $this->slugs() );
		$this->assertSame( $existing['term_id'], get_term_by( 'slug', 'executive-docs', 'document_category' )->term_id );
	}

	public function test_matches_an_existing_term_by_name_when_the_slug_differs(): void {
		wp_insert_term( 'Executive Docs', 'document_category', array( 'slug' => 'shorts' ) );

		$result = $this->run_seeder();

		$this->assertContains( 'Executive Docs', $result['skipped'] );
		$this->assertSame( array( 'operations-docs', 'presentation-decks', 'reports', 'shorts' ), $this->slugs() );
	}

	public function test_existing_term_is_not_modified(): void {
		$existing = wp_insert_term(
			'Executive Docs',
			'document_category',
			array(
				'slug'        => 'executive-docs',
				'description' => 'Custom text',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'Custom text', get_term( $existing['term_id'], 'document_category' )->description );
	}

	public function test_restores_a_deleted_term_without_touching_the_others(): void {
		$this->run_seeder();
		$keep = get_term_by( 'slug', 'executive-docs', 'document_category' )->term_id;
		wp_delete_term( get_term_by( 'slug', 'reports', 'document_category' )->term_id, 'document_category' );

		$result = $this->run_seeder();

		$this->assertSame( array( 'Reports' ), $result['created'] );
		$this->assertSame( $keep, get_term_by( 'slug', 'executive-docs', 'document_category' )->term_id );
		$this->assertCount( 4, $this->slugs() );
	}

	public function test_missing_csv_errors(): void {
		$result = $this->run_seeder( false, '/nonexistent/document-categories.csv' );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
	}

	public function test_wrong_header_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "name,slug\nExecutive Docs,executive-docs\n" ) );

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_header', $result->get_error_code() );
	}

	public function test_row_with_empty_title_errors(): void {
		$result = $this->run_seeder( false, $this->temp_csv( "title,slug\n,executive-docs\n" ) );

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
			'empty slug'         => array( "Executive Docs,executive-docs\nOther,\n" ),
			'unsanitized slug'   => array( "Executive Docs,executive-docs\nOther,Not A Slug\n" ),
			'duplicate title'    => array( "Executive Docs,executive-docs\nExecutive Docs,other\n" ),
			'duplicate slug'     => array( "Executive Docs,executive-docs\nOther,executive-docs\n" ),
		);
	}
}
