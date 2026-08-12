<?php
/**
 * WooCommerce Subscriptions integration (build brief step 6).
 *
 * Open Decision #1 resolved 2026-07-27: recurring subscription. WooCommerce
 * Subscriptions (v9.0.1) confirmed installed and active on
 * livinginsandpoint.com by inspecting wp-admin > Plugins directly.
 *
 * Recommended setup (done by hand in wp-admin, NOT by this plugin): one
 * variable WooCommerce Subscription product ("Vendor Showcase"), with one
 * variation per lis_vendor_category term. Each variation is tagged with the
 * category it represents via the "LIS Vendor Category" field this file adds
 * to the variation admin UI — that tag is how the rest of this file finds
 * "the Vendor Showcase variation for category X" without a dedicated
 * settings screen naming the product itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lis_directory_add_settings_page' );
add_action( 'init', 'lis_directory_register_settings' ); // Not admin_init - that never fires on REST requests, which would silently keep show_in_rest settings invisible via /wp-json/wp/v2/settings regardless of anything else being right.
add_action( 'plugins_loaded', 'lis_directory_init_woocommerce_integration' );

function lis_directory_add_settings_page() {
	add_submenu_page(
		'edit.php?post_type=lis_preferred_vendor',
		'LIS Directory Settings',
		'Settings',
		'manage_options',
		'lis_pv_settings',
		'lis_directory_render_settings_page'
	);
}

function lis_directory_register_settings() {
	register_setting( 'lis_pv_settings_group', 'lis_pv_submission_page_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
	) );
	register_setting( 'lis_pv_settings_group', 'lis_directory_google_maps_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'show_in_rest'      => true,
	) );
}

function lis_directory_render_settings_page() {
	?>
	<div class="wrap">
		<h1>LIS Directory Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'lis_pv_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="lis_pv_submission_page_id">Submission Form Page</label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'lis_pv_submission_page_id',
							'selected'         => (int) get_option( 'lis_pv_submission_page_id' ),
							'show_option_none' => '— Select the page with [lis_preferred_vendor_submit] —',
						) );
						?>
						<p class="description">Used to build the "submit your details" link shown on the WooCommerce thank-you page after a Vendor Showcase purchase.</p>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_listing_edit_page_id">Listing Edit Page</label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'lis_directory_listing_edit_page_id',
							'selected'         => (int) get_option( 'lis_directory_listing_edit_page_id' ),
							'show_option_none' => '— Select the page with [lis_listing_edit] —',
						) );
						?>
						<p class="description">Used to build the "Edit" link on the front-end Dashboard for users who don't have wp-admin edit access to their own listing (the common case for front-end signups).</p>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_featured_listing_product_id">Featured Listing Product</label></th>
					<td>
						<?php
						$featured_product_id = (int) get_option( 'lis_directory_featured_listing_product_id' );
						if ( class_exists( 'WooCommerce' ) ) :
							$products = get_posts( array(
								'post_type'      => 'product',
								'post_status'    => 'publish',
								'posts_per_page' => -1,
								'orderby'        => 'title',
								'order'          => 'ASC',
							) );
							?>
							<select id="lis_directory_featured_listing_product_id" name="lis_directory_featured_listing_product_id">
								<option value="0">— Not configured —</option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo (int) $product->ID; ?>" <?php selected( $featured_product_id, $product->ID ); ?>><?php echo esc_html( get_the_title( $product ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">The WooCommerce product a listing owner buys to feature their listing. Set up the product itself (price, one-time or WC Subscriptions) by hand — this just tells the plugin which product to watch for.</p>
						<?php else : ?>
							<p class="description">WooCommerce isn't active, so this can't be configured.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="lis_directory_google_maps_api_key">Google Maps API Key</label></th>
					<td>
						<input type="text" id="lis_directory_google_maps_api_key" name="lis_directory_google_maps_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'lis_directory_google_maps_api_key' ) ); ?>" />
						<p class="description">Enables an embedded map on each listing's page (Google Maps Embed API, using the listing's address). Leave blank to keep showing just the "open in Google Maps" link instead. Restrict this key to your domain in the Google Cloud Console — it's visible in page source, same as any Maps Embed key.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />
		<h2>Vendor Showcase product</h2>
		<?php if ( isset( $_GET['lis_vs_gen'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, no state change. ?>
			<?php if ( 'ok' === $_GET['lis_vs_gen'] ) : ?>
				<div class="notice notice-success inline"><p>Vendor Showcase updated &mdash; added <?php echo isset( $_GET['lis_vs_added'] ) ? (int) $_GET['lis_vs_added'] : 0; ?> new variation(s).</p></div>
			<?php else : ?>
				<div class="notice notice-error inline"><p>Could not build the Vendor Showcase product. Make sure WooCommerce is active.</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<p class="description" style="max-width:640px;">
			Builds the single <strong>Vendor Showcase</strong> WooCommerce Subscription
			product: one Monthly ($100/mo) and one Annually ($1,000/yr) billing variation
			(placeholder prices &mdash; <strong>edit them in WooCommerce</strong>). One
			product, no per-category variations &mdash; the category a buyer occupies is
			their listing's own category, and exclusivity (one active vendor per category)
			is enforced automatically at checkout. Safe to re-run &mdash; it only fills a
			missing Monthly/Annually variation and never touches prices you've edited.
			Created as a <strong>draft</strong>; publish it when you're ready to sell.
		</p>
		<?php
		$vs_product_id = lis_directory_get_vendor_showcase_product_id();
		if ( $vs_product_id ) :
			?>
			<p><strong>Current product:</strong>
				<a href="<?php echo esc_url( get_edit_post_link( $vs_product_id ) ); ?>">Vendor Showcase (#<?php echo (int) $vs_product_id; ?>)</a>
				&mdash; status: <?php echo esc_html( get_post_status( $vs_product_id ) ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lis_directory_generate_vendor_showcase" />
			<?php wp_nonce_field( 'lis_directory_generate_vendor_showcase', 'lis_vs_gen_nonce' ); ?>
			<?php submit_button( $vs_product_id ? 'Top up Vendor Showcase product' : 'Generate Vendor Showcase product', 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2>Standard Listing product</h2>
		<?php if ( isset( $_GET['lis_std_gen'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, no state change. ?>
			<?php if ( 'ok' === $_GET['lis_std_gen'] ) : ?>
				<div class="notice notice-success inline"><p>Standard Listing product updated &mdash; added <?php echo isset( $_GET['lis_std_added'] ) ? (int) $_GET['lis_std_added'] : 0; ?> new variation(s).</p></div>
			<?php else : ?>
				<div class="notice notice-error inline"><p>Could not build the Standard Listing product. Make sure WooCommerce is active.</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<p class="description" style="max-width:640px;">
			Builds the base <strong>Standard Listing</strong> WooCommerce Subscription
			product: a Monthly and an Annually billing variation (placeholder prices
			&mdash; <strong>edit them in WooCommerce</strong>), no category limit. This is
			the product every plain paid listing checks out through. Created as a
			<strong>draft</strong>; set your prices, then publish it when you're ready to sell.
		</p>
		<?php
		$std_product_id = lis_directory_get_standard_listing_product_id();
		if ( $std_product_id ) :
			?>
			<p><strong>Current product:</strong>
				<a href="<?php echo esc_url( get_edit_post_link( $std_product_id ) ); ?>">Standard Listing (#<?php echo (int) $std_product_id; ?>)</a>
				&mdash; status: <?php echo esc_html( get_post_status( $std_product_id ) ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lis_directory_generate_standard_listing" />
			<?php wp_nonce_field( 'lis_directory_generate_standard_listing', 'lis_std_gen_nonce' ); ?>
			<?php submit_button( $std_product_id ? 'Top up Standard Listing variations' : 'Generate Standard Listing product', 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2>Featured Listing product</h2>
		<?php if ( isset( $_GET['lis_feat_gen'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, no state change. ?>
			<?php if ( 'ok' === $_GET['lis_feat_gen'] ) : ?>
				<div class="notice notice-success inline"><p>Featured Listing product updated &mdash; added <?php echo isset( $_GET['lis_feat_added'] ) ? (int) $_GET['lis_feat_added'] : 0; ?> new variation(s), and the Featured Listing Product above now points to it.</p></div>
			<?php else : ?>
				<div class="notice notice-error inline"><p>Could not build the Featured Listing product. Make sure WooCommerce is active.</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<p class="description" style="max-width:640px;">
			Builds a <strong>Featured Listing</strong> WooCommerce Subscription product
			with Monthly ($19) and Annually ($190) billing variations (so Featured has a
			real annual price, not one flat fee), and repoints the <strong>Featured
			Listing Product</strong> setting above at it. Created as a <strong>draft</strong>;
			adjust prices if you like, then publish it. (Your old simple "Feature My
			Listing" product is left untouched — you can trash it once this is live.)
		</p>
		<?php
		$feat_gen_id = function_exists( 'lis_directory_get_generated_featured_product_id' ) ? lis_directory_get_generated_featured_product_id() : 0;
		if ( $feat_gen_id ) :
			?>
			<p><strong>Current product:</strong>
				<a href="<?php echo esc_url( get_edit_post_link( $feat_gen_id ) ); ?>">Featured Listing (#<?php echo (int) $feat_gen_id; ?>)</a>
				&mdash; status: <?php echo esc_html( get_post_status( $feat_gen_id ) ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lis_directory_generate_featured_listing" />
			<?php wp_nonce_field( 'lis_directory_generate_featured_listing', 'lis_feat_gen_nonce' ); ?>
			<?php submit_button( $feat_gen_id ? 'Top up Featured Listing variations' : 'Generate Featured Listing product', 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2>Showcase category cleanup</h2>
		<?php if ( isset( $_GET['lis_sc'] ) && 'done' === $_GET['lis_sc'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
			$sc_remaining = isset( $_GET['lis_sc_r'] ) ? (int) $_GET['lis_sc_r'] : 0; ?>
			<div class="notice notice-success inline"><p>Removed <?php echo isset( $_GET['lis_sc_d'] ) ? (int) $_GET['lis_sc_d'] : 0; ?> empty vendor categories this pass<?php echo $sc_remaining ? ' &mdash; ' . (int) $sc_remaining . ' still to go, running again…' : ' &mdash; all clear.'; ?></p></div>
			<?php if ( $sc_remaining > 0 ) : ?>
				<script>window.addEventListener('load',function(){var f=[].slice.call(document.querySelectorAll('form')).filter(function(x){return x.querySelector('input[value="lis_directory_showcase_cleanup"]');})[0];if(f){setTimeout(function(){HTMLFormElement.prototype.submit.call(f);},600);}});</script>
			<?php endif; ?>
		<?php endif; ?>
		<p class="description" style="max-width:640px;">
			Undo the retired "mirror every listing category as a Showcase slot" experiment:
			deletes the stray empty <code>lis_vendor_category</code> terms and trashes the
			bloated per-category Showcase product (any category still held by an active
			vendor is kept). The Showcase then moves to a single product that uses the
			listing's own category. Safe to run once.
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete empty vendor categories and trash the old per-category Showcase product? Active showcase vendors are kept.');">
			<input type="hidden" name="action" value="lis_directory_showcase_cleanup" />
			<?php wp_nonce_field( 'lis_directory_showcase_cleanup', 'lis_sc_nonce' ); ?>
			<?php submit_button( 'Run Showcase category cleanup', 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2>Directorist → LIS migration</h2>
		<?php
		$dm_stats = function_exists( 'lis_directory_migration_stats' ) ? lis_directory_migration_stats() : array( 'exists' => false, 'total' => 0, 'migrated' => 0 );
		?>
		<p class="description" style="max-width:640px;">
			Migrate existing Directorist (<code>at_biz_dir</code>) listings into this
			plugin's <code>lis_listing</code> post type, preserving owner, expiration,
			category, photos, hours, and contact fields. Preview the field mapping
			against real data first — nothing is written until the mapping is confirmed.
		</p>
		<?php if ( isset( $_GET['lis_dm'] ) && 'ran' === $_GET['lis_dm'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only. ?>
			<div class="notice notice-success inline"><p>Migration complete &mdash; created <?php echo isset( $_GET['lis_dm_c'] ) ? (int) $_GET['lis_dm_c'] : 0; ?>, skipped <?php echo isset( $_GET['lis_dm_s'] ) ? (int) $_GET['lis_dm_s'] : 0; ?> (already migrated), errors <?php echo isset( $_GET['lis_dm_e'] ) ? (int) $_GET['lis_dm_e'] : 0; ?>.</p></div>
		<?php endif; ?>
		<?php if ( $dm_stats['exists'] ) : ?>
			<p><strong>Directorist listings:</strong> <?php echo (int) $dm_stats['total']; ?> &middot; <strong>already migrated:</strong> <?php echo (int) $dm_stats['migrated']; ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'lis_dm_preview', '1', admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' ) ) ); ?>">Preview mapping (dry run)</a>
			</p>
			<?php if ( isset( $_GET['lis_dm_preview'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview. ?>
				<div style="max-width:900px;">
					<h3>What would be created (first 3)</h3>
					<?php if ( function_exists( 'lis_directory_migration_render_mapped_preview' ) ) { lis_directory_migration_render_mapped_preview(); } ?>
					<h3 style="margin-top:24px;">Raw source data (first 3)</h3>
					<?php if ( function_exists( 'lis_directory_migration_render_preview' ) ) { lis_directory_migration_render_preview(); } ?>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Create lis_listing posts for all not-yet-migrated Directorist listings? This is safe to re-run (it skips ones already migrated), but review the dry-run preview first.');" style="margin-top:16px;">
				<input type="hidden" name="action" value="lis_directory_migration_run" />
				<?php wp_nonce_field( 'lis_directory_migration_run', 'lis_dm_nonce' ); ?>
				<?php submit_button( 'Run migration (create ' . ( (int) $dm_stats['total'] - (int) $dm_stats['migrated'] ) . ' listings)', 'primary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p><em>Directorist (<code>at_biz_dir</code>) isn't active, so there's nothing to migrate.</em></p>
		<?php endif; ?>

		<hr />
		<h2>De-duplicate listings</h2>
		<p class="description" style="max-width:640px;">
			An earlier import left a stray admin-owned copy of most listings; the real
			migration then created the correct copy owned by the actual business. This
			keeps the correct (migrated, real-owner) copy and moves the stray one to the
			<strong>Trash</strong> (reversible). It only touches a listing when a migrated
			twin exists &mdash; never a unique listing, a migrated copy, or one a Vendor
			Showcase links to.
		</p>
		<?php if ( isset( $_GET['lis_dd'] ) && 'ran' === $_GET['lis_dd'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
			$dd_remaining = isset( $_GET['lis_dd_r'] ) ? (int) $_GET['lis_dd_r'] : 0; ?>
			<div class="notice notice-success inline"><p>Trashed <?php echo isset( $_GET['lis_dd_t'] ) ? (int) $_GET['lis_dd_t'] : 0; ?> duplicate(s) this pass<?php echo $dd_remaining ? ' &mdash; ' . (int) $dd_remaining . ' still to go, running again…' : ' &mdash; all clear.'; ?></p></div>
			<?php if ( $dd_remaining > 0 ) : ?>
				<script>window.addEventListener('load',function(){var f=[].slice.call(document.querySelectorAll('form')).filter(function(x){return x.querySelector('input[value="lis_directory_dedupe"]');})[0];if(f){setTimeout(function(){HTMLFormElement.prototype.submit.call(f);},600);}});</script>
			<?php endif; ?>
		<?php endif; ?>
		<?php $dd_count = function_exists( 'lis_directory_dedupe_count' ) ? lis_directory_dedupe_count() : 0; ?>
		<p><strong>Duplicate copies found:</strong> <?php echo (int) $dd_count; ?> (these would be trashed; their correct twin is kept).</p>
		<?php if ( $dd_count > 0 ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Move <?php echo (int) $dd_count; ?> stray duplicate listing(s) to Trash? The correct real-owner copy of each is kept. This is reversible from Trash.');">
				<input type="hidden" name="action" value="lis_directory_dedupe" />
				<?php wp_nonce_field( 'lis_directory_dedupe', 'lis_dd_nonce' ); ?>
				<?php submit_button( 'Remove duplicate listings (trash ' . (int) $dd_count . ')', 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<hr />
		<h2>Vendor Showcase links &amp; directory parity</h2>
		<p class="description" style="max-width:640px;">
			Link every Vendor Showcase entry to its <code>lis_listing</code>
			(<code>_lis_pv_listing_id</code>) so cards/logos/tickers point at the new
			listing, and see how the new directory lines up against Directorist.
		</p>
		<?php if ( isset( $_GET['lis_lv'] ) && 'ran' === $_GET['lis_lv'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only. ?>
			<div class="notice notice-success inline"><p>Linked <?php echo isset( $_GET['lis_lv_l'] ) ? (int) $_GET['lis_lv_l'] : 0; ?> vendor(s) to a listing &middot; already linked <?php echo isset( $_GET['lis_lv_a'] ) ? (int) $_GET['lis_lv_a'] : 0; ?> &middot; couldn't resolve <?php echo isset( $_GET['lis_lv_u'] ) ? (int) $_GET['lis_lv_u'] : 0; ?>.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
			<input type="hidden" name="action" value="lis_directory_link_vendors" />
			<?php wp_nonce_field( 'lis_directory_link_vendors', 'lis_lv_nonce' ); ?>
			<?php submit_button( 'Link Vendor Showcases to listings', 'secondary', 'submit', false ); ?>
		</form>
		<?php
		$parity = function_exists( 'lis_directory_directory_parity' ) ? lis_directory_directory_parity() : array( 'exists' => false );
		if ( ! empty( $parity['exists'] ) ) : ?>
			<p>
				<strong>Directorist listings:</strong> <?php echo (int) $parity['directorist']; ?> &middot;
				<strong>current LIS listings:</strong> <?php echo (int) $parity['lis']; ?> &middot;
				<strong>matched:</strong> <?php echo (int) $parity['matched']; ?> &middot;
				<strong>missing a LIS listing:</strong> <?php echo (int) $parity['missing']; ?>
			</p>
			<?php if ( ! empty( $parity['missing_titles'] ) ) : ?>
				<details style="max-width:900px;">
					<summary><?php echo (int) $parity['missing']; ?> Directorist listing(s) with no matching LIS listing (first <?php echo count( $parity['missing_titles'] ); ?>):</summary>
					<ul style="margin-left:18px;list-style:disc;">
						<?php foreach ( $parity['missing_titles'] as $t ) : ?>
							<li><?php echo esc_html( $t ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php else : ?>
				<p><em>Every Directorist listing has a matching LIS listing. 🎉</em></p>
			<?php endif; ?>
		<?php else : ?>
			<p><em>Directorist (<code>at_biz_dir</code>) isn't active, so there's nothing to compare.</em></p>
		<?php endif; ?>

		<hr />
		<h3>Testing tool</h3>
		<p class="description" style="max-width:640px;">
			Creates a throwaway <strong>$0, non-subscription</strong> product tagged to
			the first available vendor category, so the full pay &rarr; thank-you
			submission-link &rarr; category-lock flow can be exercised end-to-end with a
			free order (no card, no charge). Delete the product, its test order, and any
			test vendor afterward.
		</p>
		<?php if ( isset( $_GET['lis_vs_test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( 0 === strpos( (string) $_GET['lis_vs_test'], 'ok' ) ) : ?>
				<div class="notice notice-success inline"><p>Test spot created. <?php echo isset( $_GET['lis_vs_test_pid'] ) ? '<a href="' . esc_url( get_permalink( (int) $_GET['lis_vs_test_pid'] ) ) . '">View the $0 test product</a>.' : ''; ?></p></div>
			<?php else : ?>
				<div class="notice notice-error inline"><p>Could not create a test spot (no available category, or WooCommerce inactive).</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lis_directory_create_test_spot" />
			<?php wp_nonce_field( 'lis_directory_create_test_spot', 'lis_vs_test_nonce' ); ?>
			<?php submit_button( 'Create $0 test spot', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

function lis_directory_init_woocommerce_integration() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action( 'woocommerce_variation_options_pricing', 'lis_directory_render_variation_category_field', 10, 3 );
	add_action( 'woocommerce_save_product_variation', 'lis_directory_save_variation_category_field', 10, 2 );
	add_action( 'lis_directory_vendor_status_changed', 'lis_directory_sync_stock_on_status_change', 10, 2 );
	add_action( 'woocommerce_thankyou', 'lis_directory_maybe_show_submission_link' );
	add_action( 'admin_post_lis_directory_generate_vendor_showcase', 'lis_directory_handle_generate_vendor_showcase' );
	add_action( 'admin_post_lis_directory_create_test_spot', 'lis_directory_handle_create_test_spot' );

	if ( class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'woocommerce_subscription_status_cancelled', 'lis_directory_handle_subscription_ended' );
		add_action( 'woocommerce_subscription_status_expired', 'lis_directory_handle_subscription_ended' );
	}
}

/**
 * Variation admin field: tags a variation with the lis_vendor_category it sells.
 */
