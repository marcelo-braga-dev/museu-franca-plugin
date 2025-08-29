<?php
/**
 * [lista_colecoes min_count="0" columns="3" show_count="yes"]
 * Lista todas as coleções com link para o arquivo da taxonomia.
 */
function mp_sc_lista_colecoes($atts = []) {
    $a = shortcode_atts([
        'min_count'  => 0,     // só mostrar coleções com >= X posts
        'columns'    => 3,     // colunas do grid
        'show_count' => 'yes', // yes|no
    ], $atts, 'lista_colecoes');

    $terms = get_terms([
        'taxonomy'   => 'colecao',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return '<p>Nenhuma coleção encontrada.</p>';
    }

    $cols = max(1, (int)$a['columns']);
    ob_start(); ?>
    <style>
        .mp-colecoes-grid { display:grid; gap:1rem; }
        .mp-colecoes-grid.cols-2 { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .mp-colecoes-grid.cols-3 { grid-template-columns:repeat(3, minmax(0,1fr)); }
        .mp-colecoes-grid.cols-4 { grid-template-columns:repeat(4, minmax(0,1fr)); }

        @media (max-width: 800px) {
            .mp-colecoes-grid { grid-template-columns:repeat(1, minmax(0,1fr)) !important; }
        }

        .mp-colecao-card {
            border:1px solid #e5e7eb; border-radius:12px; padding:1rem; background:#fff;
            display:flex; flex-direction:column; gap:.5rem;
        }
        .mp-colecao-title { margin:0; font-size:1.05rem; }
        .mp-colecao-cta { margin-top:auto; display:inline-flex; align-items:center; gap:.4rem; }
    </style>

    <div class="mp-colecoes-grid cols-<?= (int)$cols; ?>">
        <?php foreach ($terms as $t):
            if ((int)$a['min_count'] > 0 && (int)$t->count < (int)$a['min_count']) continue; ?>
            <article class="mp-colecao-card">
                <h3 class="mp-colecao-title">
                    <a href="<?= esc_url(get_term_link($t)); ?>"><?= esc_html($t->name); ?></a>
                </h3>
                <?php if ($a['show_count'] === 'yes'): ?>
                    <div class="mp-colecao-meta">
                        <?= (int)$t->count; ?> artigo(s)
                    </div>
                <?php endif; ?>
                <?php if (!empty($t->description)): ?>
                    <p class="mp-colecao-desc"><?= esc_html($t->description); ?></p>
                <?php endif; ?>
                <a class="mp-colecao-cta" href="<?= esc_url(get_term_link($t)); ?>">
                    Ver artigos <i class="fa fa-arrow-right-long" aria-hidden="true"></i>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('lista_colecoes', 'mp_sc_lista_colecoes');
