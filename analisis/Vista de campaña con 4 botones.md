# Análisis – Vista de campaña con 4 botones y URLs por sección

## 1. Objetivo

1. Que cada campaña (`campaign`) tenga un **portal propio** con esta estructura fija:

   - Hero de campaña (imagen + estado + título).
   - Bloque con **4 iconos/botones**:
     - Personajes
     - Diario de campaña
     - Wiki
     - Galería

2. Que todas las URLs de campaña sigan este patrón:

   - `/campaign/{slug}/`                  → portal de campaña  
   - `/campaign/{slug}/pj/`               → sección Personajes de esa campaña  
   - `/campaign/{slug}/diario/`           → sección Diario de esa campaña  
   - `/campaign/{slug}/wiki/`             → sección Wiki de esa campaña  
   - `/campaign/{slug}/galeria/`          → sección Galería de esa campaña  

3. **Reutilizar la lógica existente** de las páginas actuales:

   - `/pj/`
   - `/diario/`
   - `/wiki/`
   - `/galeria/`

   Adaptándola para que funcione “dentro” de una campaña concreta y filtrada por el campo ACF `campaign`.

4. Dejar todo preparado para que, en una fase posterior, se puedan cambiar
   las queries internas a CPT específicos (`npc`, `lugar`, `session_log`, etc.)
   **sin cambiar estas URLs ni el layout**.

---

## 2. Routing y estructura de URLs

### 2.1. Comportamiento deseado

- `/campaign/{slug}/`  
  Muestra el **portal de campaña**:
  - Hero de campaña.
  - 4 iconos/botones.
  - Texto introductorio.

- `/campaign/{slug}/pj/`  
  Muestra:
  - El mismo hero.
  - Los mismos 4 iconos.
  - Debajo, la **sección Personajes** (listado filtrado por campaña).

- `/campaign/{slug}/diario/`  
  Igual, pero sección Diario.

- `/campaign/{slug}/wiki/`  
  Igual, pero sección Wiki.

- `/campaign/{slug}/galeria/`  
  Igual, pero sección Galería.

### 2.2. Enfoque propuesto

- Mantener el CPT `campaign` como está.
- Añadir **“sub-secciones”** tipo `pj`, `diario`, `wiki`, `galeria`
  como endpoints ligados a la URL de la campaña.
- Toda la lógica se concentra en la plantilla del CPT de campaña
  (`single-campaign`), que:
  - Detecta qué sección se está pidiendo.
  - Siempre pinta el hero + los 4 iconos.
  - Debajo carga el contenido adecuado para esa sección.

---

## 3. Plantilla de campaña (`single-campaign`)

### 3.1. Cabecera común (hero)

Elementos:

- Imagen superior (puede ser un banner global o una imagen de campaña).
- Texto de estado de campaña:
  - A partir del ACF `campaign_status` (En curso / En pausa / Terminada).
- Título de la campaña.
- Información básica:
  - Sistema (`campaign_system`).
  - Descripción breve (`campaign_summary`).

Esta cabecera debe ser **la misma** para todas las secciones de la campaña.

### 3.2. Bloque con los 4 iconos/botones

Debajo del hero, un bloque con 4 iconos o tarjetas,
siguiendo la estética actual (como la home antigua):

- **Personajes**  
- **Diario de campaña**  
- **Wiki**  
- **Galería**

Cada icono debe enlazar a la URL correspondiente de la campaña actual:

- Personajes → `/campaign/{slug}/pj/`
- Diario → `/campaign/{slug}/diario/`
- Wiki → `/campaign/{slug}/wiki/`
- Galería → `/campaign/{slug}/galeria/`

Independientemente de en qué sección estemos, estos 4 iconos **siempre** se muestran (sirven de menú interno de campaña).

### 3.3. Sección de contenido variable

Debajo del bloque de iconos, se mostrará solo UNA de estas secciones:

- `portal` (cuando no hay sub-URL).
- `pj`
- `diario`
- `wiki`
- `galeria`

La plantilla debe:

1. Identificar la campaña actual (post `campaign`).
2. Detectar qué sección se está pidiendo (por ejemplo, mediante endpoints o query vars).
3. Ejecutar la lógica correspondiente, **filtrada por el campo ACF `campaign` = campaña actual**.

---

## 4. Reutilización de la lógica actual

La idea es **no reescribir desde cero** lo que ya funciona en:

- `/pj/`
- `/diario/`
- `/wiki/`
- `/galeria/`

Sino:

1. Extraer la lógica de cada una de esas páginas a componentes reutilizables
   (funciones, plantillas parciales, etc.).
2. En `single-campaign` llamar a esos componentes, añadiendo el filtro de campaña.

### 4.1. Sección Personajes (`/campaign/{slug}/pj/`)

Debe reutilizar:

- El mismo layout y estilo que la página `/pj/` actual
  (listado de fichas de personaje).

