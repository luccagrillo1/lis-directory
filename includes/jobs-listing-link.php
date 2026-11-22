<?php
/**
 * Jobs connected to a Local Directory listing (0.64.0).
 *
 * A hiring post can carry `_lis_job_listing_id` (picked on the post form, see
 * lis_directory_user_job_listings()). The listing page then shows those jobs
 * under "Now hiring", and the job links back to the listing.
 *
 * @package LIS_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Open hiring jobs connected to a listing, newest first.
 */
function lis_directory_listing_open_jobs( $listing_id, $limit = 6 ) {
	$ids = get_posts( array(
		'post_type'      => 'lis_job',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_lis_job_listing_id',
				'value' => (int) $listing_id,
				'type'  => 'NUMERIC',
			),
		),
	) );
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! lis_directory_is_job_open( $id ) || get_post_meta( $id, '_lis_job_expired', true ) || lis_directory_job_is_gig( $id ) || lis_directory_job_is_seeking( $id ) ) {
			continue;
		}
		$out[] = $id;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * "Now hiring" section on a listing page. The listing's owner gets a nudge to
 * post one when there are none; everyone else sees nothing in that case.
 */
function lis_directory_render_listing_jobs( $listing_id ) {
	$jobs     = lis_directory_listing_open_jobs( $listing_id );
	$is_owner = is_user_logged_in() && (int) get_post_field( 'post_author', $listing_id ) === get_current_user_id();
	$post_url = lis_directory_get_job_page_url( 'submit' );
	if ( ! $jobs && ! ( $is_owner && $post_url ) ) {
		return;
	}
	$types = lis_directory_get_job_employment_types();
	?>
	<div class="lis-listing-section lis-listing-jobs">
		<h2><?php echo esc_html( $jobs ? 'Now hiring' : 'Hiring?' ); ?></h2>
		<?php if ( $jobs ) : ?>
			<ul class="lis-listing-jobs-list">
				<?php foreach ( $jobs as $id ) : ?>
					<?php
					$type = (string) get_post_meta( $id, '_lis_job_employment_type', true );
					$bits = array_filter( array( isset( $types[ $type ] ) ? $types[ $type ] : '', lis_directory_get_job_salary_text( $id ) ) );
					?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
						<?php if ( $bits ) : ?><span><?php echo esc_html( implode( ' · ', $bits ) ); ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php $board = get_post_type_archive_link( 'lis_job' ); ?>
			<?php if ( $board ) : ?>
				<p class="lis-listing-jobs-more"><a href="<?php echo esc_url( $board ); ?>">See all local jobs &rarr;</a></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="lis-listing-jobs-nudge">Only you can see this. Post a job and connect it to this listing, and it shows here and on the work board.
				<a href="<?php echo esc_url( add_query_arg( array( 'kind' => 'hiring', 'listing' => (int) $listing_id ), $post_url ) ); ?>">Post a job &rarr;</a></p>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'lis_directory_single_listing_sections', 'lis_directory_render_listing_jobs', 5 );
