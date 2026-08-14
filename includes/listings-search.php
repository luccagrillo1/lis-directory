<?php
/**
 * [lis_listing_search] — the search/filter widget Directorist has that
 * `lis_listing` didn't (see DIRECTORIST_PARITY_PLAN.md phase 2b). Keyword,
 * directory type, category, price tier, open-now, and features.
 *
 * Deliberately GET-based, no AJAX, no separate "search results" page: the
 * form submits straight to the `lis_listing` archive (or a
 * `lis_listing_category` term archive) and a `pre_get_posts` hook applies
 * the filters to that same query. Reuses `templates/archive-listing.php`
 * as-is — one results template instead of two, and it works with
 * JavaScript disabled since it's a plain form GET.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_listing_search', 'lis_directory_render_search_form_shortcode' );
add_action( 'pre_get_posts', 'lis_directory_apply_search_filters' );

/**
 * Price is free text on `lis_listing` (matches how Directorist's own
 * listings on this site are priced — "$", "$$", etc. rather than a strict
 * number), so filtering is an exact-tier match against that same
 * convention, not a numeric range slider.
 */
function lis_directory_get_price_tiers() {
	return array(
		'$'    => '$',
		'$$'   => '$$',
		'$$$'  => '$$$',
		'$$$$' => '$$$$',
	);
}

/**
 * "Highest Rated" isn't here — ratings are computed on the fly from
 * approved comments (lis_directory_get_listing_average_rating()), not
 * stored as sortable post meta, so WP_Query can't ORDER BY it without a
 * denormalized rating meta field kept in sync on every new review. A
 * reasonable follow-up if it turns out to matter, not something to fake
 * with a wrong sort.
 */
function lis_directory_get_sort_options() {
	return array(
		'newest'     => array(
			'label'   => 'Newest',
			'orderby' => 'date',
			'order'   => 'DESC',
		),
		'oldest'     => array(
			'label'   => 'Oldest',
			'orderby' => 'date',
			'order'   => 'ASC',
		),
		'title_asc'  => array(
			'label'   => 'A → Z',
			'orderby' => 'title',
			'order'   => 'ASC',
		),
		'title_desc' => array(
			'label'   => 'Z → A',
			'orderby' => 'title',
			'order'   => 'DESC',
		),
	);
}

