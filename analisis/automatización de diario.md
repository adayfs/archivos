Documento de análisis: automatización de Diario y sincronización con wiki

## Alcance y contexto
- CPT del diario: `post_type=diario` (URL típica: `/diario/sesion-2/`).
- CPTs destino: `lugar` (`/lugar/<slug>/`), `npc` (`/npc/<slug>/`), `faccion` (`/faccion/<slug>/`).
- Personajes: `post_type=personaje_wiki` (debe existir previamente). Entradas de aventura: `post_type=personaje-wiki-entry`, taxonomía/sección “Aventura”, se muestran en `/personaje-wiki/<slug>/#aventura`.
- Flags en el contenido del diario: `@L[Texto]`, `@N[Texto]`, `@F[Texto]`, `@P[Texto]`. Solo `@P` para personajes ya creados (no crear personajes desde el diario).
- Botón “Crear Nueva Entrada” en `/campaign/{slug}/diario/` que abre el editor de un nuevo `diario`.

## Mapeo tipo → destino
- `L` → CPT `lugar`, URL `/lugar/<slug>/`.
- `N` → CPT `npc`, URL `/npc/<slug>/`.
- `F` → CPT `faccion`, URL `/faccion/<slug>/`.
- `P` → CPT `personaje_wiki` ya existente. Solo se genera enlace a `/personaje-wiki/<slug>/` (no se crean ni editan entradas de personaje).

## Parser de flags
- Regex sugerida: `/@([LPNF])\[(.+?)\]/g`.
- Slug: minúsculas, sin tildes, espacios→`-`, para búsqueda/creación y URL.
- Deduplicar por `tipo+slug` en la misma pasada para evitar doble trabajo.
- Flags malformados: ignorar y loguear; no bloquean la publicación.

## Flujo en el guardado de un `diario` (hook `save_post_diario`)
1) Obtener `post_title` (título de sesión) y `post_content` (texto con flags).
2) Extraer flags y construir lista de tareas por tipo.
3) Procesar `L/N/F`:
   - Buscar por slug en su CPT.
   - Si no existe: `wp_insert_post` con título original, slug normalizado, estado `publish` (o el que corresponda) y bloque inicial de referencia a la sesión.
   - Si existe: añadir bloque de referencia si no está ya (usar meta `diario_id` o `diario_url` para idempotencia).
4) Procesar `P`:
   - Buscar `personaje_wiki` por slug/título. Si no existe: dejar el flag sin enlazar y registrar aviso.
   - Si existe: generar enlace a `/personaje-wiki/<slug>/` y no crear/editar entradas.
5) Procesar `L/N/F` con bloque de referencia como antes y sustituir flags en el contenido del `diario`:
   - Reemplazar por `<a href="URL">Texto</a>` solo si la entidad existe/ha sido creada.
   - Si el personaje no existe, dejar el texto sin enlace (o span neutro) para revisión.
6) Guardar `post_content` actualizado (filtrar `the_content` o actualizar tras crear enlaces) evitando bucles en el hook.

## Bloque/HTML de referencia a insertar en destinos
- Contenido mínimo (propuesta concreta): bloque con enlace al diario (`/diario/<slug>/`), fecha de la sesión y extracto breve (primeros ~200 caracteres). Ejemplo HTML:
  ```html
  <div class="diario-ref">
    <a href="/diario/{{diarioSlug}}/">{{sessionTitle}}</a>
    <span class="diario-ref__date">{{fecha}}</span>
    <p class="diario-ref__excerpt">{{extracto}}</p>
  </div>
  ```
- Ubicación:
  - `lugar/npc/faccion`: sección de historial/notas (alinear con plantilla actual).
  - `personaje-wiki-entry`: el propio contenido de la entrada; la plantilla del personaje ya lista las entradas sección Aventura en `#aventura`.
- Marcar cada bloque con meta o comentario identificable para no duplicar.

## Botón “Crear Nueva Entrada” en `/campaign/{slug}/diario/`
- Insertar dentro del bloque:
  ```html
  <section class="campaign-section">
    <h3 class="campaign-section__title">Diario</h3>
    <div class="campaign-actions">
      <a class="wiki-btn wiki-btn--primary" href="/wp-admin/post-new.php?post_type=diario&campaign={{campanaSlug}}">
        Crear Nueva Entrada
      </a>
    </div>
    <main class="campaign-main">…</main>
  </section>
  ```
- Reemplazar `{{campanaSlug}}` por la variable disponible en la plantilla/shortcode. Ajustar clases a las del tema si ya existen estilos de botón.

## Idempotencia y duplicados
- Meta por entrada creada/actualizada (`L/N/F`): `diario_id`, `diario_url`, `session_title` para evitar repetir bloques.
- Para `P` no se crean entradas; solo se enlaza si existe el personaje.
- Al sustituir flags, usar la lista de entidades confirmadas; si una creación falla, no enlazar ese flag.

## Errores y validación
- Flags malformados: ignorar/loguear.
- Personaje inexistente: no crear, no enlazar; registrar aviso.
- No bloquear la publicación del diario por fallos parciales; dejar flags sin enlace si no se pudieron resolver.

## Pruebas recomendadas
- Unit tests del parser: múltiples flags, repetidos, acentos, mayúsculas, texto con `@` no flag.
- Flujo de guardado: crear/actualizar `lugar/npc/faccion` con un diario de prueba.
- Personaje existente vs inexistente: validar que solo se crean `personaje-wiki-entry` cuando el personaje existe.
- Verificación de enlaces sustituidos en el contenido final del `diario`.

## Decisiones concretas para implementar
- Título de entradas de aventura: usar directamente `post_title` del diario (evita parsing de número de sesión y mantiene consistencia).
- Sustitución de flags: actualizar `post_content` tras procesar las entidades y guardar el post (evita dependencias de render y mantiene el HTML final persistido); proteger el hook para no reentrar.
- Bloque de referencia: usar el bloque propuesto (`diario-ref`) con enlace, fecha y extracto; almacenar también en meta la referencia al diario para idempotencia.
