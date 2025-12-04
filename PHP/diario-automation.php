<?php
/**
 * Automatizacion de Diario: parsea flags y sincroniza wiki.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene el ID de campana desde el diario (ACF o meta plano).
 */
function drak_diario_get_campaign_id( $post_id ) {
    $campaign = get_field( 'campaign', $post_id );
    if ( is_array( $campaign ) ) {
        $campaign = array_filter( $campaign );
        $campaign = reset( $campaign );
    }
    if ( ! $campaign ) {
        $campaign = get_post_meta( $post_id, 'campaign', true );
    }
    if ( ! $campaign && isset( $_REQUEST['campaign'] ) ) {
        $raw = sanitize_text_field( wp_unslash( $_REQUEST['campaign'] ) );
        if ( is_numeric( $raw ) ) {
            $campaign = (int) $raw;
        } else {
            $slug_post = get_page_by_path( sanitize_title( $raw ), OBJECT, 'campaign' );
            if ( $slug_post ) {
                $campaign = $slug_post->ID;
            }
        }
    }
    if ( ! $campaign && ! empty( $_REQUEST['_wp_http_referer'] ) ) {
        $ref = wp_parse_url( wp_unslash( $_REQUEST['_wp_http_referer'] ) );
        if ( ! empty( $ref['query'] ) ) {
            parse_str( $ref['query'], $ref_query );
            if ( isset( $ref_query['campaign'] ) ) {
                $raw = sanitize_text_field( $ref_query['campaign'] );
                if ( is_numeric( $raw ) ) {
                    $campaign = (int) $raw;
                } else {
                    $slug_post = get_page_by_path( sanitize_title( $raw ), OBJECT, 'campaign' );
                    if ( $slug_post ) {
                        $campaign = $slug_post->ID;
                    }
                }
            }
        }
    }
    if ( ! $campaign ) {
        $default = get_page_by_path( 'cronicas-de-drakkenheim', OBJECT, 'campaign' );
        if ( $default ) {
            $campaign = $default->ID;
        }
    }
    return (int) $campaign;
}

/**
 * Extrae flags unicos del contenido.
 *
 * @return array[] lista de ['type','label','slug','raw']
 */
function drak_diario_extract_flags( $content ) {
    $results = [];
    $seen    = [];
    if ( ! $content ) {
        return $results;
    }
    if ( ! preg_match_all( '/@([LPNF])\[(.+?)\]/', $content, $matches, PREG_SET_ORDER ) ) {
        return $results;
    }
    foreach ( $matches as $match ) {
        $type  = $match[1];
        $label = trim( $match[2] );
        $slug  = sanitize_title( $label );
        if ( '' === $slug ) {
            continue;
        }
        $key = $type . '|' . $slug;
        if ( isset( $seen[ $key ] ) ) {
            continue;
        }
        $seen[ $key ] = true;
        $results[]    = [
            'type'  => $type,
            'label' => $label,
            'slug'  => $slug,
            'raw'   => $match[0],
        ];
    }
    return $results;
}

/**
 * Devuelve post por slug y tipo.
 */
function drak_diario_get_post_by_slug( $slug, $post_type ) {
    return get_page_by_path( $slug, OBJECT, $post_type );
}

/**
 * Construye el bloque HTML de referencia al diario.
 */
