# Módulo de Inventario de Personaje

## 1. Objetivo y alcance
- Página dedicada (`PHP/page-inventario-personaje.php`) que permite a cada personaje gestionar su mochila, monedas y arma principal.
- Sirve como puente entre la vista de la hoja y el grimorio: reutiliza el mismo slug que llega vía `personaje_slug` y arma la navegación hacia `/hoja-personaje/{slug}`, `/inventario/{slug}` y `/grimorio/{slug}`.
- Requiere un personaje válido; si no lo encuentra, aborta con un mensaje y no expone la UI.

## 2. Flujo de renderizado
### 2.1 Plantilla
- Carga `get_header()` y `get_footer()`.
- Imprime avatar del personaje, botones de navegación y la salida de `renderizar_inventario_personaje($personaje->ID)`.
- Inyecta el marcado de los modales:
  * Formulario principal para añadir objetos a un slot.
  * Modal secundario para eliminar objetos de un slot existente.
  * Las ventanas adicionales (oro y arma) se imprimen dentro del renderizador PHP.

### 2.2 `renderizar_inventario_personaje($post_id)` (`PHP/functions.php`)
1. Inicia un buffer (`ob_start()`) y retorna la cadena completa con `ob_get_clean()`.
2. **Persistencia al enviar el formulario**:
   - Verifica `$_POST['mainpack_guardar']` y confirma que el `post_id` recibido coincide.
   - Actualiza `golden_coins`, `arma_principal` (array asociativo con los metadatos del arma) y los campos `mainpack_slot_1` a `mainpack_slot_8`.
   - Muestra un aviso de éxito `✅ Inventario actualizado correctamente.`.
3. **Construcción del formulario**:
   - Contenedor `.mainpack-container` con `<form id="mainpack-formulario">`.
   - **Oro**: slot especial (`data-slot="oro"`) que muestra las monedas actuales, botones `＋/−` y `golden_coins` como `hidden`.
   - **Slots básicos**: ocho bloques repetidos (`mainpack_slot_1` … `mainpack_slot_8`) con:
     * Etiqueta `Slot N`.
     * Contenedor `.slot-content` vacío que el JS rellena.
     * `input type="hidden"` donde persiste el string de objetos.
     * Botones `add-item` y `remove-item`.
   - **Slots extra**: si `cs_inteligencia` o `cs_sabiduria` ≥ 16 se crean los slots 9 y 10, se muestra un encabezado con la característica que otorgó el bonus y se reacondiciona el HTML para esos dos campos.
   - **Arma principal**:
     * Muestra ficha resumida del arma guardada (nombre + `damage_dice` + `damage_type`).
     * Botones para abrir el modal de selección (`#armaModal`) y el modal de confirmación de borrado (`#armaModalEliminar`).
     * Lista de `hidden inputs` (`arma_principal[name]`, `slug`, `category`, `damage_dice`, `damage_type`, `weight`, `properties`, `es_magica`, `requiere_attunement`, `descripcion`) que envían la selección al guardar.
   - Botón `Guardar Inventario` (`name="mainpack_guardar"`).
4. **Modales server-side**:
   - `#armaModal`: contiene `<select>` para las armas, vista previa (daño, tipo, peso, propiedades) y checkboxes para “¿Es mágica?” y “¿Requiere attunement?”.
   - `#armaModalEliminar`: confirmación antes de limpiar la selección del arma.
   - `#modal-add-gold` y `#modal-remove-gold`: formularios para sumar/restar monedas (limitan la entrada con `min="1"` y `max="99999"`).

## 3. Datos y formato persistido (ACF)
- `golden_coins`: número entero no negativo guardado con `update_field`.
- `mainpack_slot_N` (N=1..10): campo de texto con el contenido del slot serializado como una cadena.
  * Objetos **normales** se guardan como texto plano (`"Espada larga"`).
  * Objetos **pequeños** usan el patrón `"Nombre x Cantidad"` y se concatenan con ` - ` (`"Antorcha x 3 - Ración x 2"`). El JS hace todo el parseo basándose en ese formato.
- `arma_principal`: array asociativo con las claves mencionadas arriba. El modal llena los valores y el servidor lo guarda tal cual lo recibe.
- Campos de gating para slots extra: `cs_inteligencia`, `cs_sabiduria`.

## 4. Interacción en frontend
### 4.1 Script inline en `wp_footer` (`PHP/functions.php`)
- Se imprime solo si la página actual es un `page` y luego, al iniciar, sale inmediatamente si no encuentra los elementos del inventario.
- **Renderizado de slots**:
  * `buildSlotHTMLFromValue` trocea el string de cada slot en `<p class="slot-item">`.
  * `updateSlotView` sincroniza cada slot con su `input hidden` y añade la clase `.empty` cuando corresponde.
  * En el `DOMContentLoaded` inicializa únicamente los slots 1–8.
