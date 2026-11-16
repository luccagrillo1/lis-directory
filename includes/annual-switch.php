<?php
/**
 * Vendor Showcase → Switch to Annual: moves existing monthly Standard / Featured
 * subscriptions onto the annual price, one click per customer, after the site
 * owner has asked them.
 *
 * Standard and Featured are sold annually only from 0.52.0
 * (lis_directory_plan_billing()), but subscriptions bought before that keep
 * renewing monthly until someone switches them. This page lists them and does
 * the switch in place on the existing WooCommerce subscription:
 *   - the line item moves to the product's annual variation and price,
 *   - the subscription's billing period becomes 1 year,
 *   - taxes and totals are recalculated,
 *   - the next payment date is NOT changed, so nothing is charged at switch
 *     time: the customer keeps the month already paid for, then their saved
 *     payment method is charged the annual price on that date and yearly after.
 * Every switch adds a subscription note naming who did it and can be undone
 * from the same page.
 *
 * Refuses (with a plain reason) when the subscription has a coupon, isn't
 * active/on-hold, or its payment method can't change amount/schedule (e.g.
 * PayPal Standard). Those are left for a person in WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_annual_switch_page' );
add_action( 'admin_post_lis_directory_annual_switch', 'lis_directory_handle_annual_switch' );
add_action( 'woocommerce_checkout_subscription_created', 'lis_directory_mark_trial_subscription', 10, 2 );
add_action( 'woocommerce_subscription_renewal_payment_complete', 'lis_directory_maybe_convert_trial_to_annual', 10, 2 );

function lis_directory_add_annual_switch_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'Switch to Annual',
		'Switch to Annual',
		'manage_options',
		'lis_annual_switch',
		'lis_directory_render_annual_switch_page'
	);
}

/**
 * Tier product ids that are sold annual-only, keyed by tier.
 *
 * @return int[]
 */
function lis_directory_annual_only_products() {
	$out = array();
	foreach ( array( 'standard', 'featured' ) as $tier ) {
		$pid = 'standard' === $tier
			? ( function_exists( 'lis_directory_get_standard_listing_product_id' ) ? lis_directory_get_standard_listing_product_id() : 0 )
			: (int) get_option( 'lis_directory_featured_listing_product_id' );
		if ( $pid ) {
			$out[ $tier ] = (int) $pid;
		}
	}
	return $out;
}

/**
 * The subscription's line item for one of our tier products, or null.
 */
function lis_directory_annual_switch_item( $sub, $product_id ) {
	foreach ( $sub->get_items() as $item ) {
		if ( is_a( $item, 'WC_Order_Item_Product' ) && (int) $item->get_product_id() === (int) $product_id ) {
			return $item;
		}
	}
	return null;
}

/**
 * Switched on this page and not yet renewed at the annual price.
 */
function lis_directory_annual_switch_can_undo( $sub ) {
	$at = (int) $sub->get_meta( '_lis_switched_to_annual' );
	return $at && (int) $sub->get_time( 'last_order_date_created' ) < $at;
}

/**
 * Why this subscription can't be switched automatically, or '' if it can.
 */
function lis_directory_annual_switch_blocker( $sub ) {
	if ( ! $sub->has_status( array( 'active', 'on-hold' ) ) ) {
		return 'Subscription is ' . $sub->get_status() . '.';
	}
	if ( count( $sub->get_coupon_codes() ) ) {
		return 'Has a coupon — change it by hand in WooCommerce.';
	}
	if ( ! $sub->is_manual() ) {
		foreach ( array( 'subscription_amount_changes', 'subscription_date_changes' ) as $feature ) {
			if ( ! $sub->payment_method_supports( $feature ) ) {
				return 'Their payment method (' . $sub->get_payment_method_title() . ') can\'t change the amount. They\'d need to re-subscribe annually.';
			}
		}
	}
	return '';
}

/**
 * One row per subscription that could be switched (or was switched here).
 *
 * @param string $period 'month' = still monthly (not on a 3-month start),
 *                       'trial' = on a 3-month start, switches itself,
 *                       'year'  = switched by this page (or a 3-month start), undoable.
 * @return array[]
 */
