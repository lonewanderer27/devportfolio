<?php
/**
 * Uninstall cleanup.
 *
 * @package CustomPostTypesDP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cptdp_contact' );
