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

function lis_directory_render_search_form_shortcode( $atts ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-search', LIS_DIRECTORY_URL . 'assets/js/listing-search.js', array(), LIS_DIRECTORY_VERSION, true );

	$atts = shortcode_atts( array( 'redirect' => '' ), $atts, 'lis_listing_search' );

	$action = $atts['redirect'] ? $atts['redirect'] : get_post_type_archive_link( 'lis_listing' );

	$current_q       = isset( $_GET['lis_q'] ) ? sanitize_text_field( wp_unslash( $_GET['lis_q'] ) ) : '';
	$current_type    = isset( $_GET['lis_type'] ) ? sanitize_key( wp_unslash( $_GET['lis_type'] ) ) : '';
	$current_cat     = isset( $_GET['lis_category'] ) ? sanitize_title( wp_unslash( $_GET['lis_category'] ) ) : '';
	$current_price   = isset( $_GET['lis_price'] ) ? sanitize_text_field( wp_unslash( $_GET['lis_price'] ) ) : '';
	$current_open    = ! empty( $_GET['lis_open_now'] );
	$current_features = isset( $_GET['lis_feature'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_GET['lis_feature'] ) ) : array();

	$categories = get_terms( array( 'taxonomy' => 'lis_listing_category', 'hide_empty' => false, 'parent' => 0 ) );
	$features   = get_terms( array( 'taxonomy' => 'lis_listing_feature', 'hide_empty' => false ) );

	ob_start();
	?>
	<form class="lis-listing-search-form" method="get" action="<?php echo esc_url( $action ); ?>">
		<div class="lis-listing-search-row">
			<input type="text" name="lis_q" class="lis-listing-search-input" placeholder="What are you looking for?" value="<?php echo esc_attr( $current_q ); ?>" />

			<select name="lis_type" class="lis-listing-search-select">
				<option value="">All Directories</option>
				<?php foreach ( lis_directory_get_listing_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

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
		|| isset( $_GET['lis_price'] ) || isset( $_GET['lis_open_now'] ) || isset( $_GET['lis_feature'] );
	if ( ! $has_any_filter ) {
		return;
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
