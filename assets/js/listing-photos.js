/**
 * Shared by [lis_listing_submit] and [lis_listing_edit] — both use the
 * same #lis_listing_photos file input markup: the native input is
 * visually hidden (see .lis-listing-submit-section input[type="file"] in
 * listings.css), a <label> styled as a dropzone triggers it on click
 * (native <label for>/click-to-open behavior, no JS needed for that
 * part), and this file adds three things the plain input can't do on its
 * own: drag-and-drop onto the dropzone, live thumbnail previews of what's
 * actually selected before upload, and — most importantly — client-side
 * validation so an oversized or wrong-type photo is caught the instant
 * it's picked instead of on the server.
 *
 * That last part matters a lot: this is a single-request wizard, so a
 * server-side photo rejection reloads the page, and a browser can never
 * repopulate a file input — so the whole multi-step form (everything the
 * person already typed) gets thrown away. Rejecting bad files here, before
 * they can ever be POSTed, keeps that from happening.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var MAX_BYTES = 2 * 1024 * 1024; // Mirrors LIS_DIRECTORY_SUBMIT_* server limit.
	var MAX_FILES = 6; // Mirrors LIS_DIRECTORY_SUBMIT_MAX_PHOTOS.
	var OK_TYPE = /^image\/(png|jpeg)$/;

	document.querySelectorAll( '#lis_listing_photos' ).forEach( function ( input ) {
		var dropzone = document.querySelector( 'label.lis-listing-photos-dropzone[for="' + input.id + '"]' );

		// The preview container is authored right after the input, but the
		// HTML parser hoists a block-level <div> out of the <p> it sits in,
		// so `input.closest('p')` no longer contains it. Scope the lookup to
		// the whole panel (or its nearest wrapper) instead so it's found
		// wherever the parser ended up putting it.
		var scope = input.closest( '.lis-listing-panel' ) || input.closest( 'p' ) || input.parentNode;
		var preview = scope ? scope.querySelector( '[data-photos-preview]' ) : null;

		// Inline error line, created once and kept next to the dropzone, so a
		// rejected file explains itself right where it happened.
		var errorBox = scope ? scope.querySelector( '[data-photos-error]' ) : null;
		if ( ! errorBox && dropzone ) {
			errorBox = document.createElement( 'div' );
			errorBox.className = 'lis-listing-photos-error';
			errorBox.setAttribute( 'data-photos-error', '' );
			errorBox.setAttribute( 'role', 'alert' );
			errorBox.hidden = true;
			dropzone.insertAdjacentElement( 'afterend', errorBox );
		}

		function showErrors( messages ) {
			if ( ! errorBox ) {
				return;
			}
			if ( ! messages.length ) {
				errorBox.hidden = true;
				errorBox.textContent = '';
				return;
			}
			errorBox.hidden = false;
			errorBox.innerHTML = messages.map( function ( message ) {
				var line = document.createElement( 'div' );
				line.textContent = message;
				return line.innerHTML;
			} ).join( '' );
		}

		function renderPreview() {
			if ( ! preview ) {
				return;
			}
			preview.innerHTML = '';
			Array.prototype.slice.call( input.files || [] ).forEach( function ( file ) {
				if ( ! OK_TYPE.test( file.type ) ) {
					return;
				}
				var item = document.createElement( 'div' );
				item.className = 'lis-listing-photos-preview-item';
				var img = document.createElement( 'img' );
				img.src = URL.createObjectURL( file );
				img.alt = '';
				img.addEventListener( 'load', function () {
					URL.revokeObjectURL( img.src );
				} );
				item.appendChild( img );
				preview.appendChild( item );
			} );
		}

		// Keep only real PNG/JPG files under the size limit, cap the count,
		// and rebuild input.files from just the survivors so the bad ones are
		// never submitted. Anything dropped is reported inline.
		function accept( fileList ) {
			var kept = [];
			var errors = [];

			Array.prototype.slice.call( fileList || [] ).forEach( function ( file ) {
				if ( ! OK_TYPE.test( file.type ) ) {
					errors.push( 'Skipped “' + file.name + '” — only PNG or JPG photos are allowed.' );
					return;
				}
				if ( file.size > MAX_BYTES ) {
					errors.push( 'Skipped “' + file.name + '” — it’s ' + ( file.size / 1048576 ).toFixed( 1 ) + 'MB, over the 2MB limit. Resize it and add it again.' );
					return;
				}
				kept.push( file );
			} );

			if ( kept.length > MAX_FILES ) {
				errors.push( 'Only the first ' + MAX_FILES + ' photos were kept.' );
				kept = kept.slice( 0, MAX_FILES );
			}

			// Rebuild the FileList from the survivors. DataTransfer is the only
			// standard way to assign a FileList; if it's unavailable and any
			// file was rejected, fall back to clearing the input so a bad file
			// can't slip through.
			try {
				var data = new DataTransfer();
				kept.forEach( function ( file ) {
					data.items.add( file );
				} );
				input.files = data.files;
			} catch ( e ) {
				if ( errors.length ) {
					input.value = '';
				}
			}

			showErrors( errors );
			renderPreview();
		}

		// Assigning input.files does not re-fire 'change', so no loop here.
		input.addEventListener( 'change', function () {
			accept( input.files );
		} );

		if ( ! dropzone ) {
			return;
		}

		[ 'dragenter', 'dragover' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function ( e ) {
				e.preventDefault();
				dropzone.classList.add( 'is-dragover' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function ( e ) {
				e.preventDefault();
				dropzone.classList.remove( 'is-dragover' );
			} );
		} );

		dropzone.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length ) {
				accept( e.dataTransfer.files );
			}
		} );
	} );
} );
