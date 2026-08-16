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

	$current = (bool) get_post_meta( $listing_id, '_lis_listing_sold', true );
	update_post_meta( $listing_id, '_lis_listing_sold', ! $current );

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

	$atts  = shortcode_atts( array( 'type' => '', 'count' => 12, 'search' => 'auto' ), $atts, 'lis_listing_grid' );
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
		'posts_per_page' => max( 1, (int) $atts['count'] ),
	);

	if ( $type ) {
		$query_args['meta_query'] = array( array( 'key' => '_lis_listing_type', 'value' => $type ) );
	}

	if ( ! empty( $_GET['lis_sort'] ) ) {
		$sort_options = lis_directory_get_sort_options();
		$sort         = sanitize_key( wp_unslash( $_GET['lis_sort'] ) );
		if ( isset( $sort_options[ $sort ] ) ) {
			$query_args['orderby'] = $sort_options[ $sort ]['orderby'];
			$query_args['order']   = $sort_options[ $sort ]['order'];
		}
	}

	$listings     = get_posts( $query_args );
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
			<p class="lis-listing-empty">You haven't submitted any listings yet.</p>
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
									<a href="<?php echo esc_url( $showcase_url ); ?>">Join the Vendor Showcase</a>
								<?php elseif ( $is_showcase ) : ?>
									<span class="lis-listing-badge lis-listing-badge--featured">Vendor Showcase</span>
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
	<?php $has_thumb = has_post_thumbnail( $post ); ?>
	<a class="lis-listing-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>" <?php echo $address ? 'data-address="' . esc_attr( $address ) . '" data-title="' . esc_attr( get_the_title( $post ) ) . '"' : ''; ?>>
		<?php if ( $has_thumb ) : ?>
			<div class="lis-listing-card-thumb-wrap">
				<?php echo get_the_post_thumbnail( $post, 'medium', array( 'class' => 'lis-listing-card-thumb' ) ); ?>
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
		<?php endif; ?>
		<div class="lis-listing-card-body">
			<div class="lis-listing-card-title-row">
				<p class="lis-listing-card-title"><?php echo esc_html( get_the_title( $post ) ); ?></p>
				<?php if ( null !== $is_open ) : ?>
					<span class="lis-listing-open-status lis-listing-open-status--sm <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></span>
				<?php endif; ?>
			</div>
			<?php if ( ! $has_thumb || $avg_rating ) : ?>
				<div class="lis-listing-card-meta-row">
					<?php if ( ! $has_thumb ) : ?>
						<?php if ( $featured ) : ?><span class="lis-listing-badge lis-listing-badge--featured">Featured</span><?php endif; ?>
						<?php if ( $popular ) : ?><span class="lis-listing-badge lis-listing-badge--popular">Popular</span><?php endif; ?>
						<?php if ( $verified ) : ?><span class="lis-listing-badge lis-listing-badge--verified">✓ Verified</span><?php endif; ?>
						<?php if ( $category ) : ?><span class="lis-listing-category-badge"><?php echo esc_html( $category->name ); ?></span><?php endif; ?>
					<?php endif; ?>
					<?php if ( $avg_rating ) : ?><span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?></span><?php endif; ?>
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
