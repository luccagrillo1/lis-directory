<?php
/**
 * GitHub-based auto-update.
 *
 * Lets WordPress show "Update available" for this plugin straight from the
 * project's GitHub repo — one click in Plugins, same as a wordpress.org plugin.
 * Backed by YahnisElsts/plugin-update-checker (MIT), bundled in lib/. Same
 * setup as LIS Events — see that plugin's includes/updater.php for the
 * original.
 *
 * The repo is PRIVATE, so update checks must authenticate. The token is read
 * from a constant defined in wp-config.php and is NEVER stored in this file or
 * committed to the repo:
 *
 *     define( 'LIS_DIRECTORY_UPDATE_TOKEN', 'github_pat_...' );
 *
 * Use a fine-grained personal access token scoped to this one repo with
 * Contents: read-only. Without the constant the checker simply doesn't run —
 * the plugin works exactly as before, just with no update banner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change this if the repo ever moves. Owner/name only — no trailing slash issues,
 * the library normalises the URL.
 */
if ( ! defined( 'LIS_DIRECTORY_GITHUB_REPO' ) ) {
	define( 'LIS_DIRECTORY_GITHUB_REPO', 'https://github.com/luccagrillo1/lis-directory/' );
}

function lis_directory_init_updater() {
	$loader = LIS_DIRECTORY_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return; // Library missing (e.g. a stripped build) — fail quiet.
	}
	require_once $loader;

	$factory = 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
	if ( ! class_exists( $factory ) ) {
		return;
	}

	$checker = call_user_func(
		[ $factory, 'buildUpdateChecker' ],
		LIS_DIRECTORY_GITHUB_REPO,
		LIS_DIRECTORY_FILE,
		'lis-directory'
	);

	// Releases drive updates; fall back to the main branch if a repo has none yet.
	if ( method_exists( $checker, 'setBranch' ) ) {
		$checker->setBranch( 'main' );
	}

	// Private repo: authenticate, or don't check at all. A failed unauthenticated
	// check would just log 404s against the GitHub API on every admin load.
	if ( defined( 'LIS_DIRECTORY_UPDATE_TOKEN' ) && LIS_DIRECTORY_UPDATE_TOKEN ) {
		if ( method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( LIS_DIRECTORY_UPDATE_TOKEN );
		}
	}
}

/*
 * Run at plugin-load time, NOT from a hook. See LIS Events' updater.php for
 * why: the library registers its own callbacks on `plugins_loaded`, `init`
 * and `admin_init`, so building it later than plugin load means some of
 * those hooks have already fired and never get the registration.
 */
lis_directory_init_updater();
