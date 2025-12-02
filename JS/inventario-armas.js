(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const hasApi = typeof window.DND5_API !== 'undefined';

    function fetchJsonWithFallback(urls) {
      const [head, ...tail] = urls;
      if (!head) return Promise.reject(new Error('Sin URL'));
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

    function joinPath(base, path) {
      if (!base || !path) return '';
      const cleanBase = base.replace(/\/+$/, '');
      const cleanPath = path.replace(/^\/+/, '');
      return `${cleanBase}/${cleanPath}`;
    }

    // --- ARMAS (principal / secundaria) ---
    (function initWeaponSelector() {
      const modal = document.getElementById('armaModal');
      const selector = document.getElementById('arma-selector');
      const applyBtn = document.getElementById('arma-aplicar');
      const preview = document.getElementById('arma-preview');
      const previewDamage = document.getElementById('arma-damage');
      const previewDamageType = document.getElementById('arma-damage-type');
      const previewWeight = document.getElementById('arma-weight');
      const previewProps = document.getElementById('propiedades-arma-lista');
      const magicCheckbox = document.getElementById('arma-magica');
      const attuneCheckbox = document.getElementById('arma-attune');
      const contextLabel = document.getElementById('arma-modal-context-label');
      const deleteModal = document.getElementById('armaModalEliminar');
      const deleteConfirm = document.getElementById('confirmarEliminarArma');
      const deleteCancel = deleteModal?.querySelector('.close-modal');
      const deleteCopy = document.getElementById('arma-delete-copy');

      const displays = {
        principal: document.getElementById('arma-principal-display'),
        secundaria: document.getElementById('arma-secundaria-display'),
      };

      const hiddenBySlot = {
        principal: {
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
        },
        secundaria: {
          name: document.getElementById('arma2_name'),
          slug: document.getElementById('arma2_slug'),
          category: document.getElementById('arma2_category'),
          damage_dice: document.getElementById('arma2_damage_dice'),
          damage_type: document.getElementById('arma2_damage_type'),
          weight: document.getElementById('arma2_weight'),
          properties: document.getElementById('arma2_properties'),
          es_magica: document.getElementById('arma2_es_magica'),
          requiere_attunement: document.getElementById('arma2_requiere_attunement'),
          descripcion: document.getElementById('arma2_descripcion'),
        },
      };

      const addButtons = document.querySelectorAll('[data-arma-slot].arma-btn-add');
      if (!modal || !selector || !applyBtn || (!displays.principal && !displays.secundaria)) return;

      let currentSlot = 'principal';
      let pendingDeleteSlot = 'principal';
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

      function setHidden(slot, key, value) {
        const target = hiddenBySlot[slot];
        if (target?.[key]) {
          target[key].value = value ?? '';
        }
      }

      function getHiddenValue(slot, key) {
        const target = hiddenBySlot[slot];
        return target?.[key]?.value || '';
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

      function setModalContextLabel() {
        if (!contextLabel) return;
        contextLabel.textContent = currentSlot === 'secundaria' ? '(secundaria)' : '(principal)';
      }

      function applySelection(weapon) {
        if (!weapon) return;
        const targetDisplay = displays[currentSlot];
        const name = readWeaponName(weapon.name);
        if (targetDisplay) {
          targetDisplay.innerHTML = `<p><strong>${name}</strong> (${weapon.damage_dice || '—'} ${weapon.damage_type || ''})</p>`;
        }

        setHidden(currentSlot, 'name', name);
        setHidden(currentSlot, 'slug', weapon.slug);
        setHidden(currentSlot, 'category', weapon.category);
        setHidden(currentSlot, 'damage_dice', weapon.damage_dice);
        setHidden(currentSlot, 'damage_type', weapon.damage_type);
        setHidden(currentSlot, 'weight', weapon.weight);
        setHidden(
          currentSlot,
          'properties',
          Array.isArray(weapon.properties) ? weapon.properties.join(', ') : weapon.properties || ''
        );
        setHidden(currentSlot, 'descripcion', weapon.desc || '');
        setHidden(currentSlot, 'es_magica', magicCheckbox?.checked ? '1' : '0');
        setHidden(currentSlot, 'requiere_attunement', attuneCheckbox?.checked ? '1' : '0');
      }

      function clearWeaponSelection(slot = currentSlot) {
        const emptyCopy =
          slot === 'secundaria' ? 'No hay arma secundaria asignada' : 'No hay arma asignada';
        const targetDisplay = displays[slot];
        if (targetDisplay) {
          targetDisplay.innerHTML = `<p>${emptyCopy}</p>`;
        }
        const fields = hiddenBySlot[slot];
        if (fields) {
          Object.keys(fields).forEach((key) => {
            if (fields[key]) fields[key].value = '';
          });
        }
        if (selector && slot === currentSlot) {
          selector.value = '';
        }
        if (preview) {
          preview.style.display = 'none';
          if (previewProps) previewProps.innerHTML = '';
        }
        if (magicCheckbox) magicCheckbox.checked = false;
        if (attuneCheckbox) attuneCheckbox.checked = false;
      }

      function syncCheckboxesFromHidden(slot) {
        if (magicCheckbox) {
          magicCheckbox.checked = getHiddenValue(slot, 'es_magica') === '1';
        }
        if (attuneCheckbox) {
          attuneCheckbox.checked = getHiddenValue(slot, 'requiere_attunement') === '1';
        }
      }

      function openWeaponModal(slot) {
        currentSlot = slot === 'secundaria' ? 'secundaria' : 'principal';
        setModalContextLabel();
        modal.style.display = 'flex';
        fetchWeapons()
          .then(() => {
            const storedSlug = getHiddenValue(currentSlot, 'slug');
            selector.value = storedSlug || '';
            const weapon = weaponCache.find((w) => w.slug === selector.value);
            if (weapon) {
              renderPreview(weapon);
            } else if (preview) {
              preview.style.display = 'none';
              if (previewProps) previewProps.innerHTML = '';
            }
            syncCheckboxesFromHidden(currentSlot);
          })
          .catch(() => {});
      }

      addButtons.forEach((btn) => {
        const slot = btn.dataset.armaSlot === 'secundaria' ? 'secundaria' : 'principal';
        btn.addEventListener('click', () => openWeaponModal(slot));
      });

      selector?.addEventListener('change', () => {
        const weapon = weaponCache.find((w) => w.slug === selector.value);
        if (!weapon) {
          if (preview) preview.style.display = 'none';
          return;
        }
        renderPreview(weapon);
      });

      applyBtn?.addEventListener('click', () => {
        const weapon = weaponCache.find((w) => w.slug === selector.value);
        if (!weapon) return;
        applySelection(weapon);
        modal.style.display = 'none';
      });

      modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });

      document.querySelector('.close-arma-popup')?.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      if (deleteModal && deleteConfirm) {
        const deleteButtons = document.querySelectorAll(
          '[data-arma-slot].arma-principal-remove, [data-arma-slot].arma-secundaria-remove'
        );

        deleteButtons.forEach((btn) => {
          btn.addEventListener('click', () => {
            pendingDeleteSlot = btn.dataset.armaSlot === 'secundaria' ? 'secundaria' : 'principal';
            if (deleteCopy) {
              deleteCopy.textContent =
                pendingDeleteSlot === 'secundaria'
                  ? '¿Eliminar el arma secundaria?'
                  : '¿Eliminar el arma principal?';
            }
            deleteModal.style.display = 'flex';
          });
        });

        deleteConfirm.addEventListener('click', () => {
          clearWeaponSelection(pendingDeleteSlot);
          deleteModal.style.display = 'none';
        });

        deleteCancel?.addEventListener('click', () => {
          deleteModal.style.display = 'none';
        });

        deleteModal.addEventListener('click', (event) => {
          if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
          }
        });
      }
    })();

    // --- ARMADURA ---
    (function initArmorSelector() {
      const modal = document.getElementById('armaduraModal');
      const selector = document.getElementById('armadura-selector');
      const applyBtn = document.getElementById('armadura-aplicar');
      const preview = document.getElementById('armadura-preview');
      const previewAc = document.getElementById('armadura-ac');
      const previewTipo = document.getElementById('armadura-tipo');
      const previewSigilo = document.getElementById('armadura-sigilo');
      const previewFuerza = document.getElementById('armadura-fuerza');
      const previewPeso = document.getElementById('armadura-peso');
      const previewDescripcion = document.getElementById('armadura-descripcion');
      const display = document.getElementById('armadura-display');
      const addBtn = document.getElementById('armadura-add');
      const removeBtn = document.getElementById('armadura-remove');
      const deleteModal = document.getElementById('armaduraModalEliminar');
      const deleteConfirm = document.getElementById('confirmarEliminarArmadura');
      const deleteCancel = deleteModal?.querySelector('.close-modal');

      if (!modal || !selector || !applyBtn || !display) return;

      const hidden = {
        name: document.getElementById('armadura_name'),
        slug: document.getElementById('armadura_slug'),
        type: document.getElementById('armadura_type'),
        ac: document.getElementById('armadura_ac'),
        strength: document.getElementById('armadura_strength'),
        stealth_disadvantage: document.getElementById('armadura_stealth_disadvantage'),
        weight: document.getElementById('armadura_weight'),
        value: document.getElementById('armadura_value'),
        descripcion: document.getElementById('armadura_descripcion'),
      };

      let armorCache = [];
      let armorPromise = null;

      function readArmorName(name) {
        if (!name) return '';
        if (typeof name === 'object') {
          return name.es || name.en || '';
        }
        return name;
      }

      function normalizeArmor(entry) {
        const desc =
          Array.isArray(entry.entries_es) && entry.entries_es.length
            ? entry.entries_es.join(' ')
            : entry.entries_es || entry.entries || '';
        return {
          slug: entry.id || entry.slug || '',
          name: readArmorName(entry.name) || entry.id || '',
          type: entry.type || '',
          ac: entry.ac || 0,
          strength: entry.strength || '',
          stealthDisadvantage: Boolean(entry.stealthDisadvantage),
          weight: entry.weight ?? '',
          value: entry.value ?? '',
          desc,
        };
      }

      function resolveArmorUrls() {
        const urls = [];
        const staticData = window.DND5_STATIC_DATA || window.DND5_STATIC || null;
        const racesUrl = staticData?.races;
        const themeBase = (window?.THEME_DIR_URI || '').replace(/\/$/, '');
        const siteBase = window.location?.origin ? window.location.origin.replace(/\/$/, '') : '';

        if (typeof racesUrl === 'string' && racesUrl.includes('/')) {
          const base = racesUrl.replace(/[^/]+$/, '');
          urls.push(`${base}dnd-armors-es.json`);
          urls.push(`${base}dnd-armors.json`);
          urls.push(`${base}jsons/dnd-armors-es.json`);
          urls.push(`${base}jsons/dnd-armors.json`);
        }

        urls.push('dnd-armors-es.json');
        urls.push('dnd-armors.json');
        urls.push('jsons/dnd-armors-es.json');
        urls.push('jsons/dnd-armors.json');

        const pathBase = window.location?.pathname ? window.location.pathname.replace(/[^/]+$/, '') : '/';
        urls.push(`${pathBase}dnd-armors-es.json`);
        urls.push(`${pathBase}dnd-armors.json`);
        urls.push(`${pathBase}jsons/dnd-armors-es.json`);
        urls.push(`${pathBase}jsons/dnd-armors.json`);
        urls.push('/dnd-armors-es.json');
        urls.push('/dnd-armors.json');
        urls.push('/jsons/dnd-armors-es.json');
        urls.push('/jsons/dnd-armors.json');

        if (themeBase) {
          urls.push(joinPath(themeBase, 'jsons/dnd-armors-es.json'));
          urls.push(joinPath(themeBase, 'jsons/dnd-armors.json'));
          urls.push(joinPath(themeBase.replace(/\/data$/i, ''), 'jsons/dnd-armors-es.json'));
          urls.push(joinPath(themeBase.replace(/\/data$/i, ''), 'jsons/dnd-armors.json'));
        }
        if (siteBase) {
          urls.push(joinPath(`${siteBase}/wp-content/themes/temahijo`, 'jsons/dnd-armors-es.json'));
          urls.push(joinPath(`${siteBase}/wp-content/themes/temahijo`, 'jsons/dnd-armors.json'));
        }

        return Array.from(new Set(urls.filter(Boolean)));
      }

      function populateArmorSelector() {
        selector.innerHTML = '<option value="">Selecciona una armadura</option>';
        const sorted = [...armorCache].sort((a, b) =>
          readArmorName(a.name).localeCompare(readArmorName(b.name), 'es', { sensitivity: 'base' })
        );
        sorted.forEach((armor) => {
          const option = document.createElement('option');
          option.value = armor.slug;
          option.textContent = readArmorName(armor.name) || armor.slug;
          selector.appendChild(option);
        });
      }

      function fetchArmors() {
        if (armorPromise) return armorPromise;
        const urls = resolveArmorUrls();

        const fetchArmorsViaApi = () => {
          const ajaxUrl = resolveAjaxUrl();
          if (!ajaxUrl) return Promise.reject(new Error('API no disponible'));
          const formData = new FormData();
          formData.append('action', 'drak_dnd5_get_armors_full');
          return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
          }).then((res) => res.json());
        };

        const processPayload = (data) => {
          const list = Array.isArray(data?.armors) ? data.armors : [];
          armorCache = list.map(normalizeArmor).filter((item) => item.slug);
          populateArmorSelector();
          return armorCache;
        };

        const tryApi = () =>
          fetchArmorsViaApi().then((json) => {
            if (!json?.success && !Array.isArray(json?.armors)) {
              throw new Error('Respuesta inválida');
            }
            return processPayload(json.data || json);
          });

        const tryStatic = () =>
          fetchJsonWithFallback(urls).then((data) => processPayload(data));

        if (hasApi || resolveAjaxUrl()) {
          armorPromise = tryApi().catch((error) => {
            console.error('Error al cargar armaduras (AJAX)', error);
            return tryStatic().catch((err) => {
              console.error('Error al cargar armaduras', err);
              selector.innerHTML = '<option value="">No se pudieron cargar las armaduras</option>';
              return [];
            });
          });
        } else {
          armorPromise = tryStatic().catch((err) => {
            console.error('Error al cargar armaduras', err);
            selector.innerHTML = '<option value="">No se pudieron cargar las armaduras</option>';
            return [];
          });
        }
        return armorPromise;
      }

      function renderArmorPreview(armor) {
        if (!armor || !preview) return;
        preview.style.display = 'block';
        if (previewAc) previewAc.textContent = armor.ac || '—';
        if (previewTipo) previewTipo.textContent = armor.type || '—';
        if (previewSigilo) {
          previewSigilo.textContent = armor.stealthDisadvantage ? 'Desventaja' : '—';
        }
        if (previewFuerza) previewFuerza.textContent = armor.strength || '—';
        if (previewPeso) previewPeso.textContent = armor.weight ?? '—';
        if (previewDescripcion) previewDescripcion.textContent = armor.desc || '';
      }

      function applyArmor(armor) {
        if (!armor) return;
        const meta = [];
        if (armor.ac) meta.push(`CA ${armor.ac}`);
        if (armor.type) meta.push(armor.type);
        meta.push(`Sigilo: ${armor.stealthDisadvantage ? 'Desventaja' : '—'}`);

        display.innerHTML = `<p><strong>${armor.name}</strong></p><p class="armor-meta">${meta.join(
          ' · '
        )}</p>`;

        hidden.name.value = armor.name || '';
        hidden.slug.value = armor.slug || '';
        hidden.type.value = armor.type || '';
        hidden.ac.value = armor.ac || '';
        hidden.strength.value = armor.strength || '';
        hidden.stealth_disadvantage.value = armor.stealthDisadvantage ? '1' : '';
        hidden.weight.value = armor.weight ?? '';
        hidden.value.value = armor.value ?? '';
        hidden.descripcion.value = armor.desc || '';
      }

      function clearArmor() {
        display.innerHTML = '<p>No hay armadura equipada</p>';
        Object.values(hidden).forEach((input) => {
          if (input) input.value = '';
        });
        if (preview) {
          preview.style.display = 'none';
          if (previewDescripcion) previewDescripcion.textContent = '';
        }
        selector.value = '';
      }

      selector?.addEventListener('change', () => {
        const armor = armorCache.find((a) => a.slug === selector.value);
        if (!armor) {
          if (preview) preview.style.display = 'none';
          return;
        }
        renderArmorPreview(armor);
      });

      applyBtn?.addEventListener('click', () => {
        const armor = armorCache.find((a) => a.slug === selector.value);
        if (!armor) return;
        applyArmor(armor);
        modal.style.display = 'none';
      });

      addBtn?.addEventListener('click', () => {
        modal.style.display = 'flex';
        fetchArmors()
          .then(() => {
            const currentSlug = hidden.slug?.value || '';
            selector.value = currentSlug;
            const armor = armorCache.find((a) => a.slug === selector.value);
            if (armor) {
              renderArmorPreview(armor);
            } else if (preview) {
              preview.style.display = 'none';
            }
          })
          .catch(() => {});
      });

      modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });
      document.querySelector('.close-armadura-popup')?.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      if (deleteModal && deleteConfirm && removeBtn) {
        removeBtn.addEventListener('click', () => {
          deleteModal.style.display = 'flex';
        });

        deleteCancel?.addEventListener('click', () => {
          deleteModal.style.display = 'none';
        });

        deleteModal.addEventListener('click', (event) => {
          if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
          }
        });

        deleteConfirm.addEventListener('click', () => {
          clearArmor();
          deleteModal.style.display = 'none';
        });
      }
    })();
  });
})();
