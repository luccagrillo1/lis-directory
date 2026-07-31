<?php
/**
 * Post meta registration + the "Vendor Details" admin meta box.
 *
 * All fields are admin-managed only in this scaffold (no front-end submission yet,
 * see build brief step 5), so meta is kept out of the REST API for now.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_STATUSES', array(
	'pending' => 'Pending',
	'active'  => 'Active',
	'expired' => 'Expired',
	'rejected'=> 'Rejected',
) );

add_action( 'init', 'lis_directory_register_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_meta_box' );
add_action( 'save_post_lis_preferred_vendor', 'lis_directory_save_meta_box' );
add_action( 'admin_enqueue_scripts', 'lis_directory_admin_enqueue' );

function lis_directory_register_meta() {
	$fields = array(
		'_lis_pv_tagline'        => 'string',
		'_lis_pv_link_url'       => 'string',
		'_lis_pv_logo_color_id'  => 'integer',
		'_lis_pv_status'         => 'string',
		'_lis_pv_term_start'     => 'string',
		'_lis_pv_term_end'       => 'string',
		'_lis_pv_wc_order_id'    => 'integer',
		'_lis_pv_contact_email'  => 'string',
	);

	foreach ( $fields as $key => $type ) {
		register_post_meta( 'lis_preferred_vendor', $key, array(
			'type'          => $type,
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}

function lis_directory_add_meta_box() {
	add_meta_box(
		'lis_pv_details',
		'Vendor Details',
		'lis_directory_render_meta_box',
		'lis_preferred_vendor',
		'normal',
		'high'
	);
}

function lis_directory_render_meta_box( $post ) {
	wp_nonce_field( 'lis_pv_save_meta', 'lis_pv_meta_nonce' );

	$tagline    = get_post_meta( $post->ID, '_lis_pv_tagline', true );
	$link_url   = get_post_meta( $post->ID, '_lis_pv_link_url', true );
	$logo_color = (int) get_post_meta( $post->ID, '_lis_pv_logo_color_id', true );
	$status     = get_post_meta( $post->ID, '_lis_pv_status', true );
	$term_start = get_post_meta( $post->ID, '_lis_pv_term_start', true );
	$term_end   = get_post_meta( $post->ID, '_lis_pv_term_end', true );
	$wc_order   = get_post_meta( $post->ID, '_lis_pv_wc_order_id', true );
	$contact    = get_post_meta( $post->ID, '_lis_pv_contact_email', true );

	if ( ! $status ) {
		$status = 'pending';
	}
	?>
	<table class="form-table">
		<tr>
			<th><label for="lis_pv_tagline">Tagline</label></th>
			<td>
				<input type="text" id="lis_pv_tagline" name="lis_pv_tagline" class="large-text"
					value="<?php echo esc_attr( $tagline ); ?>"
					placeholder="e.g. For all your insurance needs" />
			</td>
		</tr>
		<tr>
			<th><label for="lis_pv_link_url">Directorist Listing URL</label></th>
			<td>
				<input type="url" id="lis_pv_link_url" name="lis_pv_link_url" class="large-text"
					value="<?php echo esc_attr( $link_url ); ?>"
					placeholder="https://livinginsandpoint.com/directory/..." />
				<p class="description">Must be their existing, published listing on the Local Directory — required before this vendor can be set Active.</p>
			</td>
		</tr>
		<tr>
			<th>Logo</th>
			<td><?php lis_directory_render_logo_field( 'lis_pv_logo_color_id', $logo_color ); ?></td>
		</tr>
		<tr>
			<th><label for="lis_pv_status">Status</label></th>
			<td>
				<select id="lis_pv_status" name="lis_pv_status">
					<?php foreach ( LIS_DIRECTORY_STATUSES as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="lis_pv_term_start">Term Start</label></th>
			<td><input type="date" id="lis_pv_term_start" name="lis_pv_term_start" value="<?php echo esc_attr( $term_start ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_pv_term_end">Term End</label></th>
			<td><input type="date" id="lis_pv_term_end" name="lis_pv_term_end" value="<?php echo esc_attr( $term_end ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_pv_wc_order_id">WooCommerce Order/Subscription ID</label></th>
			<td><input type="number" id="lis_pv_wc_order_id" name="lis_pv_wc_order_id" value="<?php echo esc_attr( $wc_order ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="lis_pv_contact_email">Contact Email</label></th>
			<td><input type="email" id="lis_pv_contact_email" name="lis_pv_contact_email" class="regular-text" value="<?php echo esc_attr( $contact ); ?>" /></td>
		</tr>
	</table>
	<?php
}

function lis_directory_render_logo_field( $field_id, $attachment_id ) {
	$image_url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
	?>
	<div class="lis-pv-logo-field">
		<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>" />
		<img id="<?php echo esc_attr( $field_id ); ?>_preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width:150px;max-height:100px;display:<?php echo $image_url ? 'block' : 'none'; ?>;margin-bottom:8px;" />
		<br />
		<button type="button" class="button lis-pv-upload-logo" data-target="<?php echo esc_attr( $field_id ); ?>">Select Image</button>
		<button type="button" class="button lis-pv-remove-logo" data-target="<?php echo esc_attr( $field_id ); ?>" style="<?php echo $image_url ? '' : 'display:none;'; ?>">Remove</button>
		<p class="description">PNG only, transparent background — recolored to black/white automatically wherever that's needed.</p>
	</div>
	<?php
}

function lis_directory_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_pv_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lis_pv_meta_nonce'], 'lis_pv_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['lis_pv_tagline'] ) ) {
		update_post_meta( $post_id, '_lis_pv_tagline', sanitize_text_field( wp_unslash( $_POST['lis_pv_tagline'] ) ) );
	}

	if ( isset( $_POST['lis_pv_link_url'] ) ) {
		update_post_meta( $post_id, '_lis_pv_link_url', esc_url_raw( wp_unslash( $_POST['lis_pv_link_url'] ) ) );
	}

	if ( isset( $_POST['lis_pv_logo_color_id'] ) ) {
		update_post_meta( $post_id, '_lis_pv_logo_color_id', absint( $_POST['lis_pv_logo_color_id'] ) );
	}

	if ( isset( $_POST['lis_pv_status'] ) && array_key_exists( $_POST['lis_pv_status'], LIS_DIRECTORY_STATUSES ) ) {
		$new_status = sanitize_key( $_POST['lis_pv_status'] );
		$blocked    = ( 'active' === $new_status ) && ! lis_directory_can_activate_vendor( $post_id );

		if ( $blocked ) {
			// Block the change — leave the existing status untouched.
			// lis_directory_can_activate_vendor() already flagged which of the two
			// activation requirements (category lock or Directorist listing) failed.
			// On a brand-new vendor there's no "existing status" to leave untouched
			// — the meta key doesn't exist yet — so a blocked first save must still
			// default it to 'pending', otherwise the post ends up with no status at
			// all: invisible to the Pending filter and missing Approve/Reject row
			// actions (both match on '_lis_pv_status' === 'pending', not "unset").
			if ( '' === get_post_meta( $post_id, '_lis_pv_status', true ) ) {
				lis_directory_set_vendor_status( $post_id, 'pending' );
			}
		} else {
			lis_directory_set_vendor_status( $post_id, $new_status );
		}
	}

	foreach ( array( 'lis_pv_term_start' => '_lis_pv_term_start', 'lis_pv_term_end' => '_lis_pv_term_end' ) as $field => $meta_key ) {
		if ( isset( $_POST[ $field ] ) ) {
			$date = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			// Expect Y-m-d from the <input type="date">; store empty string if cleared.
			if ( '' === $date || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				update_post_meta( $post_id, $meta_key, $date );
			}
		}
	}

	if ( isset( $_POST['lis_pv_wc_order_id'] ) ) {
		update_post_meta( $post_id, '_lis_pv_wc_order_id', absint( $_POST['lis_pv_wc_order_id'] ) );
	}

	if ( isset( $_POST['lis_pv_contact_email'] ) ) {
		update_post_meta( $post_id, '_lis_pv_contact_email', sanitize_email( wp_unslash( $_POST['lis_pv_contact_email'] ) ) );
	}
}

function lis_directory_admin_enqueue( $hook ) {
	global $post_type;
	if ( 'lis_preferred_vendor' !== $post_type ) {
		return;
	}
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'lis-directory-admin',
		LIS_DIRECTORY_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		LIS_DIRECTORY_VERSION,
		true
	);
}
