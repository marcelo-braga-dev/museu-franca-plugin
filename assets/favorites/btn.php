<?php
/**
 * Favoritos de Artigos (por usuário logado)
 * - Ícone de favoritar: mp_favorito_botao($post_id, ['variant' => 'pill'|'icon'])
 * - AJAX sem jQuery (admin-ajax)
 * - Shortcode extra (opcional): [grid_favoritos]  → listar favoritos do usuário
 *
 * Requisitos:
 * - Font Awesome 6 (CDN incluído abaixo)
 * - Post type alvo: 'artigo'
 */

/* =========================================
 * Helpers
 * ======================================= */
if (!function_exists('mp_get_user_favorites')) {
    function mp_get_user_favorites($user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        $ids = get_user_meta($user_id, 'mp_favoritos', true);
        if (!is_array($ids)) $ids = [];
        // Mantém apenas IDs válidos de artigos publicados
        $ids = array_values(array_filter(array_map('intval', $ids), function ($id) {
            return get_post_type($id) === 'artigo' && get_post_status($id) === 'publish';
        }));
        return $ids;
    }
}

if (!function_exists('mp_is_favorited')) {
    function mp_is_favorited($post_id, $user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        return in_array((int)$post_id, mp_get_user_favorites($user_id), true);
    }
}

if (!function_exists('mp_toggle_favorite')) {
    function mp_toggle_favorite($post_id, $user_id = 0) {
        $user_id   = $user_id ?: get_current_user_id();
        $favoritos = mp_get_user_favorites($user_id);
        $post_id   = (int)$post_id;

        if (in_array($post_id, $favoritos, true)) {
            // Remover
            $favoritos = array_values(array_diff($favoritos, [$post_id]));
            update_user_meta($user_id, 'mp_favoritos', $favoritos);
            return ['status' => 'removed', 'count' => count($favoritos)];
        } else {
            // Adicionar
            $favoritos[] = $post_id;
            $favoritos   = array_values(array_unique(array_map('intval', $favoritos)));
            update_user_meta($user_id, 'mp_favoritos', $favoritos);
            return ['status' => 'added', 'count' => count($favoritos)];
        }
    }
}

/* =========================================
 * Botão de favorito
 *  - $options['variant']: 'pill' (padrão) ou 'icon'
 * ======================================= */
if (!function_exists('mp_favorito_botao')) {
    function mp_favorito_botao($post_id = null, array $options = []) {
        if (!$post_id) $post_id = get_the_ID();
        if (!$post_id) return '';

        $variant = isset($options['variant']) ? $options['variant'] : 'pill';

        // Visitante → link para login
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink($post_id));
            return '<a class="mp-fav-button mp-fav-guest mp-fav--' . esc_attr($variant) . '" href="' . esc_url($login_url) . '" title="Entre para favoritar" aria-label="Entre para favoritar" rel="nofollow">
                        <i class="fa-regular fa-heart" aria-hidden="true"></i>' . ($variant === 'pill' ? '<span> Favoritar</span>' : '') . '
                    </a>';
        }

        $nonce   = wp_create_nonce('mp_toggle_fav_' . $post_id);
        $isFav   = mp_is_favorited($post_id);
        // FA6: regular (contorno) / solid (preenchido)
        $icon    = $isFav ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        $title   = $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
        $pressed = $isFav ? 'true' : 'false';

        return '<button class="mp-fav-button mp-fav--' . esc_attr($variant) . ($isFav ? ' is-fav' : '') . '"
                        type="button"
                        data-postid="' . (int)$post_id . '"
                        data-nonce="' . esc_attr($nonce) . '"
                        aria-pressed="' . esc_attr($pressed) . '"
                        title="' . esc_attr($title) . '"
                        aria-label="' . esc_attr($title) . '">
                    <i class="' . esc_attr($icon) . '" aria-hidden="true"></i>' . ($variant === 'pill' ? '<span>' . ($isFav ? ' Favorito' : ' Favoritar') . '</span>' : '') . '
                </button>';
    }
}

