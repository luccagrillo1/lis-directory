<?php
/**
 * Places [lis_preferred_vendor_card] on Directorist's own category archive
 * and single-listing pages, for categories that have been explicitly mapped
 * to a `lis_vendor_category` term.
 *
 * Directorist's own category tree (`at_biz_dir-category`, ~230 terms,
 * hierarchical — e.g. "Travel & Hospitality > Hotels & Lodging") has no slug
 * overlap with this plugin's flat `lis_vendor_category` terms (e.g.
 * "Lodging") — that's a deliberate decoupling decision (see readme.txt), not
 * an oversight, so there's nothing to auto-match. A vendor category only
 * shows up on Directorist's pages once an admin explicitly maps it to a
 * Directorist category below.
 *
 * Hooks used (confirmed against Directorist 8.9.2 source — do_action() call
 * sites in templates/archive/*.php and templates/single/header-parts/
 * listing-title.php; none of these pass useful args except the single-page
 * one, which passes only the listing ID):
 * - directorist_before_grid_listings_loop
 * - directorist_before_list_listings_loop
 * - directorist_before_map_listings_loop
 * - directorist_single_listing_after_title
 * Only the "before" hooks are used — Directorist's list-view template fires
 * `directorist_after_grid_listings_loop` instead of a (non-existent)
 * "after_list" action, a copy-paste bug in Directorist itself, not
 * something to route around here since a "before" placement doesn't need it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_directorist_map_meta' );
add_action( 'lis_vendor_category_add_form_fields', 'lis_directory_render_directorist_map_field_add' );
add_action( 'lis_vendor_category_edit_form_fields', 'lis_directory_render_directorist_map_field_edit' );
add_action( 'created_lis_vendor_category', 'lis_directory_save_directorist_map_field' );
add_action( 'edited_lis_vendor_category', 'lis_directory_save_directorist_map_field' );

add_action( 'directorist_before_grid_listings_loop', 'lis_directory_render_card_on_directorist_archive' );
add_action( 'directorist_before_list_listings_loop', 'lis_directory_render_card_on_directorist_archive' );
add_action( 'directorist_before_map_listings_loop', 'lis_directory_render_card_on_directorist_archive' );
add_action( 'directorist_single_listing_after_title', 'lis_directory_render_card_on_directorist_single' );

function lis_directory_register_directorist_map_meta() {
	register_term_meta( 'lis_vendor_category', '_lis_pv_directorist_category_id', array(
		'type'          => 'integer',
		'single'        => true,
		'show_in_rest'  => false,
		'auth_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
	) );
}

function lis_directory_render_directorist_map_field_add() {
	if ( ! taxonomy_exists( 'at_biz_dir-category' ) ) {
		return; // Directorist inactive/not installed — nothing to map to.
	}
	?>
	<div class="form-field">
		<label for="lis_pv_directorist_category_id">Directorist Category</label>
		<?php
		wp_dropdown_categories( array(
			'taxonomy'         => 'at_biz_dir-category',
			'name'             => 'lis_pv_directorist_category_id',
			'id'               => 'lis_pv_directorist_category_id',
			'show_option_none' => '— Not shown on any Directorist page —',
			'hide_empty'       => false,
			'hierarchical'     => true,
		) );
		?>
		<p>If set, this vendor's card automatically appears at the top of that Directorist category's listing pages (archive and single-listing pages within it) once it has an active vendor.</p>
	</div>
	<?php
}

function lis_directory_render_directorist_map_field_edit( $term ) {
	if ( ! taxonomy_exists( 'at_biz_dir-category' ) ) {
		return;
	}
	$current = (int) get_term_meta( $term->term_id, '_lis_pv_directorist_category_id', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="lis_pv_directorist_category_id">Directorist Category</label></th>
		<td>
			<?php
			wp_dropdown_categories( array(
				'taxonomy'         => 'at_biz_dir-category',
				'name'             => 'lis_pv_directorist_category_id',
				'id'               => 'lis_pv_directorist_category_id',
				'selected'         => $current,
				'show_option_none' => '— Not shown on any Directorist page —',
				'hide_empty'       => false,
				'hierarchical'     => true,
			) );
			?>
			<p class="description">If set, this vendor's card automatically appears at the top of that Directorist category's listing pages (archive and single-listing pages within it) once it has an active vendor.</p>
		</td>
	</tr>
	<?php
}

function lis_directory_save_directorist_map_field( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( ! isset( $_POST['lis_pv_directorist_category_id'] ) ) {
		return;
	}
	$directorist_term_id = absint( $_POST['lis_pv_directorist_category_id'] );
	if ( $directorist_term_id ) {
		update_term_meta( $term_id, '_lis_pv_directorist_category_id', $directorist_term_id );
	} else {
		delete_term_meta( $term_id, '_lis_pv_directorist_category_id' );
	}
}

/**
 * Reverse lookup: given a Directorist category term ID, find the
 * `lis_vendor_category` term mapped to it, if any. Small term counts on both
 * sides make a loop simpler and just as fast as a meta_query here.
 */
