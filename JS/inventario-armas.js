(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('armaModal');
    const openBtn = document.getElementById('arma-principal-add');
    const closeBtn = document.querySelector('.close-arma-popup');
    const applyBtn = document.getElementById('arma-aplicar');
    const selector = document.getElementById('arma-selector');
    const preview = document.getElementById('arma-preview');
    const previewDamage = document.getElementById('arma-damage');
    const previewDamageType = document.getElementById('arma-damage-type');
    const previewWeight = document.getElementById('arma-weight');
    const previewProps = document.getElementById('propiedades-arma-lista');
    const magicCheckbox = document.getElementById('arma-magica');
    const attuneCheckbox = document.getElementById('arma-attune');
    const display = document.getElementById('arma-principal-display');

    const deleteBtn = document.querySelector('.arma-principal-remove');
    const deleteModal = document.getElementById('armaModalEliminar');
    const deleteConfirm = document.getElementById('confirmarEliminarArma');
    const deleteCancel = deleteModal?.querySelector('.close-modal');

    if (!modal || !openBtn || !selector || !applyBtn || !display) return;

    const hiddenFields = {
      name: document.getElementById('arma_name'),
      slug: document.getElementById('arma_slug'),
      category: document.getElementById('arma_category'),
      damage_dice: document.getElementById('arma_damage_dice'),
      damage_type: document.getElementById('arma_damage_type'),
      weight: document.getElementById('arma_weight'),
      properties: document.getElementById('arma_properties'),
      es_magica: document.getElementById('arma_es_magica'),
      requiere_attunement: document.getElementById('arma_requiere_attunement'),
      descripcion: document.getElementById('arma_descripcion'),
    };

    const hasApi = typeof window.DND5_API !== 'undefined';
    let weaponCache = [];
    let fetchPromise = null;

    function readWeaponName(name) {
      if (!name) return '';
      if (typeof name === 'object') {
        return name.es || name.en || '';
      }
      return name;
    }

    function normalizeWeapon(entry) {
      return {
        slug: entry.id || entry.slug || '',
        name: readWeaponName(entry.name) || entry.id || '',
        category: entry.category || '',
        damage_dice: entry.dmg1 || entry.damage_dice || '',
        damage_type: entry.dmgType || entry.damage_type || '',
        weight: entry.weight,
        properties: entry.properties || [],
        desc: entry.desc || '',
      };
    }

    function resolveWeaponUrls() {
      const urls = [];
      const staticData = window.DND5_STATIC_DATA || window.DND5_STATIC || null;
      const racesUrl = staticData?.races;

      if (typeof racesUrl === 'string' && racesUrl.includes('/')) {
        const base = racesUrl.replace(/[^/]+$/, '');
        urls.push(`${base}dnd-weapons.json`);
        urls.push(`${base}jsons/dnd-weapons.json`);
        if (/\/data\/?$/i.test(base)) {
          urls.push(base.replace(/\/data\/?$/i, '/jsons/dnd-weapons.json'));
        }
      }

      urls.push('dnd-weapons.json');
      urls.push('jsons/dnd-weapons.json');

      const pathBase = window.location?.pathname ? window.location.pathname.replace(/[^/]+$/, '') : '/';
      urls.push(`${pathBase}dnd-weapons.json`);
      urls.push(`${pathBase}jsons/dnd-weapons.json`);
      urls.push('/dnd-weapons.json');
      urls.push('/jsons/dnd-weapons.json');

      return Array.from(new Set(urls.filter(Boolean)));
    }

    function fetchJsonWithFallback(urls) {
      const [head, ...tail] = urls;
      if (!head) return Promise.reject(new Error('Sin URL de armas'));
      return fetch(head)
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          return response.json();
        })
        .catch((err) => {
          if (!tail.length) throw err;
          return fetchJsonWithFallback(tail);
        });
    }

    function resolveAjaxUrl() {
      if (window.DND5_API?.ajax_url) return window.DND5_API.ajax_url;
      if (window.DELERIUM_AUTOSAVE?.ajaxUrl) return window.DELERIUM_AUTOSAVE.ajaxUrl;
      if (window.ajaxurl) return window.ajaxurl;
      return '/wp-admin/admin-ajax.php';
    }

    function fetchWeaponsViaApi() {
      const ajaxUrl = resolveAjaxUrl();
      if (!ajaxUrl) return Promise.reject(new Error('API no disponible'));
      const formData = new FormData();
      formData.append('action', 'drak_dnd5_get_weapons_full');
      return fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      }).then((res) => res.json());
    }

    function processWeaponPayload(payload) {
      const list = Array.isArray(payload?.weapons) ? payload.weapons : [];
      const seen = new Set();
      weaponCache = list
        .map(normalizeWeapon)
        .filter((item) => {
          if (!item.slug || seen.has(item.slug)) return false;
          seen.add(item.slug);
          return true;
        });
      populateSelector();
    }

    function fetchWeapons() {
      if (fetchPromise) return fetchPromise;
      const weaponUrls = resolveWeaponUrls();

      const tryApi = () =>
        fetchWeaponsViaApi().then((json) => {
          if (!json?.success && !Array.isArray(json?.weapons)) {
            throw new Error('Respuesta inválida');
          }
          processWeaponPayload(json.data || json);
          return weaponCache;
        });

      const tryStatic = () =>
        fetchJsonWithFallback(weaponUrls).then((data) => {
          processWeaponPayload(data);
          return weaponCache;
        });

      if (hasApi || resolveAjaxUrl()) {
        fetchPromise = tryApi().catch((error) => {
          console.error('Error al cargar armas (AJAX)', error);
          selector.innerHTML = '<option value="">No se pudieron cargar las armas</option>';
          return [];
        });
      } else {
        fetchPromise = tryStatic().catch((error) => {
          console.error('Error al cargar armas', error);
          selector.innerHTML = '<option value="">No se pudieron cargar las armas</option>';
          return [];
        });
      }

      return fetchPromise;
    }

    function populateSelector() {
      selector.innerHTML = '<option value="">Selecciona un arma</option>';
      const sorted = [...weaponCache].sort((a, b) =>
        readWeaponName(a.name).localeCompare(readWeaponName(b.name), 'es', { sensitivity: 'base' })
      );
      sorted.forEach((weapon) => {
        const option = document.createElement('option');
        option.value = weapon.slug;
        option.textContent = readWeaponName(weapon.name) || weapon.slug;
        selector.appendChild(option);
      });
    }

    function renderPreview(weapon) {
      if (!weapon || !preview) return;
      preview.style.display = 'block';
      previewDamage.textContent = weapon.damage_dice || '—';
      previewDamageType.textContent = weapon.damage_type || '—';
      previewWeight.textContent = weapon.weight ?? '—';
      if (previewProps) {
        previewProps.innerHTML = '';
        (weapon.properties || []).forEach((prop) => {
          const li = document.createElement('li');
          li.textContent = prop;
          previewProps.appendChild(li);
        });
      }
    }

    function setHiddenField(key, value) {
      if (hiddenFields[key]) {
        hiddenFields[key].value = value ?? '';
      }
    }

    function applySelection(weapon) {
      if (!weapon) return;
      const name = readWeaponName(weapon.name);
      display.innerHTML = `<p><strong>${name}</strong> (${weapon.damage_dice || '—'} ${weapon.damage_type || ''})</p>`;

      setHiddenField('name', name);
      setHiddenField('slug', weapon.slug);
      setHiddenField('category', weapon.category);
      setHiddenField('damage_dice', weapon.damage_dice);
      setHiddenField('damage_type', weapon.damage_type);
      setHiddenField('weight', weapon.weight);
      setHiddenField('properties', Array.isArray(weapon.properties) ? weapon.properties.join(', ') : '');
      setHiddenField('descripcion', weapon.desc || '');
      setHiddenField('es_magica', magicCheckbox?.checked ? '1' : '0');
      setHiddenField('requiere_attunement', attuneCheckbox?.checked ? '1' : '0');
    }

    function clearWeaponSelection() {
      display.innerHTML = '<p>No hay arma asignada</p>';
      Object.keys(hiddenFields).forEach((key) => setHiddenField(key, ''));
      selector.value = '';
      if (preview) {
        preview.style.display = 'none';
        if (previewProps) previewProps.innerHTML = '';
      }
      if (magicCheckbox) magicCheckbox.checked = false;
      if (attuneCheckbox) attuneCheckbox.checked = false;
    }

    openBtn.addEventListener('click', () => {
      modal.style.display = 'flex';
      fetchWeapons();
    });

    closeBtn?.addEventListener('click', () => {
      modal.style.display = 'none';
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });

    selector.addEventListener('change', () => {
      const weapon = weaponCache.find((w) => w.slug === selector.value);
      if (!weapon) {
        preview.style.display = 'none';
        return;
      }
      renderPreview(weapon);
    });

    applyBtn.addEventListener('click', () => {
      const weapon = weaponCache.find((w) => w.slug === selector.value);
      if (!weapon) return;
      applySelection(weapon);
      modal.style.display = 'none';
    });

    if (deleteBtn && deleteModal && deleteConfirm && deleteCancel) {
      deleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'flex';
      });

      deleteCancel.addEventListener('click', () => {
        deleteModal.style.display = 'none';
      });

      deleteModal.addEventListener('click', (event) => {
        if (event.target === deleteModal) {
          deleteModal.style.display = 'none';
        }
      });

      deleteConfirm.addEventListener('click', () => {
        clearWeaponSelection();
        deleteModal.style.display = 'none';
      });
    }

  });
})();
