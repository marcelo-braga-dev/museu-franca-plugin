<?php
if (!defined('ABSPATH')) exit;

/* =========================================
 * Contador de visualizações (via JavaScript)
 *
 * A visita é registrada pelo navegador depois que a página carrega,
 * o que exclui a maioria dos robôs (não executam JS) e funciona
 * mesmo com a página servida pelo cache do LiteSpeed.
 * Filtros: user-agent de robôs, administradores/editores e
 * 1 visita por IP + artigo a cada 24h.
 * ======================================= */

if (!function_exists('mp_is_bot_user_agent')) {
    function mp_is_bot_user_agent(string $ua): bool {
        if ($ua === '') return true;
        return (bool) preg_match(
            '~bot|crawl|spider|slurp|scrap|fetch|curl|wget|python|java/|go-http|httpclient|okhttp|axios|'
            . 'headless|phantom|puppeteer|playwright|selenium|lighthouse|pagespeed|preview|monitor|'
            . 'facebookexternalhit|meta-webindexer|bytespider|petalbot|yandex|baidu|semrush|ahrefs|'
            . 'mj12|dotbot|gptbot|claude|anthropic|perplexity|ccbot|turnitin|bingpreview~i',
            $ua
        );
    }
}

if (!function_exists('mp_ajax_registrar_visualizacao')) {
    function mp_ajax_registrar_visualizacao() {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || get_post_type($post_id) !== 'artigo' || get_post_status($post_id) !== 'publish') {
            wp_send_json_error(null, 400);
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        if (mp_is_bot_user_agent($ua) || current_user_can('edit_others_posts')) {
            wp_send_json_success(['counted' => false]);
        }

        // 1 visita por IP + artigo a cada 24h
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $key = 'mp_vw_' . md5($ip . '|' . $post_id);
        if (get_transient($key)) {
            wp_send_json_success(['counted' => false]);
        }
        set_transient($key, 1, DAY_IN_SECONDS);

        global $wpdb;
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
            $post_id, MP_META_VIEWS
        ));
        if (!$updated) {
            add_post_meta($post_id, MP_META_VIEWS, 1, true);
        }
        wp_cache_delete($post_id, 'post_meta');

        wp_send_json_success(['counted' => true]);
    }
}
add_action('wp_ajax_mp_registrar_visualizacao', 'mp_ajax_registrar_visualizacao');
add_action('wp_ajax_nopriv_mp_registrar_visualizacao', 'mp_ajax_registrar_visualizacao');

// Script no rodapé dos artigos. Sem nonce de propósito: a página fica em cache
// por dias e o nonce expiraria; a validação é feita no servidor.
add_action('wp_footer', function () {
    if (!is_singular('artigo')) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;
    ?>
    <script>
    (function () {
        if (navigator.webdriver) return;
        var id = <?php echo (int) $post_id; ?>, k = 'mp_vw_' + id, agora = Date.now();
        try { if (agora - (+localStorage.getItem(k) || 0) < 864e5) return; } catch (e) {}
        function enviar() {
            try { localStorage.setItem(k, String(agora)); } catch (e) {}
            var d = new FormData();
            d.append('action', 'mp_registrar_visualizacao');
            d.append('post_id', id);
            fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, { method: 'POST', body: d, credentials: 'same-origin', keepalive: true });
        }
        // Só conta quem permanece na página visível por alguns segundos
        var t = null;
        function agendar() { if (!document.hidden && !t) t = setTimeout(enviar, 3000); }
        document.addEventListener('visibilitychange', function () {
            if (document.hidden && t) { clearTimeout(t); t = null; } else { agendar(); }
        });
        agendar();
    })();
    </script>
    <?php
}, 99);
