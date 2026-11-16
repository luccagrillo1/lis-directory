<?php
/**
 * Two more Directorist-parity pieces that don't fit anywhere else (see
 * DIRECTORIST_PARITY_PLAN.md phase 3): a public Author/Vendor Profile page,
 * and a logged-in Dashboard of "my listings". (Compare Listings, formerly
 * also here, was removed by request.)
 *
 * Dashboard Edit link: prefers the front-end [lis_listing_edit] form
 * (includes/listings-edit.php, once its page is configured under Settings >
 * LIS Directory Settings) so front-end signups — usually Subscribers, who
 * lack wp-admin edit_post capability — can actually edit their own listing.
 * Falls back to the wp-admin edit link only if that page isn't configured.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_listing_author_profile', 'lis_directory_render_author_profile_shortcode' );
add_shortcode( 'lis_listing_dashboard', 'lis_directory_render_dashboard_shortcode' );
add_shortcode( 'lis_listing_grid', 'lis_directory_render_grid_shortcode' );
add_action( 'admin_post_lis_directory_toggle_sold', 'lis_directory_handle_toggle_sold' );
add_shortcode( 'lis_saved_listings', 'lis_directory_render_saved_listings_shortcode' );
add_shortcode( 'lis_account_overview', 'lis_directory_render_account_overview_shortcode' );
add_action( 'admin_post_lis_directory_unsave_listing', 'lis_directory_handle_unsave_listing' );

/**
 * Lets a listing owner flip Sold/Rented from the Dashboard without needing
 * wp-admin edit access (most front-end registrants are Subscribers, who
 * don't have it — same reasoning as the Edit link above). Ownership is
 * enforced by post_author match, not just is_user_logged_in(), so one
 * user can't toggle another's listing by guessing a listing_id.
 */
