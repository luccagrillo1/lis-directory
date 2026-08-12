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

	$current_url  = remove_query_arg( array( 'lis_listing_submitted', 'lis_listing_error', 'lis_listing_draft_saved' ) );
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

	// Landed here after "Save & finish later" (?lis_listing_draft_saved=<draft id>).
	if ( ! empty( $_GET['lis_listing_draft_saved'] ) ) {
		$draft_id  = absint( wp_unslash( $_GET['lis_listing_draft_saved'] ) );
		$draft     = $draft_id ? get_post( $draft_id ) : null;
		$is_mine   = $draft && 'lis_listing' === $draft->post_type && (int) $draft->post_author === get_current_user_id();
		$resume_url = ( $is_mine && function_exists( 'lis_directory_get_listing_edit_url' ) ) ? lis_directory_get_listing_edit_url( $draft_id ) : '';
		?>
		<div class="lis-listing-thankyou">
			<div class="lis-listing-thankyou-icon" aria-hidden="true">
				<svg viewBox="0 0 52 52" width="56" height="56" role="img"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5" opacity="0.35"/><path fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M16 27.5l7 7 14-16"/></svg>
			</div>
			<h2 class="lis-listing-thankyou-title">Draft saved</h2>
			<p class="lis-listing-thankyou-text">We've saved your progress. Come back any time to finish and submit it — nothing goes live until you do. You'll also find it in your dashboard.</p>
			<div class="lis-listing-thankyou-actions">
				<?php if ( $resume_url ) : ?>
					<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $resume_url ); ?>">Continue editing</a>
				<?php endif; ?>
				<a class="lis-listing-thankyou-btn lis-listing-thankyou-btn--ghost" href="<?php echo esc_url( $current_url ); ?>">Start another listing</a>
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

		<div class="lis-listing-draft-bar">
			<button type="submit" name="lis_listing_save_draft" value="1" class="lis-listing-draft-save" formnovalidate>Save &amp; finish later</button>
			<span class="lis-listing-draft-hint">Saves what you've got so far — pick it back up any time from your dashboard.</span>
		</div>
		<script>
		( function () {
			var s = document.currentScript;
			var btn = s.previousElementSibling ? s.previousElementSibling.querySelector( '.lis-listing-draft-save' ) : null;
			if ( ! btn ) { return; }
			var form = btn.closest( 'form' );
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( window.tinymce ) { window.tinymce.triggerSave(); }
				if ( ! form.querySelector( 'input[name="lis_listing_save_draft"][type="hidden"]' ) ) {
					var i = document.createElement( 'input' );
					i.type = 'hidden'; i.name = 'lis_listing_save_draft'; i.value = '1';
					form.appendChild( i );
				}
				HTMLFormElement.prototype.submit.call( form ); // native submit: skips HTML5 validation + the wizard's submit backstop.
			} );
		}() );
		</script>

		<div class="lis-listing-panel" data-panel-section="Get Started" data-panel-question="What's your business called?" data-panel-hint="Start typing to find it on Google — or just type your name and fill the rest in yourself.">
			<p>
				<label for="lis_listing_business_name" class="screen-reader-text">Business Name</label>
				<input type="text" id="lis_listing_business_name" name="lis_listing_business_name" placeholder="Start typing your business name…" autocomplete="off" required />
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

		<div class="lis-listing-panel" data-panel-section="Branding" data-panel-question="Add your branding" data-panel-hint="All optional — but they unlock where your business can appear">
			<p>
				<label for="lis_pv_tagline">Tagline <span class="lis-listing-optional">(optional)</span></label>
				<input type="text" id="lis_pv_tagline" name="lis_pv_tagline" placeholder="e.g. For all your insurance needs" />
			</p>
			<p>
				<label for="lis_pv_logo_color">Logo (transparent PNG) <span class="lis-listing-optional">(optional)</span></label>
				<input type="file" id="lis_pv_logo_color" name="lis_pv_logo_color" accept="image/png" />
				<small>A transparent PNG looks cleanest — it's recolored to black or white where needed. Without a logo, your business won't appear in the logo showcase.</small>
			</p>
			<p>
				<label for="lis_pv_business_card">Business card <span class="lis-listing-optional">(optional)</span></label>
				<input type="file" id="lis_pv_business_card" name="lis_pv_business_card" accept="image/png,image/jpeg" />
				<small>A photo/scan of your card (3.5&quot;&times;2&quot;). Without one, your business won't appear in the business-card ticker.</small>
			</p>
		</div>

		<div class="lis-listing-panel" data-panel-section="Business Hours" data-panel-question="When are you open?" data-panel-hint="Leave a day blank if closed" data-google-fillable="true">
			<p class="lis-listing-no-hours-toggle">
				<label><input type="checkbox" name="lis_listing_no_hours" value="1" /> No set hours (by appointment / not applicable)</label>
			</p>
			<div class="lis-listing-hours-wrap">
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
			<script>
			( function () {
				var s = document.currentScript;
				var panel = s.closest( '.lis-listing-panel' );
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
		$fmt_price = function ( $price ) {
			if ( null === $price || '' === $price ) {
				return '—';
			}
			// Drop the ".00" on whole-number prices (all tiers are whole dollars),
			// but keep decimals if a price ever isn't round.
			$is_whole = ( (float) $price == (int) round( (float) $price ) );
			if ( ! function_exists( 'wc_price' ) ) {
				return '$' . ( $is_whole ? (string) (int) $price : number_format( (float) $price, 2 ) );
			}
			$decimals = $is_whole ? 0 : wc_get_price_decimals();
			return wp_strip_all_tags( wc_price( $price, array( 'decimals' => $decimals ) ) );
		};

		// Standard / Featured tier cards (product + monthly/annual price).
		$plan_tiers = array();
		if ( $std_pid ) {
			$plan_tiers['standard'] = array(
				'label' => 'Standard',
				'blurb' => 'Your business listed in the directory.',
				'm'     => $fmt_price( lis_directory_resolve_plan_variation( $std_pid, 'month' )['price'] ),
				'y'     => $fmt_price( lis_directory_resolve_plan_variation( $std_pid, 'year' )['price'] ),
			);
		}
		if ( $feat_pid ) {
			$plan_tiers['featured'] = array(
				'label' => 'Featured',
				'blurb' => 'A Featured badge and priority placement.',
				'm'     => $fmt_price( lis_directory_resolve_plan_variation( $feat_pid, 'month' )['price'] ),
				'y'     => $fmt_price( lis_directory_resolve_plan_variation( $feat_pid, 'year' )['price'] ),
			);
		}

		// Vendor Showcase — the exclusive one-per-category tier. Single-product
		// model: offered whenever the product exists. The category a buyer
		// occupies is their listing's OWN category (chosen earlier), so there's
		// no category picker here; if that category is already taken we warn at
		// this step (and the submit handler re-checks server-side).
		$vs_pid = function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ? lis_directory_get_vendor_showcase_product_id() : 0;
		$vs_product = ( $vs_pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $vs_pid ) : null;
		if ( $vs_product && 'publish' === $vs_product->get_status() ) {
			$vs_month_v = lis_directory_get_showcase_variation( 0, 'month' );
			$vs_year_v  = lis_directory_get_showcase_variation( 0, 'year' );
			$vs_month_p = $vs_month_v && wc_get_product( $vs_month_v ) ? wc_get_product( $vs_month_v )->get_price() : null;
			$vs_year_p  = $vs_year_v && wc_get_product( $vs_year_v ) ? wc_get_product( $vs_year_v )->get_price() : null;
			$plan_tiers['showcase'] = array(
				'label' => 'Vendor Showcase',
				'blurb' => 'The exclusive one-per-category slot — your logo in the showcase.',
				'm'     => $fmt_price( $vs_month_p ),
				'y'     => $fmt_price( $vs_year_p ),
			);
		}

		// Which listing categories already have an active showcase vendor — used
		// to warn (client-side) if the user's chosen category is occupied.
		$vs_taken_cats = array();
		if ( isset( $plan_tiers['showcase'] ) && function_exists( 'lis_directory_is_listing_category_showcase_taken' ) ) {
			foreach ( $cat_terms as $ct ) {
				if ( ! is_wp_error( $ct ) && lis_directory_is_listing_category_showcase_taken( $ct->term_id ) ) {
					$vs_taken_cats[] = (int) $ct->term_id;
				}
			}
		}
		$vs_contact_url = apply_filters( 'lis_directory_showcase_contact_url', home_url( '/contact/' ) );

		if ( ! empty( $plan_tiers ) ) :
			?>
			<div class="lis-listing-panel" data-panel-section="Your plan" data-panel-question="Choose your plan">
				<div class="lis-listing-plan-billing" role="radiogroup" aria-label="Billing">
					<label><input type="radio" name="lis_listing_billing" value="month" checked /> Monthly</label>
					<label><input type="radio" name="lis_listing_billing" value="year" /> Annually <span class="lis-listing-plan-save">save ~2 months</span></label>
				</div>
				<div class="lis-listing-plans">
					<?php foreach ( $plan_tiers as $key => $t ) : ?>
						<label class="lis-listing-plan-card">
							<input type="radio" name="lis_listing_plan" value="<?php echo esc_attr( $key ); ?>" required />
							<span class="lis-listing-plan-name"><?php echo esc_html( $t['label'] ); ?></span>
							<span class="lis-listing-plan-price" data-price-month="<?php echo esc_attr( $t['m'] . '/mo' ); ?>" data-price-year="<?php echo esc_attr( $t['y'] . '/yr' ); ?>"><?php echo esc_html( $t['m'] ); ?>/mo</span>
							<span class="lis-listing-plan-blurb"><?php echo esc_html( $t['blurb'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

				<?php if ( isset( $plan_tiers['showcase'] ) ) : ?>
					<div class="lis-listing-showcase-fields" hidden data-taken-cats="<?php echo esc_attr( wp_json_encode( $vs_taken_cats ) ); ?>">
						<div class="lis-listing-showcase-occupied" hidden>
							<p>This category is currently occupied. If you'd like to recommend a new category, or ask about filling this spot, <a href="<?php echo esc_url( $vs_contact_url ); ?>">contact us</a>.</p>
						</div>
						<div class="lis-listing-showcase-inputs">
							<p class="lis-listing-showcase-note">Your Vendor Showcase card uses the tagline, logo, and business card from the <strong>Branding</strong> step. Go back and add them if you haven't — a card without a logo won't appear in the logo showcase.</p>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<script>
			( function () {
				var form = document.currentScript.closest( 'form' );
				if ( ! form ) { return; }
				var showcase = form.querySelector( '.lis-listing-showcase-fields' );
				var taken = [];
				if ( showcase ) {
					try { taken = JSON.parse( showcase.getAttribute( 'data-taken-cats' ) || '[]' ); } catch ( e ) { taken = []; }
				}
				function sync() {
					var billing = ( form.querySelector( 'input[name="lis_listing_billing"]:checked' ) || {} ).value || 'month';
					form.querySelectorAll( '.lis-listing-plan-price' ).forEach( function ( el ) {
						el.textContent = 'year' === billing ? el.dataset.priceYear : el.dataset.priceMonth;
					} );
					var plan = ( form.querySelector( 'input[name="lis_listing_plan"]:checked' ) || {} ).value || '';
					if ( showcase ) {
						var on = 'showcase' === plan;
						showcase.hidden = ! on;
						var catSel = form.querySelector( '#lis_listing_category' );
						var chosen = catSel ? parseInt( catSel.value, 10 ) : 0;
						var occupied = on && chosen && taken.indexOf( chosen ) !== -1;
						var warn = showcase.querySelector( '.lis-listing-showcase-occupied' );
						var inputs = showcase.querySelector( '.lis-listing-showcase-inputs' );
						if ( warn ) { warn.hidden = ! occupied; }
						if ( inputs ) { inputs.hidden = !! occupied; }
						var submitBtn = form.querySelector( '.lis-listing-submit-button' );
						if ( submitBtn ) { submitBtn.disabled = !! occupied; }
					}
				}
				form.querySelectorAll( 'input[name="lis_listing_billing"], input[name="lis_listing_plan"]' ).forEach( function ( r ) {
					r.addEventListener( 'change', sync );
				} );
				var catSel = form.querySelector( '#lis_listing_category' );
				if ( catSel ) { catSel.addEventListener( 'change', sync ); }
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
		'pv_category'                       => 'Please pick a Vendor Showcase category.',
		'pv_category_taken'                 => 'That category slot was just taken — please pick another.',
		'lis_pv_logo_color'                 => 'A logo is required for the Vendor Showcase.',
		'lis_pv_logo_color_type'            => 'Logo must be a real PNG file with a transparent background.',
		'lis_pv_logo_color_too_large'       => 'Logo must be under 2MB.',
		'lis_pv_logo_color_upload_failed'   => 'Logo failed to upload — please try again.',
		'lis_pv_business_card_type'         => 'Business card must be a real PNG or JPEG file.',
		'lis_pv_business_card_too_large'    => 'Business card must be under 2MB.',
		'lis_pv_business_card_upload_failed' => 'Business card failed to upload — please try again.',
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

	// "Save & finish later": a relaxed save that never blocks on missing required
	// fields (no business name/category/email/photo requirement, no plan/checkout).
	// Bad uploads (wrong type/too large) are still surfaced.
	$is_draft = ! empty( $_POST['lis_listing_save_draft'] );

	$errors = array();

	$business_name = isset( $_POST['lis_listing_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_business_name'] ) ) : '';
	if ( '' === $business_name && ! $is_draft ) {
		$errors[] = 'business_name';
	}

	$term_id = isset( $_POST['lis_listing_category'] ) ? (int) $_POST['lis_listing_category'] : 0;
	$term    = $term_id ? get_term( $term_id, 'lis_listing_category' ) : null;
	if ( ( ! $term || is_wp_error( $term ) ) && ! $is_draft ) {
		$errors[] = 'category';
	}

	$email = isset( $_POST['lis_listing_email'] ) ? sanitize_email( wp_unslash( $_POST['lis_listing_email'] ) ) : '';
	if ( ( '' === $email || ! is_email( $email ) ) && ! $is_draft ) {
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

	$photo_ids = lis_directory_handle_listing_photos_upload( 'lis_listing_photos', ! $is_draft, $errors );

	// Branding assets — collected in the early "Branding" step, optional for
	// every listing (a listing without them just won't appear where those assets
	// are required). Processed ONCE here and reused by the Vendor Showcase vendor
	// below, so a file is never uploaded twice.
	$tagline = isset( $_POST['lis_pv_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_pv_tagline'] ) ) : '';
	$logo_id = lis_directory_handle_logo_upload( 'lis_pv_logo_color', false, $errors );
	$card_id = lis_directory_handle_business_card_upload( 'lis_pv_business_card', $errors );

	// Chosen plan (final wizard step). Skipped entirely for a "save as draft".
	$plan    = $is_draft ? '' : ( isset( $_POST['lis_listing_plan'] ) ? sanitize_key( wp_unslash( $_POST['lis_listing_plan'] ) ) : '' );
	$billing = ( isset( $_POST['lis_listing_billing'] ) && 'year' === $_POST['lis_listing_billing'] ) ? 'year' : 'month';

	// Vendor Showcase tier: single-product model. The category the vendor
	// occupies is the listing's OWN category ($term, chosen earlier) — there's
	// no separate slot picker. Mirror that one listing category to a
	// lis_vendor_category term just-in-time so the exclusivity engine works.
	// Logo/card/tagline are reused from the Branding step ($logo_id/$card_id/$tagline).
	$vendor_term       = null;
	$vendor_logo_id    = $logo_id;
	$vendor_card_id    = $card_id;
	$checkout_category = 0; // A lis_listing_category term id (for the taken-check in the checkout URL builder).
	if ( 'showcase' === $plan ) {
		if ( ! $term || is_wp_error( $term ) ) {
			$errors[] = 'category';
		} elseif ( function_exists( 'lis_directory_is_listing_category_showcase_taken' ) && lis_directory_is_listing_category_showcase_taken( $term_id ) ) {
			$errors[] = 'pv_category_taken';
		} else {
			$checkout_category = $term_id;
			// Mirror the listing category → vendor category (creates it if this
			// is the first showcase vendor in that category).
			$vendor_term_id = function_exists( 'lis_directory_vendor_category_for_listing_category' ) ? lis_directory_vendor_category_for_listing_category( $term_id ) : 0;
			$vendor_term    = $vendor_term_id ? get_term( $vendor_term_id, 'lis_vendor_category' ) : null;
			if ( ! $vendor_term || is_wp_error( $vendor_term ) ) {
				$errors[] = 'pv_category';
			}
		}
	}

	if ( ! empty( $errors ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', implode( ',', array_unique( $errors ) ), $redirect_base ) );
		exit;
	}

	// If the tier's product is live/purchasable we route through WooCommerce
	// checkout and the listing starts as a draft "awaiting payment" (auto-
	// published by the order-complete hook). If no purchasable product is
	// configured yet, fall back to the original free pending flow so the form
	// keeps working while products are still draft.
	$can_checkout = ( $plan && function_exists( 'lis_directory_build_listing_checkout_url' ) && '' !== lis_directory_build_listing_checkout_url( $plan, $billing, 0, $checkout_category ) );

	// draft = "save & finish later" OR awaiting payment; pending = free fallback reviewed in admin.
	$post_status = ( $is_draft || $can_checkout ) ? 'draft' : 'pending';

	$post_id = wp_insert_post( array(
		'post_type'      => 'lis_listing',
		'post_title'     => '' !== $business_name ? $business_name : 'Untitled listing',
		'post_content'   => $description,
		'post_status'    => $post_status,
		'post_author'    => get_current_user_id(),
		'comment_status' => 'open', // Explicit, not left to the site-wide Discussion default — a listing with reviews turned off has a permanently empty, unusable Reviews section (see Talus Rock Retreat, which predated this and needed a one-time fix).
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_error', 'save_failed', $redirect_base ) );
		exit;
	}

	if ( $term && ! is_wp_error( $term ) ) {
		wp_set_post_terms( $post_id, array( $term->term_id ), 'lis_listing_category' );
	}

	update_post_meta( $post_id, '_lis_listing_address', $address );
	update_post_meta( $post_id, '_lis_listing_phone', $phone );
	update_post_meta( $post_id, '_lis_listing_website', $website );
	update_post_meta( $post_id, '_lis_listing_email', $email );

	// Branding assets on the listing itself (used everywhere, not just Showcase).
	if ( '' !== $tagline ) {
		update_post_meta( $post_id, '_lis_listing_tagline', $tagline );
	}
	if ( $logo_id ) {
		update_post_meta( $post_id, '_lis_listing_logo_id', $logo_id );
	}
	if ( $card_id ) {
		update_post_meta( $post_id, '_lis_listing_business_card_id', $card_id );
	}

	if ( ! empty( $photo_ids ) ) {
		set_post_thumbnail( $post_id, $photo_ids[0] );
		update_post_meta( $post_id, '_lis_listing_gallery_ids', implode( ',', $photo_ids ) );
	}

	// Use the uploaded logo as the listing image when no photo set one.
	if ( $logo_id && ! has_post_thumbnail( $post_id ) ) {
		set_post_thumbnail( $post_id, $logo_id );
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

	// "Save & finish later": everything present is now stored on a draft the
	// submitter owns. Send them to the confirmation screen with a link to resume
	// editing — no plan, no checkout, no vendor entry.
	if ( $is_draft ) {
		wp_safe_redirect( add_query_arg( 'lis_listing_draft_saved', $post_id, $redirect_base ) );
		exit;
	}

	// Vendor Showcase: create the pending vendor entry now (linked to this
	// listing), so the whole application lives in the one wizard. Payment
	// activates it (see lis_directory_handle_featured_listing_order).
	if ( 'showcase' === $plan && $vendor_term && ! is_wp_error( $vendor_term ) ) {
		$vendor_id = wp_insert_post( array(
			'post_type'   => 'lis_preferred_vendor',
			'post_title'  => $business_name,
			'post_status' => 'publish', // The CPT isn't public; it uses its own _lis_pv_status workflow.
			'post_author' => get_current_user_id(),
		), true );
		if ( ! is_wp_error( $vendor_id ) ) {
			wp_set_post_terms( $vendor_id, array( $vendor_term->term_id ), 'lis_vendor_category' );
			lis_directory_set_vendor_status( $vendor_id, 'pending' );
			update_post_meta( $vendor_id, '_lis_pv_tagline', $tagline );
			update_post_meta( $vendor_id, '_lis_pv_contact_email', $email );
			update_post_meta( $vendor_id, '_lis_pv_link_url', get_permalink( $post_id ) );
			update_post_meta( $vendor_id, '_lis_pv_listing_id', $post_id );
			if ( $vendor_logo_id ) {
				update_post_meta( $vendor_id, '_lis_pv_logo_color_id', $vendor_logo_id );
			}
			if ( $vendor_card_id ) {
				update_post_meta( $vendor_id, '_lis_pv_business_card_id', $vendor_card_id );
			}
		}
	}

	if ( $can_checkout ) {
		update_post_meta( $post_id, '_lis_listing_pending_tier', $plan );
		update_post_meta( $post_id, '_lis_listing_pending_billing', $billing );
		$checkout_url = lis_directory_build_listing_checkout_url( $plan, $billing, $post_id, $checkout_category );
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
	// "No set hours" toggle: record the flag and clear any hours (e.g. ones
	// Google prefilled), so nothing shows for a by-appointment business.
	if ( ! empty( $_POST['lis_listing_no_hours'] ) ) {
		update_post_meta( $post_id, '_lis_listing_no_hours', 1 );
		foreach ( array_keys( LIS_DIRECTORY_WEEKDAYS ) as $day ) {
			delete_post_meta( $post_id, "_lis_listing_hours_{$day}_open" );
			delete_post_meta( $post_id, "_lis_listing_hours_{$day}_close" );
		}
		return;
	}
	delete_post_meta( $post_id, '_lis_listing_no_hours' );

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
