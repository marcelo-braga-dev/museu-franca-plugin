<?php
// === Walker para exibir contagem (vinda do counts_map) e apontar para página-alvo ===
if (!class_exists('MP_Walker_Category_Count')) {
    class MP_Walker_Category_Count extends Walker_Category {
        public function start_el( &$output, $category, $depth = 0, $args = [], $id = 0 ) {
            $counts_map = isset($args['counts_map']) && is_array($args['counts_map']) ? $args['counts_map'] : [];
            $count = isset($counts_map[$category->term_id]) ? (int) $counts_map[$category->term_id] : (int) $category->count;

            // esconde "vazios" segundo nossa contagem customizada
            if ( !empty($args['hide_empty_custom']) && $count === 0 ) {
                return;
            }

            $cat_name = apply_filters('list_cats', esc_html($category->name), $category);
            $classes  = ['cat-item', 'cat-item-' . $category->term_id];

            $current_category = !empty($args['current_category']) ? (int) $args['current_category'] : 0;
            $is_current = $current_category === (int) $category->term_id;
            if ($is_current) {
                $classes[] = 'current-cat';
            }

            // marca ancestrais da categoria atual
            $ancestors = !empty($args['current_category_ancestors']) && is_array($args['current_category_ancestors'])
                    ? array_map('intval', $args['current_category_ancestors'])
                    : [];
            if (!$is_current && in_array((int)$category->term_id, $ancestors, true)) {
                $classes[] = 'current-cat-ancestor';
            }

            // monta URL destino: base_url + ?categoria=slug, preservando args configurados
            $base_url       = !empty($args['base_url']) ? $args['base_url'] : home_url('/');
            $preserve_keys  = !empty($args['preserve_args']) && is_array($args['preserve_args']) ? $args['preserve_args'] : [];
            $preserved      = [];

            foreach ($preserve_keys as $k) {
                if (isset($_GET[$k])) {
                    // sanitização simples para valores de query string
                    $v = is_array($_GET[$k]) ? '' : sanitize_text_field(wp_unslash($_GET[$k]));
                    if ($v !== '') $preserved[$k] = $v;
                }
            }

            // remove paginação ao mudar de categoria
            $href = remove_query_arg(['paged'], $base_url);
            $href = add_query_arg(array_merge($preserved, ['categoria' => $category->slug]), $href);

            $output .= '<li class="' . esc_attr(implode(' ', $classes)) . '">';
            $output .= '<a class="mp-link" href="' . esc_url($href) . '"' . ($is_current ? ' aria-current="page"' : '') . '>';
            $output .= '<div class="mp-row">';
            $output .= $cat_name;
            if (!empty($args['show_count'])) {
                $output .= '<span class="mp-count">' . number_format_i18n($count) . '</span>';
            }
            $output .= '</div></a>';
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
            'count'        => 'true',
            'hide_empty'   => 'false',     // usa nossa contagem (CPT artigo) p/ decidir
            'depth'        => 0,
            'exclude'      => '',
            'include'      => '',
            'target'       => '',          // URL, ID da página ou slug da página onde está o grid
            'label_all'    => 'Todas as Categorias',
            'preserve_args'=> 'q,aba',     // parâmetros de query que devem ser preservados nos links
    ], $atts, 'menu_categorias');

    // resolve base_url do target
    $base_url = '';
    if (!empty($a['target'])) {
        if (is_numeric($a['target'])) {
            $p = get_permalink((int)$a['target']);
            if ($p) $base_url = $p;
        } elseif (filter_var($a['target'], FILTER_VALIDATE_URL)) {
            $base_url = $a['target'];
        } else {
            $page = get_page_by_path(sanitize_title($a['target']));
            if ($page) {
                $p = get_permalink($page->ID);
                if ($p) $base_url = $p;
            } else {
                $base_url = home_url('/' . ltrim($a['target'], '/'));
            }
        }
    }
    if (empty($base_url)) {
        $base_url = function_exists('get_permalink') ? get_permalink() : home_url('/');
    }

    // categoria atual (?categoria=)
    $current_category = 0;
    if (isset($_GET['categoria'])) {
        $val = is_array($_GET['categoria']) ? '' : sanitize_text_field(wp_unslash($_GET['categoria']));
        if ($val !== '') {
            $term = get_term_by('slug', sanitize_title($val), 'category');
            if (!$term && is_numeric($val)) {
                $term = get_term_by('id', (int)$val, 'category');
            }
            if ($term instanceof WP_Term) $current_category = (int)$term->term_id;
        }
    }

    $show_count = (strtolower($a['count']) === 'true');
    $hide_empty = (strtolower($a['hide_empty']) === 'true');
    $depth      = (int)$a['depth'];

    // ====== CONTAGEM por categoria baseada no CPT 'artigo' + TOTAL ======
    $cache_key  = 'mp_vert_cat_counts_artigo';
    $counts_map = get_transient($cache_key);
    if ($counts_map === false) {
        $counts_map = [];
        $terms = get_terms([
                'taxonomy'     => 'category',
                'hide_empty'   => false,
                'hierarchical' => true,
                'fields'       => 'id=>slug',
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id => $slug) {
                $q = new WP_Query([
                        'post_type'      => 'artigo',
                        'post_status'    => 'publish',
                        'tax_query'      => [[
                                'taxonomy'         => 'category',
                                'field'            => 'term_id',
                                'terms'            => (int) $term_id,
                                'include_children' => true,
                        ]],
                        'fields'         => 'ids',
                        'posts_per_page' => 1,
                        'no_found_rows'  => false, // para popular found_posts
                ]);
                $counts_map[(int)$term_id] = (int) $q->found_posts;
                wp_reset_postdata();
            }
        }
        set_transient($cache_key, $counts_map, 10 * MINUTE_IN_SECONDS);
    }

    // total geral de "artigo" publicados
    $obj_counts    = wp_count_posts('artigo');
    $total_artigos = $obj_counts && isset($obj_counts->publish) ? (int)$obj_counts->publish : 0;

    // parâmetros que queremos preservar ao trocar de categoria
    $preserve_args = array_filter(array_map('trim', explode(',', (string)$a['preserve_args'])));
    $preserved     = [];
    foreach ($preserve_args as $k) {
        if (isset($_GET[$k]) && !is_array($_GET[$k])) {
            $v = sanitize_text_field(wp_unslash($_GET[$k]));
            if ($v !== '') $preserved[$k] = $v;
        }
    }

    // helper para link “Todas as categorias” (limpa filtros de categoria e paged, mantém preservados)
    $link_all = remove_query_arg(['categoria','cat','paged'], $base_url);
    if (!empty($preserved)) {
        $link_all = add_query_arg($preserved, $link_all);
    }

    // ancestrais da categoria atual (para destacar árvore)
    $current_category_ancestors = $current_category ? get_ancestors($current_category, 'category') : [];

    // ===== gera HTML (com item "Todas as categorias") =====
    ob_start();
    ?>
    <div class="mp-sidebar-categorias" role="navigation" aria-label="Categorias">
        <h3 class="mp-sidebar-title">Categorias</h3>
        <ul class="mp-cat-list">
            <li class="<?php echo $current_category ? '' : 'current-cat'; ?>">
                <a class="mp-link" href="<?php echo esc_url($link_all); ?>" <?php echo $current_category ? '' : 'aria-current="page"'; ?>>
                    <div class="mp-row">
                        <?php echo esc_html($a['label_all']); ?>
                        <?php if ($show_count): ?>
                            <span class="mp-count"><?php echo number_format_i18n($total_artigos); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <?php
            // lista hierárquica usando walker, passando counts_map e flags
            wp_list_categories([
                    'taxonomy'                   => 'category',
                    'title_li'                   => '',
                    'show_count'                 => $show_count,
                    'hide_empty'                 => false, // usaremos nossa lógica custom
                    'depth'                      => $depth,
                    'hierarchical'               => true,
                    'orderby'                    => 'name',
                    'order'                      => 'ASC',
                    'exclude'                    => $a['exclude'],
                    'include'                    => $a['include'],
                    'walker'                     => new MP_Walker_Category_Count(),
                    'current_category'           => $current_category,

                // extras que nosso walker entende:
                    'base_url'                   => $base_url,
                    'counts_map'                 => $counts_map,
                    'hide_empty_custom'          => $hide_empty,
                    'current_category_ancestors' => $current_category_ancestors,
                    'preserve_args'              => $preserve_args,
            ]);
            ?>
        </ul>
    </div>
    <?php
    $html = ob_get_clean();

    // CSS inline (uma vez por página)
    $css = '';
    if (!$printed_css) {
        $css = '<style>
        .mp-sidebar-categorias{width:100%;background:#f7f8fa;padding:14px;border:1px solid #e6e8eb;border-radius:12px;}
        .mp-sidebar-title{margin:0 0 12px;font-size:14px;font-weight:700;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #e6e8eb;}
        .mp-cat-list{list-style:none;margin:0;padding:0}
        .mp-cat-list>li{margin:4px 0}
        .mp-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:10px;transition:background .2s,color .2s,box-shadow .2s}
        .mp-link{text-decoration:none;color:#1f2937;flex:1 1 auto;line-height:1.25;font-size:13px;display:block;border-radius:10px;outline:none}
        .mp-link:focus-visible .mp-row{box-shadow:0 0 0 4px rgba(158,43,25,.18)}
        .mp-count{font-size:12px;min-width:26px;padding:2px 8px;border-radius:999px;background:#e9eef5;color:#0f172a;text-align:center;flex:0 0 auto}
        .mp-row:hover{background:#9E2B19;color:#fff;box-shadow:0 2px 10px rgba(158,43,25,.25)}
        .mp-row:hover .mp-link{color:#fff}
        .mp-row:hover .mp-count{background:rgba(255,255,255,.25);color:#fff}

        /* Atual: fundo + texto branco já existiam; acrescentamos negrito */
        .current-cat > .mp-link > .mp-row{background:#9E2B19;color:#fff}
        .current-cat > .mp-link{color:#fff}
        .current-cat > .mp-link > .mp-row .mp-count{background:rgba(255,255,255,.25);color:#fff}
        .mp-cat-list > li.current-cat > .mp-link{font-weight:800}

        /* Ancestral da atual (destaque sutil) */
        .mp-cat-list > li.current-cat-ancestor > .mp-link{font-weight:700;color:#0f172a}
        .mp-cat-list > li.current-cat-ancestor > .mp-link > .mp-row{background:#f2f4f7}

        .mp-cat-list .children{list-style:none;margin:6px 0 0 12px;padding-left:12px;border-left:2px solid #e6e8eb}
        .mp-cat-list .children>li .mp-row{padding:7px 8px}

        @media (max-width:640px){
            .mp-sidebar-categorias{padding:12px}
            .mp-link{font-size:14px}
        }
        </style>';
        $printed_css = true;
    }

    return $css . $html;
}
add_shortcode('menu_categorias', 'mp_vertical_category_menu_shortcode');

// limpa caches ao mudar categorias OU posts do CPT 'artigo'
function mp_vertical_category_menu_flush_cache() {
    delete_transient('mp_vert_cat_counts_artigo');
}
add_action('created_category', 'mp_vertical_category_menu_flush_cache');
add_action('edited_category',  'mp_vertical_category_menu_flush_cache');
add_action('delete_category',  'mp_vertical_category_menu_flush_cache');
add_action('save_post_artigo', 'mp_vertical_category_menu_flush_cache'); // invalida ao publicar/editar artigos
