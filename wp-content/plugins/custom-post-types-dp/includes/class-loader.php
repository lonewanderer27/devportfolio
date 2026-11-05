<?php
/**
 * Module loader.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discovers and requires every module in includes/documents/, includes/post-types/,
 * includes/settings/ and includes/seeders/.
 */
class CPTDP_Loader {

	/**
	 * Hook the loader.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_modules' ) );
	}

	/**
	 * Require each module file. No modules = no-op.
	 *
	 * Seeder helpers load first (seeders call them), and the `wp cptdp seed`
	 * command is required only under WP-CLI.
	 */
	public static function load_modules(): void {
		require_once CPTDP_PATH . 'includes/cli/seeder-helpers.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once CPTDP_PATH . 'includes/cli/class-seed-command.php';
			require_once CPTDP_PATH . 'includes/cli/class-pages-command.php';
		}

		// Shared document services (page image store, converter, scheduler, REST) first.
		$documents  = glob( CPTDP_PATH . 'includes/documents/*.php' );
		$post_types = glob( CPTDP_PATH . 'includes/post-types/*.php' );
		$settings   = glob( CPTDP_PATH . 'includes/settings/*.php' );
		$seeders    = glob( CPTDP_PATH . 'includes/seeders/*.php' );
		$files      = array_merge(
			false === $documents ? array() : $documents,
			false === $post_types ? array() : $post_types,
			false === $settings ? array() : $settings,
			false === $seeders ? array() : $seeders
		);

		foreach ( $files as $file ) {
			if ( 'index.php' === basename( $file ) ) {
				continue;
			}
			require_once $file;
		}
	}
}
