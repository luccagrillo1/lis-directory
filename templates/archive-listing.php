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

	<?php if ( function_exists( 'lis_directory_render_search_form_shortcode' ) ) : ?>
		<?php echo lis_directory_render_search_form_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped inside the render function. ?>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php if ( function_exists( 'lis_directory_render_listing_toolbar' ) ) : ?>
			<?php lis_directory_render_listing_toolbar(); ?>
		<?php endif; ?>

		<div class="lis-listing-grid" data-lis-listing-results>
			<?php
			while ( have_posts() ) :
				the_post();
				$post_id     = get_the_ID();
				$type        = lis_directory_get_listing_type( $post_id );
				$address     = get_post_meta( $post_id, '_lis_listing_address', true );
				$price       = get_post_meta( $post_id, '_lis_listing_price', true );
				$bedrooms    = get_post_meta( $post_id, '_lis_listing_bedrooms', true );
				$bathrooms   = get_post_meta( $post_id, '_lis_listing_bathrooms', true );
				$sqft        = get_post_meta( $post_id, '_lis_listing_sqft', true );
				$salary      = get_post_meta( $post_id, '_lis_listing_salary', true );
				$categories  = get_the_terms( $post_id, 'lis_listing_category' );
				$category    = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;
				$features    = get_the_terms( $post_id, 'lis_listing_feature' );
				$features    = ( $features && ! is_wp_error( $features ) ) ? array_slice( $features, 0, 3 ) : array();
				$is_open     = lis_directory_is_listing_open_now( $post_id );
				$gallery_raw = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
				$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
				$thumb_id    = ! empty( $gallery_ids ) ? $gallery_ids[0] : ( has_post_thumbnail() ? get_post_thumbnail_id() : 0 );
				$featured    = (bool) get_post_meta( $post_id, '_lis_listing_featured', true );
				$verified    = (bool) get_post_meta( $post_id, '_lis_listing_verified', true );
				$sold        = ( 'real-estate-sale' === $type || 'real-estate-rent' === $type ) && (bool) get_post_meta( $post_id, '_lis_listing_sold', true );
				$popular     = lis_directory_is_listing_popular( $post_id );
				$avg_rating  = lis_directory_get_listing_average_rating( $post_id );
				?>
				<a class="lis-listing-card" href="<?php the_permalink(); ?>" <?php echo $address ? 'data-address="' . esc_attr( $address ) . '" data-title="' . esc_attr( get_the_title() ) . '"' : ''; ?>>
					<?php if ( $thumb_id ) : ?>
						<?php echo wp_get_attachment_image( $thumb_id, 'medium', false, array( 'class' => 'lis-listing-card-thumb' ) ); ?>
					<?php endif; ?>
					<div class="lis-listing-card-body">
						<div class="lis-listing-card-title-row">
							<p class="lis-listing-card-title"><?php the_title(); ?></p>
							<?php if ( null !== $is_open || $category ) : ?>
								<span class="lis-listing-card-title-right">
									<?php if ( null !== $is_open ) : ?>
										<span class="lis-listing-open-status lis-listing-open-status--sm <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></span>
									<?php endif; ?>
									<?php if ( $category ) : ?>
										<span class="lis-listing-category-badge"><?php echo esc_html( $category->name ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</div>
						<?php if ( $sold || $featured || $popular || $verified || $avg_rating ) : ?>
							<div class="lis-listing-card-meta-row">
								<?php if ( $sold ) : ?><span class="lis-listing-badge lis-listing-badge--sold"><?php echo 'real-estate-rent' === $type ? 'Rented' : 'Sold'; ?></span><?php endif; ?>
								<?php if ( $featured ) : ?><span class="lis-listing-badge lis-listing-badge--featured">Featured</span><?php endif; ?>
								<?php if ( $popular ) : ?><span class="lis-listing-badge lis-listing-badge--popular">Popular</span><?php endif; ?>
								<?php if ( $verified ) : ?><span class="lis-listing-badge lis-listing-badge--verified">✓ Verified</span><?php endif; ?>
								<?php if ( $avg_rating ) : ?><span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( ( 'real-estate-sale' === $type || 'real-estate-rent' === $type ) && ( $bedrooms || $bathrooms || $sqft ) ) : ?>
							<p class="lis-listing-card-facts">
								<?php echo esc_html( implode( ' · ', array_filter( array(
									$bedrooms ? $bedrooms . ' bd' : '',
									$bathrooms ? $bathrooms . ' ba' : '',
									$sqft ? number_format_i18n( (int) $sqft ) . ' sqft' : '',
								) ) ) ); ?>
							</p>
						<?php elseif ( 'job-listing' === $type && $salary ) : ?>
							<p class="lis-listing-card-facts lis-listing-card-facts--salary"><?php echo esc_html( $salary ); ?></p>
						<?php endif; ?>
						<p class="lis-listing-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></p>
						<?php if ( ! empty( $features ) ) : ?>
							<div class="lis-listing-card-tags">
								<?php foreach ( $features as $feature ) : ?>
									<span class="lis-listing-tag"><?php echo esc_html( $feature->name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</a>
			<?php endwhile; ?>
		</div>

		<?php
		$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );
		if ( $maps_api_key ) :
			?>
			<div class="lis-listing-map-view" data-lis-listing-map-view hidden>
				<div class="lis-listing-map-view-canvas"></div>
			</div>
			<script>window.lisDirectoryMapsApiKey = <?php echo wp_json_encode( $maps_api_key ); ?>;</script>
		<?php endif; ?>
		<?php wp_enqueue_script( 'lis-directory-listing-view-toggle', LIS_DIRECTORY_URL . 'assets/js/listing-view-toggle.js', array(), LIS_DIRECTORY_VERSION, true ); ?>

		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="lis-listing-empty">No listings here yet.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
