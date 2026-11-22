<?php
/**
 * [lis_directory_browse] — category photo tiles for the directory landing page
 * (v0.66.0, from mobile mockup 14 option "Category tiles").
 *
 * One tile per top-level listing category that has local-business listings
 * (children included), with a count and a photo from one of its listings, plus a
 * "See all N" tile. Each tile links to the results page with ?lis_category=.
 * Cached in a transient; any listing save or category change clears it.
 *
 * Pages using this shortcode also get the body class lis-dir-browse-page, which
 * makes the search box above it compact on phones (category menu hidden; the
 * tiles do that job).
 *
 * Attributes: url — results page (default /services/local-directory/local-business/),
 *             title — heading (default "Browse by category").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_directory_browse', 'lis_directory_render_browse_shortcode' );

const LIS_DIRECTORY_BROWSE_CACHE = 'lis_directory_browse_v1';

function lis_directory_browse_data() {
	$cached = get_transient( LIS_DIRECTORY_BROWSE_CACHE );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$base_args = array(
		'post_type'              => 'lis_listing',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'no_found_rows'          => false,
		'update_post_term_cache' => false,
		'meta_query'             => array( lis_directory_listing_type_clause( 'local-business' ) ),
	);
	$total_q = new WP_Query( array_merge( $base_args, array( 'posts_per_page' => 1 ) ) );
	$out     = array( 'total' => (int) $total_q->found_posts, 'tiles' => array() );

	$terms = get_terms( array( 'taxonomy' => 'lis_listing_category', 'parent' => 0, 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$tax = array( array( 'taxonomy' => 'lis_listing_category', 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => true ) );
			$q   = new WP_Query( array_merge( $base_args, array( 'posts_per_page' => 1, 'tax_query' => $tax ) ) );
			$n   = (int) $q->found_posts;
			if ( ! $n ) {
				continue;
			}
			// Photo: newest listing in the category that has a featured image.
			$pq  = new WP_Query( array_merge( $base_args, array(
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'tax_query'      => $tax,
				'meta_query'     => array( 'relation' => 'AND', lis_directory_listing_type_clause( 'local-business' ), array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
			) ) );
			$img = $pq->posts ? get_the_post_thumbnail_url( $pq->posts[0], 'medium_large' ) : '';
			$out['tiles'][] = array(
				'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'slug'  => $term->slug,
				'count' => $n,
				'img'   => $img ? $img : '',
			);
		}
	}
	// Biggest categories first.
	usort( $out['tiles'], function ( $a, $b ) { return $b['count'] - $a['count'] ?: strcmp( $a['name'], $b['name'] ); } );
	set_transient( LIS_DIRECTORY_BROWSE_CACHE, $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

function lis_directory_browse_flush() {
	delete_transient( LIS_DIRECTORY_BROWSE_CACHE );
}
add_action( 'save_post_lis_listing', 'lis_directory_browse_flush' );
add_action( 'deleted_post', 'lis_directory_browse_flush' );
add_action( 'set_object_terms', 'lis_directory_browse_flush' );
add_action( 'edited_lis_listing_category', 'lis_directory_browse_flush' );
add_action( 'created_lis_listing_category', 'lis_directory_browse_flush' );
add_action( 'delete_lis_listing_category', 'lis_directory_browse_flush' );

function lis_directory_render_browse_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'url'   => home_url( '/services/local-directory/local-business/' ),
		'title' => 'Browse by category',
	), $atts, 'lis_directory_browse' );
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

	$data = lis_directory_browse_data();
	if ( empty( $data['tiles'] ) ) {
		return '';
	}
	$base = $atts['url'];
	ob_start();
	?>
	<section class="lis-dir-browse" aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
		<h2><?php echo esc_html( $atts['title'] ); ?></h2>
		<p class="lis-dir-browse-sub"><?php echo esc_html( sprintf( _n( '%d local business and place', '%d local businesses and places', $data['total'], 'lis-directory' ), $data['total'] ) ); ?></p>
		<div class="lis-dir-browse-grid">
			<?php foreach ( $data['tiles'] as $t ) : ?>
				<a class="lis-dir-tile<?php echo $t['img'] ? '' : ' is-noimg'; ?>" href="<?php echo esc_url( add_query_arg( 'lis_category', $t['slug'], $base ) ); ?>"<?php echo $t['img'] ? ' style="background-image:url(\'' . esc_url( $t['img'] ) . '\')"' : ''; ?>>
					<b><?php echo esc_html( $t['name'] ); ?></b>
					<span><?php echo esc_html( sprintf( _n( '%d place', '%d places', $t['count'], 'lis-directory' ), $t['count'] ) ); ?></span>
				</a>
			<?php endforeach; ?>
			<a class="lis-dir-tile is-all" href="<?php echo esc_url( $base ); ?>">
				<b><?php echo esc_html( sprintf( 'See all %d', $data['total'] ) ); ?></b>
				<span>Every listing</span>
			</a>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

function lis_directory_browse_body_class( $classes ) {
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'lis_directory_browse' ) ) {
		$classes[] = 'lis-dir-browse-page';
	}
	return $classes;
}
add_filter( 'body_class', 'lis_directory_browse_body_class' );
