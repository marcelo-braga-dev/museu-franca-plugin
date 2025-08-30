<?php
add_shortcode('grid_artigos', function ($atts = []) {

    // ===== Atributos =====
    $a = shortcode_atts([
            'category' => '',
            'per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC',
            'show_category' => 'true',
            'show_tags' => 'true',
            'placeholder' => 'Pesquisar no acervo...',
            'title_search' => 'Pesquisar',
    ], $atts, 'grid_artigos');

    // ===== Busca =====
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

    // ===== Coleção selecionada (PRIORITÁRIA) =====
    $selected_colecao = null;
    if (!empty($_GET['colecao']) && !is_array($_GET['colecao'])) {
        $val = sanitize_text_field(wp_unslash($_GET['colecao']));
        if ($val !== '') {
            $selected_colecao = get_term_by('slug', sanitize_title($val), 'colecao');
            if (!$selected_colecao && is_numeric($val)) {
                $selected_colecao = get_term_by('id', (int)$val, 'colecao');
            }
        }
    }

    // ===== Categoria selecionada (só vale se NÃO houver coleção) =====
    $selected_term = null;
    if (!$selected_colecao) {
        if (!empty($_GET['cat']) && is_numeric($_GET['cat'])) {
            $selected_term = get_term_by('id', (int)$_GET['cat'], 'category');
        } elseif (!empty($_GET['categoria']) && !is_array($_GET['categoria'])) {
            $slug_ou_id = sanitize_text_field(wp_unslash($_GET['categoria']));
            $selected_term = get_term_by('slug', sanitize_title($slug_ou_id), 'category');
            if (!$selected_term && is_numeric($slug_ou_id)) {
                $selected_term = get_term_by('id', (int)$slug_ou_id, 'category');
            }
        }
        if (!$selected_term && !empty($a['category'])) {
            $selected_term = is_numeric($a['category'])
                    ? get_term_by('id', (int)$a['category'], 'category')
                    : get_term_by('slug', sanitize_title($a['category']), 'category');
        }
        if (!$selected_term && is_category()) {
            $selected_term = get_queried_object();
        }
    }

    // ===== Paginação =====
    $paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: 1);

    // ===== Query =====
    $args = [
            'post_type' => 'artigo',
            'post_status' => 'publish',
            'posts_per_page' => (int)$a['per_page'],
            'paged' => $paged,
            'orderby' => sanitize_key($a['orderby']),
            'order' => (strtoupper($a['order']) === 'ASC') ? 'ASC' : 'DESC',
    ];
    if ($q !== '') {
        $args['s'] = $q;
        $args['orderby'] = 'relevance';
    }

    // ===== Filtro concorrente (OU coleção OU categoria OU nenhum) =====
    if ($selected_colecao instanceof WP_Term) {
        $args['tax_query'] = [[
                'taxonomy' => 'colecao',
                'field' => 'term_id',
                'terms' => (int)$selected_colecao->term_id,
        ]];
    } elseif ($selected_term instanceof WP_Term) {
        $args['tax_query'] = [[
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => (int)$selected_term->term_id,
                'include_children' => true,
        ]];
    }
    $query = new WP_Query($args);

    // ===== Helpers (anexos) =====
    $tem_imagens = function ($post_id) {
        $imagens = (array)get_post_meta($post_id, 'imagem_adicional', false);
        $imagens = array_filter($imagens, fn($v) => !empty($v) && (int)$v > 0);
        return !empty($imagens);
    };
    $tem_youtube = function ($post_id) {
        $links_meta = (array)get_post_meta($post_id, 'youtube_link', false);
        if (!empty(array_filter($links_meta))) return true;
        $content = (string)get_post_field('post_content', $post_id);
        return (bool)preg_match('~(youtube\.com|youtu\.be)~i', $content);
    };
    $tem_pdf = function ($post_id) {
        $keys = ['pdf_files', 'pdf_arquivos', 'anexo_pdf', 'pdf_adicional', 'pdf'];
        foreach ($keys as $k) {
            $vals = (array)get_post_meta($post_id, $k, false);
            if (!empty(array_filter($vals))) return true;
        }
        $pdfs = get_children([
                'post_parent' => $post_id,
                'post_type' => 'attachment',
                'post_mime_type' => 'application/pdf',
                'numberposts' => 1,
                'fields' => 'ids',
        ]);
        if (!empty($pdfs)) return true;
        $content = (string)get_post_field('post_content', $post_id);
        return (bool)preg_match('~https?://\S+\.pdf(\b|$)~i', $content);
    };

    // ===== Helper: caminho "Pai > Filha (> ...)" =====
    $cat_path = function (WP_Term $term): string {
        $label = get_term_parents_list(
                $term->term_id,
                'category',
                ['separator' => ' > ', 'inclusive' => true, 'link' => false]
        );
        $label = trim(wp_strip_all_tags((string)$label));
        $label = rtrim($label, " >");
        return $label !== '' ? $label : $term->name;
    };

    ob_start(); ?>
    <style> :root {
            --mp-primary: #9E2B19;
            --mp-primary-weak: rgba(158, 43, 25, .12);
            --mp-ring: rgba(158, 43, 25, .18);
            --mp-text: #111827;
            --mp-muted: #4b5563;
            --mp-border: #e6e8eb;
            --mp-card: #ffffff;
            --mp-card-shadow: 0 2px 8px rgba(16, 24, 40, .06);
            --mp-card-shadow-hover: 0 14px 30px rgba(16, 24, 40, .18);
            --mp-chip-bg: #fafafa;
            --mp-chip-border: #e6e8eb;
        }

        .grid-artigos-head {
            display: grid;
            grid-template-columns:1fr auto;
            gap: 12px;
            margin: 0 0 14px;
            padding: 0 8px;
            align-items: center
        }

        @media (max-width: 640px) {
            .grid-artigos-head {
                grid-template-columns:1fr
            }
        }

        .mp-search-form {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 0
        }

        .search-wrapper::before {
            content: "\f002";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
            pointer-events: none
        }

        .search-wrapper .mp-search-input {
            width: 100%;
            display: block;
            box-sizing: border-box;
            padding-left: 2rem;
            border: 1px solid var(--mp-border);
            border-radius: 14px;
            padding-top: .65rem;
            padding-bottom: .65rem;
            padding-right: .9rem;
            font-size: .95rem;
            background: var(--mp-card);
            color: var(--mp-text);
            transition: border-color .2s, box-shadow .2s
        }

        .search-wrapper .mp-search-input:focus {
            outline: none;
            border-color: var(--mp-primary);
            box-shadow: 0 0 0 5px var(--mp-ring)
        }

        .mp-search-btn {
            border: 0;
            border-radius: 10px;
            padding: .65rem .9rem;
            font-weight: 600;
            background: var(--mp-primary);
            color: #fff;
            cursor: pointer;
            transition: transform .12s, opacity .2s
        }

        .mp-search-btn:hover {
            transform: translateY(-1px);
            opacity: .95
        }

        .badge-cat {
            justify-self: end;
            display: inline-flex;
            gap: 8px;
            background: var(--mp-primary-weak);
            color: var(--mp-primary);
            border: 1px solid var(--mp-border);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800
        }

        @media (max-width: 640px) {
            .badge-cat {
                justify-self: start
            }
        }

        .grid-artigos-container {
            display: grid;
            grid-template-columns:repeat(3, 1fr);
            gap: 18px;
            margin: 10px auto 16px;
            padding: 0 8px
        }

        @media (max-width: 768px) {
            .grid-artigos-container {
                grid-template-columns:1fr
            }
        }

        .artigo-card {
            background: var(--mp-card);
            border: 1px solid var(--mp-border);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            box-shadow: var(--mp-card-shadow);
            transition: transform .18s, box-shadow .18s, border-color .18s
        }

        .artigo-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--mp-card-shadow-hover);
            border-color: rgba(158, 43, 25, .28)
        }

        /* Capa + overlays */
        .artigo-cover {
            position: relative;
            border-radius: 10px;
            overflow: hidden
        }

        .artigo-cover img {
            width: 100%;
            aspect-ratio: 16/10;
            object-fit: cover;
            border-radius: 10px;
            display: block
        }

        /* Favorito (top-right) */
        .artigo-cover .fav-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 5
        }

        .artigo-cover .fav-overlay .mp-fav-button {
            all: unset;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--mp-border);
            cursor: pointer;
            transition: transform .15s, background .2s, box-shadow .2s;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .06)
        }

        .artigo-cover .fav-overlay .mp-fav-button:hover {
            transform: scale(1.05);
            background: #fff
        }

        .artigo-cover .fav-overlay .mp-fav-button i {
            font-size: 15px;
            color: #e11d48
        }

        /* Badges de anexos (bottom-left) */
        .artigo-cover .badges-overlay {
            position: absolute;
            left: 8px;
            bottom: 8px;
            z-index: 4;
            display: flex;
            gap: 6px
        }

        .artigo-cover .badges-overlay .anexo-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--mp-border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06)
        }

        .artigo-cover .badges-overlay .anexo-badge i {
            font-size: 13px;
            line-height: 1
        }

        .anexo-youtube {
            color: #ff0000
        }

        .anexo-pdf {
            color: #d32f2f
        }

        .anexo-imagens {
            color: #2e7d32
        }

        /* Título / resumo */
        .artigo-card h3 {
            font-size: 16px;
            margin: 2px 0 0;
            color: var(--mp-text);
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-weight: 700
        }

        .artigo-card p {
            font-size: 13px;
            color: var(--mp-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin: 0
        }

        /* Categorias e Tags como texto */
        .meta-chips {
            display: block;
            line-height: 1.2;
        }

        .chip {
            border: 0;
            background: transparent;
            padding: 0;
            border-radius: 0;
            display: inline;
            font-size: 12px;
            color: var(--mp-muted);
            white-space: normal;
            margin: 0
        }

        .chip.cat {
            font-weight: 500
        }

        .meta-chips[aria-label="Tags"] .chip {
            margin-right: 8px
        }

        .artigo-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2px
        }

        .autor-meta {
            font-size: 11px;
            color: var(--mp-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .autor-meta .avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid var(--mp-border)
        }

        .acoes-artigo {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 4px
        }

        .btn-leia {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: var(--mp-primary) !important;
            padding: 6px 8px;
            border-radius: 8px;
            transition: background .2s, transform .12s;
        }

        .btn-leia:hover {
            background: var(--mp-primary-weak);
            transform: translateX(1px)
        }

        .grid-artigos-pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 12px
        }

        .grid-artigos-pagination a, .grid-artigos-pagination span {
            padding: 8px 12px;
            border: 1px solid var(--mp-border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--mp-text);
            background: var(--mp-card);
            font-size: 13px
        }

        .grid-artigos-pagination .current {
            background: var(--mp-primary);
            color: #fff;
            border-color: var(--mp-primary)
        } </style>

    <div class="grid-artigos-head">
        <form class="mp-search-form" method="get" role="search" aria-label="Pesquisar artigos">
            <div class="search-wrapper">
                <input class="mp-search-input" type="search" name="q"
                       value="<?php echo esc_attr($q); ?>"
                       placeholder="<?php echo esc_attr($a['placeholder']); ?>"
                       aria-label="Pesquisar no acervo">
            </div>
            <?php
            // Preserva SOMENTE o filtro ativo (concorrente)
            if ($selected_colecao instanceof WP_Term) {
                echo '<input type="hidden" name="colecao" value="' . esc_attr($selected_colecao->slug) . '">';
            } elseif ($selected_term instanceof WP_Term) {
                echo '<input type="hidden" name="cat" value="' . (int)$selected_term->term_id . '">';
            } elseif (!empty($_GET['categoria']) && !is_array($_GET['categoria'])) {
                echo '<input type="hidden" name="categoria" value="' . esc_attr(sanitize_title($_GET['categoria'])) . '">';
            }
            ?>
            <button class="mp-search-btn" type="submit"><?php echo esc_html($a['title_search']); ?></button>
        </form>
    </div>

    <?php if ($selected_colecao instanceof WP_Term): ?>
        <span class="badge-col">Coleção: <?= esc_html($selected_colecao->name); ?></span>
    <?php elseif ($selected_term instanceof WP_Term): ?>
        <span class="badge-cat">Categoria: <?= esc_html($selected_term->name); ?></span>
    <?php endif; ?>

    <div class="grid-artigos-container">
        <?php
        if ($query->have_posts()):
            while ($query->have_posts()): $query->the_post();
                $post_id = get_the_ID();
                $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: 'https://via.placeholder.com/800x500?text=Sem+Imagem';
                $resumo = get_the_excerpt();
                $resumo_curto = mb_strimwidth($resumo, 0, 180, '…');
                $author_id = (int)get_post_field('post_author', $post_id);
                $author_name = get_the_author_meta('display_name', $author_id);

                $categorias_terms = [];
                if (strtolower($a['show_category']) === 'true') {
                    $categorias_terms = wp_get_post_terms($post_id, 'category', ['orderby' => 'parent', 'order' => 'ASC', 'fields' => 'all']);
                    if (is_wp_error($categorias_terms)) $categorias_terms = [];
                }

                $hasYT = $tem_youtube($post_id);
                $hasPDF = $tem_pdf($post_id);
                $hasIMG = $tem_imagens($post_id);
                ?>
                <article class="artigo-card" aria-labelledby="ttl-<?= $post_id; ?>">
                    <div class="artigo-cover">
                        <a href="<?= esc_url(get_url_artigo($post_id)); ?>" aria-label="Abrir artigo">
                            <img loading="lazy" decoding="async"
                                 src="<?= esc_url($thumb); ?>"
                                 alt="<?= esc_attr(get_the_title()); ?>">
                        </a>

                        <div class="fav-overlay">
                            <?= function_exists('mp_favorito_botao') ? mp_favorito_botao($post_id, ['variant' => 'icon']) : ''; ?>
                        </div>

                        <?php if ($hasYT || $hasPDF || $hasIMG): ?>
                            <div class="badges-overlay" aria-label="Tipos de anexos">
                                <?php if ($hasYT): ?><span class="anexo-badge" title="Possui vídeo do YouTube"><i
                                            class="fa-brands fa-youtube anexo-youtube"
                                            aria-hidden="true"></i></span><?php endif; ?>
                                <?php if ($hasPDF): ?><span class="anexo-badge" title="Possui PDF"><i
                                            class="fa-regular fa-file-pdf anexo-pdf"
                                            aria-hidden="true"></i></span><?php endif; ?>
                                <?php if ($hasIMG): ?><span class="anexo-badge" title="Possui galeria de imagens"><i
                                            class="fa-regular fa-images anexo-imagens"
                                            aria-hidden="true"></i></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= esc_url(get_url_artigo($post_id)); ?>">
                        <h3 id="ttl-<?= $post_id; ?>"
                            title="<?= esc_html(get_the_title()); ?>"><?= esc_html(get_the_title()); ?></h3>
                    </a>

