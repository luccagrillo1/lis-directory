<?php
/**
 * Single-listing template for `lis_listing`. Routed from
 * includes/listings-template.php unless a theme provides single-lis_listing.php.
 *
 * Structure follows the reference layout the client pointed to: image
 * gallery strip, header row (title/category/price/open-status), two-column
 * body (description+features+video / business hours sidebar). Reviews and
 * appointment booking from that reference are NOT built — those need a real
 * data model (review storage/moderation, a booking/slots system), not a
 * template addition, and are deliberately deferred.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	$address   = get_post_meta( $post_id, '_lis_listing_address', true );
	$phone     = get_post_meta( $post_id, '_lis_listing_phone', true );
	$website   = get_post_meta( $post_id, '_lis_listing_website', true );
	$email     = get_post_meta( $post_id, '_lis_listing_email', true );
	$price     = get_post_meta( $post_id, '_lis_listing_price', true );
	$video_url = get_post_meta( $post_id, '_lis_listing_video_url', true );

	$gallery_raw = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();

	$categories = get_the_terms( $post_id, 'lis_listing_category' );
	$category   = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;

	$features = get_the_terms( $post_id, 'lis_listing_feature' );
	$features = ( $features && ! is_wp_error( $features ) ) ? $features : array();

	$is_open = lis_directory_is_listing_open_now( $post_id );
	$week    = lis_directory_get_listing_week_hours( $post_id );

	$video_embed_url = lis_directory_video_embed_url( $video_url );
	?>
	<div class="lis-listing-single">

		<?php if ( ! empty( $gallery_ids ) ) : ?>
			<div class="lis-listing-gallery-strip">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php echo wp_get_attachment_image( $attachment_id, 'large', false, array( 'class' => 'lis-listing-gallery-img' ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php elseif ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'class' => 'lis-listing-single-thumb' ) ); ?>
		<?php endif; ?>

		<div class="lis-listing-header">
			<h1 class="lis-listing-title"><?php the_title(); ?></h1>
			<div class="lis-listing-header-meta">
				<?php if ( $price ) : ?><span class="lis-listing-price"><?php echo esc_html( $price ); ?></span><?php endif; ?>
				<?php if ( $category ) : ?><a class="lis-listing-category-badge" href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endif; ?>
				<?php if ( null !== $is_open ) : ?>
					<span class="lis-listing-open-status <?php echo $is_open ? 'is-open' : 'is-closed'; ?>">
						<?php echo $is_open ? 'Open now' : 'Closed now'; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="lis-listing-columns">
			<div class="lis-listing-main">

				<?php if ( $address || $phone || $website || $email ) : ?>
					<div class="lis-listing-meta">
						<?php if ( $address ) : ?><div><?php echo esc_html( $address ); ?></div><?php endif; ?>
						<?php if ( $phone ) : ?><div><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></div><?php endif; ?>
						<?php if ( $website ) : ?><div><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $website ); ?></a></div><?php endif; ?>
						<?php if ( $email ) : ?><div><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></div><?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="lis-listing-section">
					<h2>Description</h2>
					<div class="lis-listing-content"><?php the_content(); ?></div>
				</div>

				<?php if ( ! empty( $features ) ) : ?>
					<div class="lis-listing-section">
						<h2>Features</h2>
						<ul class="lis-listing-features">
							<?php foreach ( $features as $feature ) : ?>
								<li><?php echo esc_html( $feature->name ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $video_embed_url ) : ?>
					<div class="lis-listing-section">
						<h2>Listing Video</h2>
						<div class="lis-listing-video-wrap">
							<iframe src="<?php echo esc_url( $video_embed_url ); ?>" title="Listing video" frameborder="0" allowfullscreen loading="lazy"></iframe>
						</div>
					</div>
				<?php endif; ?>

			</div>

			<div class="lis-listing-sidebar">
				<?php if ( array_filter( $week ) ) : ?>
					<div class="lis-listing-hours-box">
						<h2>Business Hours <?php if ( null !== $is_open ) : ?><span class="lis-listing-open-status <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open now' : 'Closed'; ?></span><?php endif; ?></h2>
						<table class="lis-listing-hours-table-display">
							<?php foreach ( LIS_DIRECTORY_WEEKDAYS as $day => $label ) : ?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td>
										<?php if ( $week[ $day ] ) : ?>
											<?php echo esc_html( lis_directory_format_time( $week[ $day ]['open'] ) . ' – ' . lis_directory_format_time( $week[ $day ]['close'] ) ); ?>
										<?php else : ?>
											Closed
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
	<?php
endwhile;

get_footer();
