<?php
add_shortcode('painel_usuario', function () {
    if (!is_user_logged_in()) return '<p>Você precisa estar logado.</p>';

    $aba = isset($_GET['aba']) ? sanitize_text_field($_GET['aba']) : 'publicar';

    ob_start();
    ?>

    <style>
    .painel-container {
        display: flex;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        min-height: 700px;
        align-items: stretch;
    }

    .menu-lateral {
        width: 250px;
        padding: 20px;
        background: #f9f9f9;
        border-right: 1px solid #ccc;
        flex-shrink: 0;
    }

    .menu-lateral ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .menu-lateral li {
        margin-bottom: 15px;
    }

    .menu-lateral a {
        text-decoration: none;
        color: #992d17;
        font-weight: bold;
        display: block;
        padding: 8px 12px;
        border-radius: 4px;
        transition: background-color 0.3s;
    }

    .menu-lateral a.ativo {
        background-color: #992d17;
        color: #fff;
    }

    .menu-lateral a:hover {
        background-color: #ddd;
    }

    .conteudo-aba {
        flex-grow: 1;
        padding: 30px;
        overflow-y: auto;
        background-color: #fff;
    }

    .conteudo-aba h2 {
        color: #992d17;
    }
    </style>

    <div class="painel-container">
        <aside class="menu-lateral">
            <ul>
                <li><a href="?aba=meus-dados" class="<?= $aba === 'meus-dados' ? 'ativo' : '' ?>">Meus Dados</a></li>
                <li><a href="?aba=publicar" class="<?= $aba === 'publicar' ? 'ativo' : '' ?>">Publicar História</a></li>
                <li><a href="?aba=historico" class="<?= $aba === 'historico' ? 'ativo' : '' ?>">Histórico</a></li>
            </ul>
        </aside>

        <div class="conteudo-aba">
            <?php
            switch ($aba) {
                case 'meus-dados':
                    $user = wp_get_current_user();
                    echo "<h2>Meus Dados</h2>";
                    echo "<p><strong>Nome:</strong> " . esc_html($user->display_name) . "</p>";
                    echo "<p><strong>Email:</strong> " . esc_html($user->user_email) . "</p>";
                    break;

                case 'publicar':
                    echo do_shortcode('[submeter_artigo]');
                    break;
                    
                case 'editar':
                    echo do_shortcode('[editar_artigo]');
                    break;

                case 'historico':
                    echo do_shortcode('[meus_artigos]');
                    break;

                default:
                    echo "<p>Bem-vindo ao seu painel.</p>";
            }
            ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
});
