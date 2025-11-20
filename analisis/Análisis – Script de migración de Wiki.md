# Análisis – Script de migración de Wiki (categorías) a nuevos CPT

## 1. Objetivo

Mover el contenido existente de Wiki, que ahora está en `post` + categorías, a los nuevos **Custom Post Types**:

- `npc`
- `lugar`
- `faccion`
- `lore-entry` (si procede)

sin perder:

- IDs de las entradas,
- campo ACF `campaign`,
- ni romper el portal de campaña.

La migración debe ser **un proceso controlado**, ejecutado una sola vez y reversible mediante copia de seguridad.

---

## 2. Estado actual

- CPTs nuevos creados y visibles en el admin:
  - `NPCs`  → CPT `npc`
  - `Lugares` → CPT `lugar`
  - `Facciones` → CPT `faccion`
  - `Lore` → CPT `lore-entry`
- Field Group ACF “Asignación de campaña” ya visible en esos CPT.
- Todo el contenido de Drakkenheim está aún como:
  - `post_type = post`
  - Taxonomía `category` con slugs como:
    - `npc`
    - `lugares`
    - `facciones`
    - `wiki`
- Todas esas entradas ya tienen el campo ACF `campaign` apuntando a la campaña “Crónicas de Drakkenheim” (script previo).

---

## 3. Mapeo categorías → CPT

Migración propuesta:

- Posts con categoría **slug `npc`**    → `post_type = npc`
- Posts con categoría **slug `lugares`** → `post_type = lugar`
- Posts con categoría **slug `facciones`** → `post_type = faccion`
- (Opcional) posts con categoría **slug `wiki`** que NO estén ya en npc/lugares/facciones → `post_type = lore-entry`

> Nota: los slugs `npc`, `lugares`, `facciones`, `wiki` deben confirmarse.  
> Si en la instalación real difieren, hay que ajustar los slugs en el script.

La idea es:

- No tocar aún las entradas marcadas solo como `wiki` si no está claro el criterio; o bien migrarlas todas a `lore-entry` si así se decide.

---

## 4. Estrategia de migración

1. **Copia de seguridad** de la base de datos antes de hacer nada.
2. Implementar una función de migración en PHP que:
   - Solo se ejecute en admin.
   - Solo se dispare cuando se invoque con un parámetro GET (por ejemplo `?migrar_wiki_cpts=1`).
3. Para cada par **categoría → CPT**:
   - Buscar todos los `post` con esa categoría.
   - Cambiarles el `post_type` al CPT correspondiente vía `wp_update_post`.
4. Mostrar un resumen al finalizar:
   - Cuántos posts se han migrado a cada CPT.
5. Desactivar/eliminar el código tras la ejecución.

Detalles a tener en cuenta:

- La taxonomía `category` está registrada por defecto solo para `post`; al cambiar el `post_type`, las relaciones de categoría dejarán de ser relevantes.  
  → Es correcto: a partir de ahora la identidad del tipo la da el `post_type`, no la categoría.
- El campo ACF `campaign` **no debe tocarse**, ya está correcto y es la clave para el portal de campaña.

---

## 5. Lógica de ejemplo (pseudocódigo)

```php
$mapping = [
    'npc'       => 'npc',
    'lugares'   => 'lugar',
    'facciones' => 'faccion',
    // 'wiki'   => 'lore-entry' (opcional, solo si se decide así)
];

foreach ( $mapping as $category_slug => $new_cpt ) {
    // 1) Buscar posts con esa categoría.
    // 2) Para cada ID, hacer wp_update_post( [ 'ID' => $id, 'post_type' => $new_cpt ] );
}
