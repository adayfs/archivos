(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.GRIMORIO_DATA === 'undefined') return;
    initGrimorio();
  });

  const slotSaveTimers = {};
  const slotColumns = new Map();
  let pickerState = null;
  let pickerOnlyCantrips = false;
  let spellsPromise = null;
  let classReferenceInitialized = false;

  const state = {
    slotsUsed: {},
    slotLimits: {},
    baseSlotLimits: {},
    apothecarySlots: null, // { max, current, slotLevel, recovery: 'short_rest' }
    prepared: {},
    cantrips: [],
    preparedLimit: null,
    cantripLimit: null,
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
    spellModel: 'default',
    greaterFormulas: [], // [{id,name,level,usesMax,usesCurrent}]
    alwaysPrepared: [],
    sorcery: {
      isSorcerer: false,
      pointsMax: 0,
      pointsCurrent: 0,
      flexSlots: {},
      slotCosts: {},
      metamagicKnown: [],
      metamagicLimit: 0,
    },
    metamagicCatalog: [],
    pendingCast: null,
  };

  const selectors = {};
  const sorceryFlags = {
    bound: false,
    updating: false,
  };
  if (typeof window !== 'undefined') {
    window.__grimorioSorceryFlags = sorceryFlags;
  }
  let metamagicCastSelection = new Set();

  function initGrimorio() {
    selectors.preparedContainer = document.querySelector('.grimorio-prepared');
    selectors.autoPreparedContainer = document.querySelector('.grimorio-auto-prepared');
    selectors.editButton = document.querySelector('.grimorio-prepared__edit-btn');
    selectors.resetSlotsBtn = document.querySelector('.grimorio-reset-slots');
    selectors.resetPreparedBtn = document.querySelector('.grimorio-reset-prepared');
    selectors.pickerModal = document.getElementById('grimorio-spell-picker');
    selectors.pickerLevels = document.getElementById('grimorio-spell-picker-levels');
    selectors.pickerLoading = document.getElementById('grimorio-spell-picker-loading');
    selectors.pickerSaveBtn = document.getElementById('grimorio-spell-picker-save');
    selectors.cantripEditBtn = document.getElementById('grimorio-cantrip-edit');
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
    selectors.sorcerySection = document.getElementById('grimorio-sorcery');
    selectors.sorceryCounter = document.getElementById('grimorio-sorcery-counter');
    selectors.sorceryFlex = document.getElementById('grimorio-sorcery-flex');
    selectors.sorceryKnown = document.getElementById('grimorio-sorcery-known');
    selectors.sorceryConvertBtn = document.getElementById('grimorio-sorcery-convert');
    selectors.metamagicManageBtn = document.getElementById('grimorio-metamagic-manage-btn');
    selectors.sorceryModal = document.getElementById('grimorio-sorcery-modal');
    selectors.sorcerySlotSelect = document.getElementById('grimorio-sorcery-slot-select');
    selectors.sorceryCreateSelect = document.getElementById('grimorio-sorcery-create-select');
    selectors.sorceryCreateHint = document.getElementById('grimorio-sorcery-create-hint');
    selectors.sorcerySlotConfirm = document.getElementById('grimorio-sorcery-slot-confirm');
    selectors.sorceryCreateConfirm = document.getElementById('grimorio-sorcery-create-confirm');
    selectors.metamagicManageModal = document.getElementById('grimorio-metamagic-manage-modal');
    selectors.metamagicOptionsContainer = document.getElementById('grimorio-metamagic-options');
    selectors.metamagicLimit = document.getElementById('grimorio-metamagic-limit');
    selectors.metamagicSave = document.getElementById('grimorio-metamagic-save');
    selectors.metamagicCastModal = document.getElementById('grimorio-metamagic-cast');
    selectors.metamagicCastOptions = document.getElementById('grimorio-metamagic-cast-options');
    selectors.metamagicCastHint = document.getElementById('grimorio-metamagic-cast-hint');
    selectors.metamagicApply = document.getElementById('grimorio-metamagic-apply');
    selectors.metamagicKnownList = document.getElementById('grimorio-sorcery-known');
    selectors.sorceryColumn = document.querySelector('.grimorio-slot-column--sorcery');

    hydrateState();
    initSlots();
    initSorceryModule();
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

  function normalizeCantrips(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) {
      return raw
        .map((entry) => {
          if (entry && typeof entry === 'object') {
            const name = entry.name || entry;
            const source = entry.source || '';
            const id = entry.id || null;
            return { id, name: String(name), source: String(source), level: 0 };
          }
          return { id: null, name: String(entry), source: '', level: 0 };
        })
        .filter((c) => c.name.trim().length > 0);
    }
    if (typeof raw === 'string' && raw) {
      return raw
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
        .map((name) => ({ id: null, name, source: '', level: 0 }));
    }
    return [];
  }

  function hydrateState() {
    const data = window.GRIMORIO_DATA;
    state.slotsUsed = cloneObject(data.slots_used || {});
    state.baseSlotLimits = normalizeSlotLimits(data.slot_limits || {});
    state.slotLimits = { ...state.baseSlotLimits };
    if (data.apothecary_slots) {
      state.apothecarySlots = {
        max: Number.parseInt(data.apothecary_slots.max || '0', 10) || 0,
        current: Number.parseInt(data.apothecary_slots.current || '0', 10) || 0,
        slotLevel: Number.parseInt(data.apothecary_slots.slot_level || '1', 10) || 1,
        recovery: data.apothecary_slots.recovery || 'short_rest',
      };
    }
    state.prepared = normalizePrepared(data.prepared || {});
    const rawCantrips = Array.isArray(data.cantrips) && data.cantrips.length ? data.cantrips : (state.prepared[0] || []);
    state.cantrips = normalizeCantrips(rawCantrips);
    delete state.prepared[0];
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
    state.spellModel = data.spell_model || 'default';
    if (state.spellModel === 'apothecary') {
      state.preparedLimit = computeApothecaryPreparedLimit(state.apothecaryLevel, state.abilities.int);
    }
    state.greaterFormulas = Array.isArray(data.greater_formulas) ? data.greater_formulas : [];
    state.alwaysPrepared = Array.isArray(data.always_prepared) ? data.always_prepared : [];
    const cantripProg = data.class_reference?.cantrip_progression || {};
    const cantripLimit = cantripProg?.[state.level] ?? null;
    state.cantripLimit = Number.isFinite(cantripLimit) && cantripLimit > 0 ? cantripLimit : null;
    if (!state.cantripLimit && cantripProg) {
      const fallback = cantripProg[state.level] ?? null;
      if (Number.isFinite(fallback) && fallback > 0) {
        state.cantripLimit = fallback;
      }
    }
    const sorcery = data.sorcery || null;
    if (sorcery) {
      state.sorcery = {
        isSorcerer: true,
        pointsMax: Number.parseInt(sorcery.points_max || '0', 10) || 0,
        pointsCurrent: Number.parseInt(sorcery.points_current || '0', 10) || 0,
        flexSlots: normalizeSlotLimits(sorcery.flex_slots || {}),
        slotCosts: sorcery.slot_costs || {},
        metamagicKnown: Array.isArray(sorcery.metamagic_known) ? sorcery.metamagic_known : [],
        metamagicLimit: Number.parseInt(sorcery.metamagic_limit || '0', 10) || 0,
      };
      Object.keys(state.sorcery.flexSlots).forEach((lvlKey) => {
        const lvl = parseInt(lvlKey, 10);
        if (Number.isNaN(lvl)) return;
        const extra = state.sorcery.flexSlots[lvl] || 0;
        state.slotLimits[lvl] = (state.slotLimits[lvl] || 0) + extra;
      });
    }
    state.metamagicCatalog = buildMetamagicCatalog();
  }

  function bindEvents() {
    if (selectors.editButton) {
      selectors.editButton.addEventListener('click', openSpellPicker);
    }
    if (selectors.cantripEditBtn) {
      selectors.cantripEditBtn.addEventListener('click', openCantripPicker);
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

    bindSpellListInteractions(selectors.preparedContainer);
    bindSpellListInteractions(selectors.autoPreparedContainer);

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

    if (state.sorcery.isSorcerer) {
      if (selectors.sorceryConvertBtn) {
        selectors.sorceryConvertBtn.addEventListener('click', () => openSorceryModal('convert'));
      }
      if (selectors.sorcerySlotConfirm) {
        selectors.sorcerySlotConfirm.addEventListener('click', confirmSlotToPoints);
      }
      if (selectors.sorceryCreateConfirm) {
        selectors.sorceryCreateConfirm.addEventListener('click', confirmCreateSlot);
      }
      if (selectors.metamagicManageBtn) {
        selectors.metamagicManageBtn.addEventListener('click', openMetamagicManageModal);
      }
      if (selectors.metamagicKnownList) {
        selectors.metamagicKnownList.addEventListener('click', handleKnownMetamagicAction);
      }
      bindSorceryCheckboxes();
      if (selectors.metamagicSave) {
        selectors.metamagicSave.addEventListener('click', saveMetamagicKnown);
      }
      if (selectors.metamagicApply) {
        selectors.metamagicApply.addEventListener('click', applyMetamagicSelection);
      }
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

  function initSorceryModule() {
    if (!state.sorcery.isSorcerer || !selectors.sorcerySection) return;
    renderSorceryBlock();
  }

  function initSlots() {
    // Modelo especial: Apotecario usa bloque único de slots
    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      // Limpia cualquier grid estándar que venga renderizado desde el servidor.
      const grid = document.querySelector('.grimorio-slot-grid__inner');
      if (grid) {
        grid.remove();
      }
      renderApothecarySlotsBlock();
      return;
    }

    document.querySelectorAll('.grimorio-slot-column').forEach((column) => {
      const level = parseInt(column.dataset.level || '0', 10);
      const baseMax = parseInt(column.dataset.max || '0', 10) || 0;
      if (column.dataset.sorcery === '1') {
        return;
      }
      if (!level) return;

      const checkboxes = Array.from(column.querySelectorAll('.grimorio-slot-toggle'));
      const hidden = column.querySelector(`input[name="grimorio_slots_used[${level}]"]`);

      slotColumns.set(level, { column, checkboxes, hidden, max: baseMax });

      if (!state.baseSlotLimits[level]) {
        state.baseSlotLimits[level] = baseMax;
      }
      const totalLimit = state.slotLimits[level] || baseMax;
      state.slotLimits[level] = totalLimit;
      slotColumns.get(level).max = totalLimit;
      column.dataset.max = totalLimit;

      const used = clamp(state.slotsUsed[level] || 0, 0, totalLimit);
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

    applyFlexSlotsToColumns();
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

  function ensureSlotColumn(level) {
    if (slotColumns.has(level)) {
      return slotColumns.get(level);
    }
    const grid = document.querySelector('.grimorio-slot-grid__inner');
    const currentLimit = state.slotLimits[level] || state.baseSlotLimits[level] || 0;
    if (!grid || currentLimit <= 0) return null;

    const column = document.createElement('div');
    column.className = 'grimorio-slot-column';
    column.dataset.level = level;
    column.dataset.max = currentLimit;
    const checkboxesHtml = Array.from({ length: currentLimit }, () => `
      <label>
        <input type="checkbox" class="grimorio-slot-toggle">
        <span></span>
      </label>
    `).join('');
    column.innerHTML = `
      <header>
        <span>Nivel ${level}</span>
        <small>${currentLimit} slots</small>
      </header>
      <div class="grimorio-slot-checkboxes">${checkboxesHtml}</div>
      <input type="hidden" name="grimorio_slots_used[${level}]" value="0">
    `;
    grid.appendChild(column);

    const checkboxes = Array.from(column.querySelectorAll('.grimorio-slot-toggle'));
    const hidden = column.querySelector(`input[name="grimorio_slots_used[${level}]"]`);
    const record = { column, checkboxes, hidden, max: currentLimit };
    slotColumns.set(level, record);

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const selected = checkboxes.filter((cb) => cb.checked).length;
        state.slotsUsed[level] = selected;
        if (hidden) hidden.value = selected;
        scheduleSlotSave(level, selected);
      });
    });

    return record;
  }

  function resizeSlotColumn(level, newLimit) {
    const record = ensureSlotColumn(level);
    if (!record) return;
    const { column } = record;
    const container = column.querySelector('.grimorio-slot-checkboxes');
    if (!container) return;
    const used = clamp(state.slotsUsed[level] || 0, 0, newLimit);
    state.slotsUsed[level] = used;

    while (record.checkboxes.length > newLimit) {
      const checkbox = record.checkboxes.pop();
      const label = checkbox.closest('label');
      if (label && label.parentNode) {
        label.parentNode.removeChild(label);
      }
    }
    while (record.checkboxes.length < newLimit) {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.classList.add('grimorio-slot-toggle');
      const span = document.createElement('span');
      label.appendChild(input);
      label.appendChild(span);
      container.appendChild(label);
      record.checkboxes.push(input);
      input.addEventListener('change', () => {
        const selected = record.checkboxes.filter((cb) => cb.checked).length;
        state.slotsUsed[level] = selected;
        if (record.hidden) record.hidden.value = selected;
        scheduleSlotSave(level, selected);
      });
    }

    record.max = newLimit;
    column.dataset.max = newLimit;
    const headerSmall = column.querySelector('header small');
    if (headerSmall) {
      headerSmall.textContent = `${newLimit} slots`;
    }
    updateSlotCheckboxes(level, used);
    if (record.hidden) {
      record.hidden.value = used;
    }
  }

  function applyFlexSlotsToColumns() {
    if (!state.sorcery.isSorcerer) return;
    Object.keys(state.sorcery.flexSlots || {}).forEach((lvlKey) => {
      const level = parseInt(lvlKey, 10);
      if (Number.isNaN(level)) return;
      const extra = state.sorcery.flexSlots[level] || 0;
      const base = state.baseSlotLimits[level] || 0;
      const total = base + extra;
      if (total > 0) {
        state.slotLimits[level] = total;
        resizeSlotColumn(level, total);
      }
    });
  }

  function renderApothecarySlotsBlock() {
    const container = document.querySelector('.grimorio-slots');
    if (!container) return;
    const slots = state.apothecarySlots;
    const current = Math.max(0, Math.min(slots.current, slots.max));
    const level = slots.slotLevel || 1;
    const used = clamp(slots.max - current, 0, slots.max);

    const boxes = Array.from({ length: slots.max }, (_, idx) => {
      const checked = idx < used ? 'checked' : '';
      return `
        <label>
          <input type="checkbox" class="grimorio-slot-toggle" ${checked}>
          <span></span>
        </label>
      `;
    }).join('');

    container.innerHTML = `
      <div class="grimorio-slot-grid__inner">
        <div class="grimorio-slot-column" data-level="${level}" data-max="${slots.max}">
          <header>
            <span>Nivel ${level}</span>
            <small>${slots.max} espacios</small>
          </header>
          <div class="grimorio-slot-checkboxes">
            ${boxes}
          </div>
          <input type="hidden" class="grimorio-apothecary-hidden" value="${used}">
          <div class="grimorio-apothecary-slots__hint">Se recuperan al terminar un descanso corto o largo</div>
        </div>
      </div>
    `;

    const column = container.querySelector('.grimorio-slot-column');
    if (column) {
      const checkboxes = Array.from(column.querySelectorAll('.grimorio-slot-toggle'));
      const hidden = column.querySelector('.grimorio-apothecary-hidden');
      checkboxes.forEach((cb) => {
        cb.addEventListener('change', () => {
          const selected = checkboxes.filter((c) => c.checked).length;
          const remaining = Math.max(0, slots.max - selected);
          state.apothecarySlots.current = remaining;
          if (hidden) hidden.value = selected;
          persistApothecarySlots(remaining);
        });
      });
    }
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
      if (state.spellModel === 'apothecary') {
        // Apotecario usa preparación única 1–5, mostramos el rótulo especial.
        summary.textContent = 'Preparados (1–5)';
      } else if (state.preparedLimit) {
        summary.textContent = `Total: ${totalPrepared} / ${state.preparedLimit}`;
      } else {
        summary.textContent = `Total preparados: ${totalPrepared}`;
      }
    }

    renderCantripBlock();

    if (state.spellModel === 'apothecary') {
      renderApothecaryPrepared();
      updateConcentrationControls();
      return;
    }

    document.querySelectorAll('.grimorio-prepared-block[data-level]').forEach((block) => {
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
                <button type="button"
                        class="grimorio-prepared-spell__name"
                        data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                        data-spell-name="${escapeAttr(spell.name)}"
                        data-spell-level="${level}">
                  ${escapeHtml(spell.name)}
                </button>
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

  function renderCantripBlock() {
    const block = document.querySelector('.grimorio-prepared-block--cantrips');
    if (!block) return;
    const listEl = block.querySelector('.grimorio-prepared-block__list');
    const limitEl = block.querySelector('.grimorio-prepared-block__counter');
    const cantrips = state.cantrips || [];

    if (limitEl && state.cantripLimit) {
      limitEl.textContent = `${cantrips.length} / ${state.cantripLimit}`;
    } else if (limitEl) {
      limitEl.textContent = `${cantrips.length}`;
    }

    if (!listEl) return;

    if (!cantrips.length) {
      listEl.innerHTML = '<li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay cantrips.</li>';
      return;
    }

    listEl.innerHTML = cantrips
      .map((spell) => {
        const source = spell.source ? `<small class="grimorio-prepared-spell__source">${escapeHtml(spell.source)}</small>` : '';
        return `
          <li class="grimorio-prepared-spell"
              data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
              data-spell-name="${escapeAttr(spell.name)}"
              data-spell-level="0">
            <div class="grimorio-prepared-spell__info">
              <button type="button"
                      class="grimorio-prepared-spell__name"
                      data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                      data-spell-name="${escapeAttr(spell.name)}"
                      data-spell-level="0">
                ${escapeHtml(spell.name)}
              </button>
              ${source}
            </div>
            <button type="button"
                    class="grimorio-cast-spell"
                    data-level="0"
                    data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                    data-spell-name="${escapeAttr(spell.name)}">
              Lanzar cantrip
            </button>
          </li>
        `;
      })
      .join('');
  }

  function renderApothecaryPrepared() {
    const manualList = document.querySelector('.grimorio-prepared-block--apothecary .grimorio-prepared-block__list');
    if (manualList) {
      const allPrepared = flattenPrepared(state.prepared);
      if (!allPrepared.length) {
        manualList.innerHTML = '<li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay conjuros preparados.</li>';
      } else {
        manualList.innerHTML = allPrepared
          .map((spell) => {
            const source = spell.source ? `<small class="grimorio-prepared-spell__source">${escapeHtml(spell.source)}</small>` : '';
            const concentrationClass = isSpellConcentration(spell, spell.level) ? ' grimorio-prepared-spell--concentration' : '';
            return `
              <li class="grimorio-prepared-spell${concentrationClass}"
                  data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                  data-spell-name="${escapeAttr(spell.name)}"
                  data-spell-level="${spell.level}">
                <div class="grimorio-prepared-spell__info">
                  <button type="button"
                          class="grimorio-prepared-spell__name"
                          data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                          data-spell-name="${escapeAttr(spell.name)}"
                          data-spell-level="${spell.level}">
                    ${escapeHtml(spell.name)}
                  </button>
                  ${source}
                </div>
                <button type="button"
                        class="grimorio-cast-spell"
                        data-level="${spell.level}"
                        data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                        data-spell-name="${escapeAttr(spell.name)}">
                  Lanzar spell
                </button>
              </li>
            `;
          })
          .join('');
      }
    }

    const alwaysList = document.querySelector('.grimorio-prepared-always .grimorio-prepared-block__list');
    if (alwaysList) {
      const always = state.alwaysPrepared || [];
      if (!always.length) {
        alwaysList.innerHTML = '<li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Sin conjuros siempre preparados.</li>';
      } else {
        alwaysList.innerHTML = always
          .map((spell) => {
            const lvl = spell.level ?? '';
            return `
              <li class="grimorio-prepared-spell grimorio-prepared-spell--auto"
                  data-spell-id="${spell.id ? escapeAttr(spell.id) : ''}"
                  data-spell-name="${escapeAttr(spell.name)}"
                  data-spell-level="${lvl}">
                <div class="grimorio-prepared-spell__info">
                  <button type="button" class="grimorio-prepared-spell__name" disabled>
                    ${escapeHtml(spell.name)}
                  </button>
                  <small class="grimorio-prepared-spell__source">Siempre preparado</small>
                </div>
              </li>`;
          })
          .join('');
      }
    }

    const formulaList = document.querySelector('.grimorio-formulas-block .grimorio-prepared-block__list');
    if (formulaList) {
      const formulas = state.greaterFormulas || [];
      if (!formulas.length) {
        formulaList.innerHTML = '<li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Sin fórmulas mayores.</li>';
      } else {
        formulaList.innerHTML = formulas
          .map((f) => {
            const uses = `${Math.max(0, f.usesCurrent || 0)} / ${f.usesMax || 0}`;
            return `
              <li class="grimorio-prepared-spell grimorio-prepared-spell--formula"
                  data-formula-id="${escapeAttr(f.id || '')}">
                <div class="grimorio-prepared-spell__info">
                  <button type="button" class="grimorio-prepared-spell__name" data-spell-name="${escapeAttr(f.name || '')}">
                    ${escapeHtml(f.name || '')} (Nivel ${escapeHtml(f.level || '')})
                  </button>
                  <small class="grimorio-prepared-spell__source">Usos: ${uses}</small>
                </div>
              </li>`;
          })
          .join('');
      }
    }
  }

  function flattenPrepared(prepared) {
    const out = [];
    Object.keys(prepared).forEach((lvlKey) => {
      const lvl = parseInt(lvlKey, 10);
      if (Number.isNaN(lvl)) return;
      (prepared[lvlKey] || []).forEach((spell) => {
        if (!spell || !spell.name) return;
        out.push({ ...spell, level: lvl });
      });
    });
    return out;
  }

  function buildMetamagicCatalog() {
    return [
      { id: 'careful', name: 'Careful Spell', cost: 1, canStack: false, description: 'Aliados superan TS contra tu hechizo (mín. 1 daño).' },
      { id: 'distant', name: 'Distant Spell', cost: 1, canStack: false, description: 'Duplica alcance o convierte toque en 9 m.' },
      { id: 'empowered', name: 'Empowered Spell', cost: 1, canStack: true, description: 'Repite dados de daño (hasta mod. de CAR). Se puede combinar.' },
      { id: 'extended', name: 'Extended Spell', cost: 1, canStack: false, description: 'Duplica la duración (máx. 24h).' },
      { id: 'heightened', name: 'Heightened Spell', cost: 3, canStack: false, description: 'Desventaja al primer TS contra el hechizo.' },
      { id: 'quickened', name: 'Quickened Spell', cost: 2, canStack: false, description: 'Convierte tiempo de lanzamiento en acción adicional.' },
      { id: 'subtle', name: 'Subtle Spell', cost: 1, canStack: false, description: 'Sin componentes verbal ni somático.' },
      { id: 'twinned', name: 'Twinned Spell', cost: null, canStack: false, description: 'Duplica objetivo; coste = nivel del hechizo (1 para cantrip).' },
    ];
  }

  function findMetamagicOption(id) {
    return state.metamagicCatalog.find((opt) => opt.id === id);
  }

  function renderSorceryBlock() {
    if (!state.sorcery.isSorcerer || !selectors.sorcerySection) return;
    const { pointsCurrent, pointsMax, flexSlots } = state.sorcery;
    if (selectors.sorceryCounter) {
      selectors.sorceryCounter.textContent = `${pointsCurrent} / ${pointsMax} puntos`;
    }

    if (selectors.sorceryFlex) {
      const entries = Object.keys(flexSlots || {}).filter((lvl) => (flexSlots[lvl] || 0) > 0);
      if (!entries.length) {
        selectors.sorceryFlex.innerHTML = '<p class="grimorio-sorcery__note">Sin slots creados con puntos.</p>';
      } else {
        selectors.sorceryFlex.innerHTML = entries
          .map((lvl) => `<span class="grimorio-sorcery__pill">+${flexSlots[lvl]} slot(s) nivel ${lvl}</span>`)
          .join('');
      }
    }

    const sorceryColumn = selectors.sorceryColumn || document.querySelector('.grimorio-slot-column--sorcery');
    if (sorceryColumn) {
      const max = Math.max(0, state.sorcery.pointsMax || 0);
      ensureSorceryCheckboxes(sorceryColumn, max);
      const spent = Math.max(0, max - (state.sorcery.pointsCurrent || 0));
      const checkboxes = Array.from(sorceryColumn.querySelectorAll('.grimorio-slot-toggle'));
      sorceryFlags.updating = true;
      checkboxes.forEach((cb, idx) => {
        cb.checked = idx < spent;
      });
      sorceryFlags.updating = false;
      const small = sorceryColumn.querySelector('header small');
      if (small) {
        small.textContent = `${max} puntos`;
      }
      sorceryColumn.dataset.max = max;
      bindSorceryCheckboxes();
    }

    renderMetamagicKnown();
  }

  function ensureSorceryCheckboxes(column, max) {
    const container = column.querySelector('.grimorio-slot-checkboxes');
    if (!container) return;
    const existing = Array.from(container.querySelectorAll('.grimorio-slot-toggle')).length;
    if (existing === max && max > 0) {
      return;
    }

    // Rebuild checkbox grid to match max
    container.innerHTML = '';
    for (let i = 0; i < max; i += 1) {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.classList.add('grimorio-slot-toggle');
      const span = document.createElement('span');
      label.appendChild(input);
      label.appendChild(span);
      container.appendChild(label);
    }
    sorceryFlags.bound = false; // force rebind after rebuild
  }

  function renderMetamagicKnown() {
    if (!selectors.sorceryKnown) return;
    const known = state.sorcery.metamagicKnown || [];
    if (!known.length) {
      selectors.sorceryKnown.innerHTML = '<p class="grimorio-sorcery__note">Aún no has añadido opciones de Metamagia.</p>';
      return;
    }
    const items = known
      .map((id) => {
        const opt = findMetamagicOption(id);
        const label = opt ? opt.name : id;
        const cost = opt ? (opt.cost === null ? 'Coste variable' : `${opt.cost} ptos`) : 'Coste variable';
        const desc = escapeHtml(opt?.description || 'Sin descripción.');
        return `
          <li class="grimorio-prepared-spell grimorio-metamagic-item" data-metamagic-id="${escapeAttr(id)}">
            <div class="grimorio-prepared-spell__info">
              <button type="button"
                      class="grimorio-prepared-spell__name grimorio-metamagic-name"
                      data-metamagic-id="${escapeAttr(id)}">
                ${escapeHtml(label)}
              </button>
              <small class="grimorio-prepared-spell__source">${escapeHtml(cost)}</small>
            </div>
            <button type="button"
                    class="grimorio-cast-spell grimorio-metamagic-use"
                    data-metamagic-id="${escapeAttr(id)}">
              Usar Metamagia
            </button>
            <div class="grimorio-prepared-spell__detail grimorio-metamagic-detail" hidden>
              <p>${desc}</p>
            </div>
          </li>
        `;
      })
      .join('');

    selectors.sorceryKnown.innerHTML = `
      <ul class="grimorio-prepared-block__list grimorio-metamagic-list">
        ${items}
      </ul>
    `;
  }

  function openSorceryModal(mode) {
    if (!selectors.sorceryModal) return;
    populateSorceryModal(mode);
    showModal(selectors.sorceryModal);
  }

  function populateSorceryModal(mode) {
    if (!state.sorcery.isSorcerer) return;
    const availableSlots = [];
    Object.keys(state.slotLimits).forEach((lvlKey) => {
      const lvl = parseInt(lvlKey, 10);
      if (Number.isNaN(lvl) || lvl === 0) return;
      const total = state.slotLimits[lvl] || 0;
      const used = state.slotsUsed[lvl] || 0;
      const remaining = Math.max(0, total - used);
      if (remaining > 0) {
        availableSlots.push({ level: lvl, remaining, total });
      }
    });

    if (selectors.sorcerySlotSelect) {
      selectors.sorcerySlotSelect.innerHTML = availableSlots.length
        ? availableSlots
            .sort((a, b) => a.level - b.level)
            .map((entry) => `<option value="${entry.level}">Nivel ${entry.level} · ${entry.remaining} disponibles</option>`)
            .join('')
        : '<option value="">Sin espacios disponibles</option>';
      selectors.sorcerySlotSelect.disabled = !availableSlots.length;
    }

    const costs = state.sorcery.slotCosts || {};
    if (selectors.sorceryCreateSelect) {
      const options = Object.keys(costs)
        .map((lvlKey) => parseInt(lvlKey, 10))
        .filter((lvl) => lvl >= 1 && lvl <= 5)
        .sort((a, b) => a - b)
        .map((lvl) => {
          const cost = costs[lvl];
          const disabled = state.sorcery.pointsCurrent < cost;
          return `<option value="${lvl}" ${disabled ? 'disabled' : ''}>Nivel ${lvl} · Coste ${cost} ptos</option>`;
        })
        .join('');
      selectors.sorceryCreateSelect.innerHTML = options || '<option value="">Sin opciones</option>';
      selectors.sorceryCreateSelect.disabled = !options;
    }

    if (selectors.sorceryCreateHint) {
      selectors.sorceryCreateHint.textContent = `Puntos actuales: ${state.sorcery.pointsCurrent} / ${state.sorcery.pointsMax}`;
    }

    if (selectors.sorcerySlotSelect) {
      selectors.sorcerySlotSelect.focus();
    }
  }

  function confirmSlotToPoints() {
    if (!window.GRIMORIO_DATA.sorcery_nonce) return;
    const level = parseInt(selectors.sorcerySlotSelect?.value || '0', 10);
    if (!level) {
      openInfoModal('Sin selección', '<p>Elige un espacio para convertir en puntos de hechicería.</p>');
      return;
    }
    const gain = level;
    const max = state.sorcery.pointsMax || 0;
    const current = state.sorcery.pointsCurrent || 0;
    if (max > 0 && current >= max) {
      openInfoModal('Sin efecto', '<p>Ya tienes el máximo de Puntos de Hechicería. No puedes convertir más slots ahora.</p>');
      return;
    }
    const projected = Math.min(max, current + gain);
    if (projected === current) {
      openInfoModal('Sin efecto', '<p>Convertir este slot no te daría puntos adicionales.</p>');
      return;
    }
    const payload = new URLSearchParams({
      action: 'drak_dnd5_sorcery_slot_to_points',
      nonce: window.GRIMORIO_DATA.sorcery_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      level,
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        const data = unwrapJson(json);
        if (!data || (json && json.success === false)) {
          openInfoModal('No se pudo convertir', '<p>Verifica tus espacios disponibles.</p>');
          return;
        }
        applySorceryStateFromResponse(data);
        if (data.slots) {
          applyServerSlots(data.slots);
        } else if (data.slots_used) {
          applyServerSlots(data.slots_used);
        }
        populateSorceryModal('slot_to_points');
        renderSorceryBlock();
      })
      .catch(() => {
        openInfoModal('Error', '<p>No se pudo convertir el slot en puntos.</p>');
      });
  }

  function confirmCreateSlot() {
    if (!window.GRIMORIO_DATA.sorcery_nonce) return;
    const level = parseInt(selectors.sorceryCreateSelect?.value || '0', 10);
    if (!level) {
      openInfoModal('Sin selección', '<p>Elige el nivel del slot que quieres crear.</p>');
      return;
    }
    const payload = new URLSearchParams({
      action: 'drak_dnd5_sorcery_points_to_slot',
      nonce: window.GRIMORIO_DATA.sorcery_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      level,
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        const data = unwrapJson(json);
        if (!data || (json && json.success === false)) {
          openInfoModal('No se pudo crear', '<p>Revisa que tengas puntos suficientes.</p>');
          return;
        }
        applySorceryStateFromResponse(data);
        if (data.level && data.slot_limit) {
          state.slotLimits[data.level] = data.slot_limit;
          resizeSlotColumn(data.level, data.slot_limit);
        }
        populateSorceryModal('points_to_slot');
        renderSorceryBlock();
      })
      .catch(() => {
        openInfoModal('Error', '<p>No se pudo crear el slot con puntos.</p>');
      });
  }

  function applySorceryStateFromResponse(data) {
    const payload = data || {};
    if (!state.sorcery.isSorcerer) return;
    if (typeof payload.points_max !== 'undefined') {
      state.sorcery.pointsMax = parseInt(payload.points_max, 10) || state.sorcery.pointsMax;
    }
    if (typeof payload.points_current !== 'undefined') {
      state.sorcery.pointsCurrent = parseInt(payload.points_current, 10) || 0;
    }
    if (payload.flex_slots) {
      state.sorcery.flexSlots = normalizeSlotLimits(payload.flex_slots);
      Object.keys(state.baseSlotLimits).forEach((lvlKey) => {
        const lvl = parseInt(lvlKey, 10);
        if (Number.isNaN(lvl)) return;
        const base = state.baseSlotLimits[lvl] || 0;
        const extra = state.sorcery.flexSlots[lvl] || 0;
        state.slotLimits[lvl] = base + extra;
        resizeSlotColumn(lvl, state.slotLimits[lvl]);
      });
    }
    renderSorceryBlock();
  }

  function resetSorceryResources() {
    if (!state.sorcery.isSorcerer || !window.GRIMORIO_DATA.sorcery_nonce) return;
    const payload = new URLSearchParams({
      action: 'drak_dnd5_sorcery_reset',
      nonce: window.GRIMORIO_DATA.sorcery_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        const data = unwrapJson(json);
        if (!data || (json && json.success === false)) return;
        applySorceryStateFromResponse(data);
        if (data.slot_limits) {
          state.baseSlotLimits = normalizeSlotLimits(data.slot_limits);
          state.slotLimits = { ...state.baseSlotLimits };
          applyFlexSlotsToColumns();
          applyServerSlots(state.slotsUsed);
        }
      })
      .catch(() => {
        console.warn('No se pudo resetear los Puntos de Hechicería.');
      });
  }

  function openMetamagicManageModal() {
    if (!selectors.metamagicManageModal) return;
    const knownSet = new Set(state.sorcery.metamagicKnown || []);
    const limit = state.sorcery.metamagicLimit || 0;
    if (selectors.metamagicOptionsContainer) {
      selectors.metamagicOptionsContainer.innerHTML = state.metamagicCatalog
        .map((opt) => {
          const checked = knownSet.has(opt.id) ? 'checked' : '';
          const disabled = !checked && knownSet.size >= limit ? 'disabled' : '';
          const costLabel = opt.cost === null ? 'Coste = nivel del hechizo' : `${opt.cost} punto(s)`;
          return `
            <article class="grimorio-spell-picker__level grimorio-metamagic-option" data-meta-id="${escapeAttr(opt.id)}">
              <header class="grimorio-spell-picker__header">
                <div>
                  <h4>${escapeHtml(opt.name)}</h4>
                  <small class="grimorio-metamagic-option__cost">${escapeHtml(costLabel)}</small>
                </div>
                <button type="button"
                        class="grimorio-modal__btn grimorio-modal__btn--primary grimorio-metamagic-option__select"
                        data-meta-id="${escapeAttr(opt.id)}"
                        ${disabled}>
                  ${checked ? 'Seleccionado' : 'Seleccionar'}
                </button>
              </header>
              <div class="grimorio-spell-picker__list">
                <p class="grimorio-metamagic-card__body">${escapeHtml(opt.description || '')}</p>
              </div>
            </article>
          `;
        })
        .join('');
    }
    if (selectors.metamagicLimit) {
      selectors.metamagicLimit.textContent = `Límite disponible: ${limit}`;
    }
    selectors.metamagicOptionsContainer?.removeEventListener('click', handleMetamagicSelectionClick);
    showModal(selectors.metamagicManageModal);
    selectors.metamagicOptionsContainer?.querySelector('button.grimorio-metamagic-option__select')?.focus();
    selectors.metamagicOptionsContainer?.addEventListener('click', handleMetamagicSelectionClick);
  }

  function handleMetamagicSelectionClick(event) {
    const btn = event.target.closest('.grimorio-metamagic-option__select');
    if (!btn) return;
    const metaId = btn.dataset.metaId;
    if (!metaId) return;

    const limit = state.sorcery.metamagicLimit || 0;
    const current = new Set(state.sorcery.metamagicKnown || []);
    const isSelected = current.has(metaId);
    if (!isSelected && current.size >= limit) {
      openInfoModal('Límite de Metamagia', `<p>Solo puedes conocer ${limit} opciones a tu nivel.</p>`);
      return;
    }

    if (isSelected) {
      current.delete(metaId);
      btn.textContent = 'Seleccionar';
    } else {
      current.add(metaId);
      btn.textContent = 'Seleccionado';
    }

    state.sorcery.metamagicKnown = Array.from(current);
    Array.from(selectors.metamagicOptionsContainer.querySelectorAll('.grimorio-metamagic-option__select')).forEach((button) => {
      const id = button.dataset.metaId;
      const selected = current.has(id);
      const shouldDisable = !selected && current.size >= limit;
      button.disabled = shouldDisable && !selected;
      button.textContent = selected ? 'Seleccionado' : 'Seleccionar';
    });
  }

  function handleMetamagicCastClick(event) {
    const btn = event.target.closest('.grimorio-metamagic-option__select');
    if (!btn) return;
    const metaId = btn.dataset.metaId;
    if (!metaId) return;

    const isSelected = metamagicCastSelection.has(metaId);
    const hasEmpowered = metamagicCastSelection.has('empowered');

    if (isSelected) {
      metamagicCastSelection.delete(metaId);
    } else {
      if (metamagicCastSelection.size >= 1 && !hasEmpowered && metaId !== 'empowered') {
        openInfoModal('Regla de combinación', '<p>Solo puedes aplicar una opción de Metamagia por conjuro (Empowered puede combinarse con otra).</p>');
        return;
      }
      if (metamagicCastSelection.size >= 2) {
        openInfoModal('Regla de combinación', '<p>No puedes aplicar más de dos opciones, y solo si una es Empowered.</p>');
        return;
      }
      metamagicCastSelection.add(metaId);
    }

    Array.from(selectors.metamagicCastOptions.querySelectorAll('.grimorio-metamagic-option__select')).forEach((button) => {
      const id = button.dataset.metaId;
      const selected = metamagicCastSelection.has(id);
      button.textContent = selected ? 'Seleccionado' : 'Seleccionar';
    });
  }

  function saveMetamagicKnown() {
    if (!window.GRIMORIO_DATA.sorcery_nonce) return;
    const selected = Array.from(new Set(state.sorcery.metamagicKnown || []));
    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_metamagic_known',
      nonce: window.GRIMORIO_DATA.sorcery_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      known: JSON.stringify(selected),
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        const data = unwrapJson(json);
        if (!data || (json && json.success === false)) {
          openInfoModal('Error', '<p>No se pudo guardar la Metamagia conocida.</p>');
          return;
        }
        state.sorcery.metamagicKnown = data.known || [];
        renderMetamagicKnown();
        closeModal(selectors.metamagicManageModal);
      })
      .catch(() => openInfoModal('Error', '<p>No se pudo guardar la Metamagia conocida.</p>'));
  }

  function openMetamagicCastModal(level, spellId, fallbackName) {
    if (!state.sorcery.metamagicKnown.length || !selectors.metamagicCastModal) {
      finalizeCastWithMetamagic(level, spellId, fallbackName, []);
      return;
    }
    metamagicCastSelection = new Set();
    state.pendingCast = { level, spellId, fallbackName };
    const knownSet = new Set(state.sorcery.metamagicKnown || []);
    const options = state.metamagicCatalog.filter((opt) => knownSet.has(opt.id));
    const availablePoints = state.sorcery.pointsCurrent || 0;
    if (!options.length) {
      finalizeCastWithMetamagic(level, spellId, fallbackName, []);
      return;
    }
    if (selectors.metamagicCastOptions) {
      selectors.metamagicCastOptions.innerHTML = options
        .map((opt) => {
          const cost = opt.id === 'twinned' ? 'Coste = nivel del hechizo' : `${opt.cost} punto(s)`;
          const disabled = opt.cost !== null && opt.cost > availablePoints ? 'disabled' : '';
          return `
            <article class="grimorio-spell-picker__level grimorio-metamagic-option" data-meta-id="${escapeAttr(opt.id)}">
              <header class="grimorio-spell-picker__header">
                <div>
                  <h4>${escapeHtml(opt.name)}</h4>
                  <small class="grimorio-metamagic-option__cost">${escapeHtml(cost)}</small>
                </div>
                <button type="button"
                        class="grimorio-modal__btn grimorio-modal__btn--primary grimorio-metamagic-option__select"
                        data-meta-id="${escapeAttr(opt.id)}"
                        ${disabled}>
                  Seleccionar
                </button>
              </header>
              <div class="grimorio-spell-picker__list">
                <p class="grimorio-metamagic-card__body">${escapeHtml(opt.description || '')}</p>
              </div>
            </article>
          `;
        })
        .join('');
      selectors.metamagicCastOptions.removeEventListener('click', handleMetamagicCastClick);
      selectors.metamagicCastOptions.addEventListener('click', handleMetamagicCastClick);
    }
    if (selectors.metamagicCastHint) {
      selectors.metamagicCastHint.textContent = `Puntos disponibles: ${availablePoints}`;
    }
    const anyEnabled = selectors.metamagicCastOptions?.querySelector('.grimorio-metamagic-option__select:not([disabled])');
    if (!anyEnabled) {
      finalizeCastWithMetamagic(level, spellId, fallbackName, []);
      return;
    }
    showModal(selectors.metamagicCastModal);
  }

  function applyMetamagicSelection() {
    if (!state.pendingCast) {
      closeModal(selectors.metamagicCastModal);
      return;
    }
    const level = state.pendingCast.level;
    const spellId = state.pendingCast.spellId;
    const fallbackName = state.pendingCast.fallbackName;
    const selected = Array.from(metamagicCastSelection);
    if (selected.length > 1 && !selected.includes('empowered')) {
      openInfoModal('Regla de combinación', '<p>Solo puedes aplicar una opción de Metamagia por conjuro (Empowered es la excepción).</p>');
      return;
    }
    const totalCost = selected.reduce((sum, id) => {
      if (id === 'twinned') {
        return sum + (level || 1);
      }
      const opt = findMetamagicOption(id);
      return sum + (opt && opt.cost !== null ? opt.cost : 0);
    }, 0);
    if (totalCost > (state.sorcery.pointsCurrent || 0)) {
      openInfoModal('Sin puntos suficientes', '<p>No tienes suficientes Puntos de Hechicería para esa Metamagia.</p>');
      return;
    }

    if (totalCost > 0) {
      spendSorceryPoints(totalCost)
        .then(() => {
          finalizeCastWithMetamagic(level, spellId, fallbackName, selected);
          closeModal(selectors.metamagicCastModal);
        })
        .catch(() => {
          openInfoModal('Error', '<p>No se pudieron gastar los puntos de hechicería.</p>');
        });
    } else {
      finalizeCastWithMetamagic(level, spellId, fallbackName, selected);
      closeModal(selectors.metamagicCastModal);
    }
  }

  function spendSorceryPoints(cost) {
    if (!window.GRIMORIO_DATA.sorcery_nonce) return Promise.reject();
    const current = state.sorcery.pointsCurrent || 0;
    if (cost > current) {
      return Promise.reject(new Error('Insufficient points'));
    }
    const next = Math.max(0, current - cost);
    return persistSorceryPoints(next);
  }

  function finalizeCastWithMetamagic(level, spellId, fallbackName, metamagicIds) {
    const spell = findSpell(spellId, fallbackName);
    const context = spell ? extractSpellContext(spell) : { concentration: false };
    consumeSlot(level);
    if (context.concentration) {
      setConcentrationState({
        level,
        spell_id: spell?.id || '',
        spell: spell?.name || fallbackName || '',
      });
    }
    if (spell) {
      openPreparedSpellInfo(level, spellId, fallbackName);
    }
    state.pendingCast = null;
  }

  function handleKnownMetamagicAction(event) {
    const toggleBtn = event.target.closest('.grimorio-metamagic-name');
    if (toggleBtn) {
      const item = toggleBtn.closest('.grimorio-metamagic-item');
      if (item) {
        const detail = item.querySelector('.grimorio-metamagic-detail');
        if (detail) {
          const isHidden = detail.hasAttribute('hidden');
          if (isHidden) {
            detail.removeAttribute('hidden');
          } else {
            detail.setAttribute('hidden', '');
          }
        }
      }
      return;
    }

    const btn = event.target.closest('.grimorio-metamagic-use');
    if (!btn) return;
    const metaId = btn.dataset.metamagicId;
    if (!metaId) return;
    const opt = findMetamagicOption(metaId);
    if (!opt) return;

    let cost = opt.cost || 0;
    if (metaId === 'twinned') {
      const levelInput = window.prompt('Coste de Twinned: introduce el nivel del hechizo (1-9)', '1');
      const lvl = parseInt(levelInput || '1', 10);
      if (Number.isNaN(lvl) || lvl < 1 || lvl > 9) {
        openInfoModal('Nivel inválido', '<p>Introduce un nivel de 1 a 9.</p>');
        return;
      }
      cost = lvl;
    }

    if (cost > (state.sorcery.pointsCurrent || 0)) {
      openInfoModal('Sin puntos suficientes', '<p>No tienes Puntos de Hechicería suficientes para esta Metamagia.</p>');
      return;
    }

    spendSorceryPoints(cost)
      .then(() => {
        openInfoModal('Metamagia usada', `<p>Has gastado ${cost} punto(s) de hechicería en ${escapeHtml(opt.name)}.</p>`);
      })
      .catch(() => {
        openInfoModal('Error', '<p>No se pudieron gastar los Puntos de Hechicería.</p>');
      });
  }

  function openSpellPicker() {
    if (!selectors.pickerModal) return;

    pickerOnlyCantrips = false;
    pickerState = clonePrepared(state.prepared);
    if (state.cantrips && state.cantrips.length) {
      pickerState[0] = state.cantrips.map((spell) => ({ ...spell, level: 0 }));
    }
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

  function openCantripPicker() {
    if (!selectors.pickerModal) return;
    pickerOnlyCantrips = true;
    pickerState = { 0: state.cantrips ? state.cantrips.map((spell) => ({ ...spell, level: 0 })) : [] };
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
    const levels = new Set();
    if (pickerOnlyCantrips) {
      if (state.spellsByLevel[0]?.length) {
        return [0];
      }
      return [];
    }

    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      const maxLevel = Math.max(1, state.apothecarySlots.slotLevel || 1);
      for (let i = 1; i <= maxLevel; i += 1) {
        levels.add(i);
      }
    } else {
      Object.keys(state.slotLimits)
        .map((key) => parseInt(key, 10))
        .filter((lvl) => !Number.isNaN(lvl) && state.slotLimits[lvl] > 0)
        .forEach((lvl) => levels.add(lvl));
    }

    return Array.from(levels).sort((a, b) => a - b);
  }

  function renderPickerLevel(level) {
    const isCantrip = level === 0;
    const levelSlots = isCantrip
      ? state.cantripLimit
      : state.spellModel === 'apothecary'
        ? state.preparedLimit
        : (state.slotLimits[level] || 0);
    const spells = (state.spellsByLevel[level] || []).slice().sort((a, b) => a.name.localeCompare(b.name));
    const selected = pickerState[level] || [];
    const selectedIds = new Set(selected.map((spell) => spell.id || spell.name));
    const totalSelected = isCantrip ? selected.length : getTotalPreparedCount(pickerState);
    const limitReached = isCantrip
      ? (state.cantripLimit ? totalSelected >= state.cantripLimit : false)
      : (state.preparedLimit ? totalSelected >= state.preparedLimit : false);

    const disabledAll = isCantrip
      ? false
      : state.spellModel === 'apothecary'
        ? false
        : levelSlots === 0;
    const listContent = spells.length
      ? spells
          .map((spell) => {
            const identifier = spell.id || spell.name;
            const checked = selectedIds.has(identifier);
            const shouldDisable = disabledAll || (!checked && limitReached);
            const summary = spell.source ? `<small>${escapeHtml(spell.source)}</small>` : '';
            const description = getSpellSummaryHtml(spell);
            const descriptionBlock = description
              ? `<small class="grimorio-spell-picker__desc">${description}</small>`
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

    const limitNote = isCantrip
      ? `<p class="grimorio-spell-picker__limit">Cantrips: ${selected.length}${state.cantripLimit ? ' / ' + state.cantripLimit : ''}</p>`
      : (state.spellModel === 'apothecary'
          ? `<p class="grimorio-spell-picker__limit">Preparados (1–5)</p>`
          : (state.preparedLimit
              ? `<p class="grimorio-spell-picker__limit">Total preparados: ${totalSelected} / ${state.preparedLimit}</p>`
              : `<p class="grimorio-spell-picker__limit">Conjuros preparados en este nivel: ${selected.length}</p>`));

    let slotNote = '';
    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      slotNote = `<small class="grimorio-spell-picker__slots">Espacios de Apotecario: ${state.apothecarySlots.current || state.apothecarySlots.max || 0} / ${state.apothecarySlots.max || 0} · Todos los conjuros se lanzan a nivel ${state.apothecarySlots.slotLevel}</small>`;
    } else {
      slotNote = levelSlots
        ? `<small class="grimorio-spell-picker__slots">Espacios de nivel ${level}: ${levelSlots}</small>`
        : '<small class="grimorio-spell-picker__slots">Sin espacios de este nivel.</small>';
    }

    return `
      <article class="grimorio-spell-picker__level" data-picker-level="${level}" data-max="${isCantrip ? (state.cantripLimit || '') : (state.preparedLimit || '')}">
        <header class="grimorio-spell-picker__header">
          <h4>${isCantrip ? 'Cantrips' : `Nivel ${level}`}</h4>
          <span class="grimorio-spell-picker__count">${isCantrip ? selected.length : (state.spellModel === 'apothecary' ? '' : selected.length)}</span>
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
    const isCantrip = level === 0;
    const max = isCantrip
      ? state.cantripLimit || 0
      : state.spellModel === 'apothecary'
        ? state.preparedLimit || 0
        : (state.slotLimits[level] || 0);
    let current = pickerState[level] ? [...pickerState[level]] : [];
    const currentTotal = isCantrip ? current.length : getTotalPreparedCount(pickerState);
    const currentLevelCount = current.length;
    const levelCap = !isCantrip && state.spellModel !== 'apothecary' ? (state.slotLimits[level] || 0) : null;

    const spellData = findSpellById(spellId) || { id: spellId, name: spellName, source: '', level };

    if (isChecked) {
      if (!isCantrip && state.preparedLimit && currentTotal >= state.preparedLimit) {
        input.checked = false;
        openInfoModal(
          'Límite alcanzado',
          `<p>No puedes preparar más de ${state.preparedLimit} conjuros. Quita otro conjuro antes de añadir más.</p>`
        );
        return;
      }
      if (isCantrip && state.cantripLimit && currentTotal >= state.cantripLimit) {
        input.checked = false;
        openInfoModal(
          'Límite de cantrips',
          `<p>No puedes aprender más de ${state.cantripLimit} cantrips a tu nivel actual.</p>`
        );
        return;
      }
      if (!isCantrip && levelCap && currentLevelCount >= levelCap) {
        input.checked = false;
        openInfoModal(
          'Sin huecos en este nivel',
          `<p>No puedes preparar más de ${levelCap} conjuros de nivel ${level}.</p>`
        );
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

    if (pickerOnlyCantrips) {
      const nextCantrips = pickerState[0] || [];
      const previousCantrips = state.cantrips ? [...state.cantrips] : [];
      state.cantrips = nextCantrips;
      renderPreparedView();
      closeModal(selectors.pickerModal);
      pickerOnlyCantrips = false;
      persistPreparedSpells({ ...state.prepared, 0: nextCantrips }).catch(() => {
        state.cantrips = previousCantrips;
        renderPreparedView();
        openInfoModal('Error al guardar', '<p>No se pudo guardar la lista de cantrips. Intenta de nuevo.</p>');
      });
      return;
    }

    const nextState = clonePrepared(pickerState);
    const hasCantripSlice = Object.prototype.hasOwnProperty.call(nextState, 0);
    const nextCantrips = hasCantripSlice ? (nextState[0] || []) : (state.cantrips ? [...state.cantrips] : []);
    delete nextState[0];
    const previousState = clonePrepared(state.prepared);
    const previousCantrips = state.cantrips ? [...state.cantrips] : [];
    const previousConcentration = { ...state.concentration };

    state.prepared = nextState;
    state.cantrips = nextCantrips;
    if (!hasConcentrationSpell(nextState, previousConcentration)) {
      state.concentration = defaultConcentrationState();
    }
    renderPreparedView();
    closeModal(selectors.pickerModal);

    Promise.all([
      persistPreparedSpells({ ...nextState, 0: nextCantrips }),
      persistConcentration(state.concentration),
    ]).catch(() => {
      state.prepared = previousState;
      state.cantrips = previousCantrips;
      state.concentration = previousConcentration;
      renderPreparedView();
      openInfoModal('Error al guardar', '<p>No se pudo guardar la lista de conjuros. Intenta de nuevo.</p>');
    });
  }

  function handleCastSpell(level, spellId, fallbackName) {
    if (level === 0) {
      const spell = findSpell(spellId, fallbackName);
      const context = spell ? extractSpellContext(spell) : { concentration: false };
      if (context.concentration) {
        setConcentrationState({
          level: 0,
          spell_id: spell?.id || '',
          spell: spell?.name || fallbackName || '',
        });
      }
      openPreparedSpellInfo(level, spellId, fallbackName);
      return;
    }
    if (!level) return;
    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      if (state.apothecarySlots.current <= 0) {
        openInfoModal('Sin espacios', '<p>Ya has usado todos tus espacios de Apotecario.</p>');
        return;
      }
      const spell = findSpell(spellId, fallbackName);
      const context = spell ? extractSpellContext(spell) : { concentration: false };
      state.apothecarySlots.current = Math.max(0, state.apothecarySlots.current - 1);
      renderApothecarySlotsBlock();
      persistApothecarySlots(state.apothecarySlots.current);
      if (context.concentration) {
        setConcentrationState({
          level: state.apothecarySlots.slotLevel || level,
          spell_id: spell?.id || '',
          spell: spell?.name || fallbackName || '',
        });
      }
      openPreparedSpellInfo(level, spellId, fallbackName);
      return;
    }

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

    if (state.sorcery.isSorcerer) {
      openMetamagicCastModal(level, spellId, fallbackName);
      return;
    }

    const spell = findSpell(spellId, fallbackName);
    const context = spell ? extractSpellContext(spell) : { concentration: false };
    consumeSlot(level);

    if (context.concentration) {
      setConcentrationState({
        level,
        spell_id: spell?.id || '',
        spell: spell?.name || fallbackName || '',
      });
    }
  }

  function bindSpellListInteractions(container) {
    if (!container) return;
    container.addEventListener('click', (event) => {
      const infoButton = event.target.closest('.grimorio-prepared-spell__name');
      if (infoButton) {
        const infoLevel = parseInt(infoButton.dataset.spellLevel || '0', 10);
        const infoId = infoButton.dataset.spellId || '';
        const infoName = infoButton.dataset.spellName || '';
        openPreparedSpellInfo(infoLevel, infoId, infoName);
        return;
      }

      const button = event.target.closest('.grimorio-cast-spell');
      if (!button) return;
      const level = parseInt(button.dataset.level || '0', 10);
      const spellId = button.dataset.spellId || '';
      const spellName = button.dataset.spellName || '';
      handleCastSpell(level, spellId, spellName);
    });
  }

  function openPreparedSpellInfo(level, spellId, fallbackName) {
    const spell = findSpell(spellId, fallbackName);
    if (!spell) {
      const safeName = fallbackName || 'Detalle del conjuro';
      const fallbackBody = `
        <hr class="temp-pv-separator">
        <p>No se encontró información adicional para ${escapeHtml(safeName)}.</p>
      `;
      openInfoModal(safeName, fallbackBody);
      return;
    }
    const context = extractSpellContext(spell);
    const title = spell.name || fallbackName || 'Detalle del conjuro';
    const body = `
      <hr class="temp-pv-separator">
      ${buildSpellContextHtml(spell, context)}
    `;
    openInfoModal(title, body);
  }

  function resetSlots() {
    // Apotecario / warlock-like
    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      resetApothecaryResources(true);
      return;
    }

    state.slotsUsed = Object.keys(state.slotLimits).reduce((acc, level) => {
      acc[level] = 0;
      return acc;
    }, {});

    slotColumns.forEach((record, level) => {
      if (record.hidden) record.hidden.value = 0;
      updateSlotCheckboxes(level, 0);
      persistSlot(level, 0);
    });

    resetSorceryResources();
  }

  function resetPreparedSpells() {
    const empty = {};
    Object.keys(state.prepared).forEach((key) => {
      empty[key] = [];
    });
    const previousPrepared = clonePrepared(state.prepared);
    const previousCantrips = state.cantrips ? [...state.cantrips] : [];
    const previousConcentration = { ...state.concentration };

    state.prepared = empty;
    state.cantrips = [];
    state.concentration = defaultConcentrationState();
    renderPreparedView();

    Promise.all([
      persistPreparedSpells({ ...state.prepared, 0: [] }),
      persistConcentration(state.concentration),
    ]).catch(() => {
      state.prepared = previousPrepared;
      state.cantrips = previousCantrips;
      state.concentration = previousConcentration;
      renderPreparedView();
      openInfoModal('Error al reiniciar', '<p>No se pudo reiniciar el grimorio. Intenta de nuevo.</p>');
    });
  }

  function consumeSlot(level) {
    const column = slotColumns.get(level) || ensureSlotColumn(level);
    if (!column) return;
    const max = state.slotLimits[level] || column.max || 0;
    const used = clamp((state.slotsUsed[level] || 0) + 1, 0, max);
    state.slotsUsed[level] = used;
    if (column.hidden) {
      column.hidden.value = used;
    }
    updateSlotCheckboxes(level, used);
    persistSlot(level, used);
  }

  function updateSlotCheckboxes(level, used) {
    const record = slotColumns.get(level) || ensureSlotColumn(level);
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

  function persistApothecarySlots(current) {
    if (!window.GRIMORIO_DATA.nonce) return;
    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_apothecary_slots',
      nonce: window.GRIMORIO_DATA.nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      current,
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    }).catch(() => {
      console.warn('No se pudo guardar el estado de slots de Apotecario.');
    });
  }

  function resetApothecaryResources(isLongRest) {
    if (!window.GRIMORIO_DATA.nonce) return;
    if (state.apothecarySlots) {
      // Optimista: restaura slots en cliente mientras llega la respuesta.
      state.apothecarySlots.current = state.apothecarySlots.max;
      renderApothecarySlotsBlock();
    }
    const payload = new URLSearchParams({
      action: 'drak_dnd5_reset_apothecary_resources',
      nonce: window.GRIMORIO_DATA.nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      is_long: isLongRest ? '1' : '',
    });
    fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) return;
        if (json.apothecary_slots) {
          state.apothecarySlots = {
            ...state.apothecarySlots,
            ...json.apothecary_slots,
          };
          renderApothecarySlotsBlock();
        }
        if (json.greater_formulas) {
          state.greaterFormulas = json.greater_formulas;
          renderPreparedView();
        }
      })
      .catch(() => {
        console.warn('No se pudo resetear recursos de Apotecario.');
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
      const column = slotColumns.get(level) || ensureSlotColumn(level);
      if (!column) return;
      if (column.hidden) {
        column.hidden.value = used;
      }
      updateSlotCheckboxes(level, used);
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
    if (state.spellModel === 'apothecary' && state.apothecarySlots) {
      const remaining = Math.max(0, (state.apothecarySlots.current || 0));
      if (remaining > 0) {
        const lvl = state.apothecarySlots.slotLevel || 1;
        return [{ level: lvl, remaining, max: state.apothecarySlots.max || remaining }];
      }
      return [];
    }

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
        if (state.spellModel === 'apothecary' && payload.apothecary_slots) {
          state.apothecarySlots = {
            ...state.apothecarySlots,
            ...payload.apothecary_slots,
          };
          renderApothecarySlotsBlock();
        } else {
          applyServerSlots(payload.slots_used || {});
        }
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

    state.cantrips = (state.cantrips || []).map((spell) => {
      const ref = findSpell(spell.id, spell.name);
      if (!ref) {
        return spell;
      }
      return mapSpellForState(ref, 0);
    });
    renderCantripBlock();
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

    const serialized = serializePrepared({ ...prepared, 0: prepared[0] || state.cantrips || [] });
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
    const cantripList = prepared[0] || state.cantrips || [];
    if (cantripList && cantripList.length) {
      result[0] = cantripList.map((spell) => spell.name || spell).filter(Boolean);
    }
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

  function stripSpellTag(tagged) {
    return tagged.replace(/^\{@[a-zA-Z0-9_]+\s+/, '').replace(/\}$/, '');
  }

  function truncateSpellText(raw, limit = 180) {
    const tokens = raw.match(/(\(?\[{1,2}[^\]]+\]+?\)?)|\{@[^\}]+\}|[^{}]+/g) || [];
    const kept = [];
    let total = 0;
    let truncated = false;

    tokens.forEach((token) => {
      if (truncated) return;
      let display = token;
      if (token.startsWith('{@')) {
        display = stripSpellTag(token);
      } else if (token.includes('[')) {
        display = token.replace(/[()\[\]]/g, '');
      }
      const nextTotal = total + display.length;
      if (nextTotal > limit) {
        truncated = true;
        return;
      }
      kept.push(token);
      total = nextTotal;
    });

    let result = kept.join('');
    if (truncated) {
      result = `${result.trim()}...`;
    }
    return result;
  }

  function formatSpellNotation(text) {
    if (!text) return '';
    const regex = /\{@([a-zA-Z0-9_]+)\s+([^}]+)\}|(\(?\[{1,2}([^\]]+)\]+?\)?)/g;
    let result = '';
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
      if (match.index > lastIndex) {
        result += escapeHtml(text.slice(lastIndex, match.index));
      }
      // Brace-based {@tag content}
      if (match[0].startsWith('{@')) {
        const tag = match[1].toLowerCase();
        const content = stripSpellTag(match[0]);
        const label = escapeHtml(content.split('|')[0].trim());
        const classes = ['grimorio-spell-tag'];
        if (tag === 'damage' || tag === 'dice') {
          classes.push('grimorio-spell-tag--dice');
        } else if (tag === 'condition' || tag === 'status') {
          classes.push('grimorio-spell-tag--condition');
        } else if (tag === 'dc') {
          classes.push('grimorio-spell-tag--dc');
        } else {
          classes.push('grimorio-spell-tag--ref');
        }
        result += `<span class="${classes.join(' ')}">${label}</span>`;
      } else {
        // [[TAGx]] or [TAGx] placeholder tokens (with optional parens)
        const bracketContent = (match[4] || '').replace(/[()\[\]]/g, '').trim();
        const label = escapeHtml(bracketContent || 'REF');
        result += `<span class="grimorio-spell-tag grimorio-spell-tag--placeholder">${label}</span>`;
      }
      lastIndex = regex.lastIndex;
    }

    if (lastIndex < text.length) {
      result += escapeHtml(text.slice(lastIndex));
    }

    return result;
  }

  function getSpellSummaryHtml(spell) {
    if (!spell || !spell.entries) return '';
    const chunks = [];
    flattenSpellEntry(spell.entries, chunks);
    if (!chunks.length) return '';
    const text = chunks.join(' ').replace(/\s+/g, ' ').trim();
    if (!text) return '';
    const truncated = truncateSpellText(text);
    return formatSpellNotation(truncated);
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

    let summaryParagraph = '';
    if (context.text) {
      const paragraphs = context.text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => `<p>${formatSpellNotation(line)}</p>`)
        .join('');
      if (paragraphs) {
        summaryParagraph = `<div class="grimorio-cast-summary__text">${paragraphs}</div>`;
      }
    }

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
    if (modal === selectors.metamagicCastModal) {
      state.pendingCast = null;
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

  function unwrapJson(json) {
    if (json && typeof json.data !== 'undefined') {
      return json.data;
    }
    return json;
  }

  function getTotalPreparedCount(map) {
    const source = map || state.prepared;
    return Object.entries(source).reduce((sum, [lvl, list]) => {
      if (parseInt(lvl, 10) === 0) return sum;
      return sum + (Array.isArray(list) ? list.length : 0);
    }, 0);
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

  function computeApothecaryPreparedLimit(level, intScore) {
    const lvl = Number.isFinite(level) ? level : 1;
    const intMod = abilityMod(intScore);
    return Math.max(1, lvl + intMod);
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

  function bindSorceryCheckboxes() {
    if (sorceryFlags.bound) return;
    const column = selectors.sorceryColumn || document.querySelector('.grimorio-slot-column--sorcery');
    if (!column) return;
    const checkboxes = Array.from(column.querySelectorAll('.grimorio-slot-toggle'));
    if (!checkboxes.length) return;
    checkboxes.forEach((cb) => {
      cb.addEventListener('change', () => {
        if (sorceryFlags.updating) return;
        const spent = checkboxes.filter((c) => c.checked).length;
        const max = state.sorcery.pointsMax || 0;
        const nextPoints = Math.max(0, max - spent);
        persistSorceryPoints(nextPoints);
      });
    });
    sorceryFlags.bound = true;
  }

  function persistSorceryPoints(value) {
    if (!window.GRIMORIO_DATA.sorcery_nonce) {
      return Promise.resolve();
    }
    const clamped = Math.max(0, Math.min(value, state.sorcery.pointsMax || 0));
    const payload = new URLSearchParams({
      action: 'drak_dnd5_sorcery_set_points',
      nonce: window.GRIMORIO_DATA.sorcery_nonce,
      post_id: window.GRIMORIO_DATA.post_id,
      value: clamped,
    });
    return fetch(window.GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        const data = unwrapJson(json);
        if (json && json.success === false) {
          return Promise.reject();
        }
        applySorceryStateFromResponse(data);
        return data;
      })
      .catch(() => {
        console.warn('No se pudieron actualizar los Puntos de Hechicería.');
        return Promise.reject();
      });
  }

})();
