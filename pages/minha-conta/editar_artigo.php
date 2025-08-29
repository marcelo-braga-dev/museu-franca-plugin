<?php
// Permitir alguns formatos adicionais de áudio
add_filter('upload_mimes', function ($m) {
    $m['aac'] = 'audio/aac';
    $m['opus'] = 'audio/opus';
    $m['flac'] = 'audio/flac';
    $m['m4a'] = 'audio/m4a';
    return $m;
});

add_shortcode('editar_artigo', function () {

    if (!is_user_logged_in()) return '<p>Você precisa estar logado para editar um artigo.</p>';
    if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) return '<p>Artigo não encontrado.</p>';

    $post_id = (int)$_GET['post_id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'artigo') return '<p>Artigo inválido.</p>';

    // Permissões: autor do post ou quem pode editar
    $is_author = ((int)$post->post_author === (int)get_current_user_id());
    if (!$is_author && !current_user_can('edit_post', $post_id)) {
        return '<p>Você não tem permissão para editar este artigo.</p>';
    }

    // ---------- Helpers ----------
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
                    '/youtube\.com\/watch\?.*?[&?]v=([a-zA-Z0-9_-]{11})/i'
            ];
            foreach ($patterns as $p) {
                if (preg_match($p, $url, $m)) return $m[1];
            }
            return '';
        }
    }

    // ---------- Carregar dados atuais ----------
    $titulo = $post->post_title;
    $resumo = $post->post_excerpt;
    $conteudo = $post->post_content;

    $thumb_id = get_post_thumbnail_id($post_id);

    $imagens = array_map('intval', (array)get_post_meta($post_id, 'imagem_adicional'));
    $imagens = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, $imagens)));

    $pdfs = array_map('intval', (array)get_post_meta($post_id, 'pdf'));
    $pdfs = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, $pdfs)));

    // 🔊 Áudios existentes (IDs anexados em meta 'audio')
    $audios = array_map('intval', (array)get_post_meta($post_id, 'audio'));
    $audios = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, $audios)));

    $videos = (array)get_post_meta($post_id, 'youtube_link'); // arrays(url,desc,video_id)
    $videos = array_values(array_map(function ($v) {
        return is_array($v) ? $v : maybe_unserialize($v);
    }, $videos));

    $terms_cat = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
    $selected_cat = is_wp_error($terms_cat) || empty($terms_cat) ? '' : (int)$terms_cat[0];

    $tags_terms = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    $tags_str = is_wp_error($tags_terms) || empty($tags_terms) ? '' : implode(', ', $tags_terms);

    $erro_msg = '';

    // ---------- POST: atualizar ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mp_edit_nonce']) && wp_verify_nonce($_POST['mp_edit_nonce'], 'mp_editar_' . $post_id)) {

        $novo_titulo = sanitize_text_field($_POST['titulo'] ?? '');
        $novo_resumo = sanitize_text_field($_POST['resumo'] ?? '');
        $novo_conteudo = wp_kses_post($_POST['conteudo'] ?? '');
        $nova_cat = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
        $novas_tags = sanitize_text_field($_POST['tags'] ?? '');

        // Validações
        if ($novo_titulo === '') $erro_msg .= '• Informe um título.<br>';
        if ($novo_resumo !== '' && mb_strlen($novo_resumo) > 250) {
            $erro_msg .= '• O resumo deve ter no máximo 250 caracteres.<br>';
        }
        if ($nova_cat <= 0) $erro_msg .= '• Selecione uma categoria.<br>';

        // Capa obrigatória se não existir e nenhuma nova for enviada
        $tem_capa_atual = (bool)get_post_thumbnail_id($post_id);
        $tem_capa_nova = (!empty($_FILES['capa']['name']));
        if (!$tem_capa_atual && !$tem_capa_nova) {
            $erro_msg .= '• Adicione uma foto de capa.<br>';
        }

        // ======= Validações SERVER-SIDE dos NOVOS ÁUDIOS (50MB + tipo) =======
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
        $max_bytes = 50 * 1024 * 1024; // 50MB

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
                    $erro_msg .= '• O áudio "' . esc_html($file['name']) . '" excede 50MB. Envie um arquivo menor.<br>';
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $type_ok = in_array($file['type'], $allowed_mimes, true) || in_array($ext, $allowed_exts, true);
                if (!$type_ok) {
                    $erro_msg .= '• Formato de áudio não permitido para "' . esc_html($file['name']) . '". Envie mp3, m4a, wav, ogg, aac, opus ou flac.<br>';
                }
            }
        }
        // ================================================================

        if ($erro_msg === '') {
            // Atualiza o post
            $ok = wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $novo_titulo,
                    'post_excerpt' => $novo_resumo,
                    'post_content' => $novo_conteudo,
            ], true);

            if (is_wp_error($ok)) {
                $erro_msg = 'Erro ao atualizar o artigo. Tente novamente.';
            } else {

                // Categorias / Tags
                if ($nova_cat > 0) {
                    wp_set_post_terms($post_id, [$nova_cat], 'category', false);
                }
                if ($novas_tags !== '') {
                    $tags = array_map('trim', explode(',', $novas_tags));
                    wp_set_post_terms($post_id, $tags, 'post_tag', false);
                } else {
                    wp_set_post_terms($post_id, [], 'post_tag', false);
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

                // Capa (substitui se enviada)
                if ($tem_capa_nova) {
                    $capa_id = media_handle_upload('capa', $post_id);
                    if (!is_wp_error($capa_id)) {
                        set_post_thumbnail($post_id, $capa_id);
                    }
                }

                // ---------- Imagens existentes: descrições + exclusões ----------
                if (!empty($_POST['imagem_desc_existente']) && is_array($_POST['imagem_desc_existente'])) {
                    foreach ($_POST['imagem_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        $desc = sanitize_text_field($desc);
                        if ($att_id > 0) {
                            update_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id, $desc);
                        }
                    }
                }
                if (!empty($_POST['del_imagem']) && is_array($_POST['del_imagem'])) {
                    foreach ($_POST['del_imagem'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) {
                            delete_post_meta($post_id, 'imagem_adicional', $att_id);
                            delete_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id);
                        }
                    }
                }
                // Novas imagens
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

                // ---------- PDFs existentes: descrições + exclusões ----------
                if (!empty($_POST['pdf_desc_existente']) && is_array($_POST['pdf_desc_existente'])) {
                    foreach ($_POST['pdf_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        $desc = sanitize_text_field($desc);
                        if ($att_id > 0) {
                            update_post_meta($post_id, 'pdf_descricao_' . $att_id, $desc);
                        }
                    }
                }
                if (!empty($_POST['del_pdf']) && is_array($_POST['del_pdf'])) {
                    foreach ($_POST['del_pdf'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) {
                            delete_post_meta($post_id, 'pdf', $att_id);
                            delete_post_meta($post_id, 'pdf_descricao_' . $att_id);
                        }
                    }
                }
                // Novos PDFs
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

                // ---------- ÁUDIOS existentes: descrições + exclusões ----------
                if (!empty($_POST['audio_desc_existente']) && is_array($_POST['audio_desc_existente'])) {
                    foreach ($_POST['audio_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        $desc = sanitize_text_field($desc);
                        if ($att_id > 0) {
                            update_post_meta($post_id, 'audio_descricao_' . $att_id, $desc);
                        }
                    }
                }
                if (!empty($_POST['del_audio']) && is_array($_POST['del_audio'])) {
                    foreach ($_POST['del_audio'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) {
                            delete_post_meta($post_id, 'audio', $att_id);
                            delete_post_meta($post_id, 'audio_descricao_' . $att_id);
                        }
                    }
                }
                // Novos ÁUDIOS (já validados acima)
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

                // ---------- YouTube: reconstruir a lista ----------
                delete_post_meta($post_id, 'youtube_link'); // zera
                // Regrava existentes (não excluídos)
                if (!empty($_POST['yt_exist_url']) && is_array($_POST['yt_exist_url'])) {
                    foreach ($_POST['yt_exist_url'] as $idx => $url) {
                        $url = esc_url_raw($url);
                        $desc = sanitize_text_field($_POST['yt_exist_desc'][$idx] ?? '');
                        $del = !empty($_POST['yt_exist_del'][$idx]);
                        if ($del) continue;
                        if ($url !== '') {
                            $vid_id = mp_extract_youtube_id($url);
                            add_post_meta($post_id, 'youtube_link', [
                                    'url' => $url,
                                    'desc' => $desc,
                                    'video_id' => $vid_id
                            ]);
                        }
                    }
                }
                // Novos
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
                                    'video_id' => $vid_id
                            ]);
                        }
                    }
                }

                // Redireciona
                $url = site_url('/minha-conta/?aba=historico&edit=1');
                echo "<script>window.location.href = " . json_encode($url) . ";</script>";
                exit;
            }
        }
    }

    // Recarrega dados caso tenha erro
    if ($erro_msg !== '') {
        $titulo = sanitize_text_field($_POST['titulo'] ?? $titulo);
        $resumo = sanitize_text_field($_POST['resumo'] ?? $resumo);
        $conteudo = wp_kses_post($_POST['conteudo'] ?? $conteudo);
        $selected_cat = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : $selected_cat;
        $tags_str = sanitize_text_field($_POST['tags'] ?? $tags_str);
    }

    // Categorias
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
            padding: 16px;
        }

        .heading {
            margin: 8px 0 18px;
            font-size: clamp(18px, 2.5vw, 22px);
            color: var(--text);
            font-weight: 700;
        }

        .form-artigo {
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
            display: block;
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

        .descricao-imagem {
            display: block
        }

        .video-card {
            border: 1px solid var(--stroke);
            border-radius: var(--radius);
            padding: 12px;
            display: flex;
            flex-direction: column;
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

        .pill-del {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff3f2;
            border: 1px solid #ffd9d6;
            color: #9c1c0e;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 13px
        }

        .linha-existente {
            display: grid;
            gap: 8px
        }

        .erro-box {
            background: #fff3f2;
            border: 1px solid #ffd9d6;
            color: #9c1c0e;
            border-radius: 10px;
            padding: 12px
        }

        .hint-mini {
            font-size: 12px;
            color: var(--muted)
        }

        .grid-videos {
            align-items: start;
            grid-auto-rows: auto !important
        }

        .grid-videos > * {
            align-self: start
        }

        .video-actions {
            display: grid;
            gap: 8px;
            grid-auto-rows: max-content;
            align-content: start
        }

        .video-card input[type=url], .video-card input[type=text] {
            height: 44px;
            line-height: 44px
        }

        .capa-atual {
            grid-template-columns:1fr;
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
        <h5 class="heading">Editar Publicação</h5>

        <?php if ($erro_msg !== ''): ?>
            <div class="erro-box" role="alert">
                <strong>Corrija os itens abaixo:</strong><br>
                <?= wp_kses_post($erro_msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-artigo" novalidate
              aria-label="Formulário de edição de artigo">
            <?php wp_nonce_field('mp_editar_' . $post_id, 'mp_edit_nonce'); ?>

            <div class="grid">
                <label for="titulo">Título da Publicação</label>
                <input type="text" name="titulo" id="titulo" required value="<?= esc_attr($titulo); ?>" maxlength="100">
                <div class="hint-mini"><span id="titulo-count">0</span>/100</div>
            </div>

            <div class="grid">
                <label for="resumo">Resumo</label>
                <textarea name="resumo" id="resumo" rows="2" maxlength="250"><?= esc_textarea($resumo); ?></textarea>
                <div class="hint-mini"><span id="resumo-count">0</span>/250</div>
            </div>

            <div class="grid">
                <label for="conteudo">Conteúdo</label>
                <div id="editor"></div>
                <textarea name="conteudo" id="conteudo" hidden style="display:none"></textarea>
            </div>

            <div class="grid">
                <label>Foto de Capa</label>
                <?php if ($thumb_id): ?>
                    <div class="linha-existente">
                        <div class="capa-atual" aria-label="Capa atual">
                            <?= wp_get_attachment_image($thumb_id, 'medium', false, ['class' => 'preview', 'style' => 'max-height:220px;border-radius:8px']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="drop-zone" role="button" tabindex="0" aria-label="Substituir foto de capa"
                     onclick="document.getElementById('capa').click()"
                     onkeydown="if(event.key==='Enter'||event.key===' '){document.getElementById('capa').click();event.preventDefault();}">
                    <div class="icon"><i class="fas fa-image"></i></div>
                    <span class="hint" id="hint-capa">Arraste uma imagem ou clique para selecionar uma nova capa</span>
                    <input type="file" name="capa" id="capa" accept="image/*"
                           onchange="mostrarPreview(this); validarCapaMsg(this)">
                    <img src="" alt="Pré-visualização da capa" class="preview" style="display:none">
                </div>
            </div>

            <div class="grid">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id" required>
                    <option value="">Selecione a categoria...</option>
                    <?php
                    function exibir_categorias_ed($cats, $parent = 0, $prefixo = '', $selected = 0)
                    {
                        foreach ($cats as $cat) {
                            if ((int)$cat->parent === (int)$parent) {
                                $sel = ((int)$cat->term_id === (int)$selected) ? ' selected' : '';
                                echo '<option value="' . esc_attr($cat->term_id) . '"' . $sel . '>' . esc_html($prefixo . $cat->name) . '</option>';
                                exibir_categorias_ed($cats, $cat->term_id, $prefixo . ' -- ', $selected);
                            }
                        }
                    }
                    exibir_categorias_ed($categorias, 0, '', $selected_cat);
                    ?>
                </select>
            </div>

            <!-- colecao-->
            <?php if ( current_user_can('administrator') ):
                $all_colecoes = get_terms([
                        'taxonomy'   => 'colecao',
                        'hide_empty' => false
                ]);

                $selecionadas = wp_get_post_terms($post_id, 'colecao', ['fields' => 'ids']);
                $selecionadas = array_map('intval', (array) $selecionadas);
                ?>
                <div class="grid">
                    <label class="mp-label">Coleções (opcional)</label>
                    <div class="mp-colecoes-checklist">
                        <?php foreach ( $all_colecoes as $c ): ?>
                            <label class="mp-check">
                                <input
                                        type="checkbox"
                                        name="colecao_ids[]"
                                        value="<?= (int) $c->term_id; ?>"
                                        <?php checked( in_array( (int) $c->term_id, $selecionadas, true ) ); ?>
                                >
                                <span><?= esc_html( $c->name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid">
                <label for="tags">Palavras-chave (separadas por vírgula)</label>
                <input type="text" name="tags" id="tags" value="<?= esc_attr($tags_str); ?>"
                       placeholder="Palavra 1, Palavra 2, Palavra 3...">
            </div>

            <div class="grid">
                <label>Galeria de Imagens</label>

                <?php if (!empty($imagens)): ?>
                    <div class="grid-imagens">
                        <?php foreach ($imagens as $att_id):
                            $url = wp_get_attachment_url($att_id);
                            $desc = get_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id, true) ?: '';
                            ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default" aria-label="Imagem existente">
                                    <?= wp_get_attachment_image($att_id, 'medium', false, ['class' => 'preview']); ?>
                                </div>
                                <input type="text" name="imagem_desc_existente[<?= esc_attr($att_id); ?>]"
                                       value="<?= esc_attr($desc); ?>" placeholder="Descrição da imagem...">
                                <label class="pill-del">
                                    <input type="checkbox" name="del_imagem[<?= esc_attr($att_id); ?>]" value="1">
                                    <i class="fa fa-trash" style="color: red"></i> Excluir esta imagem
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="grid-imagens" id="container-imagens">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone" role="button" tabindex="0" aria-label="Adicionar imagem adicional">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-image"></i></div>
                                <span class="hint">Arraste/solte ou clique</span>
                                <input type="file" name="imagem_<?= $i ?>" accept="image/*"
                                       onchange="mostrarPreview(this); toggleDescricao(this)">
                                <img src="" class="preview" alt="" style="display:none">
                            </div>
                            <input type="text" name="imagem_descricao_<?= $i ?>" class="descricao-imagem"
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

                <?php if (!empty($pdfs)): ?>
                    <div class="grid-pdfs">
                        <?php foreach ($pdfs as $att_id):
                            $url = wp_get_attachment_url($att_id);
                            $desc = get_post_meta($post_id, 'pdf_descricao_' . $att_id, true) ?: '';
                            ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default" aria-label="PDF existente">
                                    <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                    <span class="hint"><?= esc_html(basename($url)); ?></span>
                                </div>
                                <input type="text" name="pdf_desc_existente[<?= esc_attr($att_id); ?>]"
                                       value="<?= esc_attr($desc); ?>" placeholder="Descrição do anexo">
                                <label class="pill-del">
                                    <input type="checkbox" name="del_pdf[<?= esc_attr($att_id); ?>]" value="1">
                                    Excluir este PDF
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="grid-pdfs" id="container-pdfs">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone pdf" role="button" tabindex="0" aria-label="Adicionar PDF">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                <span class="hint nome-pdf">Adicionar anexo</span>
                                <input type="file" name="pdf_<?= $i ?>" accept="application/pdf"
                                       onchange="marcarPdfAdicionado(this)">
                            </div>
                            <input type="text" name="pdf_descricao_<?= $i ?>" placeholder="Descrição do anexo">
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

            <!-- 🔊 ÁUDIOS -->
            <div class="grid">
                <label>Áudios</label>

                <?php if (!empty($audios)): ?>
                    <div class="grid-audios">
                        <?php foreach ($audios as $att_id):
                            $url = wp_get_attachment_url($att_id);
                            $mime = get_post_mime_type($att_id) ?: 'audio/mpeg';
                            $file_name = $url ? basename(parse_url($url, PHP_URL_PATH)) : ('ID ' . $att_id);
                            $desc = get_post_meta($post_id, 'audio_descricao_' . $att_id, true) ?: '';
                            ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default" aria-label="Áudio existente">
                                    <div class="icon"><i class="fas fa-file-audio"></i></div>
                                    <span class="hint"><?= esc_html($file_name); ?> · <?= esc_html($mime); ?></span>
                                    <?php if ($url): ?>
                                        <audio controls style="width:100%; margin-top:8px">
                                            <source src="<?= esc_url($url); ?>" type="<?= esc_attr($mime); ?>">
                                        </audio>
                                    <?php endif; ?>
                                </div>
                                <input type="text" name="audio_desc_existente[<?= esc_attr($att_id); ?>]"
                                       value="<?= esc_attr($desc); ?>" placeholder="Descrição do áudio (opcional)">
                                <label class="pill-del">
                                    <input type="checkbox" name="del_audio[<?= esc_attr($att_id); ?>]" value="1">
                                    Excluir este áudio
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="grid-audios" id="container-audios">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone audio" role="button" tabindex="0" aria-label="Adicionar Áudio">
                            <div onclick="this.querySelector('input[type=file]').click()"
                                 onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('input[type=file]').click();event.preventDefault();}">
                                <div class="icon"><i class="fas fa-file-audio"></i></div>
                                <span class="hint nome-audio">Adicionar arquivo de áudio (MP3, M4A, WAV...)</span>
                                <input type="file" name="audio_<?= $i ?>" accept="audio/*"
                                       onchange="marcarAudioAdicionado(this)">
                            </div>
                            <input type="text" name="audio_descricao_<?= $i ?>"
                                   placeholder="Descrição do áudio (opcional)">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-audio-btn" role="button" tabindex="0"
                         aria-label="Adicionar novo campo de áudio" onclick="adicionarCampoAudio()"
                         onkeydown="if(event.key==='Enter'||event.key===' '){adicionarCampoAudio();event.preventDefault();}">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint">Adicionar outro áudio</span>
                    </div>
                </div>
                <div class="hint-mini">Formatos aceitos: mp3, m4a, wav, ogg, aac, opus, flac. Tamanho máx.:
                    <strong>50MB</strong>.
                </div>
            </div>
            <!-- /ÁUDIOS -->

            <div class="grid">
                <label>Vídeos do YouTube</label>

                <?php if (!empty($videos)): ?>
                    <div class="grid-videos" id="yt-existentes">
                        <?php foreach ($videos as $idx => $item):
                            $u = is_array($item) ? ($item['url'] ?? '') : '';
                            $d = is_array($item) ? ($item['desc'] ?? '') : '';
                            $vid = is_array($item) ? ($item['video_id'] ?? '') : '';
                            ?>
                            <div class="video-card">
                                <div class="video-header">
                                    <i class="fab fa-youtube"></i> <span class="video-hint">Vídeo existente</span>
                                </div>
                                <div class="video-actions">
                                    <input type="url" name="yt_exist_url[<?= esc_attr($idx); ?>]"
                                           value="<?= esc_attr($u); ?>" oninput="updateVideoPreview(this)"
                                           placeholder="https://www.youtube.com/watch?v=...">
                                    <input type="text" name="yt_exist_desc[<?= esc_attr($idx); ?>]"
                                           value="<?= esc_attr($d); ?>" placeholder="Descrição do vídeo (opcional)">
                                    <label class="pill-del">
                                        <input type="checkbox" name="yt_exist_del[<?= esc_attr($idx); ?>]" value="1">
                                        Excluir este vídeo
                                    </label>
                                </div>
                                <div class="video-iframe" <?= $vid ? '' : 'hidden'; ?>>
                                    <iframe allowfullscreen loading="lazy"
                                            src="<?= $vid ? esc_url('https://www.youtube.com/embed/' . $vid) : ''; ?>"></iframe>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="grid-videos" id="container-videos">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="video-card" data-index="<?= $i ?>">
                            <div class="video-header">
                                <i class="fab fa-youtube"></i> <span class="video-hint">Insira o link YouTube.</span>
                            </div>
                            <div class="video-actions">
                                <input type="url" name="youtube_url_<?= $i ?>"
                                       placeholder="https://www.youtube.com/watch?v=..."
                                       oninput="updateVideoPreview(this)">
                                <input type="text" name="youtube_desc_<?= $i ?>"
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
                    <input type="checkbox" id="check" required>
                    <div>
                        <strong>Confirmação:</strong> Declaro que revisei os dados e desejo atualizar esta publicação.
                    </div>
                </div>
            </div>

            <button type="submit">Salvar alterações</button>
        </form>
    </div>

    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
        // -------- Dirty flag (avisar se sair com alterações) --------
        let formDirty = false;
        const formEl = document.querySelector('form.form-artigo');
        formEl.addEventListener('input', () => formDirty = true);
        window.addEventListener('beforeunload', (e) => {
            if (formDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // -------- Editor Quill --------
        const quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Edite seu conteúdo...',
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
        quill.root.innerHTML = <?= json_encode($conteudo); ?>;
        formEl.addEventListener('submit', function () {
            document.querySelector('#conteudo').value = quill.root.innerHTML;
            formDirty = false;
        });

        // -------- Contador do resumo --------
        const resumoEl = document.getElementById('resumo');
        const resumoCountEl = document.getElementById('resumo-count');

        function updateResumoCount() {
            const len = (resumoEl.value || '').length;
            resumoCountEl.textContent = String(len);
        }

        resumoEl.addEventListener('input', updateResumoCount);
        updateResumoCount();

        // -------- Upload UX --------
        function mostrarPreview(input) {
            const file = input.files[0];
            const dz = input.closest('.drop-zone');
            const img = dz.querySelector('img.preview');
            if (!img) return;
            if (file && file.type.startsWith('image/')) {
                img.src = URL.createObjectURL(file);
                img.style.display = 'block';
                const hint = dz.querySelector('.hint');
                if (hint) hint.style.display = 'none';
            } else {
                img.src = '';
                img.style.display = 'none';
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
                nomeSpan.textContent = 'Adicionar arquivo de áudio (MP3, M4A, WAV...)';
                nomeSpan.style.color = '';
            }
        }

        function toggleDescricao(input) {
            const inputDescricao = input.closest('.drop-zone').querySelector('.descricao-imagem');
            if (!inputDescricao) return;
            inputDescricao.style.display = (input.files && input.files.length > 0) ? 'block' : 'none';
        }

        // -------- Validações de envio --------
        formEl.addEventListener('submit', function (e) {
            const categoria = document.getElementById('categoria_id');
            if (!categoria || !categoria.value || categoria.value.trim() === '') {
                e.preventDefault();
                categoria.setCustomValidity('Por favor, selecione uma categoria antes de salvar.');
                categoria.reportValidity();
                categoria.focus({preventScroll: false});
                categoria.scrollIntoView({behavior: 'smooth', block: 'center'});
                return false;
            } else {
                categoria.setCustomValidity('');
            }

            const temCapaAtual = <?= $thumb_id ? 'true' : 'false'; ?>;
            const capaInput = document.getElementById('capa');
            const temCapaNova = capaInput && capaInput.files && capaInput.files.length > 0;
            if (!temCapaAtual && !temCapaNova) {
                alert('⚠️ Você precisa adicionar uma foto de capa.');
                e.preventDefault();
                return false;
            }
        });

        // -------- Contadores dinâmicos --------
        let contadorImagem = 2;
        let contadorPdf = 2;
        let contadorVideo = 2;
        let contadorAudio = 2;

        function adicionarCampoImagem() {
            contadorImagem++;
            const container = document.getElementById('container-imagens');
            const botaoAdd = document.getElementById('add-imagem-btn');
            const novaDiv = document.createElement('div');
            novaDiv.className = 'drop-zone';
            novaDiv.setAttribute('role', 'button');
            novaDiv.setAttribute('tabindex', '0');
            novaDiv.setAttribute('aria-label', 'Adicionar imagem adicional');
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
                <img src="" class="preview" alt="" style="display:none">
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
            div.setAttribute('aria-label', 'Adicionar PDF');
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

        // -------- Áudios (CLIENT-SIDE: limite 50MB + reset do nome) --------
        function adicionarCampoAudio() {
            contadorAudio++;
            const container = document.getElementById('container-audios');
            const botaoAdd = document.getElementById('add-audio-btn');
            const div = document.createElement('div');
            div.className = 'drop-zone audio';
            div.setAttribute('role', 'button');
            div.setAttribute('tabindex', '0');
            div.setAttribute('aria-label', 'Adicionar Áudio');
            div.onclick = () => div.querySelector('input[type=file]').click();
            div.onkeydown = (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    div.click();
                    ev.preventDefault();
                }
            };
            div.innerHTML = `
                <div class="icon"><i class="fas fa-file-audio"></i></div>
                <span class="hint nome-audio">Adicionar arquivo de áudio (MP3, M4A, WAV...)</span>
                <input type="file" name="audio_${contadorAudio}" accept="audio/*" onchange="marcarAudioAdicionado(this)">
                <input type="text" name="audio_descricao_${contadorAudio}" placeholder="Descrição do áudio (opcional)">
            `;
            container.insertBefore(div, botaoAdd);
        }

        // -------- YouTube --------
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
            if (!card) return;
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
                    <input type="url" name="youtube_url_${contadorVideo}" placeholder="https://www.youtube.com/watch?v=..." oninput="updateVideoPreview(this)">
                    <input type="text" name="youtube_desc_${contadorVideo}" placeholder="Descrição do vídeo (opcional)">
                </div>
                <div class="video-iframe" hidden>
                    <iframe allowfullscreen loading="lazy"></iframe>
                </div>
            `;
            container.insertBefore(wrap, addBtn);
        }

        // Inicializa previews de vídeos existentes
        document.querySelectorAll('#yt-existentes input[type=url]').forEach(inp => {
            if (inp.value) updateVideoPreview(inp);
        });

        // --- Contador de caracteres do título ---
        (function () {
            const tituloEl = document.getElementById('titulo');
            const tituloCountEl = document.getElementById('titulo-count');
            if (!tituloEl || !tituloCountEl) return;

            function updateTituloCount() {
                const len = (tituloEl.value || '').length;
                tituloCountEl.textContent = String(len);
            }

            ['input', 'change', 'keyup', 'paste', 'cut'].forEach(evt => {
                tituloEl.addEventListener(evt, updateTituloCount);
            });
            updateTituloCount();
            tituloCountEl.setAttribute('aria-live', 'polite');
        })();

        // ====== Limite de 50MB para áudios (CLIENT-SIDE) com reset do nome ======
        (function () {
            const MAX_MB = 50;
            const MAX_BYTES = MAX_MB * 1024 * 1024;

            function toast(msg, ok = false) {
                const n = document.createElement('div');
                n.textContent = msg;
                n.style.cssText = `
                    position:fixed;bottom:20px;left:50%;transform:translateX(-50%);
                    background:${ok ? '#0f172a' : '#ef4444'};color:#fff;padding:10px 14px;border-radius:999px;
                    font-weight:700;box-shadow:0 10px 24px rgba(0,0,0,.2);z-index:10001
                `;
                document.body.appendChild(n);
                setTimeout(() => n.remove(), ok ? 1600 : 2400);
            }

            function validateAudioInput(input) {
                const file = input.files && input.files[0];
                const dz = input.closest('.drop-zone');
                const nomeSpan = dz ? dz.querySelector('.nome-audio') : null;

                if (!file) return true;

                if (file.size > MAX_BYTES) {
                    // limpa o campo
                    input.value = '';

                    // reseta o texto visível no card
                    if (nomeSpan) {
                        nomeSpan.textContent = `O arquivo deve ter no máximo ${MAX_MB}MB!`;
                        nomeSpan.style.color = 'red';
                    }

                    toast(`⚠️ O áudio excede ${MAX_MB}MB. Selecione um arquivo menor.`, false);

                    // acessibilidade
                    input.setCustomValidity(`O áudio deve ter no máximo ${MAX_MB}MB.`);
                    input.reportValidity();
                    return false;
                }

                input.setCustomValidity('');
                return true;
            }

            function bindAudioValidators(root = document) {
                const audios = root.querySelectorAll('input[type="file"][name^="audio_"]');
                audios.forEach(inp => {
                    if (!inp.hasAttribute('accept')) inp.setAttribute('accept', 'audio/*');
                    inp.addEventListener('change', () => validateAudioInput(inp));
                });
            }

            function guardOnSubmit(form) {
                form.addEventListener('submit', (e) => {
                    const audios = form.querySelectorAll('input[type="file"][name^="audio_"]');
                    for (const inp of audios) {
                        if (!validateAudioInput(inp)) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    }
                    return true;
                });
            }

            const form = document.querySelector('form.form-artigo');
            if (form) {
                bindAudioValidators(document);
                guardOnSubmit(form);
            }
        })();
    </script>
    <?php
    return ob_get_clean();
});
