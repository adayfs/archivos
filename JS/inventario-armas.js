(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('armaModal');
    const openBtn = document.getElementById('arma-principal-add');
    const closeBtn = document.querySelector('.close-arma-popup');
    const applyBtn = document.getElementById('arma-aplicar');
    const selector = document.getElementById('arma-selector');
    const preview = document.getElementById('arma-preview');
    const previewDamage = document.getElementById('arma-damage');
    const previewDamageType = document.getElementById('arma-damage-type');
    const previewWeight = document.getElementById('arma-weight');
    const previewProps = document.getElementById('propiedades-arma-lista');
    const magicCheckbox = document.getElementById('arma-magica');
    const attuneCheckbox = document.getElementById('arma-attune');
    const display = document.getElementById('arma-principal-display');

    const deleteBtn = document.querySelector('.arma-principal-remove');
    const deleteModal = document.getElementById('armaModalEliminar');
    const deleteConfirm = document.getElementById('confirmarEliminarArma');
    const deleteCancel = deleteModal?.querySelector('.close-modal');

    if (!modal || !openBtn || !selector || !applyBtn || !display) return;

    const hiddenFields = {
      name: document.getElementById('arma_name'),
      slug: document.getElementById('arma_slug'),
      category: document.getElementById('arma_category'),
      damage_dice: document.getElementById('arma_damage_dice'),
      damage_type: document.getElementById('arma_damage_type'),
      weight: document.getElementById('arma_weight'),
      properties: document.getElementById('arma_properties'),
      es_magica: document.getElementById('arma_es_magica'),
      requiere_attunement: document.getElementById('arma_requiere_attunement'),
      descripcion: document.getElementById('arma_descripcion'),
    };

    const translations = {
      Axespear: 'Lanza-hacha',
      'Barge Pole': 'Pértiga',
      Battleaxe: 'Hacha de batalla',
      'Bladed Scarf': 'Bufanda afilada',
      Blowgun: 'Cerbatana',
      Blunderbuss: 'Trabuco',
      Bolas: 'Bolas',
      'Chain Hook': 'Gancho de cadena',
      Chakram: 'Chakram',
      'Climbing Adze': 'Azada de escalada',
      'Clockwork Crossbow': 'Ballesta de relojería',
      Club: 'Garrote',
      'Club Shield': 'Escudo-garrote',
      'Crossbow, hand': 'Ballesta de mano',
      'Crossbow, heavy': 'Ballesta pesada',
      'Crossbow, light': 'Ballesta ligera',
      Dagger: 'Daga',
      Dart: 'Dardo',
      'Double Axe': 'Hacha doble',
      'Dwarven Arquebus': 'Arcabuz enano',
      'Dwarven Axe': 'Hacha enana',
      'Dwarven Revolving Musket': 'Mosquete giratorio enano',
      'Elven Dueling Blade': 'Hoja de duelo élfica',
      Flail: 'Mangual',
      Glaive: 'Alabarda',
      'Granite Fist': 'Puño de granito',
      Greataxe: 'Gran hacha',
      Greatclub: 'Gran garrote',
      Greatsword: 'Gran espada',
      Halberd: 'Alabarda',
      'Hand Trebuchet': 'Trabuquete de mano',
      Handaxe: 'Hacha de mano',
      Javelin: 'Jabalina',
      'Joining Dirks': 'Dagas gemelas',
      Khopesh: 'Jopesh',
      Lance: 'Lanza',
      'Light hammer': 'Martillo ligero',
      'Light Pick': 'Pico ligero',
      Longbow: 'Arco largo',
      Longsword: 'Espada larga',
      Mace: 'Maza',
      Maul: 'Mazo',
      Morningstar: 'Estrella del alba',
      Musket: 'Mosquete',
      Net: 'Red',
      Pike: 'Pica',
      Pistol: 'Pistola',
      'Pneumatic War Pick': 'Pico de guerra neumático',
      Quarterstaff: 'Bastón',
      Rapier: 'Estoque',
    };

    let weaponCache = [];
    let fetchPromise = null;

    function translatedName(name) {
      return translations[name] || name;
    }

    function fetchWeapons() {
      if (fetchPromise) return fetchPromise;
      fetchPromise = fetch('https://api.open5e.com/weapons/')
        .then((response) => response.json())
        .then((data) => {
          weaponCache = data?.results || [];
          populateSelector();
        })
        .catch((error) => {
          console.error('Error al cargar armas', error);
          selector.innerHTML = '<option value="">No se pudieron cargar las armas</option>';
        });
      return fetchPromise;
    }

    function populateSelector() {
      selector.innerHTML = '<option value="">Selecciona un arma</option>';
      weaponCache.forEach((weapon) => {
        const option = document.createElement('option');
        option.value = weapon.slug;
        option.textContent = translatedName(weapon.name);
        selector.appendChild(option);
      });
    }

    function renderPreview(weapon) {
      if (!weapon || !preview) return;
      preview.style.display = 'block';
      previewDamage.textContent = weapon.damage_dice || '—';
      previewDamageType.textContent = weapon.damage_type || '—';
      previewWeight.textContent = weapon.weight ?? '—';
      if (previewProps) {
        previewProps.innerHTML = '';
        (weapon.properties || []).forEach((prop) => {
          const li = document.createElement('li');
          li.textContent = prop;
          previewProps.appendChild(li);
        });
      }
    }

    function setHiddenField(key, value) {
      if (hiddenFields[key]) {
        hiddenFields[key].value = value ?? '';
      }
    }

    function applySelection(weapon) {
      if (!weapon) return;
      const name = translatedName(weapon.name);
      display.innerHTML = `<p><strong>${name}</strong> (${weapon.damage_dice || '—'} ${weapon.damage_type || ''})</p>`;

      setHiddenField('name', weapon.name);
      setHiddenField('slug', weapon.slug);
      setHiddenField('category', weapon.category);
      setHiddenField('damage_dice', weapon.damage_dice);
      setHiddenField('damage_type', weapon.damage_type);
      setHiddenField('weight', weapon.weight);
      setHiddenField('properties', Array.isArray(weapon.properties) ? weapon.properties.join(', ') : '');
      setHiddenField('descripcion', weapon.desc || '');
      setHiddenField('es_magica', magicCheckbox?.checked ? '1' : '0');
      setHiddenField('requiere_attunement', attuneCheckbox?.checked ? '1' : '0');
    }

    function clearWeaponSelection() {
      display.innerHTML = '<p>No hay arma asignada</p>';
      Object.keys(hiddenFields).forEach((key) => setHiddenField(key, ''));
      selector.value = '';
      if (preview) {
        preview.style.display = 'none';
        if (previewProps) previewProps.innerHTML = '';
      }
      if (magicCheckbox) magicCheckbox.checked = false;
      if (attuneCheckbox) attuneCheckbox.checked = false;
    }

    openBtn.addEventListener('click', () => {
      modal.style.display = 'flex';
      fetchWeapons();
    });

    closeBtn?.addEventListener('click', () => {
      modal.style.display = 'none';
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });

    selector.addEventListener('change', () => {
      const weapon = weaponCache.find((w) => w.slug === selector.value);
      if (!weapon) {
        preview.style.display = 'none';
        return;
      }
      renderPreview(weapon);
    });

    applyBtn.addEventListener('click', () => {
      const weapon = weaponCache.find((w) => w.slug === selector.value);
      if (!weapon) return;
      applySelection(weapon);
      modal.style.display = 'none';
    });

    if (deleteBtn && deleteModal && deleteConfirm && deleteCancel) {
      deleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'flex';
      });

      deleteCancel.addEventListener('click', () => {
        deleteModal.style.display = 'none';
      });

      deleteModal.addEventListener('click', (event) => {
        if (event.target === deleteModal) {
          deleteModal.style.display = 'none';
        }
      });

      deleteConfirm.addEventListener('click', () => {
        clearWeaponSelection();
        deleteModal.style.display = 'none';
      });
    }

    fetchWeapons();
  });
})();
