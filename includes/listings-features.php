<?php
/**
 * Custom ("suggest a feature") support for the lis_listing_feature taxonomy.
 *
 * The submission/edit forms let people propose a feature that isn't in the
 * checkbox list yet. A proposed term is created immediately (so it can be
 * attached to their listing) but flagged pending via the `_lis_feature_pending`
 * term meta. Pending terms are kept out of the public checkbox lists and out
 * of the public single-listing display until an admin approves them — so one
 * person's suggestion doesn't silently become a site-wide, publicly shown
 * feature before anyone vetos it. Admin approves (or deletes = vetos) from the
 * normal Features taxonomy screen under LIS Listings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LIS_DIRECTORY_FEATURE_PENDING_META = '_lis_feature_pending';
const LIS_DIRECTORY_FEATURE_SUGGESTED_BY_META = '_lis_feature_suggested_by';

/**
 * Feature terms safe to show publicly — everything except pending suggestions.
 * Shared by the submission form, the edit form, and the search widget so the
 * checkbox lists stay consistent.
 */
function lis_directory_get_public_feature_terms() {
	$terms = get_terms( array(
		'taxonomy'   => 'lis_listing_feature',
		'hide_empty' => false,
		'meta_query' => array(
			array(
				'key'     => LIS_DIRECTORY_FEATURE_PENDING_META,
				'compare' => 'NOT EXISTS',
			),
		),
	) );
	return ( is_array( $terms ) ) ? $terms : array();
}

/**
 * Whether a given feature term is still awaiting admin approval.
 */
function lis_directory_is_feature_pending( $term_id ) {
	return '' !== (string) get_term_meta( (int) $term_id, LIS_DIRECTORY_FEATURE_PENDING_META, true );
}

/**
 * Strip pending suggestions out of an already-fetched WP_Term array, for the
 * public single-listing display (which reads the listing's own terms directly).
 */
function lis_directory_filter_public_features( $terms ) {
	if ( empty( $terms ) || ! is_array( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, function ( $term ) {
		return ! lis_directory_is_feature_pending( $term->term_id );
	} ) );
}

/**
 * Turn a raw "suggest a feature" text field into feature term IDs, creating
 * any genuinely new term as pending. Returns the term IDs to attach. Reused by
 * both the submit and edit save paths. Caps how many can be added at once and
 * how long each name may be, and reuses an existing term (case-insensitive)
 * rather than creating a near-duplicate.
 */
function lis_directory_ingest_suggested_features( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return array();
	}

	$names   = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	$names   = array_slice( $names, 0, 3 ); // At most 3 suggestions per submission.
	$user_id = get_current_user_id();
	$ids     = array();

	foreach ( $names as $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name || mb_strlen( $name ) > 40 ) {
			continue;
		}

		$existing = get_term_by( 'name', $name, 'lis_listing_feature' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$ids[] = (int) $existing->term_id;
			continue;
		}

		$created = wp_insert_term( $name, 'lis_listing_feature' );
		if ( is_wp_error( $created ) ) {
			continue;
		}
		update_term_meta( $created['term_id'], LIS_DIRECTORY_FEATURE_PENDING_META, '1' );
		if ( $user_id ) {
			update_term_meta( $created['term_id'], LIS_DIRECTORY_FEATURE_SUGGESTED_BY_META, $user_id );
		}
		$ids[] = (int) $created['term_id'];
	}

	return $ids;
}

/* ------------------------------------------------------------------ *
 * Admin: approve / veto pending feature suggestions on the taxonomy   *
 * list screen (Listings → Features).                                  *
 * ------------------------------------------------------------------ */

add_filter( 'manage_edit-lis_listing_feature_columns', 'lis_directory_feature_admin_columns' );
add_filter( 'manage_lis_listing_feature_custom_column', 'lis_directory_feature_admin_column_content', 10, 3 );
add_filter( 'lis_listing_feature_row_actions', 'lis_directory_feature_row_actions', 10, 2 );
add_action( 'admin_post_lis_directory_approve_feature', 'lis_directory_handle_approve_feature' );

function lis_directory_feature_admin_columns( $columns ) {
	// Insert a Status column right after the name.
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'name' === $key ) {
			$out['lis_feature_status'] = 'Status';
		}
	}
	if ( ! isset( $out['lis_feature_status'] ) ) {
		$out['lis_feature_status'] = 'Status';
	}
	return $out;
}

function lis_directory_feature_admin_column_content( $content, $column_name, $term_id ) {
	if ( 'lis_feature_status' !== $column_name ) {
		return $content;
	}
	if ( ! lis_directory_is_feature_pending( $term_id ) ) {
		return '<span style="color:#1a7f37;">● Live</span>';
	}
	$by   = (int) get_term_meta( $term_id, LIS_DIRECTORY_FEATURE_SUGGESTED_BY_META, true );
	$who  = $by ? get_the_author_meta( 'display_name', $by ) : '';
	$note = $who ? ' <span style="color:#787c82;">(suggested by ' . esc_html( $who ) . ')</span>' : '';
	return '<strong style="color:#bd8600;">● Pending review</strong>' . $note . ' — <a href="' . esc_url( lis_directory_feature_approve_url( $term_id ) ) . '">Approve</a>';
}

function lis_directory_feature_row_actions( $actions, $term ) {
	if ( isset( $term->taxonomy ) && 'lis_listing_feature' === $term->taxonomy && lis_directory_is_feature_pending( $term->term_id ) ) {
		$approve = array( 'lis_approve_feature' => '<a href="' . esc_url( lis_directory_feature_approve_url( $term->term_id ) ) . '">Approve suggestion</a>' );
		// Put Approve first; the native Delete link is the "veto".
		$actions = $approve + $actions;
	}
	return $actions;
}

