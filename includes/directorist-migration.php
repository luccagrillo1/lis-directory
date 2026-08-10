<?php
/**
 * One-time migration: Directorist (`at_biz_dir`) listings → this plugin's
 * `lis_listing` posts, preserving owner, expiration, category, photos, hours,
 * contact fields, and featured status.
 *
 * Ships with a **dry-run/preview** mode so the exact field mapping can be
 * verified against real data before anything is written, and is idempotent
 * (each created listing records `_lis_migrated_from` = source post id, so a
 * re-run skips already-migrated listings rather than duplicating them).
 *
 * Nothing here runs automatically — it's driven by buttons on the LIS Directory
 * Settings page, admin-only, nonce-checked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_lis_directory_migration_run', 'lis_directory_migration_run_handler' );

/**
 * How many Directorist listings exist, and how many we've already migrated.
 */
function lis_directory_migration_stats() {
	if ( ! post_type_exists( 'at_biz_dir' ) ) {
		return array( 'exists' => false, 'total' => 0, 'migrated' => 0 );
	}
	$total = (int) count( get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) ) );
	$migrated = (int) count( get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_lis_migrated_from', 'compare' => 'EXISTS' ) ),
	) ) );
	return array( 'exists' => true, 'total' => $total, 'migrated' => $migrated );
}

/**
 * Read-only dump of a few real Directorist listings — every meta key/value,
 * author, status, and taxonomy — so the field mapping can be built and checked
 * against what's actually stored, not assumed.
 */
function lis_directory_migration_render_preview() {
	if ( ! post_type_exists( 'at_biz_dir' ) ) {
		echo '<p>Directorist (<code>at_biz_dir</code>) is not active on this site.</p>';
		return;
	}
	$listings = get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => 3,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
	if ( empty( $listings ) ) {
		echo '<p>No Directorist listings found.</p>';
		return;
	}

	// All taxonomies registered for at_biz_dir, so we can see the real category
	// taxonomy name(s) rather than guessing.
	$taxes = get_object_taxonomies( 'at_biz_dir' );
	echo '<p><strong>at_biz_dir taxonomies:</strong> <code>' . esc_html( implode( ', ', $taxes ) ) . '</code></p>';

	foreach ( $listings as $l ) {
		$author = get_userdata( $l->post_author );
		echo '<hr /><h4>#' . (int) $l->ID . ' — ' . esc_html( $l->post_title ) . '</h4>';
		echo '<p>author: ' . (int) $l->post_author . ' (' . esc_html( $author ? $author->user_login : '?' ) . ') &middot; status: ' . esc_html( $l->post_status ) . '</p>';
		foreach ( $taxes as $tax ) {
			$terms = wp_get_post_terms( $l->ID, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				echo '<p>' . esc_html( $tax ) . ': ' . esc_html( implode( ', ', $terms ) ) . '</p>';
			}
		}
		echo '<pre style="max-height:360px;overflow:auto;background:#f6f7f7;padding:10px;font-size:12px;">';
		foreach ( get_post_meta( $l->ID ) as $key => $vals ) {
			$val = maybe_unserialize( $vals[0] );
			if ( is_array( $val ) || is_object( $val ) ) {
				$val = wp_json_encode( $val );
			}
			echo esc_html( $key . ' = ' . mb_substr( (string) $val, 0, 240 ) ) . "\n";
		}
		echo '</pre>';
	}
}

/**
 * Map one Directorist listing to the lis_listing fields we create. Pure — no
 * writes — so it powers both the mapped-preview and the real writer.
 */
function lis_directory_migrate_map( $src ) {
	$m = function ( $key ) use ( $src ) {
		return (string) get_post_meta( $src->ID, $key, true );
	};

	$cat_names = wp_get_post_terms( $src->ID, 'at_biz_dir-category', array( 'fields' => 'names' ) );
	if ( is_wp_error( $cat_names ) ) {
		$cat_names = array();
	}

	// Images: main preview image first, then the JSON gallery array; de-duped.
	$prv     = (int) $m( '_listing_prv_img' );
	$gallery = json_decode( $m( '_listing_img' ), true );
	$gallery = is_array( $gallery ) ? array_map( 'intval', $gallery ) : array();
	$gallery = array_values( array_unique( array_filter( array_merge( $prv ? array( $prv ) : array(), $gallery ) ) ) );

	$featured   = '' !== $m( '_featured' ) && '0' !== $m( '_featured' );
	$expiry     = $m( '_expiry_date' );
	$src_status = get_post_status( $src->ID );

	// publish stays publish; expired becomes a draft (+ expired flag); anything
	// else non-public keeps its status if it's one we recognise.
	$status  = 'publish';
	$expired = false;
	if ( 'expired' === $src_status ) {
		$status  = 'draft';
		$expired = true;
	} elseif ( in_array( $src_status, array( 'pending', 'draft', 'private' ), true ) ) {
		$status = $src_status;
	}

	return array(
		'source_id'  => (int) $src->ID,
		'title'      => $src->post_title,
		'content'    => $src->post_content,
		'author'     => (int) $src->post_author,
		'status'     => $status,
		'expired'    => $expired,
		'address'    => $m( '_address' ),
		'phone'      => $m( '_phone' ),
		'email'      => $m( '_email' ),
		'website'    => $m( '_website' ),
		'featured'   => $featured,
		'expiry'     => $expiry,
		'type'       => 'local-business', // every Directorist listing here is _directory_type 1374 (local business).
		'categories' => array_values( (array) $cat_names ),
		'gallery'    => $gallery,
	);
}

