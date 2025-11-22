<?php
get_header();

if ( ! function_exists( 'drak_campaign_hex_to_rgba' ) ) {
    // Helper para convertir el color de campaña a rgba (fallback por si el helper no se carga).
    function drak_campaign_hex_to_rgba( $hex, $alpha = 1 ) {
        $hex = isset( $hex ) ? ltrim( (string) $hex, '#' ) : '';
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if ( strlen( $hex ) !== 6 ) {
            return '';
        }
        $int = hexdec( $hex );
        $r = ( $int >> 16 ) & 255;
        $g = ( $int >> 8 ) & 255;
        $b = $int & 255;
        return sprintf( 'rgba(%d,%d,%d,%.2f)', $r, $g, $b, max( 0, min( 1, $alpha ) ) );
    }
}

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
  --accent-dark: rgba(155, 92, 255, 0.25);
  background: var(--bg);
  color: var(--text);
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}
.campaign-hero {
  position: relative;
  padding: 154px 53px;
  background-size: cover;
  background-position: center;
  margin-top: 16px;
  border-radius: 12px;
  overflow: hidden;
}
.campaign-hero__overlay {
  position: absolute;
  inset: 0;
  background: transparent;
}
.campaign-hero__content {
  position: relative;
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: center;
  text-align: center;
  padding-bottom: 80px;
}
.campaign-hero-logo {
  position: absolute;
  left: 50%;
  bottom: -140px;
  transform: translateX(-50%);
  max-width: 270px;
}
.campaign-hero-logo-img {
  display: block;
  width: 100%;
  height: auto;
}
.campaign-hero__inner {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 53px;
  position: relative;
}
.campaign-hero__title {
  margin: 0;
  font-size: clamp(38px, 6vw, 64px);
  letter-spacing: -0.02em;
  color: var(--accent, #9b5cff);
}
.single-campaign .site-content .ast-container {
  margin: 0 auto;
}
.campaign-hero__home-link {
  position: absolute;
  bottom: -140px;
  left: -92px;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: var(--text);
  text-decoration: none;
  display: inline-flex;
  gap: 6px;
  align-items: center;
  font-size: 10px;
}
.campaign-hero__home-link:hover {
  border-color: var(--accent, #9b5cff);
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
.single-campaign .site-content,
.single-campaign .site-content > .ast-container,
.single-campaign #primary,
.single-campaign #main {
  max-width: 100% !important;
  width: 100% !important;
  padding: 0;
  margin: 0 auto;
}
.campaign-actions {
  padding: 32px 0 12px;
  background: linear-gradient(180deg, var(--accent-dark, rgba(12, 7, 20, 0.95)), rgba(10, 6, 16, 0.9));
}
.campaign-actions__grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}
.campaign-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
  background: linear-gradient(180deg, rgba(0,0,0,0.55), rgba(0,0,0,0.85));
  border-radius: 12px;
  border: 1px solid var(--accent, #261a3a);
}
.campaign-main {
  width: 100%;
  padding-top: 16px;
  padding-bottom: 32px;
}
.campaign-main-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  box-sizing: border-box;
}
.campaign-section__title {
  margin: 0 0 8px;
  font-size: 24px;
  color: var(--accent, #9b5cff);
  text-align: center;
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
  border: 1px solid var(--accent, #261a3a);
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
  color: var(--accent, #9b5cff);
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
.wiki-list {
  max-width: 1100px;
  margin: 16px auto 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.wiki-card {
  background: linear-gradient(180deg, rgba(0,0,0,0.35), var(--accent-dark, rgba(0,0,0,0.5)));
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 14px;
  padding: 16px 18px;
  color: var(--text);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.wiki-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.35);
  border-color: var(--accent, #9b5cff);
}
.wiki-card__title {
  margin: 0;
  font-size: 20px;
  text-align: center;
}
.wiki-card__title a {
  color: var(--accent, #9b5cff);
  text-decoration: none;
}
.wiki-card__title a:hover {
  text-decoration: underline;
}
.wiki-card__excerpt {
  margin: 0;
  font-size: 14px;
  color: var(--text);
  line-height: 1.5;
}
.wiki-card__more {
  margin-top: auto;
  text-align: right;
}
.wiki-archive {
  margin-top: 16px;
}
.wiki-archive-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 18px;
}
.wiki-archive-layout--no-search {
  grid-template-columns: 1fr;
}
.wiki-archive__list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.wiki-archive__sidebar {
  background: linear-gradient(180deg, rgba(0,0,0,0.4), rgba(0,0,0,0.75));
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 14px;
  padding: 16px;
  color: var(--text);
}
.wiki-archive__sidebar h4 {
  margin: 0 0 10px;
  text-align: center;
  color: var(--accent, #9b5cff);
}
.wiki-search {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.wiki-search input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  background: #161224;
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 10px;
  color: var(--text);
  box-sizing: border-box;
}
.wiki-search button {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: var(--text);
  cursor: pointer;
}
.wiki-archive__pagination {
  grid-column: 1 / -1;
  text-align: center;
  margin-top: 12px;
}
.wiki-archive__pagination .page-numbers {
  display: inline-block;
  margin: 0 4px;
  padding: 6px 10px;
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 8px;
  text-decoration: none;
  color: var(--text);
}
.wiki-archive__pagination .page-numbers.current {
  background: var(--accent, #9b5cff);
  color: #0d0818;
}
.wiki-archive__pagination .page-numbers:hover {
  text-decoration: none;
  filter: brightness(1.05);
}
.drak-wiki-search__suggestions {
  margin-top: 8px;
  background: linear-gradient(180deg, rgba(0,0,0,0.4), rgba(0,0,0,0.7));
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 12px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.drak-wiki-search__item {
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(22, 18, 36, 0.8);
  border: 1px solid var(--accent-dark, rgba(155, 92, 255, 0.35));
  color: var(--text);
  text-decoration: none;
}
.drak-wiki-search__item strong {
  display: block;
  color: var(--accent, #9b5cff);
  margin-bottom: 4px;
}
.drak-wiki-search__item span {
  display: block;
  color: var(--muted);
  font-size: 13px;
}
.drak-wiki-search__empty {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
@media (max-width: 960px) {
  .wiki-archive-layout {
    grid-template-columns: 1fr;
  }
  .wiki-archive-search {
    order: 1;
  }
  .wiki-archive-cards {
    order: 2;
  }
}
.ast-mobile-header-wrap {
  background: #000 !important;
}
.ast-primary-header-bar.main-header-bar,
.ast-header-break-point #masthead .ast-primary-header-bar.main-header-bar {
  background-color: #000 !important;
}
.ast-mobile-header-wrap .ast-mobile-menu-buttons a,
.ast-mobile-header-wrap .ast-button-wrap .menu-toggle,
.ast-mobile-header-wrap .ast-button-wrap .menu-toggle .icon-menu {
  color: #fff !important;
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
        $slug  = get_post_field( 'post_name', get_the_ID() );
        $sheet_url = home_url( '/hoja-personaje/' . $slug . '/' );
        ?>
        <article class="campaign-card--simple">
            <a href="<?php echo esc_url( $sheet_url ); ?>">
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
    $search = isset( $_GET['wiki_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wiki_search'] ) ) : '';
    $paged  = max( 1, (int) get_query_var( 'paged' ) ?: (int) ( $_GET['paged'] ?? 1 ) );

    $query = new WP_Query( [
        'post_type'      => 'diario',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'paged'          => $paged,
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
        ],
        's'              => $search,
    ] );

    if ( ! $query->have_posts() ) {
        echo '<p class="campaign-section__empty">Sin entradas todavía para esta sección.</p>';
        return;
    }

    $base_link = add_query_arg(
        array_filter(
            [
                'wiki_search' => $search ?: null,
            ]
        ),
        trailingslashit( get_permalink( $campaign_id ) . 'diario' )
    );

    echo '<main class="campaign-main">';
    echo '<div class="campaign-main-inner">';
    echo '<div class="wiki-archive">';
    echo '<div class="wiki-archive-layout wiki-archive-layout--no-search">';

    echo '<div class="wiki-archive-cards">';
    echo '<div class="wiki-archive__list">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $excerpt = get_the_excerpt();
        if ( ! $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' );
        } else {
            $excerpt = wp_trim_words( $excerpt, 40, '…' );
        }
        echo '<article class="wiki-card">';
        echo '<h3 class="wiki-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        if ( $excerpt ) {
            echo '<p class="wiki-card__excerpt">' . esc_html( $excerpt ) . '</p>';
        }
        echo '<div class="wiki-card__more"><a href="' . esc_url( get_permalink() ) . '"></a></div>';
        echo '</article>';
    }
    echo '</div>'; // list
    echo '</div>'; // cards


    echo '</div>'; // layout
    echo '</div>'; // archive

    $paginate_links = paginate_links( [
        'total'   => (int) $query->max_num_pages,
        'current' => $paged,
        'base'    => add_query_arg( 'paged', '%#%', $base_link ),
        'format'  => '',
    ] );
    if ( $paginate_links ) {
        echo '<div class="wiki-archive__pagination">' . wp_kses_post( $paginate_links ) . '</div>';
    }
    echo '</div>'; // inner
    echo '</main>';
    wp_reset_postdata();
}

function drak_campaign_get_wiki_post_types() {
    $types = [ 'npc', 'lugar', 'faccion', 'lore-entry', 'personaje_wiki', 'post' ];
    return array_values( array_filter( $types, 'post_type_exists' ) );
}

function drak_campaign_get_wiki_categories() {
    return [ 'wiki', 'npc', 'lugares', 'facciones' ];
}

function drak_campaign_render_wiki_hub( $campaign_id, $base_url ) {
    $sections = [
        'npc'            => [ 'title' => 'NPC' ],
        'lugar'          => [ 'title' => 'Lugares' ],
        'faccion'        => [ 'title' => 'Facciones' ],
        'personaje_wiki' => [ 'title' => 'Personajes' ],
    ];

    echo '<div class="wiki-list">';
    foreach ( $sections as $key => $data ) {
        $url = add_query_arg(
            [
                'wiki_section' => $key,
                'wiki_view'    => 'archive',
            ],
            $base_url
        );
        echo '<article class="wiki-card">';
        echo '<h3 class="wiki-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( $data['title'] ) . '</a></h3>';
        echo '</article>';
    }
    echo '</div>';
}

function drak_campaign_render_wiki_section( $campaign_id, $section ) {
    $post_types = drak_campaign_get_wiki_post_types();
    if ( ! in_array( $section, $post_types, true ) ) {
        echo '<p class="campaign-section__empty">Sección no disponible.</p>';
        return;
    }

    $wiki_view  = isset( $_GET['wiki_view'] ) ? sanitize_key( wp_unslash( $_GET['wiki_view'] ) ) : '';
    if ( ! $wiki_view ) {
        $wiki_view = 'archive';
    }
    $search     = isset( $_GET['wiki_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wiki_search'] ) ) : '';
    $paged      = max( 1, (int) get_query_var( 'paged' ) ?: (int) ( $_GET['paged'] ?? 1 ) );
    $map        = [
        'npc'            => 'NPC',
        'lugar'          => 'Lugares',
        'faccion'        => 'Facciones',
        'personaje_wiki' => 'Personajes',
    ];
    $title      = $map[ $section ] ?? ucfirst( $section );
    $clean_base = remove_query_arg( [ 'wiki_view', 'wiki_search', 'paged' ] );
    $archive_url = add_query_arg(
        [
            'wiki_section' => $section,
            'wiki_view'    => 'archive',
        ],
        $clean_base
    );
    echo '<h3 class="campaign-section__title" style="text-align:center;">';
    echo '<a href="' . esc_url( $archive_url ) . '" style="color:inherit;text-decoration:none;">Wiki · ' . esc_html( $title ) . '</a>';
    echo '</h3>';

    if ( 'archive' === $wiki_view ) {
        $args = [
            'post_type'      => $section,
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'paged'          => $paged,
            'meta_query'     => [
                [
                    'key'   => 'campaign',
                    'value' => $campaign_id,
                ],
            ],
        ];
        if ( $search ) {
            $args['s'] = $search;
        }

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            echo '<p class="campaign-section__empty">Todavía no hay ' . esc_html( strtolower( $title ) ) . ' registrados para esta campaña.</p>';
            return;
        }

        $show_search = ( 'personaje_wiki' !== $section );

        $base_link = add_query_arg(
            [
                'wiki_section' => $section,
                'wiki_view'    => 'archive',
            ] + ( $search ? [ 'wiki_search' => $search ] : [] )
        );

        echo '<div class="wiki-archive">';
        echo '<div class="wiki-archive-layout' . ( $show_search ? '' : ' wiki-archive-layout--no-search' ) . '">';

        echo '<div class="wiki-archive-cards">';
        echo '<div class="wiki-archive__list">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $item_id     = get_the_ID();
            $target_link = ( $section === 'personaje_wiki' ) ? home_url( '/personaje-wiki/' . get_post_field( 'post_name', $item_id ) . '/' ) : get_permalink( $item_id );
            $excerpt     = get_the_excerpt();
            if ( ! $excerpt ) {
                $excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' );
            } else {
                $excerpt = wp_trim_words( $excerpt, 40, '…' );
            }
            echo '<article class="wiki-card">';
            echo '<h3 class="wiki-card__title"><a href="' . esc_url( $target_link ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
            if ( $excerpt ) {
                echo '<p class="wiki-card__excerpt">' . esc_html( $excerpt ) . '</p>';
            }
            echo '<div class="wiki-card__more"><a href="' . esc_url( $target_link ) . '"></a></div>';
            echo '</article>';
        }
        echo '</div>'; // list
        echo '</div>'; // cards

        if ( $show_search ) {
            echo '<div class="wiki-archive-search">';
            echo '<aside class="wiki-archive__sidebar">';
            echo '<h4>Buscar en ' . esc_html( $title ) . '</h4>';
            echo '<form method="get" class="wiki-search" action="' . esc_url( get_permalink( $campaign_id ) ) . '">';
            echo '<input type="hidden" name="wiki_section" value="' . esc_attr( $section ) . '">';
            echo '<input type="hidden" name="wiki_view" value="archive">';
            echo '<input type="text" id="drak-wiki-search-input" name="wiki_search" autocomplete="off" value="' . esc_attr( $search ) . '" placeholder="Buscar en ' . esc_attr( $title ) . '">';
            echo '<button type="submit" class="drak-btn drak-btn--full">Buscar</button>';
            echo '<div class="drak-wiki-search__suggestions" aria-live="polite"></div>';
            echo '</form>';
            echo '</aside>';
            echo '</div>'; // search
        }

        echo '</div>'; // layout
        echo '</div>'; // archive

        $paginate_links = paginate_links( [
            'total'   => (int) $query->max_num_pages,
            'current' => $paged,
            'base'    => add_query_arg( 'paged', '%#%', $base_link ),
            'format'  => '',
        ] );
        if ( $paginate_links ) {
            echo '<div class="wiki-archive__pagination">' . wp_kses_post( $paginate_links ) . '</div>';
        }
        wp_reset_postdata();
        return;
    }

    $query = new WP_Query( [
        'post_type'      => $section,
        'post_status'    => 'publish',
        'posts_per_page' => 12,
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
        echo '<p class="campaign-section__empty">Todavía no hay ' . esc_html( strtolower( $title ) ) . ' registrados para esta campaña.</p>';
        return;
    }

    echo '<div class="wiki-list">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $item_id     = get_the_ID();
        $target_link = ( $section === 'personaje_wiki' ) ? home_url( '/personaje-wiki/' . get_post_field( 'post_name', $item_id ) . '/' ) : get_permalink( $item_id );
        $excerpt     = get_the_excerpt();
        if ( ! $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' );
        } else {
            $excerpt = wp_trim_words( $excerpt, 40, '…' );
        }
        echo '<article class="wiki-card">';
        echo '<h3 class="wiki-card__title"><a href="' . esc_url( $target_link ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        if ( $excerpt ) {
            echo '<p class="wiki-card__excerpt">' . esc_html( $excerpt ) . '</p>';
        }
        echo '<div class="wiki-card__more"><a href="' . esc_url( $target_link ) . '"></a></div>';
        echo '</article>';
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
    $accent_dark  = $color ? drak_campaign_hex_to_rgba( $color, 0.25 ) : 'rgba(155, 92, 255, 0.25)';
    $hero_style   = $color ? ' style="--campaign-accent: ' . esc_attr( $color ) . ';--accent:' . esc_attr( $color ) . ';--accent-dark:' . esc_attr( $accent_dark ) . ';"' : ' style="--accent:#9b5cff;--accent-dark:rgba(155,92,255,0.25);"';
    $base_url     = trailingslashit( get_permalink() );
    $portal_url   = $base_url;
    $pj_url       = trailingslashit( $base_url . 'pj' );
    $diary_url    = trailingslashit( $base_url . 'diario' );
    $wiki_url     = trailingslashit( $base_url . 'wiki' );
    $gallery_url  = trailingslashit( $base_url . 'galeria' );
    $logo_id      = drak_get_campaign_logo_id( $campaign_id );
    $logo_html    = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, [ 'class' => 'campaign-hero__logo-img' ] ) : '';
    ?>

    <article class="campaign-single"<?php echo $hero_style; ?>>
        <div class="campaign-hero"<?php echo $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : ''; ?>>
        <div class="campaign-hero__overlay"></div>
        <div class="campaign-hero__inner">
            <div class="campaign-hero__content">
                    <?php if ( $logo_html ) : ?>
                        <div class="campaign-hero-logo"><?php echo $logo_html; ?></div>
                    <?php endif; ?>
                    <a class="campaign-hero__home-link drak-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">← Volver al Hub</a>
                </div>
        </div>
        </div>

        <main class="campaign-main">
            <div class="campaign-main-inner">
                <section class="campaign-actions">
                    <div class="campaign-actions__grid">
                        <a class="campaign-action drak-btn<?php echo $section === 'pj' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pj_url ); ?>">
                            <span class="campaign-action__title">Personajes</span>
                        </a>
                        <a class="campaign-action drak-btn<?php echo $section === 'diario' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $diary_url ); ?>">
                            <span class="campaign-action__title">Diario</span>
                        </a>
                        <a class="campaign-action drak-btn<?php echo $section === 'wiki' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $wiki_url ); ?>">
                            <span class="campaign-action__title">Wiki</span>
                        </a>
                        <a class="campaign-action drak-btn<?php echo $section === 'galeria' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $gallery_url ); ?>">
                            <span class="campaign-action__title">Galería</span>
                        </a>
                    </div>
                </section>

                <section class="campaign-section">
                    <?php
                    switch ( $section ) {
                        case 'pj':
                        default:
                            drak_campaign_section_title( 'Personajes' );
                            drak_campaign_render_personajes( $campaign_id );
                            break;
                        case 'diario':
                            drak_campaign_section_title( 'Diario' );
                            drak_campaign_render_diary( $campaign_id );
                            break;
                        case 'wiki':
                            $wiki_section = isset( $_GET['wiki_section'] ) ? sanitize_key( wp_unslash( $_GET['wiki_section'] ) ) : '';
                            if ( $wiki_section ) {
                                drak_campaign_render_wiki_section( $campaign_id, $wiki_section );
                            } else {
                                drak_campaign_section_title( 'Wiki' );
                                drak_campaign_render_wiki_hub( $campaign_id, $wiki_url );
                            }
                            break;
                        case 'galeria':
                            drak_campaign_section_title( 'Galería' );
                            if ( shortcode_exists( 'drak_gallery' ) ) {
                                $gallery_html = do_shortcode( '[drak_gallery campaign="' . esc_attr( $campaign_id ) . '"]' );
                                if ( trim( $gallery_html ) !== '' ) {
                                    echo $gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                } else {
                                    echo '<p class="campaign-section__empty">Esta campaña aún no tiene imágenes en la galería.</p>';
                                }
                            } else {
                                $gallery_link = add_query_arg( 'campaign', $campaign_id, home_url( '/galeria/' ) );
                                echo '<p class="campaign-section__empty">La galería no está disponible en esta vista. Puedes verla aquí: <a href="' . esc_url( $gallery_link ) . '">abrir galería</a>.</p>';
                            }
                            break;
                    }
                    ?>
                </section>
            </div>
        </main>
    </article>

<?php endwhile; ?>

<?php
get_footer();
