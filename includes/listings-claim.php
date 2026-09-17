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
 *
 * Also handles a second entry point into the SAME claim mechanism: a private
 * "claim link" (`_lis_listing_claim_token`) an admin generates for a listing
 * that's still a DRAFT — e.g. one built on a business's behalf before they've
 * ever touched the site. A draft has no public permalink, so the "Claim it"
 * box above (which lives on the published single-listing page) can't reach
 * it; the token link is a standalone page instead
 * (lis_directory_maybe_render_claim_token_page(), on template_redirect) that
 * works for a draft. It reuses the exact same tier picker
 * (lis_directory_render_claim_plan_form(), shared with the box above) and the
 * exact same claim-checkout/ownership-transfer path below — the token only
 * changes how the recipient REACHES the picker, not what happens after they
 * submit it. It also grants the recipient edit access to that one listing
 * (see lis_directory_claim_token_grants_edit_access(), consumed by
 * includes/listings-edit.php) so they can fix the draft before buying it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_claim_meta' );
add_action( 'add_meta_boxes', 'lis_directory_add_claim_meta_box' );
add_action( 'save_post_lis_listing', 'lis_directory_save_claim_meta_box' );
add_action( 'admin_post_lis_directory_claim_listing', 'lis_directory_handle_claim_listing' );
add_action( 'admin_post_nopriv_lis_directory_claim_listing', 'lis_directory_handle_claim_listing' );
add_action( 'admin_post_lis_directory_generate_claim_token', 'lis_directory_handle_generate_claim_token' );
add_action( 'template_redirect', 'lis_directory_maybe_render_claim_token_page' );
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
		return; // Already claimed — nothing left to generate/share.
	}

	$token = get_post_meta( $post->ID, '_lis_listing_claim_token', true );
	echo '<hr />';
	echo '<p><strong>Private claim link</strong><br /><span class="description">Works even while this listing is a draft — send it directly to the business so they can review, edit, and buy it. Anyone with the link can open it, so treat it like a password: regenerating replaces the old one.</span></p>';

	if ( $token ) {
		$url = lis_directory_get_claim_token_url( $token );
		echo '<p><input type="text" readonly="readonly" onclick="this.select();" value="' . esc_attr( $url ) . '" style="width:100%;" id="lis-claim-link-input" /></p>';
		echo '<p><button type="button" class="button" onclick="var i=document.getElementById(\'lis-claim-link-input\');i.select();document.execCommand(\'copy\');">Copy link</button></p>';
	}

	$generate_url = wp_nonce_url(
		add_query_arg( array(
			'action'     => 'lis_directory_generate_claim_token',
			'listing_id' => $post->ID,
		), admin_url( 'admin-post.php' ) ),
		'lis_directory_generate_claim_token_' . $post->ID,
		'lis_claim_token_nonce'
	);
	echo '<p><a class="button" href="' . esc_url( $generate_url ) . '">' . ( $token ? 'Regenerate link (invalidates the old one)' : 'Generate claim link' ) . '</a></p>';
	echo '<p class="description">Generating a link automatically marks this listing as claimable, even if you haven\'t saved the checkbox above yet.</p>';
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

/* ---------- Private claim link (works on a draft) ---------- */

/**
 * The shareable URL for a claim token — a plain query arg on the home page,
 * not a rewrite rule (this plugin doesn't register any elsewhere either;
 * lis_directory_maybe_render_claim_token_page() below intercepts it on
 * template_redirect, same hook lis_directory_track_listing_view() in
 * listings-badges.php already uses).
 */
function lis_directory_get_claim_token_url( $token ) {
	return add_query_arg( 'lis_claim_token', $token, home_url( '/' ) );
}

/**
 * The `lis_listing` a claim token belongs to, or null. `post_status =>
 * 'any'` so a token still resolves right after the listing publishes (the
 * recipient's own tab, mid-purchase-flow) — lis_directory_is_listing_claimable()
 * is what actually gates whether it's still usable, checked separately by
 * every caller below.
 */
function lis_directory_get_listing_by_claim_token( $token ) {
	$token = is_string( $token ) ? sanitize_text_field( $token ) : '';
	if ( '' === $token ) {
		return null;
	}
	$ids = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array( 'key' => '_lis_listing_claim_token', 'value' => $token ),
		),
	) );
	return $ids ? get_post( $ids[0] ) : null;
}

