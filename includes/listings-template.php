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
 * Jetpack Related Posts auto-appends itself to the_content — on the single
 * listing template, that lands it right after the Description block, ahead
 * of Claim/Report and Reviews. templates/single-listing.php wants it further
 * down the page instead, so it's placed manually via the [jetpack-related-posts]
 * shortcode (Jetpack's own documented way to do this); this just turns off
 * the automatic placement so it isn't shown twice. Scoped to lis_listing only
 * — blog posts elsewhere on the site keep Jetpack's default placement.
 */
add_action( 'wp', 'lis_directory_disable_auto_jetpack_related_posts' );

function lis_directory_disable_auto_jetpack_related_posts() {
	if ( ! is_singular( 'lis_listing' ) || ! class_exists( 'Jetpack_RelatedPosts' ) ) {
		return;
	}
	$related_posts = Jetpack_RelatedPosts::init();
	remove_filter( 'the_content', array( $related_posts, 'filter_add_target_to_dom' ) );
}
