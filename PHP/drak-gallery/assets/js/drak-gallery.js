(function ($) {
	'use strict';

	var galleryState = {
		cards: $(),
		index: -1
	};

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

	function getModal() {
		return $('#drak-gallery-modal');
	}

	function buildGalleryState(card) {
		var wrapper = card.closest('[data-drak-gallery]');
		var visible = wrapper.find('.drak-gallery-card').filter(function () {
			return !$(this).attr('hidden');
		});
		galleryState.cards = visible;
		var currentIndex = visible.index(card);
		galleryState.index = currentIndex > -1 ? currentIndex : 0;
	}

	function updateNavButtons(modal) {
		var disabled = !(galleryState.cards && galleryState.cards.length > 1);
		modal.find('[data-modal-prev], [data-modal-next]').prop('disabled', disabled);
	}

	function renderModalContent(card, modal) {
		var rel = parseRel(card);
		modal.find('.drak-gallery-modal__image img')
			.attr('src', card.data('full') || '')
			.attr('alt', card.data('title') || '');

		var author = card.data('author') || '';
		var date = card.data('date') || '';

		var metaBar = modal.find('.drak-gallery-modal__meta');
		metaBar.empty();
		if (author) {
			metaBar.append($('<span></span>').text(author));
		}
		if (date) {
			metaBar.append($('<span></span>').text(date));
		}

		modal.find('.drak-gallery-modal__title').text(card.data('title') || '');
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
				li.text(item.title || '');
				list.append(li);
			});

			group.append(title).append(list);
			links.append(group);
		});
	}

	function toggleModal(show, card) {
		var modal = getModal();
		if (!modal.length) {
			return;
		}

		if (!show) {
			modal.removeClass('is-visible').attr('aria-hidden', 'true');
			galleryState.cards = $();
			galleryState.index = -1;
			return;
		}

		if (!card || !card.length) {
			return;
		}

		buildGalleryState(card);
		renderModalContent(card, modal);
		updateNavButtons(modal);
		modal.addClass('is-visible').attr('aria-hidden', 'false');
	}

	function navigateModal(step) {
		var modal = getModal();
		if (!modal.length || !modal.hasClass('is-visible')) {
			return;
		}

		var cards = galleryState.cards;
		var total = cards ? cards.length : 0;
		if (!total || total < 2) {
			return;
		}

		var nextIndex = galleryState.index + step;
		if (nextIndex < 0) {
			nextIndex = total - 1;
		} else if (nextIndex >= total) {
			nextIndex = 0;
		}

		var nextCard = cards.eq(nextIndex);
		if (!nextCard.length) {
			return;
		}

		galleryState.index = nextIndex;
		renderModalContent(nextCard, modal);
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
		var wrappers = $('[data-drak-gallery]');
		wrappers.each(function () {
			var block = $(this);
			applyFilters(block);
			block.on('input', '.drak-gallery-search', function () {
				applyFilters(block);
			});
			block.on('change', '[data-filter]', function () {
				applyFilters(block);
			});
		});

		$(document).on('click', '.drak-gallery-card', function () {
			toggleModal(true, $(this));
		});

		$(document).on('click', '[data-modal-prev]', function (event) {
			event.preventDefault();
			navigateModal(-1);
		});

		$(document).on('click', '[data-modal-next]', function (event) {
			event.preventDefault();
			navigateModal(1);
		});

		$(document).on('click', '[data-modal-close]', function () {
			toggleModal(false);
		});
		$(document).on('keyup', function (event) {
			if (event.key === 'Escape') {
				toggleModal(false);
			} else if (event.key === 'ArrowLeft') {
				navigateModal(-1);
			} else if (event.key === 'ArrowRight') {
				navigateModal(1);
			}
		});
	});
})(jQuery);
