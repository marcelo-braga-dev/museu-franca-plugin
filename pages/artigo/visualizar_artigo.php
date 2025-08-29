<?php
add_shortcode('visualizar_artigo', function () {
    if (!isset($_GET['id'])) return 'Artigo não encontrado.';
    $post_id = intval($_GET['id']);
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'artigo') return 'Artigo inválido.';

    // ✅ Se o usuário estiver logado, salva o último acesso
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'last_login', current_time('timestamp'));
    }

    // MP: Trata o clique do botão Publicar (POST)
    if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['mp_publicar'], $_POST['mp_pub_nonce'], $_POST['post_id']) &&
            intval($_POST['post_id']) === $post_id &&
            wp_verify_nonce($_POST['mp_pub_nonce'], 'mp_publicar_' . $post_id)
    ) {
        $pode_publicar = current_user_can('manage_options') || current_user_can('publish_posts') || current_user_can('publish_artigos');

        if ($pode_publicar && get_post_status($post_id) === 'pending') {
            wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $url = site_url('/minha-conta/?aba=revisao');
            echo "<script>window.location.href = " . json_encode($url) . ";</script>";
            exit;
        }
    }

    $categorias = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    $autor_nome = get_the_author_meta('display_name', $post->post_author);
    $data_publicacao = get_the_date('d \d\e F \d\e Y', $post_id);

    // Dados para compartilhamento
    $permalink = get_permalink($post_id);
    $titulo = get_the_title($post_id);
    $share_url = rawurlencode($permalink);
    $share_txt = rawurlencode($titulo);

    // Coleta áudios (IDs salvos em meta 'audio')
    $audios_ids = array_values(array_filter((array)get_post_meta($post_id, 'audio')));
    $tem_audios = !empty($audios_ids);

    ob_start();
    ?>
    <style>
        .artigo-visualizar {
            color: #000;
            padding: 40px 20px;
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
            margin-bottom: 10px;
        }

        .artigo-meta .avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid var(--mp-border)
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }

        .status-publish {
            background: #DCFCE7;
            color: #166534;
            border: 1px solid #86EFAC;
        }

        .mp-pub-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 10px 0 24px;
        }

        .mp-btn-publish {
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }

        .mp-alert-ok {
            background: #DCFCE7;
            border: 1px solid #22C55E;
            color: #14532D;
            padding: 10px 12px;
            border-radius: 8px;
            margin: 0 0 16px;
        }

        .mp-alert-err {
            background: #FEE2E2;
            border: 1px solid #EF4444;
            color: #7F1D1D;
            padding: 10px 12px;
            border-radius: 8px;
            margin: 0 0 16px;
        }

        .artigo-visualizar img {
            display: block;
            max-width: 100%;
            margin: 0 auto 20px;
            border-radius: 6px;
        }

        .artigo-resumo {
            background: #fafafa;
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
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

        /* ===== SHARE BAR ===== */
        .share-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 0 auto 30px;
            max-width: 900px;
        }

        .share-btn {
            --bg: #111;
            --fg: #fff;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid rgba(0, 0, 0, .08);
            background: var(--bg);
            color: var(--fg);
            transition: transform .08s, filter .2s, box-shadow .2s;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .08);
        }

        .share-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.02);
        }

        .share-btn svg {
            width: 14px;
            height: 14px;
            display: block;
        }

        .share-btn.whatsapp {
            --bg: #25D366
        }

        .share-btn.telegram {
            --bg: #229ED9
        }

        .share-btn.facebook {
            --bg: #1877F2
        }

        .share-btn.twitter {
            --bg: #000
        }

        .share-btn.linkedin {
            --bg: #0A66C2
        }

        .share-btn.email {
            --bg: #6B7280
        }

        .share-btn.copy {
            --bg: #0f172a
        }

        @media (max-width: 560px) {
            .share-btn {
                padding: 9px 12px;
                font-size: 13px
            }

            .share-btn span {
                display: none
            }
        }

        /* ===== PDFs ===== */
        .pdf-list {
            display: grid;
            grid-template-columns:1fr;
            gap: 12px;
            margin: 10px 0 18px;
        }

        .pdf-item {
            display: grid;
            grid-template-columns:auto 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px solid #e6e8eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(16, 24, 40, .04);
            transition: transform .15s, box-shadow .15s, border-color .15s;
        }

        .pdf-item:hover {
            transform: translateY(-2px);
            border-color: #dbe2ea;
            box-shadow: 0 10px 24px rgba(16, 24, 40, .08);
        }

        .pdf-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #fff1f1;
            border: 1px solid #fecaca;
            color: #d32f2f;
        }

        .pdf-icon i {
            font-size: 20px;
        }

        .pdf-info {
            display: grid;
            gap: 2px;
        }

        .pdf-title {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.2;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pdf-meta {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .pdf-desc {
            font-size: 13px;
            color: #475569;
            margin: 2px 0 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pdf-actions {
            display: grid;
            gap: 8px;
            justify-items: end;
        }

        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            border: 1px solid #e6e8eb;
            background: #fff;
            color: #111827;
            transition: background .15s, border-color .15s, transform .02s;
        }

        .pdf-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .pdf-btn:active {
            transform: translateY(1px);
        }

        .pdf-btn--view i {
            color: #2563eb;
        }

        .pdf-btn--down i {
            color: #9E2B19;
        }

        /* ===== Favoritos ===== */
        .mp-fav-button {
            all: unset;
            display: inline-flex !important;
            align-items: center;
            gap: 6px;
            padding: 8px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            background: #fff !important;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            color: #374151 !important;
            line-height: 1;
            margin-left: 10px;
            transition: transform .12s, box-shadow .2s, border-color .2s, background .2s, color .2s;
        }

        .mp-fav-button i {
            font-size: 14px;
            color: #9ca3af;
            transition: color .2s;
        }

        .mp-fav-button:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
            transform: translateY(-1px);
        }

        .mp-fav-button.is-fav {
            border-color: #e11d48;
            color: #e11d48 !important;
        }

        .mp-fav-button.is-fav i {
            color: #e11d48;
        }

        /* ========== GALERIA ========== */
        .galeria-imagens {
            display: grid;
            grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 10px;
        }

        .galeria-imagens .g-item {
            border-radius: 14px;
            overflow: hidden;
            background: #f5f7fa;
            border: 1px solid #e8e8e8;
            box-shadow: 0 6px 20px rgba(2, 6, 23, .06);
            transition: transform .18s, box-shadow .18s;
        }

        .galeria-imagens .g-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(2, 6, 23, .12);
        }

        .galeria-imagens .g-media {
            aspect-ratio: 4/3;
            width: 100%;
            height: auto;
            object-fit: cover;
            display: block;
            filter: saturate(1.02);
        }

        .galeria-imagens .g-caption {
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
            color: #374151;
            background: #fff;
            border-top: 1px solid #e5e7eb;
        }

        .galeria-imagens .g-actions {
            display: flex;
            gap: 8px;
            padding: 8px 10px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .galeria-imagens .g-btn {
            all: unset;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff;
            color: #0f172a;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }

        .galeria-imagens .g-btn i {
            font-size: 13px;
        }

        .galeria-imagens .g-btn:active {
            transform: translateY(1px);
        }

        /* ===== LIGHTBOX ===== */
        #lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(10, 12, 16, .88);
            backdrop-filter: saturate(1.3) blur(2px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .lb-stage {
            position: relative;
            width: min(96vw, 1200px);
            height: min(86vh, 820px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #lb-img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 12px;
            transition: transform .25s ease;
            user-select: none;
            -webkit-user-drag: none;
            pointer-events: none;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .35);
        }

        .lb-topbar {
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            background: rgba(0, 0, 0, .45);
            border: 1px solid rgba(255, 255, 255, .18);
            padding: 6px 10px;
            border-radius: 999px;
            color: #f8fafc;
            font-size: 12px;
            font-weight: 700;
            z-index: 3;
        }

        .lb-nav {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 3;
        }

        .lb-arrow {
            all: unset;
            cursor: pointer;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 900;
            background: #9E2B19;
            color: #fff;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .25);
            transition: transform .15s ease, background .2s ease;
        }

        .lb-arrow:hover, .lb-arrow:focus {
            transform: scale(1.1);
            background: #b83224;
            outline: none;
        }

        .lb-controls {
            position: absolute;
            right: 14px;
            top: 14px;
            display: flex;
            gap: 8px;
            z-index: 4;
        }

        .lb-close {
            all: unset;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #fff;
            color: #0f172a;
            font-weight: 900;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 16px rgba(0, 0, 0, .25);
        }

        .lb-btm {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: min(96vw, 1200px);
            margin-top: 14px;
            color: #e5e7eb;
            gap: 10px;
            z-index: 2;
        }

        #lb-caption {
            font-size: 14px;
            line-height: 1.4;
            flex: 1;
        }

        .lb-actions {
            display: flex;
            gap: 8px;
        }

        .lb-btn {
            all: unset;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #ffffff;
            color: #0f172a;
            font-weight: 800;
            font-size: 13px;
            border: 1px solid #e5e7eb;
        }

        @media (max-width: 560px) {
            .lb-arrow {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 25px 0 15px;
            padding-top: 20px;
            padding-bottom: 6px;
            border-top: 1px solid #e5e5e5;
        }

        .section-title i {
            font-size: 28px;
            color: #9E2B19;
            flex-shrink: 0;
        }

        .section-title h5 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        /* ========== VÍDEOS ========== */
        .videos-view-wrap {
            max-width: 1100px;
            margin: 0 auto 24px;
            padding: 0 12px;
        }

        .videos-view-grid {
            display: grid;
            grid-template-columns:1fr;
            gap: 22px;
        }

        .video-card {
            position: relative;
            border-radius: 16px;
            padding: 1px;
            background: linear-gradient(180deg, rgba(153, 45, 23, .35), rgba(2, 6, 23, .06));
            box-shadow: 0 10px 26px rgba(2, 6, 23, .08);
            transition: transform .18s ease, box-shadow .18s ease, filter .2s ease;
        }

        .video-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 38px rgba(2, 6, 23, .12);
            filter: saturate(1.02);
        }

        .video-card__inner {
            background: #fff;
            border-radius: 16px;
            padding: 14px;
            display: grid;
            gap: 12px;
        }

        .video-frame {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
        }

        .video-frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        @supports not (aspect-ratio:16/9) {
            .video-frame {
                position: relative;
                padding-top: 56.25%
            }

            .video-frame iframe {
                position: absolute;
                inset: 0
            }
        }

        .video-desc {
            font-size: 15px;
            line-height: 1.55;
            color: #374151;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            padding: 10px 12px;
            border-radius: 10px;
            text-align: center;
        }

        @media (min-width: 1400px) {
            .videos-view-wrap {
                max-width: 1280px;
            }
        }

        .avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid var(--mp-border)
        }

        .autor-meta {
            display: flex;
            gap: 6px;
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .autor-meta .avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid var(--mp-border)
        }

        /* ===== ÁUDIO (estilo harmonizado) ===== */
        .audio-list {
            display: grid;
            grid-template-columns:1fr;
            gap: 12px;
            margin: 10px 0 18px;
        }

        .audio-item {
            display: grid;
            grid-template-columns:auto 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px solid #e6e8eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(16, 24, 40, .04);
            transition: transform .15s, box-shadow .15s, border-color .15s;
        }

        .audio-item:hover {
            transform: translateY(-2px);
            border-color: #dbe2ea;
            box-shadow: 0 10px 24px rgba(16, 24, 40, .08);
        }

        .audio-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .audio-icon i {
            font-size: 20px;
        }

        .audio-info {
            display: grid;
            gap: 2px;
        }

        .audio-title {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.2;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .audio-meta {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .audio-desc {
            font-size: 13px;
            color: #475569;
            margin: 2px 0 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .audio-actions {
            display: grid;
            gap: 8px;
            justify-items: end;
        }

        .audio-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            border: 1px solid #e6e8eb;
            background: #fff;
            color: #111827;
            transition: background .15s, border-color .15s, transform .02s;
        }

        .audio-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .audio-btn:active {
            transform: translateY(1px);
        }

        .audio-btn--down i {
            color: #9E2B19;
        }

        /* Player (Plyr) integração visual */
        .plyr {
            --plyr-color-main: #9E2B19;
            border-radius: 10px;
            overflow: hidden;
        }

        .plyr--audio {
            background: #0f0f0f;
        }

        .audio-player-row {
            grid-column: 1 / -1; /* ocupa toda a largura do card */
        }

        .artigos-semelhantes {
            margin-bottom: 40px;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #e5e7eb;
        }
    </style>

    <div class="artigo-visualizar">
        <?php if (isset($_GET['pub_ok'])): ?>
            <div class="mp-alert-ok">✅ Artigo publicado com sucesso.</div>
        <?php elseif (isset($_GET['pub_fail'])): ?>
            <div class="mp-alert-err">❌ Não foi possível publicar. Verifique permissões ou status.</div>
        <?php endif; ?>

        <div style="text-align:center; margin-bottom:10px;">
            <?php if ($post->post_status === 'pending'): ?>
                <span class="status-badge status-pending">Pendente de revisão</span>
            <?php endif; ?>
        </div>

        <?php
        $pode_publicar = current_user_can('manage_options') || current_user_can('publish_posts') || current_user_can('publish_artigos');
        if ($post->post_status === 'pending' && $pode_publicar): ?>
            <div class="mp-pub-actions">
                <form method="post">
                    <?php wp_nonce_field('mp_publicar_' . $post_id, 'mp_pub_nonce'); ?>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                    <button type="submit" name="mp_publicar" class="mp-btn-publish">Publicar agora</button>
                </form>
            </div>
        <?php endif; ?>

        <h2><?= esc_html($post->post_title); ?></h2>

        <div class="autor-meta" style=" align-items:center; justify-content:center; gap:6px; text-align:center;">
            Por: <?= esc_html($autor_nome); ?>
            , <?= esc_html($data_publicacao); ?>  <?= function_exists('mp_favorito_botao') ? mp_favorito_botao($post_id) : '' ?>
        </div>

        <!-- SHARE BAR -->
        <div class="share-bar" role="group" aria-label="Compartilhar este artigo">
            <a class="share-btn whatsapp" target="_blank" rel="noopener"
               href="https://api.whatsapp.com/send?text=<?= $share_txt ?>%20<?= $share_url ?>"
               aria-label="Compartilhar no WhatsApp">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.52 3.48A11.9 11.9 0 0 0 12.05 0C5.5 0 .2 5.3.2 11.84c0 2.09.55 4.11 1.6 5.9L0 24l6.42-1.67a11.8 11.8 0 0 0 5.62 1.43h.01c6.55 0 11.85-5.3 11.85-11.84a11.79 11.79 0 0 0-3.38-8.44Zm-8.47 18.7h-.01a9.86 9.86 0 0 1-5.02-1.38l-.36-.21-3.81 1 1.02-3.71-.24-.38a9.86 9.86 0 0 1-1.53-5.28C2.1 6.41 6.62 1.9 12.05 1.9c2.62 0 5.08 1.02 6.93 2.88a9.86 9.86 0 0 1 2.9 6.95c0 5.43-4.52 9.95-9.93 9.95Zm5.7-7.44c-.31-.16-1.83-.9-2.11-1-.28-.1-.48-.16-.69.16-.2.31-.79 1-.97 1.2-.18.2-.36.23-.67.08-.31-.16-1.32-.49-2.51-1.56-.93-.83-1.56-1.86-1.74-2.17-.18-.31-.02-.48.14-.63.14-.14.31-.36.46-.54.15-.18.2-.31.31-.52.1-.2.05-.38-.03-.54-.08-.16-.69-1.66-.95-2.28-.25-.6-.5-.52-.69-.53h-.59c-.2 0-.52.08-.79.38-.28.31-1.04 1.02-1.04 2.49 0 1.46 1.07 2.88 1.22 3.08.16.2 2.11 3.23 5.1 4.53.71.31 1.26.49 1.7.63.71.23 1.35.2 1.86.12.57-.08 1.83-.75 2.09-1.48.26-.73.26-1.35.18-1.48-.08-.13-.28-.2-.59-.36Z"/>
                </svg>
            </a>
            <a class="share-btn telegram" target="_blank" rel="noopener"
               href="https://t.me/share/url?url=<?= $share_url ?>&text=<?= $share_txt ?>"
               aria-label="Compartilhar no Telegram">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.372 0 0 5.373 0 12c0 6.627 5.372 12 12 12s12-5.373 12-12c0-6.627-5.372-12-12-12zm5.197 7.44l-2.145 10.13c-.161.723-.585.9-1.184.56l-3.274-2.413-1.58 1.523c-.175.175-.322.322-.661.322l.236-3.34 6.074-5.487c.265-.236-.058-.367-.41-.132l-7.507 4.733-3.236-1.012c-.704-.22-.723-.704.147-1.04l12.65-4.88c.585-.21 1.096.14.905 1.04z"/>
                </svg>
            </a>
            <a class="share-btn facebook" target="_blank" rel="noopener"
               href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>"
               aria-label="Compartilhar no Facebook">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M22.675 0H1.325C.593 0 0 .593 0 1.326v21.348C0 23.406.593 24 1.325 24H12.82v-9.294H9.692V11.09h3.128V8.41c0-3.1 1.893-4.788 4.66-4.788 1.325 0 2.463.099 2.795.143v3.24h-1.918c-1.504 0-1.795.715-1.795 1.763v2.32h3.587l-.467 3.615h-3.12V24h6.116C23.406 24 24 23.406 24 22.674V1.326C24 .593 23.406 0 22.675 0z"/>
                </svg>
            </a>
            <a class="share-btn twitter" target="_blank" rel="noopener"
               href="https://twitter.com/intent/tweet?text=<?= $share_txt ?>&url=<?= $share_url ?>"
               aria-label="Compartilhar no X">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.244 2H21.5l-7.54 8.6L23.5 22h-7.02l-5.49-6.74L4.7 22H1.5l8.06-9.2L1 2h7.18l5.01 6.12L18.24 2Zm-1.23 18h1.94L7.06 4H5.02l12 16Z"/>
                </svg>
            </a>
            <a class="share-btn linkedin" target="_blank" rel="noopener"
               href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share_url ?>"
               aria-label="Compartilhar no LinkedIn">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4.98 3.5C4.98 5 3.9 6 2.5 6S0 5 0 3.5C0 2 1.1 1 2.49 1h.02C3.9 1 4.98 2 4.98 3.5zM0 8h5v16H0V8zm7.5 0h4.8v2.2h.1c.7-1.3 2.3-2.7 4.7-2.7 5 0 5.9 3.3 5.9 7.6V24h-5v-7.6c0-1.8 0-4.1-2.5-4.1-2.5 0-2.9 2-2.9 4v7.7h-5V8z"/>
                </svg>
            </a>
            <a class="share-btn email"
               href="mailto:?subject=<?= $share_txt ?>&body=<?= $share_txt ?>%0A%0A<?= $share_url ?>"
               aria-label="Compartilhar por e-mail">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </a>
            <button type="button" class="share-btn copy" onclick="copiarLink('<?= esc_js($permalink) ?>')"
                    aria-label="Copiar link do artigo">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M16 1H4c-1.1 0-2 .9-2 2v12h2V3h12V1Zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2Zm0 16H8V7h11v14Z"/>
                </svg>
            </button>
        </div>
        <!-- / SHARE BAR -->

        <?php
        $capa_url = get_the_post_thumbnail_url($post_id, 'large');
        $capa_full = get_the_post_thumbnail_url($post_id, 'full');
        if ($capa_url && $capa_full): ?>
            <img src="<?= esc_url($capa_url); ?>" alt="Capa" style="cursor:zoom-in; max-height:350px"
                 onclick="abrirLightbox('<?= esc_url($capa_full); ?>')">
        <?php endif; ?>


        <div class="artigo-resumo">
            <?php if (!empty($post->post_excerpt)) : ?>
                <div style="margin-bottom:10px;">
                    <strong>Resumo</strong>
                </div>
            <?php endif; ?>
            <div><?= esc_html($post->post_excerpt); ?></div>
            <div class="info-inline" style="font-size:11px;">
                <?php if (!empty($categorias)) : ?>
                    <strong>Categoria:</strong> <?= esc_html(implode(', ', $categorias)); ?><br/>
                <?php endif; ?>

                <?php
                $badges = mp_colecao_badges($post_id);
                if ($badges) : ?>
                    <div class="mp-meta-linh a"><strong>Coleção:</strong> <?= $badges ?></div>
                <?php endif; ?>


                <?php if (!empty($tags)) :
                    $tags_formatadas = array_map(fn($t) => '#' . esc_html($t), $tags); ?>
                    <strong>Palavras-chave:</strong> <?= implode(', ', $tags_formatadas); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="artigo-conteudo"><?= apply_filters('the_content', $post->post_content); ?></div>

        <?php
        // ===== GALERIA =====
        $imagens = get_post_meta($post_id, 'imagem_adicional');
        if (!empty($imagens)) {
            echo '<div class="section-title">
                    <i class="fa-regular fa-images"></i>
                    <h5>Galeria de Imagens:</h5>
                   </div>
            <div class="galeria-imagens" id="galeria" role="list">';
            foreach ($imagens as $idx => $id) {
                $thumb = wp_get_attachment_image_url($id, 'large');
                $full = wp_get_attachment_image_url($id, 'full');
                $desc = get_post_meta($post_id, 'imagem_adicional_descricao_' . $id, true);
                if ($thumb && $full) {
                    $safeDesc = esc_attr($desc ?: '');
                    $altText = get_post_meta($id, '_wp_attachment_image_alt', true) ?: get_the_title($id);
                    echo '<figure class="g-item" role="listitem" tabindex="0"
                                data-full="' . esc_url($full) . '"
                                data-desc="' . $safeDesc . '"
                                data-index="' . intval($idx) . '">
                            <img class="g-media" src="' . esc_url($thumb) . '" alt="' . esc_attr($altText) . '" loading="lazy">';
                    if ($desc) echo '<figcaption class="g-caption">' . esc_html($desc) . '</figcaption>';
                    echo '<div class="g-actions">
                                <button type="button" class="g-btn g-open" aria-label="Ampliar"><i class="fa-regular fa-eye"></i><span>Ver</span></button>
                                <a class="g-btn g-download" href="' . esc_url($full) . '" download aria-label="Baixar imagem"><i class="fa-solid fa-download"></i><span>Baixar</span></a>
                            </div>
                        </figure>';
                }
            }
            echo '</div>';
        }

        // ===== Vídeos YouTube =====
        // Extrai ID do YouTube (se já não existir a função no escopo)
        if (!function_exists('mp_extract_youtube_id')) {
            function mp_extract_youtube_id($url)
            {
                $url = trim((string)$url);
                if ($url === '') return '';
                $pats = [
                        '/youtu\.be\/([a-zA-Z0-9_-]{11})/i',
                        '/youtube\.com\/(?:embed|shorts|v)\/([a-zA-Z0-9_-]{11})/i',
                        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i',
                        '/youtube\.com\/watch\?.*?[&?]v=([a-zA-Z0-9_-]{11})/i',
                ];
                foreach ($pats as $p) {
                    if (preg_match($p, $url, $m)) return $m[1];
                }
                return '';
            }
        }

        if (!function_exists('mp_collect_videos')) {
            function mp_collect_videos($post_id)
            {
                $out = [];
                $items = (array)get_post_meta($post_id, 'youtube_link');
                foreach ($items as $raw) {
                    $data = is_array($raw) ? $raw : maybe_unserialize($raw);
                    $vid = isset($data['video_id']) ? sanitize_text_field($data['video_id']) : '';
                    $desc = isset($data['desc']) ? wp_kses_post($data['desc']) : '';
                    if ($vid) {
                        $out[] = [
                                'id' => $vid,
                                'desc' => $desc,
                                'embed' => "https://www.youtube.com/embed/$vid",
                                'watch' => "https://www.youtube.com/watch?v=$vid",
                        ];
                    }
                }
                if (empty($out)) {
                    $links = (array)get_post_meta($post_id, 'youtube_link');
                    foreach ($links as $url) {
                        $vid = mp_extract_youtube_id($url);
                        if ($vid) {
                            $out[] = [
                                    'id' => $vid,
                                    'desc' => '',
                                    'embed' => "https://www.youtube.com/embed/$vid",
                                    'watch' => "https://www.youtube.com/watch?v=$vid",
                            ];
                        }
                    }
                }
                return $out;
            }
        }

        $cards = mp_collect_videos($post_id);
        if (!empty($cards)) : ?>
            <div class="section-title">
                <i class="fab fa-youtube" style="color:#ff0000"></i>
                <h5>Vídeos</h5>
            </div>
            <div class="videos-view-wrap">
                <div class="videos-view-grid">
                    <?php foreach ($cards as $i => $v): ?>
                        <article class="video-card" role="group"
                                 aria-label="<?php echo esc_attr('Vídeo ' . ($i + 1)); ?>">
                            <div class="video-card__inner">
                                <div class="video-frame">
                                    <iframe src="<?php echo esc_url($v['embed']); ?>" loading="lazy" allowfullscreen
                                            title="<?php echo esc_attr('YouTube player ' . ($i + 1)); ?>"></iframe>
                                </div>
                                <?php if (!empty($v['desc'])): ?>
                                    <div class="video-desc"><?php echo $v['desc']; ?></div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // ===== ÁUDIOS =====
        if ($tem_audios): ?>
            <div class="section-title">
                <i class="fa-solid fa-volume-high"></i></i>
                <h5>Áudios</h5>
            </div>

            <div class="audio-list">
                <?php
                foreach ($audios_ids as $aid):
                    $aid = intval($aid);
                    $src = wp_get_attachment_url($aid);
                    if (!$src) continue;
                    $mime = get_post_mime_type($aid) ?: 'audio/mpeg';
                    $title = get_the_title($aid) ?: wp_basename(parse_url($src, PHP_URL_PATH));
                    $desc = get_post_meta($post_id, 'audio_descricao_' . $aid, true);
                    $path = get_attached_file($aid);
                    $size = (is_string($path) && file_exists($path)) ? filesize($path) : 0;

                    // formatador de tamanho (reuso rápido local)
                    $size_h = '';
                    if ($size) {
                        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                        $pow = floor(($size ? log($size, 1024) : 0));
                        $pow = min($pow, count($units) - 1);
                        $size_h = number_format($size / pow(1024, $pow), 1, ',', '.') . ' ' . $units[$pow];
                    }
                    ?>
                    <div class="audio-item">
                        <div class="audio-icon" aria-hidden="true"><i class="fa-solid fa-volume-high"></i></div>

                        <div class="audio-info">
                            <p class="audio-title"><?= esc_html($title); ?></p>
                            <p class="audio-meta"><?= $size_h ? esc_html($size_h) . ' · ' : '' ?><?= esc_html($mime); ?></p>
                            <?php if (!empty($desc)): ?><p class="audio-desc"><?= esc_html($desc); ?></p><?php endif; ?>
                        </div>

                        <div class="audio-actions">
                            <a class="audio-btn audio-btn--down" href="<?= esc_url($src); ?>" download>
                                <i class="fa-solid fa-download" aria-hidden="true"></i> Baixar
                            </a>
                        </div>

                        <div class="audio-player-row">
                            <audio class="mp-audio" preload="none">
                                <source src="<?= esc_url($src); ?>" type="<?= esc_attr($mime); ?>">
                                Seu navegador não suporta áudio HTML5.
                            </audio>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        // ===== PDFs =====
        $pdfs = (array)get_post_meta($post_id, 'pdf');
        $pdfs = array_filter($pdfs, fn($v) => !empty($v));
        if (!empty($pdfs)) : ?>
            <div class="section-title">
                <i class="fa-regular fa-file-pdf"></i>
                <h5>Anexos PDF</h5>
            </div>
            <div class="pdf-list">
                <?php foreach ($pdfs as $id):
                    $id = intval($id);
                    $url = wp_get_attachment_url($id);
                    if (!$url) continue;
                    $title = get_the_title($id);
                    $descricao = get_post_meta($post_id, 'pdf_descricao_' . $id, true);
                    $path = get_attached_file($id);
                    if (!function_exists('mp_format_bytes')) {
                        function mp_format_bytes($bytes, $precision = 1)
                        {
                            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                            $bytes = max($bytes, 0);
                            $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
                            $pow = min($pow, count($units) - 1);
                            $bytes /= (1 << (10 * $pow));
                            return number_format($bytes, $precision, ',', '.') . ' ' . $units[$pow];
                        }
                    }
                    $size = (is_string($path) && file_exists($path)) ? mp_format_bytes(filesize($path)) : '';
                    $basename = $title ?: wp_basename(parse_url($url, PHP_URL_PATH)); ?>
                    <div class="pdf-item">
                        <div class="pdf-icon" aria-hidden="true"><i class="fa-regular fa-file-pdf"></i></div>
                        <div class="pdf-info">
                            <a href="<?= esc_url($url); ?>" target="_blank" rel="noopener"><p
                                        class="pdf-title"><?= esc_html($basename); ?></p></a>
                            <p class="pdf-meta"><?= $size ? esc_html($size) . ' · ' : '' ?>PDF</p>
                            <?php if (!empty($descricao)): ?><p
                                    class="pdf-desc"><?= esc_html($descricao); ?></p><?php endif; ?>
                        </div>
                        <div class="pdf-actions">
                            <a class="pdf-btn pdf-btn--view" href="<?= esc_url($url); ?>" target="_blank"
                               rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ver</a>
                            <a class="pdf-btn pdf-btn--down" href="<?= esc_url($url); ?>" download><i
                                        class="fa-solid fa-download" aria-hidden="true"></i> Baixar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="border:1px solid #e1e1e1; padding:15px; border-radius:5px; margin-bottom:20px">
        <small style="color:#5c5c5d">
            <b>Aviso de Responsabilidade</b><br/>
            Os conteúdos disponibilizados neste site são de uso exclusivo para fins informativos e pessoais. Qualquer
            cópia, reprodução, distribuição ou utilização indevida é de inteira responsabilidade do usuário que a
            praticar,
            estando sujeito às medidas legais cabíveis.
        </small>
    </div>

    <!-- ====== Widgets de conteúdos semelhantes (usa o outro shortcode) ====== -->
    <div class="artigos-semelhantes">
        <?php
        echo do_shortcode('[widgets_artigos mode="related_category" post_id="auto" title="Sugeridos para você!"]');
        ?>
    </div>

    <!-- /widgets -->

    <!-- Lightbox moderno -->
    <div id="lightbox-overlay" aria-hidden="true">
        <div class="lb-stage" id="lb-stage">
            <div class="lb-topbar" id="lb-counter">1 / 1</div>
            <div class="lb-controls">
                <button type="button" class="lb-btn--ghost lb-close" id="lb-close" aria-label="Fechar">✕</button>
            </div>

            <img id="lb-img" src="" alt="Imagem ampliada">

            <div class="lb-nav">
                <button type="button" class="lb-arrow" id="lb-prev" aria-label="Anterior"><i
                            class="fa-solid fa-arrow-left"></i></button>
                <button type="button" class="lb-arrow" id="lb-next" aria-label="Próxima"><i
                            class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        <div class="lb-btm">
            <div id="lb-caption"></div>
            <div class="lb-actions">
                <button type="button" class="lb-btn" id="lb-zoom-in" aria-label="Mais zoom"><i
                            class="fa-solid fa-magnifying-glass-plus"></i><span>+</span></button>
                <button type="button" class="lb-btn" id="lb-zoom-out" aria-label="Menos zoom"><i
                            class="fa-solid fa-magnifying-glass-minus"></i><span>−</span></button>
                <a class="lb-btn" id="lb-download" href="#" download aria-label="Baixar imagem"><i
                            class="fa-solid fa-download"></i><span>Baixar</span></a>
            </div>
        </div>
    </div>

    <?php if ($tem_audios): ?>
        <!-- Plyr CSS/JS (somente se houver áudios) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.css">
        <script src="https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.polyfilled.min.js"></script>
    <?php endif; ?>

    <script>
        let zoom = 1; // compat: botão da capa usa abrirLightbox()

        function abrirLightbox(src) {
            const overlay = document.getElementById('lightbox-overlay');
            const img = document.getElementById('lb-img');
            const caption = document.getElementById('lb-caption');
            const counter = document.getElementById('lb-counter');
            const btnDownload = document.getElementById('lb-download');

            img.src = src;
            img.style.transform = 'scale(1)';
            zoom = 1;
            caption.textContent = '';
            counter.textContent = '—';
            btnDownload.href = src;

            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        // ===== Novo Lightbox da Galeria =====
        (function () {
            let items = [], current = 0, z = 1;
            const qs = s => document.querySelector(s);
            const qsa = s => Array.from(document.querySelectorAll(s));
            const overlay = qs('#lightbox-overlay');
            const img = qs('#lb-img');
            const caption = qs('#lb-caption');
            const counter = qs('#lb-counter');
            const btnPrev = qs('#lb-prev');
            const btnNext = qs('#lb-next');
            const btnClose = qs('#lb-close');
            const btnZoomIn = qs('#lb-zoom-in');
            const btnZoomOut = qs('#lb-zoom-out');
            const btnDownload = qs('#lb-download');
            const stage = qs('#lb-stage');

            function collect() {
                items = qsa('.galeria-imagens .g-item').map(el => ({
                    full: el.getAttribute('data-full'),
                    desc: el.getAttribute('data-desc') || '',
                    el
                }));
            }

            function openAt(index) {
                if (!items.length) return;
                current = (index + items.length) % items.length;
                const it = items[current];
                img.src = it.full;
                img.style.transform = 'scale(1)';
                z = 1;
                caption.textContent = it.desc || '';
                counter.textContent = (current + 1) + ' / ' + items.length;
                btnDownload.href = it.full;
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function close() {
                overlay.style.display = 'none';
                overlay.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function next() {
                openAt(current + 1);
            }

            function prev() {
                openAt(current - 1);
            }

            function zoomIn() {
                z = Math.min(4, z + 0.2);
                img.style.transform = 'scale(' + z + ')';
            }

            function zoomOut() {
                z = Math.max(0.2, z - 0.2);
                img.style.transform = 'scale(' + z + ')';
            }

            function onKey(e) {
                if (overlay.getAttribute('aria-hidden') === 'true') return;
                if (e.key === 'Escape') close();
                if (e.key === 'ArrowRight') next();
                if (e.key === 'ArrowLeft') prev();
                if (e.key === '+') zoomIn();
                if (e.key === '-') zoomOut();
            }

            // Swipe
            let touchX = null;
            stage.addEventListener('touchstart', (e) => {
                touchX = e.touches[0].clientX;
            }, {passive: true});
            stage.addEventListener('touchend', (e) => {
                if (touchX == null) return;
                const dx = e.changedTouches[0].clientX - touchX;
                if (Math.abs(dx) > 40) {
                    dx < 0 ? next() : prev();
                }
                touchX = null;
            }, {passive: true});

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close();
            });
            btnClose.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                close();
            });
            btnNext.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                next();
            });
            btnPrev.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                prev();
            });
            btnZoomIn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                zoomIn();
            });
            btnZoomOut.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                zoomOut();
            });

            function wireItems() {
                qsa('.galeria-imagens .g-item').forEach((el, i) => {
                    const open = () => openAt(i);
                    el.addEventListener('click', open);
                    el.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            open();
                        }
                    });
                    const openBtn = el.querySelector('.g-open');
                    if (openBtn) openBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        open();
                    });
                    el.querySelectorAll('a.g-download[download]').forEach(a => {
                        a.addEventListener('click', (e) => {
                            e.stopPropagation();
                        });
                    });
                });
            }

            function init() {
                collect();
                wireItems();
                document.addEventListener('keydown', onKey);
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
            window._mpRefreshGallery = function () {
                collect();
                wireItems();
            };
        })();

        // Compartilhar: copiar link com feedback
        function copiarLink(url) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => toastCopyOK(), () => toastCopyFail());
            } else {
                const ta = document.createElement('textarea');
                ta.value = url;
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    toastCopyOK();
                } catch (e) {
                    toastCopyFail();
                } finally {
                    document.body.removeChild(ta);
                }
            }
        }

        function toastCopyOK() {
            const n = document.createElement('div');
            n.textContent = 'Link copiado!';
            n.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;box-shadow:0 10px 24px rgba(0,0,0,.2);z-index:10001';
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 1800);
        }

        function toastCopyFail() {
            const n = document.createElement('div');
            n.textContent = 'Não foi possível copiar o link.';
            n.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#ef4444;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;box-shadow:0 10px 24px rgba(0,0,0,.2);z-index:10001';
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 2200);
        }

        // ===== Inicializa Plyr se existir áudio =====
        (function () {
            if (typeof Plyr === 'undefined') return; // não há áudios ou CDN não carregou
            const players = Array.from(document.querySelectorAll('.mp-audio')).map(el => new Plyr(el, {
                controls: ['play', 'progress', 'current-time', 'duration', 'mute', 'volume'],
                clickToPlay: true,
                invertTime: false
            }));
            // Garante que só um toque por vez
            players.forEach(p => {
                p.on('play', () => {
                    players.forEach(other => {
                        if (other !== p) other.pause();
                    });
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
});
