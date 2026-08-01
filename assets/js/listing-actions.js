(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var bookmarkBtn = document.querySelector('.lis-listing-bookmark-btn');
		var shareBtn = document.querySelector('.lis-listing-share-btn');

		if (bookmarkBtn && window.LIS_LISTING_ACTIONS) {
			setBookmarkState(bookmarkBtn, LIS_LISTING_ACTIONS.isBookmarked);

			bookmarkBtn.addEventListener('click', function () {
				if (!LIS_LISTING_ACTIONS.isLoggedIn) {
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
						}
					})
					.finally(function () {
						bookmarkBtn.disabled = false;
					});
			});
		}

		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
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