function drak_diario_build_ref_block( $session_title, $diary_url, $session_date, $excerpt, $diary_id ) {
    $html  = '<div class="diario-ref" data-diario-id="' . esc_attr( $diary_id ) . '">';
    $html .= '<a class="diario-ref__link" href="' . esc_url( $diary_url ) . '">' . esc_html( $session_title ) . '</a>';
    if ( $session_date ) {
        $html .= '<span class="diario-ref__date">' . esc_html( $session_date ) . '</span>';
    }
    if ( $excerpt ) {
        $html .= '<p class="diario-ref__excerpt">' . esc_html( $excerpt ) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Asegura la campana en meta/ACF si esta vacia.
 */
function drak_diario_ensure_campaign_meta( $post_id, $campaign_id ) {
    if ( ! $campaign_id || ! $post_id ) {
        return;
    }
    $current       = get_field( 'campaign', $post_id );
    $has_campaign  = false;
    if ( is_array( $current ) ) {
        $has_campaign = ! empty( array_filter( $current ) );
    } elseif ( $current ) {
        $has_campaign = true;
    }
    if ( $has_campaign ) {
        return;
    }
    if ( function_exists( 'update_field' ) ) {
        update_field( 'campaign', $campaign_id, $post_id );
    } else {
        update_post_meta( $post_id, 'campaign', $campaign_id );
    }
}

/**
 * Anade el bloque de referencia a un post si no existe.
 */
function drak_diario_append_ref_block( $post_id, $ref_html, $campaign_id, $diary_id ) {
    if ( ! $post_id || ! $ref_html ) {
        return;
    }
    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }
    $content = $post->post_content ?? '';
    if ( false === strpos( $content, 'data-diario-id="' . $diary_id . '"' ) ) {
        $new_content = trim( $content ) ? ( $content . "\n\n" . $ref_html ) : $ref_html;
        wp_update_post(
            [
                'ID'           => $post_id,
                'post_content' => wp_slash( $new_content ),
            ]
        );
    }
    drak_diario_ensure_campaign_meta( $post_id, $campaign_id );
}

/**
 * Crea o actualiza un CPT wiki (lugar/npc/faccion).
 */
function drak_diario_upsert_wiki_post( $post_type, $slug, $title, $ref_html, $campaign_id, $diary_id ) {
    $existing = drak_diario_get_post_by_slug( $slug, $post_type );
    if ( $existing ) {
        $post_id = $existing->ID;
    } else {
        $post_id = wp_insert_post(
            [
                'post_type'    => $post_type,
                'post_status'  => 'publish',
                'post_title'   => wp_strip_all_tags( $title ),
                'post_name'    => $slug,
                'post_content' => wp_slash( $ref_html ),
                'post_author'  => get_current_user_id() ?: 0,
            ]
        );
        if ( is_wp_error( $post_id ) ) {
            return 0;
        }
    }

    drak_diario_append_ref_block( $post_id, $ref_html, $campaign_id, $diary_id );
    return (int) $post_id;
}

/**
 * Crea o actualiza una entrada de aventura para un personaje existente.
 */
function drak_diario_upsert_personaje_entry( $personaje_id, $personaje_slug, $session_title, $ref_html, $campaign_id, $diary_id, $diary_url, $session_date, $excerpt ) {
    $existing = get_posts(
        [
            'post_type'      => 'personaje_wiki_entry',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'diario_id',
                    'value'   => $diary_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'parent_personaje_wiki',
                    'value'   => $personaje_id,
                    'compare' => '=',
                ],
            ],
        ]
    );

    if ( $existing ) {
        $entry_id      = (int) $existing[0];
        $entry_post    = get_post( $entry_id );
        $needs_content = $entry_post && false === strpos( $entry_post->post_content ?? '', 'data-diario-id="' . $diary_id . '"' );
        if ( $needs_content ) {
            $new_content = trim( $entry_post->post_content ) ? ( $entry_post->post_content . "\n\n" . $ref_html ) : $ref_html;
            wp_update_post(
                [
                    'ID'           => $entry_id,
                    'post_content' => wp_slash( $new_content ),
                ]
            );
        }
        if ( $entry_post && $entry_post->post_title !== $session_title ) {
            wp_update_post(
                [
                    'ID'         => $entry_id,
                    'post_title' => wp_strip_all_tags( $session_title ),
                ]
            );
        }
    } else {
        $entry_id = wp_insert_post(
            [
                'post_type'    => 'personaje_wiki_entry',
                'post_status'  => 'publish',
                'post_title'   => wp_strip_all_tags( $session_title ),
                'post_name'    => sanitize_title( $session_title . '-' . $personaje_slug ),
                'post_content' => wp_slash( $ref_html ),
                'post_author'  => get_current_user_id() ?: 0,
            ]
        );
        if ( is_wp_error( $entry_id ) ) {
            return 0;
        }
    }

    if ( function_exists( 'update_field' ) ) {
        update_field( 'parent_personaje_wiki', $personaje_id, $entry_id );
        update_field( 'section', 'aventura', $entry_id );
        update_field( 'campaign', $campaign_id, $entry_id );
    } else {
        update_post_meta( $entry_id, 'parent_personaje_wiki', $personaje_id );
        update_post_meta( $entry_id, 'section', 'aventura' );
        update_post_meta( $entry_id, 'campaign', $campaign_id );
    }
    update_post_meta( $entry_id, 'pw_entry_campaign', $campaign_id );
    update_post_meta( $entry_id, 'diario_id', $diary_id );
    update_post_meta( $entry_id, 'diario_url', $diary_url );
    update_post_meta( $entry_id, 'session_title', $session_title );
    update_post_meta( $entry_id, 'session_date', $session_date );
    update_post_meta( $entry_id, 'session_excerpt', $excerpt );

    return (int) $entry_id;
}

