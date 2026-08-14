/**
 * Jetpack Related Posts always renders one #jp-relatedposts placeholder,
 * populated by Jetpack's own JS after load — on the single listing page it
 * lands inside the Description block by default. templates/single-listing.php
 * wants it after Claim/Report instead, so this just moves the same live
 * element to a fixed anchor further down the page. It's a plain node move
 * (not a clone), so whatever Jetpack's own script later does to fill it
 * still works normally — it doesn't care where in the DOM the element sits.
 */
( function () {
	'use strict';

	function move() {
		var related = document.getElementById( 'jp-relatedposts' );
		var anchor  = document.getElementById( 'lis-listing-related-anchor' );
		if ( ! related || ! anchor ) {
			return;
		}
		anchor.insertAdjacentElement( 'afterend', related );
	}

	if ( 'loading' !== document.readyState ) {
		move();
	} else {
		document.addEventListener( 'DOMContentLoaded', move );
	}
}() );
