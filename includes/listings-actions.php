<?php
/**
 * Bookmark, Share, Report, and Claim for `lis_listing`.
 *
 * Report and Claim share one lightweight, non-public `lis_listing_flag`
 * post type (own admin list under Listings) instead of two separate
 * systems — both are "something needs a human's attention on this
 * listing", just with a different `_flag_type`. Reusing WordPress's own
 * post-list UI (Edit/Trash, sortable, searchable) for free rather than
 * building a custom admin screen from scratch.
 *
 * Bookmark is a simple per-user postmeta-free list stored on the *user*
 * (usermeta, since it's "this user's saved listings", not something the
 * listing itself needs to know about), toggled via a small AJAX handler.
 *
 * Share has no server component — Web Share API where available, clipboard
 * copy fallback otherwise. Pure front-end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_flag_cpt' );
add_action( 'wp_ajax_lis_directory_toggle_bookmark', 'lis_directory_ajax_toggle_bookmark' );
add_action( 'admin_post_lis_directory_submit_report', 'lis_directory_handle_report_submission' );
add_action( 'admin_post_lis_directory_submit_claim', 'lis_directory_handle_claim_submission' );
add_action( 'admin_post_lis_directory_approve_claim', 'lis_directory_handle_claim_approval' );
add_action( 'add_meta_boxes', 'lis_directory_add_flag_meta_box' );
add_action( 'wp_enqueue_scripts', 'lis_directory_enqueue_listing_actions_script' );

function lis_directory_register_flag_cpt() {
	register_post_type( 'lis_listing_flag', array(
		'labels'          => array(
			'name'          => 'Claims & Reports',
			'singular_name' => 'Claim/Report',
			'menu_name'     => 'Claims & Reports',
			'all_items'     => 'Claims & Reports',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=lis_listing',
		'show_in_rest'    => false,
		'capability_type' => 'post',
		'supports'        => array( 'title', 'editor' ),
	) );
}

function lis_directory_add_flag_meta_box() {
	add_meta_box( 'lis_listing_flag_details', 'Details', 'lis_directory_render_flag_meta_box', 'lis_listing_flag', 'normal', 'high' );
}

function lis_directory_render_flag_meta_box( $post ) {
	$type       = get_post_meta( $post->ID, '_flag_type', true );
	$listing_id = (int) get_post_meta( $post->ID, '_flag_listing_id', true );
	$user_id    = (int) get_post_meta( $post->ID, '_flag_user_id', true );
	$listing    = $listing_id ? get_post( $listing_id ) : null;
	$user       = $user_id ? get_userdata( $user_id ) : null;
	$verified   = $listing_id ? (bool) get_post_meta( $listing_id, '_lis_listing_verified', true ) : false;
	?>
	<p><strong>Type:</strong> <?php echo esc_html( ucfirst( $type ) ); ?></p>
	<p><strong>Listing:</strong>
		<?php if ( $listing ) : ?>
			<a href="<?php echo esc_url( get_edit_post_link( $listing_id ) ); ?>"><?php echo esc_html( $listing->post_title ); ?></a>
		<?php else : ?>
			(deleted)
		<?php endif; ?>
	</p>
	<p><strong>From:</strong> <?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'unknown'; ?></p>

	<?php if ( 'claim' === $type && $listing_id ) : ?>
		<?php if ( $verified ) : ?>
			<p><strong>This claim has been approved.</strong> The listing is marked Owner Verified.</p>
		<?php else : ?>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lis_directory_approve_claim&flag_id=' . $post->ID ), 'lis_directory_approve_claim_' . $post->ID ) ); ?>" class="button button-primary">
					Approve Claim — mark Owner Verified &amp; reassign author
				</a>
			</p>
		<?php endif; ?>
	<?php endif; ?>
	<?php
}

/* ---------- Bookmark ---------- */

function lis_directory_get_user_bookmarks( $user_id ) {
	$ids = get_user_meta( $user_id, '_lis_listing_bookmarks', true );
	return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
}

function lis_directory_ajax_toggle_bookmark() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Log in to bookmark listings.' ), 401 );
	}
	check_ajax_referer( 'lis_directory_bookmark', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || 'lis_listing' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid listing.' ), 400 );
	}

	$user_id    = get_current_user_id();
	$bookmarks  = lis_directory_get_user_bookmarks( $user_id );
	$is_saved   = in_array( $post_id, $bookmarks, true );

	if ( $is_saved ) {
		$bookmarks = array_values( array_diff( $bookmarks, array( $post_id ) ) );
	} else {
		$bookmarks[] = $post_id;
	}
	update_user_meta( $user_id, '_lis_listing_bookmarks', $bookmarks );

	wp_send_json_success( array( 'bookmarked' => ! $is_saved ) );
}

