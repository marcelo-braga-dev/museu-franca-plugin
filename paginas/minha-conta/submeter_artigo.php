<?php
add_shortcode('submeter_artigo', function () {
    
    if (!is_user_logged_in()) return '<p>Você precisa estar logado para publicar um artigo.</p>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
        $titulo = sanitize_text_field($_POST['titulo']);
        $resumo = sanitize_text_field($_POST['resumo']);
        $conteudo = wp_kses_post($_POST['conteudo']);;

        $post_id = wp_insert_post([
            'post_title'   => $titulo,
            'post_excerpt' => $resumo,
            'post_content' => $conteudo,
            'post_type'    => 'artigo',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        ]);

        if ($post_id) {
            if (!empty($_POST['categoria_id'])) {
                wp_set_post_terms($post_id, [(int)$_POST['categoria_id']], 'category');
            }

            if (!empty($_POST['tags'])) {
                $tags = array_map('trim', explode(',', sanitize_text_field($_POST['tags'])));
                wp_set_post_terms($post_id, $tags, 'post_tag');
            }

            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            if (!empty($_FILES['capa']['name'])) {
                $capa_id = media_handle_upload('capa', $post_id);
                if (!is_wp_error($capa_id)) {
                    set_post_thumbnail($post_id, $capa_id);
                }
            }

            foreach ($_FILES as $key => $file) {
                if (strpos($key, 'imagem_') === 0 && !empty($file['name'])) {
                    $img_id = media_handle_upload($key, $post_id);
                    if (!is_wp_error($img_id)) {
                        add_post_meta($post_id, 'imagem_adicional', $img_id);
                        $descricao = sanitize_text_field($_POST['imagem_descricao_' . explode('_', $key)[1]] ?? '');
                        if (!empty($descricao)) {
                            add_post_meta($post_id, 'imagem_adicional_descricao_' . $img_id, $descricao);
                        }
                    }
                }
            }

            for ($i = 1; $i <= 5; $i++) {
                if (!empty($_POST['youtube_' . $i])) {
                    add_post_meta($post_id, 'youtube_link', esc_url($_POST['youtube_' . $i]));
                }
            }

            foreach ($_FILES as $key => $file) {
                if (strpos($key, 'pdf_') === 0 && !empty($file['name'])) {
                    $pdf_id = media_handle_upload($key, $post_id);
                    if (!is_wp_error($pdf_id)) {
                        add_post_meta($post_id, 'pdf', $pdf_id);
                        $descricao = sanitize_text_field($_POST['pdf_descricao_' . explode('_', $key)[1]] ?? '');
                        if (!empty($descricao)) {
                            add_post_meta($post_id, 'pdf_descricao_' . $pdf_id, $descricao);
                        }
                    }
                }
            }

            $url = site_url('/minha-conta/?aba=historico&sucesso=1');
            echo "<script>window.location.href = " . json_encode($url) . ";</script>";
            exit;
        }
    }

    $categorias = get_categories(['hide_empty' => false, 'orderby' => 'name']);

    ob_start();
    ?>
    <style>
    .grid {
        margin-bottom: 30px;
    }
    .form-artigo {
        width: 100%;
        margin: 0 auto;
        padding-inline: 20px;
    }
    .form-artigo label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }
    .form-artigo input[type="text"],
    .form-artigo input[type="url"],
    .form-artigo textarea,
    .form-artigo select {
        width: 100%;
        padding: 10px;
        font-size: 14px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    .form-artigo button {
        background-color: #992d17;
        color: #fff;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        margin-top: 20px;
        transition: background-color 0.3s;
    }
    .form-artigo button:hover {
        background-color: #7d2613;
    }
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 6px;
        padding: 10px;
        text-align: center;
        margin-bottom: 20px;
        position: relative;
        
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .drop-zone input[type="file"] {
        display: none;
    }
    .drop-zone img.preview {
        max-width: 100%;
        max-height: 200px;
        display: block;
        margin: 10px auto;
        border-radius: 6px;
    }
    .grid-imagens, .grid-pdfs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    .drop-zone.pdf span {
        max-width: 200px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        display: block;
        color: #666;
        font-size: 14px;
        pointer-events: none;
    }
    .descricao-imagem {
        display: none;
    }
    </style>

    <h5>Publique sua História</h5>
    <form method="POST" enctype="multipart/form-data" class="form-artigo">
        <div class="grid">
            <!-- titulo -->
            <label for="titulo">Título da Publicação</label>
            <input type="text" name="titulo" id="titulo" required>
        </div>
        
        <!-- resumo -->
        <div class="grid">
            <label for="resumo">Resumo (até 100 caracteres)</label>
            <textarea name="resumo" id="resumo" rows="2" maxlength="100" required></textarea>
        </div>
        
        <!-- conteudo -->
        <div class="grid">
            <label for="conteudo">Conteúdo</label>
            <div id="editor" style="height: 400px;"></div>
            <textarea name="conteudo" id="conteudo" style="display:none;"></textarea>
        </div>
        
        <!-- foto de capa -->
        <div class="grid">
            <label>Foto de Capa</label>
            <div class="drop-zone" style="padding-block: 20px; cursor: pointer;" onclick="document.getElementById('capa').click()">
                <span>Arraste uma imagem ou clique para selecionar</span>
                <input type="file" name="capa" id="capa" accept="image/*" onchange="mostrarPreview(this)">
                <img src="" class="preview" style="display:none">
            </div>
        </div>
        
        <!-- imagens -->
        <div class="grid">
            <label>Imagens Adicionais</label>
            <div class="grid-imagens" id="container-imagens">
                <?php for ($i = 1; $i <= 2; $i++): ?>
                <div class="drop-zone">
                    <div style="padding-block: 20px; cursor: pointer;" onclick="this.nextElementSibling.click()">
                        <i class="fas fa-image fa-2xl"></i>
                    </div>
                    <input type="file" name="imagem_<?= $i ?>" accept="image/*" onchange="mostrarPreview(this); toggleDescricao(this)">
                    <img src="" class="preview" style="display:none">
                    <input type="text" name="imagem_descricao_<?= $i ?>" class="descricao-imagem" placeholder="Descrição da imagem....">
                </div>
                <?php endfor; ?>

                <!-- Botão "+" -->
                <div class="drop-zone" id="add-imagem-btn" onclick="adicionarCampoImagem()" style="padding-block: 60px; cursor: pointer;">
                    <i class="fas fa-plus fa-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- pdf -->
        <div class="grid">
            <label>PDFs</label>
            <div class="grid-pdfs" id="container-pdfs">
                <?php for ($i = 1; $i <= 2; $i++): ?>
                <div class="drop-zone pdf">
                    <div style="padding-block: 20px; cursor: pointer;" onclick="this.nextElementSibling.click()">
                        <i class="fas fa-file-pdf fa-2xl" style="color: red;"></i>
                    </div>
                    <input type="file" name="pdf_<?= $i ?>" accept="application/pdf" onchange="marcarPdfAdicionado(this)" style="display: none;">
                    <input type="text" name="pdf_descricao_<?= $i ?>" placeholder="Descrição do anexo">
                </div>
                <?php endfor; ?>

                <!-- botão "+" -->
                <div class="drop-zone pdf" id="add-pdf-btn" onclick="adicionarCampoPdf()" style="cursor: pointer; display: flex; align-items: center; justify-content: center; padding-block: 60px;">
                    <i class="fas fa-plus fa-2xl" style="font-size: 32px; color: #666;"></i>
                </div>
            </div>
        </div>
        
        <!-- youtube -->
        <div class="grid">
            <label>Links de YouTube</label>
            <?php for ($i = 1; $i <= 2; $i++): ?>
                <input type="url" name="youtube_<?= $i ?>" placeholder="https://www.youtube.com/watch?v=...">
            <?php endfor; ?>
        </div>
        
        <!-- categoria -->
        <div class="grid">
            <label for="categoria_id">Categoria</label>
            <select name="categoria_id" id="categoria_id" required>
                <option value="">Selecione a categoria...</option>
                <?php
                function exibir_categorias($cats, $parent = 0, $prefixo = '') {
                    foreach ($cats as $cat) {
                        if ($cat->parent == $parent) {
                            echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($prefixo . $cat->name) . '</option>';
                            exibir_categorias($cats, $cat->term_id, $prefixo . '- ');
                        }
                    }
                }
                exibir_categorias($categorias);
                ?>
            </select>
        </div>
        
        <div class="grid">
            <label for="tags">Palavras-chave (separadas por vírgula)</label>
            <input type="text" name="tags" id="tags" placeholder="Palavra-chave 1, Palavra-chave 2, Palavra-chave 3, ...">
        </div>
        
         <div class="grid" style="border: 1px solid #ccc; border-radius: 8px; padding: 20px">
             <div style="display: flex; flex-direction: row;">
                <div style="margin-right: 20px">
                    <input type="checkbox" name="check" id="check" required style="width: 18px; height: 18px">
                </div>
                <div style="font-size: 14px">
                    <strong>Termo de Autorização para Uso de Imagem, Áudio e Conteúdos</strong><br/>
                    Autorizo, para todos os fins legais, a utilização da minha imagem, voz, fotografias, vídeos e demais materiais fornecidos por mim, no âmbito da Plataforma Museu da Pessoa Franca. Esta autorização é concedida de forma gratuita e permanecerá válida enquanto meu cadastro estiver ativo na referida plataforma.<br/>

                    Declaro que esta autorização expressa a minha livre manifestação de vontade, e que nada terei a reivindicar, a qualquer tempo, a título de direitos autorais, conexos ou quaisquer outros relacionados ao uso dos conteúdos acima mencionados.<br/>

                    O consentimento ora concedido poderá ser revogado a qualquer momento, mediante solicitação expressa, por meio de procedimento gratuito e facilitado, bastando entrar em contato com os administradores da plataforma através do e-mail disponibilizado para esse fim.
                </div>
            </div>

             
           
          
                
            </span>
        </div>

        <button type="submit">Publicar</button>
    </form>

    <script>
    function mostrarPreview(input) {
        const file = input.files[0];
        if (file && file.type.startsWith('image/')) {
            const preview = input.closest('.drop-zone').querySelector('img.preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        }
    }

    function marcarPdfAdicionado(input) {
        const dropZone = input.closest('.drop-zone');
        const nomeSpan = dropZone.querySelector('.nome-pdf');

        if (input.files.length > 0) {
            nomeSpan.textContent = input.files[0].name;
            nomeSpan.style.color = '#333';
        } else {
            nomeSpan.textContent = 'Adicionar Anexo';
            nomeSpan.style.color = '#666';
        }
    }

    function toggleDescricao(input) {
        const inputDescricao = input.closest('.drop-zone').querySelector('.descricao-imagem');
        if (input.files.length > 0) {
            inputDescricao.style.display = 'block';
        } else {
            inputDescricao.style.display = 'none';
        }
    }
    </script>
    
    <!-- Estilos do Quill -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <!-- Scripts do Quill -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
      // Inicializa o editor
    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Digite seu conteúdo aqui...',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                ['link', 'blockquote', 'code-block'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['clean']
            ]
        }
    });

    // Quando o formulário for enviado, armazena o conteúdo HTML no <textarea>
    document.querySelector('form').addEventListener('submit', function () {
        document.querySelector('#conteudo').value = quill.root.innerHTML;
    });
    </script>
    
