<?php
get_header();

$status_labels = [
    'active'   => 'En curso',
    'paused'   => 'En pausa',
    'finished' => 'Terminada',
];

?>
<style>
.campaign-single {
  --bg: #0d0818;
  --card-bg: #181024;
  --text: #f5f3ff;
  --muted: #cbc4e0;
  --accent: #9b5cff;
  background: var(--bg);
  color: var(--text);
}
.campaign-hero {
  position: relative;
  padding: 80px 24px;
  background-size: cover;
  background-position: center;
}
.campaign-hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.6), rgba(0,0,0,0.85));
}
.campaign-hero__content {
  position: relative;
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.campaign-hero__badge {
  align-self: flex-start;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(155, 92, 255, 0.2);
  border: 1px solid rgba(155, 92, 255, 0.35);
  font-size: 13px;
}
.campaign-hero__title {
  margin: 0;
  font-size: clamp(38px, 6vw, 64px);
  letter-spacing: -0.02em;
}
.campaign-hero__summary {
  margin: 0;
  color: var(--muted);
  max-width: 720px;
}
.campaign-hero__meta {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  gap: 16px;
  color: var(--muted);
}
.campaign-actions {
  padding: 32px 24px 12px;
  background: linear-gradient(180deg, rgba(12, 7, 20, 0.95), rgba(10, 6, 16, 0.9));
}
.campaign-actions__grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}
.campaign-action {
  text-decoration: none;
  padding: 16px 18px;
  border-radius: 12px;
  border: 1px solid #261a3a;
  background: var(--card-bg);
  color: var(--text);
  transition: border-color 0.2s ease, transform 0.2s ease;
  display: block;
}
.campaign-action.is-active {
  border-color: rgba(155, 92, 255, 0.45);
  box-shadow: 0 10px 30px rgba(0,0,0,0.35);
}
.campaign-action:hover {
  transform: translateY(-3px);
  border-color: rgba(155, 92, 255, 0.3);
}
.campaign-action__title {
  display: block;
  font-weight: 700;
  margin-bottom: 4px;
}
.campaign-action__desc {
  color: var(--muted);
  font-size: 13px;
}
.campaign-section {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px;
}
.campaign-section__title {
  margin: 0 0 8px;
  font-size: 24px;
}
.campaign-section__empty {
  color: var(--muted);
}
.campaign-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}
.campaign-card--simple {
  background: var(--card-bg);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #261a3a;
}
.campaign-card--simple a {
  color: inherit;
  text-decoration: none;
  display: block;
}
.campaign-card--simple__media {
  width: 100%;
  padding-top: 65%;
  background-size: cover;
  background-position: center;
  background-color: #221733;
}
.campaign-card--simple h4 {
  margin: 12px 12px 14px;
}
.campaign-posts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.campaign-post {
  background: var(--card-bg);
  border: 1px solid #261a3a;
  border-radius: 12px;
  padding: 14px;
}
.campaign-post__title {
  margin: 0 0 6px;
}
.campaign-post__date {
  display: block;
  color: var(--muted);
  font-size: 12px;
  margin-bottom: 8px;
}
.campaign-post__type {
  display: inline-block;
  color: var(--accent);
  font-size: 12px;
  margin-bottom: 4px;
}
.campaign-post__excerpt {
  color: var(--muted);
  font-size: 14px;
  margin: 0;
}
@media (max-width: 640px) {
  .campaign-hero { padding: 48px 16px; }
  .campaign-actions { padding: 24px 16px 8px; }
  .campaign-section { padding: 16px; }
}
</style>
<?php

/**
 * Pequeños helpers de rendering para cada sección
 */
function drak_campaign_section_title( $text ) {
    echo '<h3 class="campaign-section__title">' . esc_html( $text ) . '</h3>';
}

function drak_campaign_render_personajes( $campaign_id ) {
    $query = new WP_Query( [
        'post_type'      => 'personaje',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
        ],
    ] );

    if ( ! $query->have_posts() ) {
        echo '<p class="campaign-section__empty">No hay personajes aún en esta campaña.</p>';
        return;
    }

    echo '<div class="campaign-cards">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
        ?>
        <article class="campaign-card--simple">
            <a href="<?php the_permalink(); ?>">
                <div class="campaign-card--simple__media"<?php echo $thumb ? ' style="background-image:url(' . esc_url( $thumb ) . ');"' : ''; ?>></div>
                <h4><?php the_title(); ?></h4>
            </a>
        </article>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
}

function drak_campaign_render_diary( $campaign_id ) {
    $tax_query = [];
    $diary_term = get_category_by_slug( 'diario' );
    if ( $diary_term && ! is_wp_error( $diary_term ) ) {
        $tax_query[] = [
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => [ 'diario' ],
        ];
    }

    $query = new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
        ],
        'tax_query'      => $tax_query,
    ] );

    if ( ! $query->have_posts() ) {
        echo '<p class="campaign-section__empty">Sin entradas todavía para esta sección.</p>';
        return;
    }

    echo '<div class="campaign-posts">';
    while ( $query->have_posts() ) {
        $query->the_post();
        ?>
        <article class="campaign-post">
            <h4 class="campaign-post__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
            <time class="campaign-post__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
            <p class="campaign-post__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
        </article>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
}

function drak_campaign_get_wiki_post_types() {
    $types = [ 'npc', 'lugar', 'faccion', 'lore-entry', 'post' ];
    return array_values( array_filter( $types, 'post_type_exists' ) );
}

function drak_campaign_get_wiki_categories() {
    return [ 'wiki', 'npc', 'lugares', 'facciones' ];
}

