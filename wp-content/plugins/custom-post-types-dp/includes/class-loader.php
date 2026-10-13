<?php
/**
 * Module loader.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discovers and requires every module in includes/post-types/.
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
	 */
	public static function load_modules(): void {
		$post_types = glob( CPTDP_PATH . 'includes/post-types/*.php' );
		$settings   = glob( CPTDP_PATH . 'includes/settings/*.php' );
		$files      = array_merge(
			false === $post_types ? array() : $post_types,
			false === $settings ? array() : $settings
		);

		foreach ( $files as $file ) {
			if ( 'index.php' === basename( $file ) ) {
				continue;
			}
			require_once $file;
		}
	}
}
