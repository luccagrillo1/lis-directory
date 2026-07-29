<?php
/**
 * Custom post type + taxonomy registration for the Preferred Vendor Program.
 *
 * Deliberately its own CPT/taxonomy, not a hook into Directorist's `at_biz_dir` —
 * keeps this decoupled from Directorist's internal slugs and hooks (see build brief).
 * The category taxonomy is mirrored/independent by design: it stays resilient if
 * Directorist is ever removed or its own taxonomy changes, at the cost of needing
 * manual upkeep to stay in sync with Directorist's categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_cpt' );
add_action( 'init', 'lis_directory_register_taxonomy' );

function lis_directory_register_cpt() {
	register_post_type( 'lis_preferred_vendor', array(
		'labels'       => array(
			'name'               => 'Preferred Vendors',
			'singular_name'      => 'Preferred Vendor',
			'add_new_item'       => 'Add New Preferred Vendor',
			'edit_item'          => 'Edit Preferred Vendor',
			'new_item'           => 'New Preferred Vendor',
			'view_item'          => 'View Preferred Vendor',
			'search_items'       => 'Search Preferred Vendors',
			'not_found'          => 'No preferred vendors found',
			'not_found_in_trash' => 'No preferred vendors found in Trash',
			'all_items'          => 'Preferred Vendors',
			'menu_name'          => 'Preferred Vendors',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-star-filled',
		'supports'     => array( 'title' ),
		'has_archive'  => false,
		'rewrite'      => false,
		'show_in_rest' => false,
		'capability_type' => 'post',
	) );
}

function lis_directory_register_taxonomy() {
	register_taxonomy( 'lis_vendor_category', 'lis_preferred_vendor', array(
		'labels'            => array(
			'name'          => 'Vendor Categories',
			'singular_name' => 'Vendor Category',
			'search_items'  => 'Search Vendor Categories',
			'all_items'     => 'All Vendor Categories',
			'edit_item'     => 'Edit Vendor Category',
			'update_item'   => 'Update Vendor Category',
			'add_new_item'  => 'Add New Vendor Category',
			'new_item_name' => 'New Vendor Category Name',
			'menu_name'     => 'Vendor Categories',
		),
		'hierarchical'      => true,
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => false,
		'rewrite'           => false,
	) );
}
