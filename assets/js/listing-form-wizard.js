/**
 * Real one-question-at-a-time onboarding flow for [lis_listing_submit] —
 * Lucca's ask: progress bar, small section label, one big bold question per
 * panel, clean input below it, an elegant transition between panels, and a
 * review screen before the real submit. Deliberately NOT a real multi-page
 * wizard with server-side partial saves — every field still lives in the
 * same single <form> and posts in one request exactly as before. This only
 * changes what's visible and in what order, driven entirely client-side.
 *
 * [lis_listing_edit] used to share this same script, but not anymore —
 * editing an existing listing is a find-and-fix-one-field job, not an
 * onboarding flow, so it now renders every `.lis-listing-panel` flat and
 * visible at once instead (see the .lis-listing-edit-flat CSS in
 * listings.css, and the enqueue note in listings-edit.php). This file only
 * runs against `[lis_listing_submit]`'s own form now.
 *
 * Panels are read from the markup itself: each `.lis-listing-panel` in
 * listings-submission.php carries `data-panel-section` (small label),
 * `data-panel-question` (the big question), and an optional
 * `data-panel-hint`. That keeps the actual copy in the PHP template where
 * content belongs, not hardcoded here.
 *
 * The first panel is a fork (`.lis-listing-fork-panel`) — "Find it on
 * Google" vs "Enter manually". Panels marked `data-google-fillable="true"`
 * (Address, Phone, Website, Business Hours) are skipped from the
 * walkthrough when the Google path is chosen, since
 * listing-places-autocomplete.js fills them directly. They still get shown
 * on the review screen since Google did fill real values into them — just
 * not as their own step.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.lis-listing-submit-form' ).forEach( function ( form ) {
		var panels = Array.prototype.slice.call( form.querySelectorAll( ':scope > .lis-listing-panel' ) );
		if ( ! panels.length ) {
			return;
		}

		var forkPanel = panels.find( function ( panel ) {
			return panel.classList.contains( 'lis-listing-fork-panel' );
		} ) || null;

		var submitButton = form.querySelector( '.lis-listing-submit-button' );
		var submitWrap = submitButton ? ( submitButton.closest( 'p' ) || submitButton ) : null;
		if ( submitWrap ) {
			submitWrap.style.display = 'none'; // Only shown inside the review panel.
		}

		// "Save & finish later" (listings-submission.php / listings-edit.php)
		// sits before the wizard in the markup as a fixed top bar. Instead,
		// re-parent this SAME element into whichever panel's section-label row
		// is currently active (see goToPanel below) so it visually mirrors the
		// section label — same row, right-aligned — without needing N copies
		// of a real submit button.
		var draftBar = form.querySelector( ':scope > .lis-listing-draft-bar' );

		var wizard = document.createElement( 'div' );
		wizard.className = 'lis-listing-wizard';

		var progress = document.createElement( 'div' );
		progress.className = 'lis-listing-wizard-progress';
		var progressBar = document.createElement( 'div' );
		progressBar.className = 'lis-listing-wizard-progress-bar';
		progress.appendChild( progressBar );

		var stage = document.createElement( 'div' );
		stage.className = 'lis-listing-wizard-stage';

		form.insertBefore( wizard, panels[0] );
		wizard.appendChild( progress );
		wizard.appendChild( stage );
		panels.forEach( function ( panel ) {
			stage.appendChild( panel );
		} );

		// Only panels actually reachable right now, given the fork choice
		// (if any) — the fork panel itself is always included.
		function activePanels() {
			return panels.filter( function ( panel ) {
				if ( panel === forkPanel ) {
					return true;
				}
				return ! ( 'true' === panel.dataset.googleFillable && 'google' === form.dataset.entryMode );
			} );
		}

		function activeSequence() {
			return activePanels().concat( [ reviewPanel ] );
		}

		// Decorate each real panel with its section label, big question,
		// optional hint, and Back/Continue controls. The fork panel gets
		// the label/question/hint too, but its own choice buttons drive
		// navigation instead of a Continue button.
		panels.forEach( function ( panel ) {
			panel.classList.add( 'lis-listing-wizard-panel' );

			var section = document.createElement( 'div' );
			section.className = 'lis-listing-wizard-panel-section';
			section.textContent = panel.dataset.panelSection || '';

			// Same row as the section label, right-aligned — draftBar itself
			// gets moved in here (see goToPanel) rather than built fresh per
			// panel, since it's one real submit button, not N copies of one.
			var sectionRow = document.createElement( 'div' );
			sectionRow.className = 'lis-listing-wizard-panel-section-row';
			sectionRow.appendChild( section );
			panel.sectionRow = sectionRow; // stashed for goToPanel to find.

			var question = document.createElement( 'h2' );
			question.className = 'lis-listing-wizard-panel-question';
			question.textContent = panel.dataset.panelQuestion || '';

			// The section label (e.g. "GET STARTED") stays pinned at the
			// panel's top, same as the progress bar above it. Everything
			// else - the question, hint, the real field markup, and the
			// controls - lives in `body`, which vertically centers itself
			// within whatever space is left below the label.
			var body = document.createElement( 'div' );
			body.className = 'lis-listing-wizard-panel-body';
			while ( panel.firstChild ) {
				body.appendChild( panel.firstChild );
			}
			body.insertBefore( question, body.firstChild );
			panel.appendChild( sectionRow );
			panel.appendChild( body );

			if ( panel.dataset.panelHint ) {
				var hint = document.createElement( 'p' );
				hint.className = 'lis-listing-wizard-panel-hint';
				hint.textContent = panel.dataset.panelHint;
				question.insertAdjacentElement( 'afterend', hint );
			}

			if ( panel === forkPanel ) {
				panel.querySelectorAll( '.lis-listing-fork-choice' ).forEach( function ( choiceButton ) {
					choiceButton.addEventListener( 'click', function ( event ) {
						if ( event.target.closest( '.lis-listing-fork-choice-info' ) ) {
							return; // Clicking the "?" info bubble shouldn't also pick this path.
						}
						form.dataset.entryMode = choiceButton.dataset.forkChoice;
						form.dispatchEvent( new CustomEvent( 'lis-directory:entry-mode', {
							bubbles: true,
							detail: { mode: choiceButton.dataset.forkChoice },
						} ) );
						var seq = activeSequence();
						goToPanel( seq[ seq.indexOf( panel ) + 1 ] );
					} );
				} );
				return;
			}

			var controls = document.createElement( 'div' );
			controls.className = 'lis-listing-wizard-controls';

			if ( panel !== panels[0] ) {
				var back = document.createElement( 'button' );
				back.type = 'button';
				back.className = 'lis-listing-wizard-back';
				back.textContent = 'Back';
				back.addEventListener( 'click', function () {
					var seq = activeSequence();
					goToPanel( seq[ seq.indexOf( panel ) - 1 ] );
				} );
				controls.appendChild( back );
			}

			var next = document.createElement( 'button' );
			next.type = 'button';
			next.className = 'lis-listing-wizard-next';
			next.textContent = 'Continue';
			next.addEventListener( 'click', function () {
				var invalid = panel.querySelector( ':invalid' );
				if ( invalid ) {
					invalid.reportValidity();
					return;
				}
				var seq = activeSequence();
				goToPanel( seq[ seq.indexOf( panel ) + 1 ] );
			} );
			controls.appendChild( next );

			body.appendChild( controls );
		} );

		// Review panel: not part of `panels` (those map 1:1 to real form
		// fields), appended after as the final stop before the real submit.
		var reviewPanel = document.createElement( 'div' );
		reviewPanel.className = 'lis-listing-wizard-panel lis-listing-wizard-review';

		var reviewSection = document.createElement( 'div' );
		reviewSection.className = 'lis-listing-wizard-panel-section';
		reviewSection.textContent = 'Almost done';

		var reviewSectionRow = document.createElement( 'div' );
		reviewSectionRow.className = 'lis-listing-wizard-panel-section-row';
		reviewSectionRow.appendChild( reviewSection );
		reviewPanel.sectionRow = reviewSectionRow;

		var reviewQuestion = document.createElement( 'h2' );
		reviewQuestion.className = 'lis-listing-wizard-panel-question';
		reviewQuestion.textContent = 'Review before you submit';

		var reviewList = document.createElement( 'dl' );
		reviewList.className = 'lis-listing-wizard-review-list';

		var reviewControls = document.createElement( 'div' );
		reviewControls.className = 'lis-listing-wizard-controls';

		var reviewBack = document.createElement( 'button' );
		reviewBack.type = 'button';
		reviewBack.className = 'lis-listing-wizard-back';
		reviewBack.textContent = 'Back';
		reviewBack.addEventListener( 'click', function () {
			var active = activePanels();
			goToPanel( active[ active.length - 1 ] );
		} );
		reviewControls.appendChild( reviewBack );
		if ( submitWrap ) {
			submitWrap.style.display = ''; // Was hidden at init; only ever shown here, inside the review panel.
			reviewControls.appendChild( submitWrap );
		}

		var reviewBody = document.createElement( 'div' );
		reviewBody.className = 'lis-listing-wizard-panel-body';
		reviewBody.appendChild( reviewQuestion );
		reviewBody.appendChild( reviewList );
		reviewBody.appendChild( reviewControls );

		reviewPanel.appendChild( reviewSectionRow );
		reviewPanel.appendChild( reviewBody );
		stage.appendChild( reviewPanel );

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		/**
		 * Best-effort plain-text/HTML summary of whatever's in a panel, for
		 * the review screen. Not exhaustive for every possible field type
		 * this form could ever grow, but covers everything it has today.
		 */
		function readPanelValue( panel ) {
			var editorTextarea = panel.querySelector( 'textarea.wp-editor-area' );
			if ( editorTextarea ) {
				if ( window.tinymce ) {
					var ed = window.tinymce.get( editorTextarea.id );
					if ( ed && ! ed.isHidden() ) {
						ed.save();
					}
				}
				var html = editorTextarea.value.trim();
				return html || '';
			}

			var checkboxes = Array.prototype.slice.call( panel.querySelectorAll( 'input[type="checkbox"]:checked' ) );
			if ( panel.querySelectorAll( 'input[type="checkbox"]' ).length ) {
				if ( ! checkboxes.length ) {
					return '';
				}
				return escapeHtml( checkboxes.map( function ( cb ) {
					var label = cb.closest( 'label' );
					return label ? label.textContent.trim() : cb.value;
				} ).join( ', ' ) );
			}

			var timeInputs = panel.querySelectorAll( 'input[type="time"]' );
			if ( timeInputs.length ) {
				var rows = Array.prototype.slice.call( panel.querySelectorAll( 'tr' ) ).map( function ( tr ) {
					var label = tr.querySelector( 'th label' );
					var inputs = tr.querySelectorAll( 'input[type="time"]' );
					if ( ! label || ! inputs[0] || ! inputs[0].value ) {
						return '';
					}
					return escapeHtml( label.textContent ) + ': ' + escapeHtml( inputs[0].value ) + '–' + escapeHtml( inputs[1] ? inputs[1].value : '' );
				} ).filter( Boolean );
				return rows.join( '<br>' );
			}

			var fileInput = panel.querySelector( 'input[type="file"]' );
			if ( fileInput ) {
				var previewImgs = panel.querySelectorAll( '.lis-listing-photos-preview img, .lis-listing-edit-gallery img' );
				return previewImgs.length ? previewImgs.length + ' photo' + ( 1 === previewImgs.length ? '' : 's' ) : '';
			}

			var select = panel.querySelector( 'select' );
			if ( select ) {
				return select.selectedOptions[0] && select.value ? escapeHtml( select.selectedOptions[0].textContent ) : '';
			}

			var textarea = panel.querySelector( 'textarea' );
			if ( textarea ) {
				var text = textarea.value.trim();
				return text ? escapeHtml( text ).replace( /\n/g, '<br>' ) : '';
			}

			var multipleInputs = Array.prototype.slice.call( panel.querySelectorAll( 'input[type="url"], input[type="text"], input[type="email"]' ) );
			if ( multipleInputs.length > 1 ) {
				return multipleInputs.map( function ( input ) {
					return input.value ? escapeHtml( input.value ) : '';
				} ).filter( Boolean ).join( '<br>' );
			}

			var singleInput = panel.querySelector( 'input[type="url"], input[type="text"], input[type="email"]' );
			return singleInput && singleInput.value ? escapeHtml( singleInput.value ) : '';
		}

		function renderReview() {
			reviewList.innerHTML = '';
			panels.forEach( function ( panel ) {
				if ( panel === forkPanel ) {
					return;
				}
				var value = readPanelValue( panel );
				if ( ! value ) {
					return;
				}
				var dt = document.createElement( 'dt' );
				dt.textContent = panel.dataset.panelQuestion || '';
				var dd = document.createElement( 'dd' );
				dd.innerHTML = value; // phpcs:ignore -- built entirely from escapeHtml()'d text above, not raw user HTML except the already-sanitized TinyMCE field.
				reviewList.appendChild( dt );
				reviewList.appendChild( dd );
			} );
		}

		var currentPanel = panels[0];

		function goToPanel( targetPanel ) {
			if ( ! targetPanel || targetPanel === currentPanel ) {
				return;
			}
			var seq = activeSequence();
			var index = seq.indexOf( targetPanel );
			if ( -1 === index ) {
				return;
			}
			if ( targetPanel === reviewPanel ) {
				renderReview();
			}

			var outgoing = currentPanel;
			var incoming = targetPanel;

			outgoing.classList.remove( 'is-active' );
			window.setTimeout( function () {
				outgoing.style.display = 'none';
				incoming.style.display = '';
				void incoming.offsetWidth; // eslint-disable-line no-void -- force reflow so the transition actually plays.
				incoming.classList.add( 'is-active' );

				var incomingNext = incoming.querySelector( '.lis-listing-wizard-controls > .lis-listing-wizard-next' );
				if ( incomingNext ) {
					var incomingSeq = activeSequence();
					var incomingIndex = incomingSeq.indexOf( incoming );
					incomingNext.textContent = incomingSeq[ incomingIndex + 1 ] === reviewPanel ? 'Review' : 'Continue';
				}
			}, 200 );

			currentPanel = targetPanel;
			progressBar.style.width = ( ( index + 1 ) / seq.length * 100 ) + '%';
			if ( draftBar && targetPanel.sectionRow ) {
				targetPanel.sectionRow.appendChild( draftBar );
			}
			wizard.scrollIntoView( { block: 'start', behavior: 'smooth' } );
		}

		panels.concat( [ reviewPanel ] ).forEach( function ( panel ) {
			panel.style.display = panel === panels[0] ? '' : 'none';
		} );
		panels[0].classList.add( 'is-active' );
		progressBar.style.width = ( 1 / activeSequence().length * 100 ) + '%';
		if ( draftBar && panels[0].sectionRow ) {
			panels[0].sectionRow.appendChild( draftBar );
		}

		// Final backstop: whichever panel you're on when you hit the real
		// submit button, make sure nothing required got left invalid.
		form.addEventListener( 'submit', function ( e ) {
			panels.forEach( function ( panel ) {
				panel.style.display = '';
			} );
			if ( form.checkValidity() ) {
				return;
			}
			e.preventDefault();
			var invalid = form.querySelector( ':invalid' );
			if ( invalid ) {
				var target = invalid.closest( '.lis-listing-panel' );
				if ( target ) {
					goToPanel( target );
				}
				window.setTimeout( function () {
					invalid.reportValidity();
				}, 50 );
			}
		} );
	} );
} );
