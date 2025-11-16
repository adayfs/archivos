<?php
/**
 * temaHijo Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package temaHijo
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_TEMAHIJO_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'temahijo-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_TEMAHIJO_VERSION, 'all' );
    wp_enqueue_style('tema-principal', get_stylesheet_directory_uri() . '/style.css');
    wp_enqueue_style('estilos-personaje', get_stylesheet_directory_uri() . '/css/personaje.css');
    wp_enqueue_style('estilos-inventario', get_stylesheet_directory_uri() . '/css/inventario.css');
	wp_enqueue_style('estilos-hoja-personaje', get_stylesheet_directory_uri() . '/css/hoja-personaje.css');
    wp_enqueue_style('estilos-grimorio', get_stylesheet_directory_uri() . '/css/grimorio.css');

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

function enqueue_custom_scripts() {
    if (get_query_var('inventario_personaje') == 1 || is_page_template('page-inventario-personaje.php')) {
        wp_enqueue_script(
            'inventario-armas',
            get_stylesheet_directory_uri() . '/js/inventario-armas.js',
            array(),
            null,
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');

// Mover extracto dentro de entry-content
add_action('wp_footer', function(){
  if (is_category()) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!document.body.classList.contains('archive')) return;
      document.querySelectorAll('.ast-article-post').forEach(function (article) {
        const exWrap = article.querySelector('.ast-excerpt-container');
        const entry  = article.querySelector('.entry-content.clear');
        if (exWrap) exWrap.classList.add('entry-content', 'clear');
        if (entry && entry.textContent.trim() === '') entry.remove();
      });
    });
    </script>
  <?php endif;
});

/** ========= Placeholders por categoría ========= **/

// Mapea slugs de categoría => ID de la imagen placeholder (Mediateca)
function drak_placeholder_map() {
    return array(
        'npc'     => 2872,  // <— pon aquí ID numérico
        'lugares' => 2873,
        'pj'      => 2872,
        'diario'  => 0,
        'default' => 0,
    );
}

// Devuelve el placeholder correcto en base a las categorías del post
function drak_pick_placeholder_for_post( $post_id ) {
    $map = drak_placeholder_map();
    $cats = wp_get_post_categories( $post_id, array('fields'=>'all') );
    foreach ( $cats as $c ) {
        $slug = get_category( $c )->slug;
        if ( isset($map[$slug]) && intval($map[$slug]) > 0 ) {
            return intval($map[$slug]);
        }
    }
    return isset($map['default']) ? intval($map['default']) : 0;
}

// 1) Al guardar post: si no hay miniatura, pone la de placeholder
add_action('save_post', function($post_id, $post, $update){
    if ( wp_is_post_revision($post_id) ) return;
    if ( $post->post_type !== 'post' ) return;
    if ( has_post_thumbnail($post_id) ) return;

    $ph = drak_pick_placeholder_for_post($post_id);
    if ( $ph ) {
        set_post_thumbnail( $post_id, $ph );
    }
}, 10, 3);

// 2) Endpoint manual: https://tusitio.com/?set_placeholders=1
add_action('template_redirect', function(){
    if ( ! isset($_GET['set_placeholders']) ) return;

    $count = 0;
    $map = drak_placeholder_map();

    $q = new WP_Query(array(
        'post_type'      => 'post',
        'post_status'    => array('publish','draft','pending','future','private'),
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_thumbnail_id', 'value' => '0', 'compare' => '=' ),
            array( 'key' => '_thumbnail_id', 'value' => '',  'compare' => '=' ),
        ),
        'fields'         => 'ids',
    ));

    foreach ( $q->posts as $pid ) {
        $ph = drak_pick_placeholder_for_post($pid);
        if ( $ph ) { set_post_thumbnail($pid, $ph); $count++; }
    }

    wp_die( sprintf('Placeholders aplicados a %d entradas.', $count) );
});

/**
 * Forzar que la miga "Wiki" enlace a /wiki/ (Astra breadcrumb nativo)
 */
add_filter( 'astra_the_breadcrumb', 'drak_fix_wiki_breadcrumb_to_page', 20 );
function drak_fix_wiki_breadcrumb_to_page( $html ) {

    // Localiza el enlace real de la categoría "wiki" (por si cambia el dominio o el slug)
    $term = get_term_by( 'slug', 'wiki', 'category' );
    if ( $term && ! is_wp_error( $term ) ) {
        $term_link = get_term_link( $term );               // p.ej. https://adayfs.com/category/wiki/
        $target    = home_url( '/wiki/' );                 // p.ej. https://adayfs.com/wiki/

        // Reemplaza el enlace en el HTML final del breadcrumb
        $html = str_replace( esc_url( $term_link ), esc_url( $target ), $html );

        // Por si hay variaciones con/ sin barra final (algunos sitios la quitan)
        $html = str_replace( untrailingslashit( esc_url( $term_link ) ), esc_url( $target ), $html );
    }

    return $html;
}

/**
 * 1) Cambiar el enlace del item "Wiki" en las migas de Astra.
 *    (Astra usa astra_breadcrumb_trail_items para construir el trail)
 */
add_filter( 'astra_breadcrumb_trail_items', 'drak_fix_wiki_breadcrumb_items', 20, 2 );
function drak_fix_wiki_breadcrumb_items( $items, $args ) {
	$target    = esc_url( home_url( '/wiki/' ) );

	// Si existe el término "wiki", cogemos su URL real por si cambia el dominio/estructura.
	$src_term  = get_term_by( 'slug', 'wiki', 'category' );
	$src_url   = $src_term && ! is_wp_error( $src_term ) ? esc_url( get_term_link( $src_term ) ) : '';

	foreach ( $items as &$it ) {
		// Caso ideal: encontramos exactamente la URL del término.
		if ( $src_url && strpos( $it, $src_url ) !== false ) {
			$it = str_replace( $src_url, $target, $it );
			continue;
		}
		// Fallback robusto: cualquier /category/wiki/ (con o sin barra final).
		if ( preg_match( '~href=["\']([^"\']+/category/wiki/?)["\']~i', $it ) ) {
			$it = preg_replace( '~href=["\']([^"\']+/category/wiki/?)["\']~i', 'href="'.$target.'"', $it );
		}
	}
	return $items;
}

/**
 * 2) Redirección SEO: si alguien entra a /category/wiki/, lo enviamos a /wiki/
 */
add_action( 'template_redirect', function () {
	if ( is_category( 'wiki' ) ) {
		wp_redirect( home_url( '/wiki/' ), 301 );
		exit;
	}
} );


// Mostrar metadatos en entradas de categoría 'quests'
add_filter('the_content', 'mostrar_campos_quest_en_contenido');
function mostrar_campos_quest_en_contenido($content) {
    if (is_single() && has_category('quests')) {
        $estado     = get_post_meta(get_the_ID(), 'estado_de_la_mision', true);
        $encargado  = get_post_meta(get_the_ID(), 'encargado', true);
        $ubicacion  = get_post_meta(get_the_ID(), 'ubicacion', true);

        $npc_url    = $encargado ? home_url('/wiki/npc/' . sanitize_title($encargado)) : '';
        $lugar_url  = $ubicacion ? home_url('/wiki/lugares/' . sanitize_title($ubicacion)) : '';

        ob_start();
        echo '<div class="quest-meta">';

        if ($estado) {
            $estado_clase = strtolower(str_replace(' ', '-', $estado));
            echo '<p class="estado estado-' . esc_attr($estado_clase) . '">Estado: ' . esc_html($estado) . '</p>';
        }

        if ($encargado) {
            echo '<p class="encargado">Encargado: <a href="' . esc_url($npc_url) . '">' . esc_html($encargado) . '</a></p>';
        }

        if ($ubicacion) {
            echo '<p class="ubicacion">Ubicación: <a href="' . esc_url($lugar_url) . '">' . esc_html($ubicacion) . '</a></p>';
        }

        echo '<hr></div>';
        return ob_get_clean() . $content;
    }

    return $content;
}

add_action('wp_head', function () {
    ?>
    <style>
    .estado-activa {
      color: #39ff14 !important;
    }
    .estado-completada {
      color: #66ccff !important;
    }
    .estado-fallida {
      color: #ff4c4c !important;
    }
    </style>
    <?php
});

