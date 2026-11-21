<?php
/**
 * Listing photo display (0.60.0). One setting picks the layout for every
 * single-listing page: Settings → "Listing photo layout".
 *
 *   classic   — the original auto-fit grid (kept so nothing changes until a
 *               new layout is chosen).
 *   mosaic    — one large photo + smaller tiles, "Show all N photos".
 *   filmstrip — a full-width rail, every photo at its own aspect ratio.
 *   cover     — a full-bleed photo hero carrying the title, with thumbnails.
 *
 * Every new layout shares two pieces: the "ambient" frame for a single photo
 * (98 of 109 live listings have exactly one) — the whole photo, never cropped
 * or upscaled, on a blurred copy of itself instead of grey letterboxing — and
 * a lightbox (assets/js/listing-gallery.js) over plain <a href="full.jpg">
 * links, so photos still open with JavaScript off.
 *
 * Editors can preview any layout on one page with ?lis_gallery=<layout>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_gallery_setting' );

function lis_directory_gallery_styles() {
	return array(
		'classic'   => 'Classic grid',
		'mosaic'    => 'Mosaic (large photo + tiles)',
		'filmstrip' => 'Filmstrip (full-width rail)',
		'cover'     => 'Cover (photo hero with title)',
	);
}

function lis_directory_register_gallery_setting() {
	register_setting( 'lis_pv_settings_group', 'lis_directory_gallery_style', array(
		'type'              => 'string',
		'default'           => 'classic',
		'show_in_rest'      => true,
		'sanitize_callback' => function ( $value ) {
			$value = sanitize_key( $value );
			return isset( lis_directory_gallery_styles()[ $value ] ) ? $value : 'classic';
		},
	) );
}

/**
 * The layout for this request: ?lis_gallery= (editors only, for comparing
 * layouts on a live page) or the saved setting.
 */
function lis_directory_gallery_style() {
	$styles = lis_directory_gallery_styles();
	if ( isset( $_GET['lis_gallery'] ) && current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch.
		$preview = sanitize_key( wp_unslash( $_GET['lis_gallery'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $styles[ $preview ] ) ) {
			return $preview;
		}
	}
	$saved = get_option( 'lis_directory_gallery_style', 'classic' );
	return isset( $styles[ $saved ] ) ? $saved : 'classic';
}

/**
 * Photo attachment IDs for a listing: the gallery, else the featured image.
 */
function lis_directory_listing_photo_ids( $post_id ) {
	$raw = get_post_meta( $post_id, '_lis_listing_gallery_ids', true );
	$ids = $raw ? array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) : array();
	$ids = array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
	if ( ! $ids && has_post_thumbnail( $post_id ) ) {
		$ids = array( (int) get_post_thumbnail_id( $post_id ) );
	}
	return $ids;
}

/**
 * Move the photo the owner marked "Cover" (POST lis_listing_cover_photo) to
 * the front: the first photo is the listing's cover everywhere (featured
 * image, cards, the photo hero). The value is an attachment ID for a photo
 * already on the listing, or "new-N" for the Nth file in this upload.
 *
 * @param int[] $ids          Gallery IDs in order.
 * @param int[] $new_by_index File index => attachment ID for this upload.
 * @return int[]
 */
