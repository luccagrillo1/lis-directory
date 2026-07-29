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
})(jQuery);
