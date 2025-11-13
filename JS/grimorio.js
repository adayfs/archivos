(function () {
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof GRIMORIO_DATA === 'undefined') return;

    initSlots();
    initSpells();
  });

  const slotSaveTimers = {};

  function initSlots() {
    document.querySelectorAll('.grimorio-slot-column').forEach((column) => {
      const level = column.dataset.level;
      const hidden = column.querySelector(`input[name="grimorio_slots_used[${level}]"]`);
      if (!hidden) return;

      const checkboxes = column.querySelectorAll('.grimorio-slot-toggle');
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          const used = Array.from(checkboxes).filter((cb) => cb.checked).length;
          hidden.value = used;
          scheduleSlotSave(level, used);
        });
      });
    });
  }

  function scheduleSlotSave(level, used) {
    if (!GRIMORIO_DATA.nonce) return;
    if (slotSaveTimers[level]) {
      clearTimeout(slotSaveTimers[level]);
    }

    slotSaveTimers[level] = setTimeout(() => {
      persistSlot(level, used);
    }, 400);
  }

  function persistSlot(level, used) {
    const payload = new URLSearchParams({
      action: 'drak_dnd5_save_spell_slots',
      nonce: GRIMORIO_DATA.nonce,
      post_id: GRIMORIO_DATA.post_id,
      level,
      value: used,
    });

    fetch(GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    }).catch(() => {
      console.warn('No se pudo guardar el estado de los slots de conjuro.');
    });
  }

  function initSpells() {
    if (!GRIMORIO_DATA.class_id) {
      markSpellSelectsUnavailable('Selecciona una clase en la hoja de personaje.');
      return;
    }

    fetch(GRIMORIO_DATA.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: new URLSearchParams({
        action: 'drak_dnd5_get_spells',
        class_id: GRIMORIO_DATA.class_id,
      }),
    })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json || !json.success) {
          markSpellSelectsUnavailable('No se pudieron cargar los conjuros.');
          return;
        }
        const spells = json.data?.spells || [];
        const grouped = groupByLevel(spells);
        fillSpellSelects(grouped);
      })
      .catch(() => {
        markSpellSelectsUnavailable('Error de conexión al cargar conjuros.');
      });
  }

  function groupByLevel(spells) {
    return spells.reduce((acc, spell) => {
      const lvl = typeof spell.level === 'number' ? spell.level : 0;
      acc[lvl] = acc[lvl] || [];
      acc[lvl].push(spell);
      return acc;
    }, {});
  }

  function fillSpellSelects(grouped) {
    document.querySelectorAll('.grimorio-spell-select').forEach((select) => {
      const level = parseInt(select.dataset.level || '0', 10);
      const list = grouped[level] || [];
      const currentValue = select.dataset.current || select.value || '';

      while (select.options.length > 1) {
        select.remove(1);
      }

      list.forEach((spell) => {
        const option = document.createElement('option');
        option.value = spell.name;
        option.textContent = `${spell.name} (${spell.source})`;
        select.appendChild(option);
      });

      if (currentValue) {
        select.value = currentValue;
      }
    });
  }

  function markSpellSelectsUnavailable(message) {
    document.querySelectorAll('.grimorio-spell-select').forEach((select) => {
      select.innerHTML = `<option>${message}</option>`;
      select.disabled = true;
    });
  }
})();
