<?php
/**
 * [lis_preferred_vendor_card category="slug"] — single static card for one category.
 *
 * CSS is inlined via a <style> tag on first use rather than wp_enqueue_style()'d,
 * because shortcodes render during the_content (after wp_head has already fired
 * in most themes) — an enqueue at that point would silently miss <head> output.
 * Inlining once per request sidesteps that timing problem for a component this
 * small. The <style> string is RETURNED as part of the shortcode's own output,
 * never echoed directly — see lis_directory_get_card_styles_once() for why that
 * distinction matters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_preferred_vendor_card', 'lis_directory_render_vendor_card_shortcode' );

function lis_directory_render_vendor_card_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'category' => '' ), $atts, 'lis_preferred_vendor_card' );
	$slug = sanitize_title( $atts['category'] );

	if ( '' === $slug ) {
		return lis_directory_card_admin_hint( 'lis_preferred_vendor_card requires a category="slug" attribute.' );
	}

	$term = get_term_by( 'slug', $slug, 'lis_vendor_category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return lis_directory_card_admin_hint( sprintf( 'No vendor category found for slug "%s".', $slug ) );
	}

	$vendor = lis_directory_get_active_vendor_for_category( $term->term_id );
	if ( ! $vendor ) {
		return lis_directory_card_admin_hint( sprintf( 'No active vendor showcase entry for category "%s" yet.', $term->name ) );
	}

	return lis_directory_render_vendor_card_html( $vendor );
}

/**
 * Placement mistakes (bad slug, no active vendor yet) should be invisible to the
 * public but visible to whoever can edit content, so a shortcode left on a page
 * doesn't just silently render nothing forever.
 */
function lis_directory_card_admin_hint( $message ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return '<p class="lis-pv-admin-hint" style="border:1px dashed #c00;padding:8px;color:#c00;">LIS Directory: ' . esc_html( $message ) . '</p>';
}

function lis_directory_render_vendor_card_html( $vendor ) {
	$tagline  = get_post_meta( $vendor->ID, '_lis_pv_tagline', true );
	$link_url = get_post_meta( $vendor->ID, '_lis_pv_link_url', true );
	$logo_id  = (int) get_post_meta( $vendor->ID, '_lis_pv_logo_color_id', true );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$name     = get_the_title( $vendor );
	$tag      = $link_url ? 'a' : 'div';

	$styles = lis_directory_get_card_styles_once();

	ob_start();
	?>
	<div class="lis-pv-card">
		<<?php echo esc_html( $tag ); ?> class="lis-pv-card-inner"<?php if ( $link_url ) : ?> href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer"<?php endif; ?>>
			<?php if ( $logo_url ) : ?>
				<img class="lis-pv-card-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
			<?php endif; ?>
			<div class="lis-pv-card-body">
				<div class="lis-pv-card-name"><?php echo esc_html( $name ); ?></div>
				<?php if ( $tagline ) : ?><div class="lis-pv-card-tagline"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
			</div>
		</<?php echo esc_html( $tag ); ?>>
	</div>
	<?php
	return $styles . ob_get_clean();
}

/**
 * Returns (never echoes) the card CSS wrapped in a <style> tag, once per
 * request. Must stay inside the shortcode's own returned string — echoing it
 * directly, as earlier versions did, escapes whatever buffer `the_content` is
 * building. That's invisible on a normal template render (the stray output
 * just lands elsewhere in the page and browsers don't care), but corrupts any
 * context that captures the_content as a string to serialize elsewhere: the
 * REST API's content.rendered field, admin-ajax previews, feeds, excerpts.
 * Caught via the REST API returning "<style>...</style>{...}" instead of JSON
 * when creating a test page with this shortcode in its content.
 */
function lis_directory_get_card_styles_once() {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$css_path = LIS_DIRECTORY_PATH . 'assets/css/vendor-card.css';
	if ( ! file_exists( $css_path ) ) {
		return '';
	}
	return '<style id="lis-pv-card-style">' . file_get_contents( $css_path ) . '</style>'; // phpcs:ignore -- static local asset, not user input.
}

/**
 * [lis_preferred_vendor_heading] — the "LIS Partners" branding treatment,
 * meant to sit above a card or ticker wherever they're placed on the site.
 * Uses the pre-built "LIS Partners" lockup image supplied directly by the
 * client rather than this plugin recreating the pairing from a separate
 * icon + text — that lockup was already designed and kerned as one unit,
 * so reassembling it in CSS was solving a problem that didn't need solving.
 * Still wrapped in a real heading tag by default (h2) for document
 * structure — `tag="h3"` etc. to fit a page's own heading hierarchy.
 */
add_shortcode( 'lis_preferred_vendor_heading', 'lis_directory_render_heading_shortcode' );

function lis_directory_render_heading_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'tag' => 'h2' ), $atts, 'lis_preferred_vendor_heading' );
	$tag  = in_array( $atts['tag'], array( 'h1', 'h2', 'h3', 'h4', 'div' ), true ) ? $atts['tag'] : 'h2';

	$styles = lis_directory_get_heading_styles_once();

	ob_start();
	?>
	<<?php echo esc_html( $tag ); ?> class="lis-pv-heading">
		<img class="lis-pv-heading-lockup" src="<?php echo esc_url( LIS_DIRECTORY_URL . 'assets/img/lis-partners-lockup.png?ver=' . LIS_DIRECTORY_VERSION ); ?>" alt="LIS Partners" />
	</<?php echo esc_html( $tag ); ?>>
	<?php
	return $styles . ob_get_clean();
}

