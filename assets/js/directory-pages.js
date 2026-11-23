/* Category tiles (0.74.1): the More list closes on an outside click or Escape. */
( function () {
	var mores = [].slice.call( document.querySelectorAll( '[data-lis-v2-more]' ) );
	if ( ! mores.length ) {
		return;
	}
	document.addEventListener( 'click', function ( e ) {
		mores.forEach( function ( d ) {
			if ( d.open && ! d.contains( e.target ) ) {
				d.open = false;
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}
		mores.forEach( function ( d ) {
			if ( d.open ) {
				d.open = false;
				d.querySelector( 'summary' ).focus();
			}
		} );
	} );
} )();

/* A–Z Index (0.74.0): search box + category buttons filter the rows; empty letters hide. */
( function () {
	var root = document.querySelector( '[data-lis-index]' );
	if ( ! root ) {
		return;
	}
	var rows = [].slice.call( root.querySelectorAll( 'li[data-q]' ) );
	var secs = [].slice.call( root.querySelectorAll( '.lis-v2-az-sec' ) );
	var count = root.querySelector( '[data-lis-index-count]' );
	var empty = root.querySelector( '[data-lis-index-empty]' );
	var input = root.querySelector( '[data-lis-index-q]' );
	var state = { q: '', cat: '' };

	function apply() {
		var n = 0;
		rows.forEach( function ( li ) {
			var ok = ( ! state.q || li.getAttribute( 'data-q' ).indexOf( state.q ) >= 0 ) &&
				( ! state.cat || ( ' ' + li.getAttribute( 'data-cats' ) + ' ' ).indexOf( ' ' + state.cat + ' ' ) >= 0 );
			li.hidden = ! ok;
			if ( ok ) {
				n++;
			}
		} );
		secs.forEach( function ( s ) {
			s.hidden = ! s.querySelector( 'li[data-q]:not([hidden])' );
		} );
		if ( count ) {
			count.textContent = n + ( 1 === n ? ' place' : ' places' );
		}
		if ( empty ) {
			empty.hidden = n > 0;
		}
	}
	if ( input ) {
		input.addEventListener( 'input', function () {
			state.q = input.value.trim().toLowerCase();
			apply();
		} );
	}
	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.lis-v2-chips button' );
		if ( ! b ) {
			return;
		}
		state.cat = b.getAttribute( 'data-cat' ) || '';
		root.querySelectorAll( '.lis-v2-chips button' ).forEach( function ( x ) {
			x.setAttribute( 'aria-pressed', x === b ? 'true' : 'false' );
		} );
		apply();
	} );
} )();

/*
 * Photo grids (0.74.2): shortest-column masonry. CSS columns left Safari with one
 * long column and an empty one; this places each card, in order, under the
 * shortest column. Card height is the photo's shape (--lis-v2-ar), so no images
 * need to load first. Without JS the CSS columns still work.
 */
( function () {
	var grids = [].slice.call( document.querySelectorAll( '.lis-v2-grid' ) );
	if ( ! grids.length ) {
		return;
	}
	function count( w ) {
		if ( window.matchMedia( '(max-width:640px)' ).matches ) {
			return 2;
		}
		return Math.max( 1, Math.min( 4, Math.floor( ( w + 16 ) / ( 240 + 16 ) ) ) );
	}
	grids.forEach( function ( g ) {
		var cards = [].slice.call( g.querySelectorAll( ':scope > .lis-v2-card' ) );
		var n = 0;
		if ( cards.length < 2 ) {
			return;
		}
		function lay() {
			var want = count( g.clientWidth );
			if ( want === n ) {
				return;
			}
			n = want;
			var cols = [], h = [];
			for ( var i = 0; i < n; i++ ) {
				var c = document.createElement( 'div' );
				c.className = 'lis-v2-col';
				cols.push( c );
				h.push( 0 );
			}
			cards.forEach( function ( card ) {
				var ar = parseFloat( card.style.getPropertyValue( '--lis-v2-ar' ) ) || 1.333;
				var k = h.indexOf( Math.min.apply( null, h ) );
				cols[ k ].appendChild( card );
				h[ k ] += 1 / ar + 0.06; // 0.06 ≈ the gap below each card
			} );
			g.textContent = '';
			cols.forEach( function ( c ) {
				g.appendChild( c );
			} );
			g.classList.add( 'is-mason' );
		}
		lay();
		var t;
		window.addEventListener( 'resize', function () {
			clearTimeout( t );
			t = setTimeout( lay, 120 );
		} );
	} );
} )();

/*
 * Locals' favourites fade (0.74.7): after the masonry runs, cut the grid a little
 * below the shortest column so the bottom is one straight fade instead of ragged
 * column ends. The fade takes the colour of whatever sits behind the section.
 */