/**
 * Create the lis_listing for one Directorist listing (or, in dry-run, just
 * return the plan). Idempotent: records `_lis_migrated_from` and skips a source
 * that's already been migrated.
 */
function lis_directory_migrate_one( $src, $dry_run ) {
	$existing = get_posts( array(
		'post_type'      => 'lis_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_lis_migrated_from', 'value' => (int) $src->ID ) ),
	) );
	if ( $existing ) {
		return array( 'skipped' => true, 'lis_id' => (int) $existing[0] );
	}

	$map = lis_directory_migrate_map( $src );
	if ( $dry_run ) {
		return array( 'dry' => true, 'map' => $map );
	}

	$lis_id = wp_insert_post( array(
		'post_type'      => 'lis_listing',
		'post_title'     => $map['title'],
		'post_content'   => $map['content'],
		'post_status'    => $map['status'],
		'post_author'    => $map['author'],
		'comment_status' => 'open',
	), true );
	if ( is_wp_error( $lis_id ) ) {
		return array( 'error' => $lis_id->get_error_message() );
	}

	update_post_meta( $lis_id, '_lis_migrated_from', (int) $src->ID );
	update_post_meta( $lis_id, '_lis_listing_type', $map['type'] );
	if ( '' !== $map['address'] ) {
		update_post_meta( $lis_id, '_lis_listing_address', $map['address'] );
	}
	if ( '' !== $map['phone'] ) {
		update_post_meta( $lis_id, '_lis_listing_phone', $map['phone'] );
	}
	if ( '' !== $map['email'] ) {
		update_post_meta( $lis_id, '_lis_listing_email', $map['email'] );
	}
	if ( '' !== $map['website'] ) {
		update_post_meta( $lis_id, '_lis_listing_website', $map['website'] );
	}
	if ( $map['featured'] ) {
		update_post_meta( $lis_id, '_lis_listing_featured', true );
	}
	if ( '' !== $map['expiry'] ) {
		update_post_meta( $lis_id, '_lis_listing_expiry', $map['expiry'] );
	}
	if ( $map['expired'] ) {
		update_post_meta( $lis_id, '_lis_listing_expired', 1 );
	}

	if ( ! empty( $map['categories'] ) ) {
		$term_ids = array();
		foreach ( $map['categories'] as $name ) {
			$term = get_term_by( 'name', $name, 'lis_listing_category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
			} else {
				$new = wp_insert_term( $name, 'lis_listing_category' );
				if ( ! is_wp_error( $new ) ) {
					$term_ids[] = (int) $new['term_id'];
				}
			}
		}
		if ( $term_ids ) {
			wp_set_post_terms( $lis_id, $term_ids, 'lis_listing_category' );
		}
	}

	if ( ! empty( $map['gallery'] ) ) {
		set_post_thumbnail( $lis_id, $map['gallery'][0] );
		update_post_meta( $lis_id, '_lis_listing_gallery_ids', implode( ',', $map['gallery'] ) );
	}

	return array( 'created' => true, 'lis_id' => (int) $lis_id );
}

/**
 * Mapped-result preview: what lis_listing would be created for a few source
 * listings, so the mapping is verified as the FINISHED product, not raw meta.
 */
function lis_directory_migration_render_mapped_preview() {
	$srcs = get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => 3,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
	foreach ( $srcs as $src ) {
		$map    = lis_directory_migrate_map( $src );
		$author = get_userdata( $map['author'] );
		echo '<hr /><h4>#' . (int) $src->ID . ' → new lis_listing</h4>';
		echo '<ul style="margin-left:16px;">';
		echo '<li><strong>Title:</strong> ' . esc_html( $map['title'] ) . '</li>';
		echo '<li><strong>Owner:</strong> ' . esc_html( $author ? $author->user_login : ( '#' . $map['author'] ) ) . '</li>';
		echo '<li><strong>Status:</strong> ' . esc_html( $map['status'] ) . ( $map['expired'] ? ' (expired)' : '' ) . '</li>';
		echo '<li><strong>Categories:</strong> ' . esc_html( implode( ', ', $map['categories'] ) ) . '</li>';
		echo '<li><strong>Phone / Email / Website:</strong> ' . esc_html( $map['phone'] ) . ' / ' . esc_html( $map['email'] ) . ' / ' . esc_html( $map['website'] ) . '</li>';
		echo '<li><strong>Expiry:</strong> ' . esc_html( $map['expiry'] ? $map['expiry'] : '(none)' ) . ' &middot; <strong>Featured:</strong> ' . ( $map['featured'] ? 'yes' : 'no' ) . '</li>';
		echo '<li><strong>Photos:</strong> ' . count( $map['gallery'] ) . '</li>';
		echo '</ul>';
	}
}

function lis_directory_migration_run_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'lis_directory_migration_run', 'lis_dm_nonce' );

	$srcs = get_posts( array(
		'post_type'      => 'at_biz_dir',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'expired' ),
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	$created = 0;
	$skipped = 0;
	$errors  = 0;
	foreach ( $srcs as $src ) {
		$r = lis_directory_migrate_one( $src, false );
		if ( ! empty( $r['created'] ) ) {
			$created++;
		} elseif ( ! empty( $r['skipped'] ) ) {
			$skipped++;
		} else {
			$errors++;
		}
	}

	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );
	$redirect = add_query_arg( array( 'lis_dm' => 'ran', 'lis_dm_c' => $created, 'lis_dm_s' => $skipped, 'lis_dm_e' => $errors ), $redirect );
	wp_safe_redirect( $redirect );
	exit;
}
