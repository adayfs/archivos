<?php
/**
 * Gestión de Recursos de Clase (pool genérico) usando ACF repeater `recursos_de_clase`
 * y el catálogo en data/recursos-clase.json.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ruta del catálogo de recursos: intenta primero en el tema hijo (/data/recursos-clase.json), luego fallback al repo 5etools si existe.
function drak_recursos_json_path(): string {
	$theme_path = function_exists( 'get_stylesheet_directory' ) ? trailingslashit( get_stylesheet_directory() ) . 'data/recursos-clase.json' : '';
	if ( $theme_path && file_exists( $theme_path ) ) {
		return $theme_path;
	}
	$fallback = __DIR__ . '/../5etools-src-main/data/recursos-clase.json';
	return $fallback;
}

define( 'DRAK_RECURSOS_FIELD_NAME', 'recursos_de_clase' );
// Clave del repeater importado desde ACF JSON (field_recursos_repeater).
define( 'DRAK_RECURSOS_FIELD_KEY', 'field_recursos_repeater' );

/**
 * Carga el catálogo JSON de recursos.
 */
function drak_recursos_catalogo(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$path = drak_recursos_json_path();
	if ( ! file_exists( $path ) ) {
		error_log( '[recursos-clase] Catálogo no encontrado en ' . $path );
		return [];
	}
	$raw   = file_get_contents( $path );
	$data  = json_decode( $raw, true );
	$cache = $data['resources'] ?? [];
	return $cache;
}

/**
 * Evalúa una fórmula simple con variables sustituidas.
 */
function drak_recursos_eval_formula( string $expr, array $vars ): ?float {
	if ( ! preg_match( '/^[0-9+*\\/().\\-\\sA-Za-z_]*$/', $expr ) ) {
		return null;
	}
	$safe = $expr;
	foreach ( $vars as $k => $v ) {
		$safe = preg_replace( '/\\b' . preg_quote( $k, '/' ) . '\\b/', (string) ( $v ?? 0 ), $safe );
	}
	if ( preg_match( '/[A-Za-z_]/', $safe ) ) {
		return null;
	}
	try {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.eval
		return eval( "return ($safe);" );
	} catch ( Throwable $e ) {
		return null;
	}
}

function drak_recursos_valor_progression( array $progression, int $nivel ): ?int {
	$val = null;
	foreach ( $progression as $row ) {
		if ( ( $row['level'] ?? 0 ) <= $nivel ) {
			$val = (int) $row['value'];
		}
	}
	return $val;
}

function drak_recursos_die_progression( ?array $progression, int $nivel ): ?string {
	if ( ! $progression ) {
		return null;
	}
	$val = null;
	foreach ( $progression as $row ) {
		if ( ( $row['level'] ?? 0 ) <= $nivel ) {
			$val = $row['die'];
		}
	}
	return $val;
}

/**
 * Obtiene contexto del personaje desde campos ACF existentes.
 */
