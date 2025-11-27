(function () {
  document.addEventListener('DOMContentLoaded', initWizard);

  const SKILL_OPTIONS = [
    { id: 'athletics', label: 'Atletismo' },
    { id: 'acrobatics', label: 'Acrobacias' },
    { id: 'sleight of hand', label: 'Juego de Manos' },
    { id: 'stealth', label: 'Sigilo' },
    { id: 'arcana', label: 'Arcanos' },
    { id: 'history', label: 'Historia' },
    { id: 'investigation', label: 'Investigación' },
    { id: 'nature', label: 'Naturaleza' },
    { id: 'religion', label: 'Religión' },
    { id: 'animal handling', label: 'Trato con Animales' },
    { id: 'insight', label: 'Perspicacia' },
    { id: 'medicine', label: 'Medicina' },
    { id: 'perception', label: 'Percepción' },
    { id: 'survival', label: 'Supervivencia' },
    { id: 'deception', label: 'Engaño' },
    { id: 'intimidation', label: 'Intimidación' },
    { id: 'performance', label: 'Interpretación' },
    { id: 'persuasion', label: 'Persuasión' },
  ];
  const SKILL_LABELS_MAP = SKILL_OPTIONS.reduce((acc, curr) => {
    acc[curr.id] = curr.label;
    return acc;
  }, {});

  function initWizard() {
    const root = document.getElementById('personaje-wizard-root');
    if (!root || typeof window.PERSONAJE_WIZARD_API === 'undefined') return;

    const state = {
      data: {
        campaign_id: readCampaignIdFromUrl(),
        image_id: null,
      },
      loading: false,
      error: '',
      success: null,
    };

    buildUI(root, state);
    initImagePicker(state);
  }

  function readCampaignIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const value = parseInt(params.get('campaign_id'), 10);
    return Number.isFinite(value) ? value : 0;
  }

  function buildUI(root, state) {
    root.innerHTML = `
      <div class="pw-step pw-step--simple">
        <h3>Crear personaje</h3>
        <p class="pw-helper">Sólo necesitamos el nombre y la imagen. El resto se rellena en la Hoja de Personaje.</p>
        <label>Nombre <input type="text" id="pw-name" placeholder="Nombre del personaje"></label>
        <div class="pw-field pw-field--full">
          <label for="pw-image-id">Imagen / Avatar</label>
          <div class="pw-image-picker">
            <button type="button" class="pw-btn pw-btn-secondary" id="pw-image-button">Seleccionar imagen</button>
            <input type="hidden" id="pw-image-id" value="${state.data.image_id || ''}">
            <div class="pw-image-preview" id="pw-image-preview"></div>
          </div>
        </div>
        <div class="pw-nav">
          <button type="button" class="pw-btn pw-btn-success" data-submit>Crear y abrir la hoja</button>
        </div>
      </div>
      <div class="pw-feedback">
        <p class="pw-error" aria-live="assertive"></p>
        <div class="pw-success" aria-live="polite"></div>
      </div>
    `;
    const submitBtn = root.querySelector('[data-submit]');
    submitBtn.addEventListener('click', () => handleSubmit(state, root));
  }

  function renderAbilityInput(label, key, state) {
    const val = state.data.ability_scores[key] || '';
    return `
      <label class="pw-ability">
        <span>${label}</span>
        <input type="number" data-ability="${key}" min="3" max="20" value="${val}">
        <small data-ability-mod="${key}">mod 0</small>
      </label>
    `;
  }

  function renderSaveCheckbox(abbr) {
    const map = { str: 'FUE', dex: 'DES', con: 'CON', int: 'INT', wis: 'SAB', cha: 'CAR' };
    return `
      <label class="pw-checkbox">
        <input type="checkbox" data-save="${abbr}"> ${map[abbr]}
      </label>
    `;
  }

  function renderSkillCheckbox(skill) {
    return `
      <label class="pw-checkbox">
        <input type="checkbox" data-skill="${skill.id}"> ${skill.label}
      </label>
    `;
  }

  function renderProfSelect(title, key) {
    return `
      <div class="pw-prof">
        <label>${title}
          <select multiple data-prof="${key}"></select>
        </label>
      </div>
    `;
  }

  function fetchClasses(state) {
    const api = window.PERSONAJE_WIZARD_API;
    const fd = new FormData();
    fd.append('action', api.endpoints.classes);
    return fetch(api.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        state.options.classes = data?.data?.classes || [];
        fillSelect('pw-class', state.options.classes, 'id', 'name');
      })
      .catch(() => fillSelect('pw-class', [], 'id', 'name'));
  }

  function fetchRaces(state) {
    const api = window.PERSONAJE_WIZARD_API;
    const fd = new FormData();
    fd.append('action', api.endpoints.races);
    return fetch(api.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        state.options.races = data?.data?.races || [];
        fillSelect('pw-race', state.options.races, 'id', 'name');
      })
      .catch(() => {});
  }

  function fetchBackgrounds(state) {
    const api = window.PERSONAJE_WIZARD_API;
    const fd = new FormData();
    fd.append('action', api.endpoints.backgrounds);
    return fetch(api.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        state.options.backgrounds = data?.data?.backgrounds || [];
        fillSelect('pw-background', state.options.backgrounds, 'id', 'name');
      })
      .catch(() => {});
  }

  function fetchProficiencies(state) {
    const api = window.PERSONAJE_WIZARD_API;
    const fd = new FormData();
    fd.append('action', api.endpoints.proficiencies);
    return fetch(api.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        state.options.proficiencies = data?.data || { weapons: [], armors: [], tools: [], languages: [] };
        populateProfSelects(state);
      })
      .catch(() => {});
  }

  function fetchStaticData(state) {
    const uris = (window.PERSONAJE_WIZARD_API && window.PERSONAJE_WIZARD_API.static_data) || {};
    const promises = [];
    if (uris.classDetails) {
      promises.push(
        fetch(uris.classDetails)
          .then((r) => r.json())
          .then((data) => {
            state.options.classDetails = data?.classes || {};
          })
          .catch(() => {})
      );
    }
    if (uris.races) {
      promises.push(
        fetch(uris.races)
          .then((r) => r.json())
          .then((data) => {
            state.options.raceData = data?.races || [];
          })
          .catch(() => {})
      );
    }
    if (uris.backgrounds) {
      promises.push(
        fetch(uris.backgrounds)
          .then((r) => r.json())
          .then((data) => {
            state.options.backgroundData = data?.backgrounds || [];
          })
          .catch(() => {})
      );
    }
    return Promise.all(promises);
  }

  function fetchLanguages(state) {
    const uri = (window.PERSONAJE_WIZARD_API && window.PERSONAJE_WIZARD_API.static_data && window.PERSONAJE_WIZARD_API.static_data.languages) || '';
    if (!uri) return Promise.resolve();
    return fetch(uri)
      .then((r) => r.json())
      .then((data) => {
        const list = data.languages || data.language || [];
        state.options.languages = list.map((item) => {
          const id = item.id || '';
          const name = typeof item.name === 'object' ? (item.name.es || item.name.en || id) : (item.name || id);
          return { id, name };
        });
      })
      .catch(() => {});
  }

  function normalizeSkillKey(key) {
    return key ? key.toLowerCase() : '';
  }

  function normalizeLangKey(key) {
    return key ? key.toLowerCase() : '';
  }

  function computeSkillUnion(state) {
    const all = [
      ...state.data.skillsBySource.class,
      ...state.data.skillsBySource.race,
      ...state.data.skillsBySource.background,
      ...state.data.skillsBySource.feats,
    ];
    return Array.from(new Set(all.map(normalizeSkillKey))).filter(Boolean);
  }

  function computeLanguages(state) {
    const all = [
      ...state.data.languagesBySource.race,
      ...state.data.languagesBySource.background,
      ...state.data.languagesBySource.feats,
    ];
    state.data.languages = Array.from(new Set(all.map(normalizeLangKey))).filter(Boolean);
  }

  function renderClassSkills(state) {
    const classId = document.getElementById('pw-class')?.value || '';
    const target = document.getElementById('pw-class-skills');
    if (!target) return;
    const details = (state.options.classDetails && state.options.classDetails[classId]) || null;
    if (!details || !details.startingProficiencies || !Array.isArray(details.startingProficiencies.skills)) {
      target.innerHTML = '<p>No hay habilidades configurables para esta clase.</p>';
      state.data.skillsBySource.class = [];
      updateSkillSummary(state);
      return;
    }
    const groups = details.startingProficiencies.skills;
    const otherSkills = [
      ...state.data.skillsBySource.race,
      ...state.data.skillsBySource.background,
      ...state.data.skillsBySource.feats,
    ].map(normalizeSkillKey);
    let html = '<h4>Habilidades de la clase</h4>';
    groups.forEach((group, idx) => {
      const count = group.count || group.choose || group.chooseCount || group.choose?.count || 0;
      const options = group.options || (group.choose && group.choose.from) || [];
      html += `<div class="pw-skill-choice"><p>Elige ${count}:</p><div class="pw-skill-options" data-class-skill-group="${idx}" data-count="${count}">`;
      options.forEach((opt) => {
        const key = normalizeSkillKey(opt);
        const label = SKILL_LABELS_MAP[key] || opt;
        const disabled = otherSkills.includes(key);
        html += `
          <label class="pw-checkbox">
            <input type="checkbox" data-class-skill="${key}" ${disabled ? 'disabled' : ''}>
            ${label}${disabled ? ' (ya otorgada por otra fuente)' : ''}
          </label>`;
      });
      html += '</div></div>';
    });
    target.innerHTML = html;

    target.querySelectorAll('[data-class-skill]').forEach((cb) => {
      cb.addEventListener('change', () => {
        const selected = [];
        target.querySelectorAll('[data-class-skill]').forEach((c) => {
          if (c.checked && !c.disabled) selected.push(normalizeSkillKey(c.dataset.classSkill));
        });
        state.data.skillsBySource.class = selected.slice();
        enforceGroupLimits(target);
        updateSkillSummary(state);
      });
    });

    enforceGroupLimits(target);
    updateSkillSummary(state);
  }

  function enforceGroupLimits(container) {
    container.querySelectorAll('[data-class-skill-group]').forEach((group) => {
      const max = parseInt(group.dataset.count || '0', 10);
      const checkboxes = Array.from(group.querySelectorAll('input[type="checkbox"]')).filter((c) => !c.disabled);
      const selected = checkboxes.filter((c) => c.checked);
      if (selected.length >= max) {
        checkboxes.forEach((cb) => {
          if (!cb.checked) cb.disabled = true;
        });
      } else {
        checkboxes.forEach((cb) => {
          cb.disabled = false;
        });
      }
    });
  }

  function renderRaceTraits(state) {
    const raceId = document.getElementById('pw-race')?.value || '';
    const traitsEl = document.getElementById('pw-race-traits');
    const skillsEl = document.getElementById('pw-race-skills');
    const race = (state.options.raceData || []).find((r) => r.id === raceId);
    if (!race) {
      if (traitsEl) traitsEl.innerHTML = '';
      if (skillsEl) skillsEl.innerHTML = '';
      state.data.skillsBySource.race = [];
      state.data.languagesBySource.race = [];
      state.data.raceLangChoices = [];
      updateSkillSummary(state);
      computeLanguages(state);
      return;
    }

    if (traitsEl) {
      traitsEl.innerHTML = `<h4>Rasgos raciales</h4><p>${race.traitTags ? race.traitTags.join(', ') : '—'}</p>`;
    }

    const fixedSkills = [];
    if (Array.isArray(race.skillProficiencies)) {
      race.skillProficiencies.forEach((obj) => {
        Object.keys(obj || {}).forEach((k) => {
          if (obj[k]) fixedSkills.push(normalizeSkillKey(k));
        });
      });
    }
    state.data.skillsBySource.race = fixedSkills;
    if (skillsEl) {
      const labels = fixedSkills.map((k) => SKILL_LABELS_MAP[k] || k);
      let html = `<h4>Habilidades de la raza</h4><p>${labels.length ? labels.join(', ') : 'Sin habilidades adicionales'}</p>`;
      const parsedSkills = parseSkillProficiencies(race.skillProficiencies || []);
      if (parsedSkills.groups.length) {
        parsedSkills.groups.forEach((group, idx) => {
          html += renderSkillChoiceBlock('race', idx, group, state);
        });
        state.data.raceSkillChoices = state.data.raceSkillChoices || [];
      }
      skillsEl.innerHTML = html;
      if (parsedSkills.groups.length) {
        bindSkillChoiceHandlers(skillsEl, 'race', parsedSkills.fixed, state);
      }
    }
    // Idiomas de raza
    const langInfo = parseLanguageProficiencies(race.languageProficiencies || []);
    state.data.languagesBySource.race = langInfo.fixed;
    state.data.raceLangChoices = [];
    if (langInfo.choice && langInfo.choice.count > 0 && race.languageProficiencies) {
      // add selectable languages
      const langBlock = renderLanguageChoiceBlock('race', langInfo.choice, state, 'race');
      if (langBlock && traitsEl) {
        traitsEl.innerHTML += langBlock;
        bindLanguageChoiceHandlers(traitsEl, 'race', langInfo.fixed, state, 'race');
      }
    }
    computeLanguages(state);
    updateSkillSummary(state);
    renderClassSkills(state);
  }

  function renderBackgroundTraits(state) {
    const bgId = document.getElementById('pw-background')?.value || '';
    const bgSkills = document.getElementById('pw-bg-skills');
    const bgTools = document.getElementById('pw-bg-tools');
    const bgLangs = document.getElementById('pw-bg-languages');
    const bg = (state.options.backgroundData || []).find((b) => b.id === bgId);
    if (!bg) {
      state.data.skillsBySource.background = [];
      state.data.languagesBySource.background = [];
      state.data.bgSkillChoices = [];
      state.data.bgLangChoices = [];
      if (bgSkills) bgSkills.innerHTML = '';
      if (bgTools) bgTools.innerHTML = '';
      if (bgLangs) bgLangs.innerHTML = '';
      updateSkillSummary(state);
      computeLanguages(state);
      return;
    }

    const parsedSkills = parseSkillProficiencies(bg.skillProficiencies || []);
    state.data.skillsBySource.background = parsedSkills.fixed;
    state.data.bgSkillChoices = [];
    if (bgSkills) {
      const labels = parsedSkills.fixed.map((k) => SKILL_LABELS_MAP[k] || k);
      let html = `<h4>Habilidades del trasfondo</h4><p>${labels.length ? labels.join(', ') : '—'}</p>`;
      parsedSkills.groups.forEach((group, idx) => {
        html += renderSkillChoiceBlock('bg', idx, group, state);
      });
      bgSkills.innerHTML = html;
      bindSkillChoiceHandlers(bgSkills, 'bg', parsedSkills.fixed, state);
    }

    if (bgTools) {
      bgTools.innerHTML = `<h4>Herramientas</h4><p>${formatChoiceList(bg.toolProficiencies)}</p>`;
    }
    if (bgLangs) {
      const langInfo = parseLanguageProficiencies(bg.languageProficiencies || []);
      state.data.languagesBySource.background = langInfo.fixed;
      state.data.bgLangChoices = [];
      let html = `<h4>Idiomas</h4><p>${formatLanguages(langInfo.fixed, state)}</p>`;
      if (langInfo.choice && langInfo.choice.count > 0) {
        html += renderLanguageChoiceBlock('bg', langInfo.choice, state, 'background');
      }
      bgLangs.innerHTML = html;
      bindLanguageChoiceHandlers(bgLangs, 'bg', langInfo.fixed, state, 'background');
    }
    updateSkillSummary(state);
    computeLanguages(state);
    renderClassSkills(state);
  }

  function formatChoiceList(arr) {
    if (!Array.isArray(arr) || !arr.length) return '—';
    const parts = [];
    arr.forEach((entry) => {
      if (typeof entry === 'string') {
        parts.push(entry);
      } else if (typeof entry === 'object') {
        Object.keys(entry).forEach((k) => {
          if (entry[k] === true) parts.push(k);
          else if (typeof entry[k] === 'number') parts.push(`${entry[k]} ${k}`);
        });
      }
    });
    return parts.length ? parts.join(', ') : '—';
  }

  function parseSkillProficiencies(list) {
    const fixed = [];
    const groups = [];
    if (!Array.isArray(list)) {
      return { fixed, groups };
    }
    list.forEach((entry) => {
      if (typeof entry === 'string') {
        fixed.push(normalizeSkillKey(entry));
      } else if (entry && typeof entry === 'object') {
        if (entry.choose && entry.choose.from) {
          const count = entry.choose.count || entry.choose.number || entry.choose.num || 0;
          const options = entry.choose.from || [];
          groups.push({ count: parseInt(count, 10) || 0, options: options.map(normalizeSkillKey) });
        } else {
          Object.keys(entry).forEach((k) => {
            if (entry[k]) fixed.push(normalizeSkillKey(k));
          });
        }
      }
    });
    return { fixed, groups };
  }

  function parseLanguageProficiencies(list) {
    const fixed = [];
    let choice = null;
    if (!Array.isArray(list)) {
      return { fixed, choice };
    }
    list.forEach((entry) => {
      if (typeof entry === 'string') {
        fixed.push(normalizeLangKey(entry));
      } else if (entry && typeof entry === 'object') {
        if (entry.choose || entry.any || entry.anyStandard) {
          const count = entry.choose?.count || entry.any || entry.anyStandard || entry.choose || 0;
          const from = entry.choose?.from || 'any';
          choice = {
            count: parseInt(count, 10) || 0,
            from,
          };
        } else {
          Object.keys(entry).forEach((k) => {
            if (entry[k] === true) {
              fixed.push(normalizeLangKey(k));
            }
          });
        }
      }
    });
    return { fixed, choice };
  }

  function renderSkillChoiceBlock(prefix, idx, group, state) {
    const taken = new Set(computeSkillUnion(state));
    const options = (group.options || []).filter((id) => !taken.has(id));
    if (!options.length) return '<p>No quedan habilidades disponibles para elegir.</p>';
    let html = `<div class="pw-skill-choice" data-${prefix}-skill-group="${idx}" data-count="${group.count}"><p>Elige ${group.count}:</p>`;
    options.forEach((opt) => {
      const label = SKILL_LABELS_MAP[opt] || opt;
      html += `
        <label class="pw-checkbox">
          <input type="checkbox" data-${prefix}-skill="${opt}">
          ${label}
        </label>
      `;
    });
    html += '</div>';
    return html;
  }

  function bindSkillChoiceHandlers(container, prefix, fixedSkills, state) {
    const selector = `[data-${prefix}-skill]`;
    const groups = container.querySelectorAll(`[data-${prefix}-skill-group]`);
    const chosenKey = prefix === 'bg' ? 'bgSkillChoices' : prefix === 'race' ? 'raceSkillChoices' : 'classSkillChoices';
    state.data[chosenKey] = state.data[chosenKey] || [];
    groups.forEach((group) => {
      const max = parseInt(group.dataset.count || '0', 10);
      const checkboxes = Array.from(group.querySelectorAll(selector));
      checkboxes.forEach((cb) => {
        cb.addEventListener('change', () => {
          const selected = checkboxes.filter((c) => c.checked).map((c) => normalizeSkillKey(c.dataset[`${prefix}Skill`]));
          // enforce max
          if (selected.length > max) {
            cb.checked = false;
            return;
          }
          // disable if reached max
          if (selected.length >= max) {
            checkboxes.forEach((c) => {
              if (!c.checked) c.disabled = true;
            });
          } else {
            checkboxes.forEach((c) => {
              c.disabled = false;
            });
          }
          state.data[chosenKey] = selected;
          state.data.skillsBySource.background = [...fixedSkills, ...(state.data.bgSkillChoices || [])];
          updateSkillSummary(state);
        });
      });
    });
  }

  function renderLanguageChoiceBlock(prefix, choice, state, sourceKey) {
    const count = choice.count || 0;
    let candidates = [];
    if (choice.from === 'any' || choice.from === 'anyStandard') {
      candidates = state.options.languages || [];
    } else if (Array.isArray(choice.from)) {
      const allowed = new Set(choice.from.map(normalizeLangKey));
      candidates = (state.options.languages || []).filter((lang) => allowed.has(normalizeLangKey(lang.id)));
    }
    const taken = new Set(state.data.languages || []);
    candidates = candidates.filter((lang) => !taken.has(normalizeLangKey(lang.id)));
    if (!candidates.length) return '<p>No hay idiomas disponibles para elegir.</p>';
    let html = `<div class="pw-lang-choice" data-${prefix}-lang-count="${count}"><p>Elige ${count} idiomas adicionales:</p>`;
    candidates.forEach((lang) => {
      html += `
        <label class="pw-checkbox">
          <input type="checkbox" data-${prefix}-lang="${normalizeLangKey(lang.id)}">
          ${lang.name || lang.id}
        </label>
      `;
    });
    html += '</div>';
    return html;
  }

  function bindLanguageChoiceHandlers(container, prefix, fixedLangs, state, sourceKey) {
    const block = container.querySelector(`[data-${prefix}-lang-count]`);
    if (!block) return;
    const max = parseInt(block.dataset[`${prefix}LangCount`] || '0', 10);
    const checkboxes = Array.from(block.querySelectorAll(`input[data-${prefix}-lang]`));
    const chosenKey = prefix === 'bg' ? 'bgLangChoices' : 'raceLangChoices';
    checkboxes.forEach((cb) => {
      cb.addEventListener('change', () => {
        const selected = checkboxes.filter((c) => c.checked).map((c) => normalizeLangKey(c.dataset[`${prefix}Lang`]));
        if (selected.length > max) {
          cb.checked = false;
          return;
        }
        if (selected.length >= max) {
          checkboxes.forEach((c) => {
            if (!c.checked) c.disabled = true;
          });
        } else {
          checkboxes.forEach((c) => {
            c.disabled = false;
          });
        }
        state.data[chosenKey] = selected;
        state.data.languagesBySource[sourceKey] = [...fixedLangs, ...selected];
        computeLanguages(state);
      });
    });
  }

  function formatLanguages(list, state) {
    if (!Array.isArray(list) || !list.length) return '—';
    const names = list.map((id) => {
      const lang = (state.options.languages || []).find((l) => normalizeLangKey(l.id) === normalizeLangKey(id));
      return lang ? lang.name : id;
    });
    return names.join(', ');
  }

  function updateSkillSummary(state) {
    const summary = document.getElementById('pw-skill-summary');
    if (!summary) return;
    const entries = [];
    const map = {
      class: 'Clase',
      race: 'Raza',
      background: 'Trasfondo',
      feats: 'Dotes',
    };
    Object.keys(state.data.skillsBySource).forEach((source) => {
      state.data.skillsBySource[source].forEach((skill) => {
        entries.push(`${SKILL_LABELS_MAP[skill] || skill} (${map[source] || source})`);
      });
    });
    summary.innerHTML = `<h4>Habilidades totales</h4><p>${entries.length ? entries.join(', ') : '—'}</p>`;
  }

  function loadClassFeaturesForWizard(state) {
    const endpoint = (window.PERSONAJE_WIZARD_API && window.PERSONAJE_WIZARD_API.ajax_url) || '';
    const classId = document.getElementById('pw-class')?.value || '';
    const container = document.getElementById('pw-class-features');
    if (!endpoint || !classId || !container) return;

    container.innerHTML = '<p class="character-extended__loading">Cargando rasgos de clase…</p>';
    const fd = new FormData();
    fd.append('action', 'drak_get_class_features_for_wizard');
    fd.append('class_id', classId);
    fd.append('level', 1);
    fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        if (data?.success && data.data?.html) {
          container.innerHTML = data.data.html;
          initFeatureAccordions(container);
        } else {
          container.innerHTML = '<p class="character-extended__empty">No se han podido cargar los rasgos de clase.</p>';
        }
      })
      .catch(() => {
        container.innerHTML = '<p class="character-extended__error">Error al cargar los rasgos de clase.</p>';
      });
  }

  function initFeatureAccordions(root) {
    root.querySelectorAll('.feature-card').forEach((card) => {
      const toggle = card.querySelector('.feature-card__toggle');
      if (!toggle) return;
      const content = card.querySelector('.feature-card__content');
      const icon = toggle.querySelector('.feature-card__toggle-icon');
      const setState = (expanded) => {
        card.classList.toggle('is-collapsed', !expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (content) {
          content.style.display = expanded ? 'block' : 'none';
        }
        if (icon) icon.textContent = expanded ? '▲' : '▼';
      };
      setState(false);
      toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        setState(!expanded);
      });
    });
  }

  function initImagePicker(state) {
    const button = document.getElementById('pw-image-button');
    const input = document.getElementById('pw-image-id');
    const preview = document.getElementById('pw-image-preview');
    if (!button || !input || typeof wp === 'undefined' || !wp.media) return;

    let frame = null;

    function renderPreview(url) {
      if (!preview) return;
      if (!url) {
        preview.innerHTML = '';
        return;
      }
      preview.innerHTML = `<img src="${url}" alt="" style="max-width:200px;height:auto;border-radius:8px;margin-top:8px;">`;
    }

    button.addEventListener('click', (e) => {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: 'Selecciona una imagen para el personaje',
        button: { text: 'Usar esta imagen' },
        library: { type: 'image' },
        multiple: false,
      });

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        const thumb = (attachment.sizes && (attachment.sizes.medium || attachment.sizes.thumbnail || attachment.sizes.full)) || null;
        const url = thumb ? thumb.url : attachment.url;
        input.value = attachment.id;
        state.data.image_id = attachment.id;
        renderPreview(url);
      });

      frame.open();
    });
  }

  function fillSelect(id, list, valueKey, labelKey, allowEmpty = true) {
    const select = document.getElementById(id);
    if (!select) return;
    select.innerHTML = '';
    if (allowEmpty) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = '—';
      select.appendChild(opt);
    }
    list.forEach((item) => {
      const opt = document.createElement('option');
      opt.value = item[valueKey];
      opt.textContent = item[labelKey];
      select.appendChild(opt);
    });
  }

  function populateProfSelects(state) {
    ['weapons', 'armors', 'tools', 'languages'].forEach((key) => {
      const select = document.querySelector(`select[data-prof="${key}"]`);
      if (!select) return;
      select.innerHTML = '';
      (state.options.proficiencies[key] || []).forEach((item) => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name || item.id;
        select.appendChild(opt);
      });
    });
  }

  function updateAbilityMods() {
    document.querySelectorAll('[data-ability]').forEach((input) => {
      const key = input.dataset.ability;
      const val = parseInt(input.value, 10);
      const mod = Number.isFinite(val) ? Math.floor((val - 10) / 2) : 0;
      const modEl = document.querySelector(`[data-ability-mod="${key}"]`);
      if (modEl) {
        modEl.textContent = `mod ${mod >= 0 ? '+' + mod : mod}`;
      }
    });
  }

  function addFeatChoiceHandlers(state) {
    const featBtn = document.querySelector('[data-add-feat]');
    const featList = document.querySelector('[data-feat-list]');
    const choiceBtn = document.querySelector('[data-add-choice]');
    const choiceList = document.querySelector('[data-choice-list]');
    if (featBtn && featList) {
      featBtn.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'pw-row';
        row.innerHTML = `
          <input type="text" placeholder="ID dote" data-feat-id>
          <input type="text" placeholder="Nombre" data-feat-name>
          <input type="text" placeholder="Fuente" data-feat-source>
          <button type="button" class="pw-btn pw-btn-small" data-remove>×</button>
        `;
        row.querySelector('[data-remove]').addEventListener('click', () => row.remove());
        featList.appendChild(row);
      });
    }
    if (choiceBtn && choiceList) {
      choiceBtn.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'pw-row';
        row.innerHTML = `
          <input type="text" placeholder="Clave/rasgo" data-choice-key>
          <input type="text" placeholder="Elección" data-choice-value>
          <input type="text" placeholder="Fuente" data-choice-source>
          <input type="text" placeholder="Notas" data-choice-notes>
          <button type="button" class="pw-btn pw-btn-small" data-remove>×</button>
        `;
        row.querySelector('[data-remove]').addEventListener('click', () => row.remove());
        choiceList.appendChild(row);
      });
    }
  }

  function handleSubmit(state, root) {
    const api = window.PERSONAJE_WIZARD_API;
    const errorEl = root.querySelector('.pw-error');
    const successEl = root.querySelector('.pw-success');
    errorEl.textContent = '';
    successEl.innerHTML = '';

    const payload = collectPayload(state);
    if (!payload.name) {
      errorEl.textContent = 'El nombre es obligatorio.';
      return;
    }
    if (!payload.image_id) {
      errorEl.textContent = 'La imagen es obligatoria.';
      return;
    }

    const fd = new FormData();
    fd.append('action', api.endpoints.create);
    fd.append('payload', JSON.stringify(payload));

    root.classList.add('pw-is-loading');

    fetch(api.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        if (!data?.success) {
          throw new Error(data?.data?.message || api.labels.error_generic);
        }
        const urls = data.data || {};
        const redirect = urls.redirect_url || urls.sheet_url;
        if (redirect) {
          window.location.href = redirect;
          return;
        }
        successEl.innerHTML = `<p>✅ Personaje creado (ID ${urls.post_id}). Abre la hoja desde aquí: <a href="${urls.sheet_url}">${urls.sheet_url}</a></p>`;
      })
      .catch((err) => {
        errorEl.textContent = err.message || api.labels.error_generic;
      })
      .finally(() => {
        root.classList.remove('pw-is-loading');
      });
  }

  function collectPayload(state) {
    const getVal = (id) => document.getElementById(id)?.value || '';
    return {
      name: getVal('pw-name').trim(),
      image_id: state.data.image_id || parseInt(document.getElementById('pw-image-id')?.value || '0', 10) || null,
      campaign_id: state.data.campaign_id || 0,
    };
  }

  function splitLines(text) {
    return (text || '')
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean);
  }
  function loadRaceFeaturesForWizard(state) {
    const endpoint = (window.PERSONAJE_WIZARD_API && window.PERSONAJE_WIZARD_API.ajax_url) || '';
    const raceId = document.getElementById('pw-race')?.value || '';
    const container = document.getElementById('pw-race-features');
    if (!endpoint || !raceId || !container) return;

    container.innerHTML = '<p class="character-extended__loading">Cargando rasgos de raza…</p>';
    const fd = new FormData();
    fd.append('action', 'drak_get_race_features_for_wizard');
    fd.append('race_id', raceId);
    fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: fd })
      .then((res) => res.json())
      .then((data) => {
        if (data?.success && typeof data.data?.html !== 'undefined') {
          container.innerHTML = data.data.html || '<p class="character-extended__empty">No hay rasgos para esta raza.</p>';
          initFeatureAccordions(container);
          return;
        }
        container.innerHTML = '<p class="character-extended__empty">No se han podido cargar los rasgos de la raza.</p>';
      })
      .catch(() => {
        container.innerHTML = '<p class="character-extended__error">Error al cargar los rasgos de la raza.</p>';
      });
  }
})();
