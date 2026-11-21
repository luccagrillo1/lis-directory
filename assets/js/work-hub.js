/* Work board on /jobs/ (includes/work-hub.php): filters that animate cards out
   instead of reloading, empty states, the post composer, and the first-visit tour. */
( function () {
	'use strict';
	var root = document.querySelector( '.wh' );
	if ( ! root ) { return; }
	var CFG = window.lisWorkHub || {};
	var reduced = window.matchMedia && matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var $ = function ( s, el ) { return ( el || root ).querySelector( s ); };
	var $$ = function ( s, el ) { return [].slice.call( ( el || root ).querySelectorAll( s ) ); };
	var rows = $$( '.wh-row' );
	var state = { town: '', type: '', q: '' };
	var COL = { jobs: 'jobs', gigs: 'gigs', people: 'neighbors for hire' };
	var EMPTY = {
		jobs: [ 'No jobs posted right now', 'Local employers post here when they\'re hiring. Check back soon.', 'Post a job' ],
		gigs: [ 'No gigs yet', 'Need a hand with something? Yours could be the first one up.', 'Post a gig' ],
		people: [ 'Nobody listed yet', 'Do odd jobs or have a skill to offer? Let neighbors find you.', 'List yourself' ]
	};

	/* ---------- filtering ---------- */
	function matches( r ) {
		if ( state.town && r.getAttribute( 'data-town' ) !== state.town ) { return false; }
		if ( state.type && r.getAttribute( 'data-kind' ) === 'hiring' && r.getAttribute( 'data-type' ) !== state.type ) { return false; }
		if ( state.q ) {
			var t = r.getAttribute( 'data-text' );
			var words = state.q.split( /\s+/ );
			for ( var i = 0; i < words.length; i++ ) { if ( t.indexOf( words[ i ] ) < 0 ) { return false; } }
		}
		return true;
	}

	var run = 0;
	function apply() {
		var token = ++run;
		var leaving = [], entering = [], staying = [];
		rows.forEach( function ( r ) {
			var shown = ! r.classList.contains( 'is-out' ), want = matches( r );
			if ( shown && ! want ) { leaving.push( r ); } else if ( ! shown && want ) { entering.push( r ); } else if ( shown && want ) { staying.push( r ); }
			if ( r.getAnimations ) { r.getAnimations().forEach( function ( a ) { a.cancel(); } ); }
		} );
		if ( reduced || ! document.body.animate || ( ! leaving.length && ! entering.length ) ) {
			leaving.forEach( function ( r ) { r.classList.add( 'is-out' ); } );
			entering.forEach( function ( r ) { r.classList.remove( 'is-out' ); } );
			after();
			return;
		}
		// 1. cards that no longer match slip out where they are
		var outs = leaving.map( function ( r ) {
			return r.animate( [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(-14px) scale(.98)' } ], { duration: 170, easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards' } );
		} );
		Promise.all( outs.map( function ( a ) { return a.finished.catch( function () {} ); } ) ).then( function () {
			if ( token !== run ) { return; }
			// 2. the rest close the gap (FLIP), new matches fade in
			var first = staying.map( function ( r ) { return r.getBoundingClientRect().top; } );
			leaving.forEach( function ( r ) { r.classList.add( 'is-out' ); if ( r.getAnimations ) { r.getAnimations().forEach( function ( a ) { a.cancel(); } ); } } );
			entering.forEach( function ( r ) { r.classList.remove( 'is-out' ); } );
			after();
			staying.forEach( function ( r, i ) {
				var dy = first[ i ] - r.getBoundingClientRect().top;
				if ( Math.abs( dy ) > 0.5 ) {
					r.animate( [ { transform: 'translateY(' + dy + 'px)' }, { transform: 'none' } ], { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' } );
				}
			} );
			entering.forEach( function ( r, i ) {
				r.animate( [ { opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' } ], { duration: 320, delay: 60 + Math.min( i, 8 ) * 35, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'backwards' } );
			} );
		} );
	}

	function after() {
		var n = { jobs: 0, gigs: 0, people: 0 };
		$$( '.wh-col' ).forEach( function ( col ) {
			var key = col.getAttribute( 'data-sec' );
			var vis = $$( '.wh-row:not(.is-out)', col ).length;
			n[ key ] = vis;
			$( '.wh-n', col ).textContent = vis;
			empty( col, key, vis );
		} );
		var out = $( '.wh-counts' );
		if ( out ) {
			out.innerHTML = state.q ? '<b>' + n.jobs + '</b> ' + ( n.jobs === 1 ? 'job' : 'jobs' ) + ' · <b>' + n.gigs + '</b> ' + ( n.gigs === 1 ? 'gig' : 'gigs' ) +
				( $( '.wh-col--people' ) ? ' · <b>' + n.people + '</b> ' + ( n.people === 1 ? 'neighbor' : 'neighbors' ) + ' for hire' : '' ) : '';
		}
	}

	function esc( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }

	function empty( col, key, vis ) {
		var box = $( '.wh-empty', col );
		if ( ! box ) { return; }
		var total = +box.getAttribute( 'data-total' );
		var was = ! box.hidden;
		box.hidden = vis > 0;
		if ( vis > 0 ) { box.classList.remove( 'is-in' ); return; }
		var e = EMPTY[ key ], h, p, filtered = total > 0;
		if ( ! filtered ) {
			h = e[ 0 ]; p = e[ 1 ];
		} else {
			var where = state.town ? ' in ' + state.town : '';
			if ( state.q ) {
				h = 'No ' + COL[ key ] + where + ' match “' + state.q + '”';
			} else if ( state.type && key === 'jobs' ) {
				var b = $( '.wh-types [data-type="' + state.type + '"]' );
				h = 'No ' + ( b ? b.textContent.toLowerCase() : '' ) + ' jobs' + where + ' right now';
			} else {
				h = key === 'people' ? 'Nobody' + where + ' has listed themselves yet' : 'No ' + COL[ key ] + where + ' right now';
			}
			p = key === 'gigs' ? 'Need a hand' + where + '? Post it and neighbors nearby will see it.'
				: key === 'people' ? 'Do odd jobs' + where + '? List yourself so people can find you.'
				: 'Try another town or search, or see everything.';
		}
		box.setAttribute( 'data-mode', filtered ? 'filtered' : 'none' );
		$( '.wh-empty-h', box ).textContent = h;
		$( '.wh-empty-p', box ).textContent = p;
		$( '.wh-empty-clear', box ).hidden = ! filtered;
		var post = $( '.wh-empty-post', box );
		if ( post ) { post.textContent = e[ 2 ]; }
		if ( ! was && ! reduced ) { box.classList.remove( 'is-in' ); void box.offsetWidth; box.classList.add( 'is-in' ); }
	}

	function press( group, attr, val ) {
		$$( group + ' button:not(.wh-dot)' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.getAttribute( attr ) === val ? 'true' : 'false' ); } );
	}

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.wh-towns button:not(.wh-dot)' );
		if ( b ) { state.town = b.getAttribute( 'data-town' ); press( '.wh-towns', 'data-town', state.town ); apply(); return; }
		b = e.target.closest( '.wh-types button' );
		if ( b ) { state.type = b.getAttribute( 'data-type' ); press( '.wh-types', 'data-type', state.type ); apply(); return; }
		b = e.target.closest( '.wh-tabs button' );
		if ( b ) { tab( b.getAttribute( 'data-col' ) ); return; }
		b = e.target.closest( '.wh-empty-clear' );
		if ( b ) { clearAll(); return; }
		var a = e.target.closest( '[data-go]' );
		if ( a ) {
			e.preventDefault();
			var go = a.getAttribute( 'data-go' ), q = a.getAttribute( 'data-q' );
			if ( q ) { setQ( q ); }
			tab( go );
			var col = $( '[data-sec="' + go + '"]' );
			if ( col ) { scrollTo( { top: col.getBoundingClientRect().top + scrollY - 90, behavior: reduced ? 'auto' : 'smooth' } ); }
		}
	} );

	function clearAll() {
		state.town = ''; state.type = '';
		press( '.wh-towns', 'data-town', '' ); press( '.wh-types', 'data-type', '' );
		setQ( '' );
	}

	var input = $( '.wh-search input' ), qt = 0;
	function setQ( v ) { if ( input ) { input.value = v; } state.q = v.trim().toLowerCase(); apply(); }
	if ( input ) {
		state.q = input.value.trim().toLowerCase();
		input.addEventListener( 'input', function () { clearTimeout( qt ); qt = setTimeout( function () { setQ( input.value ); }, 140 ); } );
		$( '.wh-search' ).addEventListener( 'submit', function ( e ) { e.preventDefault(); setQ( input.value ); } );
	}

	function tab( k ) {
		if ( ! $( '[data-sec="' + k + '"]' ) ) { return; }
		root.setAttribute( 'data-col', k );
		$$( '.wh-tabs button' ).forEach( function ( b ) { b.setAttribute( 'aria-selected', b.getAttribute( 'data-col' ) === k ? 'true' : 'false' ); } );
	}
	tab( 'gigs' );
	if ( state.q ) { rows.forEach( function ( r ) { r.classList.toggle( 'is-out', ! matches( r ) ); } ); }
	after();

	/* ---------- composer ---------- */
	var form = $( '.wh-compose-form' );
	if ( form ) {
		var LBL = { gig: [ 'What do you need done?', 'Rake leaves in the front yard', 'Local gig' ], hiring: [ 'Job title', 'Barista, mornings', 'Job' ], seeking: [ 'What can you do?', 'Odd jobs, hauling, yard work', 'Available for work' ] };
		var prev = $( '.wh-prev-card' );
		var sync = function () {
			var k = ( form.querySelector( 'input[name="kind"]:checked' ) || {} ).value || 'gig';
			var title = form.elements.title, loc = form.elements.location, when = form.elements.when;
			$( '[data-l="title"]', form ).textContent = LBL[ k ][ 0 ];
			title.placeholder = LBL[ k ][ 1 ];
			$$( '[data-only="gig"]', form ).forEach( function ( el ) { el.hidden = k !== 'gig'; when.disabled = k !== 'gig'; } );
			var kind = $( '.wh-prev-kind', prev );
			kind.textContent = LBL[ k ][ 2 ]; kind.setAttribute( 'data-k', k );
			$( 'b', prev ).textContent = title.value.trim() || LBL[ k ][ 1 ];
			$( '.wh-prev-sub', prev ).textContent = [ loc.value.trim() || 'Sandpoint', k === 'gig' ? ( when.value.trim() || 'This week' ) : '' ].filter( Boolean ).join( ' · ' );
		};
		form.addEventListener( 'input', sync );
		form.addEventListener( 'change', sync );
		sync();
	}

	/* ---------- first-visit tour ---------- */
	var KEY = 'lis_work_tour';
	function localDone() { try { return localStorage.getItem( KEY ) === 'done'; } catch ( e ) { return false; } }
	function saveDone() {
		try { localStorage.setItem( KEY, 'done' ); } catch ( e ) {}
		if ( CFG.signedIn && CFG.rest && ! CFG.tourDone ) {
			CFG.tourDone = true;
			fetch( CFG.rest, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce }, body: JSON.stringify( { done: true } ) } ).catch( function () {} );
		}
	}
	var done = !! CFG.tourDone || localDone();
	if ( done && ! CFG.tourDone && CFG.signedIn ) { saveDone(); } // seen on this device before signing in: carry it to the account

	var STEPS = [
		{ k: 'search', t: 'Search everything at once', d: 'One box looks through jobs, gigs and people. Try “snow”, “cook” or “dog”.' },
		{ k: 'towns', t: 'Pick your side of the lake', d: 'Tap a town to see only what\'s close by.' },
		{ k: 'jobs', t: 'Jobs', d: 'Steady work from local employers: full-time, part-time and seasonal. Open one to see how to apply.' },
		{ k: 'gigs', t: 'Local Gigs', d: 'Neighbors who need a hand once. Rake a yard, shovel a walk. Open one and offer to help.' },
		{ k: 'people', t: 'Available for Work', d: 'Neighbors who list what they do. Need a handyman? Skip posting and reach out directly.' },
		{ k: 'post', t: 'Post something, free', d: 'Need a hand, hiring, or looking for work? Start here. It goes live as soon as you save it.' }
	].filter( function ( s ) { return $( '[data-tour-step="' + s.k + '"]' ); } );

	var invite = $( '.wh-invite' ), again = $( '.wh-tour-again' );
	function showEntry() {
		invite.hidden = done; again.hidden = ! done;
		$$( '.wh-dot' ).forEach( function ( d ) { d.hidden = done; } );
	}
	STEPS.forEach( function ( s, n ) {
		var d = document.createElement( 'button' );
		d.type = 'button'; d.className = 'wh-dot'; d.textContent = n + 1;
		d.setAttribute( 'aria-label', 'Tour step ' + ( n + 1 ) + ': ' + s.t );
		d.setAttribute( 'data-step', n );
		var host = $( '[data-tour-step="' + s.k + '"]' );
		if ( s.k === 'towns' ) { host.appendChild( d ); } else { host.insertBefore( d, host.firstChild ); } // first child keeps a column's last-child rounding intact
	} );
	showEntry();

	var spot = document.createElement( 'div' ), tip = document.createElement( 'div' ), at = -1;
	spot.className = 'wh-spot'; tip.className = 'wh-tip';
	tip.setAttribute( 'role', 'dialog' ); tip.setAttribute( 'aria-live', 'polite' );
	document.body.appendChild( spot ); document.body.appendChild( tip );

	function geom( n ) {
		var el = $( '[data-tour-step="' + STEPS[ n ].k + '"]' );
		if ( STEPS[ n ].k === 'jobs' || STEPS[ n ].k === 'gigs' || STEPS[ n ].k === 'people' ) { tab( STEPS[ n ].k ); }
		var r = el.getBoundingClientRect(), pad = 8;
		var x = r.left + scrollX - pad, y = r.top + scrollY - pad, w = r.width + pad * 2;
		var th = tip.offsetHeight || 170;
		var h = Math.min( r.height + pad * 2, innerHeight - th - 150 );
		return { x: x, y: y, w: w, h: Math.max( h, 60 ), th: th };
	}
	// Page coordinates, so the box rides along with the scroll instead of chasing it.
	function place( n, scroll ) {
		var g = geom( n ), tw = Math.min( 360, innerWidth - 32 );
		var tx = Math.min( Math.max( 16, g.x + 8 ), scrollX + innerWidth - tw - 16 );
		spot.style.width = g.w + 'px'; spot.style.height = g.h + 'px';
		spot.style.transform = 'translate(' + g.x + 'px,' + g.y + 'px)';
		tip.style.width = tw + 'px';
		tip.style.transform = 'translate(' + tx + 'px,' + ( g.y + g.h + 14 ) + 'px)';
		if ( scroll ) {
			var top = Math.max( 0, g.y - Math.max( 80, ( innerHeight - g.h - g.th - 14 ) / 2 ) );
			scrollTo( { top: top, behavior: reduced ? 'auto' : 'smooth' } );
		}
	}
	function go( n ) {
		if ( n < 0 ) { n = 0; }
		if ( n >= STEPS.length ) { end(); return; }
		var startNow = at < 0;
		at = n;
		var s = STEPS[ n ], last = n === STEPS.length - 1;
		tip.innerHTML = '<p class="wh-tip-n">' + ( n + 1 ) + ' of ' + STEPS.length + '</p><h3>' + esc( s.t ) + '</h3><p>' + esc( s.d ) + '</p>' +
			'<div class="wh-tip-btns"><button type="button" data-t="' + ( n ? 'prev' : 'end' ) + '">' + ( n ? 'Back' : 'Skip' ) + '</button>' +
			'<button type="button" class="wh-tip-next" data-t="next">' + ( last ? 'Done' : 'Next' ) + '</button></div>';
		if ( startNow ) {
			// first step: place without the slide-in from 0,0
			spot.style.transition = tip.style.transition = 'none';
			document.documentElement.classList.add( 'wh-touring' );
			place( n, true );
			void spot.offsetWidth;
			spot.style.transition = tip.style.transition = '';
		} else {
			place( n, true );
		}
		var next = $( '.wh-tip-next', tip ); if ( next ) { next.focus( { preventScroll: true } ); }
	}
	function end() {
		at = -1;
		document.documentElement.classList.remove( 'wh-touring' );
		done = true; saveDone(); showEntry();
	}
	tip.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' ); if ( ! b ) { return; }
		var t = b.getAttribute( 'data-t' );
		if ( t === 'next' ) { go( at + 1 ); } else if ( t === 'prev' ) { go( at - 1 ); } else { end(); }
	} );
	spot.addEventListener( 'click', end );
	document.addEventListener( 'keydown', function ( e ) {
		if ( at < 0 ) { return; }
		if ( e.key === 'Escape' ) { end(); } else if ( e.key === 'ArrowRight' ) { go( at + 1 ); } else if ( e.key === 'ArrowLeft' ) { go( at - 1 ); }
	} );
	addEventListener( 'resize', function () { if ( at > -1 ) { place( at, false ); } } );
	root.addEventListener( 'click', function ( e ) {
		var d = e.target.closest( '.wh-dot' );
		if ( d ) { e.preventDefault(); e.stopPropagation(); go( +d.getAttribute( 'data-step' ) ); return; }
		if ( e.target.closest( '[data-tour-start]' ) ) { go( 0 ); }
		if ( e.target.closest( '[data-tour-skip]' ) ) { done = true; saveDone(); showEntry(); }
	}, true );
}() );