function drak_recursos_get_ctx( int $post_id ): array {
	if ( ! function_exists( 'get_field' ) ) {
		return [];
	}
	$level      = intval( get_field( 'nivel', $post_id ) );
	$class_raw  = get_field( 'clase', $post_id );
	// ACF puede devolver string o array con value/label
	if ( is_array( $class_raw ) ) {
		$class_raw = $class_raw['value'] ?? $class_raw['label'] ?? '';
	}
	$class_slug_raw = $class_raw ? sanitize_title( $class_raw ) : '';
	$sub_raw        = get_field( 'subclase', $post_id );
	if ( is_array( $sub_raw ) ) {
		$sub_raw = $sub_raw['value'] ?? $sub_raw['label'] ?? '';
	}
	$mods       = [
		'str' => intval( get_field( 'cs_fuerza_mod', $post_id ) ),
		'dex' => intval( get_field( 'cs_destreza_mod', $post_id ) ),
		'con' => intval( get_field( 'cs_constitucion_mod', $post_id ) ),
		'int' => intval( get_field( 'cs_inteligencia_mod', $post_id ) ),
		'wis' => intval( get_field( 'cs_sabiduria_mod', $post_id ) ),
		'cha' => intval( get_field( 'cs_carisma_mod', $post_id ) ),
	];
	$prof = intval( get_field( 'cs_proeficiencia', $post_id ) );
	if ( ! $prof ) {
		$prof = max( 2, 1 + intdiv( max( 1, $level ) + 3, 4 ) );
	}

	// Alias de clases/subclases en español -> slugs del catálogo
	$class_alias = [
		'monje'         => 'monk',
		'monk'          => 'monk',
		'monk-phb'      => 'monk',
		'monk-phb-classic' => 'monk',
		'fighter-phb-classic' => 'fighter',
		'barbarian-phb-classic' => 'barbarian',
		'bard-phb-classic'      => 'bard',
		'cleric-phb-classic'    => 'cleric',
		'druid-phb-classic'     => 'druid',
		'paladin-phb-classic'   => 'paladin',
		'sorcerer-phb-classic'  => 'sorcerer',
		'warlock-phb-classic'   => 'warlock',
		'wizard-phb-classic'    => 'wizard',
		'barbaro'       => 'barbarian',
		'bárbaro'       => 'barbarian',
		'barbarian'     => 'barbarian',
		'bardo'         => 'bard',
		'bard'          => 'bard',
		'clerigo'       => 'cleric',
		'clérigo'       => 'cleric',
		'cleric'        => 'cleric',
		'druida'        => 'druid',
		'druid'         => 'druid',
		'guerrero'      => 'fighter',
		'fighter'       => 'fighter',
		'paladin'       => 'paladin',
		'paladín'       => 'paladin',
		'paladin-phb'   => 'paladin',
		'hechicero'     => 'sorcerer',
		'sorcerer'      => 'sorcerer',
		'brujo'         => 'warlock',
		'warlock'       => 'warlock',
		'mago'          => 'wizard',
		'wizard'        => 'wizard',
		'explorador'    => 'ranger',
		'ranger'        => 'ranger',
	];
	$sub_alias = [
		'maestro-de-batalla' => 'battle-master',
		'battle-master'      => 'battle-master',
		'fighter-battle-master-phb-classic' => 'battle-master',
	];

	$class_slug = $class_alias[ $class_slug_raw ] ?? $class_slug_raw;
	$sub_slug   = $sub_raw ? sanitize_title( $sub_raw ) : '';
	$sub_slug   = $sub_alias[ $sub_slug ] ?? $sub_slug;

	// Si no mapeó, intenta heurística: si contiene texto inglés conocido.
	if ( ! $class_slug && $class_raw ) {
		$lower = strtolower( $class_raw );
		foreach ( $class_alias as $k => $v ) {
			if ( strpos( $lower, $k ) !== false ) {
				$class_slug = $v;
				break;
			}
		}
	}

	return [
		'classLevels' => $class_slug ? [ $class_slug => $level ] : [],
		'subclass'    => $sub_slug,
		'totalLevel'  => $level,
		'mods'        => $mods,
		'prof'        => $prof,
	];
}

/**
 * Construye instancia de recurso con max/dado calculados.
 */
