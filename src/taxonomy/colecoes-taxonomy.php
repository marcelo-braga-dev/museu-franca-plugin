<?php
/**
 * Taxonomia personalizada: Coleções
 * - Slug: colecao
 * - Vinculada ao CPT: artigo
 */

// Registrar
function mp_register_taxonomy_colecao() {
    $labels = [
        'name'                       => 'Coleções',
        'singular_name'              => 'Coleção',
        'search_items'               => 'Buscar Coleções',
        'all_items'                  => 'Todas as Coleções',
        'edit_item'                  => 'Editar Coleção',
        'update_item'                => 'Atualizar Coleção',
        'add_new_item'               => 'Adicionar Nova Coleção',
        'new_item_name'              => 'Nome da Nova Coleção',
        'menu_name'                  => 'Coleções',
        'not_found'                  => 'Nenhuma coleção encontrada',
    ];

    register_taxonomy('colecao', ['artigo'], [
        'labels'            => $labels,
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'colecao'],
        'query_var'         => 'colecao',
    ]);
}
add_action('init', 'mp_register_taxonomy_colecao');
