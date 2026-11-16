<?php
/**
 * Signed-in account menu in the site header (0.54.1).
 *
 * The header avatar is the Astra theme's Header Builder "Account" element
 * (Customizer → Header Builder → Account). Free Astra can only make it a
 * link, so this file adds the menu without touching the theme:
 *
 * - Desktop and mobile header: clicking the avatar opens a small dropdown
 *   (Account, Saved, My Events, Log out). Without JavaScript the avatar is
 *   still the plain link Astra prints.
 * - Mobile hamburger: the same links are appended to Astra's mobile menu
 *   (theme location `mobile_menu`, <ul id="ast-hf-mobile-menu">) through
 *   `wp_nav_menu_items`, so they appear in the drawer for signed-in users.
 *
 * Log out uses wp_logout_url( home_url( '/' ) ): nonce included, lands on
 * the homepage. Filters: `lis_directory_account_menu_items` (the link list),
 * `lis_directory_account_menu_enabled` (turn it all off).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'lis_directory_render_account_menu', 20 );
add_filter( 'wp_nav_menu_items', 'lis_directory_mobile_menu_account_items', 20, 2 );

function lis_directory_account_menu_enabled() {
	if ( ! is_user_logged_in() || is_admin() ) {
		return false;
	}
	return (bool) apply_filters( 'lis_directory_account_menu_enabled', true );
}

/**
 * The menu's links, in order: array of array( 'label' => …, 'url' => …, 'class' => … ).
 * Saved and My Events only appear when their /account/<slug>/ page is published.
 */
function lis_directory_account_menu_items() {
	$account = get_page_by_path( 'account' );
	$items   = array(
		array(
			'label' => 'Account',
			'url'   => ( $account && 'publish' === $account->post_status ) ? get_permalink( $account ) : home_url( '/account/' ),
			'class' => 'lis-acct-account',
		),
	);

	$saved = lis_directory_account_page_url( 'saved' );
	if ( $saved ) {
		$items[] = array( 'label' => 'Saved', 'url' => $saved, 'class' => 'lis-acct-saved' );
	}

	$events = lis_directory_account_page_url( 'events' );
	if ( $events ) {
		$items[] = array( 'label' => 'My Events', 'url' => $events, 'class' => 'lis-acct-events' );
	}

	$items[] = array(
		'label' => 'Log out',
		'url'   => wp_logout_url( home_url( '/' ) ),
		'class' => 'lis-acct-logout',
	);

	return (array) apply_filters( 'lis_directory_account_menu_items', $items );
}

/**
 * Append the account links to Astra's mobile (hamburger) menu.
 */
function lis_directory_mobile_menu_account_items( $items, $args ) {
	if ( ! lis_directory_account_menu_enabled() || ! is_object( $args ) ) {
		return $items;
	}
	$location = isset( $args->theme_location ) ? $args->theme_location : '';
	$menu_id  = isset( $args->menu_id ) ? $args->menu_id : '';
	if ( 'mobile_menu' !== $location && 'ast-hf-mobile-menu' !== $menu_id ) {
		return $items;
	}

	$first = true;
	foreach ( lis_directory_account_menu_items() as $item ) {
		$classes = 'menu-item lis-acct-mobile-item ' . $item['class'] . ( $first ? ' lis-acct-mobile-first' : '' );
		$items  .= '<li class="' . esc_attr( $classes ) . '"><a class="menu-link" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		$first   = false;
	}
	return $items;
}

/**
 * Footer: the dropdown template, its styles and the script that attaches it
 * to every Astra header avatar on the page.
 */
function lis_directory_render_account_menu() {
	if ( ! lis_directory_account_menu_enabled() ) {
		return;
	}
	$user = wp_get_current_user();
	?>
	<template id="lis-account-menu-tpl">
		<div class="lis-account-menu" role="menu" hidden>
			<p class="lis-account-menu-who"><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_login ); ?></p>
			<?php foreach ( lis_directory_account_menu_items() as $item ) : ?>
				<a role="menuitem" class="<?php echo esc_attr( $item['class'] ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	</template>
	<style id="lis-account-menu-css">
	.ast-header-account-wrap.lis-has-account-menu { position: relative; }
	.lis-account-menu { position: absolute; top: calc(100% + 8px); right: 0; z-index: 9999; min-width: 180px; padding: 6px; background: #fff; border: 1px solid rgba(0,0,0,.1); border-radius: 10px; box-shadow: 0 10px 28px rgba(0,0,0,.14); text-align: left; }
	.lis-account-menu[hidden] { display: none; }
	.lis-account-menu-who { margin: 0; padding: 6px 10px 8px; font-size: 13px; opacity: .65; border-bottom: 1px solid rgba(0,0,0,.08); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; }
	.lis-account-menu a { display: block; padding: 8px 10px; border-radius: 6px; font-size: 15px; line-height: 1.3; color: inherit; text-decoration: none; white-space: nowrap; }
	.lis-account-menu a:hover, .lis-account-menu a:focus-visible { background: rgba(0,0,0,.05); outline: none; }
	.lis-account-menu .lis-acct-logout { margin-top: 4px; border-top: 1px solid rgba(0,0,0,.08); border-radius: 0 0 6px 6px; }
	</style>
	<script>
	(function () {
		var tpl = document.getElementById('lis-account-menu-tpl');
		if (!tpl || !('content' in tpl)) return;
		var open = null;
		function close() {
			if (!open) return;
			open.menu.hidden = true;
			open.link.setAttribute('aria-expanded', 'false');
			open = null;
		}
		document.querySelectorAll('.ast-header-account-wrap').forEach(function (wrap) {
			var link = wrap.querySelector('.ast-header-account-link');
			if (!link || wrap.querySelector('.lis-account-menu')) return;
			var menu = tpl.content.firstElementChild.cloneNode(true);
			wrap.classList.add('lis-has-account-menu');
			wrap.appendChild(menu);
			link.setAttribute('role', 'button');
			link.setAttribute('aria-haspopup', 'menu');
			link.setAttribute('aria-expanded', 'false');
			link.setAttribute('aria-label', 'Account menu');
			link.addEventListener('click', function (e) {
				if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
				e.preventDefault();
				if (open && open.menu === menu) { close(); return; }
				close();
				menu.hidden = false;
				link.setAttribute('aria-expanded', 'true');
				open = { menu: menu, link: link };
				var first = menu.querySelector('a');
				if (first && e.detail === 0) first.focus(); // keyboard activation
			});
		});
		document.addEventListener('click', function (e) {
			if (open && !open.menu.contains(e.target) && !open.link.contains(e.target)) close();
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && open) { var l = open.link; close(); l.focus(); }
		});
	})();
	</script>
	<?php
}
