<?php
/**
 * Meta boxes for `lis_listing`: contact info, price, video, and a photo
 * gallery. Business hours (and the "open now" logic derived from them) live
 * in includes/listings-hours.php — big enough a concept to warrant its own
 * file. Reviews/ratings and appointment booking are deliberately NOT here —
 * those need their own real data model and moderation/submission flow, not
 * a field bolted onto this meta box; still deferred.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_listing_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_listing_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_listing_meta_box' );
add_filter( 'manage_lis_listing_posts_columns', 'lis_directory_listing_admin_columns' );
add_action( 'manage_lis_listing_posts_custom_column', 'lis_directory_listing_admin_column_content', 10, 2 );

function lis_directory_listing_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['lis_listing_type'] = 'Directory';
		}
	}
	return $new;
}

function lis_directory_listing_admin_column_content( $column, $post_id ) {
	if ( 'lis_listing_type' === $column ) {
		$types = lis_directory_get_listing_types();
		echo esc_html( $types[ lis_directory_get_listing_type( $post_id ) ] );
	}
}

/**
 * The four directory types Directorist runs live on this site (Local
 * Business, Real Estate Sale, Real Estate Rent, Job Listing) — see
 * DIRECTORIST_PARITY_PLAN.md. A plain post-meta select rather than a
 * taxonomy: Directorist's own "Add Listing" screen uses a single `<select>`
 * ("Directory: ▾"), not checkboxes, and a listing only ever has exactly one
 * type — a taxonomy would allow (and need UI to prevent) picking more than
 * one for no reason. `local-business` is the default so the one pre-existing
 * listing (Talus Rock Retreat, saved before this field existed) still
 * resolves to something sane with no data migration needed.
 */
function lis_directory_get_listing_types() {
	return array(
		'local-business'    => 'Local Business',
		'real-estate-sale'  => 'Real Estate — For Sale',
		'real-estate-rent'  => 'Real Estate — For Rent',
		'job-listing'       => 'Job Listing',
	);
}

function lis_directory_get_listing_type( $post_id ) {
	$type  = get_post_meta( $post_id, '_lis_listing_type', true );
	$types = lis_directory_get_listing_types();
	return isset( $types[ $type ] ) ? $type : 'local-business';
}

function lis_directory_get_employment_types() {
	return array(
		'full-time' => 'Full-time',
		'part-time' => 'Part-time',
		'contract'  => 'Contract',
		'seasonal'  => 'Seasonal',
	);
}