function lis_directory_feature_approve_url( $term_id ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=lis_directory_approve_feature&term_id=' . (int) $term_id ),
		'lis_directory_approve_feature_' . (int) $term_id
	);
}

function lis_directory_handle_approve_feature() {
	$term_id = isset( $_GET['term_id'] ) ? (int) $_GET['term_id'] : 0;
	if ( ! $term_id || ! current_user_can( 'manage_categories' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'lis_directory_approve_feature_' . $term_id );

	delete_term_meta( $term_id, LIS_DIRECTORY_FEATURE_PENDING_META );
	delete_term_meta( $term_id, LIS_DIRECTORY_FEATURE_SUGGESTED_BY_META );

	$redirect = wp_get_referer();
	if ( ! $redirect ) {
		$redirect = admin_url( 'edit-tags.php?taxonomy=lis_listing_feature&post_type=lis_listing' );
	}
	wp_safe_redirect( $redirect );
	exit;
}


/* ------------------------------------------------------------------ *
 * Admin: tick/untick a listing's features on its edit screen.         *
 * ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', 'lis_directory_add_features_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_features_meta_box' );
add_filter( 'rest_prepare_taxonomy', 'lis_directory_hide_features_block_editor_panel', 10, 2 );

/**
 * Keep the block editor from also showing its own Features tag panel (that
 * would be a second writer alongside the checkbox box below): the panel only
 * renders for taxonomies the REST API reports as `visibility.show_ui`.
 */
function lis_directory_hide_features_block_editor_panel( $response, $taxonomy ) {
	if ( 'lis_listing_feature' === $taxonomy->name && isset( $response->data['visibility'] ) ) {
		$response->data['visibility']['show_ui'] = false;
	}
	return $response;
}

function lis_directory_add_features_meta_box() {
	add_meta_box( 'lis_listing_features', 'Features', 'lis_directory_render_features_meta_box', 'lis_listing', 'normal', 'default' );
}

/**
 * Every feature as a checkbox — the same list the front-end forms show, plus
 * pending suggestions (flagged) so an admin can attach or detach those too —
 * and a field to add a brand-new feature (created live, not pending, since an
 * admin is the one adding it).
 */
function lis_directory_render_features_meta_box( $post ) {
	wp_nonce_field( 'lis_listing_features_box', 'lis_listing_features_nonce' );

	$terms = get_terms( array( 'taxonomy' => 'lis_listing_feature', 'hide_empty' => false, 'orderby' => 'name' ) );
	$terms = is_array( $terms ) ? $terms : array();
	$have  = wp_get_post_terms( $post->ID, 'lis_listing_feature', array( 'fields' => 'ids' ) );
	$have  = is_array( $have ) ? array_map( 'intval', $have ) : array();

	if ( empty( $terms ) ) {
		echo '<p class="description">No features exist yet — add the first one below.</p>';
	} else {
		echo '<div style="columns:220px;column-gap:24px;max-height:260px;overflow:auto;">';
		foreach ( $terms as $term ) {
			$pending = lis_directory_is_feature_pending( $term->term_id );
			echo '<label style="display:block;margin:0 0 6px;break-inside:avoid;">';
			echo '<input type="checkbox" name="lis_feature_ids[]" value="' . (int) $term->term_id . '" ' . checked( in_array( (int) $term->term_id, $have, true ), true, false ) . ' /> ';
			echo esc_html( $term->name );
			if ( $pending ) {
				echo ' <em style="color:#bd8600;">(pending suggestion)</em>';
			}
			echo '</label>';
		}
		echo '</div>';
	}

	echo '<p style="margin-top:12px;"><label for="lis_feature_new"><strong>Add new feature(s)</strong></label><br />';
	echo '<input type="text" id="lis_feature_new" name="lis_feature_new" class="regular-text" placeholder="e.g. Rooftop seating, Pet friendly" /> ';
	echo '<span class="description">Comma-separated. New ones go live right away and are attached to this listing.</span></p>';
	echo '<p class="description">Manage the full list (rename, delete, approve suggestions) under LIS Listings &rarr; Features.</p>';
}

function lis_directory_save_features_meta_box( $post_id ) {
	// Only when this box was actually submitted — front-end saves, quick edit
	// and bulk edit set features their own way and must be left alone.
	if ( ! isset( $_POST['lis_listing_features_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_features_nonce'], 'lis_listing_features_box' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$ids = isset( $_POST['lis_feature_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['lis_feature_ids'] ) ) : array();
	// Only real terms of this taxonomy — never attach an arbitrary id.
	$ids = array_values( array_filter( $ids, function ( $id ) {
		$term = get_term( $id, 'lis_listing_feature' );
		return $term && ! is_wp_error( $term );
	} ) );

	$new = isset( $_POST['lis_feature_new'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_feature_new'] ) ) : '';
	foreach ( array_filter( array_map( 'trim', explode( ',', $new ) ) ) as $name ) {
		if ( mb_strlen( $name ) > 40 ) {
			continue;
		}
		$existing = get_term_by( 'name', $name, 'lis_listing_feature' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$ids[] = (int) $existing->term_id;
			continue;
		}
		$created = wp_insert_term( $name, 'lis_listing_feature' );
		if ( ! is_wp_error( $created ) ) {
			$ids[] = (int) $created['term_id'];
		}
	}

	wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), 'lis_listing_feature' );
}
