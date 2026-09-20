<?php
/**
 * Vendor Showcase → "Showcase Slots" admin page: one place to see who holds
 * which category slot, what's waiting to be activated, and to look up why a
 * particular category is (or isn't) shown as taken to buyers — plus a one-click
 * "release" for a slot that shouldn't be held. Before this, the only view was
 * the flat Vendor Showcase list, which doesn't answer "who is in Insurance?".
 *
 * Read-mostly: it reuses lis_directory_set_vendor_status() (the plugin's single
 * choke point for status changes) and the existing Approve/Reject handlers, so
 * WooCommerce stock sync and every other listener keeps working.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_showcase_slots_page' );
add_action( 'admin_post_lis_directory_release_slot', 'lis_directory_handle_release_slot' );

/**
 * The active vendor holding a listing category's Showcase slot, or null.
 * `lis_directory_is_listing_category_showcase_taken()` is the boolean of this.
 */
function lis_directory_get_category_showcase_holder( $listing_term_id ) {
	$src = get_term( (int) $listing_term_id, 'lis_listing_category' );
	if ( ! $src || is_wp_error( $src ) ) {
		return null;
	}
	$vendor = get_term_by( 'slug', $src->slug, 'lis_vendor_category' );
	if ( ! $vendor || is_wp_error( $vendor ) ) {
		return null;
	}
	return lis_directory_get_active_vendor_for_category( $vendor->term_id );
}

/**
 * Which of a listing's categories its Showcase slot is judged against: the
 * most specific one (a category none of the listing's OTHER categories sits
 * beneath), not just whichever happens to be listed first — a listing filed
 * under both a broad parent and "Insurance Agencies" should compete for the
 * latter. Returns a term id, or 0.
 */
function lis_directory_get_listing_showcase_term( $listing_id ) {
	$cats = get_the_terms( $listing_id, 'lis_listing_category' );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return 0;
	}
	$ids = wp_list_pluck( $cats, 'term_id' );
	$best = null;
	foreach ( $cats as $cat ) {
		// Skip a category that is an ancestor of another one the listing has.
		$is_ancestor = false;
		foreach ( $ids as $other ) {
			if ( (int) $other !== (int) $cat->term_id && in_array( (int) $cat->term_id, array_map( 'intval', get_ancestors( (int) $other, 'lis_listing_category', 'taxonomy' ) ), true ) ) {
				$is_ancestor = true;
				break;
			}
		}
		if ( ! $is_ancestor && ( null === $best || (int) $cat->term_id < (int) $best->term_id ) ) {
			$best = $cat;
		}
	}
	return $best ? (int) $best->term_id : (int) $cats[0]->term_id;
}

function lis_directory_add_showcase_slots_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'Showcase Slots',
		'Showcase Slots',
		'edit_others_posts',
		'lis_showcase_slots',
		'lis_directory_render_showcase_slots_page'
	);
}

