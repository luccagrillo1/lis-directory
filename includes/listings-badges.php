<?php
/**
 * Featured, Popular, and Verified badges for `lis_listing`.
 *
 * - Featured: admin-set flag (editorial choice — who's paid for/earned a
 *   featured slot is a business decision, not something this plugin infers).
 * - Popular: computed, not manually set — the top
 *   LIS_DIRECTORY_POPULAR_TOP_PERCENT of published listings by views over
 *   the last LIS_DIRECTORY_POPULAR_WINDOW_DAYS days, and never a listing
 *   under LIS_DIRECTORY_POPULAR_MIN_VIEWS in that window (so a quiet month
 *   doesn't hand the badge to whoever got 1 view). Views are counted by a
 *   small beacon from the single-listing page (assets/js/listing-actions.js →
 *   admin-ajax), not on template_redirect: the site sits behind the
 *   WordPress.com edge cache, so most anonymous page views never reach PHP
 *   and a server-side counter only saw cache misses. One count per listing
 *   per browser session (sessionStorage), bot user agents and editors
 *   skipped, and at most LIS_DIRECTORY_VIEW_RATE_LIMIT counted hits per IP
 *   per minute. Raw views, not
 *   unique visitors — fine for a badge, not analytics-grade.
 * - Verified: set automatically when a claim request is approved (see
 *   includes/listings-actions.php) — not admin-set directly, since the
 *   whole point is confirming a specific claim happened.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// D17: the Popular rule. TOP_PERCENT is the threshold; the window and the
// floor keep it honest. Documented in CHANGELOG.md 0.48.0.
define( 'LIS_DIRECTORY_POPULAR_TOP_PERCENT', 10 );
define( 'LIS_DIRECTORY_POPULAR_WINDOW_DAYS', 30 );
define( 'LIS_DIRECTORY_POPULAR_MIN_VIEWS', 5 );
// Beacon abuse guard: at most this many counted hits per IP per minute.
define( 'LIS_DIRECTORY_VIEW_RATE_LIMIT', 30 );

add_action( 'init', 'lis_directory_register_badge_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_badges_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_badges_meta_box' );
add_action( 'wp_ajax_lis_directory_listing_view', 'lis_directory_record_listing_view' );
add_action( 'wp_ajax_nopriv_lis_directory_listing_view', 'lis_directory_record_listing_view' );

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
	$recent   = lis_directory_get_listing_recent_views( $post->ID );
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
		<strong>Views (last <?php echo (int) LIS_DIRECTORY_POPULAR_WINDOW_DAYS; ?> days):</strong> <?php echo esc_html( number_format_i18n( $recent ) ); ?>
		<br /><strong>Views (all time):</strong> <?php echo esc_html( number_format_i18n( $views ) ); ?>
		<br /><span class="description">
			<?php if ( lis_directory_is_listing_popular( $post->ID ) ) : ?>
				Shows a "Popular" badge: top <?php echo (int) LIS_DIRECTORY_POPULAR_TOP_PERCENT; ?>% of listings by views over the last <?php echo (int) LIS_DIRECTORY_POPULAR_WINDOW_DAYS; ?> days.
			<?php else : ?>
				"Popular" goes to the top <?php echo (int) LIS_DIRECTORY_POPULAR_TOP_PERCENT; ?>% of listings by views over the last <?php echo (int) LIS_DIRECTORY_POPULAR_WINDOW_DAYS; ?> days (at least <?php echo (int) LIS_DIRECTORY_POPULAR_MIN_VIEWS; ?> views).
			<?php endif; ?>
		</span>
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

/**
 * admin-ajax endpoint for the view beacon. No nonce on purpose: the page that
 * sends it is usually served from the edge cache, where any nonce baked into
 * the HTML is stale. Worst case someone inflates a badge — same exposure as
 * the old per-request counter.
 */
