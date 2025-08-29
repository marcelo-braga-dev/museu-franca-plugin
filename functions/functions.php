<?php

function get_url_artigo($post_id) {
    if (get_post_type($post_id) !== 'artigo') {
        return '';
    }
    
    $url = site_url('/publicacoes/artigo/?id=' . intval($post_id));
    return esc_url($url);
}

function redirect_minha_conta() {
    $url = site_url('/minha-conta');
    return esc_url($url);
}

function redirect_login() {
    $url = site_url('/minha-conta/login');
    return esc_url($url);
}

function redirect_cadastro() {
    $url = site_url('/minha-conta/cadastro');
    return esc_url($url);
}

function mp_increment_artigo_views()
{
    if (is_singular('artigo')) {
        $post_id = get_queried_object_id();
        $views = (int)get_post_meta($post_id, '_views', true);
        update_post_meta($post_id, '_views', $views + 1);
    }
}

add_action('wp', 'mp_increment_artigo_views');

/**
 * ===== Último acesso (salvar no login) =====
 * Armazena o timestamp do último login em user_meta 'last_login'
 */
add_action('wp_login', function ($user_login, $user) {
    update_user_meta($user->ID, 'last_login', current_time('timestamp'));
}, 10, 2);


