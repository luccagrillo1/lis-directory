<?php
/**
 * Front-end listing submission: [lis_listing_submit] shortcode +
 * admin-post.php handler. Same pattern as includes/submission.php (the
 * Vendor Showcase submission form) — requires a WordPress account,
 * honeypot + nonce, real server-side upload validation.
 *
 * Unlike the vendor form, this doesn't need a custom '_lis_pv_status'-style
 * workflow meta: `lis_listing` has no "one active per category" concept to
 * enforce, so submissions land as native WordPress `pending` post status —
 * an editor reviews and clicks Publish in the normal admin editor.
 *
 * v0.15.0 shipped with a deliberately minimal field set (one photo, no
 * hours/features) to keep the form short. Widened in v0.15.1 to cover
 * business hours and features too, on request — those are real parts of a
 * listing, not admin-only follow-ups, once the form exists at all.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_SUBMIT_MAX_PHOTOS', 6 );

add_shortcode( 'lis_listing_submit', 'lis_directory_render_listing_submission_form_shortcode' );
add_action( 'admin_post_lis_directory_submit_listing', 'lis_directory_handle_listing_submission' );

function lis_directory_render_listing_submission_form_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-photos', LIS_DIRECTORY_URL . 'assets/js/listing-photos.js', array(), LIS_DIRECTORY_VERSION, true );

	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-listing-submit-login-required">
			You need an account to submit a listing.
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a>
			or <a href="<?php echo esc_url( wp_registration_url() ); ?>">register</a> first.
		</p>
		<?php
		return ob_get_clean();
	}

	$current_url  = remove_query_arg( array( 'lis_listing_submitted', 'lis_listing_error' ) );
	$current_user = wp_get_current_user();
	$cat_terms    = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false ) );
	$feature_terms = get_terms( array( 'taxonomy' => 'lis_listing_feature', 'hide_empty' => false ) );
	if ( is_wp_error( $feature_terms ) ) {
		$feature_terms = array();
	}

	ob_start();

	if ( isset( $_GET['lis_listing_submitted'] ) ) {
		?>
		<div class="lis-listing-submit-success">Thanks! Your listing is in for review — we'll publish it once it's approved.</div>
		<?php
	}

	if ( ! empty( $_GET['lis_listing_error'] ) ) {
		$error_keys = array_map( 'sanitize_key', explode( ',', wp_unslash( $_GET['lis_listing_error'] ) ) );
		?>
		<div class="lis-listing-submit-errors">
			<p>Please fix the following:</p>
			<ul>
				<?php foreach ( $error_keys as $key ) : ?>
					<li><?php echo esc_html( lis_directory_listing_submission_error_message( $key ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	if ( is_wp_error( $cat_terms ) || empty( $cat_terms ) ) {
		echo lis_directory_card_admin_hint( 'No listing categories exist yet — create some under LIS Listings > Categories before showing this form.' ); // phpcs:ignore -- escaped inside helper.
		return ob_get_clean();
	}
	?>
	<form class="lis-listing-submit-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lis_directory_submit_listing" />
		<input type="hidden" name="lis_listing_redirect_to" value="<?php echo esc_url( $current_url ); ?>" />
		<?php wp_nonce_field( 'lis_listing_submit', 'lis_listing_submit_nonce' ); ?>

		<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label for="lis_listing_hp">Leave this field blank</label>
			<input type="text" id="lis_listing_hp" name="lis_listing_hp" tabindex="-1" autocomplete="off" />
		</div>

		<div class="lis-listing-submit-section">
			<h3>Basics</h3>
			<p>
				<label for="lis_listing_business_name">Business Name</label>
				<input type="text" id="lis_listing_business_name" name="lis_listing_business_name" required />
			</p>
			<p>
				<label for="lis_listing_category">Category</label>
				<select id="lis_listing_category" name="lis_listing_category" required>
					<option value="">— Select a category —</option>
					<?php foreach ( $cat_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="lis_listing_description">Description</label>
				<textarea id="lis_listing_description" name="lis_listing_description" rows="4"></textarea>
			</p>
		</div>

		<div class="lis-listing-submit-section">
			<h3>Contact</h3>
			<p>
				<label for="lis_listing_address">Address</label>
				<input type="text" id="lis_listing_address" name="lis_listing_address" />
			</p>
			<p>
				<label for="lis_listing_phone">Phone</label>
				<input type="text" id="lis_listing_phone" name="lis_listing_phone" />
			</p>
			<p>
				<label for="lis_listing_website">Website</label>
				<input type="url" id="lis_listing_website" name="lis_listing_website" placeholder="https://" />
			</p>
			<p>
				<label for="lis_listing_email">Contact Email</label>
				<input type="email" id="lis_listing_email" name="lis_listing_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
			</p>
		</div>

		<div class="lis-listing-submit-section">
			<h3>Photos &amp; Video</h3>
			<p>
				<label for="lis_listing_photos">Photos (at least one required — up to <?php echo (int) LIS_DIRECTORY_SUBMIT_MAX_PHOTOS; ?>)</label>
				<label for="lis_listing_photos" class="lis-listing-photos-dropzone">
					<span class="lis-listing-photos-dropzone-label">Click to choose photos, or drag them here</span>
					<span class="lis-listing-photos-dropzone-hint">PNG or JPG, up to 2MB each. The first one becomes the main photo.</span>
				</label>
				<input type="file" id="lis_listing_photos" name="lis_listing_photos[]" accept="image/png,image/jpeg" multiple required />
				<div class="lis-listing-photos-preview" data-photos-preview></div>
			</p>
			<p>
				<label for="lis_listing_video_url">Video</label>
				<input type="url" id="lis_listing_video_url" name="lis_listing_video_url" placeholder="YouTube or Vimeo link" />
			</p>
		</div>

		<div class="lis-listing-submit-section">
			<h3>Business Hours <small>(leave a day blank if closed)</small></h3>
			<table class="lis-listing-submit-hours-table">
				<?php foreach ( LIS_DIRECTORY_WEEKDAYS as $day => $label ) : ?>
					<tr>
						<th><label for="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input type="time" id="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" />
							<span>to</span>
							<input type="time" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" />
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="lis-listing-submit-section">
			<h3>Services</h3>
			<p>
				<label for="lis_listing_services">One per line</label>
				<textarea id="lis_listing_services" name="lis_listing_services" rows="4" placeholder="Oil changes&#10;Tire rotation&#10;Brake inspection"></textarea>
			</p>
		</div>

		<?php if ( ! empty( $feature_terms ) ) : ?>
			<div class="lis-listing-submit-section">
				<h3>Features</h3>
				<div class="lis-listing-submit-checkbox-grid">
					<?php foreach ( $feature_terms as $feature ) : ?>
						<label class="lis-listing-submit-checkbox">
							<input type="checkbox" name="lis_listing_features[]" value="<?php echo esc_attr( $feature->term_id ); ?>" />
							<?php echo esc_html( $feature->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="lis-listing-submit-section">
			<h3>Social Media</h3>
			<p>
				<label for="lis_listing_facebook">Facebook</label>
				<input type="url" id="lis_listing_facebook" name="lis_listing_facebook" placeholder="https://facebook.com/..." />
			</p>
			<p>
				<label for="lis_listing_instagram">Instagram</label>
				<input type="url" id="lis_listing_instagram" name="lis_listing_instagram" placeholder="https://instagram.com/..." />
			</p>
			<p>
				<label for="lis_listing_twitter">X / Twitter</label>
				<input type="url" id="lis_listing_twitter" name="lis_listing_twitter" placeholder="https://x.com/..." />
			</p>
			<p>
				<label for="lis_listing_linkedin">LinkedIn</label>
				<input type="url" id="lis_listing_linkedin" name="lis_listing_linkedin" placeholder="https://linkedin.com/..." />
			</p>
		</div>

		<p><button type="submit" class="lis-listing-submit-button">Submit for Review</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function lis_directory_listing_submission_error_message( $key ) {
	$messages = array(
		'business_name'                     => 'Business name is required.',
		'category'                          => 'Please select a valid category.',
		'email'                              => 'A valid contact email is required.',
		'lis_listing_photos'                => 'At least one photo is required.',
		'lis_listing_photos_type'           => 'Photos must be real PNG or JPG files.',
		'lis_listing_photos_too_large'      => 'Each photo must be under 2MB.',
		'lis_listing_photos_upload_failed'  => 'One or more photos failed to upload — please try again.',
		'save_failed'                       => 'Something went wrong saving your submission — please try again.',
	);
	return isset( $messages[ $key ] ) ? $messages[ $key ] : 'Please check your submission and try again.';
}

function lis_directory_handle_listing_submission() {
	$redirect_base = isset( $_POST['lis_listing_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to submit a listing.' );
	}

	if ( ! isset( $_POST['lis_listing_submit_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_submit_nonce'], 'lis_listing_submit' ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	// Honeypot: a bot that fills the hidden field gets a fake "success" redirect
	// with nothing actually created, so it doesn't learn the submission failed.
	if ( ! empty( $_POST['lis_listing_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_submitted', '1', $redirect_base ) );
		exit;
	}

	$errors = array();

	$business_name = isset( $_POST['lis_listing_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_business_name'] ) ) : '';
	if ( '' === $business_name ) {
		$errors[] = 'business_name';
	}

	$term_id = isset( $_POST['lis_listing_category'] ) ? (int) $_POST['lis_listing_category'] : 0;
	$term    = $term_id ? get_term( $term_id, 'lis_listing_category' ) : null;
	if ( ! $term || is_wp_error( $term ) ) {
		$errors[] = 'category';
	}

	$email = isset( $_POST['lis_listing_email'] ) ? sanitize_email( wp_unslash( $_POST['lis_listing_email'] ) ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		$errors[] = 'email';
	}

	$description = isset( $_POST['lis_listing_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lis_listing_description'] ) ) : '';
	$address     = isset( $_POST['lis_listing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_address'] ) ) : '';
	$phone       = isset( $_POST['lis_listing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_phone'] ) ) : '';
	$website     = isset( $_POST['lis_listing_website'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_website'] ) ) : '';

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$photo_ids = lis_directory_handle_listing_photos_upload( 'lis_listing_photos', true, $errors );

	if ( ! empty( $errors ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', implode( ',', array_unique( $errors ) ), $redirect_base ) );
		exit;
	}

	$post_id = wp_insert_post( array(
		'post_type'      => 'lis_listing',
		'post_title'     => $business_name,
		'post_content'   => $description,
		'post_status'    => 'pending', // Native WP pending — reviewed/published from the normal admin editor.
		'post_author'    => get_current_user_id(),
		'comment_status' => 'open', // Explicit, not left to the site-wide Discussion default — a listing with reviews turned off has a permanently empty, unusable Reviews section (see Talus Rock Retreat, which predated this and needed a one-time fix).
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', 'save_failed', $redirect_base ) );
		exit;
	}

	wp_set_post_terms( $post_id, array( $term->term_id ), 'lis_listing_category' );

	update_post_meta( $post_id, '_lis_listing_address', $address );
	update_post_meta( $post_id, '_lis_listing_phone', $phone );
	update_post_meta( $post_id, '_lis_listing_website', $website );
	update_post_meta( $post_id, '_lis_listing_email', $email );

	if ( ! empty( $photo_ids ) ) {
		set_post_thumbnail( $post_id, $photo_ids[0] );
		update_post_meta( $post_id, '_lis_listing_gallery_ids', implode( ',', $photo_ids ) );
	}

	if ( ! empty( $_POST['lis_listing_video_url'] ) ) {
		update_post_meta( $post_id, '_lis_listing_video_url', esc_url_raw( wp_unslash( $_POST['lis_listing_video_url'] ) ) );
	}
	if ( ! empty( $_POST['lis_listing_services'] ) ) {
		update_post_meta( $post_id, '_lis_listing_services', sanitize_textarea_field( wp_unslash( $_POST['lis_listing_services'] ) ) );
	}
	foreach ( array( 'facebook', 'instagram', 'twitter', 'linkedin' ) as $network ) {
		$field = "lis_listing_{$network}";
		if ( ! empty( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, "_{$field}", esc_url_raw( wp_unslash( $_POST[ $field ] ) ) );
		}
	}

	lis_directory_save_submitted_hours( $post_id );
	lis_directory_save_submitted_features( $post_id );

	wp_safe_redirect( add_query_arg( 'lis_listing_submitted', '1', $redirect_base ) );
	exit;
}

/**
 * Same HH:MM validation as the admin meta box save
 * (lis_directory_save_listing_hours_meta_box() in includes/listings-hours.php)
 * — blank means closed that day, anything not matching HH:MM is silently
 * dropped rather than stored malformed.
 */
