<?php
/**
 * Three more Directorist-parity pieces that don't fit anywhere else (see
 * DIRECTORIST_PARITY_PLAN.md phase 3): Compare Listings, a public
 * Author/Vendor Profile page, and a logged-in Dashboard of "my listings".
 *
 * Dashboard scope note: this does NOT include a front-end edit form. The
 * existing [lis_listing_submit] form requires a fresh photo upload on every
 * submit (reasonable for a first submission, wrong for an edit — nobody
 * should have to re-upload photos just to fix a typo), and building a real
 * pre-filled edit variant is a substantial separate piece of work, not
 * something to bolt on in the time left for a first pass at this phase.
 * The Dashboard instead shows each listing's status plus a wp-admin Edit
 * link when the current user's role actually has edit_post capability for
 * it (Author/Contributor+, not the default Subscriber most front-end
 * registrants get) — honest about what it does and doesn't do rather than
 * shipping a half-working edit form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIS_DIRECTORY_COMPARE_MAX', 4 );

add_shortcode( 'lis_listing_compare', 'lis_directory_render_compare_shortcode' );
add_shortcode( 'lis_listing_author_profile', 'lis_directory_render_author_profile_shortcode' );
add_shortcode( 'lis_listing_dashboard', 'lis_directory_render_dashboard_shortcode' );
add_action( 'wp', 'lis_directory_enqueue_compare_on_listing_views' );

/**
 * The "+ Compare" button also renders on the archive/single templates
 * (outside any of this file's shortcodes), so the JS that makes it work
 * needs to load there too — not just when one of the three shortcodes
 * above is used. Hooked on `wp` (query is resolved by then, `wp_enqueue_scripts`
 * hasn't fired yet) rather than a blanket `wp_enqueue_scripts` callback, so
 * pages with none of this plugin's content don't pay for an unused script.
 */
function lis_directory_enqueue_compare_on_listing_views() {
	if ( is_singular( 'lis_listing' ) || is_post_type_archive( 'lis_listing' ) || is_tax( 'lis_listing_category' ) ) {
		lis_directory_enqueue_compare_assets();
	}
}

function lis_directory_enqueue_compare_assets() {
	wp_enqueue_script( 'lis-directory-compare', LIS_DIRECTORY_URL . 'assets/js/listing-compare.js', array(), LIS_DIRECTORY_VERSION, true );
	wp_localize_script( 'lis-directory-compare', 'lisDirectoryCompare', array(
		'max' => LIS_DIRECTORY_COMPARE_MAX,
	) );
}

/**
 * Reads the compare cookie set client-side by assets/js/listing-compare.js
 * — a plain browser cookie rather than a logged-in-only DB record, since
 * Directorist's own Compare Listings works for anonymous visitors too.
 */
function lis_directory_get_compare_ids() {
	if ( empty( $_COOKIE['lis_listing_compare'] ) ) {
		return array();
	}
	$raw = json_decode( wp_unslash( $_COOKIE['lis_listing_compare'] ), true );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	return array_slice( array_filter( array_map( 'absint', $raw ) ), 0, LIS_DIRECTORY_COMPARE_MAX );
}

function lis_directory_render_compare_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	lis_directory_enqueue_compare_assets();

	$ids = lis_directory_get_compare_ids();

	if ( empty( $ids ) ) {
		return '<p class="lis-listing-compare-empty">No listings selected to compare yet — use the "+ Compare" button on any listing.</p>';
	}

	$posts = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'post__in'       => $ids,
		'orderby'        => 'post__in',
		'posts_per_page' => LIS_DIRECTORY_COMPARE_MAX,
	) );

	if ( empty( $posts ) ) {
		return '<p class="lis-listing-compare-empty">Those listings are no longer available.</p>';
	}

	ob_start();
	?>
	<div class="lis-listing-compare-wrap">
		<table class="lis-listing-compare-table">
			<tbody>
				<tr class="lis-listing-compare-row-photo">
					<th>&nbsp;</th>
					<?php foreach ( $posts as $post ) : ?>
						<td>
							<?php if ( has_post_thumbnail( $post ) ) : ?>
								<?php echo get_the_post_thumbnail( $post, 'medium' ); ?>
							<?php endif; ?>
							<div class="lis-listing-compare-title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></div>
							<button type="button" class="lis-listing-compare-remove" data-id="<?php echo (int) $post->ID; ?>">Remove</button>
						</td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Directory</th>
					<?php foreach ( $posts as $post ) : $types = lis_directory_get_listing_types(); ?>
						<td><?php echo esc_html( $types[ lis_directory_get_listing_type( $post->ID ) ] ); ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Category</th>
					<?php foreach ( $posts as $post ) : $terms = get_the_terms( $post, 'lis_listing_category' ); ?>
						<td><?php echo esc_html( ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '—' ); ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Price</th>
					<?php foreach ( $posts as $post ) : $price = get_post_meta( $post->ID, '_lis_listing_price', true ); ?>
						<td><?php echo esc_html( $price ? $price : '—' ); ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Rating</th>
					<?php foreach ( $posts as $post ) : $rating = lis_directory_get_listing_average_rating( $post->ID ); ?>
						<td><?php echo $rating ? esc_html( lis_directory_render_stars( $rating ) . ' ' . $rating ) : '—'; ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Address</th>
					<?php foreach ( $posts as $post ) : $address = get_post_meta( $post->ID, '_lis_listing_address', true ); ?>
						<td><?php echo esc_html( $address ? $address : '—' ); ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Features</th>
					<?php foreach ( $posts as $post ) : $features = get_the_terms( $post, 'lis_listing_feature' ); ?>
						<td><?php echo esc_html( ( $features && ! is_wp_error( $features ) ) ? implode( ', ', wp_list_pluck( $features, 'name' ) ) : '—' ); ?></td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}

