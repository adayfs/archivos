document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('armaModal');
  const abrirBtn = document.getElementById('arma-principal-add');
  const cerrarBtn = document.querySelector('.close-arma-popup');
const botonEliminar = document.querySelector('.arma-principal-remove');
const modalEliminar = document.getElementById('armaModalEliminar');
const confirmarEliminar = document.getElementById('confirmarEliminarArma');
const cerrarEliminar = modalEliminar.querySelector('.close-modal');

  if (abrirBtn && modal) {
    abrirBtn.addEventListener('click', function () {
      modal.style.display = 'flex';
    });
  }

  if (cerrarBtn && modal) {
    cerrarBtn.addEventListener('click', function () {
      modal.style.display = 'none';
});
  }
	
if (botonEliminar && modalEliminar && confirmarEliminar && cerrarEliminar) {
  botonEliminar.addEventListener("click", () => {
    modalEliminar.style.display = "block";
  });

  cerrarEliminar.addEventListener("click", () => {
    modalEliminar.style.display = "none";
  });
	

  confirmarEliminar.addEventListener("click", () => {
    setHidden("arma_principal", "");
    setHidden("slug", "");
    setHidden("categoria", "");
    setHidden("documento", "");
    setHidden("coste", "");
    setHidden("danio", "");
    setHidden("tipo_danio", "");
    setHidden("peso", "");
    setHidden("propiedades", "");
    setHidden("es_magica", "");
    setHidden("requiere_attunement", "");

    const slot = document.querySelector('.slot.arma-principal input');
    if (slot) {
      slot.value = "";
    }

    modalEliminar.style.display = "none";
  });
}

  const selector = document.getElementById('arma-selector');
  const preview = document.getElementById('arma-preview');
  const display = document.getElementById('arma-principal-display');
  const btnAplicar = document.getElementById('arma-aplicar');

  const traduccionesArmas = {
    "Axespear": "Lanza-hacha",
"Barge Pole": "Pértiga",
"Battleaxe": "Hacha de batalla",
"Bladed Scarf": "Bufanda afilada",
"Blowgun": "Cerbatana",
"Blunderbuss": "Trabuco",
"Bolas": "Bolas",
"Chain Hook": "Gancho de cadena",
"Chakram": "Chakram",
"Climbing Adze": "Azada de escalada",
"Clockwork Crossbow": "Ballesta de relojería",
"Club": "Garrote",
"Club Shield": "Escudo-garrote",
"Crossbow, hand": "Ballesta de mano",
"Crossbow, heavy": "Ballesta pesada",
"Crossbow, light": "Ballesta ligera",
"Dagger": "Daga",
"Dart": "Dardo",
"Double Axe": "Hacha doble",
"Dwarven Arquebus": "Arcabuz enano",
"Dwarven Axe": "Hacha enana",
"Dwarven Revolving Musket": "Mosquete giratorio enano",
"Elven Dueling Blade": "Hoja de duelo élfica",
"Flail": "Mangual",
"Glaive": "Alabarda",
"Granite Fist": "Puño de granito",
"Greataxe": "Gran hacha",
"Greatclub": "Gran garrote",
"Greatsword": "Gran espada",
"Halberd": "Alabarda",
"Hand Trebuchet": "Trabuquete de mano",
"Handaxe": "Hacha de mano",
"Javelin": "Jabalina",
"Joining Dirks": "Dagas gemelas",
"Khopesh": "Jopesh",
"Lance": "Lanza",
"Light hammer": "Martillo ligero",
"Light Pick": "Pico ligero",
"Longbow": "Arco largo",
"Longsword": "Espada larga",
"Mace": "Maza",
"Maul": "Mazo",
"Morningstar": "Estrella del alba",
"Musket": "Mosquete",
"Net": "Red",
"Pike": "Pica",
"Pistol": "Pistola",
"Pneumatic War Pick": "Pico de guerra neumático",
"Quarterstaff": "Bastón",
"Rapier": "Estoque"
  };

  let armas = [];

  fetch('https://api.open5e.com/weapons/')
    .then(r => r.json())
    .then(data => {
      armas = data.results;
      selector.innerHTML = '<option value="">Selecciona un arma</option>';
      armas.forEach(a => {
        const opt = document.createElement('option');
        const nombreTraducido = traduccionesArmas[a.name] || a.name;
        opt.value = a.slug;
        opt.textContent = nombreTraducido;
        selector.appendChild(opt);
      });
    });

  selector.addEventListener('change', function () {
    const arma = armas.find(a => a.slug === this.value);
    if (!arma) return;
    preview.style.display = 'block';

    document.getElementById('arma-damage').textContent = arma.damage_dice;
    document.getElementById('arma-damage-type').textContent = arma.damage_type;
    document.getElementById('arma-weight').textContent = arma.weight;

    const propiedadesLista = document.getElementById('propiedades-arma-lista');
    propiedadesLista.innerHTML = '';
    arma.properties.forEach(prop => {
      const li = document.createElement('li');
      li.textContent = prop;
      propiedadesLista.appendChild(li);
    });
  });

  btnAplicar.addEventListener('click', function () {
    const arma = armas.find(a => a.slug === selector.value);
	  const form = document.getElementById("mainpack-formulario");

function crearInputOculto(nombre, valor) {
  let input = document.querySelector(`input[name="arma_principal[${nombre}]"]`);
  if (!input) {
    input = document.createElement("input");
    input.type = "hidden";
    input.name = `arma_principal[${nombre}]`;
    form.appendChild(input);
  }
  input.value = valor;
}

    if (!arma) return;

    const nombreTraducido = traduccionesArmas[arma.name] || arma.name;

    display.innerHTML = `
      <p><strong>${nombreTraducido}</strong> (${arma.damage_dice} ${arma.damage_type})</p>
    `;

    const setHidden = (name, value) => {
      let field = document.querySelector(`[name="arma_principal[${name}]"]`);
      if (field) field.value = value || '';
    };
setHidden("name", arma.name);
  setHidden("slug", arma.slug);
setHidden("category", arma.category);
setHidden("cost", arma.cost);
setHidden("damage_dice", arma.damage_dice);
setHidden("damage_type", arma.damage_type);
setHidden("weight", arma.weight);
setHidden("properties", arma.properties.join(", "));
setHidden("es_magica", esMagica);
setHidden("require_attunement", requiereAttunement);

document.getElementById('arma_name').value = arma.name || '';
document.getElementById('arma_slug').value = arma.slug || '';
document.getElementById('arma_category').value = arma.category || '';
document.getElementById('arma_damage_dice').value = arma.damage || '';
document.getElementById('arma_damage_type').value = arma.damage_type || '';
document.getElementById('arma_weight').value = arma.weight || '';
document.getElementById('arma_properties').value = Array.isArray(arma.properties) ? arma.properties.join(', ') : '';
document.getElementById('arma_es_magica').value = document.getElementById('arma-magica').checked ? '1' : '0';
document.getElementById('arma_requiere_attunement').value = document.getElementById('arma-attune').checked ? '1' : '0';
document.getElementById('arma_descripcion').value = arma.descripcion || '';

    modal.style.display = 'none';
  });
});