Adaptaciones:

- En lugar de mostrar **todos** los personajes:
  - Mostrar solo aquellos cuyo ACF `campaign` apunte a la campaña actual.
- Mantener cualquier lógica existente de permisos:
  - El DM puede ver todas las fichas.
  - Cada jugador ve lo que le corresponda.

### 4.2. Sección Diario (`/campaign/{slug}/diario/`)

Debe reutilizar:

- El sistema actual de listado de entradas de diario
  (posts, orden cronológico, paginación si la hay).

Adaptaciones:

- Filtrar solo las entradas que pertenezcan a la campaña actual:
  - `post_type` actual (normalmente `post`).
  - Campo ACF `campaign` = campaña actual.
- Mantener el estilo y la estructura presentes.

### 4.3. Sección Wiki (`/campaign/{slug}/wiki/`)

Debe reutilizar:

- El listado actual de Wiki (entradas de lore, NPC, lugares, etc.),
  aunque ahora estén basadas en `post` + categorías.

Adaptaciones:

- Filtrar solo contenido de la campaña actual:
  - `post_type` actual (`post` de momento).
  - Categorías que definan la wiki.
  - Campo ACF `campaign` = campaña actual.

### 4.4. Sección Galería (`/campaign/{slug}/galeria/`)

Debe reutilizar:

- La galería actual (plugin `drak-gallery` y la página `/galeria/`).

Adaptaciones:

- Mostrar imágenes **relacionadas con la campaña actual**:
  - Por ejemplo, aquellas asociadas a personajes / lugares / NPCs que tengan el campo `campaign` = campaña actual.
- Mantener el carrusel y resto de experiencia que ya funciona.

---

## 5. Comportamiento de la sección `portal`

Cuando la URL es simplemente:

- `/campaign/{slug}/`

La plantilla muestra:

- Hero de campaña.
- 4 iconos.
- Un bloque de texto tipo “Portal de campaña”, que puede incluir:
  - Descripción general de la campaña.
  - Explicación de lo que se encuentra en cada botón:
    - Diario → resumen de sesiones.
    - Wiki → lore (NPC, lugares, facciones, etc.).
    - Personajes → fichas de PJ.
    - Galería → imágenes relacionadas con la campaña.

Es una vista más “informativa” y menos funcional.

---

## 6. Preparación para *migración a CPT* (recordatorio importante)

Este punto es clave para no tirar trabajo más adelante.

En fases futuras se migrará el contenido de:

- Wiki (NPC, lugares, facciones…)
- Diario (sesiones)
- Otros elementos

desde el modelo actual:

- `post` + categorías,

a un modelo basado en varios **Custom Post Types**:

- `npc`
- `lugar`
- `faccion`
- `session_log`
- etc.

### 6.1. Requisito para que la migración sea fácil

La implementación de esta fase debe:

1. **No acoplarse** de manera rígida a `post_type = post` ni a nombres de categorías.
2. Centralizar la obtención de contenidos por campaña en “puntos únicos” que luego se puedan modificar.

Ejemplos de buenas prácticas conceptuales:

- Para la sección Diario:
  - Tener un único lugar donde se define “cómo se obtienen las entradas de diario de una campaña”.
  - Más adelante solo se cambiará allí el `post_type` y/o taxonomías implicadas.

- Para la sección Wiki:
  - Tener un único lugar donde se define “cómo se obtiene el conjunto de elementos de wiki de una campaña”.
  - Más adelante, en vez de `post` con categoría, se consultarán los nuevos CPT (`npc`, `lugar`, `faccion`, etc.).

### 6.2. Uso del campo ACF `campaign`

El único punto que **no debe cambiar** con la migración es:

- El campo ACF `campaign` que relaciona cada elemento con su campaña.

Por tanto:

- Todas las queries (Personajes, Diario, Wiki, Galería) deben filtrarse SIEMPRE por ese campo.
- Al migrar a CPT, solo será necesario ajustar:
  - `post_type`
  - taxonomías / meta adicionales

pero no la forma de detectar “a qué campaña pertenece cada cosa”.

---

## 7. Resumen

- `single-campaign` será el **portal central de cada campaña**, con:
  - Hero.
  - 4 iconos fijos (Personajes, Diario, Wiki, Galería).
  - Sección de contenido que cambia según la URL (`/pj`, `/diario`, `/wiki`, `/galeria`).

- La lógica actual de `/pj`, `/diario`, `/wiki`, `/galeria` debe extraerse
  y reutilizarse, añadiendo filtro por campaña mediante el ACF `campaign`.

- La implementación debe estar pensada desde ya para que, cuando se migre
  a nuevos CPT (`npc`, `lugar`, `faccion`, `session_log`, etc.), solo
  haya que cambiar las queries internas, sin tocar:
  - URLs,
  - layout,
  - ni estructura general de la plantilla de campaña.
