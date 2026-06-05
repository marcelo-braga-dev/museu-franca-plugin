<?php
if (!defined('ABSPATH')) exit;

add_shortcode('meus_artigos', function () {
    $pageTitle = 'Minhas Publicações';
    $pageIcon  = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i>';

    $queryData = [
        'author' => get_current_user_id(),
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
