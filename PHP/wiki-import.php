<?php
/**
 * Importador de wiki desde texto estructurado.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parsea el texto del importador en secciones y entradas.
 *
 * @return array [
 *   'LUGARES' => [ ['name' => '', 'content' => ''], ... ],
 *   'NPCS' => ...
 * ]
 */
function drak_wiki_import_parse_text( $text ) {
    $lines    = preg_split( "/\r?\n/", (string) $text );
    $section  = '';
    $entries  = [];
    $current  = null;
    $allow_new = false;

    $section_map = [
        'LUGARES'    => 'LUGARES',
        'NPCS'       => 'NPCS',
        'NPC'        => 'NPCS',
        'FACCIONES'  => 'FACCIONES',
        'FACCION'    => 'FACCIONES',
        'PERSONAJES' => 'PERSONAJES',
        'PERSONAJE'  => 'PERSONAJES',
    ];

    foreach ( $lines as $line ) {
        $trim = trim( $line );
        if ( '' === $trim ) {
            if ( $current ) {
                $entries[ $section ][] = $current;
                $current               = null;
            }
            $allow_new = true;
            continue;
        }

        $upper = strtoupper( $trim );
        if ( isset( $section_map[ $upper ] ) ) {
            if ( $current ) {
                $entries[ $section ][] = $current;
                $current               = null;
            }
            $section = $section_map[ $upper ];
            if ( ! isset( $entries[ $section ] ) ) {
                $entries[ $section ] = [];
            }
            $allow_new = true;
            continue;
        }

        // Entrada "Nombre: contenido".
        if ( $allow_new && preg_match( '/^([^:]+):\s*(.*)$/', $line, $m ) ) {
            if ( $current ) {
                $entries[ $section ][] = $current;
            }
            $name     = trim( $m[1] );
            $content  = trim( $m[2] );
            $current  = [
                'name'    => $name,
                'content' => $content,
            ];
            $allow_new = false;
            continue;
        }

        if ( $current ) {
            $current['content'] .= "\n" . $line;
        }
        $allow_new = false;
    }

    if ( $current ) {
        $entries[ $section ][] = $current;
    }

    return $entries;
}

/**
 * Construye mapa de nombres -> url para auto-link.
 */
function drak_wiki_import_build_link_map() {
    $types = [
        'personaje_wiki' => 'personaje',
        'personaje'      => 'personaje',
        'npc'            => 'post',
        'lugar'          => 'post',
        'faccion'        => 'post',
    ];
    $link_map = [];
    $seen     = [];
    foreach ( array_keys( $types ) as $type ) {
        $posts = get_posts(
            [
                'post_type'      => $type,
                'post_status'    => [ 'publish', 'private' ],
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]
        );
        foreach ( $posts as $pid ) {
            $title = get_the_title( $pid );
            $slug  = get_post_field( 'post_name', $pid );
            if ( 'personaje_wiki' === $type ) {
                $url = home_url( '/personaje-wiki/' . $slug . '/' );
            } elseif ( 'personaje' === $type ) {
                $url = home_url( '/hoja-personaje/' . $slug . '/' );
            } else {
                $url = get_permalink( $pid );
            }
            if ( ! $title || ! $url ) {
                continue;
            }
            $k = strtolower( $title );
            if ( isset( $seen[ $k ] ) ) {
                continue;
            }
            $seen[ $k ] = true;
            $link_map[] = [
                'label' => $title,
                'slug'  => $slug,
                'url'   => $url,
            ];
        }
    }

    // Ordenar por longitud descendente para evitar que nombres cortos rompan los largos.
    usort(
        $link_map,
        function( $a, $b ) {
            return strlen( $b['label'] ) <=> strlen( $a['label'] );
        }
    );

    return $link_map;
}

function drak_wiki_import_find_url_for_label( $label, $link_map ) {
    foreach ( $link_map as $item ) {
        if ( 0 === strcasecmp( $item['label'], $label ) ) {
            return $item['url'];
        }
    }
    return '';
}

/**
 * Enlaza nombres conocidos en un texto.
 */
function drak_wiki_import_autolink( $text, $link_map ) {
    if ( ! $text || ! $link_map ) {
        return $text;
    }
    foreach ( $link_map as $item ) {
        $label = $item['label'];
        $url   = $item['url'];
        if ( ! $label || ! $url ) {
            continue;
        }
        $pattern = '/\b(' . preg_quote( $label, '/' ) . ')\b/iu';
        $count   = 0;
        $text    = preg_replace_callback(
            $pattern,
            function( $m ) use ( $url, &$count ) {
                if ( $count >= 1 ) {
                    return $m[0];
                }
                $count++;
                return '<a href="' . esc_url( $url ) . '">' . esc_html( $m[1] ) . '</a>';
            },
            $text
        );
    }
    // Excepción explícita para Wade Colton si no fue enlazado.
    $wade_url = drak_wiki_import_find_url_for_label( 'Wade Colton', $link_map );
    if ( ! $wade_url ) {
        $wade_url = home_url( '/personaje-wiki/wade-colton/' );
    }
    $text = preg_replace(
        '/\bWade\s+Colton\b/iu',
        '<a href="' . esc_url( $wade_url ) . '">Wade Colton</a>',
        $text,
        1
    );
    // Limpia comillas/guillemets erróneos en href.
    $text = preg_replace( '/href="([^">]+)[»”]/u', 'href="$1"', $text );
    $text = preg_replace( "/href='([^'>]+)[»”]/u", "href='$1'", $text );
    return $text;
}
/**
 * Añade bloque de contenido a un CPT si no existe ya (por hash).
 */
