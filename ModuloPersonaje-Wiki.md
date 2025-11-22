# Análisis – Nuevo módulo **Personaje-Wiki** (mini-wiki por personaje)

## 1. Objetivo general

Crear un nuevo módulo de **Personaje-Wiki** que complemente a la hoja de personaje (`personaje`) con una capa narrativa estructurada.

Cada **Personaje-Wiki** será una “mini-wiki” propia de un PJ, con:

1. **Portada del personaje** (ficha principal).
2. **Sección Origen** – entradas narrativas sobre el pasado del PJ.
3. **Sección Aventura** – entradas narrativas sobre lo que ocurre durante la campaña.
4. **Sección Galería** – todas las imágenes asociadas al PJ agrupadas en una sola galería.
5. **Botón Notas** – despliega el módulo de notas existente para crear nuevas entradas en Origen/Aventura.

Este módulo debe integrarse con:

- La arquitectura de **campañas** (`campaign`).
- El CPT de **hoja de personaje jugable** (`personaje`).
- El plugin de **galería** (`drak-gallery`).
- El módulo de **importación/notas** (`drak-importer`).

---

## 2. Nuevo CPT: Personaje-Wiki

### 2.1. Definición conceptual

Crear un nuevo Custom Post Type para representar la “wiki” de un personaje jugador:

- Nombre de trabajo: **Personaje-Wiki**.
- Uso: ficha narrativa del personaje (no stats jugables).
- Será un elemento más dentro de la **Wiki de la campaña**.

### 2.2. Campos y relaciones (ACF)

Crear un Field Group específico para Personaje-Wiki con, al menos:

1. **Campaña asociada**
   - Tipo: Relationship o Post Object.
   - Post Type: `campaign`.
   - Obligatorio.
   - Uso: filtrar Personaje-Wikis en la wiki de cada campaña.

2. **Hoja de personaje asociada**
   - Tipo: Relationship o Post Object.
   - Post Type: `personaje`.
   - Opcional (al principio puede no existir, pero será la referencia a la hoja jugable).
   - Uso: añadir un enlace “Ver hoja” en la portada del Personaje-Wiki.

3. **Imagen principal del personaje**
   - Tipo: Imagen.
   - Uso: cabecera visual del Personaje-Wiki.

(El color temático de la campaña se puede heredar del propio CPT `campaign`, no hace falta duplicarlo aquí.)

---

## 3. Modelo de contenido interno: Origen y Aventura

### 3.1. Entradas internas por personaje

Necesitamos un sistema para crear **entradas narrativas** que:

- Pertenecen a un **Personaje-Wiki concreto**.
- Están clasificadas en **Orígen** o **Aventura**.
- Son de tipo “post de blog” (título, texto, imágenes).

Requisitos funcionales:

- Cada entrada:
  - Se asocia a UN solo Personaje-Wiki.
  - Se asocia a UNA sola sección: Origen o Aventura.
- En la interfaz del personaje:
  - Sección **Origen** → muestra solo sus entradas de Origen (ordenadas por fecha, las más recientes arriba).
  - Sección **Aventura** → muestra solo sus entradas de Aventura (mismo criterio).

La implementación interna (si es un CPT nuevo, taxonomy, meta, etc.) queda a tu criterio, siempre que podamos:

- Listar de forma eficiente “entradas de Origen de este Personaje-Wiki”.
- Listar “entradas de Aventura de este Personaje-Wiki”.
- Crear nuevas entradas desde el módulo de Notas (ver punto 5).

---

## 4. Sección Galería del Personaje-Wiki

### 4.1. Fuente de imágenes

La sección **Galería** del Personaje-Wiki debe ser un **recopilatorio único** de todas las imágenes del personaje, independientemente de su origen.

Imágenes que deben aparecer en la Galería del personaje:

1. Imágenes incluidas en **entradas de Origen** del Personaje-Wiki.
2. Imágenes incluidas en **entradas de Aventura** del Personaje-Wiki.
3. Imágenes subidas a través del **módulo de galería global** (`drak-gallery`) y asociadas explícitamente a ese Personaje-Wiki.

### 4.2. Comportamiento de la sección Galería

- Al entrar en la sección **Galería** del Personaje-Wiki:
  - Mostrar un layout de galería (grid/carrusel) con **todas** las imágenes asociadas al personaje.
  - No hace falta distinguir visualmente de dónde viene cada imagen (origen, aventura o galería general); el objetivo es un “álbum completo” del PJ.

- Si no hay imágenes:
  - Mostrar un mensaje de estado del tipo “Este personaje aún no tiene imágenes en la galería”.

La integración con `drak-gallery` se debe hacer reutilizando en lo posible la lógica existente de asociación imagen → post.

---

## 5. Botón Notas – Integración con el módulo `drak-importer`

### 5.1. Ubicación e interfaz

En la página del Personaje-Wiki:

- Junto a las secciones **Origen / Aventura / Galería**, añadir un cuarto elemento:
  - Un botón o pestaña **“Notas”**.
- Este botón **no cambia de sección** como las otras pestañas, sino que:
  - Al pulsarlo, **despliega** el formulario del módulo de notas (módulo `drak-importer`) adaptado a este contexto.

