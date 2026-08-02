<?php
/**
 * WooCommerce Subscriptions integration (build brief step 6).
 *
 * Open Decision #1 resolved 2026-07-27: recurring subscription. WooCommerce
 * Subscriptions (v9.0.1) confirmed installed and active on
 * livinginsandpoint.com by inspecting wp-admin > Plugins directly.
 *
 * Recommended setup (done by hand in wp-admin, NOT by this plugin): one
 * variable WooCommerce Subscription product ("Vendor Showcase"), with one
 * variation per lis_vendor_category term. Each variation is tagged with the
 * category it represents via the "LIS Vendor Category" field this file adds
 * to the variation admin UI — that tag is how the rest of this file finds
 * "the Vendor Showcase variation for category X" without a dedicated
 * settings screen naming the product itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_settings_page' );
add_action( 'admin_init', 'lis_directory_register_settings' );
add_action( 'plugins_loaded', 'lis_directory_init_woocommerce_integration' );

function lis_directory_add_settings_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'LIS Directory Settings',
		'Settings',
		'manage_options',
		'lis_pv_settings',
		'lis_directory_render_settings_page'
	);
}

function lis_directory_register_settings() {
	register_setting( 'lis_pv_settings_group', 'lis_pv_submission_page_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
	) );
}

function lis_directory_render_settings_page() {
	?>
	<div class="wrap">
		<h1>LIS Directory Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'lis_pv_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="lis_pv_submission_page_id">Submission Form Page</label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'lis_pv_submission_page_id',
							'selected'         => (int) get_option( 'lis_pv_submission_page_id' ),
							'show_option_none' => '— Select the page with [lis_preferred_vendor_submit] —',
						) );
						?>
						<p class="description">Used to build the "submit your details" link shown on the WooCommerce thank-you page after a Vendor Showcase purchase.</p>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_listing_edit_page_id">Listing Edit Page</label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'lis_directory_listing_edit_page_id',
							'selected'         => (int) get_option( 'lis_directory_listing_edit_page_id' ),
							'show_option_none' => '— Select the page with [lis_listing_edit] —',
						) );
						?>
						<p class="description">Used to build the "Edit" link on the front-end Dashboard for users who don't have wp-admin edit access to their own listing (the common case for front-end signups).</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function lis_directory_init_woocommerce_integration() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action( 'woocommerce_variation_options_pricing', 'lis_directory_render_variation_category_field', 10, 3 );
	add_action( 'woocommerce_save_product_variation', 'lis_directory_save_variation_category_field', 10, 2 );
	add_action( 'lis_directory_vendor_status_changed', 'lis_directory_sync_stock_on_status_change', 10, 2 );
	add_action( 'woocommerce_thankyou', 'lis_directory_maybe_show_submission_link' );

	if ( class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'woocommerce_subscription_status_cancelled', 'lis_directory_handle_subscription_ended' );
		add_action( 'woocommerce_subscription_status_expired', 'lis_directory_handle_subscription_ended' );
	}
}

/**
 * Variation admin field: tags a variation with the lis_vendor_category it sells.
 */
function lis_directory_render_variation_category_field( $loop, $variation_data, $variation ) {
	$terms   = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );
	$current = get_post_meta( $variation->ID, '_lis_pv_category_term_id', true );
	?>
	<div class="options_group form-row form-row-full">
		<p class="form-field">
			<label for="lis_pv_category_term_id_<?php echo esc_attr( $loop ); ?>">LIS Vendor Category</label>
			<select id="lis_pv_category_term_id_<?php echo esc_attr( $loop ); ?>" name="lis_pv_category_term_id[<?php echo esc_attr( $loop ); ?>]">
				<option value="">— Not a Vendor Showcase variation —</option>
				<?php foreach ( (array) $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( (int) $current, $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description">If set, this variation's stock is kept in sync with whether the category currently has an active Vendor Showcase entry.</span>
		</p>
	</div>
	<?php
}

function lis_directory_save_variation_category_field( $variation_id, $loop ) {
	if ( ! isset( $_POST['lis_pv_category_term_id'][ $loop ] ) ) {
		return;
	}
	$term_id = (int) $_POST['lis_pv_category_term_id'][ $loop ];
	if ( $term_id ) {
		update_post_meta( $variation_id, '_lis_pv_category_term_id', $term_id );
	} else {
		delete_post_meta( $variation_id, '_lis_pv_category_term_id' );
	}
}

/**
 * Every product variation tagged for a given category.
 *
 * @return int[] Variation post IDs.
 */
function lis_directory_get_variations_for_category( $term_id ) {
	return get_posts( array(
		'post_type'      => 'product_variation',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_category_term_id',
				'value' => (int) $term_id,
			),
		),
	) );
}

