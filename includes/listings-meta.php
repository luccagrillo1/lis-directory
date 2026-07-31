<?php
/**
 * Basic contact-info meta box for `lis_listing` — framework-phase field set
 * only (address/phone/website/email). Deliberately not attempting Directorist
 * feature parity yet: no business hours, gallery, map, reviews, claim-listing,
 * or booking. Those are real features to design, not oversights to patch —
 * add them once the framework itself is confirmed working.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_listing_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_listing_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_listing_meta_box' );

function lis_directory_register_listing_meta() {
	$fields = array(
		'_lis_listing_address' => 'string',
		'_lis_listing_phone'   => 'string',
		'_lis_listing_website' => 'string',
		'_lis_listing_email'   => 'string',
	);

	foreach ( $fields as $key => $type ) {
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

	$address = get_post_meta( $post->ID, '_lis_listing_address', true );
	$phone   = get_post_meta( $post->ID, '_lis_listing_phone', true );
	$website = get_post_meta( $post->ID, '_lis_listing_website', true );
	$email   = get_post_meta( $post->ID, '_lis_listing_email', true );
	?>
	<table class="form-table">
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
	</table>
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
}
