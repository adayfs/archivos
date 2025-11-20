<?php
/**
 * Plugin Name: Drak Importer
 * Description: Importador de notas de sesión y por elemento, con contexto de campaña.
 * Version: 0.1.0
 * Author: Aday
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Capabilities para el importador.
 */
function drak_importer_register_caps() {
    $caps = [ 'drak_import_notes' ];

    $roles_to_grant = [ 'administrator', 'dm' ];
    foreach ( $roles_to_grant as $role_slug ) {
        $role = get_role( $role_slug );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'drak_importer_register_caps' );

/**
 * En situaciones de importación, permite a DM crear/editar posts controladamente.
 */
$GLOBALS['drak_importer_force_post_caps'] = false;
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
    if ( empty( $GLOBALS['drak_importer_force_post_caps'] ) ) {
        return $allcaps;
    }
    if ( ! in_array( 'dm', (array) $user->roles, true ) ) {
        return $allcaps;
    }
    $post_caps = [
        'edit_posts',
        'edit_others_posts',
        'publish_posts',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
        'edit_published_posts',
        'create_posts',
    ];
    foreach ( $post_caps as $cap ) {
        $allcaps[ $cap ] = true;
    }
    return $allcaps;
}, 10, 4 );

function drak_importer_user_can_session_import() {
    return current_user_can( 'manage_options' ) || current_user_can( 'drak_import_notes' );
}

function drak_importer_user_can_element_import( $post_type, $post_id = 0 ) {
    if ( current_user_can( 'manage_options' ) || current_user_can( 'drak_import_notes' ) ) {
        return true;
    }

    if ( $post_type === 'personaje' && $post_id ) {
        $owner = get_field( 'jugador_asociado', $post_id );
        if ( $owner && intval( $owner ) === get_current_user_id() ) {
            return true;
        }
    }

    return false;
}

