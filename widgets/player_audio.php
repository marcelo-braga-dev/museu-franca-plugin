<?php
/**
 * Shortcode: [player_audios_artigo post_id="" layout="grid|list" download="yes|no" title="Áudios"]
 * - Busca anexos de áudio por meta 'audio' (IDs) ou por anexos do post (mime audio/*)
 * - Player moderno com Plyr
 */

add_action('wp_enqueue_scripts', function () {
    // Plyr (CDN)
    wp_enqueue_style(
        'plyr-css',
        'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.css',
        [],
        null
    );
    wp_enqueue_script(
        'plyr-js',
        'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.polyfilled.min.js',
        [],
        null,
        true
    );

    // Estilos do card
    $css = "
    .mp-audio-wrap{margin:1rem 0;}
    .mp-audio-grid{
        display:grid;
        gap:16px;
        grid-template-columns: repeat(auto-fill,minmax(260px,1fr));
    }
    .mp-audio-list{display:flex;flex-direction:column;gap:12px;}
    .mp-audio-card{
        background:#111; /* dark mode ok */
        border:1px solid rgba(255,255,255,.08);
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 6px 24px rgba(0,0,0,.2);
        transition:transform .18s ease, box-shadow .18s ease;
    }
    .mp-audio-card:hover{transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,0,0,.28);}
    .mp-audio-cover{
        position:relative; aspect-ratio:16/9; background:#1e1e1e; overflow:hidden;
    }
    .mp-audio-cover img{width:100%;height:100%;object-fit:cover;display:block;filter:saturate(1.05);}
    .mp-audio-badge{
        position:absolute; top:10px; left:10px;
        background:#9E2B19; color:#fff; font-size:.75rem; padding:.25rem .5rem; border-radius:999px;
        letter-spacing:.02em;
    }
    .mp-audio-body{padding:14px;}
    .mp-audio-title{
        display:flex;align-items:center;gap:8px;font-weight:600;font-size:1rem;margin:0 0 6px 0;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .mp-audio-desc{
        color:#cfcfcf;font-size:.9rem; line-height:1.3; margin:0 0 10px 0;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
    }
    .mp-audio-footer{
        display:flex;align-items:center;justify-content:space-between; gap:10px; margin-top:10px;
    }
    .mp-audio-download{
        display:inline-flex;align-items:center;gap:8px;
        padding:.45rem .7rem; border-radius:12px; background:#222; color:#fff; text-decoration:none;
        border:1px solid rgba(255,255,255,.08); font-size:.875rem;
    }
    .mp-audio-download:hover{background:#2a2a2a;}
    /* Plyr tweaks */
    .plyr{--plyr-color-main:#9E2B19; border-radius:12px; overflow:hidden;}
    .plyr--audio{ background:#0f0f0f; }
    ";
    wp_add_inline_style('plyr-css', $css);

    // JS de inicialização
    $js = "
    window.addEventListener('DOMContentLoaded',function(){
        const players = Array.from(document.querySelectorAll('.mp-audio')).map(el => new Plyr(el, {
            controls: ['play','progress','current-time','duration','mute','volume'],
            clickToPlay: true,
            invertTime: false
        }));
        // Tocar um por vez
        players.forEach(p => {
            p.on('play', () => {
                players.forEach(other => { if(other !== p) other.pause(); });
            });
        });
    });
    ";
    wp_add_inline_script('plyr-js', $js);
});

function mp_maybe_get_post_cover_url($post_id)
{
    $thumb = get_the_post_thumbnail_url($post_id, 'large');
    if ($thumb) return $thumb;
    // fallback simples (1x1 transparente)
    return 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
}

function mp_get_article_audios($post_id)
{
    $ids = [];
    // 1) IDs via meta 'audio' (um meta por ID)
    $meta = get_post_meta($post_id, 'audio');
    if (!empty($meta)) {
        foreach ($meta as $val) {
            $id = is_numeric($val) ? intval($val) : 0;
            if ($id && get_post_mime_type($id) && strpos(get_post_mime_type($id), 'audio/') === 0) {
                $ids[] = $id;
            }
        }
    }
    // 2) fallback: anexos de áudio ligados ao post
    if (empty($ids)) {
        $attached = get_children([
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => -1,
        ]);
        if ($attached) {
            foreach ($attached as $att) {
                $mime = get_post_mime_type($att);
                if ($mime && strpos($mime, 'audio/') === 0) $ids[] = $att->ID;
            }
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

add_shortcode('player_audios_artigo', function ($atts = []) {
    $a = shortcode_atts([
        'post_id' => '',
        'layout' => 'grid', // grid | list
        'download' => 'yes',
        'title' => 'Áudios'
    ], $atts, 'player_audios_artigo');

    $post_id = $a['post_id'] !== '' ? intval($a['post_id']) : get_the_ID();
    if (!$post_id) return '';

    $audio_ids = mp_get_article_audios($post_id);
    if (empty($audio_ids)) return '';

    $is_grid = strtolower($a['layout']) !== 'list';
    $download = strtolower($a['download']) === 'yes';
    $cover = mp_maybe_get_post_cover_url($post_id);

    ob_start(); ?>
    <section class="mp-audio-wrap">
        <?php if (!empty($a['title'])): ?>
            <h3 style="margin:.2rem 0 1rem 0; font-size:1.15rem; font-weight:700; letter-spacing:.01em;">
                <?= esc_html($a['title']); ?>
            </h3>
        <?php endif; ?>

        <div class="<?= $is_grid ? 'mp-audio-grid' : 'mp-audio-list'; ?>">
            <?php foreach ($audio_ids as $idx => $aid):
                $src = wp_get_attachment_url($aid);
                if (!$src) continue;
                $att_title = get_the_title($aid);
                // descrição opcional salva como meta: 'audio_descricao_{id}'
                $desc = get_post_meta($post_id, 'audio_descricao_' . $aid, true);
                ?>
                <article class="mp-audio-card" role="group" aria-label="<?= esc_attr('Áudio ' . ($idx + 1)); ?>">
                    <div class="mp-audio-cover">
                        <img src="<?= esc_url($cover); ?>" alt="Capa do artigo">
                        <span class="mp-audio-badge">Áudio</span>
                    </div>
                    <div class="mp-audio-body">
                        <h4 class="mp-audio-title" title="<?= esc_attr($att_title); ?>">
                            <!-- pequeno ícone svg de onda -->
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor"
                                      d="M3 12h2v4H3zm4-6h2v10H7zm4 3h2v11h-2zm4-5h2v16h-2zm4 8h2v8h-2z"/>
                            </svg>
                            <span><?= esc_html($att_title); ?></span>
                        </h4>
                        <?php if (!empty($desc)): ?>
                            <p class="mp-audio-desc"><?= wp_kses_post($desc); ?></p>
                        <?php endif; ?>

                        <audio class="mp-audio" preload="none">
                            <source src="<?= esc_url($src); ?>" type="<?= esc_attr(get_post_mime_type($aid)); ?>">
                            Seu navegador não suporta áudio HTML5.
                        </audio>

                        <div class="mp-audio-footer">
                            <?php if ($download): ?>
                                <a class="mp-audio-download" href="<?= esc_url($src); ?>" download>
                                    <!-- ícone download -->
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="currentColor" d="M5 20h14v-2H5v2zm7-18l-5 5h3v6h4v-6h3l-5-5z"/>
                                    </svg>
                                    Baixar
                                </a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <!-- espaço para extras futuros (duração, etc.) -->
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
});
