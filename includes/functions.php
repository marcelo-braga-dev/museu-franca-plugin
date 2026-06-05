<?php
if (!defined('ABSPATH')) exit;

function mp_get_url_artigo(int $post_id): string {
    return (string) get_permalink($post_id);
}

function mp_url_minha_conta(): string {
    return esc_url(home_url('/' . MP_SLUG_CONTA . '/'));
}

function mp_url_login(): string {
    return esc_url(home_url('/' . MP_SLUG_LOGIN . '/'));
}

function mp_url_cadastro(): string {
    return esc_url(home_url('/' . MP_SLUG_CADASTRO . '/'));
}

function mp_format_bytes(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $pow   = min($pow, count($units) - 1);
    return number_format($bytes / (1 << (10 * $pow)), $precision, ',', '.') . ' ' . $units[$pow];
}

// YouTube ID extractor - definida uma única vez
if (!function_exists('mp_extract_youtube_id')) {
    function mp_extract_youtube_id(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $patterns = [
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/i',
            '/youtube\.com\/(?:embed|shorts|v)\/([a-zA-Z0-9_-]{11})/i',
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i',
            '/youtube\.com\/watch\?.*?[&?]v=([a-zA-Z0-9_-]{11})/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) return $m[1];
        }
        return '';
    }
}

// Categoria helper
function mp_exibir_categorias(array $cats, int $parent = 0, string $prefixo = '', int $selected = 0): void {
    foreach ($cats as $cat) {
        if ((int) $cat->parent !== $parent) continue;
        $sel = ((int) $cat->term_id === $selected) ? ' selected' : '';
        echo '<option value="' . esc_attr($cat->term_id) . '"' . $sel . '>'
           . esc_html($prefixo . $cat->name) . '</option>';
        mp_exibir_categorias($cats, (int) $cat->term_id, $prefixo . '-- ', $selected);
    }
}
