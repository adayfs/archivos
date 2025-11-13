(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.GRIMORIO_DATA === 'undefined') return;
    initGrimorio();
  });

  const slotSaveTimers = {};
  const slotColumns = new Map();
  let pickerState = null;
  let spellsPromise = null;

  const state = {
    slotsUsed: {},
    slotLimits: {},
    prepared: {},
    spellsByLevel: {},
    spellIndexById: {},
    spellIndexByName: {},
    spellsLoaded: false,
  };

  const selectors = {};

  function initGrimorio() {
    selectors.preparedContainer = document.querySelector('.grimorio-prepared');
    selectors.editButton = document.querySelector('.grimorio-prepared__edit-btn');
    selectors.pickerModal = document.getElementById('grimorio-spell-picker');
    selectors.pickerLevels = document.getElementById('grimorio-spell-picker-levels');
    selectors.pickerLoading = document.getElementById('grimorio-spell-picker-loading');
    selectors.pickerSaveBtn = document.getElementById('grimorio-spell-picker-save');
    selectors.infoModal = document.getElementById('grimorio-info-modal');
    selectors.infoTitle = document.getElementById('grimorio-info-title');
    selectors.infoBody = document.getElementById('grimorio-info-content');

    hydrateState();
    initSlots();
    renderPreparedView();
    bindEvents();
    fetchSpellList();
  }

  function hydrateState() {
    const data = window.GRIMORIO_DATA;
    state.slotsUsed = cloneObject(data.slots_used || {});
    state.slotLimits = normalizeSlotLimits(data.slot_limits || {});
    state.prepared = normalizePrepared(data.prepared || {});
  }

  function bindEvents() {
    if (selectors.editButton) {
      selectors.editButton.addEventListener('click', openSpellPicker);
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
          return `
            <li class="grimorio-prepared-spell">
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
    const levels = Object.keys(state.slotLimits)
      .map((key) => parseInt(key, 10))
      .filter((lvl) => !Number.isNaN(lvl))
      .sort((a, b) => a - b);
    return levels;
  }

  function renderPickerLevel(level) {
    const max = state.slotLimits[level] || 0;
    const spells = (state.spellsByLevel[level] || []).slice().sort((a, b) => a.name.localeCompare(b.name));
    const selected = pickerState[level] || [];
    const selectedIds = new Set(selected.map((spell) => spell.id || spell.name));

    const disabledAll = max === 0;
    const listContent = spells.length
      ? spells
          .map((spell) => {
            const checked = selectedIds.has(spell.id);
            const shouldDisable = disabledAll || (!checked && max > 0 && selected.length >= max);
            const summary = spell.source ? `<small>${escapeHtml(spell.source)}</small>` : '';
            return `
              <label class="grimorio-spell-picker__item">
                <input type="checkbox"
                       value="${escapeAttr(spell.id)}"
                       data-level="${level}"
                       data-spell-id="${escapeAttr(spell.id)}"
                       data-spell-name="${escapeAttr(spell.name)}"
                       ${checked ? 'checked' : ''}
                       ${shouldDisable ? 'disabled' : ''}>
                <span>
                  ${escapeHtml(spell.name)}
                  ${summary}
                </span>
              </label>
            `;
          })
          .join('')
      : '<p class="grimorio-spell-picker__empty">No hay conjuros de este nivel para tu clase.</p>';

    const limitNote =
      max === 0
        ? '<p class="grimorio-spell-picker__limit">No tienes espacios de conjuro para este nivel.</p>'
        : `<p class="grimorio-spell-picker__limit">Huecos disponibles: ${selected.length} / ${max}</p>`;

    return `
      <article class="grimorio-spell-picker__level" data-picker-level="${level}" data-max="${max}">
        <header class="grimorio-spell-picker__header">
          <h4>Nivel ${level}</h4>
          <span class="grimorio-spell-picker__count">${selected.length} / ${max}</span>
        </header>
        ${limitNote}
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
    const spellId = input.dataset.spellId;
    const spellName = input.dataset.spellName || input.value;
    const isChecked = input.checked;
    const max = state.slotLimits[level] || 0;
    const current = pickerState[level] || [];

    if (isChecked && max > 0 && current.length >= max) {
      input.checked = false;
      openInfoModal('Sin huecos disponibles', `<p>No puedes preparar más conjuros de nivel ${level}.</p>`);
      return;
    }

    const spellData = findSpellById(spellId) || { id: spellId, name: spellName, source: '', level };

    if (isChecked) {
      if (!current.find((item) => item.id === spellData.id || item.name === spellData.name)) {
        current.push(mapSpellForState(spellData, level));
      }
    } else {
      pickerState[level] = current.filter((item) => item.id !== spellData.id && item.name !== spellData.name);
    }

    pickerState[level] = current;
    buildSpellPickerUI();
  }

  function saveSpellPickerSelection() {
    if (!pickerState) {
      closeModal(selectors.pickerModal);
      return;
    }

    const nextState = clonePrepared(pickerState);
    const previousState = clonePrepared(state.prepared);

    state.prepared = nextState;
    renderPreparedView();
    closeModal(selectors.pickerModal);

    persistPreparedSpells(nextState).catch(() => {
      state.prepared = previousState;
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
    const concentration = context.concentration ? 'Sí' : 'No';
    const savingThrowText = context.savingThrows.length
      ? context.savingThrows.map(formatSavingThrow).join(', ')
      : 'Añade manualmente la tirada de salvación.';
    const dcNote = context.savingThrows.length
      ? `DC estimada: 8 + bono de competencia + modificador de ${formatSavingThrowShort(context.savingThrows[0])}.`
      : 'Calcula la DC según tus estadísticas.';
    const conditions = context.conditions.length
      ? context.conditions.map((cond) => `<span class="grimorio-tag">${escapeHtml(cond)}</span>`).join(' ')
      : '<span class="grimorio-placeholder">Añade aquí las condiciones aplicadas.</span>';
    const summaryText = context.text
      ? escapeHtml(context.text.split('\n')[0]).slice(0, 400)
      : 'Agrega el efecto completo del conjuro en tus notas.';

    const source = spell?.source ? `<li><strong>Fuente:</strong> ${escapeHtml(spell.source)}</li>` : '';

    return `
      <ul class="grimorio-cast-summary">
        <li><strong>Concentración:</strong> ${concentration}</li>
        <li>
          <strong>Tirada de salvación:</strong> ${savingThrowText}
          <div class="grimorio-cast-summary__note">${escapeHtml(dcNote)}</div>
        </li>
        <li><strong>Condiciones:</strong> ${conditions}</li>
        ${source}
      </ul>
      <p class="grimorio-cast-summary__text">${summaryText}</p>
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
})();
