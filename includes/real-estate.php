<?php
/**
 * Real estate (For Rent / For Sale) on `lis_listing`.
 *
 * NOT a separate post type. `lis_listing` already carried a directory-type
 * dimension (`_lis_listing_type`, includes/listings-meta.php) with
 * `real-estate-rent` / `real-estate-sale` values plus bedrooms / bathrooms /
 * sq ft / sold meta, a Sold/Rented dashboard toggle, the per-type
 * [lis_listing_grid] and the Directorist migration mapping. Real estate also
 * needs exactly what business listings already have — photo gallery upload,
 * address + map, contact box, owner dashboard, moderation, the expiry cron —
 * so this file adds the rent/sale field group, pages and rules on top of that
 * one model instead of forking it the way LIS Jobs (`lis_job`) did.
 *
 * What lives here:
 *   - field registry + save/validate (shared by wp-admin and the front-end form)
 *   - [lis_real_estate type="rent|sale"] — filterable archive (price, beds,
 *     baths, area) for an ordinary page
 *   - [lis_real_estate_submit] — front-end submit/edit form (wizard on create,
 *     flat on edit), honeypot + nonce, saves `pending`, emails the admin
 *   - single template routing (templates/single-real-estate.php), JSON-LD,
 *     noindex + sitemap exclusion for sold/rented listings
 *   - default expiry on publish + a settings page that shows what the daily
 *     expiry sweep would unpublish next
 *   - keeping real estate out of the business directory's archive/search
 *
 * Pricing: undecided. Listings are free and admin-moderated. See
 * lis_directory_real_estate_checkout_url() for the hook to attach a
 * WooCommerce product later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_real_estate_meta' );
add_action( 'init', 'lis_directory_register_real_estate_settings' );
add_action( 'admin_menu', 'lis_directory_add_real_estate_settings_page' );
add_action( 'lis_directory_listing_meta_box_type_rows', 'lis_directory_render_real_estate_admin_rows' );
add_action( 'save_post_lis_listing', 'lis_directory_save_real_estate_admin_fields', 20 );
add_action( 'transition_post_status', 'lis_directory_real_estate_expiry_on_publish', 10, 3 );
add_action( 'admin_post_lis_directory_submit_real_estate', 'lis_directory_handle_real_estate_submission' );
add_action( 'admin_post_lis_directory_create_real_estate_pages', 'lis_directory_handle_create_real_estate_pages' );
add_action( 'admin_post_lis_directory_run_expiry_now', 'lis_directory_handle_run_expiry_now' );
add_action( 'wp_head', 'lis_directory_real_estate_head', 1 );
add_action( 'template_redirect', 'lis_directory_real_estate_robots_header' );
add_filter( 'wp_robots', 'lis_directory_real_estate_wp_robots' );
add_filter( 'seopress_sitemaps_single_query', 'lis_directory_real_estate_sitemap_query', 10, 2 );
add_filter( 'wp_sitemaps_posts_query_args', 'lis_directory_real_estate_core_sitemap_query', 10, 2 );
add_shortcode( 'lis_real_estate', 'lis_directory_render_real_estate_shortcode' );
add_shortcode( 'lis_real_estate_submit', 'lis_directory_render_real_estate_submit_shortcode' );

/* ---------- Types + option lists ---------- */

/**
 * The two real-estate directory types → their short name.
 */
function lis_directory_get_real_estate_types() {
	return array(
		'real-estate-rent' => 'rent',
		'real-estate-sale' => 'sale',
	);
}

function lis_directory_is_real_estate_type( $type ) {
	return isset( lis_directory_get_real_estate_types()[ $type ] );
}

function lis_directory_is_real_estate_listing( $listing_id ) {
	return lis_directory_is_real_estate_type( lis_directory_get_listing_type( $listing_id ) );
}

/**
 * 'rent' / 'sale' (or a full type key) → the full type key, or ''.
 */
function lis_directory_real_estate_type_from( $value ) {
	$value = sanitize_key( (string) $value );
	if ( lis_directory_is_real_estate_type( $value ) ) {
		return $value;
	}
	$full = array_search( $value, lis_directory_get_real_estate_types(), true );
	return false === $full ? '' : $full;
}

/**
 * Communities a listing can be filed under — the "area" filter. Bonner County
 * plus the Bonners Ferry side of Boundary County; "Other" covers the rest.
 */
function lis_directory_get_real_estate_areas() {
	return apply_filters( 'lis_directory_real_estate_areas', array(
		'sandpoint'     => 'Sandpoint',
		'ponderay'      => 'Ponderay',
		'kootenai'      => 'Kootenai',
		'dover'         => 'Dover',
		'sagle'         => 'Sagle',
		'hope'          => 'Hope',
		'clark-fork'    => 'Clark Fork',
		'cocolalla'     => 'Cocolalla',
		'careywood'     => 'Careywood',
		'laclede'       => 'Laclede',
		'priest-river'  => 'Priest River',
		'priest-lake'   => 'Priest Lake',
		'oldtown'       => 'Oldtown',
		'bonners-ferry' => 'Bonners Ferry',
		'other'         => 'Other',
	) );
}

function lis_directory_get_real_estate_lease_terms() {
	return array(
		'month-to-month' => 'Month-to-month',
		'6-months'       => '6 months',
		'12-months'      => '12 months',
		'other'          => 'Other / ask',
	);
}

function lis_directory_get_real_estate_pet_options() {
	return array(
		'no'          => 'No pets',
		'yes'         => 'Pets allowed',
		'negotiable'  => 'Ask about pets',
	);
}

function lis_directory_get_real_estate_sale_statuses() {
	return array(
		'active'  => 'Active',
		'pending' => 'Sale pending',
		'sold'    => 'Sold',
	);
}

/**
 * The rent/sale field group. Keys map to `_lis_listing_{key}` meta. bedrooms,
 * bathrooms and sqft are the keys `lis_listing` already had for real estate;
 * address, phone, email and the photo gallery are the shared listing fields
 * and aren't repeated here.
 *
 * `types` = which listing types use the field. `input` = number|text|date|select.
 */
function lis_directory_get_real_estate_fields() {
	return array(
		'rent'           => array( 'label' => 'Monthly rent', 'types' => array( 'rent' ), 'input' => 'number', 'min' => 0, 'step' => 1, 'required' => true, 'prefix' => '$' ),
		'deposit'        => array( 'label' => 'Security deposit', 'types' => array( 'rent' ), 'input' => 'number', 'min' => 0, 'step' => 1, 'prefix' => '$' ),
		'sale_price'     => array( 'label' => 'Price', 'types' => array( 'sale' ), 'input' => 'number', 'min' => 0, 'step' => 1, 'required' => true, 'prefix' => '$' ),
		'sale_status'    => array( 'label' => 'Status', 'types' => array( 'sale' ), 'input' => 'select', 'options' => lis_directory_get_real_estate_sale_statuses(), 'required' => true ),
		'bedrooms'       => array( 'label' => 'Bedrooms', 'types' => array( 'rent', 'sale' ), 'input' => 'number', 'min' => 0, 'step' => 1, 'required' => true, 'hint' => '0 for a studio' ),
		'bathrooms'      => array( 'label' => 'Bathrooms', 'types' => array( 'rent', 'sale' ), 'input' => 'number', 'min' => 0, 'step' => 0.5, 'required' => true ),
		'sqft'           => array( 'label' => 'Square feet', 'types' => array( 'rent', 'sale' ), 'input' => 'number', 'min' => 0, 'step' => 1 ),
		'lot_size'       => array( 'label' => 'Lot size', 'types' => array( 'sale' ), 'input' => 'text', 'placeholder' => 'e.g. 0.25 acres' ),
		'year_built'     => array( 'label' => 'Year built', 'types' => array( 'sale' ), 'input' => 'number', 'min' => 1800, 'step' => 1 ),
		'available_date' => array( 'label' => 'Available', 'types' => array( 'rent' ), 'input' => 'date' ),
		'lease_term'     => array( 'label' => 'Lease term', 'types' => array( 'rent' ), 'input' => 'select', 'options' => lis_directory_get_real_estate_lease_terms() ),
		'pets'           => array( 'label' => 'Pets', 'types' => array( 'rent' ), 'input' => 'select', 'options' => lis_directory_get_real_estate_pet_options() ),
		'utilities'      => array( 'label' => 'Utilities included', 'types' => array( 'rent' ), 'input' => 'text', 'placeholder' => 'e.g. Water, sewer, trash' ),
		'area'           => array( 'label' => 'Area', 'types' => array( 'rent', 'sale' ), 'input' => 'select', 'options' => lis_directory_get_real_estate_areas(), 'required' => true ),
	);
}

/**
 * Fields for one listing type ('rent' or 'sale').
 */
function lis_directory_get_real_estate_fields_for( $short ) {
	return array_filter( lis_directory_get_real_estate_fields(), function ( $field ) use ( $short ) {
		return in_array( $short, $field['types'], true );
	} );
}