function lis_directory_handle_toggle_sold() {
	$redirect_base = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to do that.' );
	}

	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;

	if ( ! isset( $_POST['lis_listing_toggle_sold_nonce'] ) || ! wp_verify_nonce( $_POST['lis_listing_toggle_sold_nonce'], 'lis_listing_toggle_sold_' . $listing_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	$listing = get_post( $listing_id );
	if ( ! $listing || 'lis_listing' !== $listing->post_type || (int) $listing->post_author !== get_current_user_id() ) {
		wp_die( 'You can only do that for your own listings.' );
	}

	$type = lis_directory_get_listing_type( $listing_id );
	if ( 'real-estate-sale' !== $type && 'real-estate-rent' !== $type ) {
		wp_safe_redirect( $redirect_base );
		exit;
	}

	lis_directory_set_real_estate_off_market( $listing_id, ! lis_directory_real_estate_is_off_market( $listing_id ) );

	wp_safe_redirect( $redirect_base );
	exit;
}

/**
 * [lis_listing_grid type="real-estate-sale" count="12"] — a standalone grid
 * for embedding on an ordinary page, pre-filtered to one directory type.
 * Exists because the real filtered browsing experience lives on the
 * `lis_listing` post-type archive / category archive (routed by
 * templates/archive-listing.php against the actual WP query), which isn't
 * something a plain page can embed directly — this is the lightweight
 * version for the draft page tree's per-type landing pages (Real Estate
 * Sale/Rent, Job Listings), reusing the same card markup as Author Profile
 * via lis_directory_render_listing_card().
 *
 * Brought to feature parity with the real archive template by request —
 * same toolbar (Grid/List/Map + Sort By), sort actually reorders this
 * shortcode's own query via the same lis_sort param, and cards carry the
 * same data-address/data-title map-view attributes. The search box's
 * directory selector was also removed globally (includes/listings-search.php)
 * so both surfaces show the same fields.
 */
function lis_directory_render_grid_shortcode( $atts ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	// count="-1" (the default) means every published listing, paged
	// LIS_DIRECTORY_LISTINGS_PER_PAGE at a time with numbered links, same as the
	// main archive (D15). An explicit count is a fixed preview widget: that many
	// listings, no pagination.
	$atts  = shortcode_atts( array( 'type' => '', 'count' => -1, 'search' => 'auto' ), $atts, 'lis_listing_grid' );
	$paged = -1 === (int) $atts['count'];
	$types = lis_directory_get_listing_types();
	$type  = isset( $types[ $atts['type'] ] ) ? $atts['type'] : '';

	// Show the keyword + category search box above the grid so these per-type
	// landing pages have the same search parity as the main archive. On by
	// default for a typed grid (scoped to that directory); `search="yes"`
	// forces it on for an all-types grid, `search="no"` hides it. The form
	// submits to the archive with the filters applied — same results
	// template, no separate page.
	$show_search = ( 'no' !== $atts['search'] ) && ( '' !== $type || 'yes' === $atts['search'] );
	$search_html = '';
	if ( $show_search ) {
		$search_html = $type
			? do_shortcode( '[lis_listing_search directory="' . esc_attr( $type ) . '"]' )
			: do_shortcode( '[lis_listing_search]' );
	}

	$query_args = array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => $paged ? LIS_DIRECTORY_LISTINGS_PER_PAGE : max( 1, (int) $atts['count'] ),
		'paged'          => $paged ? max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ) : 1,
		'no_found_rows'  => ! $paged,
	);

	if ( $type ) {
		$query_args['meta_query'] = array( array( 'key' => '_lis_listing_type', 'value' => $type ) );
	} elseif ( function_exists( 'lis_directory_not_real_estate_clause' ) ) {
		// An all-types grid is the business directory; real estate has its own pages.
		$query_args['meta_query'] = array( lis_directory_not_real_estate_clause() );
	}

	if ( ! empty( $_GET['lis_sort'] ) ) {
		$sort_options = lis_directory_get_sort_options();
		$sort         = sanitize_key( wp_unslash( $_GET['lis_sort'] ) );
		if ( isset( $sort_options[ $sort ] ) ) {
			$query_args['orderby'] = $sort_options[ $sort ]['orderby'];
			$query_args['order']   = $sort_options[ $sort ]['order'];
		}
	}

	$grid_query   = new WP_Query( $query_args );
	$listings     = $grid_query->posts;
	$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );

	ob_start();
	if ( $search_html ) : ?>
		<div class="lis-listing-grid-search"><?php echo $search_html; // phpcs:ignore -- built from the search shortcode, escaped there. ?></div>
	<?php endif; ?>
	<?php if ( empty( $listings ) ) : ?>
		<p class="lis-listing-empty">No listings here yet.</p>
	<?php else : ?>
		<?php if ( function_exists( 'lis_directory_render_listing_toolbar' ) ) : ?>
			<?php lis_directory_render_listing_toolbar(); ?>
		<?php endif; ?>

		<?php if ( $maps_api_key ) : ?>
			<div class="lis-listing-map-view" data-lis-listing-map-view hidden>
				<div class="lis-listing-map-view-canvas"></div>
			</div>
			<script>window.lisDirectoryMapsApiKey = <?php echo wp_json_encode( $maps_api_key ); ?>;</script>
		<?php endif; ?>

		<div class="lis-listing-grid" data-lis-listing-results>
			<?php foreach ( $listings as $listing ) : ?>
				<?php echo lis_directory_render_listing_card( $listing ); // phpcs:ignore -- escaped inside helper. ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $paged ) : ?>
			<?php echo lis_directory_render_listing_pagination( $grid_query->max_num_pages, $query_args['paged'] ); // phpcs:ignore -- built by paginate_links(). ?>
		<?php endif; ?>

		<?php wp_enqueue_script( 'lis-directory-listing-view-toggle', LIS_DIRECTORY_URL . 'assets/js/listing-view-toggle.js', array(), LIS_DIRECTORY_VERSION, true ); ?>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

