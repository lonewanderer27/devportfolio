<?php
/**
 * Daily sweep of stray LibreOffice temporary folders (DPB-NNN F3).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group document-pages
 * @group presentations
 */
class CPTDP_Test_Temp_Sweep extends CPTDP_Test_Case {

	/** @var string[] Folders a test made, removed in tear_down. */
	private $made = array();

	public function tear_down(): void {
		foreach ( $this->made as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
		parent::tear_down();
	}

	private function folder( string $prefix, int $age ): string {
		$dir = trailingslashit( get_temp_dir() ) . $prefix . wp_generate_password( 8, false );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		touch( $dir, time() - $age );
		$this->made[] = $dir;
		return $dir;
	}

	public function test_the_sweep_is_scheduled_daily_on_init(): void {
		$this->assertSame( 10, has_action( 'init', 'cptdp_schedule_temp_sweep' ) );
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );

		cptdp_schedule_temp_sweep();

		$this->assertNotFalse( wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( CPTDP_TEMP_SWEEP_HOOK ) );
	}

	public function test_scheduling_again_keeps_one_event(): void {
		wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
		cptdp_schedule_temp_sweep();
		$first = wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK );

		cptdp_schedule_temp_sweep();

		$this->assertSame( $first, wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
		$count = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			$count += isset( $hooks[ CPTDP_TEMP_SWEEP_HOOK ] ) ? count( $hooks[ CPTDP_TEMP_SWEEP_HOOK ] ) : 0;
		}
		$this->assertSame( 1, $count );
	}

	public function test_the_cron_event_runs_the_sweep(): void {
		$this->assertSame( 10, has_action( CPTDP_TEMP_SWEEP_HOOK, 'cptdp_sweep_temp_folders' ) );
	}

	public function test_the_sweep_removes_only_old_folders_of_its_own(): void {
		$old   = $this->folder( 'cptdp-pptx-', 2 * DAY_IN_SECONDS );
		$fresh = $this->folder( 'cptdp-pptx-', 60 );
		$other = $this->folder( 'not-ours-', 2 * DAY_IN_SECONDS );

		$removed = cptdp_sweep_temp_folders();

		$this->assertGreaterThanOrEqual( 1, $removed );
		$this->assertDirectoryDoesNotExist( $old );
		$this->assertDirectoryExists( $fresh, 'A folder a running conversion may still use is kept.' );
		$this->assertDirectoryExists( $other, 'Only cptdp-pptx-* folders are touched.' );
	}

	public function test_deactivation_removes_the_event(): void {
		cptdp_schedule_temp_sweep();

		cptdp_deactivate();

		$this->assertFalse( wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) );
	}
}
