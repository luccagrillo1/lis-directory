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
	// Show the listing once, as a clean "Listing: <name>" row under the product
	// (woocommerce_get_item_data). The old woocommerce_cart_item_name /
	// woocommerce_order_item_name appends ("— Featuring: <name>") duplicated it
	// and made the line item read as a run-on, so they're intentionally not hooked.
	add_filter( 'woocommerce_get_item_data', 'lis_directory_show_listing_in_cart_item_data', 10, 2 );
	add_action( 'woocommerce_checkout_create_order_line_item', 'lis_directory_persist_listing_id_to_order_item', 10, 4 );
	add_action( 'woocommerce_order_status_completed', 'lis_directory_handle_featured_listing_order' );
	add_action( 'woocommerce_order_status_processing', 'lis_directory_handle_featured_listing_order' );
	add_action( 'woocommerce_thankyou', 'lis_directory_listing_order_thankyou' );
	add_action( 'admin_post_lis_directory_generate_standard_listing', 'lis_directory_handle_generate_standard_listing' );
	add_action( 'admin_post_lis_directory_generate_featured_listing', 'lis_directory_handle_generate_featured_listing' );
	add_action( 'admin_post_lis_directory_bundle_checkout', 'lis_directory_handle_bundle_checkout' );
	add_action( 'admin_post_nopriv_lis_directory_bundle_checkout', 'lis_directory_handle_bundle_checkout' );
	// Standard-only checkouts go through WooCommerce's own ?add-to-cart= URL
	// (no admin-post handler), so clear leftovers from an add-to-cart hook too.
	add_action( 'woocommerce_add_to_cart', 'lis_directory_purge_foreign_plan_items_on_add', 10, 6 );
	add_filter( 'woocommerce_cart_item_name', 'lis_directory_checkout_remove_link', 20, 3 );
	// Priority 100: after WooCommerce's order details and billing address.
	add_action( 'woocommerce_thankyou', 'lis_directory_thankyou_home_button', 100 );

	if ( class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'woocommerce_subscription_status_cancelled', 'lis_directory_handle_featured_listing_subscription_ended' );
		add_action( 'woocommerce_subscription_status_expired', 'lis_directory_handle_featured_listing_subscription_ended' );
		add_action( 'woocommerce_subscription_status_cancelled', 'lis_directory_handle_standard_listing_subscription_ended' );
		add_action( 'woocommerce_subscription_status_expired', 'lis_directory_handle_standard_listing_subscription_ended' );
	}
}

/**
 * Upgrade checkout URL for an existing listing to buy Featured and/or Vendor
 * Showcase together — shared by the Dashboard's single-tier post-publish
 * upsell links (lis_directory_get_feature_listing_url() /
 * lis_directory_get_showcase_upgrade_url() below, via the single-tier
 * wrapper lis_directory_get_listing_upgrade_url() further down) and by
 * lis_directory_build_listing_checkout_url() for the initial wizard's
 * checkbox-based Plan step, which can select both at once. By request,
 * Standard is a required foundation for either upgrade: rather than
 * blocking the purchase, lis_directory_handle_bundle_checkout() silently
 * also adds Standard to the SAME cart when this listing doesn't already
 * have an active one (_lis_listing_standard_active) — the buyer sees every
 * line item on the normal WooCommerce cart/checkout review before paying,
 * same as adding the products by hand would look.
 *
 * Returns '' if ANY requested tier's product isn't configured/published/
 * purchasable, or — Showcase only — this listing's own category is already
 * taken by another active vendor, so callers can hide the button/checkbox
 * rather than link to a checkout that will fail.
 */
