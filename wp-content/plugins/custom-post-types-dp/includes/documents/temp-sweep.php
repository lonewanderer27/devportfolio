<?php
/**
 * Daily removal of LibreOffice temporary folders left behind by a crashed conversion (DPB-NNN F3).
 *
 * CPTDP_Pptx_Converter::to_pdf() makes a `cptdp-pptx-*` folder in the temp dir (a copy of the uploaded file, the
 * PDF and a throwaway profile) and removes it when it is done. A PHP fatal or a killed worker skips that, so the
 * folder stayed for ever. A daily WP-Cron event deletes the ones older than a day with the converter's own
 * safe delete (only `cptdp-pptx-*` folders, never through a link).
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

const CPTDP_TEMP_SWEEP_HOOK = 'cptdp_sweep_temp_folders';

/**
 * Schedule the daily sweep once (on every request it only reads the cron option).
 */
function cptdp_schedule_temp_sweep(): void {
	if ( ! wp_next_scheduled( CPTDP_TEMP_SWEEP_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CPTDP_TEMP_SWEEP_HOOK );
	}
}
add_action( 'init', 'cptdp_schedule_temp_sweep' );

/**
 * Delete the stray temporary folders.
 *
 * @return int Number of folders removed.
 */
function cptdp_sweep_temp_folders(): int {
	$removed = 0;

	foreach ( CPTDP_Pptx_Converter::stray_folders() as $dir ) {
		CPTDP_Pptx_Converter::cleanup( $dir );

		if ( ! is_dir( $dir ) ) {
			++$removed;
		}
	}

	return $removed;
}
add_action( CPTDP_TEMP_SWEEP_HOOK, 'cptdp_sweep_temp_folders' );

/**
 * Remove the event when the plugin is deactivated.
 */
function cptdp_unschedule_temp_sweep(): void {
	wp_clear_scheduled_hook( CPTDP_TEMP_SWEEP_HOOK );
}