function lis_directory_get_vendor_category_for_directorist_term( $directorist_term_id ) {
	if ( ! $directorist_term_id ) {
		return null;
	}
	$vendor_terms = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );
	if ( is_wp_error( $vendor_terms ) ) {
		return null;
	}
	foreach ( $vendor_terms as $vendor_term ) {
		$mapped_id = (int) get_term_meta( $vendor_term->term_id, '_lis_pv_directorist_category_id', true );
		if ( $mapped_id === (int) $directorist_term_id ) {
			return $vendor_term;
		}
	}
	return null;
}

/**
 * Determines the Directorist category being viewed on an archive page.
 * Mirrors Directorist's own resolution order (class-shortcode.php
 * category_archive()) so this works both on a real `/at_biz_dir-category/
 * {slug}/` taxonomy archive AND on a page rendering the archive via
 * shortcode with a `?category=` query arg — get_queried_object() alone only
 * covers the first case.
 */
function lis_directory_get_current_directorist_category_term() {
	if ( is_tax( 'at_biz_dir-category' ) ) {
		$queried = get_queried_object();
		return ( $queried instanceof WP_Term ) ? $queried : null;
	}

	$slug = '';
	if ( ! empty( $_GET['category'] ) ) { // phpcs:ignore -- read-only, sanitized below.
		$slug = sanitize_text_field( wp_unslash( $_GET['category'] ) ); // phpcs:ignore
	} elseif ( get_query_var( 'atbdp_category' ) ) {
		$slug = sanitize_text_field( urldecode( get_query_var( 'atbdp_category' ) ) );
	}
	if ( '' === $slug ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, 'at_biz_dir-category' );
	return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

function lis_directory_render_card_on_directorist_archive() {
	$directorist_term = lis_directory_get_current_directorist_category_term();
	if ( ! $directorist_term ) {
		return;
	}
	$vendor_term = lis_directory_get_vendor_category_for_directorist_term( $directorist_term->term_id );
	if ( ! $vendor_term ) {
		return;
	}
	echo do_shortcode( '[lis_preferred_vendor_card category="' . esc_attr( $vendor_term->slug ) . '"]' ); // phpcs:ignore -- shortcode output already escapes internally.
}

function lis_directory_render_card_on_directorist_single( $listing_id ) {
	$directorist_terms = get_the_terms( $listing_id, 'at_biz_dir-category' );
	if ( empty( $directorist_terms ) || is_wp_error( $directorist_terms ) ) {
		return;
	}
	foreach ( $directorist_terms as $directorist_term ) {
		$vendor_term = lis_directory_get_vendor_category_for_directorist_term( $directorist_term->term_id );
		if ( $vendor_term ) {
			echo do_shortcode( '[lis_preferred_vendor_card category="' . esc_attr( $vendor_term->slug ) . '"]' ); // phpcs:ignore -- shortcode output already escapes internally.
			return; // One card per listing even if it has multiple mapped categories.
		}
	}
}
