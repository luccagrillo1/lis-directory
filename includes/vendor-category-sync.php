<?php
/**
 * Keep the Vendor Showcase categories (`lis_vendor_category`) mirrored to the
 * directory's listing categories (`lis_listing_category`), matched by slug, so
 * the showcase "category slot" is always the same set the listings use and
 * updates automatically when a listing category is added, renamed, or removed.
 *
 * The showcase machinery (product variations, stock sync, one-per-category
 * lock) still runs on `lis_vendor_category`; this just guarantees those terms
 * always equal the listing categories rather than being a hand-kept second set.
 * When a new vendor category appears, the Showcase product is topped up so it
 * gets its Monthly/Annually variations without a manual re-generate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The category-mirroring approach (a lis_vendor_category per listing category)
// was retired: it created hundreds of exclusive slots + product variations and
// bogged wp-admin down. The Showcase now uses the listing's own category
// directly (single product; see the model rework). The live mirror hooks are
// intentionally NOT registered anymore; this file keeps the cleanup tool that
// removes the stray auto-created categories + the bloated product.
add_action( 'admin_post_lis_directory_showcase_cleanup', 'lis_directory_handle_showcase_cleanup' );

/**
 * One-time (per install) reconcile so existing listing categories get mirrored
 * without a manual trigger. New categories after this are handled live by the
 * created/edited hooks.
 */
function lis_directory_maybe_reconcile_vendor_categories() {
	if ( '1' === get_option( 'lis_directory_vendor_cat_synced' ) ) {
		return;
	}
	// Create all mirrored categories first WITHOUT the per-category product
	// top-up (which would re-run the whole generator once per category), then
	// top the Showcase product up a single time at the end.
	remove_action( 'created_lis_vendor_category', 'lis_directory_topup_showcase_on_new_category' );
	lis_directory_reconcile_vendor_categories();
	add_action( 'created_lis_vendor_category', 'lis_directory_topup_showcase_on_new_category' );

	if ( function_exists( 'lis_directory_get_vendor_showcase_product_id' )
		&& lis_directory_get_vendor_showcase_product_id()
		&& function_exists( 'lis_directory_generate_vendor_showcase_product' ) ) {
		lis_directory_generate_vendor_showcase_product();
	}
	update_option( 'lis_directory_vendor_cat_synced', '1' );
}

/**
 * Ensure a matching lis_vendor_category exists for one listing category, with
 * the same name + slug. Returns the vendor term id (0 on failure).
 */
function lis_directory_mirror_listing_category( $listing_term_id ) {
	$src = get_term( (int) $listing_term_id, 'lis_listing_category' );
	if ( ! $src || is_wp_error( $src ) ) {
		return 0;
	}

	$existing = get_term_by( 'slug', $src->slug, 'lis_vendor_category' );
	if ( $existing && ! is_wp_error( $existing ) ) {
		// Keep the name in sync (a rename on the listing side).
		if ( $existing->name !== $src->name ) {
			wp_update_term( $existing->term_id, 'lis_vendor_category', array( 'name' => $src->name ) );
		}
		return (int) $existing->term_id;
	}

	$created = wp_insert_term( $src->name, 'lis_vendor_category', array( 'slug' => $src->slug ) );
	if ( is_wp_error( $created ) ) {
		return 0;
	}
	return (int) $created['term_id'];
}

/**
 * When a listing category is deleted, remove its mirrored vendor category too —
 * but only if nothing depends on it (no vendor assigned), so an occupied slot
 * is never silently pulled out from under a paying showcase vendor.
 */
function lis_directory_mirror_delete_listing_category( $term, $tt_id, $deleted_term, $object_ids ) {
	if ( ! isset( $deleted_term->slug ) ) {
		return;
	}
	$vendor = get_term_by( 'slug', $deleted_term->slug, 'lis_vendor_category' );
	if ( ! $vendor || is_wp_error( $vendor ) ) {
		return;
	}
	if ( (int) $vendor->count > 0 ) {
		return; // A vendor still uses this slot; leave it.
	}
	wp_delete_term( $vendor->term_id, 'lis_vendor_category' );
}

