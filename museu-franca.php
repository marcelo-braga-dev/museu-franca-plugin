<?php
/**
 * Plugin Name: Museu Franca
 * Description: Museu da Pessoa de Franca.
 * Version: 1.0
 * Author: Marcelo Braga (marcelobraga.dev@gmail.com)
 */

if (!defined('ABSPATH')) exit;

// Register post type
add_action('init', function() {
    register_post_type('artigo', [
        'labels' => [
            'name' => 'Artigos',
            'singular_name' => 'Artigo'
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'author'],
        'taxonomies' => ['category', 'post_tag'],
        'rewrite' => ['slug' => 'artigos'],
        'show_in_rest' => true
    ]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('font-awesome-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
});

add_action('after_setup_theme', function () {
    if (!current_user_can('administrator')) {
         show_admin_bar(false);
     }
});

// includes
require_once __DIR__ . '/assets/vlibras.php';

require_once __DIR__ . '/functions/functions.php';

require_once __DIR__ . '/paginas/login_form.php';

require_once __DIR__ . '/paginas/artigo/visualizar_artigo.php';
require_once __DIR__ . '/paginas/artigo/grid_artigos.php';

require_once __DIR__ . '/paginas/minha-conta/painel_usuario.php';
require_once __DIR__ . '/paginas/minha-conta/submeter_artigo.php';
require_once __DIR__ . '/paginas/minha-conta/meus_artigos.php';
require_once __DIR__ . '/paginas/minha-conta/editar_artigo.php';

require_once __DIR__ . '/paginas/login/login.php';
require_once __DIR__ . '/paginas/login/cadastro.php';
require_once __DIR__ . '/paginas/login/menu_cabecalho.php';

require_once __DIR__ . '/layout/menu_categorias.php';