function lis_directory_render_variation_category_field( $loop, $variation_data, $variation ) {
	$terms   = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );
	$current = get_post_meta( $variation->ID, '_lis_pv_category_term_id', true );
	?>
	<div class="options_group form-row form-row-full">
		<p class="form-field">
			<label for="lis_pv_category_term_id_<?php echo esc_attr( $loop ); ?>">LIS Vendor Category</label>
			<select id="lis_pv_category_term_id_<?php echo esc_attr( $loop ); ?>" name="lis_pv_category_term_id[<?php echo esc_attr( $loop ); ?>]">
				<option value="">— Not a Vendor Showcase variation —</option>
				<?php foreach ( (array) $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( (int) $current, $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description">If set, this variation's stock is kept in sync with whether the category currently has an active Vendor Showcase entry.</span>
		</p>
	</div>
	<?php
}

function lis_directory_save_variation_category_field( $variation_id, $loop ) {
	if ( ! isset( $_POST['lis_pv_category_term_id'][ $loop ] ) ) {
		return;
	}
	$term_id = (int) $_POST['lis_pv_category_term_id'][ $loop ];
	if ( $term_id ) {
		update_post_meta( $variation_id, '_lis_pv_category_term_id', $term_id );
	} else {
		delete_post_meta( $variation_id, '_lis_pv_category_term_id' );
	}
}

/**
 * The single generated Vendor Showcase product, found by its marker meta so
 * the generator is idempotent. Returns 0 if it hasn't been built yet.
 */
function lis_directory_get_vendor_showcase_product_id() {
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_lis_vendor_showcase_product',
		'meta_value'     => '1',
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * admin-post handler behind the "Generate Vendor Showcase product" button on
 * the settings page.
 */
function lis_directory_handle_generate_vendor_showcase() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_vs_gen_nonce'] ) || ! wp_verify_nonce( $_POST['lis_vs_gen_nonce'], 'lis_directory_generate_vendor_showcase' ) ) {
		wp_die( 'Security check failed.' );
	}

	$result   = lis_directory_generate_vendor_showcase_product();
	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );

	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'lis_vs_gen', 'error', $redirect );
	} else {
		$redirect = add_query_arg( array( 'lis_vs_gen' => 'ok', 'lis_vs_added' => (int) $result['added'] ), $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Builds the single Vendor Showcase variable-subscription product with just a
 * Monthly ($100/mo) and Annually ($1,000/yr) variation — the same shape as the
 * Standard and Featured products.
 *
 * SINGLE-PRODUCT MODEL (replaces the old per-category-variation approach that
 * created hundreds of variations + a mirrored `lis_vendor_category` per listing
 * category and bogged wp-admin down): there is now ONE product and no category
 * attribute. Which category a Showcase buyer occupies comes from the listing's
 * OWN `lis_listing_category`, chosen earlier in the add-listing wizard. That
 * one category is mirrored to a `lis_vendor_category` term just-in-time at
 * submit time (see lis_directory_vendor_category_for_listing_category), so the
 * proven one-active-vendor-per-category exclusivity engine
 * (lis_directory_get_active_vendor_for_category) keeps working with zero
 * pre-created bloat. Exclusivity is enforced in PHP at plan-step + submit time,
 * not via per-variation stock.
 *
 * Idempotent: keyed on the `_lis_pv_billing_period` marker, so a re-run only
 * fills gaps and never disturbs prices an admin has edited by hand.
 *
 * @return array|WP_Error { added:int, product_id:int } on success.
 */
function lis_directory_generate_vendor_showcase_product() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'no_wc', 'WooCommerce is not active.' );
	}
	$use_sub = class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Product_Variable_Subscription' );

	$product_id = lis_directory_get_vendor_showcase_product_id();
	if ( ! $product_id ) {
		$product = $use_sub ? new WC_Product_Variable_Subscription() : new WC_Product_Variable();
		$product->set_name( 'Vendor Showcase' );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );

		$attr_bill = new WC_Product_Attribute();
		$attr_bill->set_name( 'Billing' );
		$attr_bill->set_options( array( 'Monthly', 'Annually' ) );
		$attr_bill->set_visible( true );
		$attr_bill->set_variation( true );

		$product->set_attributes( array( $attr_bill ) );
		$product->update_meta_data( '_lis_vendor_showcase_product', '1' );
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'save_failed', 'Could not create the Vendor Showcase product.' );
		}
	} else {
		$product = wc_get_product( $product_id );
	}

	$existing = array();
	foreach ( $product->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_pv_billing_period', true );
		if ( $period ) {
			$existing[ $period ] = true;
		}
	}

	$plans = array(
		array( 'label' => 'Monthly',  'price' => 100,  'period' => 'month' ),
		array( 'label' => 'Annually', 'price' => 1000, 'period' => 'year' ),
	);

	$added = 0;
	foreach ( $plans as $plan ) {
		if ( isset( $existing[ $plan['period'] ] ) ) {
			continue;
		}
		$var = ( $use_sub && class_exists( 'WC_Product_Subscription_Variation' ) )
			? new WC_Product_Subscription_Variation()
			: new WC_Product_Variation();
		$var->set_parent_id( $product_id );
		$var->set_attributes( array( 'billing' => $plan['label'] ) );
		$var->set_regular_price( $plan['price'] );
		$var->set_price( $plan['price'] );
		$var->set_virtual( true );
		$var->update_meta_data( '_lis_pv_billing_period', $plan['period'] );
		if ( $use_sub ) {
			$var->update_meta_data( '_subscription_price', $plan['price'] );
			$var->update_meta_data( '_subscription_period', $plan['period'] );
			$var->update_meta_data( '_subscription_period_interval', '1' );
			$var->update_meta_data( '_subscription_length', '0' );
			$var->update_meta_data( '_subscription_sign_up_fee', '0' );
			$var->update_meta_data( '_subscription_trial_length', '0' );
			$var->update_meta_data( '_subscription_trial_period', 'day' );
		}
		$var->save();
		$added++;
	}

	// Write each child's billing-attribute meta directly so the product page's
	// Billing dropdown resolves to the right variation (same guard the Standard
	// and Featured generators use).
	$fresh = wc_get_product( $product_id );
	foreach ( $fresh->get_children() as $child_id ) {
		$period = get_post_meta( $child_id, '_lis_pv_billing_period', true );
		if ( $period ) {
			update_post_meta( $child_id, 'attribute_billing', 'year' === $period ? 'Annually' : 'Monthly' );
		}
	}

	if ( class_exists( 'WC_Product_Variable' ) ) {
		WC_Product_Variable::sync( $product_id );
	}
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients( $product_id );
	}

	return array( 'added' => $added, 'product_id' => $product_id );
}

