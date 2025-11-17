(function ($) {
	'use strict';

	function parseRel(card) {
		var data = card.attr('data-rel');
		if (!data) {
			return {};
		}

		try {
			return JSON.parse(data);
		} catch (e) {
			return {};
		}
	}

	function toggleModal(show, card) {
		var modal = $('#drak-gallery-modal');
		if (!modal.length) {
			return;
		}

		if (!show) {
			modal.removeClass('is-visible').attr('aria-hidden', 'true');
			return;
		}

		var rel = parseRel(card);
		modal.find('.drak-gallery-modal__image img')
			.attr('src', card.data('full') || '')
			.attr('alt', card.data('title') || '');

		var author = card.data('author') || '';
		var date = card.data('date') || '';
		var metaText = '';

		if (author && date) {
			metaText = author + ' · ' + date;
		} else {
			metaText = author || date;
		}

		modal.find('.drak-gallery-modal__title').text(card.data('title') || '');
		modal.find('.drak-gallery-modal__meta').text(metaText);
		modal.find('.drak-gallery-modal__desc').text(card.data('description') || '');

		var links = modal.find('.drak-gallery-modal__links');
		links.empty();

		var labels = (window.drakGalleryData && window.drakGalleryData.labels) || {
			personaje: 'Personaje',
			lugar: 'Lugar',
			npc: 'NPC'
		};

		['personaje', 'lugar', 'npc'].forEach(function (type) {
			if (!rel[type] || !rel[type].length) {
				return;
			}

			var group = $('<div class="drak-gallery-modal__group"></div>');
			var title = $('<strong></strong>').text(labels[type] || type);
			var list = $('<ul></ul>');

			rel[type].forEach(function (item) {
				var li = $('<li></li>');
				if (item.url) {
					$('<a></a>').attr('href', item.url).text(item.title || item.url).appendTo(li);
				} else {
					li.text(item.title || '');
				}
				list.append(li);
			});

			group.append(title).append(list);
			links.append(group);
		});

		modal.addClass('is-visible').attr('aria-hidden', 'false');
	}

	function applyFilters(wrapper) {
		var searchInput = wrapper.find('.drak-gallery-search');
		var filterInputs = wrapper.find('[data-filter]');
		var cards = wrapper.find('.drak-gallery-card');
		var query = searchInput.val() ? searchInput.val().toLowerCase() : '';
		var activeFilters = {};

		filterInputs.each(function () {
			activeFilters[$(this).data('filter')] = $(this).is(':checked');
		});

		cards.each(function () {
			var card = $(this);
			var text = (card.data('search') || '').toLowerCase();
			var passesSearch = !query || text.indexOf(query) !== -1;

			var matchesFilter = false;
			$.each(activeFilters, function (type, enabled) {
				if (!enabled) {
					return;
				}
				var hasType = card.data('has-' + type);
				if (hasType === 1 || hasType === '1') {
					matchesFilter = true;
					return false;
				}
			});

			if (!passesSearch || !matchesFilter) {
				card.attr('hidden', true);
			} else {
				card.removeAttr('hidden');
			}
		});
	}

	$(function () {
		var wrapper = $('[data-drak-gallery]');
		if (!wrapper.length) {
			return;
		}

		wrapper.each(function () {
			var block = $(this);
			applyFilters(block);
			block.on('input', '.drak-gallery-search', function () {
				applyFilters(block);
			});
			block.on('change', '[data-filter]', function () {
				applyFilters(block);
			});
			block.on('click', '.drak-gallery-card', function () {
				toggleModal(true, $(this));
			});
		});

		$(document).on('click', '[data-modal-close]', function () {
			toggleModal(false);
		});
		$(document).on('keyup', function (event) {
			if (event.key === 'Escape') {
				toggleModal(false);
			}
		});
	});
})(jQuery);