add_action( 'init', function () {
    if ( ! isset( $_GET['spellcasting-log'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para ver este log.' );
    }

    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'spellcasting-debug.log';
    if ( ! file_exists( $log_file ) ) {
        wp_die( 'El log todavía no existe.' );
    }

    header( 'Content-Type: text/plain' );
    header( 'Content-Disposition: attachment; filename="spellcasting-debug.log"' );
    readfile( $log_file );
    exit;
} );

/**
 * Sanitiza valores provenientes de $_POST (strings o arrays) con soporte para defaults.
 */
function drak_get_post_value( $key, $default = '' ) {
    if ( ! isset( $_POST[ $key ] ) ) {
        return $default;
    }

    $value = wp_unslash( $_POST[ $key ] );

    if ( is_array( $value ) ) {
        return array_map( 'sanitize_text_field', $value );
    }

    return sanitize_text_field( $value );
}

// RENDERIZAR INVENTARIO DE PERSONAJE____________________________________________________________________

function renderizar_inventario_personaje($post_id) {
    ob_start();

    if (!$post_id) return '';

    // Guardado del formulario
    if (isset($_POST['mainpack_guardar']) && isset($_POST['post_id']) && intval($_POST['post_id']) === $post_id) {
        if (isset($_POST['golden_coins'])) {
            $oro = max(0, intval(drak_get_post_value('golden_coins', 0)));
            update_field('golden_coins', $oro, $post_id);
        }

        if (isset($_POST['arma_principal']) && is_array($_POST['arma_principal'])) {
            $arma_data = drak_get_post_value('arma_principal', []);
            update_field('arma_principal', $arma_data, $post_id);
        }

        for ($i = 1; $i <= 8; $i++) {
            $campo = 'mainpack_slot_' . $i;
            $valor = drak_get_post_value($campo, '');
            update_field($campo, $valor, $post_id);
        }
        echo '<div class="mensaje-confirmacion">✅ Inventario actualizado correctamente.</div>';
    }

    echo '<div class="mainpack-container">';
    echo '<form method="post" id="mainpack-formulario" class="formulario-inventario">';
	
	// Slot especial: Oro
$gold = get_field('golden_coins', $post_id);
echo '<div class="inventory-slot" data-slot="oro">';
echo '  <span class="slot-label">Oro:</span>';
echo '  <div class="slot-content" id="oro-display">';
echo      '<p class="slot-item" id="oro-valor">' . intval($gold) . ' monedas</p>';
echo '  </div>';
echo '  <input type="hidden" name="golden_coins" id="input_oro" value="' . intval($gold) . '">';
echo '  <button type="button" class="add-gold">＋</button>';
echo '  <button type="button" class="remove-gold">−</button>';
echo '</div>';

    for ($i = 1; $i <= 8; $i++) {
        $campo = 'mainpack_slot_' . $i;
        $valor = get_field($campo, $post_id);
        echo '<div class="inventory-slot" data-slot="' . $i . '">';
        echo '<span class="slot-label">Slot ' . $i . ':</span>';
		// Contenedor del contenido del slot (JS se encarga de pintar los <p>)
		echo '<div class="slot-content ' . (empty($valor) ? 'empty' : '') . '" id="texto_' . $i . '"></div>';
        echo '<input type="hidden" name="' . esc_attr($campo) . '" id="input_slot_' . $i . '" value="' . esc_attr($valor) . '">';
        echo '<button type="button" class="add-item" data-slot="' . $i . '" data-personaje="' . esc_attr($post_id) . '">＋</button>';
        echo '<button type="button" class="remove-item">−</button>';
        echo '</div>';
    }
// Verificar si INT o SAB >= 16 para mostrar los slots 9 y 10 extra
$inteligencia = (int) get_field('cs_inteligencia', $post_id);
$sabiduria = (int) get_field('cs_sabiduria', $post_id);

if ($inteligencia >= 16 || $sabiduria >= 16) {
$slot_boost_reason = '';
if ($inteligencia >= 16) {
    $slot_boost_reason = "Inteligencia (" . esc_html($inteligencia) . ")";
} elseif ($sabiduria >= 16) {
    $slot_boost_reason = "Sabiduría (" . esc_html($sabiduria) . ")";
}

if (!empty($slot_boost_reason)) {
    echo '<h3 class="inventory-extra-header">Slots extra por ' . $slot_boost_reason . '</h2>';
}	
    for ($i = 9; $i <= 10; $i++) {
        $slot = get_field("mainpack_slot_$i", $post_id);
        $slot_text = !empty($slot) ? esc_html($slot) : '(vacío)';
        $empty_class = empty($slot) ? ' empty' : '';
        ?>
        <div class="inventory-slot" data-slot="<?php echo $i; ?>">
            <span class="slot-label">Slot <?php echo $i; ?>:</span>
            <div class="slot-content<?php echo $empty_class; ?>" id="texto_<?php echo $i; ?>">
                <p class="slot-empty"><?php echo $slot_text; ?></p>
            </div>
            <input type="hidden" name="mainpack_slot_<?php echo $i; ?>" id="input_slot_<?php echo $i; ?>" value="<?php echo esc_attr($slot); ?>">
            <button type="button" class="add-item" data-slot="<?php echo $i; ?>" data-personaje="<?php echo esc_attr($post_id); ?>">+</button>
            <button type="button" class="remove-item" data-slot="<?php echo $i; ?>">−</button>
        </div>

        <?php
    }
	echo '<hr>';
}




	echo '<div class="inventory-slot arma-principal-slot">';
	echo '  <span class="slot-label">Arma Principal:</span>';
	echo '  <div id="arma-principal-display" class="slot-content">';
$arma = get_field('arma_principal', $post_id);
if (!empty($arma) && !empty($arma['name'])) {
    echo '<p><strong>' . esc_html($arma['name']) . '</strong> (' . esc_html($arma['damage_dice']) . ' ' . esc_html($arma['damage_type']) . ')</p>';
} else {
    echo '<p>No hay arma asignada</p>';
}

	echo '  </div>';
	echo ' <button type="button" id="arma-principal-add" class="arma-btn-add">+</button>';
	echo ' <button type="button"  id="arma-principal-remove" class="arma-principal-remove">−</button>';
	echo '</div>';
	echo '<input type="hidden" name="arma_principal[name]" id="arma_name">';
echo '<input type="hidden" name="arma_principal[slug]" id="arma_slug">';
echo '<input type="hidden" name="arma_principal[category]" id="arma_category">';
echo '<input type="hidden" name="arma_principal[damage_dice]" id="arma_damage_dice">';
echo '<input type="hidden" name="arma_principal[damage_type]" id="arma_damage_type">';
echo '<input type="hidden" name="arma_principal[weight]" id="arma_weight">';
echo '<input type="hidden" name="arma_principal[properties]" id="arma_properties">';
echo '<input type="hidden" name="arma_principal[es_magica]" id="arma_es_magica">';
echo '<input type="hidden" name="arma_principal[requiere_attunement]" id="arma_requiere_attunement">';
echo '<input type="hidden" name="arma_principal[descripcion]" id="arma_descripcion">';

    echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
    echo '<div class="boton-centrao"><button type="submit" name="mainpack_guardar">Guardar Inventario</button></div>';
    echo '</form>';
    echo '</div>';

// MODAL DE ARMA PRINCIPAL
echo '<div id="armaModal" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-arma-popup">&times;</span>';
echo '    <h3>Seleccionar arma principal</h3>';

echo '    <select id="arma-selector">';
echo '      <option value=\"\">Cargando armas...</option>';
echo '    </select>';

echo '    <div id="arma-preview" style="display:none;">';
echo '      <h4>Resumen:</h4>';
echo '      <div class="fila-dano">';
echo '        <p><strong>Daño:</strong> <span id="arma-damage"></span></p>';
echo '        <p><strong>Tipo de daño:</strong> <span id="arma-damage-type"></span></p>';
echo '      </div>';
echo '      <p><strong>Peso:</strong> <span id="arma-weight"></span></p>';
echo '      <p><strong>Propiedades:</strong></p>';
echo '      <ul id="propiedades-arma-lista"></ul>';
echo '      <div class="checkbox-group">';
echo '      <label><input type="checkbox" id="arma-magica"> ¿Es mágica?</label><br>';
echo '      <label><input type="checkbox" id="arma-attune"> ¿Requiere attunement?</label>';
echo '    </div>';
echo '    </div>';

echo '    <button id="arma-aplicar">Usar esta arma</button>';

echo '  </div>';
echo '</div>';

	
echo '<div id="armaModalEliminar" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <p>¿Estás seguro de que deseas eliminar el arma principal?</p>';
echo '    <button id="confirmarEliminarArma" class="btn-danger">Eliminar</button>';
echo '    <button class="close-modal">Cancelar</button>';
echo '  </div>';
echo '</div>';

//MODAL: Añadir Oro
echo '<div id="modal-add-gold" class="modal-overlay" style="display:none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-popup">&times;</span>';
echo '    <h2>Añadir oro</h2>';
echo '    <form id="form-add-gold">';
echo '      <label for="gold-amount-add">Cantidad a añadir:</label>';
echo '      <input type="number" id="gold-amount-add" min="1" max="99999">';
echo '      <button type="submit">Añadir</button>';
echo '    </form>';
echo '  </div>';
echo '</div>';
//MODAL: Eliminar Oro
echo '<div id="modal-remove-gold" class="modal-overlay" style="display:none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-popup">&times;</span>';
echo '    <h2>Eliminar oro</h2>';
echo '    <form id="form-remove-gold">';
echo '      <label for="gold-amount-remove">Cantidad a eliminar:</label>';
echo '      <input type="number" id="gold-amount-remove" min="1" max="99999">';
echo '      <button type="submit">Eliminar</button>';
echo '    </form>';
echo '  </div>';
echo '</div>';



    return ob_get_clean();
}
// RENDERIZAR HOJA DE PERSONAJE____________________________________________________________________

function renderizar_hoja_personaje($post_id) {
    if (!$post_id) return '';

    // Guardado del formulario
    if (isset($_POST['hoja_guardar']) && intval($_POST['post_id']) === $post_id) {
        $campos = [
            // Características + mods
            'cs_fuerza', 'cs_fuerza_mod',
            'cs_destreza', 'cs_destreza_mod',
            'cs_constitucion', 'cs_constitucion_mod',
            'cs_inteligencia', 'cs_inteligencia_mod',
            'cs_sabiduria', 'cs_sabiduria_mod',
            'cs_carisma', 'cs_carisma_mod',
            'cs_proeficiencia',
			
			'nivel',
            'clase',
            'subclase',
			'raza',
			
			'cs_iniciativa',
        	'cs_ac',
        	'cs_velocidad',
        	'cs_hp',
			'cs_hp_temp',


            // Habilidades
            'cs_skill_acrobacias',
            'cs_skill_atletismo',
            'cs_skill_juego_manos',
            'cs_skill_sigilo',
            'cs_skill_arcanos',
            'cs_skill_historia',
            'cs_skill_investigacion',
            'cs_skill_naturaleza',
            'cs_skill_religion',
            'cs_skill_trato_animales',
            'cs_skill_perspicacia',
            'cs_skill_medicina',
            'cs_skill_percepcion',
            'cs_skill_supervivencia',
            'cs_skill_engano',
            'cs_skill_intimidacion',
            'cs_skill_interpretacion',
            'cs_skill_persuasion',

            // Competencias en habilidades
            'cs_prof_acrobacias',
            'cs_prof_atletismo',
            'cs_prof_juego_manos',
            'cs_prof_sigilo',
            'cs_prof_arcanos',
            'cs_prof_historia',
            'cs_prof_investigacion',
            'cs_prof_naturaleza',
            'cs_prof_religion',
            'cs_prof_trato_animales',
            'cs_prof_perspicacia',
            'cs_prof_medicina',
            'cs_prof_percepcion',
            'cs_prof_supervivencia',
            'cs_prof_engano',
            'cs_prof_intimidacion',
            'cs_prof_interpretacion',
            'cs_prof_persuasion',
			
			        // Tiradas de salvación
        'cs_save_fuerza',
        'cs_save_destreza',
        'cs_save_constitucion',
        'cs_save_inteligencia',
        'cs_save_sabiduria',
        'cs_save_carisma',

        'cs_prof_save_fuerza',
        'cs_prof_save_destreza',
        'cs_prof_save_constitucion',
        'cs_prof_save_inteligencia',
        'cs_prof_save_sabiduria',
        'cs_prof_save_carisma',
		'prof_weapons',
		'prof_armors',
		'prof_languages',
		'prof_tools',
        'background',
        'spellcasting_hability',
        'spell_save_dc',
        'spell_attack_bonus',
        ];

        foreach ($campos as $campo) {
            if (isset($_POST[$campo])) {
                update_field($campo, drak_get_post_value($campo, ''), $post_id);
            } else {
                // Para las competencias (checkbox tipo circulito) que no envían valor si se desmarcan
                if (strpos($campo, 'cs_prof_') === 0) {
                    update_field($campo, '0', $post_id);
                }
            }
        }

        if (isset($_POST['skills_expertise'])) {
            update_post_meta(
                $post_id,
                'skills_expertise',
                sanitize_text_field(wp_unslash($_POST['skills_expertise']))
            );
        }

        $class_from_post = drak_get_post_value( 'clase', '' );
        $level_from_post = intval( drak_get_post_value( 'nivel', 0 ) );
        $raw_theories    = drak_get_post_value( 'apothecary_theories', '' );
        $selected_theories = drak_sanitize_apothecary_theory_submission( $raw_theories, $class_from_post, $level_from_post );
        drak_save_apothecary_theory_selection( $post_id, $selected_theories );

        $hp_manual_flag = drak_get_post_value( 'cs_hp_manual_override', '' ) === '1' ? '1' : '';
        update_post_meta( $post_id, 'cs_hp_manual_override', $hp_manual_flag );

        drak_update_spellcasting_fields($post_id);

        echo '<div class="mensaje-confirmacion">✅ Hoja de personaje actualizada.</div>';
    }

    // Cargar valores actuales
    $keys = [
        'cs_fuerza',
        'cs_fuerza_mod',
        'cs_destreza',
        'cs_destreza_mod',
        'cs_constitucion',
        'cs_constitucion_mod',
        'cs_inteligencia',
        'cs_inteligencia_mod',
        'cs_sabiduria',
        'cs_sabiduria_mod',
        'cs_carisma',
        'cs_carisma_mod',
        'cs_proeficiencia',
		
			'nivel',
            'clase',
            'subclase',
			'raza',
            'background',
		
		'cs_iniciativa',
    	'cs_ac',
    	'cs_velocidad',
    	'cs_hp',
		'cs_hp_temp',

        'cs_skill_acrobacias',
        'cs_skill_atletismo',
        'cs_skill_juego_manos',
        'cs_skill_sigilo',
        'cs_skill_arcanos',
        'cs_skill_historia',
        'cs_skill_investigacion',
        'cs_skill_naturaleza',
        'cs_skill_religion',
        'cs_skill_trato_animales',
        'cs_skill_perspicacia',
        'cs_skill_medicina',
        'cs_skill_percepcion',
        'cs_skill_supervivencia',
        'cs_skill_engano',
        'cs_skill_intimidacion',
        'cs_skill_interpretacion',
        'cs_skill_persuasion',

        'cs_prof_acrobacias',
        'cs_prof_atletismo',
        'cs_prof_juego_manos',
        'cs_prof_sigilo',
        'cs_prof_arcanos',
        'cs_prof_historia',
        'cs_prof_investigacion',
        'cs_prof_naturaleza',
        'cs_prof_religion',
        'cs_prof_trato_animales',
        'cs_prof_perspicacia',
        'cs_prof_medicina',
        'cs_prof_percepcion',
        'cs_prof_supervivencia',
        'cs_prof_engano',
        'cs_prof_intimidacion',
        'cs_prof_interpretacion',
        'cs_prof_persuasion',
		
		'cs_save_fuerza',
        'cs_save_destreza',
        'cs_save_constitucion',
        'cs_save_inteligencia',
        'cs_save_sabiduria',
        'cs_save_carisma',

        'cs_prof_save_fuerza',
        'cs_prof_save_destreza',
        'cs_prof_save_constitucion',
        'cs_prof_save_inteligencia',
        'cs_prof_save_sabiduria',
        'cs_prof_save_carisma',
		
		      // NUEVO: competencias generales
        'prof_weapons',
        'prof_armors',
        'prof_tools',
        'prof_languages',
        'spellcasting_hability',
        'spell_save_dc',
        'spell_attack_bonus'

    ];

    $datos = [];
    foreach ($keys as $k) {
        $datos[$k] = get_field($k, $post_id);
    }
    $datos['skills_expertise'] = get_post_meta($post_id, 'skills_expertise', true);

    $apothecary_selection = drak_get_character_apothecary_theories( $post_id );
    $apothecary_selection_json = wp_json_encode( $apothecary_selection );
    if ( ! $apothecary_selection_json ) {
        $apothecary_selection_json = '[]';
    }
    $apothecary_selection_display = drak_format_apothecary_theories_display( $apothecary_selection );

    // Definimos grupos de habilidades por característica, ordenados por nº de habilidades (más a menos)
    $skill_groups = [
        'Sabiduría' => [
            ['label' => 'Trato con Animales', 'skill' => 'cs_skill_trato_animales', 'prof' => 'cs_prof_trato_animales'],
            ['label' => 'Perspicacia',        'skill' => 'cs_skill_perspicacia',    'prof' => 'cs_prof_perspicacia'],
            ['label' => 'Medicina',           'skill' => 'cs_skill_medicina',       'prof' => 'cs_prof_medicina'],
            ['label' => 'Percepción',         'skill' => 'cs_skill_percepcion',     'prof' => 'cs_prof_percepcion'],
            ['label' => 'Supervivencia',      'skill' => 'cs_skill_supervivencia',  'prof' => 'cs_prof_supervivencia'],
        ],
        'Inteligencia' => [
            ['label' => 'Arcanos',        'skill' => 'cs_skill_arcanos',       'prof' => 'cs_prof_arcanos'],
            ['label' => 'Historia',       'skill' => 'cs_skill_historia',      'prof' => 'cs_prof_historia'],
            ['label' => 'Investigación',  'skill' => 'cs_skill_investigacion', 'prof' => 'cs_prof_investigacion'],
            ['label' => 'Naturaleza',     'skill' => 'cs_skill_naturaleza',    'prof' => 'cs_prof_naturaleza'],
            ['label' => 'Religión',       'skill' => 'cs_skill_religion',      'prof' => 'cs_prof_religion'],
        ],
        'Carisma' => [
            ['label' => 'Engaño',         'skill' => 'cs_skill_engano',         'prof' => 'cs_prof_engano'],
            ['label' => 'Intimidación',   'skill' => 'cs_skill_intimidacion',   'prof' => 'cs_prof_intimidacion'],
            ['label' => 'Interpretación', 'skill' => 'cs_skill_interpretacion', 'prof' => 'cs_prof_interpretacion'],
            ['label' => 'Persuasión',     'skill' => 'cs_skill_persuasion',     'prof' => 'cs_prof_persuasion'],
        ],
        'Destreza' => [
            ['label' => 'Acrobacias',     'skill' => 'cs_skill_acrobacias',    'prof' => 'cs_prof_acrobacias'],
            ['label' => 'Juego de Manos', 'skill' => 'cs_skill_juego_manos',   'prof' => 'cs_prof_juego_manos'],
            ['label' => 'Sigilo',         'skill' => 'cs_skill_sigilo',        'prof' => 'cs_prof_sigilo'],
        ],
        'Fuerza' => [
            ['label' => 'Atletismo',      'skill' => 'cs_skill_atletismo',     'prof' => 'cs_prof_atletismo'],
        ],
    ];

    ob_start();
    ?>
    <div class="hoja-personaje-container">
      <form method="post" class="formulario-hoja-personaje">
        <input type="hidden" id="hoja_guardar" name="hoja_guardar" value="">
        <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
        <input type="hidden" id="apothecary_theories" name="apothecary_theories" value="<?php echo esc_attr( $apothecary_selection_json ); ?>">
		  
		      <?php
	$nivel     = isset($datos['nivel']) ? intval($datos['nivel']) : '';
    $clase_val = isset($datos['clase']) ? $datos['clase'] : '';
    $sub_val   = isset($datos['subclase']) ? $datos['subclase'] : '';
	$raza_val  = isset($datos['raza'])  ? $datos['raza']  : '';
    $background_val = isset($datos['background']) ? $datos['background'] : '';

    // Valores actuales de los 4 básicos
    $init  = isset($datos['cs_iniciativa']) ? $datos['cs_iniciativa'] : '';
    $ac    = isset($datos['cs_ac']) ? $datos['cs_ac'] : '';
    $speed = isset($datos['cs_velocidad']) ? $datos['cs_velocidad'] : '';
	$hp = isset($datos['cs_hp']) ? $datos['cs_hp'] : '';
	$hp_temp = isset($datos['cs_hp_temp']) ? $datos['cs_hp_temp'] : '';
    $hp_manual_override = get_post_meta( $post_id, 'cs_hp_manual_override', true ) ? '1' : '';
	
	$armas_val        = isset($datos['prof_weapons'])        ? $datos['prof_weapons']        : '';
	$armaduras_val    = isset($datos['prof_armors'])    ? $datos['prof_armors']    : '';
	$herramientas_val = isset($datos['prof_tools']) ? $datos['prof_tools'] : '';
	$idiomas_val      = isset($datos['prof_languages'])      ? $datos['prof_languages']      : '';


    ?>
    <!-- BLOQUE PROGRESION: NIVEL / CLASE / SUBCLASE -->
    <div class="basicos-extra">
      <!-- Nivel, en su propia línea -->
      <div class="basic-item basic-item-level">
        <span class="basic-label">Nivel</span>
        <p class="basic-circle basic-circle-small" id="display_nivel">
          <?php echo $nivel !== '' ? esc_html($nivel) : ''; ?>
        </p>
      </div>

      <!-- Fila con Clase / Subclase / Raza en la misma línea -->
  <div class="basicos-secundarios">
    <div class="basic-item basic-item-wide">
      <span class="basic-label">Clase</span>
      <p class="basic-text" id="display_clase"></p>
    </div>

    <div class="basic-item basic-item-wide">
      <span class="basic-label">Subclase</span>
      <p class="basic-text" id="display_subclase"></p>
    </div>

    <div class="basic-item basic-item-wide">
      <span class="basic-label">Raza</span>
      <p class="basic-text" id="display_raza"></p>
    </div>
    <div class="basic-item basic-item-wide">
      <span class="basic-label">Trasfondo</span>
      <p class="basic-text" id="display_background"></p>
    </div>
  </div>

    <?php $is_mutagenist = ( $sub_val === 'apothecary-mutagenist-scgtd-drakkenheim' ); ?>
  <div class="basic-item basic-item-wide basic-item-theories<?php echo $is_mutagenist ? '' : ' is-hidden'; ?>">
    <span class="basic-label">Teorías esotéricas</span>
    <p class="basic-text" id="display_apothecary_theories">
      <?php echo $apothecary_selection_display ? esc_html( $apothecary_selection_display ) : '—'; ?>
    </p>
  </div>
</div>

    <!-- BLOQUE: Iniciativa / CA / Vel / PV -->
    <div class="basicos-container">
      <div class="basicos-list">
        <div class="basic-item">
          <span class="basic-label">INI</span>
          <p class="basic-circle" id="display_cs_iniciativa"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">CA</span>
          <p class="basic-circle" id="display_cs_ac"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">VEL</span>
          <p class="basic-circle" id="display_cs_velocidad"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">PV</span>
          <p class="basic-circle" id="display_cs_hp"></p>
        </div>
      </div>
		      <!-- BLOQUE: Nivel / Clase / Subclase (solo lectura) -->



<hr class="temp-pv-separator">

<div class="temp-pv-block">
<div>
  <h3 class="subtitulo-hoja-personaje">PV TEMPORALES</h3>
</div>


  <p id="display_cs_temp_hp" class="basic-circle" contenteditable="true" spellcheck="false"><?php echo esc_html($hp_temp); ?></p>

  <div class="temp-pv-slider-wrapper">
    <input type="range" id="slider_temp_hp" min="0" max="<?php echo esc_attr($hp); ?>" value="<?php echo esc_attr($hp_temp); ?>">
  </div>
<button type="button" id="btn-reset-temp-pv" class="btn-reset-temp-pv">RESET TEMP.PV</button>

</div>

<hr class="temp-pv-separator">


    </div>

    <!-- Hidden que se guardan en ACF -->
    <input type="hidden" id="cs_iniciativa" name="cs_iniciativa" value="<?php echo esc_attr($init); ?>">
    <input type="hidden" id="cs_ac"         name="cs_ac"         value="<?php echo esc_attr($ac); ?>">
    <input type="hidden" id="cs_velocidad"  name="cs_velocidad"  value="<?php echo esc_attr($speed); ?>">
    <input type="hidden" id="cs_hp"         name="cs_hp"         value="<?php echo esc_attr($hp); ?>">
    <!-- Nivel / Clase / Subclase -->
    <input type="hidden" id="nivel"    name="nivel"    value="<?php echo esc_attr($nivel); ?>">
    <input type="hidden" id="clase"    name="clase"    value="<?php echo esc_attr($clase_val); ?>">
    <input type="hidden" id="subclase" name="subclase" value="<?php echo esc_attr($sub_val); ?>">
	<input type="hidden" id="raza"     name="raza"     value="<?php echo esc_attr($raza_val); ?>">
<input type="hidden" id="background" name="background" value="<?php echo esc_attr($background_val); ?>">
<input type="hidden" id="prof_weapons" name="prof_weapons" value="<?php echo esc_attr($armas_val); ?>">
<input type="hidden" id="prof_armors" name="prof_armors" value="<?php echo esc_attr($armaduras_val); ?>">
<input type="hidden" id="prof_tools" name="prof_tools" value="<?php echo esc_attr($herramientas_val); ?>">
<input type="hidden" id="prof_languages" name="prof_languages" value="<?php echo esc_attr($idiomas_val); ?>">
<input type="hidden" id="spellcasting_hability" name="spellcasting_hability" value="<?php echo esc_attr(isset($datos['spellcasting_hability']) ? $datos['spellcasting_hability'] : ''); ?>">
<input type="hidden" id="spell_save_dc" name="spell_save_dc" value="<?php echo esc_attr(isset($datos['spell_save_dc']) ? $datos['spell_save_dc'] : ''); ?>">
<input type="hidden" id="spell_attack_bonus" name="spell_attack_bonus" value="<?php echo esc_attr(isset($datos['spell_attack_bonus']) ? $datos['spell_attack_bonus'] : ''); ?>">



<input type="hidden" id="cs_hp_temp" name="cs_hp_temp" value="<?php echo esc_attr($hp_temp); ?>">
<input type="hidden" id="cs_hp_manual_override" name="cs_hp_manual_override" value="<?php echo esc_attr( $hp_manual_override ); ?>">

        <!-- CARACTERÍSTICAS -->
      <h3 class="subtitulo-hoja-personaje">Características</h3>

<?php
$filas = [
  'Fuerza'              => ['cs_fuerza', 'cs_fuerza_mod'],
  'Destreza'            => ['cs_destreza', 'cs_destreza_mod'],
  'Constitución'        => ['cs_constitucion', 'cs_constitucion_mod'],
  'Inteligencia'        => ['cs_inteligencia', 'cs_inteligencia_mod'],
  'Sabiduría'           => ['cs_sabiduria', 'cs_sabiduria_mod'],
  'Carisma'             => ['cs_carisma', 'cs_carisma_mod'],
  'Bonificador de competencia' => ['cs_proeficiencia', null],
];

foreach ($filas as $label => $keys_row) :
    $campo_valor = $keys_row[0];
    $campo_mod   = $keys_row[1];
    $valor       = isset($datos[$campo_valor]) ? $datos[$campo_valor] : '';
    $mod         = $campo_mod ? (isset($datos[$campo_mod]) ? $datos[$campo_mod] : '') : '';
?>
  <div class="fila-stat">
    <label class="stat-label">
      <?php echo esc_html($label); ?>
    </label>

    <p class="stat-main-display" id="display_<?php echo esc_attr($campo_valor); ?>"></p>

    <span class="stat-mod-text">
      <?php echo ($campo_valor === 'cs_proeficiencia') ? 'Bonus' : 'Mod'; ?>
    </span>

    <p
      class="stat-mod-display"
      id="display_<?php echo esc_attr($campo_mod ? $campo_mod : $campo_valor . '_mod'); ?>"
    ></p>

    <input
      type="hidden"
      id="<?php echo esc_attr($campo_valor); ?>"
      name="<?php echo esc_attr($campo_valor); ?>"
      value="<?php echo esc_attr($valor); ?>"
    >

    <?php if ($campo_mod): ?>
      <input
        type="hidden"
        id="<?php echo esc_attr($campo_mod); ?>"
        name="<?php echo esc_attr($campo_mod); ?>"
        value="<?php echo esc_attr($mod); ?>"
      >
    <?php endif; ?>
  </div>
<?php endforeach; ?>

        <!-- TIRADAS DE SALVACIÓN -->
        <h3 class="subtitulo-hoja-personaje">Tiradas de salvación</h3>

        <?php
        $saving_throws = [
          'Fuerza'       => ['save' => 'cs_save_fuerza',       'prof' => 'cs_prof_save_fuerza',       'mod' => 'cs_fuerza_mod'],
          'Destreza'     => ['save' => 'cs_save_destreza',     'prof' => 'cs_prof_save_destreza',     'mod' => 'cs_destreza_mod'],
          'Constitución' => ['save' => 'cs_save_constitucion', 'prof' => 'cs_prof_save_constitucion', 'mod' => 'cs_constitucion_mod'],
          'Inteligencia' => ['save' => 'cs_save_inteligencia', 'prof' => 'cs_prof_save_inteligencia', 'mod' => 'cs_inteligencia_mod'],
          'Sabiduría'    => ['save' => 'cs_save_sabiduria',    'prof' => 'cs_prof_save_sabiduria',    'mod' => 'cs_sabiduria_mod'],
          'Carisma'      => ['save' => 'cs_save_carisma',      'prof' => 'cs_prof_save_carisma',      'mod' => 'cs_carisma_mod'],
        ];
        ?>

        <div class="saves-list">
          <?php foreach ($saving_throws as $label => $cfg) :
              $save_field = $cfg['save'];
              $prof_field = $cfg['prof'];

              $save_val  = isset($datos[$save_field]) ? $datos[$save_field] : '';
              $raw_prof  = isset($datos[$prof_field]) ? $datos[$prof_field] : 0;
              $es_prof   = ($raw_prof == 1); // 1 o "1" => true
          ?>
            <div class="fila-save">
              <span class="save-label"><?php echo esc_html($label); ?></span>
 <!-- Valor de la tirada como <p> (no editable) -->
              <p class="save-display" id="display_<?php echo esc_attr($save_field); ?>"></p>

              <!-- Indicador visual de competencia -->
              <span class="skill-indicator" data-save-indicator="<?php echo esc_attr($save_field); ?>">
                <span class="skill-icon<?php echo $es_prof ? ' skill-icon--prof' : ''; ?>" data-save-icon="<?php echo esc_attr($save_field); ?>"></span>
              </span>


             
              <!-- Inputs ocultos -->
              <input
                type="hidden"
                id="<?php echo esc_attr($save_field); ?>"
                name="<?php echo esc_attr($save_field); ?>"
                value="<?php echo esc_attr($save_val); ?>"
              >
              <input
                type="hidden"
                id="prof_<?php echo esc_attr($save_field); ?>"
                name="<?php echo esc_attr($prof_field); ?>"
                value="<?php echo $es_prof ? '1' : '0'; ?>"
              >
            </div>
          <?php endforeach; ?>
        </div>

	<hr class="temp-pv-separator">	  
        <!-- HABILIDADES AGRUPADAS -->
        <h3 class="subtitulo-hoja-personaje">Habilidades</h3>

        <input type="hidden" id="skills_expertise" name="skills_expertise" value="<?php echo esc_attr(isset($datos['skills_expertise']) ? $datos['skills_expertise'] : ''); ?>">
        <div class="skills-list">
          <?php foreach ($skill_groups as $stat_label => $skills) : ?>
            <div class="skills-group">
              <h4 class="skills-group-title"><?php echo esc_html($stat_label); ?></h4>
              <hr class="skills-group-separator">

<?php foreach ($skills as $skill) :
    $s_field = $skill['skill'];
    $p_field = $skill['prof'];

    $s_val    = isset($datos[$s_field]) ? $datos[$s_field] : '';
    $raw_prof = isset($datos[$p_field]) ? $datos[$p_field] : 0;

    // ACF puede devolver 1, "1", true... lo normalizamos a booleano
    $es_prof = ($raw_prof == 1); // OJO: comparación no estricta a propósito
?>

  <div class="fila-skill">
    <div class="skill-toggles">
      <span class="skill-indicator" data-skill-indicator="<?php echo esc_attr($s_field); ?>">
        <span class="skill-icon<?php echo $es_prof ? ' skill-icon--prof' : ''; ?>" data-skill-icon="<?php echo esc_attr($s_field); ?>"></span>
        <small class="skill-source-label" data-skill-label="<?php echo esc_attr($s_field); ?>"></small>
      </span>
    </div>

    <!-- Nombre de la habilidad -->
    <span class="skill-label"><?php echo esc_html($skill['label']); ?></span>

    <!-- Modificador (columna roja, alineado a la derecha) -->
    <p class="skill-display" id="display_<?php echo esc_attr($s_field); ?>"></p>

    <input
      type="hidden"
      id="<?php echo esc_attr($s_field); ?>"
      name="<?php echo esc_attr($s_field); ?>"
      value="<?php echo esc_attr($s_val); ?>"
    >
    <input
      type="hidden"
      id="prof_<?php echo esc_attr($s_field); ?>"
      name="<?php echo esc_attr($p_field); ?>"
      value="<?php echo $es_prof ? '1' : '0'; ?>"
    >
	  
	  
	  
	  


	  
	  
	  
	  
	  
	  
	  
  </div>
				
				
<?php endforeach; ?>

            </div>
          <?php endforeach; ?>
        </div>
		  <hr class="temp-pv-separator">

		  	  <!-- BLOQUE: Competencias (armas, armaduras, herramientas, idiomas) -->
<div class="profs-container">
  <div class="profs-header">
    <h3 class="profs-title">Competencias</h3>
  </div>

  <div class="profs-list">
    <div class="prof-item">
      <span class="basic-label">Armas</span>
      <p class="basic-text" id="display_cs_armas"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Armaduras</span>
      <p class="basic-text" id="display_cs_armaduras"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Herramientas</span>
      <p class="basic-text" id="display_cs_herramientas"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Idiomas</span>
      <p class="basic-text" id="display_cs_idiomas"></p>
    </div>

  </div>
</div>

<div id="character-extended-module" class="character-extended">
  <div class="character-extended__tabs">
    <button type="button" class="character-extended__tab is-active" data-ext-tab="features">
      Feats &amp; Traits
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="spells">
      Conjuros
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="actions">
      Acciones
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="background">
      Trasfondo
    </button>
  </div>
  <div id="character-extended-panel" class="character-extended__panel">
    <p class="character-extended__loading">Cargando datos...</p>
  </div>
</div>

        <!-- Modal para editar INI / CA / VEL / PV -->
        <div id="sheet-overlay" class="modal-overlay" style="display:none;">
  <div class="modal-contenido modal-contenido--sheet">
    <span class="close-sheet-popup">&times;</span>
    <div class="sheet-modal-scroll">
      <section class="sheet-section sheet-section--stats">
        <h3>Modificar características</h3>
        <div id="stats-fields">
<?php
  $stats_modal = [
    'Fuerza'       => 'cs_fuerza',
    'Destreza'     => 'cs_destreza',
    'Constitución' => 'cs_constitucion',
    'Inteligencia' => 'cs_inteligencia',
    'Sabiduría'    => 'cs_sabiduria',
    'Carisma'      => 'cs_carisma',
  ];
  foreach ($stats_modal as $label => $field) :
?>
          <div class="stats-modal-row">
            <label><?php echo esc_html($label); ?></label>
            <input
              type="number"
              class="stats-modal-input"
              data-stat="<?php echo esc_attr($field); ?>"
              min="0"
              max="99"
            >
          </div>
<?php endforeach; ?>
        </div>
        <div class="stats-expertise-manager">
          <label for="expertise-select">Añadir pericia</label>
          <div class="expertise-controls">
            <select id="expertise-select" class="basics-modal-input"></select>
            <button type="button" id="expertise-add" class="btn-basicos-mod">Añadir</button>
          </div>
          <ul id="expertise-list" class="expertise-list"></ul>
        </div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--basics">
        <h3>Datos básicos</h3>
        <div class="basics-modal-row">
          <label>Nivel</label>
          <input type="number" min="1" max="20" class="basics-modal-input" data-basic="nivel">
        </div>
        <div class="basics-modal-row">
          <label>Clase</label>
          <select id="modal-clase" class="basics-modal-input">
            <option value="">Cargando clases…</option>
          </select>
        </div>
        <div class="basics-modal-row">
          <label>Subclase</label>
          <select id="modal-subclase" class="basics-modal-input" disabled>
            <option value="">Selecciona una clase primero…</option>
          </select>
        </div>
        <div class="basics-modal-row">
          <label>Raza</label>
          <select id="modal-raza" class="basics-modal-input">
            <option value="">Cargando razas…</option>
          </select>
        </div>
        <div class="basics-modal-row basics-modal-row--background">
          <label>Trasfondo</label>
          <select id="modal-background" class="basics-modal-input">
            <option value="">Cargando trasfondos…</option>
          </select>
        </div>
        <div id="basics-fields">
          <div class="basics-modal-row">
            <label>Iniciativa</label>
            <input type="number" class="basics-modal-input" data-basic="cs_iniciativa">
          </div>
          <div class="basics-modal-row">
            <label>Clase de armadura (CA)</label>
            <input type="number" class="basics-modal-input" data-basic="cs_ac">
          </div>
          <div class="basics-modal-row">
            <label>Velocidad</label>
            <input type="number" class="basics-modal-input" data-basic="cs_velocidad">
          </div>
          <div class="basics-modal-row">
            <label>Puntos de vida (PV)</label>
            <input type="number" class="basics-modal-input" data-basic="cs_hp">
          </div>
        </div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--esoterics<?php echo $is_mutagenist ? '' : ' is-hidden'; ?>" data-theory-section>
        <h3>Teorías esotéricas</h3>
        <p class="sheet-section__hint">Gestiona aquí las Esoteric Theories disponibles para la clase Apothecary.</p>
        <div id="esoteric-theories-container" class="theory-picker"></div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--profs">
        <h3>Competencias</h3>
        <div class="profs-modal-section">
          <h4>Armas</h4>
          <div class="profs-modal-row">
            <select id="profs-weapons-select" class="basics-modal-input">
              <option value="">Cargando armas…</option>
            </select>
            <button type="button" id="profs-weapons-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-weapons-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Armaduras</h4>
          <div class="profs-modal-row">
            <select id="profs-armors-select" class="basics-modal-input">
              <option value="">Cargando armaduras…</option>
            </select>
            <button type="button" id="profs-armors-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-armors-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Herramientas</h4>
          <div class="profs-modal-row">
            <select id="profs-tools-select" class="basics-modal-input">
              <option value="">Cargando herramientas…</option>
            </select>
            <button type="button" id="profs-tools-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-tools-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Idiomas</h4>
          <div class="profs-modal-row">
            <select id="profs-languages-select" class="basics-modal-input">
              <option value="">Cargando idiomas…</option>
            </select>
            <button type="button" id="profs-languages-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-languages-list" class="profs-modal-list"></ul>
        </div>
      </section>
    </div>
    <div class="sheet-modal-actions">
      <button type="button" id="sheet-apply" class="btn-primary">Aplicar cambios</button>
    </div>
  </div>
</div>


    <?php
    return ob_get_clean();
}




//FIN RENDERIZAR HOJA DE PERSONAJE____________________________________________________________________





add_action('wp_footer', function () {
    if (!is_page()) return;
    ?>
<script>
	
	
document.addEventListener('DOMContentLoaded', function () {
  const overlay       = document.getElementById('item-form-overlay');
  const form          = document.getElementById('item-form');
  if (!overlay || !form) return; // No estamos en la página de inventario

  const nameInput     = document.getElementById('item-name');
  const nameSelect    = document.getElementById('item-name-select');
  const sizeSelect    = document.getElementById('item-size');
  const qtySelect     = document.getElementById('item-qty');
  const currentSlot   = document.getElementById('current-slot');
  const slotNumero    = document.getElementById('slot-numero');

  const deleteOverlay = document.getElementById('delete-form-overlay');
  const deleteForm    = document.getElementById('delete-form');
  const deleteContent = document.getElementById('delete-form-content');
	
	  // Convierte el string guardado ("Antorcha x 2 - Cuerda")
  // en una lista de <p> dentro del slot
  function buildSlotHTMLFromValue(value) {
    const trimmed = (value || "").trim();
    if (!trimmed) {
      return '<p class="slot-empty">(vacío)</p>';
    }

    const parts = trimmed.split(" - "); // cada objeto del slot
    const htmlParts = parts.map(part => {
      const texto = part.trim();
      if (!texto) return "";
      return `<p class="slot-item">${texto}</p>`;
    }).filter(Boolean);

    return htmlParts.join("");
  }

  function updateSlotView(slot) {
    const input     = document.getElementById("input_slot_" + slot);
    const container = document.getElementById("texto_" + slot);
    if (!input || !container) return;

    const val = (input.value || "").trim();
    container.innerHTML = buildSlotHTMLFromValue(val);

    if (!val) {
      container.classList.add("empty");
    } else {
      container.classList.remove("empty");
    }
  }

  // Inicializar la vista de los 8 slots al cargar la página
  for (let i = 1; i <= 8; i++) {
    updateSlotView(i);
  }


  // Rellenar selector de cantidad 1..10 (si está vacío)
  if (qtySelect && !qtySelect.options.length) {
    for (let i = 1; i <= 10; i++) {
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = i;
      qtySelect.appendChild(opt);
    }
  }

  function toggleQtyField() {
    const isSmall = sizeSelect.value === 'pequeño';
    qtySelect.disabled = !isSmall;
  }

  sizeSelect.addEventListener('change', toggleQtyField);
  toggleQtyField();

  // Abrir modal de añadir ("+")
document.querySelectorAll('.inventory-slot[data-slot] .add-item').forEach(btn => {
    btn.addEventListener('click', function () {
      const slot = this.closest('.inventory-slot').dataset.slot;
      currentSlot.value = slot;
      if (slotNumero) slotNumero.textContent = slot;

      nameInput.value = '';
      if (nameSelect) nameSelect.value = '';
      sizeSelect.value = 'normal';
      qtySelect.value = '1';
      toggleQtyField();

      overlay.style.display = 'flex';
    });
  });

  // Cerrar modal de añadir
  const closeAdd = document.querySelector('.close-popup');
  if (closeAdd) {
    closeAdd.addEventListener('click', function () {
      overlay.style.display = 'none';
    });
  }

  // Si elige de la lista, rellenamos el nombre
  if (nameSelect) {
    nameSelect.addEventListener('change', function () {
      if (this.value) {
        nameInput.value = this.value;
      }
    });
  }

  // Enviar formulario de añadir
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const name = nameInput.value.trim();
    const size = sizeSelect.value;
    const qty  = parseInt(qtySelect.value, 10) || 0;
    const slot = currentSlot.value;

    if (!name || !size || (size === 'pequeño' && !qty)) {
      alert('Todos los campos son obligatorios.');
      return;
    }

    const input = document.getElementById('input_slot_' + slot);
    const p     = document.getElementById('texto_' + slot);
    const currentVal = (input.value || '').trim();
    const items = currentVal ? currentVal.split(' - ') : [];

    const hayObjetoNormal = items.length === 1 && !items[0].includes('x');

   if (size === 'normal') {
      // Solo puede haber un objeto normal y nada más
      if (items.length > 0) {
        alert('Este slot ya tiene objetos. Elimina antes de añadir uno normal.');
        return;
      }
      input.value = name;
    } else {
      // Tamaño pequeño
      if (hayObjetoNormal) {
        alert('No puedes añadir objetos pequeños si ya hay uno normal.');
        return;
      }

      // Parsear objetos pequeños existentes
      const parsed = items
        .map(function (item) {
          const m = item.match(/^(.+?) x (\d+)$/);
          if (m) {
            return { nombre: m[1].trim(), cantidad: parseInt(m[2], 10) };
          }
          return null;
        })
        .filter(Boolean);

      const totalExistente = parsed.reduce(function (sum, it) {
        return sum + it.cantidad;
      }, 0);

      let existente = parsed.find(function (it) {
        return it.nombre === name;
      });

      let nuevoTotal = totalExistente + qty;
      if (nuevoTotal > 10) {
        alert('Máximo 10 objetos pequeños por slot.');
        return;
      }

      if (existente) {
        existente.cantidad += qty;
      } else {
        parsed.push({ nombre: name, cantidad: qty });
      }

      const nuevosItems = parsed.map(function (it) {
        return it.nombre + ' x ' + it.cantidad;
      });

      input.value = nuevosItems.join(' - ');
    }

    updateSlotView(slot);
    overlay.style.display = 'none';
  });

  // ----------------- ELIMINAR OBJETOS ("-") -----------------

  document.querySelectorAll('.remove-item').forEach(btn => {
    btn.addEventListener('click', function () {
      const slot = this.closest('.inventory-slot').dataset.slot;
      const input = document.getElementById('input_slot_' + slot);
      const currentVal = (input.value || '').trim();
      const items = currentVal ? currentVal.split(' - ') : [];

      deleteContent.innerHTML = '';
      deleteContent.dataset.slot = slot;

      if (!items.length) {
        deleteContent.innerHTML = '<p>No hay objetos en este slot.</p>';
        deleteOverlay.style.display = 'flex';
        return;
      }

      items.forEach(function (item) {
        const match = item.match(/^(.+?)\s*x\s*(\d+)$/);
        const wrapper = document.createElement('div');

        if (match) {
          const name = match[1].trim();
          const qty  = parseInt(match[2], 10);

          wrapper.innerHTML = `
            <label>${name}</label>
            <select data-name="${name}">
              ${Array.from({length: qty + 1}, (_, i) => `<option value="${i}">${i}</option>`).join('')}
            </select>
          `;
        } else {
          wrapper.innerHTML = `
            <label>${item}</label>
            <input type="checkbox" data-name="${item}"> Eliminar
          `;
        }

        deleteContent.appendChild(wrapper);
      });

      deleteOverlay.style.display = 'flex';
    });
  });

  const closeDelete = document.querySelector('.close-delete-popup');
  if (closeDelete) {
    closeDelete.addEventListener('click', function () {
      deleteOverlay.style.display = 'none';
    });
  }

  deleteForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const slot  = deleteContent.dataset.slot;
    const input = document.getElementById('input_slot_' + slot);
    const items = (input.value || '').split(' - ').filter(Boolean);

    const newItems = [];

    deleteContent.querySelectorAll('div').forEach(function (div) {
      const select   = div.querySelector('select');
      const checkbox = div.querySelector('input[type="checkbox"]');

      if (select) {
        const name      = select.dataset.name;
        const removeQty = parseInt(select.value, 10);
        const itemStr   = items.find(i => i.startsWith(name + ' x'));
        if (itemStr) {
          const currentQty = parseInt(itemStr.split('x')[1], 10);
          const remaining  = currentQty - removeQty;
          if (remaining > 0) {
            newItems.push(name + ' x ' + remaining);
          }
        }
      } else if (checkbox) {
        if (!checkbox.checked) {
          newItems.push(checkbox.dataset.name);
        }
      }
    });

       input.value = newItems.join(' - ');
    updateSlotView(slot);
    deleteOverlay.style.display = 'none';

  });

//MODAL ORO
const oroInput = document.getElementById('input_oro');
const oroValor = document.getElementById('oro-valor');
const btnAddGold = document.querySelector('.add-gold');
const btnRemoveGold = document.querySelector('.remove-gold');
const modalAdd = document.getElementById('modal-add-gold');
const modalRemove = document.getElementById('modal-remove-gold');
const formAdd = document.getElementById('form-add-gold');
const formRemove = document.getElementById('form-remove-gold');
const closeButtons = document.querySelectorAll('.modal-contenido .close-popup');

function updateGoldDisplay() {
  const value = parseInt(oroInput.value || '0');
  oroValor.textContent = value + ' monedas';
}

if (btnAddGold && modalAdd) {
  btnAddGold.addEventListener('click', () => {
    modalAdd.style.display = 'flex';
  });
}

if (btnRemoveGold && modalRemove) {
  btnRemoveGold.addEventListener('click', () => {
    modalRemove.style.display = 'flex';
  });
}

formAdd?.addEventListener('submit', e => {
  e.preventDefault();
  const cantidad = parseInt(document.getElementById('gold-amount-add').value || '0', 10);
  if (cantidad > 0) {
    oroInput.value = parseInt(oroInput.value) + cantidad;
    updateGoldDisplay();
  }
  modalAdd.style.display = 'none';
});

formRemove?.addEventListener('submit', e => {
  e.preventDefault();
  const cantidad = parseInt(document.getElementById('gold-amount-remove').value || '0', 10);
  if (cantidad > 0) {
    oroInput.value = Math.max(0, parseInt(oroInput.value) - cantidad);
    updateGoldDisplay();
  }
  modalRemove.style.display = 'none';
});

closeButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay').style.display = 'none';
  });
});