/**
 * admin-post handler for the "Create $0 test spot" testing button.
 */
function lis_directory_handle_create_test_spot() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}
	if ( ! isset( $_POST['lis_vs_test_nonce'] ) || ! wp_verify_nonce( $_POST['lis_vs_test_nonce'], 'lis_directory_create_test_spot' ) ) {
		wp_die( 'Security check failed.' );
	}

	$result   = lis_directory_create_test_vendor_spot();
	$redirect = admin_url( 'edit.php?post_type=lis_preferred_vendor&page=lis_pv_settings' );

	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'lis_vs_test', 'error', $redirect );
	} else {
		$redirect = add_query_arg( array( 'lis_vs_test' => 'ok', 'lis_vs_test_pid' => (int) $result['product_id'] ), $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Creates a disposable $0, non-subscription variable product with a single
 * variation tagged to the first vendor category that has no active vendor —
 * just enough to exercise the pay → thank-you submission-link → category-lock
 * flow with a free order (a real subscription would demand a saved card).
 * Non-subscription and $0 on purpose so checkout needs no payment method.
 *
 * @return array|WP_Error { product_id, variation_id, category, term_id }.
 */
function lis_directory_create_test_vendor_spot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'no_wc', 'WooCommerce is not active.' );
	}
	$terms = get_terms( array( 'taxonomy' => 'lis_vendor_category', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return new WP_Error( 'no_terms', 'No LIS Vendor Categories exist.' );
	}

	$term = null;
	foreach ( $terms as $t ) {
		if ( ! lis_directory_get_active_vendor_for_category( $t->term_id ) ) {
			$term = $t;
			break;
		}
	}
	if ( ! $term ) {
		return new WP_Error( 'no_available', 'Every category already has an active vendor.' );
	}
	$name = html_entity_decode( $term->name, ENT_QUOTES );

	$product = new WC_Product_Variable();
	$product->set_name( 'TEST — Vendor Spot ($0, delete me)' );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );

	$attr = new WC_Product_Attribute();
	$attr->set_name( 'Vendor Category' );
	$attr->set_options( array( $name ) );
	$attr->set_visible( true );
	$attr->set_variation( true );
	$product->set_attributes( array( $attr ) );
	$product->update_meta_data( '_lis_vendor_showcase_test', '1' );
	$product_id = $product->save();
	if ( ! $product_id ) {
		return new WP_Error( 'save_failed', 'Could not create the test product.' );
	}

	$var = new WC_Product_Variation();
	$var->set_parent_id( $product_id );
	$var->set_attributes( array( 'vendor-category' => $name ) );
	$var->set_regular_price( 0 );
	$var->set_price( 0 );
	$var->set_virtual( true );
	$var->update_meta_data( '_lis_pv_category_term_id', $term->term_id );
	$variation_id = $var->save();
	update_post_meta( $variation_id, 'attribute_vendor-category', $name );

	if ( class_exists( 'WC_Product_Variable' ) ) {
		WC_Product_Variable::sync( $product_id );
	}
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients( $product_id );
	}

	return array(
		'product_id'   => $product_id,
		'variation_id' => $variation_id,
		'category'     => $name,
		'term_id'      => $term->term_id,
	);
}