function lis_directory_record_listing_view() {
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see docblock.
	if ( ! $post_id || 'lis_listing' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		wp_send_json_error( null, 400 );
	}
	// Editors checking a listing aren't visitors.
	if ( current_user_can( 'edit_others_posts' ) ) {
		wp_send_json_success( array( 'counted' => false ) );
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse/', $ua ) ) {
		wp_send_json_success( array( 'counted' => false ) );
	}

	if ( lis_directory_view_rate_limited() ) {
		wp_send_json_success( array( 'counted' => false ) );
	}

	$views = (int) get_post_meta( $post_id, '_lis_listing_views', true );
	update_post_meta( $post_id, '_lis_listing_views', $views + 1 );

	$today  = current_time( 'Ymd' );
	$cutoff = lis_directory_popular_window_start();
	$by_day = get_post_meta( $post_id, '_lis_listing_views_by_day', true );
	$by_day = is_array( $by_day ) ? $by_day : array();
	$by_day[ $today ] = ( isset( $by_day[ $today ] ) ? (int) $by_day[ $today ] : 0 ) + 1;
	foreach ( array_keys( $by_day ) as $day ) {
		if ( (string) $day < $cutoff ) {
			unset( $by_day[ $day ] );
		}
	}
	update_post_meta( $post_id, '_lis_listing_views_by_day', $by_day );

	wp_send_json_success( array( 'counted' => true ) );
}

/**
 * Per-IP fixed one-minute window. The IP is hashed (never stored raw) and the
 * counter lives in a transient that expires with the window. True once the
 * IP has used up LIS_DIRECTORY_VIEW_RATE_LIMIT counted hits this minute.
 */
function lis_directory_view_rate_limited() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		return true;
	}
	$key  = 'lis_dir_vrl_' . md5( wp_salt( 'nonce' ) . $ip ) . '_' . gmdate( 'YmdHi' );
	$hits = (int) get_transient( $key );
	if ( $hits >= LIS_DIRECTORY_VIEW_RATE_LIMIT ) {
		return true;
	}
	set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
	return false;
}

/** First day (Ymd, site timezone) inside the Popular window, today included. */
function lis_directory_popular_window_start() {
	return wp_date( 'Ymd', time() - ( LIS_DIRECTORY_POPULAR_WINDOW_DAYS - 1 ) * DAY_IN_SECONDS );
}

function lis_directory_get_listing_recent_views( $post_id ) {
	$by_day = get_post_meta( $post_id, '_lis_listing_views_by_day', true );
	if ( ! is_array( $by_day ) ) {
		return 0;
	}
	$cutoff = lis_directory_popular_window_start();
	$total  = 0;
	foreach ( $by_day as $day => $count ) {
		if ( (string) $day >= $cutoff ) {
			$total += (int) $count;
		}
	}
	return $total;
}

/**
 * IDs of the listings that get the Popular badge right now. Ranked across all
 * published listings, so it's computed once and cached for an hour rather
 * than per card.
 */
function lis_directory_get_popular_listing_ids() {
	$cached = get_transient( 'lis_directory_popular_ids' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	update_meta_cache( 'post', $ids );

	$recent = array();
	foreach ( $ids as $id ) {
		$views = lis_directory_get_listing_recent_views( $id );
		if ( $views >= LIS_DIRECTORY_POPULAR_MIN_VIEWS ) {
			$recent[ $id ] = $views;
		}
	}
	arsort( $recent );

	$slots   = (int) ceil( count( $ids ) * LIS_DIRECTORY_POPULAR_TOP_PERCENT / 100 );
	$popular = array_map( 'intval', array_slice( array_keys( $recent ), 0, $slots ) );

	set_transient( 'lis_directory_popular_ids', $popular, HOUR_IN_SECONDS );
	return $popular;
}

function lis_directory_is_listing_popular( $post_id ) {
	return in_array( (int) $post_id, lis_directory_get_popular_listing_ids(), true );
}