updateGoldDisplay();

});
</script>




<?php
});

function drak_register_personaje_cpt() {
    $labels = [
        'name'               => 'Personajes',
        'singular_name'      => 'Personaje',
        'add_new'            => 'Añadir nuevo',
        'add_new_item'       => 'Añadir nuevo personaje',
        'edit_item'          => 'Editar personaje',
        'new_item'           => 'Nuevo personaje',
        'view_item'          => 'Ver personaje',
        'search_items'       => 'Buscar personajes',
        'not_found'          => 'No se encontraron personajes',
        'not_found_in_trash' => 'No hay personajes en la papelera',
        'menu_name'          => 'Personajes',
    ];

    $args = [
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'personaje'],
        'show_in_rest'  => true,
        'supports'      => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
        'menu_icon'     => 'dashicons-groups',
        'menu_position' => 5,
        'capability_type' => 'post',
    ];

    register_post_type('personaje', $args);
}

function drak_get_full_caster_slots_table() {
    return [
        1 => [1 => 2],
        2 => [1 => 3],
        3 => [1 => 4, 2 => 2],
        4 => [1 => 4, 2 => 3],
        5 => [1 => 4, 2 => 3, 3 => 2],
        6 => [1 => 4, 2 => 3, 3 => 3],
        7 => [1 => 4, 2 => 3, 3 => 3, 4 => 1],
        8 => [1 => 4, 2 => 3, 3 => 3, 4 => 2],
        9 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 1],
        10 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
        11 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        12 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        13 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        14 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        15 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        16 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        17 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        18 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        19 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 1, 8 => 1, 9 => 1],
        20 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 2, 8 => 1, 9 => 1],
    ];
}

