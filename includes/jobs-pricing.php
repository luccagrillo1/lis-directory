<?php
/**
 * Job board settings + payment. A job posting is a one-time WooCommerce
 * product ("Job Posting") that buys a posting window (default 30 days); paying
 * publishes the job and starts/extends its expiry, and the same product renews
 * an expired one. Completely separate from the business-directory tiers
 * (includes/listings-pricing.php): different product, different cart/order
 * meta (`lis_job_id` / `_lis_job_id`, never `lis_listing_id`), different hooks.
 *
 * As elsewhere in this plugin, the product itself is set up in WooCommerce —
 * this file only generates a draft one to edit and reacts to completed orders.
 * Until it's published and purchasable, new jobs fall back to admin moderation
 * ("pending") instead of checkout, so the board still works without payments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_job_settings_page' );
add_action( 'init', 'lis_directory_register_job_settings' );
add_action( 'plugins_loaded', 'lis_directory_init_job_woocommerce' );
add_action( 'admin_post_lis_directory_generate_job_product', 'lis_directory_handle_generate_job_product' );
add_action( 'admin_post_lis_directory_create_job_pages', 'lis_directory_handle_create_job_pages' );

function lis_directory_register_job_settings() {
	foreach ( array( 'lis_directory_job_product_id', 'lis_directory_job_duration_days', 'lis_directory_job_submit_page_id', 'lis_directory_job_dashboard_page_id' ) as $option ) {
		register_setting( 'lis_job_settings_group', $option, array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );
	}
}

function lis_directory_init_job_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_filter( 'woocommerce_add_to_cart_validation', 'lis_directory_validate_job_add_to_cart', 10, 2 );
	add_filter( 'woocommerce_add_cart_item_data', 'lis_directory_add_job_id_to_cart_item', 10, 2 );
	add_filter( 'woocommerce_get_item_data', 'lis_directory_show_job_in_cart_item_data', 10, 2 );
	add_action( 'woocommerce_checkout_create_order_line_item', 'lis_directory_persist_job_id_to_order_item', 10, 4 );
	add_action( 'woocommerce_order_status_completed', 'lis_directory_handle_job_order' );
	add_action( 'woocommerce_order_status_processing', 'lis_directory_handle_job_order' );
	add_action( 'woocommerce_thankyou', 'lis_directory_job_order_thankyou' );
}

function lis_directory_get_job_product_id() {
	return (int) get_option( 'lis_directory_job_product_id' );
}

/**
 * The URL of the configured "Post a job" / "My jobs" page, or '' if not set up
 * yet (Jobs → Job Settings → Create pages).
 */
function lis_directory_get_job_page_url( $which ) {
	$page_id = (int) get_option( 'submit' === $which ? 'lis_directory_job_submit_page_id' : 'lis_directory_job_dashboard_page_id' );
	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return '';
	}
	return get_permalink( $page_id );
}

/**
 * Checkout URL that buys a posting for $job_id, or '' when the product isn't
 * configured / published / purchasable (callers then use the moderation
 * fallback, or hide the pay button).
 */
function lis_directory_get_job_checkout_url( $job_id ) {
	$product_id = lis_directory_get_job_product_id();
	if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return '';
	}
	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
		return '';
	}
	return add_query_arg( array(
		'add-to-cart' => $product_id,
		'lis_job_id'  => (int) $job_id,
	), wc_get_checkout_url() );
}

/**
 * "$25" for the current posting price, or '' when there's no purchasable product.
 */
function lis_directory_get_job_price_text() {
	$product = lis_directory_get_job_product_id() && function_exists( 'wc_get_product' ) ? wc_get_product( lis_directory_get_job_product_id() ) : null;
	if ( ! $product || 'publish' !== $product->get_status() ) {
		return '';
	}
	return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $product->get_price() ) ) : '$' . $product->get_price();
}

/* ---------- Cart → order ---------- */

function lis_directory_validate_job_add_to_cart( $passed, $product_id ) {
	$job_product = lis_directory_get_job_product_id();
	if ( ! $job_product || (int) $product_id !== $job_product ) {
		return $passed;
	}
	$job_id = isset( $_REQUEST['lis_job_id'] ) ? absint( $_REQUEST['lis_job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ownership is checked right here.
	if ( ! $job_id || ! function_exists( 'lis_directory_user_can_manage_job' ) || ! lis_directory_user_can_manage_job( $job_id ) ) {
		wc_add_notice( 'A job posting has to be bought for one of your own job posts. Start from "Post a job" or "My jobs".', 'error' );
		return false;
	}
	return $passed;
}

function lis_directory_add_job_id_to_cart_item( $cart_item_data, $product_id ) {
	if ( (int) $product_id !== lis_directory_get_job_product_id() ) {
		return $cart_item_data;
	}
	$job_id = isset( $_REQUEST['lis_job_id'] ) ? absint( $_REQUEST['lis_job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $job_id && 'lis_job' === get_post_type( $job_id ) ) {
		$cart_item_data['lis_job_id'] = $job_id;
	}
	return $cart_item_data;
}

function lis_directory_show_job_in_cart_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['lis_job_id'] ) ) {
		$item_data[] = array( 'name' => 'Job', 'value' => get_the_title( $cart_item['lis_job_id'] ) );
	}
	return $item_data;
}

