<?php
/**
 * Front-end vendor submission: [lis_preferred_vendor_submit] shortcode +
 * admin-post.php handler. Requires a WordPress account (resolved decision —
 * no anonymous/email-lookup path). Submissions always land as `pending`;
 * approval happens in wp-admin (see includes/admin.php row actions).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_preferred_vendor_submit', 'lis_directory_render_submission_form_shortcode' );
add_action( 'admin_post_lis_directory_submit_vendor', 'lis_directory_handle_vendor_submission' );

function lis_directory_render_submission_form_shortcode() {
	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-pv-submit-login-required">
			You need an account to submit a Vendor Showcase listing.
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a>
			or <a href="<?php echo esc_url( wp_registration_url() ); ?>">register</a> first.
		</p>
		<?php
		return ob_get_clean();
	}

	$current_url  = remove_query_arg( array( 'lis_pv_submitted', 'lis_pv_error' ) );
	$current_user = wp_get_current_user();
	$terms        = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );

	// Arriving from the WooCommerce thank-you page link (includes/woocommerce.php)
	// locks the category to what was actually paid for, instead of letting the
	// vendor pick a different (possibly taken) one.
	$locked_term    = null;
	$locked_order   = 0;
	if ( ! empty( $_GET['lis_pv_category'] ) && ! empty( $_GET['lis_pv_order'] ) ) {
		$maybe_term = get_term( (int) $_GET['lis_pv_category'], 'lis_vendor_category' );
		if ( $maybe_term && ! is_wp_error( $maybe_term ) ) {
			$locked_term  = $maybe_term;
			$locked_order = (int) $_GET['lis_pv_order'];
		}
	}

	ob_start();

	if ( isset( $_GET['lis_pv_submitted'] ) ) {
		?>
		<div class="lis-pv-submit-success">Thanks! Your submission is in for review — we'll follow up once it's approved.</div>
		<?php
	}

	if ( ! empty( $_GET['lis_pv_error'] ) ) {
		$error_keys = array_map( 'sanitize_key', explode( ',', wp_unslash( $_GET['lis_pv_error'] ) ) );
		?>
		<div class="lis-pv-submit-errors">
			<p>Please fix the following:</p>
			<ul>
				<?php foreach ( $error_keys as $key ) : ?>
					<li><?php echo esc_html( lis_directory_submission_error_message( $key ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		echo lis_directory_card_admin_hint( 'No vendor categories exist yet — create some under Vendor Showcase before showing the submission form.' ); // phpcs:ignore -- escaped inside helper.
		return ob_get_clean();
	}
	?>
	<form class="lis-pv-submit-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lis_directory_submit_vendor" />
		<input type="hidden" name="lis_pv_redirect_to" value="<?php echo esc_url( $current_url ); ?>" />
		<?php wp_nonce_field( 'lis_pv_submit_vendor', 'lis_pv_submit_nonce' ); ?>

		<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label for="lis_pv_hp">Leave this field blank</label>
			<input type="text" id="lis_pv_hp" name="lis_pv_hp" tabindex="-1" autocomplete="off" />
		</div>

		<p>
			<label for="lis_pv_business_name">Business Name</label><br />
			<input type="text" id="lis_pv_business_name" name="lis_pv_business_name" required />
		</p>

		<?php if ( $locked_term ) : ?>
			<p>
				<strong>Category:</strong> <?php echo esc_html( $locked_term->name ); ?>
				<input type="hidden" name="lis_pv_category" value="<?php echo esc_attr( $locked_term->term_id ); ?>" />
				<input type="hidden" name="lis_pv_wc_order_id" value="<?php echo esc_attr( $locked_order ); ?>" />
			</p>
		<?php else : ?>
			<p>
				<label for="lis_pv_category">Category</label><br />
				<select id="lis_pv_category" name="lis_pv_category" required>
					<option value="">— Select a category —</option>
					<?php foreach ( $terms as $term ) : ?>
						<?php $taken = lis_directory_get_active_vendor_for_category( $term->term_id ); ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>">
							<?php echo esc_html( $term->name . ( $taken ? ' (currently taken - apply to be waitlisted)' : '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<p>
			<label for="lis_pv_link_url">Your Local Directory Listing URL</label><br />
			<input type="url" id="lis_pv_link_url" name="lis_pv_link_url" placeholder="https://livinginsandpoint.com/directory/your-business/" required />
			<br /><small>You must already have a listing on the Local Directory before you can join the Vendor Showcase. Paste its link here.</small>
		</p>

		<p>
			<label for="lis_pv_tagline">Tagline</label><br />
			<input type="text" id="lis_pv_tagline" name="lis_pv_tagline" placeholder="e.g. For all your insurance needs" />
		</p>

		<p>
			<label for="lis_pv_logo_color">Logo (PNG with a transparent background, required)</label><br />
			<input type="file" id="lis_pv_logo_color" name="lis_pv_logo_color" accept="image/png" required />
			<br /><small>Used as-is on cards, and recolored to black or white automatically wherever that's needed — a transparent PNG is what makes that recoloring look clean instead of showing a solid box.</small>
		</p>

		<p>
			<label for="lis_pv_business_card">Business Card (optional)</label><br />
			<input type="file" id="lis_pv_business_card" name="lis_pv_business_card" accept="image/png,image/jpeg" />
			<br /><small>A photo or scan of your actual business card, standard size (3.5&quot; x 2&quot;). If you upload one, it can be shown as-is in the "business card" ticker style instead of the usual name/tagline card.</small>
		</p>

		<p>
			<label for="lis_pv_contact_email">Contact Email</label><br />
			<input type="email" id="lis_pv_contact_email" name="lis_pv_contact_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
		</p>

		<p><button type="submit">Submit for Review</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function lis_directory_submission_error_message( $key ) {
	$messages = array(
		'business_name'                     => 'Business name is required.',
		'category'                          => 'Please select a valid category.',
		'contact_email'                     => 'A valid contact email is required.',
		'link_url'                          => 'Please provide the link to your existing, published Local Directory listing — approval requires one.',
		'lis_pv_logo_color'                 => 'A logo is required.',
		'lis_pv_logo_color_type'            => 'Logo must be a real PNG file with a transparent background.',
		'lis_pv_logo_color_too_large'       => 'Logo must be under 2MB.',
		'lis_pv_logo_color_upload_failed'   => 'Logo failed to upload — please try again.',
		'lis_pv_business_card_type'         => 'Business card must be a real PNG or JPEG file.',
		'lis_pv_business_card_too_large'    => 'Business card must be under 2MB.',
		'lis_pv_business_card_upload_failed'=> 'Business card failed to upload — please try again.',
		'save_failed'                       => 'Something went wrong saving your submission — please try again.',
	);
	return isset( $messages[ $key ] ) ? $messages[ $key ] : 'Please check your submission and try again.';
}

function lis_directory_handle_vendor_submission() {
	$redirect_base = isset( $_POST['lis_pv_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['lis_pv_redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to submit a vendor listing.' );
	}

	if ( ! isset( $_POST['lis_pv_submit_nonce'] ) || ! wp_verify_nonce( $_POST['lis_pv_submit_nonce'], 'lis_pv_submit_vendor' ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	// Honeypot: a bot that fills the hidden field gets a fake "success" redirect
	// with nothing actually created, so it doesn't learn the submission failed.
	if ( ! empty( $_POST['lis_pv_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'lis_pv_submitted', '1', $redirect_base ) );
		exit;
	}

	$errors = array();

	$business_name = isset( $_POST['lis_pv_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_pv_business_name'] ) ) : '';
	if ( '' === $business_name ) {
		$errors[] = 'business_name';
	}

	$term_id = isset( $_POST['lis_pv_category'] ) ? (int) $_POST['lis_pv_category'] : 0;
	$term    = $term_id ? get_term( $term_id, 'lis_vendor_category' ) : null;
	if ( ! $term || is_wp_error( $term ) ) {
		$errors[] = 'category';
	}

	$contact_email = isset( $_POST['lis_pv_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['lis_pv_contact_email'] ) ) : '';
	if ( '' === $contact_email || ! is_email( $contact_email ) ) {
		$errors[] = 'contact_email';
	}

	$link_url = isset( $_POST['lis_pv_link_url'] ) ? esc_url_raw( wp_unslash( $_POST['lis_pv_link_url'] ) ) : '';
	if ( ! lis_directory_is_valid_directorist_url( $link_url ) ) {
		$errors[] = 'link_url';
	}

	$tagline = isset( $_POST['lis_pv_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_pv_tagline'] ) ) : '';

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$logo_color_id     = lis_directory_handle_logo_upload( 'lis_pv_logo_color', true, $errors );
	$business_card_id  = lis_directory_handle_business_card_upload( 'lis_pv_business_card', $errors );

	if ( ! empty( $errors ) ) {
		wp_safe_redirect( add_query_arg( 'lis_pv_error', implode( ',', $errors ), $redirect_base ) );
		exit;
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'lis_preferred_vendor',
		'post_title'  => $business_name,
		// The CPT is not public and has its own '_lis_pv_status' workflow meta
		// (pending/active/expired/rejected) — 'publish' here just means "a real
		// row admins can see in the list", not "visible to the public".
		'post_status' => 'publish',
		'post_author' => get_current_user_id(),
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_safe_redirect( add_query_arg( 'lis_pv_error', 'save_failed', $redirect_base ) );
		exit;
	}

	wp_set_post_terms( $post_id, array( $term->term_id ), 'lis_vendor_category' );

	lis_directory_set_vendor_status( $post_id, 'pending' );
	update_post_meta( $post_id, '_lis_pv_tagline', $tagline );
	update_post_meta( $post_id, '_lis_pv_contact_email', $contact_email );
	update_post_meta( $post_id, '_lis_pv_link_url', $link_url );
	if ( $logo_color_id ) {
		update_post_meta( $post_id, '_lis_pv_logo_color_id', $logo_color_id );
	}
	if ( $business_card_id ) {
		update_post_meta( $post_id, '_lis_pv_business_card_id', $business_card_id );
	}

	// Present when the vendor arrived via the WooCommerce thank-you page link
	// (includes/woocommerce.php) — ties this submission back to what they paid for.
	if ( ! empty( $_POST['lis_pv_wc_order_id'] ) ) {
		update_post_meta( $post_id, '_lis_pv_wc_order_id', absint( $_POST['lis_pv_wc_order_id'] ) );
	}

	wp_safe_redirect( add_query_arg( 'lis_pv_submitted', '1', $redirect_base ) );
	exit;
}

/**
 * Validates and sideloads one logo field into the media library.
 *
 * Restricts to PNG via a temporary `upload_mimes` filter (scoped to this
 * call only — never widened globally, and SVG is deliberately never allowed
 * here per the build brief's XSS warning). PNG-only (not PNG-or-JPEG) is
 * deliberate: the CSS recolor trick used to derive black/white logo variants
 * from this one file (`filter: brightness(0)` / `brightness(0) invert(1)`)
 * only looks right against a transparent background, which JPEG can't have.
 * Enforces a 2MB cap, and confirms
 * the file is actually an image (not just a renamed extension) via
 * getimagesize() before handing it to media_handle_upload().
 *
 * @return int Attachment ID, or 0 if nothing valid was uploaded (errors appended to $errors).
 */
