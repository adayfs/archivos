<?php
/**
 * Renderiza rasgos de raza (reusable).
 */

function drak_render_race_features_section( $race_id, $echo = true ) {
    if ( ! $race_id ) {
        return '';
    }

    $race_data  = function_exists( 'drak_get_local_dnd_races_data' ) ? drak_get_local_dnd_races_data() : [];
    $race_entry = null;
    foreach ( $race_data['races'] ?? [] as $race ) {
        if ( isset( $race['id'] ) && $race['id'] === $race_id ) {
            $race_entry = $race;
            break;
        }
    }

    if ( ! $race_entry ) {
        return '';
    }

    if ( $echo ) {
        ob_start();
    }

    $name        = $race_entry['name']['es'] ?? $race_entry['name']['en'] ?? $race_entry['name'] ?? $race_id;
    $entries_raw = $race_entry['entries'] ?? ( $race_entry['entries_en'] ?? [] );
    $entries_out = function_exists( 'drak_render_5e_entries_html' ) ? drak_render_5e_entries_html( $entries_raw ) : '';
    if ( $entries_out === '' ) {
        $entries_out = '<p>' . esc_html__( 'Sin descripción.', 'temahijo' ) . '</p>';
    }

    echo '<section class="character-extended__section">';
    echo '<h4 class="character-extended__section-title">Rasgos raciales · ' . esc_html( $name ) . '</h4>';
    echo $entries_out;
    echo '</section>';

    if ( $echo ) {
        return ob_get_clean();
    }

    return null;
}
