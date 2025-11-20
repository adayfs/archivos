# Análisis – Módulo de importación de notas (sesiones y elementos)

## 1. Objetivo general

Crear un **módulo de importación de notas** 100% dentro de WordPress que permita:

1. **Importador de sesión (DM)**  
   - El DM sube un archivo de texto (o pega texto) con las notas de una sesión.  
   - El módulo:
     - Crea/actualiza la entrada de **Diario** de esa sesión.
     - Detecta menciones a **NPC, Lugares, Facciones, Lore, Personajes**.
     - Para cada mención:
       - Si existe el elemento en la campaña → añade información a ese elemento y lo enlaza.
       - Si no existe → crea una ficha “stub” en el CPT correspondiente.
     - Genera enlaces cruzados entre Diario ↔ NPC/Lugares/Facciones/Lore.

2. **Importador por elemento (jugadores / DM)**  
   - Un usuario sube un texto asociado a **un elemento concreto** (p. ej. “Notas sobre tal NPC”).  
   - El módulo:
     - Añade ese texto al contenido de la ficha seleccionada (NPC, Lugar, Facción, Lore, Personaje).
     - Detecta dentro del texto otras entidades marcadas y crea/enlaza igual que en el importador de sesión (pero sin crear entrada de diario).

3. Todo el sistema debe:
   - Trabajar siempre **dentro del contexto de una campaña** (`campaign`).
   - Usar los CPT existentes:  
     - `campaign`, `personaje`, `npc`, `lugar`, `faccion`, `lore-entry`  
   - Ser extensible para futuros cambios (por ejemplo, si en el futuro el Diario pasa a un CPT propio `session_log`).

---

## 2. Roles y permisos

### 2.1. Dungeon Master (rol `dm`)

- Acceso al **importador de sesión** para cualquier campaña.
- Acceso al **importador por elemento** para cualquier elemento de cualquier campaña.
- Lectura global (como ya se definió en el plan de roles).

### 2.2. Usuarios jugadores (rol estándar actual)

- Acceso al **importador por elemento**, pero con restricciones:
  - Solo pueden importar:
    - A sus **propios personajes**, y/o
    - A elementos (NPC/Lugares/Facciones/Lore) que estén dentro de campañas a las que tenga acceso (definición exacta a concretar más adelante).
- No tienen acceso al importador global de sesión.

### 2.3. Administrador

- Tiene acceso completo a ambas herramientas, además de a la configuración global del módulo.

---

## 3. Diseño del módulo (plugin)

Crear un plugin propio, por ejemplo **`drak-importer`**, con:

- Páginas de administración y shortcodes para incrustar formularios en el front.
- Servicios internos de:
  - Parsing de notas.
  - Creación/actualización de contenido.
  - Generación de enlaces cruzados.
  - Logging / registro básico.

---

## 4. Formato de entrada de las notas

### 4.1. Origen

Las notas vendrán de:

- Imágenes de notas manuscritas → procesadas por ChatGPT → texto plano o markdown.
- Escrito digital directamente (Google Docs, etc.), también exportado a texto plano.

### 4.2. Reglas de marcado propuestas

Para que el importador pueda trabajar de forma fiable, se propone usar un marcado sencillo, fácil de generar desde el prompt de ChatGPT:

- **Cabecera de sesión** (solo para importador de sesión):

  ```text
  @campaign: Cronicas de Drakkenheim
  @session: 14
  @date: 2025-11-21
