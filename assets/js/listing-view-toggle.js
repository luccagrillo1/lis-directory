/**
 * Grid/List/Map view toggle for the results grid rendered by
 * templates/archive-listing.php. Grid vs List is a pure client-side
 * display preference (same query results, different layout) — a CSS
 * class + localStorage, deliberately not a URL param, since it doesn't
 * change what's being shown, only how. Map view lazy-loads the Google
 * Maps JavaScript API (only if a listing's address triggers it — no
 * point loading it for visitors who never click Map) and geocodes each
 * currently-visible card's address client-side, same approach as the
 * single-listing map (templates/single-listing.php).
 */
( function () {
	var STORAGE_KEY = 'lis_listing_view';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toolbar = document.querySelector( '.lis-listing-toolbar' );
		var results = document.querySelector( '[data-lis-listing-results]' );
		var mapView = document.querySelector( '[data-lis-listing-map-view]' );
		if ( ! toolbar || ! results ) {
			return;
		}

		var buttons = Array.prototype.slice.call( toolbar.querySelectorAll( '.lis-listing-view-btn' ) );
		var mapLoaded = false;
		var mapLoading = false;

		function setView( view ) {
			if ( 'map' === view && ! mapView ) {
				view = 'grid';
			}

			results.hidden = 'map' === view;
			results.classList.toggle( 'lis-listing-grid--list', 'list' === view );
			if ( mapView ) {
				mapView.hidden = 'map' !== view;
			}

			buttons.forEach( function ( btn ) {
				btn.classList.toggle( 'is-active', btn.dataset.view === view );
			} );

			try {
				localStorage.setItem( STORAGE_KEY, view );
			} catch ( e ) {
				// Private browsing / storage disabled - view choice just won't persist. Not fatal.
			}

			if ( 'map' === view ) {
				loadMapView();
			}
		}

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				setView( btn.dataset.view );
			} );
		} );

		var saved = null;
		try {
			saved = localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			// Same as above - ignore, default to grid.
		}
		if ( saved && ( 'list' === saved || ( 'map' === saved && mapView ) ) ) {
			setView( saved );
		}

		function loadMapView() {
			if ( mapLoaded || mapLoading || ! mapView || ! window.lisDirectoryMapsApiKey ) {
				return;
			}
			mapLoading = true;

			window.lisDirectoryInitArchiveMap = function () {
				var canvas = mapView.querySelector( '.lis-listing-map-view-canvas' );
				var cards = Array.prototype.slice.call( results.querySelectorAll( '[data-address]' ) );
				if ( ! canvas || ! cards.length ) {
					mapLoaded = true;
					return;
				}

				var map = new google.maps.Map( canvas, { zoom: 12, center: { lat: 0, lng: 0 } } );
				var bounds = new google.maps.LatLngBounds();
				var geocoder = new google.maps.Geocoder();
				var pending = cards.length;

				cards.forEach( function ( card ) {
					geocoder.geocode( { address: card.dataset.address }, function ( results, status ) {
						pending--;
						if ( 'OK' === status && results[0] ) {
							var position = results[0].geometry.location;
							var marker = new google.maps.Marker( { map: map, position: position, title: card.dataset.title || '' } );
							bounds.extend( position );
							marker.addListener( 'click', function () {
								window.location.href = card.href;
							} );
						}
						if ( 0 === pending && ! bounds.isEmpty() ) {
							map.fitBounds( bounds );
						}
					} );
				} );

				mapLoaded = true;
			};

			var script = document.createElement( 'script' );
			script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( window.lisDirectoryMapsApiKey ) + '&callback=lisDirectoryInitArchiveMap&loading=async';
			script.async = true;
			document.head.appendChild( script );
		}
	} );
}() );
