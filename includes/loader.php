<?php
if (!defined('ABSPATH')) exit;

// Core
require_once MP_PLUGIN_DIR . 'includes/constants.php';
require_once MP_PLUGIN_DIR . 'includes/functions.php';
require_once MP_PLUGIN_DIR . 'includes/hooks.php';

// Post types & taxonomies
require_once MP_PLUGIN_DIR . 'src/post-types/artigo.php';
require_once MP_PLUGIN_DIR . 'src/taxonomies/colecao.php';

// Helpers
require_once MP_PLUGIN_DIR . 'helpers/colecao-helper.php';

// AJAX & Assets
require_once MP_PLUGIN_DIR . 'src/ajax/favoritos.php';

// Shortcodes - Auth
require_once MP_PLUGIN_DIR . 'src/shortcodes/auth/login.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/auth/cadastro.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/auth/menu-cabecalho.php';

// Shortcodes - Menus
require_once MP_PLUGIN_DIR . 'src/shortcodes/menus/menu-colecoes.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/menus/menu-categorias.php';

// Shortcodes - Artigos
require_once MP_PLUGIN_DIR . 'src/shortcodes/artigo/grid-artigos.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/artigo/visualizar-artigo.php';

// Shortcodes - Coleções
require_once MP_PLUGIN_DIR . 'src/shortcodes/colecoes/lista-colecoes.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/colecoes/artigos-colecao.php';

// Shortcodes - Conta (dashboard)
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/painel-usuario.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/submeter-artigo.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/editar-artigo.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/meus-artigos.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/revisar-artigos.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/todos-artigos.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/favoritos.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/conta/usuarios.php';

// Widgets
require_once MP_PLUGIN_DIR . 'src/widgets/artigos-widget.php';
require_once MP_PLUGIN_DIR . 'src/widgets/grid-mais-acessados.php';
require_once MP_PLUGIN_DIR . 'src/widgets/player-audio.php';

// Misc shortcodes
require_once MP_PLUGIN_DIR . 'src/shortcodes/misc/pagina-apoie.php';
require_once MP_PLUGIN_DIR . 'src/shortcodes/misc/vlibras.php';