function lis_directory_cover_photo_first( $ids, $new_by_index = array() ) {
	$ids    = array_values( array_map( 'intval', (array) $ids ) );
	$choice = isset( $_POST['lis_listing_cover_photo'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_listing_cover_photo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- callers verify their form nonce.
	$cover  = 0;
	if ( preg_match( '/^new-(\d+)$/', $choice, $m ) ) {
		$cover = isset( $new_by_index[ (int) $m[1] ] ) ? (int) $new_by_index[ (int) $m[1] ] : 0;
	} elseif ( ctype_digit( $choice ) ) {
		$cover = (int) $choice;
	}
	if ( $cover && in_array( $cover, $ids, true ) ) {
		$ids = array_values( array_diff( $ids, array( $cover ) ) );
		array_unshift( $ids, $cover );
	}
	return $ids;
}

/**
 * What the layouts need about one photo.
 */
function lis_directory_gallery_photo( $attachment_id, $post_id ) {
	$full = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $full ) {
		return null;
	}
	$big   = wp_get_attachment_image_src( $attachment_id, '2048x2048' );
	$plate = wp_get_attachment_image_src( $attachment_id, 'medium' );
	return array(
		'id'    => (int) $attachment_id,
		'full'  => $big ? $big[0] : $full[0],
		'plate' => $plate ? $plate[0] : $full[0],
		'w'     => (int) $full[1],
		'h'     => (int) $full[2],
		'ratio' => $full[2] ? $full[1] / $full[2] : 1.5,
		'alt'   => lis_directory_listing_image_alt( $attachment_id, $post_id ),
	);
}

/**
 * A photo fills a cover hero well only if it's landscape and big enough not
 * to go soft at full width. Logos, portraits and small images get the
 * ambient frame instead.
 */
function lis_directory_gallery_photo_is_cover_worthy( $photo ) {
	return $photo && $photo['ratio'] >= 1.2 && $photo['w'] >= 1200;
}

/**
 * Whether the gallery renders the listing's <h1> (cover layout with photos),
 * so the header below leaves it out.
 */
function lis_directory_gallery_owns_title( $post_id ) {
	return 'cover' === lis_directory_gallery_style() && lis_directory_listing_photo_ids( $post_id );
}

function lis_directory_enqueue_gallery_assets() {
	wp_enqueue_style( 'lis-directory-listing-gallery', LIS_DIRECTORY_URL . 'assets/css/listing-gallery.css', array( 'lis-directory-listings' ), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-listing-gallery', LIS_DIRECTORY_URL . 'assets/js/listing-gallery.js', array(), LIS_DIRECTORY_VERSION, true );
}

/**
 * <img> for a gallery cell. `sizes` tells the browser how wide the cell is so
 * it picks a sensible file from the srcset.
 */
function lis_directory_gallery_img( $photo, $sizes, $attrs = array() ) {
	return wp_get_attachment_image( $photo['id'], 'large', false, array_merge( array(
		'class'    => 'lis-gal-img',
		'alt'      => $photo['alt'],
		'sizes'    => $sizes,
		'loading'  => 'lazy',
		'decoding' => 'async',
	), $attrs ) );
}

/**
 * Link wrapper the lightbox reads. Works as a plain link without JS.
 */
function lis_directory_gallery_link_open( $photo, $index, $class = 'lis-gal-cell' ) {
	return sprintf(
		'<a class="%1$s" href="%2$s" data-lis-photo="%3$d" data-w="%4$d" data-h="%5$d" data-alt="%6$s" aria-label="%7$s">',
		esc_attr( $class ),
		esc_url( $photo['full'] ),
		(int) $index,
		(int) $photo['w'],
		(int) $photo['h'],
		esc_attr( $photo['alt'] ),
		esc_attr( sprintf( 'Open photo %d: %s', $index + 1, $photo['alt'] ) )
	);
}

/**
 * Single photo, whole and uncropped, on a blurred copy of itself. Never wider
 * or taller than the file, so a 743px logo stays sharp.
 */
function lis_directory_gallery_ambient( $photo, $extra_class = '' ) {
	$max_w = (int) $photo['w'];
	ob_start();
	?>
	<figure class="lis-gal-ambient <?php echo esc_attr( $extra_class ); ?>">
		<div class="lis-gal-ambient-plate" style="background-image:url('<?php echo esc_url( $photo['plate'] ); ?>')" aria-hidden="true"></div>
		<?php echo lis_directory_gallery_link_open( $photo, 0, 'lis-gal-ambient-photo' ); // phpcs:ignore -- escaped inside helper. ?>
			<?php echo lis_directory_gallery_img( $photo, '(max-width: 720px) 100vw, 1100px', array( 'style' => 'max-width:min(100%,' . $max_w . 'px)', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); // phpcs:ignore -- core-built <img>. ?>
		</a>
	</figure>
	<?php
	return ob_get_clean();
}

/**
 * "Show all N photos" button. Opens the lightbox at the first photo.
 */
function lis_directory_gallery_all_button( $count, $class = '' ) {
	if ( $count < 2 ) {
		return '';
	}
	return sprintf(
		'<button type="button" class="lis-gal-all %1$s" data-lis-photo-open="0"><svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="2.5" width="6" height="6" rx="1"/><rect x="11.5" y="2.5" width="6" height="6" rx="1"/><rect x="2.5" y="11.5" width="6" height="6" rx="1"/><rect x="11.5" y="11.5" width="6" height="6" rx="1"/></svg>Show all %2$d photos</button>',
		esc_attr( $class ),
		(int) $count
	);
}

/**
 * The gallery block for a single listing, in the chosen layout. Returns ''
 * when the listing has no photos (the template shows its fallback mark).
 */
function lis_directory_render_listing_gallery( $post_id ) {
	$style = lis_directory_gallery_style();
	$ids   = lis_directory_listing_photo_ids( $post_id );
	if ( ! $ids || 'classic' === $style ) {
		return '';
	}
	$photos = array_values( array_filter( array_map( function ( $id ) use ( $post_id ) {
		return lis_directory_gallery_photo( $id, $post_id );
	}, $ids ) ) );
	if ( ! $photos ) {
		return '';
	}
	lis_directory_enqueue_gallery_assets();
	$count = count( $photos );

	if ( 'cover' === $style ) {
		return lis_directory_render_gallery_cover( $post_id, $photos );
	}

	ob_start();
	echo '<div class="lis-gal lis-gal--' . esc_attr( $style ) . ' lis-gal--n' . esc_attr( min( $count, 5 ) ) . '" data-lis-gallery data-count="' . (int) $count . '">';

	if ( 1 === $count ) {
		echo lis_directory_gallery_ambient( $photos[0] ); // phpcs:ignore -- escaped inside helper.
	} elseif ( 'mosaic' === $style ) {
		echo '<div class="lis-gal-mosaic" data-lis-track>';
		foreach ( $photos as $i => $photo ) {
			// Desktop shows the first five; the rest stay in the DOM for the phone carousel and the lightbox.
			$sizes = 0 === $i ? '(max-width: 720px) 100vw, 760px' : '(max-width: 720px) 100vw, 380px';
			echo lis_directory_gallery_link_open( $photo, $i, 'lis-gal-cell' . ( $i >= 5 ? ' lis-gal-cell--extra' : '' ) ); // phpcs:ignore -- escaped inside helper.
			echo lis_directory_gallery_img( $photo, $sizes, 0 === $i ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array() ); // phpcs:ignore -- core-built <img>.
			echo '</a>';
		}
		echo '</div>';
		echo '<span class="lis-gal-count" data-lis-count aria-hidden="true">1 / ' . (int) $count . '</span>';
		echo lis_directory_gallery_all_button( $count ); // phpcs:ignore -- escaped inside helper.
	} else { // filmstrip
		echo '<div class="lis-gal-rail" data-lis-track tabindex="0" role="region" aria-label="' . esc_attr( sprintf( '%d photos, scroll sideways', $count ) ) . '">';
		foreach ( $photos as $i => $photo ) {
			// Width follows the photo's own ratio at the rail's height.
			echo lis_directory_gallery_link_open( $photo, $i, 'lis-gal-frame' ); // phpcs:ignore -- escaped inside helper.
			echo lis_directory_gallery_img( $photo, '(max-width: 720px) 90vw, 720px', array_merge( array( 'style' => 'aspect-ratio:' . $photo['w'] . '/' . $photo['h'] ), 0 === $i ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array() ) ); // phpcs:ignore -- core-built <img>.
			echo '</a>';
		}
		echo '</div>';
		echo '<div class="lis-gal-rail-bar">';
		echo '<span class="lis-gal-count" data-lis-count aria-hidden="true">1 / ' . (int) $count . '</span>';
		echo lis_directory_gallery_all_button( $count, 'lis-gal-all--quiet' ); // phpcs:ignore -- escaped inside helper.
		echo '<span class="lis-gal-rail-nav"><button type="button" class="lis-gal-arrow" data-lis-prev aria-label="Previous photos">&larr;</button><button type="button" class="lis-gal-arrow" data-lis-next aria-label="Next photos">&rarr;</button></span>';
		echo '</div>';
	}

	echo '</div>';
	return ob_get_clean();
}

/**
 * Cover layout: a hero carrying the <h1>. The cover photo (the listing's
 * first photo) sits whole, straight, at its own aspect ratio as a card on a
 * blurred plate of itself, with the title beside it. The card's width follows
 * the photo's shape (landscape / square / portrait).
 */
function lis_directory_render_gallery_cover( $post_id, $photos ) {
	$count   = count( $photos );
	$lead    = $photos[0];
	/*
	 * The cover photo shows at the shape it was uploaded, as a card. Filling
	 * the whole hero (cropped, with parallax) is opt-in:
	 * add_filter( 'lis_directory_gallery_cover_fill', '__return_true' ).
	 */
	$fill    = apply_filters( 'lis_directory_gallery_cover_fill', false, $lead, $post_id ) && lis_directory_gallery_photo_is_cover_worthy( $lead );
	$shape   = $lead['ratio'] >= 1.15 ? 'is-landscape' : ( $lead['ratio'] <= 0.87 ? 'is-portrait' : 'is-square' );
	$tagline = get_post_meta( $post_id, '_lis_listing_tagline', true );
	$terms   = get_the_terms( $post_id, 'lis_listing_category' );
	$cat     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;

	ob_start();
	?>
	<section class="lis-gal lis-gal--cover <?php echo $fill ? 'is-fill' : 'is-framed ' . esc_attr( $shape ); ?>" data-lis-gallery data-count="<?php echo (int) $count; ?>">
		<div class="lis-gal-cover-stage">
			<?php if ( $fill ) : ?>
				<?php echo lis_directory_gallery_link_open( $lead, 0, 'lis-gal-cover-photo' ); // phpcs:ignore -- escaped inside helper. ?>
					<?php echo lis_directory_gallery_img( $lead, '100vw', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'data-lis-parallax' => '' ) ); // phpcs:ignore -- core-built <img>. ?>
				</a>
				<div class="lis-gal-cover-scrim" aria-hidden="true"></div>
			<?php else : ?>
				<div class="lis-gal-ambient-plate" style="background-image:url('<?php echo esc_url( $lead['plate'] ); ?>')" aria-hidden="true"></div>
				<?php echo lis_directory_gallery_link_open( $lead, 0, 'lis-gal-cover-card' ); // phpcs:ignore -- escaped inside helper. ?>
					<?php echo lis_directory_gallery_img( $lead, '(max-width: 720px) 92vw, 680px', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'style' => 'max-width:min(100%,' . (int) $lead['w'] . 'px)' ) ); // phpcs:ignore -- core-built <img>. ?>
				</a>
			<?php endif; ?>
			<div class="lis-gal-cover-copy">
				<?php if ( $cat ) : ?>
					<a class="lis-gal-cover-kicker" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endif; ?>
				<h1 class="lis-listing-title lis-gal-cover-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<?php if ( $tagline ) : ?>
					<p class="lis-gal-cover-tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $count > 1 ) : ?>
			<div class="lis-gal-cover-thumbs">
				<?php foreach ( $photos as $i => $photo ) : ?>
					<?php if ( $i > 4 ) { break; } ?>
					<?php echo lis_directory_gallery_link_open( $photo, $i, 'lis-gal-thumb' . ( 0 === $i ? ' is-current' : '' ) ); // phpcs:ignore -- escaped inside helper. ?>
						<?php echo wp_get_attachment_image( $photo['id'], 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
						<?php if ( 4 === $i && $count > 5 ) : ?><span class="lis-gal-thumb-more">+<?php echo (int) ( $count - 5 ); ?></span><?php endif; ?>
					</a>
				<?php endforeach; ?>
				<?php echo lis_directory_gallery_all_button( $count, 'lis-gal-all--quiet' ); // phpcs:ignore -- escaped inside helper. ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Today's hours as one human line: "Open now · until 9 pm",
 * "Closed · opens 7 am", "Closed · opens Monday 7 am". '' when no hours.
 */
function lis_directory_listing_hours_status_line( $post_id ) {
	$week = lis_directory_get_listing_week_hours( $post_id );
	if ( ! array_filter( $week ) ) {
		return '';
	}
	$days  = array_keys( LIS_DIRECTORY_WEEKDAYS );
	$today = (int) current_time( 'N' ) - 1;
	$now   = current_time( 'H:i' );
	$hours = $week[ $days[ $today ] ];
	$fmt   = function ( $t ) {
		return str_replace( ':00', '', lis_directory_format_time( $t ) );
	};

	if ( $hours && $now >= $hours['open'] && $now < $hours['close'] ) {
		return 'Open now · until ' . $fmt( $hours['close'] );
	}
	if ( $hours && $now < $hours['open'] ) {
		return 'Closed · opens ' . $fmt( $hours['open'] );
	}
	for ( $step = 1; $step <= 7; $step++ ) {
		$day  = $days[ ( $today + $step ) % 7 ];
		$next = $week[ $day ];
		if ( $next ) {
			$when = 1 === $step ? 'tomorrow' : LIS_DIRECTORY_WEEKDAYS[ $day ];
			return 'Closed · opens ' . $when . ' ' . $fmt( $next['open'] );
		}
	}
	return 'Closed';
}

/**
 * Call / Directions / Website, the three things most people open a listing
 * for, as real buttons under the title.
 */
function lis_directory_render_listing_quick_actions( $post_id ) {
	$phone   = get_post_meta( $post_id, '_lis_listing_phone', true );
	$address = get_post_meta( $post_id, '_lis_listing_address', true );
	$website = get_post_meta( $post_id, '_lis_listing_website', true );
	if ( ! $phone && ! $address && ! $website ) {
		return '';
	}
	$icon = array(
		'call' => '<path d="M5.5 3.5h2.4l1.3 3.4-1.7 1.1a9 9 0 0 0 4.5 4.5l1.1-1.7 3.4 1.3v2.4a1.5 1.5 0 0 1-1.6 1.5A13 13 0 0 1 4 5.1 1.5 1.5 0 0 1 5.5 3.5Z"/>',
		'dir'  => '<path d="M10 2.5 17.5 10 10 17.5 2.5 10Z"/><path d="M8 11.5V9.5h4M10.5 8l1.5 1.5-1.5 1.5"/>',
		'web'  => '<circle cx="10" cy="10" r="7.5"/><path d="M2.5 10h15M10 2.5c2.2 2.3 2.2 12.7 0 15M10 2.5c-2.2 2.3-2.2 12.7 0 15"/>',
	);
	$svg = function ( $name ) use ( $icon ) {
		return '<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round">' . $icon[ $name ] . '</svg>';
	};
	$out = '<nav class="lis-listing-quick" aria-label="Contact ' . esc_attr( get_the_title( $post_id ) ) . '">';
	if ( $phone ) {
		$out .= '<a class="lis-listing-quick-btn is-primary" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . $svg( 'call' ) . '<span>Call</span></a>';
	}
	if ( $address ) {
		$out .= '<a class="lis-listing-quick-btn" href="' . esc_url( 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $address ) ) . '" target="_blank" rel="noopener noreferrer">' . $svg( 'dir' ) . '<span>Directions</span></a>';
	}
	if ( $website ) {
		$out .= '<a class="lis-listing-quick-btn" href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">' . $svg( 'web' ) . '<span>Website</span></a>';
	}
	return $out . '</nav>';
}