function lis_directory_render_author_profile_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	$author_id = isset( $_GET['author_id'] ) ? absint( $_GET['author_id'] ) : 0;
	if ( ! $author_id ) {
		return '<p class="lis-listing-author-missing">No vendor specified.</p>';
	}

	$author = get_userdata( $author_id );
	if ( ! $author ) {
		return '<p class="lis-listing-author-missing">Vendor not found.</p>';
	}

	$listings = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'author'         => $author_id,
		'posts_per_page' => -1,
	) );

	ob_start();
	?>
	<div class="lis-listing-author-profile">
		<div class="lis-listing-author-header">
			<?php echo get_avatar( $author_id, 80 ); ?>
			<div>
				<h1 class="lis-listing-author-name"><?php echo esc_html( $author->display_name ); ?></h1>
				<?php if ( $author->description ) : ?>
					<p class="lis-listing-author-bio"><?php echo esc_html( $author->description ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( empty( $listings ) ) : ?>
			<p class="lis-listing-empty">This vendor has no published listings yet.</p>
		<?php else : ?>
			<div class="lis-listing-grid">
				<?php foreach ( $listings as $listing ) : ?>
					<?php echo lis_directory_render_listing_card( $listing ); // phpcs:ignore -- escaped inside helper. ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function lis_directory_render_dashboard_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-listing-dashboard-login-required">
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to see your listings.
		</p>
		<?php
		return ob_get_clean();
	}

	$user_id  = get_current_user_id();
	$listings = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => array( 'publish', 'pending', 'draft' ),
		'author'         => $user_id,
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$status_labels = array(
		'publish' => 'Published',
		'pending' => 'Pending Review',
		'draft'   => 'Draft',
	);

	// "Expired" isn't a real post_status — the daily sweep (listings-expiry.php)
	// drops an expired listing to draft and flags it, so it's computed here
	// per-listing rather than queried directly.
	$dash_status_of = function ( $listing ) {
		if ( 'draft' === $listing->post_status && get_post_meta( $listing->ID, '_lis_listing_expired', true ) ) {
			return 'expired';
		}
		return $listing->post_status;
	};

	$filter_tabs = array(
		''        => 'All Listings',
		'publish' => 'Published',
		'pending' => 'Pending',
		'draft'   => 'Draft',
		'expired' => 'Expired',
	);
	$current_filter = isset( $_GET['lis_dash_status'] ) && isset( $filter_tabs[ sanitize_key( wp_unslash( $_GET['lis_dash_status'] ) ) ] )
		? sanitize_key( wp_unslash( $_GET['lis_dash_status'] ) )
		: '';

	$visible_listings = $current_filter
		? array_values( array_filter( $listings, function ( $listing ) use ( $dash_status_of, $current_filter ) {
			return $current_filter === $dash_status_of( $listing );
		} ) )
		: $listings;

	$plan_labels = array(
		'standard' => 'Standard',
		'featured' => 'Featured',
		'showcase' => 'Vendor Showcase',
	);

	ob_start();
	?>
	<div class="lis-listing-dashboard">
		<h1>My Listings</h1>
		<?php if ( empty( $listings ) ) : ?>
			<div class="lis-account-empty">
				<p class="lis-listing-empty">You haven't submitted any listings yet.</p>
				<?php $add_listing_url = lis_directory_add_listing_url(); ?>
				<?php if ( $add_listing_url ) : ?>
					<p class="lis-account-empty-actions"><a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $add_listing_url ); ?>">Add a listing</a></p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="lis-listing-dashboard-filters">
				<?php foreach ( $filter_tabs as $value => $label ) : ?>
					<a class="lis-listing-dashboard-filter<?php echo $current_filter === $value ? ' is-active' : ''; ?>" href="<?php echo esc_url( $value ? add_query_arg( 'lis_dash_status', $value ) : remove_query_arg( 'lis_dash_status' ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php if ( empty( $visible_listings ) ) : ?>
				<p class="lis-listing-empty">No listings match this filter.</p>
			<?php else : ?>
			<table class="lis-listing-dashboard-table">
				<thead>
					<tr>
						<th>Listing</th>
						<th>Directory</th>
						<th>Expiration Date</th>
						<th>Status</th>
						<th>Plan</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $visible_listings as $listing ) :
						$types       = lis_directory_get_listing_types();
						$dash_status = $dash_status_of( $listing );
						$status      = 'expired' === $dash_status ? 'Expired' : ( isset( $status_labels[ $dash_status ] ) ? $status_labels[ $dash_status ] : $dash_status );
						$type        = lis_directory_get_listing_type( $listing->ID );
						$is_re       = 'real-estate-sale' === $type || 'real-estate-rent' === $type;
						$sold        = $is_re && (bool) get_post_meta( $listing->ID, '_lis_listing_sold', true );
						$sold_label  = 'real-estate-rent' === $type ? 'Rented' : 'Sold';
						$never_expire = (bool) get_post_meta( $listing->ID, '_lis_listing_never_expire', true );
						$expiry_raw  = lis_directory_get_listing_expiry( $listing->ID );
						$expiry_display = $never_expire ? 'Never' : ( $expiry_raw ? date_i18n( 'F j, Y', strtotime( $expiry_raw ) ) : '—' );
						// Comma-joined since the wizard's Plan step allows Featured and Vendor
						// Showcase together (Standard is always the first entry — see the write
						// site in includes/listings-submission.php) — e.g. "standard,featured".
						$plan_tier_keys = array_filter( explode( ',', (string) get_post_meta( $listing->ID, '_lis_listing_pending_tier', true ) ) );
						$plan_label     = $plan_tier_keys
							? implode( ' + ', array_map( function ( $k ) use ( $plan_labels ) { return isset( $plan_labels[ $k ] ) ? $plan_labels[ $k ] : ucfirst( $k ); }, $plan_tier_keys ) )
							: 'No Plan';
						?>
						<tr>
							<td><?php echo esc_html( get_the_title( $listing ) ); ?></td>
							<td><?php echo esc_html( $types[ $type ] ); ?></td>
							<td><?php echo esc_html( $expiry_display ); ?></td>
							<td>
								<span class="lis-listing-dashboard-status lis-listing-dashboard-status--<?php echo esc_attr( $dash_status ); ?>"><?php echo esc_html( $status ); ?></span>
								<?php if ( $sold ) : ?><span class="lis-listing-badge lis-listing-badge--sold"><?php echo esc_html( $sold_label ); ?></span><?php endif; ?>
							</td>
							<td><?php echo esc_html( $plan_label ); ?></td>
							<td>
								<?php if ( 'publish' === $listing->post_status ) : ?>
									<a href="<?php echo esc_url( get_permalink( $listing ) ); ?>">View</a>
								<?php endif; ?>
								<?php
								$front_edit_url = lis_directory_get_listing_edit_url( $listing->ID );
								if ( $front_edit_url ) :
									?>
									<a href="<?php echo esc_url( $front_edit_url ); ?>">Edit</a>
									<?php if ( function_exists( 'lis_directory_listing_can_choose_plan' ) && lis_directory_listing_can_choose_plan( $listing ) ) : ?>
										<a href="<?php echo esc_url( $front_edit_url . '#lis-listing-plan-step' ); ?>"><strong>Finish &amp; choose a plan</strong></a>
									<?php endif; ?>
								<?php elseif ( current_user_can( 'edit_post', $listing->ID ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>">Edit</a>
								<?php endif; ?>
								<?php if ( $is_re ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lis-listing-dashboard-inline-form">
										<input type="hidden" name="action" value="lis_directory_toggle_sold" />
										<input type="hidden" name="listing_id" value="<?php echo (int) $listing->ID; ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>" />
										<?php wp_nonce_field( 'lis_listing_toggle_sold_' . $listing->ID, 'lis_listing_toggle_sold_nonce' ); ?>
										<button type="submit" class="lis-listing-dashboard-link-button"><?php echo $sold ? esc_html( 'Mark Available' ) : esc_html( 'Mark ' . $sold_label ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( ! $is_re ) : // Featured / Vendor Showcase are business-directory upgrades. ?>
								<?php
								// A listing on a one-time term (Directorist-era purchase) or one
								// that has expired: offer the Standard subscription, the only way
								// to keep a business listing live once its term runs out.
								$on_term     = '' !== $expiry_raw && ! $never_expire;
								$needs_plan  = ! get_post_meta( $listing->ID, '_lis_listing_standard_active', true )
									&& ( 'expired' === $dash_status || ( 'publish' === $listing->post_status && $on_term ) );
								// Standard is sold annually only (lis_directory_plan_billing()).
								$std_year    = $needs_plan ? lis_directory_build_listing_checkout_url( array(), 'year', $listing->ID ) : '';
								if ( $std_year ) :
									?>
									<a href="<?php echo esc_url( $std_year ); ?>"><?php echo 'expired' === $dash_status ? 'Renew' : 'Keep it listed'; ?>: Standard (annual)</a>
								<?php endif; ?>
								<?php
								$featured    = (bool) get_post_meta( $listing->ID, '_lis_listing_featured', true );
								$feature_url = ( 'publish' === $listing->post_status && ! $featured ) ? lis_directory_get_feature_listing_url( $listing->ID ) : '';
								if ( $feature_url ) :
									?>
									<a href="<?php echo esc_url( $feature_url ); ?>">⭐ Feature this listing</a>
								<?php elseif ( $featured ) : ?>
									<span class="lis-listing-badge lis-listing-badge--featured">Featured</span>
								<?php endif; ?>
								<?php
								$is_showcase  = function_exists( 'lis_directory_listing_is_active_showcase' ) && lis_directory_listing_is_active_showcase( $listing->ID );
								$showcase_url = ( 'publish' === $listing->post_status && ! $is_showcase && function_exists( 'lis_directory_get_showcase_upgrade_url' ) ) ? lis_directory_get_showcase_upgrade_url( $listing->ID ) : '';
								if ( $showcase_url ) :
									?>
									<a href="<?php echo esc_url( $showcase_url ); ?>">Join the Vendor Showcase (monthly)</a>
									<?php $showcase_year_url = lis_directory_get_showcase_upgrade_url( $listing->ID, 'year' ); ?>
									<?php if ( $showcase_year_url ) : ?>
										<a href="<?php echo esc_url( $showcase_year_url ); ?>">or annually</a>
									<?php endif; ?>
								<?php elseif ( $is_showcase ) : ?>
									<span class="lis-listing-badge lis-listing-badge--featured">Vendor Showcase</span>
								<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Factored out of templates/archive-listing.php so the Author Profile page
 * (and anywhere else that needs a listing card outside the main archive
 * loop) doesn't duplicate that markup. The archive template itself keeps
 * its inline version for now rather than a risky mid-flow refactor — see
 * the "deliberately unchanged" note in this phase's CHANGELOG entry.
 */
function lis_directory_render_listing_card( $post ) {
	$post_id     = $post->ID;
	$address     = get_post_meta( $post_id, '_lis_listing_address', true );
	$type        = lis_directory_get_listing_type( $post_id );
	$bedrooms    = get_post_meta( $post_id, '_lis_listing_bedrooms', true );
	$bathrooms   = get_post_meta( $post_id, '_lis_listing_bathrooms', true );
	$sqft        = get_post_meta( $post_id, '_lis_listing_sqft', true );
	$salary      = get_post_meta( $post_id, '_lis_listing_salary', true );
	$categories  = get_the_terms( $post_id, 'lis_listing_category' );
	$category    = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;
	$is_open     = lis_directory_is_listing_open_now( $post_id );
	$featured    = (bool) get_post_meta( $post_id, '_lis_listing_featured', true );
	$verified    = (bool) get_post_meta( $post_id, '_lis_listing_verified', true );
	$popular     = lis_directory_is_listing_popular( $post_id );
	$avg_rating  = lis_directory_get_listing_average_rating( $post_id );

	ob_start();
	?>
	<a class="lis-listing-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>" <?php echo $address ? 'data-address="' . esc_attr( $address ) . '" data-title="' . esc_attr( get_the_title( $post ) ) . '"' : ''; ?>>
		<?php // B25: every card has an image — the photo, or the branded fallback (badges overlay either). ?>
		<div class="lis-listing-card-thumb-wrap">
			<?php if ( has_post_thumbnail( $post ) ) : ?>
				<?php echo get_the_post_thumbnail( $post, 'medium', array( 'class' => 'lis-listing-card-thumb', 'alt' => lis_directory_listing_image_alt( get_post_thumbnail_id( $post ), $post ) ) ); ?>
			<?php else : ?>
				<?php echo lis_directory_render_listing_fallback_image( $post, 'card' ); // phpcs:ignore -- escaped inside helper. ?>
			<?php endif; ?>
			<?php if ( $featured || $popular || $verified ) : ?>
				<div class="lis-listing-card-thumb-badges lis-listing-card-thumb-badges--left">
					<?php if ( $featured ) : ?><span class="lis-listing-badge lis-listing-badge--featured">Featured</span><?php endif; ?>
					<?php if ( $popular ) : ?><span class="lis-listing-badge lis-listing-badge--popular">Popular</span><?php endif; ?>
					<?php if ( $verified ) : ?><span class="lis-listing-badge lis-listing-badge--verified">✓ Verified</span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $category ) : ?>
				<div class="lis-listing-card-thumb-badges lis-listing-card-thumb-badges--right">
					<span class="lis-listing-category-badge"><?php echo esc_html( $category->name ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="lis-listing-card-body">
			<div class="lis-listing-card-title-row">
				<p class="lis-listing-card-title"><?php echo esc_html( get_the_title( $post ) ); ?></p>
				<?php if ( null !== $is_open ) : ?>
					<span class="lis-listing-open-status lis-listing-open-status--sm <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $avg_rating ) : ?>
				<div class="lis-listing-card-meta-row">
					<span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( ( 'real-estate-sale' === $type || 'real-estate-rent' === $type ) && ( $bedrooms || $bathrooms || $sqft ) ) : ?>
				<p class="lis-listing-card-facts">
					<?php echo esc_html( implode( ' · ', array_filter( array(
						$bedrooms ? $bedrooms . ' bd' : '',
						$bathrooms ? $bathrooms . ' ba' : '',
						$sqft ? number_format_i18n( (int) $sqft ) . ' sqft' : '',
					) ) ) ); ?>
				</p>
			<?php elseif ( 'job-listing' === $type && $salary ) : ?>
				<p class="lis-listing-card-facts lis-listing-card-facts--salary"><?php echo esc_html( $salary ); ?></p>
			<?php endif; ?>
			<p class="lis-listing-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 16 ) ); ?></p>
		</div>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * URL of the Add Listing page (0.54.0): the page at
 * /services/local-directory/add-listing/, else the first published page
 * holding [lis_listing_submit]. Filter: lis_directory_add_listing_url.
 */
function lis_directory_add_listing_url() {
	static $cached = null;
	if ( null === $cached ) {
		$page = get_page_by_path( 'services/local-directory/add-listing' );
		if ( $page && 'publish' === $page->post_status ) {
			$cached = get_permalink( $page );
		} else {
			$found  = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => '[lis_listing_submit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			$cached = $found ? get_permalink( $found[0] ) : '';
		}
	}
	return (string) apply_filters( 'lis_directory_add_listing_url', $cached );
}

/**
 * [lis_saved_listings] — the account "Saved" tab (0.54.0). Lists the
 * listings the user bookmarked with the Save button on a listing page
 * (user meta _lis_listing_bookmarks, see listings-actions.php), newest save
 * first, each with an Unsave button. Unsave is a plain nonce'd POST so it
 * works without JavaScript. Bookmarks pointing at listings that are no longer
 * published are skipped (and pruned from the list on the next unsave).
 */
function lis_directory_render_saved_listings_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-listing-dashboard-login-required">
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to see your saved listings.
		</p>
		<?php
		return ob_get_clean();
	}

	$ids   = array_reverse( lis_directory_get_user_bookmarks( get_current_user_id() ) );
	$posts = $ids ? get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'post__in'       => $ids,
		'orderby'        => 'post__in',
		'posts_per_page' => -1,
	) ) : array();

	$browse_url = apply_filters( 'lis_directory_browse_url', home_url( '/services/business-directory/' ) );
	$unsaved    = isset( $_GET['lis_unsaved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$back       = remove_query_arg( 'lis_unsaved' );

	ob_start();
	?>
	<div class="lis-listing-dashboard lis-saved-listings">
		<?php if ( $unsaved ) : ?>
			<p class="lis-saved-notice" role="status">Removed from your saved listings.</p>
		<?php endif; ?>
		<?php if ( empty( $posts ) ) : ?>
			<div class="lis-account-empty">
				<p class="lis-listing-empty">You haven't saved any listings yet. Tap <strong>☆ Save</strong> on a listing to keep it here.</p>
				<p class="lis-account-empty-actions"><a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $browse_url ); ?>">Browse the directory</a></p>
			</div>
		<?php else : ?>
			<div class="lis-listing-grid lis-saved-grid">
				<?php foreach ( $posts as $saved ) : ?>
					<div class="lis-saved-item">
						<?php echo lis_directory_render_listing_card( $saved ); // phpcs:ignore -- escaped inside the renderer. ?>
						<form class="lis-saved-unsave" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="lis_directory_unsave_listing">
							<input type="hidden" name="listing_id" value="<?php echo esc_attr( $saved->ID ); ?>">
							<input type="hidden" name="redirect_to" value="<?php echo esc_url( $back ); ?>">
							<?php wp_nonce_field( 'lis_directory_unsave_' . $saved->ID, 'lis_directory_unsave_nonce' ); ?>
							<button type="submit" class="lis-listing-thankyou-btn lis-listing-thankyou-btn--ghost lis-saved-unsave-btn" aria-label="<?php echo esc_attr( 'Unsave ' . get_the_title( $saved ) ); ?>">Unsave</button>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function lis_directory_handle_unsave_listing() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to do that.' );
	}

	$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
	if ( ! isset( $_POST['lis_directory_unsave_nonce'] ) || ! wp_verify_nonce( $_POST['lis_directory_unsave_nonce'], 'lis_directory_unsave_' . $listing_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}

	$user_id   = get_current_user_id();
	$bookmarks = lis_directory_get_user_bookmarks( $user_id );
	// Also prune bookmarks to listings that no longer exist or aren't public.
	$bookmarks = array_values( array_filter( $bookmarks, function ( $id ) use ( $listing_id ) {
		return $id !== $listing_id && 'publish' === get_post_status( $id );
	} ) );
	update_user_meta( $user_id, '_lis_listing_bookmarks', $bookmarks );

	$fallback = home_url( '/' );
	$back     = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), $fallback ) : $fallback;
	wp_safe_redirect( add_query_arg( 'lis_unsaved', '1', $back ) );
	exit;
}

