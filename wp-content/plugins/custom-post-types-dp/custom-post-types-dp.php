<?php
/**
 * Plugin Name:       Custom Post Types DP
 * Description:       Loads all portfolio modules when active.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Author:            Ian James Puzon
 * Author URI:        mailto:adrianejames27@gmail.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       custom-post-types-dp
 *
 * @package CustomPostTypesDP
 * @author  Ian James Puzon <adrianejames27@gmail.com>
 */

defined( 'ABSPATH' ) || exit;

define( 'CPTDP_VERSION', '0.1.0' );
define( 'CPTDP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPTDP_URL', plugin_dir_url( __FILE__ ) );

require_once CPTDP_PATH . 'includes/class-loader.php';

CPTDP_Loader::init();
