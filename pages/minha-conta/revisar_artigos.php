<?php
add_shortcode('revisar_artigos', function () {

    $pageTitle = 'Conteúdos aguardando revisão para serem publicados';
    $pageIcon = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i>';

    $queryData = [
            'post_status' => ['pending'],
            'no_found_rows' => true,
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