<?php
/**
 * Plugin Name: Drak Campaigns
 * Description: CPT de campañas para el proyecto de rol (hub de campañas, Drakkenheim, etc).
 * Author: Aday
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Seguridad: bloqueo acceso directo.
}

/**
 * Registrar CPT "campaign".
 */
function drak_register_campaign_cpt() {

    $labels = array(
        'name'                  => 'Campañas',
        'singular_name'         => 'Campaña',
        'menu_name'             => 'Campañas',
        'name_admin_bar'        => 'Campaña',
        'add_new'               => 'Añadir nueva',
        'add_new_item'          => 'Añadir nueva campaña',
        'edit_item'             => 'Editar campaña',
        'new_item'              => 'Nueva campaña',
        'view_item'             => 'Ver campaña',
        'view_items'            => 'Ver campañas',
        'search_items'          => 'Buscar campañas',
        'not_found'             => 'No se han encontrado campañas',
        'not_found_in_trash'    => 'No hay campañas en la papelera',
        'all_items'             => 'Todas las campañas',
        'archives'              => 'Archivo de campañas',
        'attributes'            => 'Atributos de campaña',
        'insert_into_item'      => 'Insertar en campaña',
        'uploaded_to_this_item' => 'Subido a esta campaña',
        'filter_items_list'     => 'Filtrar lista de campañas',
        'items_list'            => 'Lista de campañas',
        'items_list_navigation' => 'Navegación de campañas',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,               // Visible en el front.
        'publicly_queryable' => true,
        'show_ui'            => true,               // Visible en el admin.
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array(
            'slug'       => 'campaign',
            'with_front' => false,
        ),
        'capability_type'    => 'post',
        'has_archive'        => true,               // /campaign/ como archivo.
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-flag',   // Icono de banderita en el admin.
        'supports'           => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
        ),
        'show_in_rest'       => true,               // Para Gutenberg / REST API.
    );

    register_post_type( 'campaign', $args );
}
add_action( 'init', 'drak_register_campaign_cpt' );
