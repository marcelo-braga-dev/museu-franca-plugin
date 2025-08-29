<?php
// -------------- Shortcode: Grid de Favoritos --------------
add_shortcode('grid_favoritos', function ($atts = []) {
    $pageTitle = 'Seus Favoritos';
    $pageIcon = '<i class="far fa-heart" aria-hidden="true"></i>';
    $hiddenBtnAction = true;

    $user_id = get_current_user_id();
    $ids = mp_get_user_favorites($user_id);

    if (empty($ids)) {
        return '<p>Você ainda não favoritou nenhum conteúdo.</p>';
    }

    $queryData = [
            'post_type'      => 'artigo',
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'orderby'        => 'post__in', // mantém a ordem que o user salvou
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
