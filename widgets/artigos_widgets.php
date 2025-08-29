<?php
// ===== Shortcode [widgets_artigos] =====
// Exemplos:
// [widgets_artigos mode="most_viewed" count="6" title="Mais acessados" meta_views="_views"]
// [widgets_artigos mode="featured" count="6" title="Destaques"]
// [widgets_artigos mode="recent" count="6" title="Últimos publicados"]
// [widgets_artigos mode="category" count="8" cat="historia" title="História"]
// [widgets_artigos mode="tag" count="8" tag="entrevista" title="Entrevistas"]
// [widgets_artigos mode="related_mix" count="6" post_id="auto" title="Sugeridos pra você"]

add_shortcode('widgets_artigos', function ($atts = []) {
    $a = shortcode_atts([
            'mode' => 'recent',          // related_auto|related_category|related_parent|related_tags|related_mix|most_viewed|featured|recent|category|tag
            'count' => 20,
            'post_id' => '',                // "auto" = usa $_GET['id'] atual
            'title' => '',
            'meta_views' => '_views',          // para most_viewed
            'tax' => '',                // p/ featured por taxonomia (ex.: category)
            'term' => '',                // p/ featured por taxonomia (ex.: destaques)
            'cat' => '',                // quando mode=category (slug ou id)
            'tag' => '',                // quando mode=tag (slug ou id)
    ], $atts, 'widgets_artigos');

    $mode = sanitize_key($a['mode']);
    $limit = min(20, max(1, intval($a['count']))); // <= LIMITE ABSOLUTO 10
    $title = sanitize_text_field($a['title']);

    // ===== Determinar post base quando for "related_*" =====
    $pid = 0;
    if (in_array($mode, ['related_auto', 'related_category', 'related_parent', 'related_tags', 'related_mix'], true)) {
        if (strtolower($a['post_id']) === 'auto' && isset($_GET['id'])) {
            $pid = intval($_GET['id']);
        } elseif (is_numeric($a['post_id'])) {
            $pid = intval($a['post_id']);
        }
        if ($pid <= 0) return '';
    }

    // ===== Função auxiliar p/ relacionados (se ainda não existir) =====
    if (!function_exists('mp_widgets_collect_related')) {
        function mp_widgets_collect_related($post_id, $count, $flavor = 'auto')
        {
            $count = max(1, intval($count));
            $picked = [];
            $exclude = [$post_id];

            $terms_cat = wp_get_post_terms($post_id, 'category', ['fields' => 'all']);
            $cat_ids = array_map(fn($t) => intval($t->term_id), $terms_cat);
            $parent_ids = array_values(array_unique(array_filter(array_map(fn($t) => intval($t->parent), $terms_cat))));
            $tag_ids = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);

            $runner = function ($tax_query) use (&$picked, $exclude, $count) {
                $need = $count - count($picked);
                if ($need <= 0) return;
                $q = new WP_Query([
                        'post_type' => 'artigo',
                        'post_status' => 'publish',
                        'posts_per_page' => $need,
                        'post__not_in' => array_merge($exclude, $picked),
                        'tax_query' => $tax_query ?: [],
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                ]);
                while ($q->have_posts()) {
                    $q->the_post();
                    $picked[] = get_the_ID();
                }
                wp_reset_postdata();
            };

            $tx_cat = $cat_ids ? [['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $cat_ids, 'include_children' => false]] : [];
            $tx_par = $parent_ids ? [['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $parent_ids, 'include_children' => true]] : [];
            $tx_tag = $tag_ids ? [['taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => $tag_ids]] : [];

            switch ($flavor) {
                case 'category':
                    $runner($tx_cat);
                    break;
                case 'parent':
                    $runner($tx_par);
                    break;
                case 'tags':
                    $runner($tx_tag);
                    break;
                case 'mix':
                    if ($tx_cat && $tx_tag) $runner(array_merge($tx_cat, $tx_tag)); // AND
                    if (count($picked) < $count) $runner($tx_cat);
                    if (count($picked) < $count) $runner($tx_tag);
                    break;
                case 'auto':
                default:
                    $runner($tx_cat);
                    if (count($picked) < $count) $runner($tx_par);
                    if (count($picked) < $count) $runner($tx_tag);
                    break;
            }

            if (count($picked) < $count) {
                $q = new WP_Query([
                        'post_type' => 'artigo',
                        'post_status' => 'publish',
                        'posts_per_page' => $count - count($picked),
                        'post__not_in' => array_merge($exclude, $picked),
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                ]);
                while ($q->have_posts()) {
                    $q->the_post();
                    $picked[] = get_the_ID();
                }
                wp_reset_postdata();
            }
            return $picked;
        }
    }

    // ===== Montar lista de IDs conforme o modo =====
    $ids = [];
    switch ($mode) {
        case 'related_auto':
            $ids = mp_widgets_collect_related($pid, $limit, 'auto');
            break;
        case 'related_category':
            $ids = mp_widgets_collect_related($pid, $limit, 'category');
            break;
        case 'related_parent':
            $ids = mp_widgets_collect_related($pid, $limit, 'parent');
            break;
        case 'related_tags':
            $ids = mp_widgets_collect_related($pid, $limit, 'tags');
            break;
        case 'related_mix':
            $ids = mp_widgets_collect_related($pid, $limit, 'mix');
            break;

        case 'most_viewed':
            $q = new WP_Query([
                    'post_type' => 'artigo',
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'meta_key' => sanitize_key($a['meta_views']),
                    'orderby' => 'meta_value_num',
                    'order' => 'DESC',
                    'no_found_rows' => true,
            ]);
            while ($q->have_posts()) {
                $q->the_post();
                $ids[] = get_the_ID();
            }
            wp_reset_postdata();
            break;

        case 'featured':
        {
            $tax = sanitize_key($a['tax']);
            $term = sanitize_text_field($a['term']);
            $args = [
                    'post_type' => 'artigo',
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'no_found_rows' => true,
                    'meta_query' => [['key' => 'mp_featured', 'value' => '1']],
            ];
            if ($tax && $term) {
                $args['tax_query'] = [['taxonomy' => $tax, 'field' => is_numeric($term) ? 'term_id' : 'slug', 'terms' => $term]];
            }
            $q = new WP_Query($args);
            while ($q->have_posts()) {
                $q->the_post();
                $ids[] = get_the_ID();
            }
            wp_reset_postdata();
            break;
        }

        case 'category':
        {
            $term = $a['cat'];
            if ($term !== '') {
                $q = new WP_Query([
                        'post_type' => 'artigo',
                        'post_status' => 'publish',
                        'posts_per_page' => $limit,
                        'tax_query' => [[
                                'taxonomy' => 'category',
                                'field' => is_numeric($term) ? 'term_id' : 'slug',
                                'terms' => is_numeric($term) ? intval($term) : sanitize_title($term),
                        ]],
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                ]);
                while ($q->have_posts()) {
                    $q->the_post();
                    $ids[] = get_the_ID();
                }
                wp_reset_postdata();
            }
            break;
        }

        case 'tag':
        {
            $term = $a['tag'];
            if ($term !== '') {
                $q = new WP_Query([
                        'post_type' => 'artigo',
                        'post_status' => 'publish',
                        'posts_per_page' => $limit,
                        'tax_query' => [[
                                'taxonomy' => 'post_tag',
                                'field' => is_numeric($term) ? 'term_id' : 'slug',
                                'terms' => is_numeric($term) ? intval($term) : sanitize_title($term),
                        ]],
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                ]);
                while ($q->have_posts()) {
                    $q->the_post();
                    $ids[] = get_the_ID();
                }
                wp_reset_postdata();
            }
            break;
        }

        case 'recent':
        default:
            $q = new WP_Query([
                    'post_type' => 'artigo',
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'no_found_rows' => true,
            ]);
            while ($q->have_posts()) {
                $q->the_post();
                $ids[] = get_the_ID();
            }
            wp_reset_postdata();
            break;
    }

    if (empty($ids)) return '';
    $ids = array_slice($ids, 0, $limit);

    // id único para permitir múltiplos carrosseis na mesma página
    $uid = 'wa_' . wp_generate_password(6, false, false);

    ob_start();
    ?>
    <style>
        /* Cabeçalho */
        .wa-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 12px
        }

        .wa-title i {
            font-size: 22px;
            color: #9E2B19
        }

        /* ===== Carrossel (scroll-snap, responsivo) ===== */
        .wa-carousel {
            position: relative
        }

        .wa-track {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: clamp(180px, 26vw, 180px); /* largura de cada slide (auto-ajusta quantos cabem) */
            gap: 16px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding: 6px 4px 12px;
            scroll-behavior: smooth;
        }

        .wa-track::-webkit-scrollbar {
            height: 10px
        }

        .wa-track::-webkit-scrollbar-thumb {
            background: #e5e7eb;
            border-radius: 10px
        }

        .wa-slide {
            scroll-snap-align: start
        }

        /* Navegação */
        .wa-nav {
            position: absolute;
            inset: 0;
            pointer-events: none
        }

        .wa-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid #9E2B19;
            background: #9E2B19;
            display: grid;
            place-items: center;
            box-shadow: 0 8px 18px rgba(2, 6, 23, .12);
            pointer-events: auto;
            cursor: pointer;
            font-size: 18px;
            color: white
        }

        .wa-prev {
            left: -6px
        }

        .wa-next {
            right: -6px
        }

        .wa-arrow[disabled] {
            opacity: .45;
            cursor: not-allowed
        }

        /* Card */
        .wa-card {
            position: relative;
            border: 1px solid #e6e8eb;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            transition: transform .18s, box-shadow .18s, border-color .18s;
            box-shadow: 0 6px 18px rgba(2, 6, 23, .06)
        }

        .wa-card:hover {
            transform: translateY(-2px);
            border-color: #dbe2ea;
            box-shadow: 0 16px 36px rgba(2, 6, 23, .10)
        }

        .wa-thumb-wrap {
            position: relative;
            display: block
        }

        .wa-thumb {
            display: block;
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover
        }

        .wa-icons {
            position: absolute;
            left: 8px;
            bottom: 8px;
            display: flex;
            gap: 6px
        }

        .wa-icons span {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #ffffff;
            color: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .25)
        }

        /* Favorito canto superior direito */
        .wa-fav {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 5
        }

        .wa-fav .mp-fav-button {
            all: unset;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #1e3a8a;
            color: #fff !important;
            border: 1px solid rgba(255, 255, 255, .2);
            box-shadow: 0 6px 16px rgba(0, 0, 0, .18);
            cursor: pointer
        }

        .wa-fav .mp-fav-button i {
            font-size: 18px;
            color: #fff !important
        }

        .wa-fav .mp-fav-button.is-fav {
            background: #e11d48;
            border-color: #e11d48
        }

        .wa-fav .mp-fav-button, .wa-fav .mp-fav-button * {
            line-height: 0
        }

        .wa-body {
            padding: 10px;
        }

        .wa-title-card {
            --lines: 2;
            --lh: 1.35;
            font-size: 12px;
            font-weight: 600;
            margin: 0 0 3px;
            color: #0f172a;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: var(--lh);
            -webkit-line-clamp: var(--lines);
            line-clamp: var(--lines);
            min-height: calc(var(--lines) * var(--lh) * 1em);
            max-height: calc(var(--lines) * var(--lh) * 1em);
        }

        .wa-meta {
            font-size: 11px;
            color: #6b7280
        }

        .wa-actions {
            padding: 0 12px 12px;
            text-align: end
        }

        .wa-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            font-size: 11px;
            /*padding: 6px 10px;*/
            border-radius: 10px;
            /*border: 1px solid #9E2B19;*/
            background: #fff;
            color: #9E2B19;
            text-decoration: none
        }

        .wa-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1
        }
    </style>

    <div class="wa-carousel" id="<?= esc_attr($uid) ?>">
        <?php if ($title): ?>
            <div class="wa-title"><i class="fa-solid fa-layer-group"></i><h5
                        style="margin:0;font-size:18px"><?= esc_html($title) ?></h5></div>
        <?php endif; ?>

        <div class="wa-track" id="<?= esc_attr($uid) ?>_track" role="region" aria-roledescription="carousel"
             aria-label="<?= esc_attr($title ?: 'Conteúdos') ?>">
            <?php foreach ($ids as $rid):
                $link = function_exists('get_url_artigo') ? get_url_artigo($rid) : get_permalink($rid);
                $rtitle = get_the_title($rid);
                $thumb = get_the_post_thumbnail_url($rid, 'medium_large') ?: get_the_post_thumbnail_url($rid, 'medium') ?: '';
                $cats = wp_get_post_terms($rid, 'category', ['fields' => 'names']);
                $has_img = has_post_thumbnail($rid);
                $has_vid = !empty(get_post_meta($rid, 'youtube_link'));
                $has_pdf = !empty(get_post_meta($rid, 'pdf'));
                $has_aud = !empty(get_post_meta($rid, 'audio'));
                ?>
                <div class="wa-slide">
                    <article class="wa-card">
                        <?php if ($thumb): ?>
                            <a href="<?= esc_url($link) ?>" class="wa-thumb-wrap" aria-label="<?= esc_attr($rtitle) ?>">
                                <img class="wa-thumb" src="<?= esc_url($thumb) ?>" alt="<?= esc_attr($rtitle) ?>"
                                     loading="lazy">
                                <div class="wa-icons">
                                    <?php if ($has_img): ?><span title="Imagem"><i class="fa-regular fa-image"
                                                                                   style="color:#16a34a"></i></span><?php endif; ?>
                                    <?php if ($has_vid): ?><span title="Vídeo"><i class="fa-brands fa-youtube"
                                                                                  style="color:#ef4444"></i></span><?php endif; ?>
                                    <?php if ($has_pdf): ?><span title="PDF"><i class="fa-regular fa-file-pdf"
                                                                                style="color:#b91c1c"></i></span><?php endif; ?>
                                    <?php if ($has_aud): ?><span title="Áudio"><i class="fa-solid fa-volume-high"
                                                                                  style="color:#111827"></i></span><?php endif; ?>
                                </div>
                            </a>
                            <?php if (function_exists('mp_favorito_botao')): ?>
<!--                                <div class="wa-fav">--><?php //= mp_favorito_botao($rid, ['variant' => 'icon']) ?><!--</div>-->
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="wa-body">
                            <h6 class="wa-title-card" title="<?= esc_html($rtitle) ?>">
                                <a href="<?= esc_url($link) ?>" style="color:inherit;text-decoration:none">
                                    <?= esc_html($rtitle) ?>
                                </a>
                            </h6>
                            <div class="wa-meta"><?= !empty($cats) ? esc_html($cats[0]) : '' ?></div>
                        </div>

<!--                        <div class="wa-actions">-->
<!--                            <a class="wa-btn" href="--><?php //= esc_url($link) ?><!--">-->
<!--                                Ler mais <i class="fa-solid fa-arrow-right-long"></i>-->
<!--                            </a>-->
<!--                        </div>-->
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Botões -->
        <div class="wa-nav" aria-hidden="false">
            <button class="wa-arrow wa-prev" id="<?= esc_attr($uid) ?>_prev" type="button" aria-label="Anterior"><i
                        class="fa-solid fa-chevron-left"></i></button>
            <button class="wa-arrow wa-next" id="<?= esc_attr($uid) ?>_next" type="button" aria-label="Próximo"><i
                        class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('<?= esc_js($uid) ?>');
            const track = document.getElementById('<?= esc_js($uid) ?>_track');
            const prev = document.getElementById('<?= esc_js($uid) ?>_prev');
            const next = document.getElementById('<?= esc_js($uid) ?>_next');

            if (!track) return;

            function canScrollLeft() {
                return track.scrollLeft > 10;
            }

            function canScrollRight() {
                return track.scrollLeft + track.clientWidth < track.scrollWidth - 10;
            }

            function updateArrows() {
                if (!prev || !next) return;
                prev.disabled = !canScrollLeft();
                next.disabled = !canScrollRight();
            }

            function scrollAmount() {
                return Math.max(track.clientWidth * 0.9, 260);
            }

            prev && prev.addEventListener('click', () => track.scrollBy({left: -scrollAmount(), behavior: 'smooth'}));
            next && next.addEventListener('click', () => track.scrollBy({left: scrollAmount(), behavior: 'smooth'}));

            // mouse wheel (vertical -> horizontal)
            track.addEventListener('wheel', (e) => {
                if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    track.scrollLeft += e.deltaY;
                    e.preventDefault();
                }
                updateArrows();
            }, {passive: false});

            // atualiza estado dos botões
            track.addEventListener('scroll', updateArrows, {passive: true});
            window.addEventListener('resize', updateArrows);

            // impede que clique no favorito navegue
            root.addEventListener('click', function (e) {
                const fav = e.target.closest('.wa-fav');
                if (fav) {
                    e.stopPropagation();
                }
            }, true);

            // inicial
            updateArrows();
        })();
    </script>

    <?php
    return ob_get_clean();
});
