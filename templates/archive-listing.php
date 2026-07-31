<?php
/**
 * Archive/category template for `lis_listing` — used for both the plain
 * post-type archive (/listings/) and any `lis_listing_category` term archive.
 * Routed from includes/listings-template.php unless a theme provides its own
 * archive-lis_listing.php / taxonomy-lis_listing_category.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

get_header();

$title = is_tax( 'lis_listing_category' ) ? single_term_title( '', false ) : 'Listings';
?>
<div class="lis-listing-archive">
	<h1 class="lis-listing-archive-title"><?php echo esc_html( $title ); ?></h1>

	<?php if ( have_posts() ) : ?>
		<div class="lis-listing-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<a class="lis-listing-card" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium', array( 'class' => 'lis-listing-card-thumb' ) ); ?>
					<?php endif; ?>
					<div class="lis-listing-card-body">
						<p class="lis-listing-card-title"><?php the_title(); ?></p>
						<p class="lis-listing-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></p>
					</div>
				</a>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="lis-listing-empty">No listings here yet.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
