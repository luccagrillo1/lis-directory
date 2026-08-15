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

/**
 * FAQs, stored as a JSON-encoded array of {question, answer} pairs in a
 * single meta field rather than one meta row per Q&A — there's no fixed
 * number of them (that's the whole point of a dynamic add/remove-row admin
 * UI), and WordPress has no built-in "repeatable field group" primitive to
 * reach for instead. Always returns a clean, re-indexed array — never
 * trusts stored JSON blindly, since it round-trips through a raw meta
 * field that (in principle) something other than this admin UI could edit.
 */
function lis_directory_get_listing_faqs( $post_id ) {
	$raw = get_post_meta( $post_id, '_lis_listing_faqs', true );
	if ( ! $raw ) {
		return array();
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		return array();
	}
	$faqs = array();
	foreach ( $decoded as $pair ) {
		if ( ! empty( $pair['question'] ) && ! empty( $pair['answer'] ) ) {
			$faqs[] = array( 'question' => $pair['question'], 'answer' => $pair['answer'] );
		}
	}
	return $faqs;
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
		// Branding (also collected in the front-end "Branding" wizard step).
		'_lis_listing_tagline'          => 'string',
		'_lis_listing_logo_id'          => 'integer', // Logo attachment ID.
		'_lis_listing_business_card_id' => 'integer', // Business-card attachment ID.
		'_lis_listing_google_url'       => 'string',  // Google Maps / Business Profile link.
		// Vendor Showcase offer — a link the business provides through their
		// Showcase spot; shown as a button on the listing page only while
		// Vendor Showcase is active (see lis_directory_listing_is_active_showcase()).
		'_lis_listing_offer_url'        => 'string',
		'_lis_listing_offer_label'      => 'string', // Optional custom button text, defaults to "View Offer".
		// Plan / payment (normally set by the WooCommerce order flow; also
		// editable by hand here so an admin can grant/adjust without a purchase).
		'_lis_listing_pending_tier'     => 'string',  // '' | standard | featured | showcase.
		'_lis_listing_pending_billing'  => 'string',  // '' | month | year.
		'_lis_listing_featured'         => 'boolean',
		'_lis_listing_paid_order_id'    => 'integer', // WooCommerce order/subscription ID.
		// Standard is the required foundation for Featured/Showcase (see
		// lis_directory_get_listing_upgrade_url() in listings-pricing.php) —
		// tracked the same way Featured already tracks itself, so cancelling
		// the Standard subscription can find and revoke the upgrades too.
		'_lis_listing_standard_active'   => 'boolean',
		'_lis_listing_standard_order_id' => 'integer',
		'_lis_listing_facebook'     => 'string',
		'_lis_listing_instagram'    => 'string',
		'_lis_listing_twitter'      => 'string',
		'_lis_listing_linkedin'     => 'string',
		// Real Estate (Sale/Rent) only.
		'_lis_listing_bedrooms'     => 'string',
		'_lis_listing_bathrooms'    => 'string',
		'_lis_listing_sqft'         => 'string',
		'_lis_listing_sold'         => 'boolean', // Real Estate only — "Sold" for sale listings, "Rented" for rentals.
		// Job Listing only.
		'_lis_listing_salary'          => 'string',
		'_lis_listing_employment_type' => 'string',
		// JSON-encoded array of {question, answer} objects.
		'_lis_listing_faqs'            => 'string',
		// Original Directorist `at_biz_dir` post ID, for the one-time bulk
		// migration of real listings — lets the migration script skip a
		// listing it already created on a retried/resumed run instead of
		// creating duplicates.
		'_lis_listing_migrated_from'   => 'integer',
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
	$faqs       = lis_directory_get_listing_faqs( $post->ID );
	$sold       = (bool) get_post_meta( $post->ID, '_lis_listing_sold', true );

	// Branding.
	$tagline    = get_post_meta( $post->ID, '_lis_listing_tagline', true );
	$google_url = get_post_meta( $post->ID, '_lis_listing_google_url', true );
	$offer_url   = get_post_meta( $post->ID, '_lis_listing_offer_url', true );
	$offer_label = get_post_meta( $post->ID, '_lis_listing_offer_label', true );
	$logo_id    = (int) get_post_meta( $post->ID, '_lis_listing_logo_id', true );
	$card_id    = (int) get_post_meta( $post->ID, '_lis_listing_business_card_id', true );
	$logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$card_url   = $card_id ? wp_get_attachment_image_url( $card_id, 'medium' ) : '';

	// Plan / payment.
	$tier       = get_post_meta( $post->ID, '_lis_listing_pending_tier', true );
	$billing    = get_post_meta( $post->ID, '_lis_listing_pending_billing', true );
	$featured   = (bool) get_post_meta( $post->ID, '_lis_listing_featured', true );
	$order_id   = (int) get_post_meta( $post->ID, '_lis_listing_paid_order_id', true );

	// Expiry (meta registered in includes/listings-expiry.php).
	$expiry_raw   = get_post_meta( $post->ID, '_lis_listing_expiry', true );
	$expiry_date  = $expiry_raw ? substr( (string) $expiry_raw, 0, 10 ) : '';
	$never_expire = (bool) get_post_meta( $post->ID, '_lis_listing_never_expire', true );

	// Vendor Showcase: on when this listing has a linked, active vendor entry.
	$showcase_vendor = function_exists( 'lis_directory_get_vendor_for_listing' ) ? lis_directory_get_vendor_for_listing( $post->ID ) : 0;
	$showcase_on     = $showcase_vendor && 'active' === get_post_meta( $showcase_vendor, '_lis_pv_status', true );
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
		<tr class="lis-listing-type-fields lis-listing-type-fields--real-estate-sale lis-listing-type-fields--real-estate-rent">
			<th>Status</th>
			<td>
				<label><input type="checkbox" id="lis_listing_sold" name="lis_listing_sold" value="1" <?php checked( $sold ); ?> /> Mark as Sold / Rented</label>
				<p class="description">Shows a "Sold"/"Rented" badge and keeps the listing visible but flagged as no longer available.</p>
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
		<tr>
			<th><label for="lis_listing_tagline">Tagline</label></th>
			<td><input type="text" id="lis_listing_tagline" name="lis_listing_tagline" class="large-text" value="<?php echo esc_attr( $tagline ); ?>" placeholder="e.g. For all your insurance needs" /></td>
		</tr>
		<tr>
			<th><label for="lis_listing_google_url">Google Business link</label></th>
			<td>
				<input type="url" id="lis_listing_google_url" name="lis_listing_google_url" class="large-text" value="<?php echo esc_attr( $google_url ); ?>" placeholder="https://maps.google.com/... or Google Business Profile URL" />
				<p class="description">The business's Google Maps / Business Profile page — the link Google gives when you find the business. Optional; used as a "View on Google" link where shown.</p>
			</td>
		</tr>
		<tr>
			<th>Logo</th>
			<td>
				<input type="hidden" id="lis_listing_logo_id" name="lis_listing_logo_id" value="<?php echo (int) $logo_id; ?>" />
				<img id="lis_listing_logo_id_preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-height:64px;width:auto;vertical-align:middle;margin-right:8px;<?php echo $logo_url ? '' : 'display:none;'; ?>" />
				<button type="button" class="button lis-pv-upload-logo" data-target="lis_listing_logo_id">Select Logo</button>
				<button type="button" class="button lis-pv-remove-logo" data-target="lis_listing_logo_id" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">Remove</button>
				<p class="description">Transparent PNG works best. Also used as the listing image automatically when no listing image is set.</p>
			</td>
		</tr>
		<tr>
			<th>Business Card</th>
			<td>
				<input type="hidden" id="lis_listing_business_card_id" name="lis_listing_business_card_id" value="<?php echo (int) $card_id; ?>" />
				<img id="lis_listing_business_card_id_preview" src="<?php echo esc_url( $card_url ); ?>" style="max-height:80px;width:auto;vertical-align:middle;margin-right:8px;<?php echo $card_url ? '' : 'display:none;'; ?>" />
				<button type="button" class="button lis-pv-upload-logo" data-target="lis_listing_business_card_id">Select Business Card</button>
				<button type="button" class="button lis-pv-remove-logo" data-target="lis_listing_business_card_id" style="<?php echo $card_url ? '' : 'display:none;'; ?>">Remove</button>
				<p class="description">Shown in the business-card ticker (best at 3.5&times;2 proportions).</p>
			</td>
		</tr>
		<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
		<tr>
			<th><label for="lis_listing_owner">Owner</label></th>
			<td>
				<?php
				wp_dropdown_users( array(
					'name'             => 'lis_listing_owner',
					'selected'         => $post->post_author,
					'include_selected' => true,
					'show'             => 'display_name_with_login',
				) );
				?>
				<p class="description">The account that owns this listing (can edit it on the front end). Reassign to any user.</p>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><label for="lis_listing_pending_tier">Plan / Tier</label></th>
			<td>
				<select id="lis_listing_pending_tier" name="lis_listing_pending_tier">
					<?php
					$tier_opts = array( '' => '— None (free) —', 'standard' => 'Standard', 'featured' => 'Featured', 'showcase' => 'Vendor Showcase' );
					foreach ( $tier_opts as $val => $label ) :
						?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $tier, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="lis_listing_pending_billing" aria-label="Billing">
					<?php
					$bill_opts = array( '' => '— Billing —', 'month' => 'Monthly', 'year' => 'Annually' );
					foreach ( $bill_opts as $val => $label ) :
						?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $billing, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">The subscription plan this listing is on. Normally set by the WooCommerce order; editable here to grant/adjust by hand.</p>
			</td>
		</tr>
		<tr>
			<th>Featured</th>
			<td><label><input type="checkbox" name="lis_listing_featured" value="1" <?php checked( $featured ); ?> /> Show the Featured badge &amp; priority placement</label></td>
		</tr>
		<tr>
			<th>Vendor Showcase</th>
			<td>
				<input type="hidden" name="lis_listing_showcase_present" value="1" />
				<label><input type="checkbox" name="lis_listing_showcase" value="1" <?php checked( $showcase_on ); ?> /> Feature this listing in the Vendor Showcase</label>
				<p class="description">Creates &amp; activates a Vendor Showcase entry for this listing using its category, tagline, logo, and business card. One business per category &mdash; if the category is already taken by another vendor it's held as pending instead of active. Unchecking deactivates it.</p>
			</td>
		</tr>
		<tr>
			<th><label for="lis_listing_offer_url">Vendor Showcase offer</label></th>
			<td>
				<input type="url" id="lis_listing_offer_url" name="lis_listing_offer_url" class="regular-text" value="<?php echo esc_attr( $offer_url ); ?>" placeholder="https://…" />
				<br /><br />
				<label for="lis_listing_offer_label">Button text</label>
				<input type="text" id="lis_listing_offer_label" name="lis_listing_offer_label" class="regular-text" value="<?php echo esc_attr( $offer_label ); ?>" placeholder="View Offer" />
				<p class="description">An offer link this business is running through its Vendor Showcase spot &mdash; shown as a button on the listing page only while Vendor Showcase (above) is active. Button text is optional, defaults to &ldquo;View Offer&rdquo;.</p>
			</td>
		</tr>
		<tr>
			<th><label for="lis_listing_paid_order_id">Payment reference</label></th>
			<td>
				<input type="text" id="lis_listing_paid_order_id" name="lis_listing_paid_order_id" class="regular-text" value="<?php echo $order_id ? (int) $order_id : ''; ?>" placeholder="WooCommerce order / subscription ID" />
				<?php if ( $order_id ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" target="_blank">Open order</a>
				<?php endif; ?>
				<p class="description">The WooCommerce order/subscription this listing was paid through, if any.</p>
			</td>
		</tr>
		<tr>
			<th><label for="lis_listing_expiry">Expires</label></th>
			<td>
				<input type="date" id="lis_listing_expiry" name="lis_listing_expiry" value="<?php echo esc_attr( $expiry_date ); ?>" />
				<label style="margin-left:12px;"><input type="checkbox" name="lis_listing_never_expire" value="1" <?php checked( $never_expire ); ?> /> Never expires</label>
				<p class="description">Auto-unpublished after this date (checked once a day). Leave blank or tick "Never expires" to keep it live indefinitely.</p>
			</td>
		</tr>
		<tr>
			<th>FAQs</th>
			<td>
				<div id="lis_listing_faqs_rows">
					<?php foreach ( $faqs as $faq ) : ?>
						<div class="lis-listing-faq-row">
							<input type="text" name="lis_listing_faq_question[]" class="large-text" placeholder="Question" value="<?php echo esc_attr( $faq['question'] ); ?>" />
							<textarea name="lis_listing_faq_answer[]" class="large-text" rows="2" placeholder="Answer"><?php echo esc_textarea( $faq['answer'] ); ?></textarea>
							<button type="button" class="button lis-listing-faq-remove">Remove</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button" id="lis_listing_faq_add">+ Add FAQ</button>
				<template id="lis_listing_faq_row_template">
					<div class="lis-listing-faq-row">
						<input type="text" name="lis_listing_faq_question[]" class="large-text" placeholder="Question" value="" />
						<textarea name="lis_listing_faq_answer[]" class="large-text" rows="2" placeholder="Answer"></textarea>
						<button type="button" class="button lis-listing-faq-remove">Remove</button>
					</div>
				</template>
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

	( function () {
		var container = document.getElementById( 'lis_listing_faqs_rows' );
		var addBtn = document.getElementById( 'lis_listing_faq_add' );
		var template = document.getElementById( 'lis_listing_faq_row_template' );
		if ( ! container || ! addBtn || ! template ) {
			return;
		}
		addBtn.addEventListener( 'click', function () {
			container.appendChild( template.content.cloneNode( true ) );
		} );
		container.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'lis-listing-faq-remove' ) ) {
				e.target.closest( '.lis-listing-faq-row' ).remove();
			}
		} );
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
	update_post_meta( $post_id, '_lis_listing_sold', ! empty( $_POST['lis_listing_sold'] ) );
	if ( isset( $_POST['lis_listing_employment_type'] ) ) {
		$employment_type = sanitize_key( wp_unslash( $_POST['lis_listing_employment_type'] ) );
		$valid           = lis_directory_get_employment_types();
		update_post_meta( $post_id, '_lis_listing_employment_type', isset( $valid[ $employment_type ] ) ? $employment_type : '' );
	}
	// Branding.
	if ( isset( $_POST['lis_listing_tagline'] ) ) {
		update_post_meta( $post_id, '_lis_listing_tagline', sanitize_text_field( wp_unslash( $_POST['lis_listing_tagline'] ) ) );
	}
	if ( isset( $_POST['lis_listing_google_url'] ) ) {
		update_post_meta( $post_id, '_lis_listing_google_url', esc_url_raw( wp_unslash( $_POST['lis_listing_google_url'] ) ) );
	}
	if ( isset( $_POST['lis_listing_offer_url'] ) ) {
		update_post_meta( $post_id, '_lis_listing_offer_url', esc_url_raw( wp_unslash( $_POST['lis_listing_offer_url'] ) ) );
	}
	if ( isset( $_POST['lis_listing_offer_label'] ) ) {
		update_post_meta( $post_id, '_lis_listing_offer_label', sanitize_text_field( wp_unslash( $_POST['lis_listing_offer_label'] ) ) );
	}
	if ( isset( $_POST['lis_listing_logo_id'] ) ) {
		$logo_id = absint( $_POST['lis_listing_logo_id'] );
		if ( $logo_id ) {
			update_post_meta( $post_id, '_lis_listing_logo_id', $logo_id );
			// Use the logo as the listing image automatically when none is set yet.
			if ( ! has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $post_id, $logo_id );
			}
		} else {
			delete_post_meta( $post_id, '_lis_listing_logo_id' );
		}
	}
	if ( isset( $_POST['lis_listing_business_card_id'] ) ) {
		$card_id = absint( $_POST['lis_listing_business_card_id'] );
		if ( $card_id ) {
			update_post_meta( $post_id, '_lis_listing_business_card_id', $card_id );
		} else {
			delete_post_meta( $post_id, '_lis_listing_business_card_id' );
		}
	}

	// Plan / payment.
	if ( isset( $_POST['lis_listing_pending_tier'] ) ) {
		$t = sanitize_key( wp_unslash( $_POST['lis_listing_pending_tier'] ) );
		update_post_meta( $post_id, '_lis_listing_pending_tier', in_array( $t, array( 'standard', 'featured', 'showcase' ), true ) ? $t : '' );
	}
	if ( isset( $_POST['lis_listing_pending_billing'] ) ) {
		$b = sanitize_key( wp_unslash( $_POST['lis_listing_pending_billing'] ) );
		update_post_meta( $post_id, '_lis_listing_pending_billing', in_array( $b, array( 'month', 'year' ), true ) ? $b : '' );
	}
	update_post_meta( $post_id, '_lis_listing_featured', ! empty( $_POST['lis_listing_featured'] ) );
	if ( isset( $_POST['lis_listing_paid_order_id'] ) ) {
		$oid = absint( $_POST['lis_listing_paid_order_id'] );
		if ( $oid ) {
			update_post_meta( $post_id, '_lis_listing_paid_order_id', $oid );
		} else {
			delete_post_meta( $post_id, '_lis_listing_paid_order_id' );
		}
	}

	// Expiry.
	update_post_meta( $post_id, '_lis_listing_never_expire', ! empty( $_POST['lis_listing_never_expire'] ) );
	if ( isset( $_POST['lis_listing_expiry'] ) ) {
		$d = sanitize_text_field( wp_unslash( $_POST['lis_listing_expiry'] ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			update_post_meta( $post_id, '_lis_listing_expiry', $d . ' 23:59:59' );
		} else {
			delete_post_meta( $post_id, '_lis_listing_expiry' );
		}
	}

	// Vendor Showcase toggle — create/activate or deactivate the linked vendor.
	// Only act when the checkbox was actually part of the submitted meta box
	// (the hidden marker below). Without this guard, an editor loaded before the
	// checkbox existed would post no value and silently deactivate the vendor.
	if ( isset( $_POST['lis_listing_showcase_present'] ) && function_exists( 'lis_directory_sync_listing_showcase' ) ) {
		lis_directory_sync_listing_showcase( $post_id, ! empty( $_POST['lis_listing_showcase'] ) );
	}

	// Owner reassignment (admins only). wp_update_post() re-fires save_post, so
	// unhook this handler around it to avoid re-entrancy.
	if ( current_user_can( 'edit_others_posts' ) && isset( $_POST['lis_listing_owner'] ) ) {
		$new_author = absint( $_POST['lis_listing_owner'] );
		if ( $new_author && get_userdata( $new_author ) && $new_author !== (int) get_post_field( 'post_author', $post_id ) ) {
			remove_action( 'save_post_lis_listing', 'lis_directory_save_listing_meta_box' );
			wp_update_post( array( 'ID' => $post_id, 'post_author' => $new_author ) );
			add_action( 'save_post_lis_listing', 'lis_directory_save_listing_meta_box' );
		}
	}

	if ( isset( $_POST['lis_listing_faq_question'] ) ) {
		$questions = (array) wp_unslash( $_POST['lis_listing_faq_question'] );
		$answers   = isset( $_POST['lis_listing_faq_answer'] ) ? (array) wp_unslash( $_POST['lis_listing_faq_answer'] ) : array();
		$faqs      = array();
		foreach ( $questions as $i => $question ) {
			$question = sanitize_text_field( $question );
			$answer   = isset( $answers[ $i ] ) ? sanitize_textarea_field( $answers[ $i ] ) : '';
			if ( '' !== $question && '' !== $answer ) {
				$faqs[] = array( 'question' => $question, 'answer' => $answer );
			}
		}
		update_post_meta( $post_id, '_lis_listing_faqs', wp_json_encode( $faqs ) );
	}
}

