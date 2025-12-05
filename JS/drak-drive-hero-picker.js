(function () {
  const state = { modal: null, grid: null, loading: null, empty: null, search: null, current: null, config: null };

  function normalizeDriveUrl(url) {
    if (!url) return '';
    try {
      const drivePatterns = [
        /\/file\/d\/([^/]+)/,
        /[?&]id=([^&#]+)/,
      ];
      let id = '';
      drivePatterns.some((re) => {
        const m = url.match(re);
        if (m && m[1]) {
          id = m[1];
          return true;
        }
        return false;
      });
      if (id && id.length > 5) {
        return `https://lh3.googleusercontent.com/d/${encodeURIComponent(id)}=w2000`;
      }
    } catch (e) {
      // noop
    }
    // Si es Drive y no hay ID válida, retorna cadena vacía para evitar 400.
    if (url.includes('drive.google')) return '';
    return url;
  }

  function ensureModal() {
    if (state.modal) return;
    const modal = document.createElement('div');
    modal.className = 'drive-picker-modal';
    modal.innerHTML = `
      <div class="drive-picker-modal__overlay" data-close></div>
      <div class="drive-picker-modal__content">
        <div class="drive-picker-modal__header">
          <input type="search" class="drive-picker-modal__search" placeholder="${state.config.texts.search}">
          <button type="button" class="drive-picker-modal__close" data-close>${state.config.texts.close}</button>
        </div>
        <div class="drive-picker-modal__grid" data-grid></div>
        <div class="drive-picker-modal__loading">${state.config.texts.loading}</div>
        <div class="drive-picker-modal__empty" style="display:none;">${state.config.texts.empty}</div>
      </div>
    `;
    document.body.appendChild(modal);
    state.modal = modal;
    state.grid = modal.querySelector('[data-grid]');
    state.loading = modal.querySelector('.drive-picker-modal__loading');
    state.empty = modal.querySelector('.drive-picker-modal__empty');
    state.search = modal.querySelector('.drive-picker-modal__search');

    modal.addEventListener('click', (ev) => {
      if (ev.target.dataset.close !== undefined) {
        closeModal();
      }
    });

    state.search.addEventListener('input', () => {
      loadImages(state.search.value || '');
    });
  }

  function openModal(box) {
    if (!state.config) {
      const ajaxUrl = box.dataset.ajaxUrl || (typeof DrakDriveHeroPicker !== 'undefined' ? DrakDriveHeroPicker.ajaxUrl : '');
      const nonce = box.dataset.nonce || (typeof DrakDriveHeroPicker !== 'undefined' ? DrakDriveHeroPicker.nonce : '');
      state.config = {
        ajaxUrl: ajaxUrl || '',
        nonce: nonce || '',
        restUrl: (typeof DrakDriveHeroPicker !== 'undefined' ? DrakDriveHeroPicker.restUrl : '') || '',
        texts: (typeof DrakDriveHeroPicker !== 'undefined' && DrakDriveHeroPicker.texts) ? DrakDriveHeroPicker.texts : {
          search: 'Buscar...',
          close: 'Cerrar',
          loading: 'Cargando...',
          empty: 'Sin resultados.',
          error: 'Error cargando imágenes.',
        },
      };
    }
    if (!state.config.ajaxUrl && !state.config.restUrl) {
      return;
    }
    ensureModal();
    state.current = box;
    loadImages('');
    state.modal.style.display = 'block';
    state.modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    if (!state.modal) return;
    state.modal.style.display = 'none';
    state.modal.setAttribute('aria-hidden', 'true');
    state.current = null;
  }

  function loadImages(search) {
    if (!state.modal) return;
    state.grid.innerHTML = '';
    state.empty.style.display = 'none';
    state.loading.style.display = 'block';
    const personajeId = state.current ? (state.current.dataset.postId || '') : '';
    const restUrl = (typeof DrakDriveHeroPicker !== 'undefined' && DrakDriveHeroPicker.restUrl) ? DrakDriveHeroPicker.restUrl : '';
    const qs = new URLSearchParams({
      s: search || '',
    });
    if (personajeId) {
      qs.set('personaje', personajeId);
    }

    const fetchPromise = restUrl
      ? fetch(restUrl + (restUrl.includes('?') ? '&' : '?') + qs.toString(), { credentials: 'same-origin' })
      : fetch(state.config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'drak_drive_picker_list_public',
            nonce: state.config.nonce || '',
            s: search || '',
            personaje: personajeId,
          }),
        });

    fetchPromise
      .then((res) => res.json())
      .then((data) => {
        state.loading.style.display = 'none';
        const items = data?.data?.items || data?.items || [];
        if (!items.length) {
          state.empty.style.display = 'block';
          return;
        }
        items.forEach((item) => {
          const img = document.createElement('img');
          const src = normalizeDriveUrl(item.url);
          if (!src) return;
          img.src = src;
          img.alt = item.title;
          img.dataset.id = item.id;
          img.title = item.title;
          img.addEventListener('click', () => selectImage(item));
          state.grid.appendChild(img);
        });
      })
      .catch(() => {
        state.loading.textContent = state.config.texts.error;
      });
  }

  function selectImage(item) {
    if (!state.current) return;
    const btn = state.current;
    const hero = btn.closest('.personaje-hero')?.querySelector('[data-hero-image]');
    const postId = btn.dataset.postId;
    const context = btn.dataset.heroContext || '';
    const url = normalizeDriveUrl(item.url);
    if (hero) {
      hero.style.backgroundImage = `url('${url}')`;
    }

    if (!postId) {
      closeModal();
      return;
    }

    const payload = new URLSearchParams({
      action: 'drak_set_personaje_drive_image',
      post_id: postId,
      gallery_item_id: item.id,
      context: context,
      nonce: DrakDriveHeroPicker.nonce,
    });

    fetch(DrakDriveHeroPicker.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload,
    }).finally(() => {
      closeModal();
    });
  }

  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.personaje-hero__change');
    if (!btn) return;
    ev.preventDefault();
    openModal(btn);
  });
})();
