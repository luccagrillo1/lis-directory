/**
 * Shared by [lis_listing_submit] and [lis_listing_edit] — both use the
 * same #lis_listing_photos file input markup: the native input is
 * visually hidden (see .lis-listing-submit-section input[type="file"] in
 * listings.css), a <label> styled as a dropzone triggers it on click
 * (native <label for>/click-to-open behavior, no JS needed for that
 * part), and this file adds two things the plain input can't do on its
 * own: drag-and-drop onto the dropzone, and live thumbnail previews of
 * what's actually selected before upload.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '#lis_listing_photos' ).forEach( function ( input ) {
		var dropzone = document.querySelector( 'label.lis-listing-photos-dropzone[for="' + input.id + '"]' );
		var preview = input.closest( 'p' ) ? input.closest( 'p' ).querySelector( '[data-photos-preview]' ) : null;

		function renderPreview() {
			if ( ! preview ) {
				return;
			}
			preview.innerHTML = '';
			Array.prototype.slice.call( input.files || [] ).forEach( function ( file ) {
				if ( ! /^image\/(png|jpeg)$/.test( file.type ) ) {
					return;
				}
				var item = document.createElement( 'div' );
				item.className = 'lis-listing-photos-preview-item';
				var img = document.createElement( 'img' );
				img.src = URL.createObjectURL( file );
				img.alt = '';
				item.appendChild( img );
				preview.appendChild( item );
			} );
		}

		input.addEventListener( 'change', renderPreview );

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
				input.files = e.dataTransfer.files;
				renderPreview();
			}
		} );
	} );
} );
