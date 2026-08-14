<?php
/**
 * Front-end template routing for `lis_listing` — same pattern as LIS Events'
 * includes/template.php: route to this plugin's own template, but let a
 * theme override win if it deliberately provides one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'single_template', 'lis_directory_listing_single_template' );
add_filter( 'archive_template', 'lis_directory_listing_archive_template' );
add_filter( 'taxonomy_template', 'lis_directory_listing_category_template' );

function lis_directory_listing_single_template( $template ) {
	if ( ! is_singular( 'lis_listing' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'single-lis_listing.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/single-listing.php';
}

function lis_directory_listing_archive_template( $template ) {
	if ( ! is_post_type_archive( 'lis_listing' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'archive-lis_listing.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/archive-listing.php';
}

function lis_directory_listing_category_template( $template ) {
	if ( ! is_tax( 'lis_listing_category' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'taxonomy-lis_listing_category.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/archive-listing.php';
}

/**
 * Jetpack Related Posts auto-appends an empty #jp-relatedposts placeholder to
 * the_content (populated client-side via its own JS/AJAX, not at render
 * time) — on the single listing template, that lands it right after the
 * Description block, ahead of Claim/Report and Reviews. There's no PHP-side
 * hook to relocate this cleanly: it's a single fixed-id element Jetpack's own
 * JS finds and fills after load, so a server-side second copy (e.g. via the
 * [jetpack-related-posts] shortcode) either renders nothing or risks a
 * duplicate id. assets/js/listing-related-posts.js moves the actual element
 * client-side instead — see that file.
 */
add_action( 'wp_enqueue_scripts', 'lis_directory_enqueue_related_posts_mover' );

function lis_directory_enqueue_related_posts_mover() {
	if ( ! is_singular( 'lis_listing' ) ) {
		return;
	}
	wp_enqueue_script( 'lis-directory-listing-related-posts', LIS_DIRECTORY_URL . 'assets/js/listing-related-posts.js', array(), LIS_DIRECTORY_VERSION, true );
}
