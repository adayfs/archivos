(function () {
  const config = window.SPELL_SEARCH_CONFIG || { classes: [], labels: {} };
  const ajaxUrl = (config && config.ajax_url) || (window.DND5_API && window.DND5_API.ajax_url) || '';
  const label = (key, fallback) => (config.labels && config.labels[key]) || fallback;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-spell-search]').forEach(initSpellSearchModule);
  });

  function initSpellSearchModule(root) {
    const input = root.querySelector('[data-spell-search-input]');
    const form = root.querySelector('[data-spell-search-form]');
    const suggestions = root.querySelector('[data-spell-search-suggestions]');
    const filters = root.querySelector('[data-spell-search-classes]');
    const modal = root.querySelector('[data-spell-search-modal]');
    const resultsBody = root.querySelector('[data-spell-search-results]');

    if (!input || !form || !modal || !resultsBody) return;
    buildClassFilters(filters);

    if (input && label('placeholder')) {
      input.placeholder = label('placeholder');
    }

    let debounceTimer = null;
    input.addEventListener('input', () => {
      const value = input.value.trim();
      if (!value) {
        hideSuggestions(suggestions);
        return;
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        requestSpells({ query: value, classes: getSelectedClasses(root), limit: 5 })
          .then((response) => {
            renderSuggestions(suggestions, response.spells || [], (name) => {
              input.value = name;
              runSearch(root, name, resultsBody, modal);
            });
          })
          .catch(() => {
            hideSuggestions(suggestions);
          });
      }, 220);
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      runSearch(root, input.value.trim(), resultsBody, modal);
    });

    modal.querySelectorAll('[data-spell-search-close]').forEach((btn) => {
      btn.addEventListener('click', () => closeModal(modal));
    });
  }

  function buildClassFilters(container) {
    if (!container || !Array.isArray(config.classes)) return;
    container.innerHTML = config.classes
      .map((cls, index) => {
        const id = `spell-search-class-${cls.id || index}`;
        return `
          <label for="${id}">
            <input type="checkbox" id="${id}" value="${cls.id}">
            <span>${cls.short || cls.name}</span>
          </label>`;
      })
      .join('');
  }

  function getSelectedClasses(root) {
    return Array.from(root.querySelectorAll('[data-spell-search-classes] input:checked'))
      .map((input) => input.value)
      .filter(Boolean);
  }

  function requestSpells({ query, classes, limit }) {
    if (!ajaxUrl) return Promise.reject(new Error('No AJAX URL'));
    const payload = new URLSearchParams();
    payload.append('action', 'drak_dnd5_search_spells');
    payload.append('limit', limit || 25);
    if (query) payload.append('q', query);
    (classes || []).forEach((cls) => payload.append('classes[]', cls));

    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          throw new Error('invalid response');
        }
        return json.data || { spells: [] };
      });
  }

  function renderSuggestions(container, spells, onSelect) {
    if (!container) return;
    if (!spells.length) {
      hideSuggestions(container);
      return;
    }
    container.innerHTML = spells
      .map((spell) => `<button type="button" data-spell-suggestion>${escapeHtml(spell.name || '')}</button>`)
      .join('');
    container.hidden = false;
    container.querySelectorAll('[data-spell-suggestion]').forEach((btn, index) => {
      const spell = spells[index];
      btn.addEventListener('click', () => {
        hideSuggestions(container);
        if (spell && typeof onSelect === 'function') {
          onSelect(spell.name);
        }
      });
    });
  }

  function hideSuggestions(container) {
    if (!container) return;
    container.hidden = true;
    container.innerHTML = '';
  }

  function runSearch(root, query, resultsBody, modal) {
    const classes = getSelectedClasses(root);
    if (!query && !classes.length) {
      resultsBody.innerHTML = '<p>Introduce un nombre o selecciona una clase.</p>';
      openModal(modal);
      return;
    }

    requestSpells({ query, classes, limit: 40 })
      .then((response) => {
        renderResults(resultsBody, response.spells || [], response.total || 0, query, classes);
        openModal(modal);
      })
      .catch(() => {
        resultsBody.innerHTML = `<p>${escapeHtml(label('error', 'No se pudo completar la búsqueda.'))}</p>`;
        openModal(modal);
      });
  }

  function renderResults(container, spells, total, query, classes) {
    if (!container) return;
    if (!spells.length) {
      container.innerHTML = `<p>${escapeHtml(label('empty', 'Sin resultados.'))}</p>`;
      return;
    }

    const queryInfo = query ? `<p class="spell-search__status">Resultados para "${escapeHtml(query)}" (${total || spells.length})</p>` : '';
    container.innerHTML = `
      ${queryInfo}
      <div class="spell-search__results">
        ${spells.map(renderResultCard).join('')}
      </div>
    `;
  }

  function renderResultCard(spell) {
    const levelLabel = spell.level === 0 ? 'Truco' : `Nivel ${spell.level}`;
    const school = spell.school ? ` · ${escapeHtml(spell.school)}` : '';
    const classes = (spell.classes || [])
      .map((cls) => cls.name)
      .filter(Boolean)
      .join(', ');
    const meta = [levelLabel + school, spell.source].filter(Boolean).join(' · ');
    const classesLine = classes ? `<p class="spell-search__result-meta">${escapeHtml(classes)}</p>` : '';
    const entriesHtml = renderEntries(spell.entries || []);
    const detail = entriesHtml || renderParagraphs(spell.paragraphs) || (spell.preview ? `<p>${escapeHtml(spell.preview)}</p>` : '');

    return `
      <article class="spell-search__result">
        <header>
          <h4>${escapeHtml(spell.name || 'Conjuro')}</h4>
          <small>${escapeHtml(meta)}</small>
        </header>
        ${classesLine}
        <div class="spell-search__result-preview">${detail}</div>
      </article>
    `;
  }

  function renderParagraphs(paragraphs) {
    if (!Array.isArray(paragraphs) || !paragraphs.length) {
      return '';
    }
    return paragraphs
      .map((text) => `<p>${escapeHtml(text)}</p>`)
      .join('');
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

    const entryEs = getLocalizedTextFrom(entry, 'entry');
    if (entryEs) {
      replacements.entry = entryEs;
      shouldClone = true;
    }

    const captionEs = getLocalizedTextFrom(entry, 'caption');
    if (captionEs) {
      replacements.caption = captionEs;
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
      const rows = getLocalizedArrayFrom(entry, 'rows');
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
      return `<span class="dnd5-tag">${label}</span>`;
    }

    return label;
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
  }

  function escapeHtml(value) {
    return (value || '')
      .toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
})();