function lis_directory_persist_job_id_to_order_item( $item, $cart_item_key, $values, $order ) {
	if ( ! empty( $values['lis_job_id'] ) ) {
		$item->add_meta_data( '_lis_job_id', (int) $values['lis_job_id'], true );
	}
}

/**
 * On payment: publish the job and start (or extend) its posting window. Runs on
 * both `processing` and `completed` (many gateways never reach `completed` for
 * virtual products), so each order item is stamped `_lis_job_applied` and only
 * ever applied once — a renewal must not be double-counted.
 */
function lis_directory_handle_job_order( $order_id ) {
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$product_id = lis_directory_get_job_product_id();

	foreach ( $order->get_items() as $item ) {
		$job_id = (int) $item->get_meta( '_lis_job_id' );
		if ( ! $job_id || 'lis_job' !== get_post_type( $job_id ) || (int) $item->get_product_id() !== $product_id ) {
			continue;
		}
		if ( $item->get_meta( '_lis_job_applied' ) ) {
			continue;
		}

		$days = lis_directory_get_job_duration_days() * max( 1, (int) $item->get_quantity() );
		if ( $days > 0 ) {
			$now    = current_time( 'timestamp' );
			$expiry = (string) get_post_meta( $job_id, '_lis_job_expiry', true );
			$base   = ( '' !== $expiry && strtotime( $expiry ) > $now && 'publish' === get_post_status( $job_id ) ) ? strtotime( $expiry ) : $now;
			update_post_meta( $job_id, '_lis_job_expiry', gmdate( 'Y-m-d H:i:s', $base + $days * DAY_IN_SECONDS ) );
		} else {
			delete_post_meta( $job_id, '_lis_job_expiry' );
		}
		delete_post_meta( $job_id, '_lis_job_expired' );
		delete_post_meta( $job_id, '_lis_job_awaiting_payment' );
		update_post_meta( $job_id, '_lis_job_paid_order_id', $order_id );

		if ( 'publish' !== get_post_status( $job_id ) ) {
			wp_update_post( array( 'ID' => $job_id, 'post_status' => 'publish' ) );
		}

		$item->update_meta_data( '_lis_job_applied', 1 );
		$item->save();
	}
}

function lis_directory_job_order_thankyou( $order_id ) {
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$job_id = 0;
	foreach ( $order->get_items() as $item ) {
		$candidate = (int) $item->get_meta( '_lis_job_id' );
		if ( $candidate && 'lis_job' === get_post_type( $candidate ) ) {
			$job_id = $candidate;
			break;
		}
	}
	if ( ! $job_id ) {
		return;
	}
	wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );
	$live = ( 'publish' === get_post_status( $job_id ) );
	$dash = lis_directory_get_job_page_url( 'dashboard' );
	?>
	<div class="lis-job-notice lis-job-notice--success">
		<strong>Thanks — your job posting is paid for.</strong>
		<?php if ( $live ) : ?>
			<a href="<?php echo esc_url( get_permalink( $job_id ) ); ?>"><?php echo esc_html( get_the_title( $job_id ) ); ?></a> is now live.
		<?php else : ?>
			<?php echo esc_html( get_the_title( $job_id ) ); ?> will go live in a moment, once the order finishes processing.
		<?php endif; ?>
		<?php if ( $dash ) : ?><a href="<?php echo esc_url( $dash ); ?>">Manage your jobs &rarr;</a><?php endif; ?>
	</div>
	<?php
}

/* ---------- Settings page ---------- */

function lis_directory_add_job_settings_page() {
	add_submenu_page( 'edit.php?post_type=lis_job', 'Job Settings', 'Job Settings', 'manage_options', 'lis_job_settings', 'lis_directory_render_job_settings_page' );
}

