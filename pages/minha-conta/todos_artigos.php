<?php
add_shortcode('todos_artigos', function () {
    $pageTitle = 'Todas Publicações';
    $pageIcon = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i>';

    $queryData = [
        'post_status' => ['publish'],
    ];

    ob_start();

    $path = __DIR__ . '/../../components/cards/minha-conta/card.php';

    if (file_exists($path)) {
        $html = require $path;
        if (is_string($html)) {
            echo $html;
        }
    } else {
        echo '<p>Arquivo não encontrado.</p>';
    }

    return ob_get_clean();
});