/**
 * Whether $token grants the current request edit access to $listing_id —
 * consumed by includes/listings-edit.php so a claim-link recipient can fix
 * up the draft before buying it. True only when the token belongs to THIS
 * exact listing and it's still claimable (not already claimed, not
 * revoked/regenerated) — the same gate that decides whether the token can
 * still be used to buy it, so editing and claiming turn off together.
 */
function lis_directory_claim_token_grants_edit_access( $listing_id, $token ) {
	if ( ! $listing_id || ! $token ) {
		return false;
	}
	$listing = lis_directory_get_listing_by_claim_token( $token );
	if ( ! $listing || (int) $listing->ID !== (int) $listing_id ) {
		return false;
	}
	return lis_directory_is_listing_claimable( $listing_id );
}

/**
 * admin-post handler for the meta box's "Generate/Regenerate claim link"
 * button (a plain nonce-protected GET link, not a nested <form> — the meta
 * box already lives inside the post-edit screen's own <form>, and forms
 * can't nest). Also flips the listing claimable, so generating a link is
 * enough on its own without separately saving the checkbox first.
 */
function lis_directory_handle_generate_claim_token() {
	$listing_id = isset( $_GET['listing_id'] ) ? absint( $_GET['listing_id'] ) : 0;
	if ( ! $listing_id || 'lis_listing' !== get_post_type( $listing_id ) ) {
		wp_die( 'Invalid listing.' );
	}
	if ( ! current_user_can( 'edit_post', $listing_id ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	check_admin_referer( 'lis_directory_generate_claim_token_' . $listing_id, 'lis_claim_token_nonce' );

	update_post_meta( $listing_id, '_lis_listing_claim_token', bin2hex( random_bytes( 20 ) ) );
	update_post_meta( $listing_id, '_lis_listing_claim_token_created', current_time( 'mysql' ) );
	update_post_meta( $listing_id, '_lis_listing_claimable', 1 );

	wp_safe_redirect( admin_url( 'post.php?post=' . $listing_id . '&action=edit' ) );
	exit;
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

	$plan_form = lis_directory_render_claim_plan_form( $listing_id );
	if ( '' === $plan_form ) {
		return ''; // No purchasable plans configured yet — nothing to claim with.
	}
	?>
	<div class="lis-listing-claim-box" id="lis-claim">
		<h3>Is this your business? Claim it.</h3>
		<p>Claim this listing and it becomes yours to manage — a subscription keeps it live.</p>
		<?php echo $plan_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped HTML built by lis_directory_render_claim_plan_form(). ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * The tier/billing picker + "Claim & subscribe" form, shared by the public
 * claim box above (on an already-published listing) and the private
 * draft-claim page (lis_directory_maybe_render_claim_token_page() below) —
 * the token only changes how the recipient reaches this form, not what it
 * does. Returns '' when no tier product is configured/purchasable yet.
 *
 * @param int    $listing_id
 * @param string $token Optional — carried through as a hidden field so
 *                      lis_directory_handle_claim_listing() can send the
 *                      buyer back to the claim-token page (not a draft's
 *                      dead-end permalink) if anything goes wrong.
 */
function lis_directory_render_claim_plan_form( $listing_id, $token = '' ) {
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
		return '';
	}

	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lis-listing-claim-form">
		<input type="hidden" name="action" value="lis_directory_claim_listing" />
		<input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>" />
		<?php if ( $token ) : ?>
			<input type="hidden" name="lis_claim_token" value="<?php echo esc_attr( $token ); ?>" />
		<?php endif; ?>
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
		// The script tag is a SIBLING right after </form> above, not a
		// descendant of it, so .closest('form') on the script itself would
		// never match — walk back to the previous element instead.
		var form = document.currentScript.previousElementSibling;
		if ( ! form || 'FORM' !== form.tagName ) { return; }
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
	<?php
	return ob_get_clean();
}

/* ---------- Claim → checkout ---------- */

function lis_directory_handle_claim_listing() {
	$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$token      = isset( $_POST['lis_claim_token'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_claim_token'] ) ) : '';
	// A draft's own permalink is a dead end for anyone but its author — send
	// a token-carrying submission back to the claim page instead.
	$back       = $token ? lis_directory_get_claim_token_url( $token ) : ( $listing_id ? get_permalink( $listing_id ) : home_url( '/' ) );

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
		delete_post_meta( $listing_id, '_lis_listing_claim_token' ); // Retire the private link, if this claim came through one.
		update_post_meta( $listing_id, '_lis_listing_claimed_by', $buyer );
		update_post_meta( $listing_id, '_lis_listing_claimed_at', current_time( 'mysql' ) );
	}
}

/* ---------- The standalone private claim-link page ---------- */

/**
 * Intercepts `?lis_claim_token=…` on ANY front-end request (a plain query
 * arg, not a rewrite rule — matches lis_directory_track_listing_view()'s
 * use of the same `template_redirect` hook in listings-badges.php) and
 * renders a standalone "review, edit, and claim" page for that token's
 * listing — the one entry point that can reach a DRAFT listing, since a
 * draft has no public permalink for the usual claim box
 * (lis_directory_render_claim_box()) to live on.
 *
 * Deliberately hand-builds the page (get_header()/get_footer() around plain
 * echoed HTML) rather than forcing the draft through the normal
 * single-listing template/main-query — same "hand-roll the output" approach
 * the claim box and the WooCommerce thank-you page already use elsewhere in
 * this plugin, and it sidesteps fighting `WP_Query`'s own publish-status
 * filtering for a post that's deliberately allowed through here via the
 * token rather than the normal capability check.
 */
function lis_directory_maybe_render_claim_token_page() {
	if ( empty( $_GET['lis_claim_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route lookup; nothing is written from a GET here.
		return;
	}

	$token   = sanitize_text_field( wp_unslash( $_GET['lis_claim_token'] ) );
	$listing = lis_directory_get_listing_by_claim_token( $token );

	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	nocache_headers();
	// A leaked/crawled token URL should never end up indexed.
	header( 'X-Robots-Tag: noindex, nofollow', true );

	get_header();
	echo '<div class="lis-listing-claim-page">';

	if ( ! $listing ) {
		// Never distinguishes "no such token" from "already used" below —
		// both just say the link doesn't work anymore.
		echo '<p class="lis-listing-claim-page-invalid">This link is no longer valid.</p>';
	} elseif ( ! lis_directory_is_listing_claimable( $listing->ID ) ) {
		$claimed_by = (int) get_post_meta( $listing->ID, '_lis_listing_claimed_by', true );
		if ( $claimed_by && 'publish' === get_post_status( $listing->ID ) ) {
			echo '<p>This listing has already been claimed. <a href="' . esc_url( get_permalink( $listing->ID ) ) . '">View it &rarr;</a></p>';
		} else {
			echo '<p class="lis-listing-claim-page-invalid">This link is no longer valid.</p>';
		}
	} elseif ( ! is_user_logged_in() ) {
		$redirect_back = lis_directory_get_claim_token_url( $token );
		echo '<h1>A listing has been started for you</h1>';
		echo '<p>Log in to review, edit, and claim <strong>' . esc_html( get_the_title( $listing->ID ) ) . '</strong>.</p>';
		echo '<p><a class="lis-listing-claim-btn" href="' . esc_url( wp_login_url( $redirect_back ) ) . '">Log in</a></p>';
	} else {
		echo '<h1>Review and claim ' . esc_html( get_the_title( $listing->ID ) ) . '</h1>';
		echo '<p>Take a look below, fix anything that needs it, then choose a plan to make it yours.</p>';
		if ( function_exists( 'lis_directory_render_listing_edit_form' ) ) {
			echo lis_directory_render_listing_edit_form( $listing->ID, $token ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped HTML.
		}
		$plan_form = lis_directory_render_claim_plan_form( $listing->ID, $token );
		if ( $plan_form ) {
			echo '<hr class="lis-listing-claim-page-divider" />';
			echo '<h2>Choose a plan</h2>';
			echo $plan_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped HTML.
		}
	}

	echo '</div>';
	get_footer();
	exit;
}
