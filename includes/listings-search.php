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
add_action( 'pre_get_posts', 'lis_directory_set_listings_page_size', PHP_INT_MAX );
add_filter( 'infinite_scroll_archive_supported', 'lis_directory_disable_infinite_scroll_on_listings', 99 );
add_filter( 'posts_search', 'lis_directory_loose_posts_search', 10, 2 );
add_action( 'pre_get_posts', 'lis_directory_loose_site_search' );

/**
 * The site-wide header search (?s=) gets the same apostrophe-insensitive
 * matching, so "eichardts" finds Eichardt's there too. Front end only.
 */
function lis_directory_loose_site_search( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		$query->set( 'lis_loose_search', true );
	}
}

/**
 * Apostrophes characters stripped from both the keyword and the listing text,
 * so "eichardts", "eichardt's" and "eichardt’s" (smart punctuation on
 * Apple keyboards) all find "Eichardt's". Includes the HTML entities that
 * can sit in saved content.
 */
function lis_directory_search_apostrophes() {
	return array( "'", "\u{2019}", "\u{2018}", "\u{02BC}", '`', "\u{00B4}", '&#8217;', '&#8216;', '&#039;', '&#39;', '&rsquo;', '&lsquo;' );
}

/**
 * Replaces core's keyword WHERE for listing searches with the same per-term
 * AND/OR shape (title, excerpt, content; "-term" excludes) but compares with
 * apostrophes removed on both sides. Uses core's own parsed search_terms.
 */
function lis_directory_loose_posts_search( $search, $query ) {
	if ( ! $query->get( 'lis_loose_search' ) ) {
		return $search;
	}
	$terms = (array) $query->get( 'search_terms' );
	if ( empty( $terms ) ) {
		return $search;
	}

	global $wpdb;
	$marks = lis_directory_search_apostrophes();

	$normalize = function ( $column ) use ( $wpdb, $marks ) {
		$sql = $column;
		foreach ( $marks as $mark ) {
			$sql = $wpdb->prepare( "REPLACE({$sql}, %s, '')", $mark ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column expression built here.
		}
		return $sql;
	};
	$columns = array(
		$normalize( "{$wpdb->posts}.post_title" ),
		$normalize( "{$wpdb->posts}.post_excerpt" ),
		$normalize( "{$wpdb->posts}.post_content" ),
	);

	$clauses = array();
	foreach ( $terms as $term ) {
		$exclude = ( strlen( $term ) > 1 && '-' === $term[0] );
		if ( $exclude ) {
			$term = substr( $term, 1 );
		}
		$term = trim( str_replace( $marks, '', $term ) );
		if ( '' === $term ) {
			continue;
		}
		$like  = '%' . $wpdb->esc_like( $term ) . '%';
		$op    = $exclude ? 'NOT LIKE' : 'LIKE';
		$glue  = $exclude ? ' AND ' : ' OR ';
		$parts = array();
		foreach ( $columns as $col ) {
			$parts[] = $wpdb->prepare( "{$col} {$op} %s", $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- normalized column expression.
		}
		$clauses[] = '(' . implode( $glue, $parts ) . ')';
	}
	if ( empty( $clauses ) ) {
		return $search;
	}

	$where = ' AND (' . implode( ' AND ', $clauses ) . ')';
	if ( ! is_user_logged_in() ) {
		$where .= " AND ({$wpdb->posts}.post_password = '')";
	}
	return $where;
}

/**
 * Jetpack Infinite Scroll is on for the site and claims the listings archive
 * too: it forces its own 10-per-page size onto the main query and swaps
 * numbered pages for a "More Listings" button. Listings use numbered,
 * crawlable pages (D15), so opt these archives out. Blog archives keep it.
 */
function lis_directory_disable_infinite_scroll_on_listings( $supported ) {
	if ( is_post_type_archive( 'lis_listing' ) || is_tax( array( 'lis_listing_category', 'lis_listing_feature' ) ) ) {
		return false;
	}
	return $supported;
}

/**
 * D15: listings per page on the archive, category archives and
 * [lis_listing_grid]. 36 = an even 2-, 3- or 4-column grid.
 */
define( 'LIS_DIRECTORY_LISTINGS_PER_PAGE', 36 );

/**
 * Numbered pages on the listings archive and `lis_listing_category` term
 * archives (D15 — it used to put all ~110 listings on one page). Separate
 * from lis_directory_apply_search_filters() since it applies with or without
 * a filter. "Open now" is the exception: that filter runs after the query
 * (lis_directory_filter_the_posts_open_now()), so paging it in SQL would give
 * short or empty pages — an open-now search stays on one page.
 */
function lis_directory_set_listings_page_size( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'lis_listing' ) && ! $query->is_tax( 'lis_listing_category' ) ) {
		return;
	}
	$per_page = empty( $_GET['lis_open_now'] ) ? LIS_DIRECTORY_LISTINGS_PER_PAGE : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param.
	$query->set( 'posts_per_page', $per_page );
	// WP_Query lets posts_per_archive_page override posts_per_page on archives.
	$query->set( 'posts_per_archive_page', $per_page );
}