/* =========================================
 * AJAX
 * ======================================= */
add_action('wp_ajax_mp_toggle_favorite', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Você precisa estar logado.'], 401);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $nonce   = isset($_POST['nonce'])   ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!$post_id || !wp_verify_nonce($nonce, 'mp_toggle_fav_' . $post_id)) {
        wp_send_json_error(['message' => 'Requisição inválida.'], 400);
    }

    if (get_post_type($post_id) !== 'artigo' || get_post_status($post_id) !== 'publish') {
        wp_send_json_error(['message' => 'Artigo inválido.'], 404);
    }

    $result = mp_toggle_favorite($post_id);
    $isFav  = $result['status'] === 'added';

    wp_send_json_success([
        'status'  => $result['status'],
        'count'   => $result['count'],
        'icon'    => $isFav ? 'fa-solid fa-heart' : 'fa-regular fa-heart',
        'title'   => $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos',
        'pressed' => $isFav ? 'true' : 'false',
        'label'   => $isFav ? ' Favorito' : ' Favoritar',
    ]);
});

add_action('wp_ajax_nopriv_mp_toggle_favorite', function () {
    wp_send_json_error(['message' => 'Entre para favoritar.'], 401);
});

/* =========================================
 * Assets (JS + CSS) — sem jQuery
 * ======================================= */
