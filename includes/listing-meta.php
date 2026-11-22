<?php
/**
 * Meta description for listing pages (v0.67.0, phone audit item 35).
 *
 * Listings had no meta description or og:description, so search results and shared
 * links showed whatever text Google or Facebook picked. When a listing has no SEOPress
 * description of its own, build one: "<Category> in <Town>. <tagline or first sentence>",
 * cut to about 155 characters.
 *
 * Fed through SEOPress's filters when it calls them; if it doesn't print the tags,
 * a wp_head fallback prints them instead, so there's never a duplicate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lis_directory_listing_meta_desc( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}
	$parts = array();

	$cat   = '';
	$terms = get_the_terms( $post_id, 'lis_listing_category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		// Prefer a child category ("Coffee Shops") over its parent ("Food & Drink").
		usort( $terms, function ( $a, $b ) { return (int) ( $b->parent > 0 ) - (int) ( $a->parent > 0 ); } );
		$cat = html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
	}
	$town = lis_directory_listing_meta_town( (string) get_post_meta( $post_id, '_lis_listing_address', true ) );
	if ( $cat && $town ) {
		$parts[] = $cat . ' in ' . $town . '.';
	} elseif ( $cat ) {
		$parts[] = $cat . ' in the Sandpoint area.';
	} elseif ( $town ) {
		$parts[] = 'Local business in ' . $town . '.';
	}

	$line = trim( (string) get_post_meta( $post_id, '_lis_listing_tagline', true ) );
	if ( '' === $line ) {
		$post = get_post( $post_id );
		$text = $post ? $post->post_excerpt : '';
		if ( '' === trim( $text ) && $post ) {
			$text = $post->post_content;
		}
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
		if ( preg_match( '/^(.{20,}?[.!?])(\s|$)/u', $text, $m ) ) {
			$line = $m[1];
		} else {
			$line = $text;
		}
	}
	if ( '' !== $line ) {
		$parts[] = html_entity_decode( $line, ENT_QUOTES, 'UTF-8' );
	}

	$desc = trim( implode( ' ', $parts ) );
	if ( '' === $desc ) {
		$desc = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) . ', a local business listed in the Living in Sandpoint directory.';
	}
	if ( mb_strlen( $desc ) > 158 ) {
		$cut  = mb_substr( $desc, 0, 155 );
		$sp   = mb_strrpos( $cut, ' ' );
		$desc = rtrim( $sp > 100 ? mb_substr( $cut, 0, $sp ) : $cut, " ,.;:-" ) . '…';
	}
	return $cache[ $post_id ] = $desc;
}

/** "123 Main St, Sandpoint, ID 83864" → "Sandpoint". */
function lis_directory_listing_meta_town( $address ) {
	$bits = array_values( array_filter( array_map( 'trim', explode( ',', $address ) ) ) );
	$n    = count( $bits );
	if ( $n < 2 ) {
		return '';
	}
	// The part before "ID 83864" / "Idaho" / a zip, else the second-to-last part.
	for ( $i = $n - 1; $i > 0; $i-- ) {
		if ( preg_match( '/^(ID|Idaho|MT|Montana|WA|Washington)\b|^\d{5}/i', $bits[ $i ] ) ) {
			$town = $bits[ $i - 1 ];
			return preg_match( '/\d/', $town ) ? '' : $town;
		}
	}
	$town = $bits[ $n - 1 ];
	return preg_match( '/\d/', $town ) ? '' : $town;
}

function lis_directory_listing_meta_applies() {
	if ( ! is_singular( 'lis_listing' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return $id && '' === trim( (string) get_post_meta( $id, '_seopress_titles_desc', true ) );
}

$GLOBALS['lis_directory_meta_done'] = array( 'desc' => false, 'og' => false, 'tw' => false );

add_filter( 'seopress_titles_desc', function ( $desc ) {
	if ( '' === trim( (string) $desc ) && lis_directory_listing_meta_applies() ) {
		$GLOBALS['lis_directory_meta_done']['desc'] = true;
		return lis_directory_listing_meta_desc( get_queried_object_id() );
	}
	return $desc;
} );

add_action( 'wp_head', function () {
	if ( ! lis_directory_listing_meta_applies() ) {
		return;
	}
	// Did SEOPress print anything? Check what's already gone out isn't possible, so rely
	// on the filter flag for the description, and always add og/twitter descriptions
	// (SEOPress skips them entirely when the post has no description).
	$desc = esc_attr( lis_directory_listing_meta_desc( get_queried_object_id() ) );
	if ( ! $GLOBALS['lis_directory_meta_done']['desc'] ) {
		echo '<meta name="description" content="' . $desc . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '<meta property="og:description" content="' . $desc . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<meta name="twitter:description" content="' . $desc . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 99 );