function lis_directory_annual_switch_rows( $period = 'month' ) {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return array();
	}
	$rows = array();
	foreach ( lis_directory_annual_only_products() as $tier => $pid ) {
		$month = lis_directory_resolve_plan_variation( $pid, 'month' );
		$year  = lis_directory_resolve_plan_variation( $pid, 'year' );
		$subs  = wcs_get_subscriptions( array(
			'subscriptions_per_page' => -1,
			'subscription_status'    => array( 'active', 'on-hold' ),
			'product_id'             => $pid,
		) );
		foreach ( (array) $subs as $sub ) {
			$want = 'trial' === $period ? 'month' : $period;
			if ( $want !== $sub->get_billing_period() || (int) $sub->get_billing_interval() !== 1 ) {
				continue;
			}
			if ( 'year' !== $period && ( 'trial' === $period ) !== lis_directory_is_pending_trial( $sub ) ) {
				continue; // 3-month starts get their own list.
			}
			if ( 'year' === $period && ! lis_directory_annual_switch_can_undo( $sub ) ) {
				continue; // Only annual ones this page switched, before their first annual charge.
			}
			$item = lis_directory_annual_switch_item( $sub, $pid );
			if ( ! $item ) {
				continue;
			}
			$listing_id = (int) $item->get_meta( '_lis_listing_id' );
			$next       = $sub->get_time( 'next_payment' );
			$rows[]     = array(
				'sub'        => $sub,
				'tier'       => $tier,
				'listing_id' => $listing_id,
				'business'   => $listing_id ? get_the_title( $listing_id ) : '',
				'name'       => trim( $sub->get_billing_first_name() . ' ' . $sub->get_billing_last_name() ),
				'email'      => $sub->get_billing_email(),
				'phone'      => $sub->get_billing_phone(),
				'total'      => (float) $sub->get_total(),
				'month'      => null !== $month['price'] ? (float) $month['price'] : null,
				'year'       => null !== $year['price'] ? (float) $year['price'] : null,
				'next'       => $next ? (int) $next : 0,
				'paid'       => (int) $sub->get_payment_count(),
				'blocker'    => 'month' === $period
					? ( $year['variation_id'] ? lis_directory_annual_switch_blocker( $sub ) : 'No annual price set on this product.' )
					: '',
			);
		}
	}
	usort( $rows, function ( $a, $b ) {
		return $a['next'] <=> $b['next'];
	} );
	return $rows;
}

/**
 * Move a subscription between the monthly and annual variation of its tier.
 * Keeps the next payment date, so nothing is charged now.
 *
 * @param int    $sub_id Subscription id.
 * @param string $to     'year' or 'month' (undo).
 * @return true|string True, or a plain-language reason it wasn't changed.
 */
