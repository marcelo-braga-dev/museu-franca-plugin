<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode [lista_usuarios]
 * Exemplo:
 * [lista_usuarios per_page="24" post_type="artigo" orderby="display_name" order="ASC" show_role="yes" roles_mode="primary" roles_in="author,editor"]
 */
add_shortcode('lista_usuarios', function ($atts = []) {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    $a = shortcode_atts([
        'per_page'   => 24,
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'post_type'  => 'artigo',
        'show_email' => 'yes',
        'show_role'  => 'yes',
        'roles_mode' => 'primary',
        'roles_in'   => '',
    ], $atts, 'lista_usuarios');

    $paged    = max(1, (int)get_query_var('paged') ?: (int)get_query_var('page'));
    $per_page = (int)$a['per_page'];
    $offset   = ($paged - 1) * $per_page;

    $args = [
        'number'  => $per_page,
        'offset'  => $offset,
        'orderby' => $a['orderby'],
        'order'   => $a['order'],
        // No 'fields' restriction — we need full WP_User objects for roles
    ];

    if (!empty($a['roles_in'])) {
        $roles = array_filter(array_map('trim', explode(',', $a['roles_in'])));
        if ($roles) $args['role__in'] = $roles;
    }

    $user_query = new WP_User_Query($args);
    $users      = $user_query->get_results();
    $total      = (int)$user_query->get_total();

    $roles_reg = function_exists('wp_roles') ? wp_roles()->roles : [];

    ob_start();
    ?>
    <style>
        .lu-wrap{max-width:1100px;margin:0 auto;padding:8px 12px 24px}
        .lu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
        .lu-card{background:#fff;border:1px solid #e6e6e6;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:16px;display:flex;flex-direction:column;align-items:center;text-align:center}
        .lu-avatar{margin-bottom:12px}
        .lu-avatar img{width:80px;height:80px;border-radius:50%;object-fit:cover;display:block}
        .lu-info{width:100%;padding:8px;text-align:left}
        .lu-name{text-align:center;font-weight:700;font-size:1.05rem;margin:0 0 6px;line-height:1.25}
        .lu-email{text-align:center;color:#555;font-size:.92rem;word-break:break-word;margin-bottom:8px}
        .lu-line{font-size:.92rem;display:flex;align-items:start;gap:6px;margin-bottom:5px}
        .lu-pagination{margin-top:22px;display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
        .lu-pagination a,.lu-pagination span{padding:8px 12px;border-radius:8px;border:1px solid #e6e6e6;background:#fff;text-decoration:none;color:#333;font-size:.95rem}
        .lu-pagination .current{background:#111827;color:#fff;border-color:#111827}
        @media(max-width:480px){.lu-avatar img{width:64px;height:64px}}
    </style>
    <div class="lu-wrap">
        <?php if (!$users): ?>
            <p>Nenhum usuário encontrado.</p>
        <?php else: ?>
            <div class="lu-grid">
                <?php foreach ($users as $u):
                    $uid    = $u->ID;
                    $avatar = get_avatar_url($uid, ['size' => 96]);

                    $qtd_artigos = function_exists('count_user_posts')
                        ? (int)count_user_posts($uid, $a['post_type'], true)
                        : 0;

                    $last_login_ts = get_user_meta($uid, 'last_login', true);
                    $last_login    = $last_login_ts
                        ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int)$last_login_ts)
                        : '';

                    // Roles already available on WP_User object — no extra DB call
                    $role_slugs  = (array)$u->roles;
                    $role_labels = array_map(function ($slug) use ($roles_reg) {
                        return isset($roles_reg[$slug]['name']) ? $roles_reg[$slug]['name'] : ucfirst($slug);
                    }, $role_slugs);
                    if ($a['roles_mode'] === 'primary') $role_labels = array_slice($role_labels, 0, 1);
                    ?>
                    <div class="lu-card">
                        <div class="lu-avatar">
                            <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($u->display_name); ?>">
                        </div>
                        <div class="lu-info">
                            <div class="lu-name"><?php echo esc_html($u->display_name ?: $u->user_login); ?></div>
                            <?php if ($a['show_email'] === 'yes'): ?>
                                <div class="lu-email"><?php echo esc_html($u->user_email); ?></div>
                            <?php endif; ?>
                            <div class="lu-line">
                                <div>📝</div>
                                <strong><?php echo number_format_i18n($qtd_artigos); ?></strong> publicações
                            </div>
                            <?php if ($last_login): ?>
                                <div class="lu-line">
                                    <div>⏱️</div>
                                    <span>Último acesso:<br><?php echo esc_html($last_login); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($a['show_role'] === 'yes' && !empty($role_labels)): ?>
                                <div class="lu-line">
                                    <div>👤</div>
                                    <span>Função: <?php echo esc_html(implode(', ', $role_labels)); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $total_pages = max(1, (int)ceil($total / $per_page));
            if ($total_pages > 1) {
                $big   = 999999999;
                $base  = str_replace($big, '%#%', esc_url(get_pagenum_link($big)));
                $links = paginate_links(['base' => $base, 'format' => '?paged=%#%', 'current' => $paged, 'total' => $total_pages, 'prev_text' => '« Anterior', 'next_text' => 'Próxima »', 'type' => 'array']);
                if (!empty($links)) {
                    echo '<div class="lu-pagination">';
                    foreach ($links as $l) echo $l;
                    echo '</div>';
                }
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
