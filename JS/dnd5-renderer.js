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
  let tagLinkResolver = null;

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
    if (Array.isArray(entry.colStyles_es) && entry.colStyles_es.length) {
      replacements.colStyles = entry.colStyles_es;
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
          const tds = cells
            .map((cell, idx) => {
              const colClass = colStyles[idx] ? ` class="${escapeHtml(colStyles[idx])}"` : '';
              return `<td${colClass}>${renderTableCell(cell)}</td>`;
            })
            .join('');
          return `<tr>${tds}</tr>`;
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
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function slugify(value) {
    return String(value || '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function resolveTagLink(tag, parts, label) {
    if (typeof tagLinkResolver === 'function') {
      const resolved = tagLinkResolver({ tag, parts, label });
      if (resolved && resolved.href) {
        return resolved;
      }
    }

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

  function setTagLinkResolver(resolver) {
    tagLinkResolver = typeof resolver === 'function' ? resolver : null;
  }

  window.DND5Render = {
    renderEntries,
    renderEntryNode,
    localizeEntryNode,
    getLocalizedArrayFrom,
    getLocalizedTextFrom,
    format5eText,
    render5eTag,
    renderTableCell,
    setTagLinkResolver,
  };
})();
