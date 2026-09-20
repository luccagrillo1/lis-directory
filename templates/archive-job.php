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

get_header();

$filters = lis_directory_job_read_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$title   = is_tax( 'lis_job_category' ) ? single_term_title( '', false ) : 'Jobs';
?>
<div class="lis-job-board lis-job-archive">
	<div class="lis-job-archive-head">
		<h1 class="lis-job-archive-title"><?php echo esc_html( $title ); ?></h1>
		<?php
		$cta = lis_directory_render_job_post_cta();
		echo $cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

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