/**
 * Reemplaza flags en el contenido por enlaces o texto plano.
 */
function drak_diario_replace_flags_with_links( $content, $link_map ) {
    if ( ! $content ) {
        return $content;
    }
    return preg_replace_callback(
        '/@([LPNF])\[(.+?)\]/',
        function( $match ) use ( $link_map ) {
            $type  = $match[1];
            $label = trim( $match[2] );
            $slug  = sanitize_title( $label );
            $key   = $type . '|' . $slug;
            if ( isset( $link_map[ $key ] ) ) {
                return '<a href="' . esc_url( $link_map[ $key ] ) . '">' . esc_html( $label ) . '</a>';
            }
            return esc_html( $label );
        },
        $content
    );
}

/**
 * Hook principal de guardado del diario.
 */
function drak_diario_on_save( $post_id, $post, $update ) {
    static $processing = false;

    if ( $processing ) {
        return;
    }
    if ( 'diario' !== ( $post->post_type ?? '' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( ! in_array( $post->post_status, [ 'publish', 'future', 'draft', 'pending', 'private', 'auto-draft' ], true ) ) {
        return;
    }

    $flags = drak_diario_extract_flags( $post->post_content );
    if ( ! $flags ) {
        return;
    }

    $processing    = true;
    $campaign_id   = drak_diario_get_campaign_id( $post_id );
    drak_diario_ensure_campaign_meta( $post_id, $campaign_id );
    $session_title = get_the_title( $post_id );
    $diary_url     = get_permalink( $post_id );
    $session_date  = get_the_date( get_option( 'date_format' ), $post_id );
    $excerpt       = wp_html_excerpt( wp_strip_all_tags( $post->post_content ), 200, '...' );
    $link_map      = [];

    $map = [
        'L' => 'lugar',
        'N' => 'npc',
        'F' => 'faccion',
        'P' => 'personaje_wiki',
    ];

    foreach ( $flags as $flag ) {
        $type  = $flag['type'];
        $slug  = $flag['slug'];
        $label = $flag['label'];

        if ( ! isset( $map[ $type ] ) ) {
            continue;
        }

        if ( 'P' === $type ) {
            $personaje = drak_diario_get_post_by_slug( $slug, 'personaje_wiki' );
            if ( $personaje ) {
                $link_map[ $type . '|' . $slug ] = home_url( '/personaje-wiki/' . $slug . '/' );
            }
            continue;
        }

        $ref_block = drak_diario_build_ref_block( $session_title, $diary_url, $session_date, $excerpt, $post_id );
        $entity_id = drak_diario_upsert_wiki_post( $map[ $type ], $slug, $label, $ref_block, $campaign_id, $post_id );
        if ( $entity_id ) {
            $link_map[ $type . '|' . $slug ] = get_permalink( $entity_id );
        }
    }

    $updated_content = drak_diario_replace_flags_with_links( $post->post_content, $link_map );
    if ( $updated_content !== $post->post_content ) {
        wp_update_post(
            [
                'ID'           => $post_id,
                'post_content' => wp_slash( $updated_content ),
            ]
        );
    }

    $processing = false;
}
add_action( 'save_post_diario', 'drak_diario_on_save', 20, 3 );
