(function ($) {
	'use strict';

	$(document).on('click', '.lis-pv-upload-logo', function (e) {
		e.preventDefault();
		var button = $(this);
		var targetId = button.data('target');
		var field = $('#' + targetId);
		var preview = $('#' + targetId + '_preview');
		var removeBtn = button.siblings('.lis-pv-remove-logo');

		var frame = wp.media({
			title: 'Select Logo',
			library: { type: ['image/png', 'image/jpeg'] },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			field.val(attachment.id);
			preview.attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
			removeBtn.show();
		});

		frame.open();
	});

	$(document).on('click', '.lis-pv-remove-logo', function (e) {
		e.preventDefault();
		var button = $(this);
		var targetId = button.data('target');

		$('#' + targetId).val('');
		$('#' + targetId + '_preview').hide().attr('src', '');
		button.hide();
	});

	function lisListingGalleryIds() {
		var raw = $('#lis_listing_gallery_ids').val();
		return raw ? raw.split(',').filter(Boolean) : [];
	}

	function lisListingGalleryAddThumb(attachment) {
		var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
		var $thumb = $('<span class="lis-listing-gallery-thumb"></span>').attr('data-id', attachment.id);
		$thumb.append($('<img>').attr('src', thumbUrl));
		$thumb.append($('<button type="button" class="lis-listing-gallery-remove" aria-label="Remove">&times;</button>'));
		$('#lis_listing_gallery_preview').append($thumb);
	}

	$(document).on('click', '.lis-listing-gallery-select', function (e) {
		e.preventDefault();

		var frame = wp.media({
			title: 'Select Gallery Images',
			library: { type: 'image' },
			multiple: true,
		});

		frame.on('select', function () {
			var selection = frame.state().get('selection');
			var ids = lisListingGalleryIds();

			selection.each(function (attachment) {
				var data = attachment.toJSON();
				if (ids.indexOf(String(data.id)) === -1) {
					ids.push(String(data.id));
					lisListingGalleryAddThumb(data);
				}
			});

			$('#lis_listing_gallery_ids').val(ids.join(','));
		});

		frame.open();
	});

	$(document).on('click', '.lis-listing-gallery-remove', function (e) {
		e.preventDefault();
		var $thumb = $(this).closest('.lis-listing-gallery-thumb');
		var removeId = String($thumb.data('id'));
		var ids = lisListingGalleryIds().filter(function (id) { return id !== removeId; });

		$('#lis_listing_gallery_ids').val(ids.join(','));
		$thumb.remove();
	});
})(jQuery);
