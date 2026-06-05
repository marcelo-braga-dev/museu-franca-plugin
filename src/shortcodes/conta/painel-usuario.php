<?php
if (!defined('ABSPATH')) exit;

add_shortcode('painel_usuario', function () {
    if (!is_user_logged_in()) {
        wp_safe_redirect(mp_url_login());
        exit;
    }

    $aba = isset($_GET['aba']) ? sanitize_key($_GET['aba']) : 'publicar';

    $linkAba = function (string $slug) {
        return esc_url(add_query_arg('aba', $slug));
    };

    ob_start(); ?>
    <style>
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100dvh;width:100%;margin:0 auto;align-items:stretch;background:#fff;}
        .menu-lateral{background:#fff;border-right:1px solid #e5e5e5;padding:12px 8px;overflow-y:auto;}
        .menu-lateral ul{list-style:none;margin:0;padding:0;}
        .menu-lateral li{margin:8px 0;}
        .menu-lateral a{display:flex;align-items:center;gap:20px;text-decoration:none;color:#333;font-size:16px;padding:10px 10px;border-radius:8px;transition:background .2s ease,color .2s ease;}
        .menu-lateral a i{color:#9E2B19;font-size:20px;flex:0 0 auto;}
        .menu-lateral a:hover{background:#f6f6f6;}
        .menu-lateral a.ativo{color:#9E2B19;font-weight:600;}
        .menu-close{display:none;width:100%;align-items:center;justify-content:center;gap:10px;border:1px solid #e5e5e5;background:#fff;color:#333;border-radius:8px;padding:10px 12px;margin:4px 0 10px;cursor:pointer;}
        .menu-close i{color:#9E2B19;}
        .conteudo-aba{padding:24px;overflow:hidden;}
        .conteudo-aba h2{color:#992d17;margin-top:0;}
        @media(max-width:768px){
            .layout{grid-template-columns:64px 1fr;}
            .menu-lateral{position:sticky;top:0;height:100dvh;width:64px;padding:12px 4px;}
            .menu-lateral a{justify-content:center;gap:0;}
            .menu-lateral a .label{display:none;}
            .layout.menu-open{grid-template-columns:260px 0;}
            .layout.menu-open .menu-lateral{width:260px;padding:12px 8px;}
            .layout.menu-open .menu-lateral a{justify-content:flex-start;gap:20px;}
            .layout.menu-open .menu-lateral a .label{display:inline;white-space:nowrap;}
            .layout.menu-open .menu-close{display:flex;}
            .layout.menu-open #painel-conteudo{display:none;}
        }
        @media(min-width:769px){
            .menu-lateral{position:sticky;top:16px;height:calc(100vh - 32px);margin:16px;border-radius:12px;}
            .conteudo-aba{padding:30px;}
        }
    </style>

    <div class="layout" id="painel-layout" aria-live="polite">
        <aside id="menu-lateral" class="menu-lateral" aria-label="Menu do usuário">
            <button type="button" class="menu-close" id="menu-close-btn" aria-label="Fechar menu">
                <i class="fa fa-chevron-left" aria-hidden="true"></i>
                <span>Fechar menu</span>
            </button>
            <ul>
                <li>
                    <a href="<?php echo $linkAba('publicar'); ?>" class="<?php echo $aba === 'publicar' ? 'ativo' : ''; ?>" title="Publicar História">
                        <i class="fa-regular fa-edit" aria-hidden="true"></i>
                        <span class="label">Publicar História</span>
                    </a>
                </li>
                <?php if (current_user_can('administrator')): ?>
                    <li>
                        <a href="<?php echo $linkAba('revisao'); ?>" class="<?php echo $aba === 'revisao' ? 'ativo' : ''; ?>" title="Publicações Para Revisão">
                            <i class="fa fa-check" aria-hidden="true"></i>
                            <span class="label">Publicações Para Revisão</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $linkAba('todos_artigos'); ?>" class="<?php echo $aba === 'todos_artigos' ? 'ativo' : ''; ?>" title="Todas Publicações">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <span class="label">Todas Publicações</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $linkAba('usuarios'); ?>" class="<?php echo $aba === 'usuarios' ? 'ativo' : ''; ?>" title="Usuários">
                            <i class="fa fa-users" aria-hidden="true"></i>
                            <span class="label">Usuários</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo $linkAba('favoritos'); ?>" class="<?php echo $aba === 'favoritos' ? 'ativo' : ''; ?>" title="Favoritos">
                        <i class="far fa-heart" aria-hidden="true"></i>
                        <span class="label">Favoritos</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $linkAba('historico'); ?>" class="<?php echo $aba === 'historico' ? 'ativo' : ''; ?>" title="Histórico">
                        <i class="fa fa-history" aria-hidden="true"></i>
                        <span class="label">Minhas Publicações</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $linkAba('meus-dados'); ?>" class="<?php echo $aba === 'meus-dados' ? 'ativo' : ''; ?>" title="Meus Dados">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <span class="label">Meus Dados</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="conteudo-aba" id="painel-conteudo" role="region" aria-label="Conteúdo do painel">
            <?php
            switch ($aba) {
                case 'meus-dados':
                    $user = wp_get_current_user();
                    echo '<h2>Meus Dados</h2>';
                    echo '<p><strong>Nome:</strong> ' . esc_html($user->display_name) . '</p>';
                    echo '<p><strong>Email:</strong> ' . esc_html($user->user_email) . '</p>';
                    break;
                case 'publicar':
                    echo do_shortcode('[submeter_artigo]');
                    break;
                case 'favoritos':
                    echo do_shortcode('[grid_favoritos]');
                    break;
                case 'usuarios':
                    if (current_user_can('manage_options')) {
                        echo do_shortcode('[lista_usuarios per_page="24" post_type="artigo"]');
                    } else {
                        echo '<p>Você não tem permissão para acessar esta área.</p>';
                    }
                    break;
                case 'revisao':
                    if (current_user_can('administrator')) {
                        echo do_shortcode('[revisar_artigos]');
                    } else {
                        echo '<p>Você não tem permissão para acessar esta área.</p>';
                    }
                    break;
                case 'todos_artigos':
                    if (current_user_can('administrator')) {
                        echo do_shortcode('[todos_artigos]');
                    } else {
                        echo '<p>Você não tem permissão para acessar esta área.</p>';
                    }
                    break;
                case 'editar':
                    echo do_shortcode('[editar_artigo]');
                    break;
                case 'historico':
                    echo do_shortcode('[meus_artigos]');
                    break;
                default:
                    echo '<p>Bem-vindo ao seu painel.</p>';
            }
            ?>
        </main>
    </div>

    <script>
    (function(){
        var layout=document.getElementById('painel-layout'),aside=document.getElementById('menu-lateral'),closeBtn=document.getElementById('menu-close-btn');
        if(!layout||!aside)return;
        var isMobile=function(){return window.matchMedia('(max-width:768px)').matches;};
        function openMenu(){if(isMobile())layout.classList.add('menu-open');}
        function closeMenu(){if(isMobile())layout.classList.remove('menu-open');}
        aside.addEventListener('click',function(e){if(!isMobile())return;var clickedLink=e.target.closest('a');if(!clickedLink&&!layout.classList.contains('menu-open'))openMenu();});
        aside.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(e){if(!isMobile())return;if(!layout.classList.contains('menu-open')){e.preventDefault();openMenu();}});});
        if(closeBtn)closeBtn.addEventListener('click',function(e){e.stopPropagation();closeMenu();});
        document.addEventListener('keydown',function(e){if(e.key==='Escape'&&isMobile())closeMenu();});
        window.addEventListener('resize',function(){if(!isMobile())layout.classList.remove('menu-open');});
    })();
    </script>
    <?php
    return ob_get_clean();
});
