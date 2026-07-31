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
			<?php
			while ( have_posts() ) :
				the_post();
				$post_id     = get_the_ID();
				$price       = get_post_meta( $post_id, '_lis_listing_price', true );
				$categories  = get_the_terms( $post_id, 'lis_listing_category' );
				$category    = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;
				$is_open     = lis_directory_is_listing_open_now( $post_id );
				$gallery_raw = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
				$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
				$thumb_id    = ! empty( $gallery_ids ) ? $gallery_ids[0] : ( has_post_thumbnail() ? get_post_thumbnail_id() : 0 );
				?>
				<a class="lis-listing-card" href="<?php the_permalink(); ?>">
					<?php if ( $thumb_id ) : ?>
						<?php echo wp_get_attachment_image( $thumb_id, 'medium', false, array( 'class' => 'lis-listing-card-thumb' ) ); ?>
					<?php endif; ?>
					<div class="lis-listing-card-body">
						<div class="lis-listing-card-title-row">
							<p class="lis-listing-card-title"><?php the_title(); ?></p>
							<?php if ( null !== $is_open ) : ?>
								<span class="lis-listing-open-status lis-listing-open-status--sm <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></span>
							<?php endif; ?>
						</div>
						<div class="lis-listing-card-meta-row">
							<?php if ( $category ) : ?><span class="lis-listing-category-badge"><?php echo esc_html( $category->name ); ?></span><?php endif; ?>
							<?php if ( $price ) : ?><span class="lis-listing-price"><?php echo esc_html( $price ); ?></span><?php endif; ?>
						</div>
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
