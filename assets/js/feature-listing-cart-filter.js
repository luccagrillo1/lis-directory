/**
 * Appends "— Featuring: <listing name>" to the "Feature My Listing" cart
 * line in the WooCommerce Cart/Checkout blocks. The listing name itself
 * comes from Store API extension data registered server-side in
 * includes/listings-pricing.php (lis_directory_register_feature_listing_store_api_data) -
 * this file is the front-end half that actually renders it, since Blocks
 * ignores the classic `woocommerce_get_item_data` filter entirely.
 */
( function () {
	if ( ! window.wc || ! window.wc.blocksCheckout || typeof window.wc.blocksCheckout.registerCheckoutFilters !== 'function' ) {
		return;
	}

	window.wc.blocksCheckout.registerCheckoutFilters( 'lis-directory', {
		cartItemName: function ( value, extensions ) {
			var lisData = extensions && extensions[ 'lis-directory' ];
			if ( lisData && lisData.listing_name ) {
				return value + ' — Featuring: ' + lisData.listing_name;
			}
			return value;
		},
	} );
} )();