function drak_recursos_build_instance( array $def, array $ctx, array $existing = null ): array {
	$nivel = $ctx['classLevels'][ $def['classId'] ] ?? 0;
	$vars  = [
		'classLevel' => $nivel,
		'totalLevel' => $ctx['totalLevel'] ?? $nivel,
		'prof'       => $ctx['prof'] ?? 2,
		'strMod'     => $ctx['mods']['str'] ?? 0,
		'dexMod'     => $ctx['mods']['dex'] ?? 0,
		'conMod'     => $ctx['mods']['con'] ?? 0,
		'intMod'     => $ctx['mods']['int'] ?? 0,
		'wisMod'     => $ctx['mods']['wis'] ?? 0,
		'chaMod'     => $ctx['mods']['cha'] ?? 0,
	];

	$max = null;
	if ( ! empty( $def['max']['formula'] ) ) {
		$max = drak_recursos_eval_formula( $def['max']['formula'], $vars );
	}
	if ( $max === null && ! empty( $def['max']['progression'] ) ) {
		$max = drak_recursos_valor_progression( $def['max']['progression'], $nivel );
	}
	$max = max( 0, (int) ( $max ?? 0 ) );

	$die = drak_recursos_die_progression( $def['dice']['dieSizeProgression'] ?? null, $nivel );

	$current = $max;
	if ( $existing ) {
		$current = min( $max, max( 0, (int) ( $existing['current'] ?? $max ) ) );
	}

	return [
		'resource_id'    => $def['id'],
		'class_id'       => $def['classId'],
		'subclass_id'    => $def['subclassId'] ?? '',
		'type'           => $def['type'],
		'current'        => $current,
		'max'            => $max,
		'die_size'       => $die,
		'merge_key'      => $def['mergeKey'] ?? '',
		'source'         => $def['source'] ?? '',
		'level_snapshot' => $nivel,
	];
}

/**
 * Recalcula y persiste el repeater de recursos aplicables al personaje.
 */
function drak_recursos_upsert_para_personaje( int $post_id ) {
	if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
		return;
	}
	$ctx      = drak_recursos_get_ctx( $post_id );
	$catalogo = drak_recursos_catalogo();
	$exist    = drak_recursos_get_rows( $post_id );
	$map_exist = [];
	foreach ( $exist as $row ) {
		if ( ! empty( $row['resource_id'] ) ) {
			$map_exist[ $row['resource_id'] ] = $row;
		}
	}

	$rows = [];
	foreach ( $catalogo as $def ) {
		$nivelClase = $ctx['classLevels'][ $def['classId'] ] ?? 0;
		if ( $nivelClase <= 0 ) {
			continue;
		}
		// Si el recurso es de subclase y no coincide, saltar
		if ( ! empty( $def['subclassId'] ) && $ctx['subclass'] !== sanitize_title( $def['subclassId'] ) ) {
			continue;
		}
		$existing = $map_exist[ $def['id'] ] ?? null;
		$rows[]   = drak_recursos_build_instance( $def, $ctx, $existing );
	}

	drak_recursos_set_rows( $post_id, $rows );
}

/**
 * Aplica recarga por descanso.
 */
function drak_recursos_restaurar_descanso( int $post_id, string $tipo ) {
	$rows     = get_field( 'recursos_de_clase', $post_id ) ?: [];
	$catalogo = drak_recursos_catalogo();
	$defs     = [];
	foreach ( $catalogo as $d ) {
		$defs[ $d['id'] ] = $d;
	}
	foreach ( $rows as &$row ) {
		$def  = $defs[ $row['resource_id'] ] ?? null;
		$rest = $def['recharge']['rest'] ?? [];
		$mode = $def['recharge']['mode'] ?? 'full';
		if ( $mode === 'full' && in_array( $tipo, $rest, true ) ) {
			$row['current'] = $row['max'];
		} elseif ( $mode === 'custom' ) {
			/**
			 * Permite recargas parciales/condicionales.
			 * Filtro: recursos_custom_recharge($result,$current,$def,$row,$tipo,$post_id)
			 */
			$custom = apply_filters( 'recursos_custom_recharge', null, $row['current'], $def, $row, $tipo, $post_id );
			if ( $custom !== null ) {
				$row['current'] = max( 0, min( $row['max'], (int) $custom ) );
			}
		}
	}
	unset( $row );
	update_field( 'recursos_de_clase', $rows, $post_id );
}

function drak_recursos_buscar_index( array $rows, string $resource_id ): int {
	foreach ( $rows as $i => $row ) {
		if ( ( $row['resource_id'] ?? '' ) === $resource_id ) {
			return $i;
		}
	}
	return -1;
}

