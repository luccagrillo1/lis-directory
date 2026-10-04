<?php
/**
 * Vendor Showcase → Payment Audit: every paid WooCommerce order matched to its
 * `lis_listing`, with where each listing stands (renewing, lapsed, expired, paid
 * but not live), plus a one-click fill for listings whose Payment reference was
 * never set.
 *
 * Older orders were placed against Directorist listings: the order carries the
 * Directorist post id in `_listing_id`, and the migration recorded the pairing on
 * the new listing as `_lis_migrated_from`. Orders placed through this plugin carry
 * `_lis_listing_id` on the line item. Both are followed here.
 *
 * Read-only except for "Apply", which does two things and only for listings the
 * recorded migration pairing links with certainty:
 *   1. Writes the listing id onto each old (Directorist-era) order's line items as
 *      `_lis_listing_id` — exactly how orders placed through this plugin carry it —
 *      plus `_lis_legacy_directorist_id` recording the original Directorist listing,
 *      and a private order note. So from then on the payment belongs to the new
 *      listing, and the order shows it.
 *   2. Sets the listing's Payment reference (`_lis_listing_paid_order_id`) to its
 *      latest order when it has none.
 * It never touches a listing's status, owner, expiry, tier flags or subscription,
 * and linking an order can't trigger a purchase handler: those only react to a
 * status change on this plugin's own tier products, which old Directorist plan
 * products are not.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_payment_audit_page' );
add_action( 'admin_post_lis_directory_audit_fill', 'lis_directory_handle_audit_fill' );
add_action( 'admin_post_lis_directory_audit_csv', 'lis_directory_handle_audit_csv' );

function lis_directory_add_payment_audit_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'Payment Audit',
		'Payment Audit',
		'manage_options',
		'lis_payment_audit',
		'lis_directory_render_payment_audit_page'
	);
}

/**
 * The lis_listing a Directorist listing id was migrated to.
 *
 * @return array { id:int, how:string } — how is 'migrated' (recorded pairing, certain),
 *                'title' (same title, needs a human), or '' (not found).
 */
function lis_directory_audit_find_lis_listing( $directorist_id ) {
	$directorist_id = (int) $directorist_id;
	if ( 'lis_listing' === get_post_type( $directorist_id ) ) {
		return array( 'id' => $directorist_id, 'how' => 'migrated' );
	}
	$ids = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_lis_migrated_from', 'value' => $directorist_id ) ),
	) );
	if ( $ids ) {
		return array( 'id' => (int) $ids[0], 'how' => 'migrated' );
	}
	$title = get_the_title( $directorist_id );
	if ( $title ) {
		$ids = get_posts( array(
			'post_type'      => 'lis_listing',
			'post_status'    => 'any',
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'title'          => $title,
		) );
		if ( 1 === count( $ids ) ) {
			return array( 'id' => (int) $ids[0], 'how' => 'title' );
		}
	}
	return array( 'id' => 0, 'how' => '' );
}

/**
 * The one Directorist listing a paying account owns, or 0 if it has none or
 * several. Judged by date, not post id (ids on imported Directorist posts aren't
 * chronological): the listing must not have been created long AFTER the order.
 * A short window after is allowed because Directorist sells the plan first and
 * the buyer then creates the listing with it (Pivo Peaks: paid Oct 29, listing
 * created Oct 31); a listing that appeared months later can't be what the
 * order was for.
 */
function lis_directory_audit_single_directorist_listing( $customer_id, $order_ts ) {
	$customer_id = (int) $customer_id;
	if ( ! $customer_id ) {
		return 0;
	}
	$ids = get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => 'any',
		'author'         => $customer_id,
		'posts_per_page' => 3,
		'fields'         => 'ids',
	) );
	if ( 1 !== count( $ids ) ) {
		return 0;
	}
	$listing_ts = (int) get_post_time( 'U', true, $ids[0] );
	if ( $order_ts && $listing_ts > $order_ts + 14 * DAY_IN_SECONDS ) {
		return 0;
	}
	return (int) $ids[0];
}

/**
 * Where a listing stands, from its subscriptions if it has any, else from its
 * expiry date. Returns array( code, label ).
 */
