/**
 * Google Places autocomplete on the Business Name field, [lis_listing_submit]
 * and [lis_listing_edit]. Picking a real business from the dropdown
 * auto-fills Address, Phone, Website, and per-day Business Hours from
 * Google's own data — saves retyping everything by hand. Purely additive:
 * if a visitor just types a name and never picks a suggestion (or Places
 * has no data for it), every field behaves exactly as before, nothing
 * required depends on this running.
 *
 * Needs the Maps JavaScript API loaded with `libraries=places` — see the
 * loader in listings-submission.php / listings-edit.php, gated on a Maps
 * API key being configured (Settings > LIS Directory Settings), same key
 * used for the single-listing and archive maps.
 */
window.lisDirectoryInitPlacesAutocomplete = window.lisDirectoryInitPlacesAutocomplete || function () {
	if ( ! window.google || ! window.google.maps || ! window.google.maps.places ) {
		return;
	}

	var GOOGLE_DAY_TO_NAME = [ 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ];

	function formatGoogleTime( t ) {
		// Google returns "0900" - our <input type="time"> wants "09:00".
		return t.length === 4 ? t.slice( 0, 2 ) + ':' + t.slice( 2, 4 ) : '';
	}

	function fillHours( form, periods ) {
		if ( ! periods || ! periods.length ) {
			return;
		}
		// Clear existing values first - selecting a new place should replace
		// whatever hours were there before, not merge with them.
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
			if ( openEl && period.open.time ) {
				openEl.value = formatGoogleTime( period.open.time );
			}
			// A business open past midnight has close.day different from
			// open.day - not handled specially here (rare for the kind of
			// local listings this directory covers), the close TIME still
			// gets set against the OPEN day's row, which is what the form's
			// one-shift-per-day model can represent anyway.
			if ( closeEl && period.close && period.close.time ) {
				closeEl.value = formatGoogleTime( period.close.time );
			}
		} );
	}

	document.querySelectorAll( '#lis_listing_business_name' ).forEach( function ( input ) {
		if ( input.dataset.placesBound ) {
			return;
		}
		input.dataset.placesBound = '1';

		var form = input.closest( 'form' );
		if ( ! form ) {
			return;
		}

		var autocomplete = new google.maps.places.Autocomplete( input, {
			types: [ 'establishment' ],
			fields: [ 'name', 'formatted_address', 'formatted_phone_number', 'international_phone_number', 'website', 'opening_hours' ],
		} );

		autocomplete.addListener( 'place_changed', function () {
			var place = autocomplete.getPlace();
			if ( ! place || ! place.name ) {
				return; // User pressed Enter on free text without picking a real suggestion.
			}

			input.value = place.name;

			var addressEl = form.querySelector( '#lis_listing_address' );
			if ( addressEl && place.formatted_address ) {
				addressEl.value = place.formatted_address;
			}

			var phoneEl = form.querySelector( '#lis_listing_phone' );
			if ( phoneEl ) {
				phoneEl.value = place.formatted_phone_number || place.international_phone_number || phoneEl.value;
			}

			var websiteEl = form.querySelector( '#lis_listing_website' );
			if ( websiteEl && place.website ) {
				websiteEl.value = place.website;
			}

			if ( place.opening_hours && place.opening_hours.periods ) {
				fillHours( form, place.opening_hours.periods );
			}
		} );
	} );
};
