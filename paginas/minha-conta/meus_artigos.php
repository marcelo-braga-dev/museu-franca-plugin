<?php
// Shortcode: Meus artigos
add_shortcode('meus_artigos', function() {
    if (!is_user_logged_in()) return '<p>Você precisa estar logado.</p>';

    ob_start();

    // Exclusão, se houver
    if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
        $id = intval($_GET['excluir']);
        if (get_post_field('post_author', $id) == get_current_user_id()) {
            wp_delete_post($id, true);
            echo '<p>Artigo excluído.</p>';
        }
    }

    // Mensagem de sucesso
    if (isset($_GET['sucesso'])) {
        echo '<div class="mensagem-sucesso" style="background:#dff0d8; border:1px solid #3c763d; padding:15px; color:#3c763d; margin-bottom:20px;">História publicada com sucesso!</div>';
    }

    // Consulta os artigos do usuário
    $query = new WP_Query([
        'post_type' => 'artigo',
        'author'    => get_current_user_id(),
        'posts_per_page' => -1
    ]);

    ?>

    <style>
        .grid-artigos-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px auto;
            max-width: 1200px;
        }
        .artigo-card {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        .artigo-card:hover {
            transform: translateY(-4px);
        }
        .artigo-card img {
            width: 100%;
            height: auto;
            max-height: 150px; /* Limita a altura da imagem */
            object-fit: contain; /* Evita distorções, mantém proporção */
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .artigo-card h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #111;
        }
        .artigo-card p {
            font-size: 14px;
            color: #555;
            margin-bottom: 6px;
        }
        .artigo-card .meta {
            font-size: 11px;
            color: #777;
            margin-bottom: 3px;
        }
        .artigo-card a {
            margin-top: auto;
            font-weight: bold;
            color: #992d17;
            text-decoration: none;
        }
        .artigo-card a:hover {
            text-decoration: underline;
        }
    </style>
    
    <h5>Seu Histórico de Artigos Publicados</h5>
    <div class="grid-artigos-container">
        <?php
        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
                $post_id = get_the_ID();
                $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: 'https://via.placeholder.com/400x200?text=Sem+Imagem';
                $categorias = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
                $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
        ?>
                <div class="artigo-card">
                    <a href="<?= esc_url(get_url_artigo($post_id)); ?>">
                        <img src="<?= esc_url($thumb); ?>" alt="<?= esc_attr(get_the_title()); ?>">
                    </a>
                    <h3><?= esc_html(get_the_title()); ?></h3>
                    <p><?= esc_html(get_the_excerpt()); ?></p>

                    <?php if (!empty($categorias)): ?>
                        <span class="meta"><strong>Categoria:</strong> <?= esc_html(implode(', ', $categorias)); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($tags)):
                        $tags_formatadas = array_map(function($tag) {
                            return '#' . esc_html($tag);
                        }, $tags);
                    ?>
                        <p class="meta"><?= implode(', ', $tags_formatadas); ?></p>
                    <?php endif; ?>

                    <div style="margin-top: 10px;">
                        <a href="<?= esc_url(get_url_artigo($post_id)); ?>">Ver</a> |
                        <a href="?aba=editar&post_id=<?= $post_id ?>">Editar</a> |
                        <a href="?aba=historico&excluir=<?= $post_id ?>" onclick="return confirm('Tem certeza que deseja excluir este artigo?')">Excluir</a>
                    </div>
                </div>
        <?php
            endwhile;
        else :
            echo '<p>Você ainda não publicou nenhum artigo.</p>';
        endif;
        wp_reset_postdata();
        ?>
    </div>

    <?php
    return ob_get_clean();
});
