<?php
add_shortcode('meus_artigos', function () {

    $pageTitle = 'Minhas Publicações';
    $pageIcon = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i>';

    $queryData = [
            'author' => get_current_user_id(),
//        'post_status' => ['publish', 'pending'], // exibe tudo relevante
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
