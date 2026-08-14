<?php
/**
 * Single-listing template for `lis_listing`. Routed from
 * includes/listings-template.php unless a theme provides single-lis_listing.php.
 *
 * Structure follows the reference layout the client pointed to, expanded
 * over several passes to match Directorist's own real field set (checked
 * live via its Add Listing pricing-plan page, not guessed): gallery strip,
 * header (title/category/badges/open-status/bookmark/share), two-column
 * body (contact/description/features/services/video/social/reviews on the
 * left, business hours on the right), a Report/Claim block. FAQs (admin-
 * managed, dynamic add/remove rows) render as a plain accordion. An
 * embedded map (Maps JavaScript API + client-side Geocoder — geocodes
 * the plain address string in-browser, no lat/long stored on the
 * listing) shows whenever both an address and a Maps API key
 * (Settings > LIS Directory Settings, scoped to Maps JavaScript API +
 * Geocoding API in Google Cloud Console) are present; otherwise the
 * address just links out to Google Maps like before. Started as the
 * simpler Maps Embed API (an iframe, no JS SDK needed) but the API key
 * provided was scoped to Maps JavaScript API + Geocoding API instead, so
 * this uses those.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	$type      = lis_directory_get_listing_type( $post_id );
	$address   = get_post_meta( $post_id, '_lis_listing_address', true );
	$phone     = get_post_meta( $post_id, '_lis_listing_phone', true );
	$website   = get_post_meta( $post_id, '_lis_listing_website', true );
	$email     = get_post_meta( $post_id, '_lis_listing_email', true );
	$price     = get_post_meta( $post_id, '_lis_listing_price', true );
	$video_url = get_post_meta( $post_id, '_lis_listing_video_url', true );
	$bedrooms  = get_post_meta( $post_id, '_lis_listing_bedrooms', true );
	$bathrooms = get_post_meta( $post_id, '_lis_listing_bathrooms', true );
	$sqft      = get_post_meta( $post_id, '_lis_listing_sqft', true );
	$salary    = get_post_meta( $post_id, '_lis_listing_salary', true );
	$employment_types = lis_directory_get_employment_types();
	$employment_type  = get_post_meta( $post_id, '_lis_listing_employment_type', true );
	$faqs      = lis_directory_get_listing_faqs( $post_id );
	$services  = get_post_meta( $post_id, '_lis_listing_services', true );
	$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );
	$service_lines = $services ? array_filter( array_map( 'trim', explode( "\n", $services ) ) ) : array();

	$social = array(
		'Facebook'  => get_post_meta( $post_id, '_lis_listing_facebook', true ),
		'Instagram' => get_post_meta( $post_id, '_lis_listing_instagram', true ),
		'X / Twitter' => get_post_meta( $post_id, '_lis_listing_twitter', true ),
		'LinkedIn'  => get_post_meta( $post_id, '_lis_listing_linkedin', true ),
	);
	$social = array_filter( $social );

	$gallery_raw = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();

	$categories = get_the_terms( $post_id, 'lis_listing_category' );
	$category   = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;

	$features = get_the_terms( $post_id, 'lis_listing_feature' );
	$features = ( $features && ! is_wp_error( $features ) ) ? $features : array();
	// Hide any feature the listing owner suggested that an admin hasn't
	// approved yet, so an unreviewed suggestion isn't shown publicly.
	$features = lis_directory_filter_public_features( $features );

	$is_open  = lis_directory_is_listing_open_now( $post_id );
	$week     = lis_directory_get_listing_week_hours( $post_id );
	$featured = (bool) get_post_meta( $post_id, '_lis_listing_featured', true );
	$verified = (bool) get_post_meta( $post_id, '_lis_listing_verified', true );
	$popular  = lis_directory_is_listing_popular( $post_id );
	$sold     = ( 'real-estate-sale' === $type || 'real-estate-rent' === $type ) && (bool) get_post_meta( $post_id, '_lis_listing_sold', true );

	$avg_rating   = lis_directory_get_listing_average_rating( $post_id );
	$review_count = lis_directory_get_listing_review_count( $post_id );

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
			<div>
				<h1 class="lis-listing-title"><?php the_title(); ?></h1>
				<?php $lis_tagline = get_post_meta( get_the_ID(), '_lis_listing_tagline', true ); ?>
				<?php if ( $lis_tagline ) : ?>
					<p class="lis-listing-tagline"><?php echo esc_html( $lis_tagline ); ?></p>
				<?php endif; ?>
				<div class="lis-listing-header-meta">
					<?php if ( $sold ) : ?><span class="lis-listing-badge lis-listing-badge--sold"><?php echo 'real-estate-rent' === $type ? 'Rented' : 'Sold'; ?></span><?php endif; ?>
					<?php if ( $featured ) : ?><span class="lis-listing-badge lis-listing-badge--featured">Featured</span><?php endif; ?>
					<?php if ( $popular ) : ?><span class="lis-listing-badge lis-listing-badge--popular">Popular</span><?php endif; ?>
					<?php if ( $verified ) : ?><span class="lis-listing-badge lis-listing-badge--verified">✓ Owner Verified</span><?php endif; ?>
					<?php if ( $category ) : ?><a class="lis-listing-category-badge" href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endif; ?>
					<?php if ( null !== $is_open ) : ?>
						<span class="lis-listing-open-status <?php echo $is_open ? 'is-open' : 'is-closed'; ?>">
							<?php echo $is_open ? 'Open now' : 'Closed now'; ?>
						</span>
					<?php endif; ?>
					<?php if ( $avg_rating ) : ?>
						<span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?> <?php echo esc_html( $avg_rating ); ?> (<?php echo (int) $review_count; ?>)</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="lis-listing-header-actions">
				<button type="button" class="lis-listing-bookmark-btn">☆ Save</button>
				<button type="button" class="lis-listing-share-btn">Share</button>
			</div>
		</div>

		<?php
		$offer_url = '';
		if ( function_exists( 'lis_directory_listing_is_active_showcase' ) && lis_directory_listing_is_active_showcase( $post_id ) ) {
			$offer_url = get_post_meta( $post_id, '_lis_listing_offer_url', true );
		}
		$offer_label = get_post_meta( $post_id, '_lis_listing_offer_label', true );
		$offer_label = $offer_label ? $offer_label : 'View Offer';
		?>
		<?php if ( $offer_url ) : ?>
			<div class="lis-listing-offer-banner">
				<span class="lis-listing-offer-eyebrow">Vendor Showcase Offer</span>
				<a class="lis-listing-offer-button" href="<?php echo esc_url( $offer_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $offer_label ); ?></a>
			</div>
		<?php endif; ?>

		<div class="lis-listing-columns">
			<div class="lis-listing-main">

				<?php
				if ( function_exists( 'lis_directory_render_claim_box' ) ) {
					echo lis_directory_render_claim_box( $post_id ); // phpcs:ignore -- escaped inside helper.
				}
				?>

				<?php if ( 'real-estate-sale' === $type || 'real-estate-rent' === $type ) : ?>
					<?php if ( $bedrooms || $bathrooms || $sqft ) : ?>
						<div class="lis-listing-realestate-facts">
							<?php if ( $bedrooms ) : ?><span><strong><?php echo esc_html( $bedrooms ); ?></strong> Beds</span><?php endif; ?>
							<?php if ( $bathrooms ) : ?><span><strong><?php echo esc_html( $bathrooms ); ?></strong> Baths</span><?php endif; ?>
							<?php if ( $sqft ) : ?><span><strong><?php echo esc_html( $sqft ); ?></strong> Sq Ft</span><?php endif; ?>
						</div>
					<?php endif; ?>
				<?php elseif ( 'job-listing' === $type ) : ?>
					<?php if ( $salary || $employment_type ) : ?>
						<div class="lis-listing-job-facts">
							<?php if ( $salary ) : ?><span class="lis-listing-job-salary"><?php echo esc_html( $salary ); ?></span><?php endif; ?>
							<?php if ( $employment_type && isset( $employment_types[ $employment_type ] ) ) : ?><span class="lis-listing-job-type"><?php echo esc_html( $employment_types[ $employment_type ] ); ?></span><?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="lis-listing-section">
					<h2>Description</h2>
					<div class="lis-listing-content"><?php the_content(); ?></div>
				</div>

				<?php if ( ! empty( $service_lines ) ) : ?>
					<div class="lis-listing-section">
						<h2>Services</h2>
						<ul class="lis-listing-services-list">
							<?php foreach ( $service_lines as $service ) : ?>
								<li><?php echo esc_html( $service ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

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

				<?php if ( ! empty( $social ) ) : ?>
					<div class="lis-listing-section">
						<h2>Social</h2>
						<div class="lis-listing-social-links">
							<?php foreach ( $social as $label => $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $faqs ) ) : ?>
					<div class="lis-listing-section">
						<h2>FAQs</h2>
						<div class="lis-listing-faqs">
							<?php foreach ( $faqs as $faq ) : ?>
								<details class="lis-listing-faq">
									<summary><?php echo esc_html( $faq['question'] ); ?></summary>
									<p><?php echo esc_html( $faq['answer'] ); ?></p>
								</details>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="lis-listing-section lis-listing-flag-actions">
					<?php if ( is_user_logged_in() ) : ?>
						<?php if ( ! $verified ) : ?>
							<details class="lis-listing-flag-form">
								<summary>Claim this listing</summary>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="lis_directory_submit_claim" />
									<input type="hidden" name="listing_id" value="<?php echo (int) $post_id; ?>" />
									<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>" />
									<?php wp_nonce_field( 'lis_listing_claim', 'lis_listing_claim_nonce' ); ?>
									<p><label>Tell us how you're connected to this business (optional)<br /><textarea name="message" rows="2"></textarea></label></p>
									<p><button type="submit">Submit Claim</button></p>
								</form>
							</details>
						<?php endif; ?>
						<details class="lis-listing-flag-form">
							<summary>Report this listing</summary>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="lis_directory_submit_report" />
								<input type="hidden" name="listing_id" value="<?php echo (int) $post_id; ?>" />
								<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>" />
								<?php wp_nonce_field( 'lis_listing_report', 'lis_listing_report_nonce' ); ?>
								<p><label>What's wrong with this listing?<br /><textarea name="reason" rows="2" required></textarea></label></p>
								<p><button type="submit">Submit Report</button></p>
							</form>
						</details>
					<?php else : ?>
						<p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to claim or report this listing.</p>
					<?php endif; ?>
				</div>

				<?php /* #jp-relatedposts (Jetpack's own auto-injected placeholder, populated
				client-side) is relocated here from inside the Description block by
				assets/js/listing-related-posts.js — see that file for why. This
				marker just gives the script a fixed anchor to insert after. */ ?>
				<span id="lis-listing-related-anchor"></span>

				<div class="lis-listing-section">
					<h2>Reviews <?php if ( $avg_rating ) : ?><span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?> <?php echo esc_html( $avg_rating ); ?> (<?php echo (int) $review_count; ?> review<?php echo 1 === $review_count ? '' : 's'; ?>)</span><?php endif; ?></h2>
					<?php
					if ( comments_open() || $review_count ) {
						comments_template();
					}
					?>
				</div>

			</div>

			<div class="lis-listing-sidebar">
				<?php if ( $address || $phone || $website || $email ) : ?>
					<div class="lis-listing-meta">
						<?php if ( $address ) : ?>
							<div class="lis-listing-meta-row">
								<span class="lis-listing-meta-label">Address</span>
								<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $address ); ?></a>
							</div>
						<?php endif; ?>
						<?php if ( $phone ) : ?>
							<div class="lis-listing-meta-row">
								<span class="lis-listing-meta-label">Phone</span>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
							</div>
						<?php endif; ?>
						<?php if ( $website ) : ?>
							<div class="lis-listing-meta-row">
								<span class="lis-listing-meta-label">Website</span>
								<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $website ); ?></a>
							</div>
						<?php endif; ?>
						<?php if ( $email ) : ?>
							<div class="lis-listing-meta-row">
								<span class="lis-listing-meta-label">Email</span>
								<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $address && $maps_api_key ) : ?>
					<div class="lis-listing-map">
						<div class="lis-listing-map-canvas" data-address="<?php echo esc_attr( $address ); ?>"></div>
					</div>
					<script>
					window.lisDirectoryInitMaps = window.lisDirectoryInitMaps || function () {
						document.querySelectorAll( '.lis-listing-map-canvas[data-address]' ).forEach( function ( el ) {
							var geocoder = new google.maps.Geocoder();
							geocoder.geocode( { address: el.dataset.address }, function ( results, status ) {
								if ( 'OK' !== status || ! results[0] ) {
									return;
								}
								var map = new google.maps.Map( el, { center: results[0].geometry.location, zoom: 15 } );
								new google.maps.Marker( { map: map, position: results[0].geometry.location } );
							} );
						} );
					};
					</script>
					<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $maps_api_key ); ?>&callback=lisDirectoryInitMaps&loading=async" async defer></script>
				<?php endif; ?>

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