( function () {
	var wraps = [].slice.call( document.querySelectorAll( '[data-lis-v2-fade]' ) );
	if ( ! wraps.length ) {
		return;
	}
	function bg( el ) {
		while ( el ) {
			var c = getComputedStyle( el ).backgroundColor;
			if ( c && 'transparent' !== c && ! /rgba\([^)]*,\s*0\)$/.test( c ) ) {
				return c;
			}
			el = el.parentElement;
		}
		return '#fff';
	}
	function clip( w ) {
		var g = w.querySelector( '.lis-v2-grid' );
		var cols = g.classList.contains( 'is-mason' ) ? [].slice.call( g.children ) : [];
		if ( cols.length < 2 ) {
			w.classList.remove( 'is-clipped' );
			return;
		}
		var hs = cols.map( function ( c ) {
			return c.offsetHeight;
		} );
		var lo = Math.min.apply( null, hs );
		var hi = Math.max.apply( null, hs );
		if ( hi - lo < 24 ) {
			w.classList.remove( 'is-clipped' );
			return;
		}
		w.style.setProperty( '--v2-clip', ( lo + 90 ) + 'px' );
		w.classList.add( 'is-clipped' );
	}
	wraps.forEach( function ( w ) {
		w.style.setProperty( '--v2-fadebg', bg( w.parentElement ) );
		clip( w );
	} );
	var t;
	window.addEventListener( 'resize', function () {
		clearTimeout( t );
		t = setTimeout( function () {
			wraps.forEach( clip );
		}, 180 );
	} );
} )();

/*
 * Motion (0.75.0). Reduced motion: none of this runs.
 * - Hero photos fan apart as the hero scrolls away (individual translate/rotate
 *   properties, so the hover transform still works).
 * - Below-the-fold pieces reveal once: tiles rise in a cascade, the All listings
 *   door wipes up, the A–Z slides in, favourites cards rise column by column.
 *   Anything already on screen at load is left alone (no flash).
 */
( function () {
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}
	var root = document.documentElement;

	/* hero fan */
	var hero = document.querySelector( '.lis-v2-hero' );
	var phs = hero ? [].slice.call( hero.querySelectorAll( '.lis-v2-stack-ph' ) ) : [];
	var FAN = [ [ -38, 34, -4 ], [ 36, -20, 4 ], [ 0, 64, 0 ] ]; // x px, y px, extra deg at full scroll
	var ticking = false;
	function fan() {
		ticking = false;
		var r = hero.getBoundingClientRect();
		var p = Math.max( 0, Math.min( 1, -r.top / Math.max( 1, r.height * 0.9 ) ) );
		phs.forEach( function ( el, i ) {
			var f = FAN[ i ] || FAN[ 2 ];
			el.style.translate = ( f[0] * p ).toFixed( 1 ) + 'px ' + ( f[1] * p ).toFixed( 1 ) + 'px';
			el.style.rotate = ( f[2] * p ).toFixed( 2 ) + 'deg';
		} );
	}

	/* reveals */
	var items = [];
	function mark( el, kind, delay ) {
		if ( ! el || el.getBoundingClientRect().top < window.innerHeight * 0.92 ) {
			return; // already visible at load
		}
		el.setAttribute( 'data-v2-rv', kind );
		el.style.setProperty( '--v2-d', ( delay || 0 ) + 'ms' );
		items.push( el );
	}
	document.querySelectorAll( '.lis-v2-band' ).forEach( function ( band ) {
		band.querySelectorAll( '.lis-v2-sec-h' ).forEach( function ( h ) { mark( h, 'up', 0 ); } );
		[].slice.call( band.querySelectorAll( '.lis-v2-tiles > *' ) ).forEach( function ( t, i ) { mark( t, 'up', 60 + i * 55 ); } );
		mark( band.querySelector( '.lis-v2-door--all' ), 'wipe', 0 );
		mark( band.querySelector( '.lis-v2-door--idx' ), 'up', 160 );
		mark( band.querySelector( '.lis-v2-door--idx .lis-v2-az' ), 'right', 380 );
	} );
	document.querySelectorAll( '.lis-v2-sec--favs' ).forEach( function ( sec ) {
		mark( sec.querySelector( '.lis-v2-sec-h' ), 'up', 0 );
		sec.querySelectorAll( '.lis-v2-card' ).forEach( function ( c ) {
			var col = c.parentElement.classList.contains( 'lis-v2-col' ) ? [].indexOf.call( c.parentElement.parentElement.children, c.parentElement ) : 0;
			mark( c, 'card', col * 90 );
		} );
		mark( sec.querySelector( '.lis-v2-fade-link' ), 'up', 300 );
	} );
	mark( document.querySelector( '.lis-pv-heading' ), 'fade', 0 );

	if ( items.length ) {
		root.classList.add( 'lis-v2-motion' );
	}
	function check() {
		var line = window.innerHeight * 0.88;
		items = items.filter( function ( el ) {
			if ( el.getBoundingClientRect().top > line ) {
				return true;
			}
			el.classList.add( 'is-in' );
			var d = parseInt( el.style.getPropertyValue( '--v2-d' ), 10 ) || 0;
			setTimeout( function () {
				// hand the element back to its own hover transitions
				el.removeAttribute( 'data-v2-rv' );
				el.classList.remove( 'is-in' );
			}, d + 1200 );
			return false;
		} );
	}

	function onScroll() {
		if ( items.length ) {
			check();
		}
		if ( phs.length && ! ticking ) {
			ticking = true;
			requestAnimationFrame( fan );
		}
	}
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onScroll );
	onScroll();
} )();

