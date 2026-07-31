<?php
/**
 * `lis_listing` — the general business-listing CPT that this plugin is
 * growing into a lean, self-hosted replacement for Directorist, the same
 * way LIS Events was built to replace EventON. Framework only for now: CPT +
 * taxonomy + basic admin fields + basic front-end templates. No migration of
 * Directorist's existing ~230 categories or listings yet — that's deliberate,
 * a separate later step, not an oversight.
 *
 * Kept entirely separate from `lis_preferred_vendor` / `lis_vendor_category`
 * (this plugin's original Preferred Vendor Program) — different CPT,
 * different taxonomy, different purpose. The Preferred Vendor Program is a
 * paid featured-slot overlay; `lis_listing` is the general directory itself
 * it will eventually sit on top of, replacing Directorist as that base layer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lis_directory_register_listing_cpt() {
	$labels = array(
		'name'                  => 'Listings',
		'singular_name'         => 'Listing',
		'menu_name'             => 'LIS Listings',
		'name_admin_bar'        => 'Listing',
		'add_new'               => 'Add New',
		'add_new_item'          => 'Add New Listing',
		'new_item'              => 'New Listing',
		'edit_item'             => 'Edit Listing',
		'view_item'             => 'View Listing',
		'all_items'             => 'All Listings',
		'search_items'          => 'Search Listings',
		'not_found'             => 'No listings found.',
		'not_found_in_trash'    => 'No listings found in Trash.',
		'featured_image'        => 'Listing Image',
		'set_featured_image'    => 'Set listing image',
		'remove_featured_image' => 'Remove listing image',
		'use_featured_image'    => 'Use as listing image',
	);

	register_post_type( 'lis_listing', array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'menu_position'      => 21,
		'menu_icon'          => 'dashicons-store',
		'has_archive'        => 'listings',
		'rewrite'            => array(
			'slug'       => 'listings',
			'with_front' => false,
		),
		'supports'           => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'excerpt' ),
		'capability_type'    => 'post',
		'hierarchical'       => false,
		'taxonomies'         => array( 'lis_listing_category', 'lis_listing_feature' ),
	) );
}
add_action( 'init', 'lis_directory_register_listing_cpt' );

/**
 * Non-hierarchical (tag-style) — "Air Conditioning", "Free WiFi", etc. Reuses
 * WordPress's own tag-input admin UI (comes free with a non-hierarchical
 * taxonomy) rather than a custom checkbox field.
 */
function lis_directory_register_listing_feature_taxonomy() {
	register_taxonomy( 'lis_listing_feature', array( 'lis_listing' ), array(
		'labels'            => array(
			'name'          => 'Features',
			'singular_name' => 'Feature',
			'menu_name'     => 'Features',
			'search_items'  => 'Search Features',
			'add_new_item'  => 'Add New Feature',
		),
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_admin_column' => false,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'listing-feature' ),
	) );
}
add_action( 'init', 'lis_directory_register_listing_feature_taxonomy' );

function lis_directory_register_listing_taxonomy() {
	$labels = array(
		'name'              => 'Listing Categories',
		'singular_name'     => 'Listing Category',
		'search_items'      => 'Search Categories',
		'all_items'         => 'All Categories',
		'parent_item'       => 'Parent Category',
		'parent_item_colon' => 'Parent Category:',
		'edit_item'         => 'Edit Category',
		'update_item'       => 'Update Category',
		'add_new_item'      => 'Add New Category',
		'new_item_name'     => 'New Category Name',
		'menu_name'         => 'Categories',
	);

	register_taxonomy( 'lis_listing_category', array( 'lis_listing' ), array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'listing-category' ),
	) );
}
add_action( 'init', 'lis_directory_register_listing_taxonomy' );
