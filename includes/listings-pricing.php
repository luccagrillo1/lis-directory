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

add_action( 'admin_init', 'lis_directory_register_listing_pricing_settings' );
add_action( 'plugins_loaded', 'lis_directory_init_listing_pricing_woocommerce' );

function lis_directory_register_listing_pricing_settings() {
	register_setting( 'lis_pv_settings_group', 'lis_directory_featured_listing_product_id', array(
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
	add_action( 'woocommerce_checkout_create_order_line_item', 'lis_directory_persist_listing_id_to_order_item', 10, 4 );
	add_action( 'woocommerce_order_status_completed', 'lis_directory_handle_featured_listing_order' );
	add_action( 'woocommerce_order_status_processing', 'lis_directory_handle_featured_listing_order' );

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

function lis_directory_show_listing_in_cart_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['lis_listing_id'] ) ) {
		$item_data[] = array(
			'name'  => 'Listing',
			'value' => get_the_title( $cart_item['lis_listing_id'] ),
		);
	}
	return $item_data;
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