/**
 * Plain numbered links (works without JS, crawlable). paginate_links() builds
 * on get_pagenum_link(), which keeps the current query string, so search,
 * category, feature and sort params carry across pages.
 */
function lis_directory_render_listing_pagination( $total_pages, $current_page ) {
	if ( $total_pages < 2 ) {
		return '';
	}
	$links = paginate_links( array(
		'total'     => (int) $total_pages,
		'current'   => max( 1, (int) $current_page ),
		'type'      => 'list',
		'mid_size'  => 1,
		'prev_text' => '<span aria-hidden="true">&larr;</span> Previous',
		'next_text' => 'Next <span aria-hidden="true">&rarr;</span>',
	) );
	return $links ? '<nav class="lis-listing-pagination" aria-label="Listings pages">' . $links . '</nav>' : '';
}

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
		// ID breaks ties: the Directorist migration published ~110 listings
		// with identical dates, and MySQL returns ties in no fixed order.
		'newest'     => array(
			'label'   => 'Newest',
			'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'order'   => 'DESC',
		),
		'oldest'     => array(
			'label'   => 'Oldest',
			'orderby' => array( 'date' => 'ASC', 'ID' => 'ASC' ),
			'order'   => 'ASC',
		),
		'title_asc'  => array(
			'label'   => 'A → Z',
			'orderby' => array( 'title' => 'ASC', 'ID' => 'ASC' ),
			'order'   => 'ASC',
		),
		'title_desc' => array(
			'label'   => 'Z → A',
			'orderby' => array( 'title' => 'DESC', 'ID' => 'DESC' ),
			'order'   => 'DESC',
		),
	);
}

/**
 * The sort the visitor picked (?lis_sort=), else Newest. Newest is applied
 * explicitly, keyword searches included (core would otherwise sort a search by
 * relevance while the Sort By box still says "Newest").
 */