/**
 * Every product variation tagged for a given category.
 *
 * @return int[] Variation post IDs.
 */
function lis_directory_get_variations_for_category( $term_id ) {
	return get_posts( array(
		'post_type'      => 'product_variation',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_category_term_id',
				'value' => (int) $term_id,
			),
		),
	) );
}

/**
 * Keeps a category's tagged variation(s) stock in sync with whether it
 * currently has an active vendor — "0 in stock" doubles as the availability
 * check per the build brief, so WooCommerce's own "sold out" UI does the work
 * instead of building custom availability logic a second time.
 */
function lis_directory_sync_stock_for_category( $term_id ) {
	$variation_ids = lis_directory_get_variations_for_category( $term_id );
	if ( empty( $variation_ids ) ) {
		return;
	}

	$taken = (bool) lis_directory_get_active_vendor_for_category( $term_id );

	foreach ( $variation_ids as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			continue;
		}
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( $taken ? 0 : 1 );
		$variation->set_stock_status( $taken ? 'outofstock' : 'instock' );
		$variation->save();
	}
}

function lis_directory_sync_stock_on_status_change( $post_id, $new_status ) {
	$term_ids = wp_get_post_terms( $post_id, 'lis_vendor_category', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) ) {
		return;
	}
	foreach ( $term_ids as $term_id ) {
		lis_directory_sync_stock_for_category( $term_id );
	}
}

