<?php
/**
 * Site audit 2026-11-23 fixes (v0.73.0).
 *
 * - Thin category pages out of search (item 6): a listing category with fewer
 *   than 3 published listings (its own + child categories) gets SEOPress's
 *   noindex term meta, which also drops it from /sitemaps.xml. Marked with
 *   _lis_auto_noindex so only our own flags are ever cleared again; a noindex
 *   set by hand in SEOPress is left alone. Threshold: filter
 *   lis_directory_thin_category_min (0 turns it off). Synced daily, right after
 *   each version change, and a minute after any listing is published/unpublished.
 * - Header Sign In link (item 10): Astra gives it aria-label "Account icon link",
 *   so screen readers announce that instead of the visible "Sign in". An empty
 *   aria-label is ignored, so the link's own text becomes its name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Thin category pages ---------- */

function lis_directory_thin_category_min() {
	return max( 0, (int) apply_filters( 'lis_directory_thin_category_min', 3 ) );
}

/** Published listings in a category, child categories included. */
function lis_directory_category_listing_count( $term_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => array( array( 'taxonomy' => 'lis_listing_category', 'field' => 'term_id', 'terms' => (int) $term_id, 'include_children' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	return (int) $q->found_posts;
}

function lis_directory_sync_thin_categories() {
	$min   = lis_directory_thin_category_min();
	$terms = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false ) );
	$out   = array( 'noindexed' => 0, 'cleared' => 0, 'thin' => 0 );
	if ( is_wp_error( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $term ) {
		$thin = $min && lis_directory_category_listing_count( $term->term_id ) < $min;
		$cur  = (string) get_term_meta( $term->term_id, '_seopress_robots_index', true );
		$auto = (bool) get_term_meta( $term->term_id, '_lis_auto_noindex', true );
		$out['thin'] += $thin ? 1 : 0;
		if ( $thin && 'yes' !== $cur ) {
			update_term_meta( $term->term_id, '_seopress_robots_index', 'yes' );
			update_term_meta( $term->term_id, '_lis_auto_noindex', 1 );
			$out['noindexed']++;
		} elseif ( ! $thin && $auto ) {
			delete_term_meta( $term->term_id, '_seopress_robots_index' );
			delete_term_meta( $term->term_id, '_lis_auto_noindex' );
			$out['cleared']++;
		}
	}
	$out['time']    = time();
	$out['version'] = LIS_DIRECTORY_VERSION;
	update_option( 'lis_directory_thin_categories_synced', $out, false );
	return $out;
}
add_action( 'lis_directory_thin_categories_sync', 'lis_directory_sync_thin_categories' );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'lis_directory_thin_categories_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lis_directory_thin_categories_daily' );
	}
	$synced = get_option( 'lis_directory_thin_categories_synced' );
	if ( ( ! is_array( $synced ) || ( $synced['version'] ?? '' ) !== LIS_DIRECTORY_VERSION ) && ! wp_next_scheduled( 'lis_directory_thin_categories_sync' ) ) {
		wp_schedule_single_event( time(), 'lis_directory_thin_categories_sync' );
	}
} );
add_action( 'lis_directory_thin_categories_daily', 'lis_directory_sync_thin_categories' );

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $post && 'lis_listing' === $post->post_type && ( 'publish' === $new || 'publish' === $old ) && $new !== $old
		&& ! wp_next_scheduled( 'lis_directory_thin_categories_sync' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'lis_directory_thin_categories_sync' );
	}
}, 10, 3 );

/* ---------- Header Sign In link name ---------- */

add_filter( 'gettext_astra', function ( $translation, $text ) {
	return 'Account icon link' === $text ? '' : $translation;
}, 10, 2 );
