(function () {
  document.addEventListener('DOMContentLoaded', () => {
    initTempHpControls();
    initBasicsModal();
    initProficiencyModal();
    initStatsOverlay();
    refreshAbilityDisplays();
    initSkillSaveSystem();
    initStandaloneClassSelects();
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

  let recomputeSkillsAndSaves = () => {};

  function qs(selector, scope = document) {
    return scope.querySelector(selector);
  }

  function qsa(selector, scope = document) {
    return Array.from(scope.querySelectorAll(selector));
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

  function parseIds(value) {
    return (value || '')
      .split(',')
      .map((v) => v.trim())
      .filter(Boolean);
  }

  function serializeIds(value) {
    return value.join(',');
  }

  function getNumberFromInput(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const raw = el.value ?? el.textContent;
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

  function initBasicsModal() {
    const overlay = qs('#basics-overlay');
    const openBtn = qs('#btn-basicos-modal');
    const applyBtn = qs('#basics-apply');
    const closeBtn = qs('.close-basics-popup');

    if (!overlay || !openBtn || !applyBtn || !closeBtn) return;

    const hiddenBasics = {
      cs_iniciativa: document.getElementById('cs_iniciativa'),
      cs_ac: document.getElementById('cs_ac'),
      cs_velocidad: document.getElementById('cs_velocidad'),
      cs_hp: document.getElementById('cs_hp'),
      nivel: document.getElementById('nivel'),
      clase: document.getElementById('clase'),
      subclase: document.getElementById('subclase'),
      raza: document.getElementById('raza'),
    };

    const displayBasics = {
      cs_iniciativa: document.getElementById('display_cs_iniciativa'),
      cs_ac: document.getElementById('display_cs_ac'),
      cs_velocidad: document.getElementById('display_cs_velocidad'),
      cs_hp: document.getElementById('display_cs_hp'),
      nivel: document.getElementById('display_nivel'),
      clase: document.getElementById('display_clase'),
      subclase: document.getElementById('display_subclase'),
      raza: document.getElementById('display_raza'),
    };

    const modalInputs = qsa('.basics-modal-input[data-basic]', overlay);
    const classSelect = document.getElementById('modal-clase');
    const subclassSelect = document.getElementById('modal-subclase');
    const raceSelect = document.getElementById('modal-raza');

    function populateModal() {
      modalInputs.forEach((input) => {
        const key = input.dataset.basic;
        const hidden = hiddenBasics[key];
        if (hidden) {
          input.value = hidden.value || '';
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
    }

    function hideOverlay() {
      overlay.style.display = 'none';
    }

    openBtn.addEventListener('click', () => {
      populateModal();
      overlay.style.display = 'flex';
    });

    closeBtn.addEventListener('click', hideOverlay);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) hideOverlay();
    });

    applyBtn.addEventListener('click', () => {
      modalInputs.forEach((input) => {
        const key = input.dataset.basic;
        const hidden = hiddenBasics[key];
        const display = displayBasics[key];
        if (!hidden || !display) return;
        hidden.value = input.value;
        display.textContent = input.value || '';
      });

      hideOverlay();
      refreshAbilityDisplays();
    });

    function syncSelect(select, hiddenField, displayField) {
      if (!select || !hiddenField || !displayField) return;
      const option = select.options[select.selectedIndex];
      hiddenField.value = select.value || '';
      displayField.textContent = option ? option.textContent : '';
    }

    if (classSelect) {
      classSelect.addEventListener('change', () => {
        syncSelect(classSelect, hiddenBasics.clase, displayBasics.clase);
        loadSubclasses(classSelect.value, hiddenBasics.subclase?.value || '');
      });
    }

    if (subclassSelect) {
      subclassSelect.addEventListener('change', () => {
        syncSelect(subclassSelect, hiddenBasics.subclase, displayBasics.subclase);
      });
    }

    if (raceSelect) {
      raceSelect.addEventListener('change', () => {
        syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza);
      });
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
          syncSelect(classSelect, hiddenBasics.clase, displayBasics.clase);
          const currentClass = hiddenBasics.clase?.value || '';
          loadSubclasses(currentClass, hiddenBasics.subclase?.value || '');
        })
        .catch(() => {
          populateSelect(classSelect, 'Error al cargar clases', []);
        });
    }

    function loadSubclasses(classId, preselect) {
      if (!subclassSelect || !classId || typeof window.DND5_API === 'undefined') {
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
          syncSelect(subclassSelect, hiddenBasics.subclase, displayBasics.subclase);
        })
        .catch(() => {
          populateSelect(subclassSelect, 'Error al cargar subclases', []);
          subclassSelect.disabled = true;
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
          syncSelect(raceSelect, hiddenBasics.raza, displayBasics.raza);
        })
        .catch(() => {
          populateSelect(raceSelect, 'Error al cargar razas', []);
          raceSelect.disabled = true;
        });
    }

    loadClasses();
    loadRaces();
  }

  function initProficiencyModal() {
    const overlay = qs('#profs-overlay');
    const openBtn = qs('#btn-profs-modal');
    const applyBtn = qs('#profs-apply');
    const closeBtn = qs('.close-profs-popup');

    if (!overlay || !openBtn || !applyBtn || !closeBtn || typeof window.DND5_API === 'undefined') {
      return;
    }

    const config = Object.entries(PROF_TYPES).reduce((acc, [type, ids]) => {
      acc[type] = {
        hidden: document.getElementById(ids.hidden),
        display: document.getElementById(ids.display),
        select: document.getElementById(ids.select),
        addBtn: document.getElementById(ids.add),
        list: document.getElementById(ids.list),
        values: [],
        options: [],
      };
      return acc;
    }, {});

    let profDataPromise = null;

    function fetchProficiencies() {
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
        const names = ids.map((id) => findName(info.options, id));
        info.display.textContent = names.join(', ');
      });
    }

    Object.entries(config).forEach(([type, info]) => {
      if (info.hidden) {
        info.values = parseIds(info.hidden.value);
      }
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

    openBtn.addEventListener('click', () => {
      ensureLookupCache().then((data) => {
        Object.entries(config).forEach(([type, info]) => {
          const label = PROF_LABELS[type] || type;
          populateSelect(info.select, `Selecciona ${label}…`, info.options, '');
          info.values = parseIds(info.hidden?.value || '');
          renderList(type);
        });
        overlay.style.display = 'flex';
      });
    });

    closeBtn.addEventListener('click', () => {
      overlay.style.display = 'none';
    });

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        overlay.style.display = 'none';
      }
    });

    applyBtn.addEventListener('click', () => {
      Object.values(config).forEach((info) => {
        if (!info.hidden) return;
        info.hidden.value = serializeIds(info.values);
      });
      refreshSheetDisplays();
      overlay.style.display = 'none';
    });

    ensureLookupCache();
  }

  function initStatsOverlay() {
    const trigger = document.getElementById('btn-modificar-hoja');
    const overlay = document.getElementById('stats-overlay');
    const closeBtn = qs('.close-stats-popup');
    const applyBtn = document.getElementById('stats-apply');

    if (!trigger || !overlay || !applyBtn) return;

    function hideOverlay() {
      overlay.style.display = 'none';
    }

    trigger.addEventListener('click', () => {
      const inputs = qsa('.stats-modal-input[data-stat]', overlay);
      inputs.forEach((input) => {
        const stat = input.dataset.stat;
        const hidden = document.getElementById(stat);
        input.value = hidden?.value || '';
      });
      overlay.style.display = 'flex';
    });

    closeBtn?.addEventListener('click', hideOverlay);

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) hideOverlay();
    });

    applyBtn.addEventListener('click', () => {
      const inputs = qsa('.stats-modal-input[data-stat]', overlay);
      inputs.forEach((input) => {
        const stat = input.dataset.stat;
        const hidden = document.getElementById(stat);
        if (!hidden) return;
        hidden.value = input.value;

        const modId = stat === 'cs_proeficiencia' ? null : `${stat}_mod`;
        if (modId) {
          const modValue = Math.floor((parseInt(input.value || '0', 10) - 10) / 2);
          const modHidden = document.getElementById(modId);
          const modDisplay = document.getElementById(`display_${modId}`);
          if (modHidden) modHidden.value = modValue;
          if (modDisplay) {
            modDisplay.textContent = formatMod(modValue);
          }
        }
      });

      refreshAbilityDisplays();
      recomputeSkillsAndSaves();
      overlay.style.display = 'none';
    });
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
      const expertiseBtn = qs(`.skill-expertise-toggle[data-skill="${skillId}"]`);
      const isProf = profInput?.value === '1';
      const hasExpertise = expertiseBtn?.dataset.expertise === '1';

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

    qsa('.skill-prof-toggle[data-skill]').forEach((btn) => {
      const skillId = btn.dataset.skill;
      const profInput = document.getElementById(`prof_${skillId}`);
      const circle = btn.querySelector('.skill-prof-circle');

      if (profInput?.value === '1' && circle) {
        circle.classList.add('skill-prof-active');
      }

      btn.addEventListener('click', () => {
        if (!profInput) return;
        const newValue = profInput.value === '1' ? '0' : '1';
        profInput.value = newValue;
        circle?.classList.toggle('skill-prof-active', newValue === '1');

        const expertiseBtn = qs(`.skill-expertise-toggle[data-skill="${skillId}"]`);
        if (expertiseBtn) {
          if (newValue !== '1') {
            expertiseBtn.style.display = 'none';
            expertiseBtn.dataset.expertise = '0';
            expertiseBtn.querySelector('.skill-prof-circle')?.classList.remove('skill-prof-active');
          } else {
            expertiseBtn.style.display = 'inline-block';
          }
        }

        updateSkill(skillId);
      });
    });

    qsa('.skill-expertise-toggle').forEach((btn) => {
      const skillId = btn.dataset.skill;
      const profInput = document.getElementById(`prof_${skillId}`);
      const circle = btn.querySelector('.skill-prof-circle');
      if (!profInput || profInput.value !== '1') {
        btn.style.display = 'none';
      }

      btn.dataset.expertise = btn.dataset.expertise === '1' ? '1' : '0';
      circle?.classList.toggle('skill-prof-active', btn.dataset.expertise === '1');

      btn.addEventListener('click', () => {
        if (profInput?.value !== '1') return;
        const newValue = btn.dataset.expertise === '1' ? '0' : '1';
        btn.dataset.expertise = newValue;
        circle?.classList.toggle('skill-prof-active', newValue === '1');
        updateSkill(skillId);
      });
    });

    qsa('.save-prof-toggle').forEach((btn) => {
      const saveId = btn.dataset.save;
      const profInput = document.getElementById(`prof_${saveId}`);
      const circle = btn.querySelector('.skill-prof-circle');

      if (profInput?.value === '1') {
        circle?.classList.add('skill-prof-active');
      }

      btn.addEventListener('click', () => {
        if (!profInput) return;
        const newValue = profInput.value === '1' ? '0' : '1';
        profInput.value = newValue;
        circle?.classList.toggle('skill-prof-active', newValue === '1');
        updateSave(saveId);
      });
    });

    recomputeSkillsAndSaves = () => {
      Object.keys(skillAbilityMap).forEach(updateSkill);
      Object.keys(saveAbilityMap).forEach(updateSave);
    };

    recomputeSkillsAndSaves();
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
    });
  }
})();
