# Análisis Fase 1  
## Sistema de campañas + rol DM + ACF base

---

## 1. Objetivo y alcance de esta fase

### Objetivo general

Introducir en la web el concepto de **Campaña** y la figura del **Dungeon Master (DM)**, sin cambiar todavía la estructura profunda de Diario / Wiki, pero dejándola preparada para:

- soportar varias campañas simultáneas, y  
- poder construir encima el módulo de importación de notas y la futura migración a nuevos CPTs de Wiki.

### Alcance de Fase 1

1. Crear un **nuevo rol de usuario `dm`** con permisos de lectura global y sin permisos de edición directa.  
2. Crear un **CPT `campaign`** que represente cada campaña de rol.  
3. Crear y asociar **campos ACF**:
   - Metadatos de la campaña.
   - Campo de relación `campaign` en los tipos de contenido que participan en una campaña.  
4. Ajustar las **reglas de acceso** para que:
   - El DM pueda leer cualquier ficha de personaje, diario, wiki, etc., aunque no sea el autor ni el propietario.  
   - El resto de usuarios mantengan las restricciones actuales.  
5. Crear una **navegación básica de campañas**:
   - Un listado simple de campañas (Hub inicial).  
   - Una página de campaña con enlaces a Diario / Wiki / Personajes filtrados por campaña (aunque, de momento, se puedan reutilizar listados existentes).

No entra en esta fase:

- Migrar Wiki/NPC/Lugares a CPTs nuevos (eso será Fase 2).  
- Implementar el módulo de importación de notas (Fase 3).  
- Quitar Elementor de las páginas actuales.

---

## 2. Nuevo rol de usuario: DM (Dungeon Master)

### 2.1. Nombre y propósito

- **Slug del rol**: `dm`  
- **Nombre visible**: `Dungeon Master`  

El DM es un usuario que:

- Puede **ver todo** el contenido de cualquier campaña (lectura global).  
- Puede usar, en el futuro, el **módulo de importación** y herramientas de gestión de campaña.  
- No puede modificar la configuración global del sitio, ni instalar plugins, ni editar el código, ni editar contenido arbitrario fuera de los flujos controlados (p. ej. importador).

### 2.2. Capacidades (conceptuales)

El rol `dm` debería tener capacidades equivalentes a algo entre `editor` y `author`, pero ajustadas:

**Debe poder:**

- Acceso al panel de administración.  
- `read` en todos los tipos de post relevantes (posts, páginas, personajes, entradas de diario, contenidos de wiki, etc.).  
- Acceso a los listados de contenido de todas las campañas.  
- Acceso a las pantallas que se creen para:
  - Hub de campañas.
  - Vista de campaña.
  - Importador de notas (futuro).

**Debe estar restringido / no permitir:**

- `publish_*`, `delete_*`, `manage_options`, `install_plugins`, `activate_plugins`, etc.  
- Edición directa de contenidos (p. ej. `edit_posts`, `edit_others_posts`) salvo que alguna capacidad concreta sea necesaria para flujos específicos; la idea es que el DM **no edite fichas a mano**, sino que use flujos controlados (importador u otros formularios).

> Nota para Codex: revisar capacidades actuales de los tipos `personaje` y otros para que, a nivel de código, allí donde se hace comprobación “propietario o admin”, se incluya también al rol `dm` como excepción para lectura.

---

## 3. Nueva entidad: Campaña (CPT `campaign`)

### 3.1. Definición conceptual

Crear un **Custom Post Type** llamado `campaign` que represente cada campaña de rol en el sistema.

- **Slug de CPT**: `campaign`  
- **Etiqueta singular**: `Campaña`  
- **Etiqueta plural**: `Campañas`  

No necesita editor clásico de contenido largo (el contenido principal se puede representar con campos ACF), pero se puede dejar habilitado si es útil.

Este CPT será la “raíz” de toda la información jugable:

- Una campaña tiene muchos personajes, muchas sesiones de diario, muchos NPC, muchos lugares, etc.  
- Todo ese contenido quedará relacionado vía un campo ACF `campaign`.

---

## 4. Campos ACF a crear