function lis_directory_register_real_estate_meta() {
	foreach ( lis_directory_get_real_estate_fields() as $key => $field ) {
		if ( in_array( $key, array( 'bedrooms', 'bathrooms', 'sqft' ), true ) ) {
			continue; // Already registered in includes/listings-meta.php.
		}
		register_post_meta( 'lis_listing', '_lis_listing_' . $key, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}

/* ---------- Reading values ---------- */

function lis_directory_get_real_estate_value( $listing_id, $key ) {
	return (string) get_post_meta( $listing_id, '_lis_listing_' . $key, true );
}

/**
 * The listing's headline price as a number (monthly rent or sale price), or 0.
 */
function lis_directory_get_real_estate_price( $listing_id ) {
	$key = 'real-estate-rent' === lis_directory_get_listing_type( $listing_id ) ? 'rent' : 'sale_price';
	return (float) lis_directory_get_real_estate_value( $listing_id, $key );
}

/**
 * "$1,450/mo", "$489,000", or '' when no price is set.
 */
function lis_directory_format_real_estate_price( $listing_id ) {
	$price = lis_directory_get_real_estate_price( $listing_id );
	if ( $price <= 0 ) {
		return '';
	}
	$text = '$' . number_format_i18n( $price, floor( $price ) == $price ? 0 : 2 );
	return 'real-estate-rent' === lis_directory_get_listing_type( $listing_id ) ? $text . '/mo' : $text;
}

/**
 * Sold (sale) or rented (rent) — off the market. One flag for both,
 * `_lis_listing_sold`, as before.
 */
function lis_directory_real_estate_is_off_market( $listing_id ) {
	return (bool) get_post_meta( $listing_id, '_lis_listing_sold', true );
}

/**
 * Mark a listing off the market or available again, keeping a sale listing's
 * status select in step with the flag (sold ↔ active).
 */
function lis_directory_set_real_estate_off_market( $listing_id, $off ) {
	update_post_meta( $listing_id, '_lis_listing_sold', (bool) $off );
	if ( 'real-estate-sale' === lis_directory_get_listing_type( $listing_id ) ) {
		$status = lis_directory_get_real_estate_value( $listing_id, 'sale_status' );
		if ( $off ) {
			update_post_meta( $listing_id, '_lis_listing_sale_status', 'sold' );
		} elseif ( 'sold' === $status || '' === $status ) {
			update_post_meta( $listing_id, '_lis_listing_sale_status', 'active' );
		}
	}
	lis_directory_sync_real_estate_seo( $listing_id );
}

/**
 * Status badge text: "Rented", "Sold", "Sale pending", or ''.
 */
function lis_directory_get_real_estate_status_label( $listing_id ) {
	$type = lis_directory_get_listing_type( $listing_id );
	if ( lis_directory_real_estate_is_off_market( $listing_id ) ) {
		return 'real-estate-rent' === $type ? 'Rented' : 'Sold';
	}
	if ( 'real-estate-sale' === $type && 'pending' === lis_directory_get_real_estate_value( $listing_id, 'sale_status' ) ) {
		return 'Sale pending';
	}
	return '';
}

/**
 * "3 bd · 2 ba · 1,450 sqft".
 */
function lis_directory_get_real_estate_facts_line( $listing_id ) {
	$beds  = lis_directory_get_real_estate_value( $listing_id, 'bedrooms' );
	$baths = lis_directory_get_real_estate_value( $listing_id, 'bathrooms' );
	$sqft  = lis_directory_get_real_estate_value( $listing_id, 'sqft' );
	return implode( ' · ', array_filter( array(
		'' !== $beds ? ( '0' === $beds ? 'Studio' : $beds . ' bd' ) : '',
		'' !== $baths ? $baths . ' ba' : '',
		'' !== $sqft && (float) $sqft > 0 ? number_format_i18n( (float) $sqft ) . ' sqft' : '',
	) ) );
}

function lis_directory_get_real_estate_area_label( $listing_id ) {
	$areas = lis_directory_get_real_estate_areas();
	$area  = lis_directory_get_real_estate_value( $listing_id, 'area' );
	return isset( $areas[ $area ] ) ? $areas[ $area ] : '';
}

/**
 * A field's display value on the single page ('' = nothing to show).
 */
function lis_directory_get_real_estate_display_value( $listing_id, $key ) {
	$fields = lis_directory_get_real_estate_fields();
	$value  = lis_directory_get_real_estate_value( $listing_id, $key );
	if ( '' === $value || ! isset( $fields[ $key ] ) ) {
		return '';
	}
	$field = $fields[ $key ];
	if ( 'select' === $field['input'] ) {
		return isset( $field['options'][ $value ] ) ? $field['options'][ $value ] : '';
	}
	if ( 'date' === $field['input'] ) {
		$ts = strtotime( $value );
		if ( ! $ts ) {
			return '';
		}
		return $ts <= current_time( 'timestamp' ) ? 'Now' : date_i18n( get_option( 'date_format' ), $ts );
	}
	if ( isset( $field['prefix'] ) && '$' === $field['prefix'] ) {
		return '$' . number_format_i18n( (float) $value, floor( (float) $value ) == (float) $value ? 0 : 2 );
	}
	if ( 'sqft' === $key ) {
		return number_format_i18n( (float) $value );
	}
	return $value;
}

/* ---------- Saving + validating (wp-admin and front end) ---------- */

/**
 * Sanitize one submitted field value against its definition. '' = blank/invalid.
 */
function lis_directory_sanitize_real_estate_value( $field, $raw ) {
	$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}
	switch ( $field['input'] ) {
		case 'number':
			$raw = str_replace( array( '$', ',', ' ' ), '', $raw );
			if ( ! is_numeric( $raw ) ) {
				return '';
			}
			$num = (float) $raw;
			if ( isset( $field['min'] ) && $num < $field['min'] ) {
				return '';
			}
			if ( (float) $field['step'] >= 1 ) {
				return (string) (int) round( $num );
			}
			return (string) ( round( $num * 2 ) / 2 ); // Bathrooms: halves.
		case 'date':
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
		case 'select':
			$raw = sanitize_key( $raw );
			return isset( $field['options'][ $raw ] ) ? $raw : '';
		default:
			return sanitize_text_field( $raw );
	}
}

/**
 * Error keys for a front-end submission of type $short ('rent'|'sale').
 * $src is unslashed POST data.
 */
function lis_directory_validate_real_estate_fields( $short, array $src ) {
	$errors = array();
	foreach ( lis_directory_get_real_estate_fields_for( $short ) as $key => $field ) {
		$posted = isset( $src[ 'lis_re_' . $key ] ) ? $src[ 'lis_re_' . $key ] : '';
		$value  = lis_directory_sanitize_real_estate_value( $field, $posted );
		if ( '' === $value && '' !== trim( (string) $posted ) ) {
			$errors[] = 'invalid_' . $key;
		} elseif ( '' === $value && ! empty( $field['required'] ) ) {
			$errors[] = 'required_' . $key;
		}
	}
	if ( 'sale' === $short && ! empty( $src['lis_re_year_built'] ) && (int) $src['lis_re_year_built'] > (int) gmdate( 'Y' ) + 2 ) {
		$errors[] = 'invalid_year_built';
	}
	return array_values( array_unique( $errors ) );
}

/**
 * Write the type's field group from $src (unslashed, `lis_re_{key}` names).
 * Only fields present in $src are touched, so a partial form can't wipe
 * anything it didn't show. Fields of the OTHER type are left alone (an admin
 * flipping rent ↔ sale doesn't lose data).
 */
function lis_directory_save_real_estate_fields( $listing_id, $type, array $src ) {
	$short = lis_directory_get_real_estate_types()[ $type ];
	foreach ( lis_directory_get_real_estate_fields_for( $short ) as $key => $field ) {
		if ( ! array_key_exists( 'lis_re_' . $key, $src ) ) {
			continue;
		}
		$value = lis_directory_sanitize_real_estate_value( $field, $src[ 'lis_re_' . $key ] );
		if ( '' === $value ) {
			delete_post_meta( $listing_id, '_lis_listing_' . $key );
		} else {
			update_post_meta( $listing_id, '_lis_listing_' . $key, $value );
		}
	}
	// A sale listing's status select is the source of truth for the shared
	// off-market flag (sold ↔ `_lis_listing_sold`).
	if ( 'real-estate-sale' === $type && array_key_exists( 'lis_re_sale_status', $src ) ) {
		update_post_meta( $listing_id, '_lis_listing_sold', 'sold' === lis_directory_get_real_estate_value( $listing_id, 'sale_status' ) );
	}
	lis_directory_sync_real_estate_seo( $listing_id );
}

/* ---------- wp-admin meta box rows (inside "Listing Details") ---------- */

function lis_directory_render_real_estate_admin_rows( $post ) {
	foreach ( lis_directory_get_real_estate_fields() as $key => $field ) {
		if ( in_array( $key, array( 'bedrooms', 'bathrooms', 'sqft' ), true ) ) {
			continue; // The existing Beds / Baths / Sq Ft row covers these.
		}
		$classes = 'lis-listing-type-fields';
		foreach ( $field['types'] as $short ) {
			$classes .= ' lis-listing-type-fields--real-estate-' . $short;
		}
		$value = lis_directory_get_real_estate_value( $post->ID, $key );
		$id    = 'lis_re_' . $key;
		?>
		<tr class="<?php echo esc_attr( $classes ); ?>">
			<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
			<td>
				<?php lis_directory_render_real_estate_input( $key, $field, $value, false ); ?>
				<?php if ( 'sale_status' === $key ) : ?>
					<p class="description">"Sold" shows a Sold badge, drops it from the For Sale page and hides it from search engines.</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
	?>
	<tr class="lis-listing-type-fields lis-listing-type-fields--real-estate-rent lis-listing-type-fields--real-estate-sale">
		<th>Real estate expiry</th>
		<td><p class="description">Published real estate listings always get an expiry: <?php echo (int) lis_directory_get_real_estate_duration_days( 'rent' ); ?> days for rentals, <?php echo (int) lis_directory_get_real_estate_duration_days( 'sale' ); ?> days for sales (LIS Listings &rarr; Real Estate). A blank Expires date is filled in on publish; tick "Never expires" below to opt one out.</p></td>
	</tr>
	<?php
}

/**
 * One input. Names are `lis_re_{key}` everywhere (admin + front end).
 */
function lis_directory_render_real_estate_input( $key, $field, $value, $front_end = true ) {
	$id       = 'lis_re_' . $key;
	$required = $front_end && ! empty( $field['required'] ) ? ' required' : '';
	if ( 'select' === $field['input'] ) {
		printf( '<select id="%1$s" name="%1$s"%2$s>', esc_attr( $id ), $required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal attribute.
		if ( 'sale_status' !== $key ) {
			echo '<option value="">— Select —</option>';
		}
		foreach ( $field['options'] as $opt => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
		}
		echo '</select>';
		return;
	}
	$attrs = '';
	foreach ( array( 'min', 'step', 'placeholder' ) as $attr ) {
		if ( isset( $field[ $attr ] ) ) {
			$attrs .= sprintf( ' %s="%s"', $attr, esc_attr( $field[ $attr ] ) );
		}
	}
	if ( 'number' === $field['input'] ) {
		$attrs .= ' inputmode="' . ( 0.5 === $field['step'] ? 'decimal' : 'numeric' ) . '"';
	}
	printf(
		'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s"%4$s%5$s%6$s />',
		esc_attr( $field['input'] ),
		esc_attr( $id ),
		esc_attr( $value ),
		$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal attribute.
		$front_end ? '' : ' class="regular-text"'
	);
}

/**
 * Admin save, after the main meta box save (priority 10) has stored the type.
 * Same nonce, so it only runs for a real meta box submit.
 */
function lis_directory_save_real_estate_admin_fields( $post_id ) {
	if ( ! isset( $_POST['lis_listing_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_meta_nonce'], 'lis_listing_save_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$type = lis_directory_get_listing_type( $post_id );
	if ( ! lis_directory_is_real_estate_type( $type ) ) {
		return;
	}
	$src = wp_unslash( $_POST );
	// The admin "Mark as Rented" checkbox (rent only) is saved by the main box.
	lis_directory_save_real_estate_fields( $post_id, $type, $src );
	lis_directory_maybe_stamp_real_estate_expiry( $post_id );
}

/* ---------- Expiry ---------- */

function lis_directory_register_real_estate_settings() {
	foreach ( array( 'lis_directory_re_rent_days', 'lis_directory_re_sale_days', 'lis_directory_re_rent_page_id', 'lis_directory_re_sale_page_id', 'lis_directory_re_submit_page_id' ) as $option ) {
		register_setting( 'lis_re_settings_group', $option, array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );
	}
}

/**
 * Posting window in days for 'rent' / 'sale'. Defaults: 60 / 180.
 */
function lis_directory_get_real_estate_duration_days( $short ) {
	$default = 'rent' === $short ? 60 : 180;
	$days    = get_option( 'lis_directory_re_' . $short . '_days', $default );
	return max( 1, (int) $days );
}

/**
 * Give a published real-estate listing an expiry when it doesn't have a
 * current one. Deliberately NEVER copies or keeps a stale date and always
 * honors `_lis_listing_never_expire` — the 2026-08-29 migration bug was a
 * stale `_expiry_date` carried over without checking never-expire, which
 * silently unpublished 77 listings.
 *
 * @param bool $restamp_past Also replace an expiry that's already in the past
 *                           (true on a fresh publish/republish; false on a
 *                           plain admin save so an admin can deliberately
 *                           date one into the past to expire it).
 */
function lis_directory_maybe_stamp_real_estate_expiry( $listing_id, $restamp_past = false ) {
	$type = lis_directory_get_listing_type( $listing_id );
	if ( ! lis_directory_is_real_estate_type( $type ) || 'publish' !== get_post_status( $listing_id ) ) {
		return;
	}
	if ( get_post_meta( $listing_id, '_lis_listing_never_expire', true ) ) {
		return;
	}
	$expiry = (string) get_post_meta( $listing_id, '_lis_listing_expiry', true );
	$stale  = '' !== $expiry && strtotime( $expiry ) < current_time( 'timestamp' );
	if ( '' !== $expiry && ! ( $stale && $restamp_past ) ) {
		return;
	}
	$days = lis_directory_get_real_estate_duration_days( lis_directory_get_real_estate_types()[ $type ] );
	update_post_meta( $listing_id, '_lis_listing_expiry', gmdate( 'Y-m-d', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) . ' 23:59:59' );
}

/**
 * First publish, or republishing an expired one: start a fresh window.
 */
function lis_directory_real_estate_expiry_on_publish( $new_status, $old_status, $post ) {
	if ( 'lis_listing' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	if ( ! lis_directory_is_real_estate_listing( $post->ID ) ) {
		return;
	}
	delete_post_meta( $post->ID, '_lis_listing_expired' );
	lis_directory_maybe_stamp_real_estate_expiry( $post->ID, true );
	lis_directory_sync_real_estate_seo( $post->ID );
}

/* ---------- Search engines ---------- */

/**
 * Sold/rented listings stay reachable but get noindex. Mirrored into
 * SEOPress's own per-post flag so SEOPress prints the noindex itself and
 * drops the listing from its XML sitemap.
 */
function lis_directory_sync_real_estate_seo( $listing_id ) {
	if ( ! lis_directory_is_real_estate_listing( $listing_id ) ) {
		return;
	}
	if ( lis_directory_real_estate_is_off_market( $listing_id ) ) {
		update_post_meta( $listing_id, '_seopress_robots_index', 'yes' );
		update_post_meta( $listing_id, '_lis_listing_re_noindex', 1 );
	} elseif ( get_post_meta( $listing_id, '_lis_listing_re_noindex', true ) ) {
		// Only undo a flag this plugin set, never one an editor set by hand.
		delete_post_meta( $listing_id, '_seopress_robots_index' );
		delete_post_meta( $listing_id, '_lis_listing_re_noindex' );
	}
}

function lis_directory_real_estate_should_noindex() {
	if ( ! is_singular( 'lis_listing' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return lis_directory_is_real_estate_listing( $id ) && lis_directory_real_estate_is_off_market( $id );
}

function lis_directory_real_estate_wp_robots( $robots ) {
	if ( lis_directory_real_estate_should_noindex() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
	}
	return $robots;
}

function lis_directory_real_estate_robots_header() {
	if ( lis_directory_real_estate_should_noindex() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow', true );
	}
}

/**
 * Keep sold/rented listings out of the SEOPress lis_listing sitemap (belt and
 * braces next to `_seopress_robots_index`).
 */
function lis_directory_real_estate_sitemap_query( $args, $post_type = '' ) {
	if ( 'lis_listing' !== $post_type && ( ! isset( $args['post_type'] ) || 'lis_listing' !== $args['post_type'] ) ) {
		return $args;
	}
	$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
	$args['meta_query'][] = lis_directory_real_estate_on_market_clause();
	return $args;
}

function lis_directory_real_estate_core_sitemap_query( $args, $post_type ) {
	return lis_directory_real_estate_sitemap_query( $args, $post_type );
}

/**
 * meta_query clause: anything not flagged sold/rented.
 */
function lis_directory_real_estate_on_market_clause() {
	return array(
		'relation' => 'OR',
		array( 'key' => '_lis_listing_sold', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_lis_listing_sold', 'value' => array( '', '0' ), 'compare' => 'IN' ),
	);
}

/**
 * meta_query clause: listings that are NOT real estate (business directory
 * surfaces). Listings saved before the type field existed have no type meta
 * and count as Local Business.
 */
function lis_directory_not_real_estate_clause() {
	return array(
		'relation' => 'OR',
		array( 'key' => '_lis_listing_type', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_lis_listing_type', 'value' => array_keys( lis_directory_get_real_estate_types() ), 'compare' => 'NOT IN' ),
	);
}

/* ---------- JSON-LD ---------- */

/**
 * schema.org markup for a real-estate single. A RealEstateListing (a WebPage
 * type) carrying an Offer — businessFunction Sell for a sale, LeaseOut with a
 * per-month UnitPriceSpecification for a rental — whose itemOffered is an
 * Accommodation. Accommodation (not Residence) because it's the type that
 * carries numberOfBedrooms / numberOfBathroomsTotal / floorSize / yearBuilt /
 * petsAllowed; Residence has none of those. Google has no real-estate rich
 * result, so this is plain descriptive markup.
 */
function lis_directory_get_real_estate_jsonld( $listing_id ) {
	$post = get_post( $listing_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return array();
	}
	$type  = lis_directory_get_listing_type( $listing_id );
	$rent  = 'real-estate-rent' === $type;
	$v     = function ( $key ) use ( $listing_id ) {
		return lis_directory_get_real_estate_value( $listing_id, $key );
	};
	$place = array(
		'@type' => 'Accommodation',
		'name'  => get_the_title( $listing_id ),
	);

	$address = array( '@type' => 'PostalAddress', 'addressRegion' => 'ID', 'addressCountry' => 'US' );
	$street  = (string) get_post_meta( $listing_id, '_lis_listing_address', true );
	if ( '' !== $street ) {
		$address['streetAddress'] = $street;
	}
	$area = lis_directory_get_real_estate_area_label( $listing_id );
	if ( '' !== $area && 'Other' !== $area ) {
		$address['addressLocality'] = $area;
	}
	$place['address'] = $address;

	if ( '' !== $v( 'bedrooms' ) ) {
		$place['numberOfBedrooms'] = (int) $v( 'bedrooms' );
	}
	if ( '' !== $v( 'bathrooms' ) ) {
		$baths = (float) $v( 'bathrooms' );
		$place['numberOfBathroomsTotal'] = (int) ceil( $baths );
		if ( floor( $baths ) != $baths ) {
			$place['numberOfFullBathrooms']    = (int) floor( $baths );
			$place['numberOfPartialBathrooms'] = 1;
		}
	}
	if ( (float) $v( 'sqft' ) > 0 ) {
		$place['floorSize'] = array( '@type' => 'QuantitativeValue', 'value' => (int) $v( 'sqft' ), 'unitCode' => 'FTK' );
	}
	if ( ! $rent && '' !== $v( 'year_built' ) ) {
		$place['yearBuilt'] = (int) $v( 'year_built' );
	}
	if ( $rent && in_array( $v( 'pets' ), array( 'yes', 'no' ), true ) ) {
		$place['petsAllowed'] = 'yes' === $v( 'pets' );
	}

	$images = array();
	$gallery = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $listing_id, '_lis_listing_gallery_ids', true ) ) ) );
	foreach ( array_slice( $gallery, 0, 6 ) as $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( $url ) {
			$images[] = $url;
		}
	}
	if ( ! $images && has_post_thumbnail( $listing_id ) ) {
		$images[] = get_the_post_thumbnail_url( $listing_id, 'large' );
	}

	$price = lis_directory_get_real_estate_price( $listing_id );
	$offer = array(
		'@type'            => 'Offer',
		'businessFunction' => $rent ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
		'priceCurrency'    => 'USD',
		'itemOffered'      => $place,
		'url'              => get_permalink( $listing_id ),
	);
	if ( $price > 0 ) {
		$offer['price'] = $price;
		if ( $rent ) {
			$offer['priceSpecification'] = array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $price,
				'priceCurrency' => 'USD',
				'unitCode'      => 'MON',
			);
		}
	}
	if ( lis_directory_real_estate_is_off_market( $listing_id ) ) {
		$offer['availability'] = 'https://schema.org/SoldOut';
	} elseif ( ! $rent && 'pending' === $v( 'sale_status' ) ) {
		$offer['availability'] = 'https://schema.org/LimitedAvailability';
	} else {
		$offer['availability'] = 'https://schema.org/InStock';
	}
	if ( $rent && '' !== $v( 'available_date' ) ) {
		$offer['availabilityStarts'] = $v( 'available_date' );
	}

	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'RealEstateListing',
		'name'       => get_the_title( $listing_id ),
		'url'        => get_permalink( $listing_id ),
		'datePosted' => get_post_time( 'Y-m-d', false, $listing_id ),
		'offers'     => $offer,
	);
	$description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 60, '…' );
	if ( '' !== $description ) {
		$data['description'] = $description;
	}
	if ( $images ) {
		$data['image'] = $images;
	}
	$lease = array( 'month-to-month' => 'P1M', '6-months' => 'P6M', '12-months' => 'P12M' );
	if ( $rent && isset( $lease[ $v( 'lease_term' ) ] ) ) {
		$data['leaseLength'] = $lease[ $v( 'lease_term' ) ];
	}
	return $data;
}

function lis_directory_real_estate_head() {
	if ( ! is_singular( 'lis_listing' ) ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! lis_directory_is_real_estate_listing( $id ) ) {
		return;
	}
	$json = lis_directory_get_real_estate_jsonld( $id );
	if ( $json ) {
		echo '<script type="application/ld+json" class="lis-re-schema">' . wp_json_encode( $json ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded; wp_json_encode escapes "</".
	}
}

/* ---------- Pages + settings ---------- */

/**
 * URL of the configured For Rent / For Sale / submit page, or ''.
 *
 * @param string $which rent|sale|submit
 */
function lis_directory_get_real_estate_page_url( $which ) {
	$page_id = (int) get_option( 'lis_directory_re_' . $which . '_page_id' );
	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return '';
	}
	return get_permalink( $page_id );
}

/**
 * Where an owner edits a real-estate listing: the submit page in edit mode.
 */
function lis_directory_get_real_estate_edit_url( $listing_id ) {
	$submit = lis_directory_get_real_estate_page_url( 'submit' );
	return $submit ? add_query_arg( 'listing_id', (int) $listing_id, $submit ) : '';
}

/**
 * PRICING HOOK — real-estate pricing hasn't been decided, so every new
 * listing is free and goes to `pending` for an admin to approve.
 *
 * To charge later, return a checkout URL from the
 * `lis_directory_real_estate_checkout_url` filter (e.g. an add-to-cart URL for
 * a WooCommerce product, carrying the listing id the way
 * lis_directory_build_listing_checkout_url() does for business listings). The
 * listing is then saved as a draft flagged `_lis_listing_awaiting_payment` and
 * the submitter is sent to that URL; whatever handles the paid order is
 * responsible for publishing it.
 *
 * @param int    $listing_id The just-created listing.
 * @param string $type       real-estate-rent | real-estate-sale.
 * @return string Checkout URL, or '' for the free moderated flow.
 */
function lis_directory_real_estate_checkout_url( $listing_id, $type ) {
	return (string) apply_filters( 'lis_directory_real_estate_checkout_url', '', (int) $listing_id, $type );
}

function lis_directory_add_real_estate_settings_page() {
	add_submenu_page( 'edit.php?post_type=lis_listing', 'Real Estate', 'Real Estate', 'manage_options', 'lis_re_settings', 'lis_directory_render_real_estate_settings_page' );
}

/**
 * Published listings the next daily sweep (lis_directory_run_expiry_check())
 * would unpublish — the same query, run read-only.
 */
function lis_directory_get_listings_due_for_expiry() {
	return get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => lis_directory_expiry_due_meta_query(),
	) );
}

function lis_directory_render_real_estate_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$flag    = isset( $_GET['lis_re_notice'] ) ? sanitize_key( wp_unslash( $_GET['lis_re_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$count   = isset( $_GET['lis_re_count'] ) ? absint( $_GET['lis_re_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notices = array(
		'pages_ok'   => 'Real estate pages are set up.',
		'expiry_ran' => sprintf( 'Expiry check ran: %d listing%s unpublished.', $count, 1 === $count ? '' : 's' ),
	);
	$re_listings = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => array( 'publish', 'pending', 'draft' ),
		'posts_per_page' => 50,
		'meta_query'     => array( array( 'key' => '_lis_listing_type', 'value' => array_keys( lis_directory_get_real_estate_types() ), 'compare' => 'IN' ) ),
	) );
	$due = lis_directory_get_listings_due_for_expiry();
	?>
	<div class="wrap">
		<h1>Real Estate</h1>
		<?php if ( isset( $notices[ $flag ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $flag ] ); ?></p></div>
		<?php endif; ?>
		<p>For Rent and For Sale listings are <code>lis_listing</code> posts with the Directory set to Real Estate. Pricing isn't set up: new listings are free and wait in <strong>Pending</strong> for approval (see <code>lis_directory_real_estate_checkout_url</code> in <code>includes/real-estate.php</code>).</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'lis_re_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="lis_directory_re_rent_days">Rental listing length (days)</label></th>
					<td><input type="number" min="1" id="lis_directory_re_rent_days" name="lis_directory_re_rent_days" value="<?php echo esc_attr( lis_directory_get_real_estate_duration_days( 'rent' ) ); ?>" class="small-text" /> <span class="description">Default 60.</span></td>
				</tr>
				<tr>
					<th><label for="lis_directory_re_sale_days">Sale listing length (days)</label></th>
					<td><input type="number" min="1" id="lis_directory_re_sale_days" name="lis_directory_re_sale_days" value="<?php echo esc_attr( lis_directory_get_real_estate_duration_days( 'sale' ) ); ?>" class="small-text" /> <span class="description">Default 180. Counted from the day it's published (or republished after expiring). A listing's own "Never expires" box opts it out.</span></td>
				</tr>
				<?php foreach ( array( 'rent' => array( 'For Rent page ID', '[lis_real_estate type="rent"]' ), 'sale' => array( 'For Sale page ID', '[lis_real_estate type="sale"]' ), 'submit' => array( '"List a property" page ID', '[lis_real_estate_submit]' ) ) as $which => $row ) : ?>
					<tr>
						<th><label for="lis_directory_re_<?php echo esc_attr( $which ); ?>_page_id"><?php echo esc_html( $row[0] ); ?></label></th>
						<td><input type="number" id="lis_directory_re_<?php echo esc_attr( $which ); ?>_page_id" name="lis_directory_re_<?php echo esc_attr( $which ); ?>_page_id" value="<?php echo esc_attr( (int) get_option( 'lis_directory_re_' . $which . '_page_id' ) ); ?>" class="small-text" /> <span class="description">Page containing <code><?php echo esc_html( $row[1] ); ?></code>.</span>
						<?php $url = lis_directory_get_real_estate_page_url( $which ); ?>
						<?php if ( $url ) : ?> <a href="<?php echo esc_url( $url ); ?>">view</a><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />
		<h2>Quick setup</h2>
		<p>Creates any missing For Rent / For Sale / List a property pages under the Real Estate page (<code>/services/real-estate/</code>) and points the settings above at them.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lis_directory_create_real_estate_pages" />
			<?php wp_nonce_field( 'lis_directory_create_real_estate_pages', 'lis_re_pages_nonce' ); ?>
			<?php submit_button( 'Create real estate pages', 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2>Real estate listings</h2>
		<?php if ( ! $re_listings ) : ?>
			<p>None yet.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Listing</th><th>Type</th><th>Status</th><th>Expires</th></tr></thead>
				<tbody>
				<?php foreach ( $re_listings as $listing ) : ?>
					<?php
					$types  = lis_directory_get_listing_types();
					$expiry = (string) get_post_meta( $listing->ID, '_lis_listing_expiry', true );
					$never  = (bool) get_post_meta( $listing->ID, '_lis_listing_never_expire', true );
					$label  = lis_directory_get_real_estate_status_label( $listing->ID );
					?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>"><?php echo esc_html( get_the_title( $listing ) ); ?></a></td>
						<td><?php echo esc_html( $types[ lis_directory_get_listing_type( $listing->ID ) ] ); ?></td>
						<td><?php echo esc_html( $listing->post_status . ( $label ? ' — ' . $label : '' ) . ( get_post_meta( $listing->ID, '_lis_listing_expired', true ) ? ' (expired)' : '' ) ); ?></td>
						<td><?php echo esc_html( $never ? 'Never' : ( $expiry ? $expiry : ( 'publish' === $listing->post_status ? '—' : 'set on publish' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<hr />
		<h2>Expiry check</h2>
		<p>The daily sweep unpublishes (to draft, flagged expired) every published listing, of any type, whose Expires date has passed and that isn't set to "Never expires". Next scheduled run:
			<strong><?php $next = wp_next_scheduled( 'lis_directory_daily_expiry_check' ); echo esc_html( $next ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'Y-m-d H:i' ) : 'not scheduled' ); ?></strong>.</p>
		<?php if ( ! $due ) : ?>
			<p>Nothing is due — the next run would unpublish 0 listings.</p>
		<?php else : ?>
			<p>The next run would unpublish these <?php echo (int) count( $due ); ?>:</p>
			<ul style="list-style:disc;margin-left:20px;">
				<?php foreach ( $due as $listing ) : ?>
					<li><a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>"><?php echo esc_html( get_the_title( $listing ) ); ?></a> — expired <?php echo esc_html( (string) get_post_meta( $listing->ID, '_lis_listing_expiry', true ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lis_directory_run_expiry_now" />
				<?php wp_nonce_field( 'lis_directory_run_expiry_now', 'lis_re_expiry_nonce' ); ?>
				<?php submit_button( 'Run the expiry check now', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function lis_directory_handle_run_expiry_now() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_re_expiry_nonce'] ) || ! wp_verify_nonce( $_POST['lis_re_expiry_nonce'], 'lis_directory_run_expiry_now' ) ) {
		wp_die( 'Security check failed.' );
	}
	$count = (int) lis_directory_run_expiry_check();
	wp_safe_redirect( add_query_arg( array( 'lis_re_notice' => 'expiry_ran', 'lis_re_count' => $count ), admin_url( 'edit.php?post_type=lis_listing&page=lis_re_settings' ) ) );
	exit;
}

function lis_directory_handle_create_real_estate_pages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_re_pages_nonce'] ) || ! wp_verify_nonce( $_POST['lis_re_pages_nonce'], 'lis_directory_create_real_estate_pages' ) ) {
		wp_die( 'Security check failed.' );
	}
	$parent = get_page_by_path( 'services/real-estate' );
	$pages  = array(
		'rent'   => array( 'Homes for Rent', 'for-rent', "<!-- wp:paragraph -->\n<p>Rentals in Sandpoint and around Bonner County, listed by their owners and managers.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[lis_real_estate type=\"rent\"]\n<!-- /wp:shortcode -->" ),
		'sale'   => array( 'Homes for Sale', 'for-sale', "<!-- wp:paragraph -->\n<p>Homes and property for sale in Sandpoint and around Bonner County, listed by their owners.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[lis_real_estate type=\"sale\"]\n<!-- /wp:shortcode -->" ),
		'submit' => array( 'List a Property', 'list-a-property', "<!-- wp:shortcode -->\n[lis_real_estate_submit]\n<!-- /wp:shortcode -->" ),
	);
	foreach ( $pages as $which => $page ) {
		$option  = 'lis_directory_re_' . $which . '_page_id';
		$current = (int) get_option( $option );
		if ( $current && 'publish' === get_post_status( $current ) ) {
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $page[0],
			'post_name'    => $page[1],
			'post_parent'  => $parent ? (int) $parent->ID : 0,
			'post_content' => $page[2],
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( $option, (int) $id );
		}
	}
	wp_safe_redirect( add_query_arg( 'lis_re_notice', 'pages_ok', admin_url( 'edit.php?post_type=lis_listing&page=lis_re_settings' ) ) );
	exit;
}

/* ---------- Archive: [lis_real_estate type="rent|sale"] ---------- */

/**
 * Filter inputs, sanitized. Every filter is optional.
 */
function lis_directory_read_real_estate_filters( array $src ) {
	$areas = lis_directory_get_real_estate_areas();
	$num   = function ( $key ) use ( $src ) {
		if ( ! isset( $src[ $key ] ) || '' === $src[ $key ] ) {
			return '';
		}
		$raw = str_replace( array( '$', ',' ), '', wp_unslash( (string) $src[ $key ] ) );
		return is_numeric( $raw ) && (float) $raw >= 0 ? (float) $raw : '';
	};
	$area  = isset( $src['re_area'] ) ? sanitize_key( wp_unslash( $src['re_area'] ) ) : '';
	$sort  = isset( $src['re_sort'] ) ? sanitize_key( wp_unslash( $src['re_sort'] ) ) : '';
	return array(
		'min'   => $num( 're_min' ),
		'max'   => $num( 're_max' ),
		'beds'  => $num( 're_beds' ),
		'baths' => $num( 're_baths' ),
		'area'  => isset( $areas[ $area ] ) ? $area : '',
		'sort'  => in_array( $sort, array( 'newest', 'price_asc', 'price_desc' ), true ) ? $sort : 'newest',
	);
}

function lis_directory_real_estate_query_args( $type, array $filters, $per_page, $paged ) {
	$price_key = 'real-estate-rent' === $type ? '_lis_listing_rent' : '_lis_listing_sale_price';
	$meta      = array(
		'relation' => 'AND',
		array( 'key' => '_lis_listing_type', 'value' => $type ),
		lis_directory_real_estate_on_market_clause(),
	);
	if ( '' !== $filters['min'] ) {
		$meta[] = array( 'key' => $price_key, 'value' => $filters['min'], 'compare' => '>=', 'type' => 'NUMERIC' );
	}
	if ( '' !== $filters['max'] ) {
		$meta[] = array( 'key' => $price_key, 'value' => $filters['max'], 'compare' => '<=', 'type' => 'NUMERIC' );
	}
	if ( '' !== $filters['beds'] ) {
		$meta[] = array( 'key' => '_lis_listing_bedrooms', 'value' => $filters['beds'], 'compare' => '>=', 'type' => 'NUMERIC' );
	}
	if ( '' !== $filters['baths'] ) {
		$meta[] = array( 'key' => '_lis_listing_bathrooms', 'value' => $filters['baths'], 'compare' => '>=', 'type' => 'DECIMAL(4,1)' );
	}
	if ( '' !== $filters['area'] ) {
		$meta[] = array( 'key' => '_lis_listing_area', 'value' => $filters['area'] );
	}

	$args = array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'meta_query'     => $meta,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( 'newest' !== $filters['sort'] ) {
		$args['meta_query']['lis_re_price'] = array( 'key' => $price_key, 'type' => 'NUMERIC', 'compare' => 'EXISTS' );
		$args['orderby'] = array( 'lis_re_price' => 'price_asc' === $filters['sort'] ? 'ASC' : 'DESC', 'date' => 'DESC' );
		unset( $args['order'] );
	}
	return $args;
}

function lis_directory_render_real_estate_shortcode( $atts ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	$atts = shortcode_atts( array( 'type' => 'rent', 'count' => -1, 'search' => 'yes' ), $atts, 'lis_real_estate' );
	$type = lis_directory_real_estate_type_from( $atts['type'] );
	if ( ! $type ) {
		return lis_directory_card_admin_hint( '[lis_real_estate] needs type="rent" or type="sale".' );
	}
	$short   = lis_directory_get_real_estate_types()[ $type ];
	$filters = lis_directory_read_real_estate_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$paged   = -1 === (int) $atts['count'];
	$page    = $paged ? max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ) : 1;
	$query   = new WP_Query( lis_directory_real_estate_query_args( $type, $filters, $paged ? LIS_DIRECTORY_LISTINGS_PER_PAGE : max( 1, (int) $atts['count'] ), $page ) );
	$action  = get_permalink();
	$submit  = lis_directory_get_real_estate_page_url( 'submit' );
	$active  = '' !== $filters['min'] . $filters['max'] . $filters['beds'] . $filters['baths'] . $filters['area'];

	$price_label = 'rent' === $short ? 'Rent' : 'Price';
	ob_start();
	?>
	<div class="lis-re-archive lis-re-archive--<?php echo esc_attr( $short ); ?>">
		<?php if ( 'no' !== $atts['search'] ) : ?>
			<form class="lis-listing-search-form lis-re-filters" method="get" action="<?php echo esc_url( $action ); ?>">
				<div class="lis-listing-search-row">
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Min <?php echo esc_html( strtolower( $price_label ) ); ?></span>
						<input type="number" min="0" step="1" inputmode="numeric" name="re_min" class="lis-listing-search-input" placeholder="No min" value="<?php echo esc_attr( $filters['min'] ); ?>" />
					</label>
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Max <?php echo esc_html( strtolower( $price_label ) ); ?></span>
						<input type="number" min="0" step="1" inputmode="numeric" name="re_max" class="lis-listing-search-input" placeholder="No max" value="<?php echo esc_attr( $filters['max'] ); ?>" />
					</label>
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Beds</span>
						<select name="re_beds" class="lis-listing-search-select">
							<option value="">Any</option>
							<?php foreach ( array( 1, 2, 3, 4, 5 ) as $n ) : ?>
								<option value="<?php echo (int) $n; ?>" <?php selected( (string) $filters['beds'], (string) $n ); ?>><?php echo (int) $n; ?>+</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Baths</span>
						<select name="re_baths" class="lis-listing-search-select">
							<option value="">Any</option>
							<?php foreach ( array( 1, 2, 3, 4 ) as $n ) : ?>
								<option value="<?php echo (int) $n; ?>" <?php selected( (string) $filters['baths'], (string) $n ); ?>><?php echo (int) $n; ?>+</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Area</span>
						<select name="re_area" class="lis-listing-search-select">
							<option value="">All areas</option>
							<?php foreach ( lis_directory_get_real_estate_areas() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['area'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="lis-re-filter">
						<span class="lis-re-filter-label">Sort</span>
						<select name="re_sort" class="lis-listing-search-select">
							<option value="newest" <?php selected( $filters['sort'], 'newest' ); ?>>Newest</option>
							<option value="price_asc" <?php selected( $filters['sort'], 'price_asc' ); ?>><?php echo esc_html( $price_label ); ?>: low to high</option>
							<option value="price_desc" <?php selected( $filters['sort'], 'price_desc' ); ?>><?php echo esc_html( $price_label ); ?>: high to low</option>
						</select>
					</label>
					<button type="submit" class="lis-listing-search-submit">Search</button>
				</div>
				<?php if ( $active ) : ?>
					<p class="lis-re-filters-reset"><a class="lis-listing-search-reset" href="<?php echo esc_url( $action ); ?>">Clear filters</a></p>
				<?php endif; ?>
			</form>
		<?php endif; ?>

		<?php if ( $submit ) : ?>
			<p class="lis-re-archive-cta"><a class="lis-re-btn" href="<?php echo esc_url( add_query_arg( 'type', $short, $submit ) ); ?>"><?php echo 'rent' === $short ? 'List a rental' : 'List a home for sale'; ?></a></p>
		<?php endif; ?>

		<?php if ( $query->have_posts() ) : ?>
			<div class="lis-listing-grid lis-re-grid">
				<?php
				foreach ( $query->posts as $listing ) {
					echo lis_directory_render_real_estate_card( $listing->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				}
				?>
			</div>
			<?php
			if ( $paged ) {
				echo lis_directory_render_listing_pagination( $query->max_num_pages, $page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by paginate_links().
			}
			?>
		<?php else : ?>
			<p class="lis-listing-empty"><?php echo $active ? 'Nothing matches those filters right now.' : ( 'rent' === $short ? 'No rentals listed right now.' : 'No homes for sale listed right now.' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}

/**
 * One real-estate card — the listing-card markup with price, facts and area.
 */
function lis_directory_render_real_estate_card( $listing_id ) {
	$gallery  = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $listing_id, '_lis_listing_gallery_ids', true ) ) ) );
	$thumb_id = $gallery ? reset( $gallery ) : (int) get_post_thumbnail_id( $listing_id );
	$price    = lis_directory_format_real_estate_price( $listing_id );
	$facts    = lis_directory_get_real_estate_facts_line( $listing_id );
	$area     = lis_directory_get_real_estate_area_label( $listing_id );
	$status   = lis_directory_get_real_estate_status_label( $listing_id );
	$type     = lis_directory_get_listing_type( $listing_id );
	$avail    = 'real-estate-rent' === $type ? lis_directory_get_real_estate_display_value( $listing_id, 'available_date' ) : '';

	ob_start();
	?>
	<a class="lis-listing-card lis-re-card" href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>">
		<span class="lis-listing-card-thumb-wrap">
			<?php if ( $thumb_id ) : ?>
				<?php echo wp_get_attachment_image( $thumb_id, 'medium_large', false, array( 'class' => 'lis-listing-card-thumb', 'alt' => lis_directory_listing_image_alt( $thumb_id, $listing_id ) ) ); ?>
			<?php else : ?>
				<?php echo lis_directory_render_listing_fallback_image( $listing_id, 'card' ); // phpcs:ignore -- escaped inside helper. ?>
			<?php endif; ?>
			<?php if ( $status ) : ?>
				<span class="lis-listing-card-thumb-badges lis-listing-card-thumb-badges--left">
					<span class="lis-listing-badge <?php echo 'Sale pending' === $status ? 'lis-re-badge--pending' : 'lis-listing-badge--sold'; ?>"><?php echo esc_html( $status ); ?></span>
				</span>
			<?php endif; ?>
		</span>
		<span class="lis-listing-card-body">
			<?php if ( $price ) : ?><span class="lis-re-card-price"><?php echo esc_html( $price ); ?></span><?php endif; ?>
			<span class="lis-listing-card-title"><?php echo esc_html( get_the_title( $listing_id ) ); ?></span>
			<?php if ( $facts ) : ?><span class="lis-listing-card-facts"><?php echo esc_html( $facts ); ?></span><?php endif; ?>
			<?php if ( $area || $avail ) : ?>
				<span class="lis-re-card-meta"><?php echo esc_html( implode( ' · ', array_filter( array( $area, $avail ? 'Available ' . ( 'Now' === $avail ? 'now' : $avail ) : '' ) ) ) ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

/* ---------- Submit / edit: [lis_real_estate_submit] ---------- */

function lis_directory_real_estate_error_message( $key ) {
	$fields = lis_directory_get_real_estate_fields();
	if ( 0 === strpos( $key, 'required_' ) && isset( $fields[ substr( $key, 9 ) ] ) ) {
		return $fields[ substr( $key, 9 ) ]['label'] . ' is required.';
	}
	if ( 0 === strpos( $key, 'invalid_' ) && isset( $fields[ substr( $key, 8 ) ] ) ) {
		return $fields[ substr( $key, 8 ) ]['label'] . ' doesn\'t look right — please check it.';
	}
	$messages = array(
		'type'                             => 'Choose whether this is for rent or for sale.',
		'title'                            => 'Give the listing a title.',
		'email'                            => 'A valid contact email is required.',
		'lis_re_photos'                    => 'At least one photo is required.',
		'lis_re_photos_type'               => 'Photos must be real PNG or JPG files.',
		'lis_re_photos_too_large'          => 'Each photo must be under 2MB.',
		'lis_re_photos_upload_failed'      => 'One or more photos failed to upload — please try again.',
		'save_failed'                      => 'Something went wrong saving the listing — please try again.',
	);
	return isset( $messages[ $key ] ) ? $messages[ $key ] : 'Please check the form and try again.';
}

/**
 * Whether the current user may edit this real-estate listing on the front end.
 */
function lis_directory_user_can_edit_real_estate( $listing_id ) {
	$listing = get_post( $listing_id );
	return $listing && 'lis_listing' === $listing->post_type && is_user_logged_in()
		&& lis_directory_is_real_estate_listing( $listing_id )
		&& ( (int) $listing->post_author === get_current_user_id() || current_user_can( 'edit_post', $listing_id ) );
}

function lis_directory_render_real_estate_submit_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'type' => '' ), $atts, 'lis_real_estate_submit' );
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-photos', LIS_DIRECTORY_URL . 'assets/js/listing-photos.js', array(), LIS_DIRECTORY_VERSION, true );

	if ( ! is_user_logged_in() ) {
		return '<p class="lis-listing-submit-login-required">You need an account to list a property. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log in</a> or <a href="' . esc_url( wp_registration_url() ) . '">register</a> first.</p>';
	}

	$listing_id = isset( $_GET['listing_id'] ) ? absint( $_GET['listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$listing    = null;
	if ( $listing_id ) {
		if ( ! lis_directory_user_can_edit_real_estate( $listing_id ) ) {
			return '<p class="lis-listing-submit-errors">You can only edit your own listings.</p>';
		}
		$listing = get_post( $listing_id );
	}
	$editing = (bool) $listing;
	if ( ! $editing ) {
		wp_enqueue_script( 'lis-directory-listing-form-wizard', LIS_DIRECTORY_URL . 'assets/js/listing-form-wizard.js', array(), LIS_DIRECTORY_VERSION, true );
	}

	$current_url = remove_query_arg( array( 'lis_re_error', 'lis_re_submitted', 'lis_re_updated' ) );

	if ( isset( $_GET['lis_re_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$archive = lis_directory_get_real_estate_page_url( 'rent' === sanitize_key( wp_unslash( $_GET['lis_re_submitted'] ) ) ? 'rent' : 'sale' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		ob_start();
		?>
		<div class="lis-listing-thankyou">
			<div class="lis-listing-thankyou-icon" aria-hidden="true">
				<svg viewBox="0 0 52 52" width="56" height="56" role="img"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5" opacity="0.35"/><path fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M16 27.5l7 7 14-16"/></svg>
			</div>
			<h2 class="lis-listing-thankyou-title">Thanks — your listing is in!</h2>
			<p class="lis-listing-thankyou-text">It's in the review queue and will go live once it's approved. You can check its status from your dashboard.</p>
			<div class="lis-listing-thankyou-actions">
				<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $current_url ); ?>">List another property</a>
				<?php if ( $archive ) : ?>
					<a class="lis-listing-thankyou-btn lis-listing-thankyou-btn--ghost" href="<?php echo esc_url( $archive ); ?>">Browse listings</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// After a failed save, re-show what was typed (stashed by the handler).
	$prefill = array();
	$errors  = array();
	if ( ! empty( $_GET['lis_re_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors = array_map( 'sanitize_key', explode( ',', wp_unslash( $_GET['lis_re_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stash  = get_transient( 'lis_re_form_' . get_current_user_id() );
		if ( is_array( $stash ) ) {
			$prefill = $stash;
		}
		delete_transient( 'lis_re_form_' . get_current_user_id() );
	}

	$val = function ( $name, $meta_key = '' ) use ( $prefill, $listing ) {
		if ( isset( $prefill[ $name ] ) && is_scalar( $prefill[ $name ] ) ) {
			return (string) $prefill[ $name ];
		}
		return ( $listing && $meta_key ) ? (string) get_post_meta( $listing->ID, $meta_key, true ) : '';
	};

	if ( $editing ) {
		$type = lis_directory_get_listing_type( $listing->ID );
	} else {
		$type = lis_directory_real_estate_type_from( isset( $prefill['lis_re_type'] ) ? $prefill['lis_re_type'] : '' );
		if ( ! $type ) {
			$type = lis_directory_real_estate_type_from( isset( $_GET['type'] ) ? wp_unslash( $_GET['type'] ) : $atts['type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	$title       = isset( $prefill['lis_re_title'] ) ? $prefill['lis_re_title'] : ( $listing ? $listing->post_title : '' );
	$description = isset( $prefill['lis_re_description'] ) ? $prefill['lis_re_description'] : ( $listing ? trim( wp_strip_all_tags( preg_replace( array( '#</p>\s*#i', '#<br\s*/?>#i' ), array( "\n\n", "\n" ), $listing->post_content ) ) ) : '' );
	$email       = $val( 'lis_re_email', '_lis_listing_email' );
	if ( '' === $email && ! $editing ) {
		$email = wp_get_current_user()->user_email;
	}
	$fields = lis_directory_get_real_estate_fields();

	// Panel helpers. `data-show-if` panels are skipped by the wizard (and their
	// inputs disabled on submit) unless the chosen type matches.
	$panel_open = function ( $section, $question, $hint = '', $only = '' ) use ( $editing, $type ) {
		if ( $editing && $only && 'real-estate-' . $only !== $type ) {
			return false;
		}
		printf(
			'<div class="lis-listing-panel" data-review-labels="1" data-panel-section="%s" data-panel-question="%s"%s%s>',
			esc_attr( $section ),
			esc_attr( $question ),
			$hint ? ' data-panel-hint="' . esc_attr( $hint ) . '"' : '',
			( $only && ! $editing ) ? ' data-show-if="lis_re_type=real-estate-' . esc_attr( $only ) . '"' : ''
		);
		return true; // In edit (flat) mode the CSS prints data-panel-question as the heading.
	};
	$field_row = function ( $key ) use ( $fields, $val ) {
		$field = $fields[ $key ];
		echo '<p class="lis-re-field"><label for="lis_re_' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . ( empty( $field['required'] ) ? ' <span class="lis-listing-optional">(optional)</span>' : '' ) . '</label>';
		lis_directory_render_real_estate_input( $key, $field, $val( 'lis_re_' . $key, '_lis_listing_' . $key ) );
		if ( ! empty( $field['hint'] ) ) {
			echo '<small>' . esc_html( $field['hint'] ) . '</small>';
		}
		echo '</p>';
	};

	ob_start();
	if ( $editing && isset( $_GET['lis_re_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="lis-listing-submit-success">Listing updated.</div>';
	}
	if ( $errors ) {
		echo '<div class="lis-listing-submit-errors"><p>Please fix the following:</p><ul>';
		foreach ( $errors as $key ) {
			echo '<li>' . esc_html( lis_directory_real_estate_error_message( $key ) ) . '</li>';
		}
		echo '</ul></div>';
	}
	?>
	<form class="lis-listing-submit-form lis-re-submit-form<?php echo $editing ? ' lis-listing-edit-flat' : ''; ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lis_directory_submit_real_estate" />
		<input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>" />
		<input type="hidden" name="lis_re_redirect_to" value="<?php echo esc_url( $current_url ); ?>" />
		<?php wp_nonce_field( 'lis_re_submit_' . $listing_id, 'lis_re_submit_nonce' ); ?>
		<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
			<label for="lis_re_hp">Leave this field blank</label>
			<input type="text" id="lis_re_hp" name="lis_re_hp" tabindex="-1" autocomplete="off" />
		</div>

		<?php if ( $editing ) : ?>
			<input type="hidden" name="lis_re_type" value="<?php echo esc_attr( $type ); ?>" />
		<?php else : ?>
			<div class="lis-listing-panel" data-review-labels="1" data-panel-section="Get Started" data-panel-question="What are you listing?">
				<div class="lis-re-type-choices" role="radiogroup" aria-label="Listing type">
					<label class="lis-listing-plan-card"><input type="radio" name="lis_re_type" value="real-estate-rent" <?php checked( $type, 'real-estate-rent' ); ?> required /> <span class="lis-listing-plan-name">For rent</span><span class="lis-listing-plan-blurb">A house, apartment or room to rent.</span></label>
					<label class="lis-listing-plan-card"><input type="radio" name="lis_re_type" value="real-estate-sale" <?php checked( $type, 'real-estate-sale' ); ?> required /> <span class="lis-listing-plan-name">For sale</span><span class="lis-listing-plan-blurb">A home, land or other property you're selling.</span></label>
				</div>
			</div>
		<?php endif; ?>

		<?php $panel_open( 'Basics', 'Give it a title', 'e.g. "3-bedroom home near City Beach"' ); ?>
			<p><label for="lis_re_title" class="screen-reader-text">Title</label>
			<input type="text" id="lis_re_title" name="lis_re_title" maxlength="120" value="<?php echo esc_attr( $title ); ?>" required /></p>
		</div>

		<?php $panel_open( 'Location', 'Where is it?', 'Leave the street address blank to show only the area.' ); ?>
			<?php $field_row( 'area' ); ?>
			<p class="lis-re-field"><label for="lis_re_address">Street address <span class="lis-listing-optional">(optional)</span></label>
			<input type="text" id="lis_re_address" name="lis_re_address" value="<?php echo esc_attr( $val( 'lis_re_address', '_lis_listing_address' ) ); ?>" /></p>
		</div>

		<?php if ( $panel_open( 'Price', 'What\'s the rent?', '', 'rent' ) ) : ?>
			<?php $field_row( 'rent' ); ?>
			<?php $field_row( 'deposit' ); ?>
		</div>
		<?php endif; ?>

		<?php if ( $panel_open( 'Price', 'What\'s the asking price?', '', 'sale' ) ) : ?>
			<?php $field_row( 'sale_price' ); ?>
			<?php if ( $editing ) : ?>
				<?php $field_row( 'sale_status' ); ?>
			<?php else : ?>
				<p class="lis-re-field"><label for="lis_re_sale_status">Status</label>
				<select id="lis_re_sale_status" name="lis_re_sale_status" required>
					<?php foreach ( array( 'active', 'pending' ) as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $val( 'lis_re_sale_status' ), $s ); ?>><?php echo esc_html( lis_directory_get_real_estate_sale_statuses()[ $s ] ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php $panel_open( 'The place', 'How big is it?' ); ?>
			<div class="lis-re-field-row">
				<?php $field_row( 'bedrooms' ); ?>
				<?php $field_row( 'bathrooms' ); ?>
				<?php $field_row( 'sqft' ); ?>
			</div>
		</div>

		<?php if ( $panel_open( 'The place', 'Lot and age', 'Both optional', 'sale' ) ) : ?>
			<div class="lis-re-field-row">
				<?php $field_row( 'lot_size' ); ?>
				<?php $field_row( 'year_built' ); ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $panel_open( 'Rental terms', 'The rental terms', 'All optional', 'rent' ) ) : ?>
			<div class="lis-re-field-row">
				<?php $field_row( 'available_date' ); ?>
				<?php $field_row( 'lease_term' ); ?>
				<?php $field_row( 'pets' ); ?>
			</div>
			<?php $field_row( 'utilities' ); ?>
		</div>
		<?php endif; ?>

		<?php $panel_open( 'Description', 'Describe the property' ); ?>
			<p><label for="lis_re_description" class="screen-reader-text">Description</label>
			<textarea id="lis_re_description" name="lis_re_description" rows="7"><?php echo esc_textarea( $description ); ?></textarea></p>
		</div>

		<?php $panel_open( 'Photos', $editing ? 'Photos' : 'Add photos', 'At least one photo — up to ' . LIS_DIRECTORY_SUBMIT_MAX_PHOTOS . ' at a time. The first one is the main photo.' ); ?>
			<?php
			$gallery = $listing ? array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $listing->ID, '_lis_listing_gallery_ids', true ) ) ) ) : array();
			if ( $gallery ) :
				?>
				<div class="lis-listing-edit-gallery">
					<?php foreach ( $gallery as $attachment_id ) : ?>
						<label class="lis-listing-edit-gallery-item"><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) ); ?>
						<span><input type="checkbox" name="lis_re_remove_photos[]" value="<?php echo (int) $attachment_id; ?>" /> Remove</span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p>
				<label for="lis_re_photos" class="lis-listing-photos-dropzone">
					<span class="lis-listing-photos-dropzone-label">Click to choose photos, or drag them here</span>
					<span class="lis-listing-photos-dropzone-hint">PNG or JPG, up to 2MB each.</span>
				</label>
				<input type="file" id="lis_re_photos" name="lis_re_photos[]" accept="image/png,image/jpeg" multiple <?php echo $gallery ? '' : 'required'; ?> />
			</p>
			<div class="lis-listing-photos-preview" data-photos-preview></div>
		</div>

		<?php $panel_open( 'Contact', 'How should people reach you?', 'Your email and phone are shown on the listing.' ); ?>
			<p class="lis-re-field"><label for="lis_re_email">Contact email</label>
			<input type="email" id="lis_re_email" name="lis_re_email" value="<?php echo esc_attr( $email ); ?>" required /></p>
			<p class="lis-re-field"><label for="lis_re_phone">Phone <span class="lis-listing-optional">(optional)</span></label>
			<input type="tel" id="lis_re_phone" name="lis_re_phone" value="<?php echo esc_attr( $val( 'lis_re_phone', '_lis_listing_phone' ) ); ?>" /></p>
		</div>

		<p class="lis-listing-real-submit"><button type="submit" class="lis-listing-submit-button"><?php echo $editing ? 'Save changes' : 'Submit for review'; ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}

function lis_directory_handle_real_estate_submission() {
	$fallback = home_url( '/' );
	$back     = isset( $_POST['lis_re_redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['lis_re_redirect_to'] ) ), $fallback ) : $fallback;

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to list a property.' );
	}
	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
	if ( ! isset( $_POST['lis_re_submit_nonce'] ) || ! wp_verify_nonce( $_POST['lis_re_submit_nonce'], 'lis_re_submit_' . $listing_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}
	if ( $listing_id && ! lis_directory_user_can_edit_real_estate( $listing_id ) ) {
		wp_die( 'You can only edit your own listings.' );
	}

	// Honeypot: same fake-success as the business form, nothing created.
	if ( ! empty( $_POST['lis_re_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'lis_re_submitted', '1', $back ) );
		exit;
	}

	$src    = wp_unslash( $_POST );
	$errors = array();
	$type   = $listing_id ? lis_directory_get_listing_type( $listing_id ) : lis_directory_real_estate_type_from( isset( $src['lis_re_type'] ) ? $src['lis_re_type'] : '' );
	if ( ! $type ) {
		$errors[] = 'type';
	}
	$short = $type ? lis_directory_get_real_estate_types()[ $type ] : '';
	$title = isset( $src['lis_re_title'] ) ? sanitize_text_field( $src['lis_re_title'] ) : '';
	if ( '' === $title ) {
		$errors[] = 'title';
	}
	$email = isset( $src['lis_re_email'] ) ? sanitize_email( $src['lis_re_email'] ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		$errors[] = 'email';
	}
	if ( $short ) {
		$errors = array_merge( $errors, lis_directory_validate_real_estate_fields( $short, $src ) );
	}

	$existing_gallery = $listing_id ? array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $listing_id, '_lis_listing_gallery_ids', true ) ) ) ) : array();
	$remove           = isset( $src['lis_re_remove_photos'] ) ? array_map( 'absint', (array) $src['lis_re_remove_photos'] ) : array();
	$kept_gallery     = array_values( array_diff( $existing_gallery, $remove ) );

	$photo_ids = array();
	if ( ! $errors ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$photo_ids = lis_directory_handle_listing_photos_upload( 'lis_re_photos', empty( $kept_gallery ), $errors );
	}

	if ( $errors ) {
		unset( $src['lis_re_submit_nonce'], $src['_wp_http_referer'] );
		set_transient( 'lis_re_form_' . get_current_user_id(), $src, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'lis_re_error', implode( ',', array_unique( $errors ) ), $back ) );
		exit;
	}

	$content = wp_kses_post( wpautop( isset( $src['lis_re_description'] ) ? sanitize_textarea_field( $src['lis_re_description'] ) : '' ) );

	if ( $listing_id ) {
		// Editing never sends a published listing back to review (same rule as
		// business listings — see includes/listings-edit.php).
		wp_update_post( array( 'ID' => $listing_id, 'post_title' => $title, 'post_content' => $content ) );
	} else {
		$listing_id = wp_insert_post( array(
			'post_type'      => 'lis_listing',
			'post_status'    => 'pending',
			'post_title'     => $title,
			'post_content'   => $content,
			'post_author'    => get_current_user_id(),
			'comment_status' => 'closed', // No reviews on real estate.
		), true );
		if ( is_wp_error( $listing_id ) || ! $listing_id ) {
			set_transient( 'lis_re_form_' . get_current_user_id(), $src, 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'lis_re_error', 'save_failed', $back ) );
			exit;
		}
		update_post_meta( $listing_id, '_lis_listing_type', $type );
	}

	update_post_meta( $listing_id, '_lis_listing_email', $email );
	update_post_meta( $listing_id, '_lis_listing_phone', isset( $src['lis_re_phone'] ) ? sanitize_text_field( $src['lis_re_phone'] ) : '' );
	update_post_meta( $listing_id, '_lis_listing_address', isset( $src['lis_re_address'] ) ? sanitize_text_field( $src['lis_re_address'] ) : '' );
	lis_directory_save_real_estate_fields( $listing_id, $type, $src );

	$gallery = array_values( array_unique( array_merge( $kept_gallery, $photo_ids ) ) );
	update_post_meta( $listing_id, '_lis_listing_gallery_ids', implode( ',', $gallery ) );
	if ( $gallery ) {
		set_post_thumbnail( $listing_id, $gallery[0] );
	} else {
		delete_post_thumbnail( $listing_id );
	}
	foreach ( $photo_ids as $attachment_id ) {
		wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $listing_id ) );
	}

	if ( ! empty( $src['listing_id'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'listing_id' => $listing_id, 'lis_re_updated' => '1' ), $back ) );
		exit;
	}

	$checkout = lis_directory_real_estate_checkout_url( $listing_id, $type );
	if ( '' !== $checkout ) {
		wp_update_post( array( 'ID' => $listing_id, 'post_status' => 'draft' ) );
		update_post_meta( $listing_id, '_lis_listing_awaiting_payment', 1 );
		wp_redirect( $checkout ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- URL comes from site code via the filter.
		exit;
	}

	$types = lis_directory_get_listing_types();
	wp_mail(
		get_option( 'admin_email' ),
		'New real estate listing awaiting review: ' . $title,
		"A new real estate listing was submitted and is waiting for review:\n\n"
		. $title . ' (' . $types[ $type ] . ")\n"
		. 'Submitted by: ' . wp_get_current_user()->user_login . ' <' . $email . ">\n"
		. admin_url( 'post.php?post=' . $listing_id . '&action=edit' ) . "\n"
	);

	wp_safe_redirect( add_query_arg( 'lis_re_submitted', $short, $back ) );
	exit;
}