/**
 * Full reconcile: make sure every listing category has a mirrored vendor
 * category. Safe to run repeatedly; only fills gaps. (Does not delete orphan
 * vendor categories — deletions go through the delete hook, which guards
 * occupied slots.)
 */
function lis_directory_reconcile_vendor_categories() {
	$listing_terms = get_terms( array(
		'taxonomy'   => 'lis_listing_category',
		'hide_empty' => false,
	) );
	if ( is_wp_error( $listing_terms ) ) {
		return;
	}
	foreach ( $listing_terms as $term ) {
		lis_directory_mirror_listing_category( $term->term_id );
	}
}

/**
 * A new vendor category (usually just mirrored from a listing category) needs
 * its Monthly/Annually Showcase variations. Top up the existing product so the
 * new slot is immediately buyable, without a manual re-generate.
 */
function lis_directory_topup_showcase_on_new_category( $vendor_term_id ) {
	if ( function_exists( 'lis_directory_get_vendor_showcase_product_id' )
		&& lis_directory_get_vendor_showcase_product_id()
		&& function_exists( 'lis_directory_generate_vendor_showcase_product' ) ) {
		lis_directory_generate_vendor_showcase_product();
	}
}

/**
 * Whether a listing category's Showcase slot is currently occupied (its mirrored
 * vendor category has an active vendor). Used to warn in the plan step.
 */
function lis_directory_is_listing_category_showcase_taken( $listing_term_id ) {
	$src = get_term( (int) $listing_term_id, 'lis_listing_category' );
	if ( ! $src || is_wp_error( $src ) ) {
		return false;
	}
	$vendor = get_term_by( 'slug', $src->slug, 'lis_vendor_category' );
	if ( ! $vendor || is_wp_error( $vendor ) ) {
		return false;
	}
	return (bool) lis_directory_get_active_vendor_for_category( $vendor->term_id );
}

/**
 * The mirrored vendor-category term id for a listing category (mirroring it
 * first if needed), or 0.
 */
function lis_directory_vendor_category_for_listing_category( $listing_term_id ) {
	return lis_directory_mirror_listing_category( $listing_term_id );
}

/* ------------------------------------------------------------------ *
 * Cleanup: undo the retired category-mirror — delete the stray empty  *
 * vendor categories and trash the bloated per-category Showcase        *
 * product, so the model can move to a single Showcase product.         *
 * ------------------------------------------------------------------ */

/**
 * @return array { deleted_categories:int, kept_categories:int, trashed_product:int }
 */
function lis_directory_showcase_cleanup() {
	$deleted = 0;
	$kept    = 0;
	$terms   = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			// Only remove empty ones (no vendor assigned), so an active showcase
			// vendor never loses its category.
			if ( 0 === (int) $term->count ) {
				wp_delete_term( $term->term_id, 'lis_vendor_category' );
				$deleted++;
			} else {
				$kept++;
			}
		}
	}

	// Trash the bloated per-category Showcase product; the single-product model
	// creates a fresh one. Also clear the sync flag.
	$trashed = 0;
	if ( function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ) {
		$pid = lis_directory_get_vendor_showcase_product_id();
		if ( $pid ) {
			wp_trash_post( $pid );
			$trashed = (int) $pid;
		}
	}
	delete_option( 'lis_directory_vendor_cat_synced' );

	return array( 'deleted_categories' => $deleted, 'kept_categories' => $kept, 'trashed_product' => $trashed );
}

function lis_directory_handle_showcase_cleanup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'lis_directory_showcase_cleanup', 'lis_sc_nonce' );
	$r        = lis_directory_showcase_cleanup();
	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );
	$redirect = add_query_arg( array( 'lis_sc' => 'done', 'lis_sc_d' => (int) $r['deleted_categories'], 'lis_sc_k' => (int) $r['kept_categories'] ), $redirect );
	wp_safe_redirect( $redirect );
	exit;
}