La idea es que Codex genere estos **Field Groups** en formato JSON ACF, para que puedan importarse fácilmente desde el propio plugin de ACF. A continuación se definen:

- Nombre del grupo.  
- Ubicación (post types donde aplica).  
- Campos (`label`, `name`, tipo y configuración básica).

> Los `field_key` concretos pueden generarse al exportar JSON; no hace falta fijarlos aquí, pero conviene usar nombres consistentes.

---

### 4.1. Field Group: `Campaña – Metadatos`

**Objetivo**  
Guardar la información principal de cada campaña.

**Ubicación**  
Mostrar el grupo de campos si:

- `Post Type` es igual a `campaign`.

**Campos**

1. **Título corto de campaña (opcional)**  
   - `label`: `Título corto`  
   - `name`: `campaign_short_title`  
   - `type`: `text`  
   - `instructions`: alias corto para menús o tarjetas.

2. **Sistema de juego**  
   - `label`: `Sistema de juego`  
   - `name`: `campaign_system`  
   - `type`: `select` o `text`  
   - En caso de `select`, valores típicos:  
     - `D&D 5e`  
     - `Drakkenheim`  
     - `Otro`

3. **Estado de la campaña**  
   - `label`: `Estado`  
   - `name`: `campaign_status`  
   - `type`: `select`  
   - `choices`:  
     - `active` → `En curso`  
     - `paused` → `En pausa`  
     - `finished` → `Terminada`

4. **Imagen de portada**  
   - `label`: `Imagen de portada`  
   - `name`: `campaign_cover_image`  
   - `type`: `image`  
   - `return_format`: `id`  
   - `preview_size`: `medium`

5. **Color / acento de campaña**  
   - `label`: `Color de campaña`  
   - `name`: `campaign_color`  
   - `type`: `color_picker`

6. **Descripción breve**  
   - `label`: `Descripción breve`  
   - `name`: `campaign_summary`  
   - `type`: `textarea`  
   - Pensada para usar en tarjetas del Hub.

---

### 4.2. Field Group: `Asignación de campaña`

**Objetivo**  
Enlazar cada contenido jugable con una campaña concreta.

**Ubicación**  
Se puede usar un solo Field Group con varias reglas de ubicación OR.

Mostrar este grupo de campos si `Post Type` es igual a cualquiera de:

- `personaje` (CPT actual de fichas de PJ).  
- `post` (para entradas de Diario y, temporalmente, Wiki/NPC/Lugares basadas en categorías).  
- Cualquier CPT de Wiki que se cree en fases posteriores (por ejemplo: `npc`, `lugar`, `faccion`, `lore`).

> En esta fase, lo importante es que **personajes y posts** puedan recibir una campaña. Más adelante se añadirá el mismo grupo a los nuevos CPTs que se creen.

**Campos**

1. **Campaña**  
   - `label`: `Campaña`  
   - `name`: `campaign`  
   - `type`: `post_object`  
   - `post_type`: `campaign`  
   - `multiple`: `false`  
   - `allow_null`: `false`  
   - `ui`: `true`  
   - `instructions`: Selecciona a qué campaña pertenece este contenido.

2. **Visibilidad en campaña** (opcional, pero útil a futuro)  
   - `label`: `Visibilidad en campaña`  
   - `name`: `campaign_visibility`  
   - `type`: `select`  
   - `choices`:  
     - `public` → `Visible para todos los jugadores`  
     - `dm_only` → `Solo visible para DM y Admin`  
   - `default_value`: `public`

> Campo 2 puede no usarse inmediatamente, pero es interesante para gestionar contenido oculto al grupo de jugadores (por ejemplo, notas del DM asociadas a un lugar o NPC).

---

## 5. Asociación del contenido actual a la campaña Drakkenheim

Una vez creados el CPT `campaign` y el Field Group `Asignación de campaña`, habrá que:

1. Crear manualmente la primera campaña:

   - `Título`: `Crónicas de Drakkenheim`  
   - Rellenar metadatos ACF (imagen, color, sistema, estado, etc.).

2. Rellenar el campo `campaign` en:

   - Todas las fichas de `personaje`.  
   - Todas las entradas de `post` que pertenezcan a:
     - Diario de campaña (categoría Diario).  
     - Wiki / NPC / Lugares / Facciones actuales (categorías específicas que se usen para Drakkenheim).

