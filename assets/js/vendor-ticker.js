/**
 * Vendor Showcase ticker motion.
 *
 * Markup (from the shortcode): `.lis-pv-ticker` > `.lis-pv-ticker-track` >
 * two identical `.lis-pv-ticker-group`s (the second an aria-hidden copy).
 * One group's width is one seamless loop.
 *
 * Why JS and not a CSS marquee: pausing a percentage-transform CSS animation
 * repaints from the keyframe start in some browsers (hover "jumped back"), and
 * CSS can't be scrubbed. Driving the transform here pauses in place.
 *
 * Behaviour (0.59.0):
 *   - Constant speed in px/s, so a short vendor list doesn't crawl and a long
 *     one doesn't race (it used to be "one loop per 40s").
 *   - Extra copies of the group are cloned so the row always covers the visible
 *     width plus one loop: no empty gap with only a few vendors.
 *   - Hover / focus eases the row to a stop and back up instead of snapping.
 *   - Only a horizontal swipe (trackpad, or shift + wheel) scrubs it. A
 *     vertical wheel always scrolls the page; it used to be captured, which
 *     froze page scrolling while the cursor crossed the ticker.
 *   - Touch / pen / mouse drag scrubs it with a little momentum; vertical
 *     touch moves still scroll the page (touch-action: pan-y).
 *   - Logo style: every logo is sized to the same visual weight (width grows
 *     with sqrt(aspect)) from its width/height attributes, before it loads.
 *   - The frame loop stops while the ticker is off-screen or the tab is hidden.
 * Reduced motion: no motion at all; CSS makes it a plain horizontal scroller.
 */