function lis_directory_render_showcase_slots_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$page_url = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_showcase_slots' );
	$statuses = defined( 'LIS_DIRECTORY_STATUSES' ) ? LIS_DIRECTORY_STATUSES : array();

	$vendors = get_posts( array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$active  = array();
	$pending = array();
	foreach ( $vendors as $v ) {
		$st = get_post_meta( $v->ID, '_lis_pv_status', true );
		if ( 'active' === $st ) {
			$active[] = $v;
		} elseif ( 'pending' === $st ) {
			$pending[] = $v;
		}
	}

	$cell_category = function ( $vendor_id ) {
		$terms = wp_get_post_terms( $vendor_id, 'lis_vendor_category' );
		return ( $terms && ! is_wp_error( $terms ) ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—';
	};
	$cell_listing = function ( $vendor_id ) {
		$lid = (int) get_post_meta( $vendor_id, '_lis_pv_listing_id', true );
		if ( $lid && get_post( $lid ) ) {
			$status = get_post_status( $lid );
			return '<a href="' . esc_url( get_edit_post_link( $lid ) ) . '">' . esc_html( get_the_title( $lid ) ) . '</a>' . ( 'publish' === $status ? '' : ' <em>(' . esc_html( $status ) . ')</em>' );
		}
		$url = get_post_meta( $vendor_id, '_lis_pv_link_url', true );
		return $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>' : '<em>none</em>';
	};

	$notice = isset( $_GET['lis_slot_msg'] ) ? sanitize_key( wp_unslash( $_GET['lis_slot_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1>Showcase Slots</h1>
		<?php if ( 'released' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>Slot released — the vendor is now Expired and the category is open again. (Any WooCommerce subscription is <strong>not</strong> cancelled by this.)</p></div>
		<?php endif; ?>
		<p>One vendor can hold each category's Showcase slot at a time. <strong><?php echo (int) count( $active ); ?></strong> held, <strong><?php echo (int) count( $pending ); ?></strong> waiting.</p>

		<h2>Slot lookup</h2>
		<?php
		$look_id = isset( $_GET['slot_cat'] ) ? absint( $_GET['slot_cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cats    = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false, 'orderby' => 'name' ) );
		$cats    = is_wp_error( $cats ) ? array() : $cats;
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="lis_preferred_vendor" />
			<input type="hidden" name="page" value="lis_showcase_slots" />
			<select name="slot_cat">
				<option value="">— Pick a listing category —</option>
				<?php foreach ( $cats as $c ) : ?>
					<option value="<?php echo (int) $c->term_id; ?>" <?php selected( $look_id, $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button">Check slot</button>
		</form>
		<?php
		if ( $look_id ) {
			$term   = get_term( $look_id, 'lis_listing_category' );
			$vterm  = $term && ! is_wp_error( $term ) ? get_term_by( 'slug', $term->slug, 'lis_vendor_category' ) : null;
			$holder = lis_directory_get_category_showcase_holder( $look_id );
			echo '<p style="margin-top:12px;">';
			if ( ! $term || is_wp_error( $term ) ) {
				echo 'Unknown category.';
			} else {
				echo '<strong>' . esc_html( $term->name ) . '</strong> &rarr; ';
				echo $vterm ? 'matches Showcase category "' . esc_html( $vterm->name ) . '" (#' . (int) $vterm->term_id . ')' : 'has no matching Showcase category yet (created on first purchase)';
				echo '. ';
				if ( $holder ) {
					echo '<span style="color:#b32d2e;"><strong>Taken</strong></span> by <a href="' . esc_url( get_edit_post_link( $holder->ID ) ) . '">' . esc_html( $holder->post_title ) . '</a> (Active).';
				} else {
					echo '<span style="color:#1a7f37;"><strong>Open</strong></span> — nobody holds it.';
				}
			}
			echo '</p>';
		}
		?>

		<h2>Held slots</h2>
		<?php if ( empty( $active ) ) : ?>
			<p>No slots are held right now.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Showcase category</th><th>Vendor</th><th>Listing</th><th>Term end</th><th>Subscription/order</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $active as $v ) : ?>
					<?php
					$release = wp_nonce_url( admin_url( 'admin-post.php?action=lis_directory_release_slot&post=' . $v->ID ), 'lis_pv_release_' . $v->ID );
					$end     = get_post_meta( $v->ID, '_lis_pv_term_end', true );
					$order   = get_post_meta( $v->ID, '_lis_pv_wc_order_id', true );
					?>
					<tr>
						<td><?php echo esc_html( $cell_category( $v->ID ) ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $v->ID ) ); ?>"><?php echo esc_html( $v->post_title ); ?></a></td>
						<td><?php echo $cell_listing( $v->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></td>
						<td><?php echo $end ? esc_html( $end ) : '—'; ?></td>
						<td><?php echo $order ? '#' . (int) $order : '—'; ?></td>
						<td><a href="<?php echo esc_url( $release ); ?>" style="color:#b32d2e;" onclick="return confirm('Release this slot? The vendor becomes Expired and disappears from the showcase.');">Release slot</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Waiting</h2>
		<p class="description">Pending entries are created when someone picks Vendor Showcase while adding or claiming a listing; paying activates them automatically. Approve manually only if you're activating one without a purchase.</p>
		<?php if ( empty( $pending ) ) : ?>
			<p>Nothing waiting.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Showcase category</th><th>Vendor</th><th>Listing</th><th>Applied</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $pending as $v ) : ?>
					<?php
					$approve = wp_nonce_url( admin_url( 'admin-post.php?action=lis_directory_approve_vendor&post=' . $v->ID ), 'lis_pv_approve_' . $v->ID );
					$reject  = wp_nonce_url( admin_url( 'admin-post.php?action=lis_directory_reject_vendor&post=' . $v->ID ), 'lis_pv_reject_' . $v->ID );
					?>
					<tr>
						<td><?php echo esc_html( $cell_category( $v->ID ) ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $v->ID ) ); ?>"><?php echo esc_html( $v->post_title ); ?></a></td>
						<td><?php echo $cell_listing( $v->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $v->post_date ) ); ?></td>
						<td><a href="<?php echo esc_url( $approve ); ?>">Approve</a> &nbsp; <a href="<?php echo esc_url( $reject ); ?>" style="color:#b32d2e;">Reject</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top:24px;"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lis_preferred_vendor' ) ); ?>">All Vendor Showcase entries (<?php echo (int) count( $vendors ); ?>, every status) &rarr;</a></p>
	</div>
	<?php
}

/**
 * Release a held slot: status → Expired via the plugin's single status choke
 * point, so stock sync and other listeners react exactly as they would for a
 * subscription ending. Deliberately does NOT touch WooCommerce billing.
 */
function lis_directory_handle_release_slot() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id || 'lis_preferred_vendor' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'You are not allowed to do that.' );
	}
	check_admin_referer( 'lis_pv_release_' . $post_id );

	lis_directory_set_vendor_status( $post_id, 'expired' );

	wp_safe_redirect( add_query_arg( 'lis_slot_msg', 'released', admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_showcase_slots' ) ) );
	exit;
}
