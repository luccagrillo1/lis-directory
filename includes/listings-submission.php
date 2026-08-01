<?php
/**
 * Front-end listing submission: [lis_listing_submit] shortcode +
 * admin-post.php handler. Same pattern as includes/submission.php (the
 * Preferred Vendor submission form) — requires a WordPress account,
 * honeypot + nonce, real server-side upload validation.
 *
 * Unlike the vendor form, this doesn't need a custom '_lis_pv_status'-style
 * workflow meta: `lis_listing` has no "one active per category" concept to
 * enforce, so submissions land as native WordPress `pending` post status —
 * an editor reviews and clicks Publish in the normal admin editor, using
 * the existing "Listing Details" meta box (includes/listings-meta.php)
 * already built for admin-created listings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_listing_submit', 'lis_directory_render_listing_submission_form_shortcode' );
add_action( 'admin_post_lis_directory_submit_listing', 'lis_directory_handle_listing_submission' );

function lis_directory_render_listing_submission_form_shortcode() {
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
	$terms        = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false ) );

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

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
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

		<p>
			<label for="lis_listing_business_name">Business Name</label><br />
			<input type="text" id="lis_listing_business_name" name="lis_listing_business_name" required />
		</p>

		<p>
			<label for="lis_listing_category">Category</label><br />
			<select id="lis_listing_category" name="lis_listing_category" required>
				<option value="">— Select a category —</option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="lis_listing_description">Description</label><br />
			<textarea id="lis_listing_description" name="lis_listing_description" rows="4"></textarea>
		</p>

		<p>
			<label for="lis_listing_address">Address</label><br />
			<input type="text" id="lis_listing_address" name="lis_listing_address" />
		</p>

		<p>
			<label for="lis_listing_phone">Phone</label><br />
			<input type="text" id="lis_listing_phone" name="lis_listing_phone" />
		</p>

		<p>
			<label for="lis_listing_website">Website</label><br />
			<input type="url" id="lis_listing_website" name="lis_listing_website" placeholder="https://" />
		</p>

		<p>
			<label for="lis_listing_email">Contact Email</label><br />
			<input type="email" id="lis_listing_email" name="lis_listing_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
		</p>

		<p>
			<label for="lis_listing_photo">Photo (PNG or JPG, required)</label><br />
			<input type="file" id="lis_listing_photo" name="lis_listing_photo" accept="image/png,image/jpeg" required />
			<br /><small>Shown as the listing's main photo. More photos, business hours, and features can be added once you're published — just ask.</small>
		</p>

		<p><button type="submit">Submit for Review</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function lis_directory_listing_submission_error_message( $key ) {
	$messages = array(
		'business_name'          => 'Business name is required.',
		'category'                => 'Please select a valid category.',
		'email'                   => 'A valid contact email is required.',
		'lis_listing_photo'              => 'A photo is required.',
		'lis_listing_photo_type'         => 'Photo must be a real PNG or JPG file.',
		'lis_listing_photo_too_large'    => 'Photo must be under 2MB.',
		'lis_listing_photo_upload_failed' => 'Photo failed to upload — please try again.',
		'save_failed'             => 'Something went wrong saving your submission — please try again.',
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

	$photo_id = lis_directory_handle_listing_photo_upload( 'lis_listing_photo', true, $errors );

	if ( ! empty( $errors ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', implode( ',', $errors ), $redirect_base ) );
		exit;
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'lis_listing',
		'post_title'  => $business_name,
		'post_content' => $description,
		'post_status' => 'pending', // Native WP pending — reviewed/published from the normal admin editor.
		'post_author' => get_current_user_id(),
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
	if ( $photo_id ) {
		set_post_thumbnail( $post_id, $photo_id );
	}

	wp_safe_redirect( add_query_arg( 'lis_listing_submitted', '1', $redirect_base ) );
	exit;
}

/**
 * Validates and sideloads the listing photo. PNG or JPG (unlike the vendor
 * logo upload, this photo is never CSS-recolored, so there's no reason to
 * restrict to PNG-only). Same real-mime-type + getimagesize() validation
 * pattern as lis_directory_handle_logo_upload() in includes/submission.php.
 */
function lis_directory_handle_listing_photo_upload( $field_name, $required, array &$errors ) {
	$has_file = ! empty( $_FILES[ $field_name ]['name'] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ $field_name ]['error'];

	if ( ! $has_file ) {
		if ( $required ) {
			$errors[] = $field_name;
		}
		return 0;
	}

	if ( UPLOAD_ERR_OK !== $_FILES[ $field_name ]['error'] ) {
		$errors[] = $field_name . '_upload_failed';
		return 0;
	}

	$max_bytes = 2 * MB_IN_BYTES;
	if ( $_FILES[ $field_name ]['size'] > $max_bytes ) {
		$errors[] = $field_name . '_too_large';
		return 0;
	}

	$filetype = wp_check_filetype_and_ext( $_FILES[ $field_name ]['tmp_name'], $_FILES[ $field_name ]['name'] );
	if ( empty( $filetype['ext'] ) || ! in_array( $filetype['ext'], array( 'jpg', 'jpeg', 'png' ), true ) ) {
		$errors[] = $field_name . '_type';
		return 0;
	}

	// Extension can lie; confirm the bytes actually decode as an image.
	if ( false === @getimagesize( $_FILES[ $field_name ]['tmp_name'] ) ) { // phpcs:ignore -- deliberate suppression, failure handled below.
		$errors[] = $field_name . '_type';
		return 0;
	}

	add_filter( 'upload_mimes', 'lis_directory_restrict_listing_photo_mimes' );
	$attachment_id = media_handle_upload( $field_name, 0 );
	remove_filter( 'upload_mimes', 'lis_directory_restrict_listing_photo_mimes' );

	if ( is_wp_error( $attachment_id ) ) {
		$errors[] = $field_name . '_upload_failed';
		return 0;
	}

	return (int) $attachment_id;
}

function lis_directory_restrict_listing_photo_mimes( $mimes ) {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
	);
}
