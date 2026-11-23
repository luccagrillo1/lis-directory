<?php
/**
 * Meta descriptions for listing category pages and the /jobs/ board (v0.72.0,
 * site audit 2026-11-23). Neither had one, so Google picked page text itself.
 *
 * - Category: "Coffee Shops in the Sandpoint area: Evans Brothers, Pine Street
 *   Bakery and Cafe Bodega. Hours, phone numbers and directions in the Living
 *   in Sandpoint directory." (up to three names, newest first.)
 * - /jobs/: a fixed line describing the board (filter lis_directory_jobs_meta_desc).
 *
 * A description typed into SEOPress for the term always wins. Same pattern as
 * listing-meta.php: fed through seopress_titles_desc, wp_head fallback otherwise.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lis_directory_archive_meta_trim( $desc ) {
	$desc = trim( preg_replace( '/\s+/u', ' ', (string) $desc ) );
	if ( mb_strlen( $desc ) > 158 ) {
		$cut  = mb_substr( $desc, 0, 155 );
		$sp   = mb_strrpos( $cut, ' ' );
		$desc = rtrim( $sp > 100 ? mb_substr( $cut, 0, $sp ) : $cut, " ,.;:-" ) . '…';
	}
	return $desc;
}

function lis_directory_category_meta_desc( $term ) {
	$name  = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
	$names = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array( 'taxonomy' => 'lis_listing_category', 'field' => 'term_id', 'terms' => (int) $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	$titles = array_map( function ( $id ) {
		return html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	}, $names );
	if ( ! $titles ) {
		return lis_directory_archive_meta_trim( $name . ' in the Sandpoint area, Idaho, in the Living in Sandpoint local business directory.' );
	}
	$last = array_pop( $titles );
	$list = $titles ? implode( ', ', $titles ) . ' and ' . $last : $last;
	return lis_directory_archive_meta_trim( $name . ' in the Sandpoint area: ' . $list . '. Hours, phone numbers and directions in the Living in Sandpoint directory.' );
}

function lis_directory_jobs_meta_desc() {
	return (string) apply_filters(
		'lis_directory_jobs_meta_desc',
		'Local jobs, odd jobs and people available for work in Sandpoint, Idaho. A free community work board from Living in Sandpoint, with no fees and no national middlemen.'
	);
}

/** The description this request should get, or '' when it's not ours to set. */
function lis_directory_archive_meta_current() {
	static $desc = null;
	if ( null !== $desc ) {
		return $desc;
	}
	$desc = '';
	if ( is_tax( 'lis_listing_category' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) && '' === trim( (string) get_term_meta( $term->term_id, '_seopress_titles_desc', true ) ) ) {
			$desc = lis_directory_category_meta_desc( $term );
		}
	} elseif ( is_post_type_archive( 'lis_job' ) ) {
		$desc = lis_directory_jobs_meta_desc();
	}
	return $desc;
}

$GLOBALS['lis_directory_archive_meta_done'] = false;

// Set once SEOPress calls the filter at all: from then on it prints the tag, so the
// fallback below must stay quiet even when SEOPress kept its own description.
add_filter( 'seopress_titles_desc', function ( $desc ) {
	$GLOBALS['lis_directory_archive_meta_done'] = true;
	if ( '' === trim( (string) $desc ) ) {
		$ours = lis_directory_archive_meta_current();
		if ( '' !== $ours ) {
			return $ours;
		}
	}
	return $desc;
} );

add_action( 'wp_head', function () {
	if ( $GLOBALS['lis_directory_archive_meta_done'] ) {
		return;
	}
	$desc = lis_directory_archive_meta_current();
	if ( '' === $desc ) {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
}, 99 );

/**
 * Listing archives: drop a <link rel="next|prev"> that points past the last page.
 * Something in the head (SEOPress/Jetpack) works out rel=next from the site's
 * 10-posts-per-page setting, but listing archives show 36 a page (D15), so a
 * 13-listing category advertised /page/2/, which is a 404 (site audit 2026-11-23).
 * Buffers only the head of listing archives and keeps links that are in range.
 */
add_action( 'wp_head', function () {
	if ( is_post_type_archive( 'lis_listing' ) || is_tax( array( 'lis_listing_category', 'lis_listing_feature' ) ) ) {
		$GLOBALS['lis_directory_head_buffer'] = true;
		ob_start();
	}
}, 0 );

add_action( 'wp_head', function () {
	if ( empty( $GLOBALS['lis_directory_head_buffer'] ) ) {
		return;
	}
	$GLOBALS['lis_directory_head_buffer'] = false;
	$html  = (string) ob_get_clean();
	// Count pages the way the archive shows them (36 a page), not by the query's
	// own max_num_pages, which on these archives comes out at 10 a page.
	$found = (int) $GLOBALS['wp_query']->found_posts;
	$per   = defined( 'LIS_DIRECTORY_LISTINGS_PER_PAGE' ) ? LIS_DIRECTORY_LISTINGS_PER_PAGE : 36;
	$max   = empty( $_GET['lis_open_now'] ) ? max( 1, (int) ceil( $found / $per ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
	$html  = preg_replace_callback( '/<link[^>]+rel=["\'](?:next|prev)["\'][^>]*>\s*/i', function ( $m ) use ( $max ) {
		$page = preg_match( '#/page/(\d+)/?#', $m[0], $p ) ? (int) $p[1] : ( preg_match( '/[?&]paged=(\d+)/', $m[0], $p ) ? (int) $p[1] : 1 );
		return $page > $max ? '' : $m[0];
	}, $html );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' head output, unchanged apart from removed tags.
}, PHP_INT_MAX );