- **Formulario “Añadir objeto”**:
  * Rellena el selector de cantidades (1..10) y solo lo habilita si se marca tamaño “pequeño”.
  * Al abrir el modal, coloca el número de slot actual en el título y limpia los campos.
  * Al enviar:
    - Rechaza entradas vacías.
    - Impide mezclar un objeto normal con otros objetos en el mismo slot.
    - Almacena objetos pequeños agregando o sumando al formato `"Nombre x cantidad"` y valida que la suma total no supere 10.
  * Tras escribir en el `input hidden`, llama a `updateSlotView` para refrescar el DOM y cierra el modal.
- **Formulario “Eliminar objetos”**:
  * Construye dinámicamente el contenido del modal: si el ítem sigue el patrón `x N` genera un `<select>` para restar unidades; si no, muestra un checkbox para borrar el objeto completo.
  * Al confirmar, recalcula el string del slot con la cantidad restante.
- **Gestión del oro**:
  * Actualiza en vivo el texto “X monedas” leyendo `#input_oro`.
  * Usa dos modales para sumar o restar; nunca permite bajar de 0.
- **Cierre de modales**: cualquier `.close-popup` o clic en el overlay correspondiente oculta la ventana activa.

### 4.2 `JS/inventario-armas.js`
- Se incluye únicamente cuando la plantilla es `page-inventario-personaje.php` o se detecta `inventario_personaje=1` en la query.
- Flujo:
  1. Declara referencias a los elementos del modal y a los campos ocultos.
  2. Mantiene un `weaponCache` y una `fetchPromise` para no repetir la llamada HTTP a `https://api.open5e.com/weapons/`.
  3. Traduce al español algunos nombres comunes (mapa `translations`).
  4. `populateSelector()` llena el `<select>` con los resultados recibidos del API (solo la primera página que devuelve Open5e).
  5. `renderPreview()` muestra daño, tipo, peso y propiedades del arma elegida.
  6. `applySelection()`:
     - Actualiza el bloque visual “Arma principal”.
     - Copia todos los metadatos disponibles a los `hidden inputs`.
     - Guarda el estado de los checkboxes (mágica / requiere attunement).
  7. `clearWeaponSelection()` limpia tanto la UI como los campos ocultos y oculta la previsualización.
  8. Maneja la ventana para eliminar el arma (confirmación antes de llamar a `clearWeaponSelection()`).

## 5. Estilos (`CSS/inventario.css`)
- Define:
  * Layout centrado, tarjetas semitransparentes y tipografía blanca.
  * Slots como filas flexibles con botones morados y contenedores que se adaptan a móvil (hay tres variantes `@media (max-width: 600px)` para reorganizar en columnas o grid).
  * Apariencia de los modales (`.modal-overlay`, `.modal-contenido`) compartidos por objetos, oro y armas.
  * Personalización del bloque del arma principal (fichas y botones).
  * Estética del modal de borrado de arma y de los modales de oro.

## 6. Dependencias y relaciones
- **WordPress / ACF**: se apoya en `get_field()` y `update_field()` para obtener y persistir los metacampos del personaje. `drak_get_post_value()` se usa como helper de saneamiento antes de guardar.
- **Custom Post Type `personaje`**: el módulo se muestra únicamente cuando el slug recibido corresponde a este CPT.
- **Hooks**:
  * `wp_enqueue_scripts` carga `css/inventario.css` y, bajo condición, el script `js/inventario-armas.js`.
  * `wp_footer` imprime el bloque `<script>` que gobierna los modales de items y oro.
- **API externa**: `inventario-armas.js` depende de `https://api.open5e.com/weapons/`; si la llamada falla el `<select>` muestra un mensaje de error.

## 7. Observaciones y riesgos detectados
1. **Persistencia de slots extra**: aunque se renderizan los campos `mainpack_slot_9` y `mainpack_slot_10`, el guardado solo itera de 1 a 8. Cualquier cambio en los slots extra no llega al servidor.
2. **Sincronización de la vista**: el script de inicialización también deja fuera los slots 9 y 10, por lo que esos contenedores nunca se rellenan dinámicamente si estaban poblados.
3. **Arma principal**:
   - Los `hidden inputs` se imprimen sin `value` inicial. Si el usuario hace cambios en el inventario y pulsa “Guardar” sin reseleccionar el arma, `$_POST['arma_principal']` llegará con strings vacíos y se borrará la información guardada.
   - El selector consume solo la primera página de `/weapons/` en Open5e (20 resultados). No hay paginación ni búsqueda.
4. **Formato plano de slots**: toda la lógica depende del string manual `Objeto x Cantidad - ...`. No hay validaciones en el servidor que impidan que un valor mal formado se guarde, por lo que cualquier edición manual vía inspección podría romper el parser JS.

En conjunto, el módulo combina plantillas PHP, campos ACF y dos capas de JavaScript (inline + `inventario-armas.js`) para ofrecer una UI completa de inventario con slots limitados, monedas y arma principal.