function drak_recursos_consumir( int $post_id, string $resource_id, int $amount ): ?array {
	$amount = max( 1, $amount );
	$rows   = get_field( 'recursos_de_clase', $post_id ) ?: [];
	$idx    = drak_recursos_buscar_index( $rows, $resource_id );
	if ( $idx === -1 ) {
		return null;
	}
	$rows[ $idx ]['current'] = max( 0, $rows[ $idx ]['current'] - $amount );
	drak_recursos_set_rows( $post_id, $rows );
	return $rows[ $idx ];
}

function drak_recursos_restaurar_manual( int $post_id, string $resource_id, int $amount ): ?array {
	$amount = max( 1, $amount );
	$rows   = get_field( 'recursos_de_clase', $post_id ) ?: [];
	$idx    = drak_recursos_buscar_index( $rows, $resource_id );
	if ( $idx === -1 ) {
		return null;
	}
	$rows[ $idx ]['current'] = min( $rows[ $idx ]['max'], $rows[ $idx ]['current'] + $amount );
	drak_recursos_set_rows( $post_id, $rows );
	return $rows[ $idx ];
}

/**
 * Helpers de catálogo y render
 */
function drak_recursos_catalogo_map(): array {
	static $map = null;
	if ( $map !== null ) {
		return $map;
	}
	$map = [];
	foreach ( drak_recursos_catalogo() as $def ) {
		$map[ $def['id'] ] = $def;
	}
	return $map;
}

/**
 * Helpers para leer/escribir el repeater de forma robusta.
 */
function drak_recursos_get_rows( int $post_id ): array {
	if ( function_exists( 'get_field' ) ) {
		$rows = get_field( DRAK_RECURSOS_FIELD_NAME, $post_id );
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			return $rows;
		}
		$rows = get_field( DRAK_RECURSOS_FIELD_KEY, $post_id );
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			return $rows;
		}
	}
	$meta = get_post_meta( $post_id, DRAK_RECURSOS_FIELD_NAME, true );
	return is_array( $meta ) ? $meta : [];
}

function drak_recursos_set_rows( int $post_id, array $rows ): void {
	if ( function_exists( 'update_field' ) ) {
		// Intenta por key y por name para cubrir ambos casos.
		update_field( DRAK_RECURSOS_FIELD_KEY, $rows, $post_id );
		update_field( DRAK_RECURSOS_FIELD_NAME, $rows, $post_id );
	} else {
		update_post_meta( $post_id, DRAK_RECURSOS_FIELD_NAME, $rows );
	}
}

