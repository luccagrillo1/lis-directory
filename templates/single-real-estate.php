<?php
/**
 * Single template for a real-estate `lis_listing` (For Rent / For Sale).
 * Routed from includes/listings-template.php when the listing's type is
 * real-estate-rent / real-estate-sale, unless a theme provides
 * single-lis_listing-real-estate.php.
 *
 * Same frame as templates/single-listing.php (gallery, header, two columns,
 * contact box + map in the sidebar, report form) minus the business-only parts:
 * hours, open-now, services, features, reviews, claim, Vendor Showcase offer.
 * JSON-LD + noindex for sold/rented are printed from includes/real-estate.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$type    = lis_directory_get_listing_type( $post_id );
	$short   = lis_directory_get_real_estate_types()[ $type ];
	$rent    = 'rent' === $short;

	$address      = get_post_meta( $post_id, '_lis_listing_address', true );
	$phone        = get_post_meta( $post_id, '_lis_listing_phone', true );
	$email        = get_post_meta( $post_id, '_lis_listing_email', true );
	$video_url    = get_post_meta( $post_id, '_lis_listing_video_url', true );
	$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );
	$gallery_raw  = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
	$gallery_ids  = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
	$price        = lis_directory_format_real_estate_price( $post_id );
	$facts        = lis_directory_get_real_estate_facts_line( $post_id );
	$area         = lis_directory_get_real_estate_area_label( $post_id );
	$status       = lis_directory_get_real_estate_status_label( $post_id );
	$archive_url  = lis_directory_get_real_estate_page_url( $short );
	$map_query    = $address ? $address : ( $area && 'Other' !== $area ? $area . ', Idaho' : '' );

	// The details list, in reading order. Beds/baths/sq ft have their own facts
	// bar above, so they aren't repeated here.
	$detail_keys = $rent
		? array( 'rent', 'deposit', 'available_date', 'lease_term', 'pets', 'utilities', 'area' )
		: array( 'sale_price', 'sale_status', 'lot_size', 'year_built', 'area' );
	$fields  = lis_directory_get_real_estate_fields();
	$details = array();
	foreach ( $detail_keys as $key ) {
		$value = lis_directory_get_real_estate_display_value( $post_id, $key );
		if ( '' !== $value ) {
			$details[ $fields[ $key ]['label'] ] = $value;
		}
	}

	$video_embed_url = lis_directory_video_embed_url( $video_url );
	?>
	<div class="lis-listing-single lis-re-single">

		<?php if ( $archive_url ) : ?>
			<p class="lis-re-back"><a href="<?php echo esc_url( $archive_url ); ?>">&larr; <?php echo $rent ? 'All rentals' : 'All homes for sale'; ?></a></p>
		<?php endif; ?>

		<?php if ( ! empty( $gallery_ids ) ) : ?>
			<div class="lis-listing-gallery-strip">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php echo wp_get_attachment_image( $attachment_id, 'large', false, array( 'class' => 'lis-listing-gallery-img', 'alt' => lis_directory_listing_image_alt( $attachment_id, $post_id ) ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php elseif ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'class' => 'lis-listing-single-thumb', 'alt' => lis_directory_listing_image_alt( get_post_thumbnail_id(), $post_id ) ) ); ?>
		<?php else : ?>
			<?php echo lis_directory_render_listing_fallback_image( $post_id, 'single' ); // phpcs:ignore -- escaped inside helper. ?>
		<?php endif; ?>

		<div class="lis-listing-header">
			<div>
				<?php if ( $price ) : ?>
					<p class="lis-re-price"><?php echo esc_html( $price ); ?></p>
				<?php endif; ?>
				<h1 class="lis-listing-title"><?php the_title(); ?></h1>
				<div class="lis-listing-header-meta">
					<?php if ( $status ) : ?>
						<span class="lis-listing-badge <?php echo 'Sale pending' === $status ? 'lis-re-badge--pending' : 'lis-listing-badge--sold'; ?>"><?php echo esc_html( $status ); ?></span>
					<?php endif; ?>
					<span class="lis-listing-category-badge"><?php echo $rent ? 'For rent' : 'For sale'; ?></span>
					<?php if ( $area ) : ?><span class="lis-re-area"><?php echo esc_html( $area ); ?></span><?php endif; ?>
				</div>
			</div>
			<div class="lis-listing-header-actions">
				<button type="button" class="lis-listing-share-btn">Share</button>
			</div>
		</div>

		<?php if ( $facts ) : ?>
			<div class="lis-listing-realestate-facts">
				<?php foreach ( array( 'bedrooms' => 'Beds', 'bathrooms' => 'Baths', 'sqft' => 'Sq Ft' ) as $key => $label ) : ?>
					<?php $value = lis_directory_get_real_estate_display_value( $post_id, $key ); ?>
					<?php if ( '' !== $value ) : ?>
						<span><strong><?php echo esc_html( 'bedrooms' === $key && '0' === $value ? 'Studio' : $value ); ?></strong><?php echo 'bedrooms' === $key && '0' === $value ? '' : ' ' . esc_html( $label ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="lis-listing-columns">
			<div class="lis-listing-main">

				<?php
				$has_description = '' !== trim( wp_strip_all_tags( get_the_content() ) );
				?>
				<?php if ( $has_description ) : ?>
					<div class="lis-listing-section">
						<h2>About this <?php echo $rent ? 'rental' : 'property'; ?></h2>
						<div class="lis-listing-content"><?php the_content(); ?></div>
					</div>
				<?php endif; ?>

				<?php if ( $details ) : ?>
					<div class="lis-listing-section">
						<h2>Details</h2>
						<dl class="lis-re-details">
							<?php foreach ( $details as $label => $value ) : ?>
								<div class="lis-re-details-row">
									<dt><?php echo esc_html( $label ); ?></dt>
									<dd><?php echo esc_html( $value ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					</div>
				<?php endif; ?>

				<?php if ( $video_embed_url ) : ?>
					<div class="lis-listing-section">
						<h2>Video</h2>
						<div class="lis-listing-video-wrap">
							<iframe src="<?php echo esc_url( $video_embed_url ); ?>" title="Listing video" frameborder="0" allowfullscreen loading="lazy"></iframe>
						</div>
					</div>
				<?php endif; ?>

				<div class="lis-listing-section lis-listing-flag-actions">
					<?php if ( is_user_logged_in() ) : ?>
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
						<p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to report this listing.</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="lis-listing-sidebar">
				<div class="lis-listing-meta">
					<?php if ( $address || $area ) : ?>
						<div class="lis-listing-meta-row">
							<span class="lis-listing-meta-label"><?php echo $address ? 'Address' : 'Area'; ?></span>
							<?php if ( $map_query ) : ?>
								<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $map_query ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $address ? $address : $area ); ?></a>
							<?php else : ?>
								<span><?php echo esc_html( $area ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<div class="lis-listing-meta-row">
							<span class="lis-listing-meta-label">Phone</span>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
						</div>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<div class="lis-listing-meta-row">
							<span class="lis-listing-meta-label">Email</span>
							<a href="<?php echo esc_attr( 'mailto:' . antispambot( $email ) . '?subject=' . rawurlencode( get_the_title() ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $map_query && $maps_api_key ) : ?>
					<div class="lis-listing-map">
						<div class="lis-listing-map-canvas" data-address="<?php echo esc_attr( $map_query ); ?>" data-zoom="<?php echo $address ? 15 : 12; ?>"></div>
					</div>
					<script>
					window.lisDirectoryInitMaps = window.lisDirectoryInitMaps || function () {
						document.querySelectorAll( '.lis-listing-map-canvas[data-address]' ).forEach( function ( el ) {
							var geocoder = new google.maps.Geocoder();
							geocoder.geocode( { address: el.dataset.address }, function ( results, status ) {
								if ( 'OK' !== status || ! results[0] ) {
									return;
								}
								var map = new google.maps.Map( el, { center: results[0].geometry.location, zoom: parseInt( el.dataset.zoom || '15', 10 ) } );
								new google.maps.Marker( { map: map, position: results[0].geometry.location } );
							} );
						} );
					};
					</script>
					<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $maps_api_key ); ?>&callback=lisDirectoryInitMaps&loading=async" async defer></script>
				<?php endif; ?>
			</div>
		</div>

	</div>
	<?php
endwhile;

get_footer();
