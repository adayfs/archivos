(function ($) {
	'use strict';

	function syncPanels(form) {
		var selected = {};
		form.find('.drak-gallery-type-checkbox').each(function () {
			selected[$(this).val()] = $(this).is(':checked');
		});

		form.find('.drak-conditional-panel').each(function () {
			var panel = $(this);
			var type = panel.data('type');
			if (selected[type]) {
				panel.slideDown(150);
			} else {
				panel.slideUp(150);
			}
		});
	}

	$(function () {
		var form = $('.drak-gallery-upload-form');
		if (!form.length) {
			return;
		}

		syncPanels(form);
		form.on('change', '.drak-gallery-type-checkbox', function () {
			syncPanels(form);
		});

		form.on('mousedown', 'select[multiple] option', function (event) {
			event.preventDefault();
			var option = $(this);
			var select = option.closest('select');

			option.prop('selected', !option.prop('selected'));
			select.trigger('change');
		});
	});
})(jQuery);
