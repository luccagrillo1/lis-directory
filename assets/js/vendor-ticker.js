/**
 * Vendor Showcase ticker motion.
 *
 * Replaces the old pure-CSS marquee (a `@keyframes` translate on the track,
 * paused via `:hover`). Two reasons for JS:
 *   1. Pausing a CSS animation that uses a percentage transform repaints from
 *      the keyframe start in some browsers — so hovering "jumped back to the
 *      beginning" instead of freezing in place. Driving the transform ourselves
 *      pauses exactly where it is.
 *   2. It lets the row be scrubbed by the mouse wheel / a Mac two-finger swipe,
 *      which a CSS animation can't do.
 *
 * The markup is unchanged: each `.lis-pv-ticker` has a `.lis-pv-ticker-track`
 * holding two identical `.lis-pv-ticker-group`s (the second an aria-hidden
 * copy), so one group's width is a seamless loop. Under reduced motion we don't
 * animate at all — CSS turns the ticker into a plain horizontal scroll area.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var LOOP_SECONDS = 40; // Same cadence as the old CSS animation (one group per 40s).

	function initTicker( ticker ) {
		var track  = ticker.querySelector( '.lis-pv-ticker-track' );
		var groups = ticker.querySelectorAll( '.lis-pv-ticker-group' );
		if ( ! track || groups.length < 2 ) {
			return;
		}

		var offset    = 0;   // px the track is shifted left
		var loopW     = 0;   // width of one group = one seamless loop
		var pxPerMs   = 0;
		var paused    = false;
		var userUntil = 0;   // timestamp until which auto-scroll yields to manual scrubbing
		var last      = 0;
		var raf       = null;

		function measure() {
			// Distance from the start of group 0 to the start of group 1 is
			// exactly one group's outer width — the seamless loop distance.
			loopW   = groups[1].offsetLeft - groups[0].offsetLeft;
			pxPerMs = loopW > 0 ? loopW / ( LOOP_SECONDS * 1000 ) : 0;
		}

		function wrap() {
			if ( loopW <= 0 ) {
				return;
			}
			offset = offset % loopW;
			if ( offset < 0 ) {
				offset += loopW;
			}
		}

		function apply() {
			track.style.transform = 'translateX(' + ( -offset ) + 'px)';
		}

		function frame( t ) {
			if ( ! last ) {
				last = t;
			}
			var dt = t - last;
			last = t;

			if ( loopW > 0 && ! reduce && ! paused && t >= userUntil ) {
				offset += pxPerMs * dt;
				wrap();
				apply();
			}
			raf = window.requestAnimationFrame( frame );
		}

		// Freeze in place while hovered or focused — no jump.
		ticker.addEventListener( 'mouseenter', function () { paused = true; } );
		ticker.addEventListener( 'mouseleave', function () { paused = false; } );
		ticker.addEventListener( 'focusin', function () { paused = true; } );
		ticker.addEventListener( 'focusout', function () { paused = false; } );

		// Wheel / two-finger swipe scrubs the row. A horizontal swipe uses
		// deltaX; a plain wheel uses deltaY — either drives it. Auto-scroll
		// yields for a moment after each scrub so it doesn't fight the user.
		ticker.addEventListener( 'wheel', function ( e ) {
			var delta = Math.abs( e.deltaX ) >= Math.abs( e.deltaY ) ? e.deltaX : e.deltaY;
			if ( ! delta || loopW <= 0 ) {
				return;
			}
			offset += delta;
			wrap();
			apply();
			userUntil = ( window.performance ? performance.now() : Date.now() ) + 1500;
			e.preventDefault(); // scrub the ticker instead of scrolling the page
		}, { passive: false } );

		function start() {
			measure();
			apply();
			if ( ! raf ) {
				raf = window.requestAnimationFrame( frame );
			}
		}

		window.addEventListener( 'resize', measure );
		// Widths depend on images; re-measure as each loads.
		ticker.querySelectorAll( 'img' ).forEach( function ( img ) {
			if ( ! img.complete ) {
				img.addEventListener( 'load', measure );
			}
		} );

		if ( document.readyState === 'complete' ) {
			start();
		} else {
			window.addEventListener( 'load', start );
		}
	}

	function boot() {
		document.querySelectorAll( '.lis-pv-ticker' ).forEach( initTicker );
	}

	if ( 'loading' !== document.readyState ) {
		boot();
	} else {
		document.addEventListener( 'DOMContentLoaded', boot );
	}
}() );