function lis_directory_current_sort_key() {
	$options = lis_directory_get_sort_options();
	$sort    = isset( $_GET['lis_sort'] ) ? sanitize_key( wp_unslash( $_GET['lis_sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param.
	return isset( $options[ $sort ] ) ? $sort : 'newest';
}

/**
 * Empty-state text: a search that matched nothing says so, with the keyword.
 */
function lis_directory_listing_empty_message() {
	$f = lis_directory_listing_request_filters();
	if ( '' !== $f['s'] ) {
		return sprintf( 'No listings match "%s". Try a shorter word or a different category.', $f['s'] );
	}
	if ( $f['tax_query'] || $f['meta_query'] || $f['open_now'] ) {
		return 'No listings match these filters.';
	}
	return 'No listings here yet.';
}

function lis_directory_current_sort_option() {
	$options = lis_directory_get_sort_options();
	return $options[ lis_directory_current_sort_key() ];
}

/**
 * The [lis_listing_search] filters from the request, as WP_Query pieces.
 * Shared by the archive (lis_directory_apply_search_filters()) and
 * [lis_listing_grid] so both answer the same form the same way.
 */
function lis_directory_listing_request_filters() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params.
	$out = array(
		's'          => '',
		'tax_query'  => array(),
		'meta_query' => array(),
		'open_now'   => ! empty( $_GET['lis_open_now'] ),
	);
	if ( isset( $_GET['lis_q'] ) ) {
		$out['s'] = trim( sanitize_text_field( wp_unslash( $_GET['lis_q'] ) ) );
	}
	if ( ! empty( $_GET['lis_category'] ) ) {
		$out['tax_query'][] = array(
			'taxonomy' => 'lis_listing_category',
			'field'    => 'slug',
			'terms'    => sanitize_title( wp_unslash( $_GET['lis_category'] ) ),
		);
	}
	if ( ! empty( $_GET['lis_feature'] ) ) {
		$out['tax_query'][] = array(
			'taxonomy' => 'lis_listing_feature',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', (array) wp_unslash( $_GET['lis_feature'] ) ),
			'operator' => 'AND', // Every ticked feature, not just any one.
		);
	}
	if ( count( $out['tax_query'] ) > 1 ) {
		$out['tax_query']['relation'] = 'AND';
	}
	if ( ! empty( $_GET['lis_price'] ) ) {
		$price = sanitize_text_field( wp_unslash( $_GET['lis_price'] ) );
		if ( isset( lis_directory_get_price_tiers()[ $price ] ) ) {
			$out['meta_query'][] = array( 'key' => '_lis_listing_price', 'value' => $price );
		}
	}
	// phpcs:enable
	return $out;
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
		<?php
		// A GET form drops its action's own query string, so carry it as fields
		// (e.g. ?page_id=123 on a site without pretty permalinks).
		parse_str( (string) wp_parse_url( $action, PHP_URL_QUERY ), $action_args );
		foreach ( $action_args as $arg_key => $arg_value ) :
			if ( is_scalar( $arg_value ) && 0 !== strpos( $arg_key, 'lis_' ) ) :
				?>
				<input type="hidden" name="<?php echo esc_attr( $arg_key ); ?>" value="<?php echo esc_attr( $arg_value ); ?>" />
				<?php
			endif;
		endforeach;
		?>
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
 * "6 results for “coffee”" above the results (0.65.0). Only when a search or
 * filter is active; a plain browse of the whole directory shows nothing.
 * Before this a search landed on a page still titled "All Listings" with no
 * count, so on a phone it wasn't clear the search had run.
 */
function lis_directory_render_results_summary( $count ) {
	$filters = function_exists( 'lis_directory_listing_request_filters' ) ? lis_directory_listing_request_filters() : array();
	$keyword = isset( $filters['s'] ) ? trim( (string) $filters['s'] ) : '';
	$active  = '' !== $keyword || ! empty( $filters['tax_query'] ) || ! empty( $filters['meta_query'] ) || ! empty( $filters['open_now'] );
	if ( ! $active ) {
		// 0.69.0: a plain browse shows the total too ("36 listings").
		return '<p class="lis-listing-results-summary">' . esc_html( sprintf( _n( '%s listing', '%s listings', (int) $count, 'lis-directory' ), number_format_i18n( (int) $count ) ) ) . '</p>';
	}
	$label = sprintf( _n( '%s result', '%s results', (int) $count, 'lis-directory' ), number_format_i18n( (int) $count ) );
	if ( '' !== $keyword ) {
		/* translators: 1: "6 results", 2: search keyword */
		$label = sprintf( __( '%1$s for “%2$s”', 'lis-directory' ), $label, $keyword );
	}
	return '<p class="lis-listing-results-summary" role="status">' . esc_html( $label ) . '</p>';
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
	$current_sort = lis_directory_current_sort_key();
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
 * secondary/widget query. With none of this plugin's filter params present,
 * the only change is leaving real estate out (it has its own pages).
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
	// Real estate (For Rent / For Sale) has its own pages
	// (includes/real-estate.php), so the business archive, category archives
	// and keyword search leave it out unless a real-estate type is asked for
	// explicitly with lis_type.
	$exclude_real_estate = function_exists( 'lis_directory_not_real_estate_clause' );

	$sort_option = lis_directory_current_sort_option();
	$query->set( 'orderby', $sort_option['orderby'] );
	$query->set( 'order', $sort_option['order'] );

	if ( ! $has_any_filter ) {
		if ( $exclude_real_estate ) {
			$query->set( 'meta_query', array( lis_directory_not_real_estate_clause() ) );
		}
		return;
	}

	$filters = lis_directory_listing_request_filters();
	if ( '' !== $filters['s'] ) {
		$query->set( 's', $filters['s'] );
		// Apostrophe-insensitive matching (lis_directory_loose_posts_search()).
		// A query var, not is_main_query(), because templates/archive-listing.php
		// runs its own WP_Query rebuilt from these query vars.
		$query->set( 'lis_loose_search', true );
	}
	if ( $filters['tax_query'] ) {
		$query->set( 'tax_query', $filters['tax_query'] );
	}

	$meta_query = $filters['meta_query'];
	$typed      = false;
	if ( ! empty( $_GET['lis_type'] ) ) {
		$types = lis_directory_get_listing_types();
		$type  = sanitize_key( wp_unslash( $_GET['lis_type'] ) );
		if ( isset( $types[ $type ] ) ) {
			$typed        = true;
			$meta_query[] = lis_directory_listing_type_clause( $type );
		}
	}
	if ( ! $typed && $exclude_real_estate ) {
		$meta_query[] = lis_directory_not_real_estate_clause();
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