function lis_directory_switch_subscription_billing( $sub_id, $to, $why = '' ) {
	if ( ! function_exists( 'wcs_get_subscription' ) ) {
		return 'WooCommerce Subscriptions is not active.';
	}
	$to  = 'month' === $to ? 'month' : 'year';
	$sub = wcs_get_subscription( (int) $sub_id );
	if ( ! $sub ) {
		return 'Subscription not found.';
	}
	$from = 'year' === $to ? 'month' : 'year';
	if ( $from !== $sub->get_billing_period() ) {
		return 'Already ' . ( 'year' === $to ? 'annual' : 'monthly' ) . '.';
	}
	if ( 'month' === $to && ! lis_directory_annual_switch_can_undo( $sub ) ) {
		return 'Only a switch made on this page, before its first annual charge, can be undone here.';
	}
	$blocker = lis_directory_annual_switch_blocker( $sub );
	if ( '' !== $blocker ) {
		return $blocker;
	}

	$item = null;
	$pid  = 0;
	foreach ( lis_directory_annual_only_products() as $tier_pid ) {
		$item = lis_directory_annual_switch_item( $sub, $tier_pid );
		if ( $item ) {
			$pid = $tier_pid;
			break;
		}
	}
	if ( ! $item ) {
		return 'Not a Standard or Featured subscription.';
	}
	$var = lis_directory_resolve_plan_variation( $pid, $to );
	if ( ! $var['variation_id'] || null === $var['price'] ) {
		return 'No ' . ( 'year' === $to ? 'annual' : 'monthly' ) . ' price set on this product.';
	}

	$old_total = (float) $sub->get_total();
	$line      = (float) $var['price'] * max( 1, (int) $item->get_quantity() );
	$item->set_variation_id( (int) $var['variation_id'] );
	$attr_set = false;
	foreach ( $item->get_meta_data() as $meta ) {
		if ( in_array( strtolower( $meta->key ), array( 'billing', 'pa_billing' ), true ) ) {
			$item->update_meta_data( $meta->key, $var['attr'] );
			$attr_set = true;
		}
	}
	if ( ! $attr_set ) {
		$item->update_meta_data( 'billing', $var['attr'] );
	}
	$item->set_subtotal( $line );
	$item->set_total( $line );
	$item->save();

	$sub->set_billing_period( $to );
	$sub->set_billing_interval( 1 );
	$sub->calculate_totals(); // Recalculates tax on the new price.
	if ( 'year' === $to ) {
		$sub->update_meta_data( '_lis_switched_to_annual', time() );
	} else {
		$sub->delete_meta_data( '_lis_switched_to_annual' );
	}
	$user = wp_get_current_user();
	$next = $sub->get_time( 'next_payment' );
	$sub->add_order_note( sprintf(
		'%1$s by %2$s. Was %3$s; now %4$s. Next payment date unchanged%5$s.',
		'year' === $to ? 'Switched from monthly to annual billing' : 'Switched back to monthly billing (undo)',
		'' !== $why ? $why : ( ( $user && $user->exists() ? $user->display_name : 'an admin' ) . ' (LIS Directory → Switch to Annual)' ),
		wp_strip_all_tags( wc_price( $old_total ) ) . ( 'year' === $to ? '/month' : '/year' ),
		wp_strip_all_tags( wc_price( $sub->get_total() ) ) . ( 'year' === $to ? '/year' : '/month' ),
		$next ? ' (' . date_i18n( get_option( 'date_format' ), $next ) . ')' : ''
	) );
	$sub->save();
	return true;
}

function lis_directory_handle_annual_switch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'lis_annual_switch' );
	$sub_id = isset( $_POST['sub_id'] ) ? absint( $_POST['sub_id'] ) : 0;
	$to     = ( isset( $_POST['to'] ) && 'month' === $_POST['to'] ) ? 'month' : 'year';
	$result = lis_directory_switch_subscription_billing( $sub_id, $to );
	$args   = array(
		'post_type' => 'lis_preferred_vendor',
		'page'      => 'lis_annual_switch',
		'lis_done'  => true === $result ? $to : 'error',
		'lis_sub'   => $sub_id,
	);
	if ( true !== $result ) {
		set_transient( 'lis_annual_switch_error_' . get_current_user_id(), $result, 120 );
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
	exit;
}

function lis_directory_annual_switch_email_link( array $r ) {
	// Plain text for an email: wc_price() writes "$" as the entity &#36;.
	$money = function ( $n ) {
		return null === $n ? '' : html_entity_decode( wp_strip_all_tags( wc_price( $n ) ), ENT_QUOTES, 'UTF-8' );
	};
	$plan    = 'featured' === $r['tier'] ? 'Featured' : 'Standard';
	$save    = ( null !== $r['month'] && null !== $r['year'] ) ? ( $r['month'] * 12 - $r['year'] ) : 0;
	$date    = $r['next'] ? date_i18n( 'F j', $r['next'] ) : 'your next renewal';
	$first   = $r['name'] ? strtok( $r['name'], ' ' ) : 'there';
	$subject = 'Your ' . ( $r['business'] ? $r['business'] : 'business' ) . ' listing on Living in Sandpoint';
	$body    = "Hi {$first},\n\n"
		. 'Thanks for listing ' . ( $r['business'] ? $r['business'] : 'your business' ) . " in our directory! Your {$plan} listing is currently billed monthly ({$money( $r['month'] )}/month plus tax).\n\n"
		. "We're moving listings to annual billing: {$money( $r['year'] )} per year" . ( $save > 0 ? ", which saves you {$money( $save )}" : '' ) . ".\n\n"
		. "If that works for you, just reply and we'll switch it over. Nothing changes until {$date}, when you'd be charged {$money( $r['year'] )} (plus tax) instead of the monthly amount, then once a year after that.\n\n"
		. "Thanks!\n";
	return 'mailto:' . rawurlencode( $r['email'] ) . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );
}

