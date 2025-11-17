(function () {
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

  function getLocalizedArrayFrom(obj, key) {
    if (!obj || typeof obj !== 'object') return Array.isArray(obj) ? obj : [];
    const esKey = `${key}_es`;
    if (Array.isArray(obj[esKey]) && obj[esKey].length) {
      return obj[esKey];
    }
    return Array.isArray(obj[key]) ? obj[key] : [];
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

    const entryText = getLocalizedTextFrom(entry, 'entry');
    if (entryText) {
      replacements.entry = entryText;
      shouldClone = true;
    }

    const captionText = getLocalizedTextFrom(entry, 'caption');
    if (captionText) {
      replacements.caption = captionText;
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
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  window.DND5Render = {
    renderEntries,
    renderEntryNode,
    localizeEntryNode,
    getLocalizedArrayFrom,
    getLocalizedTextFrom,
    format5eText,
    render5eTag,
  };
})();