add_action('wp_enqueue_scripts', function () {
    // Carrega FA caso o tema não carregue
    wp_enqueue_style(
        'mp-fa',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        [],
        '6.5.2'
    );

    // Script base para anexar JS inline
    wp_enqueue_script('mp-favoritos', includes_url('js/wp-util.min.js'), [], null, true);

    wp_localize_script('mp-favoritos', 'MPFAV', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    // ==== JS inline (delegação + bloqueio de navegação do card) ====
    $js = <<<JS
(function(){
  function syncButtons(postId, data){
    var list = document.querySelectorAll('.mp-fav-button[data-postid="'+postId+'"]');
    list.forEach(function(btn){
      btn.classList.toggle('is-fav', data.status === 'added');
      btn.setAttribute('aria-pressed', data.pressed);
      btn.setAttribute('title', data.title);
      btn.setAttribute('aria-label', data.title);
      var i = btn.querySelector('i'); if(i){ i.className = data.icon; }
      var s = btn.querySelector('span'); if(s){ s.textContent = data.label || ''; }
    });
  }

  function handleClick(e){
    var btn = e.target.closest('.mp-fav-button');
    if(!btn) return;

    // Convidado (link) → permite navegar
    if(btn.classList.contains('mp-fav-guest') || btn.tagName === 'A'){ return; }

    // Evita que o clique no favorito acione o link do card
    e.preventDefault();
    e.stopPropagation();
    if(typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    var postId = btn.getAttribute('data-postid');
    var nonce  = btn.getAttribute('data-nonce');
    if(!postId || !nonce) return;

    btn.disabled = true;
    btn.classList.add('is-loading');

    var form = new FormData();
    form.append('action','mp_toggle_favorite');
    form.append('post_id', postId);
    form.append('nonce', nonce);

    fetch(MPFAV.ajax_url, {
      method:'POST',
      credentials:'same-origin',
      body: form,
      headers: { 'X-Requested-With':'XMLHttpRequest' }
    })
    .then(function(r){ return r.json().catch(function(){ throw new Error('Resposta inválida do servidor.'); }); })
    .then(function(res){
      if(!res || !res.success){
        var msg = (res && res.data && res.data.message) ? res.data.message : 'Erro ao favoritar.';
        throw new Error(msg);
      }
      syncButtons(postId, res.data);
    })
    .catch(function(err){
      alert(err && err.message ? err.message : 'Não foi possível atualizar o favorito.');
    })
    .finally(function(){
      btn.disabled = false;
      btn.classList.remove('is-loading');
    });
  }

  // Usa captura para interceptar antes de <a> pais
  document.addEventListener('click', handleClick, true);
})();
JS;
    wp_add_inline_script('mp-favoritos', $js);

    // ==== CSS inline (estilização) ====
    $css = <<<CSS
/* ===========================
   Variáveis (customize aqui)
   =========================== */
:root{
  --mp-fav-red: #dc2626;     /* vermelho do coração ATIVO */
  --mp-fav-red-dark: #b91c1c;
  --mp-fav-gray: #6b7280;    /* cinza do coração inativo */
  --mp-fav-bg: #ffffff;      /* fundo padrão */
  --mp-fav-border: #e5e7eb;  /* borda padrão */
  --mp-fav-text: #374151;    /* texto padrão (pill) */
  --mp-fav-hover-bg: #fff5f5;
  --mp-fav-active-bg: #fee2e2;
  --mp-fav-shadow: rgba(220,38,38,0.18);
}

/* ===== Botão padrão (pill) ===== */
.mp-fav-button{
  all: unset;
  display:inline-flex !important;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:10px 16px;
  border:2px solid var(--mp-fav-border);
  border-radius:9999px;
  background:var(--mp-fav-bg);
  cursor:pointer;
  font-weight:700;
  font-size:15px;
  color:var(--mp-fav-text);
  line-height:1;
  transition: transform .20s ease, box-shadow .25s ease, border-color .2s ease, background .2s ease, color .2s ease, opacity .2s ease;
  -webkit-tap-highlight-color: transparent;
}
.mp-fav-button i{
  font-size:20px;
  color:var(--mp-fav-gray) !important;          /* INATIVO: cinza */
  transition: transform .20s ease, color .2s ease;
}
.mp-fav-button:hover{
  border-color:var(--mp-fav-red);
  background:var(--mp-fav-hover-bg);
  box-shadow:0 6px 16px var(--mp-fav-shadow);
  transform:translateY(-2px);
}
.mp-fav-button:active{ transform:translateY(0); }
.mp-fav-button:focus-visible{
  outline: 3px solid color-mix(in srgb, var(--mp-fav-red) 40%, transparent);
  outline-offset: 2px;
}
.mp-fav-button.is-fav{
  border-color:var(--mp-fav-red);
  background:var(--mp-fav-active-bg);
  color:var(--mp-fav-red);
}
.mp-fav-button.is-fav i{
  color:var(--mp-fav-red) !important;           /* ATIVO: vermelho */
}
.mp-fav-button.is-loading,
.mp-fav-button:disabled{ opacity:.7; cursor:progress; pointer-events:none; }
.mp-fav-guest{ text-decoration:none; }

/* ===== Variante "icon" (só ícone) ===== */
.mp-fav--icon{
  width:40px;
  height:40px;
  padding:0;
  border-radius:9999px;     
  background:#ffffff !important;                /* FUNDO SEMPRE BRANCO */
  border:1px solid #e5e7eb;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
}
.mp-fav--icon:hover{
  background:#ffffff !important;                /* mantém branco no hover */
  border-color:var(--mp-fav-red);
  box-shadow:0 10px 22px rgba(220,38,38,.15);
  transform:translateY(-2px);
}
.mp-fav--icon.is-fav{
  background:#ffffff !important;                /* permanece branco ativo */
  border-color:var(--mp-fav-red);
}
.mp-fav--icon i{
  font-size:18px;
}

/* Responsivo */
@media (max-width: 480px){
  .mp-fav-button{ padding:8px 12px; font-size:14px; }
  .mp-fav-button i{ font-size:18px; }
  .mp-fav--icon{ width:38px; height:38px; }
}

/* Utilitário opcional */
.mp-flex-between{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
CSS;

    // Handle base para anexar CSS inline
    wp_enqueue_style('wp-block-library');
    wp_add_inline_style('wp-block-library', $css);
});
