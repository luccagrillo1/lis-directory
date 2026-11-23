<?php
/**
 * Phone bottom bar (v0.70.0, audit idea 38).
 *
 * A floating green-glass pill at the bottom of the screen on phones (≤767px):
 * Directory · Events · Jobs · Search · Account. It hides while scrolling down
 * and comes back scrolling up. Search opens a small sheet with the site search
 * box. Picked from mockup 38: pill, tabs a, glass 2, hides on scroll.
 * Filter lis_directory_phone_tabbar_enabled turns it off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lis_directory_phone_tabbar_enabled() {
	$on = ! is_admin() && ! is_customize_preview() && ! wp_doing_ajax() && ! is_embed() && ! is_feed();
	return (bool) apply_filters( 'lis_directory_phone_tabbar_enabled', $on );
}

/** Which tab the current URL belongs to. */
function lis_directory_phone_tabbar_current() {
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( is_search() ) {
		return 'search';
	}
	if ( is_singular( 'lis_listing' ) || 0 === strpos( $path, '/services/local-directory' ) || 0 === strpos( $path, '/listings/' ) ) {
		return 'dir';
	}
	if ( is_singular( 'lis_event' ) || 0 === strpos( $path, '/services/event-calendar' ) || 0 === strpos( $path, '/events/' ) ) {
		return 'ev';
	}
	if ( is_singular( 'lis_job' ) || 0 === strpos( $path, '/jobs' ) ) {
		return 'job';
	}
	if ( 0 === strpos( $path, '/account' ) ) {
		return 'me';
	}
	return '';
}

