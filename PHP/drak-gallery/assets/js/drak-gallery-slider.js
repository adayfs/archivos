(function () {
	'use strict';

	if (typeof Swiper === 'undefined') {
		return;
	}

	var init = function () {
		document.querySelectorAll('.drak-gallery-swiper').forEach(function (node) {
			// eslint-disable-next-line no-new
			new Swiper(node, {
				loop: true,
				slidesPerView: 1,
				spaceBetween: 16,
				pagination: {
					el: node.querySelector('.swiper-pagination'),
					clickable: true
				},
				navigation: {
					nextEl: node.querySelector('.swiper-button-next'),
					prevEl: node.querySelector('.swiper-button-prev')
				}
			});
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