> Esto puede hacerse inicialmente mediante edición rápida/manual, o Codex puede preparar un script de ayuda que:
>
> - detecte posts por categorías “Drakkenheim” (o las que se definan),  
> - y les asigne automáticamente la campaña creada.

---

## 6. Reglas de acceso y visibilidad

### 6.1. Matriz conceptual de permisos (lectura)

| Recurso                         | Invitado | Usuario normal | Propietario (jugador) | DM       | Admin   |
|--------------------------------|----------|----------------|------------------------|----------|---------|
| Ver página pública / Hub       | Sí       | Sí             | Sí                     | Sí       | Sí      |
| Ver ficha de su personaje      | No       | Sí*            | Sí                     | Sí       | Sí      |
| Ver ficha de otros personajes  | No       | No             | No                     | **Sí**   | Sí      |
| Ver Diario de campaña          | No o Sí (según config actual) | Sí | Sí | Sí | Sí |
| Ver Wiki / NPC / Lugares       | No o Sí (según config actual) | Sí | Sí | Sí | Sí |

\* Usuario normal podrá ver los personajes que tenga asignados según la lógica ya existente.

### 6.2. Cambios que debe aplicar Codex

A alto nivel:

- En todos los puntos donde ahora mismo la lógica de acceso comprueba algo tipo  
  `current_user_id === autor/owner` **o** `current_user_can('administrator')`,  
  añadir la condición de que un usuario con rol `dm` tenga **acceso de lectura**.

- Si existe lógica que impide a un usuario ver fichas de personaje de otros, deberá permitirlo al rol `dm` (solo lectura).

- Más adelante, cuando se implemente el campo `campaign_visibility`, las vistas públicas podrán filtrar:

  - Si `campaign_visibility = dm_only` → solo DM/Admin ven ese contenido.

---

## 7. Navegación básica de campañas (Hub inicial)

Aunque el diseño final sin Elementor se hará en una fase posterior, en esta fase conviene dar ya una estructura mínima para poder trabajar y probar el modelo.

### 7.1. Listado de campañas (Hub)

Crear una vista que liste todas las `campaign`:

- Para cada campaña, mostrar:

  - Imagen de portada (`campaign_cover_image`).  
  - Título.  
  - Estado (en curso / terminada / en pausa).  
  - Descripción breve (`campaign_summary`).  
  - Botón “Entrar a la campaña”.

Codex puede implementarlo como:

- Plantilla de archivo `archive-campaign.php`, **o**  
- Página específica con una consulta personalizada (`WP_Query` sobre `campaign`) y un shortcode o plantilla.

### 7.2. Vista de campaña (portal)

Al hacer clic en una campaña, se mostrará una página tipo “portal de campaña” con:

- Hero de campaña (imagen + título + estado + resumen).  
- Tres botones grandes:

  - **Diario**: listado de entradas de Diario filtradas por esa campaña.  
  - **Wiki**: listado de contenidos de wiki (de momento, posts por categorías relevantes + campaña).  
  - **Personajes**: listado de `personaje` filtrado por campaña.

En esta fase, los listados pueden reutilizar plantillas existentes con simples filtros por campaña; no es necesario rediseñar todavía.

---

## 8. Preparación para fases futuras (contexto para Codex)

Sin implementarlo aún, es importante que Codex tenga en mente que:

- El campo `campaign` en todos los contenidos será la **clave** para:

  - El futuro **módulo de importación** (que siempre trabajará sobre una campaña concreta).  
  - Los filtros del Hub y del portal de campaña.  
  - La separación de campañas cuando haya más de una.

- El rol `dm` será el usuario autorizado para:

  - Ejecutar el importador global de notas de sesión.  
  - Revisar y aprobar aportaciones de otros usuarios (importador por elemento).

Por eso es crucial que en esta Fase 1 queden bien definidos y probados:

1. El CPT `campaign`.  
2. El Field Group `Campaña – Metadatos`.  
3. El Field Group `Asignación de campaña`.  
4. El rol `dm` y las excepciones de lectura.

---