function lis_directory_render_search_form_shortcode( $atts ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-search', LIS_DIRECTORY_URL . 'assets/js/listing-search.js', array(), LIS_DIRECTORY_VERSION, true );

	$atts = shortcode_atts( array( 'redirect' => '', 'directory' => '' ), $atts, 'lis_listing_search' );

	$action = $atts['redirect'] ? $atts['redirect'] : get_post_type_archive_link( 'lis_listing' );

	// `directory="local-business"` (any key from lis_directory_get_listing_types)
	// locks this form to one directory type: the "All Directories" dropdown is
	// replaced by a hidden field, so it's a single-directory search box — e.g.
	// [lis_listing_search directory="local-business"] for the Local Directory.
	$listing_types = lis_directory_get_listing_types();
	$locked_type   = isset( $listing_types[ $atts['directory'] ] ) ? $atts['directory'] : '';

	$current_q       = isset( $_GET['lis_q'] ) ? sanitize_text_field( wp_unslash( $_GET['lis_q'] ) ) : '';
	$current_cat     = isset( $_GET['lis_category'] ) ? sanitize_title( wp_unslash( $_GET['lis_category'] ) ) : '';
	$current_price   = isset( $_GET['lis_price'] ) ? sanitize_text_field( wp_unslash( $_GET['lis_price'] ) ) : '';
	$current_open    = ! empty( $_GET['lis_open_now'] );
	$current_features = isset( $_GET['lis_feature'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_GET['lis_feature'] ) ) : array();

	$categories = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false, 'parent' => 0 ) );
	$features   = lis_directory_get_public_feature_terms();

	ob_start();
	?>
	<form class="lis-listing-search-form" method="get" action="<?php echo esc_url( $action ); ?>">
		<div class="lis-listing-search-row">
			<input type="text" name="lis_q" class="lis-listing-search-input" placeholder="What are you looking for?" value="<?php echo esc_attr( $current_q ); ?>" />

			<?php if ( $locked_type ) : ?>
				<input type="hidden" name="lis_type" value="<?php echo esc_attr( $locked_type ); ?>" />
			<?php endif; ?>
			<?php // No visible directory selector, by request — locked-type pages (directory="…") still filter via the hidden field above. ?>

			<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
				<select name="lis_category" class="lis-listing-search-select">
					<option value="">All Categories</option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $current_cat, $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<button type="submit" class="lis-listing-search-submit">Search Listings</button>
		</div>

		<details class="lis-listing-search-more">
			<summary>More Filters</summary>
			<div class="lis-listing-search-more-body">
				<div class="lis-listing-search-field">
					<label><input type="checkbox" name="lis_open_now" value="1" <?php checked( $current_open ); ?> /> Open Now</label>
				</div>

				<?php
				// Price filter removed for now (by request) — lis_directory_get_price_tiers()
				// and the lis_price query-filtering logic in lis_directory_apply_search_filters()
				// are left intact, so this can come back by flipping this to true.
				if ( false ) :
					?>
					<div class="lis-listing-search-field">
						<span class="lis-listing-search-field-label">Price</span>
						<?php foreach ( lis_directory_get_price_tiers() as $value => $label ) : ?>
							<label class="lis-listing-search-radio">
								<input type="radio" name="lis_price" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current_price, $value ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<label class="lis-listing-search-radio">
							<input type="radio" name="lis_price" value="" <?php checked( $current_price, '' ); ?> /> Any
						</label>
					</div>
				<?php endif; ?>

				<?php if ( ! is_wp_error( $features ) && ! empty( $features ) ) : ?>
					<div class="lis-listing-search-field" data-feature-field>
						<span class="lis-listing-search-field-label">Features</span>
						<div class="lis-listing-search-checkbox-grid" data-feature-checkboxes>
							<?php foreach ( $features as $feature ) : ?>
								<label class="lis-listing-search-checkbox">
									<input type="checkbox" name="lis_feature[]" value="<?php echo esc_attr( $feature->slug ); ?>" data-feature-name="<?php echo esc_attr( $feature->name ); ?>" <?php checked( in_array( $feature->slug, $current_features, true ) ); ?> />
									<?php echo esc_html( $feature->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="lis-listing-search-field">
					<a class="lis-listing-search-reset" href="<?php echo esc_url( $action ); ?>">Reset Filters</a>
				</div>
			</div>
		</details>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Grid/List/Map view toggle + Sort By, rendered just above the results
 * grid in templates/archive-listing.php. View is a pure display
 * preference (which layout to show the SAME results in) — handled
 * entirely client-side via a CSS class + localStorage, no query param,
 * since it doesn't change what's being queried. Sort changes the actual
 * query, so it's a real GET form (works with JS off; the onchange
 * auto-submit is just a convenience) that preserves every other active
 * filter param as a hidden field.
 */
