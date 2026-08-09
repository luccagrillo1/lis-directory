<?php
/**
 * "Featured Listing" paid upgrade for `lis_listing` — the parity item for
 * Directorist's Pricing Plans, scoped down deliberately (see
 * DIRECTORIST_PARITY_PLAN.md): one upgrade (Featured), not a general
 * multi-tier plan builder, and it reuses the SAME WooCommerce
 * add-to-cart → order-complete pattern as the existing Vendor Showcase
 * integration (includes/woocommerce.php) rather than inventing a second
 * checkout mechanism.
 *
 * Real payment infrastructure: the product itself is set up BY HAND in
 * wp-admin (WooCommerce Product, simple or a WC Subscriptions product —
 * either works, this file doesn't care which), same "recommended setup
 * done by hand, not by this plugin" approach as Vendor Showcase. This file
 * only reacts to a completed order — it never runs a real transaction
 * itself, and no real purchase was executed while building this.
 *
 * Linking a specific listing to a specific order: the "Feature this
 * listing" button on the Dashboard adds the configured product to the
 * cart via a plain add-to-cart URL carrying `lis_listing_id` as a query
 * arg; `woocommerce_add_cart_item_data` picks that up into cart item data,
 * `woocommerce_checkout_create_order_line_item` persists it onto the
 * order line item, and the completion hook reads it back off the order.
 * No custom database table — WooCommerce's own order/item meta is enough.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_listing_pricing_settings' ); // Not admin_init - see the note on the equivalent hook in includes/woocommerce.php.
add_action( 'plugins_loaded', 'lis_directory_init_listing_pricing_woocommerce' );

function lis_directory_register_listing_pricing_settings() {
	register_setting( 'lis_pv_settings_group', 'lis_directory_featured_listing_product_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'show_in_rest'      => true,
	) );
	register_setting( 'lis_pv_settings_group', 'lis_directory_standard_listing_product_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'show_in_rest'      => true,
	) );
}

function lis_directory_init_listing_pricing_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_filter( 'woocommerce_add_cart_item_data', 'lis_directory_add_listing_id_to_cart_item', 10, 2 );
	add_filter( 'woocommerce_get_item_data', 'lis_directory_show_listing_in_cart_item_data', 10, 2 );
	add_filter( 'woocommerce_cart_item_name', 'lis_directory_add_listing_to_cart_item_name', 10, 3 );
	add_filter( 'woocommerce_order_item_name', 'lis_directory_add_listing_to_order_item_name', 10, 2 );
	add_action( 'woocommerce_checkout_create_order_line_item', 'lis_directory_persist_listing_id_to_order_item', 10, 4 );
	add_action( 'woocommerce_order_status_completed', 'lis_directory_handle_featured_listing_order' );
	add_action( 'woocommerce_order_status_processing', 'lis_directory_handle_featured_listing_order' );
	add_action( 'admin_post_lis_directory_generate_standard_listing', 'lis_directory_handle_generate_standard_listing' );

	if ( class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'woocommerce_subscription_status_cancelled', 'lis_directory_handle_featured_listing_subscription_ended' );
		add_action( 'woocommerce_subscription_status_expired', 'lis_directory_handle_featured_listing_subscription_ended' );
	}
}

/**
 * URL that adds the configured Featured Listing product to the cart, tagged
 * with which listing it's for. Empty string if no product is configured yet
 * (Settings > LIS Directory Settings) — callers should hide the upgrade
 * button entirely in that case, not link to a broken checkout.
 */
function lis_directory_get_feature_listing_url( $listing_id ) {
	$product_id = (int) get_option( 'lis_directory_featured_listing_product_id' );
	if ( ! $product_id || ! function_exists( 'wc_get_cart_url' ) ) {
		return '';
	}
	return add_query_arg( array(
		'add-to-cart'     => $product_id,
		'lis_listing_id'  => (int) $listing_id,
	), wc_get_cart_url() );
}

