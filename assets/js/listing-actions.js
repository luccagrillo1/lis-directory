(function () {
	'use strict';

	// View beacon for the Popular badge (includes/listings-badges.php). Sent
	// from the browser because the page itself is usually edge-cached; one per
	// listing per session so reloads don't count twice.
	function recordView() {
		if (!window.LIS_LISTING_ACTIONS || !LIS_LISTING_ACTIONS.postId) {
			return;
		}
		var key = 'lis_viewed_' + LIS_LISTING_ACTIONS.postId;
		try {
			if (window.sessionStorage.getItem(key)) {
				return;
			}
			window.sessionStorage.setItem(key, '1');
		} catch (e) {}
		var body = new URLSearchParams();
		body.append('action', 'lis_directory_listing_view');
		body.append('post_id', LIS_LISTING_ACTIONS.postId);
		if (navigator.sendBeacon) {
			navigator.sendBeacon(LIS_LISTING_ACTIONS.ajaxUrl, body);
		} else {
			fetch(LIS_LISTING_ACTIONS.ajaxUrl, { method: 'POST', body: body, keepalive: true });
		}
	}

	// GA4 (0.56.0): the site's existing tag; nothing is sent without one.
	function lisGa(name, params) {
		try {
			if (typeof window.lisTrack === 'function') { window.lisTrack(name, params); return; }
			if (typeof window.gtag === 'function') { window.gtag('event', name, params); }
		} catch (e) {}
	}

	document.addEventListener('DOMContentLoaded', function () {
		recordView();

		var bookmarkBtn = document.querySelector('.lis-listing-bookmark-btn');
		var shareBtn = document.querySelector('.lis-listing-share-btn');

		if (bookmarkBtn && window.LIS_LISTING_ACTIONS) {
			setBookmarkState(bookmarkBtn, LIS_LISTING_ACTIONS.isBookmarked);

			bookmarkBtn.addEventListener('click', function () {
				if (!LIS_LISTING_ACTIONS.isLoggedIn) {
					// Guests: open the site's sign-in card when it's on the page
					// (site snippet, exposes window.lisOpenLoginCard); else go to login.
					if (typeof window.lisOpenLoginCard === 'function') {
						window.lisOpenLoginCard(window.location.href);
						return;
					}
					window.location.href = LIS_LISTING_ACTIONS.loginUrl;
					return;
				}

				var body = new URLSearchParams();
				body.append('action', 'lis_directory_toggle_bookmark');
				body.append('nonce', LIS_LISTING_ACTIONS.bookmarkNonce);
				body.append('post_id', LIS_LISTING_ACTIONS.postId);

				bookmarkBtn.disabled = true;
				fetch(LIS_LISTING_ACTIONS.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				})
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (data.success) {
							setBookmarkState(bookmarkBtn, data.data.bookmarked);
							lisGa(data.data.bookmarked ? 'save_listing' : 'unsave_listing', { listing_id: String(LIS_LISTING_ACTIONS.postId), listing_title: document.title });
						}
					})
					.finally(function () {
						bookmarkBtn.disabled = false;
					});
			});
		}

		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
				lisGa('share', { method: navigator.share ? 'native' : 'copy', content_type: 'listing', item_id: window.LIS_LISTING_ACTIONS ? String(LIS_LISTING_ACTIONS.postId) : '' });
				var shareData = {
					title: document.title,
					url: window.location.href
				};
				if (navigator.share) {
					navigator.share(shareData).catch(function () {});
				} else if (navigator.clipboard) {
					navigator.clipboard.writeText(window.location.href).then(function () {
						var original = shareBtn.textContent;
						shareBtn.textContent = 'Link copied!';
						setTimeout(function () { shareBtn.textContent = original; }, 1800);
					});
				}
			});
		}
	});

	function setBookmarkState(btn, bookmarked) {
		btn.classList.toggle('is-bookmarked', bookmarked);
		btn.textContent = bookmarked ? '★ Saved' : '☆ Save';
	}
})();
