<?php
// Shortcode: Formulário de Login/Cadastro
add_shortcode('artigos_login_form', function() {
    if (is_user_logged_in()) return '<p>Você já está logado.</p>';

    ob_start();
    wp_login_form();
    echo '<p>Não tem conta? <a href="' . wp_registration_url() . '">Cadastre-se aqui</a></p>';
    return ob_get_clean();
});