function drak_user_can_manage_personaje( $post_id ) {
    if ( current_user_can( 'edit_post', $post_id ) ) {
        return true;
    }

    $owner = get_field( 'jugador_asociado', $post_id );
    if ( $owner && intval( $owner ) === get_current_user_id() ) {
        return true;
    }

    return false;
}

function drak_grimorio_decode_meta_array( $value ) {
    if ( is_array( $value ) ) {
        return $value;
    }
    if ( is_string( $value ) && $value !== '' ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return $decoded;
        }
    }
    return [];
}

function drak_grimorio_normalize_slots_array( $data ) {
    if ( ! is_array( $data ) ) {
        return [];
    }
    $clean = [];
    foreach ( $data as $level => $value ) {
        $level = intval( $level );
        if ( $level < 0 || $level > 9 ) {
            continue;
        }
        $clean[ $level ] = max( 0, intval( $value ) );
    }
    ksort( $clean );
    return $clean;
}

function drak_grimorio_normalize_spells_array( $data ) {
    if ( ! is_array( $data ) ) {
        return [];
    }
    $clean = [];
    foreach ( $data as $level => $list ) {
        $level = intval( $level );
        if ( $level < 0 || $level > 9 ) {
            continue;
        }
        $list      = is_array( $list ) ? $list : [];
        $sanitized = [];
        foreach ( $list as $spell_name ) {
            $spell_name = sanitize_text_field( (string) $spell_name );
            if ( '' === $spell_name ) {
                continue;
            }
            if ( ! in_array( $spell_name, $sanitized, true ) ) {
                $sanitized[] = $spell_name;
            }
        }
        if ( ! empty( $sanitized ) ) {
            $clean[ $level ] = $sanitized;
        }
    }
    ksort( $clean );
    return $clean;
}

function drak_grimorio_save_slots( $post_id, $data ) {
    $clean   = drak_grimorio_normalize_slots_array( $data );
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_field( 'grimorio_slots_used', $payload, $post_id );
    return $clean;
}

function drak_grimorio_save_prepared( $post_id, $data ) {
    $clean   = drak_grimorio_normalize_spells_array( $data );
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_field( 'grimorio_spells', $payload, $post_id );
    return $clean;
}

function drak_grimorio_get_slots( $post_id ) {
    $raw   = get_field( 'grimorio_slots_used', $post_id );
    $array = drak_grimorio_decode_meta_array( $raw );
    $clean = drak_grimorio_normalize_slots_array( $array );
    if ( is_array( $raw ) ) {
        drak_grimorio_save_slots( $post_id, $clean );
    }
    return $clean;
}

function drak_grimorio_get_prepared( $post_id ) {
    $raw   = get_field( 'grimorio_spells', $post_id );
    $array = drak_grimorio_decode_meta_array( $raw );
    $clean = drak_grimorio_normalize_spells_array( $array );
    if ( is_array( $raw ) ) {
        drak_grimorio_save_prepared( $post_id, $clean );
    }
    return $clean;
}

function drak_grimorio_get_transformation_state( $post_id ) {
    $state = get_post_meta( $post_id, 'grimorio_transformation_state', true );
    if ( ! is_array( $state ) ) {
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    $state['active']     = ! empty( $state['active'] );
    $state['slot_level'] = isset( $state['slot_level'] ) ? intval( $state['slot_level'] ) : null;
    $state['started_at'] = isset( $state['started_at'] ) ? intval( $state['started_at'] ) : null;

    if ( ! $state['active'] || ! $state['slot_level'] ) {
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    return $state;
}

function drak_grimorio_save_transformation_state( $post_id, $state ) {
    if ( empty( $state['active'] ) || empty( $state['slot_level'] ) ) {
        delete_post_meta( $post_id, 'grimorio_transformation_state' );
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    $payload = [
        'active'     => true,
        'slot_level' => intval( $state['slot_level'] ),
        'started_at' => isset( $state['started_at'] ) ? intval( $state['started_at'] ) : time(),
    ];

    update_post_meta( $post_id, 'grimorio_transformation_state', $payload );

    return $payload;
}

function drak_get_class_detail_entry( $class_id ) {
    static $class_details = null;

    if ( $class_details === null ) {
        $class_details = [];
        $path          = drak_locate_theme_data_file( 'dnd-class-details.json' );

        if ( $path ) {
            $json = file_get_contents( $path );
            if ( $json !== false ) {
                $data = json_decode( $json, true );
                if ( isset( $data['classes'] ) && is_array( $data['classes'] ) ) {
                    $class_details = $data['classes'];
                }
            }
        }
    }

    return $class_details[ $class_id ] ?? null;
}

function drak_get_spellcasting_ability_for_class( $class_id ) {
    if ( ! $class_id ) {
        return null;
    }

    $entry = drak_get_class_detail_entry( $class_id );
    if ( ! $entry ) {
        return null;
    }

    $ability = $entry['spellcastingAbility'] ?? null;
    if ( ! is_string( $ability ) || $ability === '' ) {
        return null;
    }

    return strtolower( $ability );
}

function drak_set_class_reference_error( $message ) {
    $GLOBALS['drak_class_reference_error'] = $message;
    error_log( '[ClassRef] ' . $message );
}

function drak_get_class_reference_error() {
    return $GLOBALS['drak_class_reference_error'] ?? '';
}

function drak_load_5etools_class_file( $class_name ) {
    static $cache = [];

    if ( ! $class_name ) {
        return null;
    }

    $slug = sanitize_title( $class_name );
    if ( isset( $cache[ $slug ] ) ) {
        return $cache[ $slug ];
    }

    $path = drak_locate_theme_data_file( 'class/class-' . $slug . '.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'class-' . $slug . '.json' );
    }
    if ( ! $path ) {
        $path = trailingslashit( get_stylesheet_directory() ) . '5etools-src-main/data/class/class-' . $slug . '.json';
        if ( ! file_exists( $path ) ) {
            drak_set_class_reference_error( sprintf( 'Archivo de clase no encontrado para "%s" (slug "%s").', $class_name, $slug ) );
            $cache[ $slug ] = null;
            return null;
        }
    }

    $json = file_get_contents( $path );
    if ( $json === false ) {
        drak_set_class_reference_error( sprintf( 'No se pudo leer el archivo %s', $path ) );
        $cache[ $slug ] = null;
        return null;
    }

    $decoded = json_decode( $json, true );
    if ( ! is_array( $decoded ) ) {
        drak_set_class_reference_error( sprintf( 'JSON inválido en %s', $path ) );
        $cache[ $slug ] = null;
        return null;
    }

    $cache[ $slug ] = $decoded;
    return $cache[ $slug ];
}

function drak_find_class_json_entry( $class_id ) {
    if ( ! $class_id ) {
        return null;
    }

    $meta = drak_get_class_detail_entry( $class_id );
    if ( ! $meta ) {
        return null;
    }

    $file = drak_load_5etools_class_file( $meta['name'] ?? '' );
    if ( ! $file || empty( $file['class'] ) || ! is_array( $file['class'] ) ) {
        return null;
    }

    foreach ( $file['class'] as $entry ) {
        $entry_name   = strtolower( $entry['name'] ?? '' );
        $target_name  = strtolower( $meta['name'] ?? '' );
        $entry_source = $entry['source'] ?? '';
        $entry_edition = $entry['edition'] ?? 'classic';

        if ( $entry_name === $target_name
            && $entry_source === ( $meta['source'] ?? '' )
            && $entry_edition === ( $meta['edition'] ?? 'classic' ) ) {
            return [
                'meta'  => $meta,
                'entry' => $entry,
                'file'  => $file,
            ];
        }
    }

    if ( ! empty( $file['class'] ) ) {
        foreach ( $file['class'] as $entry ) {
            if ( strtolower( $entry['name'] ?? '' ) === strtolower( $meta['name'] ?? '' ) ) {
                return [
                    'meta'  => $meta,
                    'entry' => $entry,
                    'file'  => $file,
                ];
            }
        }

        drak_set_class_reference_error( sprintf( 'No hubo coincidencia exacta para la clase "%s" (%s %s). Se usará la primera entrada del JSON.', $meta['name'] ?? $class_id, $meta['source'] ?? 'sin fuente', $meta['edition'] ?? 'sin edición' ) );
        return [
            'meta'  => $meta,
            'entry' => $file['class'][0],
            'file'  => $file,
        ];
    }

    return null;
}

function drak_find_subclass_json_entry( $subclass_id ) {
    if ( ! $subclass_id ) {
        return null;
    }

    $lookup = drak_get_local_dnd_class_lookup();
    $record = $lookup['subclasses'][ $subclass_id ] ?? null;
    if ( ! $record ) {
        return null;
    }

    $class_id  = $record['class_id'] ?? '';
    $class_ref = drak_find_class_json_entry( $class_id );
    if ( ! $class_ref ) {
        return null;
    }

    $file        = $class_ref['file'];
    $sub_meta    = $record['data'] ?? [];
    $target_name = strtolower( $sub_meta['name'] ?? '' );
    $target_source = $sub_meta['source'] ?? '';
    $target_short = strtolower( $sub_meta['shortName'] ?? '' );
    $target_edition = $sub_meta['edition'] ?? ( $class_ref['meta']['edition'] ?? 'classic' );
    $target_class_name = strtolower( $sub_meta['className'] ?? ( $class_ref['meta']['name'] ?? '' ) );
    $target_class_source = $sub_meta['classSource'] ?? ( $class_ref['meta']['source'] ?? '' );

    foreach ( $file['subclass'] ?? [] as $entry ) {
        $entry_name  = strtolower( $entry['name'] ?? '' );
        $entry_short = strtolower( $entry['shortName'] ?? '' );
        $entry_source = $entry['source'] ?? '';
        $entry_class_name = strtolower( $entry['className'] ?? '' );
        $entry_class_source = $entry['classSource'] ?? '';
        $entry_edition = $entry['edition'] ?? ( $class_ref['meta']['edition'] ?? 'classic' );

        if (
            ( $entry_name === $target_name || $entry_short === $target_short )
            && $entry_source === $target_source
            && $entry_class_name === $target_class_name
            && $entry_class_source === $target_class_source
            && $entry_edition === $target_edition
        ) {
            return [
                'entry' => $entry,
                'meta'  => $sub_meta,
                'class' => $class_ref,
            ];
        }
    }

    foreach ( $file['subclass'] ?? [] as $entry ) {
        if ( strtolower( $entry['name'] ?? '' ) === $target_name ) {
            return [
                'entry' => $entry,
                'meta'  => $sub_meta,
                'class' => $class_ref,
            ];
        }
    }

    if ( ! empty( $file['subclass'] ) ) {
        drak_set_class_reference_error( sprintf( 'No hubo coincidencia exacta para la subclase "%s". Se usará la primera entrada disponible.', $sub_meta['name'] ?? $subclass_id ) );
        return [
            'entry' => $file['subclass'][0],
            'meta'  => $sub_meta,
            'class' => $class_ref,
        ];
    }

    return null;
}

function drak_strip_5e_markup( $text ) {
    if ( ! is_string( $text ) ) {
        return '';
    }

    $clean = preg_replace( '/\{@[^|}]+\|([^}|]+)(?:\|[^}]*)?\}/', '$1', $text );
    $clean = preg_replace( '/{[^}]+}/', '', $clean );

    return trim( $clean );
}

function drak_parse_spell_slot_label( $label ) {
    $plain = drak_strip_5e_markup( $label );
    if ( $plain === '' ) {
        $plain = (string) $label;
    }

    if ( preg_match( '/level=(\d+)/i', $label, $matches ) ) {
        return intval( $matches[1] );
    }

    if ( preg_match( '/(\d+)(?:st|nd|rd|th)/i', $plain, $matches ) ) {
        return intval( $matches[1] );
    }

    if ( preg_match( '/(\d+)/', $plain, $matches ) ) {
        return intval( $matches[1] );
    }

    return null;
}

function drak_extract_prepared_progression( $class_entry ) {
    if ( empty( $class_entry ) || ! is_array( $class_entry ) ) {
        return [];
    }

    if ( ! empty( $class_entry['preparedSpellsProgression'] ) && is_array( $class_entry['preparedSpellsProgression'] ) ) {
        $progression = [];
        foreach ( $class_entry['preparedSpellsProgression'] as $index => $value ) {
            $progression[ $index + 1 ] = intval( $value );
        }
        return $progression;
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = $group['colLabels'] ?? [];
        $rows   = $group['rows'] ?? [];

        if ( empty( $labels ) || empty( $rows ) ) {
            continue;
        }

        foreach ( $labels as $idx => $label ) {
            $plain = strtolower( drak_strip_5e_markup( $label ) );
            if ( false !== strpos( $plain, 'prepared spells' ) ) {
                $progression = [];
                foreach ( $rows as $row_index => $row_values ) {
                    $progression[ $row_index + 1 ] = intval( $row_values[ $idx ] ?? 0 );
                }
                return $progression;
            }
        }
    }

    return [];
}

function drak_extract_cantrip_progression( $class_entry ) {
    if ( empty( $class_entry['cantripProgression'] ) || ! is_array( $class_entry['cantripProgression'] ) ) {
        return [];
    }

    $progression = [];
    foreach ( $class_entry['cantripProgression'] as $index => $value ) {
        $progression[ $index + 1 ] = intval( $value );
    }

    return $progression;
}

function drak_extract_spell_slot_progression( $class_entry ) {
    if ( empty( $class_entry ) || ! is_array( $class_entry ) ) {
        return [];
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        if ( empty( $group['rowsSpellProgression'] ) || ! is_array( $group['rowsSpellProgression'] ) ) {
            continue;
        }

        $labels     = $group['colLabels'] ?? [];
        $slot_index = [];
        foreach ( $labels as $idx => $label ) {
            $slot_level = drak_parse_spell_slot_label( $label );
            if ( $slot_level !== null ) {
                $slot_index[ $idx ] = $slot_level;
            }
        }

        if ( empty( $slot_index ) ) {
            continue;
        }

        $progression = [];
        foreach ( $group['rowsSpellProgression'] as $row_idx => $row_values ) {
            $character_level = $row_idx + 1;
            foreach ( $slot_index as $col_idx => $slot_level ) {
                $progression[ $character_level ][ $slot_level ] = intval( $row_values[ $col_idx ] ?? 0 );
            }
        }

        if ( ! empty( $progression ) ) {
            return $progression;
        }
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = $group['colLabels'] ?? [];
        $rows   = $group['rows'] ?? [];

        if ( empty( $labels ) || empty( $rows ) ) {
            continue;
        }

        $slots_idx = null;
        $level_idx = null;

        foreach ( $labels as $idx => $label ) {
            $plain = strtolower( drak_strip_5e_markup( $label ) );
            if ( $plain === 'spell slots' ) {
                $slots_idx = $idx;
            }
            if ( false !== strpos( $plain, 'slot level' ) ) {
                $level_idx = $idx;
            }
        }

        if ( $slots_idx === null || $level_idx === null ) {
            continue;
        }

        $progression = [];
        foreach ( $rows as $row_index => $row_values ) {
            $character_level = $row_index + 1;
            $slot_count      = intval( $row_values[ $slots_idx ] ?? 0 );
            $slot_level      = drak_parse_spell_slot_label( $row_values[ $level_idx ] ?? '' );

            if ( $slot_count > 0 && $slot_level ) {
                $progression[ $character_level ][ $slot_level ] = $slot_count;
            }
        }

        if ( ! empty( $progression ) ) {
            return $progression;
        }
    }

    return [];
}

function drak_parse_spell_token( $value ) {
    if ( is_array( $value ) ) {
        if ( isset( $value['choose'] ) ) {
            return null;
        }
        if ( isset( $value['item'] ) && is_array( $value['item'] ) ) {
            $value = reset( $value['item'] );
        } elseif ( isset( $value['entry'] ) ) {
            $value = $value['entry'];
        } else {
            $value = '';
        }
    }

    $raw = trim( (string) $value );
    if ( $raw === '' ) {
        return null;
    }

    $name   = $raw;
    $source = '';

    if ( preg_match( '/^\{@spell ([^}|]+)(?:\|([^}|]+))?(?:\|([^}]+))?\}$/i', $raw, $matches ) ) {
        $name   = $matches[1];
        $source = $matches[2] ?? '';
    } elseif ( strpos( $raw, '|' ) !== false ) {
        $parts  = explode( '|', $raw );
        $name   = $parts[0];
        $source = $parts[1] ?? '';
    }

    $name = drak_strip_5e_markup( $name );

    if ( $name === '' ) {
        return null;
    }

    return [
        'name'   => trim( $name ),
        'source' => strtoupper( trim( $source ) ),
        'raw'    => $raw,
    ];
}

function drak_collect_prepared_spells_from_additional( $additional, $max_level = null ) {
    if ( empty( $additional ) || ! is_array( $additional ) ) {
        return [];
    }

    $result = [];

    foreach ( $additional as $block ) {
        if ( empty( $block['prepared'] ) || ! is_array( $block['prepared'] ) ) {
            continue;
        }

        foreach ( $block['prepared'] as $level => $spells ) {
            $lvl = intval( $level );
            if ( $lvl <= 0 ) {
                continue;
            }
            if ( $max_level !== null && $lvl > $max_level ) {
                continue;
            }

            foreach ( (array) $spells as $entry ) {
                $spell = drak_parse_spell_token( $entry );
                if ( ! $spell ) {
                    continue;
                }
                $key = strtolower( $spell['name'] . '|' . $spell['source'] );
                if ( ! isset( $result[ $lvl ] ) ) {
                    $result[ $lvl ] = [];
                }
                if ( isset( $result[ $lvl ][ $key ] ) ) {
                    continue;
                }
                $result[ $lvl ][ $key ] = $spell;
            }
        }
    }

    foreach ( $result as $lvl => $spells ) {
        $result[ $lvl ] = array_values( $spells );
    }

    ksort( $result );

    return $result;
}

function drak_normalize_class_table_groups( $class_entry ) {
    $groups = [];

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = array_map( 'drak_strip_5e_markup', $group['colLabels'] ?? [] );
        $rows   = [];
        $has_rows = false;

        if ( ! empty( $group['rows'] ) && is_array( $group['rows'] ) ) {
            foreach ( $group['rows'] as $row ) {
                $rows[] = array_map( 'drak_strip_5e_markup', $row );
            }
            $has_rows = true;
        } elseif ( ! empty( $group['rowsSpellProgression'] ) && is_array( $group['rowsSpellProgression'] ) ) {
            $labels = array_merge( [ 'Level' ], $labels );
            foreach ( $group['rowsSpellProgression'] as $idx => $row ) {
                $display = [ $idx + 1 ];
                foreach ( $row as $value ) {
                    $display[] = is_numeric( $value ) ? intval( $value ) : drak_strip_5e_markup( (string) $value );
                }
                $rows[] = $display;
            }
            $has_rows = true;
        }

        if ( ! $has_rows ) {
            continue;
        }

        $groups[] = [
            'title'    => $group['title'] ?? '',
            'subtitle' => $group['subtitle'] ?? '',
            'colLabels'=> $labels,
            'rows'     => $rows,
        ];
    }

    return $groups;
}

function drak_get_class_reference_map( $class_id ) {
    static $cache = [];

    if ( isset( $cache[ $class_id ] ) ) {
        return $cache[ $class_id ];
    }

    $class_ref = drak_find_class_json_entry( $class_id );
    if ( ! $class_ref ) {
        drak_set_class_reference_error( sprintf( 'No se encontró referencia para la clase "%s".', $class_id ) );
        $cache[ $class_id ] = null;
        return null;
    }

    $entry = $class_ref['entry'];

    $cache[ $class_id ] = [
        'meta'                => $class_ref['meta'],
        'table_groups'        => drak_normalize_class_table_groups( $entry ),
        'prepared_progression'=> drak_extract_prepared_progression( $entry ),
        'slot_progression'    => drak_extract_spell_slot_progression( $entry ),
        'cantrip_progression' => drak_extract_cantrip_progression( $entry ),
        'additional_prepared' => drak_collect_prepared_spells_from_additional( $entry['additionalSpells'] ?? [] ),
    ];

    return $cache[ $class_id ];
}

function drak_get_class_reference_payload( $class_id, $subclass_id = '', $apothecary_theories = [] ) {
    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return null;
    }

    $payload = [
        'class_id'            => $class_id,
        'class_meta'          => $class_data['meta'],
        'table_groups'        => $class_data['table_groups'],
        'prepared_progression'=> $class_data['prepared_progression'],
        'slot_progression'    => $class_data['slot_progression'],
        'class_prepared_spells'=> $class_data['additional_prepared'],
        'cantrip_progression' => $class_data['cantrip_progression'],
    ];

    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref ) {
            $payload['subclass'] = [
                'id'        => $subclass_id,
                'meta'      => $sub_ref['meta'],
                'prepared'  => drak_collect_prepared_spells_from_additional( $sub_ref['entry']['additionalSpells'] ?? [] ),
            ];
        }
    }

    if ( drak_is_apothecary_class( $class_id ) ) {
        $payload['esoteric_theories'] = drak_expand_apothecary_theories( $apothecary_theories );
    } else {
        $payload['esoteric_theories'] = [];
    }

    return $payload;
}

