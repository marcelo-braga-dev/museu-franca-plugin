<?php

function get_url_artigo($post_id) {
   return get_permalink($post_id);
}

function redirect_minha_conta() {
    $url = site_url('/minha-conta');
    return esc_url($url);
}

function redirect_login() {
    $url = site_url('/minha-conta/login');
    return esc_url($url);
}

function redirect_cadastro() {
    $url = site_url('/minha-conta/cadastro');
    return esc_url($url);
}

function mp_increment_artigo_views()
{
    if (is_singular('artigo')) {
        $post_id = get_queried_object_id();
        $views = (int)get_post_meta($post_id, '_views', true);
        update_post_meta($post_id, '_views', $views + 1);
    }
}

add_action('wp', 'mp_increment_artigo_views');

/**
 * ===== Último acesso (salvar no login) =====
 * Armazena o timestamp do último login em user_meta 'last_login'
 */
add_action('wp_login', function ($user_login, $user) {
    update_user_meta($user->ID, 'last_login', current_time('timestamp'));
}, 10, 2);

add_action('before_delete_post', function ($post_id) {
    $post = get_post($post_id);

    // Apenas para o CPT "artigo"
    if (!$post || $post->post_type !== 'artigo') {
        return;
    }

    /* ==============================
     * 1) Anexos filhos (post_parent)
     * ============================== */
    $attachments = get_children([
        'post_parent' => $post_id,
        'post_type'   => 'attachment',
        'numberposts' => -1,
        'post_status' => 'any',
    ]);

    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true); // true = remove do disco
        }
    }

    /* ================================================
     * 2) Anexos guardados em metadados personalizados
     * ================================================ */
    // Adicione aqui todos os campos que guardam IDs de anexos
    $campos_personalizados = ['capa', 'galeria', 'pdfs', 'audios'];

    foreach ($campos_personalizados as $campo) {
        $ids = get_post_meta($post_id, $campo, true);
        if (!$ids) continue;

        // Se não for array, transforma em array
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            if (get_post_type($id) === 'attachment') {
                wp_delete_attachment((int) $id, true);
            }
        }
    }
}, 10, 1);