/* ---------- Report ---------- */

function lis_directory_handle_report_submission() {
	$redirect_base = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to report a listing.' );
	}
	if ( ! isset( $_POST['lis_listing_report_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_report_nonce'], 'lis_listing_report' ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
	$reason     = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

	if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) || '' === $reason ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_report_error', '1', $redirect_base ) );
		exit;
	}

	$flag_id = wp_insert_post( array(
		'post_type'    => 'lis_listing_flag',
		'post_title'   => 'Report: ' . get_the_title( $listing_id ),
		'post_content' => $reason,
		'post_status'  => 'publish', // "publish" here just means "a real row admins can see" — the CPT isn't public.
		'post_author'  => get_current_user_id(),
	) );

	if ( ! is_wp_error( $flag_id ) ) {
		update_post_meta( $flag_id, '_flag_type', 'report' );
		update_post_meta( $flag_id, '_flag_listing_id', $listing_id );
		update_post_meta( $flag_id, '_flag_user_id', get_current_user_id() );
	}

	wp_safe_redirect( add_query_arg( 'lis_listing_reported', '1', $redirect_base ) );
	exit;
}

/* ---------- Claim ---------- */

function lis_directory_handle_claim_submission() {
	$redirect_base = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to claim a listing.' );
	}
	if ( ! isset( $_POST['lis_listing_claim_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_claim_nonce'], 'lis_listing_claim' ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
	$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_claim_error', '1', $redirect_base ) );
		exit;
	}

	$flag_id = wp_insert_post( array(
		'post_type'    => 'lis_listing_flag',
		'post_title'   => 'Claim: ' . get_the_title( $listing_id ),
		'post_content' => $message,
		'post_status'  => 'publish',
		'post_author'  => get_current_user_id(),
	) );

	if ( ! is_wp_error( $flag_id ) ) {
		update_post_meta( $flag_id, '_flag_type', 'claim' );
		update_post_meta( $flag_id, '_flag_listing_id', $listing_id );
		update_post_meta( $flag_id, '_flag_user_id', get_current_user_id() );
	}

	wp_safe_redirect( add_query_arg( 'lis_listing_claimed', '1', $redirect_base ) );
	exit;
}

function lis_directory_handle_claim_approval() {
	$flag_id = isset( $_GET['flag_id'] ) ? absint( $_GET['flag_id'] ) : 0;
	if ( ! $flag_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'lis_directory_approve_claim_' . $flag_id ) ) {
		wp_die( 'Security check failed.' );
	}
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( 'You do not have permission to approve claims.' );
	}

	$listing_id = (int) get_post_meta( $flag_id, '_flag_listing_id', true );
	$claimant_id = (int) get_post_meta( $flag_id, '_flag_user_id', true );

	if ( $listing_id && 'lis_listing' === get_post_type( $listing_id ) ) {
		update_post_meta( $listing_id, '_lis_listing_verified', 1 );
		if ( $claimant_id ) {
			wp_update_post( array( 'ID' => $listing_id, 'post_author' => $claimant_id ) );
		}
	}

	wp_safe_redirect( get_edit_post_link( $flag_id, 'raw' ) );
	exit;
}

/* ---------- Share (front-end only, script enqueue) ---------- */

function lis_directory_enqueue_listing_actions_script() {
	if ( ! is_singular( 'lis_listing' ) ) {
		return;
	}
	wp_enqueue_script(
		'lis-directory-listing-actions',
		LIS_DIRECTORY_URL . 'assets/js/listing-actions.js',
		array(),
		LIS_DIRECTORY_VERSION,
		true
	);
	wp_localize_script( 'lis-directory-listing-actions', 'LIS_LISTING_ACTIONS', array(
		'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
		'bookmarkNonce'   => wp_create_nonce( 'lis_directory_bookmark' ),
		'postId'          => get_queried_object_id(),
		'isLoggedIn'      => is_user_logged_in(),
		'isBookmarked'    => is_user_logged_in() ? in_array( get_queried_object_id(), lis_directory_get_user_bookmarks( get_current_user_id() ), true ) : false,
		'loginUrl'        => wp_login_url( get_permalink() ),
	) );
}