function drak_get_class_prepared_limit( $class_id, $level ) {
    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return 0;
    }

    $level = max( 1, min( 20, intval( $level ) ) );
    return intval( $class_data['prepared_progression'][ $level ] ?? 0 );
}

function drak_get_class_spell_slots_for_level( $class_id, $level ) {
    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return [];
    }

    $level = max( 1, min( 20, intval( $level ) ) );
    return $class_data['slot_progression'][ $level ] ?? [];
}

function drak_filter_prepared_spell_list_by_level( $spell_map, $max_level ) {
    $result = [];
    foreach ( $spell_map as $lvl => $spells ) {
        if ( $max_level !== null && intval( $lvl ) > $max_level ) {
            continue;
        }
        $result[ intval( $lvl ) ] = $spells;
    }
    ksort( $result );
    return $result;
}

function drak_lookup_spell_reference( $name, $source = '' ) {
    static $index = null;

    if ( $index === null ) {
        $index = [];
        foreach ( drak_get_local_dnd_spells() as $spell ) {
            $spell_name   = strtolower( $spell['name'] ?? '' );
            $spell_source = strtoupper( $spell['source'] ?? '' );
            if ( $spell_name === '' ) {
                continue;
            }
            $index[ $spell_name . '|' . $spell_source ] = $spell;
            if ( $spell_source ) {
                $index[ $spell_name . '|' ] = $spell;
            }
        }
    }

    $key = strtolower( $name ) . '|' . strtoupper( $source );
    if ( isset( $index[ $key ] ) ) {
        return $index[ $key ];
    }

    $fallback = strtolower( $name ) . '|';
    return $index[ $fallback ] ?? null;
}

function drak_enrich_prepared_spell_map( $spell_map ) {
    $result = [];
    foreach ( $spell_map as $level => $spells ) {
        foreach ( $spells as $spell ) {
            $lookup = drak_lookup_spell_reference( $spell['name'], $spell['source'] );
            if ( $lookup ) {
                $spell['spell_level'] = intval( $lookup['level'] ?? 0 );
                $spell['spell_id']    = $lookup['id'] ?? '';
                $spell['source']      = $lookup['source'] ?? $spell['source'];
            } else {
                $spell['spell_level'] = 0;
                $spell['spell_id']    = '';
            }
            $result[ $level ][] = $spell;
        }
    }
    return $result;
}

function drak_get_auto_prepared_spells( $class_id, $subclass_id, $character_level, &$reference_payload = null, $options = [] ) {
    $payload = [
        'class'    => [],
        'subclass' => [],
    ];

    $class_data = drak_get_class_reference_map( $class_id );
    if ( $class_data && ! empty( $class_data['class_prepared_spells'] ) ) {
        $filtered = drak_filter_prepared_spell_list_by_level( $class_data['class_prepared_spells'], $character_level );
        $payload['class'] = drak_enrich_prepared_spell_map( $filtered );
    }

    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref ) {
            $spell_map = drak_collect_prepared_spells_from_additional( $sub_ref['entry']['additionalSpells'] ?? [] );
            $filtered  = drak_filter_prepared_spell_list_by_level( $spell_map, $character_level );
            $payload['subclass'] = drak_enrich_prepared_spell_map( $filtered );
        }
    }

    if ( func_num_args() >= 4 ) {
        $selected_theories = [];
        if ( is_array( $options ) && isset( $options['apothecary_theories'] ) ) {
            $selected_theories = $options['apothecary_theories'];
        }
        $reference_payload = drak_get_class_reference_payload( $class_id, $subclass_id, $selected_theories );
    }

    return $payload;
}

function drak_format_signed_number( $value ) {
    if ( $value === null || $value === '' ) {
        return '—';
    }
    $value = intval( $value );
    return $value > 0 ? '+' . $value : (string) $value;
}

function drak_spellcasting_log( $message ) {
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit( $upload_dir['basedir'] );
    $log_file   = $log_dir . 'spellcasting-debug.log';

    if ( ! file_exists( $log_file ) ) {
        @file_put_contents( $log_file, "=== Spellcasting Debug Log ===\n" );
    }

    $timestamp = date_i18n( 'Y-m-d H:i:s' );
    @file_put_contents( $log_file, '[' . $timestamp . '] ' . $message . "\n", FILE_APPEND );
}

function drak_get_ability_field_map() {
    return [
        'str' => ['field' => 'cs_fuerza',       'label' => 'Fuerza',       'short' => 'FUE'],
        'dex' => ['field' => 'cs_destreza',     'label' => 'Destreza',     'short' => 'DES'],
        'con' => ['field' => 'cs_constitucion', 'label' => 'Constitución', 'short' => 'CON'],
        'int' => ['field' => 'cs_inteligencia', 'label' => 'Inteligencia', 'short' => 'INT'],
        'wis' => ['field' => 'cs_sabiduria',    'label' => 'Sabiduría',    'short' => 'SAB'],
        'cha' => ['field' => 'cs_carisma',      'label' => 'Carisma',      'short' => 'CAR'],
    ];
}

function drak_get_grimorio_spellcasting_stats( $post_id, $context = [] ) {
    $defaults = [
        'ability_key'      => null,
        'ability_label'    => '—',
        'ability_short'    => null,
        'ability_display'  => '—',
        'ability_modifier' => null,
        'spell_attack'     => '—',
        'spell_attack_value' => null,
        'spell_dc'         => '—',
    ];

    if ( ! $post_id || ! function_exists( 'get_field' ) ) {
        drak_spellcasting_log( '[Spellcasting] abort: invalid post or missing get_field' );
        return $defaults;
    }

    $class_id = $context['class_id'] ?? get_field( 'clase', $post_id );
    $ability_key = drak_get_spellcasting_ability_for_class( $class_id );
    if ( ! $ability_key ) {
        drak_spellcasting_log( '[Spellcasting] no spellcasting ability for post ' . $post_id . ' class=' . var_export( $class_id, true ) );
        return $defaults;
    }

    $ability_map = drak_get_ability_field_map();
    if ( ! isset( $ability_map[ $ability_key ] ) ) {
        return $defaults;
    }

    $score_field = $ability_map[ $ability_key ]['field'];
    if ( isset( $context['scores'][ $score_field ] ) ) {
        $score_value = $context['scores'][ $score_field ];
    } else {
        $score_raw   = get_field( $score_field, $post_id );
        $score_value = ( $score_raw === '' || $score_raw === null ) ? null : intval( $score_raw );
    }

    if ( isset( $context['prof_bonus'] ) ) {
        $prof_bonus = intval( $context['prof_bonus'] );
    } else {
        $prof_bonus = intval( get_field( 'cs_proeficiencia', $post_id ) );
    }

    $ability_mod = $score_value !== null ? floor( ( $score_value - 10 ) / 2 ) : null;

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] computed context post=%d class=%s ability=%s score=%s prof=%s mod=%s',
            $post_id,
            $class_id ?? 'n/a',
            $ability_key,
            var_export( $score_value, true ),
            var_export( $prof_bonus, true ),
            var_export( $ability_mod, true )
        )
    );

    $stats = [
        'ability_key'        => $ability_key,
        'ability_label'      => $ability_map[ $ability_key ]['label'],
        'ability_short'      => $ability_map[ $ability_key ]['short'],
        'ability_modifier'   => $ability_mod,
        'ability_display'    => $ability_map[ $ability_key ]['short'],
        'spell_attack'       => '—',
        'spell_attack_value' => null,
        'spell_dc'           => '—',
    ];

    if ( $ability_mod !== null ) {
        $attack_value = $prof_bonus + $ability_mod;
        $stats['ability_display'] = sprintf( '%s (%s)', $stats['ability_short'], drak_format_signed_number( $ability_mod ) );
        $stats['spell_attack']    = drak_format_signed_number( $attack_value );
        $stats['spell_attack_value'] = $attack_value;
        $stats['spell_dc']        = (string) ( 8 + $prof_bonus + $ability_mod );
    }

    return $stats;
}

function drak_update_spellcasting_fields( $post_id ) {
    if ( ! $post_id || ! function_exists( 'update_field' ) ) {
        return;
    }

    $ability_map = drak_get_ability_field_map();
    $context = [
        'class_id'   => isset( $_POST['clase'] ) ? drak_get_post_value( 'clase', get_field( 'clase', $post_id ) ) : get_field( 'clase', $post_id ),
        'scores'     => [],
        'prof_bonus' => isset( $_POST['cs_proeficiencia'] ) ? intval( drak_get_post_value( 'cs_proeficiencia', get_field( 'cs_proeficiencia', $post_id ) ) ) : intval( get_field( 'cs_proeficiencia', $post_id ) ),
    ];

    foreach ( $ability_map as $info ) {
        $field = $info['field'];
        if ( isset( $_POST[ $field ] ) ) {
            $context['scores'][ $field ] = intval( drak_get_post_value( $field, get_field( $field, $post_id ) ) );
        }
    }

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] save context post=%d class=%s scores=%s prof=%s',
            $post_id,
            var_export( $context['class_id'], true ),
            json_encode( $context['scores'] ),
            var_export( $context['prof_bonus'], true )
        )
    );

    $stats = drak_get_grimorio_spellcasting_stats( $post_id, $context );
    if ( ! $stats ) {
        drak_spellcasting_log( '[Spellcasting] stats not available for post ' . $post_id );
        return;
    }

    $ability_value = $stats['ability_short'] ?? '';
    $dc_value      = $stats['spell_dc'] !== '—' ? $stats['spell_dc'] : '';
    $attack_value  = $stats['spell_attack_value'] !== null ? $stats['spell_attack_value'] : '';

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] saving post=%d ability=%s dc=%s attack=%s',
            $post_id,
            var_export( $ability_value, true ),
            var_export( $dc_value, true ),
            var_export( $attack_value, true )
        )
    );

    update_field( 'spellcasting_hability', $ability_value, $post_id );
    update_field( 'spell_save_dc', $dc_value, $post_id );
    update_field( 'spell_attack_bonus', $attack_value, $post_id );
}