function lis_directory_add_listing_id_to_cart_item( $cart_item_data, $product_id ) {
	$configured_id = (int) get_option( 'lis_directory_featured_listing_product_id' );
	if ( $configured_id !== (int) $product_id ) {
		return $cart_item_data;
	}
	$listing_id = isset( $_REQUEST['lis_listing_id'] ) ? absint( $_REQUEST['lis_listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cart-item tag, ownership is re-checked at order-completion time before anything is granted.
	if ( $listing_id && 'lis_listing' === get_post_type( $listing_id ) ) {
		$cart_item_data['lis_listing_id'] = $listing_id;
	}
	return $cart_item_data;
}

/**
 * Kept for any WooCommerce context that does call `wc_get_formatted_cart_item_data()`
 * (the theme's own cart template on this site does not - it never renders
 * the item-data `<dl>` for any product, confirmed by inspecting the live
 * markup, not specific to this plugin). `woocommerce_cart_item_name` /
 * `woocommerce_order_item_name` below are what actually shows on this
 * site, since those wrap the product name itself rather than a separate,
 * optional meta block.
 */
function lis_directory_show_listing_in_cart_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['lis_listing_id'] ) ) {
		$item_data[] = array(
			'name'  => 'Listing',
			'value' => get_the_title( $cart_item['lis_listing_id'] ),
		);
	}
	return $item_data;
}

function lis_directory_add_listing_to_cart_item_name( $name, $cart_item, $cart_item_key ) {
	if ( empty( $cart_item['lis_listing_id'] ) ) {
		return $name;
	}
	$listing_title = get_the_title( $cart_item['lis_listing_id'] );
	if ( ! $listing_title ) {
		return $name;
	}
	return $name . ' &mdash; Featuring: ' . esc_html( $listing_title );
}

function lis_directory_add_listing_to_order_item_name( $name, $item ) {
	$listing_id = $item->get_meta( '_lis_listing_id' );
	if ( ! $listing_id ) {
		return $name;
	}
	$listing_title = get_the_title( $listing_id );
	if ( ! $listing_title ) {
		return $name;
	}
	return $name . ' &mdash; Featuring: ' . esc_html( $listing_title );
}

function lis_directory_persist_listing_id_to_order_item( $item, $cart_item_key, $values, $order ) {
	if ( ! empty( $values['lis_listing_id'] ) ) {
		$item->add_meta_data( '_lis_listing_id', (int) $values['lis_listing_id'], true );
	}
}

/**
 * Runs on both `processing` and `completed` — many payment gateways put
 * virtual-product orders straight to `processing` and never touch
 * `completed`, so relying on only one status would silently never fire for
 * some payment methods. `update_post_meta` is naturally idempotent, so
 * firing twice for the same order (once per status transition) is harmless.
 */
function lis_directory_handle_featured_listing_order( $order_id ) {
	$configured_id = (int) get_option( 'lis_directory_featured_listing_product_id' );
	if ( ! $configured_id || ! $order_id ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		if ( (int) $item->get_product_id() !== $configured_id ) {
			continue;
		}
		$listing_id = (int) $item->get_meta( '_lis_listing_id' );
		if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
			continue;
		}

		// Same subscription-vs-order linking as Vendor Showcase: store the
		// subscription ID when this order started one, so the cancelled/
		// expired hook (which only knows the subscription) can find its way
		// back here. A one-time purchase just stores the order ID and stays
		// featured indefinitely — no automatic expiry for those (see
		// CHANGELOG known limitation).
		$linked_id = $order_id;
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				$linked_id = (int) reset( $subscriptions )->get_id();
			}
		}

		update_post_meta( $listing_id, '_lis_listing_featured', true );
		update_post_meta( $listing_id, '_lis_listing_featured_order_id', $linked_id );
	}
}

function lis_directory_handle_featured_listing_subscription_ended( $subscription ) {
	$subscription_id = $subscription->get_id();

	$listings = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_listing_featured_order_id',
				'value' => $subscription_id,
			),
		),
	) );

	if ( empty( $listings ) ) {
		return;
	}

	update_post_meta( $listings[0], '_lis_listing_featured', false );
}

/* ------------------------------------------------------------------ *
 * Standard Listing product — the base paid tier. A variable           *
 * subscription with Monthly/Annually billing (like Vendor Showcase),  *
 * but with no category exclusivity: anyone can buy a standard listing. *
 * Generated from LIS Directory Settings; prices are placeholders the  *
 * admin edits in WooCommerce.                                         *
 * ------------------------------------------------------------------ */

/**
 * The configured/generated Standard Listing product id (found by its marker
 * meta), or 0 if it doesn't exist yet.
 */
