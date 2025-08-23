<?php
// === Walker para exibir contagem na mesma linha (badge) e apontar para página-alvo ===
if (!class_exists('MP_Walker_Category_Count')) {
    class MP_Walker_Category_Count extends Walker_Category {
        public function start_el( &$output, $category, $depth = 0, $args = [], $id = 0 ) {
            $cat_name = apply_filters('list_cats', esc_html($category->name), $category);
            $classes  = ['cat-item', 'cat-item-' . $category->term_id];
            if (!empty($args['current_category']) && intval($args['current_category']) === intval($category->term_id)) {
                $classes[] = 'current-cat';
            }

            // monta URL destino: base_url + ?categoria=slug
            $base_url = !empty($args['base_url']) ? $args['base_url'] : home_url('/');
            $href = add_query_arg('categoria', $category->slug, $base_url);

            $output .= '<li class="' . implode(' ', $classes) . '">';
            $output .= '<div class="mp-row">';
            $output .= '<a class="mp-link" href="' . esc_url($href) . '">' . $cat_name . '</a>';
            if (!empty($args['show_count'])) {
                $output .= '<span class="mp-count">' . number_format_i18n($category->count) . '</span>';
            }
            $output .= '</div>';
        }
        public function end_el( &$output, $category, $depth = 0, $args = [] ) {
            $output .= "</li>\n";
        }
    }
}

// === Shortcode do menu: [menu_categorias target="/artigos/"] ou [menu_categorias target="123"] (ID) ===
function mp_vertical_category_menu_shortcode($atts = []) {
    static $printed_css = false;

    $a = shortcode_atts([
        'count'      => 'false',
        'hide_empty' => 'false',
        'depth'      => 0,
        'exclude'    => '',
        'include'    => '',
        'target'     => '',  // URL, ID da página ou slug da página onde está o grid
    ], $atts, 'menu_categorias');

    // resolve base_url do target
    $base_url = '';
    if (!empty($a['target'])) {
        if (is_numeric($a['target'])) {
            $p = get_permalink(intval($a['target']));
            if ($p) $base_url = $p;
        } elseif (filter_var($a['target'], FILTER_VALIDATE_URL)) {
            $base_url = $a['target'];
        } else {
            // trata como slug/URL path de página
            $page = get_page_by_path(sanitize_title($a['target']));
            if ($page) {
                $p = get_permalink($page->ID);
                if ($p) $base_url = $p;
            } else {
                // fallback: assume que veio um path como "/artigos/"
                $base_url = home_url('/' . ltrim($a['target'], '/'));
            }
        }
    }
    if (empty($base_url)) {
        // fallback: página atual
        $base_url = (function_exists('get_permalink')) ? get_permalink() : home_url('/');
    }

    // marca a categoria atual com base na query ?categoria=
    $current_category = 0;
    if (!empty($_GET['categoria'])) {
        $term = get_term_by('slug', sanitize_title($_GET['categoria']), 'category');
        if (!$term && is_numeric($_GET['categoria'])) {
            $term = get_term_by('id', intval($_GET['categoria']), 'category');
        }
        if ($term instanceof WP_Term) $current_category = $term->term_id;
    }

    $show_count = (strtolower($a['count']) === 'true');
    $hide_empty = (strtolower($a['hide_empty']) === 'true');
    $depth      = intval($a['depth']);

    // cache
    $cache_key = 'mp_vert_cat_menu_' . md5(json_encode([$a['count'],$a['hide_empty'],$a['depth'],$a['exclude'],$a['include'],$base_url,$current_category]));
    $html = get_transient($cache_key);

    if ($html === false) {
        ob_start();
        echo '<div class="mp-sidebar-categorias">';
        echo '<h3 class="mp-sidebar-title">Categorias</h3>';
        echo '<ul class="mp-cat-list">';

        wp_list_categories([
            'taxonomy'        => 'category',
            'title_li'        => '',
            'show_count'      => $show_count,
            'hide_empty'      => $hide_empty,
            'depth'           => $depth,
            'hierarchical'    => true,
            'orderby'         => 'name',
            'order'           => 'ASC',
            'exclude'         => $a['exclude'],
            'include'         => $a['include'],
            'walker'          => new MP_Walker_Category_Count(),
            'current_category'=> $current_category,
            // passa base para o walker
            'base_url'        => $base_url,
        ]);

        echo '</ul>';
        echo '</div>';

        $html = ob_get_clean();
        set_transient($cache_key, $html, 12 * HOUR_IN_SECONDS);
    }

    // CSS inline (uma vez por página)
    $css = '';
    if (!$printed_css) {
        $css = '<style>
        .mp-sidebar-categorias{width:100%;background:#f7f8fa;padding:14px;border:1px solid #e6e8eb;border-radius:12px;}
        .mp-sidebar-title{margin:0 0 12px;font-size:16px;font-weight:700;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #e6e8eb;}
        .mp-cat-list{list-style:none;margin:0;padding:0}
        .mp-cat-list>li{margin:4px 0}
        .mp-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:10px;transition:background .2s,color .2s,box        -shadow .2s}
        .mp-link{text-decoration:none;color:#1f2937;flex:1 1 auto;line-height:1.25}
        .mp-count{font-size:12px;min-width:26px;padding:2px 8px;border-radius:999px;background:#e9eef5;color:#0f172a;text-align:center;flex:0 0 auto}

        /* hover/ativo com cor personalizada */
        .mp-row:hover{background:#9E2B19;color:#fff;box-shadow:0 2px 10px rgba(158,43,25,.25)}
        .mp-row:hover .mp-link{color:#fff}
        .mp-row:hover .mp-count{background:rgba(255,255,255,.25);color:#fff}

        /* Categoria atual */
        .current-cat>.mp-row{background:#9E2B19;color:#fff}
        .current-cat>.mp-row .mp-link{color:#fff}
        .current-cat>.mp-row .mp-count{background:rgba(255,255,255,.25);color:#fff}

        .mp-cat-list .children{list-style:none;margin:6px 0 0 12px;padding-left:12px;border-left:2px solid #e6e8eb}
        .mp-cat-list .children>li .mp-row{padding:7px 8px}
        </style>';
        $printed_css = true;
    }
    return $css . $html;
}
add_shortcode('menu_categorias', 'mp_vertical_category_menu_shortcode');

// limpa caches
function mp_vertical_category_menu_flush_cache() {
    global $wpdb;
    $rows = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_mp_vert_cat_menu_%'");
    foreach ($rows as $name) {
        delete_transient(str_replace('_transient_', '', $name));
    }
}
add_action('created_category', 'mp_vertical_category_menu_flush_cache');
add_action('edited_category',  'mp_vertical_category_menu_flush_cache');
add_action('delete_category',  'mp_vertical_category_menu_flush_cache');
