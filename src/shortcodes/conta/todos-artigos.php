<?php
if (!defined('ABSPATH')) exit;

add_shortcode('todos_artigos', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    $pageTitle = 'Todas Publicações';
    $pageIcon  = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i>';

    $queryData = [
        'post_status' => ['publish'],
    ];

    ob_start();

    $path = MP_PLUGIN_DIR . 'components/artigo-card.php';

    if (file_exists($path)) {
        $html = require $path;
        if (is_string($html)) echo $html;
    } else {
        echo '<p>Arquivo não encontrado.</p>';
    }

    return ob_get_clean();
});