function lis_directory_audit_state( $listing_id, array $subs ) {
	$fmt = function ( $sub, $key ) {
		$ts = (int) $sub->get_time( $key );
		return $ts ? wp_date( 'M j, Y', $ts ) : '';
	};
	if ( $subs ) {
		$best = null;
		$rank = array( 'active' => 1, 'on-hold' => 2, 'pending-cancel' => 3, 'cancelled' => 4, 'expired' => 4 );
		foreach ( $subs as $sub ) {
			if ( null === $best || ( isset( $rank[ $sub->get_status() ], $rank[ $best->get_status() ] ) && $rank[ $sub->get_status() ] < $rank[ $best->get_status() ] ) ) {
				$best = $sub;
			}
		}
		$status = $best->get_status();
		if ( 'active' === $status ) {
			$next = $fmt( $best, 'next_payment' );
			return array( 'renewing', $next ? 'Renews ' . $next : 'Active' );
		}
		if ( 'on-hold' === $status ) {
			return array( 'onhold', 'On hold (payment failed)' );
		}
		if ( 'pending-cancel' === $status ) {
			return array( 'ending', 'Cancelled, access ends ' . $fmt( $best, 'end' ) );
		}
		$end = $fmt( $best, 'end' );
		return array( 'lapsed', ucfirst( $status ) . ( $end ? ' ' . $end : '' ) );
	}

	if ( get_post_meta( $listing_id, '_lis_listing_never_expire', true ) ) {
		return array( 'never', 'Never expires' );
	}
	$expiry = lis_directory_get_listing_expiry( $listing_id );
	if ( get_post_meta( $listing_id, '_lis_listing_expired', true ) ) {
		return array( 'expired', 'Expired' . ( $expiry ? ' ' . wp_date( 'M j, Y', strtotime( $expiry ) ) : '' ) );
	}
	if ( '' === $expiry ) {
		return array( 'none', 'No term on record' );
	}
	$ts = strtotime( $expiry );
	return $ts < current_time( 'timestamp' )
		? array( 'expired', 'Expired ' . wp_date( 'M j, Y', $ts ) )
		: array( 'term', 'Runs to ' . wp_date( 'M j, Y', $ts ) );
}

/**
 * Walk every paid order and group by listing.
 *
 * @return array { listings: array<int,array>, unlinked: array[] }
 */
