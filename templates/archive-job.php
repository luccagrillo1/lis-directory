<?php
/**
 * Job board archive — /jobs/ and any lis_job_category term archive. Routed
 * from includes/jobs-template.php unless a theme provides archive-lis_job.php
 * / taxonomy-lis_job_category.php. The main query has already been filtered
 * (open jobs, plus any search/category/type/workplace filters) by
 * lis_directory_job_board_main_query().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );

// The work board (Jobs, Local Gigs, Available for Work) on the main /jobs/ page.
if ( function_exists( 'lis_directory_work_hub_active' ) && lis_directory_work_hub_active() ) {
	$lis_work_hub = lis_directory_render_work_hub(); // Enqueues its assets, so render before the header.
	get_header();
	echo '<div class="lis-work-hub">' . $lis_work_hub . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the renderer.
	get_footer();
	return;
}

get_header();

$filters = lis_directory_job_read_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$title   = is_tax( 'lis_job_category' ) ? single_term_title( '', false ) : 'Local Jobs';
?>
<div class="lis-job-board lis-job-archive">
	<div class="lis-job-archive-head">
		<h1 class="lis-job-archive-title"><?php echo esc_html( $title ); ?></h1>
		<?php
		$cta = lis_directory_render_job_post_cta();
		echo $cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php if ( ! is_tax( 'lis_job_category' ) ) : ?>
		<p class="lis-job-archive-intro">A free local job board for Sandpoint and the surrounding area: <?php echo lis_directory_jobs_allow_seeking() ? 'openings from local employers, and neighbors looking for work' : 'openings from local employers'; ?>. Posting is free.</p>
	<?php endif; ?>

	<?php echo lis_directory_render_job_filter_form( get_post_type_archive_link( 'lis_job' ), $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( have_posts() ) : ?>
		<div class="lis-job-list">
			<?php
			while ( have_posts() ) :
				the_post();
				echo lis_directory_render_job_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			endwhile;
			?>
		</div>
	<?php else : ?>
		<p class="lis-job-empty">No open positions match right now. Check back soon.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
