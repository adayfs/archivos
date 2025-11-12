document.addEventListener('DOMContentLoaded', function () {
  const slider = document.getElementById('slider_temp_hp');
  const display = document.getElementById('display_cs_temp_hp');
  const hidden = document.getElementById('cs_hp_temp');

  // Si alguno no existe, no seguimos
  if (!slider || !display || !hidden) {
    console.warn("Algunos elementos de vida temporal no están presentes. Se omite el script.");
    return;
  }

  if (typeof HP_TEMP_AJAX === 'undefined' || !HP_TEMP_AJAX.post_id) {
    console.warn("HP_TEMP_AJAX no está definido correctamente.");
    return;
  }

  let timeout = null;

  function guardarVidaTemporal(valor) {
    fetch(HP_TEMP_AJAX.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'guardar_hp_temporal',
        post_id: HP_TEMP_AJAX.post_id,
        valor: valor
      })
    })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        console.error("Error al guardar HP temporal:", data.message);
      }
    })
    .catch(error => {
      console.error("Error AJAX HP temporal:", error);
    });
  }

  function scheduleSave(value) {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      guardarVidaTemporal(value);
    }, 500);
  }

  slider.addEventListener('input', () => {
    const val = parseInt(slider.value, 10) || 0;
    display.textContent = val;
    hidden.value = val;
    scheduleSave(val);
  });

  display.addEventListener('input', () => {
    const val = parseInt(display.textContent, 10) || 0;
    slider.value = val;
	slider.setAttribute('value', val);
    hidden.value = val;
    scheduleSave(val);
  });




  // === MODAL: DATOS BÁSICOS (INI / CA / VEL / PV / NIVEL / CLASE / SUBCLASE) ===
  const basicsOverlay  = document.getElementById('basics-overlay');
  const basicsBtn      = document.getElementById('btn-basicos-modal');
  const basicsApplyBtn = document.getElementById('basics-apply');
  const basicsClose    = document.querySelector('.close-basics-popup');

  if (!basicsOverlay || !basicsBtn || !basicsApplyBtn || !basicsClose) return;

  // Ocultos en la hoja
  const hiddenBasics = {
    cs_iniciativa: document.getElementById('cs_iniciativa'),
    cs_ac:         document.getElementById('cs_ac'),
    cs_velocidad:  document.getElementById('cs_velocidad'),
    cs_hp:         document.getElementById('cs_hp'),
    nivel:         document.getElementById('nivel'),
    clase:         document.getElementById('clase'),
    subclase:      document.getElementById('subclase'),
	raza:          document.getElementById('raza'),
  };

  // Displays en la hoja
  const displayBasics = {
    cs_iniciativa: document.getElementById('display_cs_iniciativa'),
    cs_ac:         document.getElementById('display_cs_ac'),
    cs_velocidad:  document.getElementById('display_cs_velocidad'),
    cs_hp:         document.getElementById('display_cs_hp'),
    nivel:         document.getElementById('display_nivel'),
    clase:         document.getElementById('display_clase'),
    subclase:      document.getElementById('display_subclase'),
	raza:          document.getElementById('display_raza'),

  };

  // Inputs del modal (los que usan data-basic)
  const modalBasicInputs = document.querySelectorAll('.basics-modal-input[data-basic]');
  const modalClassSelect    = document.getElementById('modal-clase');
  const modalSubclassSelect = document.getElementById('modal-subclase');
  const modalRaceSelect     = document.getElementById('modal-raza');
	
	
	  // === MODAL: COMPETENCIAS (ARMAS / ARMADURAS / HERRAMIENTAS / IDIOMAS) ===
  const profsOverlay   = document.getElementById('profs-overlay');
  const profsBtn       = document.getElementById('btn-profs-modal');
  const profsApplyBtn  = document.getElementById('profs-apply');
  const profsClose     = document.querySelector('.close-profs-popup');

  if (profsOverlay && profsBtn && profsApplyBtn && profsClose && typeof DND5_API !== 'undefined') {
const hiddenProfs = {
  weapons:   document.getElementById('prof_weapons'),
  armors:    document.getElementById('prof_armors'),
  tools:     document.getElementById('prof_tools'),
  languages: document.getElementById('prof_languages'),
};


    const displayProfs = {
      weapons:   document.getElementById('display_cs_armas'),
      armors:    document.getElementById('display_cs_armaduras'),
      tools:     document.getElementById('display_cs_herramientas'),
      languages: document.getElementById('display_cs_idiomas'),
    };

    const selects = {
      weapons:   document.getElementById('profs-weapons-select'),
      armors:    document.getElementById('profs-armors-select'),
      tools:     document.getElementById('profs-tools-select'),
      languages: document.getElementById('profs-languages-select'),
    };

    const addButtons = {
      weapons:   document.getElementById('profs-weapons-add'),
      armors:    document.getElementById('profs-armors-add'),
      tools:     document.getElementById('profs-tools-add'),
      languages: document.getElementById('profs-languages-add'),
    };

    const lists = {
      weapons:   document.getElementById('profs-weapons-list'),
      armors:    document.getElementById('profs-armors-list'),
      tools:     document.getElementById('profs-tools-list'),
      languages: document.getElementById('profs-languages-list'),
    };

    // Datos de referencia cargados desde el servidor
    const lookups = {
      weapons:   [],
      armors:    [],
      tools:     [],
      languages: [],
    };

    // Estado actual (arrays de IDs seleccionados)
    const selected = {
      weapons:   [],
      armors:    [],
      tools:     [],
      languages: [],
    };

    function parseIds(str) {
      return (str || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);
    }

    function serializeIds(arr) {
      return arr.join(',');
    }

    function findName(type, id) {
      const list = lookups[type] || [];
      const item = list.find(it => it.id === id);
      return item ? (item.name || item.id) : id;
    }

    // Mostrar en la hoja (fuera del modal)
    function refreshDisplays() {
      Object.keys(hiddenProfs).forEach(type => {
        const hidden = hiddenProfs[type];
        const display = displayProfs[type];
        if (!hidden || !display) return;

        const ids = parseIds(hidden.value);
        const names = ids.map(id => findName(type, id));
        display.textContent = names.join(', ');
      });
    }

    // Rellenar selects
    function fillSelect(select, list, placeholder) {
      if (!select) return;
      select.innerHTML = '';

      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = placeholder;
      select.appendChild(opt0);

      list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id || '';
        opt.textContent = item.name || item.id || '';
        select.appendChild(opt);
      });
    }

    function renderList(type) {
      const ul = lists[type];
      if (!ul) return;
      ul.innerHTML = '';

      selected[type].forEach(id => {
        const li = document.createElement('li');
        li.dataset.id = id;
        li.textContent = findName(type, id);

const btn = document.createElement('button');
btn.type = 'button';
btn.textContent = '×';
btn.className = 'btn-basicos-mod profs-remove';
btn.style.marginLeft = '0.5rem';
        li.appendChild(btn);
        ul.appendChild(li);
      });
    }

    function loadProficiencies() {
      const formData = new FormData();
      formData.append('action', 'drak_dnd5_get_proficiencies');

      return fetch(DND5_API.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then(r => r.json())
        .then(res => {
          if (!res || !res.success || !res.data) return;

          lookups.weapons   = res.data.weapons   || [];
          lookups.armors    = res.data.armors    || [];
          lookups.tools     = res.data.tools     || [];
          lookups.languages = res.data.languages || [];

          fillSelect(selects.weapons,   lookups.weapons,   'Selecciona arma…');
          fillSelect(selects.armors,    lookups.armors,    'Selecciona armadura…');
          fillSelect(selects.tools,     lookups.tools,     'Selecciona herramienta…');
          fillSelect(selects.languages, lookups.languages, 'Selecciona idioma…');

          refreshDisplays();
        });
    }

    // Inicializar estado desde los ocultos
    Object.keys(hiddenProfs).forEach(type => {
      const hidden = hiddenProfs[type];
      selected[type] = parseIds(hidden ? hidden.value : '');
    });

    // Cargar listas desde el servidor
    loadProficiencies();

    // Botones "Añadir"
    Object.keys(addButtons).forEach(type => {
      const btn = addButtons[type];
      const select = selects[type];
      if (!btn || !select) return;

      btn.addEventListener('click', () => {
        const id = select.value;
        if (!id) return;
        if (!selected[type].includes(id)) {
          selected[type].push(id);
          renderList(type);
        }
      });
    });

    // Eliminar elementos (delegado en cada UL)
    Object.keys(lists).forEach(type => {
      const ul = lists[type];
      if (!ul) return;

      ul.addEventListener('click', e => {
        if (!e.target.classList.contains('profs-remove')) return;
        const li = e.target.closest('li');
        if (!li) return;
        const id = li.dataset.id;
        selected[type] = selected[type].filter(x => x !== id);
        renderList(type);
      });
    });

    // Abrir modal
    profsBtn.addEventListener('click', () => {
      // Re-sincronizar desde los valores actuales
      Object.keys(hiddenProfs).forEach(type => {
        const hidden = hiddenProfs[type];
        selected[type] = parseIds(hidden ? hidden.value : '');
        renderList(type);
      });

      profsOverlay.style.display = 'flex';
    });

    // Cerrar modal
    profsClose.addEventListener('click', () => {
      profsOverlay.style.display = 'none';
    });

    profsOverlay.addEventListener('click', e => {
      if (e.target === profsOverlay) {
        profsOverlay.style.display = 'none';
      }
    });

    // Aplicar cambios
    profsApplyBtn.addEventListener('click', () => {
      Object.keys(hiddenProfs).forEach(type => {
        const hidden = hiddenProfs[type];
        if (!hidden) return;
        hidden.value = serializeIds(selected[type]);
      });

      refreshDisplays();
      profsOverlay.style.display = 'none';
    });

    // Mostrar algo en la hoja al cargar
    refreshDisplays();
  }


  // === 3.1 Inicializar displays en la hoja ===
  // INI / CA / VEL / PV / nivel
  Object.keys(hiddenBasics).forEach((key) => {
  if (!displayBasics[key] || !hiddenBasics[key]) return;
  if (key === 'clase' || key === 'subclase' || key === 'raza') return; // estos los rellenamos tras cargar selects
  displayBasics[key].textContent = hiddenBasics[key].value || '';
});

  // === 3.2 Funciones auxiliares AJAX (clases / subclases) ===
  function ajaxRequest(action, extraData) {
    if (typeof DND5_API === 'undefined') return Promise.reject('DND5_API no definido');

    const formData = new FormData();
    formData.append('action', action);
    if (extraData) {
      Object.keys(extraData).forEach((key) => {
        formData.append(key, extraData[key]);
      });
    }

    return fetch(DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    }).then((resp) => resp.json());
  }

  function fillSelect(select, placeholder, list, currentValue) {
    if (!select) return;

    select.innerHTML = '';

    const optPlaceholder = document.createElement('option');
    optPlaceholder.value = '';
    optPlaceholder.textContent = placeholder;
    select.appendChild(optPlaceholder);

    list.forEach((item) => {
      const opt = document.createElement('option');
      opt.value = item.id || '';
      opt.textContent = item.name || '';
      if (currentValue && currentValue === opt.value) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
  }

  function updateClassDisplayFromSelect() {
    if (!modalClassSelect || !displayBasics.clase) return;
    const sel = modalClassSelect;
    const text = sel.value ? sel.options[sel.selectedIndex].textContent : '';
    displayBasics.clase.textContent = text;
    if (hiddenBasics.clase) hiddenBasics.clase.value = sel.value || '';
  }

  function updateSubclassDisplayFromSelect() {
    if (!modalSubclassSelect || !displayBasics.subclase) return;
    const sel = modalSubclassSelect;
    const text = sel.value ? sel.options[sel.selectedIndex].textContent : '';
    displayBasics.subclase.textContent = text;
    if (hiddenBasics.subclase) hiddenBasics.subclase.value = sel.value || '';
  }

  function loadSubclassesFor(classId, preselect) {
    if (!modalSubclassSelect) return;

    if (!classId) {
      fillSelect(modalSubclassSelect, 'Selecciona una clase primero…', [], '');
      modalSubclassSelect.disabled = true;
      updateSubclassDisplayFromSelect();
      return;
    }

    modalSubclassSelect.disabled = true;
    modalSubclassSelect.innerHTML = '<option value="">Cargando subclases…</option>';

    ajaxRequest('drak_dnd5_get_subclasses', { class_index: classId })
      .then((res) => {
        if (!res || !res.success) {
          fillSelect(modalSubclassSelect, 'No hay subclases disponibles', [], '');
          modalSubclassSelect.disabled = true;
          updateSubclassDisplayFromSelect();
          return;
        }

        const subclasses = (res.data && res.data.subclasses) ? res.data.subclasses : [];
        fillSelect(
          modalSubclassSelect,
          subclasses.length ? 'Selecciona subclase…' : 'No hay subclases disponibles',
          subclasses,
          preselect || (hiddenBasics.subclase ? hiddenBasics.subclase.value : '')
        );
        modalSubclassSelect.disabled = subclasses.length === 0;
        updateSubclassDisplayFromSelect();
      })
      .catch(() => {
        fillSelect(modalSubclassSelect, 'Error al cargar subclases', [], '');
        modalSubclassSelect.disabled = true;
        updateSubclassDisplayFromSelect();
      });
  }

	  function updateRaceDisplayFromSelect() {
    if (!modalRaceSelect || !displayBasics.raza) return;
    const sel  = modalRaceSelect;
    const text = sel.value ? sel.options[sel.selectedIndex].textContent : '';
    displayBasics.raza.textContent = text;
    if (hiddenBasics.raza) hiddenBasics.raza.value = sel.value || '';
  }

  function loadRaces(preselect) {
    if (!modalRaceSelect) return;

    modalRaceSelect.disabled = true;
    modalRaceSelect.innerHTML = '<option value=\"\">Cargando razas…</option>';

    const currentRaceId = preselect || (hiddenBasics.raza ? hiddenBasics.raza.value : '');

    ajaxRequest('drak_dnd5_get_races')
      .then((res) => {
        if (!res || !res.success) {
          modalRaceSelect.disabled = true;
          modalRaceSelect.innerHTML = '<option value=\"\">Error al cargar razas</option>';
          updateRaceDisplayFromSelect();
          return;
        }

        const races = (res.data && res.data.races) ? res.data.races : [];
        fillSelect(
          modalRaceSelect,
          races.length ? 'Selecciona raza…' : 'No hay razas disponibles',
          races,
          currentRaceId
        );
        modalRaceSelect.disabled = races.length === 0;
        updateRaceDisplayFromSelect();
      })
      .catch(() => {
        modalRaceSelect.disabled = true;
        modalRaceSelect.innerHTML = '<option value=\"\">Error al cargar razas</option>';
        updateRaceDisplayFromSelect();
      });
  }

	
  // === 3.3 Cargar lista de clases al arrancar ===
  if (modalClassSelect && typeof DND5_API !== 'undefined') {
    ajaxRequest('drak_dnd5_get_classes')
      .then((res) => {
        if (!res || !res.success) return;

        const classes = (res.data && res.data.classes) ? res.data.classes : [];
        const currentClassId = hiddenBasics.clase ? hiddenBasics.clase.value : '';

        fillSelect(
          modalClassSelect,
          classes.length ? 'Selecciona clase…' : 'No hay clases disponibles',
          classes,
          currentClassId
        );

        updateClassDisplayFromSelect();

        if (currentClassId) {
          loadSubclassesFor(currentClassId, hiddenBasics.subclase ? hiddenBasics.subclase.value : '');
        }
      })
      .catch(() => {
        // si falla, dejamos el select como está
      });

    modalClassSelect.addEventListener('change', function () {
      updateClassDisplayFromSelect();
      loadSubclassesFor(this.value || '', '');
    });
  }
	
	// Cargar razas al arrancar
  if (modalRaceSelect && typeof DND5_API !== 'undefined') {
    loadRaces('');
    modalRaceSelect.addEventListener('change', function () {
      updateRaceDisplayFromSelect();
    });
  }

  if (modalSubclassSelect) {
    modalSubclassSelect.addEventListener('change', function () {
      updateSubclassDisplayFromSelect();
    });
  }

  // === 3.4 Apertura y cierre del modal ===
  basicsBtn.addEventListener('click', function () {
    // Rellenar campos del modal con los valores actuales
    modalBasicInputs.forEach((input) => {
      const key = input.dataset.basic;
      const hidden = hiddenBasics[key];
      if (!hidden) return;
      input.value = hidden.value || '';
    });
	  
	    if (modalRaceSelect && hiddenBasics.raza) {
    modalRaceSelect.value = hiddenBasics.raza.value || '';
    updateRaceDisplayFromSelect();
  }


    basicsOverlay.style.display = 'flex';
  });

  basicsClose.addEventListener('click', function () {
    basicsOverlay.style.display = 'none';
  });

  basicsOverlay.addEventListener('click', function (e) {
    if (e.target === basicsOverlay) {
      basicsOverlay.style.display = 'none';
    }
  });

  // === 3.5 Aplicar cambios de INI / CA / VEL / PV / NIVEL ===
  basicsApplyBtn.addEventListener('click', function () {
    modalBasicInputs.forEach((input) => {
      const key = input.dataset.basic;
      const hidden = hiddenBasics[key];
      const display = displayBasics[key];
      if (!hidden || !display) return;

      hidden.value = input.value;
      display.textContent = input.value || '';
    });

    // Clase y subclase ya se sincronizan en sus listeners de change
    basicsOverlay.style.display = 'none';
  });
		
		
		
		// Mostrar modal al hacer clic en "Modificar hoja"
const btnStats = document.getElementById('btn-modificar-hoja');
const statsOverlay = document.getElementById('stats-overlay');
if (btnStats && statsOverlay) {
  btnStats.addEventListener('click', function () {
  // Copiar valores al modal
  const statsModalFields = [
    'cs_fuerza',
    'cs_destreza',
    'cs_constitucion',
    'cs_inteligencia',
    'cs_sabiduria',
    'cs_carisma',
    'cs_proeficiencia'
  ];

  statsModalFields.forEach(stat => {
    const currentVal = document.getElementById(stat)?.value || '';
    const input = statsOverlay.querySelector(`.stats-modal-input[data-stat="${stat}"]`);
    if (input) input.value = currentVal;
  });

  statsOverlay.style.display = 'flex';
});

}

// Cerrar modal de características
const closeStats = document.querySelector('.close-stats-popup');
if (closeStats && statsOverlay) {
  closeStats.addEventListener('click', function () {
    statsOverlay.style.display = 'none';
  });
}
// Aplicar cambios desde el modal de características
const applyStatsBtn = document.getElementById('stats-apply');
if (applyStatsBtn && statsOverlay) {
  applyStatsBtn.addEventListener('click', function () {
    const inputs = statsOverlay.querySelectorAll('.stats-modal-input');

    inputs.forEach(input => {
      const stat = input.dataset.stat;
      const val  = input.value;

      const hidden = document.getElementById(stat);
      if (hidden) hidden.value = val;

      const display = document.getElementById('display_' + stat);
      if (display) display.textContent = val;

      // También actualiza el mod si existe
      if (stat !== 'cs_proeficiencia') {
        const mod = Math.floor((parseInt(val, 10) - 10) / 2);
        const modId = stat + '_mod';

        const modHidden = document.getElementById(modId);
        const modDisplay = document.getElementById('display_' + modId);

        if (modHidden) modHidden.value = mod;
        if (modDisplay) {
          modDisplay.textContent = mod;

          modDisplay.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
          if (mod > 0) modDisplay.classList.add('mod-pos');
          else if (mod < 0) modDisplay.classList.add('mod-neg');
          else modDisplay.classList.add('mod-zero');
        }
      }
    });
	  
	      // NUEVO BLOQUE: Copiar modificadores a saves y habilidades

    const statToSaves = {
      cs_fuerza: 'cs_save_fuerza',
      cs_destreza: 'cs_save_destreza',
      cs_constitucion: 'cs_save_constitucion',
      cs_inteligencia: 'cs_save_inteligencia',
      cs_sabiduria: 'cs_save_sabiduria',
      cs_carisma: 'cs_save_carisma',
    };

    const statToSkills = {
      cs_fuerza: ['cs_skill_atletismo'],
      cs_destreza: ['cs_skill_acrobacias', 'cs_skill_juego_manos', 'cs_skill_sigilo'],
      cs_constitucion: [],
      cs_inteligencia: ['cs_skill_arcanos', 'cs_skill_historia', 'cs_skill_investigacion', 'cs_skill_naturaleza', 'cs_skill_religion'],
      cs_sabiduria: ['cs_skill_trato_animales', 'cs_skill_perspicacia', 'cs_skill_medicina', 'cs_skill_percepcion', 'cs_skill_supervivencia'],
      cs_carisma: ['cs_skill_engano', 'cs_skill_intimidacion', 'cs_skill_interpretacion', 'cs_skill_persuasion'],
    };

    Object.keys(statToSaves).forEach(stat => {
      const modId = stat + '_mod';
      const modVal = document.getElementById(modId)?.value || '0';

      // Tiradas de salvación
      const saveField = statToSaves[stat];
      const saveInput = document.getElementById(saveField);
      const saveDisplay = document.getElementById('display_' + saveField);
      if (saveInput) saveInput.value = modVal;
      if (saveDisplay) saveDisplay.textContent = modVal;

      // Habilidades
      const skills = statToSkills[stat] || [];
      skills.forEach(skill => {
        const skillInput = document.getElementById(skill);
        const skillDisplay = document.getElementById('display_' + skill);
        if (skillInput) skillInput.value = modVal;
        if (skillDisplay) skillDisplay.textContent = modVal;
      });
    });


    statsOverlay.style.display = 'none';
  });
}

	  // Aplicar cambios desde el modal de datos básicos
const applyBasicsBtn = document.getElementById('basics-apply');

if (applyBasicsBtn && basicsOverlay) {
  applyBasicsBtn.addEventListener('click', function () {
    const inputs = basicsOverlay.querySelectorAll('.basics-modal-input');

    inputs.forEach(input => {
      const field = input.dataset.basic;
      const val = input.value;

      // Actualizar hidden
      const hidden = document.getElementById(field);
      if (hidden) hidden.value = val;

      // Actualizar visual
      const display = document.getElementById('display_' + field);
      if (display) display.textContent = val;
    });

    basicsOverlay.style.display = 'none';
  });
}	
	  // Mostrar características principales
[
  'cs_fuerza',
  'cs_destreza',
  'cs_constitucion',
  'cs_inteligencia',
  'cs_sabiduria',
  'cs_carisma',
  'cs_proeficiencia'
].forEach(stat => {
  const statInput = document.getElementById(stat);
  const statDisplay = document.getElementById('display_' + stat);
  if (statInput && statDisplay) {
    statDisplay.textContent = statInput.value || '0';
  }

  const modInput = document.getElementById(stat + '_mod');
  const modDisplay = document.getElementById('display_' + stat + '_mod');
  if (modInput && modDisplay) {
    const val = parseInt(modInput.value || '0', 10);
    const formatted = (val > 0 ? '+' : '') + val;
    modDisplay.textContent = formatted;

    modDisplay.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
    if (val > 0) modDisplay.classList.add('mod-pos');
    else if (val < 0) modDisplay.classList.add('mod-neg');
    else modDisplay.classList.add('mod-zero');
  }

	// Mostrar valores de los campos básicos (INI, CA, VEL, PV)
['cs_iniciativa', 'cs_ac', 'cs_velocidad', 'cs_hp'].forEach(id => {
  const hidden = document.getElementById(id);
  const display = document.getElementById('display_' + id);
  if (hidden && display) {
    display.textContent = hidden.value || '0';
  }
});
	



	// Iniciativa = Modificador de Destreza
// Iniciativa = Modificador de Destreza
const modDestreza = document.getElementById('cs_destreza_mod');
const inputIniciativa = document.getElementById('cs_iniciativa');
const displayIniciativa = document.getElementById('display_cs_iniciativa');

if (modDestreza && inputIniciativa && displayIniciativa) {
  const modVal = parseInt(modDestreza.value || '0', 10);
  inputIniciativa.value = modVal;
  displayIniciativa.textContent = (modVal > 0 ? '+' : modVal < 0 ? '' : '') + modVal;
  displayIniciativa.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
  if (modVal > 0) displayIniciativa.classList.add('mod-pos');
  else if (modVal < 0) displayIniciativa.classList.add('mod-neg');
  else displayIniciativa.classList.add('mod-zero');
}



});
	

// Activar/desactivar proeficiencia en habilidades y salvaciones
document.querySelectorAll('.skill-prof-toggle').forEach(button => {
  button.addEventListener('click', () => {
    const span = button.querySelector('.skill-prof-circle');
    const isSave = button.classList.contains('save-prof-toggle');

    const dataAttr = isSave ? 'data-save' : 'data-skill';
    const field = button.getAttribute(dataAttr);
    const profInput = document.getElementById('prof_' + field);
    const mainInput = document.getElementById(field);
    const display = document.getElementById('display_' + field);

    const profBonusInput = document.getElementById('cs_proeficiencia');
    const profBonus = parseInt(profBonusInput?.value || '0', 10);
    const baseVal = parseInt(mainInput?.value || '0', 10);
    const isActive = span.classList.contains('skill-prof-active');

    let newVal;
    if (isActive) {
      // QUITAR proeficiencia
      span.classList.remove('skill-prof-active');
      if (profInput) profInput.value = '0';
      newVal = baseVal - profBonus;
    } else {
      // AÑADIR proeficiencia
      span.classList.add('skill-prof-active');
      if (profInput) profInput.value = '1';
      newVal = baseVal + profBonus;
    }

    if (mainInput) mainInput.value = newVal;
    if (display) {
		const formatted = (newVal > 0 ? '+' : '') + newVal;
      display.textContent = formatted;

      display.classList.remove('mod-pos', 'mod-neg', 'mod-zero');
      if (newVal < 0) display.classList.add('mod-neg');
      else if (profInput.value === '1') display.classList.add('mod-pos');
      else display.classList.add('mod-zero');
    }
  });
	

function recalculateSkillBonus(skillKey) {
  const base = getModifierFor(skillKey); // Esto ya lo usas para calcular
  const profBonus = parseInt(document.getElementById('cs_prof_bonus').value) || 0;
  const profButton = document.querySelector(`.skill-prof-toggle[data-skill="${skillKey}"]`);
  const expertButton = document.querySelector(`.skill-expertise-toggle[data-skill="${skillKey}"]`);

  let total = base;

  if (profButton.classList.contains('skill-prof-active')) {
    total += profBonus;
    if (expertButton.classList.contains('skill-expertise-active')) {
      total += profBonus; // Aplica bonus por expertise
    }
  }

  document.querySelector(`.display-${skillKey}`).textContent = total >= 0 ? `+${total}` : total;
}

	
});
	
	
	// Mostrar tiradas de salvación con signos y colores
document.querySelectorAll('.save-display[id^="display_cs_save_"]').forEach(p => {
  const id = p.id.replace('display_', '');
  const input = document.getElementById(id);
  const profInput = document.getElementById('prof_' + id);
  if (input) {
    const val = parseInt(input.value || '0', 10);
    const formattedVal = (val >= 0 ? '+' : '') + val;

    p.textContent = formattedVal;
    p.classList.remove('mod-pos', 'mod-neg');

    if (val < 0) p.classList.add('mod-neg');
    else if (profInput && profInput.value === '1') p.classList.add('mod-pos');
  }
});

// Mostrar habilidades
document.querySelectorAll('.skill-display[id^="display_cs_skill_"]').forEach(p => {
  const id = p.id.replace('display_', '');
  const input = document.getElementById(id);
  const profInput = document.getElementById('prof_' + id);
  if (input) {
    const val = parseInt(input.value || '0', 10);
    const formattedVal = val > 0 ? '+' + val : val.toString();

    p.textContent = formattedVal;
    p.classList.remove('mod-pos', 'mod-neg');

    if (val < 0) p.classList.add('mod-neg');
    else if (profInput && profInput.value === '1') p.classList.add('mod-pos');
  }
});

//Sliderrrrrrrrr	
	
  const tempPVDisplay = document.getElementById('display_cs_temp_hp');
  const tempPVSlider = document.getElementById('slider_temp_hp');
  const tempHPInput = document.getElementById('cs_hp_temp');
	
	function actualizarColorSlider(slider) {
  const max = parseInt(slider.max, 10);
  const val = parseInt(slider.value, 10);
  let color;

  const porcentaje = (val / max) * 100;

  if (porcentaje <= 33) {
    color = '#ff4c4c'; // rojo
  } else if (porcentaje <= 66) {
    color = '#ffcc00'; // amarillo
  } else {
    color = '#9933ff'; // morado
  }

  slider.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${(porcentaje || 0)}%, #444 ${(porcentaje || 0)}%, #444 100%)`;
}

  if (tempPVDisplay && tempPVSlider) {
    // Actualiza el slider al cambiar el texto manualmente
    tempPVDisplay.addEventListener('input', function () {
      let val = parseInt(tempPVDisplay.textContent) || 0;
      if (val < 0) val = 0;
      tempPVSlider.value = val;
	  tempHPInput.value = val;
	  tempPVSlider.max = val;
		actualizarColorSlider(tempPVDisplay);
    });

    // Actualiza el texto si cambias el slider
    tempPVSlider.addEventListener('input', function () {
      const val = parseInt(tempPVSlider.value);
      tempPVDisplay.textContent = val;
    });
	    // Inicializa color al cargar
  const initialVal = parseInt(tempHPInput.value || '0', 10);
  }

  



	const tempSlider = document.getElementById('slider_temp_hp');
const tempDisplay = document.getElementById('display_cs_temp_hp');
const hiddenInput = document.getElementById('cs_hp_temp');
	const maxHPInput = document.getElementById('cs_hp');
if (maxHPInput && tempSlider && hiddenInput && tempDisplay) {
  const maxHP = parseInt(maxHPInput.value || '0', 10);
  const tempHP = parseInt(hiddenInput.value || '0', 10);

  if (tempHP > maxHP) {
    tempSlider.max = tempHP;
    tempSlider.value = tempHP;
  } else {
    tempSlider.max = maxHP;
    tempSlider.value = tempHP;
  }

  tempDisplay.textContent = tempHP;
  actualizarColorSlider(tempSlider);
}



	

if (tempSlider && tempDisplay && hiddenInput) {
  // Iniciar el fondo dinámico según valor inicial
  actualizarColorSlider(tempSlider);

  tempSlider.addEventListener('input', () => {
    tempDisplay.textContent = tempSlider.value;
    hiddenInput.value = tempSlider.value;
    actualizarColorSlider(tempSlider);
  });
}
const btnResetTempPV = document.getElementById('btn-reset-temp-pv');

if (btnResetTempPV) {
  btnResetTempPV.addEventListener('click', () => {
    const hpField = document.getElementById('cs_hp');
    const hp = parseInt(hpField?.value || '0', 10);

    if (isNaN(hp)) return;

    tempSlider.value = hp;
    tempSlider.max = hp;
	tempSlider.setAttribute('value', hp);
						
    tempDisplay.textContent = hp;
    hiddenInput.value = hp;

    actualizarColorSlider(tempSlider);

    // Disparar guardado en base de datos
    if (typeof guardarVidaTemporal === 'function') {
      guardarVidaTemporal(hp);
    }
  });
}


// === HABILIDADES, TIRADAS DE SALVACIÓN Y EXPERTISE ===
const profBonusInput = document.getElementById('cs_proeficiencia');
if (profBonusInput) {
  const skillAbilityMap = {
    cs_skill_acrobacias:    'cs_destreza_mod',
    cs_skill_juego_manos:   'cs_destreza_mod',
    cs_skill_sigilo:        'cs_destreza_mod',
    cs_skill_atletismo:     'cs_fuerza_mod',
    cs_skill_trato_animales:'cs_sabiduria_mod',
    cs_skill_perspicacia:   'cs_sabiduria_mod',
    cs_skill_medicina:      'cs_sabiduria_mod',
    cs_skill_percepcion:    'cs_sabiduria_mod',
    cs_skill_supervivencia: 'cs_sabiduria_mod',
    cs_skill_arcanos:       'cs_inteligencia_mod',
    cs_skill_historia:      'cs_inteligencia_mod',
    cs_skill_investigacion: 'cs_inteligencia_mod',
    cs_skill_naturaleza:    'cs_inteligencia_mod',
    cs_skill_religion:      'cs_inteligencia_mod',
    cs_skill_engano:        'cs_carisma_mod',
    cs_skill_intimidacion:  'cs_carisma_mod',
    cs_skill_interpretacion:'cs_carisma_mod',
    cs_skill_persuasion:    'cs_carisma_mod',
  };

  const saveAbilityMap = {
    cs_save_fuerza:       'cs_fuerza_mod',
    cs_save_destreza:     'cs_destreza_mod',
    cs_save_constitucion: 'cs_constitucion_mod',
    cs_save_inteligencia: 'cs_inteligencia_mod',
    cs_save_sabiduria:    'cs_sabiduria_mod',
    cs_save_carisma:      'cs_carisma_mod',
  };

  function getNumberFromInput(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const raw = el.value ?? el.textContent;
    const n = parseInt(raw || '0', 10);
    return Number.isNaN(n) ? 0 : n;
  }

  function formatMod(n) {
    if (n > 0) return '+' + n;
    if (n < 0) return String(n);
    return '0';
  }

  function setDisplayAndClasses(baseId, total) {
    const display = document.getElementById('display_' + baseId);
    if (!display) return;
    display.textContent = formatMod(total);

    const posClass = 'mod-pos';
    const negClass = 'mod-neg';
    const zeroClass = 'mod-zero';

    display.classList.remove(posClass, negClass, zeroClass);
    if (total > 0) display.classList.add(posClass);
    else if (total < 0) display.classList.add(negClass);
    else display.classList.add(zeroClass);
  }

  function updateSkill(skillId) {
    const abilityId = skillAbilityMap[skillId];
    if (!abilityId) return;

    const abilityMod = getNumberFromInput(abilityId);
    const profBonus = getNumberFromInput('cs_proeficiencia');

    const profInput = document.getElementById('prof_' + skillId);
    const isProf = profInput && profInput.value === '1';

    let expertiseLevel = 0;
    const expBtn = document.querySelector('.skill-expertise-toggle[data-skill="' + skillId + '"]');
    if (isProf) {
      if (expBtn && expBtn.dataset.expertise === '1') {
        expertiseLevel = 2; // pericia -> doble proeficiencia
      } else {
        expertiseLevel = 1; // sólo proeficiencia
      }
    }

    const total = abilityMod + profBonus * expertiseLevel;

    const hiddenSkill = document.getElementById(skillId);
    if (hiddenSkill) {
      hiddenSkill.value = total;
    }

    setDisplayAndClasses(skillId, total);
  }

  function updateSave(saveId) {
    const abilityId = saveAbilityMap[saveId];
    if (!abilityId) return;

    const abilityMod = getNumberFromInput(abilityId);
    const profBonus = getNumberFromInput('cs_proeficiencia');

    const profInput = document.getElementById('prof_' + saveId);
    const isProf = profInput && profInput.value === '1';

    const total = abilityMod + (isProf ? profBonus : 0);

    const hiddenSave = document.getElementById(saveId);
    if (hiddenSave) {
      hiddenSave.value = total;
    }

    setDisplayAndClasses(saveId, total);
  }

  // --- Proeficiencia en habilidades ---
  document.querySelectorAll('.skill-prof-toggle[data-skill]').forEach((btn) => {
    const skillId = btn.dataset.skill;
    const profInput = document.getElementById('prof_' + skillId);
    const circle = btn.querySelector('.skill-prof-circle');

    // Estado inicial del circulito según el hidden
    if (profInput && profInput.value === '1' && circle) {
      circle.classList.add('skill-prof-active');
    }

    btn.addEventListener('click', () => {
      if (!profInput) return;

      const newVal = profInput.value === '1' ? '0' : '1';
      profInput.value = newVal;

      if (circle) {
        circle.classList.toggle('skill-prof-active', newVal === '1');
      }

      const expBtn = document.querySelector('.skill-expertise-toggle[data-skill="' + skillId + '"]');
      if (expBtn) {
        if (newVal !== '1') {
          // Si pierdes proeficiencia, escondemos y reseteamos Expertise
          expBtn.style.display = 'none';
          expBtn.dataset.expertise = '0';
          const expCircle = expBtn.querySelector('.skill-prof-circle');
          if (expCircle) {
            expCircle.classList.remove('skill-prof-active');
          }
        } else {
          // Al ganar proeficiencia, mostramos el botón de Expertise
          expBtn.style.display = 'inline-block';
        }
      }

      updateSkill(skillId);
    });
  });

  // --- Botón de Expertise ---
  document.querySelectorAll('.skill-expertise-toggle').forEach((btn) => {
    const skillId = btn.dataset.skill;
    const profInput = document.getElementById('prof_' + skillId);
    const circle = btn.querySelector('.skill-prof-circle');

    // Por defecto, expertise apagado
    btn.dataset.expertise = '0';

    // Visibilidad inicial según proeficiencia
    if (!profInput || profInput.value !== '1') {
      btn.style.display = 'none';
    } else {
      btn.style.display = 'inline-block';
    }

    btn.addEventListener('click', () => {
      // No se puede activar expertise sin proeficiencia
      if (!profInput || profInput.value !== '1') return;

      const isOn = btn.dataset.expertise === '1';
      const newState = isOn ? '0' : '1';
      btn.dataset.expertise = newState;

      if (circle) {
        circle.classList.toggle('skill-prof-active', newState === '1');
      }

      updateSkill(skillId);
    });
  });

  // --- Tiradas de salvación ---
  document.querySelectorAll('.save-prof-toggle').forEach((btn) => {
    const saveId = btn.dataset.save;
    const profInput = document.getElementById('prof_' + saveId);
    const circle = btn.querySelector('.skill-prof-circle');

    if (profInput && profInput.value === '1' && circle) {
      circle.classList.add('skill-prof-active');
    }

    btn.addEventListener('click', () => {
      if (!profInput) return;

      const newVal = profInput.value === '1' ? '0' : '1';
      profInput.value = newVal;

      if (circle) {
        circle.classList.toggle('skill-prof-active', newVal === '1');
      }

      updateSave(saveId);
    });
  });

  // --- Recalcular todo al cargar ---
  Object.keys(skillAbilityMap).forEach((skillId) => updateSkill(skillId));
  Object.keys(saveAbilityMap).forEach((saveId) => updateSave(saveId));
}

						
});

	
document.addEventListener('DOMContentLoaded', function () {
  if (typeof DND5_API === 'undefined') return;

  const claseSelect    = document.getElementById('clase');
  const subclaseSelect = document.getElementById('subclase');

  if (!claseSelect || !subclaseSelect) return;

  const currentClass    = claseSelect.dataset.current || '';
  const currentSubclass = subclaseSelect.dataset.current || '';

  // Helper AJAX hacia admin-ajax.php
  function ajaxRequest(action, extraData) {
    const formData = new FormData();
    formData.append('action', action);

    if (extraData) {
      Object.keys(extraData).forEach((key) => {
        formData.append(key, extraData[key]);
      });
    }

    return fetch(DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    }).then((response) => response.json());
  }

  function fillSelect(select, placeholder, list, currentValue) {
    select.innerHTML = '';

    const optPlaceholder = document.createElement('option');
    optPlaceholder.value = '';
    optPlaceholder.textContent = placeholder;
    select.appendChild(optPlaceholder);

    list.forEach((item) => {
      const opt = document.createElement('option');
      // AHORA usamos item.id como value
      opt.value = item.id || '';
      opt.textContent = item.name || '';
      if (currentValue && currentValue === opt.value) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
  }

  function loadSubclasses(classId, preselect) {
    if (!classId) {
      fillSelect(subclaseSelect, 'Selecciona subclase…', [], '');
      subclaseSelect.disabled = true;
      return;
    }

    subclaseSelect.disabled = true;
    subclaseSelect.innerHTML = '<option value="">Cargando subclases…</option>';

    // Seguimos usando el mismo parámetro 'class_index', pero contiene el ID de nuestra clase local
    ajaxRequest('drak_dnd5_get_subclasses', { class_index: classId })
      .then((res) => {
        if (!res || !res.success) {
          fillSelect(subclaseSelect, 'No hay subclases disponibles', [], '');
          subclaseSelect.disabled = true;
          return;
        }

        const subclasses = (res.data && res.data.subclasses) ? res.data.subclasses : [];
        fillSelect(
          subclaseSelect,
          subclasses.length ? 'Selecciona subclase…' : 'No hay subclases disponibles',
          subclasses,
          preselect || currentSubclass
        );
        subclaseSelect.disabled = subclasses.length === 0;
      })
      .catch(() => {
        fillSelect(subclaseSelect, 'Error al cargar subclases', [], '');
        subclaseSelect.disabled = true;
      });
  }

  // 1) Cargar clases al arrancar
  ajaxRequest('drak_dnd5_get_classes')
    .then((res) => {
      if (!res || !res.success) return;

      const classes = (res.data && res.data.classes) ? res.data.classes : [];
      fillSelect(
        claseSelect,
        classes.length ? 'Selecciona clase…' : 'No hay clases disponibles',
        classes,
        currentClass
      );

      if (currentClass) {
        loadSubclasses(currentClass, currentSubclass);
      }
    })
    .catch(() => {
      // Si falla, simplemente no llenamos el select
    });

  // 2) Cuando cambie la clase, recargar subclases
  claseSelect.addEventListener('change', function () {
    const selected = this.value || '';
    this.dataset.current = selected;
    loadSubclasses(selected, '');
  });
});
