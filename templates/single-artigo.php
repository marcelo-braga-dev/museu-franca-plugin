<?php
/**
 * Template customizado para o CPT artigo
 * Carrega o shortcode [visualizar_artigo id="auto"]
 */
if (!defined('ABSPATH')) exit;

get_header();
?>

<style>
    /* ===== Container centralizado e responsivo ===== */
    .mp-article {
        --mp-max: 1100px; /* largura-alvo de leitura */
        --mp-pad: clamp(16px, 2.5vw, 28px);
        width: min(100%, var(--mp-max));
        margin-inline: auto; /* centraliza */
        padding: var(--mp-pad);
        box-sizing: border-box;
    }

    /* Pequenos ajustes mobile */
    @media (max-width: 480px) {
        .mp-article {
            --mp-pad: 16px;
        }
    }
</style>

<?php while (have_posts()) : the_post(); ?>
    <div class="mp-article">
        <?php echo do_shortcode('[visualizar_artigo id="auto"]'); ?>
    </div>
<?php endwhile; ?>

<?php get_footer(); ?>
