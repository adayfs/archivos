Esqueleto PHP (WordPress + ACF) para poblar y gestionar el repeater `recursos_de_clase` desde `data/recursos-clase.json`. Adapta rutas y funciones de obtención de nivel/atributos a tu ficha.

```php
<?php
/**
 * Requiere ACF y el JSON en data/recursos-clase.json.
 * Ajusta RUTA_JSON y las funciones get_personaje_context(), get_rest_event().
 */

const RUTA_JSON_RECURSOS = __DIR__ . '/../5etools-src-main/data/recursos-clase.json';

function recursos_catalogo(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    if (!file_exists(RUTA_JSON_RECURSOS)) return [];
    $json = file_get_contents(RUTA_JSON_RECURSOS);
    $data = json_decode($json, true);
    $cache = $data['resources'] ?? [];
    return $cache;
}

function recursos_eval_formula(string $expr, array $vars): ?float {
    // Permite solo letras, números, operadores básicos y paréntesis
    if (!preg_match('/^[0-9+*\\/().\\-\\sA-Za-z_]*$/', $expr)) return null;
    // Sustituye variables permitidas
    $safe = $expr;
    foreach ($vars as $k => $v) {
        $safe = preg_replace('/\\b' . preg_quote($k, '/') . '\\b/', (string)($v ?? 0), $safe);
    }
    // Si quedan identificadores sin resolver, aborta
    if (preg_match('/[A-Za-z_]/', $safe)) return null;
    try {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.eval
        return eval("return ($safe);");
    } catch (Throwable $e) {
        return null;
    }
}

function recursos_valor_progression(array $progression, int $nivel): ?int {
    $val = null;
    foreach ($progression as $row) {
        if (($row['level'] ?? 0) <= $nivel) {
            $val = (int)$row['value'];
        }
    }
    return $val;
}

function recursos_die_progression(?array $progression, int $nivel): ?string {
    if (!$progression) return null;
    $val = null;
    foreach ($progression as $row) {
        if (($row['level'] ?? 0) <= $nivel) {
            $val = $row['die'];
        }
    }
    return $val;
}

function recursos_build_instance(array $def, array $ctx): array {
    $nivel = $ctx['classLevels'][$def['classId']] ?? 0;
    $vars = array_merge(
        [
            'classLevel' => $nivel,
            'totalLevel' => $ctx['totalLevel'] ?? $nivel,
            'prof' => $ctx['prof'] ?? 2,
            'strMod' => $ctx['mods']['str'] ?? 0,
            'dexMod' => $ctx['mods']['dex'] ?? 0,
            'conMod' => $ctx['mods']['con'] ?? 0,
            'intMod' => $ctx['mods']['int'] ?? 0,
            'wisMod' => $ctx['mods']['wis'] ?? 0,
            'chaMod' => $ctx['mods']['cha'] ?? 0,
        ],
        $ctx['varsExtra'] ?? []
    );

    $max = null;
    if (!empty($def['max']['formula'])) {
        $max = recursos_eval_formula($def['max']['formula'], $vars);
    }
    if ($max === null && !empty($def['max']['progression'])) {
        $max = recursos_valor_progression($def['max']['progression'], $nivel);
    }
    $max = max(0, (int)($max ?? 0));

    $die = recursos_die_progression($def['dice']['dieSizeProgression'] ?? null, $nivel);

    return [
        'resource_id' => $def['id'],
        'class_id' => $def['classId'],
        'subclass_id' => $def['subclassId'] ?? '',
        'type' => $def['type'],
        'current' => $max, // inicial al máximo
        'max' => $max,
        'die_size' => $die,
        'merge_key' => $def['mergeKey'] ?? '',
        'source' => $def['source'] ?? '',
        'level_snapshot' => $nivel,
    ];
}

function recursos_get_ctx(int $post_id): array {
    // TODO: sustituir por cómo guardas niveles y mods en tu ficha
    $classLevels = get_field('clases', $post_id) ?: []; // esperar array ['monk' => 5, ...]
    $mods = get_field('mods', $post_id) ?: []; // ['str'=>2,'dex'=>3,...]
    $totalLevel = array_sum($classLevels);
    $prof = max(2, 1 + intdiv(max(1, $totalLevel) + 3, 4)); // aproximado
    return [
        'classLevels' => $classLevels,
        'mods' => $mods,
        'totalLevel' => $totalLevel,
        'prof' => $prof,
    ];
}

function recursos_upsert_para_personaje(int $post_id) {
    $ctx = recursos_get_ctx($post_id);
    $catalogo = recursos_catalogo();
    $rows = [];
    foreach ($catalogo as $def) {
        $nivelClase = $ctx['classLevels'][$def['classId']] ?? 0;
        if ($nivelClase <= 0) continue; // recurso no aplica
        $rows[] = recursos_build_instance($def, $ctx);
    }
    update_field('recursos_de_clase', $rows, $post_id);
}

// Hooks de ejemplo
add_action('save_post_personaje', function($post_id, $post, $update){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    recursos_upsert_para_personaje($post_id);
}, 20, 3);

function recursos_restaurar_descanso(int $post_id, string $tipo) {
    $rows = get_field('recursos_de_clase', $post_id) ?: [];
    $catalogo = recursos_catalogo();
    $defs = [];
    foreach ($catalogo as $d) { $defs[$d['id']] = $d; }
    foreach ($rows as &$row) {
        $def = $defs[$row['resource_id']] ?? null;
        if (!$def) continue;
        $rest = $def['recharge']['rest'] ?? [];
        $mode = $def['recharge']['mode'] ?? 'full';
        if ($mode === 'full' && in_array($tipo, $rest, true)) {
            $row['current'] = $row['max'];
        } elseif ($mode === 'custom') {
            /**
             * Filtro para recargas parciales: devuelve current nuevo o null para ignorar.
             * add_filter('recursos_custom_recharge', fn($current,$def,$row,$tipo,$post_id)=>... );
             */
            $custom = apply_filters('recursos_custom_recharge', null, $row['current'], $def, $row, $tipo, $post_id);
            if ($custom !== null) $row['current'] = max(0, min($row['max'], (int)$custom));
        }
    }
    update_field('recursos_de_clase', $rows, $post_id);
}

function recursos_buscar_index(array $rows, string $resourceId): int {
    foreach ($rows as $i => $row) {
        if (($row['resource_id'] ?? '') === $resourceId) return $i;
    }
    return -1;
}

function recursos_consumir(int $post_id, string $resourceId, int $amount): ?array {
    $amount = max(1, $amount);
    $rows = get_field('recursos_de_clase', $post_id) ?: [];
    $idx = recursos_buscar_index($rows, $resourceId);
    if ($idx === -1) return null;
    $rows[$idx]['current'] = max(0, $rows[$idx]['current'] - $amount);
    update_field('recursos_de_clase', $rows, $post_id);
    return $rows[$idx];
}

function recursos_restaurar(int $post_id, string $resourceId, int $amount): ?array {
    $amount = max(1, $amount);
    $rows = get_field('recursos_de_clase', $post_id) ?: [];
    $idx = recursos_buscar_index($rows, $resourceId);
    if ($idx === -1) return null;
    $rows[$idx]['current'] = min($rows[$idx]['max'], $rows[$idx]['current'] + $amount);
    update_field('recursos_de_clase', $rows, $post_id);
    return $rows[$idx];
}

// Endpoints básicos (endurece permisos y nonce en tu código)
add_action('rest_api_init', function() {
    register_rest_route('recursos/v1', '/consume', [
        'methods' => 'POST',
        'callback' => function($req) {
            $post_id = (int)$req['post_id'];
            $rid = sanitize_text_field($req['resource_id'] ?? '');
            $amount = (int)($req['amount'] ?? 1);
            $res = recursos_consumir($post_id, $rid, $amount);
            if ($res === null) return new WP_Error('not_found', 'Recurso no encontrado', 404);
            return $res;
        },
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('recursos/v1', '/restore', [
        'methods' => 'POST',
        'callback' => function($req) {
            $post_id = (int)$req['post_id'];
            $rid = sanitize_text_field($req['resource_id'] ?? '');
            $amount = (int)($req['amount'] ?? 1);
            $res = recursos_restaurar($post_id, $rid, $amount);
            if ($res === null) return new WP_Error('not_found', 'Recurso no encontrado', 404);
            return $res;
        },
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('recursos/v1', '/rest', [
        'methods' => 'POST',
        'callback' => function($req) {
            $post_id = (int)$req['post_id'];
            $tipo = sanitize_text_field($req['type'] ?? 'short'); // short|long
            recursos_restaurar_descanso($post_id, $tipo);
            return get_field('recursos_de_clase', $post_id);
        },
        'permission_callback' => '__return_true',
    ]);
});
```

Notas:
- Ajusta `RUTA_JSON_RECURSOS` según dónde tengas el `recursos-clase.json`.
- `recursos_get_ctx` debe adaptarse a cómo guardas niveles y mods en tu ficha.
- Para producción, refina seguridad REST (nonce/capabilities) y usa `resource_id` para las operaciones, como en los endpoints de ejemplo.
- El filtro `recursos_custom_recharge` te permite implementar recargas parciales/condicionales por recurso.
