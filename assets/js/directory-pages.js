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