/**
 * Thank-you page: if this order is for a tagged Vendor Showcase variation,
 * link straight to the submission form (includes/submission.php), locked to
 * that category and carrying the subscription ID — so the vendor doesn't have
 * to hunt for the form, and the resulting post is tied back to what they paid
 * for. Only the thank-you page is wired up in this version, not a duplicate
 * link in the order confirmation email or My Account — flag if that turns out
 * to be needed too once this is live.
 */
function lis_directory_maybe_show_submission_link( $order_id ) {
	if ( ! $order_id ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$page_id = (int) get_option( 'lis_pv_submission_page_id' );
	if ( ! $page_id ) {
		return; // Not configured yet — see LIS Directory > Settings.
	}

	foreach ( $order->get_items() as $item ) {
		$variation_id = $item->get_variation_id();
		if ( ! $variation_id ) {
			continue;
		}
		$term_id = (int) get_post_meta( $variation_id, '_lis_pv_category_term_id', true );
		if ( ! $term_id ) {
			continue;
		}

		// Store the subscription ID, not the one-time order ID, so the later
		// cancelled/expired hook (which only knows the subscription) can find
		// the vendor post it needs to expire.
		$linked_id = $order_id;
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				$linked_id = (int) reset( $subscriptions )->get_id();
			}
		}

		$link = add_query_arg( array(
			'lis_pv_category' => $term_id,
			'lis_pv_order'    => $linked_id,
		), get_permalink( $page_id ) );
		?>
		<div class="lis-pv-thankyou-link" style="margin:16px 0;padding:16px;border:1px solid #7ad03a;background:#f7fff0;">
			<p>Your Vendor Showcase spot is reserved! <a href="<?php echo esc_url( $link ); ?>">Click here to submit your business details</a> so we can get your listing live.</p>
		</div>
		<?php
		return; // Only one Vendor Showcase line item is expected per order.
	}
}

/**
 * Cancellation/expiration: free the category back up by expiring the linked
 * vendor. Runs on both `cancelled` and `expired` subscription statuses —
 * either one means the paid term ended, and the category lock (v0.2.0) only
 * cares about `active`, so anything else already reopens it.
 */
function lis_directory_handle_subscription_ended( $subscription ) {
	$subscription_id = $subscription->get_id();

	$vendors = get_posts( array(
		'post_type'      => 'lis_preferred_vendor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_lis_pv_wc_order_id',
				'value' => $subscription_id,
			),
		),
	) );

	if ( empty( $vendors ) ) {
		return;
	}

	lis_directory_set_vendor_status( $vendors[0], 'expired' );
}
