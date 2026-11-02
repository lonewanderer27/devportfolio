<?php
/**
 * PHPUnit bootstrap for the devportfolio theme.
 *
 * Loads the WordPress test library and the custom-post-types-dp plugin (the
 * navigation links to its post type archives). PHPUnit and the polyfills come
 * from the plugin's Composer install; the theme has no dependencies of its own.
 *
 * @package devportfolio
 */

$devportfolio_plugin_dir = dirname( __DIR__, 3 ) . '/plugins/custom-post-types-dp';
$devportfolio_tests_dir  = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

if ( ! file_exists( $devportfolio_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found at {$devportfolio_tests_dir}. Run the plugin's bin/install-wp-tests.sh first.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

if ( ! is_file( $devportfolio_plugin_dir . '/custom-post-types-dp.php' ) ) {
	fwrite( STDERR, "Plugin custom-post-types-dp not found at {$devportfolio_plugin_dir}.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

// The test library drops every table in its database. Never let it near the dev DB.
$devportfolio_config = (string) file_get_contents( $devportfolio_tests_dir . '/wp-tests-config.php' );
if ( ! preg_match( "/define\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $devportfolio_config, $devportfolio_match ) || 'db' === $devportfolio_match[1] ) {
	fwrite( STDERR, "Refusing to run: test DB_NAME must be set and must not be the dev database 'db'.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . $devportfolio_plugin_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $devportfolio_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $devportfolio_plugin_dir ): void {
		require $devportfolio_plugin_dir . '/custom-post-types-dp.php';
	}
);

// Never let a flush write .htaccess into the real project root.
tests_add_filter( 'flush_rewrite_rules_hard', '__return_false' );

require $devportfolio_tests_dir . '/includes/bootstrap.php';