function lis_directory_render_annual_switch_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$money = function ( $n ) {
		return null === $n ? '—' : wp_strip_all_tags( wc_price( $n ) );
	};
	$date = function ( $ts ) {
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '—';
	};
	$done  = isset( $_GET['lis_done'] ) ? sanitize_key( $_GET['lis_done'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = '';
	if ( 'error' === $done ) {
		$error = (string) get_transient( 'lis_annual_switch_error_' . get_current_user_id() );
		delete_transient( 'lis_annual_switch_error_' . get_current_user_id() );
	}
	$monthly  = lis_directory_annual_switch_rows( 'month' );
	$trials   = lis_directory_annual_switch_rows( 'trial' );
	$switched = lis_directory_annual_switch_rows( 'year' );
	$button   = function ( array $r, $to ) use ( $money, $date ) {
		$who = $r['business'] ? $r['business'] : ( $r['name'] ? $r['name'] : 'this customer' );
		$msg = 'year' === $to
			? sprintf( "Switch %s to annual?\n\nNothing is charged today. On %s they will be charged %s + tax, then once a year.", $who, $date( $r['next'] ), $money( $r['year'] ) )
			: sprintf( 'Undo: put %s back on monthly billing (%s/month + tax)?', $who, $money( $r['month'] ) );
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( $msg ) ); ?>);">
			<input type="hidden" name="action" value="lis_directory_annual_switch" />
			<input type="hidden" name="sub_id" value="<?php echo (int) $r['sub']->get_id(); ?>" />
			<input type="hidden" name="to" value="<?php echo esc_attr( $to ); ?>" />
			<?php wp_nonce_field( 'lis_annual_switch' ); ?>
			<button type="submit" class="button<?php echo 'year' === $to ? ' button-primary' : ''; ?>"><?php echo 'year' === $to ? 'Switch to annual' : 'Undo'; ?></button>
		</form>
		<?php
		return ob_get_clean();
	};
	?>
	<div class="wrap">
		<h1>Switch to Annual</h1>
		<?php if ( 'year' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>Switched to annual. A note was added to the subscription. Nothing was charged today.</p></div>
		<?php elseif ( 'month' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>Undone — back on monthly billing.</p></div>
		<?php elseif ( 'error' === $done ) : ?>
			<div class="notice notice-error is-dismissible"><p>Not changed: <?php echo esc_html( $error ? $error : 'unknown error.' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! function_exists( 'wcs_get_subscriptions' ) ) : ?>
			<p>WooCommerce Subscriptions isn't active, so there's nothing to show.</p>
		</div>
			<?php
			return;
		endif;
		?>

		<div style="max-width:760px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:12px 16px;margin:16px 0;">
			<p style="margin-top:0"><strong>How this works</strong></p>
			<ol style="margin-bottom:0">
				<li>These customers pay <strong>monthly</strong> for a Standard or Featured listing from before the 3-month start existed. New listings are annual, or monthly for 3 payments and then annual automatically (listed further down).</li>
				<li>Contact the customer first. <em>Email draft</em> opens your email app with a message ready to edit and send.</li>
				<li>Once they agree, click <strong>Switch to annual</strong>. <strong>Nothing is charged right away</strong>: they keep the month they've paid for, then their card on file is charged the annual price on their next payment date, and once a year after that.</li>
				<li>Clicked the wrong one? Use <strong>Undo</strong> in the list below the table.</li>
			</ol>
		</div>

		<h2>Still monthly (<?php echo (int) count( $monthly ); ?>)</h2>
		<?php if ( ! $monthly ) : ?>
			<p>Nobody left on monthly Standard or Featured. 🎉</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Business</th><th>Customer</th><th>Plan</th><th>Pays now</th><th>Annual price</th><th>Next payment</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $monthly as $r ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $r['business'] ? $r['business'] : '(no listing linked)' ); ?></strong>
							<?php if ( $r['listing_id'] ) : ?><br /><a href="<?php echo esc_url( get_edit_post_link( $r['listing_id'] ) ); ?>">Listing</a><?php endif; ?>
							&middot; <a href="<?php echo esc_url( $r['sub']->get_edit_order_url() ); ?>">Subscription #<?php echo (int) $r['sub']->get_id(); ?></a>
						</td>
						<td><?php echo esc_html( $r['name'] ); ?><br /><a href="mailto:<?php echo esc_attr( $r['email'] ); ?>"><?php echo esc_html( $r['email'] ); ?></a><?php echo $r['phone'] ? '<br />' . esc_html( $r['phone'] ) : ''; ?></td>
						<td><?php echo 'featured' === $r['tier'] ? 'Featured' : 'Standard'; ?></td>
						<td><?php echo esc_html( $money( $r['total'] ) ); ?>/mo<br /><small>incl. tax</small></td>
						<td><?php echo esc_html( $money( $r['year'] ) ); ?>/yr<br /><small>+ tax</small></td>
						<td><?php echo esc_html( $date( $r['next'] ) ); ?></td>
						<td style="white-space:nowrap">
							<?php if ( $r['email'] ) : ?><a class="button" href="<?php echo esc_attr( lis_directory_annual_switch_email_link( $r ) ); ?>">Email draft</a><?php endif; ?>
							<?php if ( '' === $r['blocker'] ) : ?>
								<?php echo $button( $r, 'year' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
							<?php else : ?>
								<br /><small><?php echo esc_html( $r['blocker'] ); ?></small>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( $trials ) : ?>
			<h2>On the 3-month start (<?php echo (int) count( $trials ); ?>)</h2>
			<p>These switch to annual by themselves after their 3rd monthly payment, and the customer is emailed then. Nothing to do here.</p>
			<table class="widefat striped">
				<thead><tr><th>Business</th><th>Customer</th><th>Plan</th><th>Pays now</th><th>Monthly payments made</th><th>Next payment</th></tr></thead>
				<tbody>
				<?php foreach ( $trials as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['business'] ? $r['business'] : '(no listing linked)' ); ?></strong> &middot; <a href="<?php echo esc_url( $r['sub']->get_edit_order_url() ); ?>">#<?php echo (int) $r['sub']->get_id(); ?></a></td>
						<td><?php echo esc_html( $r['name'] ); ?></td>
						<td><?php echo 'featured' === $r['tier'] ? 'Featured' : 'Standard'; ?></td>
						<td><?php echo esc_html( $money( $r['total'] ) ); ?>/mo <small>incl. tax</small></td>
						<td><?php echo (int) $r['paid']; ?> of <?php echo (int) lis_directory_trial_months(); ?></td>
						<td><?php echo esc_html( $date( $r['next'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( $switched ) : ?>
			<h2>Switched on this page (<?php echo (int) count( $switched ); ?>)</h2>
			<table class="widefat striped">
				<thead><tr><th>Business</th><th>Customer</th><th>Plan</th><th>Now pays</th><th>First annual charge</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $switched as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['business'] ? $r['business'] : '(no listing linked)' ); ?></strong> &middot; <a href="<?php echo esc_url( $r['sub']->get_edit_order_url() ); ?>">#<?php echo (int) $r['sub']->get_id(); ?></a></td>
						<td><?php echo esc_html( $r['name'] ); ?></td>
						<td><?php echo 'featured' === $r['tier'] ? 'Featured' : 'Standard'; ?></td>
						<td><?php echo esc_html( $money( $r['total'] ) ); ?>/yr <small>incl. tax</small></td>
						<td><?php echo esc_html( $date( $r['next'] ) ); ?></td>
						<td><?php echo $button( $r, 'month' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------- 3-month start: monthly for 3 payments, then annual ---------- */

/**
 * A new checkout that bought monthly Standard or Featured is a 3-month start
 * (the only way to buy those monthly — lis_directory_plan_billing()). Flag the
 * subscription so it switches itself to annual. Subscriptions from before
 * this existed have no flag and are left alone (Switch to Annual page).
 */
function lis_directory_mark_trial_subscription( $subscription, $order = null ) {
	if ( ! $subscription || 'month' !== $subscription->get_billing_period() ) {
		return;
	}
	foreach ( lis_directory_annual_only_products() as $pid ) {
		if ( lis_directory_annual_switch_item( $subscription, $pid ) ) {
			$subscription->update_meta_data( '_lis_trial_to_annual', lis_directory_trial_months() );
			$subscription->add_order_note( sprintf( '3-month start: billed monthly for %d payments, then switches to annual automatically (LIS Directory).', lis_directory_trial_months() ) );
			$subscription->save();
			return;
		}
	}
}

/** Whether a subscription is a 3-month start that hasn't switched yet. */
function lis_directory_is_pending_trial( $sub ) {
	return $sub && (int) $sub->get_meta( '_lis_trial_to_annual' ) > 0 && 'month' === $sub->get_billing_period() && ! $sub->get_meta( '_lis_trial_converted' );
}

/**
 * After each renewal payment: once a 3-month start has made its last monthly
 * payment, switch it to annual (same in-place switch as the admin page — no
 * charge now; the annual price is charged on the next payment date) and email
 * the customer so they have a month's notice and can cancel. If it can't be
 * switched automatically (coupon, a payment method that can't change amount),
 * the site admin is emailed instead.
 */
function lis_directory_maybe_convert_trial_to_annual( $subscription, $last_order = null ) {
	if ( ! lis_directory_is_pending_trial( $subscription ) ) {
		return;
	}
	$months = (int) $subscription->get_meta( '_lis_trial_to_annual' );
	if ( (int) $subscription->get_payment_count() < $months ) {
		return;
	}
	$sub_id = $subscription->get_id();
	$result = lis_directory_switch_subscription_billing( $sub_id, 'year', 'the 3-month start (automatic, after the last monthly payment)' );
	$sub    = wcs_get_subscription( $sub_id ); // Fresh copy after the switch.
	if ( true !== $result ) {
		$sub->add_order_note( '3-month start could not switch to annual automatically: ' . $result );
		$sub->update_meta_data( '_lis_trial_converted', 'failed' );
		$sub->save();
		wp_mail(
			get_option( 'admin_email' ),
			'Listing subscription needs a manual switch to annual',
			"Subscription #{$sub_id} finished its 3 monthly payments but couldn't be switched to annual automatically:\n\n{$result}\n\n" . admin_url( 'post.php?post=' . $sub_id . '&action=edit' ) . "\n"
		);
		return;
	}
	$sub->update_meta_data( '_lis_trial_converted', time() );
	$sub->save();
	lis_directory_send_trial_converted_email( $sub );
}

/**
 * "Your 3 months are up" email: what they'll be charged, when, and how to cancel.
 */
function lis_directory_send_trial_converted_email( $sub ) {
	$email = $sub->get_billing_email();
	if ( ! $email ) {
		return;
	}
	$business = '';
	$tier     = 'listing';
	foreach ( lis_directory_annual_only_products() as $t => $pid ) {
		$item = lis_directory_annual_switch_item( $sub, $pid );
		if ( $item ) {
			$tier       = 'featured' === $t ? 'Featured listing' : 'listing';
			$listing_id = (int) $item->get_meta( '_lis_listing_id' );
			$business   = $listing_id ? get_the_title( $listing_id ) : '';
			break;
		}
	}
	$next    = $sub->get_time( 'next_payment' );
	$when    = $next ? date_i18n( get_option( 'date_format' ), $next ) : 'your next payment date';
	$amount  = wp_strip_all_tags( wc_price( $sub->get_total() ) );
	$account = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'subscriptions' ) : home_url( '/' );
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$first   = $sub->get_billing_first_name();

	$body  = ( $first ? "Hi {$first},\n\n" : "Hi,\n\n" );
	$body .= 'Thanks for trying a ' . $tier . ' on ' . $site . ( $business ? ' for ' . $business : '' ) . ". Your first 3 monthly payments are done, so your plan now moves to annual billing, as described when you signed up.\n\n";
	$body .= "On {$when}, your card on file will be charged {$amount} for a full year, then once a year after that. Nothing else changes; your listing stays live.\n\n";
	$body .= "Don't want to continue? Cancel any time before {$when} from your account and you won't be charged:\n{$account}\n\n";
	$body .= "Questions? Just reply to this email.\n\n{$site}\n";

	wp_mail( $email, 'Your ' . $site . ' listing is switching to annual on ' . $when, $body, array( 'Reply-To: ' . get_option( 'admin_email' ) ) );
	$sub->add_order_note( 'Emailed the customer about the switch to annual (' . $email . ').' );
	$sub->save();
}
