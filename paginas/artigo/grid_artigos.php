<?php
add_shortcode('grid_artigos', function ($atts = []) {
    // Atributos opcionais
    $a = shortcode_atts([
            'category' => '',     // slug OU ID da categoria
            'per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC',
            'show_category' => 'true', // exibe as categorias no card
            'show_tags' => 'true', // exibe as tags no card
            'placeholder' => 'Pesquisar no acervo...', // placeholder do input
            'title_search' => 'Pesquisar',              // rótulo do botão
    ], $atts, 'grid_artigos');

    // Termo de busca (?q=texto)
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

    // Descobre a categoria selecionada (URL > shortcode > arquivo de categoria)
    $selected_term = null;

    // 1) ?cat=ID (padrão WP) OU ?categoria=slug
    if (!empty($_GET['cat']) && is_numeric($_GET['cat'])) {
        $selected_term = get_term_by('id', intval($_GET['cat']), 'category');
    } elseif (!empty($_GET['categoria'])) {
        $selected_term = get_term_by('slug', sanitize_title($_GET['categoria']), 'category');
        if (!$selected_term && is_numeric($_GET['categoria'])) {
            $selected_term = get_term_by('id', intval($_GET['categoria']), 'category');
        }
    }

    // 2) atributo do shortcode
    if (!$selected_term && !empty($a['category'])) {
        if (is_numeric($a['category'])) {
            $selected_term = get_term_by('id', intval($a['category']), 'category');
        } else {
            $selected_term = get_term_by('slug', sanitize_title($a['category']), 'category');
        }
    }

    // 3) arquivo de categoria
    if (!$selected_term && is_category()) {
        $selected_term = get_queried_object(); // term (category)
    }

    // Paginação (suporta /page/2 e ?paged=2)
    $paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: 1);

    // Monta a WP_Query
    $args = [
            'post_type' => 'artigo',
            'post_status' => 'publish',
            'posts_per_page' => intval($a['per_page']),
            'paged' => $paged,
            'orderby' => sanitize_key($a['orderby']),
            'order' => (strtoupper($a['order']) === 'ASC') ? 'ASC' : 'DESC',
    ];

    // Busca por texto (relevância nativa do WP)
    // Busca por texto (título, conteúdo, excerpt)
    if ($q !== '') {
        $args['s'] = $q;           // já procura no TÍTULO e conteúdo
        $args['orderby'] = 'relevance';
    }

    // Filtro por categoria (se houver)
    if ($selected_term instanceof WP_Term) {
        $args['tax_query'] = [[
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $selected_term->term_id,
                'include_children' => true,
        ]];
    }

    $query = new WP_Query($args);

    ob_start();
    ?>
    <style>
        @media (max-width: 640px) {
            .grid-artigos-container {
                grid-template-columns: 1fr;
                justify-content: stretch;
            }
        }

        .grid-artigos-head {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0 0 10px
        }

        .mp-search-form {
            display: flex;
            gap: 8px;
            align-items: center
        }

        .mp-search-input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 24px !important;
            padding: .6rem 1rem;
            font-size: 1rem;
            background: #fff
        }

        .mp-search-btn {
            border: none;
            border-radius: 24px;
            padding: .6rem 1rem;
            font-weight: 600;
            background: #111827;
            color: #fff;
            cursor: pointer
        }

        .grid-artigos-container {
            display: grid;
            grid-template-columns:repeat(auto-fit, minmax(250px, 280px));
            gap: 20px;
            margin: 10px auto 18px;
            max-width: 100%;
        }

        .artigo-card {
            background: #fff;
            border: 1px solid #e6e8eb;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(16, 24, 40, .04);
            transition: transform .2s, box-shadow .2s;
        }

        .artigo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(16, 24, 40, .08)
        }

        .artigo-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #f2f4f7
        }

        .artigo-card h3 {
            font-size: 16px;
            margin: 8px 0 6px;
            color: #111827;
            line-height: 1.25
        }

        .artigo-card p {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 8px
        }

        .artigo-card .meta {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px
        }

        .artigo-card a.btn-leia {
            text-align: end;
            margin-top: auto;
            font-size: 13px;
            font-weight: 600;
            color: #9E2B19;
            text-decoration: none;
            padding: 8px 0
        }

        .artigo-card a.btn-leia:hover {
            text-decoration: underline
        }

        .grid-artigos-head .badge-cat{
            align-self: flex-start;     /* não estica no flex container */
            display: inline-flex;
            gap: 8px;
            background: rgba(158,43,25,.13);
            color: #9E2B19;
            border: 1px solid #e0e7ff;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;        /* não quebra linha */
            flex: 0 0 auto;             /* garante tamanho do conteúdo */
            width: auto;                /* redundante, mas ajuda contra overrides */
        }


        .grid-artigos-pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin-top: 8px
        }

        .grid-artigos-pagination a, .grid-artigos-pagination span {
            padding: 6px 10px;
            border: 1px solid #e6e8eb;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            font-size: 13px
        }

        .grid-artigos-pagination .current {
            background: #9E2B19;
            color: #fff;
            border-color: #9E2B19
        }
    </style>

    <div class="grid-artigos-head">
        <!-- Campo de pesquisa -->
        <form class="mp-search-form" method="get">
            <input class="mp-search-input" type="search" name="q"
                   value="<?php echo esc_attr($q); ?>"
                   placeholder="<?php echo esc_attr($a['placeholder']); ?>"
                   aria-label="Pesquisar artigos"/>
            <?php
            // preserva parâmetros de categoria ao pesquisar
            if ($selected_term instanceof WP_Term) {
                echo '<input type="hidden" name="cat" value="' . intval($selected_term->term_id) . '">';
            } elseif (!empty($_GET['categoria'])) {
                echo '<input type="hidden" name="categoria" value="' . esc_attr(sanitize_title($_GET['categoria'])) . '">';
            }
            ?>
            <button class="mp-search-btn" type="submit"><?php echo esc_html($a['title_search']); ?></button>
        </form>
        <?php if ($selected_term instanceof WP_Term): ?>
            <span class="badge-cat">Categoria: <?= esc_html($selected_term->name); ?></span>
        <?php endif; ?>

    </div>


    <div class="grid-artigos-container">
        <?php
        if ($query->have_posts()):
            while ($query->have_posts()): $query->the_post();
                $post_id = get_the_ID();
                $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: 'https://via.placeholder.com/600x400?text=Sem+Imagem';
                $resumo = get_the_excerpt();
                $resumo_curto = mb_strimwidth($resumo, 0, 90, '…');

                $categorias = [];
                $tags = [];

                if (strtolower($a['show_category']) === 'true') {
                    $categorias = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
                }
                if (strtolower($a['show_tags']) === 'true') {
                    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
                }
                ?>
                <div class="artigo-card">
                    <a href="<?= esc_url(get_url_artigo($post_id)); ?>">
                        <img src="<?= esc_url($thumb); ?>" alt="<?= esc_attr(get_the_title()); ?>">
                    </a>
                    <a href="<?= esc_url(get_url_artigo($post_id)); ?>">
                        <h3><?= esc_html(get_the_title()); ?></h3>
                    </a>

                    <p><?= esc_html($resumo_curto); ?></p>

                    <?php if (!empty($categorias)): ?>
                        <div class="meta"><strong>Categoria:</strong> <?= esc_html(implode(', ', $categorias)); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($tags)):
                        $tags_formatadas = array_map(function ($tag) {
                            return '#' . esc_html($tag);
                        }, $tags); ?>
                        <div class="meta"><?= implode(', ', $tags_formatadas); ?></div>
                    <?php endif; ?>
                    <a class="btn-leia" href="<?= esc_url(get_url_artigo($post_id)); ?>">Ler mais</a>
                </div>
            <?php
            endwhile;
        else:
            echo '<p>Nenhum artigo encontrado.</p>';
        endif;
        ?>
    </div>

    <?php
    // Paginação (se per_page != -1), preservando q e categoria
    if (intval($a['per_page']) !== -1 && $query->max_num_pages > 1) {
        $add_args = [];
        if ($q !== '') $add_args['q'] = $q;
        if ($selected_term instanceof WP_Term) {
            $add_args['cat'] = $selected_term->term_id;
        } elseif (!empty($_GET['categoria'])) {
            $add_args['categoria'] = sanitize_title($_GET['categoria']);
        }

        echo '<div class="grid-artigos-pagination">';
        echo paginate_links([
                'total' => $query->max_num_pages,
                'current' => $paged,
                'mid_size' => 1,
                'prev_text' => '« Anteriores',
                'next_text' => 'Próximos »',
                'add_args' => $add_args,
        ]);
        echo '</div>';
    }

    wp_reset_postdata();
    return ob_get_clean();
});