### 5.2. Comportamiento del formulario de Notas

El formulario debe:

1. Estar **pre-asociado** al Personaje-Wiki actual.
   - El usuario no debe elegir personaje; ya estamos dentro de la ficha concreta.

2. Incluir un campo obligatorio de selección de **sección destino**:
   - Opción “Origen”
   - Opción “Aventura”

3. Permitir introducir:
   - Texto libre (como hace ahora el plugin de notas).
   - Imágenes adjuntas (misma lógica que el plugin actual).

4. Al enviar el formulario:
   - Debe crear una **nueva entrada interna** para este Personaje-Wiki:
     - Asociada al personaje actual.
     - Marcada como “Origen” o “Aventura” según lo elegido.
   - Esta nueva entrada debe aparecer como **la más reciente** en el listado de su sección (Origen o Aventura).
   - Cualquier imagen subida con esa nota debe contar automáticamente como imagen del personaje y aparecer también en la **Galería** del personaje.

### 5.3. Reutilización del módulo existente

- El módulo `drak-importer` ya gestiona notas/entradas para campañas.
- Aquí se requiere una **adaptación** o extensión:
  - Nuevo “modo Personaje-Wiki” donde el importador sabe:
    - El ID del Personaje-Wiki.
    - Que el destino es “Origen” o “Aventura” dentro de ese personaje.
- El comportamiento (validaciones, permisos, etc.) puede seguir el patrón actual del módulo de notas, pero orientado a este nuevo destino.

---

## 6. Plantilla y UX del Personaje-Wiki

### 6.1. Estructura visual

La plantilla `single` de Personaje-Wiki debe tener:

1. **Cabecera de personaje**
   - Nombre del personaje.
   - Imagen principal.
   - Color de campaña aplicado en tonos y acentos.
   - Botón/enlace a la hoja de personaje (`personaje`) si existe relación.
   - Botón “Volver a la campaña” o similar, para volver al portal de campaña.

2. **Navegación de secciones**
   - 4 elementos:
     - Origen
     - Aventura
     - Galería
     - Notas (desplegable)
   - Origen, Aventura y Galería cambian el contenido de la sección principal.
   - Notas abre el formulario, pero no cambia la sección de listado.

3. **Zona de contenido**
   - Si está activa la pestaña **Origen**:
     - Mostrar listado de entradas de Origen del Personaje-Wiki (título + extracto + fecha, clic para ver detalle).
   - Si está activa **Aventura**:
     - Listado de entradas de Aventura (mismo patrón).
   - Si está activa **Galería**:
     - Galería visual con todas las imágenes del personaje.
   - El formulario de **Notas** se muestra como panel desplegable superpuesto o debajo de la navegación.

---

## 7. Integración con la Wiki y la campaña

### 7.1. Wiki de la campaña

Dentro de la vista de **Wiki** de una campaña:

- Incluir los Personaje-Wiki como un tipo más de contenido de lore, igual que NPC, Lugar, Facción, etc.
- En la sección de Wiki de una campaña, se debe poder:
  - Listar Personaje-Wiki asociados a esa campaña (filtrados por el campo ACF `campaign`).

### 7.2. Enlace desde la hoja de personaje

En la plantilla de la hoja de personaje (`personaje`):

- Si existe un Personaje-Wiki asociado:
  - Mostrar un enlace claro tipo “Ver ficha en la Wiki” que lleve al `single` del Personaje-Wiki.

---

## 8. Roles y permisos (alto nivel)

- **Jugador**:
  - Puede ver los Personaje-Wiki (propios y/o de otros, según se defina).
  - Puede crear nuevas entradas (vía Notas) para su propio Personaje-Wiki.
  - Puede subir imágenes desde la galería general y asociarlas a su Personaje-Wiki.

- **DM**:
  - Puede ver y consultar todos los Personaje-Wiki.
  - Opcionalmente, puede crear/editar entradas para cualquier personaje.

- **Admin**:
  - Todos los permisos anteriores + configuración.

Los detalles finos de permisos se pueden alinear con el sistema de roles que ya se está implantando (rol `dm`, etc.).

---

## 9. Resumen

- Crear un nuevo CPT **Personaje-Wiki** como mini-wiki del personaje, ligado a `campaign` y a su hoja `personaje`.
- Implementar un sistema de **entradas internas** para Origen y Aventura, asociadas a cada Personaje-Wiki.
- Integrar una sección **Galería** que agrupe todas las imágenes del personaje, vengan de entradas (Origen/Aventura) o del módulo `drak-gallery`.
- Reutilizar y adaptar el módulo de notas (`drak-importer`) como **botón Notas**, que:
  - ya conoce el Personaje-Wiki,
  - pregunta si la entrada va a Origen o Aventura,
  - crea la nueva entrada y suma las imágenes a la Galería.
- Integrar Personaje-Wiki dentro de la **Wiki de campaña** y enlazarlo desde la hoja de personaje jugable.

Este análisis describe el comportamiento esperado sin entrar en detalles de implementación (CPT/taxonomías/meta). La implementación concreta puede adaptarse a la estructura actual del tema y los plugins (`drak-gallery`, `drak-importer`).
