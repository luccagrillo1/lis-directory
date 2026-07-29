<?php
/**
 * Plugin Name:       LIS Directory
 * Plugin URI:        https://livinginsandpoint.com
 * Description:       Preferred Vendor Program for Living in Sandpoint. Runs alongside Directorist without touching it.
 * Version:           0.9.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Lucca Grillo
 * License:           GPL v2 or later
 * Text Domain:       lis-directory
 *
 * LIS Directory — v0.9.0 (GitHub-based auto-update, same as LIS Events)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_VERSION', '0.9.0' );
define( 'LIS_DIRECTORY_PATH', plugin_dir_path( __FILE__ ) );
define( 'LIS_DIRECTORY_URL', plugin_dir_url( __FILE__ ) );
define( 'LIS_DIRECTORY_FILE', __FILE__ );

require_once LIS_DIRECTORY_PATH . 'includes/cpt.php';
require_once LIS_DIRECTORY_PATH . 'includes/enforcement.php';
require_once LIS_DIRECTORY_PATH . 'includes/meta.php';
require_once LIS_DIRECTORY_PATH . 'includes/admin.php';
require_once LIS_DIRECTORY_PATH . 'includes/shortcodes.php';
require_once LIS_DIRECTORY_PATH . 'includes/submission.php';
require_once LIS_DIRECTORY_PATH . 'includes/woocommerce.php';
require_once LIS_DIRECTORY_PATH . 'includes/updater.php';

register_activation_hook( __FILE__, function () {
	lis_directory_register_cpt();
	lis_directory_register_taxonomy();
	flush_rewrite_rules();
	if ( WP_DEBUG ) {
		error_log( '[LIS Directory] Activated v' . LIS_DIRECTORY_VERSION );
	}
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

function lis_directory_get_version() {
	return LIS_DIRECTORY_VERSION;
}