( function () {
	'use strict';

	var SPEED      = 34;   // px per second at cruise.
	var EASE       = 5;    // velocity approach rate (per second): ~0.5s to stop or resume.
	var LOGO_BASE  = 92;   // a square logo renders 92x92; wider ones get wider and shorter.
	var LOGO_MAX_W = 210;
	var LOGO_MAX_H = 96;

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function now() {
		return window.performance ? performance.now() : Date.now();
	}

	// Equal visual weight: roughly the same area for every logo, clamped to the box.
	function sizeLogos( ticker ) {
		if ( ! ticker.classList.contains( 'lis-pv-ticker-logos' ) ) {
			return;
		}
		ticker.querySelectorAll( '.lis-pv-ticker-logo' ).forEach( function ( img ) {
			var w = parseFloat( img.getAttribute( 'width' ) ) || img.naturalWidth;
			var h = parseFloat( img.getAttribute( 'height' ) ) || img.naturalHeight;
			if ( ! w || ! h ) {
				img.addEventListener( 'load', function () { sizeLogos( ticker ); }, { once: true } );
				return;
			}
			var ar = w / h;
			var bw = LOGO_BASE * Math.sqrt( ar );
			var bh = bw / ar;
			if ( bw > LOGO_MAX_W ) { bw = LOGO_MAX_W; bh = bw / ar; }
			if ( bh > LOGO_MAX_H ) { bh = LOGO_MAX_H; bw = bh * ar; }
			img.style.width     = Math.round( bw ) + 'px';
			img.style.height    = Math.round( bh ) + 'px';
			img.style.maxWidth  = 'none';
			img.style.maxHeight = 'none';
		} );
	}

	function initTicker( ticker ) {
		var track  = ticker.querySelector( '.lis-pv-ticker-track' );
		var groups = ticker.querySelectorAll( '.lis-pv-ticker-group' );
		if ( ! track ) {
			return;
		}
		sizeLogos( ticker );
		if ( reduce || groups.length < 2 ) {
			track.style.visibility = 'visible'; // shortcode renders it hidden until positioned
			return;
		}

		var offset  = 0;     // px the track is shifted left
		var loopW   = 0;     // one group's width = one seamless loop
		var vel     = 0;     // auto-scroll px/s (eased)
		var coast   = 0;     // fling momentum px/s after a drag (decays)
		var hover   = false;
		var drag    = null;
		var userTil = 0;     // auto-scroll yields until this time after a scrub
		var visible = true;
		var last    = 0;
		var raf     = 0;
		var suppressClick = false;

		function wrap() {
			if ( loopW > 0 ) {
				offset = offset % loopW;
				if ( offset < 0 ) {
					offset += loopW;
				}
			}
		}

		function apply() {
			track.style.transform = 'translate3d(' + ( -offset ).toFixed( 2 ) + 'px,0,0)';
		}

		function measure() {
			loopW = groups[1].offsetLeft - groups[0].offsetLeft;
			if ( loopW <= 0 ) {
				return;
			}
			var have = track.querySelectorAll( '.lis-pv-ticker-group' ).length;
			var need = Math.ceil( ( ticker.clientWidth + loopW ) / loopW ) + 1;
			for ( ; have < need; have++ ) {
				var c = groups[1].cloneNode( true ); // already aria-hidden
				c.querySelectorAll( 'a' ).forEach( function ( a ) { a.setAttribute( 'tabindex', '-1' ); } );
				track.appendChild( c );
			}
			wrap();
			apply();
		}

		function schedule() {
			if ( ! raf && visible && ! document.hidden ) {
				raf = window.requestAnimationFrame( frame );
			}
		}

		function wake() {
			last = 0; // no catch-up leap after being paused
			schedule();
		}

		function frame( t ) {
			raf = 0;
			var dt = last ? Math.min( ( t - last ) / 1000, 0.05 ) : 0;
			last = t;
			if ( ! drag && loopW > 0 ) {
				var target = ( hover || t < userTil ) ? 0 : SPEED;
				vel += ( target - vel ) * ( 1 - Math.exp( -EASE * dt ) );
				coast *= Math.exp( -3.2 * dt );
				if ( Math.abs( coast ) < 1 ) {
					coast = 0;
				}
				offset += ( vel + coast ) * dt;
				wrap();
				apply();
			}
			schedule();
		}

		// Hover / keyboard focus: ease to a stop in place.
		ticker.addEventListener( 'mouseenter', function () { hover = true; } );
		ticker.addEventListener( 'mouseleave', function () { hover = false; } );
		ticker.addEventListener( 'focusin', function () { hover = true; } );
		ticker.addEventListener( 'focusout', function () { hover = false; } );

		// Horizontal swipe (or shift + wheel) scrubs. Vertical wheel scrolls the page.
		ticker.addEventListener( 'wheel', function ( e ) {
			var dx = e.deltaX, dy = e.deltaY;
			if ( e.shiftKey && ! dx ) {
				dx = dy;
				dy = 0;
			}
			if ( ! dx || Math.abs( dx ) <= Math.abs( dy ) || loopW <= 0 ) {
				return;
			}
			offset += dx;
			wrap();
			apply();
			vel = 0;
			coast = 0;
			userTil = now() + 1200;
			e.preventDefault();
		}, { passive: false } );

		// Drag / swipe with momentum. Direction is decided after 6px:
		// a vertical move is left to the page.
		ticker.style.touchAction = 'pan-y';
		ticker.addEventListener( 'pointerdown', function ( e ) {
			if ( ( e.pointerType === 'mouse' && e.button !== 0 ) || loopW <= 0 ) {
				return;
			}
			drag = { id: e.pointerId, x: e.clientX, y: e.clientY, start: offset, lastX: e.clientX, lastT: now(), v: 0, horizontal: null };
			coast = 0;
		} );
		ticker.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag || e.pointerId !== drag.id ) {
				return;
			}
			if ( e.pointerType === 'mouse' && ! ( e.buttons & 1 ) ) {
				drag = null; // button released outside the ticker before a drag began
				return;
			}
			var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
			if ( drag.horizontal === null ) {
				if ( Math.abs( dx ) < 6 && Math.abs( dy ) < 6 ) {
					return;
				}
				drag.horizontal = Math.abs( dx ) > Math.abs( dy );
				if ( ! drag.horizontal ) {
					drag = null;
					return;
				}
				try { ticker.setPointerCapture( e.pointerId ); } catch ( err ) {}
				ticker.classList.add( 'is-dragging' );
			}
			var t = now(), step = Math.max( t - drag.lastT, 1 );
			drag.v = 0.8 * ( -( e.clientX - drag.lastX ) / step * 1000 ) + 0.2 * drag.v;
			drag.lastX = e.clientX;
			drag.lastT = t;
			offset = drag.start - dx;
			wrap();
			apply();
			vel = 0;
		} );
		function endDrag( e ) {
			if ( ! drag || e.pointerId !== drag.id ) {
				return;
			}
			if ( drag.horizontal ) {
				suppressClick = true;
				setTimeout( function () { suppressClick = false; }, 0 );
				coast = Math.max( -1200, Math.min( 1200, drag.v ) );
				userTil = now() + 900;
				ticker.classList.remove( 'is-dragging' );
			}
			drag = null;
			wake();
		}
		ticker.addEventListener( 'pointerup', endDrag );
		ticker.addEventListener( 'pointercancel', endDrag );
		// A drag that ends on a logo shouldn't open its link.
		ticker.addEventListener( 'click', function ( e ) {
			if ( suppressClick ) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true );
		ticker.addEventListener( 'dragstart', function ( e ) { e.preventDefault(); } );

		// Only run frames while on screen and the tab is visible.
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				visible = entries[ entries.length - 1 ].isIntersecting;
				if ( visible ) {
					wake();
				}
			}, { rootMargin: '100px 0px' } ).observe( ticker );
		}
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden ) {
				wake();
			}
		} );

		var resizeRaf = 0;
		window.addEventListener( 'resize', function () {
			if ( ! resizeRaf ) {
				resizeRaf = window.requestAnimationFrame( function () { resizeRaf = 0; measure(); } );
			}
		} );

		// Measure, then reveal at a random point in the loop (the track is
		// rendered visibility:hidden so it never flashes at offset 0).
		measure();
		if ( loopW > 0 ) {
			offset = Math.random() * loopW;
		}
		vel = SPEED;
		apply();
		track.style.visibility = 'visible';
		// Logos without size attributes can change width once loaded.
		ticker.querySelectorAll( 'img' ).forEach( function ( img ) {
			if ( ! img.complete ) {
				img.addEventListener( 'load', measure, { once: true } );
			}
		} );
		schedule();
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
