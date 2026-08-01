<?php
/**
 * Featured, Popular, and Verified badges for `lis_listing`.
 *
 * - Featured: admin-set flag (editorial choice — who's paid for/earned a
 *   featured slot is a business decision, not something this plugin infers).
 * - Popular: computed, not manually set — a simple view-count threshold.
 *   Tracked here (one counter increment per single-listing page view; no
 *   dedup by visitor, so it's a raw view count, not unique visitors — fine
 *   for "popular enough to badge", not analytics-grade).
 * - Verified: set automatically when a claim request is approved (see
 *   includes/listings-actions.php) — not admin-set directly, since the
 *   whole point is confirming a specific claim happened.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_POPULAR_VIEW_THRESHOLD', 20 );

add_action( 'init', 'lis_directory_register_badge_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_badges_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_badges_meta_box' );
add_action( 'template_redirect', 'lis_directory_track_listing_view' );

function lis_directory_register_badge_meta() {
	register_post_meta( 'lis_listing', '_lis_listing_featured', array(
		'type'          => 'boolean',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_others_posts' ); // Editorial call, not the submitter's.
		},
	) );
	register_post_meta( 'lis_listing', '_lis_listing_verified', array(
		'type'          => 'boolean',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_others_posts' );
		},
	) );
	register_post_meta( 'lis_listing', '_lis_listing_views', array(
		'type'          => 'integer',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
}

function lis_directory_add_badges_meta_box() {
	add_meta_box(
		'lis_listing_badges',
		'Badges',
		'lis_directory_render_badges_meta_box',
		'lis_listing',
		'side',
		'default'
	);
}

function lis_directory_render_badges_meta_box( $post ) {
	wp_nonce_field( 'lis_listing_save_badges', 'lis_listing_badges_nonce' );
	$featured = (bool) get_post_meta( $post->ID, '_lis_listing_featured', true );
	$verified = (bool) get_post_meta( $post->ID, '_lis_listing_verified', true );
	$views    = (int) get_post_meta( $post->ID, '_lis_listing_views', true );
	?>
	<p>
		<label>
			<input type="checkbox" name="lis_listing_featured" value="1" <?php checked( $featured ); ?> />
			Featured
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="lis_listing_verified" value="1" <?php checked( $verified ); ?> />
			Owner Verified
		</label>
		<br /><span class="description">Normally set automatically when a claim request is approved (Listings > Claims &amp; Reports) — check manually only if approving outside that flow.</span>
	</p>
	<p>
		<strong>Views:</strong> <?php echo esc_html( number_format_i18n( $views ) ); ?>
		<?php if ( $views >= LIS_DIRECTORY_POPULAR_VIEW_THRESHOLD ) : ?>
			<br /><span class="description">Shows a "Popular" badge (≥ <?php echo (int) LIS_DIRECTORY_POPULAR_VIEW_THRESHOLD; ?> views).</span>
		<?php endif; ?>
	</p>
	<?php
}

function lis_directory_save_badges_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_listing_badges_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_badges_nonce'], 'lis_listing_save_badges' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return; // Featured/Verified are editorial, not the listing owner's own call.
	}
	update_post_meta( $post_id, '_lis_listing_featured', ! empty( $_POST['lis_listing_featured'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_lis_listing_verified', ! empty( $_POST['lis_listing_verified'] ) ? 1 : 0 );
}

function lis_directory_track_listing_view() {
	if ( ! is_singular( 'lis_listing' ) || is_admin() ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}
	$views = (int) get_post_meta( $post_id, '_lis_listing_views', true );
	update_post_meta( $post_id, '_lis_listing_views', $views + 1 );
}

function lis_directory_is_listing_popular( $post_id ) {
	return (int) get_post_meta( $post_id, '_lis_listing_views', true ) >= LIS_DIRECTORY_POPULAR_VIEW_THRESHOLD;
}