function lis_directory_save_submitted_hours( $post_id ) {
	foreach ( array_keys( LIS_DIRECTORY_WEEKDAYS ) as $day ) {
		foreach ( array( 'open', 'close' ) as $edge ) {
			$field = "lis_listing_hours_{$day}_{$edge}";
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			if ( '' === $value || preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
				update_post_meta( $post_id, "_{$field}", $value );
			}
		}
	}
}

/**
 * Only accepts feature IDs that resolve to real, existing lis_listing_feature
 * terms — a public form must not be able to create arbitrary new taxonomy
 * terms via a tampered request.
 */
function lis_directory_save_submitted_features( $post_id ) {
	if ( empty( $_POST['lis_listing_features'] ) || ! is_array( $_POST['lis_listing_features'] ) ) {
		return;
	}
	$submitted_ids = array_map( 'absint', wp_unslash( $_POST['lis_listing_features'] ) );
	$valid_ids     = array();
	foreach ( $submitted_ids as $term_id ) {
		$term = get_term( $term_id, 'lis_listing_feature' );
		if ( $term && ! is_wp_error( $term ) ) {
			$valid_ids[] = $term->term_id;
		}
	}
	if ( ! empty( $valid_ids ) ) {
		wp_set_post_terms( $post_id, $valid_ids, 'lis_listing_feature' );
	}
}

