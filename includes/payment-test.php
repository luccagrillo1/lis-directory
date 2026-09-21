<?php
/**
 * One-click "$1 payment test": creates a hidden, admin-only, one-time $1
 * WooCommerce product, sends you to checkout with it, and deletes the product
 * again once the payment goes through (or on demand). It exists to prove the
 * real payment gateway → order → processing chain works with a genuine card
 * without touching any listing, job or subscription product.
 *
 * It tests the gateway and WooCommerce's order handling only — it deliberately
 * does NOT run the listing/claim/job order handlers (those key off the real
 * tier products), so it can't publish, transfer or grant anything.
 *
 * Where: Vendor Showcase → Payment Test (needs manage_woocommerce/manage_options).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_payment_test_page' );
add_action( 'admin_post_lis_directory_payment_test_start', 'lis_directory_handle_payment_test_start' );
add_action( 'admin_post_lis_directory_payment_test_delete', 'lis_directory_handle_payment_test_delete' );
add_action( 'lis_directory_payment_test_cleanup', 'lis_directory_payment_test_cleanup' );
add_filter( 'woocommerce_is_purchasable', 'lis_directory_payment_test_restrict', 10, 2 );
add_action( 'woocommerce_order_status_processing', 'lis_directory_payment_test_after_order' );
add_action( 'woocommerce_order_status_completed', 'lis_directory_payment_test_after_order' );

function lis_directory_payment_test_can() {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

function lis_directory_payment_test_product_id() {
	$id = (int) get_option( 'lis_directory_payment_test_product_id' );
	return ( $id && 'product' === get_post_type( $id ) ) ? $id : 0;
}

/**
 * The test product may only ever be bought by an admin, even if its URL leaks.
 */
function lis_directory_payment_test_restrict( $purchasable, $product ) {
	$id = lis_directory_payment_test_product_id();
	if ( $id && is_object( $product ) && (int) $product->get_id() === $id && ! lis_directory_payment_test_can() ) {
		return false;
	}
	return $purchasable;
}

function lis_directory_add_payment_test_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'Payment Test',
		'Payment Test',
		'manage_options',
		'lis_payment_test',
		'lis_directory_render_payment_test_page'
	);
}

