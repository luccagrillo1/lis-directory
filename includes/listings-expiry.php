<?php
/**
 * Listing expiration for `lis_listing`.
 *
 * `_lis_listing_expiry` holds a MySQL datetime; a once-daily cron unpublishes
 * (sets to draft, flagged `_lis_listing_expired`) any published listing whose
 * expiry has passed. This mirrors Directorist's `_expiry_date` behaviour so the
 * migration can carry expirations straight over, and gives paid/one-off
 * listings a real term. A blank expiry (or `_lis_listing_never_expire` = 1)
 * means it never expires.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_listing_expiry_meta' );
add_action( 'init', 'lis_directory_schedule_expiry_check' );
add_action( 'lis_directory_daily_expiry_check', 'lis_directory_run_expiry_check' );

function lis_directory_register_listing_expiry_meta() {
	register_post_meta( 'lis_listing', '_lis_listing_expiry', array(
		'type'         => 'string',
		'single'       => true,
		'show_in_rest' => false,
	) );
	register_post_meta( 'lis_listing', '_lis_listing_never_expire', array(
		'type'         => 'boolean',
		'single'       => true,
		'show_in_rest' => false,
	) );
}

function lis_directory_schedule_expiry_check() {
	if ( ! wp_next_scheduled( 'lis_directory_daily_expiry_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lis_directory_daily_expiry_check' );
	}
}

/**
 * The stored expiry for a listing, or '' if none / never-expires.
 */
function lis_directory_get_listing_expiry( $listing_id ) {
	if ( get_post_meta( $listing_id, '_lis_listing_never_expire', true ) ) {
		return '';
	}
	return (string) get_post_meta( $listing_id, '_lis_listing_expiry', true );
}

/**
 * Whether a listing's term has already passed.
 */
function lis_directory_is_listing_expired( $listing_id ) {
	$expiry = lis_directory_get_listing_expiry( $listing_id );
	if ( '' === $expiry ) {
		return false;
	}
	return strtotime( $expiry ) < current_time( 'timestamp' );
}

/**
 * Daily sweep: any published listing past its expiry goes to draft and is
 * flagged expired (so the dashboard/admin can show "Expired" and it drops out
 * of public views). Renewing/republishing clears the flag on next publish.
 */
function lis_directory_run_expiry_check() {
	$due = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => lis_directory_expiry_due_meta_query(),
	) );

	foreach ( $due as $listing_id ) {
		wp_update_post( array( 'ID' => $listing_id, 'post_status' => 'draft' ) );
		update_post_meta( $listing_id, '_lis_listing_expired', 1 );
	}
	return count( $due );
}

/**
 * The sweep's selection: an expiry that has passed, not "never expires", and
 * not on an active Standard subscription.
 * Shared with the read-only "what would expire next" list on LIS Listings →
 * Real Estate (includes/real-estate.php).
 */
function lis_directory_expiry_due_meta_query() {
	return array(
		'relation' => 'AND',
		array(
			'key'     => '_lis_listing_expiry',
			'value'   => current_time( 'mysql' ),
			'compare' => '<',
			'type'    => 'DATETIME',
		),
		array(
			'relation' => 'OR',
			array( 'key' => '_lis_listing_never_expire', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_lis_listing_never_expire', 'value' => '1', 'compare' => '!=' ),
		),
		// Never unpublish a listing a Standard subscription is paying for — the
		// subscription decides when it ends. Backstop for listings that got a
		// subscription while still carrying an old one-time end date.
		array(
			'relation' => 'OR',
			array( 'key' => '_lis_listing_standard_active', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_lis_listing_standard_active', 'value' => '1', 'compare' => '!=' ),
		),
	);
}
