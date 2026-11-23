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