function drak_wiki_import_append_block( $post_id, $content, $session_title ) {
    $hash    = md5( $content );
    $post    = get_post( $post_id );
    $current = $post ? $post->post_content : '';
    if ( strpos( $current, $hash ) !== false ) {
        return false;
    }
    $block = '<div class="wiki-import-block" data-import-hash="' . esc_attr( $hash ) . '" data-session="' . esc_attr( $session_title ) . '">';
    $block .= wp_kses_post( wpautop( $content ) );
    $block .= '</div>';
    $new_content = trim( $current ) ? $current . "\n\n" . $block : $block;
    wp_update_post(
        [
            'ID'           => $post_id,
            'post_content' => wp_slash( $new_content ),
        ]
    );
    return true;
}

/**
 * Procesa las entradas de lugares/npc/faccion.
 */
function drak_wiki_import_process_wiki_posts( $campaign_id, $entries, &$report, $link_map ) {
    $map = [
        'LUGARES'   => 'lugar',
        'NPCS'      => 'npc',
        'FACCIONES' => 'faccion',
    ];
    foreach ( $map as $section => $post_type ) {
        if ( empty( $entries[ $section ] ) ) {
            continue;
        }
        foreach ( $entries[ $section ] as $entry ) {
            $name = trim( $entry['name'] ?? '' );
            $body = trim( $entry['content'] ?? '' );
            if ( '' === $name || '' === $body ) {
                continue;
            }
            $slug = sanitize_title( $name );
            $post = get_page_by_path( $slug, OBJECT, $post_type );
            if ( ! $post ) {
                $report['missing'][] = [ 'section' => $section, 'name' => $name ];
                continue;
            }
            $body     = drak_wiki_import_autolink( $body, $link_map );
            $updated = drak_wiki_import_append_block( $post->ID, $body, $report['session_title'] );
            if ( $campaign_id ) {
                if ( function_exists( 'update_field' ) ) {
                    update_field( 'campaign', $campaign_id, $post->ID );
                } else {
                    update_post_meta( $post->ID, 'campaign', $campaign_id );
                }
            }
            $report['updated'][] = [
                'section' => $section,
                'name'    => $name,
                'action'  => $updated ? 'appended' : 'skipped-duplicate',
                'url'     => get_permalink( $post->ID ),
            ];
        }
    }
}

/**
 * Procesa personajes: crea entrada de aventura.
 */
function drak_wiki_import_process_characters( $campaign_id, $entries, &$report, $link_map ) {
    if ( empty( $entries['PERSONAJES'] ) ) {
        return;
    }
    foreach ( $entries['PERSONAJES'] as $entry ) {
        $name = trim( $entry['name'] ?? '' );
        $body = trim( $entry['content'] ?? '' );
        if ( '' === $name || '' === $body ) {
            continue;
        }
        $slug       = sanitize_title( $name );
        $personaje  = get_page_by_path( $slug, OBJECT, 'personaje_wiki' );
        if ( ! $personaje ) {
            $report['missing'][] = [ 'section' => 'PERSONAJES', 'name' => $name ];
            continue;
        }
        $body  = drak_wiki_import_autolink( $body, $link_map );
        $title = $report['session_title'] ? $report['session_title'] . ' - ' . $name : ( 'Actualizacion wiki - ' . $name );
        $entry_id = wp_insert_post(
            [
                'post_type'    => 'personaje_wiki_entry',
                'post_status'  => 'publish',
                'post_title'   => wp_strip_all_tags( $title ),
                'post_name'    => sanitize_title( $title . '-' . $slug ),
                'post_content' => wp_kses_post( wpautop( $body ) ),
                'post_author'  => get_current_user_id() ?: 0,
            ]
        );
        if ( is_wp_error( $entry_id ) ) {
            $report['errors'][] = [ 'section' => 'PERSONAJES', 'name' => $name, 'error' => $entry_id->get_error_message() ];
            continue;
        }
        if ( function_exists( 'update_field' ) ) {
            update_field( 'parent_personaje_wiki', $personaje->ID, $entry_id );
            update_field( 'section', 'aventura', $entry_id );
            if ( $campaign_id ) {
                update_field( 'campaign', $campaign_id, $entry_id );
            }
        } else {
            update_post_meta( $entry_id, 'parent_personaje_wiki', $personaje->ID );
            update_post_meta( $entry_id, 'section', 'aventura' );
            if ( $campaign_id ) {
                update_post_meta( $entry_id, 'campaign', $campaign_id );
            }
        }
        if ( $campaign_id ) {
            update_post_meta( $entry_id, 'pw_entry_campaign', $campaign_id );
        }
        if ( $report['session_title'] ) {
            update_post_meta( $entry_id, 'session_title', $report['session_title'] );
        }
        if ( $report['session_date'] ) {
            update_post_meta( $entry_id, 'session_date', $report['session_date'] );
        }
        $report['updated'][] = [
            'section' => 'PERSONAJES',
            'name'    => $name,
            'action'  => 'created-entry',
            'url'     => get_permalink( $entry_id ),
        ];
    }
}