function drak_campaign_render_wiki( $campaign_id ) {
    $post_types = drak_campaign_get_wiki_post_types();

    $tax_query = [];
    $categories = drak_campaign_get_wiki_categories();
    if ( in_array( 'post', $post_types, true ) && ! empty( $categories ) ) {
        $tax_query[] = [
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => $categories,
        ];
    }

    $query = new WP_Query( [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
        ],
        'tax_query'      => $tax_query,
    ] );

    if ( ! $query->have_posts() ) {
        echo '<p class="campaign-section__empty">Sin entradas todavía para la wiki de esta campaña.</p>';
        return;
    }

    echo '<div class="campaign-posts">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $pt_label = get_post_type_object( get_post_type() );
        $type_name = $pt_label ? $pt_label->labels->singular_name : '';
        ?>
        <article class="campaign-post">
            <h4 class="campaign-post__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
            <?php if ( $type_name ) : ?>
                <small class="campaign-post__type"><?php echo esc_html( $type_name ); ?></small>
            <?php endif; ?>
            <time class="campaign-post__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
            <p class="campaign-post__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
        </article>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
}

while ( have_posts() ) :
    the_post();

    $campaign_id  = get_the_ID();
    $section      = get_query_var( 'campaign_section' ) ?: 'portal';
    $cover_id     = get_field( 'campaign_cover_image' );
    $cover_url    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
    $status       = get_field( 'campaign_status' ) ?: 'active';
    $summary      = get_field( 'campaign_summary' );
    $system       = get_field( 'campaign_system' );
    $color        = get_field( 'campaign_color' );
    $hero_style   = $color ? ' style="--campaign-accent: ' . esc_attr( $color ) . ';"' : '';
    $badge        = $status_labels[ $status ] ?? $status;
    $base_url     = trailingslashit( get_permalink() );
    $portal_url   = $base_url;
    $pj_url       = trailingslashit( $base_url . 'pj' );
    $diary_url    = trailingslashit( $base_url . 'diario' );
    $wiki_url     = trailingslashit( $base_url . 'wiki' );
    $gallery_url  = trailingslashit( $base_url . 'galeria' );
    ?>

    <article class="campaign-single"<?php echo $hero_style; ?>>
        <div class="campaign-hero"<?php echo $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : ''; ?>>
            <div class="campaign-hero__overlay"></div>
            <div class="campaign-hero__content">
                <span class="campaign-hero__badge"><?php echo esc_html( $badge ); ?></span>
                <h1 class="campaign-hero__title"><?php the_title(); ?></h1>
                <?php if ( $summary ) : ?>
                    <p class="campaign-hero__summary"><?php echo esc_html( $summary ); ?></p>
                <?php endif; ?>
                <ul class="campaign-hero__meta">
                    <?php if ( $system ) : ?>
                        <li><strong>Sistema:</strong> <?php echo esc_html( $system ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <section class="campaign-actions">
            <div class="campaign-actions__grid">
                <a class="campaign-action<?php echo $section === 'portal' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $portal_url ); ?>">
                    <span class="campaign-action__title">Portal</span>
                    <span class="campaign-action__desc">Visión general de la campaña.</span>
                </a>
                <a class="campaign-action<?php echo $section === 'pj' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pj_url ); ?>">
                    <span class="campaign-action__title">Personajes</span>
                    <span class="campaign-action__desc">Fichas asociadas a esta campaña.</span>
                </a>
                <a class="campaign-action<?php echo $section === 'diario' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $diary_url ); ?>">
                    <span class="campaign-action__title">Diario</span>
                    <span class="campaign-action__desc">Entradas de sesión filtradas por campaña.</span>
                </a>
                <a class="campaign-action<?php echo $section === 'wiki' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $wiki_url ); ?>">
                    <span class="campaign-action__title">Wiki</span>
                    <span class="campaign-action__desc">Lore, NPC y ubicaciones de la campaña.</span>
                </a>
                <a class="campaign-action<?php echo $section === 'galeria' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $gallery_url ); ?>">
                    <span class="campaign-action__title">Galería</span>
                    <span class="campaign-action__desc">Imágenes asociadas a la campaña.</span>
                </a>
            </div>
        </section>

        <section class="campaign-section">
            <?php
            switch ( $section ) {
                case 'pj':
                    drak_campaign_section_title( 'Personajes de la campaña' );
                    drak_campaign_render_personajes( $campaign_id );
                    break;
                case 'diario':
                    drak_campaign_section_title( 'Diario de campaña' );
                    drak_campaign_render_diary( $campaign_id );
                    break;
                case 'wiki':
                    drak_campaign_section_title( 'Wiki de campaña' );
                    drak_campaign_render_wiki( $campaign_id );
                    break;
                case 'galeria':
                    drak_campaign_section_title( 'Galería de campaña' );
                    $gallery_link = add_query_arg( 'campaign', $campaign_id, home_url( '/galeria/' ) );
                    echo '<p class="campaign-section__empty">Puedes navegar la galería global filtrando por campaña: <a href="' . esc_url( $gallery_link ) . '">ver galería</a>.</p>';
                    break;
                case 'portal':
                default:
                    drak_campaign_section_title( 'Portal de campaña' );
                    echo '<p>Explora las secciones de la campaña mediante los accesos rápidos. Aquí podrás consultar el diario, la wiki, los personajes y la galería asociados.</p>';
                    if ( function_exists( 'do_shortcode' ) ) {
                        if ( function_exists( 'drak_importer_user_can_session_import' ) && drak_importer_user_can_session_import() ) {
                            echo do_shortcode( '[drak_import_session campaign="' . esc_attr( $campaign_id ) . '"]' );
                        }
                        echo do_shortcode( '[drak_import_element campaign="' . esc_attr( $campaign_id ) . '"]' );
                    }
                    break;
            }
            ?>
        </section>
    </article>

<?php endwhile; ?>

<?php
get_footer();