function lis_directory_audit_collect() {
	$out = array( 'listings' => array(), 'unlinked' => array() );
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return $out;
	}
	$orders = wc_get_orders( array(
		'limit'   => -1,
		'status'  => array( 'completed', 'processing', 'on-hold', 'refunded' ),
		'orderby' => 'date',
		'order'   => 'ASC',
	) );

	foreach ( $orders as $order ) {
		if ( (float) $order->get_total() <= 0 ) {
			continue; // Free Directorist plans.
		}
		$names = array();
		$lid   = 0;
		$how   = '';
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
			$m       = (int) $item->get_meta( '_lis_listing_id' );
			if ( ! $lid && $m && 'lis_listing' === get_post_type( $m ) ) {
				$lid = $m;
				$how = 'migrated';
			}
		}
		$dir_id = 0;
		if ( ! $lid ) {
			$dir_id = (int) $order->get_meta( '_listing_id' );
			if ( ! $dir_id ) {
				// No listing recorded on the order itself: ask Directorist. If the
				// paying account has exactly ONE Directorist listing, that is what
				// it paid for (an account with several is ambiguous, so left alone).
				$dir_id = lis_directory_audit_single_directorist_listing( $order->get_customer_id(), $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0 );
				$by_acct = (bool) $dir_id;
			} else {
				$by_acct = false;
			}
			if ( $dir_id ) {
				$found = lis_directory_audit_find_lis_listing( $dir_id );
				$lid   = $found['id'];
				$how   = ( $by_acct && 'migrated' === $found['how'] ) ? 'account' : $found['how'];
			}
		}

		$summary = array(
			'order_id' => $order->get_id(),
			'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
			'total'    => (float) $order->get_total(),
			'status'   => $order->get_status(),
			'plan'     => implode( ' + ', $names ),
			// An order with no payment method was made by hand (admin tooling,
			// tests) — not a charge. Real payments are preferred as "the" order.
			'charged'  => '' !== (string) $order->get_payment_method(),
			'billing'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'dir_id'   => $dir_id,
			'how'      => $how,
		);
		if ( $lid ) {
			$out['listings'][ $lid ]['orders'][] = $summary;
			if ( 'title' === $how ) {
				$out['listings'][ $lid ]['uncertain'] = true;
			}
		} else {
			$out['unlinked'][] = $summary;
		}
	}

	foreach ( $out['listings'] as $lid => &$row ) {
		$post          = get_post( $lid );
		$charged       = array_values( array_filter( $row['orders'], function ( $o ) { return $o['charged']; } ) );
		$latest        = $charged ? end( $charged ) : end( $row['orders'] );
		$subs          = lis_directory_get_listing_subscriptions( $lid );
		if ( ! $subs && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			foreach ( $row['orders'] as $o ) {
				foreach ( (array) wcs_get_subscriptions_for_order( $o['order_id'], array( 'order_type' => 'any' ) ) as $found_sub ) {
					$subs[ $found_sub->get_id() ] = $found_sub;
				}
			}
		}
		$state         = lis_directory_audit_state( $lid, $subs );
		$row['id']     = $lid;
		$row['title']  = $post ? $post->post_title : '(missing)';
		$row['status'] = $post ? $post->post_status : 'missing';
		$row['owner']  = $post ? get_the_author_meta( 'display_name', $post->post_author ) : '';
		$row['latest'] = $latest;
		$row['ref']    = (int) get_post_meta( $lid, '_lis_listing_paid_order_id', true );
		$row['has_sub'] = ! empty( $subs );
		$row['state']  = $state;
		$row['uncertain'] = ! empty( $row['uncertain'] );
		// Orders that reached this listing through the Directorist pairing and
		// don't yet carry the listing id themselves.
		$row['legacy']     = array();
		$row['by_account'] = false;
		foreach ( $row['orders'] as $o ) {
			if ( $o['dir_id'] && in_array( $o['how'], array( 'migrated', 'account' ), true ) ) {
				$row['legacy'][ $o['order_id'] ] = $o['dir_id'];
				if ( 'account' === $o['how'] ) {
					$row['by_account'] = true;
				}
			}
		}
		// Paying (or in term) but the listing itself isn't published.
		$row['notlive'] = $post && 'publish' !== $post->post_status && in_array( $state[0], array( 'renewing', 'term', 'never', 'none', 'ending' ), true );
	}
	unset( $row );

	uasort( $out['listings'], function ( $a, $b ) {
		return strcasecmp( $a['title'], $b['title'] );
	} );
	return $out;
}

function lis_directory_audit_is_lapsed( $row ) {
	return in_array( $row['state'][0], array( 'lapsed', 'expired', 'onhold' ), true );
}

function lis_directory_audit_table( array $rows, $with_checkbox_note = false ) {
	if ( empty( $rows ) ) {
		return '<p><em>None.</em></p>';
	}
	$h = '<table class="widefat striped"><thead><tr><th>Listing</th><th>Owner</th><th>Listing status</th><th>Where it stands</th><th>Latest order</th><th>Payment reference</th><th>Old orders to link</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$h .= '<tr>'
			. '<td><a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html( $r['title'] ) . '</a>' . ( $r['uncertain'] ? ' <em title="Matched only by title">(title match)</em>' : '' ) . ( $r['by_account'] ? ' <em title="The order names no listing; the paying account has exactly one Directorist listing, and it predates the order">(matched by account)</em>' : '' ) . '</td>'
			. '<td>' . esc_html( $r['owner'] ) . '</td>'
			. '<td>' . esc_html( $r['status'] ) . '</td>'
			. '<td><strong>' . esc_html( $r['state'][1] ) . '</strong></td>'
			. '<td>#' . (int) $r['latest']['order_id'] . ' &middot; ' . esc_html( $r['latest']['date'] ) . ' &middot; $' . esc_html( number_format( $r['latest']['total'], 2 ) ) . ' &middot; ' . esc_html( $r['latest']['plan'] ) . '</td>'
			. '<td>' . ( $r['ref'] ? '#' . (int) $r['ref'] : '<span style="color:#b32d2e;">empty</span>' ) . '</td>'
			. '<td>' . ( $r['legacy'] ? esc_html( '#' . implode( ', #', array_keys( $r['legacy'] ) ) ) : '&mdash;' ) . '</td>'
			. '</tr>';
	}
	return $h . '</tbody></table>';
}

