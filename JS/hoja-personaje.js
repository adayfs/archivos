(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.formulario-hoja-personaje')) {
      return;
    }
    initBasicTextChips();
    initManualOverrideState();
    hydrateManualSkills();
    initTempHpControls();
    initCombatModule();
    initSheetModal();
    refreshAbilityDisplays();
    initEditableBasics();
    initSkillSaveSystem();
    applyManualSkillSelections();
    recomputeSkillsAndSaves();
    initExtendedModule();
    initStandaloneClassSelects();
    initExpertiseManager();
    initClassReferenceModule();
    initCharacterAutomation();
    updateTheorySummaryVisibility();
    initLanguageChoiceModal();
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
  const ABILITY_LABELS = Object.freeze({
    str: 'FUE',
    dex: 'DES',
    con: 'CON',
    int: 'INT',
    wis: 'SAB',
    cha: 'CAR',
  });
  const LANGUAGE_FALLBACK = Object.freeze([
    { id: 'common', name: 'Común' },
    { id: 'dwarvish', name: 'Enano' },
    { id: 'elvish', name: 'Élfico' },
    { id: 'giant', name: 'Gigante' },
    { id: 'gnomish', name: 'Gnomo' },
    { id: 'goblin', name: 'Goblin' },
    { id: 'halfling', name: 'Mediano' },
    { id: 'orc', name: 'Orco' },
    { id: 'abyssal', name: 'Abisal' },
    { id: 'celestial', name: 'Celestial' },
    { id: 'draconic', name: 'Dracónico' },
    { id: 'deep-speech', name: 'Habla Profunda' },
    { id: 'infernal', name: 'Infernal' },
    { id: 'primordial', name: 'Primordial' },
    { id: 'sylvan', name: 'Silvano' },
    { id: 'undercommon', name: 'Inframundo' },
  ]);

  const manualOverrideConfig = Object.freeze({
    cs_hp: 'cs_hp_manual_override',
    cs_iniciativa: 'cs_iniciativa_manual_override',
    cs_ac: 'cs_ac_manual_override',
    cs_velocidad: 'cs_velocidad_manual_override',
  });

  const manualBasicOverrides = new Set();
  const manualSkillSelections = new Set();

  const STATIC_DATA = window.DND5_STATIC_DATA || null;
  const COMBAT_CONFIG = window.COMBAT_CONFIG || {};
  const BASIC_SAVE_CONFIG = window.BASIC_AUTOSAVE || null;
  const PRELOADED_THEORY_CATALOG = Array.isArray(window.APOTHECARY_THEORY_CATALOG)
    ? window.APOTHECARY_THEORY_CATALOG
    : null;
  const APOTHECARY_CLASS_IDS = new Set(['apothecary-scgtd-drakkenheim']);
  const APOTHECARY_MUTAGENIST_ID = 'apothecary-mutagenist-scgtd-drakkenheim';
  let lastDerivedTools = [];

  function initBasicTextChips() {
    const nodes = document.querySelectorAll('.profs-list .basic-text');
    nodes.forEach((node) => {
      const raw = node.textContent || '';
      const isLanguages = node.id === 'display_cs_idiomas';
      const chips = Array.from(node.querySelectorAll('.prof-chip')).map((chip) => chip.textContent.trim());
      const parts = raw
        .split(/[,;]+/)
        .map((p) => p.trim())
        .filter(Boolean)
        .concat(chips);
      if (!parts.length) return;

      const frag = document.createDocumentFragment();
      parts.forEach((item, idx) => {
        const span = document.createElement('span');
        span.className = 'prof-chip prof-chip--plain';
        let label = item;

        const sourceMatch = item.match(/^(Raza|Trasfondo)\s*:\s*(.+)$/i);
        if (sourceMatch) {
          const kind = sourceMatch[1].toLowerCase();
          label = sourceMatch[2];
          span.classList.add(kind === 'raza' ? 'prof-chip--source-race' : 'prof-chip--source-bg');
        }

        if (isLanguages) {
          span.dataset.langIndex = idx;
          span.dataset.langId = label;
          if (/any/i.test(label)) {
            span.dataset.langAny = '1';
            span.classList.add('prof-chip--choose');
            span.classList.remove('prof-chip--plain');
          }
        }

        span.textContent = label;
        frag.appendChild(span);
      });

      node.textContent = '';
      node.appendChild(frag);
    });
  }

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
  const combatModuleState = {
    weaponMain: COMBAT_CONFIG.weapon_main || null,
    weaponOff: COMBAT_CONFIG.weapon_offhand || null,
    attackExtraMain: Number(COMBAT_CONFIG.attack_extra_main || 0) || 0,
    damageExtraMain: Number(COMBAT_CONFIG.damage_extra_main || 0) || 0,
    attackExtraOff: Number(COMBAT_CONFIG.attack_extra_off || 0) || 0,
    damageExtraOff: Number(COMBAT_CONFIG.damage_extra_off || 0) || 0,
    acExtra: Number(COMBAT_CONFIG.ac_extra || 0) || 0,
    shieldExtra: Number(COMBAT_CONFIG.shield_extra || 0) || 0,
    tempHpExtra: Number(COMBAT_CONFIG.temp_hp_extra || 0) || 0,
    notes: COMBAT_CONFIG.notes || '',
  };
  let lastCombatContext = null;
  let apothecaryTheoryCache = null;
  let apothecaryTheoryPromise = null;

  const WEAPON_MASTERY_MAP = Object.freeze({
    'club': 'slow',
    'dagger': 'nick',
    'greatclub': 'push',
    'handaxe': 'vex',
    'javelin': 'slow',
    'light-hammer': 'nick',
    'mace': 'sap',
    'quarterstaff': 'topple',
    'sickle': 'nick',
    'spear': 'sap',
    'light-crossbow': 'slow',
    'dart': 'vex',
    'shortbow': 'vex',
    'sling': 'slow',
    'battleaxe': 'topple',
    'flail': 'sap',
    'glaive': 'graze',
    'greataxe': 'cleave',
    'greatsword': 'graze',
    'halberd': 'cleave',
    'lance': 'topple',
    'longsword': 'sap',
    'maul': 'topple',
    'morningstar': 'sap',
    'pike': 'push',
    'rapier': 'vex',
    'scimitar': 'nick',
    'shortsword': 'vex',
    'trident': 'topple',
    'war-pick': 'sap',
    'warhammer': 'push',
    'whip': 'slow',
    'blowgun': 'vex',
    'hand-crossbow': 'vex',
    'heavy-crossbow': 'push',
    'longbow': 'slow',
  });

  const WEAPON_MASTERY_TEXT = Object.freeze({
    cleave: 'Tras impactar, permite un ataque extra a otra criatura a 5 ft (sin mod. de stat al daño).',
    graze: 'Si fallas, el objetivo recibe daño igual a tu modificador de stat del ataque.',
    nick: 'El ataque con la otra mano (Light) se integra en la misma acción de Ataque.',
    push: 'Al impactar, puedes empujar hasta 10 ft en línea recta.',
    sap: 'Al impactar, el siguiente ataque del objetivo tiene desventaja.',
    slow: 'Al impactar, reduces su velocidad hasta tu siguiente turno.',
    topple: 'Al impactar, puedes derribarlo (salvación de CON).',
    vex: 'Al impactar, tienes ventaja en tu siguiente ataque contra ese objetivo antes de tu siguiente turno.',
  });

  const WEAPON_MASTERY_CLASSES = new Set(['fighter', 'barbarian', 'paladin', 'ranger', 'rogue']);

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
    return isApothecaryClassId(classId);
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
    let raw = STATIC_DATA?.esotericTheoriesData;
    if (!raw || (Array.isArray(raw) && raw.length === 0)) {
      raw = PRELOADED_THEORY_CATALOG;
    }
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

  apothecaryTheoryCache = updateTheoryCacheFromList(getStaticEsotericTheoryList());

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

  function getManualSkillsHiddenField() {
    return document.getElementById('manual_skill_overrides');
  }

  function hydrateManualSkills() {
    const hidden = getManualSkillsHiddenField();
    if (!hidden || !hidden.value) return;
    hidden.value
      .split(',')
      .map((v) => v.trim())
      .filter(Boolean)
      .forEach((id) => manualSkillSelections.add(id));
  }

  function persistManualSkills() {
    const hidden = getManualSkillsHiddenField();
    if (!hidden) return;
    hidden.value = Array.from(manualSkillSelections).join(',');
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

    const basicDisplays = [
      { id: 'cs_iniciativa', formatter: formatMod },
      { id: 'cs_ac' },
      { id: 'cs_velocidad' },
      { id: 'cs_hp' },
    ];
    basicDisplays.forEach(({ id, formatter }) => {
      const valueInput = document.getElementById(id);
      const displayId = id === 'cs_hp_temp' ? 'display_cs_hp_temp' : `display_${id}`;
      const display = document.getElementById(displayId);
      if (valueInput && display) {
        const raw = valueInput.value || '0';
        const numeric = parseInt(raw, 10);
        const text = formatter ? formatter(Number.isNaN(numeric) ? 0 : numeric) : raw;
        display.textContent = text;
        if (id === 'cs_iniciativa') {
          display.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
          const value = Number.isNaN(numeric) ? 0 : numeric;
          if (value > 0) display.classList.add('mod-pos');
          else if (value < 0) display.classList.add('mod-neg');
          else display.classList.add('mod-zero');
        }
      }
    });

    const dexMod = document.getElementById('cs_destreza_mod');
    const iniInput = document.getElementById('cs_iniciativa');
    const iniDisplay = document.getElementById('display_cs_iniciativa');
    if (!hasManualOverride('cs_iniciativa') && dexMod && iniInput && iniDisplay) {
      const value = parseInt(dexMod.value || '0', 10);
      iniInput.value = value;
      iniDisplay.textContent = formatMod(value);
      iniDisplay.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
      if (value > 0) iniDisplay.classList.add('mod-pos');
      else if (value < 0) iniDisplay.classList.add('mod-neg');
      else iniDisplay.classList.add('mod-zero');
    }
  }

  function initEditableBasics() {
    const editable = [
      { id: 'cs_iniciativa', formatter: formatMod, numeric: true, usesOverride: true },
      { id: 'cs_ac', numeric: true, usesOverride: true },
      { id: 'cs_velocidad', numeric: true, usesOverride: true },
      { id: 'cs_hp', numeric: true, usesOverride: true },
    ];

    function applyEdit(config, displayEl, hiddenEl) {
      if (!displayEl || !hiddenEl) return;
      const raw = (displayEl.textContent || '').trim();
      if (!raw) {
        hiddenEl.value = '';
        if (config.usesOverride && manualOverrideConfig[config.id]) {
          setManualOverride(config.id, false);
        }
        if (config.id === 'cs_hp') {
          setManualOverride('cs_hp', false);
        }
        scheduleCharacterRecalc();
        recomputeSkillsAndSaves();
        return;
      }

      let value = raw;
      if (config.numeric) {
        value = parseInt(raw, 10);
        if (Number.isNaN(value)) {
          displayEl.textContent = hiddenEl.value || '';
          return;
        }
      }

      hiddenEl.value = value;
      const formatted =
        typeof config.formatter === 'function' ? config.formatter(Number.isNaN(value) ? 0 : value) : value;
      displayEl.textContent = formatted;

      if (config.usesOverride && manualOverrideConfig[config.id]) {
        setManualOverride(config.id, true);
      }

      scheduleCharacterRecalc();
      recomputeSkillsAndSaves();
    }

    editable.forEach((config) => {
      const display = document.querySelector(`[data-basic-edit="${config.id}"]`);
      const hidden = document.getElementById(config.id);
      if (!display || !hidden) return;

      display.addEventListener('focus', () => {
        const range = document.createRange();
        range.selectNodeContents(display);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      });

      display.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          display.blur();
        }
      });

      display.addEventListener('blur', () => {
        applyEdit(config, display, hidden);
        scheduleBasicAutosave();
      });
    });
  }

  let basicSaveTimer = null;
  function scheduleBasicAutosave() {
    if (!BASIC_SAVE_CONFIG?.ajax_url || !BASIC_SAVE_CONFIG?.post_id || !BASIC_SAVE_CONFIG?.nonce) return;
    if (!document.querySelector('input[name="hoja_guardar"]')) return;
    clearTimeout(basicSaveTimer);
    basicSaveTimer = setTimeout(() => {
      const payload = new URLSearchParams({
        action: 'drak_save_basic_stats',
        post_id: BASIC_SAVE_CONFIG.post_id,
        nonce: BASIC_SAVE_CONFIG.nonce,
        cs_iniciativa: document.getElementById('cs_iniciativa')?.value || '',
        cs_ac: document.getElementById('cs_ac')?.value || '',
        cs_velocidad: document.getElementById('cs_velocidad')?.value || '',
        cs_hp: document.getElementById('cs_hp')?.value || '',
      });
      fetch(BASIC_SAVE_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: payload,
      }).catch(() => {});
    }, 400);
  }

  function initTempHpControls() {
    const slider = document.getElementById('slider_temp_hp');
    const display = document.getElementById('display_cs_temp_hp');
    const hidden = document.getElementById('cs_hp_temp');
    const bonusInput = document.getElementById('combat_temp_hp_extra');
    const hpBaseInput = document.getElementById('cs_hp');

    if (!slider || !display || !hidden) {
      return;
    }

    if (!window.HP_TEMP_AJAX || !window.HP_TEMP_AJAX.post_id) {
      console.warn('HP_TEMP_AJAX no está definido correctamente.');
    }

    let saveTimeout = null;

    function updateSliderGradient(value, max) {
      const safeMax = Math.max(max || 0, 1);
      const percentage = (value / safeMax) * 100;
      let color = '#9933ff';
      if (percentage <= 33) color = '#ff4c4c';
      else if (percentage <= 66) color = '#ffcc00';
      slider.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${percentage}%, #444 ${percentage}%, #444 100%)`;
    }

    function syncTempHp(value) {
      const safeValue = Math.max(0, value);
      const baseHp = parseInt(hpBaseInput?.value || '0', 10) || 0;
      const bonus = combatModuleState.tempHpExtra || 0;
      const newMax = Math.max(safeValue, baseHp + bonus, baseHp || 0, 1);
      slider.min = 0;
      slider.max = newMax;
      slider.value = safeValue;
      slider.setAttribute('value', safeValue);
      display.textContent = safeValue;
      hidden.value = safeValue;
      const topDisplay = document.getElementById('display_cs_hp_temp');
      if (topDisplay) {
        topDisplay.textContent = safeValue;
      }
      updateSliderGradient(safeValue, newMax);
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
      const total = parseInt(display.textContent || '0', 10) || 0;
      const baseVal = Math.max(0, total - (combatModuleState.tempHpExtra || 0));
      syncTempHp(baseVal);
      scheduleSave(baseVal);
    });

    const resetBtn = document.getElementById('btn-reset-temp-pv');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        const baseHp = parseInt(hpBaseInput?.value || '0', 10) || 0;
        syncTempHp(baseHp);
        scheduleSave(baseHp);
      });
    }

    if (bonusInput) {
      bonusInput.value = combatModuleState.tempHpExtra;
      bonusInput.addEventListener('input', () => {
        combatModuleState.tempHpExtra = parseInt(bonusInput.value || '0', 10) || 0;
        syncTempHp(parseInt(hidden.value || '0', 10) || 0);
        scheduleSave(parseInt(hidden.value || '0', 10) || 0);
      });
    }

    const startVal = parseInt(slider.value || hidden.value || '0', 10) || 0;
    syncTempHp(startVal);
  }

  function initCombatModule() {
    const attackCard = document.getElementById('combat-attack-card');
    if (!attackCard) return;

    const mainBlock = document.getElementById('combat-weapon-main');
    const offBlock = document.getElementById('combat-weapon-offhand');
    const weaponFromDataset = parseWeaponData(mainBlock?.dataset.weapon);
    const weaponSecondaryFromDataset = parseWeaponData(offBlock?.dataset.weapon);
    combatModuleState.weaponMain = weaponFromDataset || normalizeWeaponData(combatModuleState.weaponMain);
    combatModuleState.weaponOff = weaponSecondaryFromDataset || normalizeWeaponData(combatModuleState.weaponOff);

    const notesInput = document.getElementById('combat_notes');

    if (notesInput) {
      notesInput.value = combatModuleState.notes || '';
      notesInput.addEventListener('input', () => {
        combatModuleState.notes = notesInput.value;
        scheduleCombatSave();
      });
    }

    const weaponInputs = [
      { id: 'combat_attack_extra_main', key: 'attackExtraMain' },
      { id: 'combat_damage_extra_main', key: 'damageExtraMain' },
      { id: 'combat_attack_extra_off', key: 'attackExtraOff' },
      { id: 'combat_damage_extra_off', key: 'damageExtraOff' },
    ];
    weaponInputs.forEach(({ id, key }) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.value = combatModuleState[key] ?? 0;
      el.addEventListener('input', () => {
        combatModuleState[key] = parseInt(el.value || '0', 10) || 0;
        refreshCombatFromCache();
        scheduleCombatSave();
      });
    });

    const acExtraInput = document.getElementById('combat_ac_extra');
    const shieldExtraInput = document.getElementById('combat_shield_extra');
    [
      { el: acExtraInput, key: 'acExtra' },
      { el: shieldExtraInput, key: 'shieldExtra' },
    ].forEach(({ el, key }) => {
      if (!el) return;
      el.value = combatModuleState[key] ?? 0;
      el.addEventListener('input', () => {
        combatModuleState[key] = parseInt(el.value || '0', 10) || 0;
        updateCombatBasicsDisplay(lastCombatContext?.derived || buildFallbackDerived(collectCombatContextOnly()));
        updateArmorTotals(lastCombatContext?.derived || buildFallbackDerived(collectCombatContextOnly()));
        scheduleCombatSave();
      });
    });

    renderCombatWeaponsInfo(combatModuleState.weaponMain, combatModuleState.weaponOff);
    const fallbackContext = collectCombatContextOnly();
    const fallbackDerived = buildFallbackDerived(fallbackContext);
    updateCombatBasicsDisplay(fallbackDerived);
    updateArmorTotals(fallbackDerived);
    updateCombatCard(fallbackDerived, fallbackContext);
    refreshCombatFromCache();

    if (characterDataStore.data) {
      initConditionSelector();
    } else {
      loadCharacterData()
        .then(() => initConditionSelector())
        .catch(() => {});
    }
  }

  // ---------- Proficiency Modals ----------
  const ITEM_DATA_FILES = Object.freeze({
    weapon: 'jsons/dnd-weapons.json',
    armor: 'jsons/dnd-armors.json',
    tool: 'jsons/dnd-tools-es.json',
  });
  const itemCache = {
    weapon: null,
    armor: null,
    tool: null,
  };

  function preferXphbMap(map) {
    const result = {};
    if (!map || typeof map !== 'object') return result;
    Object.entries(map).forEach(([key, entry]) => {
      if (!entry) return;
      const current = result[key];
      const src = (entry.source || '').toString().toUpperCase();
      const currentSrc = (current?.source || '').toString().toUpperCase();
      const isXphb = src === 'XPHB';
      const currentIsXphb = currentSrc === 'XPHB';
      if (!current || (isXphb && !currentIsXphb)) {
        result[key] = entry;
      }
    });
    return result;
  }

  function ensureProfModal() {
    let modal = document.getElementById('prof-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'prof-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
      <div class="modal-contenido">
        <span class="close-prof-popup">&times;</span>
        <h3 id="prof-modal-title"></h3>
        <div id="prof-modal-body" class="prof-modal-body"></div>
      </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) modal.style.display = 'none';
    });
    modal.querySelector('.close-prof-popup')?.addEventListener('click', () => {
      modal.style.display = 'none';
    });
    return modal;
  }

  function openProfModal(title, bodyHtml) {
    const modal = ensureProfModal();
    const titleEl = modal.querySelector('#prof-modal-title');
    const bodyEl = modal.querySelector('#prof-modal-body');
    if (titleEl) titleEl.textContent = title || '';
    if (bodyEl) bodyEl.innerHTML = bodyHtml || '';
    modal.style.display = 'flex';
  }

  function loadItemData(type) {
    if (itemCache[type]) return Promise.resolve(itemCache[type]);

    const themeBase = (window?.THEME_DIR_URI || '').replace(/\/$/, '');
    const siteBase = window.location.origin.replace(/\/$/, '');

    let files = [];
    if (type === 'tool') {
      files = [
        'data/dnd-tools-es.json',
        'data/dnd-tools.json',
        'jsons/dnd-tools-es.json',
        'jsons/dnd-tools.json',
      ];
    } else {
      const file = ITEM_DATA_FILES[type];
      if (file) {
        files.push(file);
        if (file.includes('-es.')) {
          files.push(file.replace(/-es\./, '.'));
        }
      }
    }

    const joinPath = (base, path) => {
      if (!base || !path) return '';
      let cleanBase = base.replace(/\/+$/, '');
      let cleanPath = path.replace(/^\/+/, '');
      if (/\/data$/i.test(cleanBase) && cleanPath.startsWith('data/')) {
        cleanPath = cleanPath.replace(/^data\//, '');
      }
      if (/\/jsons$/i.test(cleanBase) && cleanPath.startsWith('jsons/')) {
        cleanPath = cleanPath.replace(/^jsons\//, '');
      }
      return `${cleanBase}/${cleanPath}`;
    };

    const candidates = [];
    files.forEach((path) => {
      const uri = getStaticDataUri(path);
      if (uri) candidates.push(uri);
      if (themeBase) candidates.push(joinPath(themeBase, path));
      candidates.push(joinPath(`${siteBase}/wp-content/themes/temahijo`, path));
    });

    const seen = new Set();
    const urlList = candidates
      .map((u) => (u || '').trim())
      .filter(Boolean)
      .map((u) => {
        let clean = u.replace(/([^:])\/{2,}/g, (m, p1) => (p1 === ':' ? m : p1 + '/'));
        clean = clean.replace(/\/data\/data\//g, '/data/');
        return clean;
      })
      .filter((u) => {
        if (!u || seen.has(u)) return false;
        seen.add(u);
        return true;
      });

    const tryNext = (idx = 0) => {
      if (idx >= urlList.length) return Promise.resolve(null);
      return fetch(urlList[idx], { credentials: 'same-origin' })
        .then((res) => (res.ok ? res.json() : null))
        .catch(() => null)
        .then((data) => {
          if (data) {
            itemCache[type] = data;
            return data;
          }
          return tryNext(idx + 1);
        });
    };

    return tryNext();
  }

  function findItemEntry(type, ref) {
    if (!ref) return null;
    const [rawId] = ref.split('|');
    const needle = slugifyWeaponId(rawId);
    const data = itemCache[type];
    if (!data) return null;
    const key = type === 'armor' ? 'armors' : type === 'tool' ? 'tools' : 'weapons';
    const list = data[key] || [];
    const direct = list.find((item) => slugifyWeaponId(item.id) === needle);
    if (direct) return direct;
    return list.find((item) => {
      const name = typeof item.name === 'object' ? item.name.es || item.name.en : item.name;
      return slugifyWeaponId(name) === needle;
    });
  }

  function renderItemDetails(type, entry) {
    if (!entry) return '<p>No se encontró información.</p>';
    const name = typeof entry.name === 'object' ? entry.name.es || entry.name.en || entry.id : entry.name;
    const lines = [];
    if (type === 'weapon') {
      if (entry.dmg1) lines.push(`<strong>Daño:</strong> ${escapeHtml(entry.dmg1)} ${escapeHtml(entry.dmgType || '')}`);
      if (entry.properties?.length) lines.push(`<strong>Propiedades:</strong> ${escapeHtml(entry.properties.join(', '))}`);
      if (entry.category) lines.push(`<strong>Categoría:</strong> ${escapeHtml(entry.category)}`);
      if (entry.range) lines.push(`<strong>Alcance:</strong> ${escapeHtml(entry.range)}`);
    } else if (type === 'armor') {
      if (entry.type) lines.push(`<strong>Tipo:</strong> ${escapeHtml(entry.type)}`);
      if (entry.ac) lines.push(`<strong>CA:</strong> ${escapeHtml(entry.ac)}`);
      if (entry.strength) lines.push(`<strong>Fuerza mín.:</strong> ${escapeHtml(entry.strength)}`);
      if (entry.stealthDisadvantage) lines.push(`<strong>Sigilo:</strong> Desventaja`);
    } else if (type === 'tool') {
      if (entry.category) lines.push(`<strong>Categoría:</strong> ${escapeHtml(entry.category)}`);
    }
    if (entry.weight) lines.push(`<strong>Peso:</strong> ${escapeHtml(String(entry.weight))}`);
    if (entry.value) lines.push(`<strong>Valor:</strong> ${escapeHtml(String(entry.value))}`);
    return `<h4>${escapeHtml(name)}</h4><div class="prof-modal-details">${lines.join('<br>')}</div>`;
  }

  document.addEventListener('click', (ev) => {
    const chip = ev.target.closest('.prof-chip.dnd5-link-item');
    if (!chip) return;
    const ref = chip.dataset.dnd5Ref || '';
    const type = chip.dataset.profType || 'item';
    loadItemData(type).then(() => {
      const entry = findItemEntry(type, ref);
      const body = renderItemDetails(type, entry);
      const title = typeof entry?.name === 'object' ? entry.name.es || entry.name.en || '' : entry?.name || '';
      openProfModal(title || chip.textContent || 'Detalle', body);
    });
  });

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
    const modalClassSelect = overlay.querySelector('#modal-clase');

    if (modalClassSelect && typeof profsSection.applyClassProficiencies === 'function') {
      modalClassSelect.addEventListener('change', () => {
        profsSection.applyClassProficiencies(modalClassSelect.value);
      });
    }

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

      function renderFromCatalog(catalog) {
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
      }

      const cached = ensureEsotericTheoryCatalog();
      if (Object.keys(cached).length) {
        renderFromCatalog(cached);
        return;
      }

      container.innerHTML = '<p class="theory-picker__hint">Cargando teorías esotéricas…</p>';
      fetchApothecaryTheoryCatalog()
        .then((catalog) => renderFromCatalog(catalog))
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
    const staticProfBase = (() => {
      const racesUri = STATIC_DATA?.races || '';
      const idx = racesUri.lastIndexOf('/');
      return idx === -1 ? '' : racesUri.slice(0, idx + 1);
    })();

    function mapStaticEntry(item) {
      if (!item) return { id: '', name: '' };
      const name =
        typeof item.name === 'object' ? item.name.es || item.name.en || item.id : item.name || item.id || '';
      return {
        id: item.id || sanitizeProficiencyValue(name),
        name,
      };
    }

    function loadStaticList(type) {
      if (!staticProfBase) return Promise.resolve([]);
      const fileMap = {
        weapons: ['dnd-weapons-es.json', 'dnd-weapons.json'],
        armors: ['dnd-armors-es.json', 'dnd-armors.json'],
        tools: ['dnd-tools-es.json', 'dnd-tools.json'],
        languages: ['dnd-languages-es.json', 'dnd-languages.json'],
      };
      const rootKeyMap = {
        weapons: 'weapons',
        armors: 'armors',
        tools: 'tools',
        languages: 'languages',
      };
      const files = fileMap[type] || [];
      const root = rootKeyMap[type] || '';

      const attempt = (index = 0) => {
        if (index >= files.length) return Promise.resolve([]);
        const target = staticProfBase + files[index];
        return fetch(target)
          .then((res) => (res.ok ? res.json() : Promise.reject(new Error('No se pudo cargar el json'))))
          .then((json) => {
            const list = Array.isArray(json?.[root]) ? json[root] : [];
            return list.map(mapStaticEntry);
          })
          .catch(() => attempt(index + 1));
      };

      return attempt();
    }

    function fallbackProficiencyData() {
      return Promise.all([
        loadStaticList('weapons'),
        loadStaticList('armors'),
        loadStaticList('tools'),
        loadStaticList('languages'),
      ]).then(([weapons, armors, tools, languages]) => ({
        weapons,
        armors,
        tools,
        languages,
      }));
    }

    function fetchProficiencies() {
      if (profDataPromise) return profDataPromise;
      const useFallback = () =>
        fallbackProficiencyData().catch(() => ({
          weapons: [],
          armors: [],
          tools: [],
          languages: [],
        }));
      if (!hasApi) return useFallback();
      const formData = new FormData();
      formData.append('action', 'drak_dnd5_get_proficiencies');
      profDataPromise = fetch(window.DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => (data?.success ? data.data : {}))
        .then((data) => {
          const needsFallback =
            !data?.weapons?.length || !data?.armors?.length || !data?.tools?.length || !data?.languages?.length;
          if (!needsFallback) {
            return data;
          }
          return useFallback().then((fallback) => ({
            weapons: data?.weapons?.length ? data.weapons : fallback.weapons,
            armors: data?.armors?.length ? data.armors : fallback.armors,
            tools: data?.tools?.length ? data.tools : fallback.tools,
            languages: data?.languages?.length ? data.languages : fallback.languages,
          }));
        })
        .catch((error) => {
          console.error('Error al cargar competencias', error);
          return useFallback();
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

    function sanitizeProficiencyValue(value) {
      const clean = strip5eTags(value || '');
      return clean.replace(/[,;]+/g, ' / ').replace(/\s+/g, ' ').trim();
    }

    function flattenClassProficiencies(entries) {
      if (!entries) return [];
      const list = Array.isArray(entries) ? entries : [entries];
      const seen = new Set();
      const result = [];

      list.forEach((entry) => {
        if (!entry) return;
        if (typeof entry === 'string') {
          const clean = sanitizeProficiencyValue(entry);
          if (clean && !seen.has(clean)) {
            seen.add(clean);
            result.push(clean);
          }
          return;
        }

        if (entry.type === 'fixed' && Array.isArray(entry.items)) {
          entry.items.forEach((item) => {
            const clean = sanitizeProficiencyValue(item);
            if (clean && !seen.has(clean)) {
              seen.add(clean);
              result.push(clean);
            }
          });
          return;
        }

        const choice = entry.choose || (entry.type === 'choice' ? entry : null);
        if (choice && Array.isArray(choice.from || choice.options)) {
          const from = choice.from || choice.options;
          const count = choice.count || from.length || 1;
          const label = sanitizeProficiencyValue(`Elige ${count} de ${from.map(strip5eTags).join(' / ')}`);
          if (label && !seen.has(label)) {
            seen.add(label);
            result.push(label);
          }
          return;
        }

        if (typeof entry === 'object') {
          Object.keys(entry).forEach((key) => {
            if (!entry[key]) return;
            const clean = sanitizeProficiencyValue(key);
            if (clean && !seen.has(clean)) {
              seen.add(clean);
              result.push(clean);
            }
          });
        }
      });

      return result;
    }

    function applyClassProficiencies(classId) {
      const nextClassId = (classId || '').trim();
      if (!nextClassId) {
        Object.entries(config).forEach(([type, info]) => {
          info.values = [];
          renderList(type);
        });
        return;
      }

      loadCharacterData()
        .then((data) => {
          const classDef = data?.classDetails?.[nextClassId];
          const profs = classDef?.startingProficiencies || {};
          const nextValues = {
            weapons: flattenClassProficiencies(profs.weapons),
            armors: flattenClassProficiencies(profs.armor),
            tools: flattenClassProficiencies(profs.tools || profs.toolProficiencies),
            languages: flattenClassProficiencies(profs.languages),
          };

          Object.entries(config).forEach(([type, info]) => {
            info.values = nextValues[type] || [];
            renderList(type);
          });
        })
        .catch(() => {});
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

    return { populate, apply, ensureLookupCache, applyClassProficiencies };
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

    qsa('.skill-indicator[data-skill-indicator]').forEach((indicator) => {
      const skillId = indicator.dataset.skillIndicator;
      if (!skillId) return;
      indicator.addEventListener('click', () => {
        const isAuto = characterAutomationState.autoSkills?.has(skillId);
        if (isAuto) return;
        if (manualSkillSelections.has(skillId)) {
          manualSkillSelections.delete(skillId);
          setSkillProficiency(skillId, false, null, characterAutomationState.expertise.has(skillId), false);
        } else {
          manualSkillSelections.add(skillId);
          setSkillProficiency(skillId, true, 'Manual', characterAutomationState.expertise.has(skillId), true);
        }
        persistManualSkills();
        recomputeSkillsAndSaves();
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
      features: 'Rasgos y habilidades',
      spells: 'Conjuros',
      actions: 'Acciones',
      background: 'Trasfondo',
      tools: 'Herramientas',
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
      if (tabName === 'tools') {
        renderToolsTab();
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

    function renderToolsTab() {
      const profInput = document.getElementById('prof_tools');
      const tokens = new Set();
      parseIds(profInput?.value || '').forEach((id) => tokens.add(id));
      (lastDerivedTools || []).forEach((item) => tokens.add(item));
      const list = Array.from(tokens).filter(Boolean);

      if (!list.length) {
        showEmpty('No hay herramientas con competencia.');
        return;
      }

      showLoading();
      loadItemData('tool')
        .then((data) => {
          const tools = data?.tools || [];
          const cards = list
            .map((raw) => {
              const info = normalizeToolInfo(raw, tools);
              return renderToolCard(info);
            })
            .join('');
          panelEl.innerHTML = `
            <section class="character-extended__section">
              <h4 class="character-extended__section-title">Herramientas</h4>
              ${cards}
            </section>
          `;
          initFeatureAccordions(panelEl);
        })
        .catch(() => {
          const cards = list
            .map((raw) => {
              const info = { name: raw };
              return renderToolCard(info);
            })
            .join('');
          panelEl.innerHTML = `
            <section class="character-extended__section">
              <h4 class="character-extended__section-title">Herramientas</h4>
              ${cards}
            </section>
          `;
        });
    }

  function normalizeToolInfo(raw, dataset) {
    const text = (raw || '').toString();
    const sourceMatch = text.match(/^(Clase|Raza|Trasfondo)\s*:\s*(.+)$/i);
    const source = sourceMatch ? sourceMatch[1] : '';
    const label = sourceMatch ? sourceMatch[2] : text;
    const slugifyTool = (value) => {
      const base = slugifyWeaponId(value);
      return base.replace(/^(herramientas?-de-|juego-de-|kit-de-|kit-)/, '');
    };
    const slugCandidates = [
      slugifyTool(label),
      slugifyWeaponId(label),
    ].filter(Boolean);

    let entry = null;
    if (Array.isArray(dataset)) {
      entry = dataset.find((item) => {
        const names = [
          item.id,
          item.name?.es,
          item.name?.en,
          item.name,
        ].filter(Boolean);
        const itemSlugs = names.flatMap((n) => [slugifyTool(n), slugifyWeaponId(n)]).filter(Boolean);
        return itemSlugs.some((s) => slugCandidates.includes(s));
      }) || null;
    }

    const name =
      entry && typeof entry.name === 'object'
        ? entry.name.es || entry.name.en || entry.id
        : entry?.name || label;
    return {
      source,
      name: name || label,
      entry,
    };
    }

    function renderToolCard(info) {
      if (!info) return '';
      const entry = info.entry;
      const meta = [];
      if (entry?.category) meta.push(entry.category);
      const metaHtml = meta.length ? `<div class="feature-card__meta">${meta.join(' · ')}</div>` : '';
    const bodyEntries = entry ? getLocalizedArrayFrom(entry, 'entries') : [];
    const fallbackEntries = entry?.entries || entry?.text || entry?.description || entry?.desc;
      const body = bodyEntries && bodyEntries.length
        ? renderEntries(bodyEntries)
        : (fallbackEntries ? renderEntries(fallbackEntries) : '');
      const extra = [];
      if (entry?.weight) extra.push(`<p><strong>Peso:</strong> ${escapeHtml(String(entry.weight))}</p>`);
      if (entry?.value) extra.push(`<p><strong>Valor:</strong> ${escapeHtml(String(entry.value))}</p>`);
      return `
        <article class="feature-card is-collapsed">
          <header class="feature-card__header">
            <h5 class="feature-card__title">${escapeHtml(info.name || 'Herramienta')}</h5>
            <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="Mostrar detalle">
              <span class="feature-card__toggle-icon">▼</span>
            </button>
          </header>
          <div class="feature-card__content">
            ${metaHtml}
            <div class="feature-card__body">
              ${body || '<p>Sin descripción disponible.</p>'}
              ${extra.join('')}
            </div>
          </div>
        </article>
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

    if (Array.isArray(entry.colStyles_es) && entry.colStyles_es.length) {
      replacements.colStyles = entry.colStyles_es;
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
      const colLabels = getLocalizedArrayFrom(entry, 'colLabels');
      const colStyles = getLocalizedArrayFrom(entry, 'colStyles');
      const rows = getLocalizedArrayFrom(entry, 'rows');
      const header = colLabels.length
        ? `<thead><tr>${colLabels
            .map((label, idx) => {
              const colClass = colStyles[idx] ? ` class="${escapeHtml(colStyles[idx])}"` : '';
              return `<th${colClass}>${format5eText(label)}</th>`;
            })
            .join('')}</tr></thead>`
        : '';
      const body = rows
        .map((row) => {
          const cells = Array.isArray(row) ? row : [];
          return `<tr>${cells
            .map((cell, idx) => {
              const colClass = colStyles[idx] ? ` class="${escapeHtml(colStyles[idx])}"` : '';
              return `<td${colClass}>${renderTableCell(cell)}</td>`;
            })
            .join('')}</tr>`;
        })
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

    if (type === 'quote') {
      const body = renderEntries(entry.entries || []);
      const by = entry.by ? `<footer>${format5eText(entry.by)}</footer>` : '';
      return `<blockquote class="dnd5-quote">${body}${by}</blockquote>`;
    }

    if (type === 'inset') {
      const title = entry.name ? `<h4>${escapeHtml(entry.name)}</h4>` : '';
      const body = renderEntries(entry.entries || []);
      return `<div class="dnd5-entry-block dnd5-inset">${title}${body}</div>`;
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

  function renderTableCell(cell) {
    if (cell == null) return '';
    if (Array.isArray(cell)) {
      return cell.map((c) => renderTableCell(c)).join('<br>');
    }
    if (typeof cell === 'object') {
      if (cell.type) {
        return renderEntryNode(cell);
      }
      if (cell.entry) {
        return format5eText(cell.entry);
      }
    }
    return format5eText(cell);
  }

  function fetchStaticJson(url) {
    if (!url) return Promise.resolve(null);
    return fetch(url, { credentials: 'same-origin' })
      .then((resp) => (resp.ok ? resp.json() : null))
      .catch(() => null);
  }

  function getStaticDataUri(filename) {
    if (!STATIC_DATA?.races) return filename;
    const base = STATIC_DATA.races.replace(/[^/]+$/, '');
    if (filename.startsWith('jsons/')) {
      // Sustituimos /data/ por /jsons/ para los ficheros locales.
      const altBase = base.replace(/\/data\/?$/i, '/jsons/');
      return altBase + filename.replace(/^jsons\//, '');
    }
    return base + filename;
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
      STATIC_DATA.conditions ? fetchStaticJson(STATIC_DATA.conditions) : Promise.resolve(null),
    ];
    characterDataStore.promise = Promise.all(requests)
      .then(([races, backgrounds, classDetails, classList, feats, theories, conditions]) => {
          const raceMapRaw = {};
          (races?.races || []).forEach((race) => {
            if (race?.id) raceMapRaw[race.id] = race;
          });
          const raceMap = preferXphbMap(raceMapRaw);

          const backgroundMapRaw = {};
          (backgrounds?.backgrounds || []).forEach((bg) => {
            if (bg?.id) backgroundMapRaw[bg.id] = bg;
          });
          const backgroundMap = preferXphbMap(backgroundMapRaw);

          const classDetailMap = preferXphbMap(classDetails?.classes || {});
          const classListMap = preferXphbMap(
            (classList?.classes || []).reduce((acc, cls) => {
              if (cls?.id) acc[cls.id] = cls;
              return acc;
            }, {})
          );

          const featMap = preferXphbMap(
            (feats?.feats || []).reduce((acc, feat) => {
              if (!feat) return acc;
              const key = feat.id || feat.name;
              if (key) acc[key] = feat;
              return acc;
            }, {})
          );

          const fallbackTheories = getStaticEsotericTheoryList();
          const rawTheories =
            Array.isArray(theories?.classFeature) && theories.classFeature.length
              ? theories.classFeature
              : fallbackTheories;
          const theoryMap = buildEsotericTheoryMap(rawTheories);

          const conditionMap = {};
          (conditions?.conditions || []).forEach((cond) => {
            if (cond?.id) conditionMap[cond.id] = cond;
          });

          characterDataStore.data = {
            races: raceMap,
            backgrounds: backgroundMap,
            classes: classListMap,
            classDetails: classDetailMap,
            feats: featMap,
            esotericTheories: theoryMap,
            conditions: conditionMap,
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
    updateCombatCard(derived, context);
  }

  function refreshCombatFromCache() {
    if (lastCombatContext) {
      updateCombatCard(lastCombatContext.derived, lastCombatContext.context);
    }
  }

  let combatSaveTimer = null;
  function scheduleCombatSave() {
    clearTimeout(combatSaveTimer);
    combatSaveTimer = setTimeout(persistCombatState, 400);
  }

  function persistCombatState() {
    if (!window.DND5_API || !window.HP_TEMP_AJAX?.post_id) return;
    const formData = new FormData();
    formData.append('action', 'guardar_modulo_combate');
    formData.append('post_id', window.HP_TEMP_AJAX.post_id);
    formData.append('attack_extra_main', combatModuleState.attackExtraMain || 0);
    formData.append('damage_extra_main', combatModuleState.damageExtraMain || 0);
    formData.append('attack_extra_off', combatModuleState.attackExtraOff || 0);
    formData.append('damage_extra_off', combatModuleState.damageExtraOff || 0);
    formData.append('ac_extra', combatModuleState.acExtra || 0);
    formData.append('shield_extra', combatModuleState.shieldExtra || 0);
    formData.append('temp_hp_extra', combatModuleState.tempHpExtra || 0);
    formData.append('notes', combatModuleState.notes || '');

    fetch(window.DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    }).catch((error) => console.error('Error al guardar módulo de combate', error));
  }

  function parseWeaponData(raw) {
    if (!raw) return null;
    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw);
        return normalizeWeaponData(parsed);
      } catch (e) {
        try {
          const decoded = raw.replace(/&quot;/g, '"');
          const parsed = JSON.parse(decoded);
          return normalizeWeaponData(parsed);
        } catch (err) {
          return null;
        }
      }
    }
    if (typeof raw === 'object') {
      return normalizeWeaponData(raw);
    }
    return null;
  }

  function normalizeWeaponData(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const propsRaw = Array.isArray(value.properties)
      ? value.properties
      : typeof value.properties === 'string'
        ? value.properties.split(',')
        : [];
    const props = propsRaw
      .map((prop) => (prop == null ? '' : prop.toString().trim()))
      .filter((prop) => prop !== '');

    return {
      name: value.name || '',
      slug: value.slug || '',
      category: value.category || '',
      damageDice: value.damage_dice || value.damageDice || '',
      damageType: value.damage_type || value.damageType || '',
      properties: props,
      isMagical: Boolean(value.is_magical || value.es_magica),
      description: value.description || value.descripcion || '',
    };
  }

  function normalizeArmorData(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const acRaw = value.ac ?? value.base_ac ?? value.baseAc ?? '';
    const ac = typeof acRaw === 'number' ? acRaw : parseInt(acRaw || '0', 10) || 0;
    return {
      name: value.name || '',
      slug: value.slug || '',
      type: value.type || '',
      ac,
      strength: value.strength || '',
      stealthDisadvantage: Boolean(value.stealth_disadvantage || value.stealthDisadvantage),
      weight: value.weight ?? '',
      value: value.value ?? '',
      description: value.description || value.descripcion || '',
    };
  }

  function renderCombatArmorInfo(armor, derived) {
    const nameEl = document.getElementById('combat-armor-name');
    const metaEl = document.getElementById('combat-armor-meta');
    if (!nameEl || !metaEl) return;

    if (!armor) {
      nameEl.textContent = 'Sin armadura';
      metaEl.textContent = 'Selecciona armadura en el inventario.';
      return;
    }

    nameEl.textContent = armor.name || 'Armadura';
    const meta = [];
    if (armor.ac) meta.push(`CA base ${armor.ac}`);
    if (typeof derived?.ac === 'number') meta.push(`CA actual ${derived.ac}`);
    if (armor.type) meta.push(`Tipo: ${armor.type}`);
    meta.push(`Sigilo: ${armor.stealthDisadvantage ? 'Desventaja' : '—'}`);
    metaEl.textContent = meta.filter(Boolean).join(' · ') || '—';
  }

  function renderCombatWeaponsInfo(weaponMain, weaponOff) {
    const slots = [
      { key: 'main', weapon: normalizeWeaponData(weaponMain) },
      { key: 'off', weapon: normalizeWeaponData(weaponOff) },
    ];
    slots.forEach(({ key, weapon }) => {
      const nameEl = document.getElementById(`combat-${key}-weapon-name`);
      const metaEl = document.getElementById(`combat-${key}-weapon-meta`);
      if (!nameEl || !metaEl) return;
      if (!weapon) {
        nameEl.textContent = key === 'off' ? 'Sin arma secundaria' : 'Sin arma asignada';
        metaEl.textContent = key === 'off'
          ? 'Selecciona un arma secundaria en el inventario.'
          : 'Selecciona un arma en el inventario.';
        return;
      }
      nameEl.textContent = weapon.name || (key === 'off' ? 'Arma secundaria' : 'Arma principal');
      const parts = [];
      if (weapon.damageDice) parts.push(weapon.damageDice);
      if (weapon.damageType) parts.push(weapon.damageType);
      if (weapon.category) parts.push(weapon.category);
      if (weapon.properties?.length) parts.push(weapon.properties.join(', '));
      metaEl.textContent = parts.filter(Boolean).join(' · ') || '—';
    });
  }

  function updateCombatCard(derived, context) {
    const card = document.getElementById('combat-attack-card');
    if (!card) return;
    lastCombatContext = { derived, context };
    updateCombatBasicsDisplay(derived);
    updateArmorTotals(derived);

    renderCombatWeaponsInfo(combatModuleState.weaponMain, combatModuleState.weaponOff);
    renderCombatArmorInfo(normalizeArmorData(COMBAT_CONFIG.armor), derived);

    const proficiencySet = buildWeaponProficiencySet(context);

    const slots = [
      { key: 'main', weapon: normalizeWeaponData(combatModuleState.weaponMain) },
      { key: 'off', weapon: normalizeWeaponData(combatModuleState.weaponOff) },
    ];

    slots.forEach(({ key, weapon }) => {
      const attackEl = document.getElementById(`combat-${key}-attack-value`);
      const damageEl = document.getElementById(`combat-${key}-damage-value`);
      const attackBreakEl = document.getElementById(`combat-${key}-attack-breakdown`);
      const damageBreakEl = document.getElementById(`combat-${key}-damage-breakdown`);
      const extraAttack =
        key === 'off' ? combatModuleState.attackExtraOff || 0 : combatModuleState.attackExtraMain || 0;
      const extraDamage =
        key === 'off' ? combatModuleState.damageExtraOff || 0 : combatModuleState.damageExtraMain || 0;

      if (!weapon) {
        if (attackEl) attackEl.textContent = '—';
        if (damageEl) damageEl.textContent = '—';
        if (attackBreakEl) attackBreakEl.textContent = key === 'off' ? 'Selecciona un arma secundaria.' : 'Selecciona un arma en el inventario.';
        if (damageBreakEl) damageBreakEl.textContent = '';
        renderWeaponMasteryInfo(card, '', false, '');
        return;
      }

      const abilityInfo = pickWeaponAbility(weapon, derived);
      const isProficient = isProficientWithWeapon(weapon, proficiencySet);
      const profBonus = isProficient ? derived.proficiencyBonus : 0;
      const masteryKey = getWeaponMasteryKey(weapon);
      const hasMastery = hasWeaponMasteryAccess(context);
      const masteryActive = Boolean(masteryKey && isProficient && hasMastery);
      const masteryReason = !masteryActive && masteryKey
        ? (!isProficient ? 'sin competencia' : !hasMastery ? 'sin rasgo/feat de Maestría' : '')
        : '';
      renderWeaponMasteryInfo(card, masteryKey, masteryActive, masteryReason);
      const attackTotal = abilityInfo.mod + profBonus + extraAttack;

      if (attackEl) attackEl.textContent = formatMod(attackTotal);
      if (attackBreakEl) {
        const parts = [];
        parts.push(`${ABILITY_LABELS[abilityInfo.ability] || abilityInfo.ability}: ${formatMod(abilityInfo.mod)}`);
        if (isProficient) {
          parts.push(`Competencia ${formatMod(derived.proficiencyBonus)}`);
        }
        if (extraAttack) {
          parts.push(`Extra ${formatMod(extraAttack)}`);
        }
        attackBreakEl.textContent = parts.join(' · ');
      }

      const damageMod = abilityInfo.mod + extraDamage;
      if (damageEl) damageEl.textContent = formatDamageString(weapon.damageDice, damageMod, weapon.damageType);
      if (damageBreakEl) {
        const parts = [];
        parts.push(`Mod (${ABILITY_LABELS[abilityInfo.ability] || abilityInfo.ability}): ${formatMod(abilityInfo.mod)}`);
        if (extraDamage) {
          parts.push(`Extra ${formatMod(extraDamage)}`);
        }
        damageBreakEl.textContent = parts.join(' · ');
      }
    });
  }

  function pickWeaponAbility(weapon, derived) {
    const props = new Set((weapon?.properties || []).map((prop) => prop.toString().trim().toLowerCase()));
    const category = (weapon?.category || '').toLowerCase();
    const strMod = derived?.abilityMods?.str ?? 0;
    const dexMod = derived?.abilityMods?.dex ?? 0;

    const hasThrown = Array.from(props).some((p) => p.includes('thrown') || p.includes('arrojadiza'));
    const hasAmmo = Array.from(props).some((p) => p.includes('ammunition') || p.includes('municion'));
    const hasFinesse = Array.from(props).some((p) => p.includes('finesse') || p.includes('sutileza'));
    const isRanged = category.includes('ranged') || category.includes('distancia') || hasAmmo;
    const isThrown = hasThrown;

    if (isRanged) {
      return { ability: 'dex', mod: dexMod, reason: 'Arma a distancia' };
    }
    if (hasFinesse) {
      if (dexMod >= strMod) {
        return { ability: 'dex', mod: dexMod, reason: 'Arma sutil' };
      }
      return { ability: 'str', mod: strMod, reason: 'Arma sutil (FUE)' };
    }
    if (isThrown) {
      return { ability: 'str', mod: strMod, reason: 'Arma arrojadiza' };
    }
    return { ability: 'str', mod: strMod, reason: 'Arma cuerpo a cuerpo' };
  }

  function buildWeaponProficiencySet(context) {
    const set = new Set();
    const manualWeapons = parseIds(document.getElementById('prof_weapons')?.value || '');
    manualWeapons.forEach((id) => addWeaponTokenVariants(set, id));

    const classDef = characterDataStore.data?.classDetails?.[context.classId];
    addWeaponEntriesToSet(set, classDef?.startingProficiencies?.weapons);

    const raceDef = characterDataStore.data?.races?.[context.raceId];
    if (raceDef?.weaponProficiencies) {
      addWeaponEntriesToSet(set, raceDef.weaponProficiencies);
    }

    const backgroundDef = characterDataStore.data?.backgrounds?.[context.backgroundId];
    if (backgroundDef?.weaponProficiencies) {
      addWeaponEntriesToSet(set, backgroundDef.weaponProficiencies);
    }

    return set;
  }

  function addWeaponEntriesToSet(set, entries) {
    if (!entries) return;
    const list = Array.isArray(entries) ? entries : [entries];
    list.forEach((entry) => {
      if (!entry) return;
      if (typeof entry === 'string') {
        addWeaponTokenVariants(set, entry);
      } else if (entry.type === 'fixed' && Array.isArray(entry.items)) {
        entry.items.forEach((item) => addWeaponTokenVariants(set, item));
      } else if (entry.choose && Array.isArray(entry.choose.from)) {
        entry.choose.from.forEach((item) => addWeaponTokenVariants(set, item));
      }
    });
  }

  function addWeaponTokenVariants(set, value) {
    const slug = slugifyWeaponId(value);
    if (!slug) return;
    set.add(slug);
    const trimmed = slug.replace(/-(phb|dmg|tce|xge|scag|ua|lvl).*$/, '');
    if (trimmed && trimmed !== slug) {
      set.add(trimmed);
    }
    const simplified = slug.replace(/-weapons?/, '');
    if (simplified) {
      set.add(simplified);
    }
  }

  function slugifyWeaponId(value) {
    return (value || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function baseWeaponSlug(value) {
    const slug = slugifyWeaponId(value);
    return slug.replace(/-(phb|xphb|one|tce|scag|xge|erlw|bgg|dmg|lvl|mm|vgm|ua).*$/, '');
  }

  function getWeaponMasteryKey(weapon) {
    if (!weapon) return '';
    const candidates = [];
    if (weapon.slug) candidates.push(weapon.slug);
    if (weapon.name) candidates.push(weapon.name);
    for (const candidate of candidates) {
      const base = baseWeaponSlug(candidate);
      if (WEAPON_MASTERY_MAP[base]) return WEAPON_MASTERY_MAP[base];
      if (WEAPON_MASTERY_MAP[candidate]) return WEAPON_MASTERY_MAP[candidate];
    }
    return '';
  }

  function collectFeatNames() {
    const feats = new Set();
    qsa('[data-feat-name]').forEach((node) => {
      const raw = (node.value || node.textContent || '').trim();
      if (raw) feats.add(raw);
    });
    qsa('input[name*="feat_name"]').forEach((node) => {
      const raw = (node.value || '').trim();
      if (raw) feats.add(raw);
    });
    qsa('input[name*="feat_id"]').forEach((node) => {
      const raw = (node.value || '').trim();
      if (raw) feats.add(raw);
    });
    const featList = document.getElementById('feat_list');
    if (featList?.value) {
      const raw = featList.value.trim();
      try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
          parsed.forEach((entry) => {
            if (!entry) return;
            if (typeof entry === 'string') feats.add(entry);
            else if (entry.feat_name) feats.add(entry.feat_name);
          });
        }
      } catch (e) {
        raw
          .split(/[,\\n]+/)
          .map((v) => v.trim())
          .filter(Boolean)
          .forEach((v) => feats.add(v));
      }
    }
    return Array.from(feats);
  }

  function hasWeaponMasteryFeat() {
    return collectFeatNames().some((name) => /weapon mastery|maestr[ií]a con armas/i.test(name));
  }

  function hasWeaponMasteryAccess(context) {
    const classId = (context?.classId || '').toLowerCase();
    const hasClassMastery = Array.from(WEAPON_MASTERY_CLASSES).some((slug) => classId.includes(slug));
    if (hasClassMastery) return true;
    return hasWeaponMasteryFeat();
  }

  function ensureCombatMasteryElement(card) {
    if (!card) return null;
    let el = card.querySelector('#combat-weapon-mastery');
    if (!el) {
      el = document.createElement('p');
      el.id = 'combat-weapon-mastery';
      el.className = 'combat-weapon-mastery';
      const header = card.querySelector('.combat-card__header') || card;
      header.appendChild(el);
    }
    return el;
  }

  function renderWeaponMasteryInfo(card, masteryKey, isActive, reason) {
    const el = ensureCombatMasteryElement(card);
    if (!el) return;
    if (!masteryKey) {
      el.textContent = '';
      el.classList.add('is-hidden');
      return;
    }
    el.classList.remove('is-hidden');
    const label = masteryKey.charAt(0).toUpperCase() + masteryKey.slice(1);
    const effect = WEAPON_MASTERY_TEXT[masteryKey] || '';
    const status = isActive ? 'Activa' : 'No aplicable';
    const detail = reason ? ` (${reason})` : '';
    el.textContent = `Maestría: ${label} — ${status}${detail}${effect ? ` · ${effect}` : ''}`;
  }

  function isProficientWithWeapon(weapon, proficiencySet) {
    if (!weapon) return false;
    const tokens = new Set();
    [weapon.slug, weapon.name].forEach((val) => {
      const slug = slugifyWeaponId(val);
      if (slug) {
        tokens.add(slug);
        tokens.add(slug.replace(/-(phb|dmg|tce|xge|scag|ua|lvl).*$/, ''));
      }
    });

    const categorySlug = slugifyWeaponId(weapon.category || '');
    if (categorySlug) {
      tokens.add(categorySlug);
      if (categorySlug.includes('martial')) tokens.add('martial');
      if (categorySlug.includes('simple')) tokens.add('simple');
      if (categorySlug.includes('firearm')) tokens.add('firearm');
      if (categorySlug.includes('ranged')) tokens.add('ranged');
      if (categorySlug.includes('melee')) tokens.add('melee');
    }

    for (const token of tokens) {
      if (proficiencySet.has(token)) return true;
    }
    return false;
  }

  function formatDamageString(dice, mod, type) {
    const pieces = [];
    if (dice) pieces.push(dice);
    if (mod) pieces.push(formatMod(mod));
    const joined = pieces.join(' ').trim();
    const typeLabel = type ? ` ${type}` : '';
    return (joined || '—') + typeLabel;
  }

  function initConditionSelector() {
    const select = document.getElementById('combat-condition-select');
    const preview = document.getElementById('combat-condition-preview');
    const listEl = document.getElementById('combat-condition-list');
    const addBtn = document.getElementById('combat-condition-add');
    if (!select || !preview || !listEl || !characterDataStore.data?.conditions) {
      if (select) {
        select.innerHTML = '<option value="">No se pudieron cargar condiciones.</option>';
      }
      return;
    }

    const activeConditions = [];

    const entries = Object.values(characterDataStore.data.conditions || {});
    const sorted = entries.sort((a, b) => (a.name_es || a.name || '').localeCompare(b.name_es || b.name || ''));

    select.innerHTML = `<option value="">Selecciona una condición…</option>${sorted
      .map((cond) => `<option value="${cond.id}">${escapeHtml(cond.name_es || cond.name || cond.id)}</option>`)
      .join('')}`;

    function renderPreview(cond) {
      if (!cond) {
        preview.innerHTML = '<p>Selecciona una condición para ver sus efectos y añádela.</p>';
        return;
      }
      const entriesHtml = Array.isArray(cond.entries)
        ? `<ul>${cond.entries.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}</ul>`
        : '<p>No hay detalles disponibles.</p>';
      preview.innerHTML = `
        <h4>${escapeHtml(cond.name_es || cond.name || cond.id)}</h4>
        ${entriesHtml}
        <p class="combat-condition-source">Fuente: ${escapeHtml(cond.source || '')}</p>
      `;
    }

    function renderConditionList() {
      if (!activeConditions.length) {
        listEl.innerHTML = '<p class="combat-condition-empty">Sin condiciones activas.</p>';
        return;
      }
      const html = activeConditions
        .map((condId) => {
          const cond = characterDataStore.data.conditions[condId];
          if (!cond) return '';
          return `
            <div class="combat-condition-chip" data-cond="${condId}">
              <div>
                <strong>${escapeHtml(cond.name_es || cond.name || cond.id)}</strong>
                <small>${escapeHtml(cond.source || '')}</small>
              </div>
              <button type="button" class="combat-condition-remove" aria-label="Quitar ${escapeHtml(
                cond.name_es || cond.name || cond.id
              )}">✕</button>
            </div>
          `;
        })
        .join('');
      listEl.innerHTML = html;
    }

    select.addEventListener('change', () => {
      const cond = characterDataStore.data.conditions[select.value];
      renderPreview(cond);
    });

    addBtn?.addEventListener('click', () => {
      const condId = select.value;
      if (!condId) return;
      if (!activeConditions.includes(condId)) {
        activeConditions.push(condId);
        renderConditionList();
      }
    });

    listEl.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.combat-condition-remove');
      if (!btn) return;
      const chip = btn.closest('.combat-condition-chip');
      const condId = chip?.dataset.cond;
      if (!condId) return;
      const idx = activeConditions.indexOf(condId);
      if (idx >= 0) {
        activeConditions.splice(idx, 1);
        renderConditionList();
      }
    });

    renderPreview(null);
    renderConditionList();
  }

function normalizeProficiencyList(list) {
    if (!Array.isArray(list)) return [];
    const seen = new Set();
    const filtered = [];
    const skipTokens = /\b(phb|xphb|ua|tce|xge|dmg|scag)\b/i;
    list.forEach((raw) => {
      const text = (raw || '').toString().trim();
      if (!text) return;
      // Descarta entradas que parecen solo la fuente (phb, etc.).
      if (!text.includes(':') && skipTokens.test(text)) return;
      const normalized = text.replace(/\\s*[,;]\\s*/g, ', ').replace(/\\s+/g, ' ').trim();
      const key = normalized.toLowerCase();
      if (seen.has(key)) return;
      seen.add(key);
      filtered.push(normalized);
    });
    return filtered;
}

  function renderProficiencyLinks(list, targetId, type = '') {
    const target = document.getElementById(targetId);
    if (!target) return;
    const items = Array.isArray(list) ? list : [];
    if (!items.length) {
      target.textContent = '—';
      return;
    }
    const html = items
      .map((entry, idx) => {
        const raw = (entry || '').toString();
        const clean = raw.replace(/^[^:]+:\s*/, '').trim();
        const match = clean.match(/\{@item\s+([^|}]+)\|([^|}]+)(?:\|([^}]+))?\}/i);
        if (match) {
          const name = match[3]?.trim() || match[1].trim();
          const source = match[2]?.trim() || '';
          const ref = `${match[1].trim()}|${source.toUpperCase()}`;
          return `<span class="prof-chip dnd5-link dnd5-link-item" data-dnd5-tag="item" data-prof-type="${escapeHtml(
            type
          )}" data-dnd5-ref="${escapeHtml(ref)}">${escapeHtml(name)}</span>`;
        }
        const plain = strip5eTags(clean);
        const attrs = [];
        if (type === 'language') {
          attrs.push(`data-lang-index="${idx}"`);
          attrs.push(`data-lang-id="${escapeHtml(plain)}"`);
          if (/any/i.test(plain)) {
            attrs.push('data-lang-any="1"');
            attrs.push('class="prof-chip prof-chip--choose"');
            return `<span ${attrs.join(' ')}>${escapeHtml(plain)}</span>`;
          }
        }
        const sourceMatch = plain.match(/^(Raza|Trasfondo)\s*:\s*(.+)$/i);
        const classes = [type === 'language' ? 'prof-chip' : 'prof-chip prof-chip--plain'];
        let label = plain;
        if (sourceMatch) {
          const kind = sourceMatch[1].toLowerCase();
          label = sourceMatch[2];
          classes.push(kind === 'raza' ? 'prof-chip--source-race' : 'prof-chip--source-bg');
        }
        const extra = attrs.length ? ` ${attrs.join(' ')}` : '';
        return `<span class="${classes.join(' ')}"${extra}>${escapeHtml(label)}</span>`;
      })
      .join(' ');
    target.innerHTML = html;
  }

  let languageOptionsCache = null;
  function loadLanguageOptions() {
    if (languageOptionsCache) return Promise.resolve(languageOptionsCache);

    const selectOptions = Array.from(document.querySelectorAll('#profs-languages-select option'))
      .filter((opt) => opt.value)
      .map((opt) => ({ id: opt.value, name: opt.textContent || opt.value }));
    if (selectOptions.length) {
      languageOptionsCache = selectOptions;
      return Promise.resolve(languageOptionsCache);
    }

    const candidates = [];
    if (STATIC_DATA?.languages) candidates.push(STATIC_DATA.languages);
    if (STATIC_DATA?.languages && STATIC_DATA.languages.endsWith('-es.json')) {
      candidates.push(STATIC_DATA.languages.replace('-es.json', '.json'));
    }
    const fallbackList = [
      { id: 'common', name: 'Común' },
      { id: 'dwarvish', name: 'Enano' },
      { id: 'elvish', name: 'Élfico' },
      { id: 'giant', name: 'Gigante' },
      { id: 'gnomish', name: 'Gnomo' },
      { id: 'goblin', name: 'Goblin' },
      { id: 'halfling', name: 'Mediano' },
      { id: 'orc', name: 'Orco' },
      { id: 'abyssal', name: 'Abisal' },
      { id: 'celestial', name: 'Celestial' },
      { id: 'draconic', name: 'Dracónico' },
      { id: 'deep-speech', name: 'Habla Profunda' },
      { id: 'infernal', name: 'Infernal' },
      { id: 'primordial', name: 'Primordial' },
      { id: 'sylvan', name: 'Silvano' },
      { id: 'undercommon', name: 'Inframundo' },
    ];

    const tryFetch = (index = 0) => {
      if (index >= candidates.length) {
        languageOptionsCache = fallbackList;
        return Promise.resolve(languageOptionsCache);
      }
      const url = candidates[index];
      return fetch(url)
        .then((res) => (res.ok ? res.json() : Promise.reject()))
        .then((json) => {
          const list = Array.isArray(json?.languages) ? json.languages : [];
          languageOptionsCache = list.map((item) => {
            const name =
              typeof item.name === 'object' ? item.name.es || item.name.en || item.id : item.name || item.id || '';
            return { id: item.id || name, name };
          });
          return languageOptionsCache;
        })
        .catch(() => tryFetch(index + 1));
    };

    return tryFetch();
  }

  function buildFallbackDerived(context) {
    const pbHidden = getNumberFromInput('cs_proeficiencia');
    const level = Math.max(1, context.level || 1);
    const pb = Number.isFinite(pbHidden) && pbHidden > 0 ? pbHidden : Math.max(2, 2 + Math.floor((level - 1) / 4));
    return {
      abilityMods: context.abilityMods || {},
      proficiencyBonus: pb,
    };
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
      armor: normalizeArmorData(COMBAT_CONFIG.armor),
    };
  }

  function collectCombatContextOnly() {
    const ctx = collectCharacterContext();
    return ctx;
  }

  function computeArmorClass(armor, dexMod) {
    if (!armor) return null;
    const type = (armor.type || '').toString().toLowerCase();
    const base = typeof armor.ac === 'number' ? armor.ac : parseInt(armor.ac || '0', 10);
    if (!base) return null;

    let dexBonus = dexMod;
    if (type.includes('ma')) {
      dexBonus = Math.min(dexMod, 2);
    } else if (type.includes('ha')) {
      dexBonus = 0;
    }

    return base + dexBonus;
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
    const armorAc = computeArmorClass(context.armor, context.abilityMods.dex);
    if (armorAc) {
      derived.ac = armorAc;
    }

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

    updateCombatBasicsDisplay(derived);
    updateArmorTotals(derived);

    updateSaveDisplays(derived);
    updateSkillDisplays(derived);

    renderProficiencyLinks(derived.weaponText, 'display_cs_armas', 'weapon');
    renderProficiencyLinks(derived.armorText, 'display_cs_armaduras', 'armor');
    renderProficiencyLinks(derived.toolText, 'display_cs_herramientas', 'tool');
    lastDerivedTools = derived.toolText || [];
    renderProficiencyLinks(derived.languageText, 'display_cs_idiomas', 'language');

    recomputeSkillsAndSaves();
  }

  function initLanguageChoiceModal() {
    const container = document.getElementById('display_cs_idiomas');
    const hidden = document.getElementById('prof_languages');
    if (!container || !hidden) return;

    let modal = document.getElementById('lang-choice-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'lang-choice-modal';
      modal.className = 'modal-overlay';
      modal.style.display = 'none';
      modal.innerHTML = `
        <div class="modal-contenido">
          <span class="close-lang-popup" role="button" aria-label="Cerrar">×</span>
          <h3>Selecciona idioma</h3>
          <div class="lang-choice-body">
            <select id="lang-choice-select" class="basics-modal-input"></select>
            <p class="lang-choice-empty" style="display:none; margin-top:8px;">No hay idiomas disponibles.</p>
          </div>
          <div class="sheet-modal-actions">
            <button type="button" id="lang-choice-apply" class="btn-primary">Elegir</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const select = modal.querySelector('#lang-choice-select');
    const emptyMsg = modal.querySelector('.lang-choice-empty');
    const closeBtn = modal.querySelector('.close-lang-popup');
    const applyBtn = modal.querySelector('#lang-choice-apply');
    let options = [];
    let currentChip = null;

    function fillSelect(current = '') {
      if (!select) return;
      select.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Elige un idioma…';
      select.appendChild(placeholder);
      options.forEach((opt) => {
        const option = document.createElement('option');
        option.value = opt.id;
        option.textContent = opt.name;
        if (opt.id === current) option.selected = true;
        select.appendChild(option);
      });
      if (emptyMsg) emptyMsg.style.display = options.length ? 'none' : 'block';
      if (select) select.style.display = options.length ? 'block' : 'none';
      if (applyBtn) applyBtn.disabled = !options.length;
    }

    function closeModal() {
      modal.style.display = 'none';
      currentChip = null;
    }
    closeBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) closeModal();
    });

    applyBtn?.addEventListener('click', () => {
      if (!currentChip || !select) return;
      const value = select.value;
      if (!value) return;
      const option = options.find((o) => o.id === value);
      const label = option?.name || value;

      const idx = parseInt(currentChip.dataset.langIndex || '-1', 10);
      const ids = parseIds(hidden.value);
      if (idx >= 0 && idx < ids.length) {
        ids[idx] = value;
      } else {
        ids.push(value);
      }
      hidden.value = ids.join(',');

      // Re-render chips para reflejar el cambio y quitar el estado "any"
      const newList = ids.map((id) => {
        const matchOpt = options.find((o) => o.id === id);
        return matchOpt ? matchOpt.name : id;
      });
      renderProficiencyLinks(newList, 'display_cs_idiomas', 'language');

      scheduleCharacterRecalc();
      closeModal();
    });

    container.addEventListener('click', (ev) => {
      const chip = ev.target.closest('.prof-chip[data-lang-any]');
      if (!chip) return;
      currentChip = chip;
      loadLanguageOptions()
        .then((list) => {
          options = list || [];
          fillSelect(chip.dataset.langId || '');
          modal.style.display = 'flex';
        })
        .catch(() => {
          options = [];
          fillSelect('');
          modal.style.display = 'flex';
        });
    });
  }

  function updateBasicStat(fieldId, value, formatter) {
    if (hasManualOverride(fieldId)) {
      const manualDisplayId = fieldId === 'cs_hp_temp' ? 'display_cs_hp_temp' : `display_${fieldId}`;
      const manualDisplay = document.getElementById(manualDisplayId);
      if (manualDisplay) {
        const hidden = document.getElementById(fieldId);
        const current = hidden?.value || manualDisplay.textContent || '';
        manualDisplay.textContent =
          typeof formatter === 'function' ? formatter(parseInt(current, 10) || 0) : current;
      }
      return;
    }
    const hidden = document.getElementById(fieldId);
    if (hidden) hidden.value = typeof value === 'number' ? value : (value || '');
    const displayId = fieldId === 'cs_hp_temp' ? 'display_cs_hp_temp' : `display_${fieldId}`;
    const display = document.getElementById(displayId);
    if (!display) return;
    const formatted = typeof formatter === 'function' ? formatter(value) : value;
    display.textContent = formatted ?? '';
  }

  function updateCombatBasicsDisplay(derived) {
    const fallback = (id) => {
      const el = document.getElementById(id);
      return el ? el.value || el.textContent || '' : '';
    };
    const initSource =
      typeof derived?.initiative !== 'undefined'
        ? derived.initiative
        : parseInt(fallback('cs_iniciativa') || '0', 10);
    const initValue = Number.isFinite(initSource) ? initSource : 0;
    const acBase = Number.isFinite(derived?.ac) ? derived.ac : parseInt(fallback('cs_ac') || '0', 10) || 0;
    const acTotal = acBase + (combatModuleState.acExtra || 0) + (combatModuleState.shieldExtra || 0);
    const speedVal = Number.isFinite(derived?.speed) ? derived.speed : fallback('cs_velocidad') || '—';
    const hpVal = Number.isFinite(derived?.hp) ? derived.hp : fallback('cs_hp') || '—';
    const map = [
      { id: 'combat_display_cs_iniciativa', value: formatMod(initValue) },
      { id: 'combat_display_cs_ac', value: acTotal || acBase || '—' },
      { id: 'combat_display_cs_velocidad', value: speedVal ?? '—' },
      { id: 'combat_display_cs_hp', value: hpVal ?? '—' },
    ];
    map.forEach(({ id, value }) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    });
  }

  function updateArmorTotals(derived) {
    const base = Number.isFinite(derived?.ac) ? derived.ac : 0;
    const acExtra = combatModuleState.acExtra || 0;
    const shieldExtra = combatModuleState.shieldExtra || 0;
    const acTotal = base + acExtra + shieldExtra;
    const armorBaseEl = document.getElementById('combat-armor-base');
    const armorTotalEl = document.getElementById('combat-armor-total');
    const shieldBaseEl = document.getElementById('combat-shield-base');
    const shieldTotalEl = document.getElementById('combat-shield-total');
    if (armorBaseEl) armorBaseEl.textContent = base || '—';
    if (armorTotalEl) armorTotalEl.textContent = acTotal || base || '—';
    if (shieldBaseEl) shieldBaseEl.textContent = '0';
    if (shieldTotalEl) shieldTotalEl.textContent = shieldExtra || 0;
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
    applyManualSkillSelections();

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

  function applyManualSkillSelections() {
    const autoSet = characterAutomationState.autoSkills || new Set();
    const expertiseSet = characterAutomationState.expertise || new Set();
    manualSkillSelections.forEach((fieldId) => {
      if (autoSet.has(fieldId)) {
        manualSkillSelections.delete(fieldId);
        return;
      }
      setSkillProficiency(fieldId, true, 'Manual', expertiseSet.has(fieldId), true);
    });
    persistManualSkills();
  }

  function setSkillProficiency(fieldId, isActive, source, hasExpertise, isManual = false) {
    const profInput = document.getElementById(`prof_${fieldId}`);
    if (profInput) {
      profInput.value = isActive ? '1' : '0';
    }

    const icon = document.querySelector(`.skill-icon[data-skill-icon="${fieldId}"]`);
    if (icon) {
      icon.classList.remove('skill-icon--prof', 'skill-icon--expert', 'skill-icon--manual');
      if (hasExpertise) {
        icon.classList.add('skill-icon--expert');
      } else if (isActive) {
        icon.classList.add(isManual ? 'skill-icon--manual' : 'skill-icon--prof');
      }
    }

    const label = document.querySelector(`.skill-source-label[data-skill-label="${fieldId}"]`);
    if (label) {
      if (isManual) {
        label.textContent = 'M';
      } else if (isActive && source) {
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
        target.push(entry);
      } else if (entry.type === 'fixed' && Array.isArray(entry.items)) {
        entry.items.forEach((item) => target.push(item));
      } else if (entry.type === 'choice' && Array.isArray(entry.options)) {
        target.push(`Elige ${entry.count || 1}: ${entry.options.map(strip5eTags).join(', ')}`);
      } else if (typeof entry === 'object') {
        const keys = Object.keys(entry)
          .filter((key) => entry[key] && key !== 'type')
          .map(strip5eTags);
        if (keys.length) {
          target.push(keys.join(', '));
        }
      } else if (Array.isArray(entry)) {
        entry.forEach((val) => target.push(val));
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
    const replaced = text.replace(/\{@item\s+([^|}]+)\|([^|}]+)(?:\|([^}]+))?\}/gi, (_, id, source, display) => {
      return (display && display.trim()) || id.trim();
    });
    return replaced
      .replace(/\{@([^}|]+)\|([^}|]+)(?:\|([^}]+))?\}/gi, (_, id, source, display) => {
        return (display && display.trim()) || id.trim();
      })
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
    const manualSet = manualSkillSelections || new Set();
    const available = Object.keys(SKILL_LABELS).filter((fieldId) => {
      if (set.has(fieldId)) return false;
      if (!autoSet.size && !manualSet.size) return true;
      return autoSet.has(fieldId) || manualSet.has(fieldId);
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
    const parts = body.split('|');
    const label = parts[0] || body;

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
        ? `<strong>${escapeHtml(label)}</strong>`
        : `<em>${escapeHtml(label)}</em>`;
    }

    if (strongTags.has(tag)) {
      return `<strong>${escapeHtml(label)}</strong>`;
    }

    if (chipTags.has(tag)) {
      const modifier = ['spell', 'action', 'skill'].includes(tag) ? ` dnd5-tag-${tag}` : '';
      const link = resolveTagLink(tag, parts, label);
      const content = escapeHtml(link?.label || label);
      if (link && link.href) {
        const attrs = [
          `href="${escapeHtml(link.href)}"`,
          `class="dnd5-tag${modifier} dnd5-link dnd5-link-${tag}"`,
          `data-dnd5-tag="${escapeHtml(tag)}"`,
          `data-dnd5-ref="${escapeHtml(parts.join('|'))}"`,
          link.target ? `target="${escapeHtml(link.target)}"` : '',
          link.rel ? `rel="${escapeHtml(link.rel)}"` : '',
        ]
          .filter(Boolean)
          .join(' ');
        return `<a ${attrs}>${content}</a>`;
      }
      return `<span class="dnd5-tag${modifier}">${content}</span>`;
    }

    const link = resolveTagLink(tag, parts, label);
    if (link && link.href) {
      const content = escapeHtml(link.label || label);
      const attrs = [
        `href="${escapeHtml(link.href)}"`,
        `class="dnd5-link dnd5-link-${tag}"`,
        `data-dnd5-tag="${escapeHtml(tag)}"`,
        `data-dnd5-ref="${escapeHtml(parts.join('|'))}"`,
        link.target ? `target="${escapeHtml(link.target)}"` : '',
        link.rel ? `rel="${escapeHtml(link.rel)}"` : '',
      ]
        .filter(Boolean)
        .join(' ');
      return `<a ${attrs}>${content}</a>`;
    }

    return escapeHtml(label);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;');
  }

  function slugify(value) {
    return String(value || '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function resolveTagLink(tag, parts, label) {
    const baseMap = typeof window !== 'undefined' ? window.DND5_LINK_BASES || {} : {};
    if (baseMap[tag]) {
      const slug = slugify(parts[0]);
      if (slug) {
        const base = String(baseMap[tag]).replace(/\/$/, '');
        return { href: `${base}/${slug}`, label };
      }
    }

    const slug = slugify(parts[0]);
    if (slug) {
      return { href: `#${tag}-${slug}`, label };
    }

    return null;
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
