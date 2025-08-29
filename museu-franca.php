<?php
/**
 * Plugin Name: Museu Franca
 * Description: Museu da Pessoa de Franca.
 * Version: 1.0
 * Author: Marcelo Braga (marcelobraga.dev@gmail.com)
 */

if (!defined('ABSPATH')) exit;

// Register post type
add_action('init', function () {
    register_post_type('artigo', [
        'labels' => ['name' => 'Artigos', 'singular_name' => 'Artigo'],
        'public' => true,
        'publicly_queryable' => true,
        'exclude_from_search' => false,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'author', 'excerpt'],
        'taxonomies' => ['category', 'post_tag'],
        'rewrite' => ['slug' => 'artigos'],
        'show_in_rest' => true,
        'map_meta_cap' => true,
        'query_var' => true,
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
require_once __DIR__ . '/functions/functions.php';
require_once __DIR__ . '/functions/includes.php';