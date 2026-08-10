/**
 * Google Places autocomplete on the Business Name step of [lis_listing_submit]
 * and [lis_listing_edit] — ONE text box, not two.
 *
 * Uses the new programmatic Places API (`AutocompleteSuggestion.fetch-
 * AutocompleteSuggestions` + `AutocompleteSessionToken`), NOT the self-
 * contained `PlaceAutocompleteElement` web component. The element renders its
 * own shadow-DOM input, which forced a second search box above the real
 * business-name field; the data API lets us hang a custom suggestions dropdown
 * directly off the existing `#lis_listing_business_name` input instead, so
 * there's a single box: type your name, pick a Google match to auto-fill
 * address/phone/website/hours, or just keep typing to enter everything
 * yourself. (The old class is also "not available to new customers" for Cloud
 * projects created after March 2025 — confirmed live on this project.)
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

		google.maps.importLibrary( 'places' ).then( function ( places ) {
			// Graceful no-op if the programmatic API isn't available: the input
			// still works as a plain business-name field.
			if ( ! places || ! places.AutocompleteSuggestion || ! places.AutocompleteSessionToken ) {
				return;
			}

			nameInput.setAttribute( 'autocomplete', 'off' );

			// Wrap the input so the dropdown can be absolutely positioned under it.
			var wrap = document.createElement( 'div' );
			wrap.className = 'lis-listing-place-ac';
			nameInput.parentNode.insertBefore( wrap, nameInput );
			wrap.appendChild( nameInput );

			var confirm = document.createElement( 'small' );
			confirm.className = 'lis-listing-place-confirm';
			confirm.hidden = true;
			wrap.appendChild( confirm );

			var menu = document.createElement( 'ul' );
			menu.className = 'lis-listing-place-ac-menu';
			menu.setAttribute( 'role', 'listbox' );
			menu.hidden = true;
			wrap.appendChild( menu );

			var token = new places.AutocompleteSessionToken();
			var items = [];
			var activeIndex = -1;
			var debounceTimer;

			function closeMenu() {
				menu.hidden = true;
				menu.innerHTML = '';
				items = [];
				activeIndex = -1;
			}

			function highlight( i ) {
				activeIndex = i;
				Array.prototype.forEach.call( menu.children, function ( li, idx ) {
					li.classList.toggle( 'is-active', idx === i );
				} );
			}

			function select( i ) {
				var prediction = items[ i ] && items[ i ].placePrediction;
				if ( ! prediction ) {
					return;
				}
				closeMenu();
				var place = prediction.toPlace();
				place.fetchFields( {
					fields: [ 'displayName', 'formattedAddress', 'internationalPhoneNumber', 'nationalPhoneNumber', 'websiteURI', 'regularOpeningHours' ],
				} ).then( function () {
					var filled = [];

					if ( place.displayName ) {
						nameInput.value = place.displayName;
					}

					var addressEl = form.querySelector( '#lis_listing_address' );
					if ( addressEl && place.formattedAddress ) {
						addressEl.value = place.formattedAddress;
						filled.push( 'address' );
					}

					var phoneEl = form.querySelector( '#lis_listing_phone' );
					if ( phoneEl && ( place.nationalPhoneNumber || place.internationalPhoneNumber ) ) {
						phoneEl.value = place.nationalPhoneNumber || place.internationalPhoneNumber;
						filled.push( 'phone' );
					}

					var websiteEl = form.querySelector( '#lis_listing_website' );
					if ( websiteEl && place.websiteURI ) {
						websiteEl.value = place.websiteURI;
						filled.push( 'website' );
					}

					if ( place.regularOpeningHours && place.regularOpeningHours.periods ) {
						fillHours( form, place.regularOpeningHours.periods );
						filled.push( 'hours' );
					}

					confirm.hidden = false;
					confirm.textContent = filled.length
						? '✓ Pulled from Google: ' + filled.join( ', ' ) + '. Everything’s editable as you go.'
						: '✓ Found on Google. Fill in the rest below — everything’s editable.';

					// A selection ends the billing session; start a fresh token.
					token = new places.AutocompleteSessionToken();
				} );
			}

			function render() {
				menu.innerHTML = '';
				if ( ! items.length ) {
					closeMenu();
					return;
				}
				items.forEach( function ( suggestion, i ) {
					var prediction = suggestion.placePrediction;
					var li = document.createElement( 'li' );
					li.className = 'lis-listing-place-ac-item';
					li.setAttribute( 'role', 'option' );

					var mainText = prediction.mainText ? prediction.mainText.text : ( prediction.text ? prediction.text.text : '' );
					var subText = prediction.secondaryText ? prediction.secondaryText.text : '';

					var main = document.createElement( 'span' );
					main.className = 'lis-listing-place-ac-main';
					main.textContent = mainText;
					li.appendChild( main );

					if ( subText ) {
						var sub = document.createElement( 'span' );
						sub.className = 'lis-listing-place-ac-sub';
						sub.textContent = subText;
						li.appendChild( sub );
					}

					li.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault(); // keep focus so the selection sticks
						select( i );
					} );
					menu.appendChild( li );
				} );
				activeIndex = -1;
				menu.hidden = false;
			}

			function fetchSuggestions( input ) {
				places.AutocompleteSuggestion.fetchAutocompleteSuggestions( {
					input: input,
					sessionToken: token,
				} ).then( function ( response ) {
					items = ( response && response.suggestions ? response.suggestions : [] ).filter( function ( s ) {
						return s.placePrediction;
					} );
					render();
				} ).catch( function () {
					closeMenu();
				} );
			}

			nameInput.addEventListener( 'input', function () {
				confirm.hidden = true;
				var value = nameInput.value.trim();
				window.clearTimeout( debounceTimer );
				if ( value.length < 3 ) {
					closeMenu();
					return;
				}
				debounceTimer = window.setTimeout( function () {
					fetchSuggestions( value );
				}, 220 );
			} );

			nameInput.addEventListener( 'keydown', function ( e ) {
				if ( menu.hidden || ! items.length ) {
					return;
				}
				if ( 'ArrowDown' === e.key ) {
					e.preventDefault();
					highlight( ( activeIndex + 1 ) % items.length );
				} else if ( 'ArrowUp' === e.key ) {
					e.preventDefault();
					highlight( ( activeIndex - 1 + items.length ) % items.length );
				} else if ( 'Enter' === e.key && activeIndex >= 0 ) {
					e.preventDefault();
					select( activeIndex );
				} else if ( 'Escape' === e.key ) {
					closeMenu();
				}
			} );

			document.addEventListener( 'click', function ( e ) {
				if ( ! wrap.contains( e.target ) ) {
					closeMenu();
				}
			} );
		} );
	} );
};
