/**
 * Progressive enhancement for the Features field in [lis_listing_search]:
 * the server always renders a plain checkbox grid (works with JS off, and
 * is the actual thing submitted with the form) - this hides that grid and
 * replaces it with a type-to-filter picker that toggles the same
 * checkboxes underneath, so nothing about form submission changes.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '[data-feature-field]' ).forEach( function ( field ) {
		var grid = field.querySelector( '[data-feature-checkboxes]' );
		if ( ! grid ) {
			return;
		}
		var checkboxes = Array.prototype.slice.call( grid.querySelectorAll( 'input[type="checkbox"]' ) );
		if ( ! checkboxes.length ) {
			return;
		}

		grid.hidden = true;

		var wrap = document.createElement( 'div' );
		wrap.className = 'lis-listing-feature-picker';

		var chips = document.createElement( 'div' );
		chips.className = 'lis-listing-feature-chips';

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'lis-listing-feature-input';
		input.placeholder = 'Type to search features…';
		input.setAttribute( 'autocomplete', 'off' );

		var suggestions = document.createElement( 'div' );
		suggestions.className = 'lis-listing-feature-suggestions';
		suggestions.hidden = true;

		function renderChips() {
			chips.innerHTML = '';
			checkboxes.filter( function ( cb ) {
				return cb.checked;
			} ).forEach( function ( cb ) {
				var chip = document.createElement( 'span' );
				chip.className = 'lis-listing-feature-chip';
				chip.appendChild( document.createTextNode( cb.dataset.featureName ) );

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'lis-listing-feature-chip-remove';
				remove.setAttribute( 'aria-label', 'Remove ' + cb.dataset.featureName );
				remove.textContent = '×';
				remove.addEventListener( 'click', function () {
					cb.checked = false;
					renderChips();
				} );

				chip.appendChild( remove );
				chips.appendChild( chip );
			} );
		}

		function renderSuggestions() {
			var q = input.value.trim().toLowerCase();
			suggestions.innerHTML = '';

			var matches = checkboxes.filter( function ( cb ) {
				return ! cb.checked && ( ! q || cb.dataset.featureName.toLowerCase().indexOf( q ) !== -1 );
			} );

			if ( ! matches.length ) {
				suggestions.hidden = true;
				return;
			}

			matches.forEach( function ( cb ) {
				var opt = document.createElement( 'button' );
				opt.type = 'button';
				opt.className = 'lis-listing-feature-option';
				opt.textContent = cb.dataset.featureName;
				opt.addEventListener( 'click', function () {
					cb.checked = true;
					input.value = '';
					renderChips();
					renderSuggestions();
					input.focus();
				} );
				suggestions.appendChild( opt );
			} );

			suggestions.hidden = false;
		}

		input.addEventListener( 'focus', renderSuggestions );
		input.addEventListener( 'input', renderSuggestions );
		document.addEventListener( 'click', function ( e ) {
			if ( ! wrap.contains( e.target ) ) {
				suggestions.hidden = true;
			}
		} );

		wrap.appendChild( chips );
		wrap.appendChild( input );
		wrap.appendChild( suggestions );
		grid.insertAdjacentElement( 'afterend', wrap );

		renderChips();
	} );
} );
