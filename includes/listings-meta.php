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

function lis_directory_register_listing_meta() {
	$string_fields = array(
		'_lis_listing_address'      => 'string',
		'_lis_listing_phone'        => 'string',
		'_lis_listing_website'      => 'string',
		'_lis_listing_email'        => 'string',
		'_lis_listing_price'        => 'string',
		'_lis_listing_video_url'    => 'string',
		'_lis_listing_gallery_ids'  => 'string', // Comma-separated attachment IDs.
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

	$address    = get_post_meta( $post->ID, '_lis_listing_address', true );
	$phone      = get_post_meta( $post->ID, '_lis_listing_phone', true );
	$website    = get_post_meta( $post->ID, '_lis_listing_website', true );
	$email      = get_post_meta( $post->ID, '_lis_listing_email', true );
	$price      = get_post_meta( $post->ID, '_lis_listing_price', true );
	$video_url  = get_post_meta( $post->ID, '_lis_listing_video_url', true );
	$gallery_raw = get_post_meta( $post->ID, '_lis_listing_gallery_ids', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
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
	</table>
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
