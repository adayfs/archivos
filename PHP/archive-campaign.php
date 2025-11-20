<?php
get_header();

$status_labels = [
    'active'   => 'En curso',
    'paused'   => 'En pausa',
    'finished' => 'Terminada',
];
?>

<div class="campaign-archive">
    <header class="campaign-archive__intro">
        <h1>Campañas</h1>
        <p>Selecciona una campaña para ver su portal.</p>
    </header>

    <?php if ( have_posts() ) : ?>
        <div class="campaign-grid">
            <?php while ( have_posts() ) : the_post();
                $cover_id   = get_field( 'campaign_cover_image' );
                $cover_url  = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
                $summary    = get_field( 'campaign_summary' );
                $status     = get_field( 'campaign_status' ) ?: 'active';
                $color      = get_field( 'campaign_color' );
                $badge      = $status_labels[ $status ] ?? $status;
                $style_attr = $color ? ' style="--campaign-accent: ' . esc_attr( $color ) . ';"' : '';
                ?>
                <article <?php post_class( 'campaign-card' ); ?><?php echo $style_attr; ?>>
                    <a class="campaign-card__link" href="<?php the_permalink(); ?>">
                        <div class="campaign-card__media"<?php echo $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : ''; ?>></div>
                        <div class="campaign-card__body">
                            <span class="campaign-card__status"><?php echo esc_html( $badge ); ?></span>
                            <h2 class="campaign-card__title"><?php the_title(); ?></h2>
                            <?php if ( $summary ) : ?>
                                <p class="campaign-card__summary"><?php echo esc_html( $summary ); ?></p>
                            <?php endif; ?>
                            <span class="campaign-card__cta">Entrar a la campaña →</span>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No hay campañas creadas todavía.</p>
    <?php endif; ?>
</div>

<?php
get_footer();
