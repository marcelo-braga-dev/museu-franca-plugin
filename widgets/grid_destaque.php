<?php
/**
 * ====== SHORTCODE [destaques_artigos] ======
 * Atributos: count (qtd de cartões), title (título da seção)
 */
add_shortcode('destaques_artigos', function ($atts) {
    $a = shortcode_atts([
            'count' => 3,
            'title' => 'Destaques',
    ], $atts, 'destaques_artigos');

    // Primeiro, verifica se existe ao menos 1 artigo com _views
    $probe = new WP_Query([
            'post_type' => 'artigo',
            'post_status' => 'publish',
            'meta_key' => '_views',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
    ]);
    wp_reset_postdata();

    // Monta args com ou sem _views de acordo com o resultado
    $args = [
            'post_type' => 'artigo',
            'post_status' => 'publish',
            'posts_per_page' => (int)$a['count'],
    ];

    if ($probe->have_posts()) {
        // Já existem artigos com _views: ordene por _views DESC e desempate
        $args['meta_key'] = '_views';
        $args['orderby'] = [
                'meta_value_num' => 'DESC',
                'comment_count' => 'DESC',
                'date' => 'DESC',
        ];
    } else {
        // Ainda não há _views: ordene por engajamento/data
        $args['orderby'] = [
                'comment_count' => 'DESC',
                'date' => 'DESC',
        ];
    }

    $q = new WP_Query($args);

    ob_start();
    ?>
    <style>
        @media (max-width: 768px) {
            .mp-destaques-grid {
                grid-template-columns: 1fr;
            }
        }

        .mp-destaques {
            max-width: 1200px;
            margin: 0 auto
        }

        .mp-destaques h3 {
            margin: 0 0 .75rem 0;
            font-weight: 700
        }

        .mp-destaques-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(3, 1fr);
        }

        .mp-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .06);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .mp-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .10)
        }

        .mp-thumb {
            display: block;
            aspect-ratio: 16/9;
            background: #f1f5f9;
            overflow: hidden
        }

        .mp-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .mp-body {
            padding: 12px 14px
        }

        .mp-title {
            font-weight: 700;
            font-size: 12px;
            color: #111827;

            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mp-title a {
            text-decoration: none;
            color: inherit
        }

        .mp-meta {
            font-size: .825rem;
            color: #6b7280;
            margin-top: 6px
        }

        .mp-badge {
            display: inline-block;
            font-size: .75rem;
            padding: .15rem .5rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #374151;
            margin-left: .4rem
        }
    </style>

    <section class="mp-destaques">
        <div class="mp-destaques-grid">
            <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post();
                $views = (int)get_post_meta(get_the_ID(), '_views', true);
                $thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium_large');
                ?>
                <article class="mp-card">
                    <a class="mp-thumb" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                        <?php if ($thumb): ?>
                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>">
                        <?php else: ?>
                            <!-- placeholder sem imagem -->
                            <svg viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg" role="img"
                                 aria-label="Sem imagem">
                                <rect width="400" height="225" fill="#e5e7eb"/>
                                <circle cx="75" cy="65" r="10" fill="#cbd5e1"/>
                                <path d="M0 180 Q120 120 220 170 T400 150 V225 H0 Z" fill="#fff"/>
                            </svg>
                        <?php endif; ?>
                    </a>
                    <div class="mp-body">
                        <span class="mp-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></span>
<!--                        <div class="mp-meta">-->
<!--                            --><?php //echo get_the_date(); ?>
<!--                            <span class="mp-badge">--><?php //echo $views; ?><!-- views</span>-->
<!--                        </div>-->
                    </div>
                </article>
            <?php endwhile;
                wp_reset_postdata(); else: ?>
                <p>Sem destaques no momento.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
});
