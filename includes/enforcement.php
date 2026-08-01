<?php
/**
 * Category-lock enforcement: only one `active` vendor per category at a time.
 *
 * This is the core rule the whole feature depends on (see build brief), so it's
 * kept as small reusable helpers rather than inlined in the meta box save —
 * the front-end submission form (build order step 5) will need
 * lis_directory_get_active_vendor_for_category() too, to hide/waitlist taken
 * categories from applicants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'lis_directory_conflict_notice' );

/**
 * Find the vendor currently `active` in a given category, if any.
 *
 * @param int      $term_id         lis_vendor_category term ID.
 * @param int|null $exclude_post_id Post ID to ignore (e.g. the vendor being saved).
 * @return WP_Post|null
 */
function lis_directory_get_active_vendor_for_category( $term_id, $exclude_post_id = null ) {
	$args = array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'lis_vendor_category',
				'field'    => 'term_id',
				'terms'    => (int) $term_id,
			),
		),
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_status',
				'value' => 'active',
			),
		),
	);

	if ( $exclude_post_id ) {
		$args['post__not_in'] = array( (int) $exclude_post_id );
	}

	$query = new WP_Query( $args );

	return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * All currently `active` vendors, across every category — used by the ticker
 * shortcode. Ordered by category then business name so the ticker groups
 * vendors from the same category together rather than in a random order.
 *
 * @return WP_Post[]
 */
function lis_directory_get_all_active_vendors() {
	$query = new WP_Query( array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_status',
				'value' => 'active',
			),
		),
	) );

	return $query->posts;
}

/**
 * Check whether setting $post_id to `active` would conflict with an existing
 * active vendor in any of its assigned categories.
 *
 * @param int $post_id
 * @return WP_Post|null The conflicting vendor post, or null if the category is open.
 */
function lis_directory_get_active_conflict( $post_id ) {
	$term_ids = wp_get_post_terms( $post_id, 'lis_vendor_category', array( 'fields' => 'ids' ) );
	if ( empty( $term_ids ) || is_wp_error( $term_ids ) ) {
		return null;
	}

	foreach ( $term_ids as $term_id ) {
		$conflict = lis_directory_get_active_vendor_for_category( $term_id, $post_id );
		if ( $conflict ) {
			return $conflict;
		}
	}

	return null;
}

/**
 * Second activation requirement (added after v0.5.2): a vendor must already
 * have a real, published listing on the site's Local Directory (Directorist's
 * `at_biz_dir` post type) before they can go Active — a Vendor Showcase slot
 * is meant to sit on top of an existing directory presence, not replace one.
 * `_lis_pv_link_url` is reused for this rather than adding a second URL field,
 * since it already meant "their Directorist listing or external site" and
 * Talus Rock Retreat's real submission already happened to satisfy this
 * (its Link URL was already a Directorist URL).
 *
 * @param string $url
 * @return bool
 */
function lis_directory_is_valid_directorist_url( $url ) {
	if ( empty( $url ) ) {
		return false;
	}

	$post_id = url_to_postid( $url );
	if ( ! $post_id ) {
		return false;
	}

	return 'at_biz_dir' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
}

/**
 * Single choke point for changing a vendor's status. Every call site (meta box
 * save, list-table Approve/Reject, the WooCommerce subscription-ended handler
 * in includes/woocommerce.php) goes through this instead of calling
 * update_post_meta() directly, so anything that needs to react to a status
 * change — currently just WooCommerce stock sync — only has to listen in one
 * place instead of being wired into every caller individually.
 */
function lis_directory_set_vendor_status( $post_id, $new_status ) {
	update_post_meta( $post_id, '_lis_pv_status', $new_status );
	do_action( 'lis_directory_vendor_status_changed', $post_id, $new_status );
}

/**
 * Two independent reasons an activate-to-`active` attempt can be blocked:
 * a category conflict (v0.2.0), or a missing/invalid Directorist listing
 * (added after v0.5.2). Both call sites (meta box save, list-table "Approve")
 * check both requirements and flag whichever fails first via this one
 * mechanism — a short-lived, per-user transient, simpler and more reliable
 * across redirect paths than threading a query arg through
 * `redirect_post_location`.
 */
function lis_directory_flag_activation_blocked( $vendor_id, $reason, $data = array() ) {
	set_transient( 'lis_pv_conflict_' . get_current_user_id(), array_merge(
		array( 'vendor_id' => $vendor_id, 'reason' => $reason ),
		$data
	), 45 );
}

/**
 * Runs both activation requirements — category lock (v0.2.0) and the
 * Directorist-listing requirement — for a vendor about to be set Active.
 * Flags the right notice and returns false on the first one that fails, so
 * the meta box save and the list-table Approve action share one source of
 * truth instead of two copies of "block + explain why" that could drift.
 *
 * @param int $post_id
 * @return bool True if activation is allowed.
 */
function lis_directory_can_activate_vendor( $post_id ) {
	$conflict = lis_directory_get_active_conflict( $post_id );
	if ( $conflict ) {
		lis_directory_flag_activation_blocked( $post_id, 'category_conflict', array( 'conflict_id' => $conflict->ID ) );
		return false;
	}

	$link_url = get_post_meta( $post_id, '_lis_pv_link_url', true );
	if ( ! lis_directory_is_valid_directorist_url( $link_url ) ) {
		lis_directory_flag_activation_blocked( $post_id, 'not_on_directory' );
		return false;
	}

	return true;
}

function lis_directory_conflict_notice() {
	$key  = 'lis_pv_conflict_' . get_current_user_id();
	$data = get_transient( $key );
	if ( ! $data ) {
		return;
	}
	delete_transient( $key );

	$current_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( $current_post_id && $current_post_id !== (int) $data['vendor_id'] ) {
		return;
	}

	if ( 'not_on_directory' === $data['reason'] ) {
		?>
		<div class="notice notice-error">
			<p>
				<strong>Status not changed to Active.</strong>
				This vendor's Directorist Listing URL is missing, or doesn't point to a
				real, published listing on the Local Directory. A Vendor Showcase slot
				requires an existing directory listing first — add or fix that URL,
				then try activating again.
			</p>
		</div>
		<?php
		return;
	}

	$conflict_title = get_the_title( $data['conflict_id'] );
	$edit_link      = get_edit_post_link( $data['conflict_id'] );
	?>
	<div class="notice notice-error">
		<p>
			<strong>Status not changed to Active.</strong>
			<?php if ( $edit_link ) : ?>
				"<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $conflict_title ); ?></a>"
			<?php else : ?>
				"<?php echo esc_html( $conflict_title ); ?>"
			<?php endif; ?>
			is already the active vendor in one of this vendor's categories. Only one
			active vendor is allowed per category — set the other vendor to
			Expired/Rejected first if you want to replace it.
		</p>
	</div>
	<?php
}