function lis_directory_get_listing_upgrades_url( $listing_id, $tiers, $billing = 'month' ) {
	$listing_id = (int) $listing_id;
	$tiers      = array_values( array_unique( array_intersect( array( 'featured', 'showcase' ), (array) $tiers ) ) );
	if ( empty( $tiers ) || ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	// $listing_id === 0 is a real, intentional case: lis_directory_build_listing_checkout_url()
	// pre-validates chosen upgrades before the listing post exists yet (see
	// the $can_checkout check in listings-submission.php), just to confirm
	// each tier's product is configured/purchasable. get_post_meta(0, ...)
	// and get_the_terms(0, ...) below both correctly return empty for a
	// nonexistent post, so the "already has Standard" / "category taken"
	// checks harmlessly no-op rather than falsely blocking a real submission.

	foreach ( $tiers as $tier ) {
		$product_id = ( 'showcase' === $tier )
			? ( function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ? lis_directory_get_vendor_showcase_product_id() : 0 )
			: (int) get_option( 'lis_directory_featured_listing_product_id' );
		if ( ! $product_id ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
			return '';
		}

		if ( 'showcase' === $tier && function_exists( 'lis_directory_is_listing_category_showcase_taken' ) ) {
			$term_id = function_exists( 'lis_directory_get_listing_showcase_term' ) ? lis_directory_get_listing_showcase_term( $listing_id ) : 0;
			if ( $term_id && lis_directory_is_listing_category_showcase_taken( $term_id ) ) {
				return '';
			}
		}
	}

	return add_query_arg( array(
		'action'         => 'lis_directory_bundle_checkout',
		'tier'           => implode( ',', $tiers ),
		'lis_listing_id' => $listing_id,
		'billing'        => ( 'year' === $billing ) ? 'year' : 'month',
	), admin_url( 'admin-post.php' ) );
}

/**
 * Single-tier convenience wrapper around lis_directory_get_listing_upgrades_url()
 * — used by the Dashboard's two separate upsell links (Featured, Showcase),
 * each its own button rather than a combined checkbox picker.
 */
function lis_directory_get_listing_upgrade_url( $listing_id, $tier, $billing = 'month' ) {
	if ( ! in_array( $tier, array( 'featured', 'showcase' ), true ) ) {
		return '';
	}
	return lis_directory_get_listing_upgrades_url( $listing_id, array( $tier ), $billing );
}

/**
 * Empty string if the Featured product isn't configured yet (Settings >
 * LIS Directory Settings) — callers should hide the upgrade button entirely
 * in that case, not link to a broken checkout.
 */
function lis_directory_get_feature_listing_url( $listing_id, $billing = 'month' ) {
	return lis_directory_get_listing_upgrade_url( $listing_id, 'featured', $billing );
}

/**
 * Same as lis_directory_get_feature_listing_url() but for the Vendor
 * Showcase upgrade — added alongside it on the Dashboard so a listing owner
 * can join the Showcase after the fact, not only at initial signup.
 */
function lis_directory_get_showcase_upgrade_url( $listing_id, $billing = 'month' ) {
	return lis_directory_get_listing_upgrade_url( $listing_id, 'showcase', $billing );
}

/**
 * Bridges a Featured/Showcase upgrade click into a single checkout that also
 * includes Standard, silently, when this listing doesn't already have an
 * active one. Not a real payment itself — just cart assembly + a redirect to
 * WooCommerce's own checkout, same "hand it off to WooCommerce" approach as
 * the rest of this file.
 */
function lis_directory_handle_bundle_checkout() {
	$tiers_raw  = isset( $_GET['tier'] ) ? sanitize_text_field( wp_unslash( $_GET['tier'] ) ) : '';
	$tiers      = array_values( array_unique( array_intersect( array( 'featured', 'showcase' ), array_map( 'sanitize_key', explode( ',', $tiers_raw ) ) ) ) );
	$listing_id = isset( $_GET['lis_listing_id'] ) ? absint( $_GET['lis_listing_id'] ) : 0;
	$billing    = ( isset( $_GET['billing'] ) && 'year' === $_GET['billing'] ) ? 'year' : 'month';

	if ( empty( $tiers ) || ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) || ! function_exists( 'WC' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	if ( ! WC()->cart ) {
		wc_load_cart();
	}
	lis_directory_purge_foreign_plan_cart_items();

	$std_pid   = function_exists( 'lis_directory_get_standard_listing_product_id' ) ? lis_directory_get_standard_listing_product_id() : 0;
	$needs_std = $std_pid && ! (bool) get_post_meta( $listing_id, '_lis_listing_standard_active', true );

	$upgrade_pids = array();
	foreach ( $tiers as $tier ) {
		$pid = ( 'showcase' === $tier )
			? ( function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ? lis_directory_get_vendor_showcase_product_id() : 0 )
			: (int) get_option( 'lis_directory_featured_listing_product_id' );
		if ( $pid ) {
			$upgrade_pids[] = $pid;
		}
	}

	// WooCommerce Subscriptions only allows ONE cart line per subscription
	// product at a time — a leftover item from a DIFFERENT listing's
	// still-open cart (e.g. an earlier upgrade the buyer never finished
	// checking out) makes add_to_cart() below fail silently (it just
	// queues a "You cannot add another X" notice, no exception). Catch
	// that up front and send the buyer to their cart to resolve it,
	// rather than continuing on to a checkout that's silently missing a
	// line item — that's how a "Featured" purchase could complete without
	// its required Standard line, defeating the whole bundle.
	foreach ( array_filter( array_merge( array( $needs_std ? $std_pid : 0 ), $upgrade_pids ) ) as $pid ) {
		if ( lis_directory_cart_has_product_for_other_listing( $pid, $listing_id ) ) {
			$product = wc_get_product( $pid );
			wc_add_notice(
				sprintf(
					/* translators: %s: product name already in the cart for a different listing */
					__( 'Your cart already has "%s" for a different listing. Please finish or empty that order before upgrading another listing.', 'lis-directory' ),
					$product ? $product->get_name() : __( 'a listing product', 'lis-directory' )
				),
				'error'
			);
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
	}

	if ( $needs_std ) {
		$std_var = lis_directory_resolve_plan_variation( $std_pid, $billing );
		WC()->cart->add_to_cart( $std_pid, 1, (int) $std_var['variation_id'], $std_var['variation_id'] ? array( 'attribute_billing' => $std_var['attr'] ) : array() );
	}

	foreach ( $upgrade_pids as $pid ) {
		$var = lis_directory_resolve_plan_variation( $pid, $billing );
		WC()->cart->add_to_cart( $pid, 1, (int) $var['variation_id'], $var['variation_id'] ? array( 'attribute_billing' => $var['attr'] ) : array() );
	}

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * The upgrades a submitted plan choice means. Plans are single-select tiers
 * that each include the ones below (Standard < Featured < Vendor Showcase), so
 * the choice maps to at most ONE upgrade product: Standard is always bundled in
 * by the checkout builder, and Vendor Showcase carries the Featured benefits
 * itself (see lis_directory_sync_featured_with_showcase()) — a Showcase buyer is
 * never also charged for the Featured product.
 *
 * Reads the new `lis_listing_plan` radio; a page rendered before the picker
 * became single-select still posts `lis_listing_upgrades[]` checkboxes, so
 * those are honored too (Showcase wins over Featured).
 *
 * @param array $src Unslashed request data ($_POST).
 * @return string[] array() (Standard only), array('featured') or array('showcase').
 */
function lis_directory_read_plan_upgrades( array $src ) {
	$plan = isset( $src['lis_listing_plan'] ) ? sanitize_key( $src['lis_listing_plan'] ) : '';
	if ( in_array( $plan, array( 'standard', 'featured', 'showcase' ), true ) ) {
		return 'standard' === $plan ? array() : array( $plan );
	}
	$legacy = array_map( 'sanitize_key', (array) ( isset( $src['lis_listing_upgrades'] ) ? $src['lis_listing_upgrades'] : array() ) );
	if ( in_array( 'showcase', $legacy, true ) ) {
		return array( 'showcase' );
	}
	return in_array( 'featured', $legacy, true ) ? array( 'featured' ) : array();
}

/**
 * Vendor Showcase includes the Featured benefits: while a listing's showcase
 * vendor is Active the listing is Featured, and when the vendor stops being
 * Active that Featured is taken back — but only if Showcase is what granted
 * it (`_lis_listing_featured_via_showcase`), never a Featured someone paid for
 * or an admin ticked by hand. Hooked on the plugin's single status choke point
 * so payment, manual approval and subscription-end all behave the same.
 */
add_action( 'lis_directory_vendor_status_changed', 'lis_directory_sync_featured_with_showcase', 10, 2 );

function lis_directory_sync_featured_with_showcase( $vendor_id, $new_status ) {
	$listing_id = (int) get_post_meta( $vendor_id, '_lis_pv_listing_id', true );
	if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
		return;
	}
	if ( 'active' === $new_status ) {
		if ( ! get_post_meta( $listing_id, '_lis_listing_featured', true ) ) {
			update_post_meta( $listing_id, '_lis_listing_featured', true );
			update_post_meta( $listing_id, '_lis_listing_featured_via_showcase', 1 );
		}
	} elseif ( get_post_meta( $listing_id, '_lis_listing_featured_via_showcase', true ) ) {
		delete_post_meta( $listing_id, '_lis_listing_featured_via_showcase' );
		update_post_meta( $listing_id, '_lis_listing_featured', false );
	}
}

/**
 * One-time catch-up: Showcase vendors that were already Active before Showcase
 * started including Featured get it too (the status hook above only fires on a
 * change). Flagged by an option, so it never re-runs.
 */
add_action( 'init', 'lis_directory_backfill_showcase_featured', 30 );

function lis_directory_backfill_showcase_featured() {
	if ( get_option( 'lis_directory_showcase_featured_backfill' ) || ! function_exists( 'lis_directory_get_all_active_vendors' ) ) {
		return;
	}
	foreach ( lis_directory_get_all_active_vendors() as $vendor ) {
		lis_directory_sync_featured_with_showcase( $vendor->ID, 'active' );
	}
	update_option( 'lis_directory_showcase_featured_backfill', 1 );
}

/**
 * A "Back to homepage" button at the very end of the order-received page, so
 * the page isn't a dead end after paying. Shown for every order (listing, job
 * or otherwise); the listing/job thank-you blocks above keep their own more
 * specific buttons.
 */
function lis_directory_thankyou_home_button( $order_id ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	echo '<p class="lis-listing-thankyou-actions" style="margin-top:28px;justify-content:flex-start;"><a class="lis-listing-thankyou-btn" href="' . esc_url( home_url( '/' ) ) . '">Back to homepage</a></p>';
}

/**
 * A "×" in front of each line in the checkout's order review (the theme's
 * checkout has no remove control — only the cart page does), so a stale or
 * unwanted line can be dropped without leaving checkout. WooCommerce's own
 * remove link is used, so it's nonce-protected, and it sends you back to the
 * page you clicked from (checkout).
 *
 * One exception: a Standard tier line that's bundled with a Featured/Showcase
 * line for the SAME listing has no ×. Standard is the required foundation for
 * both, and letting a buyer strip it would sell Featured/Showcase without it;
 * drop the upgrade line instead (or change plan) and Standard stays on its own.
 */
function lis_directory_checkout_remove_link( $name, $cart_item, $cart_item_key ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! function_exists( 'wc_get_cart_remove_url' ) ) {
		return $name;
	}
	if ( ! empty( $cart_item['lis_listing_id'] ) && function_exists( 'lis_directory_listing_tier_for_product' )
		&& 'standard' === lis_directory_listing_tier_for_product( (int) $cart_item['product_id'] ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $other_key => $other ) {
			if ( $other_key !== $cart_item_key
				&& isset( $other['lis_listing_id'] ) && (int) $other['lis_listing_id'] === (int) $cart_item['lis_listing_id']
				&& in_array( lis_directory_listing_tier_for_product( (int) $other['product_id'] ), array( 'featured', 'showcase' ), true ) ) {
				return $name;
			}
		}
	}
	$link = sprintf(
		'<a href="%s" class="remove lis-checkout-remove" aria-label="%s" title="Remove from cart" style="display:inline-block;margin-right:8px;text-decoration:none;font-size:1.3em;line-height:1;font-weight:700;">&times;</a>',
		esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
		esc_attr__( 'Remove this item', 'lis-directory' )
	);
	return $link . $name;
}

/**
 * A cart line that's a leftover from the OLD Directorist listing-plan flow
 * (e.g. "Standard - Local Business", a one-time $60 plan) rather than one of
 * this plugin's own tiers. WooCommerce keeps a logged-in user's cart between
 * visits, so one abandoned Directorist plan purchase rides along into every
 * later checkout and quietly adds its price to the total.
 *
 * Deliberately narrow — it matches Directorist's plan product type
 * (`listing_pricing_plans`) or that exact "… - Local Business" plan name, and
 * never touches anything else in the cart (events, other products). The
 * `lis_directory_is_foreign_plan_cart_item` filter lets it be extended.
 */
function lis_directory_is_foreign_plan_cart_item( $item ) {
	$product = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : null;
	if ( ! $product || ! empty( $item['lis_listing_id'] ) || ! empty( $item['lis_job_id'] ) ) {
		return false;
	}
	$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
	if ( $pid && function_exists( 'lis_directory_listing_tier_for_product' ) && lis_directory_listing_tier_for_product( $pid ) ) {
		return false;
	}
	if ( $pid && function_exists( 'lis_directory_get_job_product_id' ) && lis_directory_get_job_product_id() === $pid ) {
		return false;
	}
	$parent  = $pid ? wc_get_product( $pid ) : null;
	$foreign = 'listing_pricing_plans' === $product->get_type()
		|| ( $parent && 'listing_pricing_plans' === $parent->get_type() )
		|| (bool) preg_match( '/\s[-–]\s*Local Business$/i', wp_strip_all_tags( $product->get_name() ) );
	return (bool) apply_filters( 'lis_directory_is_foreign_plan_cart_item', $foreign, $item );
}

function lis_directory_purge_foreign_plan_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$removed = false;
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( lis_directory_is_foreign_plan_cart_item( $item ) ) {
			WC()->cart->remove_cart_item( $key );
			$removed = true;
		}
	}
	if ( $removed && function_exists( 'wc_add_notice' ) ) {
		wc_add_notice( 'We removed an old listing plan that was left in your cart, so you\'re only charged for what you picked here.', 'notice' );
	}
}

