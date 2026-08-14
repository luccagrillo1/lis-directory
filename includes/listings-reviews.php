<?php
/**
 * Reviews for `lis_listing`, built on WordPress's own comment system rather
 * than a parallel custom table + moderation UI. Deliberate: this site
 * already has Akismet active (spam filtering "for free"), and wp-admin's
 * Comments screen already gives moderation, reply, and email-notification
 * UI that a from-scratch reviews system would have to rebuild badly or not
 * at all. The only addition needed is a 1-5 star rating stored as comment
 * meta, and forcing reviews to pending regardless of the site's general
 * "comments must be manually approved" Discussion setting (a review should
 * always be moderated even if regular blog comments aren't).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_review_meta' );
add_filter( 'preprocess_comment', 'lis_directory_require_review_rating' );
add_filter( 'pre_comment_approved', 'lis_directory_force_review_pending', 10, 2 );
add_action( 'comment_post', 'lis_directory_save_review_rating' );
add_action( 'comment_form_before_fields', 'lis_directory_render_rating_field' );
add_filter( 'comment_text', 'lis_directory_prepend_rating_to_comment', 10, 2 );
add_filter( 'comment_form_defaults', 'lis_directory_relabel_comment_form' );
add_filter( 'gettext', 'lis_directory_relabel_jetpack_comment_form', 10, 3 );

/**
 * Only shows/relabels on `lis_listing` single pages — get_the_ID() inside
 * comment_form() context reflects the post the form is for, which is
 * exactly the post being displayed since this form only ever appears on
 * that post's own page.
 */
function lis_directory_render_rating_field() {
	if ( 'lis_listing' !== get_post_type() ) {
		return;
	}
	?>
	<p class="lis-listing-rating-field">
		<label>Your rating</label>
		<span class="lis-listing-rating-stars">
			<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
				<label>
					<input type="radio" name="lis_listing_rating" value="<?php echo (int) $i; ?>" required />
					<?php echo (int) $i; ?>★
				</label>
			<?php endfor; ?>
		</span>
	</p>
	<?php
}

function lis_directory_relabel_comment_form( $defaults ) {
	if ( 'lis_listing' === get_post_type() ) {
		$defaults['title_reply']  = 'Leave a Review';
		$defaults['label_submit'] = 'Submit Review';
	}
	return $defaults;
}

/**
 * This site's Jetpack Comments module replaces the form above with its own
 * cross-domain iframe (jetpack.wordpress.com/jetpack-comment/) for visitors
 * with JS enabled — comment_form_defaults above only reaches the hidden
 * no-JS fallback markup, never what most visitors actually see. Jetpack
 * builds that iframe's greeting/button copy from its own translatable
 * strings rather than calling comment_form() locally, so the only hook that
 * reaches it is gettext, scoped to Jetpack's own text domain so nothing
 * outside the comment form is touched. Confirmed live: the iframe's
 * `greeting` query param carried the untranslated "Leave a Reply" despite
 * the filter above already being in place.
 */
function lis_directory_relabel_jetpack_comment_form( $translated, $original, $domain ) {
	if ( 'jetpack' !== $domain || 'lis_listing' !== get_post_type() ) {
		return $translated;
	}
	$map = array(
		'Leave a Reply'       => 'Leave a Review',
		'Leave a Reply to %s' => 'Leave a Review for %s',
		'Post Comment'        => 'Submit Review',
		'Comment'             => 'Submit Review',
	);
	return isset( $map[ $original ] ) ? $map[ $original ] : $translated;
}

function lis_directory_prepend_rating_to_comment( $text, $comment = null ) {
	if ( ! $comment || 'lis_listing' !== get_post_type( $comment->comment_post_ID ) ) {
		return $text;
	}
	$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
	if ( $rating < 1 ) {
		return $text;
	}
	return '<span class="lis-listing-review-stars">' . esc_html( lis_directory_render_stars( $rating ) ) . '</span>' . $text;
}

function lis_directory_register_review_meta() {
	// No register_comment_meta() convenience wrapper exists in WordPress core
	// (unlike register_post_meta() / register_term_meta()) — comment meta is
	// registered through the generic register_meta() with an explicit
	// 'comment' object type instead. Confirmed the fatal ("Call to undefined
	// function register_comment_meta()") live on this site before fixing.
	register_meta( 'comment', 'rating', array(
		'type'          => 'integer',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => '__return_true',
	) );
}

/**
 * A review with no rating isn't useful data — reject the comment outright
 * (same mechanism WordPress itself uses: throw on $commentdata) rather than
 * silently accepting a 0-star review.
 */
function lis_directory_require_review_rating( $commentdata ) {
	if ( 'lis_listing' !== get_post_type( $commentdata['comment_post_ID'] ) ) {
		return $commentdata;
	}
	$rating = isset( $_POST['lis_listing_rating'] ) ? (int) $_POST['lis_listing_rating'] : 0; // phpcs:ignore -- read-only check, sanitized/stored properly in lis_directory_save_review_rating().
	if ( $rating < 1 || $rating > 5 ) {
		wp_die( esc_html__( 'Please choose a star rating between 1 and 5.', 'lis-directory' ) );
	}
	return $commentdata;
}

function lis_directory_force_review_pending( $approved, $commentdata ) {
	if ( isset( $commentdata['comment_post_ID'] ) && 'lis_listing' === get_post_type( $commentdata['comment_post_ID'] ) ) {
		return 0; // Always pending, regardless of Settings > Discussion.
	}
	return $approved;
}

function lis_directory_save_review_rating( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'lis_listing' !== get_post_type( $comment->comment_post_ID ) ) {
		return;
	}
	$rating = isset( $_POST['lis_listing_rating'] ) ? (int) $_POST['lis_listing_rating'] : 0; // phpcs:ignore -- validated range below.
	if ( $rating >= 1 && $rating <= 5 ) {
		update_comment_meta( $comment_id, 'rating', $rating );
	}
}

/**
 * Average of all *approved* reviews' ratings, rounded to one decimal.
 * Returns null (not 0) when there are no reviews yet — a listing with no
 * reviews should show "no reviews" in the template, not a misleading 0.0.
 */
function lis_directory_get_listing_average_rating( $post_id ) {
	$comments = get_comments( array(
		'post_id' => $post_id,
		'status'  => 'approve',
	) );
	if ( empty( $comments ) ) {
		return null;
	}
	$total = 0;
	$count = 0;
	foreach ( $comments as $comment ) {
		$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
		if ( $rating >= 1 ) {
			$total += $rating;
			++$count;
		}
	}
	return $count ? round( $total / $count, 1 ) : null;
}

function lis_directory_get_listing_review_count( $post_id ) {
	return (int) get_comments( array(
		'post_id' => $post_id,
		'status'  => 'approve',
		'count'   => true,
	) );
}

/**
 * Renders filled/empty stars as plain text glyphs — no icon font/SVG
 * dependency, works everywhere.
 */
function lis_directory_render_stars( $rating ) {
	$rating  = max( 0, min( 5, round( $rating ) ) );
	$filled  = str_repeat( '★', $rating );
	$empty   = str_repeat( '☆', 5 - $rating );
	return $filled . $empty;
}
