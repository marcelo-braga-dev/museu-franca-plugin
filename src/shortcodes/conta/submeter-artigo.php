<?php
if (!defined('ABSPATH')) exit;

// Note: upload_mimes filter and mp_extract_youtube_id are already in hooks.php / functions.php

add_shortcode('submeter_artigo', function () {

    if (!is_user_logged_in()) return '<p>Você precisa estar logado para publicar um artigo.</p>';

    $erro_box = '';
    $conteudo = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'], $_POST['mp_nonce'])) {

        // Nonce verification
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mp_nonce'])), 'mp_submeter_artigo')) {
            return '<p>Requisição inválida.</p>';
        }

        $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
        if ($categoria_id <= 0) {
            $erro_box .= '• Selecione uma categoria.<br>';
        }

        $allowed_mimes = ['audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg','audio/oga','audio/mp4','audio/aac','audio/webm','audio/m4a','audio/x-m4a','audio/flac','audio/opus'];
        $allowed_exts  = ['mp3','wav','ogg','oga','mp4','aac','webm','m4a','flac','opus'];
        $max_bytes     = 5 * 1024 * 1024;

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
                    $erro_box .= '• O áudio "' . esc_html($file['name']) . '" excede 5MB.<br>';
                }
                $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $type_ok = in_array($file['type'], $allowed_mimes, true) || in_array($ext, $allowed_exts, true);
                if (!$type_ok) {
                    $erro_box .= '• Formato de áudio não permitido para "' . esc_html($file['name']) . '".<br>';
                }
            }
        }

        if ($erro_box === '') {
            $titulo   = sanitize_text_field($_POST['titulo'] ?? '');
            $resumo   = sanitize_text_field($_POST['resumo'] ?? '');
            $conteudo = wp_kses_post(wp_unslash($_POST['conteudo'] ?? ''));
            $user_id  = get_current_user_id();
            $status   = current_user_can('publish_posts') ? 'publish' : 'pending';

            $post_id = wp_insert_post([
                'post_title'   => $titulo,
                'post_excerpt' => $resumo,
                'post_content' => $conteudo,
                'post_type'    => 'artigo',
                'post_status'  => $status,
                'post_author'  => $user_id,
            ]);

            if ($post_id) {
                wp_set_post_terms($post_id, [$categoria_id], 'category');

                if (!empty($_POST['tags'])) {
                    $tags = array_map('trim', explode(',', sanitize_text_field($_POST['tags'])));
                    wp_set_post_terms($post_id, $tags, 'post_tag');
                }

                if (!empty($_POST['colecao_ids']) && is_array($_POST['colecao_ids'])) {
                    $colecao_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['colecao_ids']), function ($v) { return $v > 0; })));
                    wp_set_post_terms($post_id, $colecao_ids, 'colecao');
                }

                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                if (!empty($_FILES['capa']['name'])) {
                    $capa_id = media_handle_upload('capa', $post_id);
                    if (!is_wp_error($capa_id)) set_post_thumbnail($post_id, $capa_id);
                }

                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'imagem_') === 0 && !empty($file['name'])) {
                        $img_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($img_id)) {
                            add_post_meta($post_id, MP_META_IMG, $img_id);
                            $indice    = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['imagem_descricao_' . $indice] ?? '');
                            if ($descricao !== '') add_post_meta($post_id, 'imagem_adicional_descricao_' . $img_id, $descricao);
                        }
                    }
                }

                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'pdf_') === 0 && !empty($file['name'])) {
                        $pdf_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($pdf_id)) {
                            add_post_meta($post_id, MP_META_PDF, $pdf_id);
                            $indice    = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['pdf_descricao_' . $indice] ?? '');
                            if ($descricao !== '') add_post_meta($post_id, 'pdf_descricao_' . $pdf_id, $descricao);
                        }
                    }
                }

                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'audio_') === 0 && !empty($file['name'])) {
                        $audio_id = media_handle_upload($key, $post_id);
                        if (!is_wp_error($audio_id)) {
                            add_post_meta($post_id, MP_META_AUDIO, $audio_id);
                            $indice    = explode('_', $key)[1] ?? '';
                            $descricao = sanitize_text_field($_POST['audio_descricao_' . $indice] ?? '');
                            if ($descricao !== '') add_post_meta($post_id, 'audio_descricao_' . $audio_id, $descricao);
                        }
                    }
                }

                foreach ($_POST as $key => $val) {
                    if (strpos($key, 'youtube_url_') === 0) {
                        $i      = substr($key, strlen('youtube_url_'));
                        $url    = esc_url_raw($val);
                        if ($url) {
                            $desc   = sanitize_text_field($_POST['youtube_desc_' . $i] ?? '');
                            $vid_id = mp_extract_youtube_id($url);
                            add_post_meta($post_id, MP_META_YOUTUBE, ['url' => $url, 'desc' => $desc, 'video_id' => $vid_id ?: '']);
                        }
                    }
                }

                wp_safe_redirect(get_permalink($post_id));
                exit;
            } else {
                $erro_box .= '• Não foi possível criar a publicação. Tente novamente.<br>';
            }
        } else {
            $conteudo = wp_kses_post(wp_unslash($_POST['conteudo'] ?? ''));
        }
    }

    $categorias = get_categories(['hide_empty' => false, 'orderby' => 'name']);
    ob_start();
    ?>
    <style>
        :root{--brand:#992d17;--brand-dark:#7d2613;--text:#1f2937;--muted:#6b7280;--stroke:#e5e7eb;--card:#ffffff;--shadow:0 6px 18px rgba(0,0,0,.06);--radius:12px;--radius-sm:8px;--space:16px;}
        .form-wrap{max-width:980px;margin:0 auto;padding:16px}
        .heading{margin:8px 0 18px;font-size:clamp(18px,2.5vw,22px);color:var(--text);font-weight:700}
        .form-artigo{width:100%;display:grid;gap:18px}
        .grid{display:grid;gap:10px;background:var(--card);border:1px solid var(--stroke);border-radius:var(--radius);padding:14px;box-shadow:var(--shadow)}
        .form-artigo label{font-weight:600;color:var(--text);font-size:14px}
        .form-artigo input[type=text],.form-artigo input[type=url],.form-artigo textarea,.form-artigo select{width:100%;padding:12px 14px;font-size:15px;border:1px solid var(--stroke);border-radius:var(--radius-sm);background:#fff;color:var(--text);outline:none;transition:box-shadow .2s,border-color .2s}
        .form-artigo input:focus,.form-artigo textarea:focus,.form-artigo select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(153,45,23,.15)}
        .form-artigo button{background:var(--brand);color:#fff;padding:14px 16px;border:none;border-radius:var(--radius);font-weight:700;cursor:pointer;width:100%;transition:background .2s,transform .02s}
        .form-artigo button:hover{background:var(--brand-dark)}
        .drop-zone{border:2px dashed var(--stroke);border-radius:var(--radius);padding:16px;text-align:center;background:#fff;display:grid;gap:10px;align-items:center;justify-items:center;min-height:140px;outline:none}
        .drop-zone input[type=file]{display:none}
        .drop-zone .icon{font-size:28px}
        .drop-zone .hint{color:var(--muted);font-size:13px}
        .drop-zone img.preview{max-width:100%;height:auto;max-height:220px;display:none;border-radius:var(--radius-sm)}
        .grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{display:grid;gap:var(--space);grid-template-columns:1fr}
        @media(min-width:600px){.grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{grid-template-columns:repeat(2,1fr)}}
        @media(min-width:900px){.grid-imagens,.grid-pdfs,.grid-videos,.grid-audios{grid-template-columns:repeat(3,1fr)}}
        .video-card{border:1px solid var(--stroke);border-radius:var(--radius);padding:12px;display:grid;gap:10px;background:#fff;box-shadow:var(--shadow)}
        .video-iframe{overflow:hidden;border-radius:var(--radius-sm);background:#000;aspect-ratio:16/9}
        .video-iframe iframe{width:100%;height:100%;border:0;display:block}
        .add-card{display:grid;place-items:center;text-align:center;border:2px dashed var(--stroke);border-radius:var(--radius);min-height:140px;background:#fff;gap:8px;cursor:pointer}
        .add-card:hover{border-color:var(--brand)}
        #editor{height:min(60vh,420px);border-radius:var(--radius-sm);background:#fff}
        .ql-toolbar.ql-snow{border-radius:var(--radius-sm) var(--radius-sm) 0 0}
        .ql-container.ql-snow{border-radius:0 0 var(--radius-sm) var(--radius-sm)}
        .termo{display:grid;grid-template-columns:24px 1fr;gap:12px;align-items:start;font-size:14px}
        .termo input[type=checkbox]{width:18px;height:18px;margin-top:3px}
        .hint-mini{font-size:12px;color:var(--muted)}
        .mp-progress-overlay{position:fixed;inset:0;background:rgba(10,12,16,.5);display:flex;align-items:center;justify-content:center;z-index:99999}
        .mp-progress-box{background:#fff;padding:20px 24px;border-radius:14px;box-shadow:0 10px 32px rgba(0,0,0,.15);width:320px;text-align:center}
        .mp-progress-bar{width:100%;height:12px;background:#f3f4f6;border-radius:999px;overflow:hidden;margin-top:10px}
        .mp-progress-fill{height:100%;width:0%;background:linear-gradient(90deg,#992d17,#c2410c);transition:width .2s}
        .mp-progress-text{margin-top:8px;font-weight:600;color:#374151}
        .erro-box{background:#fff3f2;border:1px solid #ffd9d6;color:#9c1c0e;border-radius:10px;padding:12px}
        .mp-colecoes-checklist{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px}
        .mp-check{position:relative;display:inline-flex;align-items:center;cursor:pointer;user-select:none}
        .mp-check input[type="checkbox"]{position:absolute;opacity:0;pointer-events:none}
        .mp-check span{display:inline-flex;align-items:center;padding:6px 14px;border-radius:999px;border:1px solid #d1d5db;background:#fff;font-size:14px;font-weight:500;color:#374151;transition:all .2s ease}
        .mp-check:hover span{border-color:#9e2b19;color:#9e2b19}
        .mp-check input[type="checkbox"]:checked + span{background:#9e2b19;color:#fff;border-color:#9e2b19}
    </style>

    <div class="form-wrap">
        <h5 class="heading">Publique sua História, Documentos, Fotos ou Vídeos</h5>

        <?php if (!empty($erro_box)): ?>
            <div class="erro-box" role="alert">
                <strong>Corrija os itens abaixo:</strong><br><?php echo wp_kses_post($erro_box); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-artigo" novalidate>
            <?php wp_nonce_field('mp_submeter_artigo', 'mp_nonce'); ?>

            <div class="grid">
                <label for="titulo">Título da Publicação</label>
                <input type="text" name="titulo" id="titulo" required maxlength="100">
                <div class="hint-mini"><span id="titulo-count">0</span>/100</div>
            </div>

            <div class="grid">
                <label for="resumo">Resumo</label>
                <textarea name="resumo" id="resumo" rows="2" maxlength="250" placeholder=""></textarea>
                <div class="hint-mini"><span id="resumo-count">0</span>/250</div>
            </div>

            <div class="grid">
                <label for="conteudo">Conteúdo</label>
                <div id="editor"></div>
                <textarea name="conteudo" id="conteudo" hidden style="display:none"><?php echo esc_textarea($conteudo); ?></textarea>
            </div>

            <div class="grid">
                <label>Foto de Capa <small style="color:#9E2B19">(obrigatória)</small></label>
                <div class="drop-zone" role="button" tabindex="0" aria-label="Adicionar foto de capa"
                     onclick="document.getElementById('capa').click()"
                     onkeydown="if(event.key==='Enter'||event.key===' '){document.getElementById('capa').click();event.preventDefault();}">
                    <div class="icon"><i class="fas fa-image"></i></div>
                    <span class="hint" id="hint-capa">Arraste uma imagem ou clique para selecionar</span>
                    <input type="file" name="capa" id="capa" accept="image/*" required
                           onchange="mostrarPreview(this); validarCapaMsg(this)">
                    <img src="" alt="Pré-visualização da capa" class="preview">
                </div>
            </div>

            <div class="grid">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id" required>
                    <option value="">Selecione a categoria...</option>
                    <?php mp_exibir_categorias($categorias); ?>
                </select>
            </div>

            <?php if (current_user_can('administrator')):
                $all_colecoes = get_terms(['taxonomy' => 'colecao', 'hide_empty' => false]);
                ?>
                <div class="grid">
                    <label class="mp-label">Coleções (opcional)</label>
                    <div class="mp-colecoes-checklist">
                        <?php foreach ($all_colecoes as $c): ?>
                            <label class="mp-check">
                                <input type="checkbox" name="colecao_ids[]" value="<?php echo (int)$c->term_id; ?>">
                                <span><?php echo esc_html($c->name); ?></span>
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
                            <div onclick="this.querySelector('input[type=file]').click()">
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
                         aria-label="Adicionar nova imagem" onclick="adicionarCampoImagem()">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint" style="color:var(--muted)">Adicionar outra imagem</span>
                    </div>
                </div>
            </div>

            <div class="grid">
                <label>PDFs</label>
                <div class="grid-pdfs" id="container-pdfs">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone pdf" role="button" tabindex="0">
                            <div onclick="this.querySelector('input[type=file]').click()">
                                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                <span class="hint nome-pdf">Adicionar anexo</span>
                                <input type="file" name="pdf_<?php echo $i; ?>" accept="application/pdf"
                                       onchange="marcarPdfAdicionado(this)">
                            </div>
                            <input type="text" name="pdf_descricao_<?php echo $i; ?>" placeholder="Descrição do anexo">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-pdf-btn" role="button" tabindex="0" onclick="adicionarCampoPdf()">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint" style="color:var(--muted)">Adicionar outro PDF</span>
                    </div>
                </div>
            </div>

            <div class="grid">
                <label>Áudios</label>
                <div class="grid-audios" id="container-audios">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="drop-zone audio" role="button" tabindex="0">
                            <div onclick="this.querySelector('input[type=file]').click()">
                                <div class="icon"><i class="fas fa-file-audio"></i></div>
                                <span class="hint nome-audio">Arraste/solte ou clique</span>
                                <input type="file" name="audio_<?php echo $i; ?>" accept="audio/*"
                                       onchange="marcarAudioAdicionado(this)">
                            </div>
                            <input type="text" name="audio_descricao_<?php echo $i; ?>"
                                   placeholder="Descrição do áudio (opcional)">
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-audio-btn" role="button" tabindex="0" onclick="adicionarCampoAudio()">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint" style="color:var(--muted)">Adicionar outro Áudio</span>
                    </div>
                </div>
                <div class="hint-mini">Formatos: mp3, m4a, wav, ogg, aac, opus, flac. Máx.: <strong>5MB</strong>.</div>
            </div>

            <div class="grid">
                <label>Vídeos do YouTube</label>
                <div class="grid-videos" id="container-videos">
                    <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="video-card" data-index="<?php echo $i; ?>">
                            <div style="display:flex;align-items:center;gap:8px;font-weight:600">
                                <i class="fab fa-youtube" style="font-size:28px;color:red"></i>
                                <span>Insira o link YouTube.</span>
                            </div>
                            <div>
                                <input type="url" name="youtube_url_<?php echo $i; ?>"
                                       placeholder="https://www.youtube.com/watch?v=..."
                                       oninput="updateVideoPreview(this)">
                                <input type="text" name="youtube_desc_<?php echo $i; ?>"
                                       placeholder="Descrição do vídeo (opcional)" style="margin-top:8px">
                            </div>
                            <div class="video-iframe" hidden>
                                <iframe allowfullscreen loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <div class="add-card" id="add-video-btn" role="button" tabindex="0" onclick="adicionarCampoVideo()">
                        <div class="icon"><i class="fas fa-plus"></i></div>
                        <span class="hint" style="color:var(--muted)">Adicionar outro vídeo</span>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="termo">
                    <input type="checkbox" name="check" id="check" required>
                    <div>
                        <strong>Termo de Autorização para Uso de Imagem, Áudio e Conteúdos</strong><br/>
                        Autorizo a utilização da minha imagem, voz, fotografias, vídeos e materiais fornecidos no âmbito da Plataforma Museu da Pessoa Franca.
                    </div>
                </div>
            </div>

            <button type="submit">Publicar</button>

            <div id="mp-progress" class="mp-progress-overlay" style="display:none">
                <div class="mp-progress-box">
                    <div>Enviando sua publicação…</div>
                    <div class="mp-progress-bar"><div class="mp-progress-fill" id="mp-progress-fill"></div></div>
                    <div class="mp-progress-text"><span id="mp-progress-num">0</span>%</div>
                </div>
            </div>
        </form>
    </div>

    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
    var quill = new Quill('#editor',{theme:'snow',placeholder:'Digite seu conteúdo aqui...',modules:{toolbar:[[{header:[1,2,3,false]}],['bold','italic','underline'],['link','blockquote','code-block'],[{list:'ordered'},{list:'bullet'}],['clean']]}});
    quill.root.innerHTML = <?php echo json_encode($conteudo); ?>;

    function mostrarPreview(input){var file=input.files[0],dz=input.closest('.drop-zone'),img=dz.querySelector('img.preview');if(file&&file.type.startsWith('image/')){img.src=URL.createObjectURL(file);img.style.display='block';var hint=dz.querySelector('.hint');if(hint)hint.style.display='none';}}
    function validarCapaMsg(input){var hint=document.getElementById('hint-capa');if(!hint)return;hint.style.display=(input.files&&input.files.length>0)?'none':'block';}
    function marcarPdfAdicionado(input){var dz=input.closest('.drop-zone'),n=dz.querySelector('.nome-pdf');if(!n)return;if(input.files&&input.files.length>0){n.textContent=input.files[0].name;n.style.color='#333';}else{n.textContent='Adicionar anexo';n.style.color='';}}
    function marcarAudioAdicionado(input){var dz=input.closest('.drop-zone'),n=dz.querySelector('.nome-audio');if(!n)return;if(input.files&&input.files.length>0){n.textContent=input.files[0].name;n.style.color='#333';}else{n.textContent='Arraste/solte ou clique';n.style.color='';}}
    function toggleDescricao(input){var d=input.closest('.drop-zone').querySelector('.descricao-imagem');if(!d)return;d.style.display=(input.files&&input.files.length>0)?'block':'none';}

    var contadorImagem=2,contadorPdf=2,contadorVideo=2,contadorAudio=2;
    function adicionarCampoImagem(){contadorImagem++;var c=document.getElementById('container-imagens'),b=document.getElementById('add-imagem-btn'),d=document.createElement('div');d.className='drop-zone';d.setAttribute('role','button');d.setAttribute('tabindex','0');d.onclick=function(){d.querySelector('input[type=file]').click();};d.innerHTML='<div class="icon"><i class="fas fa-image"></i></div><span class="hint">Arraste/solte ou clique</span><input type="file" name="imagem_'+contadorImagem+'" accept="image/*" onchange="mostrarPreview(this); toggleDescricao(this)"><img src="" class="preview" alt=""><input type="text" name="imagem_descricao_'+contadorImagem+'" class="descricao-imagem" placeholder="Descrição da imagem..." style="display:none">';c.insertBefore(d,b);}
    function adicionarCampoPdf(){contadorPdf++;var c=document.getElementById('container-pdfs'),b=document.getElementById('add-pdf-btn'),d=document.createElement('div');d.className='drop-zone pdf';d.setAttribute('role','button');d.setAttribute('tabindex','0');d.onclick=function(){d.querySelector('input[type=file]').click();};d.innerHTML='<div class="icon"><i class="fas fa-file-pdf"></i></div><span class="hint nome-pdf">Adicionar anexo</span><input type="file" name="pdf_'+contadorPdf+'" accept="application/pdf" onchange="marcarPdfAdicionado(this)"><input type="text" name="pdf_descricao_'+contadorPdf+'" placeholder="Descrição do anexo">';c.insertBefore(d,b);}
    function adicionarCampoAudio(){contadorAudio++;var c=document.getElementById('container-audios'),b=document.getElementById('add-audio-btn'),d=document.createElement('div');d.className='drop-zone audio';d.setAttribute('role','button');d.setAttribute('tabindex','0');d.onclick=function(){d.querySelector('input[type=file]').click();};d.innerHTML='<div class="icon"><i class="fas fa-file-audio"></i></div><span class="hint nome-audio">Arraste/solte ou clique</span><input type="file" name="audio_'+contadorAudio+'" accept="audio/*" onchange="marcarAudioAdicionado(this)"><input type="text" name="audio_descricao_'+contadorAudio+'" placeholder="Descrição do áudio (opcional)">';c.insertBefore(d,b);}
    function extractYouTubeId(url){if(!url)return'';var pats=[/youtu\.be\/([a-zA-Z0-9_-]{11})/i,/youtube\.com\/(?:embed|shorts)\/([a-zA-Z0-9_-]{11})/i,/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i,/youtube\.com\/watch\?.*?[&?]v=([a-zA-Z0-9_-]{11})/i];for(var p of pats){var m=url.match(p);if(m&&m[1])return m[1];}return'';}
    function adicionarCampoVideo(){contadorVideo++;var c=document.getElementById('container-videos'),b=document.getElementById('add-video-btn'),w=document.createElement('div');w.className='video-card';w.setAttribute('data-index',String(contadorVideo));w.innerHTML='<div style="display:flex;align-items:center;gap:8px;font-weight:600"><i class="fab fa-youtube" style="font-size:28px;color:red"></i><span>Insira o link YouTube.</span></div><div><input type="url" name="youtube_url_'+contadorVideo+'" placeholder="https://www.youtube.com/watch?v=..." oninput="updateVideoPreview(this)"><input type="text" name="youtube_desc_'+contadorVideo+'" placeholder="Descrição (opcional)" style="margin-top:8px"></div><div class="video-iframe" hidden><iframe allowfullscreen loading="lazy"></iframe></div>';c.insertBefore(w,b);}
    function updateVideoPreview(inputEl){var card=inputEl.closest('.video-card'),url=inputEl.value.trim(),vid=extractYouTubeId(url),box=card.querySelector('.video-iframe'),iframe=box?box.querySelector('iframe'):null;if(vid&&iframe){iframe.src='https://www.youtube.com/embed/'+vid;box.hidden=false;}else if(iframe){iframe.src='';box.hidden=true;}}

    (function(){
        var form=document.querySelector('form.form-artigo');
        if(!form)return;
        var overlay=document.getElementById('mp-progress'),fill=document.getElementById('mp-progress-fill'),num=document.getElementById('mp-progress-num'),MAX_BYTES=5*1024*1024;
        function validateAudioInput(input){var file=input.files&&input.files[0];if(!file)return true;if(file.size>MAX_BYTES){var dz=input.closest('.drop-zone'),n=dz?dz.querySelector('.nome-audio'):null;input.value='';if(n){n.textContent='Arraste/solte ou clique';n.style.color='';}alert('O áudio excede 5MB. Selecione um arquivo menor.');input.setCustomValidity('O áudio deve ter no máximo 5MB.');input.reportValidity();return false;}input.setCustomValidity('');return true;}
        form.addEventListener('submit',function(e){
            e.preventDefault();
            var capa=document.getElementById('capa');
            if(!capa||!capa.files||capa.files.length===0){alert('Você precisa adicionar uma foto de capa.');var dz=document.getElementById('hint-capa')?document.getElementById('hint-capa').closest('.drop-zone'):null;if(dz)dz.scrollIntoView({behavior:'smooth',block:'center'});return;}
            if(!form.checkValidity()){form.reportValidity();return;}
            var categoria=document.getElementById('categoria_id');
            if(!categoria||!categoria.value||categoria.value.trim()===''){categoria.setCustomValidity('Por favor, selecione uma categoria.');categoria.reportValidity();return;}else{categoria.setCustomValidity('');}
            var audios=form.querySelectorAll('input[type="file"][name^="audio_"]');for(var inp of audios){if(!validateAudioInput(inp))return;}
            var hidden=document.getElementById('conteudo');if(hidden)hidden.value=quill.root.innerHTML;
            var xhr=new XMLHttpRequest(),data=new FormData(form);
            xhr.upload.addEventListener('progress',function(ev){if(ev.lengthComputable){var p=Math.round((ev.loaded/ev.total)*100);fill.style.width=p+'%';num.textContent=p;}});
            xhr.onreadystatechange=function(){if(xhr.readyState===4){if(xhr.status===200){num.textContent='100';fill.style.width='100%';setTimeout(function(){overlay.style.display='none';window.location.href='?enviado=1&aba=historico';},600);}else{alert('Erro ao enviar. Tente novamente.');overlay.style.display='none';}}};
            xhr.open('POST',window.location.href);overlay.style.display='flex';xhr.send(data);
        });
    })();

    (function(){var r=document.getElementById('resumo'),rc=document.getElementById('resumo-count');if(r&&rc){function u(){rc.textContent=String((r.value||'').length);}['input','change','keyup','paste','cut'].forEach(function(e){r.addEventListener(e,u);});u();}
    var t=document.getElementById('titulo'),tc=document.getElementById('titulo-count');if(t&&tc){function ut(){tc.textContent=String((t.value||'').length);}['input','change','keyup','paste','cut'].forEach(function(e){t.addEventListener(e,ut);});ut();}})();
    </script>
    <?php
    return ob_get_clean();
});
