<?php
// ====== Shortcode do menu de Coleções: [menu_colecoes target="/artigos/"] ======
// Atributos:
// - count:         "true"|"false"  (mostrar contagem)
// - hide_empty:    "false"|"true"  (esconde termos com 0 artigos na contagem custom)
// - exclude:       CSV de IDs de termos para excluir
// - include:       CSV de IDs de termos para incluir
// - target:        URL, ID da página, slug da página (onde está seu grid)
// - label_all:     rótulo do item “Todas as Coleções”
// - preserve_args: CSV de query-strings para manter (ex.: "q,aba,categoria,cat")
function mp_menu_colecoes_shortcode($atts = []) {
    static $printed_css = false;

    $a = shortcode_atts([
            'count'         => 'true',
            'hide_empty'    => 'false',
            'exclude'       => '',
            'include'       => '',
            'target'        => '',
            'preserve_args' => 'q,aba,categoria,cat',
    ], $atts, 'menu_colecoes');

    // ===== Resolve base_url =====
    $base_url = '';
    if (!empty($a['target'])) {
        if (is_numeric($a['target'])) {
            $p = get_permalink((int)$a['target']);
            if ($p) $base_url = $p;
        } elseif (filter_var($a['target'], FILTER_VALIDATE_URL)) {
            $base_url = $a['target'];
        } else {
            $page = get_page_by_path(sanitize_title($a['target']));
            $base_url = $page ? get_permalink($page->ID) : home_url('/' . ltrim($a['target'], '/'));
        }
    }
    if (!$base_url) {
        $base_url = function_exists('get_permalink') ? get_permalink() : home_url('/');
    }

    // ===== Coleção atual (?colecao=slug|id) =====
    $current_term_id = 0;
    if (isset($_GET['colecao']) && !is_array($_GET['colecao'])) {
        $val  = sanitize_text_field(wp_unslash($_GET['colecao']));
        if ($val !== '') {
            $term = get_term_by('slug', sanitize_title($val), 'colecao');
            if (!$term && is_numeric($val)) $term = get_term_by('id', (int)$val, 'colecao');
            if ($term instanceof WP_Term) $current_term_id = (int)$term->term_id;
        }
    }

    $show_count = (strtolower($a['count']) === 'true');
    $hide_empty = (strtolower($a['hide_empty']) === 'true');

    // ===== Termos de 'colecao' =====
    $args_terms = [
            'taxonomy'   => 'colecao',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
    ];
    if (!empty($a['exclude'])) $args_terms['exclude'] = array_map('intval', array_filter(array_map('trim', explode(',', $a['exclude']))));
    if (!empty($a['include'])) $args_terms['include'] = array_map('intval', array_filter(array_map('trim', explode(',', $a['include']))));

    $terms = get_terms($args_terms);
    if (is_wp_error($terms)) $terms = [];

    // ===== Contagem custom por coleção (CPT 'artigo') com cache =====
    $cache_key  = 'mp_colecao_counts_artigo';
    $counts_map = get_transient($cache_key);

    if ($counts_map === false) {
        $counts_map = [];
        foreach ($terms as $t) {
            $q = new WP_Query([
                    'post_type'      => 'artigo',
                    'post_status'    => 'publish',
                    'tax_query'      => [[
                            'taxonomy' => 'colecao',
                            'field'    => 'term_id',
                            'terms'    => (int)$t->term_id,
                    ]],
                    'fields'         => 'ids',
                    'posts_per_page' => 1,
                    'no_found_rows'  => false,
            ]);
            $counts_map[(int)$t->term_id] = (int)$q->found_posts;
            wp_reset_postdata();
        }
        set_transient($cache_key, $counts_map, 10 * MINUTE_IN_SECONDS);
    }

    // Total geral (artigos publicados)
    $obj_counts    = wp_count_posts('artigo');
    $total_artigos = $obj_counts && isset($obj_counts->publish) ? (int)$obj_counts->publish : 0;

    // ===== Preservar parâmetros =====
    $preserve_keys = array_filter(array_map('trim', explode(',', (string)$a['preserve_args'])));
    $preserved     = [];
    foreach ($preserve_keys as $k) {
        if (isset($_GET[$k]) && !is_array($_GET[$k])) {
            $v = sanitize_text_field(wp_unslash($_GET[$k]));
            if ($v !== '') $preserved[$k] = $v;
        }
    }

    // "Todas as Coleções": remove colecao e paged (mantém demais preservados)
    $link_all = remove_query_arg(['colecao','paged'], $base_url);
    if (!empty($preserved)) $link_all = add_query_arg($preserved, $link_all);

    // Para links de coleção: REMOVER categoria/cat (concorrente)
    $preserved_no_cat = $preserved;
    unset($preserved_no_cat['categoria'], $preserved_no_cat['cat']);

    // ===== Render =====
    ob_start(); ?>
    <div class="mp-sidebar-colecoes" role="navigation" aria-label="Coleções">
        <h3 class="mp-sidebar-title">Coleções</h3>
        <ul class="mp-col-list">
            <?php foreach ($terms as $t):
                $count = isset($counts_map[$t->term_id]) ? (int)$counts_map[$t->term_id] : (int)$t->count;
                if ($hide_empty && $count === 0) continue;

                // link para a coleção (limpa cat/categoria e paginação)
                $href = remove_query_arg(['paged'], $base_url);
                $href = add_query_arg(array_merge($preserved_no_cat, ['colecao' => $t->slug]), $href);

                $is_current = ((int)$current_term_id === (int)$t->term_id);
                ?>
                <li class="<?php echo $is_current ? 'current-col' : ''; ?>">
                    <a class="mp-link" href="<?php echo esc_url($href); ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
                        <div class="mp-row">
                            <?php echo esc_html($t->name); ?>
                            <?php if ($show_count): ?>
                                <span class="mp-count"><?php echo number_format_i18n($count); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    $html = ob_get_clean();

    // ===== CSS uma vez =====
    $css = '';
    if (!$printed_css) {
        $css = '<style>
        .mp-sidebar-colecoes{width:100%;background:#f7f8fa;padding:14px;border:1px solid #e6e8eb;border-radius:12px;}
        .mp-sidebar-title{margin:0 0 12px;font-size:14px;font-weight:700;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #e6e8eb;}
        .mp-col-list{list-style:none;margin:0;padding:0}
        .mp-col-list>li{margin:4px 0}
        .mp-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:10px;transition:background .2s,color .2s,box-shadow .2s}
        .mp-link{text-decoration:none;color:#1f2937;flex:1 1 auto;line-height:1.25;font-size:13px;display:block;border-radius:10px;outline:none}
        .mp-link:focus-visible .mp-row{box-shadow:0 0 0 4px rgba(158,43,25,.18)}
        .mp-count{font-size:12px;min-width:26px;padding:2px 8px;border-radius:999px;background:#e9eef5;color:#0f172a;text-align:center;flex:0 0 auto}
        .mp-row:hover{background:#9E2B19;color:#fff;box-shadow:0 2px 10px rgba(158,43,25,.25)}
        .mp-row:hover .mp-link{color:#fff}
        .mp-row:hover .mp-count{background:rgba(255,255,255,.25);color:#fff}
        .current-col > .mp-link > .mp-row{background:#9E2B19;color:#fff}
        .current-col > .mp-link{color:#fff}
        .current-col > .mp-link > .mp-row .mp-count{background:rgba(255,255,255,.25);color:#fff}
        .mp-col-list > li.current-col > .mp-link{font-weight:800}
        @media (max-width:640px){
            .mp-sidebar-colecoes{padding:12px}
            .mp-link{font-size:14px}
        }
        </style>';
        $printed_css = true;
    }

    return $css . $html;
}
add_shortcode('menu_colecoes', 'mp_menu_colecoes_shortcode');

// ===== Invalida cache quando coleções ou artigos mudarem =====
function mp_menu_colecoes_flush_cache() {
    delete_transient('mp_colecao_counts_artigo');
}
add_action('created_colecao', 'mp_menu_colecoes_flush_cache');
add_action('edited_colecao',  'mp_menu_colecoes_flush_cache');
add_action('delete_colecao',  'mp_menu_colecoes_flush_cache');
add_action('save_post_artigo','mp_menu_colecoes_flush_cache');