function drak_recursos_render_block( int $post_id ): string {
	try {
		if ( ! function_exists( 'get_field' ) ) {
			return '<div class="recursos-clase-block"><h3>Recursos de Clase</h3><p class="recursos-empty">ACF no está disponible en frontend.</p></div>';
		}
	// Recalcula siempre según clase/nivel actuales para evitar arrastrar recursos de otra clase.
	drak_recursos_upsert_para_personaje( $post_id );
	$rows = drak_recursos_get_rows( $post_id );
	if ( empty( $rows ) ) {
		// Info extra para depurar: clase y nivel detectados.
		$ctx       = drak_recursos_get_ctx( $post_id );
		$clase_dbg = implode( ',', array_keys( $ctx['classLevels'] ?? [] ) );
		return '<div class="recursos-clase-block"><h3>Recursos de Clase</h3><p class="recursos-empty">Este personaje no tiene recursos configurados.</p><p class="recursos-debug">Clase detectada: ' . esc_html( $clase_dbg ?: 'ninguna' ) . ' · Nivel: ' . esc_html( (string) ( $ctx['totalLevel'] ?? 0 ) ) . '</p></div>';
		}
	} catch ( Throwable $e ) {
		error_log( '[recursos-clase] Error render_block: ' . $e->getMessage() );
		return '<div class="recursos-clase-block"><h3>Recursos de Clase</h3><p class="recursos-empty">No se pudo cargar recursos (ver log).</p><p class="recursos-debug">Error: ' . esc_html( $e->getMessage() ) . '</p></div>';
	}

	$defs   = drak_recursos_catalogo_map();
	$groups = [];
	foreach ( $rows as $row ) {
		$def      = $defs[ $row['resource_id'] ] ?? [];
		$group    = $def['display']['group'] ?? ucfirst( $row['class_id'] ?? 'Clase' );
		$order    = $def['display']['order'] ?? 50;
		$groups[ $group ]['order']   = $order;
		$groups[ $group ]['items'][] = [ 'row' => $row, 'def' => $def ];
	}
	// Orden de grupos
	uksort(
		$groups,
		function( $a, $b ) use ( $groups ) {
			return ( $groups[ $a ]['order'] ?? 0 ) <=> ( $groups[ $b ]['order'] ?? 0 );
		}
	);

	$rest_base = esc_url_raw( rest_url( 'recursos/v1' ) );
	$nonce     = wp_create_nonce( 'wp_rest' );

	ob_start();
	?>
	<div class="recursos-clase-block" data-recursos-block="1" data-endpoint="<?php echo esc_attr( $rest_base ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	  <?php foreach ( $groups as $group_name => $data ) : ?>
	    <div class="recurso-group">
	      <h4><?php echo esc_html( $group_name ); ?></h4>
	      <div class="recurso-grid">
	      <?php foreach ( $data['items'] as $item ) :
		      $row     = $item['row'];
		      $def     = $item['def'];
		      $rest    = implode( '/', $def['recharge']['rest'] ?? [] );
		      $die     = $row['die_size'] ?: ( $def['dice']['dieSizeProgression'][0]['die'] ?? '' );
		      $type    = strtoupper( $row['type'] );
		      $used    = max( 0, (int) $row['max'] - (int) $row['current'] );
		      $max     = max( 0, (int) $row['max'] );
		      ?>
	        <div class="recurso-card" data-resource-id="<?php echo esc_attr( $row['resource_id'] ); ?>" data-current="<?php echo esc_attr( $row['current'] ); ?>" data-max="<?php echo esc_attr( $row['max'] ); ?>">
	          <div class="recurso-card-head">
	            <div>
	              <p class="recurso-type"><?php echo esc_html( $type ); ?></p>
	              <p class="recurso-name"><?php echo esc_html( $def['name'] ?? $row['resource_id'] ); ?></p>
	            </div>
	            <div class="recurso-badges">
	              <span class="recurso-count"><?php echo esc_html( $row['current'] . ' / ' . $row['max'] ); ?></span>
	              <?php if ( $die ) : ?>
	                <span class="recurso-die"><?php echo esc_html( $die ); ?></span>
	              <?php endif; ?>
	            </div>
	          </div>
	          <div class="recurso-track" data-used="<?php echo esc_attr( $used ); ?>">
	            <?php for ( $i = 1; $i <= $max; $i++ ) : ?>
	              <label class="recurso-slot">
	                <input type="checkbox" <?php checked( $i <= $used ); ?> data-index="<?php echo esc_attr( $i ); ?>">
	                <span></span>
	              </label>
	            <?php endfor; ?>
	          </div>
	          <p class="recurso-meta">Recarga: <?php echo esc_html( $rest ?: '—' ); ?><?php if ( ! empty( $def['notes'] ) ) echo ' · ' . esc_html( $def['notes'] ); ?></p>
	        </div>
	      <?php endforeach; ?>
	      </div>
	    </div>
	  <?php endforeach; ?>
	  <div class="recursos-clase-rest">
	    <button type="button" class="recurso-rest-btn" data-rest="short">Descanso corto</button>
	    <button type="button" class="recurso-rest-btn" data-rest="long">Descanso largo</button>
	  </div>
	</div>
	<style>
	.recursos-clase-block { background: #0f0f13; border: 1px solid #2a2a33; border-radius: 12px; padding: 16px; margin: 20px 0; color: #e8e8f0; }
	.recursos-clase-rest { display:flex; gap:8px; margin-top:12px; }
	.recursos-clase-rest button { flex:1; background:#1c2633; border:1px solid #355379; color:#dceeff; padding:10px 12px; border-radius:8px; cursor:pointer; text-align:center; }
	.recurso-group { margin-top: 14px; }
	.recurso-group h4 { margin: 8px 0; color:#9bd8ff; }
	.recurso-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:12px; }
	.recurso-card { background:#131823; border:1px solid #273146; border-radius:10px; padding:12px; text-align:center; }
	.recurso-card-head { display:flex; flex-direction:column; gap:4px; align-items: center; justify-content:center; }
	.recurso-type { margin:0; font-size:11px; letter-spacing:0.08em; color:#9ca7c2; text-transform: uppercase; }
	.recurso-name { margin:0; font-weight:600; }
	.recurso-badges { display:flex; gap:6px; align-items:center; justify-content:center; }
	.recurso-die { background:#1f2b3b; border:1px solid #37465d; border-radius:6px; padding:4px 8px; font-size:12px; }
	.recurso-count { background:#1a2434; border:1px solid #2f3b52; border-radius:6px; padding:4px 8px; font-size:12px; color:#dceeff; }
	.recurso-track { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; justify-content:center; }
	.recurso-slot { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:6px; background:#0f121a; border:1px solid #2e3c52; cursor:pointer; }
	.recurso-slot input { display:none; }
	.recurso-slot span { width:14px; height:14px; border-radius:4px; border:1px solid #2e3c52; background:#18202c; display:block; }
	.recurso-slot input:checked + span { background:#c34fff; border-color:#c34fff; box-shadow:0 0 0 1px rgba(195,79,255,0.3); }
	.recurso-meta { margin:6px 0 0; color:#7f8aa8; font-size:13px; }
	.recursos-empty { margin: 8px 0 0; color:#9ca7c2; }
	</style>
	<script>
	(() => {
	  const block = document.querySelector('[data-recursos-block]');
	  if (!block) return;
	  const endpoint = block.dataset.endpoint;
	  const postId = block.dataset.postId;
	  const nonce = block.dataset.nonce;

	  async function callApi(path, payload) {
	    const res = await fetch(`${endpoint}${path}`, {
	      method: 'POST',
	      credentials: 'same-origin',
	      headers: {
	        'Content-Type': 'application/json',
	        'X-WP-Nonce': nonce
	      },
	      body: JSON.stringify(Object.assign({ post_id: postId }, payload))
	    });
	    if (!res.ok) {
	      throw new Error(`Error ${res.status}`);
	    }
	    return res.json();
	  }

	  function updateCard(card, data) {
	    card.dataset.current = data.current;
	    card.dataset.max = data.max;
	    const count = card.querySelector('.recurso-count');
	    if (count) {
	      count.textContent = `${data.current} / ${data.max}`;
	    }
	  }

	  function rebuildTrack(card, data) {
	    const track = card.querySelector('.recurso-track');
	    if (!track) return;
	    const max = Math.max(0, parseInt(data.max || 0, 10));
	    const current = Math.max(0, parseInt(data.current || 0, 10));
	    const used = Math.max(0, max - current);
	    track.dataset.used = used;
	    let html = '';
	    for (let i = 1; i <= max; i++) {
	      const checked = i <= used ? 'checked' : '';
	      html += `<label class="recurso-slot"><input type="checkbox" data-index="${i}" ${checked}><span></span></label>`;
	    }
	    track.innerHTML = html;
	  }

	  // Inicializa tracks con estado actual
	  block.querySelectorAll('.recurso-card').forEach((card) => {
	    const data = {
	      current: parseInt(card.dataset.current || '0', 10),
	      max: parseInt(card.dataset.max || '0', 10),
	    };
	    rebuildTrack(card, data);
	  });

	  function syncCard(card, data) {
	    updateCard(card, data);
	    rebuildTrack(card, data);
	  }

	  block.addEventListener('click', async (ev) => {
	    const card = ev.target.closest('.recurso-card');
	    if (ev.target.matches('.recurso-rest-btn')) {
	      ev.preventDefault();
	      const type = ev.target.dataset.rest;
	      callApi('/rest', { type })
	        .then((list) => {
	          if (Array.isArray(list)) {
	            list.forEach((row) => {
	              const c = block.querySelector(`.recurso-card[data-resource-id="${row.resource_id}"]`);
	              if (c) syncCard(c, row);
	            });
	          }
	        })
	        .catch(console.error);
	    }
	  });

	  block.addEventListener('change', async (ev) => {
	    const card = ev.target.closest('.recurso-card');
	    if (!card || !ev.target.matches('.recurso-slot input')) return;
	    const rid = card.dataset.resourceId;
	    const max = parseInt(card.dataset.max || '0', 10);
	    const current = parseInt(card.dataset.current || '0', 10);
	    const desiredUsed = card.querySelectorAll('.recurso-slot input:checked').length;
	    const currentUsed = Math.max(0, max - current);
	    const delta = desiredUsed - currentUsed;
	    if (delta === 0) return;
	    const amount = Math.abs(delta);
	    const path = delta > 0 ? '/consume' : '/restore';
	    try {
	      const data = await callApi(path, { resource_id: rid, amount });
	      syncCard(card, data);
	    } catch(e) {
	      console.error(e);
	      // revert visual state
	      rebuildTrack(card, { current, max });
	    }
	  });
	})();
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Hooks
 */
add_action(
	'save_post_personaje',
	function( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		drak_recursos_upsert_para_personaje( $post_id );
	},
	50,
	3
);

/**
 * Endpoints REST básicos (refuerza permisos/nonce según tu flujo).
 */
add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'recursos/v1',
			'/consume',
			[
				'methods'             => 'POST',
				'callback'            => function( $req ) {
					$post_id = (int) $req['post_id'];
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new WP_Error( 'forbidden', 'Sin permisos', [ 'status' => 403 ] );
					}
					$rid    = sanitize_text_field( $req['resource_id'] ?? '' );
					$amount = (int) ( $req['amount'] ?? 1 );
					$res    = drak_recursos_consumir( $post_id, $rid, $amount );
					if ( $res === null ) {
						return new WP_Error( 'not_found', 'Recurso no encontrado', [ 'status' => 404 ] );
					}
					return $res;
				},
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'recursos/v1',
			'/restore',
			[
				'methods'             => 'POST',
				'callback'            => function( $req ) {
					$post_id = (int) $req['post_id'];
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new WP_Error( 'forbidden', 'Sin permisos', [ 'status' => 403 ] );
					}
					$rid    = sanitize_text_field( $req['resource_id'] ?? '' );
					$amount = (int) ( $req['amount'] ?? 1 );
					$res    = drak_recursos_restaurar_manual( $post_id, $rid, $amount );
					if ( $res === null ) {
						return new WP_Error( 'not_found', 'Recurso no encontrado', [ 'status' => 404 ] );
					}
					return $res;
				},
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'recursos/v1',
			'/rest',
			[
				'methods'             => 'POST',
				'callback'            => function( $req ) {
					$post_id = (int) $req['post_id'];
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new WP_Error( 'forbidden', 'Sin permisos', [ 'status' => 403 ] );
					}
					$tipo = sanitize_text_field( $req['type'] ?? 'short' ); // short|long
					drak_recursos_restaurar_descanso( $post_id, $tipo );
					return drak_recursos_get_rows( $post_id );
				},
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'recursos/v1',
			'/recalc',
			[
				'methods'             => 'POST',
				'callback'            => function( $req ) {
					$post_id = (int) $req['post_id'];
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new WP_Error( 'forbidden', 'Sin permisos', [ 'status' => 403 ] );
					}
					drak_recursos_upsert_para_personaje( $post_id );
					return drak_recursos_get_rows( $post_id );
				},
				'permission_callback' => '__return_true',
			]
		);
	}
);
