<?php
/**
 * Front-end listing edit: [lis_listing_edit] shortcode + admin-post.php
 * handler. Deliberately separate from [lis_listing_submit] rather than one
 * shared form with a mode flag — the two have real differences (photos
 * optional vs required, wp_update_post vs wp_insert_post, ownership check
 * instead of "anyone logged in") that would make a single merged function
 * harder to follow than two smaller ones.
 *
 * Editing does NOT reset a published listing back to pending — an owner
 * fixing a typo shouldn't have to wait for re-approval every time. Only a
 * brand-new submission goes through moderation.
 *
 * Type-specific fields (bedrooms/bathrooms/sqft, salary/employment type,
 * directory type itself) are NOT editable here — the original submission
 * form never collected them either (they're admin-only, set via the
 * wp-admin meta box), so adding them only to the edit form would be an
 * inconsistent half-step. Flagged inline in the form when relevant, not
 * silently omitted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_listing_edit', 'lis_directory_render_listing_edit_form_shortcode' );
add_action( 'admin_post_lis_directory_update_listing', 'lis_directory_handle_listing_update' );
add_action( 'init', 'lis_directory_register_listing_edit_settings' ); // Not admin_init - see the note on the equivalent hook in includes/woocommerce.php.

function lis_directory_register_listing_edit_settings() {
	register_setting( 'lis_pv_settings_group', 'lis_directory_listing_edit_page_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'show_in_rest'      => true, // Lets this be set via the REST settings endpoint once the Edit Listing page exists, not only by hand in wp-admin.
	) );
}

/**
 * Empty string when no edit page is configured yet (Settings > LIS Directory
 * Settings) — callers should fall back to the wp-admin edit link in that case.
 */
function lis_directory_get_listing_edit_url( $listing_id ) {
	$page_id = (int) get_option( 'lis_directory_listing_edit_page_id' );
	if ( ! $page_id ) {
		return '';
	}
	return add_query_arg( 'listing_id', (int) $listing_id, get_permalink( $page_id ) );
}

