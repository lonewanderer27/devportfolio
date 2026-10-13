<?php
/**
 * PHPUnit bootstrap: loads the WordPress test library and this plugin.
 *
 * @package CustomPostTypesDP
 */

$cptdp_plugin_dir = dirname( __DIR__ );
$cptdp_tests_dir  = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

if ( ! file_exists( $cptdp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found at {$cptdp_tests_dir}. Run bin/install-wp-tests.sh first.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

// The test library drops every table in its database. Never let it near the dev DB.
$cptdp_config = (string) file_get_contents( $cptdp_tests_dir . '/wp-tests-config.php' );
if ( ! preg_match( "/define\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $cptdp_config, $cptdp_match ) || 'db' === $cptdp_match[1] ) {
	fwrite( STDERR, "Refusing to run: test DB_NAME must be set and must not be the dev database 'db'.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

$cptdp_polyfills = $cptdp_plugin_dir . '/vendor/yoast/phpunit-polyfills';
if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . $cptdp_polyfills );
}

require_once $cptdp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $cptdp_plugin_dir ): void {
		require $cptdp_plugin_dir . '/custom-post-types-dp.php';
	}
);

// Never let a flush write .htaccess into the real project root.
tests_add_filter( 'flush_rewrite_rules_hard', '__return_false' );

require $cptdp_tests_dir . '/includes/bootstrap.php';

// Admin-only APIs the modules rely on (meta boxes, menus, settings, screens).
// Not misc.php: it holds save_mod_rewrite_rules().
foreach ( array( 'plugin', 'template', 'class-wp-screen', 'screen' ) as $cptdp_admin_file ) {
	require_once ABSPATH . 'wp-admin/includes/' . $cptdp_admin_file . '.php';
}

require_once __DIR__ . '/class-cptdp-test-case.php';
require_once __DIR__ . '/class-cptdp-cpt-test-case.php';
require_once __DIR__ . '/class-cptdp-media-cpt-test-case.php';