/** See lis_directory_get_card_styles_once() — same "return, don't echo" reasoning. */
function lis_directory_get_heading_styles_once() {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$css_path = LIS_DIRECTORY_PATH . 'assets/css/vendor-heading.css';
	if ( ! file_exists( $css_path ) ) {
		return '';
	}
	return '<style id="lis-pv-heading-style">' . file_get_contents( $css_path ) . '</style>'; // phpcs:ignore -- static local asset, not user input.
}

/**
 * [lis_preferred_vendor_ticker] — horizontally auto-scrolling row of every
 * active vendor, across all categories. Two display styles:
 * `style="cards"` (default) — the same card as [lis_preferred_vendor_card].
 * `style="logos"` — logos only, no name/tagline text (added after v0.5.2 as
 * a second display option, alongside the original card version). In logos
 * mode, `tone="black"` / `tone="white"` recolors each vendor's single
 * uploaded logo via CSS instead of requiring a separate upload per tone —
 * default `tone="color"` shows the logo as uploaded.
 * Placement is manual; there is no auto-injection into Directorist pages.
 */
add_shortcode( 'lis_preferred_vendor_ticker', 'lis_directory_render_vendor_ticker_shortcode' );

function lis_directory_render_vendor_ticker_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'style' => 'cards', 'tone' => 'color' ), $atts, 'lis_preferred_vendor_ticker' );
	$style = ( 'logos' === $atts['style'] ) ? 'logos' : 'cards';
	$tone  = in_array( $atts['tone'], array( 'black', 'white' ), true ) ? $atts['tone'] : 'color';

	$vendors = lis_directory_get_all_active_vendors();

	if ( empty( $vendors ) ) {
		return lis_directory_card_admin_hint( 'No active vendor showcase entries yet — the ticker has nothing to show.' );
	}

	$items = array();
	foreach ( $vendors as $vendor ) {
		$items[] = ( 'logos' === $style )
			? lis_directory_render_vendor_logo_html( $vendor, $tone )
			: lis_directory_render_vendor_card_html( $vendor );
	}
	$items = array_filter( $items ); // A vendor with no logo renders '' in logos mode — drop it, not an empty slot.

	if ( empty( $items ) ) {
		return lis_directory_card_admin_hint( 'logos' === $style
			? 'No active vendor showcase entries have a logo uploaded yet — the logo ticker has nothing to show.'
			: 'No active vendor showcase entries yet — the ticker has nothing to show.'
		);
	}

	$styles = lis_directory_get_ticker_styles_once();
	$styles .= ( 'logos' === $style ) ? lis_directory_get_ticker_logo_styles_once() : '';
	$items_html = implode( '', $items );

	ob_start();
	?>
	<div class="lis-pv-ticker lis-pv-ticker-<?php echo esc_attr( $style ); ?>" role="region" aria-label="Vendor showcase">
		<div class="lis-pv-ticker-track">
			<div class="lis-pv-ticker-group"><?php echo $items_html; // phpcs:ignore -- built from escaped markup above. ?></div>
			<div class="lis-pv-ticker-group lis-pv-ticker-copy" aria-hidden="true"><?php echo $items_html; // phpcs:ignore -- same. ?></div>
		</div>
	</div>
	<?php
	return $styles . ob_get_clean();
}

/**
 * Logo-only ticker item — no card, no name/tagline, just the logo (linked if
 * the vendor has a Directorist listing URL). Returns '' for a vendor with no
 * logo uploaded, since there's nothing sensible to show.
 *
 * `$tone` recolors the single uploaded logo instead of requiring a second
 * upload: `brightness(0)` turns every non-transparent pixel solid black,
 * and `brightness(0) invert(1)` flips that to solid white. Only looks right
 * against a transparent PNG, which is why the logo upload is PNG-only (see
 * includes/submission.php).
 */
function lis_directory_render_vendor_logo_html( $vendor, $tone = 'color' ) {
	$logo_id  = (int) get_post_meta( $vendor->ID, '_lis_pv_logo_color_id', true );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	if ( ! $logo_url ) {
		return '';
	}

	$link_url = get_post_meta( $vendor->ID, '_lis_pv_link_url', true );
	$name     = get_the_title( $vendor );
	$tag      = $link_url ? 'a' : 'div';
	$tone_class = in_array( $tone, array( 'black', 'white' ), true ) ? ' lis-pv-ticker-logo--' . $tone : '';

	ob_start();
	?>
	<<?php echo esc_html( $tag ); ?> class="lis-pv-ticker-logo-item"<?php if ( $link_url ) : ?> href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer"<?php endif; ?>>
		<img class="lis-pv-ticker-logo<?php echo esc_attr( $tone_class ); ?>" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
	</<?php echo esc_html( $tag ); ?>>
	<?php
	return ob_get_clean();
}

/** See lis_directory_get_card_styles_once() — same "return, don't echo" reasoning. */
function lis_directory_get_ticker_styles_once() {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$css_path = LIS_DIRECTORY_PATH . 'assets/css/vendor-ticker.css';
	if ( ! file_exists( $css_path ) ) {
		return '';
	}
	return '<style id="lis-pv-ticker-style">' . file_get_contents( $css_path ) . '</style>'; // phpcs:ignore -- static local asset, not user input.
}

/** See lis_directory_get_card_styles_once() — same "return, don't echo" reasoning. */
function lis_directory_get_ticker_logo_styles_once() {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$css_path = LIS_DIRECTORY_PATH . 'assets/css/vendor-ticker-logos.css';
	if ( ! file_exists( $css_path ) ) {
		return '';
	}
	return '<style id="lis-pv-ticker-logos-style">' . file_get_contents( $css_path ) . '</style>'; // phpcs:ignore -- static local asset, not user input.
}
