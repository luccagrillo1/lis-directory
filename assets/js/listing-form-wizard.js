/**
 * Multi-step wizard presentation over [lis_listing_submit] and
 * [lis_listing_edit] — Lucca asked for something closer to Directorist's
 * own step-sidebar Add Listing flow. Deliberately NOT a real multi-page
 * wizard with server-side partial saves: every field still lives in the
 * same single <form> and posts in one request exactly as before — this
 * only changes which .lis-listing-submit-section is visible at a time,
 * driven entirely client-side. That keeps the existing save handlers in
 * listings-submission.php / listings-edit.php completely untouched.
 *
 * Each section's own <h3> text becomes its sidebar label, so the step
 * list can't drift out of sync with the actual sections if one is ever
 * added, removed, or renamed.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.lis-listing-submit-form' ).forEach( function ( form ) {
		var sections = Array.prototype.slice.call( form.querySelectorAll( ':scope > .lis-listing-submit-section' ) );
		if ( sections.length < 2 ) {
			return;
		}

		var current = 0;
		var navItems = [];
		var submitButton = form.querySelector( '.lis-listing-submit-button' );
		var submitWrap = submitButton ? submitButton.closest( 'p' ) || submitButton : null;

		var wizard = document.createElement( 'div' );
		wizard.className = 'lis-listing-wizard';

		var progress = document.createElement( 'div' );
		progress.className = 'lis-listing-wizard-progress';
		var progressBar = document.createElement( 'div' );
		progressBar.className = 'lis-listing-wizard-progress-bar';
		progress.appendChild( progressBar );

		var progressLabel = document.createElement( 'p' );
		progressLabel.className = 'lis-listing-wizard-progress-label';

		var body = document.createElement( 'div' );
		body.className = 'lis-listing-wizard-body';

		var nav = document.createElement( 'nav' );
		nav.className = 'lis-listing-wizard-nav';
		nav.setAttribute( 'aria-label', 'Form sections' );

		var stepsWrap = document.createElement( 'div' );
		stepsWrap.className = 'lis-listing-wizard-steps';

		function stepLabel( section, index ) {
			var h3 = section.querySelector( 'h3' );
			if ( ! h3 ) {
				return 'Step ' + ( index + 1 );
			}
			// Drop a trailing "(leave a day blank if closed)"-style aside - the
			// sidebar just needs the short name, the detail stays in the step itself.
			var clone = h3.cloneNode( true );
			var small = clone.querySelector( 'small' );
			if ( small ) {
				small.remove();
			}
			return clone.textContent.trim();
		}

		function goToStep( index ) {
			current = index;
			sections.forEach( function ( section, i ) {
				section.style.display = i === index ? '' : 'none';
			} );
			navItems.forEach( function ( item, i ) {
				var isComplete = i < index;
				item.classList.toggle( 'is-active', i === index );
				item.classList.toggle( 'is-complete', isComplete );
				var numberEl = item.querySelector( '.lis-listing-wizard-nav-number' );
				if ( numberEl ) {
					numberEl.textContent = isComplete ? '✓' : String( i + 1 );
				}
			} );
			progressBar.style.width = ( ( index + 1 ) / sections.length * 100 ) + '%';
			progressLabel.textContent = 'Step ' + ( index + 1 ) + ' of ' + sections.length;
			if ( submitWrap ) {
				submitWrap.style.display = index === sections.length - 1 ? '' : 'none';
			}
			wizard.scrollIntoView( { block: 'start', behavior: 'smooth' } );
		}

		sections.forEach( function ( section, i ) {
			var navItem = document.createElement( 'button' );
			navItem.type = 'button';
			navItem.className = 'lis-listing-wizard-nav-item';
			var numberEl = document.createElement( 'span' );
			numberEl.className = 'lis-listing-wizard-nav-number';
			numberEl.textContent = String( i + 1 );
			var labelEl = document.createElement( 'span' );
			labelEl.textContent = stepLabel( section, i );
			navItem.appendChild( numberEl );
			navItem.appendChild( labelEl );
			navItem.addEventListener( 'click', function () {
				goToStep( i );
			} );
			nav.appendChild( navItem );
			navItems.push( navItem );

			section.classList.add( 'lis-listing-wizard-step' );

			var controls = document.createElement( 'div' );
			controls.className = 'lis-listing-wizard-controls';

			if ( i > 0 ) {
				var back = document.createElement( 'button' );
				back.type = 'button';
				back.className = 'lis-listing-wizard-back';
				back.textContent = 'Back';
				back.addEventListener( 'click', function () {
					goToStep( i - 1 );
				} );
				controls.appendChild( back );
			}

			if ( i < sections.length - 1 ) {
				var next = document.createElement( 'button' );
				next.type = 'button';
				next.className = 'lis-listing-wizard-next';
				next.textContent = 'Continue';
				next.addEventListener( 'click', function () {
					var invalid = section.querySelector( ':invalid' );
					if ( invalid ) {
						invalid.reportValidity();
						return;
					}
					goToStep( i + 1 );
				} );
				controls.appendChild( next );
			}

			if ( controls.childNodes.length ) {
				section.appendChild( controls );
			}
		} );

		form.insertBefore( wizard, sections[0] );
		wizard.appendChild( progress );
		wizard.appendChild( progressLabel );
		wizard.appendChild( body );
		body.appendChild( nav );
		body.appendChild( stepsWrap );
		sections.forEach( function ( section ) {
			stepsWrap.appendChild( section );
		} );

		goToStep( 0 );

		// Final backstop: whichever step you're on when you hit the real
		// submit button, make sure nothing required got left invalid on an
		// earlier step you skipped past via the sidebar.
		form.addEventListener( 'submit', function ( e ) {
			sections.forEach( function ( section ) {
				section.style.display = '';
			} );
			if ( form.checkValidity() ) {
				return;
			}
			e.preventDefault();
			var invalid = form.querySelector( ':invalid' );
			if ( invalid ) {
				var stepEl = invalid.closest( '.lis-listing-wizard-step' );
				var idx = sections.indexOf( stepEl );
				if ( idx > -1 ) {
					goToStep( idx );
				}
				window.setTimeout( function () {
					invalid.reportValidity();
				}, 50 );
			}
		} );
	} );
} );
