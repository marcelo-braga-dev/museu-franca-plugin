<?php

function mp_colecao_badges($post_id = null)
{
    $post_id = $post_id ?: get_the_ID();
    if (!$post_id) return '';

    $terms = get_the_terms($post_id, 'colecao');
    if (empty($terms) || is_wp_error($terms)) return '';

    ob_start();

    foreach ($terms as $t) {
        echo esc_html($t->name);
    }
    return ob_get_clean();
}
