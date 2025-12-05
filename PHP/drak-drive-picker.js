(function ($) {
  function openModal(box) {
    const modal = $('#drak-drive-picker-modal');
    modal.show().attr('aria-hidden', 'false');
    loadGrid(modal, '');
    modal.data('box', box);

    modal.off('click', '[data-close]').on('click', '[data-close]', function () {
      closeModal();
    });

    modal.find('.drak-drive-modal__search').val('').off('input').on('input', function () {
      loadGrid(modal, $(this).val());
    });
  }

  function closeModal() {
    $('#drak-drive-picker-modal').hide().attr('aria-hidden', 'true');
  }

  function loadGrid(modal, search) {
    const grid = modal.find('[data-grid]');
    const loading = modal.find('.drak-drive-modal__loading');
    const empty = modal.find('.drak-drive-modal__empty');
    grid.empty();
    empty.hide();
    loading.show().text(DrakDrivePicker.texts.loading);

    $.get(DrakDrivePicker.ajaxUrl, { action: 'drak_drive_picker_list', nonce: DrakDrivePicker.nonce, s: search || '' })
      .done(function (resp) {
        loading.hide();
        if (!resp.success || !resp.data.items.length) {
          empty.show();
          return;
        }
        resp.data.items.forEach(function (item) {
          const img = $('<img>').attr({ src: item.url, alt: item.title, 'data-id': item.id, title: item.title });
          grid.append(img);
        });
      })
      .fail(function () {
        loading.text(DrakDrivePicker.texts.error || 'Error cargando imágenes');
      });
  }

  $(function () {
    $(document).on('click', '.drak-drive-picker__open', function () {
      const box = $(this).closest('.drak-drive-picker');
      openModal(box);
    });

    $(document).on('click', '#drak-drive-picker-modal [data-grid] img', function () {
      const id = $(this).data('id');
      const url = $(this).attr('src');
      const box = $('#drak-drive-picker-modal').data('box');
      if (!box) return;
      box.find('#drive_gallery_item_id').val(id);
      box.attr('data-selected', id);
      box.find('.drak-drive-picker__preview').html('<img src="' + url + '" alt="">');
      closeModal();
    });
  });
})(jQuery);