/**
 * Keeps a category's tagged variation(s) stock in sync with whether it
 * currently has an active vendor — "0 in stock" doubles as the availability
 * check per the build brief, so WooCommerce's own "sold out" UI does the work
 * instead of building custom availability logic a second time.
 */
function lis_directory_sync_stock_for_category( $term_id ) {
	$variation_ids = lis_directory_get_variations_for_category( $term_id );
	if ( empty( $variation_ids ) ) {
		return;
	}

	$taken = (bool) lis_directory_get_active_vendor_for_category( $term_id );

	foreach ( $variation_ids as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			continue;
		}
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( $taken ? 0 : 1 );
		$variation->set_stock_status( $taken ? 'outofstock' : 'instock' );
		$variation->save();
	}
}

function lis_directory_sync_stock_on_status_change( $post_id, $new_status ) {
	$term_ids = wp_get_post_terms( $post_id, 'lis_vendor_category', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) ) {
		return;
	}
	foreach ( $term_ids as $term_id ) {
		lis_directory_sync_stock_for_category( $term_id );
	}
}

/**
 * Thank-you page: if this order is for a tagged Vendor Showcase variation,
 * link straight to the submission form (includes/submission.php), locked to
 * that category and carrying the subscription ID — so the vendor doesn't have
 * to hunt for the form, and the resulting post is tied back to what they paid
 * for. Only the thank-you page is wired up in this version, not a duplicate
 * link in the order confirmation email or My Account — flag if that turns out
 * to be needed too once this is live.
 */
function lis_directory_maybe_show_submission_link( $order_id ) {
	if ( ! $order_id ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$page_id = (int) get_option( 'lis_pv_submission_page_id' );
	if ( ! $page_id ) {
		return; // Not configured yet — see LIS Directory > Settings.
	}

	foreach ( $order->get_items() as $item ) {
		$variation_id = $item->get_variation_id();
		if ( ! $variation_id ) {
			continue;
		}
		$term_id = (int) get_post_meta( $variation_id, '_lis_pv_category_term_id', true );
		if ( ! $term_id ) {
			continue;
		}

		// Store the subscription ID, not the one-time order ID, so the later
		// cancelled/expired hook (which only knows the subscription) can find
		// the vendor post it needs to expire.
		$linked_id = $order_id;
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				$linked_id = (int) reset( $subscriptions )->get_id();
			}
		}

		$link = add_query_arg( array(
			'lis_pv_category' => $term_id,
			'lis_pv_order'    => $linked_id,
		), get_permalink( $page_id ) );
		?>
		<div class="lis-pv-thankyou-link" style="margin:16px 0;padding:16px;border:1px solid #7ad03a;background:#f7fff0;">
			<p>Your Vendor Showcase spot is reserved! <a href="<?php echo esc_url( $link ); ?>">Click here to submit your business details</a> so we can get your listing live.</p>
		</div>
		<?php
		return; // Only one Vendor Showcase line item is expected per order.
	}
}

/**
 * Cancellation/expiration: free the category back up by expiring the linked
 * vendor. Runs on both `cancelled` and `expired` subscription statuses —
 * either one means the paid term ended, and the category lock (v0.2.0) only
 * cares about `active`, so anything else already reopens it.
 */
function lis_directory_handle_subscription_ended( $subscription ) {
	$subscription_id = $subscription->get_id();

	$vendors = get_posts( array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_wc_order_id',
				'value' => $subscription_id,
			),
		),
	) );

	if ( empty( $vendors ) ) {
		return;
	}

	lis_directory_set_vendor_status( $vendors[0], 'expired' );
}