function lis_directory_render_job_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$product_id = lis_directory_get_job_product_id();
	$product    = ( $product_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;
	$flag       = isset( $_GET['lis_job_notice'] ) ? sanitize_key( wp_unslash( $_GET['lis_job_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notices    = array(
		'product_ok'    => 'Job Posting product created as a draft. Set its price in WooCommerce, then publish it.',
		'product_exists' => 'A Job Posting product already exists — pointed the setting at it.',
		'product_error' => 'Could not create the product (is WooCommerce active?).',
		'pages_ok'      => 'Job pages are set up.',
	);
	?>
	<div class="wrap">
		<h1>Job Settings</h1>
		<?php if ( isset( $notices[ $flag ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $flag ] ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'lis_job_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="lis_directory_job_product_id">Job Posting product ID</label></th>
					<td>
						<input type="number" id="lis_directory_job_product_id" name="lis_directory_job_product_id" value="<?php echo esc_attr( $product_id ); ?>" class="small-text" />
						<?php if ( $product ) : ?>
							<span class="description"><?php echo esc_html( $product->get_name() . ' — ' . $product->get_status() ); ?>
							<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>">edit</a></span>
						<?php endif; ?>
						<p class="description">A one-time WooCommerce product. While it's missing or unpublished, new jobs wait for admin approval instead of checkout.</p>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_job_duration_days">Posting length (days)</label></th>
					<td>
						<input type="number" min="0" id="lis_directory_job_duration_days" name="lis_directory_job_duration_days" value="<?php echo esc_attr( lis_directory_get_job_duration_days() ); ?>" class="small-text" />
						<p class="description">How long a posting stays live from payment/approval. 0 = never expires.</p>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_job_submit_page_id">"Post a job" page ID</label></th>
					<td><input type="number" id="lis_directory_job_submit_page_id" name="lis_directory_job_submit_page_id" value="<?php echo esc_attr( (int) get_option( 'lis_directory_job_submit_page_id' ) ); ?>" class="small-text" /> <span class="description">Page containing <code>[lis_job_submit]</code>.</span></td>
				</tr>
				<tr>
					<th><label for="lis_directory_job_dashboard_page_id">"My jobs" page ID</label></th>
					<td><input type="number" id="lis_directory_job_dashboard_page_id" name="lis_directory_job_dashboard_page_id" value="<?php echo esc_attr( (int) get_option( 'lis_directory_job_dashboard_page_id' ) ); ?>" class="small-text" /> <span class="description">Page containing <code>[lis_job_dashboard]</code>.</span></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />
		<h2>Quick setup</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
			<input type="hidden" name="action" value="lis_directory_generate_job_product" />
			<?php wp_nonce_field( 'lis_directory_generate_job_product', 'lis_job_gen_nonce' ); ?>
			<?php submit_button( 'Create Job Posting product', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<input type="hidden" name="action" value="lis_directory_create_job_pages" />
			<?php wp_nonce_field( 'lis_directory_create_job_pages', 'lis_job_pages_nonce' ); ?>
			<?php submit_button( 'Create "Post a job" and "My jobs" pages', 'secondary', 'submit', false ); ?>
		</form>

		<h2>Shortcodes</h2>
		<p><code>[lis_job_submit]</code> post/edit form &middot; <code>[lis_job_dashboard]</code> employer dashboard &middot; <code>[lis_job_board]</code> board on any page (<code>count</code>, <code>category</code>, <code>type</code>, <code>search="no"</code>). The public board lives at <a href="<?php echo esc_url( get_post_type_archive_link( 'lis_job' ) ); ?>"><?php echo esc_html( get_post_type_archive_link( 'lis_job' ) ); ?></a>.</p>
	</div>
	<?php
}

function lis_directory_handle_generate_job_product() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_job_gen_nonce'] ) || ! wp_verify_nonce( $_POST['lis_job_gen_nonce'], 'lis_directory_generate_job_product' ) ) {
		wp_die( 'Security check failed.' );
	}
	$redirect = admin_url( 'edit.php?post_type=lis_job&page=lis_job_settings' );

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		wp_safe_redirect( add_query_arg( 'lis_job_notice', 'product_error', $redirect ) );
		exit;
	}

	$existing = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_lis_job_product', 'value' => '1' ) ),
	) );
	if ( $existing ) {
		update_option( 'lis_directory_job_product_id', (int) $existing[0] );
		wp_safe_redirect( add_query_arg( 'lis_job_notice', 'product_exists', $redirect ) );
		exit;
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'Job Posting' );
	$product->set_status( 'draft' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );
	$product->set_regular_price( '25' );
	$product->update_meta_data( '_lis_job_product', '1' );
	$new_id = $product->save();

	if ( $new_id ) {
		update_option( 'lis_directory_job_product_id', (int) $new_id );
	}
	wp_safe_redirect( add_query_arg( 'lis_job_notice', $new_id ? 'product_ok' : 'product_error', $redirect ) );
	exit;
}

function lis_directory_handle_create_job_pages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_job_pages_nonce'] ) || ! wp_verify_nonce( $_POST['lis_job_pages_nonce'], 'lis_directory_create_job_pages' ) ) {
		wp_die( 'Security check failed.' );
	}
	$pages = array(
		'lis_directory_job_submit_page_id'    => array( 'Post a Job Opening', 'post-job-opening', '[lis_job_submit]' ),
		'lis_directory_job_dashboard_page_id' => array( 'My Job Postings', 'my-job-postings', '[lis_job_dashboard]' ),
	);
	foreach ( $pages as $option => $page ) {
		$current = (int) get_option( $option );
		if ( $current && 'publish' === get_post_status( $current ) ) {
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $page[0],
			'post_name'    => $page[1],
			'post_content' => $page[2],
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( $option, (int) $id );
		}
	}
	wp_safe_redirect( add_query_arg( 'lis_job_notice', 'pages_ok', admin_url( 'edit.php?post_type=lis_job&page=lis_job_settings' ) ) );
	exit;
}
