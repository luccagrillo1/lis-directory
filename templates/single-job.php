<?php
/**
 * Single job template for `lis_job`. Routed from includes/jobs-template.php
 * unless a theme provides single-lis_job.php. Schema.org JobPosting JSON-LD is
 * printed in <head> by lis_directory_job_output_meta().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	$job_id     = get_the_ID();
	$types      = lis_directory_get_job_employment_types();
	$workplaces = lis_directory_get_job_workplaces();
	$company    = get_post_meta( $job_id, '_lis_job_company', true );
	$company_site = get_post_meta( $job_id, '_lis_job_company_website', true );
	$listing_id   = function_exists( 'lis_directory_job_listing_id' ) && 'hiring' === lis_directory_get_job_kind( $job_id ) ? lis_directory_job_listing_id( $job_id ) : 0;
	$logo_id      = function_exists( 'lis_directory_job_logo_id' ) ? lis_directory_job_logo_id( $job_id ) : (int) get_post_thumbnail_id( $job_id );
	$location   = get_post_meta( $job_id, '_lis_job_location', true );
	$type       = get_post_meta( $job_id, '_lis_job_employment_type', true );
	$workplace  = get_post_meta( $job_id, '_lis_job_workplace', true );
	$salary     = lis_directory_get_job_salary_text( $job_id );
	$deadline   = get_post_meta( $job_id, '_lis_job_deadline', true );
	$filled     = (bool) get_post_meta( $job_id, '_lis_job_filled', true );
	$cats       = get_the_terms( $job_id, 'lis_job_category' );
	$cat        = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
	$apply      = lis_directory_render_job_apply_buttons( $job_id );
	$can_edit   = function_exists( 'lis_directory_user_can_manage_job' ) && lis_directory_user_can_manage_job( $job_id );
	$edit_url   = $can_edit ? lis_directory_get_job_edit_url( $job_id ) : '';
	$is_live    = 'publish' === get_post_status( $job_id );
	$seeking    = lis_directory_job_is_seeking( $job_id );
	$gig        = lis_directory_job_is_gig( $job_id );
	$when       = get_post_meta( $job_id, '_lis_job_when', true );
	$skills     = $seeking ? lis_directory_job_skills( $job_id ) : array();
	$areas      = $seeking ? lis_directory_job_areas( $job_id ) : array();
	$avail      = $seeking ? (string) get_post_meta( $job_id, '_lis_job_availability', true ) : '';
	$details    = array_filter( array(
		( $seeking ? 'Name' : ( $gig ? 'Posted by' : 'Company' ) ) => $seeking ? '' : $company,
		( $seeking ? 'Based in' : 'Location' ) => $location,
		'Works in'  => implode( ', ', $areas ),
		'Available' => $avail,
		'When'      => $gig ? $when : '',
		'Type'      => ( ! $gig && ! $seeking && isset( $types[ $type ] ) ) ? $types[ $type ] : '',
		'Workplace' => ( ! $gig && ! $seeking && isset( $workplaces[ $workplace ] ) ) ? $workplaces[ $workplace ] : '',
		( $seeking ? 'Rate' : 'Pay' ) => $salary,
		'Category'  => $cat ? $cat->name : '',
		'Apply by'  => ( $deadline && ! $seeking && ! $gig ) ? mysql2date( get_option( 'date_format' ), $deadline . ' 00:00:00' ) : '',
	) );
	?>
	<article class="lis-job-single">
		<p class="lis-job-back"><a href="<?php echo esc_url( get_post_type_archive_link( 'lis_job' ) ); ?>">&larr; Back to the work board</a></p>

		<?php if ( $is_live && isset( $_GET['lis_job_posted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="lis-job-notice lis-job-notice--success">Your post is live on the job board. You can edit, renew or close it any time from My Job Postings.</div>
		<?php endif; ?>
		<?php if ( ! $is_live ) : ?>
			<div class="lis-job-notice">This post isn't live — only you can see this preview.</div>
		<?php elseif ( $filled ) : ?>
			<div class="lis-job-notice"><?php echo $gig ? 'Someone is already on this one.' : 'This position has been filled.'; ?></div>
		<?php endif; ?>

		<?php if ( $seeking ) : ?>
		<header class="lis-job-single-head lis-job-profile-head">
			<?php if ( has_post_thumbnail() ) : ?>
				<span class="lis-job-profile-photo"><?php the_post_thumbnail( 'medium', array( 'alt' => '' ) ); ?></span>
			<?php else : ?>
				<span class="lis-job-profile-photo lis-job-profile-photo--initial" style="--c:<?php echo esc_attr( function_exists( 'lis_directory_work_hub_tint' ) ? lis_directory_work_hub_tint( $company ) : '#2C5240' ); ?>" aria-hidden="true"><?php echo esc_html( function_exists( 'lis_directory_work_hub_initial' ) ? lis_directory_work_hub_initial( $company ) : '' ); ?></span>
			<?php endif; ?>
			<div>
				<p class="lis-job-profile-kick">Available for work</p>
				<h1 class="lis-job-single-title"><?php echo esc_html( $company ? $company : get_the_title() ); ?></h1>
				<p class="lis-job-single-company"><?php the_title(); ?><?php if ( $location ) : ?> · <?php echo esc_html( $location ); ?><?php endif; ?></p>
				<?php if ( $skills ) : ?>
					<ul class="lis-job-skills" aria-label="Skills">
						<?php foreach ( $skills as $s ) : ?><li><?php echo esc_html( $s ); ?></li><?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( $apply && ! $filled ) : ?>
				<div class="lis-job-single-apply"><?php echo $apply; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the render function. ?></div>
			<?php endif; ?>
		</header>
		<?php else : ?>
		<header class="lis-job-single-head">
			<?php if ( $logo_id ) : ?>
				<span class="lis-job-single-logo"><?php echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'alt' => '' ) ); ?></span>
			<?php endif; ?>
			<div>
				<h1 class="lis-job-single-title"><?php the_title(); ?></h1>
				<p class="lis-job-single-company">
					<?php if ( $listing_id ) : ?>
						<a href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>"><?php echo esc_html( $company ); ?></a>
					<?php elseif ( $company_site ) : ?>
						<a href="<?php echo esc_url( $company_site ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $company ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $company ); ?>
					<?php endif; ?>
					<?php if ( $location ) : ?> · <?php echo esc_html( $location ); ?><?php endif; ?>
				</p>
				<span class="lis-job-chips">
					<?php if ( $seeking ) : ?><span class="lis-job-chip lis-job-chip--seeking">Looking for work</span><?php endif; ?>
					<?php if ( $gig ) : ?><span class="lis-job-chip lis-job-chip--gig">Local gig</span><?php if ( $when ) : ?><span class="lis-job-chip"><?php echo esc_html( $when ); ?></span><?php endif; ?><?php endif; ?>
					<?php if ( ! $gig && isset( $types[ $type ] ) ) : ?><span class="lis-job-chip"><?php echo esc_html( $types[ $type ] ); ?></span><?php endif; ?>
					<?php if ( ! $gig && isset( $workplaces[ $workplace ] ) ) : ?><span class="lis-job-chip"><?php echo esc_html( $workplaces[ $workplace ] ); ?></span><?php endif; ?>
					<?php if ( $salary ) : ?><span class="lis-job-chip lis-job-chip--pay"><?php echo esc_html( $salary ); ?></span><?php endif; ?>
				</span>
			</div>
			<?php if ( $apply && ! $filled ) : ?>
				<div class="lis-job-single-apply"><?php echo $apply; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the render function. ?></div>
			<?php endif; ?>
		</header>
		<?php endif; ?>

		<div class="lis-job-single-body">
			<div class="lis-job-single-content">
				<h2><?php echo $seeking ? 'About me' : ( $gig ? 'What\'s needed' : 'About the job' ); ?></h2>
				<?php the_content(); ?>
				<?php if ( $apply && ! $filled ) : ?>
					<div class="lis-job-single-apply lis-job-single-apply--bottom"><?php echo $apply; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
			</div>
			<aside class="lis-job-single-side">
				<h2>Details</h2>
				<dl class="lis-job-details">
					<?php foreach ( $details as $label => $value ) : ?>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( $value ); ?></dd>
					<?php endforeach; ?>
					<dt>Posted</dt>
					<dd><?php echo esc_html( get_the_date() ); ?></dd>
				</dl>
				<?php if ( $listing_id ) : ?>
					<p class="lis-job-listing-link"><a href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>"><?php echo esc_html( 'See ' . html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES, 'UTF-8' ) . ' in the Local Directory' ); ?> &rarr;</a></p>
				<?php endif; ?>
				<?php if ( $edit_url ) : ?>
					<p><a class="lis-job-btn" href="<?php echo esc_url( $edit_url ); ?>">Edit this post</a></p>
				<?php endif; ?>
			</aside>
		</div>
	</article>
	<?php
endwhile;

get_footer();
