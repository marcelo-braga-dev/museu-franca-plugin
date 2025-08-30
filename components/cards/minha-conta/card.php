<?php
if (!is_user_logged_in()) {
    echo '<p>Você precisa estar logado.</p>';
    return; // só interrompe este sub-arquivo
}

// ===== Exclusão segura com nonce =====
if (isset($_GET['excluir'], $_GET['_wpnonce']) && is_numeric($_GET['excluir'])) {
    $id    = intval($_GET['excluir']);
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

    if (wp_verify_nonce($nonce, 'excluir_artigo_' . $id)) {
        $autor_id = (int) get_post_field('post_author', $id);
        $user_id  = get_current_user_id();

        if ($autor_id === $user_id || current_user_can('delete_post', $id)) {
            wp_delete_post($id, true);

            // preserva a 'aba' atual, se existir
            $aba_atual = isset($_GET['aba']) ? sanitize_key($_GET['aba']) : null;

            // base sem os parâmetros de ação
            $target = remove_query_arg(['excluir', '_wpnonce', 'paged'], wp_get_referer() ?: '');

            // fallback
            if (!$target) {
                $target = remove_query_arg(['excluir', '_wpnonce', 'paged'], home_url(add_query_arg([])));
            }

            // aviso de sucesso
            $target = add_query_arg(['ta_ok' => 1], $target);

            // reanexa a aba, se existir
            if ($aba_atual) {
                $target = add_query_arg(['aba' => $aba_atual], $target);
            }

            wp_safe_redirect($target);
            exit;
        }
    }
}

// ===== Paginação =====
$paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: (isset($_GET['paged']) ? intval($_GET['paged']) : 1));

// ===== Query =====
$query = new WP_Query([
        'post_type'      => 'artigo',
        'post_status'    => ['publish', 'pending'], // exibe tudo relevante
        'posts_per_page' => 12,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        ...$queryData
]);

// ===== Helpers =====
$mk_url = function ($args = []) {
    $base = remove_query_arg(['excluir', '_wpnonce', 'ta_ok', 'paged']);
    return esc_url(add_query_arg($args, $base));
};

// Detecta anexos
$tem_imagens = function ($post_id) {
    $imagens = (array)get_post_meta($post_id, 'imagem_adicional', false);
    $imagens = array_filter($imagens, fn($v) => !empty($v) && intval($v) > 0);
    return !empty($imagens);
};
$tem_youtube = function ($post_id) {
    $links_meta = (array)get_post_meta($post_id, 'youtube_link', false);
    if (!empty(array_filter($links_meta))) return true;
    $content = (string)get_post_field('post_content', $post_id);
    return (bool)preg_match('~(youtube\.com|youtu\.be)~i', $content);
};
$tem_pdf = function ($post_id) {
    $keys = ['pdf_files', 'pdf_arquivos', 'anexo_pdf', 'pdf_adicional'];
    foreach ($keys as $k) {
        $vals = (array)get_post_meta($post_id, $k, false);
        if (!empty(array_filter($vals))) return true;
    }
    $pdfs = get_children([
            'post_parent'    => $post_id,
            'post_type'      => 'attachment',
            'post_mime_type' => 'application/pdf',
            'numberposts'    => 1,
            'fields'         => 'ids',
    ]);
    if (!empty($pdfs)) return true;
    $content = (string)get_post_field('post_content', $post_id);
    return (bool)preg_match('~https?://\S+\.pdf(\b|$)~i', $content);
};

// Caminho "Pai > Filho" para categoria
$cat_path = function (WP_Term $term) {
    $trail = [];
    $t = $term;
    while ($t && $t->parent) {
        $trail[] = $t->name;
        $t = get_term($t->parent, 'category');
        if ($t instanceof WP_Error) break;
    }
    if ($t && $t instanceof WP_Term) {
        $trail[] = $t->name;
    } elseif (empty($trail)) {
        $trail[] = $term->name;
    }
    $trail = array_reverse($trail);
    return implode(' > ', $trail);
};

