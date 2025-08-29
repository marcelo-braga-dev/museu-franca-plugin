<?php
/**
 * ====== SHORTCODE [destaques_artigos] ======
 * Atributos:
 *  - count: quantidade de cartões (padrão 3)
 *  - title: título da seção (opcional, não exibido — mantenho para compatibilidade)
 */
add_shortcode('destaques_artigos', function ($atts) {
    $a = shortcode_atts([
            'count' => 3,
            'title' => 'Destaques',
    ], $atts, 'destaques_artigos');

    // ----- Helpers (encapsulados) -----
    if (!function_exists('mp_da_get_url_artigo')) {
        function mp_da_get_url_artigo($post_id) {
            if (function_exists('get_url_artigo')) {
                return get_url_artigo($post_id);
            }
            return get_permalink($post_id);
        }
    }

    if (!function_exists('mp_da_detect_media_types')) {
        /**
         * Retorna um array com os tipos de mídia presentes no artigo.
         * Tipos possíveis (chaves): image, pdf, video, audio
         */
        function mp_da_detect_media_types($post_id) {
            $types = [
                    'image' => false,
                    'pdf'   => false,
                    'video' => false,
                    'audio' => false,
            ];

            // Anexos padrão do WP
            $attachments = get_posts([
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'posts_per_page' => 50,
                    'post_parent'    => $post_id,
                    'fields'         => 'ids',
            ]);

            if (!empty($attachments)) {
                foreach ($attachments as $aid) {
                    $mime = get_post_mime_type($aid);
                    if (!$mime) continue;

                    if (strpos($mime, 'image/') === 0)  $types['image'] = true;
                    if ($mime === 'application/pdf')    $types['pdf']   = true;
                    if (strpos($mime, 'video/') === 0)  $types['video'] = true;
                    if (strpos($mime, 'audio/') === 0)  $types['audio'] = true;
                }
            }

            // Heurística para vídeos embutidos (YouTube/Vimeo) no conteúdo/metas
            $content = get_post_field('post_content', $post_id);
            if (is_string($content) && preg_match('~(youtube\.com|youtu\.be|vimeo\.com)~i', $content)) {
                $types['video'] = true;
            }

            // Se existir um meta de vídeos usado no projeto (ex.: array serializado com video_id)
            $videos_meta = get_post_meta($post_id, 'videos', false);
            if (!empty($videos_meta)) $types['video'] = true;

            return $types;
        }
    }

    // ----- Verifica se há ao menos 1 artigo com _views -----
    $probe = new WP_Query([
            'post_type'      => 'artigo',
            'post_status'    => 'publish',
            'meta_key'       => '_views',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
    ]);
    wp_reset_postdata();

    // ----- Monta argumentos de busca -----
    $args = [
            'post_type'      => 'artigo',
            'post_status'    => 'publish',
            'posts_per_page' => (int)$a['count'],
    ];

    if ($probe->have_posts()) {
        $args['meta_key'] = '_views';
        $args['orderby']  = [
                'meta_value_num' => 'DESC',
                'comment_count'  => 'DESC',
                'date'           => 'DESC',
        ];
    } else {
        $args['orderby']  = [
                'comment_count'  => 'DESC',
                'date'           => 'DESC',
        ];
    }

    $q = new WP_Query($args);

    ob_start(); ?>
    <style>
        /* ====== Layout ====== */
        .mp-destaques {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 10px;
        }
        .mp-destaques-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        @media (max-width: 1024px) {
            .mp-destaques-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 700px) {
            .mp-destaques-grid { grid-template-columns: 1fr; }
        }

        /* ====== Card ====== */
        .mp-card {
            position: relative;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .mp-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,0,0,.12);
        }

        /* Thumb com overlay e ícones */
        .mp-thumb {
            position: relative;
            aspect-ratio: 16/9;
            background: #f1f5f9;
            overflow: hidden;
            display: block;
        }
        .mp-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform: scale(1);
            transition: transform .25s ease;
        }
        .mp-card:hover .mp-thumb img {
            transform: scale(1.03);
        }
        .mp-thumb__overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.45), rgba(0,0,0,0) 55%);
            pointer-events: none;
        }

        /* Ícones de mídia (tipos de anexos) */
        .mp-media-icons {
            position: absolute;
            left: 10px;
            bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 2;
        }
        .mp-media-icons .mp-media-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
            font-size: 14px;
            color: #111827;
        }
        .mp-media-icons .mp-media-chip[data-type="image"] { /* opcional: leve nuance */ }
        .mp-media-icons .mp-media-chip[data-type="pdf"]   { }
        .mp-media-icons .mp-media-chip[data-type="video"] { }
        .mp-media-icons .mp-media-chip[data-type="audio"] { }

        /* Corpo do card com título enxuto */
        .mp-body {
            padding: 10px 12px 12px;
        }
        .mp-title {
            margin: 0;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.3;
            color: #111827;
            display: -webkit-box;
            -webkit-line-clamp: 2;     /* 2 linhas no máx */
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mp-title a {
            color: inherit;
            text-decoration: none;
        }
        .mp-title a:hover { text-decoration: underline; }

        /* Placeholder simples quando não há imagem */
        .mp-placeholder {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: #9ca3af;
            font-size: 0.85rem;
        }
    </style>

    <section class="mp-destaques" aria-label="Destaques">
        <div class="mp-destaques-grid">
            <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post();
                $post_id = get_the_ID();
                $url     = esc_url(mp_da_get_url_artigo($post_id));
                $thumb   = get_the_post_thumbnail_url($post_id, 'medium_large');
                $types   = mp_da_detect_media_types($post_id);
                ?>
                <article class="mp-card" role="article" aria-label="<?php the_title_attribute(); ?>">
                    <a class="mp-thumb" href="<?= $url; ?>" aria-label="<?php the_title_attribute(); ?>">
                        <?php if ($thumb): ?>
                            <img
                                    src="<?= esc_url($thumb); ?>"
                                    alt="<?php echo esc_attr(wp_strip_all_tags(get_the_title())); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    width="800" height="450"
                            >
                        <?php else: ?>
                            <div class="mp-placeholder">Sem imagem</div>
                        <?php endif; ?>

                        <span class="mp-thumb__overlay" aria-hidden="true"></span>

                        <div class="mp-media-icons" aria-label="Tipos de anexos neste artigo">
                            <?php if ($types['image']): ?>
                                <span class="mp-media-chip" data-type="image" title="Imagens">
                                    <i class="fa-regular fa-images" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                            <?php if ($types['pdf']): ?>
                                <span class="mp-media-chip" data-type="pdf" title="PDF">
                                    <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                            <?php if ($types['video']): ?>
                                <span class="mp-media-chip" data-type="video" title="Vídeo">
                                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                            <?php if ($types['audio']): ?>
                                <span class="mp-media-chip" data-type="audio" title="Áudio">
                                    <i class="fa-solid fa-headphones" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>

                    <div class="mp-body">
                        <h4 class="mp-title">
                            <a href="<?= $url; ?>"><?php echo esc_html(get_the_title()); ?></a>
                        </h4>
                    </div>
                </article>
            <?php endwhile; wp_reset_postdata(); else: ?>
                <p>Sem destaques no momento.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
});