add_action( 'wp_footer', function () {
	if ( ! lis_directory_phone_tabbar_enabled() ) {
		return;
	}
	$icons = array(
		'dir'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M4 10 12 4l8 6v9a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/></svg>',
		'ev'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>',
		'job'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M9 7.5V5.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 5.5v2M3.5 12.5h17"/></svg>',
		'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>',
		'me'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c1.2-3.6 4-5.3 7.5-5.3s6.3 1.7 7.5 5.3"/></svg>',
	);
	$tabs = array(
		array( 'dir', 'Directory', home_url( '/services/local-directory/' ) ),
		array( 'ev', 'Events', home_url( '/services/event-calendar/' ) ),
		array( 'job', 'Jobs', home_url( '/jobs/' ) ),
		array( 'search', 'Search', home_url( '/?s=' ) ),
		array( 'me', 'Account', home_url( '/account/' ) ),
	);
	$tabs = apply_filters( 'lis_directory_phone_tabbar_tabs', $tabs );
	$cur  = lis_directory_phone_tabbar_current();
	?>
<nav class="lis-tabbar" aria-label="<?php esc_attr_e( 'Quick links', 'lis-directory' ); ?>">
	<?php foreach ( $tabs as $t ) : ?>
		<a href="<?php echo esc_url( $t[2] ); ?>" class="lis-tabbar-<?php echo esc_attr( $t[0] ); ?>"<?php echo $cur === $t[0] ? ' aria-current="page"' : ''; ?>><?php echo isset( $icons[ $t[0] ] ) ? $icons[ $t[0] ] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $t[1] ); ?></span></a>
	<?php endforeach; ?>
</nav>
<div class="lis-tabbar-sheet" hidden>
	<div class="lis-tabbar-sheet-bg"></div>
	<form class="lis-tabbar-sheet-panel" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label for="lis-tabbar-q"><?php esc_html_e( 'Search Living in Sandpoint', 'lis-directory' ); ?></label>
		<div><input id="lis-tabbar-q" type="search" name="s" placeholder="<?php esc_attr_e( 'Businesses, events, jobs…', 'lis-directory' ); ?>" enterkeyhint="search" autocomplete="off"><button type="submit"><?php esc_html_e( 'Search', 'lis-directory' ); ?></button></div>
	</form>
</div>
<style id="lis-tabbar-css">
.lis-tabbar,.lis-tabbar-sheet{display:none}
@media (max-width:767px){
body{padding-bottom:calc(84px + env(safe-area-inset-bottom))}
.lis-tabbar{display:flex;position:fixed;z-index:9990;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom));padding:4px 8px;border-radius:999px;background:rgba(36,84,60,.66);-webkit-backdrop-filter:blur(18px) saturate(160%);backdrop-filter:blur(18px) saturate(160%);border:1px solid rgba(251,250,246,.16);box-shadow:inset 0 1px 0 rgba(255,255,255,.14),0 16px 40px -14px rgba(10,20,15,.45);font:600 11px/1.1 'Source Sans 3',system-ui,sans-serif;transition:transform .32s cubic-bezier(.2,.8,.2,1)}
.lis-tabbar.is-hidden{transform:translateY(140%)}
.lis-tabbar a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;flex:1;min-height:50px;color:rgba(255,255,255,.72)!important;text-decoration:none!important;text-shadow:0 1px 3px rgba(10,20,15,.45);position:relative;-webkit-tap-highlight-color:transparent;letter-spacing:0}
.lis-tabbar a svg{width:23px;height:23px}
.lis-tabbar a[aria-current]{color:#fff!important}
.lis-tabbar a[aria-current]::before{content:"";position:absolute;bottom:3px;width:5px;height:5px;border-radius:50%;background:#D07B4F}
.lis-tabbar a:active{transform:scale(.94)}
body:has(.ast-mobile-popup-drawer.active) .lis-tabbar,body.lis-tabbar-off .lis-tabbar{display:none}
.lis-tabbar-sheet:not([hidden]){display:block;position:fixed;inset:0;z-index:99980}
.lis-tabbar-sheet-bg{position:absolute;inset:0;background:rgba(10,18,14,.45)}
.lis-tabbar-sheet-panel{position:absolute;left:0;right:0;bottom:0;margin:0;background:#FBFAF6;border-radius:20px 20px 0 0;padding:10px 16px calc(22px + env(safe-area-inset-bottom));animation:lisTabUp .3s cubic-bezier(.2,.8,.2,1)}
.lis-tabbar-sheet-panel::before{content:"";display:block;width:40px;height:4px;border-radius:4px;background:#C9CBC2;margin:0 auto 14px}
.lis-tabbar-sheet-panel label{display:block;margin:0 0 8px;font:600 17px 'Source Sans 3',system-ui,sans-serif;color:#1A1D19}
.lis-tabbar-sheet-panel div{display:flex;gap:8px}
.lis-tabbar-sheet-panel input{flex:1;min-width:0;height:48px;padding:0 14px;border:1px solid #C9CBC2;border-radius:12px;background:#fff;font:400 16px 'Source Sans 3',system-ui,sans-serif;color:#1A1D19}
.lis-tabbar-sheet-panel button{height:48px;padding:0 18px;border:0;border-radius:12px;background:#16281F;color:#fff;font:600 16px 'Source Sans 3',system-ui,sans-serif}
@keyframes lisTabUp{from{transform:translateY(100%)}to{transform:none}}
}
@media (prefers-reduced-motion:reduce){.lis-tabbar{transition:none}.lis-tabbar-sheet-panel{animation:none}}
</style>
<script id="lis-tabbar-js">
( function () {
	var bar = document.querySelector( '.lis-tabbar' ), sheet = document.querySelector( '.lis-tabbar-sheet' );
	if ( ! bar ) return;
	var lastY = window.scrollY, ticking = false;
	window.addEventListener( 'scroll', function () {
		if ( ticking ) return;
		ticking = true;
		requestAnimationFrame( function () {
			ticking = false;
			var y = window.scrollY;
			if ( Math.abs( y - lastY ) < 8 ) return;
			bar.classList.toggle( 'is-hidden', y > lastY && y > 200 );
			lastY = y;
		} );
	}, { passive: true } );
	function close() { sheet.hidden = true; }
	bar.querySelector( '.lis-tabbar-search' ).addEventListener( 'click', function ( e ) {
		if ( ! sheet ) return;
		e.preventDefault();
		sheet.hidden = false;
		sheet.querySelector( 'input' ).focus();
		if ( typeof window.lisTrack === 'function' ) window.lisTrack( 'tabbar_search_open', {} );
	} );
	sheet.querySelector( '.lis-tabbar-sheet-bg' ).addEventListener( 'click', close );
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! sheet.hidden ) close(); } );
} )();
</script>
	<?php
}, 30 );
