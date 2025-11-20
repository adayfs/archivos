# Análisis – Migración de Wiki a Custom Post Types (NPC, Lugar, Facción, etc.)

## 1. Objetivo

1. Dejar de usar `post` + categorías para el contenido de **Wiki** (NPC, lugares, facciones, lore…).
2. Introducir **Custom Post Types específicos** para cada tipo de elemento de la campaña:
   - `npc`
   - `lugar`
   - `faccion`
   - (opcional) `lore_entry` u otro CPT genérico para entradas de lore que no encajan en los anteriores.
3. Mantener la integración con:
   - El campo ACF `campaign` (ya implantado).
   - El portal de campaña (`single-campaign` + secciones `wiki`, `diario`, etc.).
   - El rol `dm` y el resto de permisos.
4. Preparar la base de datos para que el futuro **módulo de importación de notas** trabaje ya contra estos CPT.

---

## 2. Estado actual (resumen)

- Wiki, NPC, lugares, facciones, etc. se almacenan como **posts estándar** (`post`).
- La distinción de tipo se basa en **categorías** (y/o subcategorías), por ejemplo:
  - Categoría `NPC`
  - Categoría `Lugares`
  - Categoría `Facciones`
  - Alguna categoría global `Wiki` o similares.
- Todas estas entradas pertenecen ya a la campaña **“Crónicas de Drakkenheim”** gracias al campo ACF `campaign`.

El portal de campaña (`/campaign/{slug}/wiki/`) actualmente obtiene su contenido mediante una query a `post` filtrando por:

- Campo ACF `campaign`
- Categorías de Wiki / NPC / Lugares / Facciones

---

## 3. Diseño de nuevos Custom Post Types

Se propone crear los siguientes CPT:

### 3.1. CPT `npc`

- **Slug**: `npc`
- **Singular label**: `NPC`
- **Plural label**: `NPCs`
- Se usará para personajes no jugadores de la campaña.

Configuración sugerida (alta nivel):

- `public` = `true`
- `show_ui` = `true`
- `has_archive` = `true`
- `rewrite.slug` = `npc`
- `supports` = `[ 'title', 'editor', 'thumbnail', 'excerpt' ]`
- `show_in_rest` = `true` (para compatibilidad futura con REST / editor de bloques).

### 3.2. CPT `lugar`

- **Slug**: `lugar`
- **Singular label**: `Lugar`
- **Plural label**: `Lugares`
- Se usará para ubicaciones importantes (ciudades, barrios, lugares clave, etc.).

Configuración similar:

- `public` = `true`
- `has_archive` = `true`
- `rewrite.slug` = `lugar`
- `supports` = `[ 'title', 'editor', 'thumbnail', 'excerpt' ]`
- `show_in_rest` = `true`

### 3.3. CPT `faccion`

- **Slug**: `faccion`
- **Singular label**: `Facción`
- **Plural label**: `Facciones`
- Se usará para organizaciones, grupos, órdenes, etc.

Configuración similar:

- `public` = `true`
- `has_archive` = `true`
- `rewrite.slug` = `faccion`
- `supports` = `[ 'title', 'editor', 'thumbnail', 'excerpt' ]`
- `show_in_rest` = `true`

### 3.4. (Opcional) CPT `lore_entry`

Solo si se considera necesario. Sirve para:

- Conceptos abstractos,
- Eventos importantes,
- Objetos legendarios,
- etc.

Se puede definir como:

- **Slug**: `lore-entry`
- **Label**: `Entrada de lore`
- Misma configuración básica que los otros CPT.

---

## 4. Campos ACF y relación con campañas

### 4.1. Reutilizar grupo `Asignación de campaña`

El Field Group **`Asignación de campaña`** ya existente debe ampliarse para que aparezca también en:

- `npc`
- `lugar`
- `faccion`
- (y `lore_entry` si se crea)

De este modo, TODOS estos elementos quedarán ligados a una campaña mediante el campo:

- `name` = `campaign`
- `type` = `post_object` (a `campaign`)

Este campo será la clave que usará:

- El portal de campaña `single-campaign` (sección `wiki`).
- El futuro importador de notas.

### 4.2. Metadatos específicos de Wiki (opcional)

Para esta fase no es obligatorio añadir nuevos campos ACF específicos por tipo; se puede seguir usando:

- Título
- Contenido (editor)
- Imagen destacada

Si se considera conveniente, se pueden definir más adelante Field Groups como:

- `NPC – Detalles`
- `Lugar – Detalles`
- `Facción – Detalles`

con campos tipo “alias”, “región”, “alineamiento”, etc.  
Por ahora, para no romper nada, lo principal es **migrar el contenido** a CPT sin cambiar su estructura interna.

---

## 5. Estrategia de migración de contenidos

### 5.1. Principio general

La migración se hará **in situ**, cambiando el `post_type` de las entradas existentes, **sin crear IDs nuevos**, para:

- mantener enlaces internos,
- no romper referencias existentes,
- y simplificar el trabajo de Google Apps Script/importadores actuales.

### 5.2. Pasos previos (importante)

1. **Backup completo** de la base de datos antes de tocar nada.  
2. Confirmar los **slugs de categorías** que se usan para cada tipo:
   - Categoría de NPC: p.ej. `npc`
   - Categoría de Lugares: p.ej. `lugares`
   - Categoría de Facciones: p.ej. `facciones`
3. Verificar que todas las entradas de wiki relevantes ya tienen asignada la campaña correcta mediante el ACF `campaign` (en este momento, Drakkenheim).

### 5.3. Migración tipo por tipo

Para cada categoría:

1. Localizar todos los `post` que tengan esa categoría.
2. Ejecutar un script que haga:

   ```php
   wp_update_post( array(
       'ID'        => $post_id,
       'post_type' => 'npc', // o 'lugar', 'faccion' según el caso
   ) );
