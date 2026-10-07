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
	if ( function_exists( 'lis_directory_is_real_estate_listing' ) && lis_directory_is_real_estate_listing( get_queried_object_id() ) ) {
		$theme_template = locate_template( array( 'single-lis_listing-real-estate.php' ) );
		return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/single-real-estate.php';
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

/**
 * D22: alt text for any listing image this plugin outputs. Uses the
 * attachment's own alt when someone set one, otherwise the listing title, so
 * no listing image ever goes out with alt="".
 */
function lis_directory_listing_image_alt( $attachment_id, $post ) {
	$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	return '' !== $alt ? $alt : get_the_title( $post );
}

/**
 * B25: branded stand-in for a listing with no photo. Inline SVG (the LIS
 * logomark) on a token-driven fill, so it themes with everything else and
 * needs no image request. role="img" + the listing title stands in for alt.
 * $context is "card" or "single" (CSS sizes it per context).
 */
function lis_directory_render_listing_fallback_image( $post, $context = 'card' ) {
	return sprintf(
		'<div class="lis-listing-fallback-img lis-listing-fallback-img--%1$s" role="img" aria-label="%2$s"><svg class="lis-listing-fallback-mark" viewBox="0 0 2000 2000" aria-hidden="true" focusable="false"><path fill="currentColor" d="M362.99,330.8h241.4s-317.36,794.95-318.77,798.21c-1.41,3.26-33.9,73.68-33.9,144.53,0,106.49,68.89,170.93,178.11,170.93,193.97,0,344.15-157.68,573.76-157.68s248.52,152.35,419.45,152.35c90.74,0,162.46-67.26,162.46-145.18,0-193.21-402.94-252.76-402.94-574.41,0-194.3,174.08-388.76,463.79-388.76,175.17,0,327.74,71.23,327.74,71.23l-81.5,209.29s-128.88-55.2-247.33-55.2c-127.79,0-237.55,63.9-237.55,170.39,0,164.74,402.07,228.64,402.07,573.33,0,241.24-217.77,363.17-394.24,363.17-232.98,0-279.6-151.92-412.28-151.92-156.15,0-326.33,158.11-572.78,158.11-217.77,0-404.57-148.98-404.57-396.74,0-124.53,68.46-272.54,84.11-311.01,15.65-38.47,252.98-630.65,252.98-630.65ZM451.67,1344.61c80.52-3.59,252.32-88.02,281.66-99.54l251.18-628.04h-241.08l-291.77,727.58ZM858.22,330.8l-76.8,191.52h240.98l76.6-191.52h-240.77Z"/></svg></div>',
		esc_attr( $context ),
		esc_attr( get_the_title( $post ) )
	);
}

/**
 * D19 + D22 on Jetpack Related Posts (the "Related" block under a listing).
 *
 * Jetpack prints its "Related" headline server-side and fills the items in
 * later over AJAX, so a listing with nothing related still shipped an empty
 * "Related" heading in its HTML (hidden on screen, but there for crawlers and
 * screen readers). On listings the headline is dropped here and
 * assets/js/listing-related-posts.js adds it back only once items arrive.
 * Related thumbnails of other listings also get the title as alt when the
 * image has none.
 */
add_filter( 'jetpack_relatedposts_filter_headline', 'lis_directory_related_posts_headline' );
add_filter( 'jetpack_relatedposts_returned_results', 'lis_directory_related_posts_alt', 10, 2 );

function lis_directory_related_posts_headline( $headline ) {
	return is_singular( 'lis_listing' ) ? '' : $headline;
}

function lis_directory_related_posts_alt( $results, $post_id ) {
	if ( 'lis_listing' !== get_post_type( $post_id ) || ! is_array( $results ) ) {
		return $results;
	}
	foreach ( $results as $i => $result ) {
		if ( isset( $result['img'] ) && is_array( $result['img'] ) && empty( $result['img']['alt_text'] ) && ! empty( $result['title'] ) ) {
			$results[ $i ]['img']['alt_text'] = wp_strip_all_tags( $result['title'] );
		}
	}
	return $results;
}

/**
 * B13: assets/css/directory-skin.css — the directory rules that used to sit in
 * the site's inline Page Skin snippets. Printed as a <link> from wp_head just
 * before those snippets print, so the moved rules keep their old place in the
 * cascade (after the theme and WooCommerce, before the rest of the skin).
 */
define( 'LIS_DIRECTORY_SKIN_HEAD_PRIORITY', 99 );

add_action( 'wp_head', 'lis_directory_print_directory_skin', LIS_DIRECTORY_SKIN_HEAD_PRIORITY );

function lis_directory_print_directory_skin() {
	if ( ! lis_directory_is_directory_request() ) {
		return;
	}
	wp_register_style( 'lis-directory-skin', LIS_DIRECTORY_URL . 'assets/css/directory-skin.css', array(), LIS_DIRECTORY_VERSION );
	wp_print_styles( 'lis-directory-skin' );
}

/**
 * True on any page this plugin renders listings on: single/archive/term
 * templates, or a page whose content carries one of its listing shortcodes.
 */
function lis_directory_is_directory_request() {
	if ( is_singular( 'lis_listing' ) || is_post_type_archive( 'lis_listing' ) || is_tax( array( 'lis_listing_category', 'lis_listing_feature' ) ) ) {
		return true;
	}
	if ( wp_style_is( 'lis-directory-listings', 'enqueued' ) ) {
		return true;
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && preg_match( '/\[lis_(listing_(grid|search|author_profile|dashboard)|real_estate)\b/', $post->post_content ) ) {
			return true;
		}
	}
	return false;
}