function lis_directory_render_author_profile_shortcode() {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	lis_directory_enqueue_compare_assets();

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

	ob_start();
	?>
	<div class="lis-listing-dashboard">
		<h1>My Listings</h1>
		<?php if ( empty( $listings ) ) : ?>
			<p class="lis-listing-empty">You haven't submitted any listings yet.</p>
		<?php else : ?>
			<table class="lis-listing-dashboard-table">
				<thead>
					<tr>
						<th>Listing</th>
						<th>Directory</th>
						<th>Status</th>
						<th>Submitted</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $listings as $listing ) :
						$types  = lis_directory_get_listing_types();
						$status = isset( $status_labels[ $listing->post_status ] ) ? $status_labels[ $listing->post_status ] : $listing->post_status;
						?>
						<tr>
							<td><?php echo esc_html( get_the_title( $listing ) ); ?></td>
							<td><?php echo esc_html( $types[ lis_directory_get_listing_type( $listing->ID ) ] ); ?></td>
							<td><span class="lis-listing-dashboard-status lis-listing-dashboard-status--<?php echo esc_attr( $listing->post_status ); ?>"><?php echo esc_html( $status ); ?></span></td>
							<td><?php echo esc_html( get_the_date( '', $listing ) ); ?></td>
							<td>
								<?php if ( 'publish' === $listing->post_status ) : ?>
									<a href="<?php echo esc_url( get_permalink( $listing ) ); ?>">View</a>
								<?php endif; ?>
								<?php if ( current_user_can( 'edit_post', $listing->ID ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>">Edit</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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
	<a class="lis-listing-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<?php echo get_the_post_thumbnail( $post, 'medium', array( 'class' => 'lis-listing-card-thumb' ) ); ?>
		<?php endif; ?>
		<div class="lis-listing-card-body">
			<div class="lis-listing-card-title-row">
				<p class="lis-listing-card-title"><?php echo esc_html( get_the_title( $post ) ); ?></p>
				<?php if ( null !== $is_open ) : ?>
					<span class="lis-listing-open-status lis-listing-open-status--sm <?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></span>
				<?php endif; ?>
			</div>
			<div class="lis-listing-card-meta-row">
				<?php if ( $featured ) : ?><span class="lis-listing-badge lis-listing-badge--featured">Featured</span><?php endif; ?>
				<?php if ( $popular ) : ?><span class="lis-listing-badge lis-listing-badge--popular">Popular</span><?php endif; ?>
				<?php if ( $verified ) : ?><span class="lis-listing-badge lis-listing-badge--verified">✓ Verified</span><?php endif; ?>
				<?php if ( $category ) : ?><span class="lis-listing-category-badge"><?php echo esc_html( $category->name ); ?></span><?php endif; ?>
				<?php if ( $avg_rating ) : ?><span class="lis-listing-rating-summary"><?php echo esc_html( lis_directory_render_stars( $avg_rating ) ); ?></span><?php endif; ?>
			</div>
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
			<button type="button" class="lis-listing-compare-btn" data-id="<?php echo (int) $post_id; ?>">+ Compare</button>
		</div>
	</a>
	<?php
	return ob_get_clean();
}