function lis_directory_render_payment_audit_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$data     = lis_directory_audit_collect();
	$rows     = $data['listings'];
	$lapsed   = array_filter( $rows, 'lis_directory_audit_is_lapsed' );
	$notlive  = array_filter( $rows, function ( $r ) { return $r['notlive']; } );
	$missing  = array_filter( $rows, function ( $r ) { return ( ! $r['ref'] || $r['legacy'] ) && ! $r['uncertain'] && 'missing' !== $r['status']; } );
	$healthy  = count( $rows ) - count( $lapsed );
	$msg      = isset( $_GET['lis_audit_filled'] ) ? absint( $_GET['lis_audit_filled'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$csv_url  = function ( $scope ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lis_directory_audit_csv&scope=' . $scope ), 'lis_audit_csv' );
	};
	?>
	<div class="wrap">
		<h1>Payment Audit</h1>
		<?php if ( $msg >= 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>Applied to <strong><?php echo (int) $msg; ?></strong> listing(s) &mdash; <?php echo (int) ( isset( $_GET['lis_audit_linked'] ) ? absint( $_GET['lis_audit_linked'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> old order(s) linked.</p></div>
		<?php endif; ?>
		<p>Every paid order (<?php echo (int) array_sum( array_map( function ( $r ) { return count( $r['orders'] ); }, $rows ) ) + count( $data['unlinked'] ); ?> total, free $0 plans excluded) matched to its listing. Older orders are matched through the Directorist listing they were bought for.</p>
		<p><strong><?php echo (int) count( $rows ); ?></strong> listings with a payment &middot; <strong><?php echo (int) count( $lapsed ); ?></strong> expired/lapsed &middot; <strong><?php echo (int) count( $notlive ); ?></strong> paid but not published &middot; <strong><?php echo (int) count( $missing ); ?></strong> to apply &middot; <strong><?php echo (int) count( $data['unlinked'] ); ?></strong> orders I couldn't match</p>

		<h2>Expired / lapsed (<?php echo (int) count( $lapsed ); ?>)</h2>
		<p><a class="button" href="<?php echo esc_url( $csv_url( 'lapsed' ) ); ?>">Download CSV</a> <a class="button" href="<?php echo esc_url( $csv_url( 'all' ) ); ?>">Download everything (CSV)</a></p>
		<?php echo lis_directory_audit_table( $lapsed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>

		<h2>Paying or in term, but the listing isn't published (<?php echo (int) count( $notlive ); ?>)</h2>
		<p class="description">These are the ones to look at first: money taken (or a term still running) and the listing is a draft.</p>
		<?php echo lis_directory_audit_table( $notlive ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<h2>To apply (<?php echo (int) count( $missing ); ?>)</h2>
		<p class="description">Old Directorist-era orders whose listing was migrated, and listings with no Payment reference. <strong>Apply</strong> writes the new listing onto each of those orders (so the payment belongs to the new listing, the same way newer orders do, with a note and the original Directorist listing id kept on the order) and fills the listing's Payment reference with its latest order. It changes nothing else &mdash; not status, owner, expiry, tier or subscription.</p>
		<?php echo lis_directory_audit_table( $missing ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $missing ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;" onsubmit="return confirm('Apply to <?php echo (int) count( $missing ); ?> listing(s)?');">
				<input type="hidden" name="action" value="lis_directory_audit_fill" />
				<?php wp_nonce_field( 'lis_directory_audit_fill', 'lis_audit_nonce' ); ?>
				<?php submit_button( 'Apply to ' . count( $missing ) . ' listing(s)', 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2>Paid orders I couldn't match to a listing (<?php echo (int) count( $data['unlinked'] ); ?>)</h2>
		<p class="description">No listing id on the order, or the Directorist listing it was bought for was never migrated. Mostly early tests.</p>
		<?php if ( empty( $data['unlinked'] ) ) : ?>
			<p><em>None.</em></p>
		<?php else : ?>
			<table class="widefat striped"><thead><tr><th>Order</th><th>Date</th><th>Plan</th><th>Total</th><th>Billing name</th><th>Directorist listing</th></tr></thead><tbody>
			<?php foreach ( $data['unlinked'] as $u ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $u['order_id'] . '&action=edit' ) ); ?>">#<?php echo (int) $u['order_id']; ?></a></td>
					<td><?php echo esc_html( $u['date'] ); ?></td>
					<td><?php echo esc_html( $u['plan'] ); ?></td>
					<td>$<?php echo esc_html( number_format( $u['total'], 2 ) ); ?></td>
					<td><?php echo esc_html( $u['billing'] ); ?></td>
					<td><?php echo $u['dir_id'] ? '#' . (int) $u['dir_id'] . ( get_post( $u['dir_id'] ) ? ' (' . esc_html( get_the_title( $u['dir_id'] ) ) . ')' : ' (deleted)' ) : '&mdash;'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

function lis_directory_handle_audit_fill() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_audit_nonce'] ) || ! wp_verify_nonce( $_POST['lis_audit_nonce'], 'lis_directory_audit_fill' ) ) {
		wp_die( 'Security check failed.' );
	}
	$filled = 0;
	$linked = 0;
	// Recomputed here, not taken from the form, so only certain pairings are written.
	foreach ( lis_directory_audit_collect()['listings'] as $lid => $row ) {
		if ( $row['uncertain'] || 'missing' === $row['status'] ) {
			continue;
		}
		$touched = false;
		foreach ( $row['legacy'] as $order_id => $dir_id ) {
			if ( lis_directory_audit_link_order( $order_id, $lid, $dir_id ) ) {
				$linked++;
				$touched = true;
			}
		}
		if ( ! $row['ref'] ) {
			update_post_meta( $lid, '_lis_listing_paid_order_id', (int) $row['latest']['order_id'] );
			$touched = true;
		}
		if ( $touched ) {
			$filled++;
		}
	}
	wp_safe_redirect( add_query_arg( array( 'lis_audit_filled' => $filled, 'lis_audit_linked' => $linked ), admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_payment_audit' ) ) );
	exit;
}

/**
 * Point one old order at its new listing: `_lis_listing_id` on each line item
 * (how this plugin's own orders carry it), the original Directorist listing id
 * beside it, and a private note. Idempotent — a line that already has a listing
 * id is left alone. Returns whether anything was written.
 */
function lis_directory_audit_link_order( $order_id, $listing_id, $directorist_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false;
	}
	$changed = false;
	foreach ( $order->get_items() as $item ) {
		if ( $item->get_meta( '_lis_listing_id' ) ) {
			continue;
		}
		$item->add_meta_data( '_lis_listing_id', (int) $listing_id, true );
		$item->add_meta_data( '_lis_legacy_directorist_id', (int) $directorist_id, true );
		$item->save();
		$changed = true;
	}
	if ( $changed ) {
		$order->add_order_note( sprintf( 'Payment Audit: linked to listing #%1$d "%2$s" (migrated from Directorist listing #%3$d).', $listing_id, get_the_title( $listing_id ), $directorist_id ) );
	}
	return $changed;
}

function lis_directory_handle_audit_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	check_admin_referer( 'lis_audit_csv' );
	$scope = ( isset( $_GET['scope'] ) && 'lapsed' === $_GET['scope'] ) ? 'lapsed' : 'all';
	$rows  = lis_directory_audit_collect()['listings'];
	if ( 'lapsed' === $scope ) {
		$rows = array_filter( $rows, 'lis_directory_audit_is_lapsed' );
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="listing-payments-' . $scope . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$f = fopen( 'php://output', 'w' );
	fputcsv( $f, array( 'Listing ID', 'Listing', 'Owner', 'Listing status', 'Where it stands', 'Latest order', 'Order date', 'Amount', 'Plan', 'Payment reference', 'Billing name', 'Edit link' ) );
	foreach ( $rows as $r ) {
		fputcsv( $f, array(
			$r['id'],
			$r['title'],
			$r['owner'],
			$r['status'],
			$r['state'][1],
			$r['latest']['order_id'],
			$r['latest']['date'],
			number_format( $r['latest']['total'], 2, '.', '' ),
			$r['latest']['plan'],
			$r['ref'] ? $r['ref'] : '',
			$r['latest']['billing'],
			get_edit_post_link( $r['id'], 'raw' ),
		) );
	}
	fclose( $f );
	exit;
}
