<?php
/**
 * Plugin Name:       Custom Post Types DP
 * Description:       Loads all portfolio modules when active: custom post types and site settings (e.g. Contact).
 * Version:           0.13.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Author:            Ian James Puzon
 * Author URI:        https://jay.thedev.id
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       custom-post-types-dp
 *
 * @package CustomPostTypesDP
 * @author  Ian James Puzon <adrianejames27@gmail.com>
 */

defined( 'ABSPATH' ) || exit;

define( 'CPTDP_VERSION', '0.13.0' );
define( 'CPTDP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPTDP_URL', plugin_dir_url( __FILE__ ) );

require_once CPTDP_PATH . 'includes/class-loader.php';

CPTDP_Loader::init();

/**
 * Fire the post type registration hook that modules attach to.
 */
function cptdp_register_post_types(): void {
	do_action( 'cptdp_register_post_types' );
}
add_action( 'init', 'cptdp_register_post_types' );

/**
 * Register post types, then flush rewrite rules.
 *
 * Modules load on plugins_loaded, which has not run yet during activation,
 * so load them here before registering.
 */
function cptdp_activate(): void {
	CPTDP_Loader::load_modules();
	cptdp_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cptdp_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