<script>
    let contadorImagem = 2;
    let contadorPdf = 2;

    function adicionarCampoImagem() {
        contadorImagem++;

        const container = document.getElementById('container-imagens');
        const botaoAdd = document.getElementById('add-imagem-btn');

        // Cria novo campo de imagem
        const novaDiv = document.createElement('div');
        novaDiv.className = 'drop-zone';
        novaDiv.innerHTML = `
            <div style="padding-block: 20px; cursor: pointer;" onclick="this.nextElementSibling.click()">
                <i class="fas fa-image fa-2xl"></i>
            </div>
            <input type="file" name="imagem_${contadorImagem}" accept="image/*" onchange="mostrarPreview(this); toggleDescricao(this)">
            <img src="" class="preview" style="display:none">
            <input type="text" name="imagem_descricao_${contadorImagem}" class="descricao-imagem" placeholder="Descrição da imagem....">
        `;

        // Insere novo campo antes do botão de adicionar
        container.insertBefore(novaDiv, botaoAdd);
    }
    
    function adicionarCampoPdf() {
        contadorPdf++;
        const container = document.getElementById('container-pdfs');
        const botaoAdd = document.getElementById('add-pdf-btn');

        const div = document.createElement('div');
        div.className = 'drop-zone pdf';
        div.innerHTML = `
            <div style="padding-block: 20px; cursor: pointer;" onclick="this.nextElementSibling.click()">
                <i class="fas fa-file-pdf fa-2xl" style="color: red;"></i>
            </div>
            <input type="file" name="pdf_${contadorPdf}" accept="application/pdf" onchange="marcarPdfAdicionado(this)" style="display: none;">
            <input type="text" name="pdf_descricao_${contadorPdf}" placeholder="Descrição do anexo">
        `;

        container.insertBefore(div, botaoAdd);
    }
</script>


    <?php
    return ob_get_clean();
});