function drak_importer_get_campaign_dropdown( $selected = 0 ) {
    $campaigns = get_posts( [
        'post_type'      => 'campaign',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    $html = '<select name="campaign_id" required>';
    $html .= '<option value="">Selecciona campaña</option>';
    foreach ( $campaigns as $c ) {
        $sel = selected( $selected, $c->ID, false );
        $html .= '<option value="' . esc_attr( $c->ID ) . '"' . $sel . '>' . esc_html( $c->post_title ) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function drak_importer_append_content( $post_id, $new_content ) {
    $existing = get_post_field( 'post_content', $post_id );
    $block    = "\n\n<!-- drak-importer -->\n" . wp_kses_post( $new_content ) . "\n<!-- /drak-importer -->\n";
    $content  = $existing ? $existing . $block : $new_content;
    remove_action( 'save_post', 'drak_importer_save_guard', 10 );
    wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $content,
    ] );
}

function drak_importer_create_diary_entry( $campaign_id, $title, $content, $meta = [] ) {
    $GLOBALS['drak_importer_force_post_caps'] = true;
    $diary_term = get_category_by_slug( 'diario' );

    $postarr = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'post',
        'post_status'  => 'publish',
    ];
    if ( $diary_term && ! is_wp_error( $diary_term ) ) {
        $postarr['tax_input'] = [ 'category' => [ $diary_term->term_id ] ];
    }

    $post_id = wp_insert_post( $postarr, true );
    $GLOBALS['drak_importer_force_post_caps'] = false;

    if ( ! is_wp_error( $post_id ) ) {
        update_post_meta( $post_id, 'campaign', $campaign_id );
        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    return $post_id;
}

function drak_importer_handle_session_form() {
    if ( ! isset( $_POST['drak_import_session_nonce'] ) || ! wp_verify_nonce( $_POST['drak_import_session_nonce'], 'drak_import_session' ) ) {
        return;
    }
    if ( ! drak_importer_user_can_session_import() ) {
        wp_die( 'Permisos insuficientes para importar sesión.' );
    }

    $campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
    $session_no  = isset( $_POST['session_number'] ) ? sanitize_text_field( wp_unslash( $_POST['session_number'] ) ) : '';
    $session_date= isset( $_POST['session_date'] ) ? sanitize_text_field( wp_unslash( $_POST['session_date'] ) ) : '';
    $notes       = isset( $_POST['session_notes'] ) ? wp_kses_post( wp_unslash( $_POST['session_notes'] ) ) : '';

    if ( $campaign_id <= 0 || empty( $notes ) ) {
        wp_die( 'Campaña y notas son obligatorios.' );
    }

    $title_parts = [];
    $title_parts[] = get_the_title( $campaign_id );
    $title_parts[] = 'Sesión';
    if ( $session_no ) {
        $title_parts[] = $session_no;
    }
    $title = implode( ' ', array_filter( $title_parts ) );

    $meta = [];
    if ( $session_no ) {
        $meta['session_number'] = $session_no;
    }
    if ( $session_date ) {
        $meta['session_date'] = $session_date;
    }

    $result = drak_importer_create_diary_entry( $campaign_id, $title, $notes, $meta );

    if ( is_wp_error( $result ) ) {
        wp_die( 'Error al importar: ' . esc_html( $result->get_error_message() ) );
    }

    add_filter( 'wp_redirect', function ( $location ) use ( $result ) {
        return add_query_arg( [ 'drak_import_session_ok' => 1, 'post_id' => $result ], $location );
    } );
}
add_action( 'template_redirect', 'drak_importer_handle_session_form' );

function drak_importer_handle_element_form() {
    if ( ! isset( $_POST['drak_import_element_nonce'] ) || ! wp_verify_nonce( $_POST['drak_import_element_nonce'], 'drak_import_element' ) ) {
        return;
    }

    $campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
    $post_type   = isset( $_POST['element_type'] ) ? sanitize_text_field( wp_unslash( $_POST['element_type'] ) ) : '';
    $post_id     = isset( $_POST['element_id'] ) ? intval( $_POST['element_id'] ) : 0;
    $title       = isset( $_POST['element_title'] ) ? sanitize_text_field( wp_unslash( $_POST['element_title'] ) ) : '';
    $notes       = isset( $_POST['element_notes'] ) ? wp_kses_post( wp_unslash( $_POST['element_notes'] ) ) : '';

    if ( ! $campaign_id || ! $post_type || ! $notes ) {
        wp_die( 'Campaña, tipo y notas son obligatorios.' );
    }

    if ( $post_id && ! drak_importer_user_can_element_import( $post_type, $post_id ) ) {
        wp_die( 'No puedes importar notas para este elemento.' );
    }

    if ( ! $post_id ) {
        $GLOBALS['drak_importer_force_post_caps'] = true;
        $post_id = wp_insert_post( [
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'post_title'  => $title ? $title : 'Borrador importado',
        ], true );
        $GLOBALS['drak_importer_force_post_caps'] = false;
        if ( is_wp_error( $post_id ) ) {
            wp_die( 'Error al crear el elemento: ' . esc_html( $post_id->get_error_message() ) );
        }
        update_post_meta( $post_id, 'campaign', $campaign_id );
    }

    drak_importer_append_content( $post_id, $notes );
    add_filter( 'wp_redirect', function ( $location ) use ( $post_id ) {
        return add_query_arg( [ 'drak_import_element_ok' => 1, 'post_id' => $post_id ], $location );
    } );
}
add_action( 'template_redirect', 'drak_importer_handle_element_form' );

function drak_importer_resolve_campaign_field( $campaign_id ) {
    $campaign_id = intval( $campaign_id );
    if ( $campaign_id > 0 && get_post_status( $campaign_id ) === 'publish' && get_post_type( $campaign_id ) === 'campaign' ) {
        $label = get_the_title( $campaign_id );
        $html  = '<input type="hidden" name="campaign_id" value="' . esc_attr( $campaign_id ) . '">';
        $html .= '<div class="drak-importer-form__fixed-campaign">Campaña: <strong>' . esc_html( $label ) . '</strong></div>';
        return $html;
    }

    return drak_importer_get_campaign_dropdown();
}

/**
 * Shortcode: importador de sesión
 */
function drak_importer_shortcode_session( $atts = [] ) {
    if ( ! drak_importer_user_can_session_import() ) {
        return '<p>No tienes permisos para importar sesiones.</p>';
    }

    $atts = shortcode_atts( [
        'campaign' => 0,
    ], $atts );

    $campaign_field = drak_importer_resolve_campaign_field( $atts['campaign'] );

    ob_start();
    ?>
    <form method="post" class="drak-importer-form">
        <h3>Importar sesión (Diario)</h3>
        <label>Campaña <?php echo $campaign_field; ?></label>
        <label>Número / título de sesión <input type="text" name="session_number" placeholder="Ej: Sesión 12"></label>
        <label>Fecha <input type="date" name="session_date"></label>
        <label>Notas (texto/markdown)</label>
        <textarea name="session_notes" rows="10" required></textarea>
        <?php wp_nonce_field( 'drak_import_session', 'drak_import_session_nonce' ); ?>
        <button type="submit">Importar sesión</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'drak_import_session', 'drak_importer_shortcode_session' );

/**
 * Shortcode: importador por elemento
 */
function drak_importer_shortcode_element( $atts = [] ) {
    $atts = shortcode_atts( [
        'campaign' => 0,
    ], $atts );
    $campaign_field = drak_importer_resolve_campaign_field( $atts['campaign'] );

    $post_types = [
        'personaje'  => 'Personaje',
        'npc'        => 'NPC',
        'lugar'      => 'Lugar',
        'faccion'    => 'Facción',
        'lore-entry' => 'Entrada de lore',
    ];

    ob_start();
    ?>
    <form method="post" class="drak-importer-form">
        <h3>Importar notas a un elemento</h3>
        <label>Campaña <?php echo $campaign_field; ?></label>
        <label>Tipo de elemento
            <select name="element_type" required>
                <option value="">Selecciona tipo</option>
                <?php foreach ( $post_types as $slug => $label ) : ?>
                    <?php if ( post_type_exists( $slug ) ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>ID existente (opcional, deja vacío para crear nuevo)
            <input type="number" name="element_id" min="0" step="1">
        </label>
        <label>Título (solo al crear nuevo)</label>
        <input type="text" name="element_title" placeholder="Nombre del elemento">
        <label>Notas (texto/markdown)</label>
        <textarea name="element_notes" rows="10" required></textarea>
        <?php wp_nonce_field( 'drak_import_element', 'drak_import_element_nonce' ); ?>
        <button type="submit">Adjuntar notas</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'drak_import_element', 'drak_importer_shortcode_element' );

/**
 * Estilos rápidos para los formularios.
 */
add_action( 'wp_head', function () {
    ?>
    <style>
    .drak-importer-form {
        margin: 24px 0;
        padding: 16px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #f8f8fb;
        max-width: 720px;
    }
    .drak-importer-form h3 { margin-top: 0; }
    .drak-importer-form label { display: block; margin-bottom: 8px; }
    .drak-importer-form input[type="text"],
    .drak-importer-form input[type="number"],
    .drak-importer-form input[type="date"],
    .drak-importer-form select,
    .drak-importer-form textarea {
        width: 100%;
        padding: 8px;
        margin-top: 4px;
        box-sizing: border-box;
    }
    .drak-importer-form button {
        margin-top: 12px;
        padding: 10px 16px;
        background: #4c2bb8;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    .drak-importer-form button:hover { background: #3d2094; }
    </style>
    <?php
} );
