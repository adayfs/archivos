(function () {
  if (window.drakClassReferenceLoaded) return;
  window.drakClassReferenceLoaded = true;

  const classReferenceState = window.classReferenceState || {
    container: null,
    cache: new Map(),
    currentKey: '',
  };

  window.classReferenceState = classReferenceState;

  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function initClassReferenceModule() {
    classReferenceState.container = document.getElementById('class-reference-module');
    if (!classReferenceState.container) return;
    const body = classReferenceState.container.querySelector('.class-reference-module__body');
    if (body) {
      body.innerHTML = '<p class="class-reference-module__hint">Selecciona una clase para ver su progresión.</p>';
    }
  }

  function refreshClassReferenceModule(context = {}) {
    if (!classReferenceState.container) return;
    const target = classReferenceState.container.querySelector('.class-reference-module__body');
    if (!target) return;

    const classId = (context.classId || '').trim();
    if (!classId) {
      target.innerHTML = '<p class="class-reference-module__hint">Selecciona una clase en la hoja para mostrar los datos.</p>';
      classReferenceState.currentKey = '';
      return;
    }

    const theoryKey = JSON.stringify(context.esotericTheories || []);
    const key = [classId, context.subclassId || '', context.level || '', theoryKey].join('|');
    const preloaded = context.prefetchedReference;
    if (
      preloaded &&
      preloaded.class_id === classId &&
      (!context.subclassId ||
        !preloaded.subclass?.id ||
        preloaded.subclass.id === context.subclassId)
    ) {
      const preloadedIds = Array.isArray(preloaded.esoteric_theories)
        ? preloaded.esoteric_theories.map((item) => item.id)
        : [];
      const preloadedKey = JSON.stringify(preloadedIds);
      if (preloadedKey === theoryKey) {
        classReferenceState.cache.set(key, preloaded);
      }
    }
    if (classReferenceState.cache.has(key)) {
      classReferenceState.currentKey = key;
      renderClassReference(classReferenceState.cache.get(key), context);
      return;
    }

    classReferenceState.currentKey = key;
    target.innerHTML = '<p>Cargando referencia de clase...</p>';

    fetchClassReference(classId, context.subclassId, context.esotericTheories)
      .then((reference) => {
        classReferenceState.cache.set(key, reference);
        if (classReferenceState.currentKey === key) {
          renderClassReference(reference, context);
        }
      })
      .catch((error) => {
        console.error('[ClassRef] Error al obtener la referencia de clase.', error);
        target.innerHTML = '<p class="class-reference-module__hint">No se pudo cargar la referencia de clase.</p>';
      });
  }

  function fetchClassReference(classId, subclassId, theories) {
    if (!window.DND5_API?.ajax_url) {
      return Promise.reject(new Error('DND5_API no disponible'));
    }
    const payload = new URLSearchParams({
      action: 'drak_dnd5_get_class_reference',
      class_id: classId,
    });
    if (subclassId) {
      payload.append('subclass_id', subclassId);
    }
    if (Array.isArray(theories) && theories.length) {
      payload.append('apothecary_theories', JSON.stringify(theories));
    }

    return fetch(window.DND5_API.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json?.success || !json.data?.reference) {
          console.error('[ClassRef] Respuesta inválida del servidor', json);
          const debugMessage = json?.data?.debug || json?.data?.message || 'Respuesta inválida';
          throw new Error(debugMessage);
        }
        return json.data.reference;
      });
  }

  function renderClassReference(reference, context) {
    const target = classReferenceState.container?.querySelector('.class-reference-module__body');
    if (!target) return;
    const level = Math.max(1, context.level || 1);
    const slots = reference.slot_progression?.[level] || {};
    const cantripCount = reference.cantrip_progression?.[level] ?? '—';

    let html = `
      <div class="class-reference-module__summary">
        <p><strong>Clase:</strong> ${escapeHtml(reference.class_meta?.name || reference.class_id)}</p>
        <p><strong>Nivel actual:</strong> ${level}</p>
        <p><strong>Cantrips conocidos:</strong> ${cantripCount}</p>
      </div>
    `;
    html += formatSlotList(slots);

    if (reference.subclass?.meta?.name) {
      // Subclase oculta por UX
    }

    const groups = (Array.isArray(reference.table_groups) ? reference.table_groups : []).filter(
      (group) => !shouldSkipTableGroup(group)
    );
    html += groups.map(renderClassReferenceGroup).join('');

    html += renderAlwaysPreparedSection(reference.class_prepared_spells, 'Conjuros siempre preparados de la clase', level);
    if (Array.isArray(reference.esoteric_theories) && reference.esoteric_theories.length) {
      html += renderEsotericTheorySummary(reference.esoteric_theories);
    }

    target.innerHTML = html;
  }

  function renderClassReferenceGroup(group) {
    if (!group || !group.rows?.length) return '';
    const title = group.title ? `<h4>${escapeHtml(group.title)}</h4>` : '';
    const subtitle = group.subtitle ? `<p>${escapeHtml(group.subtitle)}</p>` : '';
    const header = group.colLabels?.length
      ? `<thead><tr>${group.colLabels.map((label) => `<th>${escapeHtml(label || '')}</th>`).join('')}</tr></thead>`
      : '';
    const rows = group.rows
      .map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`)
      .join('');

    return `
      ${title}
      ${subtitle}
      <table>
        ${header}
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  function renderAlwaysPreparedSection(spellMap, title, characterLevel = 20) {
    if (!spellMap) return '';
    const levels = Object.keys(spellMap)
      .map((lvl) => parseInt(lvl, 10))
      .filter((lvl) => !Number.isNaN(lvl) && lvl <= characterLevel)
      .sort((a, b) => a - b);
    if (!levels.length) return '';

    const rows = levels
      .map((lvl) => {
        const spells = spellMap[lvl] || [];
        const list = spells
          .map((spell) => {
            const name = escapeHtml(spell.name || '');
            const source = spell.source ? `<small>${escapeHtml(spell.source)}</small>` : '';
            return `<li>${name} ${source}</li>`;
          })
          .join('');
        return `
          <div class="class-reference-module__auto">
            <strong>Nivel ${lvl}</strong>
            <ul>${list}</ul>
          </div>
        `;
      })
      .join('');

    return `
      <h4>${escapeHtml(title)}</h4>
      ${rows}
    `;
  }

  function renderEsotericTheorySummary(theories) {
    const items = theories
      .map((theory) => {
        const requirement = theory.level ? `Nivel ${theory.level}+` : 'Sin requisito';
        const source = theory.source ? ` · ${escapeHtml(theory.source)}` : '';
        return `<li><strong>${escapeHtml(theory.name || theory.id)}</strong> <small>${escapeHtml(requirement)}${source}</small></li>`;
      })
      .join('');
    return `
      <div class="class-reference-module__theories">
        <h4>Teorías esotéricas seleccionadas</h4>
        <ul>${items}</ul>
      </div>
    `;
  }

  function formatSlotList(slots) {
    const entries = Object.entries(slots || {})
      .map(([lvl, count]) => [parseInt(lvl, 10), count])
      .filter(([lvl, count]) => !Number.isNaN(lvl) && count > 0)
      .sort((a, b) => a[0] - b[0]);
    if (!entries.length) return '';
    const items = entries.map(([lvl, count]) => `<li>Nivel ${lvl}: ${count}</li>`).join('');
    return `<div class="class-reference-module__slots"><p><strong>Espacios de conjuro</strong></p><ul>${items}</ul></div>`;
  }

  function shouldSkipTableGroup(group) {
    if (!group) return true;
    const title = (group.title || '').toLowerCase();
    if (title.includes('spell slots per spell level')) {
      return true;
    }
    if (Array.isArray(group.colLabels) && group.colLabels.some((label) => /spells/i.test(label))) {
      return true;
    }
    return false;
  }

  window.initClassReferenceModule = initClassReferenceModule;
  window.refreshClassReferenceModule = refreshClassReferenceModule;
})();
