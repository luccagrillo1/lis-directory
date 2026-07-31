<?php
/**
 * Business hours for `lis_listing`: one open/close pair per day (a single
 * range per day — not the multiple-range-per-day or "open 24h" richness the
 * reference design showed; that's a real second pass, not implemented here)
 * plus the "open now" status derived from it.
 *
 * Uses current_time(), which resolves against Settings > General's site
 * timezone, rather than hardcoding a timezone — LIS Events hardcoded Pacific
 * for a specific reason noted in its own code; there's no equivalent reason
 * here, so this stays portable to whatever the site's configured timezone is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_WEEKDAYS', array(
	'monday'    => 'Monday',
	'tuesday'   => 'Tuesday',
	'wednesday' => 'Wednesday',
	'thursday'  => 'Thursday',
	'friday'    => 'Friday',
	'saturday'  => 'Saturday',
	'sunday'    => 'Sunday',
) );

add_action( 'init', 'lis_directory_register_listing_hours_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_listing_hours_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_listing_hours_meta_box' );

function lis_directory_register_listing_hours_meta() {
	foreach ( array_keys( LIS_DIRECTORY_WEEKDAYS ) as $day ) {
		register_post_meta( 'lis_listing', "_lis_listing_hours_{$day}_open", array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
		register_post_meta( 'lis_listing', "_lis_listing_hours_{$day}_close", array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}

function lis_directory_add_listing_hours_meta_box() {
	add_meta_box(
		'lis_listing_hours',
		'Business Hours',
		'lis_directory_render_listing_hours_meta_box',
		'lis_listing',
		'normal',
		'default'
	);
}

function lis_directory_render_listing_hours_meta_box( $post ) {
	wp_nonce_field( 'lis_listing_save_hours', 'lis_listing_hours_nonce' );
	?>
	<table class="form-table lis-listing-hours-table">
		<thead>
			<tr><th>Day</th><th>Open</th><th>Close</th></tr>
		</thead>
		<tbody>
			<?php foreach ( LIS_DIRECTORY_WEEKDAYS as $day => $label ) : ?>
				<?php
				$open  = get_post_meta( $post->ID, "_lis_listing_hours_{$day}_open", true );
				$close = get_post_meta( $post->ID, "_lis_listing_hours_{$day}_close", true );
				?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td><input type="time" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_open" value="<?php echo esc_attr( $open ); ?>" /></td>
					<td><input type="time" name="lis_listing_hours_<?php echo esc_attr( $day ); ?>_close" value="<?php echo esc_attr( $close ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">Leave both fields blank for a day the business is closed.</p>
	<?php
}

function lis_directory_save_listing_hours_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_listing_hours_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_hours_nonce'], 'lis_listing_save_hours' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array_keys( LIS_DIRECTORY_WEEKDAYS ) as $day ) {
		foreach ( array( 'open', 'close' ) as $edge ) {
			$field = "lis_listing_hours_{$day}_{$edge}";
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				// <input type="time"> gives HH:MM or empty; anything else is ignored rather than stored malformed.
				if ( '' === $value || preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
					update_post_meta( $post_id, "_{$field}", $value );
				}
			}
		}
	}
}

/**
 * Returns the day's hours as ['open' => 'HH:MM', 'close' => 'HH:MM'] or null
 * if closed that day (either field blank).
 */
function lis_directory_get_listing_hours_for_day( $post_id, $day ) {
	$open  = get_post_meta( $post_id, "_lis_listing_hours_{$day}_open", true );
	$close = get_post_meta( $post_id, "_lis_listing_hours_{$day}_close", true );
	if ( '' === $open || '' === $close ) {
		return null;
	}
	return array( 'open' => $open, 'close' => $close );
}

/**
 * Full week as [day_key => ['open'=>..,'close'=>..]|null], in display order.
 */
function lis_directory_get_listing_week_hours( $post_id ) {
	$week = array();
	foreach ( array_keys( LIS_DIRECTORY_WEEKDAYS ) as $day ) {
		$week[ $day ] = lis_directory_get_listing_hours_for_day( $post_id, $day );
	}
	return $week;
}

/**
 * Whether the listing is open right now, based on the site's configured
 * timezone (current_time()). A listing with no hours set at all returns
 * null (unknown), not false — the front-end should omit the badge rather
 * than confidently claim "Closed" for a listing that just never set hours.
 */
function lis_directory_is_listing_open_now( $post_id ) {
	$week_hours = lis_directory_get_listing_week_hours( $post_id );
	if ( ! array_filter( $week_hours ) ) {
		return null; // No hours configured for any day.
	}

	$day_keys = array_keys( LIS_DIRECTORY_WEEKDAYS );
	$today    = $day_keys[ (int) current_time( 'N' ) - 1 ];
	$hours    = $week_hours[ $today ];
	if ( ! $hours ) {
		return false;
	}

	$now = current_time( 'H:i' );
	return ( $now >= $hours['open'] && $now < $hours['close'] );
}

/**
 * "14:30" -> "2:30 pm", for display only (stored value stays 24h HH:MM,
 * which is what <input type="time"> and the string comparisons above need).
 */
function lis_directory_format_time( $time_24h ) {
	$timestamp = strtotime( $time_24h );
	return $timestamp ? date( 'g:i a', $timestamp ) : $time_24h; // phpcs:ignore -- display formatting of a stored HH:MM string, not a real timestamp; no timezone conversion needed.
}
