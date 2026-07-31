<?php
/**
 * Single-listing template for `lis_listing`. Routed from
 * includes/listings-template.php unless a theme provides single-lis_listing.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$address = get_post_meta( $post_id, '_lis_listing_address', true );
	$phone   = get_post_meta( $post_id, '_lis_listing_phone', true );
	$website = get_post_meta( $post_id, '_lis_listing_website', true );
	$email   = get_post_meta( $post_id, '_lis_listing_email', true );
	?>
	<div class="lis-listing-single">
		<h1><?php the_title(); ?></h1>

		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'class' => 'lis-listing-single-thumb' ) ); ?>
		<?php endif; ?>

		<?php if ( $address || $phone || $website || $email ) : ?>
			<div class="lis-listing-meta">
				<?php if ( $address ) : ?><div><?php echo esc_html( $address ); ?></div><?php endif; ?>
				<?php if ( $phone ) : ?><div><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></div><?php endif; ?>
				<?php if ( $website ) : ?><div><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $website ); ?></a></div><?php endif; ?>
				<?php if ( $email ) : ?><div><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></div><?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="lis-listing-content">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