function lis_directory_register_listing_meta() {
	$string_fields = array(
		'_lis_listing_type'         => 'string', // local-business | real-estate-sale | real-estate-rent | job-listing.
		'_lis_listing_address'      => 'string',
		'_lis_listing_phone'        => 'string',
		'_lis_listing_website'      => 'string',
		'_lis_listing_email'        => 'string',
		'_lis_listing_price'        => 'string',
		'_lis_listing_video_url'    => 'string',
		'_lis_listing_gallery_ids'  => 'string', // Comma-separated attachment IDs.
		'_lis_listing_services'     => 'string', // One service per line.
		'_lis_listing_facebook'     => 'string',
		'_lis_listing_instagram'    => 'string',
		'_lis_listing_twitter'      => 'string',
		'_lis_listing_linkedin'     => 'string',
		// Real Estate (Sale/Rent) only.
		'_lis_listing_bedrooms'     => 'string',
		'_lis_listing_bathrooms'    => 'string',
		'_lis_listing_sqft'         => 'string',
		// Job Listing only.
		'_lis_listing_salary'          => 'string',
		'_lis_listing_employment_type' => 'string',
	);

	foreach ( $string_fields as $key => $type ) {
		register_post_meta( 'lis_listing', $key, array(
			'type'          => $type,
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}

function lis_directory_add_listing_meta_box() {
	add_meta_box(
		'lis_listing_details',
		'Listing Details',
		'lis_directory_render_listing_meta_box',
		'lis_listing',
		'normal',
		'high'
	);
}

function lis_directory_render_listing_meta_box( $post ) {
	wp_nonce_field( 'lis_listing_save_meta', 'lis_listing_meta_nonce' );

	$type       = lis_directory_get_listing_type( $post->ID );
	$address    = get_post_meta( $post->ID, '_lis_listing_address', true );
	$phone      = get_post_meta( $post->ID, '_lis_listing_phone', true );
	$website    = get_post_meta( $post->ID, '_lis_listing_website', true );
	$email      = get_post_meta( $post->ID, '_lis_listing_email', true );
	$price      = get_post_meta( $post->ID, '_lis_listing_price', true );
	$video_url  = get_post_meta( $post->ID, '_lis_listing_video_url', true );
	$gallery_raw = get_post_meta( $post->ID, '_lis_listing_gallery_ids', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
	$services   = get_post_meta( $post->ID, '_lis_listing_services', true );
	$facebook   = get_post_meta( $post->ID, '_lis_listing_facebook', true );
	$instagram  = get_post_meta( $post->ID, '_lis_listing_instagram', true );
	$twitter    = get_post_meta( $post->ID, '_lis_listing_twitter', true );
	$linkedin   = get_post_meta( $post->ID, '_lis_listing_linkedin', true );
	$bedrooms   = get_post_meta( $post->ID, '_lis_listing_bedrooms', true );
	$bathrooms  = get_post_meta( $post->ID, '_lis_listing_bathrooms', true );
	$sqft       = get_post_meta( $post->ID, '_lis_listing_sqft', true );
	$salary     = get_post_meta( $post->ID, '_lis_listing_salary', true );
	$employment_type = get_post_meta( $post->ID, '_lis_listing_employment_type', true );
	?>
	<table class="form-table">
		<tr>
			<th><label for="lis_listing_type">Directory</label></th>
			<td>
				<select id="lis_listing_type" name="lis_listing_type">
					<?php foreach ( lis_directory_get_listing_types() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Which of the four live directory types this listing belongs to.</p>
			</td>
		</tr>
		<tr class="lis-listing-type-fields lis-listing-type-fields--real-estate-sale lis-listing-type-fields--real-estate-rent">
			<th>Bedrooms / Bathrooms / Sq Ft</th>
			<td>
				<input type="text" id="lis_listing_bedrooms" name="lis_listing_bedrooms" class="small-text" placeholder="Beds" value="<?php echo esc_attr( $bedrooms ); ?>" />
				<input type="text" id="lis_listing_bathrooms" name="lis_listing_bathrooms" class="small-text" placeholder="Baths" value="<?php echo esc_attr( $bathrooms ); ?>" />
				<input type="text" id="lis_listing_sqft" name="lis_listing_sqft" class="small-text" placeholder="Sq Ft" value="<?php echo esc_attr( $sqft ); ?>" />
				<p class="description">Real Estate only.</p>
			</td>
		</tr>
		<tr class="lis-listing-type-fields lis-listing-type-fields--job-listing">
			<th><label for="lis_listing_salary">Salary</label></th>
			<td>
				<input type="text" id="lis_listing_salary" name="lis_listing_salary" class="regular-text" placeholder="e.g. $45,000&ndash;$55,000/yr" value="<?php echo esc_attr( $salary ); ?>" />
				<p class="description">Job Listing only.</p>
			</td>
		</tr>
		<tr class="lis-listing-type-fields lis-listing-type-fields--job-listing">
			<th><label for="lis_listing_employment_type">Employment Type</label></th>
			<td>
				<select id="lis_listing_employment_type" name="lis_listing_employment_type">
					<option value="">— Select —</option>
					<?php foreach ( lis_directory_get_employment_types() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $employment_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Job Listing only. Application contact uses the Email/Website fields below.</p>
			</td>
		</tr>
		<tr>
			<th><label for="lis_listing_address">Address</label></th>
			<td><input type="text" id="lis_listing_address" name="lis_listing_address" class="large-text" value="<?php echo esc_attr( $address ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_phone">Phone</label></th>
			<td><input type="text" id="lis_listing_phone" name="lis_listing_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_website">Website</label></th>
			<td><input type="url" id="lis_listing_website" name="lis_listing_website" class="large-text" value="<?php echo esc_attr( $website ); ?>" placeholder="https://" /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_email">Email</label></th>
			<td><input type="email" id="lis_listing_email" name="lis_listing_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_price">Price</label></th>
			<td><input type="text" id="lis_listing_price" name="lis_listing_price" class="regular-text" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g. $150.00 or $$ " /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_video_url">Video URL</label></th>
			<td><input type="url" id="lis_listing_video_url" name="lis_listing_video_url" class="large-text" value="<?php echo esc_attr( $video_url ); ?>" placeholder="YouTube or Vimeo link" /></td>
		</tr>
		<tr>
			<th>Gallery</th>
			<td>
				<input type="hidden" id="lis_listing_gallery_ids" name="lis_listing_gallery_ids" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>" />
				<div id="lis_listing_gallery_preview" class="lis-listing-gallery-preview">
					<?php foreach ( $gallery_ids as $attachment_id ) : ?>
						<?php lis_directory_render_gallery_thumb( $attachment_id ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button lis-listing-gallery-select">Select Images</button>
				<p class="description">Pick as many as you like — shown as a strip at the top of the listing page.</p>
			</td>
		</tr>
		<tr>
			<th><label for="lis_listing_services">Services</label></th>
			<td>
				<textarea id="lis_listing_services" name="lis_listing_services" class="large-text" rows="4"><?php echo esc_textarea( $services ); ?></textarea>
				<p class="description">One service per line.</p>
			</td>
		</tr>
		<tr>
			<th>Social Media</th>
			<td>
				<p><label for="lis_listing_facebook">Facebook</label><br />
				<input type="url" id="lis_listing_facebook" name="lis_listing_facebook" class="large-text" value="<?php echo esc_attr( $facebook ); ?>" placeholder="https://facebook.com/..." /></p>
				<p><label for="lis_listing_instagram">Instagram</label><br />
				<input type="url" id="lis_listing_instagram" name="lis_listing_instagram" class="large-text" value="<?php echo esc_attr( $instagram ); ?>" placeholder="https://instagram.com/..." /></p>
				<p><label for="lis_listing_twitter">X / Twitter</label><br />
				<input type="url" id="lis_listing_twitter" name="lis_listing_twitter" class="large-text" value="<?php echo esc_attr( $twitter ); ?>" placeholder="https://x.com/..." /></p>
				<p><label for="lis_listing_linkedin">LinkedIn</label><br />
				<input type="url" id="lis_listing_linkedin" name="lis_listing_linkedin" class="large-text" value="<?php echo esc_attr( $linkedin ); ?>" placeholder="https://linkedin.com/..." /></p>
			</td>
		</tr>
	</table>
	<script>
	( function () {
		var select = document.getElementById( 'lis_listing_type' );
		if ( ! select ) {
			return;
		}
		function sync() {
			var type = select.value;
			document.querySelectorAll( '.lis-listing-type-fields' ).forEach( function ( row ) {
				row.style.display = row.classList.contains( 'lis-listing-type-fields--' + type ) ? '' : 'none';
			} );
		}
		select.addEventListener( 'change', sync );
		sync();
	}() );
	</script>
	<?php
}

function lis_directory_render_gallery_thumb( $attachment_id ) {
	$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
	if ( ! $url ) {
		return;
	}
	?>
	<span class="lis-listing-gallery-thumb" data-id="<?php echo esc_attr( $attachment_id ); ?>">
		<img src="<?php echo esc_url( $url ); ?>" />
		<button type="button" class="lis-listing-gallery-remove" aria-label="Remove">&times;</button>
	</span>
	<?php
}

function lis_directory_save_listing_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_listing_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_meta_nonce'], 'lis_listing_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['lis_listing_address'] ) ) {
		update_post_meta( $post_id, '_lis_listing_address', sanitize_text_field( wp_unslash( $_POST['lis_listing_address'] ) ) );
	}
	if ( isset( $_POST['lis_listing_phone'] ) ) {
		update_post_meta( $post_id, '_lis_listing_phone', sanitize_text_field( wp_unslash( $_POST['lis_listing_phone'] ) ) );
	}
	if ( isset( $_POST['lis_listing_website'] ) ) {
		update_post_meta( $post_id, '_lis_listing_website', esc_url_raw( wp_unslash( $_POST['lis_listing_website'] ) ) );
	}
	if ( isset( $_POST['lis_listing_email'] ) ) {
		update_post_meta( $post_id, '_lis_listing_email', sanitize_email( wp_unslash( $_POST['lis_listing_email'] ) ) );
	}
	if ( isset( $_POST['lis_listing_price'] ) ) {
		update_post_meta( $post_id, '_lis_listing_price', sanitize_text_field( wp_unslash( $_POST['lis_listing_price'] ) ) );
	}
	if ( isset( $_POST['lis_listing_video_url'] ) ) {
		update_post_meta( $post_id, '_lis_listing_video_url', esc_url_raw( wp_unslash( $_POST['lis_listing_video_url'] ) ) );
	}
	if ( isset( $_POST['lis_listing_gallery_ids'] ) ) {
		$ids = array_filter( array_map( 'absint', explode( ',', wp_unslash( $_POST['lis_listing_gallery_ids'] ) ) ) );
		update_post_meta( $post_id, '_lis_listing_gallery_ids', implode( ',', $ids ) );
	}
	if ( isset( $_POST['lis_listing_services'] ) ) {
		update_post_meta( $post_id, '_lis_listing_services', sanitize_textarea_field( wp_unslash( $_POST['lis_listing_services'] ) ) );
	}
	foreach ( array( 'facebook', 'instagram', 'twitter', 'linkedin' ) as $network ) {
		$field = "lis_listing_{$network}";
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, "_{$field}", esc_url_raw( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
	if ( isset( $_POST['lis_listing_type'] ) ) {
		$type  = sanitize_key( wp_unslash( $_POST['lis_listing_type'] ) );
		$types = lis_directory_get_listing_types();
		update_post_meta( $post_id, '_lis_listing_type', isset( $types[ $type ] ) ? $type : 'local-business' );
	}
	foreach ( array( 'bedrooms', 'bathrooms', 'sqft', 'salary' ) as $field ) {
		$key = "lis_listing_{$field}";
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, "_{$key}", sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['lis_listing_employment_type'] ) ) {
		$employment_type = sanitize_key( wp_unslash( $_POST['lis_listing_employment_type'] ) );
		$valid           = lis_directory_get_employment_types();
		update_post_meta( $post_id, '_lis_listing_employment_type', isset( $valid[ $employment_type ] ) ? $employment_type : '' );
	}
}

/**
 * A plain YouTube/Vimeo watch/share URL isn't directly embeddable in an
 * <iframe> — converts to the embed-URL form, or returns '' if the URL
 * doesn't match either pattern (nothing embeds rather than embedding
 * something wrong).
 */
function lis_directory_video_embed_url( $url ) {
	if ( ! $url ) {
		return '';
	}

	if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
		return 'https://www.youtube.com/embed/' . $m[1];
	}

	if ( preg_match( '~vimeo\.com/(\d+)~', $url, $m ) ) {
		return 'https://player.vimeo.com/video/' . $m[1];
	}

	return '';
}
