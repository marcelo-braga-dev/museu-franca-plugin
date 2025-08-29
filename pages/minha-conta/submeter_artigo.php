<?php
/**
 * Shortcode [submeter_artigo]
 * - Upload de imagens, PDFs, áudios (até 5MB) e vídeos YouTube
 * - Corrigido: modal de upload só abre após passar nas validações
 * - Corrigido: categoria obrigatória (cliente e servidor)
 * - Corrigido: áudio > 5MB limpa o input e reseta o texto do card
 */

add_filter('upload_mimes', function ($m) {
    // formatos extras de áudio
    $m['aac'] = 'audio/aac';
    $m['opus'] = 'audio/opus';
    $m['flac'] = 'audio/flac';
    $m['m4a'] = 'audio/m4a';
    return $m;
});

add_shortcode('submeter_artigo', function () {

    if (!is_user_logged_in()) return '<p>Você precisa estar logado para publicar um artigo.</p>';

    // ---------- Helper PHP: extrair ID do YouTube ----------
    if (!function_exists('mp_extract_youtube_id')) {
        function mp_extract_youtube_id($url)
        {
            $url = trim((string)$url);
            if ($url === '') return '';
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $patterns = [
                    '/youtu\.be\/([a-zA-Z0-9_-]{11})/i',
                    '/youtube\.com\/(?:embed|shorts)\/([a-zA-Z0-9_-]{11})/i',
                    '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i',
                    '/youtube\.com\/watch\?.*?&v=([a-zA-Z0-9_-]{11})/i'
            ];
            foreach ($patterns as $p) {
                if (preg_match($p, $url, $m)) return $m[1];
            }
            return '';
        }
    }

    $erro_box = '';

    // ---------- POST ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {

        // ====== Validações de servidor (não dependem do front) ======
        $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
        if ($categoria_id <= 0) {
            $erro_box .= '• Selecione uma categoria.<br>';
        }
        // validação de áudio (5MB + tipo)
        $allowed_mimes = [
                'audio/mpeg', 'audio/mp3',
                'audio/wav', 'audio/x-wav',
                'audio/ogg', 'audio/oga',
                'audio/mp4', 'audio/aac',
                'audio/webm',
                'audio/m4a', 'audio/x-m4a',
                'audio/flac', 'audio/opus'
        ];
        $allowed_exts = ['mp3', 'wav', 'ogg', 'oga', 'mp4', 'aac', 'webm', 'm4a', 'flac', 'opus'];
        $max_bytes = 5 * 1024 * 1024; // 5MB

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
                    $erro_box .= '• O áudio "' . esc_html($file['name']) . '" excede 5MB. Envie um arquivo menor.<br>';
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $type_ok = in_array($file['type'], $allowed_mimes, true) || in_array($ext, $allowed_exts, true);
                if (!$type_ok) {
                    $erro_box .= '• Formato de áudio não permitido para "' . esc_html($file['name']) . '". Envie mp3, m4a, wav, ogg, aac, opus ou flac.<br>';
                }
            }
        }

        if ($erro_box === '') {
            // prossegue criação do post
            $titulo = sanitize_text_field($_POST['titulo']);
            $resumo = sanitize_text_field($_POST['resumo']);
            $conteudo = wp_kses_post($_POST['conteudo']);

            $post_id = wp_insert_post([
                    'post_title' => $titulo,
                    'post_excerpt' => $resumo,
                    'post_content' => $conteudo,
                    'post_type' => 'artigo',
                    'post_status' => 'pending',
                    'post_author' => get_current_user_id()
            ]);

            if ($post_id) {

                // categoria (já validada)
                wp_set_post_terms($post_id, [$categoria_id], 'category');

                // tags
                if (!empty($_POST['tags'])) {
                    $tags = array_map('trim', explode(',', sanitize_text_field($_POST['tags'])));
                    wp_set_post_terms($post_id, $tags, 'post_tag');
                }

                // coleções
                if (!empty($_POST['colecao_ids']) && is_array($_POST['colecao_ids'])) {
                    $colecao_ids = array_map('intval', $_POST['colecao_ids']);

                    $colecao_ids = array_values(array_unique(array_filter($colecao_ids, function ($v) {
                        return $v > 0;
                    })));

                    if (!empty($colecao_ids)) {
                        wp_set_post_terms($post_id, $colecao_ids, 'colecao'); // por ID (term_id)
                    } else {
                        wp_set_post_terms($post_id, [], 'colecao');
                    }
                }

                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // Capa
                if (!empty($_FILES['capa']['name'])) {
                    $capa_id = media_handle_upload('capa', $post_id);
                    if (!is_wp_error($capa_id)) set_post_thumbnail($post_id, $capa_id);
                }

                // Imagens adicionais
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'imagem_') === 0 && !empty($file['name'])) {
                        $img_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($img_id)) {
                            add_post_meta($post_id, 'imagem_adicional', $img_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['imagem_descricao_' . $indice] ?? '');
                            if ($descricao !== '') {
                                add_post_meta($post_id, 'imagem_adicional_descricao_' . $img_id, $descricao);
                            }
                        }
                    }
                }

                // PDFs
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'pdf_') === 0 && !empty($file['name'])) {
                        $pdf_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($pdf_id)) {
                            add_post_meta($post_id, 'pdf', $pdf_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['pdf_descricao_' . $indice] ?? '');
                            if ($descricao !== '') {
                                add_post_meta($post_id, 'pdf_descricao_' . $pdf_id, $descricao);
                            }
                        }
                    }
                }

                // ÁUDIOS (até 5MB – já validados no servidor)
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                        $audio_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($audio_id)) {
                            add_post_meta($post_id, 'audio', $audio_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['audio_descricao_' . $indice] ?? '');
                            if ($descricao !== '') {
                                add_post_meta($post_id, 'audio_descricao_' . $audio_id, $descricao);
                            }
                        }
                    }
                }

                // YouTube
                foreach ($_POST as $key => $val) {
                    if (strpos($key, 'youtube_url_') === 0) {
                        $i = substr($key, strlen('youtube_url_'));
                        $url = esc_url_raw($val);
                        if ($url) {
                            $desc = sanitize_text_field($_POST['youtube_desc_' . $i] ?? '');
                            $vid_id = mp_extract_youtube_id($url);
                            add_post_meta($post_id, 'youtube_link', [
                                    'url' => $url,
                                    'desc' => $desc,
                                    'video_id' => $vid_id ?: '',
                            ]);
                        }
                    }
                }

                // Redireciona
                $url = get_url_artigo($post_id);
                echo "<script>window.location.href = " . json_encode($url) . ";</script>";
                exit;
            } else {
                $erro_box .= '• Não foi possível criar a publicação. Tente novamente.<br>';
            }
        }
    }

    $categorias = get_categories(['hide_empty' => false, 'orderby' => 'name']);
    ob_start();
    ?>
    <style>
        :root {
            --brand: #992d17;
            --brand-dark: #7d2613;
            --text: #1f2937;
            --muted: #6b7280;
            --stroke: #e5e7eb;
            --card: #ffffff;
            --shadow: 0 6px 18px rgba(0, 0, 0, .06);
            --radius: 12px;
            --radius-sm: 8px;
            --space: 16px;
        }

        .form-wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 16px
        }

        .heading {
            margin: 8px 0 18px;
            font-size: clamp(18px, 2.5vw, 22px);
            color: var(--text);
            font-weight: 700
        }

        .form-artigo {
            width: 100%;
            display: grid;
            gap: 18px
        }

        .grid {
            display: grid;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--stroke);
            border-radius: var(--radius);
            padding: 14px;
            box-shadow: var(--shadow)
        }

        .form-artigo label {
            font-weight: 600;
            color: var(--text);
            font-size: 14px
        }

        .form-artigo input[type=text], .form-artigo input[type=url], .form-artigo textarea, .form-artigo select {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border: 1px solid var(--stroke);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text);
            outline: none;
            transition: box-shadow .2s, border-color .2s
        }

        .form-artigo input:focus, .form-artigo textarea:focus, .form-artigo select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(153, 45, 23, .15)
        }

        .form-artigo button {
            background: var(--brand);
            color: #fff;
            padding: 14px 16px;
            border: none;
            border-radius: var(--radius);
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background .2s, transform .02s
        }

        .form-artigo button:hover {
            background: var(--brand-dark)
        }

        .form-artigo button:active {
            transform: translateY(1px)
        }

        .drop-zone {
            border: 2px dashed var(--stroke);
            border-radius: var(--radius);
            padding: 16px;
            text-align: center;
            position: relative;
            background: #fff;
            display: grid;
            gap: 10px;
            align-items: center;
            justify-items: center;
            min-height: 140px;
            outline: none
        }

        .drop-zone[role=button] {
            cursor: pointer
        }

        .drop-zone:focus-visible {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(153, 45, 23, .15)
        }

        .drop-zone input[type=file] {
            display: none
        }

        .drop-zone .icon {
            font-size: 28px;
            line-height: 1
        }

        .drop-zone .hint {
            color: var(--muted);
            font-size: 13px
        }

        .drop-zone img.preview {
            max-width: 100%;
            height: auto;
            max-height: 220px;
            display: none;
            border-radius: var(--radius-sm)
        }

        .grid-imagens, .grid-pdfs, .grid-videos, .grid-audios {
            display: grid;
            gap: var(--space);
            grid-template-columns:1fr
        }

        @media (min-width: 600px) {
            .grid-imagens, .grid-pdfs, .grid-videos, .grid-audios {
                grid-template-columns:repeat(2, 1fr)
            }
        }

        @media (min-width: 900px) {
            .grid-imagens, .grid-pdfs, .grid-videos, .grid-audios {
                grid-template-columns:repeat(3, 1fr)
            }
        }

        .video-card {
            border: 1px solid var(--stroke);
            border-radius: var(--radius);
            padding: 12px;
            display: grid;
            gap: 10px;
            background: #fff;
            box-shadow: var(--shadow)
        }

        .video-header {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
            font-weight: 600
        }

        .video-header i {
            font-size: 28px;
            color: red
        }

        .video-iframe {
            overflow: hidden;
            border-radius: var(--radius-sm);
            background: #000;
            aspect-ratio: 16/9
        }

        .video-iframe iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block
        }

        @supports not (aspect-ratio:16/9) {
            .video-iframe {
                position: relative;
                padding-top: 56.25%
            }

            .video-iframe iframe {
                position: absolute;
                inset: 0
            }
        }

        .add-card {
            display: grid;
            place-items: center;
            text-align: center;
            border: 2px dashed var(--stroke);
            border-radius: var(--radius);
            min-height: 140px;
            background: #fff;
            gap: 8px;
            cursor: pointer
        }

        .add-card .icon {
            font-size: 26px
        }

        .add-card .hint {
            color: var(--muted);
            font-size: 13px
        }

        .add-card:hover {
            border-color: var(--brand)
        }

        #editor {
            height: min(60vh, 420px);
            border-radius: var(--radius-sm);
            background: #fff
        }

        .ql-toolbar.ql-snow {
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            flex-wrap: wrap
        }

        .ql-container.ql-snow {
            border-radius: 0 0 var(--radius-sm) var(--radius-sm)
        }

        .termo {
            display: grid;
            grid-template-columns:24px 1fr;
            gap: 12px;
            align-items: start;
            font-size: 14px;
            color: var(--text)
        }

        .termo input[type=checkbox] {
            width: 18px;
            height: 18px;
            margin-top: 3px
        }

        .hint-mini {
            font-size: 12px;
            color: var(--muted)
        }

        .mp-progress-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 12, 16, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999
        }

        .mp-progress-box {
            background: #fff;
            padding: 20px 24px;
            border-radius: 14px;
            box-shadow: 0 10px 32px rgba(0, 0, 0, .15);
            width: 320px;
            text-align: center
        }

        .mp-progress-bar {
            width: 100%;
            height: 12px;
            background: #f3f4f6;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 10px
        }

        .mp-progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #992d17, #c2410c);
            transition: width .2s
        }

        .mp-progress-text {
            margin-top: 8px;
            font-weight: 600;
            color: #374151
        }

        .erro-box {
            background: #fff3f2;
            border: 1px solid #ffd9d6;
            color: #9c1c0e;
            border-radius: 10px;
            padding: 12px
        }

        /* checkbox */
        .mp-colecoes-checklist {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 6px;
        }

        /* Cada item */
        .mp-check {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        /* Esconde o checkbox nativo */
        .mp-check input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* O visual da pill */
        .mp-check span {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            transition: all .2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Hover */
        .mp-check:hover span {
            border-color: #9e2b19;
            color: #9e2b19;
        }

        /* Quando marcado */
        .mp-check input[type="checkbox"]:checked + span {
            background: #9e2b19;
            color: #fff;
            border-color: #9e2b19;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        /* Efeito de foco (acessibilidade) */
        .mp-check input[type="checkbox"]:focus + span {
            outline: 3px solid rgba(158, 43, 25, 0.3);
            outline-offset: 2px;
        }
    </style>

    <div class="form-wrap">
        <h5 class="heading">Publique sua História, Documentos, Fotos ou Vídeos</h5>

        <?php if (!empty($erro_box)): ?>
            <div class="erro-box" role="alert">
                <strong>Corrija os itens abaixo:</strong><br><?php echo wp_kses_post($erro_box); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-artigo" novalidate>
            <div class="grid">
                <label for="titulo">Título da Publicação</label>
                <input type="text" name="titulo" id="titulo" required maxlength="100">
                <div class="hint-mini"><span id="titulo-count">0</span>/100</div>
            </div>

            <div class="grid">
                <label for="resumo">Resumo</label>
                <textarea name="resumo" id="resumo" rows="2" maxlength="250"
                          placeholder=""></textarea>
                <div class="hint-mini"><span id="resumo-count">0</span>/250</div>
            </div>

            <div class="grid">
                <label for="conteudo">Conteúdo</label>
                <div id="editor"></div>
                <textarea name="conteudo" id="conteudo" hidden style="display:none"></textarea>
            </div>

            <div class="grid">
                <label>Foto de Capa <small style="color:#9E2B19">(obrigatória)</small></label>
                <div class="drop-zone" role="button" tabindex="0" aria-label="Adicionar foto de capa"
                     onclick="document.getElementById('capa').click()"
                     onkeydown="if(event.key==='Enter'||event.key===' '){document.getElementById('capa').click();event.preventDefault();}">
                    <div class="icon"><i class="fas fa-image"></i></div>
                    <span class="hint" id="hint-capa">Arraste uma imagem ou clique para selecionar</span>
                    <input type="file" name="capa" id="capa" accept="image/*" required
                           onchange="mostrarPreview(this); validarCapaMsg(this)"
                           oninvalid="this.setCustomValidity('Por favor, adicione uma foto de capa (obrigatória).')"
                           oninput="this.setCustomValidity('')">
                    <img src="" alt="Pré-visualização da capa" class="preview">
                </div>
            </div>

            <div class="grid">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id" required>
                    <option value="">Selecione a categoria...</option>
                    <?php
                    function exibir_categorias($cats, $parent = 0, $prefixo = '')
                    {
                        foreach ($cats as $cat) {
                            if ((int)$cat->parent === (int)$parent) {
                                echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($prefixo . $cat->name) . '</option>';
                                exibir_categorias($cats, $cat->term_id, $prefixo . ' -- ');
                            }
                        }
                    }

                    exibir_categorias($categorias);
                    ?>
                </select>
            </div>

            <!-- colecao-->
            <?php if (current_user_can('administrator')):
                $all_colecoes = get_terms(['taxonomy' => 'colecao', 'hide_empty' => false]);
                $selecionadas = []; // no editar, preencha com wp_get_post_terms($post_id, 'colecao', ['fields'=>'ids'])
                ?>
                <div class="grid">
                    <label class="mp-label">Coleções (opcional)</label>
                    <div class="mp-colecoes-checklist">
                        <?php foreach ($all_colecoes as $c): ?>
                            <label class="mp-check">
                                <input type="checkbox" name="colecao_ids[]" value="<?= (int)$c->term_id; ?>"
                                        <?= in_array($c->term_id, $selecionadas, true) ? 'checked' : ''; ?>>
                                <span><?= esc_html($c->name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid">
                <label for="tags">Palavras-chave (separadas por vírgula)</label>
                <input type="text" name="tags" id="tags" placeholder="Palavra 1, Palavra 2, Palavra 3...">
            </div>

            <div class="grid">
                <label>Galeria de Imagens</label>
                <div class="grid-imagens" id="container-imagens">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone" role="button" tabindex="0" aria-label="Adicionar imagem adicional">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-image"></i></div>
                                <span class="hint">Arraste/solte ou clique</span>
                                <input type="file" name="imagem_<?php echo $i; ?>" accept="image/*"
                                       onchange="mostrarPreview(this); toggleDescricao(this)">
                                <img src="" class="preview" alt="">
                            </div>
                            <input type="text" name="imagem_descricao_<?php echo $i; ?>" class="descricao-imagem"
                                   placeholder="Descrição da imagem..." style="display:none">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-imagem-btn" role="button" tabindex="0"
                         aria-label="Adicionar novo campo de imagem" onclick="adicionarCampoImagem()"
                         onkeydown="if(event.key==='Enter'||event.key===' '){adicionarCampoImagem();event.preventDefault();}">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint">Adicionar outra imagem</span>
                    </div>
                </div>
            </div>

            <div class="grid">
                <label>PDFs</label>
                <div class="grid-pdfs" id="container-pdfs">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone pdf" role="button" tabindex="0" aria-label="Adicionar PDF">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                <span class="hint nome-pdf">Adicionar anexo</span>
                                <input type="file" name="pdf_<?php echo $i; ?>" accept="application/pdf"
                                       onchange="marcarPdfAdicionado(this)">
                            </div>
                            <input type="text" name="pdf_descricao_<?php echo $i; ?>" placeholder="Descrição do anexo">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-pdf-btn" role="button" tabindex="0"
                         aria-label="Adicionar novo campo de PDF" onclick="adicionarCampoPdf()"
                         onkeydown="if(event.key==='Enter'||event.key===' '){adicionarCampoPdf();event.preventDefault();}">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint">Adicionar outro PDF</span>
                    </div>
                </div>
            </div>

            <!-- ÁUDIOS -->
            <div class="grid">
                <label>Áudios</label>
                <div class="grid-audios" id="container-audios">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone audio" role="button" tabindex="0" aria-label="Adicionar Áudio">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-file-audio"></i></div>
                                <span class="hint nome-audio">Arraste/solte ou clique</span>
                                <input type="file" name="audio_<?php echo $i; ?>" accept="audio/*"
                                       onchange="marcarAudioAdicionado(this)">
                            </div>
                            <input type="text" name="audio_descricao_<?php echo $i; ?>"
                                   placeholder="Descrição do áudio (opcional)">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-audio-btn" role="button" tabindex="0"
                         aria-label="Adicionar novo campo de Áudio" onclick="adicionarCampoAudio()"
                         onkeydown="if(event.key==='Enter'||event.key===' '){adicionarCampoAudio();event.preventDefault();}">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint">Adicionar outro Áudio</span>
                    </div>
                </div>
                <div class="hint-mini">Formatos aceitos: mp3, m4a, wav, ogg, aac, opus, flac. Tamanho máx.:
                    <strong>5MB</strong>.
                </div>
            </div>

            <!-- YouTube -->
            <div class="grid">
                <label>Vídeos do YouTube</label>
                <div class="grid-videos" id="container-videos">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="video-card" data-index="<?php echo $i; ?>">
                            <div class="video-header">
                                <i class="fab fa-youtube"></i> <span class="video-hint">Insira o link YouTube.</span>
                            </div>
                            <div class="video-actions">
                                <input type="url" name="youtube_url_<?php echo $i; ?>"
                                       placeholder="https://www.youtube.com/watch?v=..."
                                       oninput="updateVideoPreview(this)" aria-label="Link do vídeo do YouTube">
                                <input type="text" name="youtube_desc_<?php echo $i; ?>"
                                       placeholder="Descrição do vídeo (opcional)">
                            </div>
                            <div class="video-iframe" hidden>
                                <iframe allowfullscreen loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="add-card" id="add-video-btn" role="button" tabindex="0"
                         aria-label="Adicionar novo campo de vídeo" onclick="adicionarCampoVideo()"
                         onkeydown="if(event.key==='Enter'||event.key===' '){adicionarCampoVideo();event.preventDefault();}">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint">Adicionar outro vídeo</span>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="termo">
                    <input type="checkbox" name="check" id="check" required>
                    <div>
                        <strong>Termo de Autorização para Uso de Imagem, Áudio e Conteúdos</strong><br/> Autorizo, para
                        todos os fins legais, a utilização da minha imagem, voz, fotografias, vídeos e demais materiais
                        fornecidos por mim, no âmbito da Plataforma Museu da Pessoa Franca. Esta autorização é concedida
                        de forma gratuita e permanecerá válida enquanto meu cadastro estiver ativo na referida
                        plataforma.<br/><br/> Declaro que esta autorização expressa a minha livre manifestação de
                        vontade, e que nada terei a reivindicar, a qualquer tempo, a título de direitos autorais,
                        conexos ou quaisquer outros relacionados ao uso dos conteúdos acima mencionados.<br/><br/> O
                        consentimento ora concedido poderá ser revogado a qualquer momento, mediante solicitação
                        expressa, por meio de procedimento gratuito e facilitado, bastando entrar em contato com os
                        administradores da plataforma através do e-mail disponibilizado para esse fim.
                    </div>
                </div>
            </div>

            <button type="submit">Publicar</button>

            <!-- Modal Progresso -->
            <div id="mp-progress" class="mp-progress-overlay" style="display:none">
                <div class="mp-progress-box">
                    <div>⏳ Enviando sua publicação…</div>
                    <div class="mp-progress-bar">
                        <div class="mp-progress-fill" id="mp-progress-fill"></div>
                    </div>
                    <div class="mp-progress-text"><span id="mp-progress-num">0</span>%</div>
                </div>
            </div>
        </form>
    </div>

    <!-- Quill CSS/JS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
        // -------- Editor Quill --------
        const quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Digite seu conteúdo aqui...',
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline'],
                    ['link', 'blockquote', 'code-block'],
                    [{list: 'ordered'}, {list: 'bullet'}],
                    ['clean']
                ]
            }
        });

        // -------- Utilitários de UI --------
        function mostrarPreview(input) {
            const file = input.files[0];
            const dz = input.closest('.drop-zone');
            const img = dz.querySelector('img.preview');
            if (file && file.type.startsWith('image/')) {
                img.src = URL.createObjectURL(file);
                img.style.display = 'block';
                const hint = dz.querySelector('.hint');
                if (hint) hint.style.display = 'none';
            }
        }

        function validarCapaMsg(input) {
            const hint = document.getElementById('hint-capa');
            if (!hint) return;
            hint.style.display = (input.files && input.files.length > 0) ? 'none' : 'block';
        }

        function marcarPdfAdicionado(input) {
            const dz = input.closest('.drop-zone');
            const nomeSpan = dz.querySelector('.nome-pdf');
            if (!nomeSpan) return;
            if (input.files && input.files.length > 0) {
                nomeSpan.textContent = input.files[0].name;
                nomeSpan.style.color = '#333';
            } else {
                nomeSpan.textContent = 'Adicionar anexo';
                nomeSpan.style.color = '';
            }
        }

        function marcarAudioAdicionado(input) {
            const dz = input.closest('.drop-zone');
            const nomeSpan = dz.querySelector('.nome-audio');
            if (!nomeSpan) return;
            if (input.files && input.files.length > 0) {
                nomeSpan.textContent = input.files[0].name;
                nomeSpan.style.color = '#333';
            } else {
                nomeSpan.textContent = 'Arraste/solte ou clique';
                nomeSpan.style.color = '';
            }
        }

        function toggleDescricao(input) {
            const inputDescricao = input.closest('.drop-zone').querySelector('.descricao-imagem');
            if (!inputDescricao) return;
            inputDescricao.style.display = (input.files && input.files.length > 0) ? 'block' : 'none';
        }

        // -------- Adição dinâmica --------
        let contadorImagem = 2, contadorPdf = 2, contadorVideo = 2, contadorAudio = 2;

        function adicionarCampoImagem() {
            contadorImagem++;
            const container = document.getElementById('container-imagens');
            const botaoAdd = document.getElementById('add-imagem-btn');
            const novaDiv = document.createElement('div');
            novaDiv.className = 'drop-zone';
            novaDiv.setAttribute('role', 'button');
            novaDiv.setAttribute('tabindex', '0');
            novaDiv.onclick = () => novaDiv.querySelector('input[type=file]').click();
            novaDiv.onkeydown = (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    novaDiv.click();
                    ev.preventDefault();
                }
            };
            novaDiv.innerHTML = `
                <div class="icon"><i class="fas fa-image"></i></div>
                <span class="hint">Arraste/solte ou clique</span>
                <input type="file" name="imagem_${contadorImagem}" accept="image/*" onchange="mostrarPreview(this); toggleDescricao(this)">
                <img src="" class="preview" alt="">
                <input type="text" name="imagem_descricao_${contadorImagem}" class="descricao-imagem" placeholder="Descrição da imagem..." style="display:none">
            `;
            container.insertBefore(novaDiv, botaoAdd);
        }

        function adicionarCampoPdf() {
            contadorPdf++;
            const container = document.getElementById('container-pdfs');
            const botaoAdd = document.getElementById('add-pdf-btn');
            const div = document.createElement('div');
            div.className = 'drop-zone pdf';
            div.setAttribute('role', 'button');
            div.setAttribute('tabindex', '0');
            div.onclick = () => div.querySelector('input[type=file]').click();
            div.onkeydown = (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    div.click();
                    ev.preventDefault();
                }
            };
            div.innerHTML = `
                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                <span class="hint nome-pdf">Adicionar anexo</span>
                <input type="file" name="pdf_${contadorPdf}" accept="application/pdf" onchange="marcarPdfAdicionado(this)">
                <input type="text" name="pdf_descricao_${contadorPdf}" placeholder="Descrição do anexo">
            `;
            container.insertBefore(div, botaoAdd);
        }

        function adicionarCampoVideo() {
            contadorVideo++;
            const container = document.getElementById('container-videos');
            const addBtn = document.getElementById('add-video-btn');
            const wrap = document.createElement('div');
            wrap.className = 'video-card';
            wrap.setAttribute('data-index', String(contadorVideo));
            wrap.innerHTML = `
                <div class="video-header">
                    <i class="fab fa-youtube"></i> <span class="video-hint">Insira o link YouTube.</span>
                </div>
                <div class="video-actions">
                    <input type="url" name="youtube_url_${contadorVideo}" placeholder="https://www.youtube.com/watch?v=..." oninput="updateVideoPreview(this)" aria-label="Link do vídeo do YouTube">
                    <input type="text" name="youtube_desc_${contadorVideo}" placeholder="Descrição do vídeo (opcional)">
                </div>
                <div class="video-iframe" hidden>
                    <iframe allowfullscreen loading="lazy"></iframe>
                </div>
            `;
            container.insertBefore(wrap, addBtn);
        }

        function adicionarCampoAudio() {
            contadorAudio++;
            const container = document.getElementById('container-audios');
            const addBtn = document.getElementById('add-audio-btn');
            const div = document.createElement('div');
            div.className = 'drop-zone audio';
            div.setAttribute('role', 'button');
            div.setAttribute('tabindex', '0');
            div.onclick = () => div.querySelector('input[type=file]').click();
            div.onkeydown = (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    div.click();
                    ev.preventDefault();
                }
            };
            div.innerHTML = `
                <div class="icon"><i class="fas fa-file-audio"></i></div>
                <span class="hint nome-audio">Arraste/solte ou clique</span>
                <input type="file" name="audio_${contadorAudio}" accept="audio/*" onchange="marcarAudioAdicionado(this)">
                <input type="text" name="audio_descricao_${contadorAudio}" placeholder="Descrição do áudio (opcional)">
            `;
            container.insertBefore(div, addBtn);
        }

        // -------- YouTube preview --------
        function extractYouTubeId(url) {
            if (!url) return '';
            const patterns = [
                /youtu\.be\/([a-zA-Z0-9_-]{11})/i,
                /youtube\.com\/(?:embed|shorts)\/([a-zA-Z0-9_-]{11})/i,
                /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i,
                /youtube\.com\/watch\?.*?[&?]v=([a-zA-Z0-9_-]{11})/i
            ];
            for (const p of patterns) {
                const m = url.match(p);
                if (m && m[1]) return m[1];
            }
            return '';
        }

        function updateVideoPreview(inputEl) {
            const card = inputEl.closest('.video-card');
            const url = inputEl.value.trim();
            const vid = extractYouTubeId(url);
            const box = card.querySelector('.video-iframe');
            const iframe = box ? box.querySelector('iframe') : null;
            if (vid && iframe) {
                iframe.src = `https://www.youtube.com/embed/${vid}`;
                box.hidden = false;
            } else if (iframe) {
                iframe.src = '';
                box.hidden = true;
            }
        }

        // =================== SUBMIT ÚNICO (valida antes de abrir modal) ===================
        (function () {
            const form = document.querySelector("form.form-artigo");
            if (!form) return;

            const overlay = document.getElementById("mp-progress");
            const fill = document.getElementById("mp-progress-fill");
            const num = document.getElementById("mp-progress-num");

            const MAX_MB = 5, MAX_BYTES = MAX_MB * 1024 * 1024;

            function toast(msg) {
                alert(msg);
            }

            function validateAudioInput(input) {
                const file = input.files && input.files[0];
                if (!file) return true;
                if (file.size > MAX_BYTES) {
                    const dz = input.closest('.drop-zone');
                    const nomeSpan = dz ? dz.querySelector('.nome-audio') : null;
                    input.value = '';
                    if (nomeSpan) {
                        nomeSpan.textContent = 'Arraste/solte ou clique';
                        nomeSpan.style.color = '';
                    }
                    toast(`⚠️ O áudio excede ${MAX_MB}MB. Selecione um arquivo menor.`);
                    input.setCustomValidity(`O áudio deve ter no máximo ${MAX_MB}MB.`);
                    input.reportValidity();
                    return false;
                }
                input.setCustomValidity('');
                return true;
            }

            function validateAllAudios() {
                const audios = form.querySelectorAll('input[type="file"][name^="audio_"]');
                for (const inp of audios) {
                    if (!validateAudioInput(inp)) return false;
                }
                return true;
            }

            form.addEventListener("submit", function (e) {
                e.preventDefault();

                // 1) Validar CAPA primeiro (para mostrar alerta visível)
                const capa = document.getElementById('capa');
                if (!capa || !capa.files || capa.files.length === 0) {
                    toast('⚠️ Você precisa adicionar uma foto de capa antes de publicar.');
                    // foca/rola até o card da capa
                    const dz = document.getElementById('hint-capa')?.closest('.drop-zone');
                    if (dz) dz.scrollIntoView({behavior: 'smooth', block: 'center'});
                    return;
                }

                // 2) Agora sim, validação HTML5 padrão dos demais 'required'
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // 3) Categoria obrigatória (mensagem amigável + foco)
                const categoria = document.getElementById('categoria_id');
                if (!categoria || !categoria.value || categoria.value.trim() === '') {
                    categoria.setCustomValidity('Por favor, selecione uma categoria antes de publicar.');
                    categoria.reportValidity();
                    categoria.focus({preventScroll: false});
                    categoria.scrollIntoView({behavior: 'smooth', block: 'center'});
                    return;
                } else {
                    categoria.setCustomValidity('');
                }

                // 4) Áudios até 5MB
                if (!validateAllAudios()) return;

                // 5) Serializa conteúdo do Quill
                const hidden = document.getElementById('conteudo');
                if (hidden && window.quill) hidden.value = quill.root.innerHTML;

                // 6) Envia — agora pode abrir o modal
                const xhr = new XMLHttpRequest();
                const data = new FormData(form);

                xhr.upload.addEventListener("progress", function (ev) {
                    if (ev.lengthComputable) {
                        const percent = Math.round((ev.loaded / ev.total) * 100);
                        fill.style.width = percent + "%";
                        num.textContent = percent;
                    }
                });

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            num.textContent = "100";
                            fill.style.width = "100%";
                            setTimeout(() => {
                                overlay.style.display = "none";
                                window.location.href = "?enviado=1&aba=historico";
                            }, 600);
                        } else {
                            alert("❌ Erro ao enviar. Tente novamente.");
                            overlay.style.display = "none";
                        }
                    }
                };

                xhr.open("POST", window.location.href);
                overlay.style.display = "flex";
                xhr.send(data);
            });
        })();

        // -------- Contadores --------
        (function () {
            const resumoEl = document.getElementById('resumo');
            const resumoCountEl = document.getElementById('resumo-count');
            if (resumoEl && resumoCountEl) {
                function updateResumoCount() {
                    resumoCountEl.textContent = String((resumoEl.value || '').length);
                }

                ['input', 'change', 'keyup', 'paste', 'cut'].forEach(evt => resumoEl.addEventListener(evt, updateResumoCount));
                updateResumoCount();
                resumoCountEl.setAttribute('aria-live', 'polite');
            }
            const tituloEl = document.getElementById('titulo');
            const tituloCountEl = document.getElementById('titulo-count');
            if (tituloEl && tituloCountEl) {
                function updateTituloCount() {
                    tituloCountEl.textContent = String((tituloEl.value || '').length);
                }

                ['input', 'change', 'keyup', 'paste', 'cut'].forEach(evt => tituloEl.addEventListener(evt, updateTituloCount));
                updateTituloCount();
                tituloCountEl.setAttribute('aria-live', 'polite');
            }
        })();
    </script>
    <?php
    return ob_get_clean();
});
