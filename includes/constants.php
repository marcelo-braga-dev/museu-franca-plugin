<?php
if (!defined('ABSPATH')) exit;

define('MP_VERSION',        '2.0.0');
define('MP_PLUGIN_DIR',     plugin_dir_path(dirname(__FILE__)));
define('MP_PLUGIN_URL',     plugin_dir_url(dirname(__FILE__)));

// Meta keys — mantêm os nomes legados, onde os dados já estão gravados
define('MP_META_VIEWS',     '_views');
define('MP_META_FEATURED',  'mp_featured');
define('MP_META_IMG',       'imagem_adicional');
define('MP_META_PDF',       'pdf');
define('MP_META_AUDIO',     'audio');
define('MP_META_YOUTUBE',   'youtube_link');
define('MP_META_FAVORITOS', 'mp_favoritos');
define('MP_META_LAST_LOGIN','mp_last_login');

// Slugs de páginas configuráveis
define('MP_SLUG_CONTA',     'minha-conta');
define('MP_SLUG_LOGIN',     'minha-conta/login');
define('MP_SLUG_CADASTRO',  'minha-conta/cadastro');
