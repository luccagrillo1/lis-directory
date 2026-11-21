/**
 * Listing photo layouts (includes/listing-gallery.php):
 *  - lightbox over the gallery's plain photo links (<dialog>, arrows, swipe,
 *    Esc, thumbnail strip; focus returns to the photo that opened it),
 *  - filmstrip arrows + counter, mosaic phone-carousel counter,
 *  - cover hero parallax (skipped under reduced motion).
 * Without JavaScript every photo is still a link to the full image.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function photosOf( gallery ) {
		var seen = {};
		var list = [];
		gallery.querySelectorAll( '[data-lis-photo]' ).forEach( function ( a ) {
			var i = parseInt( a.getAttribute( 'data-lis-photo' ), 10 );
			if ( seen[ i ] ) {
				return;
			}
			seen[ i ] = true;
			var thumb = a.querySelector( 'img' );
			list[ i ] = {
				src: a.getAttribute( 'href' ),
				alt: a.getAttribute( 'data-alt' ) || '',
				w: parseInt( a.getAttribute( 'data-w' ), 10 ) || 0,
				h: parseInt( a.getAttribute( 'data-h' ), 10 ) || 0,
				thumb: thumb ? ( thumb.currentSrc || thumb.src ) : a.getAttribute( 'href' ),
			};
		} );
		return list.filter( Boolean );
	}

	/* ---------- Lightbox ---------- */

	function buildLightbox( photos ) {
		var dlg = document.createElement( 'dialog' );
		dlg.className = 'lis-lb';
		dlg.setAttribute( 'aria-label', 'Photos' );
		dlg.innerHTML =
			'<div class="lis-lb-top"><span class="lis-lb-count" aria-live="polite"></span>' +
			'<button type="button" class="lis-lb-close" aria-label="Close photos">&#x2715;</button></div>' +
			'<div class="lis-lb-stage">' +
			( photos.length > 1 ? '<button type="button" class="lis-lb-nav" data-dir="-1" aria-label="Previous photo">&larr;</button>' : '' ) +
			'<img class="lis-lb-img" alt="" />' +
			( photos.length > 1 ? '<button type="button" class="lis-lb-nav" data-dir="1" aria-label="Next photo">&rarr;</button>' : '' ) +
			'</div>' +
			( photos.length > 1 ? '<div class="lis-lb-strip"></div>' : '' );
		document.body.appendChild( dlg );

		var img = dlg.querySelector( '.lis-lb-img' );
		var count = dlg.querySelector( '.lis-lb-count' );
		var strip = dlg.querySelector( '.lis-lb-strip' );
		var index = 0;
		var opener = null;

		if ( strip ) {
			photos.forEach( function ( p, i ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.setAttribute( 'aria-label', 'Photo ' + ( i + 1 ) );
				b.innerHTML = '<img alt="" loading="lazy" />';
				b.firstChild.src = p.thumb;
				b.addEventListener( 'click', function () {
					show( i );
				} );
				strip.appendChild( b );
			} );
		}

		function show( i ) {
			index = ( i + photos.length ) % photos.length;
			var p = photos[ index ];
			img.classList.add( 'is-loading' );
			var pre = new Image();
			pre.onload = pre.onerror = function () {
				if ( photos[ index ] !== p ) {
					return;
				}
				img.src = p.src;
				img.alt = p.alt;
				img.classList.remove( 'is-loading' );
			};
			pre.src = p.src;
			count.textContent = photos.length > 1 ? ( index + 1 ) + ' / ' + photos.length : '';
			if ( strip ) {
				strip.querySelectorAll( 'button' ).forEach( function ( b, bi ) {
					b.setAttribute( 'aria-current', bi === index ? 'true' : 'false' );
				} );
				var cur = strip.children[ index ];
				if ( cur && cur.scrollIntoView ) {
					cur.scrollIntoView( { block: 'nearest', inline: 'center' } );
				}
			}
			// Warm the neighbours so arrowing feels instant.
			[ 1, -1 ].forEach( function ( d ) {
				var n = photos[ ( index + d + photos.length ) % photos.length ];
				if ( n ) {
					( new Image() ).src = n.src;
				}
			} );
		}

		function open( i, from ) {
			opener = from || document.activeElement;
			show( i );
			if ( typeof dlg.showModal === 'function' ) {
				dlg.showModal();
			} else {
				dlg.setAttribute( 'open', '' );
			}
			document.documentElement.style.overflow = 'hidden';
		}

		function close() {
			if ( dlg.open ) {
				dlg.close();
			}
		}

		dlg.addEventListener( 'close', function () {
			document.documentElement.style.overflow = '';
			if ( opener && opener.focus ) {
				opener.focus( { preventScroll: true } );
			}
		} );
		dlg.querySelector( '.lis-lb-close' ).addEventListener( 'click', close );
		dlg.querySelectorAll( '.lis-lb-nav' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				show( index + parseInt( b.getAttribute( 'data-dir' ), 10 ) );
			} );
		} );
		dlg.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' === e.key ) {
				show( index + 1 );
			} else if ( 'ArrowLeft' === e.key ) {
				show( index - 1 );
			}
		} );
		// Click on the dark area (not the photo or a control) closes.
		dlg.addEventListener( 'click', function ( e ) {
			if ( e.target === dlg || e.target.classList.contains( 'lis-lb-stage' ) ) {
				close();
			}
		} );

		// Swipe.
		var x0 = null;
		var stage = dlg.querySelector( '.lis-lb-stage' );
		stage.addEventListener( 'pointerdown', function ( e ) {
			x0 = e.clientX;
		} );
		stage.addEventListener( 'pointerup', function ( e ) {
			if ( null === x0 || photos.length < 2 ) {
				return;
			}
			var dx = e.clientX - x0;
			x0 = null;
			if ( Math.abs( dx ) > 50 ) {
				show( index + ( dx < 0 ? 1 : -1 ) );
			}
		} );

		return open;
	}

	/* ---------- Rails: filmstrip arrows, mosaic phone carousel counter ---------- */

	function wireTrack( gallery, total ) {
		var track = gallery.querySelector( '[data-lis-track]' );
		var counter = gallery.querySelector( '[data-lis-count]' );
		var prev = gallery.querySelector( '[data-lis-prev]' );
		var next = gallery.querySelector( '[data-lis-next]' );
		if ( ! track ) {
			return;
		}
		var items = Array.prototype.slice.call( track.children );

		function current() {
			var left = track.getBoundingClientRect().left + parseFloat( getComputedStyle( track ).paddingLeft || 0 );
			var best = 0;
			var bestD = Infinity;
			items.forEach( function ( el, i ) {
				if ( ! el.offsetWidth ) {
					return;
				}
				var d = Math.abs( el.getBoundingClientRect().left - left );
				if ( d < bestD ) {
					bestD = d;
					best = i;
				}
			} );
			return best;
		}

		function update() {
			var i = current();
			if ( counter ) {
				counter.textContent = ( i + 1 ) + ' / ' + total;
			}
			if ( prev ) {
				prev.disabled = track.scrollLeft <= 2;
			}
			if ( next ) {
				next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
			}
		}

		function step( dir ) {
			var target = items[ Math.min( items.length - 1, Math.max( 0, current() + dir ) ) ];
			if ( target ) {
				var pad = parseFloat( getComputedStyle( track ).paddingLeft || 0 );
				track.scrollTo( { left: target.offsetLeft - pad, behavior: reduceMotion ? 'auto' : 'smooth' } );
			}
		}

		var raf = 0;
		track.addEventListener( 'scroll', function () {
			cancelAnimationFrame( raf );
			raf = requestAnimationFrame( update );
		}, { passive: true } );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				step( -1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				step( 1 );
			} );
		}
		track.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' === e.key ) {
				e.preventDefault();
				step( 1 );
			} else if ( 'ArrowLeft' === e.key ) {
				e.preventDefault();
				step( -1 );
			}
		} );
		window.addEventListener( 'resize', update );
		update();
	}

	/* ---------- Cover parallax: the photo drifts slower than the page ---------- */

	function wireParallax( gallery ) {
		var img = gallery.querySelector( '[data-lis-parallax]' );
		if ( ! img || reduceMotion ) {
			return;
		}
		var stage = gallery.querySelector( '.lis-gal-cover-stage' );
		var ticking = false;
		function frame() {
			ticking = false;
			var r = stage.getBoundingClientRect();
			if ( r.bottom < 0 || r.top > window.innerHeight ) {
				return;
			}
			var p = Math.min( 1, Math.max( 0, -r.top / r.height ) ); // 0 at rest, 1 when scrolled past.
			img.style.setProperty( '--lis-par', ( p * r.height * 0.28 ) + 'px' );
			img.style.setProperty( '--lis-par-scale', ( 1.04 + p * 0.04 ).toFixed( 4 ) );
		}
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				requestAnimationFrame( frame );
			}
		}, { passive: true } );
		frame();
	}

	/* Full-bleed layouts line their content up with the page's text column,
	   whatever width the theme gives it. */
	function alignToColumn( gallery ) {
		var col = gallery.parentElement;
		function measure() {
			var r = col.getBoundingClientRect();
			var cs = getComputedStyle( col );
			var left = Math.max( 16, r.left + parseFloat( cs.paddingLeft || 0 ) );
			gallery.style.setProperty( '--lis-gal-bleed', left + 'px' );
			gallery.style.setProperty( '--lis-gal-pad', left + 'px' );
		}
		measure();
		window.addEventListener( 'resize', measure );
	}

	function init() {
		document.querySelectorAll( '[data-lis-gallery]' ).forEach( function ( gallery ) {
			if ( gallery.classList.contains( 'lis-gal--filmstrip' ) || gallery.classList.contains( 'lis-gal--cover' ) ) {
				alignToColumn( gallery );
			}
			var photos = photosOf( gallery );
			if ( ! photos.length ) {
				return;
			}
			var open = buildLightbox( photos );
			gallery.addEventListener( 'click', function ( e ) {
				var link = e.target.closest( '[data-lis-photo]' );
				var all = e.target.closest( '[data-lis-photo-open]' );
				if ( link ) {
					// Ctrl/Cmd-click still opens the file in a new tab.
					if ( e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button ) {
						return;
					}
					e.preventDefault();
					open( parseInt( link.getAttribute( 'data-lis-photo' ), 10 ), link );
				} else if ( all ) {
					open( parseInt( all.getAttribute( 'data-lis-photo-open' ), 10 ) || 0, all );
				}
			} );
			wireTrack( gallery, photos.length );
			if ( gallery.classList.contains( 'lis-gal--cover' ) ) {
				document.body.classList.add( 'lis-gal-cover-active' );
				wireParallax( gallery );
			}
			if ( gallery.classList.contains( 'lis-gal--filmstrip' ) ) {
				document.body.classList.add( 'lis-gal-filmstrip-active' );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
