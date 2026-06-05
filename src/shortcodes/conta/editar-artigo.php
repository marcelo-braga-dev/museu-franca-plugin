<?php
if (!defined('ABSPATH')) exit;

add_shortcode('editar_artigo', function () {

    if (!is_user_logged_in()) return '<p>Você precisa estar logado para editar um artigo.</p>';
    if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) return '<p>Artigo não encontrado.</p>';

    $post_id = (int)$_GET['post_id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'artigo') return '<p>Artigo inválido.</p>';

    $is_author = ((int)$post->post_author === (int)get_current_user_id());
    if (!$is_author && !current_user_can('edit_post', $post_id)) {
        return '<p>Você não tem permissão para editar este artigo.</p>';
    }

    $titulo   = $post->post_title;
    $resumo   = $post->post_excerpt;
    $conteudo = $post->post_content;

    $thumb_id = get_post_thumbnail_id($post_id);

    $imagens = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, (array)get_post_meta($post_id, MP_META_IMG))));

    $pdfs = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, (array)get_post_meta($post_id, MP_META_PDF))));

    $audios = array_values(array_filter(array_map(function ($v) {
        return is_array($v) ? intval(reset($v)) : intval($v);
    }, (array)get_post_meta($post_id, MP_META_AUDIO))));

    $videos = array_values(array_map(function ($v) {
        return is_array($v) ? $v : maybe_unserialize($v);
    }, (array)get_post_meta($post_id, MP_META_YOUTUBE)));

    $terms_cat   = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
    $selected_cat = is_wp_error($terms_cat) || empty($terms_cat) ? '' : (int)$terms_cat[0];

    $tags_terms = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    $tags_str   = is_wp_error($tags_terms) || empty($tags_terms) ? '' : implode(', ', $tags_terms);

    $erro_msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mp_edit_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mp_edit_nonce'])), 'mp_editar_' . $post_id)) {

        $novo_titulo   = sanitize_text_field(wp_unslash($_POST['titulo'] ?? ''));
        $novo_resumo   = sanitize_text_field(wp_unslash($_POST['resumo'] ?? ''));
        $novo_conteudo = wp_kses_post(wp_unslash($_POST['conteudo'] ?? ''));
        $nova_cat      = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
        $novas_tags    = sanitize_text_field(wp_unslash($_POST['tags'] ?? ''));

        if ($novo_titulo === '') $erro_msg .= '• Informe um título.<br>';
        if ($novo_resumo !== '' && mb_strlen($novo_resumo) > 250) {
            $erro_msg .= '• O resumo deve ter no máximo 250 caracteres.<br>';
        }
        if ($nova_cat <= 0) $erro_msg .= '• Selecione uma categoria.<br>';

        $tem_capa_atual = (bool)get_post_thumbnail_id($post_id);
        $tem_capa_nova  = (!empty($_FILES['capa']['name']));
        if (!$tem_capa_atual && !$tem_capa_nova) {
            $erro_msg .= '• Adicione uma foto de capa.<br>';
        }

        $allowed_mimes = ['audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg','audio/oga','audio/mp4','audio/aac','audio/webm','audio/m4a','audio/x-m4a','audio/flac','audio/opus'];
        $allowed_exts  = ['mp3','wav','ogg','oga','mp4','aac','webm','m4a','flac','opus'];
        $max_bytes     = 50 * 1024 * 1024;

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
                    $erro_msg .= '• O áudio "' . esc_html($file['name']) . '" excede 50MB.<br>';
                }
                $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $type_ok = in_array($file['type'], $allowed_mimes, true) || in_array($ext, $allowed_exts, true);
                if (!$type_ok) {
                    $erro_msg .= '• Formato de áudio não permitido para "' . esc_html($file['name']) . '".<br>';
                }
            }
        }

        if ($erro_msg === '') {
            $ok = wp_update_post(['ID' => $post_id, 'post_title' => $novo_titulo, 'post_excerpt' => $novo_resumo, 'post_content' => $novo_conteudo], true);

            if (is_wp_error($ok)) {
                $erro_msg = 'Erro ao atualizar o artigo. Tente novamente.';
            } else {
                if ($nova_cat > 0) wp_set_post_terms($post_id, [$nova_cat], 'category', false);
                wp_set_post_terms($post_id, $novas_tags !== '' ? array_map('trim', explode(',', $novas_tags)) : [], 'post_tag', false);

                if (!empty($_POST['colecao_ids']) && is_array($_POST['colecao_ids'])) {
                    $colecao_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['colecao_ids']), function ($v) { return $v > 0; })));
                    wp_set_post_terms($post_id, $colecao_ids ?: [], 'colecao');
                }

                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                if ($tem_capa_nova) {
                    $capa_id = media_handle_upload('capa', $post_id);
                    if (!is_wp_error($capa_id)) set_post_thumbnail($post_id, $capa_id);
                }

                if (!empty($_POST['imagem_desc_existente']) && is_array($_POST['imagem_desc_existente'])) {
                    foreach ($_POST['imagem_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) update_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id, sanitize_text_field($desc));
                    }
                }
                if (!empty($_POST['del_imagem']) && is_array($_POST['del_imagem'])) {
                    foreach ($_POST['del_imagem'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) { delete_post_meta($post_id, MP_META_IMG, $att_id); delete_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id); }
                    }
                }
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'imagem_') === 0 && !empty($file['name'])) {
                        $img_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($img_id)) {
                            add_post_meta($post_id, MP_META_IMG, $img_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field(wp_unslash($_POST['imagem_descricao_' . $indice] ?? ''));
                            if ($descricao !== '') add_post_meta($post_id, 'imagem_adicional_descricao_' . $img_id, $descricao);
                        }
                    }
                }

                if (!empty($_POST['pdf_desc_existente']) && is_array($_POST['pdf_desc_existente'])) {
                    foreach ($_POST['pdf_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) update_post_meta($post_id, 'pdf_descricao_' . $att_id, sanitize_text_field($desc));
                    }
                }
                if (!empty($_POST['del_pdf']) && is_array($_POST['del_pdf'])) {
                    foreach ($_POST['del_pdf'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) { delete_post_meta($post_id, MP_META_PDF, $att_id); delete_post_meta($post_id, 'pdf_descricao_' . $att_id); }
                    }
                }
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'pdf_') === 0 && !empty($file['name'])) {
                        $pdf_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($pdf_id)) {
                            add_post_meta($post_id, MP_META_PDF, $pdf_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field(wp_unslash($_POST['pdf_descricao_' . $indice] ?? ''));
                            if ($descricao !== '') add_post_meta($post_id, 'pdf_descricao_' . $pdf_id, $descricao);
                        }
                    }
                }

                if (!empty($_POST['audio_desc_existente']) && is_array($_POST['audio_desc_existente'])) {
                    foreach ($_POST['audio_desc_existente'] as $att_id => $desc) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) update_post_meta($post_id, 'audio_descricao_' . $att_id, sanitize_text_field($desc));
                    }
                }
                if (!empty($_POST['del_audio']) && is_array($_POST['del_audio'])) {
                    foreach ($_POST['del_audio'] as $att_id => $flag) {
                        $att_id = (int)$att_id;
                        if ($att_id > 0) { delete_post_meta($post_id, MP_META_AUDIO, $att_id); delete_post_meta($post_id, 'audio_descricao_' . $att_id); }
                    }
                }
                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                        $audio_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($audio_id)) {
                            add_post_meta($post_id, MP_META_AUDIO, $audio_id);
                            $indice = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field(wp_unslash($_POST['audio_descricao_' . $indice] ?? ''));
                            if ($descricao !== '') add_post_meta($post_id, 'audio_descricao_' . $audio_id, $descricao);
                        }
                    }
                }

                delete_post_meta($post_id, MP_META_YOUTUBE);
                if (!empty($_POST['yt_exist_url']) && is_array($_POST['yt_exist_url'])) {
                    foreach ($_POST['yt_exist_url'] as $idx => $url) {
                        $url = esc_url_raw(wp_unslash($url));
                        $desc = sanitize_text_field(wp_unslash($_POST['yt_exist_desc'][$idx] ?? ''));
                        $del  = !empty($_POST['yt_exist_del'][$idx]);
                        if ($del || $url === '') continue;
                        $vid_id = mp_extract_youtube_id($url);
                        add_post_meta($post_id, MP_META_YOUTUBE, ['url' => $url, 'desc' => $desc, 'video_id' => $vid_id]);
                    }
                }
                foreach ($_POST as $key => $val) {
                    if (strpos($key, 'youtube_url_') === 0) {
                        $i   = substr($key, strlen('youtube_url_'));
                        $url = esc_url_raw(wp_unslash($val));
                        if ($url) {
                            $desc   = sanitize_text_field(wp_unslash($_POST['youtube_desc_' . $i] ?? ''));
                            $vid_id = mp_extract_youtube_id($url);
                            add_post_meta($post_id, MP_META_YOUTUBE, ['url' => $url, 'desc' => $desc, 'video_id' => $vid_id]);
                        }
                    }
                }

                wp_safe_redirect(site_url('/minha-conta/?aba=historico&edit=1'));
                exit;
            }
        }
    }

    if ($erro_msg !== '') {
        $titulo       = sanitize_text_field(wp_unslash($_POST['titulo'] ?? $titulo));
        $resumo       = sanitize_text_field(wp_unslash($_POST['resumo'] ?? $resumo));
        $conteudo     = wp_kses_post(wp_unslash($_POST['conteudo'] ?? $conteudo));
        $selected_cat = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : $selected_cat;
        $tags_str     = sanitize_text_field(wp_unslash($_POST['tags'] ?? $tags_str));
    }

    $categorias = get_categories(['hide_empty' => false, 'orderby' => 'name']);

    ob_start();
    ?>
    <style>
        :root{--brand:#992d17;--brand-dark:#7d2613;--text:#1f2937;--muted:#6b7280;--stroke:#e5e7eb;--card:#ffffff;--shadow:0 6px 18px rgba(0,0,0,.06);--radius:12px;--radius-sm:8px;--space:16px}
        .form-wrap{max-width:980px;margin:0 auto;padding:16px}
        .heading{margin:8px 0 18px;font-size:clamp(18px,2.5vw,22px);color:var(--text);font-weight:700}
        .form-artigo{display:grid;gap:18px}
        .grid{display:grid;gap:10px;background:var(--card);border:1px solid var(--stroke);border-radius:var(--radius);padding:14px;box-shadow:var(--shadow)}
        .form-artigo label{font-weight:600;color:var(--text);font-size:14px}
        .form-artigo input[type=text],.form-artigo input[type=url],.form-artigo textarea,.form-artigo select{width:100%;padding:12px 14px;font-size:15px;border:1px solid var(--stroke);border-radius:var(--radius-sm);background:#fff;color:var(--text);outline:none;transition:box-shadow .2s,border-color .2s}
        .form-artigo input:focus,.form-artigo textarea:focus,.form-artigo select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(153,45,23,.15)}
        .form-artigo button{background:var(--brand);color:#fff;padding:14px 16px;border:none;border-radius:var(--radius);font-weight:700;cursor:pointer;width:100%;transition:background .2s,transform .02s}
        .form-artigo button:hover{background:var(--brand-dark)}
        .drop-zone{border:2px dashed var(--stroke);border-radius:var(--radius);padding:16px;text-align:center;position:relative;background:#fff;display:grid;gap:10px;align-items:center;justify-items:center;min-height:140px;outline:none}
        .drop-zone[role=button]{cursor:pointer}
        .drop-zone input[type=file]{display:none}
        .drop-zone .icon{font-size:28px;line-height:1}
        .drop-zone .hint{color:var(--muted);font-size:13px}
        .drop-zone img.preview{max-width:100%;height:auto;max-height:220px;display:block;border-radius:var(--radius-sm)}
        .grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{display:grid;gap:var(--space);grid-template-columns:1fr}
        @media(min-width:600px){.grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{grid-template-columns:repeat(2,1fr)}}
        @media(min-width:900px){.grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{grid-template-columns:repeat(3,1fr)}}
        .video-card{border:1px solid var(--stroke);border-radius:var(--radius);padding:12px;display:flex;flex-direction:column;gap:10px;background:#fff;box-shadow:var(--shadow)}
        .video-header{display:flex;align-items:center;gap:8px;color:var(--text);font-weight:600}
        .video-iframe{overflow:hidden;border-radius:var(--radius-sm);background:#000;aspect-ratio:16/9}
        .video-iframe iframe{width:100%;height:100%;border:0;display:block}
        .add-card{display:grid;place-items:center;text-align:center;border:2px dashed var(--stroke);border-radius:var(--radius);min-height:140px;background:#fff;gap:8px;cursor:pointer}
        .add-card:hover{border-color:var(--brand)}
        .pill-del{display:inline-flex;align-items:center;gap:8px;background:#fff3f2;border:1px solid #ffd9d6;color:#9c1c0e;border-radius:999px;padding:6px 10px;font-size:13px}
        .erro-box{background:#fff3f2;border:1px solid #ffd9d6;color:#9c1c0e;border-radius:10px;padding:12px}
        .hint-mini{font-size:12px;color:var(--muted)}
        .mp-colecoes-checklist{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px}
        .mp-check{position:relative;display:inline-flex;align-items:center;cursor:pointer;user-select:none}
        .mp-check input[type=checkbox]{position:absolute;opacity:0;pointer-events:none}
        .mp-check span{display:inline-flex;align-items:center;padding:6px 14px;border-radius:999px;border:1px solid #d1d5db;background:#fff;font-size:14px;font-weight:500;color:#374151;transition:all .2s ease}
        .mp-check:hover span{border-color:#9e2b19;color:#9e2b19}
        .mp-check input[type=checkbox]:checked+span{background:#9e2b19;color:#fff;border-color:#9e2b19}
    </style>

    <div class="form-wrap">
        <h5 class="heading">Editar Publicação</h5>

        <?php if ($erro_msg !== ''): ?>
            <div class="erro-box" role="alert">
                <strong>Corrija os itens abaixo:</strong><br>
                <?php echo wp_kses_post($erro_msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-artigo" novalidate aria-label="Formulário de edição de artigo">
            <?php wp_nonce_field('mp_editar_' . $post_id, 'mp_edit_nonce'); ?>

            <div class="grid">
                <label for="titulo">Título da Publicação</label>
                <input type="text" name="titulo" id="titulo" required value="<?php echo esc_attr($titulo); ?>" maxlength="100">
                <div class="hint-mini"><span id="titulo-count">0</span>/100</div>
            </div>

            <div class="grid">
                <label for="resumo">Resumo</label>
                <textarea name="resumo" id="resumo" rows="2" maxlength="250"><?php echo esc_textarea($resumo); ?></textarea>
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
                    <div><?php echo wp_get_attachment_image($thumb_id, 'medium', false, ['class' => 'preview', 'style' => 'max-height:220px;border-radius:8px']); ?></div>
                <?php endif; ?>
                <div class="drop-zone" role="button" tabindex="0" aria-label="Substituir foto de capa"
                     onclick="document.getElementById('capa').click()"
                     onkeydown="if(event.key==='Enter'||event.key===' '){document.getElementById('capa').click();event.preventDefault();}">
                    <div class="icon"><i class="fas fa-image"></i></div>
                    <span class="hint" id="hint-capa">Arraste uma imagem ou clique para selecionar uma nova capa</span>
                    <input type="file" name="capa" id="capa" accept="image/*" onchange="mostrarPreview(this); validarCapaMsg(this)">
                    <img src="" alt="" class="preview" style="display:none">
                </div>
            </div>

            <div class="grid">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id" required>
                    <option value="">Selecione a categoria...</option>
                    <?php mp_exibir_categorias($categorias, 0, '', $selected_cat); ?>
                </select>
            </div>

            <?php if (current_user_can('administrator')):
                $all_colecoes = get_terms(['taxonomy' => 'colecao', 'hide_empty' => false]);
                $selecionadas = array_map('intval', (array)wp_get_post_terms($post_id, 'colecao', ['fields' => 'ids']));
                ?>
                <div class="grid">
                    <label>Coleções (opcional)</label>
                    <div class="mp-colecoes-checklist">
                        <?php foreach ($all_colecoes as $c): ?>
                            <label class="mp-check">
                                <input type="checkbox" name="colecao_ids[]" value="<?php echo (int)$c->term_id; ?>" <?php checked(in_array((int)$c->term_id, $selecionadas, true)); ?>>
                                <span><?php echo esc_html($c->name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid">
                <label for="tags">Palavras-chave (separadas por vírgula)</label>
                <input type="text" name="tags" id="tags" value="<?php echo esc_attr($tags_str); ?>" placeholder="Palavra 1, Palavra 2...">
            </div>

            <div class="grid">
                <label>Galeria de Imagens</label>
                <?php if (!empty($imagens)): ?>
                    <div class="grid-imagens">
                        <?php foreach ($imagens as $att_id):
                            $url  = wp_get_attachment_url($att_id);
                            $desc = get_post_meta($post_id, 'imagem_adicional_descricao_' . $att_id, true) ?: ''; ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default"><?php echo wp_get_attachment_image($att_id, 'medium', false, ['class' => 'preview']); ?></div>
                                <input type="text" name="imagem_desc_existente[<?php echo esc_attr($att_id); ?>]" value="<?php echo esc_attr($desc); ?>" placeholder="Descrição da imagem...">
                                <label class="pill-del"><input type="checkbox" name="del_imagem[<?php echo esc_attr($att_id); ?>]" value="1"> <i class="fa fa-trash" style="color:red"></i> Excluir esta imagem</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="grid-imagens" id="container-imagens">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone" role="button" tabindex="0">
                            <div onclick="this.querySelector('input[type=file]').click()">
                                <div class="icon"><i class="fas fa-image"></i></div>
                                <span class="hint">Arraste/solte ou clique</span>
                                <input type="file" name="imagem_<?php echo $i; ?>" accept="image/*" onchange="mostrarPreview(this)">
                                <img src="" class="preview" alt="" style="display:none">
                            </div>
                            <input type="text" name="imagem_descricao_<?php echo $i; ?>" placeholder="Descrição da imagem..." style="display:none">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" onclick="adicionarCampoImagem()"><div class="icon"><i class="fas fa-plus"></i></div><span>Adicionar outra imagem</span></div>
                </div>
            </div>

            <div class="grid">
                <label>PDFs</label>
                <?php if (!empty($pdfs)): ?>
                    <div class="grid-pdfs">
                        <?php foreach ($pdfs as $att_id):
                            $url  = wp_get_attachment_url($att_id);
                            $desc = get_post_meta($post_id, 'pdf_descricao_' . $att_id, true) ?: ''; ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default"><div class="icon"><i class="fas fa-file-pdf"></i></div><span class="hint"><?php echo esc_html(basename($url)); ?></span></div>
                                <input type="text" name="pdf_desc_existente[<?php echo esc_attr($att_id); ?>]" value="<?php echo esc_attr($desc); ?>" placeholder="Descrição do anexo">
                                <label class="pill-del"><input type="checkbox" name="del_pdf[<?php echo esc_attr($att_id); ?>]" value="1"> Excluir este PDF</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="grid-pdfs" id="container-pdfs">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone pdf" role="button" tabindex="0">
                            <div onclick="this.querySelector('input[type=file]').click()">
                                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                <span class="hint nome-pdf">Adicionar anexo</span>
                                <input type="file" name="pdf_<?php echo $i; ?>" accept="application/pdf" onchange="marcarPdfAdicionado(this)">
                            </div>
                            <input type="text" name="pdf_descricao_<?php echo $i; ?>" placeholder="Descrição do anexo">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" onclick="adicionarCampoPdf()"><div class="icon"><i class="fas fa-plus"></i></div><span>Adicionar outro PDF</span></div>
                </div>
            </div>

            <div class="grid">
                <label>Áudios</label>
                <?php if (!empty($audios)): ?>
                    <div class="grid-audios">
                        <?php foreach ($audios as $att_id):
                            $url       = wp_get_attachment_url($att_id);
                            $mime      = get_post_mime_type($att_id) ?: 'audio/mpeg';
                            $file_name = $url ? basename(parse_url($url, PHP_URL_PATH)) : ('ID ' . $att_id);
                            $desc      = get_post_meta($post_id, 'audio_descricao_' . $att_id, true) ?: ''; ?>
                            <div class="grid" style="gap:10px">
                                <div class="drop-zone" style="cursor:default">
                                    <div class="icon"><i class="fas fa-file-audio"></i></div>
                                    <span class="hint"><?php echo esc_html($file_name); ?></span>
                                    <?php if ($url): ?><audio controls style="width:100%;margin-top:8px"><source src="<?php echo esc_url($url); ?>" type="<?php echo esc_attr($mime); ?>"></audio><?php endif; ?>
                                </div>
                                <input type="text" name="audio_desc_existente[<?php echo esc_attr($att_id); ?>]" value="<?php echo esc_attr($desc); ?>" placeholder="Descrição do áudio (opcional)">
                                <label class="pill-del"><input type="checkbox" name="del_audio[<?php echo esc_attr($att_id); ?>]" value="1"> Excluir este áudio</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="grid-audios" id="container-audios">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone audio" role="button" tabindex="0">
                            <div onclick="this.querySelector('input[type=file]').click()">
                                <div class="icon"><i class="fas fa-file-audio"></i></div>
                                <span class="hint nome-audio">Adicionar arquivo de áudio (MP3, M4A, WAV...)</span>
                                <input type="file" name="audio_<?php echo $i; ?>" accept="audio/*" onchange="marcarAudioAdicionado(this)">
                            </div>
                            <input type="text" name="audio_descricao_<?php echo $i; ?>" placeholder="Descrição do áudio (opcional)">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" onclick="adicionarCampoAudio()"><div class="icon"><i class="fas fa-plus"></i></div><span>Adicionar outro áudio</span></div>
                </div>
                <div class="hint-mini">Formatos aceitos: mp3, m4a, wav, ogg, aac, opus, flac. Tamanho máx.: <strong>50MB</strong>.</div>
            </div>

            <div class="grid">
                <label>Vídeos do YouTube</label>
                <?php if (!empty($videos)): ?>
                    <div class="grid-videos" id="yt-existentes">
                        <?php foreach ($videos as $idx => $item):
                            $u   = is_array($item) ? ($item['url'] ?? '') : '';
                            $d   = is_array($item) ? ($item['desc'] ?? '') : '';
                            $vid = is_array($item) ? ($item['video_id'] ?? '') : ''; ?>
                            <div class="video-card">
                                <div class="video-header"><i class="fab fa-youtube"></i> <span>Vídeo existente</span></div>
                                <input type="url" name="yt_exist_url[<?php echo esc_attr($idx); ?>]" value="<?php echo esc_attr($u); ?>" oninput="updateVideoPreview(this)" placeholder="https://www.youtube.com/watch?v=...">
                                <input type="text" name="yt_exist_desc[<?php echo esc_attr($idx); ?>]" value="<?php echo esc_attr($d); ?>" placeholder="Descrição do vídeo (opcional)">
                                <label class="pill-del"><input type="checkbox" name="yt_exist_del[<?php echo esc_attr($idx); ?>]" value="1"> Excluir este vídeo</label>
                                <div class="video-iframe" <?php echo $vid ? '' : 'hidden'; ?>>
                                    <iframe allowfullscreen loading="lazy" src="<?php echo $vid ? esc_url('https://www.youtube.com/embed/' . $vid) : ''; ?>"></iframe>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="grid-videos" id="container-videos">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="video-card">
                            <div class="video-header"><i class="fab fa-youtube"></i> <span>Insira o link YouTube.</span></div>
                            <input type="url" name="youtube_url_<?php echo $i; ?>" placeholder="https://www.youtube.com/watch?v=..." oninput="updateVideoPreview(this)">
                            <input type="text" name="youtube_desc_<?php echo $i; ?>" placeholder="Descrição do vídeo (opcional)">
                            <div class="video-iframe" hidden><iframe allowfullscreen loading="lazy"></iframe></div>
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" onclick="adicionarCampoVideo()"><div class="icon"><i class="fas fa-plus"></i></div><span>Adicionar outro vídeo</span></div>
                </div>
            </div>

            <div class="grid">
                <label><input type="checkbox" id="check" required> Declaro que revisei os dados e desejo atualizar esta publicação.</label>
            </div>

            <button type="submit">Salvar alterações</button>
        </form>
    </div>

    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        let formDirty = false;
        const formEl = document.querySelector('form.form-artigo');
        formEl.addEventListener('input', () => formDirty = true);
        window.addEventListener('beforeunload', (e) => { if (formDirty) { e.preventDefault(); e.returnValue = ''; } });

        const quill = new Quill('#editor', { theme: 'snow', placeholder: 'Edite seu conteúdo...', modules: { toolbar: [[{header:[1,2,3,false]}],['bold','italic','underline'],['link','blockquote','code-block'],[{list:'ordered'},{list:'bullet'}],['clean']] } });
        quill.root.innerHTML = <?php echo json_encode($conteudo); ?>;
        formEl.addEventListener('submit', function () { document.querySelector('#conteudo').value = quill.root.innerHTML; formDirty = false; });

        const resumoEl = document.getElementById('resumo');
        const resumoCountEl = document.getElementById('resumo-count');
        resumoEl.addEventListener('input', () => resumoCountEl.textContent = (resumoEl.value || '').length);
        resumoCountEl.textContent = (resumoEl.value || '').length;

        function mostrarPreview(input) {
            const file = input.files[0]; const dz = input.closest('.drop-zone'); const img = dz && dz.querySelector('img.preview');
            if (!img) return;
            if (file && file.type.startsWith('image/')) { img.src = URL.createObjectURL(file); img.style.display = 'block'; } else { img.src = ''; img.style.display = 'none'; }
        }
        function validarCapaMsg(input) { const hint = document.getElementById('hint-capa'); if (hint) hint.style.display = (input.files && input.files.length > 0) ? 'none' : 'block'; }
        function marcarPdfAdicionado(input) { const s = input.closest('.drop-zone') && input.closest('.drop-zone').querySelector('.nome-pdf'); if (s && input.files[0]) s.textContent = input.files[0].name; }
        function marcarAudioAdicionado(input) { const s = input.closest('.drop-zone') && input.closest('.drop-zone').querySelector('.nome-audio'); if (s && input.files[0]) s.textContent = input.files[0].name; }

        let cI=2,cP=2,cV=2,cA=2;
        function adicionarCampoImagem(){ cI++; const c=document.getElementById('container-imagens'), b=document.getElementById('add-imagem-btn'), d=document.createElement('div'); d.className='drop-zone'; d.innerHTML=`<div onclick="this.querySelector('input').click()"><div class="icon"><i class="fas fa-image"></i></div><span class="hint">Arraste/solte ou clique</span><input type="file" name="imagem_${cI}" accept="image/*" onchange="mostrarPreview(this)"><img src="" class="preview" alt="" style="display:none"></div><input type="text" name="imagem_descricao_${cI}" placeholder="Descrição..." style="display:none">`; c.insertBefore(d, b); }
        function adicionarCampoPdf(){ cP++; const c=document.getElementById('container-pdfs'), b=document.getElementById('add-pdf-btn'), d=document.createElement('div'); d.className='drop-zone pdf'; d.innerHTML=`<div onclick="this.querySelector('input').click()"><div class="icon"><i class="fas fa-file-pdf"></i></div><span class="hint nome-pdf">Adicionar anexo</span><input type="file" name="pdf_${cP}" accept="application/pdf" onchange="marcarPdfAdicionado(this)"></div><input type="text" name="pdf_descricao_${cP}" placeholder="Descrição...">`; c.insertBefore(d, b); }
        function adicionarCampoAudio(){ cA++; const c=document.getElementById('container-audios'), b=document.getElementById('add-audio-btn'), d=document.createElement('div'); d.className='drop-zone audio'; d.innerHTML=`<div onclick="this.querySelector('input').click()"><div class="icon"><i class="fas fa-file-audio"></i></div><span class="hint nome-audio">Adicionar áudio (MP3, M4A...)</span><input type="file" name="audio_${cA}" accept="audio/*" onchange="marcarAudioAdicionado(this)"></div><input type="text" name="audio_descricao_${cA}" placeholder="Descrição (opcional)">`; c.insertBefore(d, b); }

        function extractYouTubeId(url) { const p=[/youtu\.be\/([a-zA-Z0-9_-]{11})/i,/youtube\.com\/(?:embed|shorts)\/([a-zA-Z0-9_-]{11})/i,/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i]; for(const r of p){const m=url.match(r);if(m)return m[1];} return ''; }
        function updateVideoPreview(el) { const card=el.closest('.video-card'), vid=extractYouTubeId(el.value.trim()), box=card&&card.querySelector('.video-iframe'), iframe=box&&box.querySelector('iframe'); if(vid&&iframe){iframe.src=`https://www.youtube.com/embed/${vid}`;box.hidden=false;}else if(iframe){iframe.src='';box.hidden=true;} }
        function adicionarCampoVideo(){ cV++; const c=document.getElementById('container-videos'), b=document.getElementById('add-video-btn'), d=document.createElement('div'); d.className='video-card'; d.innerHTML=`<div class="video-header"><i class="fab fa-youtube"></i> <span>Insira o link YouTube.</span></div><input type="url" name="youtube_url_${cV}" placeholder="https://www.youtube.com/watch?v=..." oninput="updateVideoPreview(this)"><input type="text" name="youtube_desc_${cV}" placeholder="Descrição (opcional)"><div class="video-iframe" hidden><iframe allowfullscreen loading="lazy"></iframe></div>`; c.insertBefore(d, b); }

        document.querySelectorAll('#yt-existentes input[type=url]').forEach(inp => { if(inp.value) updateVideoPreview(inp); });

        (function(){ const t=document.getElementById('titulo'), tc=document.getElementById('titulo-count'); if(!t||!tc) return; const u=()=>tc.textContent=String((t.value||'').length); ['input','change','keyup'].forEach(e=>t.addEventListener(e,u)); u(); })();

        (function(){ const MAX=50*1024*1024;
            function validate(input){ const f=input.files&&input.files[0]; if(!f) return true; if(f.size>MAX){ input.value=''; const s=input.closest('.drop-zone')&&input.closest('.drop-zone').querySelector('.nome-audio'); if(s){s.textContent='Arquivo excede 50MB!';s.style.color='red';} input.setCustomValidity('O áudio deve ter no máximo 50MB.'); input.reportValidity(); return false; } input.setCustomValidity(''); return true; }
            document.querySelectorAll('input[type="file"][name^="audio_"]').forEach(i=>i.addEventListener('change',()=>validate(i)));
            const form=document.querySelector('form.form-artigo');
            if(form) form.addEventListener('submit', e=>{ for(const i of form.querySelectorAll('input[type="file"][name^="audio_"]')){ if(!validate(i)){e.preventDefault();return;} } });
        })();
    </script>
    <?php
    return ob_get_clean();
});