function lis_directory_render_listing_edit_form_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-photos', LIS_DIRECTORY_URL . 'assets/js/listing-photos.js', array(), LIS_DIRECTORY_VERSION, true );
	// Deliberately NOT the one-question-at-a-time wizard (listing-form-wizard.js)
	// that [lis_listing_submit] uses — that's a guided onboarding flow for a
	// brand-new listing. Editing an existing one is a different job: a
	// business owner wants to find and fix one field fast, not step through a
	// dozen questions to reach it. Flat, all-fields-visible layout instead,
	// same panel markup — see the .lis-listing-edit-flat CSS.
	lis_directory_enqueue_places_autocomplete();

	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-listing-submit-login-required">
			You need an account to edit a listing.
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> first.
		</p>
		<?php
		return ob_get_clean();
	}

	$listing_id = isset( $_GET['listing_id'] ) ? absint( $_GET['listing_id'] ) : 0;
	$listing    = $listing_id ? get_post( $listing_id ) : null;

	if ( ! $listing || 'lis_listing' !== $listing->post_type || (int) $listing->post_author !== get_current_user_id() ) {
		ob_start();
		?>
		<p class="lis-listing-submit-errors">You can only edit your own listings.</p>
		<?php
		return ob_get_clean();
	}

	$current_url    = remove_query_arg( array( 'lis_listing_updated', 'lis_listing_error' ) );
	$current_user   = wp_get_current_user();
	$cat_terms      = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false ) );
	$feature_terms  = lis_directory_get_public_feature_terms();

	$type              = lis_directory_get_listing_type( $listing_id );
	$categories        = get_the_terms( $listing_id, 'lis_listing_category' );
	$current_cat_id    = ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) ? $categories[0]->term_id : 0;
	$current_features  = wp_get_post_terms( $listing_id, 'lis_listing_feature', array( 'fields' => 'ids' ) );

	// Keep this listing's own still-pending suggestion(s) in the checkbox list
	// so they stay checked and aren't dropped on save (they're excluded from
	// the public list above until an admin approves them).
	$known_feature_ids = wp_list_pluck( $feature_terms, 'term_id' );
	foreach ( (array) $current_features as $cf_id ) {
		if ( ! in_array( (int) $cf_id, array_map( 'intval', $known_feature_ids ), true ) ) {
			$cf_term = get_term( $cf_id, 'lis_listing_feature' );
			if ( $cf_term && ! is_wp_error( $cf_term ) ) {
				$feature_terms[] = $cf_term;
			}
		}
	}
	$gallery_raw       = get_post_meta( $listing_id, '_lis_listing_gallery_ids', true );
	$gallery_ids       = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
	$week              = lis_directory_get_listing_week_hours( $listing_id );

	ob_start();

	if ( isset( $_GET['lis_listing_updated'] ) ) {
		?>
		<div class="lis-listing-submit-success">Your listing has been updated.</div>
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
	?>
	<form class="lis-listing-submit-form lis-listing-edit-flat" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lis_directory_update_listing" />
		<input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>" />
		<input type="hidden" name="lis_listing_redirect_to" value="<?php echo esc_url( $current_url ); ?>" />
		<?php wp_nonce_field( 'lis_listing_update_' . $listing_id, 'lis_listing_update_nonce' ); ?>

		<div class="lis-listing-panel" data-panel-section="Basics" data-panel-question="What's your business called?">
			<p>
				<label for="lis_listing_business_name" class="screen-reader-text">Business Name</label>
				<input type="text" id="lis_listing_business_name" name="lis_listing_business_name" value="<?php echo esc_attr( $listing->post_title ); ?>" required />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Basics" data-panel-question="Which category fits best?">
			<p>
				<label for="lis_listing_category" class="screen-reader-text">Category</label>
				<select id="lis_listing_category" name="lis_listing_category" required>
					<option value="">— Select a category —</option>
					<?php foreach ( $cat_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $current_cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Basics" data-panel-question="Tell us about your business">
			<div class="lis-listing-submit-editor">
				<label for="lis_listing_description" class="screen-reader-text">Description</label>
				<?php
				wp_editor( $listing->post_content, 'lis_listing_description', array(
					'textarea_name' => 'lis_listing_description',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				) );
				?>
			</div>
			<?php if ( 'local-business' !== $type ) : ?>
				<p class="description">Directory type and its specific details (<?php echo 'job-listing' === $type ? 'salary, employment type' : 'bedrooms, bathrooms, sq ft'; ?>) aren't editable here yet — contact us if those need to change.</p>
			<?php endif; ?>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="Where are you located?">
			<p>
				<label for="lis_listing_address" class="screen-reader-text">Address</label>
				<input type="text" id="lis_listing_address" name="lis_listing_address" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_address', true ) ); ?>" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="What's your phone number?">
			<p>
				<label for="lis_listing_phone" class="screen-reader-text">Phone</label>
				<input type="text" id="lis_listing_phone" name="lis_listing_phone" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_phone', true ) ); ?>" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="What's your website?">
			<p>
				<label for="lis_listing_website" class="screen-reader-text">Website</label>
				<input type="url" id="lis_listing_website" name="lis_listing_website" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_website', true ) ); ?>" placeholder="https://" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="Best email to reach you?">
			<p>
				<label for="lis_listing_email" class="screen-reader-text">Contact Email</label>
				<input type="email" id="lis_listing_email" name="lis_listing_email" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_email', true ) ?: $current_user->user_email ); ?>" required />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Photos &amp; Video" data-panel-question="Your photos">
			<?php if ( ! empty( $gallery_ids ) ) : ?>
				<div class="lis-listing-edit-gallery">
					<?php foreach ( $gallery_ids as $attachment_id ) : ?>
						<?php $thumb = wp_get_attachment_image_url( $attachment_id, 'thumbnail' ); ?>
						<?php if ( $thumb ) : ?>
							<label class="lis-listing-edit-gallery-item">
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
								<span><input type="checkbox" name="lis_listing_remove_photos[]" value="<?php echo (int) $attachment_id; ?>" /> Remove</span>
							</label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p>
				<label for="lis_listing_photos" class="lis-listing-photos-dropzone">
					<span class="lis-listing-photos-dropzone-label">Click to choose photos, or drag them here</span>
					<span class="lis-listing-photos-dropzone-hint">PNG or JPG, up to 2MB each. Add more, or remove any above.</span>
				</label>
				<input type="file" id="lis_listing_photos" name="lis_listing_photos[]" accept="image/png,image/jpeg" multiple />
			</p>
			<div class="lis-listing-photos-preview" data-photos-preview></div>
		</div>

		<div class="lis-listing-panel" data-panel-section="Photos &amp; Video" data-panel-question="Got a video?" data-panel-hint="Optional — YouTube or Vimeo link">
			<p>
				<label for="lis_listing_video_url" class="screen-reader-text">Video</label>
				<input type="url" id="lis_listing_video_url" name="lis_listing_video_url" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_video_url', true ) ); ?>" placeholder="YouTube or Vimeo link" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Branding" data-panel-question="Your branding" data-panel-hint="All optional — but they unlock where your business can appear">
			<?php
			$edit_tagline  = get_post_meta( $listing_id, '_lis_listing_tagline', true );
			$edit_logo_id  = (int) get_post_meta( $listing_id, '_lis_listing_logo_id', true );
			$edit_card_id  = (int) get_post_meta( $listing_id, '_lis_listing_business_card_id', true );
			$edit_logo_url = $edit_logo_id ? wp_get_attachment_image_url( $edit_logo_id, 'thumbnail' ) : '';
			$edit_card_url = $edit_card_id ? wp_get_attachment_image_url( $edit_card_id, 'thumbnail' ) : '';
			?>
			<p>
				<label for="lis_pv_tagline">Tagline <span class="lis-listing-optional">(optional)</span></label>
				<input type="text" id="lis_pv_tagline" name="lis_pv_tagline" value="<?php echo esc_attr( $edit_tagline ); ?>" placeholder="e.g. For all your insurance needs" />
			</p>
			<p>
				<label for="lis_pv_logo_color">Logo (transparent PNG) <span class="lis-listing-optional">(optional)</span></label>
				<?php if ( $edit_logo_url ) : ?>
					<span class="lis-listing-edit-asset-current"><img src="<?php echo esc_url( $edit_logo_url ); ?>" alt="Current logo" /> <label><input type="checkbox" name="lis_listing_remove_logo" value="1" /> Remove</label></span>
				<?php endif; ?>
				<input type="file" id="lis_pv_logo_color" name="lis_pv_logo_color" accept="image/png" />
				<small>A transparent PNG looks cleanest. Without a logo, your business won't appear in the logo showcase.</small>
			</p>
			<p>
				<label for="lis_pv_business_card">Business card <span class="lis-listing-optional">(optional)</span></label>
				<?php if ( $edit_card_url ) : ?>
					<span class="lis-listing-edit-asset-current"><img src="<?php echo esc_url( $edit_card_url ); ?>" alt="Current business card" /> <label><input type="checkbox" name="lis_listing_remove_business_card" value="1" /> Remove</label></span>
				<?php endif; ?>
				<input type="file" id="lis_pv_business_card" name="lis_pv_business_card" accept="image/png,image/jpeg" />
				<small>Without one, your business won't appear in the business-card ticker.</small>
			</p>
		</div>

		<?php if ( function_exists( 'lis_directory_listing_is_active_showcase' ) && lis_directory_listing_is_active_showcase( $listing_id ) ) : ?>
			<div class="lis-listing-panel" data-panel-section="Vendor Showcase" data-panel-question="Running an offer?" data-panel-hint="Optional — a link visitors can follow from your listing page">
				<p>
					<label for="lis_listing_offer_url">Offer link</label>
					<input type="url" id="lis_listing_offer_url" name="lis_listing_offer_url" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_offer_url', true ) ); ?>" placeholder="https://" />
				</p>
				<p>
					<label for="lis_listing_offer_label">Button text <span class="lis-listing-optional">(optional)</span></label>
					<input type="text" id="lis_listing_offer_label" name="lis_listing_offer_label" value="<?php echo esc_attr( get_post_meta( $listing_id, '_lis_listing_offer_label', true ) ); ?>" placeholder="View Offer" />
				</p>
				<p class="description">Shown as a button on your listing page while your Vendor Showcase spot is active. Leave the link blank to hide the button.</p>
			</div>
		<?php endif; ?>

		<div class="lis-listing-panel" data-panel-section="Business Hours" data-panel-question="When are you open?" data-panel-hint="Leave a day blank if closed">
			<?php $no_hours = (bool) get_post_meta( $listing_id, '_lis_listing_no_hours', true ); ?>
			<p class="lis-listing-no-hours-toggle">
				<label><input type="checkbox" name="lis_listing_no_hours" value="1" <?php checked( $no_hours ); ?> /> No set hours (by appointment / not applicable)</label>
			</p>
			<div class="lis-listing-hours-wrap"<?php echo $no_hours ? ' style="display:none;"' : ''; ?>>
				<table class="lis-listing-submit-hours-table">
					<?php foreach ( LIS_DIRECTORY_WEEKDAYS as $day => $label ) : ?>
						<tr>
							<th><label for="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="time" id="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" value="<?php echo esc_attr( $week[ $day ]['open'] ?? '' ); ?>" />
								<span>to</span>
								<input type="time" id="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" value="<?php echo esc_attr( $week[ $day ]['close'] ?? '' ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
			<script>
			( function () {
				var panel = document.currentScript.closest( '.lis-listing-panel' );
				if ( ! panel ) { return; }
				var cb = panel.querySelector( 'input[name="lis_listing_no_hours"]' );
				var wrap = panel.querySelector( '.lis-listing-hours-wrap' );
				function sync() { if ( wrap ) { wrap.style.display = cb.checked ? 'none' : ''; } }
				cb.addEventListener( 'change', sync );
				sync();
			}() );
			</script>
		</div>

		<div class="lis-listing-panel" data-panel-section="Services" data-panel-question="What services do you offer?" data-panel-hint="One per line">
			<p>
				<label for="lis_listing_services" class="screen-reader-text">Services</label>
				<textarea id="lis_listing_services" name="lis_listing_services" rows="4"><?php echo esc_textarea( get_post_meta( $listing_id, '_lis_listing_services', true ) ); ?></textarea>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Features" data-panel-question="Any special features?">
			<?php if ( ! empty( $feature_terms ) ) : ?>
				<div class="lis-listing-submit-checkbox-grid">
					<?php foreach ( $feature_terms as $feature ) : ?>
						<label class="lis-listing-submit-checkbox">
							<input type="checkbox" name="lis_listing_features[]" value="<?php echo esc_attr( $feature->term_id ); ?>" <?php checked( in_array( $feature->term_id, $current_features, true ) ); ?> />
							<?php echo esc_html( $feature->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p class="lis-listing-feature-suggest">
				<label for="lis_listing_feature_new">Don't see it? Suggest one</label>
				<input type="text" id="lis_listing_feature_new" name="lis_listing_feature_new" maxlength="40" placeholder="e.g. Rooftop seating" />
				<span class="lis-listing-feature-suggest-hint">We'll review it before it shows publicly.</span>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Social Media" data-panel-question="Where can people follow you?" data-panel-hint="All optional">
			<?php foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn' ) as $network => $label ) : ?>
				<p><label for="lis_listing_<?php echo esc_attr( $network ); ?>"><?php echo esc_html( $label ); ?></label>
				<input type="url" id="lis_listing_<?php echo esc_attr( $network ); ?>" name="lis_listing_<?php echo esc_attr( $network ); ?>" value="<?php echo esc_attr( get_post_meta( $listing_id, "_lis_listing_{$network}", true ) ); ?>" /></p>
			<?php endforeach; ?>
		</div>

		<p class="lis-listing-real-submit"><button type="submit" class="lis-listing-submit-button">Save Changes</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function lis_directory_handle_listing_update() {
	$redirect_base = isset( $_POST['lis_listing_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to edit a listing.' );
	}

	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;

	if ( ! isset( $_POST['lis_listing_update_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_update_nonce'], 'lis_listing_update_' . $listing_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	$listing = get_post( $listing_id );
	if ( ! $listing || 'lis_listing' !== $listing->post_type || (int) $listing->post_author !== get_current_user_id() ) {
		wp_die( 'You can only edit your own listings.' );
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

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// New photos are optional on edit — pass required=false, unlike submission.
	$new_photo_ids = lis_directory_handle_listing_photos_upload( 'lis_listing_photos', false, $errors );

	// Branding assets — optional; a new upload replaces the stored one, a Remove
	// checkbox clears it.
	$new_logo_id = lis_directory_handle_logo_upload( 'lis_pv_logo_color', false, $errors );
	$new_card_id = lis_directory_handle_business_card_upload( 'lis_pv_business_card', $errors );

	if ( ! empty( $errors ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', implode( ',', array_unique( $errors ) ), $redirect_base ) );
		exit;
	}

	wp_update_post( array(
		'ID'           => $listing_id,
		'post_title'   => $business_name,
		'post_content' => isset( $_POST['lis_listing_description'] ) ? wp_kses_post( wp_unslash( $_POST['lis_listing_description'] ) ) : '',
	) );

	wp_set_post_terms( $listing_id, array( $term->term_id ), 'lis_listing_category' );

	update_post_meta( $listing_id, '_lis_listing_address', isset( $_POST['lis_listing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_address'] ) ) : '' );
	update_post_meta( $listing_id, '_lis_listing_phone', isset( $_POST['lis_listing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_phone'] ) ) : '' );
	update_post_meta( $listing_id, '_lis_listing_website', isset( $_POST['lis_listing_website'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_website'] ) ) : '' );
	update_post_meta( $listing_id, '_lis_listing_email', $email );
	update_post_meta( $listing_id, '_lis_listing_video_url', ! empty( $_POST['lis_listing_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_video_url'] ) ) : '' );
	update_post_meta( $listing_id, '_lis_listing_services', ! empty( $_POST['lis_listing_services'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lis_listing_services'] ) ) : '' );

	foreach ( array( 'facebook', 'instagram', 'twitter', 'linkedin' ) as $network ) {
		$field = "lis_listing_{$network}";
		update_post_meta( $listing_id, "_{$field}", ! empty( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : '' );
	}

	// Vendor Showcase offer — only reachable from a panel that's only shown
	// while the listing has an active Showcase entry, but re-checked here too
	// rather than trusting the form's own conditional rendering.
	if ( function_exists( 'lis_directory_listing_is_active_showcase' ) && lis_directory_listing_is_active_showcase( $listing_id ) ) {
		update_post_meta( $listing_id, '_lis_listing_offer_url', ! empty( $_POST['lis_listing_offer_url'] ) ? esc_url_raw( wp_unslash( $_POST['lis_listing_offer_url'] ) ) : '' );
		update_post_meta( $listing_id, '_lis_listing_offer_label', isset( $_POST['lis_listing_offer_label'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_offer_label'] ) ) : '' );
	}

	// Branding: tagline + logo + business card.
	$edit_tagline = isset( $_POST['lis_pv_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_pv_tagline'] ) ) : '';
	update_post_meta( $listing_id, '_lis_listing_tagline', $edit_tagline );
	if ( ! empty( $_POST['lis_listing_remove_logo'] ) ) {
		delete_post_meta( $listing_id, '_lis_listing_logo_id' );
	} elseif ( $new_logo_id ) {
		update_post_meta( $listing_id, '_lis_listing_logo_id', $new_logo_id );
	}
	if ( ! empty( $_POST['lis_listing_remove_business_card'] ) ) {
		delete_post_meta( $listing_id, '_lis_listing_business_card_id' );
	} elseif ( $new_card_id ) {
		update_post_meta( $listing_id, '_lis_listing_business_card_id', $new_card_id );
	}

	// Keep a linked Vendor Showcase entry's assets in sync, so the tickers/cards
	// reflect edits made here (its meta uses the _lis_pv_* keys).
	if ( function_exists( 'lis_directory_get_vendor_for_listing' ) ) {
		$linked_vendor = lis_directory_get_vendor_for_listing( $listing_id );
		if ( $linked_vendor ) {
			update_post_meta( $linked_vendor, '_lis_pv_tagline', $edit_tagline );
			$final_logo_id = (int) get_post_meta( $listing_id, '_lis_listing_logo_id', true );
			$final_card_id = (int) get_post_meta( $listing_id, '_lis_listing_business_card_id', true );
			if ( $final_logo_id ) {
				update_post_meta( $linked_vendor, '_lis_pv_logo_color_id', $final_logo_id );
			} else {
				delete_post_meta( $linked_vendor, '_lis_pv_logo_color_id' );
			}
			if ( $final_card_id ) {
				update_post_meta( $linked_vendor, '_lis_pv_business_card_id', $final_card_id );
			} else {
				delete_post_meta( $linked_vendor, '_lis_pv_business_card_id' );
			}
		}
	}

	// Gallery: remove any checked attachment IDs, then append newly uploaded ones.
	$gallery_raw = get_post_meta( $listing_id, '_lis_listing_gallery_ids', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
	if ( ! empty( $_POST['lis_listing_remove_photos'] ) ) {
		$remove_ids  = array_map( 'absint', (array) wp_unslash( $_POST['lis_listing_remove_photos'] ) );
		$gallery_ids = array_values( array_diff( $gallery_ids, $remove_ids ) );
	}
	$gallery_ids = array_merge( $gallery_ids, $new_photo_ids );
	update_post_meta( $listing_id, '_lis_listing_gallery_ids', implode( ',', $gallery_ids ) );
	if ( ! empty( $gallery_ids ) ) {
		set_post_thumbnail( $listing_id, $gallery_ids[0] );
	} else {
		// No gallery photos — fall back to the logo as the listing image if there is one.
		$final_logo = (int) get_post_meta( $listing_id, '_lis_listing_logo_id', true );
		if ( $final_logo ) {
			set_post_thumbnail( $listing_id, $final_logo );
		} else {
			delete_post_thumbnail( $listing_id );
		}
	}

	lis_directory_save_submitted_hours( $listing_id );
	lis_directory_save_submitted_features( $listing_id );

	wp_safe_redirect( add_query_arg( 'lis_listing_updated', '1', $redirect_base ) );
	exit;
}
