<?php
/**
 * Claim-by-subscription for `lis_listing`.
 *
 * A listing flagged claimable (`_lis_listing_claimable`, set by an admin — e.g.
 * for listings we created on a business's behalf) shows a "Claim it" box on its
 * public page. A logged-in visitor picks a plan and checks out; when the order
 * is paid, ownership transfers to them (post_author) and the tier is granted —
 * so claiming is always tied to an active subscription, never free.
 *
 * The heavy lifting (add-to-cart tag → order line item → publish/grant tier on
 * payment) reuses the same plumbing as the submission wizard's paid tiers
 * (includes/listings-pricing.php); this file only adds the claimable flag, the
 * claim UI, the "this is a claim" order tag, and the ownership transfer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_claim_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_claim_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_claim_meta_box' );
add_action( 'admin_post_lis_directory_claim_listing', 'lis_directory_handle_claim_listing' );
add_action( 'admin_post_nopriv_lis_directory_claim_listing', 'lis_directory_handle_claim_listing' );
add_filter( 'woocommerce_add_cart_item_data', 'lis_directory_add_claim_to_cart_item', 20, 2 );
add_action( 'woocommerce_checkout_create_order_line_item', 'lis_directory_persist_claim_to_order_item', 20, 4 );
add_action( 'woocommerce_order_status_completed', 'lis_directory_handle_claim_order' );
add_action( 'woocommerce_order_status_processing', 'lis_directory_handle_claim_order' );

function lis_directory_register_claim_meta() {
	register_post_meta( 'lis_listing', '_lis_listing_claimable', array(
		'type'         => 'boolean',
		'single'       => true,
		'show_in_rest' => false,
	) );
}

function lis_directory_is_listing_claimable( $listing_id ) {
	return (bool) get_post_meta( $listing_id, '_lis_listing_claimable', true )
		&& ! get_post_meta( $listing_id, '_lis_listing_claimed_by', true );
}

/* ---------- Admin: mark a listing claimable ---------- */

function lis_directory_add_claim_meta_box() {
	add_meta_box( 'lis_listing_claim', 'Claim', 'lis_directory_render_claim_meta_box', 'lis_listing', 'side', 'default' );
}

function lis_directory_render_claim_meta_box( $post ) {
	wp_nonce_field( 'lis_directory_claim_meta', 'lis_directory_claim_meta_nonce' );
	$claimable  = (bool) get_post_meta( $post->ID, '_lis_listing_claimable', true );
	$claimed_by = (int) get_post_meta( $post->ID, '_lis_listing_claimed_by', true );
	echo '<p><label><input type="checkbox" name="lis_listing_claimable" value="1" ' . checked( $claimable, true, false ) . ' /> Allow this listing to be claimed by subscription</label></p>';
	if ( $claimed_by ) {
		$u = get_userdata( $claimed_by );
		echo '<p><em>Claimed by ' . esc_html( $u ? $u->user_login : ( '#' . $claimed_by ) ) . '.</em></p>';
	}
}

function lis_directory_save_claim_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_directory_claim_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lis_directory_claim_meta_nonce'], 'lis_directory_claim_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_lis_listing_claimable', ! empty( $_POST['lis_listing_claimable'] ) ? 1 : 0 );
}

/* ---------- Front end: the claim box ---------- */

/**
 * The "Is this your business? Claim it." box for the single-listing template.
 * Renders nothing unless the listing is claimable and the viewer isn't already
 * its owner. Not logged in → a log-in prompt; logged in → a plan picker that
 * checks out (and transfers ownership on payment).
 */