<!--                    <p title="--><?php //= esc_html($resumo_curto); ?><!--">--><?php //= esc_html($resumo_curto); ?><!--</p>-->

                    <?php
                    // Categorias como "Pai > Filha"
                    $cat_labels = [];
                    foreach ($categorias_terms as $t) {
                        if (!($t instanceof WP_Term)) continue;
                        $label = $cat_path($t);
                        if ($label) $cat_labels[$label] = true;
                    }
                    if (!empty($cat_labels)): ?>
                        <div class="meta-chips" aria-label="Categorias">
                            <?php foreach (array_keys($cat_labels) as $label): ?>
                                <span class="chip cat"><?= esc_html($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="artigo-footer" style="margin-top: 15px">
                        <small class="autor-meta"><span><strong>Por:</strong> <?= esc_html($author_name); ?></span></small>
                    </div>

<!--                    <div class="acoes-artigo">-->
<!--                        <a class="btn-leia" href="--><?php //= esc_url(get_url_artigo($post_id)); ?><!--">-->
<!--                            Ler mais <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>-->
<!--                        </a>-->
<!--                    </div>-->
                </article>
            <?php endwhile;
        else:
            echo '<p style="padding:8px;">Nenhum artigo encontrado.</p>';
        endif; ?>
    </div>

    <?php
    // ===== Paginação (preserva SOMENTE o filtro ativo) =====
    if ((int)$a['per_page'] !== -1 && $query->max_num_pages > 1) {
        $add_args = [];
        if ($q !== '') $add_args['q'] = $q;

        if ($selected_colecao instanceof WP_Term) {
            $add_args['colecao'] = $selected_colecao->slug;
        } elseif ($selected_term instanceof WP_Term) {
            $add_args['cat'] = $selected_term->term_id;
        } elseif (!empty($_GET['categoria']) && !is_array($_GET['categoria'])) {
            $add_args['categoria'] = sanitize_title($_GET['categoria']);
        }

        echo '<nav class="grid-artigos-pagination" aria-label="Paginação">';
        echo paginate_links([
                'total' => $query->max_num_pages,
                'current' => $paged,
                'mid_size' => 1,
                'prev_text' => '« Anteriores',
                'next_text' => 'Próximos »',
                'add_args' => $add_args,
        ]);
        echo '</nav>';
    }

    wp_reset_postdata();
    return ob_get_clean();
});