function lis_directory_purge_foreign_plan_items_on_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	if ( ! empty( $cart_item_data['lis_listing_id'] ) ) {
		lis_directory_purge_foreign_plan_cart_items();
	}
}

/**
 * Whether the cart already contains $product_id tagged to a DIFFERENT
 * listing than $listing_id (see the note in lis_directory_handle_bundle_checkout()
 * above for why this matters — WC Subscriptions rejects a second cart line
 * for the same product outright).
 */
function lis_directory_cart_has_product_for_other_listing( $product_id, $listing_id ) {
	if ( ! WC()->cart ) {
		return false;
	}
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id && (int) ( $item['lis_listing_id'] ?? 0 ) !== (int) $listing_id ) {
			return true;
		}
	}
	return false;
}

/**
 * Which listing tier (if any) a WooCommerce product represents. '' for a
 * product that isn't one of the listing tiers.
 */
function lis_directory_listing_tier_for_product( $product_id ) {
	$product_id = (int) $product_id;
	if ( ! $product_id ) {
		return '';
	}
	if ( function_exists( 'lis_directory_get_standard_listing_product_id' ) && $product_id === lis_directory_get_standard_listing_product_id() ) {
		return 'standard';
	}
	if ( $product_id === (int) get_option( 'lis_directory_featured_listing_product_id' ) ) {
		return 'featured';
	}
	if ( function_exists( 'lis_directory_get_vendor_showcase_product_id' ) && $product_id === lis_directory_get_vendor_showcase_product_id() ) {
		return 'showcase';
	}
	return '';
}

