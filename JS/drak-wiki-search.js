(() => {
  const cfg = window.drakWikiSearchData || {};
  const input = document.getElementById('drak-wiki-search-input');
  const suggestions = document.querySelector('.drak-wiki-search__suggestions');

  if (!input || !suggestions || !cfg.ajaxUrl || !cfg.ajaxNonce || !cfg.wikiSection || !cfg.campaignId) {
    return;
  }

  const minChars = parseInt(cfg.minChars, 10) || 2;

  const render = (items) => {
    suggestions.innerHTML = '';
    if (!items || !items.length) {
      const p = document.createElement('p');
      p.className = 'drak-wiki-search__empty';
      p.textContent = 'Sin resultados…';
      suggestions.appendChild(p);
      return;
    }

    const frag = document.createDocumentFragment();
    items.forEach((item) => {
      const a = document.createElement('a');
      a.className = 'drak-wiki-search__item';
      a.href = item.permalink;
      const title = document.createElement('strong');
      title.textContent = item.title || '';
      const excerpt = document.createElement('span');
      excerpt.textContent = item.excerpt || '';
      a.appendChild(title);
      a.appendChild(excerpt);
      frag.appendChild(a);
    });
    suggestions.appendChild(frag);
  };

  let timer = null;
  const debounce = (fn, delay = 300) => {
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  };

  const search = debounce((term) => {
    if (!term || term.length < minChars) {
      suggestions.innerHTML = '';
      return;
    }
    const body = new FormData();
    body.append('action', 'drak_wiki_live_search');
    body.append('nonce', cfg.ajaxNonce);
    body.append('term', term);
    body.append('wiki_section', cfg.wikiSection);
    body.append('campaign_id', cfg.campaignId);

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body,
    })
      .then((res) => res.json())
      .then((json) => {
        if (json && json.success) {
          render(json.data || []);
        } else {
          render([]);
        }
      })
      .catch(() => {
        render([]);
      });
  }, 350);

  input.addEventListener('input', (ev) => {
    search(ev.target.value.trim());
  });

  input.addEventListener('focus', () => {
    const val = input.value.trim();
    if (val.length >= minChars) {
      search(val);
    }
  });

  document.addEventListener('click', (ev) => {
    if (ev.target === input || suggestions.contains(ev.target)) {
      return;
    }
    suggestions.innerHTML = '';
  });
})();
