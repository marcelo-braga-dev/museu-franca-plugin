<?php
/**
 * [artigos_colecao slug="mulheres-negras" per_page="9"]
 * - Informe 'slug' (ou 'id' do termo)
 * - Opcional: 'orderby' (date, title, meta_value...), 'order' (DESC|ASC)
 */
function mp_sc_artigos_colecao($atts = []) {
    $a = shortcode_atts([
        'slug'     => '',
        'id'       => '',
        'per_page' => 9,
        'orderby'  => 'date',
        'order'    => 'DESC',
    ], $atts, 'artigos_colecao');

    // Identificar o termo
    $term = null;
    if (!empty($a['id'])) {
        $term = get_term((int)$a['id'], 'colecao');
    } elseif (!empty($a['slug'])) {
        $term = get_term_by('slug', sanitize_title($a['slug']), 'colecao');
    }

    if (!$term || is_wp_error($term)) {
        return '<p>Coleção inválida ou não informada.</p>';
    }

    // Paginação
    $paged = max(1, get_query_var('paged') ?: (isset($_GET['pg']) ? (int)$_GET['pg'] : 1));

    $q = new WP_Query([
        'post_type'      => 'artigo',
        'posts_per_page' => (int)$a['per_page'],
        'paged'          => $paged,
        'orderby'        => $a['orderby'],
        'order'          => $a['order'],
        'tax_query'      => [[
            'taxonomy' => 'colecao',
            'field'    => 'term_id',
            'terms'    => (int)$term->term_id,
        ]],
        'post_status'    => 'publish',
    ]);

    ob_start(); ?>
    <style>
        .mp-grid-artigos { display:grid; gap:1rem; grid-template-columns:repeat(3, minmax(0,1fr)); }
        @media (max-width: 900px) { .mp-grid-artigos { grid-template-columns:repeat(2, 1fr); } }
        @media (max-width: 640px) { .mp-grid-artigos { grid-template-columns:1fr; } }

        .mp-card-artigo {
            border:1px solid #e5e7eb; border-radius:12px; background:#fff;
            overflow:hidden; display:flex; flex-direction:column; gap:.6rem;
        }
        .mp-card-thumb img { width:100%; height:200px; object-fit:cover; display:block; }
        .mp-thumb-placeholder {
            width:100%; height:200px; display:grid; place-items:center; background:#f3f4f6; color:#6b7280;
        }
        .mp-card-title { font-size:1rem; margin:.3rem .75rem 0; line-height:1.3; }
        .mp-card-title a { text-decoration:none; }
        .mp-card-meta { margin:.25rem .75rem 1rem; }
        .mp-pagination { display:flex; gap:.5rem; margin-top:1rem; }
        .mp-pagination a { padding:.4rem .7rem; border:1px solid #e5e7eb; border-radius:8px; text-decoration:none; }
        .mp-pagination a.is-active { background:#111827; color:#fff; border-color:#111827; }
    </style>

    <section class="mp-lista-artigos-colecao" aria-label="Artigos da coleção">
        <header class="mp-lista-header">
            <h2>Coleção: <?= esc_html($term->name); ?></h2>
            <?php if (!empty($term->description)): ?>
                <p class="mp-colecao-desc"><?= esc_html($term->description); ?></p>
            <?php endif; ?>
        </header>

        <?php if ($q->have_posts()): ?>
            <div class="mp-grid-artigos">
                <?php while ($q->have_posts()): $q->the_post();
                    $pid = get_the_ID();
                    $thumb = get_the_post_thumbnail_url($pid, 'medium_large');
                    ?>
                    <article class="mp-card-artigo">
                        <a class="mp-card-thumb" href="<?= esc_url(get_permalink($pid)); ?>">
                            <?php if ($thumb): ?>
                                <img src="<?= esc_url($thumb); ?>" alt="<?= esc_attr(get_the_title($pid)); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="mp-thumb-placeholder">Sem capa</div>
                            <?php endif; ?>
                        </a>
                        <h3 class="mp-card-title">
                            <a href="<?= esc_url(get_permalink($pid)); ?>"><?= esc_html(get_the_title($pid)); ?></a>
                        </h3>
                        <div class="mp-card-meta">
                            <?= mp_colecao_badges($pid); ?>
                        </div>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <?php
            // paginação simples ?pg=N (não conflitar com loops de página)
            $total = (int)$q->max_num_pages;
            if ($total > 1): ?>
                <nav class="mp-pagination" aria-label="Paginação">
                    <?php
                    $base_url = remove_query_arg('pg');
                    for ($i=1; $i <= $total; $i++):
                        $url = add_query_arg('pg', $i, $base_url);
                        $active = $i === $paged ? ' aria-current="page" class="is-active"' : '';
                        echo '<a'.$active.' href="'.esc_url($url).'">'.$i.'</a>';
                    endfor; ?>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <p>Nenhum artigo nesta coleção ainda.</p>
        <?php endif; ?>
    </section>
    <?php return ob_get_clean();
}
add_shortcode('artigos_colecao', 'mp_sc_artigos_colecao');
