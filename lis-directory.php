<?php
/**
 * Plugin Name:       LIS Directory
 * Plugin URI:        https://livinginsandpoint.com
 * Description:       Vendor Showcase + a self-hosted listings framework (replacing Directorist over time, same approach as LIS Events replacing EventON) for Living in Sandpoint.
 * Version:           0.30.5
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Lucca Grillo
 * License:           GPL v2 or later
 * Text Domain:       lis-directory
 *
 * LIS Directory — v0.30.5 (progress bar + section label ("GET STARTED" etc.) now stay pinned at the top of each panel; only the question/hint/field/controls block centers vertically below them)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_VERSION', '0.30.5' );
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
require_once LIS_DIRECTORY_PATH . 'includes/directorist-integration.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-cpt.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-meta.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-hours.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-badges.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-reviews.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-actions.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-template.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-submission.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-edit.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-search.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-account.php';
require_once LIS_DIRECTORY_PATH . 'includes/listings-pricing.php';

register_activation_hook( __FILE__, function () {
	lis_directory_register_cpt();
	lis_directory_register_taxonomy();
	lis_directory_register_listing_cpt();
	lis_directory_register_listing_taxonomy();
	lis_directory_register_listing_feature_taxonomy();
	lis_directory_register_flag_cpt();
	flush_rewrite_rules();
	if ( WP_DEBUG ) {
		error_log( '[LIS Directory] Activated v' . LIS_DIRECTORY_VERSION );
	}
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

/**
 * The `lis_listing` CPT/taxonomy are new in v0.12.0 on a site where this
 * plugin is already active (an update, not a fresh activation) — their
 * rewrite rules (`/listings/`, `/listing-category/...`) don't exist until
 * something flushes them, and activation only runs on fresh install/reactivate,
 * not on an in-place version update. Flush once per version bump instead.
 */
add_action( 'init', function () {
	if ( get_option( 'lis_directory_flushed_version' ) !== LIS_DIRECTORY_VERSION ) {
		flush_rewrite_rules();
		update_option( 'lis_directory_flushed_version', LIS_DIRECTORY_VERSION );
	}
}, 20 );

function lis_directory_get_version() {
	return LIS_DIRECTORY_VERSION;
}
