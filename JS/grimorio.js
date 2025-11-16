(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.GRIMORIO_DATA === 'undefined') return;
    initGrimorio();
  });

  const slotSaveTimers = {};
  const slotColumns = new Map();
  let pickerState = null;
  let spellsPromise = null;
  let classReferenceInitialized = false;

  const state = {
    slotsUsed: {},
    slotLimits: {},
    prepared: {},
    preparedLimit: null,
    autoPrepared: { class: {}, subclass: {} },
    spellsByLevel: {},
    spellIndexById: {},
    spellIndexByName: {},
    spellsLoaded: false,
    concentration: defaultConcentrationState(),
    transformation: defaultTransformationState(),
    abilities: {},
    baseAc: 0,
    baseSpeed: 0,
    apothecaryLevel: 1,
  };

  const selectors = {};

  function initGrimorio() {
    selectors.preparedContainer = document.querySelector('.grimorio-prepared');
    selectors.editButton = document.querySelector('.grimorio-prepared__edit-btn');
    selectors.resetSlotsBtn = document.querySelector('.grimorio-reset-slots');
    selectors.resetPreparedBtn = document.querySelector('.grimorio-reset-prepared');
    selectors.pickerModal = document.getElementById('grimorio-spell-picker');
    selectors.pickerLevels = document.getElementById('grimorio-spell-picker-levels');
    selectors.pickerLoading = document.getElementById('grimorio-spell-picker-loading');
    selectors.pickerSaveBtn = document.getElementById('grimorio-spell-picker-save');
    selectors.infoModal = document.getElementById('grimorio-info-modal');
    selectors.infoTitle = document.getElementById('grimorio-info-title');
    selectors.infoBody = document.getElementById('grimorio-info-content');
    selectors.finishConcentrationBtn = document.querySelector('.grimorio-finish-concentration');
    selectors.transformStartBtn = document.getElementById('grimorio-transform-start');
    selectors.transformFinishBtn = document.getElementById('grimorio-transform-finish');
    selectors.transformDisplay = document.getElementById('grimorio-transformation-display');
    selectors.transformModal = document.getElementById('grimorio-transformation-modal');
    selectors.transformModalLevels = document.getElementById('grimorio-transformation-levels');
    selectors.transformModalConfirm = document.getElementById('grimorio-transformation-confirm');

    hydrateState();
    initSlots();
    renderPreparedView();
    bindEvents();
    fetchSpellList();
    initTransformationModule();

    if (typeof window.initClassReferenceModule === 'function') {
      window.initClassReferenceModule();
    }

    const classReferenceEl = document.getElementById('class-reference-module');
    const classReferenceState = window.classReferenceState || null;
    if (classReferenceEl && classReferenceState && window.refreshClassReferenceModule) {
      classReferenceInitialized = true;
      refreshClassReferenceForGrimoire();
    }
  }

  function hydrateState() {
    const data = window.GRIMORIO_DATA;
    state.slotsUsed = cloneObject(data.slots_used || {});
    state.slotLimits = normalizeSlotLimits(data.slot_limits || {});
    state.prepared = normalizePrepared(data.prepared || {});
    state.level = data.level || 1;
    state.classId = data.class_id || '';
    const limitValue = Number.isFinite(data.prepared_limit)
      ? data.prepared_limit
      : parseInt(data.prepared_limit || '0', 10);
    state.preparedLimit = Number.isFinite(limitValue) && limitValue > 0 ? limitValue : null;
    state.concentration = normalizeConcentrationState(data.concentration);
    state.autoPrepared = data.auto_prepared_spells || { class: {}, subclass: {} };
    state.transformation = normalizeTransformationState(data.transformation);
    state.abilities = normalizeAbilities(data.abilities);
    state.baseAc = parseInt(data.base_ac || '0', 10) || 0;
    state.baseSpeed = parseInt(data.base_speed || '0', 10) || 0;
    state.apothecaryLevel = Number.isFinite(data.apothecary_level) && data.apothecary_level > 0 ? data.apothecary_level : state.level || 1;
  }

  function bindEvents() {
    if (selectors.editButton) {
      selectors.editButton.addEventListener('click', openSpellPicker);
    }

    if (selectors.resetSlotsBtn) {
      selectors.resetSlotsBtn.addEventListener('click', resetSlots);
    }

    if (selectors.resetPreparedBtn) {
      selectors.resetPreparedBtn.addEventListener('click', resetPreparedSpells);
    }

    if (selectors.finishConcentrationBtn) {
      selectors.finishConcentrationBtn.addEventListener('click', () => {
        setConcentrationState(null);
      });
    }

    if (selectors.preparedContainer) {
      selectors.preparedContainer.addEventListener('click', (event) => {
        const button = event.target.closest('.grimorio-cast-spell');
        if (!button) return;
        const level = parseInt(button.dataset.level || '0', 10);
        const spellId = button.dataset.spellId || '';
        const spellName = button.dataset.spellName || '';
        handleCastSpell(level, spellId, spellName);
      });
    }

    document.querySelectorAll('[data-grimorio-close]').forEach((btn) => {
      btn.addEventListener('click', () => {
        closeModal(btn.closest('.grimorio-modal'));
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const openModal = document.querySelector('.grimorio-modal.is-visible');
      if (openModal) {
        event.preventDefault();
        closeModal(openModal);
      }
    });

    if (selectors.pickerSaveBtn) {
      selectors.pickerSaveBtn.addEventListener('click', saveSpellPickerSelection);
    }
  }

  function initTransformationModule() {
    if (!document.getElementById('grimorio-transformation')) return;

    if (selectors.transformStartBtn) {
      selectors.transformStartBtn.addEventListener('click', openTransformationModal);
    }

    if (selectors.transformFinishBtn) {
      selectors.transformFinishBtn.addEventListener('click', finishTransformation);
    }

    if (selectors.transformModalConfirm) {
      selectors.transformModalConfirm.addEventListener('click', confirmTransformationSelection);
    }

    renderTransformationBlock();
  }

  function initSlots() {
    document.querySelectorAll('.grimorio-slot-column').forEach((column) => {
      const level = parseInt(column.dataset.level || '0', 10);
      const max = parseInt(column.dataset.max || '0', 10) || 0;
      if (!level) return;

      const checkboxes = Array.from(column.querySelectorAll('.grimorio-slot-toggle'));
      const hidden = column.querySelector(`input[name="grimorio_slots_used[${level}]"]`);

      slotColumns.set(level, { column, checkboxes, hidden, max });

      // Actualiza límites con lo que llega del servidor y lo que exista en el DOM.
      if (!state.slotLimits[level]) {
        state.slotLimits[level] = max;
      }

      const used = clamp(state.slotsUsed[level] || 0, 0, max);
      state.slotsUsed[level] = used;
      updateSlotCheckboxes(level, used);

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          const selected = checkboxes.filter((cb) => cb.checked).length;
          state.slotsUsed[level] = selected;
          if (hidden) hidden.value = selected;
          scheduleSlotSave(level, selected);
        });
      });
    });
  }

  function normalizeSlotLimits(limits) {
    const normalized = {};
    Object.keys(limits).forEach((key) => {
      const lvl = parseInt(key, 10);
      if (!Number.isNaN(lvl)) {
        normalized[lvl] = parseInt(limits[key], 10) || 0;
      }
    });
    return normalized;
  }

  function normalizePrepared(prepared) {
    const result = {};
    Object.keys(prepared).forEach((key) => {
      const level = parseInt(key, 10);
      if (Number.isNaN(level)) return;
      const list = Array.isArray(prepared[key]) ? prepared[key] : [];
      result[level] = list
        .map((name) => ({
          id: null,
          name: String(name),
          source: '',
          level,
        }))
        .filter((spell) => spell.name.trim().length > 0);
    });
    return result;
  }

  function renderPreparedView() {
    const totalPrepared = getTotalPreparedCount(state.prepared);
    const summary = document.getElementById('grimorio-prepared-total');
    if (summary) {
      if (state.preparedLimit) {
        summary.textContent = `Total: ${totalPrepared} / ${state.preparedLimit}`;
      } else {
        summary.textContent = `Total preparados: ${totalPrepared}`;
      }
    }

    document.querySelectorAll('.grimorio-prepared-block').forEach((block) => {
      const level = parseInt(block.dataset.level || '0', 10);
      const listEl = block.querySelector('.grimorio-prepared-block__list');
      const counterEl = block.querySelector('[data-counter-for]');
      const spells = state.prepared[level] || [];

      if (counterEl) {
        const max = state.slotLimits[level] || 0;
        counterEl.textContent = `${spells.length} / ${max}`;
      }

      if (!listEl) return;

      if (!spells.length) {
        listEl.innerHTML = '<li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay conjuros preparados.</li>';
        return;
      }

      listEl.innerHTML = spells
        .map((spell) => {
          const source = spell.source ? `<small class="grimorio-prepared-spell__source">${escapeHtml(spell.source)}</small>` : '';
          const concentrationClass = isSpellConcentration(spell, level) ? ' grimorio-prepared-spell--concentration' : '';
          return `
            <li class="grimorio-prepared-spell${concentrationClass}"
                data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                data-spell-name="${escapeAttr(spell.name)}"
                data-spell-level="${level}">
              <div class="grimorio-prepared-spell__info">
                <span class="grimorio-prepared-spell__name">${escapeHtml(spell.name)}</span>
                ${source}
              </div>
              <button type="button"
                      class="grimorio-cast-spell"
                      data-level="${level}"
                      data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                      data-spell-name="${escapeAttr(spell.name)}">
                Lanzar spell
              </button>
            </li>
          `;
        })
        .join('');
    });
    updateConcentrationControls();
  }

  function openSpellPicker() {
    if (!selectors.pickerModal) return;

    pickerState = clonePrepared(state.prepared);
    showModal(selectors.pickerModal);
    if (!state.spellsLoaded) {
      showPickerLoading(true);
      fetchSpellList().then(() => {
        buildSpellPickerUI();
      });
      return;
    }

    buildSpellPickerUI();
  }

  function showPickerLoading(isLoading) {
    if (!selectors.pickerLoading || !selectors.pickerLevels) return;
    selectors.pickerLoading.hidden = !isLoading;
    selectors.pickerLevels.hidden = isLoading;
  }

  function buildSpellPickerUI() {
    if (!selectors.pickerLevels) return;
    if (!state.spellsLoaded) return;

    const levels = getRelevantLevels();
    if (!levels.length) {
      selectors.pickerLevels.innerHTML = '<p>No hay niveles de conjuros configurados para este personaje.</p>';
      showPickerLoading(false);
      return;
    }

    const content = levels
      .map((level) => renderPickerLevel(level))
      .join('');

    selectors.pickerLevels.innerHTML = content || '<p>No hay conjuros disponibles para la clase seleccionada.</p>';
    selectors.pickerLevels.querySelectorAll('input[data-spell-id]').forEach((input) => {
      input.addEventListener('change', handlePickerToggle);
    });

    showPickerLoading(false);
  }

  function getRelevantLevels() {
    return Object.keys(state.slotLimits)
      .map((key) => parseInt(key, 10))
      .filter((lvl) => !Number.isNaN(lvl) && state.slotLimits[lvl] > 0)
      .sort((a, b) => a - b);
  }

  function renderPickerLevel(level) {
    const levelSlots = state.slotLimits[level] || 0;
    const spells = (state.spellsByLevel[level] || []).slice().sort((a, b) => a.name.localeCompare(b.name));
    const selected = pickerState[level] || [];
    const selectedIds = new Set(selected.map((spell) => spell.id || spell.name));
    const totalSelected = getTotalPreparedCount(pickerState);
    const limitReached = state.preparedLimit ? totalSelected >= state.preparedLimit : false;

    const disabledAll = levelSlots === 0;
    const listContent = spells.length
      ? spells
          .map((spell) => {
            const identifier = spell.id || spell.name;
            const checked = selectedIds.has(identifier);
            const shouldDisable = disabledAll || (!checked && limitReached);
            const summary = spell.source ? `<small>${escapeHtml(spell.source)}</small>` : '';
            const description = getSpellSummary(spell);
            const descriptionBlock = description
              ? `<small class="grimorio-spell-picker__desc">${escapeHtml(description)}</small>`
              : '';
            return `
              <label class="grimorio-spell-picker__item">
                <input type="checkbox"
                       value="${escapeAttr(identifier)}"
                       data-level="${level}"
                       data-spell-id="${escapeAttr(spell.id || '')}"
                       data-spell-name="${escapeAttr(spell.name)}"
                       ${checked ? 'checked' : ''}
                       ${shouldDisable ? 'disabled' : ''}>
                <span>
                  ${escapeHtml(spell.name)}
                  ${summary}
                  ${descriptionBlock}
                </span>
              </label>
            `;
          })
          .join('')
      : '<p class="grimorio-spell-picker__empty">No hay conjuros de este nivel para tu clase.</p>';

    const limitNote = state.preparedLimit
      ? `<p class="grimorio-spell-picker__limit">Total preparados: ${totalSelected} / ${state.preparedLimit}</p>`
      : `<p class="grimorio-spell-picker__limit">Conjuros preparados en este nivel: ${selected.length}</p>`;

    const slotNote = levelSlots
      ? `<small class="grimorio-spell-picker__slots">Espacios de nivel ${level}: ${levelSlots}</small>`
      : '<small class="grimorio-spell-picker__slots">Sin espacios de este nivel.</small>';

    return `
      <article class="grimorio-spell-picker__level" data-picker-level="${level}" data-max="${state.preparedLimit || ''}">
        <header class="grimorio-spell-picker__header">
          <h4>Nivel ${level}</h4>
          <span class="grimorio-spell-picker__count">${selected.length}</span>
        </header>
        ${limitNote}
        ${slotNote}
        <div class="grimorio-spell-picker__list">
          ${listContent}
        </div>
      </article>
    `;
  }

  function handlePickerToggle(event) {
    const input = event.target;
    if (!input || !input.dataset.level) return;

    const level = parseInt(input.dataset.level, 10);
    const identifier = input.value;
    const spellId = input.dataset.spellId || identifier;
    const spellName = input.dataset.spellName || identifier;
    const isChecked = input.checked;
    const max = state.slotLimits[level] || 0;
    let current = pickerState[level] ? [...pickerState[level]] : [];
    const currentTotal = getTotalPreparedCount(pickerState);

    const spellData = findSpellById(spellId) || { id: spellId, name: spellName, source: '', level };

    if (isChecked) {
      if (state.preparedLimit && currentTotal >= state.preparedLimit) {
        input.checked = false;
        openInfoModal(
          'Límite alcanzado',
          `<p>No puedes preparar más de ${state.preparedLimit} conjuros. Quita otro conjuro antes de añadir más.</p>`
        );
        return;
      }

      if (max === 0) {
        input.checked = false;
        openInfoModal('Sin espacios disponibles', `<p>No tienes espacios de nivel ${level}.</p>`);
        return;
      }

      if (!current.find((item) => item.id === spellData.id || item.name === spellData.name)) {
        current.push(mapSpellForState(spellData, level));
      }
      pickerState[level] = current;
    } else {
      current = current.filter((item) => item.id !== spellData.id && item.name !== spellData.name);
      pickerState[level] = current;
    }
    buildSpellPickerUI();
  }

  function saveSpellPickerSelection() {
    if (!pickerState) {
      closeModal(selectors.pickerModal);
      return;
    }

    const nextState = clonePrepared(pickerState);
    const previousState = clonePrepared(state.prepared);
    const previousConcentration = { ...state.concentration };

    state.prepared = nextState;
    if (!hasConcentrationSpell(nextState, previousConcentration)) {
      state.concentration = defaultConcentrationState();
    }
    renderPreparedView();
    closeModal(selectors.pickerModal);

    Promise.all([
      persistPreparedSpells(nextState),
      persistConcentration(state.concentration),
    ]).catch(() => {
      state.prepared = previousState;
      state.concentration = previousConcentration;
      renderPreparedView();
      openInfoModal('Error al guardar', '<p>No se pudo guardar la lista de conjuros. Intenta de nuevo.</p>');
    });
  }

  function handleCastSpell(level, spellId, fallbackName) {
    if (!level) return;
    const max = state.slotLimits[level] || 0;
    const used = state.slotsUsed[level] || 0;

    if (!max) {
      openInfoModal('Sin espacios disponibles', `<p>Tu personaje todavía no tiene espacios de conjuros de nivel ${level}.</p>`);
      return;
    }

    if (used >= max) {
      openInfoModal('Sin espacios disponibles', `<p>Has agotado los espacios de nivel ${level}. Recupera un descanso antes de lanzar más conjuros.</p>`);
      return;
    }

    const spell = findSpell(spellId, fallbackName);
    const context = extractSpellContext(spell);
    consumeSlot(level);

    const title = spell ? `Lanzamiento de ${spell.name}` : 'Lanzamiento de conjuro';
    openInfoModal(title, buildSpellContextHtml(spell, context));

    if (context.concentration) {
      setConcentrationState({
        level,
        spell_id: spell?.id || '',
        spell: spell?.name || fallbackName || '',
      });
    }
  }

  function resetSlots() {
    state.slotsUsed = Object.keys(state.slotLimits).reduce((acc, level) => {
      acc[level] = 0;
      return acc;
    }, {});

    slotColumns.forEach((record, level) => {
      if (record.hidden) record.hidden.value = 0;
      updateSlotCheckboxes(level, 0);
      persistSlot(level, 0);
    });
  }

  function resetPreparedSpells() {
    const empty = {};
    Object.keys(state.prepared).forEach((key) => {
      empty[key] = [];
    });
    const previousPrepared = clonePrepared(state.prepared);
    const previousConcentration = { ...state.concentration };

    state.prepared = empty;
    state.concentration = defaultConcentrationState();
    renderPreparedView();

    Promise.all([
      persistPreparedSpells(state.prepared),
      persistConcentration(state.concentration),
    ]).catch(() => {
      state.prepared = previousPrepared;
      state.concentration = previousConcentration;
      renderPreparedView();
      openInfoModal('Error al reiniciar', '<p>No se pudo reiniciar el grimorio. Intenta de nuevo.</p>');
    });
  }

  function consumeSlot(level) {
    const column = slotColumns.get(level);
    if (!column) return;
    const max = column.max || 0;
    const used = clamp((state.slotsUsed[level] || 0) + 1, 0, max);
    state.slotsUsed[level] = used;
    if (column.hidden) {
      column.hidden.value = used;
    }
    updateSlotCheckboxes(level, used);
    persistSlot(level, used);
  }

  function updateSlotCheckboxes(level, used) {
    const record = slotColumns.get(level);
    if (!record) return;
    record.checkboxes.forEach((checkbox, index) => {
      checkbox.checked = index < used;
    });
  }

  function persistSlot(level, used) {
    if (!window.GRIMORIO_DATA.nonce) return;
    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_spell_slots',
      nonce: window.GRIMORIO_DATA.nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      level,
      value: used,
    });

    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    }).catch(() => {
      console.warn('No se pudo guardar el estado de los slots de conjuro.');
    });
  }

  function applyServerSlots(slots) {
    if (!slots) return;
    Object.keys(slots).forEach((key) => {
      const level = parseInt(key, 10);
      if (Number.isNaN(level)) return;
      const max = state.slotLimits[level] || 0;
      const used = clamp(parseInt(slots[key], 10) || 0, 0, max);
      state.slotsUsed[level] = used;
      const column = slotColumns.get(level);
      if (column) {
        if (column.hidden) {
          column.hidden.value = used;
        }
        updateSlotCheckboxes(level, used);
      }
    });
  }

  function scheduleSlotSave(level, used) {
    if (!window.GRIMORIO_DATA.nonce) return;
    if (slotSaveTimers[level]) {
      clearTimeout(slotSaveTimers[level]);
    }

    slotSaveTimers[level] = setTimeout(() => {
      persistSlot(level, used);
    }, 350);
  }

  function fetchSpellList() {
    if (!window.GRIMORIO_DATA.class_id) {
      if (selectors.pickerLevels) {
        selectors.pickerLevels.innerHTML = '<p>Selecciona una clase en la hoja de personaje para cargar los conjuros.</p>';
      }
      showPickerLoading(false);
      return Promise.resolve();
    }

    if (state.spellsLoaded) {
      return Promise.resolve();
    }

    if (spellsPromise) {
      return spellsPromise;
    }

    const payload = new URLSearchParams({
      action: 'drak_dnd5_get_spells',
      class_id: window.GRIMORIO_DATA.class_id,
    });

    spellsPromise = fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error('No se pudieron cargar los conjuros.');
        }
        const spells = json.data?.spells || [];
        indexSpells(spells);
        hydratePreparedWithSpellData();
        renderPreparedView();
        state.spellsLoaded = true;
        return spells;
      })
      .catch((error) => {
        console.warn(error);
        showPickerLoading(false);
        openInfoModal('Error al cargar conjuros', '<p>No se pudo obtener la lista de conjuros. Revisa tu conexión.</p>');
        spellsPromise = null;
        state.spellsLoaded = false;
        throw error;
      });

    return spellsPromise;
  }

  function openTransformationModal() {
    if (state.transformation.active) {
      openInfoModal('Transformación activa', '<p>Finaliza la transformación actual antes de iniciar otra.</p>');
      return;
    }

    const options = getAvailableTransformationLevels();
    if (!options.length) {
      openInfoModal('Sin espacios disponibles', '<p>No quedan espacios de conjuro libres.</p>');
      return;
    }

    if (selectors.transformModalLevels) {
      selectors.transformModalLevels.innerHTML = options
        .map(
          (entry) => `
            <label class="grimorio-transformation-level">
              <input type="radio" name="grimorio_transform_slot" value="${entry.level}">
              <span>
                Nivel ${entry.level}
                <small>${entry.remaining} de ${entry.max} espacios disponibles</small>
              </span>
            </label>
          `
        )
        .join('');
    }

    showModal(selectors.transformModal);
  }

  function confirmTransformationSelection() {
    if (!selectors.transformModalLevels) return;
    const selected = selectors.transformModalLevels.querySelector('input[name="grimorio_transform_slot"]:checked');
    if (!selected) {
      openInfoModal('Selecciona un nivel', '<p>Elige un nivel de slot para activar la transformación.</p>');
      return;
    }
    const level = parseInt(selected.value, 10);
    if (Number.isNaN(level)) return;
    activateTransformation(level);
  }

  function getAvailableTransformationLevels() {
    const result = [];
    Object.keys(state.slotLimits).forEach((key) => {
      const level = parseInt(key, 10);
      if (Number.isNaN(level) || level <= 0) return;
      const max = state.slotLimits[level] || 0;
      if (!max) return;
      const used = state.slotsUsed[level] || 0;
      const remaining = max - used;
      if (remaining > 0) {
        result.push({ level, remaining, max });
      }
    });
    return result.sort((a, b) => a.level - b.level);
  }

  function activateTransformation(level) {
    if (!window.GRIMORIO_DATA.transformation_nonce) {
      openInfoModal('Acción no disponible', '<p>No se pudo verificar la solicitud. Recarga la página.</p>');
      return;
    }
    if (selectors.transformModalConfirm) {
      selectors.transformModalConfirm.disabled = true;
    }

    requestTransformationActivation(level)
      .then((payload) => {
        applyServerSlots(payload.slots_used || {});
        state.transformation = normalizeTransformationState(payload.transformation);
        renderTransformationBlock();
        closeModal(selectors.transformModal);
        openInfoModal('Transformación activada', `<p>Has consumido un slot de nivel ${level}. Recuerda finalizarla tras 1 minuto.</p>`);
      })
      .catch((error) => {
        const message = error?.message || 'No se pudo activar la transformación.';
        openInfoModal('Error', `<p>${message}</p>`);
      })
      .finally(() => {
        if (selectors.transformModalConfirm) {
          selectors.transformModalConfirm.disabled = false;
        }
      });
  }

  function finishTransformation() {
    if (!state.transformation.active) return;
    if (!window.GRIMORIO_DATA.transformation_nonce) {
      openInfoModal('Acción no disponible', '<p>No se pudo verificar la solicitud. Recarga la página.</p>');
      return;
    }
    if (selectors.transformFinishBtn) {
      selectors.transformFinishBtn.disabled = true;
    }
    requestTransformationFinish()
      .then((payload) => {
        state.transformation = normalizeTransformationState(payload.transformation);
        renderTransformationBlock();
      })
      .catch((error) => {
        const message = error?.message || 'No se pudo finalizar la transformación.';
        openInfoModal('Error', `<p>${message}</p>`);
      })
      .finally(() => {
        if (selectors.transformFinishBtn) {
          selectors.transformFinishBtn.disabled = false;
        }
      });
  }

  function requestTransformationActivation(level) {
    const payload = new URLSearchParams({
      action: 'drak_dnd5_activate_transformation',
      nonce: window.GRIMORIO_DATA.transformation_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      slot_level: level,
    });

    return fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          const message = json?.data?.message || 'No se pudo activar la transformación.';
          throw new Error(message);
        }
        return json.data || {};
      });
  }

  function requestTransformationFinish() {
    const payload = new URLSearchParams({
      action: 'drak_dnd5_finish_transformation',
      nonce: window.GRIMORIO_DATA.transformation_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
    });

    return fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          const message = json?.data?.message || 'No se pudo finalizar la transformación.';
          throw new Error(message);
        }
        return json.data || {};
      });
  }

  function renderTransformationBlock() {
    if (!selectors.transformDisplay) return;
    const active = Boolean(state.transformation.active && state.transformation.slotLevel);
    if (selectors.transformStartBtn) {
      selectors.transformStartBtn.disabled = active;
    }
    if (selectors.transformFinishBtn) {
      selectors.transformFinishBtn.disabled = !active;
    }

    if (!active) {
      selectors.transformDisplay.innerHTML =
        '<p class="grimorio-transformation__empty">No hay una transformación activa.</p>';
      return;
    }

    const stats = computeTransformationStats(state.transformation.slotLevel);
    const startedInfo = stats.startedAt ? `<p class="grimorio-transformation-card__meta">Inicio: ${formatTimestamp(stats.startedAt)}</p>` : '';
    selectors.transformDisplay.innerHTML = `
      <div class="grimorio-transformation-card">
        <div class="grimorio-transformation-card__media" aria-hidden="true"></div>
        <div class="grimorio-transformation-card__content">
          <header>
            <h4>Transformación activa</h4>
            <p>Slot de nivel ${stats.slotLevel}</p>
            ${startedInfo}
          </header>
          <ul class="grimorio-transformation-card__stats">
            <li><strong>Fuerza:</strong> ${stats.strValue} (${formatMod(stats.strMod)})</li>
            <li><strong>Inteligencia:</strong> ${stats.intValue} (${formatMod(stats.intMod)})</li>
            <li><strong>AC:</strong> ${stats.ac}</li>
            <li><strong>Velocidad:</strong> ${stats.speed} ft</li>
            <li><strong>Regeneración:</strong> ${stats.regen} por turno</li>
            <li><strong>Puntos de golpe temporales:</strong> ${stats.tempHp}</li>
            <li><strong>Ataque natural:</strong> ${stats.attackDamage}</li>
            <li><strong>Darkvision:</strong> 120 ft</li>
            <li><strong>Tamaño:</strong> Large (salto x2, carga como un tamaño adicional)</li>
            <li><strong>Restricciones:</strong> No puede lanzar ni mantener concentración en conjuros.</li>
          </ul>
          <p class="grimorio-transformation-card__note">
            Duración: 1 minuto o hasta caer a 0 PV o quedar incapacitado.
          </p>
        </div>
      </div>
    `;
  }

  function computeTransformationStats(slotLevel) {
    const baseStr = Number.isFinite(state.abilities.str) ? state.abilities.str : 10;
    const baseInt = Number.isFinite(state.abilities.int) ? state.abilities.int : 10;
    const swappedStr = Number.isFinite(state.abilities.int) ? state.abilities.int : baseStr;
    const swappedInt = Number.isFinite(state.abilities.str) ? state.abilities.str : baseInt;
    const strMod = abilityMod(swappedStr);
    const intMod = abilityMod(swappedInt);
    const apothecaryLevel = state.apothecaryLevel || state.level || 1;
    const tempHp = apothecaryLevel * 5;
    const speed = (state.baseSpeed || 0) + slotLevel * 5;
    const ac = 13 + slotLevel;
    const regen = slotLevel;
    const attackDamage = `1d10 ${formatMod(strMod)} + ${apothecaryLevel}`;

    return {
      slotLevel,
      strValue: swappedStr,
      intValue: swappedInt,
      strMod,
      intMod,
      tempHp,
      speed,
      ac,
      regen,
      attackDamage,
      startedAt: state.transformation.startedAt || null,
    };
  }

  function indexSpells(spells) {
    state.spellsByLevel = {};
    state.spellIndexById = {};
    state.spellIndexByName = {};

    spells.forEach((spell) => {
      const level = typeof spell.level === 'number' ? spell.level : 0;
      if (!state.spellsByLevel[level]) {
        state.spellsByLevel[level] = [];
      }
      state.spellsByLevel[level].push(spell);
      if (spell.id) {
        state.spellIndexById[spell.id] = spell;
      }
      if (spell.name) {
        state.spellIndexByName[spell.name.toLowerCase()] = spell;
      }
    });
  }

  function hydratePreparedWithSpellData() {
    Object.keys(state.prepared).forEach((key) => {
      const level = parseInt(key, 10);
      state.prepared[level] = (state.prepared[level] || []).map((spell) => {
        const ref = findSpell(spell.id, spell.name);
        if (!ref) {
          return spell;
        }
        return mapSpellForState(ref, level);
      });
    });
  }

  function findSpell(spellId, fallbackName) {
    if (spellId && state.spellIndexById[spellId]) {
      return state.spellIndexById[spellId];
    }
    if (fallbackName) {
      return state.spellIndexByName[fallbackName.toLowerCase()] || null;
    }
    return null;
  }

  function findSpellById(spellId) {
    if (!spellId) return null;
    return state.spellIndexById[spellId] || null;
  }

  function mapSpellForState(spell, level) {
    return {
      id: spell.id || null,
      name: spell.name || 'Conjuro',
      source: spell.source || '',
      level,
    };
  }

  function persistPreparedSpells(prepared) {
    if (!window.GRIMORIO_DATA.prepared_nonce) {
      return Promise.resolve();
    }

    const serialized = serializePrepared(prepared);
    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_prepared_spells',
      nonce: window.GRIMORIO_DATA.prepared_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      prepared: JSON.stringify(serialized),
    });

    return fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error('No se pudo guardar el grimorio.');
        }
        return json;
      });
  }

  function serializePrepared(prepared) {
    const result = {};
    Object.keys(prepared).forEach((key) => {
      const level = parseInt(key, 10);
      if (Number.isNaN(level)) return;
      const names = (prepared[level] || []).map((spell) => spell.name).filter(Boolean);
      if (names.length) {
        result[level] = names;
      }
    });
    return result;
  }

  function extractSpellContext(spell) {
    if (!spell) {
      return {
        concentration: false,
        savingThrows: [],
        conditions: [],
        text: '',
      };
    }

    const concentration = Array.isArray(spell.duration)
      ? spell.duration.some((entry) => entry && typeof entry === 'object' && entry.concentration)
      : false;

    const textBlocks = [];
    (spell.entries || []).forEach((entry) => flattenSpellEntry(entry, textBlocks));
    const rawText = textBlocks.join('\n');

    const savingThrows = extractMatches(rawText, /\{@savingThrow ([^}|]+)(?:\|[^}]*)?\}/gi);
    const conditions = extractMatches(rawText, /\{@condition ([^}|]+)(?:\|[^}]*)?\}/gi);

    return {
      concentration,
      savingThrows,
      conditions,
      text: rawText,
    };
  }

  function flattenSpellEntry(entry, output) {
    if (!entry) return;
    if (typeof entry === 'string') {
      output.push(entry);
      return;
    }
    if (Array.isArray(entry)) {
      entry.forEach((item) => flattenSpellEntry(item, output));
      return;
    }
    if (entry.entries) {
      flattenSpellEntry(entry.entries, output);
    }
    if (entry.items) {
      flattenSpellEntry(entry.items, output);
    }
    if (entry.entry) {
      flattenSpellEntry(entry.entry, output);
    }
  }

  function getSpellSummary(spell) {
    if (!spell || !spell.entries) return '';
    const chunks = [];
    flattenSpellEntry(spell.entries, chunks);
    if (!chunks.length) return '';
    const text = chunks.join(' ').replace(/\s+/g, ' ').trim();
    if (!text) return '';
    return text.length > 180 ? `${text.slice(0, 177).trim()}...` : text;
  }

  function extractMatches(text, regex) {
    if (!text) return [];
    const matches = [];
    let match;
    while ((match = regex.exec(text)) !== null) {
      matches.push(match[1]);
    }
    return Array.from(new Set(matches));
  }

  function buildSpellContextHtml(spell, context) {
    const rows = [];
    rows.push(`<li><strong>Concentración:</strong> ${context.concentration ? 'Sí' : 'No'}</li>`);

    if (context.savingThrows.length) {
      const list = context.savingThrows.map(formatSavingThrow).join(', ');
      const dcNote = `DC estimada: 8 + bono de competencia + modificador de ${formatSavingThrowShort(context.savingThrows[0])}.`;
      rows.push(`
        <li>
          <strong>Tirada de salvación:</strong> ${list}
          <div class="grimorio-cast-summary__note">${escapeHtml(dcNote)}</div>
        </li>
      `);
    }

    if (context.conditions.length) {
      const tags = context.conditions.map((cond) => `<span class="grimorio-tag">${escapeHtml(cond)}</span>`).join(' ');
      rows.push(`<li><strong>Condiciones:</strong> ${tags}</li>`);
    }

    if (spell?.source) {
      rows.push(`<li><strong>Fuente:</strong> ${escapeHtml(spell.source)}</li>`);
    }

    const summaryText = context.text ? escapeHtml(context.text.split('\n')[0]).slice(0, 400) : '';
    const summaryParagraph = summaryText ? `<p class="grimorio-cast-summary__text">${summaryText}</p>` : '';

    return `
      <ul class="grimorio-cast-summary">
        ${rows.join('')}
      </ul>
      ${summaryParagraph}
    `;
  }

  function formatSavingThrow(value) {
    return escapeHtml(value.replace(/^\w/, (c) => c.toUpperCase()));
  }

  function formatSavingThrowShort(value) {
    if (!value) return 'la característica adecuada';
    const lower = value.toLowerCase();
    const map = {
      strength: 'Fuerza',
      dexterity: 'Destreza',
      constitution: 'Constitución',
      intelligence: 'Inteligencia',
      wisdom: 'Sabiduría',
      charisma: 'Carisma',
      str: 'Fuerza',
      dex: 'Destreza',
      con: 'Constitución',
      int: 'Inteligencia',
      wis: 'Sabiduría',
      cha: 'Carisma',
    };
    return map[lower] || value;
  }

  function openInfoModal(title, html) {
    if (!selectors.infoModal) return;
    if (selectors.infoTitle) {
      selectors.infoTitle.textContent = title || 'Información';
    }
    if (selectors.infoBody) {
      selectors.infoBody.innerHTML = html || '<p>Sin contenido.</p>';
    }
    showModal(selectors.infoModal);
  }

  function showModal(modal) {
    if (!modal) return;
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal(modal) {
    if (!modal) return;
    const active = document.activeElement;
    if (active && modal.contains(active)) {
      active.blur();
    }
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
    if (modal === selectors.pickerModal) {
      pickerState = null;
    }
  }

  function clonePrepared(prepared) {
    const clone = {};
    Object.keys(prepared).forEach((key) => {
      clone[key] = (prepared[key] || []).map((spell) => ({ ...spell }));
    });
    return clone;
  }

  function cloneObject(obj) {
    return JSON.parse(JSON.stringify(obj || {}));
  }

  function getTotalPreparedCount(map) {
    const source = map || state.prepared;
    return Object.values(source).reduce((sum, list) => sum + (Array.isArray(list) ? list.length : 0), 0);
  }

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttr(text) {
    return escapeHtml(text).replace(/`/g, '&#096;');
  }

  function defaultConcentrationState() {
    return { level: null, spell: '', spell_id: '' };
  }

  function normalizeConcentrationState(raw) {
    if (!raw || typeof raw !== 'object') return defaultConcentrationState();
    const level = Number.isInteger(raw.level) ? raw.level : null;
    const spell = typeof raw.spell === 'string' ? raw.spell : '';
    const spellId = typeof raw.spell_id === 'string' ? raw.spell_id : '';
    if (level === null || (!spell && !spellId)) {
      return defaultConcentrationState();
    }
    return { level, spell, spell_id: spellId };
  }

  function isSpellConcentration(spell, level) {
    if (!state.concentration || state.concentration.level === null) return false;
    if (parseInt(level, 10) !== parseInt(state.concentration.level, 10)) return false;
    if (state.concentration.spell_id && spell.id) {
      return state.concentration.spell_id === spell.id;
    }
    return (spell.name || '').toLowerCase() === (state.concentration.spell || '').toLowerCase();
  }

  function updateConcentrationControls() {
    if (!selectors.finishConcentrationBtn) return;
    const active =
      state.concentration &&
      state.concentration.level !== null &&
      (state.concentration.spell || state.concentration.spell_id);
    selectors.finishConcentrationBtn.disabled = !active;
  }

  function setConcentrationState(newState, options = {}) {
    const prev = { ...state.concentration };
    if (newState && typeof newState === 'object') {
      state.concentration = {
        level: Number.isInteger(newState.level) ? newState.level : null,
        spell: newState.spell || '',
        spell_id: newState.spell_id || '',
      };
    } else {
      state.concentration = defaultConcentrationState();
    }

    if (!options.skipRender) {
      renderPreparedView();
    } else {
      updateConcentrationControls();
    }

    if (options.persist === false) {
      return Promise.resolve();
    }

    return persistConcentration(state.concentration).catch(() => {
      state.concentration = prev;
      if (!options.skipRender) {
        renderPreparedView();
      } else {
        updateConcentrationControls();
      }
      openInfoModal('Error de concentración', '<p>No se pudo actualizar el estado de concentración.</p>');
    });
  }

  function persistConcentration(value) {
    if (!window.GRIMORIO_DATA.concentration_nonce) {
      return Promise.resolve();
    }

    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_concentration_state',
      nonce: window.GRIMORIO_DATA.concentration_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      state: JSON.stringify(value || defaultConcentrationState()),
    });

    return fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error('No se pudo actualizar la concentración.');
        }
        return json;
      });
  }

  function hasConcentrationSpell(prepared, concentration) {
    if (!concentration || concentration.level === null) return false;
    const list = prepared[concentration.level] || [];
    return list.some((spell) => {
      if (concentration.spell_id && spell.id) {
        return concentration.spell_id === spell.id;
      }
      return (spell.name || '').toLowerCase() === (concentration.spell || '').toLowerCase();
    });
  }

  function abilityMod(score) {
    if (!Number.isFinite(score)) return 0;
    return Math.floor((score - 10) / 2);
  }

  function formatMod(value) {
    const num = Number(value) || 0;
    return num >= 0 ? `+${num}` : `${num}`;
  }

  function defaultTransformationState() {
    return { active: false, slotLevel: null, startedAt: null };
  }

  function normalizeTransformationState(raw) {
    if (!raw || !raw.active || !raw.slot_level) {
      return defaultTransformationState();
    }
    return {
      active: true,
      slotLevel: parseInt(raw.slot_level, 10),
      startedAt: raw.started_at ? parseInt(raw.started_at, 10) : null,
    };
  }

  function normalizeAbilities(raw) {
    if (!raw || typeof raw !== 'object') {
      return {};
    }
    const map = {};
    ['str', 'dex', 'con', 'int', 'wis', 'cha'].forEach((ability) => {
      const value = raw[ability];
      const parsed = Number.isFinite(value) ? value : parseInt(value, 10);
      map[ability] = Number.isNaN(parsed) ? null : parsed;
    });
    return map;
  }

  function formatTimestamp(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp * 1000);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString();
  }

  function collectGrimoireContext() {
    return {
      level: state.level || 1,
      classId: state.classId || (window.GRIMORIO_DATA?.class_id || ''),
      subclassId: window.GRIMORIO_DATA?.subclass_id || '',
      prefetchedReference: window.GRIMORIO_DATA?.class_reference || null,
      esotericTheories: window.GRIMORIO_DATA?.esoteric_theories || [],
    };
  }

  function refreshClassReferenceForGrimoire() {
    const classReferenceState = window.classReferenceState || null;
    if (!classReferenceState?.container) return;

    const context = collectGrimoireContext();
    if (!context.classId) return;

    const loader =
      typeof window.loadCharacterData === 'function'
        ? window.loadCharacterData()
        : Promise.resolve();

    loader
      .catch(() => null)
      .then(() => {
        refreshClassReferenceModule(context);
      });
  }
})();