/**
 * Controlador principal: procesa la importación desde el formulario.
 */
function drak_wiki_import_handle_submit( $campaign_id ) {
    if ( ! isset( $_POST['drak_wiki_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['drak_wiki_import_nonce'] ) ), 'drak_wiki_import' ) ) {
        return [ 'error' => 'Nonce inválido.' ];
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        return [ 'error' => 'Sin permisos.' ];
    }
    $text          = isset( $_POST['wiki_import_text'] ) ? wp_unslash( $_POST['wiki_import_text'] ) : '';
    $session_title = isset( $_POST['wiki_session_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wiki_session_title'] ) ) : '';
    $session_date  = isset( $_POST['wiki_session_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wiki_session_date'] ) ) : '';

    if ( ! $text ) {
        return [ 'error' => 'El texto está vacío.' ];
    }

    $entries = drak_wiki_import_parse_text( $text );
    $link_map = drak_wiki_import_build_link_map();

    $report = [
        'session_title' => $session_title,
        'session_date'  => $session_date,
        'updated'       => [],
        'missing'       => [],
        'errors'        => [],
    ];

    drak_wiki_import_process_wiki_posts( $campaign_id, $entries, $report, $link_map );
    drak_wiki_import_process_characters( $campaign_id, $entries, $report, $link_map );

    return $report;
}

/**
 * Renderiza el formulario de importación y procesa si hay POST.
 */
function drak_wiki_import_render_page( $campaign_id ) {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        echo '<p>No tienes permisos para importar contenido.</p>';
        return;
    }
    $result = null;
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wiki_import_text'] ) ) {
        $result = drak_wiki_import_handle_submit( $campaign_id );
    }
    ?>
    <div class="wiki-import">
        <h3>Importar contenido a la Wiki</h3>
        <form method="post">
            <?php wp_nonce_field( 'drak_wiki_import', 'drak_wiki_import_nonce' ); ?>
            <p><label for="wiki_session_title">Título de sesión / referencia (opcional)</label><br>
            <input type="text" id="wiki_session_title" name="wiki_session_title" style="width:100%;" value="<?php echo isset( $_POST['wiki_session_title'] ) ? esc_attr( wp_unslash( $_POST['wiki_session_title'] ) ) : ''; ?>"></p>
            <p><label for="wiki_session_date">Fecha (opcional)</label><br>
            <input type="date" id="wiki_session_date" name="wiki_session_date" value="<?php echo isset( $_POST['wiki_session_date'] ) ? esc_attr( wp_unslash( $_POST['wiki_session_date'] ) ) : ''; ?>"></p>
            <p><label for="wiki_import_text"><strong>Texto estructurado</strong></label><br>
            <textarea id="wiki_import_text" name="wiki_import_text" rows="20" style="width:100%;"><?php echo isset( $_POST['wiki_import_text'] ) ? esc_textarea( wp_unslash( $_POST['wiki_import_text'] ) ) : ''; ?></textarea></p>
            <p><button type="submit" class="drak-btn">Procesar</button></p>
        </form>
    </div>
    <?php
    if ( is_array( $result ) ) {
        if ( isset( $result['error'] ) ) {
            echo '<div class="wiki-import-result"><p><strong>Error:</strong> ' . esc_html( $result['error'] ) . '</p></div>';
            return;
        }
        echo '<div class="wiki-import-result">';
        if ( ! empty( $result['updated'] ) ) {
            echo '<h4>Actualizados / creados</h4><ul>';
            foreach ( $result['updated'] as $item ) {
                echo '<li>' . esc_html( $item['section'] . ' · ' . $item['name'] . ' (' . $item['action'] . ')' );
                if ( ! empty( $item['url'] ) ) {
                    echo ' - <a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noreferrer">ver</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        if ( ! empty( $result['missing'] ) ) {
            echo '<h4>No encontrados</h4><ul>';
            foreach ( $result['missing'] as $item ) {
                echo '<li>' . esc_html( $item['section'] . ' · ' . $item['name'] ) . '</li>';
            }
            echo '</ul>';
        }
        if ( ! empty( $result['errors'] ) ) {
            echo '<h4>Errores</h4><ul>';
            foreach ( $result['errors'] as $item ) {
                echo '<li>' . esc_html( $item['section'] . ' · ' . $item['name'] . ': ' . $item['error'] ) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
}
