<?php
/**
 * One-time migration: Directorist (`at_biz_dir`) listings → this plugin's
 * `lis_listing` posts, preserving owner, expiration, category, photos, hours,
 * contact fields, and featured status.
 *
 * Ships with a **dry-run/preview** mode so the exact field mapping can be
 * verified against real data before anything is written, and is idempotent
 * (each created listing records `_lis_migrated_from` = source post id, so a
 * re-run skips already-migrated listings rather than duplicating them).
 *
 * Nothing here runs automatically — it's driven by buttons on the LIS Directory
 * Settings page, admin-only, nonce-checked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_lis_directory_migration_run', 'lis_directory_migration_run_handler' );

/**
 * How many Directorist listings exist, and how many we've already migrated.
 */
function lis_directory_migration_stats() {
	if ( ! post_type_exists( 'at_biz_dir' ) ) {
		return array( 'exists' => false, 'total' => 0, 'migrated' => 0 );
	}
	$total = (int) count( get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) ) );
	$migrated = (int) count( get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_lis_migrated_from', 'compare' => 'EXISTS' ) ),
	) ) );
	return array( 'exists' => true, 'total' => $total, 'migrated' => $migrated );
}

/**
 * Read-only dump of a few real Directorist listings — every meta key/value,
 * author, status, and taxonomy — so the field mapping can be built and checked
 * against what's actually stored, not assumed.
 */
function lis_directory_migration_render_preview() {
	if ( ! post_type_exists( 'at_biz_dir' ) ) {
		echo '<p>Directorist (<code>at_biz_dir</code>) is not active on this site.</p>';
		return;
	}
	$listings = get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => 3,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
	if ( empty( $listings ) ) {
		echo '<p>No Directorist listings found.</p>';
		return;
	}

	// All taxonomies registered for at_biz_dir, so we can see the real category
	// taxonomy name(s) rather than guessing.
	$taxes = get_object_taxonomies( 'at_biz_dir' );
	echo '<p><strong>at_biz_dir taxonomies:</strong> <code>' . esc_html( implode( ', ', $taxes ) ) . '</code></p>';

	foreach ( $listings as $l ) {
		$author = get_userdata( $l->post_author );
		echo '<hr /><h4>#' . (int) $l->ID . ' — ' . esc_html( $l->post_title ) . '</h4>';
		echo '<p>author: ' . (int) $l->post_author . ' (' . esc_html( $author ? $author->user_login : '?' ) . ') &middot; status: ' . esc_html( $l->post_status ) . '</p>';
		foreach ( $taxes as $tax ) {
			$terms = wp_get_post_terms( $l->ID, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				echo '<p>' . esc_html( $tax ) . ': ' . esc_html( implode( ', ', $terms ) ) . '</p>';
			}
		}
		echo '<pre style="max-height:360px;overflow:auto;background:#f6f7f7;padding:10px;font-size:12px;">';
		foreach ( get_post_meta( $l->ID ) as $key => $vals ) {
			$val = maybe_unserialize( $vals[0] );
			if ( is_array( $val ) || is_object( $val ) ) {
				$val = wp_json_encode( $val );
			}
			echo esc_html( $key . ' = ' . mb_substr( (string) $val, 0, 240 ) ) . "\n";
		}
		echo '</pre>';
	}
}

function lis_directory_migration_run_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'lis_directory_migration_run', 'lis_dm_nonce' );

	// This handler is intentionally a stub for now — the preview/dump above is
	// used first to lock down the field mapping, then the real writer lands
	// here. Redirect back rather than doing anything destructive yet.
	wp_safe_redirect( add_query_arg( 'lis_dm', 'notready', admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' ) ) );
	exit;
}
