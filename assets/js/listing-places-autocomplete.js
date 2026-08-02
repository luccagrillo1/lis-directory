/**
 * Google Places autocomplete on the Business Name step, [lis_listing_submit]
 * and [lis_listing_edit]. Picking a real business auto-fills Address,
 * Phone, Website, and per-day Business Hours from Google's Place Details.
 * Purely additive — anyone who ignores the search box and just types
 * their own values everywhere gets exactly the old behavior.
 *
 * Uses the new `PlaceAutocompleteElement` (google.maps.importLibrary),
 * NOT the older `google.maps.places.Autocomplete` class — Google retired
 * that class for any Cloud project created after March 2025 ("not
 * available to new customers"), confirmed live via a real console error
 * on this exact project. `PlaceAutocompleteElement` is a self-contained
 * web component with its own input UI, so it's inserted as a separate
 * "Search for your business" field above Business Name rather than
 * bound onto the existing plain <input> — the new element renders its
 * own shadow-DOM input, it can't attach to one that already exists.
 */
window.lisDirectoryInitPlacesAutocomplete = window.lisDirectoryInitPlacesAutocomplete || function () {
	var GOOGLE_DAY_TO_NAME = [ 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ];

	function pad2( n ) {
		return String( n ).padStart( 2, '0' );
	}

	function fillHours( form, periods ) {
		if ( ! periods || ! periods.length ) {
			return;
		}
		GOOGLE_DAY_TO_NAME.forEach( function ( day ) {
			var openEl = form.querySelector( '#lis_listing_hours_' + day + '_open' );
			var closeEl = form.querySelector( '#lis_listing_hours_' + day + '_close' );
			if ( openEl ) {
				openEl.value = '';
			}
			if ( closeEl ) {
				closeEl.value = '';
			}
		} );
		periods.forEach( function ( period ) {
			if ( ! period.open || undefined === period.open.day ) {
				return;
			}
			var day = GOOGLE_DAY_TO_NAME[ period.open.day ];
			var openEl = form.querySelector( '#lis_listing_hours_' + day + '_open' );
			var closeEl = form.querySelector( '#lis_listing_hours_' + day + '_close' );
			if ( openEl && period.open.hour !== undefined ) {
				openEl.value = pad2( period.open.hour ) + ':' + pad2( period.open.minute || 0 );
			}
			// Overnight hours (close.day !== open.day) aren't handled specially -
			// the close time still lands on the open day's row, which is what
			// this form's one-shift-per-day model can represent anyway.
			if ( closeEl && period.close && period.close.hour !== undefined ) {
				closeEl.value = pad2( period.close.hour ) + ':' + pad2( period.close.minute || 0 );
			}
		} );
	}

	document.querySelectorAll( '#lis_listing_business_name' ).forEach( function ( nameInput ) {
		if ( nameInput.dataset.placesBound ) {
			return;
		}
		nameInput.dataset.placesBound = '1';

		var form = nameInput.closest( 'form' );
		if ( ! form ) {
			return;
		}

		var wrap = document.createElement( 'p' );
		wrap.className = 'lis-listing-places-search';

		var label = document.createElement( 'label' );
		label.textContent = 'Search for your business (optional)';

		var searchHolder = document.createElement( 'div' );
		searchHolder.className = 'lis-listing-places-search-holder';

		var hint = document.createElement( 'small' );
		hint.textContent = 'Find it on Google and we’ll fill in the address, phone, website, and hours for you.';

		wrap.appendChild( label );
		wrap.appendChild( searchHolder );
		wrap.appendChild( hint );
		nameInput.closest( 'p' ).insertAdjacentElement( 'beforebegin', wrap );

		google.maps.importLibrary( 'places' ).then( function ( places ) {
			var autocompleteEl = new places.PlaceAutocompleteElement();
			searchHolder.appendChild( autocompleteEl );

			autocompleteEl.addEventListener( 'gmp-select', function ( event ) {
				var prediction = event.placePrediction;
				if ( ! prediction ) {
					return;
				}
				var place = prediction.toPlace();
				place.fetchFields( {
					fields: [ 'displayName', 'formattedAddress', 'internationalPhoneNumber', 'nationalPhoneNumber', 'websiteURI', 'regularOpeningHours' ],
				} ).then( function () {
					if ( place.displayName ) {
						nameInput.value = place.displayName;
					}

					var addressEl = form.querySelector( '#lis_listing_address' );
					if ( addressEl && place.formattedAddress ) {
						addressEl.value = place.formattedAddress;
					}

					var phoneEl = form.querySelector( '#lis_listing_phone' );
					if ( phoneEl ) {
						phoneEl.value = place.nationalPhoneNumber || place.internationalPhoneNumber || phoneEl.value;
					}

					var websiteEl = form.querySelector( '#lis_listing_website' );
					if ( websiteEl && place.websiteURI ) {
						websiteEl.value = place.websiteURI;
					}

					if ( place.regularOpeningHours && place.regularOpeningHours.periods ) {
						fillHours( form, place.regularOpeningHours.periods );
					}
				} );
			} );
		} );
	} );
};