function renderizar_grimorio_personaje( $post_id ) {
    if ( ! $post_id ) {
        return '';
    }

    if ( isset( $_POST['grimorio_guardar'], $_POST['grimorio_nonce'] ) && wp_verify_nonce( $_POST['grimorio_nonce'], 'grimorio_guardar_' . $post_id ) && drak_user_can_manage_personaje( $post_id ) ) {
        $slots_posted = isset( $_POST['grimorio_slots_used'] ) ? $_POST['grimorio_slots_used'] : [];
        drak_grimorio_save_slots( $post_id, $slots_posted );

        $spells_posted = isset( $_POST['grimorio_spells'] ) ? $_POST['grimorio_spells'] : [];
        drak_grimorio_save_prepared( $post_id, $spells_posted );
    }

    $nivel      = intval( get_field( 'nivel', $post_id ) );
    $clase_id   = get_field( 'clase', $post_id );
    $slots_used = drak_grimorio_get_slots( $post_id );
    $prepared   = drak_grimorio_get_prepared( $post_id );
    $concentration = get_post_meta( $post_id, 'grimorio_concentration_state', true );
    $concentration = is_array( $concentration ) ? $concentration : [];
    $concentration_level = isset( $concentration['level'] ) ? intval( $concentration['level'] ) : null;
    $concentration_spell = isset( $concentration['spell'] ) ? (string) $concentration['spell'] : '';
    $concentration_spell_id = isset( $concentration['spell_id'] ) ? (string) $concentration['spell_id'] : '';
    $transformation_state = drak_grimorio_get_transformation_state( $post_id );

    $row = drak_get_class_spell_slots_for_level( $clase_id, $nivel );
    if ( empty( $row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $row      = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }
    ksort( $row );

    $subclass_id    = get_field( 'subclase', $post_id );
    $auto_prepared  = drak_get_auto_prepared_spells( $clase_id, $subclass_id, $nivel );
    $has_auto_prepared = false;
    foreach ( $auto_prepared as $group ) {
        foreach ( $group as $spells ) {
            if ( ! empty( $spells ) ) {
                $has_auto_prepared = true;
                break 2;
            }
        }
    }

    ob_start();
    ?>
    <div class="grimorio-formulario">
      <section class="grimorio-slot-grid">
        <h3>Espacios de conjuro</h3>
        <div class="grimorio-slot-grid__inner">
        <?php foreach ( $row as $lvl => $max_slots ) :
            $max_slots = intval( $max_slots );
            if ( $max_slots <= 0 ) {
                continue;
            }
            $used = isset( $slots_used[ $lvl ] ) ? intval( $slots_used[ $lvl ] ) : 0;
            ?>
            <div class="grimorio-slot-column" data-level="<?php echo esc_attr( $lvl ); ?>" data-max="<?php echo esc_attr( $max_slots ); ?>">
              <header>
                <span>Nivel <?php echo esc_html( $lvl ); ?></span>
                <small><?php echo esc_html( $max_slots ); ?> slots</small>
              </header>
              <div class="grimorio-slot-checkboxes">
                <?php for ( $i = 1; $i <= $max_slots; $i++ ) :
                    $checked = $i <= $used ? 'checked' : '';
                    ?>
                    <label>
                      <input type="checkbox" class="grimorio-slot-toggle" <?php echo $checked; ?>>
                      <span></span>
                    </label>
                <?php endfor; ?>
              </div>
              <input type="hidden" name="grimorio_slots_used[<?php echo esc_attr( $lvl ); ?>]" value="<?php echo esc_attr( $used ); ?>">
            </div>
        <?php endforeach; ?>
        </div>
      </section>

      <section class="grimorio-quick-actions">
        <button type="button" class="grimorio-reset-slots">Descanso largo</button>
        <button type="button" class="grimorio-reset-prepared">Reiniciar grimorio</button>
        <button type="button" class="grimorio-finish-concentration" <?php disabled( empty( $concentration_spell ) && empty( $concentration_spell_id ) ); ?>>
          Fin concentración
        </button>
      </section>
    </div>

    <?php
    $is_mutagenist_apothecary = ( 'apothecary-mutagenist-scgtd-drakkenheim' === $subclass_id );
    if ( $is_mutagenist_apothecary ) :
        ?>
        <section class="grimorio-transformation" id="grimorio-transformation">
          <div class="grimorio-transformation__head">
            <h3>Transformación temporal</h3>
            <p>Usa un espacio de conjuro para adoptar tu forma potenciada.</p>
          </div>
          <div class="grimorio-transformation__actions">
            <button type="button" class="grimorio-transform-start" id="grimorio-transform-start">Activar transformación</button>
            <button type="button" class="grimorio-transform-finish" id="grimorio-transform-finish" <?php disabled( empty( $transformation_state['active'] ) ); ?>>
              Finalizar transformación
            </button>
          </div>
          <div class="grimorio-transformation__body" id="grimorio-transformation-display"></div>
        </section>
    <?php endif; ?>

    <section class="grimorio-prepared">
      <div class="grimorio-prepared__header">
        <h3>Conjuros preparados</h3>
        <div class="grimorio-prepared__summary" id="grimorio-prepared-total"></div>
        <button type="button" class="grimorio-prepared__edit-btn">Editar conjuros</button>
      </div>

      <div class="grimorio-prepared__levels">
        <?php foreach ( $row as $lvl => $max_slots ) :
            if ( intval( $max_slots ) <= 0 ) {
                continue;
            }
            $current = $prepared[ $lvl ] ?? [];
            ?>
            <article class="grimorio-prepared-block" data-level="<?php echo esc_attr( $lvl ); ?>" data-max="<?php echo esc_attr( $max_slots ); ?>">
              <div class="grimorio-prepared-block__head">
                <div>
                  <span class="grimorio-prepared-block__label">Nivel <?php echo esc_html( $lvl ); ?></span>
                  <small><?php echo esc_html( $max_slots ); ?> huecos disponibles</small>
                </div>
                <span class="grimorio-prepared-block__counter" data-counter-for="<?php echo esc_attr( $lvl ); ?>">
                  <?php echo esc_html( count( array_filter( $current ) ) ); ?> / <?php echo esc_html( $max_slots ); ?>
                </span>
              </div>
              <ul class="grimorio-prepared-block__list" data-list-level="<?php echo esc_attr( $lvl ); ?>">
                <?php if ( empty( $current ) ) : ?>
                  <li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay conjuros preparados.</li>
                <?php else : ?>
                  <?php foreach ( $current as $spell_name ) :
                      $spell_name = trim( (string) $spell_name );
                      if ( '' === $spell_name ) {
                          continue;
                      }
                      ?>
                      <?php
                        $is_concentration = ( null !== $concentration_level && intval( $concentration_level ) === intval( $lvl ) )
                          && ( $concentration_spell === $spell_name );
                      ?>
                      <li class="grimorio-prepared-spell <?php echo $is_concentration ? 'grimorio-prepared-spell--concentration' : ''; ?>"
                          data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                        <span class="grimorio-prepared-spell__name"><?php echo esc_html( $spell_name ); ?></span>
                        <button type="button"
                                class="grimorio-cast-spell"
                                data-level="<?php echo esc_attr( $lvl ); ?>"
                                data-spell-id=""
                                data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                          Lanzar spell
                        </button>
                      </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </article>
        <?php endforeach; ?>
      </div>
    </section>

    <div id="grimorio-spell-picker" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Seleccionar conjuros preparados</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">
            Marca los conjuros que quieres preparar en cada nivel. El límite total de conjuros preparados depende de tu clase y nivel.
          </p>
          <div id="grimorio-spell-picker-loading" class="grimorio-spell-picker__loading">
            Cargando lista de conjuros...
          </div>
          <div id="grimorio-spell-picker-levels" class="grimorio-spell-picker__levels" hidden></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-spell-picker-save">
            Guardar y cerrar
          </button>
        </footer>
      </div>
    </div>

    <div id="grimorio-info-modal" class="grimorio-modal grimorio-modal--small" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3 id="grimorio-info-title">Información</h3>
        </header>
        <div class="grimorio-modal__body" id="grimorio-info-content">
          <p>Información del conjuro.</p>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn grimorio-info-close" data-grimorio-close>Cerrar</button>
        </footer>
      </div>
    </div>

    <div id="grimorio-transformation-modal" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Seleccionar nivel de slot</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">Solo puedes transformar si tienes espacios de conjuro disponibles.</p>
          <div id="grimorio-transformation-levels" class="grimorio-transformation-levels"></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-transformation-confirm">
            Activar
          </button>
        </footer>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
function drak_render_grimorio_auto_prepared_section( $auto_prepared, $subclass_id ) {
    $subclass_label = '';
    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref && ! empty( $sub_ref['meta']['name'] ) ) {
            $subclass_label = $sub_ref['meta']['name'];
        }
    }
    ob_start();
    ?>
  <section class="grimorio-auto-prepared">
      <div class="grimorio-auto-prepared__header">
        <h3>Conjuros siempre preparados</h3>
        <p>Estas opciones no consumen tus huecos de conjuros preparados.</p>
      </div>
      <?php
        $auto_groups = [
            'class'    => __( 'De tu clase', 'grimorio' ),
            'subclass' => $subclass_label ? $subclass_label : __( 'De tu subclase', 'grimorio' ),
        ];
        foreach ( $auto_groups as $group_key => $group_label ) :
            $group_spells = $auto_prepared[ $group_key ] ?? [];
            $group_has_spells = false;
            foreach ( $group_spells as $spell_list ) {
                if ( ! empty( $spell_list ) ) {
                    $group_has_spells = true;
                    break;
                }
            }
            if ( ! $group_has_spells ) {
                continue;
            }
        ?>
        <div class="grimorio-auto-prepared__group">
          <h4><?php echo esc_html( $group_label ); ?></h4>
          <?php foreach ( $group_spells as $unlock_level => $spells ) : ?>
            <?php if ( empty( $spells ) ) { continue; } ?>
            <div class="grimorio-auto-prepared__level">
              <span class="grimorio-auto-prepared__level-label">
                Disponible al nivel <?php echo esc_html( $unlock_level ); ?>
              </span>
              <ul class="grimorio-auto-prepared__list">
                <?php foreach ( $spells as $spell ) :
                    $spell_name   = $spell['name'] ?? '';
                    $spell_source = $spell['source'] ?? '';
                    $spell_level  = intval( $spell['spell_level'] ?? 0 );
                    $spell_id     = $spell['spell_id'] ?? '';
                    if ( '' === $spell_name ) {
                        continue;
                    }
                    ?>
                    <li class="grimorio-prepared-spell grimorio-prepared-spell--auto">
                      <div class="grimorio-prepared-spell__info">
                        <button type="button"
                                class="grimorio-prepared-spell__name"
                                data-spell-id="<?php echo esc_attr( $spell_id ); ?>"
                                data-spell-name="<?php echo esc_attr( $spell_name ); ?>"
                                data-spell-level="<?php echo esc_attr( $spell_level ); ?>">
                          <?php echo esc_html( $spell_name ); ?>
                        </button>
                        <?php if ( $spell_source ) : ?>
                          <small class="grimorio-prepared-spell__source"><?php echo esc_html( $spell_source ); ?></small>
                        <?php endif; ?>
                      </div>
                      <?php if ( $spell_level > 0 ) : ?>
                        <button type="button"
                                class="grimorio-cast-spell"
                                data-level="<?php echo esc_attr( $spell_level ); ?>"
                                data-spell-id="<?php echo esc_attr( $spell_id ); ?>"
                                data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                          Lanzar spell
                        </button>
                      <?php endif; ?>
                    </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function drak_render_spell_search_module() {
    if ( empty( drak_get_spellcasting_classes() ) ) {
        return '';
    }

    ob_start();
    ?>
    <section class="spell-search-module" data-spell-search>
      <div class="spell-search-module__intro">
        <h3>Buscador de conjuros</h3>
        <p>Encuentra rápidamente conjuros por nombre o cada catálogo de clase.</p>
      </div>
      <form class="spell-search__form" data-spell-search-form>
        <label class="spell-search__field">
          <span class="screen-reader-text">Nombre del conjuro</span>
          <input type="text" data-spell-search-input placeholder="Escribe el nombre del conjuro" autocomplete="off">
        </label>
        <button type="submit" class="spell-search__submit">Buscar</button>
      </form>
      <div class="spell-search__filters" data-spell-search-classes></div>
      <div class="spell-search__suggestions" data-spell-search-suggestions hidden></div>

      <div class="grimorio-modal spell-search-modal" role="dialog" aria-modal="true" aria-hidden="true" data-spell-search-modal>
        <div class="grimorio-modal__dialog">
          <header class="grimorio-modal__header">
            <h3>Resultados del buscador</h3>
            <button type="button" class="grimorio-modal__close" data-spell-search-close>&times;</button>
          </header>
          <div class="grimorio-modal__body" data-spell-search-results>
            <p>Aún no has realizado ninguna búsqueda.</p>
          </div>
          <footer class="grimorio-modal__footer">
            <button type="button" class="grimorio-modal__btn" data-spell-search-close>Cerrar</button>
          </footer>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key' => 'group_grimorio_personaje',
        'title' => 'Grimorio personaje',
        'fields' => [
            [
                'key' => 'field_grimorio_slots_used',
                'label' => 'Slots de conjuro usados',
                'name' => 'grimorio_slots_used',
                'type' => 'textarea',
                'instructions' => 'Almacén interno del módulo de grimorio (no editar manualmente).',
            ],
            [
                'key' => 'field_grimorio_spells',
                'label' => 'Conjuros preparados',
                'name' => 'grimorio_spells',
                'type' => 'textarea',
                'instructions' => 'Almacén interno del módulo de grimorio (no editar manualmente).',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'personaje',
                ],
            ],
        ],
        'position' => 'normal',
        'style'    => 'default',
    ] );
} );
add_action('init', 'drak_register_personaje_cpt');

// Ocultar barra de administración para usuarios no administradores
add_action('after_setup_theme', function () {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});

if ( is_admin() ) {
    add_filter( 'acf/load_value/name=grimorio_slots_used', 'drak_grimorio_acf_admin_format_json' );
    add_filter( 'acf/load_value/name=grimorio_spells', 'drak_grimorio_acf_admin_format_json' );
}

function drak_grimorio_acf_admin_format_json( $value ) {
    if ( is_array( $value ) ) {
        return wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
    if ( is_string( $value ) && $value !== '' ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }
    }
    return $value;
}

// Redirigir /pj al login si no está logueado
function redirigir_pj_si_no_logueado() {
    if (is_page('pj') && !is_user_logged_in()) {
        auth_redirect();
    }
}
add_action('template_redirect', 'redirigir_pj_si_no_logueado');

/**
 * Obtiene la consulta de personajes asociados a un usuario.
 */
function drak_get_user_personajes_query( $user_id ) {
    $args = [
        'post_type'      => 'personaje',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'jugador_asociado',
            'value'   => $user_id,
            'compare' => '=',
        ]],
    ];

    return new WP_Query( $args );
}

/**
 * HTML reutilizable de bienvenida para las vistas de personajes del usuario.
 */
function drak_render_personajes_welcome( WP_User $user ) {
    return '<div class="bienvenida-usuario">Bienvenido, <strong>' . esc_html( $user->display_name ) . '</strong></div>';
}

// Shortcode para mostrar personajes asociados a un usuario
function mostrar_personajes_del_usuario() {
    if (!is_user_logged_in()) {
        wp_login_form(['redirect' => home_url('/pj/')]);
        return '';
    }

    $usuario = wp_get_current_user();
    $query   = drak_get_user_personajes_query( $usuario->ID );

    ob_start();

    echo drak_render_personajes_welcome( $usuario );

    if ($query->have_posts()) {
        echo '<div class="lista-personajes">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="tarjeta-personaje">';
            if (has_post_thumbnail()) {
                echo '<div class="avatar-personaje">';
                echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail(get_the_ID(), 'medium') . '</a>';
                echo '</div>';
            }
            echo '<h3 class="nombre-personaje"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No tienes personajes asignados todavía.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('personajes_usuario', 'mostrar_personajes_del_usuario');


function drak_render_galeria_personajes_usuario() {
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url(home_url('/pj')));
        exit;
    }

    $usuario = wp_get_current_user();

    $query = drak_get_user_personajes_query( $usuario->ID );

    ob_start();

    echo drak_render_personajes_welcome( $usuario );

    if ($query->have_posts()) {
      echo '<div class="galeria-personajes">';
while ($query->have_posts()) {
    $query->the_post();
    $img_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
    $link = get_permalink(get_the_ID());  // Enlace a single-personaje.php

    echo '<a href="' . esc_url($link) . '" class="personaje-card">';
    echo '<div class="imagen-personaje" style="background-image: url(' . esc_url($img_url) . ')"></div>';
    echo '<div class="nombre-personaje">' . esc_html(get_the_title()) . '</div>';
    echo '</a>';
}
echo '</div>';

    } else {
        echo '<p class="sin-personajes">No tienes personajes asignados todavía.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('galeria_personajes_usuario', 'drak_render_galeria_personajes_usuario');



add_action('add_meta_boxes', function() {
    add_meta_box('postimagediv', __('Imagen destacada'), 'post_thumbnail_meta_box', 'personaje', 'side', 'low');
});

/**
 * Busca el primer slug de página existente para mantener reglas de reescritura flexibles.
 */
function drak_locate_existing_page_slug( array $candidates, $fallback = '' ) {
    foreach ( $candidates as $slug ) {
        if ( get_page_by_path( $slug ) ) {
            return $slug;
        }
    }

    if ( $fallback ) {
        return $fallback;
    }

    return $candidates[0] ?? '';
}

/**
 * Registra todas las reglas de URLs amigables relacionadas con personajes.
 */
function drak_register_personaje_rewrites() {
    $inventory_page = drak_locate_existing_page_slug( ['inventario-personaje', 'inventario'], 'inventario-personaje' );
    $sheet_page     = drak_locate_existing_page_slug( ['hoja-personaje'], 'hoja-personaje' );
    $grimorio_page  = drak_locate_existing_page_slug( ['grimorio'], 'grimorio' );

    $rules = [
        '^personaje/([^/]+)/inventario/?' => 'index.php?personaje=$matches[1]&inventario_personaje=1',
        '^inventario/([^/]+)/?$'          => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $inventory_page ),
        '^hoja-personaje/([^/]+)/?$'      => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $sheet_page ),
        '^grimorio/([^/]+)/?$'            => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $grimorio_page ),
    ];

    foreach ( $rules as $regex => $query ) {
        add_rewrite_rule( $regex, $query, 'top' );
    }
}
add_action( 'init', 'drak_register_personaje_rewrites' );

/**
 * Registra las query vars personalizadas usadas por las reglas anteriores.
 */
function drak_register_personaje_query_vars( $vars ) {
    $vars[] = 'inventario_personaje';
    $vars[] = 'personaje_slug';

    return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'drak_register_personaje_query_vars' );

function drak_get_static_data_base() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $candidates = [ 'data', 'jsons' ];
    foreach ( $candidates as $folder ) {
        $dir = trailingslashit( get_stylesheet_directory() ) . $folder;
        $uri = trailingslashit( get_stylesheet_directory_uri() ) . $folder;
        if ( file_exists( $dir . '/dnd-races.json' ) ) {
            $cache = [
                'dir' => trailingslashit( $dir ),
                'uri' => trailingslashit( $uri ),
            ];
            return $cache;
        }
    }

    $cache = [
        'dir' => trailingslashit( get_stylesheet_directory() ),
        'uri' => trailingslashit( get_stylesheet_directory_uri() ),
    ];

    return $cache;
}

function drak_static_data_uri( $filename ) {
    $base      = drak_get_static_data_base();
    $file_path = $base['dir'] . $filename;
    if ( file_exists( $file_path ) ) {
        return $base['uri'] . $filename;
    }

    return '';
}

function guardar_hp_temporal() {
	
	    if (!isset($_POST['post_id']) || !isset($_POST['valor'])) {
        wp_send_json_error(['message' => 'Faltan parámetros.']);
    }
    $post_id = intval($_POST['post_id']);
    $valor = intval($_POST['valor']);

    // Usa ACF para guardar correctamente el campo
    update_field('cs_hp_temp', $valor, $post_id);

    wp_send_json_success(['message' => 'HP temporal guardado con éxito']);
}

add_action('wp_ajax_guardar_hp_temporal', 'guardar_hp_temporal');
add_action('wp_ajax_nopriv_guardar_hp_temporal', 'guardar_hp_temporal');


