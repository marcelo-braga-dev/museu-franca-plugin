<?php
/**
 * Plugin Name:  Museu Franca
 * Plugin URI:   https://museudapessoadefranca.org.br/
 * Description:  Sistema de acervo digital do Museu da Pessoa de Franca.
 * Version:      2.0.0
 * Author:       Marcelo Braga
 * Author URI:   https://marcelobraga.dev
 * Text Domain:  museu-franca
 * Domain Path:  /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) exit;

// Bootstrap
require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/loader.php';

// Assets globais
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'mp-font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        [],
        '6.5.2'
    );
});

// Template customizado para single artigo
add_filter('single_template', function (string $template): string {
    if (is_singular('artigo')) {
        $tpl = MP_PLUGIN_DIR . 'templates/single-artigo.php';
        if (file_exists($tpl)) return $tpl;
    }
    return $template;
});