/**
 * Validates and sideloads every file in a multi-file input
 * (`name="field[]"` + `multiple`). PNG or JPG — unlike the vendor logo
 * upload, these photos are never CSS-recolored, so there's no reason to
 * restrict to PNG-only. Same real-mime-type + getimagesize() validation as
 * lis_directory_handle_logo_upload() in includes/submission.php, just
 * looped per file since PHP's multi-file $_FILES shape isn't compatible
 * with media_handle_upload() (which reads a single named field directly) —
 * media_handle_sideload() is used instead, which takes an explicit file
 * array rather than reading $_FILES itself.
 *
 * @return int[] Attachment IDs of everything that uploaded successfully.
 */
function lis_directory_handle_listing_photos_upload( $field_name, $required, array &$errors ) {
	if ( empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) ) {
		if ( $required ) {
			$errors[] = $field_name;
		}
		return array();
	}

	$files    = $_FILES[ $field_name ];
	$count    = min( count( (array) $files['name'] ), LIS_DIRECTORY_SUBMIT_MAX_PHOTOS );
	$has_any  = false;
	$ids      = array();

	for ( $i = 0; $i < $count; $i++ ) {
		if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] ) {
			continue;
		}
		$has_any = true;

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			$errors[] = $field_name . '_upload_failed';
			continue;
		}

		$max_bytes = 2 * MB_IN_BYTES;
		if ( $file['size'] > $max_bytes ) {
			$errors[] = $field_name . '_too_large';
			continue;
		}

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $filetype['ext'] ) || ! in_array( $filetype['ext'], array( 'jpg', 'jpeg', 'png' ), true ) ) {
			$errors[] = $field_name . '_type';
			continue;
		}

		// Extension can lie; confirm the bytes actually decode as an image.
		if ( false === @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore -- deliberate suppression, failure handled below.
			$errors[] = $field_name . '_type';
			continue;
		}

		add_filter( 'upload_mimes', 'lis_directory_restrict_listing_photo_mimes' );
		$attachment_id = media_handle_sideload( $file, 0 );
		remove_filter( 'upload_mimes', 'lis_directory_restrict_listing_photo_mimes' );

		if ( is_wp_error( $attachment_id ) ) {
			$errors[] = $field_name . '_upload_failed';
			continue;
		}

		$ids[] = (int) $attachment_id;
	}

	if ( ! $has_any && $required ) {
		$errors[] = $field_name;
	}

	return $ids;
}

function lis_directory_restrict_listing_photo_mimes( $mimes ) {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
	);
}