function lis_directory_render_payment_test_page() {
	if ( ! lis_directory_payment_test_can() ) {
		return;
	}
	$page      = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_payment_test' );
	$has_wc    = class_exists( 'WooCommerce' );
	$pid       = lis_directory_payment_test_product_id();
	$order_id  = (int) get_option( 'lis_directory_payment_test_last_order' );
	$order     = ( $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
	$flag      = isset( $_GET['lis_pt'] ) ? sanitize_key( wp_unslash( $_GET['lis_pt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notices   = array(
		'deleted'  => 'Test product deleted.',
		'nothing'  => 'There was no test product to delete.',
		'noproduct' => 'Could not create the test product.',
	);
	?>
	<div class="wrap">
		<h1>Payment Test</h1>
		<?php if ( isset( $notices[ $flag ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $flag ] ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $has_wc ) : ?>
			<p>WooCommerce isn't active.</p>
		<?php else : ?>
			<p>Makes a one-time <strong>$1.00</strong> test product that only you can buy, takes you to checkout, and deletes the product again after the payment goes through. Use a real card, then refund the $1 from the order if you want it back.</p>
			<p class="description">This proves your payment gateway and WooCommerce's order processing work. It does <em>not</em> run the listing, claim or job purchase handlers — those need the real products.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
				<input type="hidden" name="action" value="lis_directory_payment_test_start" />
				<?php wp_nonce_field( 'lis_directory_payment_test_start', 'lis_pt_nonce' ); ?>
				<?php submit_button( 'Start $1 test', 'primary', 'submit', false ); ?>
			</form>
			<?php if ( $pid ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="lis_directory_payment_test_delete" />
					<?php wp_nonce_field( 'lis_directory_payment_test_delete', 'lis_pt_nonce' ); ?>
					<?php submit_button( 'Delete test product now', 'secondary', 'submit', false ); ?>
				</form>
				<p>A test product currently exists (#<?php echo (int) $pid; ?>, private).</p>
			<?php else : ?>
				<p>No test product exists right now.</p>
			<?php endif; ?>

			<h2>Last test</h2>
			<?php if ( $order ) : ?>
				<table class="widefat striped" style="max-width:560px;">
					<tr><th>Order</th><td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo (int) $order->get_id(); ?></a></td></tr>
					<tr><th>Status</th><td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td></tr>
					<tr><th>Total</th><td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td></tr>
					<tr><th>Paid with</th><td><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : '—' ); ?></td></tr>
					<tr><th>Transaction</th><td><?php echo esc_html( $order->get_transaction_id() ? $order->get_transaction_id() : '—' ); ?></td></tr>
				</table>
			<?php else : ?>
				<p>No test payment yet.</p>
			<?php endif; ?>
		<?php endif; ?>
		<p style="margin-top:16px;"><a href="<?php echo esc_url( $page ); ?>">Refresh</a></p>
	</div>
	<?php
}

/**
 * The existing test product, or a fresh private $1 one. Private + hidden +
 * tax-free + virtual, so it can't appear in the shop and the total is exactly $1.
 */
function lis_directory_payment_test_get_or_create_product() {
	$id = lis_directory_payment_test_product_id();
	if ( $id ) {
		return $id;
	}
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}
	$product = new WC_Product_Simple();
	$product->set_name( 'LIS Payment Test' );
	$product->set_status( 'private' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );
	$product->set_sold_individually( true );
	$product->set_tax_status( 'none' );
	$product->set_regular_price( '1' );
	$product->update_meta_data( '_lis_payment_test_product', '1' );
	$id = (int) $product->save();
	if ( $id ) {
		update_option( 'lis_directory_payment_test_product_id', $id );
	}
	return $id;
}

function lis_directory_handle_payment_test_start() {
	if ( ! lis_directory_payment_test_can() ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_pt_nonce'] ) || ! wp_verify_nonce( $_POST['lis_pt_nonce'], 'lis_directory_payment_test_start' ) ) {
		wp_die( 'Security check failed.' );
	}
	$back = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_payment_test' );
	$pid  = lis_directory_payment_test_get_or_create_product();
	if ( ! $pid || ! function_exists( 'WC' ) ) {
		wp_safe_redirect( add_query_arg( 'lis_pt', 'noproduct', $back ) );
		exit;
	}

	if ( ! WC()->cart ) {
		wc_load_cart();
	}
	// Drop any earlier test line, leave the rest of the cart alone.
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( (int) $item['product_id'] === $pid ) {
			WC()->cart->remove_cart_item( $key );
		}
	}
	WC()->cart->add_to_cart( $pid, 1 );

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * A test order was paid: remember it and schedule the product's removal a
 * minute out — not inline, because deleting the product while WooCommerce is
 * still finishing the very order (stock, emails) that references it invites
 * warnings.
 */
function lis_directory_payment_test_after_order( $order_id ) {
	$pid   = lis_directory_payment_test_product_id();
	$order = $pid && $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	foreach ( $order->get_items() as $item ) {
		if ( (int) $item->get_product_id() !== $pid ) {
			continue;
		}
		update_option( 'lis_directory_payment_test_last_order', (int) $order_id );
		$order->add_order_note( 'LIS Payment Test: payment went through. The test product is being deleted; refund this order if you don\'t want to keep the $1.' );
		if ( ! wp_next_scheduled( 'lis_directory_payment_test_cleanup' ) ) {
			wp_schedule_single_event( time() + 60, 'lis_directory_payment_test_cleanup' );
		}
		return;
	}
}

/**
 * Delete the test product for good (skips the trash) and forget it. The paid
 * order keeps its own copy of the line item, so it still reads correctly.
 */
function lis_directory_payment_test_cleanup() {
	$pid = lis_directory_payment_test_product_id();
	if ( $pid ) {
		wp_delete_post( $pid, true );
	}
	delete_option( 'lis_directory_payment_test_product_id' );
	return (bool) $pid;
}

function lis_directory_handle_payment_test_delete() {
	if ( ! lis_directory_payment_test_can() ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_pt_nonce'] ) || ! wp_verify_nonce( $_POST['lis_pt_nonce'], 'lis_directory_payment_test_delete' ) ) {
		wp_die( 'Security check failed.' );
	}
	$had = lis_directory_payment_test_cleanup();
	wp_safe_redirect( add_query_arg( 'lis_pt', $had ? 'deleted' : 'nothing', admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_payment_test' ) ) );
	exit;
}