/**
 * URL of an account sub-page (/account/<slug>/) if that page is published,
 * else ''. Filter: lis_directory_account_page_url.
 */
function lis_directory_account_page_url( $slug ) {
	$page = get_page_by_path( 'account/' . $slug );
	$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	return (string) apply_filters( 'lis_directory_account_page_url', $url, $slug );
}

/**
 * [lis_account_overview] — the account Overview tab (0.54.0). Replaces
 * [wpum_account] on the Overview page, which rendered WP User Manager's
 * profile Settings form there.
 *
 * Shows counts of the user's events, listings and saved listings (each
 * linking to its tab when that page exists) plus "Submit an event" and
 * "Add a listing" buttons. WP User Manager's own screens still work from the
 * same page: when WPUM's `tab` query var is set (/account/password/,
 * /account/settings/ via WPUM routing, …) or ?lis_view=profile is present,
 * this renders [wpum_account] instead, so profile and password editing stay
 * reachable from the "Edit profile & password" link.
 */
function lis_directory_render_account_overview_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	$wpum_tab     = (string) get_query_var( 'tab' );
	$want_profile = isset( $_GET['lis_view'] ) && 'profile' === sanitize_key( wp_unslash( $_GET['lis_view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ( '' !== $wpum_tab || $want_profile ) && shortcode_exists( 'wpum_account' ) ) {
		$back = remove_query_arg( 'lis_view' );
		return '<p class="lis-account-overview-back"><a href="' . esc_url( $want_profile ? $back : get_permalink() ) . '">&larr; Back to Overview</a></p>' . do_shortcode( '[wpum_account]' );
	}

	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<p class="lis-listing-dashboard-login-required">
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to see your account.
		</p>
		<?php
		return ob_get_clean();
	}

	$user    = wp_get_current_user();
	$user_id = (int) $user->ID;

	$listing_count = count( get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => array( 'publish', 'pending', 'draft' ),
		'author'         => $user_id,
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) ) );

	$bookmarks   = lis_directory_get_user_bookmarks( $user_id );
	$saved_count = $bookmarks ? count( get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'post__in'       => $bookmarks,
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) ) ) : 0;

	$events_active = function_exists( 'lis_events_get_user_events' );
	$event_count   = $events_active ? count( lis_events_get_user_events( $user_id ) ) : 0;
	$submit_event  = function_exists( 'lis_events_find_submit_page_url' ) ? lis_events_find_submit_page_url() : '';
	$add_listing   = lis_directory_add_listing_url();
	$profile_url   = add_query_arg( 'lis_view', 'profile', get_permalink() );

	$stats = array();
	if ( $events_active ) {
		$stats[] = array( 'My events', $event_count, lis_directory_account_page_url( 'events' ) );
	}
	$stats[] = array( 'My listings', $listing_count, lis_directory_account_page_url( 'listings' ) );
	$stats[] = array( 'Saved', $saved_count, lis_directory_account_page_url( 'saved' ) );

	$name = trim( $user->first_name ) !== '' ? $user->first_name : $user->display_name;

	ob_start();
	?>
	<div class="lis-account-overview">
		<h1 class="lis-account-overview-title">Hi, <?php echo esc_html( $name ); ?></h1>

		<?php if ( function_exists( 'lis_account_email_confirmed' ) && ! lis_account_email_confirmed( $user_id ) ) : ?>
			<?php echo lis_account_confirm_notice_html( 'events, listings or job posts' ); // phpcs:ignore -- escaped inside. ?>
		<?php endif; ?>

		<div class="lis-account-stats">
			<?php foreach ( $stats as $stat ) : ?>
				<?php $tag = $stat[2] ? 'a' : 'div'; ?>
				<<?php echo esc_html( $tag ); ?> class="lis-account-stat"<?php if ( $stat[2] ) : ?> href="<?php echo esc_url( $stat[2] ); ?>"<?php endif; ?>>
					<span class="lis-account-stat-num"><?php echo esc_html( number_format_i18n( $stat[1] ) ); ?></span>
					<span class="lis-account-stat-label"><?php echo esc_html( $stat[0] ); ?></span>
				</<?php echo esc_html( $tag ); ?>>
			<?php endforeach; ?>
		</div>

		<div class="lis-account-actions">
			<?php if ( $submit_event ) : ?>
				<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $submit_event ); ?>">Submit an event</a>
			<?php endif; ?>
			<?php if ( $add_listing ) : ?>
				<a class="lis-listing-thankyou-btn" href="<?php echo esc_url( $add_listing ); ?>">Add a listing</a>
			<?php endif; ?>
		</div>

		<p class="lis-account-overview-profile">
			<?php if ( shortcode_exists( 'wpum_account' ) ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>">Edit profile &amp; password</a>
				<span class="lis-account-overview-sep" aria-hidden="true">&middot;</span>
			<?php endif; ?>
			<a class="lis-account-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

