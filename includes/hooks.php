<?php
if (!defined('ABSPATH')) exit;

// ===== View counter (atômico via $wpdb) =====
add_action('wp', function () {
    if (!is_singular('artigo')) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;

    global $wpdb;
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
        $post_id, MP_META_VIEWS
    ));
    if (!$updated) {
        add_post_meta($post_id, MP_META_VIEWS, 1, true);
    }
});

// ===== Last login =====
add_action('wp_login', function ($user_login, $user) {
    update_user_meta($user->ID, MP_META_LAST_LOGIN, time());
}, 10, 2);

// ===== Admin bar =====
add_filter('show_admin_bar', function ($show) {
    return current_user_can('manage_options') ? $show : false;
});

// ===== Limpeza de anexos ao excluir artigo =====
add_action('before_delete_post', function ($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'artigo') return;

    // 1) Filhos diretos (post_parent)
    $attachments = get_children([
        'post_parent' => $post_id,
        'post_type'   => 'attachment',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ]);
    foreach ($attachments as $att_id) {
        wp_delete_attachment($att_id, true);
    }

    // 2) Metas customizadas (meta keys CORRETAS do plugin)
    foreach ([MP_META_IMG, MP_META_PDF, MP_META_AUDIO] as $meta_key) {
        $ids = get_post_meta($post_id, $meta_key);
        foreach ((array) $ids as $id) {
            $id = is_array($id) ? (int) reset($id) : (int) $id;
            if ($id > 0 && get_post_type($id) === 'attachment') {
                wp_delete_attachment($id, true);
            }
        }
    }
});

// ===== Tipos MIME extras para áudio =====
add_filter('upload_mimes', function (array $m): array {
    $m['aac']  = 'audio/aac';
    $m['opus'] = 'audio/ogg; codecs=opus';
    $m['flac'] = 'audio/flac';
    $m['m4a']  = 'audio/mp4';
    return $m;
});

// ===== Invalida caches de taxonomy ao salvar artigo =====
add_action('save_post_artigo', function () {
    delete_transient('mp_colecao_counts_artigo');
    delete_transient('mp_cat_counts_artigo');
});
add_action('created_colecao', function () { delete_transient('mp_colecao_counts_artigo'); });
add_action('edited_colecao',  function () { delete_transient('mp_colecao_counts_artigo'); });
add_action('delete_colecao',  function () { delete_transient('mp_colecao_counts_artigo'); });