/**
 * The Vendor Showcase billing variation (Monthly/Annually) of the single
 * Showcase product. 0 if none exists. Single-product model: the Showcase no
 * longer has per-category variations — which category a buyer occupies comes
 * from their listing's own category (see the generator note in
 * includes/woocommerce.php), so this only resolves the billing period. The
 * `$term_id` argument is kept for call-site compatibility but ignored.
 */
function lis_directory_get_showcase_variation( $term_id, $billing ) {
	$product_id = function_exists( 'lis_directory_get_vendor_showcase_product_id' ) ? lis_directory_get_vendor_showcase_product_id() : 0;
	if ( ! $product_id ) {
		return 0;
	}
	$want    = ( 'year' === $billing ) ? 'year' : 'month';
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product ) {
		return 0;
	}
	foreach ( $product->get_children() as $vid ) {
		if ( get_post_meta( $vid, '_lis_pv_billing_period', true ) === $want ) {
			return (int) $vid;
		}
	}
	return 0;
}

/**
 * The pending/active lis_preferred_vendor entry created for a given listing by
 * the unified wizard's Vendor Showcase path (linked via `_lis_pv_listing_id`).
 */
function lis_directory_get_vendor_for_listing( $listing_id ) {
	$ids = get_posts( array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array( 'key' => '_lis_pv_listing_id', 'value' => (int) $listing_id ),
		),
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Whether a listing currently has an ACTIVE Vendor Showcase entry — the gate
 * for Showcase-only UI: the offer button on the single-listing template, and
 * the offer panel in the front-end edit form (includes/listings-edit.php).
 */
function lis_directory_listing_is_active_showcase( $listing_id ) {
	$vendor_id = lis_directory_get_vendor_for_listing( $listing_id );
	return $vendor_id && 'active' === get_post_meta( $vendor_id, '_lis_pv_status', true );
}

function lis_directory_add_listing_id_to_cart_item( $cart_item_data, $product_id ) {
	if ( ! lis_directory_listing_tier_for_product( (int) $product_id ) ) {
		return $cart_item_data;
	}
	$listing_id = isset( $_REQUEST['lis_listing_id'] ) ? absint( $_REQUEST['lis_listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cart-item tag, ownership is re-checked at order-completion time before anything is granted.
	if ( $listing_id && 'lis_listing' === get_post_type( $listing_id ) ) {
		$cart_item_data['lis_listing_id'] = $listing_id;
	}
	return $cart_item_data;
}

/**
 * Resolve a tier product's monthly/annual variation for a given billing. For a
 * simple (non-variable) product it returns just the price. Recognises the
 * Standard product's `_lis_standard_billing_period` marker, the Showcase's
 * `_lis_pv_billing_period`, and a plain `attribute_billing` (Monthly/Annually).
 *
 * @return array { variation_id:int, price:string|null, attr:string }
 */
function lis_directory_resolve_plan_variation( $product_id, $billing ) {
	$out     = array( 'variation_id' => 0, 'price' => null, 'attr' => '' );
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product ) {
		return $out;
	}
	if ( ! $product->is_type( array( 'variable', 'variable-subscription' ) ) ) {
		$out['price'] = $product->get_price();
		return $out;
	}

	$want = ( 'year' === $billing ) ? 'year' : 'month';
	$first = 0;
	foreach ( $product->get_children() as $child_id ) {
		if ( ! $first ) {
			$first = $child_id;
		}
		$std  = get_post_meta( $child_id, '_lis_standard_billing_period', true );
		$vs   = get_post_meta( $child_id, '_lis_pv_billing_period', true );
		$attr = get_post_meta( $child_id, 'attribute_billing', true );
		$period = $std ? $std : ( $vs ? $vs : ( 'Annually' === $attr ? 'year' : ( 'Monthly' === $attr ? 'month' : '' ) ) );
		if ( $period === $want ) {
			$v = wc_get_product( $child_id );
			return array(
				'variation_id' => (int) $child_id,
				'price'        => $v ? $v->get_price() : null,
				'attr'         => ( 'year' === $want ) ? 'Annually' : 'Monthly',
			);
		}
	}
	if ( $first ) {
		$v = wc_get_product( $first );
		$out['variation_id'] = (int) $first;
		$out['price']        = $v ? $v->get_price() : null;
		$out['attr']         = get_post_meta( $first, 'attribute_billing', true );
	}
	return $out;
}

/**
 * Build the URL that lands the buyer on checkout for their chosen upgrades,
 * used by the initial [lis_listing_submit] wizard's Plan step — Standard is
 * always the (non-optional) baseline there, with Featured/Vendor Showcase as
 * independent checkboxes, so $plan_or_upgrades is normally an array of zero
 * or more of 'featured'/'showcase'. A non-empty array delegates to
 * lis_directory_get_listing_upgrades_url() — the same bundling logic the
 * Dashboard's post-publish upsell links use, so choosing either at initial
 * signup silently includes Standard too (by request, Standard is a required
 * foundation for both). An empty array falls through to a plain Standard-only
 * add-to-cart — it never needs to bundle itself.
 *
 * Also accepts the legacy single-tier string ('standard'|'featured'|'showcase')
 * still used by the claim-listing flow (includes/listings-claim.php), whose
 * plan picker predates the wizard's checkbox redesign and stays single-select.
 *
 * Returns '' when a requested tier's product isn't configured, published, or
 * purchasable yet — callers fall back to the free pending flow in that case,
 * so the form keeps working while the products are still draft/unpriced.
 */
function lis_directory_build_listing_checkout_url( $plan_or_upgrades, $billing, $listing_id, $category_term_id = 0 ) {
	if ( ! function_exists( 'wc_get_checkout_url' ) ) {
		return '';
	}

	$upgrades = is_array( $plan_or_upgrades )
		? array_values( array_intersect( array( 'featured', 'showcase' ), $plan_or_upgrades ) )
		: ( in_array( $plan_or_upgrades, array( 'featured', 'showcase' ), true ) ? array( $plan_or_upgrades ) : array() );

	if ( ! empty( $upgrades ) ) {
		// Which category the buyer occupies is the listing's OWN category
		// ($category_term_id is a lis_listing_category term id here) —
		// exclusivity is enforced in PHP (return '' when that category
		// already has an active showcase vendor, so the caller falls
		// back/blocks), not via per-variation stock. Re-checked again inside
		// lis_directory_get_listing_upgrades_url() from the listing's actual
		// saved category, since at this point in the wizard the category may
		// not be saved to the post yet.
		if ( in_array( 'showcase', $upgrades, true ) && $category_term_id
			&& function_exists( 'lis_directory_is_listing_category_showcase_taken' )
			&& lis_directory_is_listing_category_showcase_taken( $category_term_id ) ) {
			return '';
		}
		return lis_directory_get_listing_upgrades_url( $listing_id, $upgrades, $billing );
	}

	// No upgrades ('standard', '', or an empty array) — just the mandatory
	// Standard product on its own; it never needs to bundle itself.
	$product_id = function_exists( 'lis_directory_get_standard_listing_product_id' ) ? lis_directory_get_standard_listing_product_id() : 0;
	if ( ! $product_id ) {
		return '';
	}
	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
		return '';
	}

	$args = array(
		'add-to-cart'    => $product_id,
		'lis_listing_id' => (int) $listing_id,
	);
	$var = lis_directory_resolve_plan_variation( $product_id, $billing );
	if ( $var['variation_id'] ) {
		$args['variation_id']      = $var['variation_id'];
		$args['attribute_billing'] = $var['attr'];
	}
	return add_query_arg( $args, wc_get_checkout_url() );
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
	if ( ! $order_id ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	foreach ( $order->get_items() as $item ) {
		$listing_id = (int) $item->get_meta( '_lis_listing_id' );
		if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
			continue;
		}
		$tier = lis_directory_listing_tier_for_product( (int) $item->get_product_id() );
		if ( ! $tier ) {
			continue;
		}

		// Auto-publish the listing on payment — it was created as a draft
		// "awaiting payment" by the submission wizard. (A listing that was
		// already published, e.g. an existing one being upgraded to Featured
		// from the dashboard, is left as-is.)
		if ( in_array( get_post_status( $listing_id ), array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			wp_update_post( array( 'ID' => $listing_id, 'post_status' => 'publish' ) );
		}
		update_post_meta( $listing_id, '_lis_listing_paid_order_id', $order_id );

		if ( 'standard' === $tier ) {
			// Same subscription-id-lookup pattern as Featured/Showcase below, so
			// lis_directory_handle_standard_listing_subscription_ended() can find
			// its way back to this listing when the Standard subscription ends.
			$linked_id = $order_id;
			if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				if ( ! empty( $subscriptions ) ) {
					$linked_id = (int) reset( $subscriptions )->get_id();
				}
			}
			update_post_meta( $listing_id, '_lis_listing_standard_active', true );
			update_post_meta( $listing_id, '_lis_listing_standard_order_id', $linked_id );
		}

		if ( 'featured' === $tier ) {
			// Store the subscription ID when this order started one, so the
			// cancelled/expired hook (which only knows the subscription) can
			// find its way back. A one-time purchase stores the order ID.
			$linked_id = $order_id;
			if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				if ( ! empty( $subscriptions ) ) {
					$linked_id = (int) reset( $subscriptions )->get_id();
				}
			}
			update_post_meta( $listing_id, '_lis_listing_featured', true );
			update_post_meta( $listing_id, '_lis_listing_featured_order_id', $linked_id );
			delete_post_meta( $listing_id, '_lis_listing_featured_via_showcase' ); // Now genuinely paid for — a later Showcase end must not revoke it.
		}

		if ( 'showcase' === $tier ) {
			// The wizard already created the pending vendor entry; payment
			// activates it (auto-publish parity), which shows its card and marks
			// the category taken via the stock-sync hook.
			$vendor_id = lis_directory_get_vendor_for_listing( $listing_id );
			if ( $vendor_id ) {
				$linked_id = $order_id;
				if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
					$subs = wcs_get_subscriptions_for_order( $order_id );
					if ( ! empty( $subs ) ) {
						$linked_id = (int) reset( $subs )->get_id();
					}
				}
				// Store the SUBSCRIPTION id (not the order id) so the existing
				// cancelled/expired handler in woocommerce.php can find and expire
				// this vendor when the subscription ends.
				update_post_meta( $vendor_id, '_lis_pv_wc_order_id', $linked_id );

				// Activate unless the category was somehow taken in the meantime
				// (the out-of-stock guard on the variation should prevent that).
				if ( ! function_exists( 'lis_directory_get_active_conflict' ) || ! lis_directory_get_active_conflict( $vendor_id ) ) {
					lis_directory_set_vendor_status( $vendor_id, 'active' );
				}
			}
		}
	}
}

