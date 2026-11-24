<?php
/**
 * Portrait seeder tests (DPH-001).
 *
 * The cases that copy the bundled file skip until data/seeders/portrait/portrait.png exists (Dave's own cut-out,
 * see the DPH-001 plan). The validation cases always run.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group seeder
 * @group settings
 */
class CPTDP_Test_Seeder_Portrait extends CPTDP_Test_Case {

	private const FILE = 'data/seeders/portrait/portrait.png';

	public function set_up(): void {
		parent::set_up();

		delete_option( 'cptdp_contact' );
	}

	/**
	 * Skip a case that needs the bundled file when it is not there yet.
	 */
	private function require_bundled_portrait(): void {
		if ( ! is_file( CPTDP_PATH . self::FILE ) ) {
			$this->markTestSkipped( 'Bundled portrait ' . self::FILE . ' is not in the plugin yet (DPH-001).' );
		}
	}

	private function run_seeder( bool $dry_run = false ) {
		$seeder = cptdp_get_seeders()['portrait'];

		return call_user_func( $seeder['callback'], $dry_run, $seeder['csv'] );
	}

	private function temp_csv( string $contents ): string {
		$path = wp_tempnam( 'portrait.csv' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $path;
	}

	public function test_it_is_registered_with_its_csv(): void {
		$seeders = cptdp_get_seeders();

		$this->assertArrayHasKey( 'portrait', $seeders );
		$this->assertSame( CPTDP_PATH . 'data/seeders/portrait.csv', $seeders['portrait']['csv'] );
		$this->assertArrayNotHasKey( 'post_type', $seeders['portrait'], 'A settings seeder, not a post seeder.' );
	}

	public function test_the_bundled_file_is_a_png_when_present(): void {
		$this->require_bundled_portrait();

		$png = CPTDP_PATH . self::FILE;

		$this->assertSame( 'image/png', wp_check_filetype_and_ext( $png, 'portrait.png' )['type'] );
	}

	public function test_it_sets_the_portrait_as_a_png_attachment_the_settings_accept(): void {
		$this->require_bundled_portrait();

		$result = $this->run_seeder();

		$this->assertSame( array( 'portrait.png' ), $result['created'] );

		$id = cptdp_get_contact( 'portrait_id' );
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'attachment', get_post_type( $id ) );
		$this->assertSame( 'image/png', get_post_mime_type( $id ) );
		$this->assertSame( $id, cptdp_contact_sanitize( array( 'portrait_id' => (string) $id ) )['portrait_id'] );
	}

	public function test_it_keeps_the_other_contact_values(): void {
		$this->require_bundled_portrait();

		update_option(
			'cptdp_contact',
			array(
				'email'        => 'dave@example.com',
				'linkedin_url' => 'https://www.linkedin.com/in/dave',
			)
		);

		$this->run_seeder();

		$this->assertSame( 'dave@example.com', cptdp_get_contact( 'email' ) );
		$this->assertSame( 'https://www.linkedin.com/in/dave', cptdp_get_contact( 'linkedin_url' ) );
		$this->assertGreaterThan( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_a_second_run_changes_nothing_and_uploads_nothing(): void {
		$this->require_bundled_portrait();

		$this->run_seeder();
		$id    = cptdp_get_contact( 'portrait_id' );
		$count = (int) wp_count_posts( 'attachment' )->inherit;

		$result = $this->run_seeder();

		$this->assertSame( array(), $result['created'] );
		$this->assertSame( array( 'portrait.png' ), $result['skipped'] );
		$this->assertSame( $id, cptdp_get_contact( 'portrait_id' ) );
		$this->assertSame( $count, (int) wp_count_posts( 'attachment' )->inherit );
	}

	public function test_an_existing_portrait_is_never_replaced(): void {
		$this->require_bundled_portrait();

		$mine = self::factory()->attachment->create( array( 'post_mime_type' => 'image/webp' ) );
		update_option( 'cptdp_contact', array( 'portrait_id' => $mine ) );

		$result = $this->run_seeder();

		$this->assertSame( array( 'portrait.png' ), $result['skipped'] );
		$this->assertSame( $mine, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_a_dangling_portrait_id_is_replaced(): void {
		$this->require_bundled_portrait();

		update_option( 'cptdp_contact', array( 'portrait_id' => 999999 ) );

		$this->run_seeder();

		$this->assertNotSame( 999999, cptdp_get_contact( 'portrait_id' ) );
		$this->assertGreaterThan( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_after_the_portrait_is_removed_the_same_upload_is_reused(): void {
		$this->require_bundled_portrait();

		$this->run_seeder();
		$first = cptdp_get_contact( 'portrait_id' );
		update_option( 'cptdp_contact', array( 'portrait_id' => 0 ) );

		$this->run_seeder();

		$this->assertSame( $first, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$this->require_bundled_portrait();

		$count  = (int) wp_count_posts( 'attachment' )->inherit;
		$result = $this->run_seeder( true );

		$this->assertSame( array( 'portrait.png' ), $result['created'] );
		$this->assertSame( 0, cptdp_get_contact( 'portrait_id' ) );
		$this->assertSame( $count, (int) wp_count_posts( 'attachment' )->inherit );
	}

	public function test_a_file_name_with_a_path_errors_before_writing(): void {
		$path   = $this->temp_csv( "file\n../evil.png\n" );
		$result = cptdp_seed_portrait( false, $path );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_portrait_missing', $result->get_error_code() );
		$this->assertSame( 0, cptdp_get_contact( 'portrait_id' ) );
	}

	public function test_more_than_one_row_errors(): void {
		$path   = $this->temp_csv( "file\nportrait.png\nportrait.png\n" );
		$result = cptdp_seed_portrait( false, $path );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertWPError( $result );
		$this->assertSame( 'cptdp_seed_csv_row', $result->get_error_code() );
	}
}