function lis_directory_handle_logo_upload( $field_name, $required, array &$errors ) {
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
	if ( empty( $filetype['ext'] ) || 'png' !== $filetype['ext'] ) {
		$errors[] = $field_name . '_type';
		return 0;
	}

	// Extension can lie; confirm the bytes actually decode as an image.
	if ( false === @getimagesize( $_FILES[ $field_name ]['tmp_name'] ) ) { // phpcs:ignore -- deliberate suppression, failure handled below.
		$errors[] = $field_name . '_type';
		return 0;
	}

	add_filter( 'upload_mimes', 'lis_directory_restrict_logo_mimes' );
	$attachment_id = media_handle_upload( $field_name, 0 );
	remove_filter( 'upload_mimes', 'lis_directory_restrict_logo_mimes' );

	if ( is_wp_error( $attachment_id ) ) {
		$errors[] = $field_name . '_upload_failed';
		return 0;
	}

	return (int) $attachment_id;
}

function lis_directory_restrict_logo_mimes( $mimes ) {
	return array(
		'png' => 'image/png',
	);
}

/**
 * Business card upload — optional (unlike the logo), and PNG-or-JPEG rather
 * than PNG-only, since this is a photo/scan of a real printed card, not
 * something that ever needs the CSS black/white recolor trick the logo does.
 * Same validation shape as lis_directory_handle_logo_upload() otherwise:
 * 2MB cap, real-image check via getimagesize(), restricted upload_mimes.
 *
 * @return int Attachment ID, or 0 if nothing was uploaded (errors appended to $errors only on a real problem, not on "left blank").
 */
function lis_directory_handle_business_card_upload( $field_name, array &$errors ) {
	$has_file = ! empty( $_FILES[ $field_name ]['name'] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ $field_name ]['error'];

	if ( ! $has_file ) {
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
	if ( empty( $filetype['ext'] ) || ! in_array( $filetype['ext'], array( 'png', 'jpg', 'jpeg' ), true ) ) {
		$errors[] = $field_name . '_type';
		return 0;
	}

	if ( false === @getimagesize( $_FILES[ $field_name ]['tmp_name'] ) ) { // phpcs:ignore -- deliberate suppression, failure handled below.
		$errors[] = $field_name . '_type';
		return 0;
	}

	add_filter( 'upload_mimes', 'lis_directory_restrict_business_card_mimes' );
	$attachment_id = media_handle_upload( $field_name, 0 );
	remove_filter( 'upload_mimes', 'lis_directory_restrict_business_card_mimes' );

	if ( is_wp_error( $attachment_id ) ) {
		$errors[] = $field_name . '_upload_failed';
		return 0;
	}

	return (int) $attachment_id;
}

function lis_directory_restrict_business_card_mimes( $mimes ) {
	return array(
		'png'  => 'image/png',
		'jpg|jpeg' => 'image/jpeg',
	);
}
