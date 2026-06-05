<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [formulario_cadastro]
add_shortcode('formulario_cadastro', function () {
    if (is_user_logged_in()) {
        wp_safe_redirect(mp_url_minha_conta());
        exit;
    }

    $erro = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastro_user'], $_POST['mp_cadastro_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mp_cadastro_nonce'])), 'mp_cadastro')) {
            $erro = 'Requisição inválida.';
        } else {
            $username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
            $email    = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if (username_exists($username) || email_exists($email)) {
                $erro = 'Usuário ou e-mail já está cadastrado.';
            } elseif (strlen($password) < 6) {
                $erro = 'A senha deve ter no mínimo 6 caracteres.';
            } else {
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    wp_safe_redirect(mp_url_login());
                    exit;
                } else {
                    $erro = 'Erro ao cadastrar: ' . esc_html($user_id->get_error_message());
                }
            }
        }
    }

    ob_start();
    ?>
    <style>
    .form-cadastro {
        max-width: 400px;
        margin: 80px auto;
        background: #eee;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .form-cadastro h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 20px;
    }
    .form-cadastro label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }
    .form-cadastro input[type="text"],
    .form-cadastro input[type="email"],
    .form-cadastro input[type="password"] {
        width: 100%;
        padding: 10px;
        font-size: 15px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .form-cadastro button {
        width: 100%;
        background-color: #992d17;
        color: white;
        padding: 12px;
        font-weight: bold;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    .form-cadastro button:hover {
        background-color: #7c1f13;
    }
    </style>

    <?php if (!empty($erro)): ?>
        <p style="color:red;max-width:400px;margin:0 auto 10px;"><?php echo esc_html($erro); ?></p>
    <?php endif; ?>

    <form method="post" class="form-cadastro">
        <?php wp_nonce_field('mp_cadastro', 'mp_cadastro_nonce'); ?>
        <h3>Criar Conta</h3>
        <p><label>Nome de Usuário</label>
        <input type="text" name="username" required></p>
        <p><label>E-mail</label>
        <input type="email" name="email" required></p>
        <p><label>Senha</label>
        <input type="password" name="password" required minlength="6"></p>
        <p><button type="submit" name="cadastro_user">Cadastrar</button></p>
    </form>
    <?php
    return ob_get_clean();
});