add_action('wp_enqueue_scripts', function () {
    if (is_page_template('page-hoja-personaje.php')) {
        $personaje_slug = get_query_var('personaje_slug');
        $personaje = $personaje_slug ? get_page_by_path($personaje_slug, OBJECT, 'personaje') : null;
        $post_id = $personaje ? $personaje->ID : 0;

        wp_enqueue_script('class-reference-js', get_stylesheet_directory_uri() . '/js/class-reference.js', ['jquery'], null, true);
        wp_enqueue_script('hoja-personaje-js', get_stylesheet_directory_uri() . '/js/hoja-personaje.js', ['jquery', 'class-reference-js'], null, true);
        wp_localize_script('hoja-personaje-js', 'HP_TEMP_AJAX', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'post_id' => $post_id,
        ]);
        wp_localize_script('hoja-personaje-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        wp_localize_script('hoja-personaje-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => drak_static_data_uri( 'esotherics.json' ),
            'esotericTheoriesData' => $apothecary_theories,
        ]);
        wp_localize_script('hoja-personaje-js', 'APOTHECARY_THEORY_CATALOG', $apothecary_theories );

    }

    if (is_page_template('page-grimorio-personaje.php')) {
        $personaje_slug = get_query_var('personaje_slug');
        $personaje = $personaje_slug ? get_page_by_path($personaje_slug, OBJECT, 'personaje') : null;
        $post_id = $personaje ? $personaje->ID : 0;
        $nivel  = $personaje ? intval( get_field( 'nivel', $post_id ) ) : 0;
        $clase  = $personaje ? get_field( 'clase', $post_id ) : '';
        $subclase = $personaje ? get_field( 'subclase', $post_id ) : '';
        $slots  = drak_grimorio_get_slots( $post_id );
        $spells = drak_grimorio_get_prepared( $post_id );
        $subclase   = $personaje ? get_field( 'subclase', $post_id ) : '';
        $slot_row   = drak_get_class_spell_slots_for_level( $clase, $nivel );
        if ( empty( $slot_row ) ) {
            $fallback  = drak_get_full_caster_slots_table();
            $slot_row  = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
        }
        ksort( $slot_row );
        $concentration = get_post_meta( $post_id, 'grimorio_concentration_state', true );
        $concentration = is_array( $concentration ) ? $concentration : [];
        $apothecary_selection = drak_get_character_apothecary_theories( $post_id );
        $class_reference_payload = null;
        $auto_prepared = drak_get_auto_prepared_spells(
            $clase,
            $subclase,
            $nivel,
            $class_reference_payload,
            [
                'apothecary_theories' => $apothecary_selection,
            ]
        );
        $prepared_limit = drak_get_class_prepared_limit( $clase, $nivel );
        $ability_fields = drak_get_ability_field_map();
        $ability_scores = [];
        foreach ( $ability_fields as $ability_key => $meta ) {
            $raw                       = get_field( $meta['field'], $post_id );
            $ability_scores[ $ability_key ] = ( $raw === '' || $raw === null ) ? null : intval( $raw );
        }
        $base_ac_raw    = get_field( 'cs_ac', $post_id );
        $base_speed_raw = get_field( 'cs_velocidad', $post_id );
        $base_ac        = ( $base_ac_raw === '' || $base_ac_raw === null ) ? 0 : intval( $base_ac_raw );
        $base_speed     = is_numeric( $base_speed_raw ) ? intval( $base_speed_raw ) : intval( preg_replace( '/[^0-9]/', '', (string) $base_speed_raw ) );
        $base_speed     = max( 0, $base_speed );
        $transformation_state = drak_grimorio_get_transformation_state( $post_id );
        $transformation_nonce = wp_create_nonce( 'grimorio_transformation_' . $post_id );

        wp_enqueue_script('class-reference-js', get_stylesheet_directory_uri() . '/js/class-reference.js', ['jquery'], null, true);
        wp_enqueue_script('hoja-personaje-js', get_stylesheet_directory_uri() . '/js/hoja-personaje.js', ['jquery', 'class-reference-js'], null, true);
        wp_enqueue_script('grimorio-js', get_stylesheet_directory_uri() . '/js/grimorio.js', ['jquery', 'class-reference-js', 'hoja-personaje-js'], null, true);
        $grimorio_apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        wp_localize_script('grimorio-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => drak_static_data_uri( 'esotherics.json' ),
            'esotericTheoriesData' => $grimorio_apothecary_theories,
        ]);
        wp_localize_script('grimorio-js', 'APOTHECARY_THEORY_CATALOG', $grimorio_apothecary_theories );
        wp_localize_script('grimorio-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $slots_nonce     = wp_create_nonce( 'grimorio_slots_' . $post_id );
        $prepared_nonce  = wp_create_nonce( 'grimorio_prepared_' . $post_id );
        $concentration_nonce = wp_create_nonce( 'grimorio_concentration_' . $post_id );

        wp_localize_script('grimorio-js', 'GRIMORIO_DATA', [
            'ajax_url'       => drak_get_admin_ajax_url(),
            'post_id'        => $post_id,
            'level'          => $nivel,
            'class_id'       => $clase,
            'subclass_id'    => $subclase,
            'slots_used'     => is_array( $slots ) ? $slots : [],
            'prepared'       => is_array( $spells ) ? $spells : [],
            'slot_limits'    => array_map( 'intval', $slot_row ),
            'prepared_limit' => $prepared_limit,
            'nonce'          => $slots_nonce,
            'prepared_nonce' => $prepared_nonce,
            'concentration'  => [
                'level'   => isset( $concentration['level'] ) ? intval( $concentration['level'] ) : null,
                'spell'   => $concentration['spell'] ?? '',
                'spell_id'=> $concentration['spell_id'] ?? '',
            ],
            'concentration_nonce' => $concentration_nonce,
            'auto_prepared_spells' => $auto_prepared,
            'class_reference'   => $class_reference_payload,
            'esoteric_theories' => $apothecary_selection,
            'abilities'         => $ability_scores,
            'base_ac'           => $base_ac,
            'base_speed'        => $base_speed,
            'apothecary_level'  => $nivel,
            'transformation'    => $transformation_state,
            'transformation_nonce' => $transformation_nonce,
        ]);
        wp_enqueue_script('spell-search-js', get_stylesheet_directory_uri() . '/js/spell-search.js', ['jquery'], null, true);
        wp_localize_script('spell-search-js', 'SPELL_SEARCH_CONFIG', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'classes'  => drak_get_spellcasting_classes(),
            'labels'   => [
                'placeholder' => __( 'Escribe el nombre del conjuro…', 'grimorio' ),
                'empty'       => __( 'No se encontraron resultados para tu búsqueda.', 'grimorio' ),
                'error'       => __( 'No se pudo completar la búsqueda. Inténtalo nuevamente.', 'grimorio' ),
            ],
        ]);
        wp_localize_script('hoja-personaje-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $sheet_apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        wp_localize_script('hoja-personaje-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => drak_static_data_uri( 'esotherics.json' ),
            'esotericTheoriesData' => $sheet_apothecary_theories,
        ]);
        wp_localize_script('hoja-personaje-js', 'APOTHECARY_THEORY_CATALOG', $sheet_apothecary_theories );
    }
});


/**
 * AJAX: lista de clases D&D 5e
 */

/**
 * Lee y cachea el JSON local con clases/subclases de D&D.
 */
function drak_locate_theme_data_file( $relative ) {
    $relative = ltrim( $relative, '/\\' );
    $candidates = [];
    $child_base = trailingslashit( get_stylesheet_directory() );
    $parent_base = function_exists( 'get_template_directory' ) ? trailingslashit( get_template_directory() ) : $child_base;

    $bases = [
        $child_base . 'data/',
        $child_base . 'jsons/',
        $child_base . '5etools-src-main/data/',
        $parent_base . 'data/',
        $parent_base . 'jsons/',
        $parent_base . '5etools-src-main/data/',
        $child_base,
        $parent_base,
    ];

    foreach ( $bases as $base ) {
        $path = $base . $relative;
        if ( file_exists( $path ) ) {
            return $path;
        }
    }

    return '';
}

function drak_get_local_dnd_classes_data() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $path = drak_locate_theme_data_file( 'dnd-classes.json' );
    if (!$path) {
        return null;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $cached = $data;
    return $cached;
}

/**
 * AJAX: lista de clases (desde dnd-classes.json)
 */

function drak_dnd5_get_classes() {
    $data = drak_get_local_dnd_classes_data();

    if (!$data || empty($data['classes']) || !is_array($data['classes'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-classes.json']);
    }

    $out  = [];
    $seen = [];

    foreach ($data['classes'] as $cls) {
        $name = $cls['name'] ?? '';
        if ($name === '') {
            continue;
        }

        // Usamos el slug del nombre para detectar duplicados
        $slug = sanitize_title($name);
        if (isset($seen[$slug])) {
            // Ya hemos añadido esta clase (aunque haya otra edición/libro)
            continue;
        }
        $seen[$slug] = true;

        $out[] = [
            'id'            => $cls['id']            ?? '',
            'name'          => $name,
            'source'        => $cls['source']        ?? '',
            'edition'       => $cls['edition']       ?? '',
            'subclassTitle' => $cls['subclassTitle'] ?? '',
        ];
    }

    wp_send_json_success(['classes' => $out]);
}


add_action('wp_ajax_drak_dnd5_get_classes', 'drak_dnd5_get_classes');
add_action('wp_ajax_nopriv_drak_dnd5_get_classes', 'drak_dnd5_get_classes');

/**
 * AJAX: subclases disponibles para una clase
 */
/**
 * AJAX: subclases para una clase concreta (desde dnd-classes.json)
 */

function drak_dnd5_get_subclasses() {
    // Usamos el mismo nombre de parámetro ('class_index'),
    // pero contiene el ID de nuestra clase local.
    $class_id = drak_get_post_value('class_index', '');

    if (!$class_id) {
        wp_send_json_error(['message' => 'Falta el parámetro class_index']);
    }

    $data = drak_get_local_dnd_classes_data();
    if (!$data || empty($data['classes']) || !is_array($data['classes'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-classes.json']);
    }

    // 1) Localizamos la clase por ID para conocer su "name"
    $target_name = '';
    foreach ($data['classes'] as $cls) {
        if (!empty($cls['id']) && $cls['id'] === $class_id) {
            $target_name = $cls['name'] ?? '';
            break;
        }
    }

    if ($target_name === '') {
        wp_send_json_error(['message' => 'Clase no encontrada en dnd-classes.json']);
    }

    // 2) Reunimos subclases de TODAS las clases con ese mismo nombre
    $subclasses     = [];
    $seen_sub_ids   = [];

    foreach ($data['classes'] as $cls) {
        if (($cls['name'] ?? '') !== $target_name) {
            continue;
        }

        if (empty($cls['subclasses']) || !is_array($cls['subclasses'])) {
            continue;
        }

        foreach ($cls['subclasses'] as $sc) {
            $sc_id = $sc['id'] ?? '';
            if ($sc_id && isset($seen_sub_ids[$sc_id])) {
                continue; // evitar duplicados exactos
            }
            if ($sc_id) {
                $seen_sub_ids[$sc_id] = true;
            }

            // Etiqueta tipo "Life (PHB)" usando shortName + source
            $label = $sc['shortName'] ?? $sc['name'] ?? '';
            if (!empty($sc['source'])) {
                $label .= ' (' . $sc['source'] . ')';
            }

            $subclasses[] = [
                'id'      => $sc_id,
                'name'    => $label,
                'source'  => $sc['source']  ?? '',
                'edition' => $sc['edition'] ?? '',
            ];
        }
    }

    wp_send_json_success(['subclasses' => $subclasses]);
}



add_action('wp_ajax_drak_dnd5_get_subclasses', 'drak_dnd5_get_subclasses');
add_action('wp_ajax_nopriv_drak_dnd5_get_subclasses', 'drak_dnd5_get_subclasses');

/**
 * (Opcional) AJAX: lista de armas
 * Usa una categoría de equipo de la API: /api/equipment-categories/{index}
 * El índice exacto depende de lo que quieras (por ejemplo "weapon",
 * "simple-weapons", "martial-weapons", etc.).
 */
function drak_dnd5_get_weapons() {
    $category_index = drak_get_post_value('category', 'weapon');

    $url      = 'https://www.dnd5eapi.co/api/equipment-categories/' . rawurlencode($category_index);
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error de conexión con la API de D&D 5e']);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        wp_send_json_error(['message' => 'La API devolvió un código ' . $code]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $weapons = [];

    // La respuesta de equipment-categories trae normalmente un array 'equipment'
    if (!empty($body['equipment']) && is_array($body['equipment'])) {
        foreach ($body['equipment'] as $item) {
            $weapons[] = [
                'index' => $item['index'] ?? '',
                'name'  => $item['name']  ?? '',
            ];
        }
    }

    wp_send_json_success(['weapons' => $weapons]);
}
add_action('wp_ajax_drak_dnd5_get_weapons', 'drak_dnd5_get_weapons');
add_action('wp_ajax_nopriv_drak_dnd5_get_weapons', 'drak_dnd5_get_weapons');

function drak_spell_matches_classes( $spell, $filters ) {
    if ( empty( $filters ) ) {
        return true;
    }
    if ( empty( $spell['classes'] ) || ! is_array( $spell['classes'] ) ) {
        return false;
    }
    foreach ( $spell['classes'] as $class ) {
        $id = $class['id'] ?? '';
        if ( $id && in_array( $id, $filters, true ) ) {
            return true;
        }
    }
    return false;
}

function drak_collect_spell_text( $entry, &$chunks ) {
    if ( $entry === null ) {
        return;
    }
    if ( is_string( $entry ) ) {
        $chunks[] = $entry;
        return;
    }
    if ( is_array( $entry ) ) {
        foreach ( $entry as $item ) {
            drak_collect_spell_text( $item, $chunks );
        }
    } elseif ( is_object( $entry ) ) {
        foreach ( get_object_vars( $entry ) as $value ) {
            drak_collect_spell_text( $value, $chunks );
        }
    }
}

function drak_build_spell_preview( $spell ) {
    $chunks = [];
    if ( ! empty( $spell['entries'] ) ) {
        drak_collect_spell_text( $spell['entries'], $chunks );
    }
    $text = trim( implode( ' ', $chunks ) );
    $text = preg_replace( '/\s+/', ' ', $text );
    if ( '' === $text ) {
        return '';
    }
    if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 220 ) {
        return mb_substr( $text, 0, 217 ) . '…';
    }
    return strlen( $text ) > 220 ? substr( $text, 0, 217 ) . '…' : $text;
}

function drak_build_spell_paragraphs( $spell ) {
    if ( empty( $spell['entries'] ) ) {
        return [];
    }
    $chunks = [];
    drak_collect_spell_text( $spell['entries'], $chunks );
    if ( empty( $chunks ) ) {
        return [];
    }
    $text = trim( implode( "\n", $chunks ) );
    if ( '' === $text ) {
        return [];
    }
    $parts = preg_split( '/\n+/u', $text );
    $paragraphs = [];
    foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( '' !== $part ) {
            $paragraphs[] = $part;
        }
    }
    return $paragraphs;
}

function drak_dnd5_search_spells() {
    if ( ! isset( $_POST['q'] ) && empty( $_POST['classes'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros faltantes.' ], 400 );
    }

    $query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 25;
    $limit = max( 1, min( 50, $limit ) );

    $raw_classes = $_POST['classes'] ?? [];
    if ( is_string( $raw_classes ) ) {
        $raw_classes = [ $raw_classes ];
    }
    $class_filters = array_filter( array_map( 'sanitize_text_field', (array) $raw_classes ) );

    $needle = trim( strtolower( remove_accents( $query ) ) );
    $matches = [];
    $total = 0;
    foreach ( drak_get_local_dnd_spells() as $spell ) {
        $name = $spell['name'] ?? '';
        if ( $needle !== '' ) {
            $haystack = strtolower( remove_accents( $name ) );
            if ( strpos( $haystack, $needle ) === false ) {
                continue;
            }
        } elseif ( empty( $class_filters ) ) {
            continue;
        }

        if ( ! drak_spell_matches_classes( $spell, $class_filters ) ) {
            continue;
        }

        $total++;
        if ( count( $matches ) >= $limit ) {
            continue;
        }

        $matches[] = [
            'id'       => $spell['id'] ?? '',
            'name'     => $name,
            'level'    => intval( $spell['level'] ?? 0 ),
            'school'   => $spell['school'] ?? '',
            'source'   => $spell['source'] ?? '',
            'classes'  => array_map( static function ( $cls ) {
                return [
                    'id'   => $cls['id'] ?? '',
                    'name' => $cls['name'] ?? '',
                ];
            }, $spell['classes'] ?? [] ),
            'preview'    => drak_build_spell_preview( $spell ),
            'paragraphs' => drak_build_spell_paragraphs( $spell ),
            'entries'    => $spell['entries'] ?? [],
        ];
    }

    wp_send_json_success(
        [
            'spells' => $matches,
            'total'  => $total,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_search_spells', 'drak_dnd5_search_spells' );
add_action( 'wp_ajax_nopriv_drak_dnd5_search_spells', 'drak_dnd5_search_spells' );

/**
 * Carga el JSON local de razas
 */
function drak_get_local_dnd_races_data() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $path = drak_locate_theme_data_file( 'dnd-races.json' );
    if (!$path) {
        return null;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $cached = $data;
    return $cached;
}

/**
 * AJAX: lista de razas (desde dnd-races.json)
 */
function drak_dnd5_get_races() {
    $data = drak_get_local_dnd_races_data();

    if (!$data || empty($data['races']) || !is_array($data['races'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-races.json']);
    }

    $out  = [];
    $seen = [];

    foreach ($data['races'] as $race) {
        $name_es = $race['name']['es'] ?? '';
        $name_en = $race['name']['en'] ?? '';

        if ($name_es === '' && $name_en === '') {
            continue;
        }

        // Evitar duplicados por nombre en español
        $slug = sanitize_title($name_es ?: $name_en);
        if (isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;

        $out[] = [
            'id'      => $race['id'] ?? '',
            'name'    => $name_es ?: $name_en, // mostramos siempre ES si existe
            'name_en' => $name_en,
            'source'  => $race['source'] ?? '',
        ];
    }

    usort($out, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    wp_send_json_success(['races' => $out]);
}
add_action('wp_ajax_drak_dnd5_get_races', 'drak_dnd5_get_races');
add_action('wp_ajax_nopriv_drak_dnd5_get_races', 'drak_dnd5_get_races');

/**
 * Carga un JSON de /data y devuelve el array del key indicado.
 */
function drak_get_local_dnd_list( $filename, $root_key ) {
    static $cache = [];

    if ( isset( $cache[ $filename ] ) ) {
        return $cache[ $filename ];
    }

    $path = drak_locate_theme_data_file( $filename );
    if ( ! $path ) {
        return [];
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );

    if ( ! is_array( $data ) || ! isset( $data[ $root_key ] ) || ! is_array( $data[ $root_key ] ) ) {
        return [];
    }

    $cache[ $filename ] = $data[ $root_key ];
    return $cache[ $filename ];
}

function drak_get_local_dnd_actions() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-actions.json' );
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['actions'] ) ) {
        $cache = [];
        return $cache;
    }

    $cache = $data['actions'];
    return $cache;
}

/**
 * Lee el JSON con los rasgos de clase/subclase.
 */
function drak_get_local_dnd_class_features_data() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-class-features.json' );
    if ( ! $path ) {
        $cache = [
            'classFeatures'    => [],
            'subclassFeatures' => [],
        ];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) ) {
        $cache = [
            'classFeatures'    => [],
            'subclassFeatures' => [],
        ];
        return $cache;
    }

    $defaults = [
        'classFeatures'    => [],
        'subclassFeatures' => [],
    ];

    $cache = array_merge( $defaults, $data );
    return $cache;
}

/**
 * Índices rápidos para clases y subclases.
 */
function drak_get_local_dnd_class_lookup() {
    static $lookup = null;
    if ( $lookup !== null ) {
        return $lookup;
    }

    $data = drak_get_local_dnd_classes_data();
    $result = [
        'classes'    => [],
        'subclasses' => [],
    ];

    if ( ! $data || empty( $data['classes'] ) ) {
        $lookup = $result;
        return $lookup;
    }

    foreach ( $data['classes'] as $class ) {
        $class_id = $class['id'] ?? '';
        if ( ! $class_id ) {
            continue;
        }

        $result['classes'][ $class_id ] = $class;

        if ( empty( $class['subclasses'] ) || ! is_array( $class['subclasses'] ) ) {
            continue;
        }

        foreach ( $class['subclasses'] as $sub ) {
            $sub_id = $sub['id'] ?? '';
            if ( ! $sub_id ) {
                continue;
            }

            $result['subclasses'][ $sub_id ] = [
                'class_id' => $class_id,
                'data'     => $sub,
            ];
        }
    }

    $lookup = $result;
    return $lookup;
}

function drak_get_local_dnd_backgrounds() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-backgrounds.json' );
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['backgrounds'] ) ) {
        $cache = [];
        return $cache;
    }

    $cache = $data['backgrounds'];
    return $cache;
}

function drak_get_local_dnd_spells() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-spells.json' );
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['spells'] ) ) {
        $cache = [];
        return $cache;
    }

    $cache = $data['spells'];
    return $cache;
}

