<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [formulario_login]
add_shortcode('formulario_login', function () {
    if (is_user_logged_in()) {
        wp_safe_redirect(mp_url_minha_conta());
        exit;
    }
    return '';
});