function lis_directory_render_claim_box( $listing_id ) {
	if ( ! lis_directory_is_listing_claimable( $listing_id ) ) {
		return '';
	}
	$current = get_current_user_id();
	if ( $current && (int) get_post_field( 'post_author', $listing_id ) === $current ) {
		return '';
	}

	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	// Default: just a compact button on the listing. It opens the full plan
	// block only on the focused claim view (`?claim=1`), so the listing page
	// itself stays clean.
	$is_claim_view = ! empty( $_GET['claim'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only view switch.
	if ( ! $is_claim_view ) {
		$claim_url = add_query_arg( 'claim', '1', get_permalink( $listing_id ) ) . '#lis-claim';
		return '<div class="lis-listing-claim-cta"><a class="lis-listing-claim-btn" href="' . esc_url( $claim_url ) . '">Is this your business? Claim it &rarr;</a></div>';
	}

	ob_start();

	if ( ! is_user_logged_in() ) {
		?>
		<div class="lis-listing-claim-box" id="lis-claim">
			<h3>Is this your business?</h3>
			<p><a href="<?php echo esc_url( wp_login_url( add_query_arg( 'claim', '1', get_permalink( $listing_id ) ) ) ); ?>">Log in</a> to claim this listing.</p>
		</div>
		<?php
		return ob_get_clean();
	}

	$fmt = function ( $price ) {
		if ( null === $price || '' === $price ) {
			return '—';
		}
		return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price ) ) : ( '$' . $price );
	};

	$tiers = array();
	$std   = function_exists( 'lis_directory_get_standard_listing_product_id' ) ? lis_directory_get_standard_listing_product_id() : 0;
	$feat  = (int) get_option( 'lis_directory_featured_listing_product_id' );
	if ( $std ) {
		$tiers['standard'] = array( 'label' => 'Standard', 'pid' => $std, 'blurb' => 'Claim and keep it listed.' );
	}
	if ( $feat ) {
		$tiers['featured'] = array( 'label' => 'Featured', 'pid' => $feat, 'blurb' => 'Claim it with a Featured badge.' );
	}
	if ( empty( $tiers ) ) {
		return ''; // No purchasable plans configured yet — nothing to claim with.
	}
	?>
	<div class="lis-listing-claim-box" id="lis-claim">
		<h3>Is this your business? Claim it.</h3>
		<p>Claim this listing and it becomes yours to manage — a subscription keeps it live.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lis-listing-claim-form">
			<input type="hidden" name="action" value="lis_directory_claim_listing" />
			<input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>" />
			<?php wp_nonce_field( 'lis_directory_claim_listing_' . (int) $listing_id, 'lis_claim_nonce' ); ?>

			<div class="lis-listing-plan-billing" role="radiogroup" aria-label="Billing">
				<label><input type="radio" name="lis_listing_billing" value="month" checked /> Monthly</label>
				<label><input type="radio" name="lis_listing_billing" value="year" /> Annually <span class="lis-listing-plan-save">save ~2 months</span></label>
			</div>
			<div class="lis-listing-plans">
				<?php foreach ( $tiers as $key => $t ) :
					$m = lis_directory_resolve_plan_variation( $t['pid'], 'month' );
					$y = lis_directory_resolve_plan_variation( $t['pid'], 'year' );
					?>
					<label class="lis-listing-plan-card">
						<input type="radio" name="lis_listing_plan" value="<?php echo esc_attr( $key ); ?>" required />
						<span class="lis-listing-plan-name"><?php echo esc_html( $t['label'] ); ?></span>
						<span class="lis-listing-plan-price" data-price-month="<?php echo esc_attr( $fmt( $m['price'] ) . '/mo' ); ?>" data-price-year="<?php echo esc_attr( $fmt( $y['price'] ) . '/yr' ); ?>"><?php echo esc_html( $fmt( $m['price'] ) ); ?>/mo</span>
						<span class="lis-listing-plan-blurb"><?php echo esc_html( $t['blurb'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p><button type="submit" class="lis-listing-claim-submit">Claim &amp; subscribe</button></p>
		</form>
		<script>
		( function () {
			var form = document.currentScript.closest( 'form' );
			if ( ! form ) { return; }
			function sync() {
				var billing = ( form.querySelector( 'input[name="lis_listing_billing"]:checked' ) || {} ).value || 'month';
				form.querySelectorAll( '.lis-listing-plan-price' ).forEach( function ( el ) {
					el.textContent = 'year' === billing ? el.dataset.priceYear : el.dataset.priceMonth;
				} );
			}
			form.querySelectorAll( 'input[name="lis_listing_billing"]' ).forEach( function ( r ) {
				r.addEventListener( 'change', sync );
			} );
			sync();
		}() );
		</script>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------- Claim → checkout ---------- */

function lis_directory_handle_claim_listing() {
	$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$back       = $listing_id ? get_permalink( $listing_id ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $back ) );
		exit;
	}
	if ( ! $listing_id || ! isset( $_POST['lis_claim_nonce'] ) || ! wp_verify_nonce( $_POST['lis_claim_nonce'], 'lis_directory_claim_listing_' . $listing_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}
	if ( ! lis_directory_is_listing_claimable( $listing_id ) ) {
		wp_safe_redirect( add_query_arg( 'lis_claim', 'unavailable', $back ) );
		exit;
	}

	$plan    = isset( $_POST['lis_listing_plan'] ) ? sanitize_key( wp_unslash( $_POST['lis_listing_plan'] ) ) : '';
	$billing = ( isset( $_POST['lis_listing_billing'] ) && 'year' === $_POST['lis_listing_billing'] ) ? 'year' : 'month';

	$url = function_exists( 'lis_directory_build_listing_checkout_url' ) ? lis_directory_build_listing_checkout_url( $plan, $billing, $listing_id ) : '';
	if ( ! $url ) {
		wp_safe_redirect( add_query_arg( 'lis_claim', 'noplan', $back ) );
		exit;
	}
	// Tag the checkout as a claim so the order-complete hook transfers ownership.
	$url = add_query_arg( 'lis_listing_claim', '1', $url );
	wp_safe_redirect( $url );
	exit;
}

/* ---------- Carry the claim flag cart → order ---------- */

function lis_directory_add_claim_to_cart_item( $cart_item_data, $product_id ) {
	if ( ! empty( $_REQUEST['lis_listing_claim'] ) && function_exists( 'lis_directory_listing_tier_for_product' ) && lis_directory_listing_tier_for_product( (int) $product_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tag, ownership transfer re-checks the listing at order-completion.
		$cart_item_data['lis_listing_claim'] = 1;
	}
	return $cart_item_data;
}

function lis_directory_persist_claim_to_order_item( $item, $cart_item_key, $values, $order ) {
	if ( ! empty( $values['lis_listing_claim'] ) ) {
		$item->add_meta_data( '_lis_listing_claim', 1, true );
	}
}

/**
 * On payment, transfer ownership of a claimed listing to the buyer. Runs
 * alongside the pricing handler (which publishes + grants the tier); this only
 * reassigns the author and records the claim.
 */
function lis_directory_handle_claim_order( $order_id ) {
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$buyer = (int) $order->get_customer_id();
	if ( ! $buyer ) {
		return;
	}
	foreach ( $order->get_items() as $item ) {
		if ( ! $item->get_meta( '_lis_listing_claim' ) ) {
			continue;
		}
		$listing_id = (int) $item->get_meta( '_lis_listing_id' );
		if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
			continue;
		}
		if ( (int) get_post_meta( $listing_id, '_lis_listing_claimed_by', true ) ) {
			continue; // already claimed
		}
		wp_update_post( array( 'ID' => $listing_id, 'post_author' => $buyer ) );
		delete_post_meta( $listing_id, '_lis_listing_claimable' );
		update_post_meta( $listing_id, '_lis_listing_claimed_by', $buyer );
		update_post_meta( $listing_id, '_lis_listing_claimed_at', current_time( 'mysql' ) );
	}
}