function drak_get_spellcasting_classes() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $data = drak_get_local_dnd_classes_data();
    if ( ! $data || empty( $data['classes'] ) || ! is_array( $data['classes'] ) ) {
        $cache = [];
        return $cache;
    }

    $unique = [];
    foreach ( $data['classes'] as $class ) {
        $class_id = $class['id'] ?? '';
        if ( ! $class_id ) {
            continue;
        }
        $details = drak_get_class_detail_entry( $class_id );
        if ( empty( $details['spellcastingAbility'] ) ) {
            continue;
        }

        $slug = sanitize_title( $class['name'] ?? $class_id );
        if ( isset( $unique[ $slug ] ) ) {
            continue;
        }

        $unique[ $slug ] = [
            'id'    => $class_id,
            'name'  => $class['name'] ?? $class_id,
            'short' => $class['shortName'] ?? ( $class['name'] ?? $class_id ),
        ];
    }

    $cache = array_values( $unique );
    return $cache;
}

function drak_get_apothecary_class_ids() {
    return [ 'apothecary-scgtd-drakkenheim' ];
}

function drak_is_apothecary_class( $class_id ) {
    if ( ! $class_id ) {
        return false;
    }
    return in_array( $class_id, drak_get_apothecary_class_ids(), true );
}

function drak_esoteric_theory_id( $name ) {
    $base = sanitize_title( $name );
    if ( ! $base ) {
        $base = 'theory-' . substr( md5( (string) $name ), 0, 8 );
    }
    return 'apothecary-theory-' . $base;
}

function drak_get_apothecary_theories_catalog() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }

    $cache = [];
    $path  = drak_locate_theme_data_file( 'esotherics.json' );
    if ( ! $path ) {
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['classFeature'] ) ) {
        return $cache;
    }

    foreach ( $data['classFeature'] as $entry ) {
        if ( empty( $entry['name'] ) ) {
            continue;
        }
        $id = isset( $entry['id'] ) && $entry['id'] !== '' ? $entry['id'] : drak_esoteric_theory_id( $entry['name'] );
        if ( isset( $cache[ $id ] ) ) {
            $id .= '-' . substr( md5( $entry['name'] . wp_rand() ), 0, 4 );
        }
        $entry['id']    = $id;
        $entry['level'] = isset( $entry['level'] ) ? intval( $entry['level'] ) : 0;
        $cache[ $id ]   = $entry;
    }

    return $cache;
}

function drak_parse_apothecary_theory_ids( $raw ) {
    if ( is_array( $raw ) ) {
        return array_values( array_filter( array_map( 'strval', $raw ) ) );
    }

    if ( ! is_string( $raw ) || $raw === '' ) {
        return [];
    }

    $raw = trim( $raw );
    if ( $raw === '' ) {
        return [];
    }

    if ( $raw[0] === '[' ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            return array_values( array_filter( array_map( 'strval', $decoded ) ) );
        }
    }

    $parts = array_map( 'trim', explode( ',', $raw ) );
    return array_values( array_filter( array_map( 'strval', $parts ) ) );
}

function drak_filter_apothecary_theory_selection( $ids, $class_id, $level ) {
    if ( ! drak_is_apothecary_class( $class_id ) ) {
        return [];
    }

    $level    = max( 0, intval( $level ) );
    $catalog  = drak_get_apothecary_theories_catalog();
    $selected = [];

    foreach ( $ids as $id ) {
        if ( ! isset( $catalog[ $id ] ) ) {
            continue;
        }
        $required_level = intval( $catalog[ $id ]['level'] ?? 0 );
        if ( $required_level > $level ) {
            continue;
        }
        $selected[ $id ] = true;
    }

    return array_keys( $selected );
}

function drak_save_apothecary_theory_selection( $post_id, $ids ) {
    $ids = array_values( $ids );
    if ( empty( $ids ) ) {
        delete_post_meta( $post_id, 'apothecary_theories' );
        return;
    }

    update_post_meta( $post_id, 'apothecary_theories', wp_json_encode( $ids ) );
}

function drak_get_character_apothecary_theories( $post_id ) {
    if ( ! $post_id ) {
        return [];
    }
    $raw = get_post_meta( $post_id, 'apothecary_theories', true );
    return drak_parse_apothecary_theory_ids( $raw );
}

function drak_format_apothecary_theories_display( $ids ) {
    $catalog = drak_get_apothecary_theories_catalog();
    $names   = [];
    foreach ( $ids as $id ) {
        if ( isset( $catalog[ $id ]['name'] ) ) {
            $names[] = $catalog[ $id ]['name'];
        }
    }
    return implode( ', ', $names );
}

function drak_expand_apothecary_theories( $ids ) {
    $catalog = drak_get_apothecary_theories_catalog();
    $out     = [];
    foreach ( $ids as $id ) {
        if ( ! isset( $catalog[ $id ] ) ) {
            continue;
        }
        $entry = $catalog[ $id ];
        $out[] = [
            'id'      => $entry['id'],
            'name'    => $entry['name'] ?? $entry['id'],
            'source'  => $entry['source'] ?? '',
            'page'    => $entry['page'] ?? '',
            'level'   => intval( $entry['level'] ?? 0 ),
            'entries' => $entry['entries'] ?? [],
        ];
    }
    return $out;
}

function drak_sanitize_apothecary_theory_submission( $raw_value, $class_id = '', $level = 0 ) {
    $ids = drak_parse_apothecary_theory_ids( $raw_value );
    if ( ! $class_id ) {
        return $ids;
    }
    return drak_filter_apothecary_theory_selection( $ids, $class_id, $level );
}

/**
 * Devuelve listas de armas, armaduras, herramientas e idiomas para el modal.
 */
function drak_dnd5_get_proficiencies() {
    $weapons   = drak_get_local_dnd_list( 'dnd-weapons.json',   'weapons' );
    $armors    = drak_get_local_dnd_list( 'dnd-armors.json',    'armors' );
    $tools     = drak_get_local_dnd_list( 'dnd-tools.json',     'tools' );
    $languages = drak_get_local_dnd_list( 'dnd-languages.json', 'languages' );

    // Nos quedamos solo con id + name (preferimos español si existe)
    $map = function( $item ) {
        $raw_name = isset( $item['name'] ) ? $item['name'] : '';

        if ( is_array( $raw_name ) ) {
            // Estructura tipo: "name": { "en": "...", "es": "..." }
            $name = $raw_name['es'] ?? $raw_name['en'] ?? '';
        } else {
            $name = $raw_name;
        }

        return [
            'id'   => isset( $item['id'] ) ? $item['id'] : '',
            'name' => $name,
        ];
    };


    $data = [
        'weapons'   => array_map( $map, $weapons ),
        'armors'    => array_map( $map, $armors ),
        'tools'     => array_map( $map, $tools ),
        'languages' => array_map( $map, $languages ),
    ];

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_drak_dnd5_get_proficiencies',        'drak_dnd5_get_proficiencies' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_proficiencies', 'drak_dnd5_get_proficiencies' );

function drak_dnd5_get_actions() {
    $actions = drak_get_local_dnd_actions();
    wp_send_json_success( [ 'actions' => $actions ] );
}
add_action( 'wp_ajax_drak_dnd5_get_actions', 'drak_dnd5_get_actions' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_actions', 'drak_dnd5_get_actions' );

function drak_dnd5_get_esoteric_theories() {
    $catalog = drak_get_apothecary_theories_catalog();
    wp_send_json_success(
        [
            'theories' => array_values( $catalog ),
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_get_esoteric_theories', 'drak_dnd5_get_esoteric_theories' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_esoteric_theories', 'drak_dnd5_get_esoteric_theories' );

function drak_dnd5_get_backgrounds() {
    $backgrounds = drak_get_local_dnd_backgrounds();
    wp_send_json_success( [ 'backgrounds' => $backgrounds ] );
}
add_action( 'wp_ajax_drak_dnd5_get_backgrounds', 'drak_dnd5_get_backgrounds' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_backgrounds', 'drak_dnd5_get_backgrounds' );

function drak_dnd5_get_class_reference() {
    $class_id    = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $subclass_id = isset( $_POST['subclass_id'] ) ? sanitize_text_field( wp_unslash( $_POST['subclass_id'] ) ) : '';
    $raw_theories = isset( $_POST['apothecary_theories'] ) ? wp_unslash( $_POST['apothecary_theories'] ) : '';
    $selected_theories = drak_parse_apothecary_theory_ids( $raw_theories );

    if ( ! $class_id ) {
        wp_send_json_error( [ 'message' => 'Falta el parámetro class_id.' ], 400 );
    }

    $reference = drak_get_class_reference_payload( $class_id, $subclass_id, $selected_theories );
    if ( ! $reference ) {
        $debug = drak_get_class_reference_error();
        drak_set_class_reference_error( sprintf( 'drak_get_class_reference_payload devolvió vacío para class_id=%s subclass_id=%s', $class_id, $subclass_id ) );
        wp_send_json_error(
            [
                'message' => 'No se pudo generar la referencia de clase.',
                'debug'   => $debug,
            ],
            404
        );
    }

    wp_send_json_success( [ 'reference' => $reference ] );
}
add_action( 'wp_ajax_drak_dnd5_get_class_reference', 'drak_dnd5_get_class_reference' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_class_reference', 'drak_dnd5_get_class_reference' );

function drak_dnd5_get_spells() {
    $class_id = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $spells   = drak_get_local_dnd_spells();

    if ( ! $class_id ) {
        wp_send_json_success( [ 'spells' => [] ] );
    }

    $filtered = array_values( array_filter( $spells, function ( $spell ) use ( $class_id ) {
        if ( empty( $spell['classes'] ) || ! is_array( $spell['classes'] ) ) {
            return false;
        }

        foreach ( $spell['classes'] as $cls ) {
            if ( isset( $cls['id'] ) && $cls['id'] === $class_id ) {
                return true;
            }
        }

        return false;
    } ) );

    wp_send_json_success( [ 'spells' => $filtered ] );
}
add_action( 'wp_ajax_drak_dnd5_get_spells', 'drak_dnd5_get_spells' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_spells', 'drak_dnd5_get_spells' );

function drak_dnd5_activate_transformation() {
    if ( ! isset( $_POST['post_id'], $_POST['slot_level'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id    = intval( $_POST['post_id'] );
    $slot_level = intval( $_POST['slot_level'] );
    $nonce      = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( $slot_level <= 0 ) {
        wp_send_json_error( [ 'message' => 'Nivel de slot inválido.' ], 400 );
    }

    if ( ! wp_verify_nonce( $nonce, 'grimorio_transformation_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $current_state = drak_grimorio_get_transformation_state( $post_id );
    if ( ! empty( $current_state['active'] ) ) {
        wp_send_json_error( [ 'message' => 'La transformación ya está activa.' ], 400 );
    }

    $slots_used = drak_grimorio_get_slots( $post_id );
    $nivel      = intval( get_field( 'nivel', $post_id ) );
    $clase_id   = get_field( 'clase', $post_id );

    $slot_row = drak_get_class_spell_slots_for_level( $clase_id, $nivel );
    if ( empty( $slot_row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $slot_row = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }

    $slot_cap = intval( $slot_row[ $slot_level ] ?? 0 );
    $used     = intval( $slots_used[ $slot_level ] ?? 0 );

    if ( $slot_cap <= 0 ) {
        wp_send_json_error( [ 'message' => 'No tienes espacios de ese nivel.' ], 400 );
    }

    if ( $used >= $slot_cap ) {
        wp_send_json_error( [ 'message' => 'No quedan espacios disponibles en ese nivel.' ], 400 );
    }

    $slots_used[ $slot_level ] = $used + 1;
    $clean_slots               = drak_grimorio_save_slots( $post_id, $slots_used );

    $saved_state = drak_grimorio_save_transformation_state(
        $post_id,
        [
            'active'     => true,
            'slot_level' => $slot_level,
            'started_at' => time(),
        ]
    );

    wp_send_json_success(
        [
            'slots_used'     => $clean_slots,
            'transformation' => $saved_state,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_activate_transformation', 'drak_dnd5_activate_transformation' );

function drak_dnd5_finish_transformation() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_transformation_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $state = drak_grimorio_save_transformation_state(
        $post_id,
        [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ]
    );

    wp_send_json_success( [ 'transformation' => $state ] );
}
add_action( 'wp_ajax_drak_dnd5_finish_transformation', 'drak_dnd5_finish_transformation' );

function drak_dnd5_save_spell_slots() {
    if ( ! isset( $_POST['post_id'], $_POST['level'], $_POST['value'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $level   = intval( $_POST['level'] );
    $value   = intval( $_POST['value'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_slots_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $stored           = drak_grimorio_get_slots( $post_id );
    $stored[ $level ] = max( 0, $value );
    $clean            = drak_grimorio_save_slots( $post_id, $stored );

    wp_send_json_success( [ 'slots' => $clean ] );
}
add_action( 'wp_ajax_drak_dnd5_save_spell_slots', 'drak_dnd5_save_spell_slots' );

function drak_dnd5_save_prepared_spells() {
    if ( ! isset( $_POST['post_id'], $_POST['prepared'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_prepared_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $prepared_raw = wp_unslash( $_POST['prepared'] );
    $decoded      = json_decode( $prepared_raw, true );
    if ( ! is_array( $decoded ) ) {
        wp_send_json_error( [ 'message' => 'Formato inválido.' ], 400 );
    }
    $clean = drak_grimorio_save_prepared( $post_id, $decoded );

    wp_send_json_success( [ 'prepared' => $clean ] );
}
add_action( 'wp_ajax_drak_dnd5_save_prepared_spells', 'drak_dnd5_save_prepared_spells' );

function drak_dnd5_save_concentration_state() {
    if ( ! isset( $_POST['post_id'], $_POST['state'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_concentration_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $state_raw = json_decode( wp_unslash( $_POST['state'] ), true );
    if ( ! is_array( $state_raw ) ) {
        delete_post_meta( $post_id, 'grimorio_concentration_state' );
        wp_send_json_success( [ 'concentration' => null ] );
    }

    $level    = isset( $state_raw['level'] ) ? intval( $state_raw['level'] ) : null;
    $spell    = isset( $state_raw['spell'] ) ? sanitize_text_field( $state_raw['spell'] ) : '';
    $spell_id = isset( $state_raw['spell_id'] ) ? sanitize_text_field( $state_raw['spell_id'] ) : '';

    if ( null === $level || ( '' === $spell && '' === $spell_id ) ) {
        delete_post_meta( $post_id, 'grimorio_concentration_state' );
        wp_send_json_success( [ 'concentration' => null ] );
    }

    $payload = [
        'level'    => $level,
        'spell'    => $spell,
        'spell_id' => $spell_id,
    ];

    update_post_meta( $post_id, 'grimorio_concentration_state', $payload );

    wp_send_json_success( [ 'concentration' => $payload ] );
}
add_action( 'wp_ajax_drak_dnd5_save_concentration_state', 'drak_dnd5_save_concentration_state' );

/**
 * AJAX: rasgos combinados (raza + clase + subclase).
 */
function drak_dnd5_get_feature_traits() {
    $class_id    = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $subclass_id = isset( $_POST['subclass_id'] ) ? sanitize_text_field( wp_unslash( $_POST['subclass_id'] ) ) : '';
    $race_id     = isset( $_POST['race_id'] ) ? sanitize_text_field( wp_unslash( $_POST['race_id'] ) ) : '';
    $raw_theories = isset( $_POST['apothecary_theories'] ) ? wp_unslash( $_POST['apothecary_theories'] ) : '';
    $selected_theories = drak_parse_apothecary_theory_ids( $raw_theories );
    if ( ! drak_is_apothecary_class( $class_id ) ) {
        $selected_theories = [];
    }

    $class_lookup     = drak_get_local_dnd_class_lookup();
    $features_data    = drak_get_local_dnd_class_features_data();
    $race_data        = drak_get_local_dnd_races_data();

    $class_entry   = $class_lookup['classes'][ $class_id ] ?? null;
    $subclass_meta = $class_lookup['subclasses'][ $subclass_id ] ?? null;
    if ( $subclass_meta && $class_id && $subclass_meta['class_id'] !== $class_id ) {
        $parent_class_id = $subclass_meta['class_id'];
        $current_name    = $class_entry['name'] ?? '';
        $parent_entry    = $class_lookup['classes'][ $parent_class_id ] ?? null;
        $parent_name     = $parent_entry['name'] ?? '';

        // Algunas clases existen en varias ediciones, así que permitimos la
        // subclase si el nombre base coincide; de lo contrario se descarta.
        if ( ! $current_name || ! $parent_name || $current_name !== $parent_name ) {
            $subclass_meta = null; // No pertenece a la clase seleccionada.
        }
    }

    $race_entry = null;
    if ( $race_id && $race_data && ! empty( $race_data['races'] ) ) {
        foreach ( $race_data['races'] as $race ) {
            if ( isset( $race['id'] ) && $race['id'] === $race_id ) {
                $race_entry = $race;
                break;
            }
        }
    }

    $race_payload = null;
    if ( $race_entry ) {
        $race_payload = [
            'id'      => $race_entry['id'],
            'name'    => $race_entry['name']['es'] ?? $race_entry['name']['en'] ?? $race_entry['id'],
            'source'  => $race_entry['source'] ?? '',
            'entries' => $race_entry['entries'] ?? ( $race_entry['entries_en'] ?? [] ),
        ];
    }

    $class_payload = [
        'id'       => $class_id,
        'name'     => $class_entry['name'] ?? '',
        'source'   => $class_entry['source'] ?? '',
        'features' => $features_data['classFeatures'][ $class_id ] ?? [],
    ];

    $subclass_payload = null;
    if ( $subclass_meta ) {
        $sub_data = $subclass_meta['data'];
        $subclass_payload = [
            'id'       => $subclass_id,
            'name'     => $sub_data['name'] ?? '',
            'source'   => $sub_data['source'] ?? '',
            'features' => $features_data['subclassFeatures'][ $subclass_id ] ?? [],
        ];
    }

    wp_send_json_success([
        'race'     => $race_payload,
        'class'    => $class_payload,
        'subclass' => $subclass_payload,
        'esotericTheories' => drak_expand_apothecary_theories( $selected_theories ),
    ]);
}
add_action( 'wp_ajax_drak_dnd5_get_feature_traits', 'drak_dnd5_get_feature_traits' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_feature_traits', 'drak_dnd5_get_feature_traits' );
function drak_get_admin_ajax_url() {
    static $cached = null;

    if ( $cached !== null ) {
        return $cached;
    }

    $cached = site_url( '/wp-admin/admin-ajax.php' );

    return $cached;
}