ob_start();
?>
    <style>
        :root{
            --brand:#992d17; --text:#1f2937; --muted:#64748b; --stroke:#e5e7eb; --card:#fff;
            --radius:14px; --radius-sm:10px; --shadow:0 8px 22px rgba(0,0,0,.06);
        }
        .ta-wrap{max-width:1200px;margin:0 auto;padding:clamp(12px,2.5vw,24px)}
        .ta-title{margin:4px 0 18px;font-size:20px;line-height:1.25;color:var(--text);font-weight:800;letter-spacing:-.01em;display:flex;align-items:center;gap:8px}
        .ta-alert{border:1px solid var(--stroke);background:#ecfdf5;color:#065f46;padding:12px 14px;border-radius:10px;margin:8px 0 16px}
        .ta-grid{display:grid;gap:clamp(12px,2.2vw,20px);grid-template-columns:1fr}
        @media(min-width:640px){.ta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(min-width:1024px){.ta-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1280px){.ta-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}

        .ta-card{display:grid;grid-template-rows:auto 1fr auto;gap:10px;background:var(--card);border:1px solid var(--stroke);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease}
        .ta-card:focus-within,.ta-card:hover{transform:translateY(-2px);border-color:#d1d5db;box-shadow:0 12px 28px rgba(0,0,0,.08)}

        /* Media 16:9 */
        .ta-card__media{position:relative;width:100%;aspect-ratio:16/9;background:#f8fafc;overflow:hidden}
        .ta-card__media img{width:100%;height:100%;object-fit:cover;display:block}

        /* BADGES sobre a capa (canto inferior esquerdo) */
        .ta-media-badges{
            position:absolute;left:8px;bottom:8px;display:flex;gap:6px;z-index:2
        }
        .ta-media-badge{
            display:inline-flex;align-items:center;justify-content:center;
            width:28px;height:28px;border-radius:999px;
            background:rgba(255,255,255,.85); /* leve translúcido */
            border:1px solid rgba(0,0,0,.08);
            backdrop-filter:saturate(1.2) blur(2px);
            -webkit-backdrop-filter:saturate(1.2) blur(2px);
            box-shadow:0 2px 6px rgba(0,0,0,.06);
            transition:transform .12s ease, background .15s ease, border-color .15s ease
        }
        .ta-media-badge:hover{transform:translateY(-1px);background:#fff;border-color:#e5e7eb}
        .ta-media-badge i{font-size:14px;line-height:1}

        .ta-attach--yt{color:#ff0000}
        .ta-attach--pdf{color:#d32f2f}
        .ta-attach--img{color:#2e7d32}

        /* Conteúdo */
        .ta-card__body{padding:12px 14px 0;display:grid;gap:8px}
        .ta-card__title{font-size:clamp(13px,1.6vw,15px);font-weight:600;color:#0f172a;margin:0;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;min-height:calc(1.3em * 2)}
        .ta-meta{font-size:12px;color:#475569;display:block;margin:0}

        /* Chips */
        .ta-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;min-width:0}
        .ta-chip-category{display:inline-flex;align-items:center;border-radius:999px;padding:0 10px;font-size:12px;font-weight:500;line-height:1.4;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;min-width:0;max-width:100%;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
        @media(min-width:480px){.ta-chip-category{max-width:220px}}

        /* Rodapé */
        .ta-card__footer{padding:10px 14px 14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .ta-btn{display:inline-flex;align-items:center;gap:5px;padding:9px 9px;border-radius:10px;text-decoration:none;font-weight:800;font-size:13px;line-height:1;transition:background .15s,color .15s,border-color .15s,transform .02s;border:1px solid #e2e8f0;background:#fff}
        .ta-btn:active{transform:translateY(1px)}
        .ta-btn--primary{color:var(--brand)}
        .ta-btn--primary:hover{border-color:var(--brand);background:rgba(153,45,23,.06)}
        .ta-btn--edit{color:#16a34a}.ta-btn--edit:hover{border-color:#16a34a;background:#f0fdf4}
        .ta-btn--delete{color:#ef4444}.ta-btn--delete:hover{background:#fef2f2;border-color:#ef4444}

        .ta-empty{padding:24px;text-align:center;color:var(--muted);border:1px dashed var(--stroke);border-radius:12px;background:#fff}

        /* Paginação */
        .ta-pag{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:16px}
        .ta-pag a,.ta-pag span{padding:8px 12px;border:1px solid #e6e8eb;border-radius:10px;text-decoration:none;color:#374151;font-size:13px;transition:background .2s,border-color .2s}
        .ta-pag a:hover{background:#f8fafc;border-color:#dbe2ea}
        .ta-pag .current{background:var(--brand);color:#fff;border-color:var(--brand)}

        .ta-status{display:inline-flex;align-items:center;justify-content:center;padding:0 12px;height:28px;line-height:28px;font-size:12px;font-weight:700;border-radius:999px;white-space:nowrap}
        .ta-status--publish{background:#dcfce7;color:#166534;border:1px solid #86efac}
        .ta-status--pending{background:#dbeafe;color:#1e3a8a;border:1px solid #93c5fd}
        .ta-status--draft{background:#f3f4f6;color:#374151;border:1px solid #d1d5db}

        @media (prefers-reduced-motion: reduce){
            .ta-card,.ta-btn,.ta-media-badge{transition:none}
        }
    </style>

    <div class="ta-wrap">
        <h6 class="ta-title">
            <?= $pageIcon ?? null; ?>
            <?= esc_html($pageTitle ?? null); ?>
        </h6>

        <?php if (isset($_GET['ta_ok'])): ?>
            <div class="ta-alert">Artigo excluído com sucesso.</div>
        <?php endif; ?>

        <?php if ($query->have_posts()) : ?>
            <div class="ta-grid">
                <?php while ($query->have_posts()) : $query->the_post();
                    $post_id     = get_the_ID();
                    $thumb       = get_the_post_thumbnail_url($post_id, 'medium') ?: '/wp-content/uploads/2025/07/logo-4.png';
                    $categorias  = wp_get_post_terms($post_id, 'category', ['fields' => 'all']);
                    $author_id   = get_post_field('post_author', $post_id);
                    $author_name = get_the_author_meta('display_name', $author_id);
                    $status      = get_post_status($post_id);
                    $status_obj  = $status ? get_post_status_object($status) : null;

                    $status_class = ($status === 'publish') ? 'ta-status ta-status--publish'
                            : (($status === 'pending') ? 'ta-status ta-status--pending'
                                    : 'ta-status ta-status--draft');

                    $url_ver = function () use ($post_id) {
                        $u = function_exists('get_url_artigo') ? get_url_artigo($post_id) : get_permalink($post_id);
                        return esc_url($u);
                    };
                    $url_editar = function () use ($post_id, $mk_url) {
                        return $mk_url(['aba' => 'editar', 'post_id' => $post_id]);
                    };
                    $url_deletar = function () use ($post_id, $mk_url) {
                        $nonce = wp_create_nonce('excluir_artigo_' . $post_id);
                        return $mk_url(['excluir' => $post_id, '_wpnonce' => $nonce]);
                    };

                    // Tipos de anexos
                    $hasYT  = $tem_youtube($post_id);
                    $hasPDF = $tem_pdf($post_id);
                    $hasIMG = $tem_imagens($post_id);

                    // Permissão para deletar?
                    $user_can_delete = ((int)$author_id === get_current_user_id()) || current_user_can('delete_post', $post_id);
                    ?>
                    <article class="ta-card">
                        <a class="ta-card__media" href="<?= $url_ver(); ?>"
                           aria-label="Abrir: <?= esc_attr(get_the_title()); ?>">
                            <img src="<?= esc_url($thumb); ?>" alt="<?= esc_attr(get_the_title()); ?>" loading="lazy" decoding="async">

                            <?php if ($hasYT || $hasPDF || $hasIMG): ?>
                                <div class="ta-media-badges" aria-label="Tipos de conteúdo">
                                    <?php if ($hasYT): ?>
                                        <span class="ta-media-badge" title="Possui vídeo do YouTube" aria-label="Vídeo do YouTube">
                                        <i class="fa-brands fa-youtube ta-attach--yt" aria-hidden="true"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($hasPDF): ?>
                                        <span class="ta-media-badge" title="Possui PDF" aria-label="PDF">
                                        <i class="fa-regular fa-file ta-attach--pdf" aria-hidden="true"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($hasIMG): ?>
                                        <span class="ta-media-badge" title="Possui galeria de imagens" aria-label="Galeria de imagens">
                                        <i class="fa-regular fa-images ta-attach--img" aria-hidden="true"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="ta-card__body">
                            <a href="<?= $url_ver(); ?>" aria-label="Abrir: <?= esc_attr(get_the_title()); ?>">
                                <h3 class="ta-card__title"><?= esc_html(get_the_title()); ?></h3>
                            </a>

                            <span class="<?= esc_attr($status_class); ?>">
                            <?= esc_html($status_obj ? $status_obj->label : ucfirst($status)); ?>
                        </span>

                            <?php if (!empty($categorias) && !is_wp_error($categorias)) : ?>
                                <div class="ta-chips" aria-label="Categorias">
                                    <?php
                                    $rendered = [];
                                    foreach ($categorias as $term) :
                                        if (!($term instanceof WP_Term)) continue;
                                        $label = $cat_path($term); // Pai > Filho
                                        if (isset($rendered[$label])) continue;
                                        $rendered[$label] = true;
                                        ?>
                                        <span class="ta-chip-category" title="<?= esc_attr($label); ?>">
                                        <?= esc_html($label); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <span class="ta-meta" style="margin-top:4px;">
                            <strong>Por:</strong> <?= esc_html($author_name); ?>
                        </span>
                        </div>

                        <div class="ta-card__footer">
                            <a class="ta-btn ta-btn--primary" href="<?= $url_ver(); ?>">
                                <i class="fa fa-eye" aria-hidden="true"></i> Ver
                            </a>
                            <?php if (empty($hiddenBtnAction) ): ?>
                                <a class="ta-btn ta-btn--edit" href="<?= $url_editar(); ?>">
                                    <i class="fa fa-edit" aria-hidden="true"></i> Editar
                                </a>
                            <?php endif; ?>
                            <?php if ($user_can_delete && empty($hiddenBtnAction)): ?>
                                <a class="ta-btn ta-btn--delete"
                                   href="<?= $url_deletar(); ?>"
                                   onclick="return confirm('Tem certeza que deseja excluir esta publicação?')">
                                    <i class="fa fa-trash" aria-hidden="true"></i> Deletar
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            // ===== Paginação =====
            $big   = 999999999; // need an unlikely integer
            $aba_atual = isset($_GET['aba']) ? sanitize_key($_GET['aba']) : null;

            $links = paginate_links([
                    'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format'    => '?paged=%#%',
                    'current'   => max(1, $paged),
                    'total'     => $query->max_num_pages,
                    'mid_size'  => 1,
                    'prev_text' => '« Anteriores',
                    'next_text' => 'Próximos »',
                    'type'      => 'array',
                    'add_args'  => $aba_atual ? ['aba' => $aba_atual] : [],
            ]);

            if ($links) {
                echo '<nav class="ta-pag" aria-label="Paginação">';
                foreach ($links as $link) {
                    echo $link; // mantém classes geradas pelo WP
                }
                echo '</nav>';
            }
            ?>

        <?php else: ?>
            <div class="ta-empty">Não há artigos para exibir no momento.</div>
        <?php endif;
        wp_reset_postdata(); ?>
    </div>
<?php
echo ob_get_clean();