/**
 * Post-payment confirmation on the WooCommerce order-received (thank-you) page,
 * when the order paid for a listing. Complements the immediate thank-you that
 * [lis_listing_submit] shows for the free fallback path.
 */
function lis_directory_listing_order_thankyou( $order_id ) {
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$listing_id = 0;
	foreach ( $order->get_items() as $item ) {
		$candidate = (int) $item->get_meta( '_lis_listing_id' );
		if ( $candidate && 'lis_listing' === get_post_type( $candidate ) && lis_directory_listing_tier_for_product( (int) $item->get_product_id() ) ) {
			$listing_id = $candidate;
			break;
		}
	}
	if ( ! $listing_id ) {
		return;
	}
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	$is_live = ( 'publish' === get_post_status( $listing_id ) );
	?>
	<div class="lis-listing-thankyou">
		<div class="lis-listing-thankyou-icon" aria-hidden="true">
			<svg viewBox="0 0 52 52" width="56" height="56" role="img"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5" opacity="0.35"/><path fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M16 27.5l7 7 14-16"/></svg>
		</div>
		<h2 class="lis-listing-thankyou-title">You're all set &mdash; thank you!</h2>
		<p class="lis-listing-thankyou-text">
			<?php if ( $is_live ) : ?>
				Your payment went through and <strong><?php echo esc_html( get_the_title( $listing_id ) ); ?></strong> is now live in the directory.
			<?php else : ?>
				Your payment went through. <strong><?php echo esc_html( get_the_title( $listing_id ) ); ?></strong> will go live in a moment once the order finishes processing.
			<?php endif; ?>
		</p>
		<div class="lis-listing-thankyou-actions">
			<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>">View my listing</a>
			<a class="lis-listing-thankyou-btn lis-listing-thankyou-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'lis_listing' ) ); ?>">Browse the directory</a>
		</div>
	</div>
	<?php
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

/**
 * Standard is the required foundation for Featured/Vendor Showcase (by
 * request) — cancelling or expiring it cascades to revoke both, on top of
 * clearing its own active flag. Featured already revokes itself the same way
 * on its own subscription ending (see above); Vendor Showcase's own ending
 * is handled separately in includes/woocommerce.php
 * (lis_directory_handle_subscription_ended()) — this just adds the same
 * expiry to the vendor from the Standard side too, so either subscription
 * ending is enough to take the Showcase slot down.
 */
function lis_directory_handle_standard_listing_subscription_ended( $subscription ) {
	$subscription_id = $subscription->get_id();

	$listings = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_listing_standard_order_id',
				'value' => $subscription_id,
			),
		),
	) );

	if ( empty( $listings ) ) {
		return;
	}
	$listing_id = $listings[0];

	update_post_meta( $listing_id, '_lis_listing_standard_active', false );
	update_post_meta( $listing_id, '_lis_listing_featured', false );

	$vendor_id = lis_directory_get_vendor_for_listing( $listing_id );
	if ( $vendor_id && function_exists( 'lis_directory_set_vendor_status' ) ) {
		lis_directory_set_vendor_status( $vendor_id, 'expired' );
	}
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
		array( 'label' => 'Monthly',  'price' => 9,  'period' => 'month' ),
		array( 'label' => 'Annually', 'price' => 90, 'period' => 'year' ),
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