function lis_directory_render_listing_toolbar() {
	$sort_options = lis_directory_get_sort_options();
	$current_sort = isset( $_GET['lis_sort'] ) && isset( $sort_options[ sanitize_key( wp_unslash( $_GET['lis_sort'] ) ) ] )
		? sanitize_key( wp_unslash( $_GET['lis_sort'] ) )
		: 'newest';
	$maps_api_key = get_option( 'lis_directory_google_maps_api_key' );
	?>
	<div class="lis-listing-toolbar">
		<div class="lis-listing-view-toggle" role="group" aria-label="View">
			<button type="button" class="lis-listing-view-btn is-active" data-view="grid" aria-label="Grid view" title="Grid view">
				<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="6" height="6" rx="1"/><rect x="12" y="2" width="6" height="6" rx="1"/><rect x="2" y="12" width="6" height="6" rx="1"/><rect x="12" y="12" width="6" height="6" rx="1"/></svg>
			</button>
			<button type="button" class="lis-listing-view-btn" data-view="list" aria-label="List view" title="List view">
				<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><line x1="3" y1="5" x2="17" y2="5"/><line x1="3" y1="10" x2="17" y2="10"/><line x1="3" y1="15" x2="17" y2="15"/></svg>
			</button>
			<?php if ( $maps_api_key ) : ?>
				<button type="button" class="lis-listing-view-btn" data-view="map" aria-label="Map view" title="Map view">
					<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M7 3 2 5v12l5-2 6 2 5-2V3l-5 2-6-2Z"/><line x1="7" y1="3" x2="7" y2="15"/><line x1="13" y1="5" x2="13" y2="17"/></svg>
				</button>
			<?php endif; ?>
		</div>

		<form method="get" class="lis-listing-sort-form">
			<?php foreach ( $_GET as $key => $value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, re-emitted as hidden fields to preserve filter state, no state change on this request.
				if ( 'lis_sort' === $key ) {
					continue;
				}
				foreach ( (array) $value as $v ) :
					?>
					<input type="hidden" name="<?php echo esc_attr( is_array( $value ) ? $key . '[]' : $key ); ?>" value="<?php echo esc_attr( is_array( $v ) ? '' : $v ); ?>" />
				<?php endforeach; ?>
			<?php endforeach; ?>
			<label class="lis-listing-sort-label">
				Sort By
				<select name="lis_sort" onchange="this.form.submit()">
					<?php foreach ( $sort_options as $value => $option ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_sort, $value ); ?>><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<noscript><button type="submit" class="lis-listing-sort-submit">Apply</button></noscript>
		</form>
	</div>
	<?php
}

/**
 * Only touches the real, public, main query — never wp-admin, never a
 * secondary/widget query — and only when one of this plugin's own filter
 * params is actually present, so a plain visit to `/listings/` with no
 * query string behaves exactly as it did before this shortcode existed.
 */
function lis_directory_apply_search_filters( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'lis_listing' ) && ! $query->is_tax( 'lis_listing_category' ) ) {
		return;
	}

	$has_any_filter = isset( $_GET['lis_q'] ) || isset( $_GET['lis_type'] ) || isset( $_GET['lis_category'] )
		|| isset( $_GET['lis_price'] ) || isset( $_GET['lis_open_now'] ) || isset( $_GET['lis_feature'] )
		|| isset( $_GET['lis_sort'] );
	if ( ! $has_any_filter ) {
		return;
	}

	if ( ! empty( $_GET['lis_sort'] ) ) {
		$sort_options = lis_directory_get_sort_options();
		$sort         = sanitize_key( wp_unslash( $_GET['lis_sort'] ) );
		if ( isset( $sort_options[ $sort ] ) ) {
			$query->set( 'orderby', $sort_options[ $sort ]['orderby'] );
			$query->set( 'order', $sort_options[ $sort ]['order'] );
		}
	}

	if ( ! empty( $_GET['lis_q'] ) ) {
		$query->set( 's', sanitize_text_field( wp_unslash( $_GET['lis_q'] ) ) );
	}

	$tax_query = array();

	if ( ! empty( $_GET['lis_category'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'lis_listing_category',
			'field'    => 'slug',
			'terms'    => sanitize_title( wp_unslash( $_GET['lis_category'] ) ),
		);
	}

	if ( ! empty( $_GET['lis_feature'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'lis_listing_feature',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', (array) wp_unslash( $_GET['lis_feature'] ) ),
		);
	}

	if ( ! empty( $tax_query ) ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$query->set( 'tax_query', $tax_query );
	}

	$meta_query = array();

	if ( ! empty( $_GET['lis_type'] ) ) {
		$types = lis_directory_get_listing_types();
		$type  = sanitize_key( wp_unslash( $_GET['lis_type'] ) );
		if ( isset( $types[ $type ] ) ) {
			$meta_query[] = array(
				'key'   => '_lis_listing_type',
				'value' => $type,
			);
		}
	}

	if ( ! empty( $_GET['lis_price'] ) ) {
		$price = sanitize_text_field( wp_unslash( $_GET['lis_price'] ) );
		if ( isset( lis_directory_get_price_tiers()[ $price ] ) ) {
			$meta_query[] = array(
				'key'   => '_lis_listing_price',
				'value' => $price,
			);
		}
	}

	if ( ! empty( $meta_query ) ) {
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}
		$query->set( 'meta_query', $meta_query );
	}
}

/**
 * "Open now" isn't a single stored value to meta_query against — it's
 * derived at request time from the day-by-day hours meta (see
 * lis_directory_is_listing_open_now() in listings-hours.php), the same way
 * the single/archive templates already compute it for display. Rather than
 * duplicate that logic in SQL, filter the already-fetched post list in
 * `the_posts` instead of trying to push it into WHERE.
 */
add_filter( 'the_posts', 'lis_directory_filter_the_posts_open_now', 10, 2 );

function lis_directory_filter_the_posts_open_now( $posts, $query ) {
	if ( is_admin() || ! $query->is_main_query() || empty( $_GET['lis_open_now'] ) ) {
		return $posts;
	}
	if ( ! $query->is_post_type_archive( 'lis_listing' ) && ! $query->is_tax( 'lis_listing_category' ) ) {
		return $posts;
	}
	return array_values( array_filter( $posts, function ( $post ) {
		return true === lis_directory_is_listing_open_now( $post->ID );
	} ) );
}
