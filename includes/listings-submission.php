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

/**
 * Shared by both [lis_listing_submit] and [lis_listing_edit] — loads the
 * Maps JavaScript API with the `places` library and this plugin's own
 * autocomplete script (assets/js/listing-places-autocomplete.js), which
 * binds to #lis_listing_business_name and auto-fills address/phone/
 * website/hours from Google's Place Details when a real business is
 * picked. No-op if no Maps API key is configured yet (Settings > LIS
 * Directory Settings) — same key already used for the single-listing and
 * archive maps, so nothing new to set up if that's already there.
 */
function lis_directory_enqueue_places_autocomplete() {
	$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );
	if ( ! $maps_api_key ) {
		return;
	}
	wp_enqueue_script( 'lis-directory-listing-places-autocomplete', LIS_DIRECTORY_URL . 'assets/js/listing-places-autocomplete.js', array(), LIS_DIRECTORY_VERSION, true );
	wp_enqueue_script(
		'lis-directory-google-maps-places',
		'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $maps_api_key ) . '&libraries=places&callback=lisDirectoryInitPlacesAutocomplete&loading=async',
		array( 'lis-directory-listing-places-autocomplete' ),
		null,
		true
	);
}

function lis_directory_render_listing_submission_form_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-photos', LIS_DIRECTORY_URL . 'assets/js/listing-photos.js', array(), LIS_DIRECTORY_VERSION, true );
	wp_enqueue_script( 'lis-directory-listing-form-wizard', LIS_DIRECTORY_URL . 'assets/js/listing-form-wizard.js', array(), LIS_DIRECTORY_VERSION, true );
	lis_directory_enqueue_places_autocomplete();

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
	$feature_terms = lis_directory_get_public_feature_terms();

	ob_start();

	// On a successful submit we land here via a redirect (?lis_listing_submitted=1).
	// Show a dedicated thank-you screen instead of the form — not a banner
	// sitting on top of the still-present wizard — and stop here.
	if ( isset( $_GET['lis_listing_submitted'] ) ) {
		?>
		<div class="lis-listing-thankyou">
			<div class="lis-listing-thankyou-icon" aria-hidden="true">
				<svg viewBox="0 0 52 52" width="56" height="56" role="img"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5" opacity="0.35"/><path fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M16 27.5l7 7 14-16"/></svg>
			</div>
			<h2 class="lis-listing-thankyou-title">Thanks — your listing is in!</h2>
			<p class="lis-listing-thankyou-text">We've received your submission and it's in the review queue. We'll publish it as soon as it's approved — you can check its status any time from your dashboard.</p>
			<div class="lis-listing-thankyou-actions">
				<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $current_url ); ?>">Submit another listing</a>
				<a class="lis-listing-thankyou-btn lis-listing-thankyou-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'lis_listing' ) ); ?>">Browse the directory</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
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

		<div class="lis-listing-panel" data-panel-section="Get Started" data-panel-question="What's your business called?" data-panel-hint="Start typing to find it on Google — or just type your name and fill the rest in yourself.">
			<p>
				<label for="lis_listing_business_name" class="screen-reader-text">Business Name</label>
				<input type="text" id="lis_listing_business_name" name="lis_listing_business_name" required />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Basics" data-panel-question="Which category fits best?">
			<p>
				<label for="lis_listing_category" class="screen-reader-text">Category</label>
				<select id="lis_listing_category" name="lis_listing_category" required>
					<option value="">— Select a category —</option>
					<?php foreach ( $cat_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Basics" data-panel-question="Tell us about your business">
			<div class="lis-listing-submit-editor">
				<label for="lis_listing_description" class="screen-reader-text">Description</label>
				<?php
				wp_editor( '', 'lis_listing_description', array(
					'textarea_name' => 'lis_listing_description',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				) );
				?>
			</div>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="Where are you located?" data-google-fillable="true">
			<p>
				<label for="lis_listing_address" class="screen-reader-text">Address</label>
				<input type="text" id="lis_listing_address" name="lis_listing_address" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="What's your phone number?" data-google-fillable="true">
			<p>
				<label for="lis_listing_phone" class="screen-reader-text">Phone</label>
				<input type="text" id="lis_listing_phone" name="lis_listing_phone" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="What's your website?" data-google-fillable="true">
			<p>
				<label for="lis_listing_website" class="screen-reader-text">Website</label>
				<input type="url" id="lis_listing_website" name="lis_listing_website" placeholder="https://" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Contact" data-panel-question="Best email to reach you?">
			<p>
				<label for="lis_listing_email" class="screen-reader-text">Contact Email</label>
				<input type="email" id="lis_listing_email" name="lis_listing_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Photos &amp; Video" data-panel-question="Show off your business" data-panel-hint="At least one photo required — up to <?php echo (int) LIS_DIRECTORY_SUBMIT_MAX_PHOTOS; ?>">
			<p>
				<label for="lis_listing_photos" class="lis-listing-photos-dropzone">
					<span class="lis-listing-photos-dropzone-label">Click to choose photos, or drag them here</span>
					<span class="lis-listing-photos-dropzone-hint">PNG or JPG, up to 2MB each. The first one becomes the main photo.</span>
				</label>
				<input type="file" id="lis_listing_photos" name="lis_listing_photos[]" accept="image/png,image/jpeg" multiple required />
			</p>
			<div class="lis-listing-photos-preview" data-photos-preview></div>
		</div>

		<div class="lis-listing-panel" data-panel-section="Photos &amp; Video" data-panel-question="Got a video?" data-panel-hint="Optional — YouTube or Vimeo link">
			<p>
				<label for="lis_listing_video_url" class="screen-reader-text">Video</label>
				<input type="url" id="lis_listing_video_url" name="lis_listing_video_url" placeholder="YouTube or Vimeo link" />
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Business Hours" data-panel-question="When are you open?" data-panel-hint="Leave a day blank if closed" data-google-fillable="true">
			<table class="lis-listing-submit-hours-table">
				<?php foreach ( LIS_DIRECTORY_WEEKDAYS as $day => $label ) : ?>
					<tr>
						<th><label for="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input type="time" id="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" />
							<span>to</span>
							<input type="time" id="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" />
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="lis-listing-panel" data-panel-section="Services" data-panel-question="What services do you offer?" data-panel-hint="One per line">
			<p>
				<label for="lis_listing_services" class="screen-reader-text">Services</label>
				<textarea id="lis_listing_services" name="lis_listing_services" rows="4" placeholder="Oil changes&#10;Tire rotation&#10;Brake inspection"></textarea>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Features" data-panel-question="Any special features?">
			<?php if ( ! empty( $feature_terms ) ) : ?>
				<div class="lis-listing-submit-checkbox-grid">
					<?php foreach ( $feature_terms as $feature ) : ?>
						<label class="lis-listing-submit-checkbox">
							<input type="checkbox" name="lis_listing_features[]" value="<?php echo esc_attr( $feature->term_id ); ?>" />
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

		<?php
		$std_pid  = function_exists( 'lis_directory_get_standard_listing_product_id' ) ? lis_directory_get_standard_listing_product_id() : 0;
		$feat_pid = (int) get_option( 'lis_directory_featured_listing_product_id' );
		$plan_tiers = array();
		if ( $std_pid ) {
			$plan_tiers['standard'] = array( 'label' => 'Standard', 'blurb' => 'Your business listed in the directory.', 'pid' => $std_pid );
		}
		if ( $feat_pid ) {
			$plan_tiers['featured'] = array( 'label' => 'Featured', 'blurb' => 'A Featured badge and priority placement.', 'pid' => $feat_pid );
		}
		$fmt_price = function ( $price ) {
			if ( null === $price || '' === $price ) {
				return '—';
			}
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price ) ) : ( '$' . $price );
		};
		if ( ! empty( $plan_tiers ) ) :
			?>
			<div class="lis-listing-panel" data-panel-section="Your plan" data-panel-question="Choose your plan">
				<div class="lis-listing-plan-billing" role="radiogroup" aria-label="Billing">
					<label><input type="radio" name="lis_listing_billing" value="month" checked /> Monthly</label>
					<label><input type="radio" name="lis_listing_billing" value="year" /> Annually <span class="lis-listing-plan-save">save ~2 months</span></label>
				</div>
				<div class="lis-listing-plans">
					<?php foreach ( $plan_tiers as $key => $t ) :
						$m = lis_directory_resolve_plan_variation( $t['pid'], 'month' );
						$y = lis_directory_resolve_plan_variation( $t['pid'], 'year' );
						?>
						<label class="lis-listing-plan-card">
							<input type="radio" name="lis_listing_plan" value="<?php echo esc_attr( $key ); ?>" required />
							<span class="lis-listing-plan-name"><?php echo esc_html( $t['label'] ); ?></span>
							<span class="lis-listing-plan-price" data-price-month="<?php echo esc_attr( $fmt_price( $m['price'] ) . '/mo' ); ?>" data-price-year="<?php echo esc_attr( $fmt_price( $y['price'] ) . '/yr' ); ?>"><?php echo esc_html( $fmt_price( $m['price'] ) ); ?>/mo</span>
							<span class="lis-listing-plan-blurb"><?php echo esc_html( $t['blurb'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php
				$vs_pid = function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ? lis_directory_get_vendor_showcase_product_id() : 0;
				$vs_url = $vs_pid ? get_permalink( $vs_pid ) : '';
				if ( $vs_url ) :
					?>
					<p class="lis-listing-plan-showcase-note">Want the exclusive one-per-category slot? Check out the <a href="<?php echo esc_url( $vs_url ); ?>">Vendor Showcase</a>.</p>
				<?php endif; ?>
			</div>
			<script>
			( function () {
				var form = document.currentScript.closest( 'form' );
				if ( ! form ) { return; }
				function sync() {
					var billing = ( form.querySelector( 'input[name="lis_listing_billing"]:checked' ) || {} ).value || 'month';
					form.querySelectorAll( '.lis-listing-plan-price' ).forEach( function ( el ) {
						el.textContent = 'year' === billing ? el.dataset.priceYear : el.dataset.priceMonth;
					} );
				}
				form.querySelectorAll( 'input[name="lis_listing_billing"]' ).forEach( function ( r ) {
					r.addEventListener( 'change', sync );
				} );
				sync();
			}() );
			</script>
		<?php endif; ?>

		<p class="lis-listing-real-submit"><button type="submit" class="lis-listing-submit-button">Submit</button></p>
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

	$description = isset( $_POST['lis_listing_description'] ) ? wp_kses_post( wp_unslash( $_POST['lis_listing_description'] ) ) : '';
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

	// Chosen plan (final wizard step). If the tier's product is live/purchasable
	// we route through WooCommerce checkout and the listing starts as a draft
	// "awaiting payment" (auto-published by the order-complete hook). If no
	// purchasable product is configured yet, fall back to the original free
	// pending flow so the form keeps working while products are still draft.
	$plan         = isset( $_POST['lis_listing_plan'] ) ? sanitize_key( wp_unslash( $_POST['lis_listing_plan'] ) ) : '';
	$billing      = ( isset( $_POST['lis_listing_billing'] ) && 'year' === $_POST['lis_listing_billing'] ) ? 'year' : 'month';
	$can_checkout = ( $plan && function_exists( 'lis_directory_build_listing_checkout_url' ) && '' !== lis_directory_build_listing_checkout_url( $plan, $billing, 0 ) );

	$post_id = wp_insert_post( array(
		'post_type'      => 'lis_listing',
		'post_title'     => $business_name,
		'post_content'   => $description,
		'post_status'    => $can_checkout ? 'draft' : 'pending', // draft = awaiting payment; pending = free fallback reviewed in admin.
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

	if ( $can_checkout ) {
		update_post_meta( $post_id, '_lis_listing_pending_tier', $plan );
		update_post_meta( $post_id, '_lis_listing_pending_billing', $billing );
		$checkout_url = lis_directory_build_listing_checkout_url( $plan, $billing, $post_id );
		if ( $checkout_url ) {
			wp_safe_redirect( $checkout_url );
			exit;
		}
	}

	// Free fallback (no purchasable product configured yet): the original
	// pending-listing + immediate thank-you behaviour.
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
	$valid_ids = array();

	// Checkbox selections: only IDs that resolve to real existing terms (a
	// public form must not be able to attach arbitrary term IDs).
	if ( ! empty( $_POST['lis_listing_features'] ) && is_array( $_POST['lis_listing_features'] ) ) {
		$submitted_ids = array_map( 'absint', wp_unslash( $_POST['lis_listing_features'] ) );
		foreach ( $submitted_ids as $term_id ) {
			$term = get_term( $term_id, 'lis_listing_feature' );
			if ( $term && ! is_wp_error( $term ) ) {
				$valid_ids[] = $term->term_id;
			}
		}
	}

	// "Suggest a feature" free text: creates any new term as pending (kept out
	// of public lists until an admin approves it — see listings-features.php).
	if ( isset( $_POST['lis_listing_feature_new'] ) ) {
		$valid_ids = array_merge( $valid_ids, lis_directory_ingest_suggested_features( wp_unslash( $_POST['lis_listing_feature_new'] ) ) );
	}

	$valid_ids = array_unique( array_map( 'intval', $valid_ids ) );
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
