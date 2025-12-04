<?php
/**
 * Script de consola para asignar campaña a todos los diarios existentes.
 * Ejecutar con: wp eval-file PHP/scripts/backfill-diario-campaign.php
 */

if ( ! defined( 'WP_CLI' ) ) {
    echo "Este script debe ejecutarse con WP-CLI (wp eval-file ...)\n";
    return;
}

$campaign = get_page_by_path( 'cronicas-de-drakkenheim', OBJECT, 'campaign' );
if ( ! $campaign ) {
    WP_CLI::error( 'No se encontró la campaña cronicas-de-drakkenheim.' );
}
$campaign_id = $campaign->ID;

$query = new WP_Query( [
    'post_type'      => 'diario',
    'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future', 'auto-draft' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
] );

$updated = 0;
if ( $query->have_posts() ) {
    foreach ( $query->posts as $post_id ) {
        $current = get_field( 'campaign', $post_id );
        if ( empty( $current ) ) {
            if ( function_exists( 'update_field' ) ) {
                update_field( 'campaign', $campaign_id, $post_id );
            } else {
                update_post_meta( $post_id, 'campaign', $campaign_id );
            }
            $updated++;
        }
    }
}

WP_CLI::success( sprintf( 'Procesados %d diarios, actualizados %d con campaña %d.', $query->post_count, $updated, $campaign_id ) );