/* ------------------------------------------------------------------ *
 * Featured Listing product — same monthly/annual variable-subscription *
 * shape as Standard (so it has a real annual price, not one flat fee),  *
 * just a higher tier. Generating it repoints the Featured Listing        *
 * Product setting to the generated product.                              *
 * ------------------------------------------------------------------ */

function lis_directory_get_generated_featured_product_id() {
	$ids = get_posts( array(
		'post_type'        => 'product',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'meta_query'       => array(
			array( 'key' => '_lis_featured_listing_product', 'value' => '1' ),
		),
		'suppress_filters' => false,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Builds (or tops up) a "Featured Listing" variable-subscription product with
 * Monthly ($19) and Annually ($190) variations, and points
 * lis_directory_featured_listing_product_id at it. Idempotent.
 *
 * @return array|WP_Error { added:int, product_id:int } on success.
 */
function lis_directory_generate_featured_listing_product() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'no_wc', 'WooCommerce is not active.' );
	}
	$use_sub = class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Product_Variable_Subscription' );

	$product_id = lis_directory_get_generated_featured_product_id();
	if ( ! $product_id ) {
		$product = $use_sub ? new WC_Product_Variable_Subscription() : new WC_Product_Variable();
		$product->set_name( 'Featured Listing' );
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
		$product->update_meta_data( '_lis_featured_listing_product', '1' );
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'save_failed', 'Could not create the Featured Listing product.' );
		}
	} else {
		$product = wc_get_product( $product_id );
	}

	$existing = array();
	foreach ( $product->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_featured_billing_period', true );
		if ( $period ) {
			$existing[ $period ] = true;
		}
	}

	$plans = array(
		array( 'label' => 'Monthly',  'price' => 19,  'period' => 'month' ),
		array( 'label' => 'Annually', 'price' => 190, 'period' => 'year' ),
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
		$var->update_meta_data( '_lis_featured_billing_period', $plan['period'] );
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

	$fresh = wc_get_product( $product_id );
	foreach ( $fresh->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_featured_billing_period', true );
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

	// Point the Featured setting at this product so the plan step + order hook use it.
	update_option( 'lis_directory_featured_listing_product_id', $product_id );

	return array( 'added' => $added, 'product_id' => $product_id );
}

function lis_directory_handle_generate_featured_listing() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_feat_gen_nonce'] ) || ! wp_verify_nonce( $_POST['lis_feat_gen_nonce'], 'lis_directory_generate_featured_listing' ) ) {
		wp_die( 'Security check failed.' );
	}

	$result   = lis_directory_generate_featured_listing_product();
	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );

	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'lis_feat_gen', 'error', $redirect );
	} else {
		$redirect = add_query_arg( array( 'lis_feat_gen' => 'ok', 'lis_feat_added' => (int) $result['added'] ), $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}