/**
 * Manual "Vendor Showcase" toggle on a listing. When enabled, ensure a linked
 * `lis_preferred_vendor` exists (created if needed), synced from the listing's
 * own category / tagline / logo / business card, and set Active — unless the
 * listing's category is already held by another active vendor, in which case
 * it's held Pending (one business per category). When disabled, a linked active
 * vendor is set back to Pending (deactivated, not deleted).
 *
 * Reuses the same pieces as the wizard's showcase path: the linked-vendor
 * lookup, the just-in-time listing-category → vendor-category mirror, the
 * status choke point, and the category-conflict check.
 *
 * @param int  $listing_id
 * @param bool $enable
 */
function lis_directory_sync_listing_showcase( $listing_id, $enable ) {
	if ( ! function_exists( 'lis_directory_get_vendor_for_listing' ) || ! function_exists( 'lis_directory_set_vendor_status' ) ) {
		return;
	}
	$vendor_id = lis_directory_get_vendor_for_listing( $listing_id );

	if ( ! $enable ) {
		if ( $vendor_id && 'active' === get_post_meta( $vendor_id, '_lis_pv_status', true ) ) {
			lis_directory_set_vendor_status( $vendor_id, 'pending' );
		}
		return;
	}

	$listing = get_post( $listing_id );
	if ( ! $listing ) {
		return;
	}

	// The listing's own category → its mirrored vendor category (created on demand).
	$term_ids       = wp_get_post_terms( $listing_id, 'lis_listing_category', array( 'fields' => 'ids' ) );
	$listing_term   = ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) ? (int) $term_ids[0] : 0;
	$vendor_term_id = ( $listing_term && function_exists( 'lis_directory_vendor_category_for_listing_category' ) )
		? lis_directory_vendor_category_for_listing_category( $listing_term )
		: 0;

	if ( ! $vendor_id ) {
		$vendor_id = wp_insert_post( array(
			'post_type'   => 'lis_preferred_vendor',
			'post_title'  => $listing->post_title,
			'post_status' => 'publish', // Not public; uses its own _lis_pv_status workflow.
			'post_author' => $listing->post_author,
		), true );
		if ( is_wp_error( $vendor_id ) || ! $vendor_id ) {
			return;
		}
		update_post_meta( $vendor_id, '_lis_pv_listing_id', $listing_id );
	}

	if ( $vendor_term_id ) {
		wp_set_post_terms( $vendor_id, array( (int) $vendor_term_id ), 'lis_vendor_category' );
	}

	// Sync branding + link from the listing.
	update_post_meta( $vendor_id, '_lis_pv_tagline', (string) get_post_meta( $listing_id, '_lis_listing_tagline', true ) );
	update_post_meta( $vendor_id, '_lis_pv_link_url', get_permalink( $listing_id ) );
	update_post_meta( $vendor_id, '_lis_pv_contact_email', (string) get_post_meta( $listing_id, '_lis_listing_email', true ) );
	$logo = (int) get_post_meta( $listing_id, '_lis_listing_logo_id', true );
	$card = (int) get_post_meta( $listing_id, '_lis_listing_business_card_id', true );
	if ( $logo ) {
		update_post_meta( $vendor_id, '_lis_pv_logo_color_id', $logo );
	}
	if ( $card ) {
		update_post_meta( $vendor_id, '_lis_pv_business_card_id', $card );
	}

	// Activate unless the category is already taken by another active vendor.
	$conflict = function_exists( 'lis_directory_get_active_conflict' ) ? lis_directory_get_active_conflict( $vendor_id ) : null;
	lis_directory_set_vendor_status( $vendor_id, $conflict ? 'pending' : 'active' );
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
