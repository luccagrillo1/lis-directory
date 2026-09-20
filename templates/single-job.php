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
	$details    = array_filter( array(
		'Company'   => $company,
		'Location'  => $location,
		'Type'      => isset( $types[ $type ] ) ? $types[ $type ] : '',
		'Workplace' => isset( $workplaces[ $workplace ] ) ? $workplaces[ $workplace ] : '',
		'Pay'       => $salary,
		'Category'  => $cat ? $cat->name : '',
		'Apply by'  => $deadline ? mysql2date( get_option( 'date_format' ), $deadline . ' 00:00:00' ) : '',
	) );
	?>
	<article class="lis-job-single">
		<p class="lis-job-back"><a href="<?php echo esc_url( get_post_type_archive_link( 'lis_job' ) ); ?>">&larr; All jobs</a></p>

		<?php if ( ! $is_live ) : ?>
			<div class="lis-job-notice">This job isn't live — only you can see this preview.</div>
		<?php elseif ( $filled ) : ?>
			<div class="lis-job-notice">This position has been filled.</div>
		<?php endif; ?>

		<header class="lis-job-single-head">
			<?php if ( has_post_thumbnail() ) : ?>
				<span class="lis-job-single-logo"><?php the_post_thumbnail( 'medium', array( 'alt' => '' ) ); ?></span>
			<?php endif; ?>
			<div>
				<h1 class="lis-job-single-title"><?php the_title(); ?></h1>
				<p class="lis-job-single-company">
					<?php if ( $company_site ) : ?>
						<a href="<?php echo esc_url( $company_site ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $company ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $company ); ?>
					<?php endif; ?>
					<?php if ( $location ) : ?> · <?php echo esc_html( $location ); ?><?php endif; ?>
				</p>
				<span class="lis-job-chips">
					<?php if ( isset( $types[ $type ] ) ) : ?><span class="lis-job-chip"><?php echo esc_html( $types[ $type ] ); ?></span><?php endif; ?>
					<?php if ( isset( $workplaces[ $workplace ] ) ) : ?><span class="lis-job-chip"><?php echo esc_html( $workplaces[ $workplace ] ); ?></span><?php endif; ?>
					<?php if ( $salary ) : ?><span class="lis-job-chip lis-job-chip--pay"><?php echo esc_html( $salary ); ?></span><?php endif; ?>
				</span>
			</div>
			<?php if ( $apply && ! $filled ) : ?>
				<div class="lis-job-single-apply"><?php echo $apply; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the render function. ?></div>
			<?php endif; ?>
		</header>

		<div class="lis-job-single-body">
			<div class="lis-job-single-content">
				<h2>About the job</h2>
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
				<?php if ( $edit_url ) : ?>
					<p><a class="lis-job-btn" href="<?php echo esc_url( $edit_url ); ?>">Edit this job</a></p>
				<?php endif; ?>
			</aside>
		</div>
	</article>
	<?php
endwhile;

get_footer();
