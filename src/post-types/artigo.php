<?php
if (!defined('ABSPATH')) exit;

function mp_register_post_type_artigo() {
    $labels = [
        'name'               => 'Artigos',
        'singular_name'      => 'Artigo',
        'add_new'            => 'Adicionar Novo',
        'add_new_item'       => 'Adicionar Novo Artigo',
        'edit_item'          => 'Editar Artigo',
        'new_item'           => 'Novo Artigo',
        'view_item'          => 'Ver Artigo',
        'search_items'       => 'Buscar Artigos',
        'not_found'          => 'Nenhum artigo encontrado',
        'not_found_in_trash' => 'Nenhum artigo na lixeira',
        'menu_name'          => 'Artigos',
    ];

    register_post_type('artigo', [
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments'],
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'artigos'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'menu_icon'           => 'dashicons-welcome-write-blog',
    ]);
}
add_action('init', 'mp_register_post_type_artigo');
