<?php
// Shortcode: [menu_cabecalho]
add_shortcode('menu_cabecalho', function () {
    ob_start();
    ?>
    <style>
        .menu-cabecalho a {
            color: #555;
            text-decoration: none;
            font-size: 12px;
        }
        .menu-cabecalho a:hover {
            color: #000;
        }
    </style>
    <div class="menu-cabecalho">
        <?php if (is_user_logged_in()) : ?>
            <a href="<?= esc_url(redirect_minha_conta()) ?>">Minha Conta</a> | 
            <a href="<?= esc_url(wp_logout_url(home_url())) ?>">Sair</a>
        <?php else : ?>
            <a href="<?= esc_url(redirect_login()) ?>">Entrar</a> |
            <a href="<?= esc_url(redirect_cadastro()) ?>">Cadastrar</a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
