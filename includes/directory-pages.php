<?php
/**
 * Directory pages redesign (0.74.0, from the lis-dir-lab mockups 09–11).
 *
 * [lis_directory_home]   — parent page /services/local-directory/: hero with search and a
 *                          three-photo stack (each photo links to its listing), Locals'
 *                          favourites (featured + popular only), category tiles, doors to
 *                          All Listings and the A–Z Index.
 * [lis_listing_grid layout="photo"] — All Listings: big heading, search, category tiles as
 *                          filters, then a true-shape photo grid with the text on a dark fade.
 * [lis_directory_index]  — A–Z Index: every local-business listing, one row each, with a
 *                          true-shape thumbnail, letter rail, search and category filter.
 *
 * Pages holding any of these get body class lis-dir-v2-page, which hides the theme's own
 * page title (each renders its own h1).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_directory_home', 'lis_directory_render_home_shortcode' );
add_shortcode( 'lis_directory_index', 'lis_directory_render_index_shortcode' );

function lis_directory_v2_assets( $js = false ) {
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_style( 'lis-directory-pages', LIS_DIRECTORY_URL . 'assets/css/directory-pages.css', array( 'lis-directory-listings' ), LIS_DIRECTORY_VERSION );
	if ( $js ) {
		wp_enqueue_script( 'lis-directory-pages', LIS_DIRECTORY_URL . 'assets/js/directory-pages.js', array(), LIS_DIRECTORY_VERSION, true );
	}
}

/** URLs of the three directory pages. Filters: lis_directory_all_url, lis_directory_index_url, lis_directory_home_url. */
function lis_directory_v2_url( $which ) {
	$paths = array(
		'home'  => '/services/local-directory/',
		'all'   => '/services/local-directory/local-business/',
		'index' => '/services/local-directory/a-z/',
	);
	return apply_filters( 'lis_directory_' . $which . '_url', home_url( $paths[ $which ] ) );
}

function lis_directory_v2_body_class( $classes ) {
	$post = get_post();
	if ( is_singular() && $post && (
		has_shortcode( $post->post_content, 'lis_directory_home' ) ||
		has_shortcode( $post->post_content, 'lis_directory_index' ) ||
		( has_shortcode( $post->post_content, 'lis_listing_grid' ) && false !== strpos( $post->post_content, 'layout="photo"' ) )
	) ) {
		$classes[] = 'lis-dir-v2-page';
	}
	return $classes;
}
add_filter( 'body_class', 'lis_directory_v2_body_class' );

/* ---------- small pieces ---------- */

