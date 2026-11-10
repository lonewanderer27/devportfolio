<?php
/**
 * Collateral Categories seeder tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group collateral-category
 * @group seeder
 */
class CPTDP_Test_Seeder_Collateral_Categories extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();
		foreach ( get_terms( array( 'taxonomy' => 'collateral_category', 'hide_empty' => false ) ) as $term ) {
			wp_delete_term( $term->term_id, 'collateral_category' );
		}
	}

	private function seeder(): array {
		$seeders = cptdp_get_seeders();
		$this->assertArrayHasKey( 'collateral-categories', $seeders );
		return $seeders['collateral-categories'];
	}

	private function run_seeder( bool $dry_run = false, ?string $path = null ) {
		$seeder = $this->seeder();
		return call_user_func( $seeder['callback'], $dry_run, $path ?? $seeder['csv'] );
	}

	private function slugs(): array {
		$slugs = get_terms( array( 'taxonomy' => 'collateral_category', 'hide_empty' => false, 'fields' => 'slugs' ) );
		sort( $slugs );
		return $slugs;
	}

	public function test_seeder_is_registered_with_a_complete_entry(): void {
		$seeder = $this->seeder();
		$this->assertSame( 'collateral_category', $seeder['taxonomy'] );
		$this->assertSame( array( 'title', 'slug' ), $seeder['columns'] );
		$this->assertIsCallable( $seeder['callback'] );
		$this->assertFileExists( $seeder['csv'] );
	}

	public function test_registry_order_is_before_documents_chain(): void {
		$names = array_keys( cptdp_get_seeders() );
		$this->assertLessThan( array_search( 'collateral-categories', $names, true ), array_search( 'aspect-ratios', $names, true ) );
		$this->assertLessThan( array_search( 'documents', $names, true ), array_search( 'collateral-categories', $names, true ) );
	}

	public function test_csv_is_well_formed(): void {
		$rows = cptdp_seed_read_csv( $this->seeder()['csv'], $this->seeder()['columns'] );
		$this->assertSame( array( 'Flyer', 'Logo' ), wp_list_pluck( $rows, 'title' ) );
		$this->assertSame( array( 'flyer', 'logo' ), wp_list_pluck( $rows, 'slug' ) );
	}

	public function test_creates_terms_and_is_idempotent(): void {
		$this->assertSame( array( 'Flyer', 'Logo' ), $this->run_seeder()['created'] );
		$second = $this->run_seeder();
		$this->assertSame( array(), $second['created'] );
		$this->assertSame( array( 'Flyer', 'Logo' ), $second['skipped'] );
		$this->assertSame( array( 'flyer', 'logo' ), $this->slugs() );
	}

	public function test_dry_run_writes_nothing(): void {
		$result = $this->run_seeder( true );
		$this->assertSame( array( 'Flyer', 'Logo' ), $result['created'] );
		$this->assertSame( array(), $this->slugs() );
	}

	public function test_matches_name_and_preserves_existing_term(): void {
		$existing = wp_insert_term( 'Flyer', 'collateral_category', array( 'slug' => 'handmade-flyer', 'description' => 'Keep me' ) );
		$result = $this->run_seeder();
		$this->assertContains( 'Flyer', $result['skipped'] );
		$this->assertSame( 'Keep me', get_term( $existing['term_id'], 'collateral_category' )->description );
	}

	public function test_restores_deleted_term(): void {
		$this->run_seeder();
		wp_delete_term( get_term_by( 'slug', 'flyer', 'collateral_category' )->term_id, 'collateral_category' );
		$result = $this->run_seeder();
		$this->assertSame( array( 'Flyer' ), $result['created'] );
	}

	/**
	 * @dataProvider invalid_csvs
	 */
	public function test_bad_csv_aborts_before_any_write( string $rows ): void {
		$path = wp_tempnam( 'collateral-categories.csv' );
		file_put_contents( $path, "title,slug\n" . $rows ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$result = $this->run_seeder( false, $path );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertWPError( $result );
		$this->assertSame( array(), $this->slugs() );
	}

	public function invalid_csvs(): array {
		return array(
			'duplicate slug' => array( "Flyer,flyer\nOther,flyer\n" ),
			'invalid slug'   => array( "Flyer,not a slug\n" ),
		);
	}

	public function test_missing_csv_errors(): void {
		$result = $this->run_seeder( false, '/nonexistent/collateral-categories.csv' );
		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_missing', $result->get_error_code() );
	}
}