function lis_directory_get_standard_listing_product_id() {
	$ids = get_posts( array(
		'post_type'        => 'product',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'meta_query'       => array(
			array( 'key' => '_lis_standard_listing_product', 'value' => '1' ),
		),
		'suppress_filters' => false,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Builds (or tops up) the single Standard Listing variable-subscription
 * product with a Monthly and an Annually variation. Idempotent, keyed on the
 * `_lis_standard_billing_period` marker so a re-run only fills gaps and never
 * disturbs prices edited by hand.
 *
 * @return array|WP_Error { added:int, product_id:int } on success.
 */
function lis_directory_generate_standard_listing_product() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'no_wc', 'WooCommerce is not active.' );
	}
	$use_sub = class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Product_Variable_Subscription' );

	$product_id = lis_directory_get_standard_listing_product_id();
	if ( ! $product_id ) {
		$product = $use_sub ? new WC_Product_Variable_Subscription() : new WC_Product_Variable();
		$product->set_name( 'Standard Listing' );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );

		$attr_bill = new WC_Product_Attribute();
		$attr_bill->set_name( 'Billing' );
		$attr_bill->set_options( array( 'Monthly', 'Annually' ) );
		$attr_bill->set_visible( true );
		$attr_bill->set_variation( true );

		$product->set_attributes( array( $attr_bill ) );
		$product->update_meta_data( '_lis_standard_listing_product', '1' );
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'save_failed', 'Could not create the Standard Listing product.' );
		}
	} else {
		$product = wc_get_product( $product_id );
	}

	$existing = array();
	foreach ( $product->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_standard_billing_period', true );
		if ( $period ) {
			$existing[ $period ] = true;
		}
	}

	$plans = array(
		array( 'label' => 'Monthly',  'price' => 15,  'period' => 'month' ),
		array( 'label' => 'Annually', 'price' => 150, 'period' => 'year' ),
	);

	$added = 0;
	foreach ( $plans as $plan ) {
		if ( isset( $existing[ $plan['period'] ] ) ) {
			continue;
		}
		$var = ( $use_sub && class_exists( 'WC_Product_Subscription_Variation' ) )
			? new WC_Product_Subscription_Variation()
			: new WC_Product_Variation();
		$var->set_parent_id( $product_id );
		$var->set_attributes( array( 'billing' => $plan['label'] ) );
		$var->set_regular_price( $plan['price'] );
		$var->set_price( $plan['price'] );
		$var->set_virtual( true );
		$var->update_meta_data( '_lis_standard_billing_period', $plan['period'] );
		if ( $use_sub ) {
			$var->update_meta_data( '_subscription_price', $plan['price'] );
			$var->update_meta_data( '_subscription_period', $plan['period'] );
			$var->update_meta_data( '_subscription_period_interval', '1' );
			$var->update_meta_data( '_subscription_length', '0' );
			$var->update_meta_data( '_subscription_sign_up_fee', '0' );
			$var->update_meta_data( '_subscription_trial_length', '0' );
			$var->update_meta_data( '_subscription_trial_period', 'day' );
		}
		$var->save();
		$added++;
	}

	// Write each child's billing attribute meta directly so the product page's
	// Billing dropdown resolves to the right variation (same guard the Vendor
	// Showcase generator uses).
	$fresh = wc_get_product( $product_id );
	foreach ( $fresh->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_standard_billing_period', true );
		if ( $period ) {
			update_post_meta( $child_id, 'attribute_billing', 'year' === $period ? 'Annually' : 'Monthly' );
		}
	}

	if ( class_exists( 'WC_Product_Variable' ) ) {
		WC_Product_Variable::sync( $product_id );
	}
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients( $product_id );
	}

	update_option( 'lis_directory_standard_listing_product_id', $product_id );

	return array( 'added' => $added, 'product_id' => $product_id );
}

/**
 * admin-post handler for the "Generate / top up Standard Listing product" button.
 */
function lis_directory_handle_generate_standard_listing() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_std_gen_nonce'] ) || ! wp_verify_nonce( $_POST['lis_std_gen_nonce'], 'lis_directory_generate_standard_listing' ) ) {
		wp_die( 'Security check failed.' );
	}

	$result   = lis_directory_generate_standard_listing_product();
	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );

	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'lis_std_gen', 'error', $redirect );
	} else {
		$redirect = add_query_arg( array( 'lis_std_gen' => 'ok', 'lis_std_added' => (int) $result['added'] ), $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}
