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



