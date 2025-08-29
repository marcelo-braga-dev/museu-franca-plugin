<?php
// Shortcode: [formulario_login]
add_shortcode('formulario_login', function () {
    if (is_user_logged_in()) {
        return "<script>window.location.href = '" . esc_url(home_url('/minha-conta/')) . "';</script>";
    }
})
?>