/*
 * Typewriter (0.75.2): [lis_directory_home typewriter="<selector>"] types that
 * text out once it scrolls into view. Whole thing takes ~1.8s however long the
 * text is, with a blinking caret riding the last letter. Reduced motion: skipped.
 */
( function () {
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}
	var targets = [];
	document.querySelectorAll( '[data-lis-v2-typewriter]' ).forEach( function ( w ) {
		try {
			document.querySelectorAll( w.getAttribute( 'data-lis-v2-typewriter' ) ).forEach( function ( el ) {
				if ( targets.indexOf( el ) < 0 ) {
					targets.push( el );
				}
			} );
		} catch ( e ) {} // bad selector: do nothing
	} );
	targets = targets.filter( function ( el ) {
		return el.getBoundingClientRect().top > window.innerHeight * 0.8; // already visible: leave it
	} );
	if ( ! targets.length ) {
		return;
	}
	var DURATION = 1800;

	function prep( el ) {
		var walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT );
		var nodes = [], n, chars = [];
		while ( ( n = walker.nextNode() ) ) {
			if ( n.nodeValue.trim() ) {
				nodes.push( n );
			}
		}
		el.setAttribute( 'aria-label', el.textContent.replace( /\s+/g, ' ' ).trim() );
		nodes.forEach( function ( node ) {
			var frag = document.createDocumentFragment();
			node.nodeValue.split( /(\s+)/ ).forEach( function ( part ) {
				if ( ! part ) {
					return;
				}
				if ( /^\s+$/.test( part ) ) {
					var sp = document.createElement( 'span' );
					sp.className = 'lis-v2-tw-c';
					sp.setAttribute( 'aria-hidden', 'true' );
					sp.textContent = part;
					frag.appendChild( sp );
					chars.push( sp );
					return;
				}
				// keep each word unbreakable so lines never re-wrap mid-type
				var word = document.createElement( 'span' );
				word.style.whiteSpace = 'nowrap';
				word.setAttribute( 'aria-hidden', 'true' );
				for ( var i = 0; i < part.length; i++ ) {
					var c = document.createElement( 'span' );
					c.className = 'lis-v2-tw-c';
					c.textContent = part[ i ];
					word.appendChild( c );
					chars.push( c );
				}
				frag.appendChild( word );
			} );
			node.parentNode.replaceChild( frag, node );
		} );
		var caret = document.createElement( 'span' );
		caret.className = 'lis-v2-tw-caret';
		caret.setAttribute( 'aria-hidden', 'true' );
		return { chars: chars, caret: caret };
	}
	function type( el ) {
		var t = el._lisTw;
		var start = null, shown = 0;
		function frame( now ) {
			if ( null === start ) {
				start = now;
			}
			var p = Math.min( 1, ( now - start ) / DURATION );
			var want = Math.round( t.chars.length * p );
			while ( shown < want ) {
				t.chars[ shown++ ].classList.add( 'is-on' );
			}
			var last = t.chars[ Math.max( 0, shown - 1 ) ];
			last.parentNode.insertBefore( t.caret, last.nextSibling );
			if ( p < 1 ) {
				requestAnimationFrame( frame );
			} else {
				setTimeout( function () { t.caret.classList.add( 'is-done' ); }, 1400 );
			}
		}
		requestAnimationFrame( frame );
	}
	targets.forEach( function ( el ) {
		el._lisTw = prep( el );
	} );
	function check() {
		targets = targets.filter( function ( el ) {
			if ( el.getBoundingClientRect().top > window.innerHeight * 0.75 ) {
				return true;
			}
			type( el );
			return false;
		} );
		if ( ! targets.length ) {
			window.removeEventListener( 'scroll', check );
		}
	}
	window.addEventListener( 'scroll', check, { passive: true } );
	check();
} )();
