(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.formulario-hoja-personaje')) {
      return;
    }
    initManualOverrideState();
    initTempHpControls();
    initSheetModal();
    refreshAbilityDisplays();
    initSkillSaveSystem();
    initExtendedModule();
    initStandaloneClassSelects();
    initExpertiseManager();
    initClassReferenceModule();
    initCharacterAutomation();
    updateTheorySummaryVisibility();
  });

  const SELECT_PLACEHOLDERS = Object.freeze({
    class: 'Selecciona clase…',
    subclass: 'Selecciona subclase…',
    race: 'Selecciona raza…',
  });

  const PROF_TYPES = Object.freeze({
    weapons: {
      hidden: 'prof_weapons',
      display: 'display_cs_armas',
      select: 'profs-weapons-select',
      add: 'profs-weapons-add',
      list: 'profs-weapons-list',
    },
    armors: {
      hidden: 'prof_armors',
      display: 'display_cs_armaduras',
      select: 'profs-armors-select',
      add: 'profs-armors-add',
      list: 'profs-armors-list',
    },
    tools: {
      hidden: 'prof_tools',
      display: 'display_cs_herramientas',
      select: 'profs-tools-select',
      add: 'profs-tools-add',
      list: 'profs-tools-list',
    },
    languages: {
      hidden: 'prof_languages',
      display: 'display_cs_idiomas',
      select: 'profs-languages-select',
      add: 'profs-languages-add',
      list: 'profs-languages-list',
    },
  });

  const PROF_LABELS = Object.freeze({
    weapons: 'arma',
    armors: 'armadura',
    tools: 'herramienta',
    languages: 'idioma',
  });

  const abilityIds = [
    'cs_fuerza',
    'cs_destreza',
    'cs_constitucion',
    'cs_inteligencia',
    'cs_sabiduria',
    'cs_carisma',
    'cs_proeficiencia',
  ];

  const manualOverrideConfig = Object.freeze({
    cs_hp: 'cs_hp_manual_override',
  });

  const manualBasicOverrides = new Set();

  const STATIC_DATA = window.DND5_STATIC_DATA || null;
  const PRELOADED_THEORY_CATALOG = Array.isArray(window.APOTHECARY_THEORY_CATALOG)
    ? window.APOTHECARY_THEORY_CATALOG
    : null;
  const APOTHECARY_CLASS_IDS = new Set(['apothecary-scgtd-drakkenheim']);
  const APOTHECARY_MUTAGENIST_ID = 'apothecary-mutagenist-scgtd-drakkenheim';

  const SKILL_FIELD_BY_NAME = Object.freeze({
    'acrobatics': 'cs_skill_acrobacias',
    'animal handling': 'cs_skill_trato_animales',
    'arcana': 'cs_skill_arcanos',
    'athletics': 'cs_skill_atletismo',
    'deception': 'cs_skill_engano',
    'history': 'cs_skill_historia',
    'insight': 'cs_skill_perspicacia',
    'intimidation': 'cs_skill_intimidacion',
    'investigation': 'cs_skill_investigacion',
    'medicine': 'cs_skill_medicina',
    'nature': 'cs_skill_naturaleza',
    'perception': 'cs_skill_percepcion',
    'performance': 'cs_skill_interpretacion',
    'persuasion': 'cs_skill_persuasion',
    'religion': 'cs_skill_religion',
    'sleight of hand': 'cs_skill_juego_manos',
    'sleight-of-hand': 'cs_skill_juego_manos',
    'stealth': 'cs_skill_sigilo',
    'survival': 'cs_skill_supervivencia',
  });

  const SKILL_LABELS = Object.freeze({
    cs_skill_acrobacias: 'Acrobacias',
    cs_skill_juego_manos: 'Juego de Manos',
    cs_skill_sigilo: 'Sigilo',
    cs_skill_atletismo: 'Atletismo',
    cs_skill_trato_animales: 'Trato con Animales',
    cs_skill_perspicacia: 'Perspicacia',
    cs_skill_medicina: 'Medicina',
    cs_skill_percepcion: 'Percepción',
    cs_skill_supervivencia: 'Supervivencia',
    cs_skill_arcanos: 'Arcanos',
    cs_skill_historia: 'Historia',
    cs_skill_investigacion: 'Investigación',
    cs_skill_naturaleza: 'Naturaleza',
    cs_skill_religion: 'Religión',
    cs_skill_engano: 'Engaño',
    cs_skill_intimidacion: 'Intimidación',
    cs_skill_interpretacion: 'Interpretación',
    cs_skill_persuasion: 'Persuasión',
  });

  const SAVE_FIELDS = Object.freeze({
    str: 'cs_save_fuerza',
    dex: 'cs_save_destreza',
    con: 'cs_save_constitucion',
    int: 'cs_save_inteligencia',
    wis: 'cs_save_sabiduria',
    cha: 'cs_save_carisma',
  });

  const characterAutomationState = {
    autoSkills: new Set(),
    expertise: new Set(),
    manualSaves: new Map(),
    saveSources: new Map(),
    lastContextKey: '',
  };

  const characterDataStore = {
    promise: null,
    data: null,
  };
  let apothecaryTheoryCache = null;
  let apothecaryTheoryPromise = null;

  let characterRecalcTimer = null;

  const featureModuleApi = {
    invalidate: () => {},
  };

  const backgroundModuleApi = {
    invalidate: () => {},
  };

  const spellsModuleApi = {
    invalidate: () => {},
  };

  let recomputeSkillsAndSaves = () => {};

  function theorySlug(name) {
    const base = (name || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
    return base ? `apothecary-theory-${base}` : `apothecary-theory-${Math.random().toString(36).slice(2, 8)}`;
  }

  function isApothecaryClassId(value) {
    if (!value) return false;
    return APOTHECARY_CLASS_IDS.has(value);
  }

  function isMutagenistSubclassId(value) {
    return value === APOTHECARY_MUTAGENIST_ID;
  }

  function shouldEnableApothecaryTheories(classId, subclassId) {
    return isApothecaryClassId(classId) && isMutagenistSubclassId(subclassId);
  }

  function parseTheoryValue(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) {
      return raw.filter((val) => typeof val === 'string' && val.trim() !== '');
    }
    if (typeof raw === 'string') {
      try {
        const decoded = JSON.parse(raw);
        if (Array.isArray(decoded)) {
          return decoded.filter((val) => typeof val === 'string' && val.trim() !== '');
        }
      } catch (error) {
        // fall through to comma parsing
      }
      return raw
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
    }
    return [];
  }

  function getSelectedEsotericTheories() {
    const input = document.getElementById('apothecary_theories');
    if (!input) return [];
    return parseTheoryValue(input.value);
  }

  function getStaticEsotericTheoryList() {
    const raw = STATIC_DATA?.esotericTheoriesData || PRELOADED_THEORY_CATALOG;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') {
      return Object.values(raw);
    }
    return [];
  }

  function buildEsotericTheoryMap(list) {
    const map = {};
    (list || []).forEach((theory) => {
      if (!theory) return;
      const id = theory.id || theorySlug(theory.name || '');
      map[id] = {
        id,
        name: theory.name || id,
        level: Number(theory.level) || 0,
        entries: Array.isArray(theory.entries) ? theory.entries : [],
        source: theory.source || '',
        page: theory.page || '',
      };
    });
    return map;
  }

  function updateTheoryCacheFromList(list) {
    const map = buildEsotericTheoryMap(list);
    if (Object.keys(map).length) {
      apothecaryTheoryCache = map;
      if (characterDataStore.data) {
        characterDataStore.data.esotericTheories = map;
      }
    }
    return apothecaryTheoryCache || {};
  }

  function ensureEsotericTheoryCatalog() {
    if (apothecaryTheoryCache && Object.keys(apothecaryTheoryCache).length) {
      return apothecaryTheoryCache;
    }
    const data = characterDataStore.data;
    if (data && data.esotericTheories && Object.keys(data.esotericTheories).length) {
      apothecaryTheoryCache = data.esotericTheories;
      return apothecaryTheoryCache;
    }
    updateTheoryCacheFromList(getStaticEsotericTheoryList());
    return apothecaryTheoryCache || {};
  }

  function fetchApothecaryTheoryCatalog() {
    const existing = ensureEsotericTheoryCatalog();
    if (Object.keys(existing).length) {
      return Promise.resolve(existing);
    }
    if (apothecaryTheoryPromise) {
      return apothecaryTheoryPromise;
    }

    apothecaryTheoryPromise = Promise.resolve()
      .then(() => {
        // Try direct static JSON if available.
        if (STATIC_DATA?.esotericTheories) {
          return fetchStaticJson(STATIC_DATA.esotericTheories)
            .then((json) => {
              if (json?.classFeature) {
                return updateTheoryCacheFromList(json.classFeature);
              }
              return null;
            })
            .catch(() => null);
        }
        return null;
      })
      .then((result) => {
        if (result && Object.keys(result).length) {
          return result;
        }
        if (!window.DND5_API?.ajax_url) {
          return apothecaryTheoryCache || {};
        }
        const payload = new URLSearchParams({
          action: 'drak_dnd5_get_esoteric_theories',
        });
        return fetch(window.DND5_API.ajax_url, {
          method: 'POST',
          credentials: 'same-origin',
          body: payload,
        })
          .then((resp) => resp.json())
          .then((json) => {
            if (json?.success && Array.isArray(json.data?.theories)) {
              return updateTheoryCacheFromList(json.data.theories);
            }
            return apothecaryTheoryCache || {};
          })
          .catch(() => apothecaryTheoryCache || {});
      })
      .then((result) => result || apothecaryTheoryCache || {})
      .finally(() => {
        apothecaryTheoryPromise = null;
      });

    return apothecaryTheoryPromise;
  }

  function clampTheorySelection(ids, level, classId) {
    if (!isApothecaryClassId(classId) || !Number.isFinite(level) || level < 2) {
      return [];
    }
    const catalog = ensureEsotericTheoryCatalog();
    return ids.filter((id) => {
      const entry = catalog[id];
      if (!entry) return false;
      const required = Number(entry.level) || 0;
      return !required || level >= required;
    });
  }

  function formatTheoryNames(ids) {
    if (!ids || !ids.length) return '';
    const catalog = characterDataStore.data?.esotericTheories || {};
    const names = ids
      .map((id) => catalog[id]?.name || '')
      .filter((name) => name !== '');
    return names.join(', ');
  }

  function refreshApothecaryTheoryDisplay() {
    const display = document.getElementById('display_apothecary_theories');
    if (!display) return;
    const names = formatTheoryNames(getSelectedEsotericTheories());
    display.textContent = names || '—';
  }

  function updateTheorySummaryVisibility() {
    const block = document.querySelector('.basic-item-theories');
    if (!block) return;
    const currentClass = (document.getElementById('clase')?.value || '').trim();
    const currentSubclass = (document.getElementById('subclase')?.value || '').trim();
    const enabled = shouldEnableApothecaryTheories(currentClass, currentSubclass);
    block.classList.toggle('is-hidden', !enabled);
  }

  function setSelectedEsotericTheories(ids, options = {}) {
    const input = document.getElementById('apothecary_theories');
    if (!input) return;
    const silent = Boolean(options.silent);
    const classId = (document.getElementById('clase')?.value || '').trim();
    const subclassId = (document.getElementById('subclase')?.value || '').trim();
    const level = getNumberFromInput('nivel');
    let normalized = Array.isArray(ids)
      ? Array.from(new Set(ids.filter((val) => typeof val === 'string' && val !== '')))
      : [];
    if (!shouldEnableApothecaryTheories(classId, subclassId)) {
      normalized = [];
    } else if (characterDataStore.data) {
      normalized = clampTheorySelection(normalized, level, classId);
    }
    input.value = JSON.stringify(normalized);
    refreshApothecaryTheoryDisplay();
    updateTheorySummaryVisibility();
    if (!silent) {
      if (typeof featureModuleApi.invalidate === 'function') {
        featureModuleApi.invalidate();
      }
      scheduleCharacterRecalc();
    }
  }

  function qs(selector, scope = document) {
    return scope.querySelector(selector);
  }

  function qsa(selector, scope = document) {
    return Array.from(scope.querySelectorAll(selector));
  }

  function initManualOverrideState() {
    Object.entries(manualOverrideConfig).forEach(([fieldId, hiddenId]) => {
      const hidden = document.getElementById(hiddenId);
      if (hidden && hidden.value === '1') {
        manualBasicOverrides.add(fieldId);
      }
    });
  }

  function setManualOverride(fieldId, enabled) {
    const hiddenId = manualOverrideConfig[fieldId];
    if (!hiddenId) return;
    const hidden = document.getElementById(hiddenId);
    if (enabled) {
      manualBasicOverrides.add(fieldId);
      if (hidden) hidden.value = '1';
    } else {
      manualBasicOverrides.delete(fieldId);
      if (hidden) hidden.value = '';
    }
  }

  function hasManualOverride(fieldId) {
    return manualBasicOverrides.has(fieldId);
  }

  function formatMod(value) {
    if (!Number.isFinite(value)) return '0';
    return value > 0 ? `+${value}` : `${value}`;
  }

  function ajaxRequest(action, extra = {}) {
    if (typeof window.DND5_API === 'undefined') {
      return Promise.reject(new Error('DND5_API no disponible'));
    }

    const formData = new FormData();
    formData.append('action', action);
    Object.keys(extra).forEach((key) => formData.append(key, extra[key]));

    return fetch(window.DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    }).then((response) => response.json());
  }

  function populateSelect(select, placeholder, list, currentValue = '') {
    if (!select) return;

    select.innerHTML = '';
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    list.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.id || '';
      option.textContent = item.name || '';
      if (currentValue && option.value === currentValue) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  }

  const backgroundStore = {
    list: null,
    promise: null,
  };

  function fetchBackgroundData() {
    if (backgroundStore.list) {
      return Promise.resolve(backgroundStore.list);
    }
    if (backgroundStore.promise) {
      return backgroundStore.promise;
    }
    if (typeof window.DND5_API === 'undefined') {
      return Promise.reject(new Error('DND5_API no definido'));
    }
    const payload = new URLSearchParams({
      action: 'drak_dnd5_get_backgrounds',
    });
    backgroundStore.promise = fetch(window.DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((response) => response.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error('No se pudieron cargar los trasfondos.');
        }
        backgroundStore.list = json.data?.backgrounds || [];
        return backgroundStore.list;
      })
      .finally(() => {
        backgroundStore.promise = null;
      });
    return backgroundStore.promise;
  }

  function parseIds(value) {
    return (value || '')
      .split(',')
      .map((v) => v.trim())
      .filter(Boolean);
  }

  function serializeIds(value) {
    return value.join(',');
  }

  function parseExpertiseValue(value) {
    return (value || '')
      .split(',')
      .map((v) => v.trim())
      .filter(Boolean);
  }

  function getExpertiseHiddenField() {
    return document.getElementById('skills_expertise');
  }

  function getExpertiseSetFromHidden() {
    const hidden = getExpertiseHiddenField();
    if (!hidden) return new Set();
    return new Set(parseExpertiseValue(hidden.value));
  }

  function saveExpertiseSet(set) {
    const hidden = getExpertiseHiddenField();
    if (!hidden) return;
    hidden.value = Array.from(set).join(',');
  }

  function getNumberFromInput(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const raw = typeof el.value !== 'undefined' ? el.value : el.textContent;
    const parsed = parseInt(raw || '0', 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function refreshAbilityDisplays() {
    abilityIds.forEach((stat) => {
      const valueInput = document.getElementById(stat);
      const display = document.getElementById(`display_${stat}`);
      if (valueInput && display) {
        display.textContent = valueInput.value || '0';
      }

      if (stat === 'cs_proeficiencia') return;

      const modInput = document.getElementById(`${stat}_mod`);
      const modDisplay = document.getElementById(`display_${stat}_mod`);
      if (!modInput || !modDisplay) return;

      const modValue = parseInt(modInput.value || '0', 10);
      modDisplay.textContent = formatMod(modValue);
      modDisplay.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
      if (modValue > 0) modDisplay.classList.add('mod-pos');
      else if (modValue < 0) modDisplay.classList.add('mod-neg');
      else modDisplay.classList.add('mod-zero');
    });

    ['cs_iniciativa', 'cs_ac', 'cs_velocidad', 'cs_hp'].forEach((field) => {
      const valueInput = document.getElementById(field);
      const display = document.getElementById(`display_${field}`);
      if (valueInput && display) {
        display.textContent = valueInput.value || '0';
      }
    });

    const dexMod = document.getElementById('cs_destreza_mod');
    const iniInput = document.getElementById('cs_iniciativa');
    const iniDisplay = document.getElementById('display_cs_iniciativa');
    if (dexMod && iniInput && iniDisplay) {
      const value = parseInt(dexMod.value || '0', 10);
      iniInput.value = value;
      iniDisplay.textContent = formatMod(value);
      iniDisplay.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
      if (value > 0) iniDisplay.classList.add('mod-pos');
      else if (value < 0) iniDisplay.classList.add('mod-neg');
      else iniDisplay.classList.add('mod-zero');
    }
  }

  function initTempHpControls() {
    const slider = document.getElementById('slider_temp_hp');
    const display = document.getElementById('display_cs_temp_hp');
    const hidden = document.getElementById('cs_hp_temp');

    if (!slider || !display || !hidden) {
      return;
    }

    if (!window.HP_TEMP_AJAX || !window.HP_TEMP_AJAX.post_id) {
      console.warn('HP_TEMP_AJAX no está definido correctamente.');
    }

    let saveTimeout = null;

    function updateSliderGradient(value, max) {
      const percentage = max ? (value / max) * 100 : 0;
      let color = '#9933ff';
      if (percentage <= 33) color = '#ff4c4c';
      else if (percentage <= 66) color = '#ffcc00';
      slider.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${percentage}%, #444 ${percentage}%, #444 100%)`;
    }

    function syncTempHp(value) {
      const safeValue = Math.max(0, value);
      slider.value = safeValue;
      slider.setAttribute('value', safeValue);
      display.textContent = safeValue;
      hidden.value = safeValue;
      updateSliderGradient(safeValue, parseInt(slider.max || safeValue || '0', 10));
    }

    function persistTempHp(value) {
      if (!window.HP_TEMP_AJAX || !window.HP_TEMP_AJAX.post_id) return;
      const payload = new URLSearchParams({
        action: 'guardar_hp_temporal',
        post_id: window.HP_TEMP_AJAX.post_id,
        valor: value,
      });

      fetch(window.HP_TEMP_AJAX.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload,
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data?.success) {
            console.error('Error al guardar HP temporal', data?.message);
          }
        })
        .catch((error) => console.error('Error AJAX HP temporal', error));
    }

    function scheduleSave(value) {
      clearTimeout(saveTimeout);
      saveTimeout = setTimeout(() => persistTempHp(value), 500);
    }

    slider.addEventListener('input', () => {
      const value = parseInt(slider.value || '0', 10) || 0;
      syncTempHp(value);
      scheduleSave(value);
    });

    display.addEventListener('input', () => {
      const value = parseInt(display.textContent || '0', 10) || 0;
      syncTempHp(value);
      scheduleSave(value);
    });

    const resetBtn = document.getElementById('btn-reset-temp-pv');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        const hpField = document.getElementById('cs_hp');
        const hpValue = parseInt(hpField?.value || '0', 10);
        if (Number.isNaN(hpValue)) return;
        slider.max = hpValue;
        syncTempHp(hpValue);
        scheduleSave(hpValue);
      });
    }

    syncTempHp(parseInt(hidden.value || '0', 10));
  }

  function initSheetModal() {
    const overlay = document.getElementById('sheet-overlay');
    const openBtn = document.getElementById('btn-sheet-modal');
    const applyBtn = document.getElementById('sheet-apply');
    const closeBtn = overlay?.querySelector('.close-sheet-popup');
    const form = document.querySelector('.formulario-hoja-personaje');

    if (!overlay || !openBtn || !applyBtn) return;

    const statsSection = createStatsSectionController(overlay);
    const basicsSection = createBasicsSectionController(overlay);
    const profsSection = createProficiencySectionController(overlay);
    const theoriesSection = createEsotericTheoryController(overlay);

    function openModal() {
      statsSection.populate();
      basicsSection.populate();
      profsSection.populate();
      theoriesSection.populate();
      renderExpertiseList();
      populateExpertiseSelect();
      overlay.style.display = 'flex';
    }

    function closeModal() {
      overlay.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeModal();
    });

    applyBtn.addEventListener('click', () => {
      statsSection.apply();
      basicsSection.apply();
      profsSection.apply();
      theoriesSection.apply();
      refreshAbilityDisplays();
      recomputeSkillsAndSaves();
      recalculateCharacterSheet();
      scheduleCharacterRecalc();
      submitSheetForm(form, collectSpellcastingStats());
      closeModal();
    });

    basicsSection.loadInitialData();
    profsSection.ensureLookupCache();
  }

  function submitSheetForm(form, spellStats = null) {
    if (!form) return;
    const flag = form.querySelector('#hoja_guardar');
    if (flag) flag.value = '1';
    if (spellStats) {
      console.log('[Hoja] Enviando spell stats al backend', spellStats);
      Object.entries(spellStats).forEach(([key, value]) => {
        let hidden = form.querySelector(`input[name="${key}"]`);
        if (!hidden) {
          hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = key;
          hidden.id = key;
          form.appendChild(hidden);
        }
        hidden.value = value;
      });
    }
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  function collectSpellcastingStats() {
    const classId = document.getElementById('clase')?.value || '';
    if (!classId || !characterDataStore.data?.classDetails?.[classId]) {
      return null;
    }

    const classInfo = characterDataStore.data.classDetails[classId];
    const abilityKey = classInfo.spellcastingAbility;
    if (!abilityKey) {
      console.warn('[Hoja] Clase sin atributo lanzador definido', classId);
      return null;
    }

    const abilityFieldMap = {
      str: 'cs_fuerza',
      dex: 'cs_destreza',
      con: 'cs_constitucion',
      int: 'cs_inteligencia',
      wis: 'cs_sabiduria',
      cha: 'cs_carisma',
    };

    const scoreField = abilityFieldMap[abilityKey];
    const scoreInput = document.getElementById(scoreField);
    const profInput = document.getElementById('cs_proeficiencia');
    if (!scoreInput || !profInput) {
      console.warn('[Hoja] No se encontraron inputs para calcular spells', scoreField);
      return null;
    }

    const score = parseInt(scoreInput.value || '0', 10);
    const profBonus = parseInt(profInput.value || '0', 10);
    if (Number.isNaN(score) || Number.isNaN(profBonus)) {
      console.warn('[Hoja] Valores inválidos al calcular spells', { score, profBonus });
      return null;
    }

    const abilityMod = Math.floor((score - 10) / 2);
    const spellAttack = profBonus + abilityMod;
    const spellDc = 8 + profBonus + abilityMod;

    const abilityLabel = {
      str: 'Fuerza',
      dex: 'Destreza',
      con: 'Constitución',
      int: 'Inteligencia',
      wis: 'Sabiduría',
      cha: 'Carisma',
    }[abilityKey] || abilityKey.toUpperCase();

    const abilityShort = {
      str: 'FUE',
      dex: 'DES',
      con: 'CON',
      int: 'INT',
      wis: 'SAB',
      cha: 'CAR',
    }[abilityKey] || abilityKey.toUpperCase();

    console.log('[Hoja] Spell stats calculados', {
      abilityKey,
      abilityShort,
      score,
      profBonus,
      abilityMod,
      spellAttack,
      spellDc,
    });

    return {
      spellcasting_hability: abilityShort,
      spell_attack_bonus: spellAttack,
      spell_save_dc: spellDc,
    };
  }

  function createStatsSectionController(root) {
    const inputs = qsa('.stats-modal-input[data-stat]', root);

    function syncStatInput(input) {
      const stat = input.dataset.stat;
      if (!stat) return;
      const hidden = document.getElementById(stat);
      const display = document.getElementById(`display_${stat}`);
      const rawValue = input.value;
      const numeric = rawValue === '' ? Number.NaN : parseInt(rawValue, 10);
      const storedValue = Number.isNaN(numeric) ? '' : numeric;
      if (hidden) hidden.value = storedValue;
      if (display) {
        display.textContent = Number.isNaN(numeric) ? '0' : String(numeric);
      }

      if (stat === 'cs_proeficiencia') return;

      const modId = `${stat}_mod`;
      const modHidden = document.getElementById(modId);
      const modDisplay = document.getElementById(`display_${modId}`);
      if (!modHidden) return;

      const modValue = Number.isNaN(numeric) ? Number.NaN : Math.floor((numeric - 10) / 2);
      modHidden.value = Number.isNaN(modValue) ? '' : modValue;
      if (modDisplay) {
        const formatted = Number.isNaN(modValue) ? '0' : formatMod(modValue);
        modDisplay.textContent = formatted;
      }
    }

    inputs.forEach((input) => {
      input.addEventListener('input', () => syncStatInput(input));
    });

    function populate() {
      inputs.forEach((input) => {
        const stat = input.dataset.stat;
        const hidden = document.getElementById(stat);
        input.value = hidden?.value || '';
      });
    }

    function apply() {
      inputs.forEach((input) => syncStatInput(input));
    }

    return { populate, apply };
  }

  function createBasicsSectionController(root) {
    const hiddenBasics = {
      nivel: document.getElementById('nivel'),
      clase: document.getElementById('clase'),
      subclase: document.getElementById('subclase'),
      raza: document.getElementById('raza'),
      background: document.getElementById('background'),
      cs_iniciativa: document.getElementById('cs_iniciativa'),
      cs_ac: document.getElementById('cs_ac'),
      cs_velocidad: document.getElementById('cs_velocidad'),
      cs_hp: document.getElementById('cs_hp'),
    };

    const displayBasics = {
      nivel: document.getElementById('display_nivel'),
      clase: document.getElementById('display_clase'),
      subclase: document.getElementById('display_subclase'),
      raza: document.getElementById('display_raza'),
      background: document.getElementById('display_background'),
      cs_iniciativa: document.getElementById('display_cs_iniciativa'),
      cs_ac: document.getElementById('display_cs_ac'),
      cs_velocidad: document.getElementById('display_cs_velocidad'),
      cs_hp: document.getElementById('display_cs_hp'),
    };

    const modalInputs = qsa('.basics-modal-input[data-basic]', root);
    const classSelect = root.querySelector('#modal-clase');
    const subclassSelect = root.querySelector('#modal-subclase');
    const raceSelect = root.querySelector('#modal-raza');
    const backgroundSelect = root.querySelector('#modal-background');

    function populate() {
      modalInputs.forEach((input) => {
        const key = input.dataset.basic;
        const hidden = hiddenBasics[key];
        if (!hidden) return;
        const raw = hidden.value || '';
        if (input.type === 'number') {
          const parsed = parseInt(raw, 10);
          input.value = Number.isNaN(parsed) ? '' : parsed;
        } else {
          input.value = raw;
        }
      });

      if (classSelect && hiddenBasics.clase) {
        classSelect.value = hiddenBasics.clase.value || '';
      }
      if (subclassSelect && hiddenBasics.subclase) {
        subclassSelect.value = hiddenBasics.subclase.value || '';
      }
      if (raceSelect && hiddenBasics.raza) {
        raceSelect.value = hiddenBasics.raza.value || '';
      }
      if (backgroundSelect && hiddenBasics.background) {
        backgroundSelect.value = hiddenBasics.background.value || '';
      }
    }

    function apply() {
      modalInputs.forEach((input) => {
        const key = input.dataset.basic;
        const hidden = hiddenBasics[key];
        const display = displayBasics[key];
        if (!hidden || !display) return;
        const value = input.value === '' ? '' : input.value;
        hidden.value = value;
        display.textContent = value || '';
        if (key === 'cs_hp') {
          setManualOverride('cs_hp', value !== '');
        }
      });

      syncSelect(classSelect, hiddenBasics.clase, displayBasics.clase);
      syncSelect(subclassSelect, hiddenBasics.subclase, displayBasics.subclase);
      syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza);
      syncSelect(backgroundSelect, hiddenBasics.background, displayBasics.background);
    }

    function syncSelect(select, hiddenField, displayField, options = {}) {
      const silent = Boolean(options.silent);
      if (!select || !hiddenField || !displayField) return;
      const option = select.options[select.selectedIndex];
      const nextValue = select.value || '';
      const prevValue = hiddenField.value || '';
      displayField.textContent = nextValue ? (option ? option.textContent : '') : '';
      hiddenField.value = nextValue;

      if (silent || prevValue === nextValue) return;

      const watchIds = ['clase', 'subclase', 'raza'];
      if (watchIds.includes(hiddenField.id)) {
        featureModuleApi.invalidate();
      }
      if (hiddenField.id === 'background') {
        backgroundModuleApi.invalidate();
      }
      if (hiddenField.id === 'clase') {
        spellsModuleApi.invalidate();
      }
      if (hiddenField.id === 'clase' || hiddenField.id === 'subclase') {
        updateTheorySummaryVisibility();
        const currentClass = hiddenBasics.clase?.value || '';
        const currentSubclass = hiddenBasics.subclase?.value || '';
        if (!shouldEnableApothecaryTheories(currentClass, currentSubclass)) {
          if (getSelectedEsotericTheories().length) {
            setSelectedEsotericTheories([], { silent: true });
          }
        } else {
          setSelectedEsotericTheories(getSelectedEsotericTheories(), { silent: true });
        }
      }
      scheduleCharacterRecalc();
    }

    function loadClasses() {
      if (!classSelect || typeof window.DND5_API === 'undefined') return;
      ajaxRequest('drak_dnd5_get_classes')
        .then((res) => {
          if (!res?.success) return;
          const classes = res.data?.classes || [];
          populateSelect(
            classSelect,
            classes.length ? SELECT_PLACEHOLDERS.class : 'No hay clases disponibles',
            classes,
            hiddenBasics.clase?.value || ''
          );
          syncSelect(classSelect, hiddenBasics.clase, displayBasics.clase, { silent: true });
          const currentClass = classSelect.value || hiddenBasics.clase?.value || '';
          loadSubclasses(currentClass, hiddenBasics.subclase?.value || '');
        })
        .catch(() => {
          populateSelect(classSelect, 'Error al cargar clases', []);
        });
    }

    function loadSubclasses(classId, preselect) {
      if (!subclassSelect) return;
      if (!classId || typeof window.DND5_API === 'undefined') {
        populateSelect(subclassSelect, SELECT_PLACEHOLDERS.subclass, []);
        subclassSelect.disabled = true;
        return;
      }

      subclassSelect.disabled = true;
      subclassSelect.innerHTML = '<option value="">Cargando subclases…</option>';

      ajaxRequest('drak_dnd5_get_subclasses', { class_index: classId })
        .then((res) => {
          if (!res?.success) {
            populateSelect(subclassSelect, 'No hay subclases disponibles', []);
            subclassSelect.disabled = true;
            return;
          }
          const subclasses = res.data?.subclasses || [];
          populateSelect(
            subclassSelect,
            subclasses.length ? SELECT_PLACEHOLDERS.subclass : 'No hay subclases disponibles',
            subclasses,
            preselect || hiddenBasics.subclase?.value || ''
          );
          subclassSelect.disabled = subclasses.length === 0;
          syncSelect(subclassSelect, hiddenBasics.subclase, displayBasics.subclase, { silent: true });
        })
        .catch(() => {
          populateSelect(subclassSelect, 'Error al cargar subclases', []);
          subclassSelect.disabled = true;
          syncSelect(subclassSelect, hiddenBasics.subclase, displayBasics.subclase, { silent: true });
        });
    }

    function loadRaces() {
      if (!raceSelect || typeof window.DND5_API === 'undefined') return;
      raceSelect.disabled = true;
      raceSelect.innerHTML = '<option value="">Cargando razas…</option>';

      ajaxRequest('drak_dnd5_get_races')
        .then((res) => {
          if (!res?.success) {
            populateSelect(raceSelect, 'Error al cargar razas', []);
            raceSelect.disabled = true;
            syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza, { silent: true });
            return;
          }
          const races = res.data?.races || [];
          populateSelect(
            raceSelect,
            races.length ? SELECT_PLACEHOLDERS.race : 'No hay razas disponibles',
            races,
            hiddenBasics.raza?.value || ''
          );
          raceSelect.disabled = races.length === 0;
          syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza, { silent: true });
        })
        .catch(() => {
          populateSelect(raceSelect, 'Error al cargar razas', []);
          raceSelect.disabled = true;
          syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza, { silent: true });
        });
    }

    function loadBackgrounds() {
      if (!backgroundSelect) return;
      backgroundSelect.disabled = true;
      backgroundSelect.innerHTML = '<option value="">Cargando trasfondos…</option>';

      fetchBackgroundData()
        .then((list) => {
          const current = hiddenBasics.background?.value || '';
          populateSelect(
            backgroundSelect,
            list.length ? 'Selecciona trasfondo…' : 'No hay trasfondos disponibles',
            list,
            current
          );
          backgroundSelect.disabled = list.length === 0;
          syncSelect(backgroundSelect, hiddenBasics.background, displayBasics.background, { silent: true });
        })
        .catch(() => {
          populateSelect(backgroundSelect, 'Error al cargar trasfondos', []);
          backgroundSelect.disabled = true;
          syncSelect(backgroundSelect, hiddenBasics.background, displayBasics.background, { silent: true });
        });
    }

    if (classSelect) {
      classSelect.addEventListener('change', () => {
        if (subclassSelect) {
          subclassSelect.value = '';
        }
        loadSubclasses(classSelect.value, '');
      });
    }

    return {
      populate,
      apply,
      loadInitialData() {
        loadClasses();
        loadRaces();
        loadBackgrounds();
      },
    };
  }

  function createEsotericTheoryController(root) {
    const container = root.querySelector('#esoteric-theories-container');
    const classSelect = root.querySelector('#modal-clase');
    const subclassSelect = root.querySelector('#modal-subclase');
    const levelInput = root.querySelector('.basics-modal-input[data-basic="nivel"]');
    const sectionWrapper = root.querySelector('[data-theory-section]');

    if (!container) {
      return { populate: () => {}, apply: () => {} };
    }

    function modalLevel() {
      const raw = levelInput?.value ?? document.getElementById('nivel')?.value ?? '0';
      const parsed = parseInt(raw, 10);
      return Number.isNaN(parsed) ? 0 : parsed;
    }

    function modalClass() {
      return (classSelect?.value || document.getElementById('clase')?.value || '').trim();
    }

    function modalSubclass() {
      return (subclassSelect?.value || document.getElementById('subclase')?.value || '').trim();
    }

    function setSectionVisibility(visible) {
      if (sectionWrapper) {
        sectionWrapper.classList.toggle('is-hidden', !visible);
      }
    }

    function renderEmptyState(message) {
      container.innerHTML = `<p class="theory-picker__hint">${escapeHtml(message)}</p>`;
    }

    function bindCheckboxes() {
      container.querySelectorAll('input[data-theory-id]').forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
          const ids = new Set(getSelectedEsotericTheories());
          const theoryId = event.target.dataset.theoryId;
          if (!theoryId) return;
          if (event.target.checked) {
            ids.add(theoryId);
          } else {
            ids.delete(theoryId);
          }
          setSelectedEsotericTheories(Array.from(ids));
        });
      });
    }

    function renderOptions() {
      const classId = modalClass();
      const subclassId = modalSubclass();
      const level = modalLevel();
      const enabled = shouldEnableApothecaryTheories(classId, subclassId);

      setSectionVisibility(enabled);

      if (!enabled) {
        setSelectedEsotericTheories([], { silent: true });
        renderEmptyState('Disponible únicamente para la subclase Mutagenist.');
        return;
      }

      if (level < 2) {
        setSelectedEsotericTheories([], { silent: true });
        renderEmptyState('Disponibles a partir de nivel 2 de Apothecary.');
        return;
      }

      container.innerHTML = '<p class="theory-picker__hint">Cargando teorías esotéricas…</p>';
      fetchApothecaryTheoryCatalog()
        .then((catalog) => {
          const entries = Object.values(catalog || {});
          if (!entries.length) {
            renderEmptyState('No se encontraron teorías.');
            return;
          }

          const currentSelection = getSelectedEsotericTheories();
          const sanitized = clampTheorySelection(currentSelection, level, classId);
          if (sanitized.length !== currentSelection.length) {
            setSelectedEsotericTheories(sanitized, { silent: true });
          } else {
            refreshApothecaryTheoryDisplay();
          }

          const selection = new Set(getSelectedEsotericTheories());
          const sorted = entries.sort((a, b) => {
            if (a.level !== b.level) {
              return a.level - b.level;
            }
            return (a.name || '').localeCompare(b.name || '');
          });

          const content = sorted
            .map((theory) => {
              const requiredLevel = Number(theory.level) || 0;
              const unlocked = !requiredLevel || level >= requiredLevel;
              const checked = unlocked && selection.has(theory.id);
              const requirement = requiredLevel ? `Requiere nivel ${requiredLevel}` : 'Sin requisito';
              const disabledAttr = unlocked ? '' : 'disabled';
              const checkedAttr = checked ? 'checked' : '';
              return `
                <label class="theory-picker__option ${unlocked ? '' : 'is-disabled'}">
                  <input type="checkbox" data-theory-id="${theory.id}" ${checkedAttr} ${disabledAttr}>
                  <span class="theory-picker__option-name">${escapeHtml(theory.name || theory.id)}</span>
                  <small class="theory-picker__option-meta">${escapeHtml(requirement)}</small>
                </label>
              `;
            })
            .join('');

          container.innerHTML = content || '<p class="theory-picker__hint">No hay teorías disponibles.</p>';
          bindCheckboxes();
        })
        .catch(() => {
          renderEmptyState('No se pudieron cargar las teorías.');
        });
    }

    if (classSelect) {
      classSelect.addEventListener('change', renderOptions);
    }
    if (subclassSelect) {
      subclassSelect.addEventListener('change', renderOptions);
    }
    if (levelInput) {
      levelInput.addEventListener('input', renderOptions);
    }

    function populate() {
      refreshApothecaryTheoryDisplay();
      renderOptions();
    }

    function apply() {
      refreshApothecaryTheoryDisplay();
    }

    return { populate, apply };
  }

  function createProficiencySectionController(root) {
    const hasApi = typeof window.DND5_API !== 'undefined';
    const config = Object.entries(PROF_TYPES).reduce((acc, [type, ids]) => {
      acc[type] = {
        hidden: document.getElementById(ids.hidden),
        display: document.getElementById(ids.display),
        select: root.querySelector(`#${ids.select}`),
        addBtn: root.querySelector(`#${ids.add}`),
        list: root.querySelector(`#${ids.list}`),
        values: parseIds(document.getElementById(ids.hidden)?.value || ''),
        options: [],
      };
      return acc;
    }, {});

    let profDataPromise = null;

    function fetchProficiencies() {
      if (!hasApi) return Promise.resolve({});
      if (profDataPromise) return profDataPromise;
      const formData = new FormData();
      formData.append('action', 'drak_dnd5_get_proficiencies');
      profDataPromise = fetch(window.DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => (data?.success ? data.data : {}))
        .catch((error) => {
          console.error('Error al cargar competencias', error);
          return {};
        })
        .finally(() => {
          profDataPromise = null;
        });
      return profDataPromise;
    }

    function findName(list, id) {
      if (!id) return '';
      const match = list.find((item) => item.id === id);
      return match ? match.name || match.id : id;
    }

    function renderList(type) {
      const info = config[type];
      if (!info?.list) return;
      info.list.innerHTML = '';
      info.values.forEach((id) => {
        const li = document.createElement('li');
        li.dataset.id = id;
        li.textContent = findName(info.options, id);
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.className = 'btn-basicos-mod profs-remove';
        removeBtn.addEventListener('click', () => {
          info.values = info.values.filter((value) => value !== id);
          renderList(type);
        });
        li.appendChild(removeBtn);
        info.list.appendChild(li);
      });
    }

    function refreshSheetDisplays() {
      Object.values(config).forEach((info) => {
        if (!info?.hidden || !info.display) return;
        const ids = parseIds(info.hidden.value);
        if (!ids.length) {
          return;
        }
        const names = ids.map((id) => findName(info.options, id));
        info.display.textContent = names.join(', ') || '—';
      });
    }

    Object.entries(config).forEach(([type, info]) => {
      if (info.addBtn && info.select) {
        info.addBtn.addEventListener('click', () => {
          const value = info.select.value;
          if (!value || info.values.includes(value)) return;
          info.values.push(value);
          renderList(type);
        });
      }
    });

    function ensureLookupCache() {
      return fetchProficiencies().then((data) => {
        Object.entries(config).forEach(([type, info]) => {
          if (!info.options.length) {
            info.options = data?.[type] || [];
          }
        });
        refreshSheetDisplays();
        return data;
      });
    }

    function populate() {
      ensureLookupCache().then(() => {
        Object.entries(config).forEach(([type, info]) => {
          const label = PROF_LABELS[type] || type;
          populateSelect(info.select, `Selecciona ${label}…`, info.options, '');
          info.values = parseIds(info.hidden?.value || '');
          renderList(type);
        });
      });
    }

    function apply() {
      Object.values(config).forEach((info) => {
        if (!info.hidden) return;
        info.hidden.value = serializeIds(info.values);
      });
      refreshSheetDisplays();
    }

    return { populate, apply, ensureLookupCache };
  }

  function initSkillSaveSystem() {
    const profBonusInput = document.getElementById('cs_proeficiencia');
    if (!profBonusInput) {
      recomputeSkillsAndSaves = () => {};
      return;
    }

    const skillAbilityMap = {
      cs_skill_acrobacias: 'cs_destreza_mod',
      cs_skill_juego_manos: 'cs_destreza_mod',
      cs_skill_sigilo: 'cs_destreza_mod',
      cs_skill_atletismo: 'cs_fuerza_mod',
      cs_skill_trato_animales: 'cs_sabiduria_mod',
      cs_skill_perspicacia: 'cs_sabiduria_mod',
      cs_skill_medicina: 'cs_sabiduria_mod',
      cs_skill_percepcion: 'cs_sabiduria_mod',
      cs_skill_supervivencia: 'cs_sabiduria_mod',
      cs_skill_arcanos: 'cs_inteligencia_mod',
      cs_skill_historia: 'cs_inteligencia_mod',
      cs_skill_investigacion: 'cs_inteligencia_mod',
      cs_skill_naturaleza: 'cs_inteligencia_mod',
      cs_skill_religion: 'cs_inteligencia_mod',
      cs_skill_engano: 'cs_carisma_mod',
      cs_skill_intimidacion: 'cs_carisma_mod',
      cs_skill_interpretacion: 'cs_carisma_mod',
      cs_skill_persuasion: 'cs_carisma_mod',
    };

    const saveAbilityMap = {
      cs_save_fuerza: 'cs_fuerza_mod',
      cs_save_destreza: 'cs_destreza_mod',
      cs_save_constitucion: 'cs_constitucion_mod',
      cs_save_inteligencia: 'cs_inteligencia_mod',
      cs_save_sabiduria: 'cs_sabiduria_mod',
      cs_save_carisma: 'cs_carisma_mod',
    };

    function setDisplayAndClasses(fieldId, value) {
      const display = document.getElementById(`display_${fieldId}`);
      if (!display) return;
      display.textContent = formatMod(value);
      display.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
      if (value > 0) display.classList.add('mod-pos');
      else if (value < 0) display.classList.add('mod-neg');
      else display.classList.add('mod-zero');
    }

    function updateSkill(skillId) {
      const abilityId = skillAbilityMap[skillId];
      if (!abilityId) return;
      const abilityMod = getNumberFromInput(abilityId);
      const profBonus = getNumberFromInput('cs_proeficiencia');
      const profInput = document.getElementById(`prof_${skillId}`);
      const isProf = profInput?.value === '1';
      const hasExpertise = characterAutomationState.expertise.has(skillId);

      const total = abilityMod + (isProf ? profBonus : 0) + (hasExpertise ? profBonus : 0);

      const hiddenField = document.getElementById(skillId);
      if (hiddenField) hiddenField.value = total;
      setDisplayAndClasses(skillId, total);
    }

    function updateSave(saveId) {
      const abilityId = saveAbilityMap[saveId];
      if (!abilityId) return;
      const abilityMod = getNumberFromInput(abilityId);
      const profBonus = getNumberFromInput('cs_proeficiencia');
      const profInput = document.getElementById(`prof_${saveId}`);
      const isProf = profInput?.value === '1';
      const total = abilityMod + (isProf ? profBonus : 0);
      const hiddenField = document.getElementById(saveId);
      if (hiddenField) hiddenField.value = total;
      setDisplayAndClasses(saveId, total);
    }

    qsa('.skill-indicator[data-save-indicator]').forEach((indicator) => {
      const saveId = indicator.dataset.saveIndicator;
      if (!saveId) return;
      const profInput = document.getElementById(`prof_${saveId}`);
      const icon = indicator.querySelector('[data-save-icon]');

      if (profInput?.value === '1') {
        icon?.classList.add('skill-icon--prof');
      }

      indicator.addEventListener('click', () => {
        if (!profInput) return;
        const newValue = profInput.value === '1' ? '0' : '1';
        profInput.value = newValue;
        icon?.classList.toggle('skill-icon--prof', newValue === '1');
        updateSave(saveId);
        characterAutomationState.manualSaves.set(saveId, newValue === '1');
        scheduleCharacterRecalc();
      });
    });

    recomputeSkillsAndSaves = () => {
      Object.keys(skillAbilityMap).forEach(updateSkill);
      Object.keys(saveAbilityMap).forEach(updateSave);
    };

    recomputeSkillsAndSaves();
  }

  function initExtendedModule() {
    const moduleEl = document.getElementById('character-extended-module');
    const panelEl = document.getElementById('character-extended-panel');
    if (!moduleEl || !panelEl) {
      featureModuleApi.invalidate = () => {};
      return;
    }

    const tabs = moduleEl.querySelectorAll('.character-extended__tab[data-ext-tab]');
    const defaultTab = moduleEl.querySelector('.character-extended__tab.is-active');
    let activeTab = defaultTab ? defaultTab.dataset.extTab : (tabs[0]?.dataset.extTab || 'features');
    let featureCache = null;
    let featureCacheKey = '';
    let isLoading = false;
    let actionsCache = null;
    let actionsLoading = false;
    let backgroundList = null;
    let backgroundPromise = null;
    let spellsCache = {};
    let spellsLoading = false;

    const tabLabels = {
      features: 'Features & Traits',
      spells: 'Spells',
      actions: 'Acciones',
      background: 'Background',
    };

    function setActiveTab(tabName) {
      tabs.forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.extTab === tabName);
      });
    }

    function readValue(id) {
      const input = document.getElementById(id);
      return input ? (input.value || '').trim() : '';
    }

    function currentKey() {
      const theoryKey = JSON.stringify(getSelectedEsotericTheories() || []);
      return [readValue('clase'), readValue('subclase'), readValue('raza'), theoryKey].join('|');
    }

    function showLoading() {
      panelEl.innerHTML = '<p class="character-extended__loading">Cargando datos...</p>';
    }

    function showEmpty(message) {
      panelEl.innerHTML = `<p class="character-extended__empty">${message}</p>`;
    }

    function showError(message) {
      panelEl.innerHTML = `<p class="character-extended__error">${message}</p>`;
    }

    function renderFeatures(data) {
      if (!data) {
        showEmpty('Sin datos disponibles.');
        return;
      }

      const blocks = [];

      if (data.race) {
        const raceEntries = getLocalizedArrayFrom(data.race, 'entries');
        const content = renderEntries(raceEntries);
        if (content) {
          blocks.push(`
            <section class="character-extended__section">
              <h4 class="character-extended__section-title">Rasgos raciales · ${escapeHtml(data.race.name || '')}</h4>
              ${content}
            </section>
          `);
        }
      }

      if (data.class && Array.isArray(data.class.features) && data.class.features.length) {
        const entries = data.class.features.map((feature) => {
          const bodyEntries = getLocalizedArrayFrom(feature, 'entries');
          const shortEntries = getLocalizedArrayFrom(feature, 'shortEntries');
          const contentEntries = bodyEntries.length ? bodyEntries : shortEntries;
          const body = renderEntries(contentEntries);
          const level = feature.level ? `Nivel ${feature.level}` : '';
          const metaParts = [];
          if (level) metaParts.push(level);
          if (feature.source) metaParts.push(escapeHtml(feature.source));
          const meta = metaParts.length ? `<div class="feature-card__meta">${metaParts.join(' · ')}</div>` : '';
          return `
            <article class="feature-card is-collapsed">
              <header class="feature-card__header">
                <h5 class="feature-card__title">${escapeHtml(feature.name || 'Rasgo')}</h5>
                <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle">
                  <span class="feature-card__toggle-icon">▼</span>
                </button>
              </header>
              <div class="feature-card__content">
                ${meta}
                <div class="feature-card__body">${body || '<p>Sin descripción.</p>'}</div>
              </div>
            </article>
          `;
        }).join('');

        blocks.push(`
          <section class="character-extended__section">
            <h4 class="character-extended__section-title">Rasgos de clase · ${escapeHtml(data.class.name || '')}</h4>
            ${entries}
          </section>
        `);
      }

      if (Array.isArray(data.esotericTheories) && data.esotericTheories.length) {
        const entries = data.esotericTheories.map((feature) => {
          const bodyEntries = getLocalizedArrayFrom(feature, 'entries');
          const body = renderEntries(bodyEntries);
          const level = feature.level ? `Nivel ${feature.level}` : '';
          const metaParts = [];
          if (level) metaParts.push(level);
          if (feature.source) metaParts.push(escapeHtml(feature.source));
          const meta = metaParts.length ? `<div class="feature-card__meta">${metaParts.join(' · ')}</div>` : '';
          return `
            <article class="feature-card is-collapsed">
              <header class="feature-card__header">
                <h5 class="feature-card__title">${escapeHtml(feature.name || 'Teoría')}</h5>
                <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle">
                  <span class="feature-card__toggle-icon">▼</span>
                </button>
              </header>
              <div class="feature-card__content">
                ${meta}
                <div class="feature-card__body">${body || '<p>Sin descripción.</p>'}</div>
              </div>
            </article>
          `;
        }).join('');

        blocks.push(`
          <section class="character-extended__section">
            <h4 class="character-extended__section-title">Teorías esotéricas</h4>
            ${entries}
          </section>
        `);
      }

      if (data.subclass && data.subclass.features && data.subclass.features.length) {
        const entries = data.subclass.features.map((feature) => {
          const bodyEntries = getLocalizedArrayFrom(feature, 'entries');
          const shortEntries = getLocalizedArrayFrom(feature, 'shortEntries');
          const contentEntries = bodyEntries.length ? bodyEntries : shortEntries;
          const body = renderEntries(contentEntries);
          const level = feature.level ? `Nivel ${feature.level}` : '';
          const metaParts = [];
          if (level) metaParts.push(level);
          if (feature.source) metaParts.push(escapeHtml(feature.source));
          const meta = metaParts.length ? `<div class="feature-card__meta">${metaParts.join(' · ')}</div>` : '';
          return `
            <article class="feature-card is-collapsed">
              <header class="feature-card__header">
                <h5 class="feature-card__title">${escapeHtml(feature.name || 'Rasgo')}</h5>
                <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle">
                  <span class="feature-card__toggle-icon">▼</span>
                </button>
              </header>
              <div class="feature-card__content">
                ${meta}
                <div class="feature-card__body">${body || '<p>Sin descripción.</p>'}</div>
              </div>
            </article>
          `;
        }).join('');

        blocks.push(`
          <section class="character-extended__section">
            <h4 class="character-extended__section-title">Rasgos de subclase · ${escapeHtml(data.subclass.name || '')}</h4>
            ${entries}
          </section>
        `);
      }

      if (!blocks.length) {
        showEmpty('No hay rasgos disponibles para esta combinación.');
        return;
      }

      panelEl.innerHTML = blocks.join('');
      initFeatureAccordions(panelEl);
    }

    function initFeatureAccordions(root) {
      root.querySelectorAll('.feature-card').forEach((card) => {
        const toggle = card.querySelector('.feature-card__toggle');
        if (!toggle) return;
        const icon = toggle.querySelector('.feature-card__toggle-icon');

        const setState = (expanded) => {
          card.classList.toggle('is-collapsed', !expanded);
          toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          toggle.setAttribute('aria-label', expanded ? 'Ocultar detalle' : 'Mostrar detalle');
          if (icon) icon.textContent = expanded ? '▲' : '▼';
        };

        setState(false);

        toggle.addEventListener('click', () => {
          const shouldExpand = card.classList.contains('is-collapsed');
          setState(shouldExpand);
        });
      });
    }

    function initSpellLevelAccordions(root) {
      root.querySelectorAll('.spell-level').forEach((section) => {
        const toggle = section.querySelector('.spell-level__toggle');
        if (!toggle) return;
        const icon = toggle.querySelector('.feature-card__toggle-icon');

        const setState = (expanded) => {
          section.classList.toggle('is-collapsed', !expanded);
          toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          toggle.setAttribute('aria-label', expanded ? 'Ocultar conjuros' : 'Mostrar conjuros');
          if (icon) icon.textContent = expanded ? '▲' : '▼';
        };

        setState(false);

        toggle.addEventListener('click', () => {
          const shouldExpand = section.classList.contains('is-collapsed');
          setState(shouldExpand);
        });
      });
    }

    function fetchFeatures(force = false) {
      const key = currentKey();
      if (!force && featureCache && featureCacheKey === key) {
        renderFeatures(featureCache);
        return;
      }

      if (isLoading) {
        return;
      }

      if (typeof window.DND5_API === 'undefined') {
        showError('No se pudo localizar el endpoint de datos.');
        return;
      }

      isLoading = true;
      showLoading();

      const payload = new URLSearchParams({
        action: 'drak_dnd5_get_feature_traits',
        class_id: readValue('clase'),
        subclass_id: readValue('subclase'),
        race_id: readValue('raza'),
      });
      const theoryValue = document.getElementById('apothecary_theories')?.value || '';
      if (theoryValue) {
        payload.append('apothecary_theories', theoryValue);
      }

      fetch(window.DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload,
      })
        .then((resp) => resp.json())
        .then((json) => {
          isLoading = false;
          if (!json || !json.success) {
            showError('No se pudieron cargar los rasgos.');
            return;
          }
          featureCache = json.data;
          featureCacheKey = key;
          renderFeatures(featureCache);
        })
        .catch(() => {
          isLoading = false;
          showError('Error de conexión al cargar los rasgos.');
        });
    }

    function handleTabChange(tabName) {
      activeTab = tabName;
      setActiveTab(tabName);

      if (tabName === 'features') {
        fetchFeatures();
        return;
      }

      if (tabName === 'spells') {
        fetchSpells();
        return;
      }

      if (tabName === 'actions') {
        fetchActions();
        return;
      }

      if (tabName === 'background') {
        fetchBackgroundView();
        return;
      }

      const label = tabLabels[tabName] || tabName;
      showEmpty(`La sección “${label}” estará disponible próximamente.`);
    }

    function fetchActions() {
      if (actionsCache) {
        renderActions(actionsCache);
        return;
      }

      if (actionsLoading) {
        return;
      }

      if (typeof window.DND5_API === 'undefined') {
        showError('No se pudo localizar el endpoint de datos.');
        return;
      }

      actionsLoading = true;
      showLoading();

      const payload = new URLSearchParams({
        action: 'drak_dnd5_get_actions',
      });

      fetch(window.DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload,
      })
        .then((resp) => resp.json())
        .then((json) => {
          actionsLoading = false;
          if (!json || !json.success) {
            showError('No se pudieron cargar las acciones.');
            return;
          }
          actionsCache = json.data?.actions || [];
          renderActions(actionsCache);
        })
        .catch(() => {
          actionsLoading = false;
          showError('Error de conexión al cargar las acciones.');
        });
    }

    function renderActions(list) {
      if (!list || !list.length) {
        showEmpty('No hay acciones disponibles.');
        return;
      }

      const cards = list
        .map((action) => {
          const time = formatActionTime(action.time);
          const metaParts = [];
          if (time) metaParts.push(time);
          if (action.source) metaParts.push(escapeHtml(action.source));
          const meta = metaParts.length ? `<div class="feature-card__meta">${metaParts.join(' · ')}</div>` : '';
          const body = renderEntries(action.entries || []);
          return `
            <article class="feature-card is-collapsed">
              <header class="feature-card__header">
                <h5 class="feature-card__title">${escapeHtml(action.name || 'Acción')}</h5>
                <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle de la acción">
                  <span class="feature-card__toggle-icon">▼</span>
                </button>
              </header>
              <div class="feature-card__content">
                ${meta}
                <div class="feature-card__body">${body || '<p>Sin descripción.</p>'}</div>
              </div>
            </article>
          `;
        })
        .join('');

      panelEl.innerHTML = `
        <section class="character-extended__section">
          <h4 class="character-extended__section-title">Acciones generales</h4>
          ${cards}
        </section>
      `;
      initFeatureAccordions(panelEl);
    }

    function fetchSpells(force = false) {
      const classId = readValue('clase');
      if (!classId) {
        showEmpty('Selecciona una clase para ver los conjuros disponibles.');
        return;
      }

      if (!force && spellsCache[classId]) {
        renderSpells(spellsCache[classId]);
        return;
      }

      if (spellsLoading) {
        return;
      }

      if (typeof window.DND5_API === 'undefined') {
        showError('No se pudo localizar el endpoint de datos.');
        return;
      }

      spellsLoading = true;
      showLoading();

      const payload = new URLSearchParams({
        action: 'drak_dnd5_get_spells',
        class_id: classId,
      });

      fetch(window.DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload,
      })
        .then((resp) => resp.json())
        .then((json) => {
          spellsLoading = false;
          if (!json || !json.success) {
            showError('No se pudieron cargar los conjuros.');
            return;
          }
          const list = json.data?.spells || [];
          spellsCache[classId] = list;
          renderSpells(list);
        })
        .catch(() => {
          spellsLoading = false;
          showError('Error de conexión al cargar los conjuros.');
        });
    }

    function renderSpells(list) {
      if (!list || !list.length) {
        showEmpty('La clase seleccionada no tiene conjuros disponibles.');
        return;
      }

      const groups = list.reduce((acc, spell) => {
        const lvl = typeof spell.level === 'number' ? spell.level : 0;
        acc[lvl] = acc[lvl] || [];
        acc[lvl].push(spell);
        return acc;
      }, {});

      const levels = Object.keys(groups)
        .map((lvl) => parseInt(lvl, 10))
        .sort((a, b) => a - b);

      const sections = levels
        .map((level) => {
          const spells = groups[level];
          const title = level === 0 ? 'Trucos' : `Nivel ${level}`;
          const cards = spells.map((spell) => renderSpellCard(spell)).join('');
          return `
            <section class="character-extended__section spell-level is-collapsed" data-spell-level="${level}">
              <header class="spell-level__header">
                <h4 class="character-extended__section-title">${title}</h4>
                <button type="button" class="feature-card__toggle spell-level__toggle" aria-expanded="false" aria-label="Mostrar conjuros">
                  <span class="feature-card__toggle-icon">▼</span>
                </button>
              </header>
              <div class="spell-level__content">
                ${cards}
              </div>
            </section>
          `;
        })
        .join('');

      panelEl.innerHTML = sections;
      initSpellLevelAccordions(panelEl);
      initFeatureAccordions(panelEl);
    }

    function renderSpellCard(spell) {
      const levelLabel = spell.level === 0 ? 'Truco' : `Nivel ${spell.level}`;
      const school = spell.school ? spell.school : '';
      const time = formatSpellTime(spell.time);
      const metaParts = [levelLabel, school, time].filter(Boolean);

      const infoRows = [
        ['Alcance', formatRange(spell.range)],
        ['Duración', formatDuration(spell.duration)],
        ['Componentes', formatComponents(spell.components)],
      ]
        .map(([label, value]) => (value ? `<p><strong>${label}:</strong> ${value}</p>` : ''))
        .join('');

      const bodyEntries = getLocalizedArrayFrom(spell, 'entries');
      const body = renderEntries(bodyEntries);

      return `
        <article class="feature-card spell-card is-collapsed">
          <header class="feature-card__header">
            <h5 class="feature-card__title">${escapeHtml(spell.name || 'Conjuro')}</h5>
            <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle del conjuro">
              <span class="feature-card__toggle-icon">▼</span>
            </button>
          </header>
          <div class="feature-card__content">
            ${metaParts.length ? `<div class="feature-card__meta">${metaParts.join(' · ')}</div>` : ''}
            <div class="feature-card__body">
              ${infoRows}
              ${body || '<p>Sin descripción.</p>'}
            </div>
          </div>
        </article>
      `;
    }

    function ensureBackgroundList(force = false) {
      if (backgroundList && !force) {
        return Promise.resolve(backgroundList);
      }
      if (backgroundPromise) {
        return backgroundPromise;
      }
      backgroundPromise = fetchBackgroundData()
        .then((list) => {
          backgroundList = list;
          return backgroundList;
        })
        .finally(() => {
          backgroundPromise = null;
        });
      return backgroundPromise;
    }

    function getCurrentBackground() {
      const bgId = readValue('background');
      if (!bgId || !backgroundList) return null;
      return backgroundList.find((bg) => bg.id === bgId) || null;
    }

    function fetchBackgroundView(force = false) {
      ensureBackgroundList(force)
        .then(() => {
          renderBackgroundView();
        })
        .catch(() => {
          showError('No se pudieron cargar los trasfondos.');
        });
    }

    function renderBackgroundView() {
      const bg = getCurrentBackground();
      if (!bg) {
        showEmpty('Selecciona un trasfondo en la ventana de datos básicos.');
        return;
      }

      const profBlocks = renderBackgroundProficiencies(bg);
      const body = renderEntries(bg.entries || []);

      panelEl.innerHTML = `
        <section class="character-extended__section">
          <h4 class="character-extended__section-title">Trasfondo · ${escapeHtml(bg.name || '')}</h4>
          ${bg.source ? `<div class="feature-card__meta">${escapeHtml(bg.source)}</div>` : ''}
          ${profBlocks}
          <div class="feature-card__body">${body || '<p>Sin descripción.</p>'}</div>
        </section>
      `;
    }

    function renderBackgroundProficiencies(bg) {
      const sections = [];
      if (bg.skillProficiencies) {
        sections.push(renderBackgroundSummary('Competencias en habilidades', bg.skillProficiencies));
      }
      if (bg.toolProficiencies) {
        sections.push(renderBackgroundSummary('Herramientas', bg.toolProficiencies));
      }
      if (bg.languageProficiencies) {
        sections.push(renderBackgroundSummary('Idiomas', bg.languageProficiencies));
      }
      if (bg.equipment) {
        sections.push(renderBackgroundSummary('Equipo', bg.equipment));
      }
      return sections.join('');
    }

    function renderBackgroundSummary(title, value) {
      const text = formatBackgroundValue(value);
      if (!text) return '';
      return `
        <div class="feature-card">
          <h5 class="feature-card__title">${title}</h5>
          <div class="feature-card__body">${text}</div>
        </div>
      `;
    }

    tabs.forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.extTab;
        if (!target || target === activeTab) return;
        handleTabChange(target);
      });
    });

    handleTabChange(activeTab);

    featureModuleApi.invalidate = () => {
      featureCache = null;
      featureCacheKey = '';
      if (activeTab === 'features') {
        fetchFeatures(true);
      }
    };

    backgroundModuleApi.invalidate = () => {
      if (activeTab === 'background') {
        fetchBackgroundView(true);
      }
    };

    spellsModuleApi.invalidate = () => {
      spellsCache = {};
      if (activeTab === 'spells') {
        fetchSpells(true);
      }
    };
  }

  function initStandaloneClassSelects() {
    if (typeof window.DND5_API === 'undefined') return;
    const classSelect = document.getElementById('clase');
    const subclassSelect = document.getElementById('subclase');

    if (!classSelect || !subclassSelect) return;

    const currentClass = classSelect.dataset.current || '';
    const currentSubclass = subclassSelect.dataset.current || '';

    function loadSubclasses(classId, preselect) {
      if (!classId) {
        populateSelect(subclassSelect, SELECT_PLACEHOLDERS.subclass, []);
        subclassSelect.disabled = true;
        return;
      }

      subclassSelect.disabled = true;
      subclassSelect.innerHTML = '<option value="">Cargando subclases…</option>';

      ajaxRequest('drak_dnd5_get_subclasses', { class_index: classId })
        .then((res) => {
          if (!res?.success) {
            populateSelect(subclassSelect, 'No hay subclases disponibles', []);
            subclassSelect.disabled = true;
            return;
          }
          const subclasses = res.data?.subclasses || [];
          populateSelect(
            subclassSelect,
            subclasses.length ? SELECT_PLACEHOLDERS.subclass : 'No hay subclases disponibles',
            subclasses,
            preselect
          );
          subclassSelect.disabled = subclasses.length === 0;
        })
        .catch(() => {
          populateSelect(subclassSelect, 'Error al cargar subclases', []);
          subclassSelect.disabled = true;
        });
    }

    ajaxRequest('drak_dnd5_get_classes')
      .then((res) => {
        if (!res?.success) return;
        const classes = res.data?.classes || [];
        populateSelect(
          classSelect,
          classes.length ? SELECT_PLACEHOLDERS.class : 'No hay clases disponibles',
          classes,
          currentClass
        );
        if (currentClass) {
          loadSubclasses(currentClass, currentSubclass);
        }
      })
      .catch(() => {
        populateSelect(classSelect, 'Error al cargar clases', []);
      });

    classSelect.addEventListener('change', function onClassChange() {
      const selected = this.value || '';
      this.dataset.current = selected;
      loadSubclasses(selected, '');
      featureModuleApi.invalidate();
    });

    subclassSelect.addEventListener('change', () => {
      featureModuleApi.invalidate();
    });
  }

  function getLocalizedArrayFrom(obj, key) {
    if (!obj || typeof obj !== 'object') return Array.isArray(obj) ? obj : [];
    const esKey = `${key}_es`;
    const esVal = obj[esKey];
    if (Array.isArray(esVal) && esVal.length) return esVal;
    const baseVal = obj[key];
    return Array.isArray(baseVal) ? baseVal : [];
  }

  function getLocalizedTextFrom(obj, key) {
    if (!obj || typeof obj !== 'object') return '';
    const esKey = `${key}_es`;
    if (typeof obj[esKey] === 'string' && obj[esKey].trim()) {
      return obj[esKey];
    }
    if (typeof obj[key] === 'string' && obj[key].trim()) {
      return obj[key];
    }
    return '';
  }

  function localizeEntryNode(entry) {
    if (!entry || typeof entry !== 'object') {
      return entry;
    }

    let shouldClone = false;
    const replacements = {};

    if (Array.isArray(entry.entries_es) && entry.entries_es.length) {
      replacements.entries = entry.entries_es;
      shouldClone = true;
    }

    if (Array.isArray(entry.items_es) && entry.items_es.length) {
      replacements.items = entry.items_es;
      shouldClone = true;
    }

    if (Array.isArray(entry.rows_es) && entry.rows_es.length) {
      replacements.rows = entry.rows_es;
      shouldClone = true;
    }

    if (Array.isArray(entry.colLabels_es) && entry.colLabels_es.length) {
      replacements.colLabels = entry.colLabels_es;
      shouldClone = true;
    }

    if (typeof entry.entry_es === 'string' && entry.entry_es.trim()) {
      replacements.entry = entry.entry_es;
      shouldClone = true;
    }

    if (typeof entry.caption_es === 'string' && entry.caption_es.trim()) {
      replacements.caption = entry.caption_es;
      shouldClone = true;
    }

    return shouldClone ? { ...entry, ...replacements } : entry;
  }

  function renderEntries(entries) {
    if (!Array.isArray(entries) || !entries.length) {
      return '';
    }
    return entries.map((entry) => renderEntryNode(entry)).join('');
  }

  function renderEntryNode(entry) {
    if (entry == null) {
      return '';
    }
    if (typeof entry === 'string') {
      return `<p>${format5eText(entry)}</p>`;
    }
    if (typeof entry !== 'object') {
      return '';
    }

    entry = localizeEntryNode(entry);
    const type = entry.type || 'entries';

    if (type === 'entries') {
      const title = entry.name ? `<h4>${escapeHtml(entry.name)}</h4>` : '';
      const body = renderEntries(entry.entries || []);
      return `<div class="dnd5-entry-block">${title}${body}</div>`;
    }

    if (type === 'list') {
      const items = entry.items || entry.entries || [];
      const html = items
        .map((item) => {
          if (typeof item === 'string') {
            return `<li>${format5eText(item)}</li>`;
          }
          if (item.entry) {
            const extra = item.entries ? renderEntries(item.entries) : '';
            return `<li>${format5eText(item.entry)}${extra}</li>`;
          }
          if (item.name) {
            const nested = item.entries ? renderEntries(item.entries) : '';
            return `<li><strong>${escapeHtml(item.name)}:</strong> ${nested || ''}</li>`;
          }
          return `<li>${renderEntryNode(item)}</li>`;
        })
        .join('');
      return `<ul>${html}</ul>`;
    }

    if (type === 'options') {
      const options = entry.entries || entry.options || [];
      return options.map((opt) => renderEntryNode(opt)).join('');
    }

    if (type === 'table') {
      const caption = entry.caption || entry.name || '';
      const colLabels = entry.colLabels || entry.colLabel || [];
      const rows = entry.rows || [];
      const header = colLabels.length
        ? `<thead><tr>${colLabels.map((label) => `<th>${format5eText(label)}</th>`).join('')}</tr></thead>`
        : '';
      const body = rows
        .map((row) => `<tr>${row.map((cell) => `<td>${format5eText(cell)}</td>`).join('')}</tr>`)
        .join('');
      return `
        <div class="dnd5-entry-block">
          ${caption ? `<h4>${format5eText(caption)}</h4>` : ''}
          <table>
            ${header}
            <tbody>${body}</tbody>
          </table>
        </div>
      `;
    }

    if (type === 'refOptionalfeature') {
      return `<p>${format5eText(entry.optionalfeature || entry.name || '')}</p>`;
    }

    if (entry.entry) {
      return `<p>${format5eText(entry.entry)}</p>`;
    }

    if (entry.entries) {
      return renderEntries(entry.entries);
    }

    return '';
  }

  function fetchStaticJson(url) {
    if (!url) return Promise.resolve(null);
    return fetch(url, { credentials: 'same-origin' })
      .then((resp) => (resp.ok ? resp.json() : null))
      .catch(() => null);
  }

  function loadCharacterData() {
    if (characterDataStore.data) {
      return Promise.resolve(characterDataStore.data);
    }
    if (!STATIC_DATA || !STATIC_DATA.races) {
      return Promise.reject(new Error('Datos estáticos no disponibles'));
    }
    if (!characterDataStore.promise) {
      const requests = [
        fetchStaticJson(STATIC_DATA.races),
        fetchStaticJson(STATIC_DATA.backgrounds),
        fetchStaticJson(STATIC_DATA.classDetails || STATIC_DATA.classList),
        fetchStaticJson(STATIC_DATA.classList),
        STATIC_DATA.feats ? fetchStaticJson(STATIC_DATA.feats) : Promise.resolve(null),
        STATIC_DATA.esotericTheories ? fetchStaticJson(STATIC_DATA.esotericTheories) : Promise.resolve(null),
      ];
      characterDataStore.promise = Promise.all(requests)
        .then(([races, backgrounds, classDetails, classList, feats, theories]) => {
          const raceMap = {};
          (races?.races || []).forEach((race) => {
            if (race?.id) raceMap[race.id] = race;
          });

          const backgroundMap = {};
          (backgrounds?.backgrounds || []).forEach((bg) => {
            if (bg?.id) backgroundMap[bg.id] = bg;
          });

          const classDetailMap = classDetails?.classes || {};
          const classListMap = {};
          (classList?.classes || []).forEach((cls) => {
            if (cls?.id) classListMap[cls.id] = cls;
          });

          const featMap = {};
          (feats?.feats || []).forEach((feat) => {
            if (!feat) return;
            const key = feat.id || feat.name;
            if (key) featMap[key] = feat;
          });

          const fallbackTheories = getStaticEsotericTheoryList();
          const rawTheories =
            Array.isArray(theories?.classFeature) && theories.classFeature.length
              ? theories.classFeature
              : fallbackTheories;
          const theoryMap = buildEsotericTheoryMap(rawTheories);

          characterDataStore.data = {
            races: raceMap,
            backgrounds: backgroundMap,
            classes: classListMap,
            classDetails: classDetailMap,
            feats: featMap,
            esotericTheories: theoryMap,
          };
          apothecaryTheoryCache = theoryMap;
          refreshApothecaryTheoryDisplay();
          return characterDataStore.data;
        })
        .finally(() => {
          characterDataStore.promise = null;
        });
    }
    return characterDataStore.promise;
  }

  function initCharacterAutomation() {
    if (!STATIC_DATA || !STATIC_DATA.races) {
      console.warn('[Hoja] No se han localizado los datos estáticos (DND5_STATIC_DATA). Automatización desactivada.');
      return;
    }

    if (!document.getElementById('clase')) {
      return;
    }

    characterAutomationState.expertise = getExpertiseSetFromHidden();

    loadCharacterData()
      .then(() => {
        console.debug('[Hoja] Datos estáticos cargados para automatizar hoja.');
        const watcherIds = new Set([
          'nivel',
          'clase',
          'subclase',
          'raza',
          'background',
          ...abilityIds,
        ]);

        watcherIds.forEach((id) => {
          const el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('change', scheduleCharacterRecalc);
          el.addEventListener('input', scheduleCharacterRecalc);
        });

        scheduleCharacterRecalc();
      })
      .catch(() => {});
  }

  function scheduleCharacterRecalc() {
    if (!characterDataStore.data) return;
    clearTimeout(characterRecalcTimer);
    characterRecalcTimer = setTimeout(() => {
      console.debug('[Hoja] Recalcular hoja (trigger).');
      recalculateCharacterSheet();
    }, 80);
  }

  function recalculateCharacterSheet() {
    if (!characterDataStore.data) return;
    const context = collectCharacterContext();
    const contextKey = [context.classId, context.subclassId, context.raceId, context.backgroundId].join('|');
    if (contextKey !== characterAutomationState.lastContextKey) {
      characterAutomationState.manualSaves.clear();
      characterAutomationState.lastContextKey = contextKey;
    }
    const derived = buildDerivedCharacter(context, characterDataStore.data);
    console.debug('[Hoja] Contexto calculado', context, derived);
    applyDerivedCharacter(derived);
    refreshClassReferenceModule(context);
  }

  function collectCharacterContext() {
    const abilityScores = {
      str: getNumberFromInput('cs_fuerza'),
      dex: getNumberFromInput('cs_destreza'),
      con: getNumberFromInput('cs_constitucion'),
      int: getNumberFromInput('cs_inteligencia'),
      wis: getNumberFromInput('cs_sabiduria'),
      cha: getNumberFromInput('cs_carisma'),
    };

    const abilityMods = {
      str: Math.floor((abilityScores.str - 10) / 2),
      dex: Math.floor((abilityScores.dex - 10) / 2),
      con: Math.floor((abilityScores.con - 10) / 2),
      int: Math.floor((abilityScores.int - 10) / 2),
      wis: Math.floor((abilityScores.wis - 10) / 2),
      cha: Math.floor((abilityScores.cha - 10) / 2),
    };

    return {
      level: Math.max(1, getNumberFromInput('nivel')),
      abilities: abilityScores,
      abilityMods,
      classId: (document.getElementById('clase')?.value || '').trim(),
      subclassId: (document.getElementById('subclase')?.value || '').trim(),
      raceId: (document.getElementById('raza')?.value || '').trim(),
      backgroundId: (document.getElementById('background')?.value || '').trim(),
      esotericTheories: getSelectedEsotericTheories(),
    };
  }

  function buildDerivedCharacter(context, data) {
    const derived = {
      level: context.level,
      abilityMods: context.abilityMods,
      proficiencyBonus: Math.max(2, 2 + Math.floor((context.level - 1) / 4)),
      skills: new Map(),
      saves: new Map(),
      armorText: [],
      weaponText: [],
      toolText: [],
      languageText: [],
      hitDie: null,
      speed: null,
      speedNotes: [],
      hp: null,
      initiative: context.abilityMods.dex,
      ac: null,
    };

    const classDef = data.classDetails?.[context.classId];
    const raceDef = data.races?.[context.raceId];
    const backgroundDef = data.backgrounds?.[context.backgroundId];

    if (classDef) {
      derived.hitDie = classDef.hitDie || derived.hitDie;
      (classDef.savingThrows || []).forEach((abbr) => {
        const saveField = SAVE_FIELDS[abbr];
        if (saveField) derived.saves.set(saveField, { source: 'Clase' });
      });
      derived.spellcastingAbility = classDef.spellcastingAbility || derived.spellcastingAbility;
      mergeProficiencyText(derived.weaponText, classDef.startingProficiencies?.weapons, 'Clase');
      mergeProficiencyText(derived.armorText, classDef.startingProficiencies?.armor, 'Clase');
      mergeProficiencyText(derived.toolText, classDef.startingProficiencies?.tools, 'Clase');
      mergeLanguageText(derived.languageText, classDef.startingProficiencies?.languages, 'Clase');
    assignSkillGroups(derived, classDef.startingProficiencies?.skills, 'Clase');
    }

    if (raceDef) {
      mergeLanguageText(derived.languageText, raceDef.languageProficiencies, 'Raza');
      mergeProficiencyText(derived.toolText, raceDef.toolProficiencies, 'Raza');
      assignSkillGroups(derived, raceDef.skillProficiencies, 'Raza');

      if (raceDef.speed) {
        if (typeof raceDef.speed === 'number') {
          derived.speed = raceDef.speed;
        } else if (typeof raceDef.speed === 'object') {
          if (typeof raceDef.speed.walk === 'number') {
            derived.speed = raceDef.speed.walk;
          }
          Object.entries(raceDef.speed).forEach(([mode, value]) => {
            if (mode === 'walk' || typeof value !== 'number') return;
            derived.speedNotes.push(`${capitalize(mode)} ${value} ft`);
          });
        }
      }
    }

    if (backgroundDef) {
      assignSkillGroups(derived, backgroundDef.skillProficiencies, 'Trasfondo');
      mergeLanguageText(derived.languageText, backgroundDef.languageProficiencies, 'Trasfondo');
      mergeProficiencyText(derived.toolText, backgroundDef.toolProficiencies, 'Trasfondo');
    }

    if (!derived.hitDie && classDef) {
      derived.hitDie = classDef.hitDie || 8;
    }

    const hitDie = derived.hitDie || 8;
    const conMod = context.abilityMods.con;
    const firstLevelHp = Math.max(1, hitDie + conMod);
    const perLevelHp = Math.max(1, Math.ceil(hitDie / 2) + conMod);
    derived.hp = firstLevelHp + Math.max(0, context.level - 1) * perLevelHp;

    derived.ac = 10 + context.abilityMods.dex;

    if (raceDef && typeof derived.speed !== 'number') {
      derived.speed = 30;
    } else if (!derived.speed) {
      derived.speed = 30;
    }

    return derived;
  }

  function applyDerivedCharacter(derived) {
    if (!derived) return;

    updateBasicStat('cs_proeficiencia', derived.proficiencyBonus);
    updateBasicStat('cs_iniciativa', derived.initiative, formatMod);
    updateBasicStat('cs_ac', derived.ac || 10);
    const speedLabel = derived.speedNotes.length ? `${derived.speed} ft (${derived.speedNotes.join(', ')})` : `${derived.speed} ft`;
    updateBasicStat('cs_velocidad', speedLabel);
    const manualHp = hasManualOverride('cs_hp');
    if (!manualHp) {
      updateBasicStat('cs_hp', derived.hp || '');
    } else {
      const manualHidden = document.getElementById('cs_hp');
      const hpDisplay = document.getElementById('display_cs_hp');
      if (hpDisplay) {
        hpDisplay.textContent = manualHidden?.value || '0';
      }
    }

    const slider = document.getElementById('slider_temp_hp');
    if (slider) {
      const sliderMax = manualHp
        ? parseInt(document.getElementById('cs_hp')?.value || '0', 10)
        : derived.hp;
      if (sliderMax) {
        slider.max = String(sliderMax);
      }
    }

    updateSaveDisplays(derived);
    updateSkillDisplays(derived);

    setDisplayText('display_cs_armas', formatJoinedList(derived.weaponText));
    setDisplayText('display_cs_armaduras', formatJoinedList(derived.armorText));
    setDisplayText('display_cs_herramientas', formatJoinedList(derived.toolText));
    setDisplayText('display_cs_idiomas', formatJoinedList(derived.languageText));

    recomputeSkillsAndSaves();
  }

  function updateBasicStat(fieldId, value, formatter) {
    const hidden = document.getElementById(fieldId);
    if (hidden) hidden.value = typeof value === 'number' ? value : (value || '');
    const display = document.getElementById(`display_${fieldId}`);
    if (!display) return;
    const formatted = typeof formatter === 'function' ? formatter(value) : value;
    display.textContent = formatted ?? '';
  }

  function updateSaveDisplays(derived) {
    Object.values(SAVE_FIELDS).forEach((fieldId) => {
      const info = derived.saves.get(fieldId);
      const finalValue = Boolean(info);
      setSaveProficiency(fieldId, finalValue, info?.source);
    });
  }

  function updateSkillDisplays(derived) {
    const allFields = Object.values(SKILL_FIELD_BY_NAME);
    characterAutomationState.autoSkills = new Set();
    if (derived?.skills?.forEach) {
      derived.skills.forEach((info, fieldId) => {
        characterAutomationState.autoSkills.add(fieldId);
      });
    }
    characterAutomationState.expertise = getExpertiseSetFromHidden();

    allFields.forEach((fieldId) => {
      const info = derived?.skills?.get ? derived.skills.get(fieldId) : null;
      const hasProf = Boolean(info);
      const hasExpert = characterAutomationState.expertise.has(fieldId);
      setSkillProficiency(fieldId, hasProf, info?.source, hasExpert);
    });

    renderExpertiseList();
    populateExpertiseSelect();
  }

  function setSaveProficiency(fieldId, isActive, source) {
    const hidden = document.getElementById(`prof_${fieldId}`);
    if (hidden) hidden.value = isActive ? '1' : '0';
    if (source) {
      characterAutomationState.saveSources.set(fieldId, source);
    } else {
      characterAutomationState.saveSources.delete(fieldId);
    }
    const icon = document.querySelector(`.skill-icon[data-save-icon="${fieldId}"]`);
    if (icon) {
      if (isActive) {
        icon.classList.add('skill-icon--prof');
      } else {
        icon.classList.remove('skill-icon--prof', 'skill-icon--expert');
      }
    }
  }

  function setSkillProficiency(fieldId, isActive, source, hasExpertise) {
    const profInput = document.getElementById(`prof_${fieldId}`);
    if (profInput) {
      profInput.value = isActive ? '1' : '0';
    }

    const icon = document.querySelector(`.skill-icon[data-skill-icon="${fieldId}"]`);
    if (icon) {
      icon.classList.remove('skill-icon--prof', 'skill-icon--expert');
      if (hasExpertise) {
        icon.classList.add('skill-icon--expert');
      } else if (isActive) {
        icon.classList.add('skill-icon--prof');
      }
    }

    const label = document.querySelector(`.skill-source-label[data-skill-label="${fieldId}"]`);
    if (label) {
      if (isActive && source) {
        label.textContent = mapSourceLabel(source);
      } else {
        label.textContent = '';
      }
    }
  }

  function addSkillSource(derived, fieldId, source) {
    if (!fieldId) return;
    if (!derived.skills.has(fieldId)) {
      derived.skills.set(fieldId, { sources: new Set() });
    }
    const entry = derived.skills.get(fieldId);
    entry.sources.add(source);
    entry.source = source;
    console.debug('[Hoja] Asignando skill', fieldId, 'desde', source);
  }

  function assignSkillGroups(derived, groups, source) {
    if (!groups) return;
    const normalized = Array.isArray(groups) ? groups : [groups];
    normalized.forEach((originalGroup) => {
      let group = originalGroup;
      if (!group) return;
      if (typeof group === 'string') {
        const directField = mapSkillNameToField(group);
        if (directField) addSkillSource(derived, directField, source);
        return;
      }
      if (group.choose && !group.type) {
        const choose = group.choose;
        const options = Array.isArray(choose.from) ? choose.from : [];
        const count = typeof choose.count !== 'undefined' ? choose.count : choose;
        group = {
          type: 'choice',
          count,
          options,
        };
      }
      if (group.type === 'choice' && Array.isArray(group.options)) {
        const options = group.options.map((opt) => mapSkillNameToField(opt)).filter(Boolean);
        if (!options.length) return;
        const count = group.count && Number.isFinite(group.count) ? group.count : options.length;
        const assigned = selectLimitedOptions(options, count);
        assigned.forEach((fieldId) => addSkillSource(derived, fieldId, source));
        return;
      }

      const entries = [];
      if (group.items) entries.push(...group.items);
      else if (Array.isArray(group)) entries.push(...group);
      else if (typeof group === 'object') entries.push(...Object.keys(group).filter((key) => group[key]));
      entries.map((item) => mapSkillNameToField(item)).filter(Boolean).forEach((fieldId) => {
        addSkillSource(derived, fieldId, source);
      });
    });
  }

  function selectLimitedOptions(options, count) {
    const selected = new Set();
    options.some((fieldId) => {
      if (selected.size >= count) return true;
      selected.add(fieldId);
      return selected.size >= count;
    });
    return selected;
  }

  function mergeProficiencyText(target, entries, prefix) {
    if (!entries) return;
    const list = Array.isArray(entries) ? entries : [entries];
    list.forEach((entry) => {
      if (!entry) return;
      if (typeof entry === 'string') {
        target.push(`${prefix ? `${prefix}: ` : ''}${strip5eTags(entry)}`);
      } else if (entry.type === 'fixed' && Array.isArray(entry.items)) {
        target.push(`${prefix ? `${prefix}: ` : ''}${entry.items.map(strip5eTags).join(', ')}`);
      } else if (entry.type === 'choice' && Array.isArray(entry.options)) {
        target.push(
          `${prefix ? `${prefix}: ` : ''}Elige ${entry.count || 1}: ${entry.options.map(strip5eTags).join(', ')}`
        );
      } else if (typeof entry === 'object') {
        const keys = Object.keys(entry)
          .filter((key) => entry[key] && key !== 'type')
          .map(strip5eTags);
        if (keys.length) {
          target.push(`${prefix ? `${prefix}: ` : ''}${keys.join(', ')}`);
        }
      } else if (Array.isArray(entry)) {
        target.push(`${prefix ? `${prefix}: ` : ''}${entry.map(strip5eTags).join(', ')}`);
      }
    });
  }

  function mergeLanguageText(target, entries, prefix) {
    if (!entries) return;
    const list = Array.isArray(entries) ? entries : [entries];
    list.forEach((entry) => {
      if (!entry) return;
      if (typeof entry === 'string') {
        target.push(`${prefix ? `${prefix}: ` : ''}${strip5eTags(entry)}`);
      } else if (entry.choose) {
        const from = (entry.choose.from || []).map(strip5eTags).join(', ');
        target.push(`${prefix ? `${prefix}: ` : ''}Elige ${entry.choose.count || 1}${from ? ` de ${from}` : ''}`);
      } else {
        Object.keys(entry).forEach((key) => {
          if (entry[key]) {
            target.push(`${prefix ? `${prefix}: ` : ''}${strip5eTags(key)}`);
          }
        });
      }
    });
  }

  function mapSkillNameToField(value) {
    if (!value) return null;
    const text = strip5eTags(value).toLowerCase().trim();
    return SKILL_FIELD_BY_NAME[text] || null;
  }

  function strip5eTags(text) {
    if (typeof text !== 'string') return '';
    return text
      .replace(/\{@([^}|]+)\|([^}|]+)(?:\|[^}]*)?\}/gi, '$2')
      .replace(/[{}]/g, '')
      .trim();
  }

  function formatJoinedList(items) {
    if (!items || !items.length) return '—';
    return items.join('; ');
  }

  function setDisplayText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '—';
  }

  function capitalize(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function mapSourceLabel(source) {
    const value = (source || '').toLowerCase();
    if (value.includes('clase')) return 'C';
    if (value.includes('raza')) return 'R';
    if (value.includes('trasf') || value.includes('fondo')) return 'T';
    if (value.includes('feat')) return 'D';
    return 'O';
  }

  function initExpertiseManager() {
    expertiseUI.select = document.getElementById('expertise-select');
    expertiseUI.addBtn = document.getElementById('expertise-add');
    expertiseUI.list = document.getElementById('expertise-list');

    if (!expertiseUI.select || !expertiseUI.addBtn || !expertiseUI.list) {
      expertiseUI.select = expertiseUI.addBtn = expertiseUI.list = null;
      return;
    }

    characterAutomationState.expertise = getExpertiseSetFromHidden();
    renderExpertiseList();
    populateExpertiseSelect();

    expertiseUI.addBtn.addEventListener('click', () => {
      const value = expertiseUI.select.value;
      if (!value) return;
      if (characterAutomationState.expertise.has(value)) return;
      characterAutomationState.expertise.add(value);
      saveExpertiseSet(characterAutomationState.expertise);
      renderExpertiseList();
      populateExpertiseSelect();
      scheduleCharacterRecalc();
    });
  }

  function renderExpertiseList() {
    if (!expertiseUI.list) return;
    expertiseUI.list.innerHTML = '';
    const set = characterAutomationState.expertise || new Set();
    if (!set.size) {
      const li = document.createElement('li');
      li.textContent = 'Sin pericias añadidas.';
      li.className = 'expertise-empty';
      expertiseUI.list.appendChild(li);
      return;
    }
    set.forEach((fieldId) => {
      const li = document.createElement('li');
      const label = SKILL_LABELS[fieldId] || fieldId;
      li.textContent = label;
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn-basicos-mod profs-remove';
      removeBtn.textContent = '×';
      removeBtn.setAttribute('aria-label', `Quitar pericia ${label}`);
      removeBtn.addEventListener('click', () => {
        characterAutomationState.expertise.delete(fieldId);
        saveExpertiseSet(characterAutomationState.expertise);
        renderExpertiseList();
        populateExpertiseSelect();
        scheduleCharacterRecalc();
      });
      li.appendChild(removeBtn);
      expertiseUI.list.appendChild(li);
    });
  }

  function populateExpertiseSelect() {
    if (!expertiseUI.select) return;
    const set = characterAutomationState.expertise || new Set();
    const autoSet = characterAutomationState.autoSkills || new Set();
    const available = Object.keys(SKILL_LABELS).filter((fieldId) => {
      if (set.has(fieldId)) return false;
      if (!autoSet.size) return true;
      return autoSet.has(fieldId);
    });

    expertiseUI.select.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = available.length ? 'Selecciona habilidad…' : 'Sin habilidades disponibles';
    expertiseUI.select.appendChild(placeholder);

    available.forEach((fieldId) => {
      const option = document.createElement('option');
      option.value = fieldId;
      option.textContent = SKILL_LABELS[fieldId] || fieldId;
      expertiseUI.select.appendChild(option);
    });

    const disabled = available.length === 0;
    expertiseUI.select.disabled = disabled;
    expertiseUI.addBtn.disabled = disabled;
  }

  const expertiseUI = {
    select: null,
    addBtn: null,
    list: null,
  };

  function format5eText(text) {
    if (!text) return '';
    let safe = escapeHtml(String(text));
    safe = safe.replace(/\{@([^}]+)\}/g, (_, inner) => render5eTag(inner));
    return safe.replace(/\n+/g, '<br>');
  }

  function render5eTag(innerRaw) {
    if (!innerRaw) return '';
    const spaceIndex = innerRaw.indexOf(' ');
    if (spaceIndex === -1) return innerRaw;

    const tag = innerRaw.slice(0, spaceIndex).toLowerCase();
    const body = innerRaw.slice(spaceIndex + 1);
    const label = body.split('|')[0] || body;

    const textualTags = new Set(['italic', 'i', 'bold', 'b']);
    const strongTags = new Set(['dc', 'dice', 'damage', 'hit', 'skillcheck']);
    const chipTags = new Set([
      'spell',
      'action',
      'skill',
      'condition',
      'item',
      'creature',
      'classfeature',
      'subclassfeature',
      'feat',
    ]);

    if (textualTags.has(tag)) {
      return tag === 'bold' || tag === 'b'
        ? `<strong>${label}</strong>`
        : `<em>${label}</em>`;
    }

    if (strongTags.has(tag)) {
      return `<strong>${label}</strong>`;
    }

    if (chipTags.has(tag)) {
      const modifier = ['spell', 'action', 'skill'].includes(tag) ? ` dnd5-tag-${tag}` : '';
      return `<span class="dnd5-tag${modifier}">${label}</span>`;
    }

    return label;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;');
  }

  function formatBackgroundValue(value) {
    if (value == null) return '';
    if (typeof value === 'string') {
      return format5eText(value);
    }
    if (Array.isArray(value)) {
      const parts = value.map((entry) => formatBackgroundValue(entry)).filter(Boolean);
      return parts.join('<br>');
    }
    if (typeof value === 'object') {
      if (value.entry) {
        return format5eText(value.entry);
      }
      if (value.entries) {
        return formatBackgroundValue(value.entries);
      }
      if (value.choose) {
        const choose = value.choose;
        let count = '';
        let fromList = [];

        if (typeof choose === 'object' && choose !== null) {
          if (typeof choose.count !== 'undefined') {
            count = choose.count;
          }
          if (Array.isArray(choose.from)) {
            fromList = choose.from;
          }
        } else {
          count = choose;
        }

        const from = fromList.length ? fromList.join(', ') : '';
        return `Elige ${count}${from ? ` de ${from}` : ''}`;
      }
      const pieces = [];
      Object.keys(value).forEach((key) => {
        const val = value[key];
        if (val == null || val === false) return;
        if (typeof val === 'boolean') {
          if (val) pieces.push(formatKeyLabel(key));
          return;
        }
        if (key === 'anyStandard') {
          pieces.push(`Elige ${val} idiomas estándar`);
          return;
        }
        if (key === 'any') {
          pieces.push(`Elige ${val} opciones`);
          return;
        }
        const formatted = formatBackgroundValue(val);
        if (formatted) {
          pieces.push(formatted);
        }
      });
      return pieces.join('<br>');
    }
    return escapeHtml(String(value));
  }

  function formatKeyLabel(key) {
    return key
      .split('_')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }

  function formatActionTime(timeArray) {
    if (!Array.isArray(timeArray) || !timeArray.length) {
      return '';
    }
    return timeArray
      .map((time) => {
        const number = typeof time.number !== 'undefined' ? time.number : 1;
        const unit = time.unit || 'action';
        return `${number} ${unit}`;
      })
      .join(' / ');
  }

  function formatSpellTime(timeArray) {
    return formatActionTime(timeArray);
  }

  function formatRange(range) {
    if (!range) return '';
    if (range.type === 'self') {
      if (range.distance && range.distance.amount) {
        return `Personal (${range.distance.amount} ${range.distance.type})`;
      }
      return 'Personal';
    }
    if (range.type === 'point' && range.distance) {
      const dist = range.distance;
      if (dist.amount) {
        return `${dist.amount} ${dist.type || ''}`.trim();
      }
      return dist.type || 'Punto';
    }
    if (range.type === 'touch') {
      return 'Toque';
    }
    if (range.type === 'line' && range.distance) {
      return `Línea de ${range.distance.amount} ${range.distance.type}`;
    }
    return range.type || '';
  }

  function formatDuration(durationArray) {
    if (!Array.isArray(durationArray) || !durationArray.length) {
      return '';
    }
    return durationArray
      .map((entry) => {
        if (entry.type === 'instant') return 'Instantánea';
        if (entry.type === 'permanent') return 'Permanente';
        if (entry.type === 'timed') {
          const parts = [];
          if (entry.concentration) parts.push('Concentración');
          const duration = entry.duration || {};
          if (duration.amount) {
            parts.push(`${duration.amount} ${duration.type || ''}`.trim());
          }
          return parts.join(', ') || 'Tiempo limitado';
        }
        return entry.type || '';
      })
      .filter(Boolean)
      .join(', ');
  }

  function formatComponents(components) {
    if (!components) return '';
    const parts = [];
    if (components.v) parts.push('V');
    if (components.s) parts.push('S');
    if (components.m) {
      if (typeof components.m === 'object' && components.m.text) {
        parts.push(`M (${components.m.text})`);
      } else if (typeof components.m === 'object' && components.m.cost) {
        parts.push(`M (${components.m.cost} gp)`);
      } else {
        parts.push('M');
      }
    }
    return parts.join(', ');
  }
})();