function lis_directory_v2_svg( $name, $size = 18 ) {
	$paths = array(
		'arrow'  => '<path d="M4 10h12M11 5l5 5-5 5"/>',
		'search' => '<circle cx="9" cy="9" r="6"/><path d="m13.5 13.5 4 4"/>',
		'star'   => '',
		'all'    => '<path d="M4 4h5v5H4zM11 4h5v5h-5zM4 11h5v5H4zM11 11h5v5h-5z"/>',
		'food-drink' => '<path d="M6 2v6a2 2 0 0 0 2 2v8M8 2v5M10 2v6a2 2 0 0 1-2 2M14 18V2c-1.8 1-2.5 3-2.5 6s1 3.5 2.5 3.5"/>',
		'community-public-services' => '<path d="M10 2v4M8 4h4M5 18V10l5-4 5 4v8M3 18h14M8.5 18v-3.5a1.5 1.5 0 0 1 3 0V18"/>',
		'retail-shopping' => '<path d="M4 7h12l-1 11H5L4 7Z"/><path d="M7 7V5a3 3 0 0 1 6 0v2"/>',
		'agriculture' => '<path d="M10 18V7M10 11c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4ZM10 11c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4ZM10 15c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4ZM10 15c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4ZM10 7c-1-1-1-3 0-5 1 2 1 4 0 5Z"/>',
		'home-property' => '<path d="M3 9.5 10 4l7 5.5M5 8v9h10V8M8.5 17v-4.5h3V17"/>',
		'health-wellness' => '<path d="M10 17s-6-3.6-6-8.2A3.3 3.3 0 0 1 10 6.6a3.3 3.3 0 0 1 6 2.2C16 13.4 10 17 10 17Z"/>',
		'automotive' => '<path d="M3 13.5V11l1.6-4.2A2 2 0 0 1 6.5 5.5h7a2 2 0 0 1 1.9 1.3L17 11v2.5M3 13.5h14M3 13.5V16h2.5v-2.5M14.5 13.5V16H17v-2.5"/><circle cx="6.5" cy="11" r=".6"/><circle cx="13.5" cy="11" r=".6"/>',
		'events-entertainment' => '<path d="M3 7h14v3a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4V7ZM8 7v10"/>',
		'outdoor-recreation' => '<path d="M2 17l5.5-9 3.5 5 2-3 5 7Z"/><circle cx="14.5" cy="5" r="1.8"/>',
		'more'   => '<circle cx="5" cy="10" r="1.2"/><circle cx="10" cy="10" r="1.2"/><circle cx="15" cy="10" r="1.2"/>',
		'pin'    => '<path d="M10 18s6-5.3 6-10a6 6 0 1 0-12 0c0 4.7 6 10 6 10Z"/><circle cx="10" cy="8" r="2.2"/>',
	);
	if ( 'star' === $name ) {
		return '<svg viewBox="0 0 20 20" width="' . (int) $size . '" height="' . (int) $size . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="m10 2 2.4 5.2 5.6.6-4.2 3.8 1.2 5.6L10 14.4l-5 2.8 1.2-5.6L2 7.8l5.6-.6Z"/></svg>';
	}
	$d = isset( $paths[ $name ] ) ? $paths[ $name ] : '<circle cx="10" cy="10" r="6.5"/><path d="M10 6.5v7M6.5 10h7"/>';
	return '<svg viewBox="0 0 20 20" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Street part of the address ("212 Cedar St"), or the town. */
function lis_directory_v2_street( $post_id ) {
	$addr = trim( (string) get_post_meta( $post_id, '_lis_listing_address', true ) );
	if ( '' === $addr ) {
		return '';
	}
	$parts = array_map( 'trim', explode( ',', $addr ) );
	return $parts[0];
}

/** Tagline, else a short excerpt. */
function lis_directory_v2_blurb( $post ) {
	$line = trim( (string) get_post_meta( $post->ID, '_lis_listing_tagline', true ) );
	if ( strlen( $line ) < 12 ) {
		$line = wp_trim_words( get_the_excerpt( $post ), 18, '…' );
	}
	return html_entity_decode( $line, ENT_QUOTES, 'UTF-8' );
}

/**
 * Featured image with its true shape. Very wide photos are trimmed to 1.42:1 so the
 * text on the photo still fits; tall ones keep their shape down to 0.72:1.
 * Returns array( html, ratio ) or null when there's no photo.
 */
function lis_directory_v2_photo( $post, $sizes = '(max-width: 640px) 50vw, 25vw', $loading = 'lazy' ) {
	$thumb_id = get_post_thumbnail_id( $post );
	if ( ! $thumb_id ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $thumb_id, 'large' );
	if ( ! $src || empty( $src[1] ) || empty( $src[2] ) ) {
		return null;
	}
	$ratio = (float) $src[1] / (float) $src[2];
	$html  = wp_get_attachment_image( $thumb_id, 'large', false, array(
		'class'   => 'lis-v2-img',
		'alt'     => function_exists( 'lis_directory_listing_image_alt' ) ? lis_directory_listing_image_alt( $thumb_id, $post ) : get_the_title( $post ),
		'sizes'   => $sizes,
		'loading' => $loading,
	) );
	return array( $html, $ratio );
}

/** True-shape photo card with the text on a dark fade. */
function lis_directory_render_photo_card( $post, $sizes = '(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 25vw', $show_street = true ) {
	$post_id  = $post->ID;
	$photo    = lis_directory_v2_photo( $post, $sizes );
	$cats     = get_the_terms( $post_id, 'lis_listing_category' );
	$cat      = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
	$is_open  = lis_directory_is_listing_open_now( $post_id );
	$featured = (bool) get_post_meta( $post_id, '_lis_listing_featured', true );
	$popular  = lis_directory_is_listing_popular( $post_id );
	$street   = $show_street ? lis_directory_v2_street( $post_id ) : '';
	$blurb    = lis_directory_v2_blurb( $post );
	$ratio    = $photo ? max( 0.72, min( 1.42, $photo[1] ) ) : 0.8;
	$badge    = $featured ? 'Featured' : ( $popular ? 'Popular' : '' );

	ob_start();
	?>
	<a class="lis-v2-card<?php echo $photo ? '' : ' is-nophoto'; ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>" style="--lis-v2-ar:<?php echo esc_attr( number_format( $ratio, 3, '.', '' ) ); ?>">
		<span class="lis-v2-card-ph">
			<?php
			if ( $photo ) {
				echo $photo[0]; // phpcs:ignore -- wp_get_attachment_image output.
			} else {
				echo lis_directory_render_listing_fallback_image( $post, 'card' ); // phpcs:ignore -- escaped inside helper.
			}
			?>
		</span>
		<span class="lis-v2-card-top">
			<?php if ( $cat ) : ?><span class="lis-v2-card-cat"><?php echo esc_html( html_entity_decode( $cat, ENT_QUOTES, 'UTF-8' ) ); ?></span><?php endif; ?>
			<?php if ( $badge ) : ?><span class="lis-v2-card-badge"><?php echo lis_directory_v2_svg( 'star', 11 ); // phpcs:ignore ?> <?php echo esc_html( $badge ); ?></span><?php endif; ?>
		</span>
		<span class="lis-v2-card-txt">
			<span class="lis-v2-card-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<?php if ( $blurb ) : ?><span class="lis-v2-card-blurb"><?php echo esc_html( $blurb ); ?></span><?php endif; ?>
			<?php if ( $street || null !== $is_open ) : ?>
				<span class="lis-v2-card-meta"><?php echo esc_html( $street ); ?><?php if ( null !== $is_open ) : ?><?php echo $street ? ' · ' : ''; ?><b class="<?php echo $is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $is_open ? 'Open' : 'Closed'; ?></b><?php endif; ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Category tiles from lis_directory_browse_data() (top-level categories, children
 * counted). Everything + the five biggest, then a "More" tile that opens the rest.
 * $current: slug of the selected category ('' = Everything, null = nothing selected, as on the home page).
 */
function lis_directory_render_category_tiles( $base_url, $current = null ) {
	$data  = lis_directory_browse_data();
	$tiles = isset( $data['tiles'] ) ? $data['tiles'] : array();
	if ( ! $tiles ) {
		return '';
	}
	$top    = array_slice( $tiles, 0, 5 );
	$rest   = array_slice( $tiles, 5 );
	$in_rest = null;
	foreach ( $rest as $t ) {
		if ( $t['slug'] === $current ) {
			$in_rest = $t;
		}
	}
	$tile   = function ( $slug, $name, $count, $icon ) use ( $base_url, $current ) {
		$on   = null !== $current && $slug === $current;
		$href = $slug ? add_query_arg( 'lis_category', $slug, $base_url ) : $base_url;
		return '<a class="lis-v2-tile" href="' . esc_url( $href ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>'
			. lis_directory_v2_svg( $icon, 26 )
			. '<b>' . esc_html( $name ) . '</b>'
			. '<small>' . esc_html( sprintf( _n( '%d place', '%d places', $count, 'lis-directory' ), $count ) ) . '</small></a>';
	};
	$out  = '<div class="lis-v2-tiles">';
	$out .= $tile( '', 'Everything', (int) $data['total'], 'all' );
	foreach ( $top as $t ) {
		$out .= $tile( $t['slug'], $t['name'], (int) $t['count'], $t['slug'] );
	}
	if ( $rest ) {
		// A category from the More list shows as the selected More tile, closed.
		$out .= '<details class="lis-v2-more" data-lis-v2-more><summary class="lis-v2-tile"' . ( $in_rest ? ' aria-current="page"' : '' ) . '>' . lis_directory_v2_svg( 'more', 26 )
			. '<b>' . esc_html( $in_rest ? $in_rest['name'] : 'More' ) . '</b><small>' . esc_html( $in_rest ? sprintf( _n( '%d place', '%d places', (int) $in_rest['count'], 'lis-directory' ), (int) $in_rest['count'] ) . ' · change' : sprintf( '%d categories', count( $rest ) ) ) . '</small></summary><div class="lis-v2-more-list">';
		foreach ( $rest as $t ) {
			$on   = $t['slug'] === $current;
			$out .= '<a href="' . esc_url( add_query_arg( 'lis_category', $t['slug'], $base_url ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $t['name'] ) . ' <small>' . (int) $t['count'] . '</small></a>';
		}
		$out .= '</div></details>';
	}
	return $out . '</div>';
}

/** Featured + popular local-business listings with a photo (featured first). */
function lis_directory_v2_favourites( $limit = 12 ) {
	$base = array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'meta_query'     => array( 'relation' => 'AND', lis_directory_listing_type_clause( 'local-business' ), array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
	);
	$featured = get_posts( array_merge( $base, array(
		'meta_query' => array_merge( $base['meta_query'], array( array( 'key' => '_lis_listing_featured', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ) ) ),
		'orderby'    => array( 'date' => 'DESC', 'ID' => 'DESC' ),
	) ) );
	$featured = array_values( array_filter( $featured, function ( $p ) {
		return (bool) get_post_meta( $p->ID, '_lis_listing_featured', true );
	} ) );
	$have        = wp_list_pluck( $featured, 'ID' );
	$popular_ids = array_values( array_diff( lis_directory_get_popular_listing_ids(), $have ) );
	$popular     = $popular_ids ? get_posts( array_merge( $base, array( 'post__in' => $popular_ids, 'orderby' => 'post__in' ) ) ) : array();
	return array_slice( array_merge( $featured, $popular ), 0, $limit );
}

/* ---------- [lis_directory_home] ---------- */

function lis_directory_render_home_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'favourites' => 12,
		'quick'      => 'Pizza,Coffee,Golf,Church,Farm',
		// Which sections to print, so other page blocks can sit between them:
		// [lis_directory_home parts="hero,tiles,doors"] … [lis_directory_home parts="favourites"]
		'parts'      => 'hero,favourites,tiles,doors',
		// CSS selector of other text on the page to type out when scrolled to (e.g. the testimonial).
		'typewriter' => '',
	), $atts, 'lis_directory_home' );
	$show = array_flip( array_map( 'trim', explode( ',', strtolower( $atts['parts'] ) ) ) );
	lis_directory_v2_assets( true );

	$all   = lis_directory_v2_url( 'all' );
	$index = lis_directory_v2_url( 'index' );
	$data  = lis_directory_browse_data();
	$total = isset( $data['total'] ) ? (int) $data['total'] : 0;
	$favs  = lis_directory_v2_favourites( max( 1, (int) $atts['favourites'] ) );
	$stack = array_slice( $favs, 0, 3 );
	$quick = array_filter( array_map( 'trim', explode( ',', $atts['quick'] ) ) );
	$mos   = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'no_found_rows'  => true,
		'meta_query'     => array( 'relation' => 'AND', lis_directory_listing_type_clause( 'local-business' ), array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
		'post__not_in'   => wp_list_pluck( $favs, 'ID' ),
	) );

	ob_start();
	?>
	<div class="lis-v2 lis-v2-home"<?php echo '' !== $atts['typewriter'] ? ' data-lis-v2-typewriter="' . esc_attr( $atts['typewriter'] ) . '"' : ''; ?>>
		<?php if ( isset( $show['hero'] ) ) : ?>
		<section class="lis-v2-hero<?php echo count( $stack ) < 3 ? ' is-nostack' : ''; ?>">
			<div class="lis-v2-hero-copy">
				<p class="lis-v2-kick">Local Directory</p>
				<h1 class="lis-v2-h1">Where Sandpoint<br><em>actually</em> goes.</h1>
				<p class="lis-v2-dek"><?php echo esc_html( sprintf( '%d local places, added by the people who run them. Find a pint, a pastor, a plumber.', $total ) ); ?></p>
				<form class="lis-v2-search lis-v2-search--big" role="search" method="get" action="<?php echo esc_url( $all ); ?>">
					<label class="lis-v2-q"><?php echo lis_directory_v2_svg( 'search' ); // phpcs:ignore ?><input type="search" name="lis_q" placeholder="<?php echo esc_attr( sprintf( 'Search %d local places', $total ) ); ?>" aria-label="Search the directory"></label>
					<button type="submit">Search</button>
				</form>
				<?php if ( $quick ) : ?>
					<p class="lis-v2-quick"><span>Try</span><?php foreach ( $quick as $q ) : ?><a href="<?php echo esc_url( add_query_arg( 'lis_q', strtolower( $q ), $all ) ); ?>"><?php echo esc_html( $q ); ?></a><?php endforeach; ?></p>
				<?php endif; ?>
			</div>
			<?php if ( count( $stack ) >= 3 ) : ?>
				<div class="lis-v2-stack">
					<?php foreach ( $stack as $i => $p ) :
						$ph = lis_directory_v2_photo( $p, '(max-width: 900px) 60vw, 340px', 0 === $i ? 'eager' : 'lazy' );
						if ( ! $ph ) {
							continue;
						}
						?>
						<a class="lis-v2-stack-ph lis-v2-stack-ph<?php echo (int) $i; ?>" href="<?php echo esc_url( get_permalink( $p ) ); ?>" style="--lis-v2-ar:<?php echo esc_attr( number_format( max( 0.75, min( 1.5, $ph[1] ) ), 3, '.', '' ) ); ?>">
							<?php echo $ph[0]; // phpcs:ignore -- wp_get_attachment_image output. ?>
							<span><?php echo esc_html( get_the_title( $p ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if ( $favs && isset( $show['favourites'] ) ) : ?>
			<section class="lis-v2-sec lis-v2-sec--favs">
				<div class="lis-v2-sec-h">
					<div><p class="lis-v2-kick">Locals’ favourites</p><h2>The places people keep sending their friends to.</h2></div>
					<a class="lis-v2-link" href="<?php echo esc_url( $all ); ?>"><?php echo esc_html( sprintf( 'See all %d', $total ) ); ?> <?php echo lis_directory_v2_svg( 'arrow', 16 ); // phpcs:ignore ?></a>
				</div>
				<div class="lis-v2-fadewrap" data-lis-v2-fade>
					<div class="lis-v2-grid">
						<?php foreach ( $favs as $p ) { echo lis_directory_render_photo_card( $p, '(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 25vw', false ); } // phpcs:ignore -- escaped inside helper. ?>
					</div>
					<div class="lis-v2-fade"><a class="lis-v2-fade-link" href="<?php echo esc_url( $all ); ?>"><?php echo esc_html( sprintf( 'See all %d places', $total ) ); ?> <?php echo lis_directory_v2_svg( 'arrow', 16 ); // phpcs:ignore ?></a></div>
				</div>
			</section>
		<?php endif; ?>

		<?php $band = isset( $show['tiles'] ) || isset( $show['doors'] ); ?>
		<?php if ( $band ) : ?><div class="lis-v2-band"><?php endif; ?>
		<?php if ( isset( $show['tiles'] ) ) : ?>
		<section class="lis-v2-sec">
			<div class="lis-v2-sec-h"><div><p class="lis-v2-kick">Browse by kind</p><h2>What are you after?</h2></div></div>
			<?php echo lis_directory_render_category_tiles( $all ); // phpcs:ignore -- escaped inside helper. ?>
		</section>
		<?php endif; ?>

		<?php if ( isset( $show['doors'] ) ) : ?>
		<section class="lis-v2-sec lis-v2-doors">
			<a class="lis-v2-door lis-v2-door--all" href="<?php echo esc_url( $all ); ?>">
				<span class="lis-v2-mos" aria-hidden="true"><?php foreach ( $mos as $p ) { echo get_the_post_thumbnail( $p, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) ); } // phpcs:ignore ?></span>
				<span class="lis-v2-kick">Every listing</span>
				<b><?php echo esc_html( sprintf( 'All %d listings', $total ) ); ?></b>
				<em>Filter by kind, search, sort. Photos and all. <?php echo lis_directory_v2_svg( 'arrow', 16 ); // phpcs:ignore ?></em>
			</a>
			<a class="lis-v2-door lis-v2-door--idx" href="<?php echo esc_url( $index ); ?>">
				<span class="lis-v2-kick">Know the name?</span>
				<b class="lis-v2-az" aria-hidden="true">A–Z</b>
				<b>The Index</b>
				<em>Every place, alphabetical, one line each. <?php echo lis_directory_v2_svg( 'arrow', 16 ); // phpcs:ignore ?></em>
			</a>
		</section>
		<?php endif; ?>
		<?php if ( $band ) : ?></div><?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------- [lis_listing_grid layout="photo"] (called from the grid shortcode) ---------- */

function lis_directory_render_photo_grid( $grid_query, $query_args, $paged ) {
	lis_directory_v2_assets( true );
	$all      = get_permalink() ? get_permalink() : lis_directory_v2_url( 'all' );
	$filters  = lis_directory_listing_request_filters();
	$current  = isset( $_GET['lis_category'] ) ? sanitize_title( wp_unslash( $_GET['lis_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sort_key = lis_directory_current_sort_key();
	$found    = $paged ? (int) $grid_query->found_posts : count( $grid_query->posts );
	$data     = lis_directory_browse_data();
	$total    = isset( $data['total'] ) ? (int) $data['total'] : $found;
	$cat_name = '';
	if ( $current ) {
		$term     = get_term_by( 'slug', $current, 'lis_listing_category' );
		$cat_name = $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '';
	}
	$keep = array_filter( array( 'lis_category' => $current, 'lis_sort' => 'newest' !== $sort_key ? $sort_key : '' ) );

	ob_start();
	?>
	<div class="lis-v2 lis-v2-all">
		<header class="lis-v2-all-head">
			<a class="lis-v2-back" href="<?php echo esc_url( lis_directory_v2_url( 'home' ) ); ?>">← Local Directory</a>
			<div class="lis-v2-all-top">
				<div>
					<h1 class="lis-v2-h1"><?php echo esc_html( $cat_name ? $cat_name : 'All Listings' ); ?></h1>
					<p class="lis-v2-sub"><b><?php echo esc_html( sprintf( _n( '%d place', '%d places', $found, 'lis-directory' ), $found ) ); ?></b><?php echo '' !== $filters['s'] ? esc_html( sprintf( ' matching “%s”', $filters['s'] ) ) : ' around Sandpoint, Sagle and Ponderay.'; ?></p>
				</div>
				<form class="lis-v2-search" role="search" method="get" action="<?php echo esc_url( $all ); ?>">
					<?php foreach ( $keep as $k => $v ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>"><?php endforeach; ?>
					<label class="lis-v2-q"><?php echo lis_directory_v2_svg( 'search' ); // phpcs:ignore ?><input type="search" name="lis_q" value="<?php echo esc_attr( $filters['s'] ); ?>" placeholder="<?php echo esc_attr( sprintf( 'Search %d local places', $total ) ); ?>" aria-label="Search listings"></label>
				</form>
			</div>
			<?php echo lis_directory_render_category_tiles( remove_query_arg( array( 'lis_category', 'paged' ), $all ), $current ); // phpcs:ignore -- escaped inside helper. ?>
		</header>

		<div class="lis-v2-bar">
			<span><?php echo esc_html( sprintf( _n( '%d place', '%d places', $found, 'lis-directory' ), $found ) ); ?><?php if ( $current || '' !== $filters['s'] ) : ?> · <a href="<?php echo esc_url( $all ); ?>">Clear</a><?php endif; ?></span>
			<span class="lis-v2-bar-r">
				<form method="get" action="<?php echo esc_url( $all ); ?>">
					<?php foreach ( array_filter( array( 'lis_category' => $current, 'lis_q' => $filters['s'] ) ) as $k => $v ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>"><?php endforeach; ?>
					<label>Sort <select name="lis_sort" onchange="this.form.submit()">
						<?php foreach ( lis_directory_get_sort_options() as $key => $opt ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $sort_key, $key ); ?>><?php echo esc_html( $opt['label'] ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<noscript><button type="submit">Apply</button></noscript>
				</form>
				<a href="<?php echo esc_url( lis_directory_v2_url( 'index' ) ); ?>">A–Z Index <?php echo lis_directory_v2_svg( 'arrow', 16 ); // phpcs:ignore ?></a>
			</span>
		</div>

		<?php if ( empty( $grid_query->posts ) ) : ?>
			<p class="lis-listing-empty"><?php echo esc_html( lis_directory_listing_empty_message() ); ?></p>
		<?php else : ?>
			<div class="lis-v2-grid" data-lis-listing-results>
				<?php foreach ( $grid_query->posts as $p ) { echo lis_directory_render_photo_card( $p, '(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 25vw', false ); } // phpcs:ignore -- escaped inside helper. ?>
			</div>
			<?php if ( $paged ) : ?>
				<?php echo lis_directory_render_listing_pagination( $grid_query->max_num_pages, $query_args['paged'] ); // phpcs:ignore -- built by paginate_links(). ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------- [lis_directory_index] ---------- */

function lis_directory_render_index_shortcode() {
	lis_directory_v2_assets( true );
	$ids = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array( lis_directory_listing_type_clause( 'local-business' ) ),
	) );
	update_meta_cache( 'post', wp_list_pluck( $ids, 'ID' ) );

	// Sort ignoring a leading "The".
	$key = function ( $p ) {
		return strtolower( preg_replace( '/^the\s+/i', '', html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) ) );
	};
	usort( $ids, function ( $a, $b ) use ( $key ) {
		return strnatcasecmp( $key( $a ), $key( $b ) );
	} );
	$by = array();
	foreach ( $ids as $p ) {
		$k = $key( $p );
		$l = strtoupper( substr( $k, 0, 1 ) );
		$l = preg_match( '/[A-Z]/', $l ) ? $l : '#';
		$by[ $l ][] = $p;
	}

	// Top-level category of each listing, for the filter buttons.
	$data   = lis_directory_browse_data();
	$tiles  = isset( $data['tiles'] ) ? $data['tiles'] : array();
	$parent = function ( $post_id ) {
		$terms = get_the_terms( $post_id, 'lis_listing_category' );
		$out   = array();
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$anc   = get_ancestors( $t->term_id, 'lis_listing_category', 'taxonomy' );
				$top   = $anc ? get_term( end( $anc ), 'lis_listing_category' ) : $t;
				$out[] = $top && ! is_wp_error( $top ) ? $top->slug : '';
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	};

	ob_start();
	?>
	<div class="lis-v2 lis-v2-index" data-lis-index>
		<nav class="lis-v2-crumbs"><a href="<?php echo esc_url( lis_directory_v2_url( 'home' ) ); ?>">← Local Directory</a><a href="<?php echo esc_url( lis_directory_v2_url( 'all' ) ); ?>">All Listings with photos →</a></nav>
		<h1 class="lis-v2-h1 lis-v2-index-h1">The Index</h1>
		<p class="lis-v2-sub"><b data-lis-index-count><?php echo esc_html( sprintf( '%d places', count( $ids ) ) ); ?></b>, A to Z. Know the name? Start typing.</p>
		<div class="lis-v2-search"><label class="lis-v2-q"><?php echo lis_directory_v2_svg( 'search' ); // phpcs:ignore ?><input type="search" data-lis-index-q placeholder="<?php echo esc_attr( sprintf( 'Search %d local places', count( $ids ) ) ); ?>" aria-label="Search the index"></label></div>
		<div class="lis-v2-chips" role="group" aria-label="Category">
			<button type="button" data-cat="" aria-pressed="true">All <small><?php echo (int) count( $ids ); ?></small></button>
			<?php foreach ( array_slice( $tiles, 0, 8 ) as $t ) : ?>
				<button type="button" data-cat="<?php echo esc_attr( $t['slug'] ); ?>" aria-pressed="false"><?php echo esc_html( $t['name'] ); ?> <small><?php echo (int) $t['count']; ?></small></button>
			<?php endforeach; ?>
		</div>
		<nav class="lis-v2-rail" aria-label="Jump to letter">
			<?php foreach ( range( 'A', 'Z' ) as $l ) : ?>
				<?php if ( isset( $by[ $l ] ) ) : ?><a href="#lis-az-<?php echo esc_attr( $l ); ?>"><?php echo esc_html( $l ); ?></a><?php else : ?><span><?php echo esc_html( $l ); ?></span><?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( $by as $l => $posts ) : ?>
			<section class="lis-v2-az-sec" id="lis-az-<?php echo esc_attr( '#' === $l ? 'num' : $l ); ?>">
				<h2><?php echo esc_html( $l ); ?></h2>
				<ul>
					<?php foreach ( $posts as $p ) :
						$cats   = get_the_terms( $p->ID, 'lis_listing_category' );
						$cat    = ( $cats && ! is_wp_error( $cats ) ) ? html_entity_decode( $cats[0]->name, ENT_QUOTES, 'UTF-8' ) : '';
						$street = lis_directory_v2_street( $p->ID );
						$phone  = (string) get_post_meta( $p->ID, '_lis_listing_phone', true );
						$open   = lis_directory_is_listing_open_now( $p->ID );
						$ph     = lis_directory_v2_photo( $p, '88px' );
						$title  = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
						$q      = strtolower( $title . ' ' . $cat . ' ' . $street );
						?>
						<li data-q="<?php echo esc_attr( $q ); ?>" data-cats="<?php echo esc_attr( implode( ' ', $parent( $p->ID ) ) ); ?>">
							<a href="<?php echo esc_url( get_permalink( $p ) ); ?>">
								<span class="lis-v2-az-th" style="--lis-v2-ar:<?php echo esc_attr( number_format( $ph ? max( 0.72, min( 1.9, $ph[1] ) ) : 1, 3, '.', '' ) ); ?>"><?php echo $ph ? $ph[0] : lis_directory_render_listing_fallback_image( $p, 'card' ); // phpcs:ignore ?></span>
								<span class="lis-v2-az-n"><?php echo esc_html( $title ); ?><?php if ( lis_directory_is_listing_popular( $p->ID ) || get_post_meta( $p->ID, '_lis_listing_featured', true ) ) : ?> <i><?php echo lis_directory_v2_svg( 'star', 14 ); // phpcs:ignore ?></i><?php endif; ?></span>
								<span class="lis-v2-az-c"><?php echo esc_html( $cat ); ?></span>
								<span class="lis-v2-az-a"><?php echo esc_html( $street ); ?></span>
								<span class="lis-v2-az-s"><?php
								if ( null !== $open ) {
									echo '<span class="lis-v2-st ' . ( $open ? 'is-open' : 'is-closed' ) . '"><i></i>' . ( $open ? 'Open now' : 'Closed' ) . '</span>';
								} else {
									echo esc_html( $phone );
								}
								?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
		<p class="lis-v2-empty" data-lis-index-empty hidden>Nothing by that name. <a href="<?php echo esc_url( lis_directory_v2_url( 'all' ) ); ?>">Search all listings</a></p>
	</div>
	<?php
	return ob_get_clean();
}
