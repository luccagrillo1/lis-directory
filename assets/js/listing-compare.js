(function () {
	'use strict';

	var COOKIE_NAME = 'lis_listing_compare';
	var MAX = ( window.lisDirectoryCompare && window.lisDirectoryCompare.max ) || 4;

	function readIds() {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + COOKIE_NAME + '=([^;]*)' ) );
		if ( ! match ) {
			return [];
		}
		try {
			var ids = JSON.parse( decodeURIComponent( match[1] ) );
			return Array.isArray( ids ) ? ids : [];
		} catch ( e ) {
			return [];
		}
	}

	function writeIds( ids ) {
		document.cookie = COOKIE_NAME + '=' + encodeURIComponent( JSON.stringify( ids ) ) + '; path=/; max-age=' + ( 60 * 60 * 24 * 7 );
	}

	function syncButtons() {
		var ids = readIds();
		document.querySelectorAll( '.lis-listing-compare-btn' ).forEach( function ( btn ) {
			var id = parseInt( btn.getAttribute( 'data-id' ), 10 );
			if ( ids.indexOf( id ) !== -1 ) {
				btn.classList.add( 'is-comparing' );
				btn.textContent = '✓ Comparing';
			} else {
				btn.classList.remove( 'is-comparing' );
				btn.textContent = '+ Compare';
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		syncButtons();

		document.addEventListener( 'click', function ( e ) {
			var addBtn = e.target.closest( '.lis-listing-compare-btn' );
			if ( addBtn ) {
				e.preventDefault();
				var id = parseInt( addBtn.getAttribute( 'data-id' ), 10 );
				var ids = readIds();
				var idx = ids.indexOf( id );
				if ( idx !== -1 ) {
					ids.splice( idx, 1 );
				} else {
					if ( ids.length >= MAX ) {
						window.alert( 'You can compare up to ' + MAX + ' listings at a time — remove one first.' );
						return;
					}
					ids.push( id );
				}
				writeIds( ids );
				syncButtons();
				return;
			}

			var removeBtn = e.target.closest( '.lis-listing-compare-remove' );
			if ( removeBtn ) {
				e.preventDefault();
				var removeId = parseInt( removeBtn.getAttribute( 'data-id' ), 10 );
				var remaining = readIds().filter( function ( existing ) { return existing !== removeId; } );
				writeIds( remaining );
				window.location.reload();
			}
		} );
	} );
}());
