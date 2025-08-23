<?php
add_shortcode('visualizar_artigo', function () {
    if (!isset($_GET['id'])) return 'Artigo não encontrado.';
    $post_id = intval($_GET['id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'artigo') return 'Artigo inválido.';

    $categorias = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    $autor_nome = get_the_author_meta('display_name', $post->post_author);
    $data_publicacao = get_the_date('d \d\e F \d\e Y', $post_id);

    ob_start();
    ?>
    <style>
        .artigo-visualizar {
            color: #000;
            padding: 40px 20px;
            max-width: 900px;
            margin: 0 auto;
            font-family: sans-serif;
        }

        .artigo-visualizar h2 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .artigo-meta {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }

        .artigo-visualizar img {
            display: block;
            max-width: 100%;
            margin: 0 auto 20px;
            border-radius: 6px;
        }

        .artigo-resumo {
            background: #eee;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }

        .artigo-resumo strong {
            /*display: block;*/
            /*margin-bottom: 8px;*/
        }

        .artigo-resumo .info-inline {
            font-size: 11px;
            /*display: flex;*/
            /*gap: 20px;*/
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .artigo-conteudo {
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .artigo-visualizar h4 {
            margin-top: 40px;
            font-size: 18px;
            border-bottom: 1px solid #444;
            padding-bottom: 5px;
        }

        .galeria-imagens {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .galeria-imagens .item {
            text-align: center;
        }

        .galeria-imagens img {
            width: 100%;
            max-width: 250px;
            height: auto;
            border-radius: 4px;
            cursor: zoom-in;
        }

        .galeria-imagens small {
            font-size: 12px;
            color: #555;
            display: block;
            margin-top: 8px;
        }

        /* Lightbox */
        #lightbox-overlay {
            display: none;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            flex-direction: column;
        }

        #lightbox-img {
            max-width: 90%;
            max-height: 90%;
            transition: transform 0.3s ease;
            border-radius: 8px;
            pointer-events: none; /* impedir de bloquear os botões */
        }

        .lightbox-buttons {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            z-index: 10000;
            pointer-events: auto; /* garantir que clique funcione */
        }

        .lightbox-buttons button {
            background: #fff;
            color: #000;
            border: none;
            padding: 10px 16px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

    </style>

    <div class="artigo-visualizar">
        <h2><?= esc_html($post->post_title); ?></h2>
        <div class="artigo-meta">
            Por: <?= esc_html($autor_nome); ?>, <?= esc_html($data_publicacao); ?>
        </div>

        <?php
        $capa_url = get_the_post_thumbnail_url($post_id, 'large');
        $capa_full = get_the_post_thumbnail_url($post_id, 'full');
        if ($capa_url && $capa_full) :
            ?>
            <img src="<?= esc_url($capa_url); ?>" alt="Capa" style="cursor: zoom-in; max-height: 350px"
                 onclick="abrirLightbox('<?= esc_url($capa_full); ?>')">
        <?php endif; ?>

        <?php if (!empty($post->post_excerpt)) : ?>
            <div class="artigo-resumo">
                <strong>Resumo</strong>
                <div><?= esc_html($post->post_excerpt); ?></div>
                <div class="info-inline">
                    <?php if (!empty($categorias)) : ?>
                        <strong>Categoria:</strong> <?= esc_html(implode(', ', $categorias)); ?>
                    <?php endif; ?>
                    <br/>
                    <?php if (!empty($tags)) :
                        $tags_formatadas = array_map(function ($tag) {
                            return '#' . esc_html($tag);
                        }, $tags);
                        ?>
                        <strong>Palavras-chaves:</strong> <?= implode(', ', $tags_formatadas); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 50px">
            <h2><?= esc_html($post->post_title); ?></h2>
        </div>

        <div class="artigo-conteudo">
            <?= apply_filters('the_content', $post->post_content); ?>
        </div>

        <?php
        $imagens = get_post_meta($post_id, 'imagem_adicional');
        if (!empty($imagens)) {
            echo '<h4>Galeria de Imagens:</h4>';
            echo '<div class="galeria-imagens">';
            foreach ($imagens as $id) {
                $url = wp_get_attachment_image_url($id, 'medium');
                $full = wp_get_attachment_image_url($id, 'full');
                $descricao = get_post_meta($post_id, 'imagem_adicional_descricao_' . $id, true);

                if ($url) {
                    echo '<div class="item">';
                    echo '<img src="' . esc_url($url) . '" alt="" onclick="abrirLightbox(\'' . esc_url($full) . '\')">';
                    if (!empty($descricao)) {
                        echo '<small>' . esc_html($descricao) . '</small>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>';
        }

        $videos = get_post_meta($post_id, 'youtube_link');
        if (!empty($videos)) {
            echo '<h4>Vídeos:</h4>';
            foreach ($videos as $url) {
                if (preg_match('/(?:youtu.be\\/|youtube.com\\/(?:watch\\?v=|embed\\/|v\\/))([\w-]+)/', $url, $matches)) {
                    $video_id = $matches[1];
                    echo '<div class="video-wrapper">';
                    echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" allowfullscreen></iframe>';
                    echo '</div>';
                }
            }
        }


        $pdfs = get_post_meta($post_id, 'pdf');
        if (!empty($pdfs)) {
            echo '<h4>Arquivos PDF:</h4><ul>';
            foreach ($pdfs as $id) {
                $url = wp_get_attachment_url($id);
                if ($url) {
                    echo '<li><a href="' . esc_url($url) . '" target="_blank">Download PDF</a></li>';
                }
            }
            echo '</ul>';
        }
        ?>
    </div>

    <div style="border: 1px solid #e1e1e1; padding: 15px; border-radius: 5px; margin-bottom: 20px">
        <small style="color: #5c5c5d">
            <b>Aviso de Responsabilidade</b><br/>
            Os conteúdos disponibilizados neste site são de uso exclusivo para fins informativos e pessoais. Qualquer
            cópia, reprodução, distribuição ou utilização indevida é de inteira responsabilidade do usuário que a
            praticar, estando sujeito às medidas legais cabíveis.
        </small>
    </div>

    <!-- Lightbox -->
    <div id="lightbox-overlay" onclick="fecharLightbox()">
        <img id="lightbox-img" src="">
        <div class="lightbox-buttons">
            <button onclick="event.stopPropagation(); aumentarZoom()">+</button>
            <button onclick="event.stopPropagation(); diminuirZoom()">−</button>
            <button onclick="event.stopPropagation(); fecharLightbox()">Fechar</button>
        </div>
    </div>

    <script>
        let zoom = 1;

        function abrirLightbox(src) {
            const overlay = document.getElementById('lightbox-overlay');
            const img = document.getElementById('lightbox-img');
            img.src = src;
            zoom = 1;
            img.style.transform = 'scale(1)';
            overlay.style.display = 'flex';
        }

        function fecharLightbox() {
            document.getElementById('lightbox-overlay').style.display = 'none';
        }

        function aumentarZoom() {
            zoom += 0.2;
            document.getElementById('lightbox-img').style.transform = 'scale(' + zoom + ')';
        }

        function diminuirZoom() {
            zoom = Math.max(0.2, zoom - 0.2);
            document.getElementById('lightbox-img').style.transform = 'scale(' + zoom + ')';
        }
    </script>

    <?php
    return ob_get_clean();
});
