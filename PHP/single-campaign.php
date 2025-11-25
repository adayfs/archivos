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
.single-campaign .campaign-single {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  box-sizing: border-box;
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
  padding: 0 20px;
  box-sizing: border-box;
}
.campaign-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  background: linear-gradient(180deg, rgba(0,0,0,0.55), rgba(0,0,0,0.85));
  border-radius: 12px;
  border: 1px solid var(--accent, #261a3a);
  box-sizing: border-box;
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
.campaign-create-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  margin: 0 0 16px;
  border-radius: 10px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: var(--text, #fff);
  text-decoration: none;
  font-weight: 600;
}
.campaign-create-btn:hover {
  border-color: var(--accent, #9b5cff);
}
.campaign-create-btn:focus-visible {
  outline: 2px solid var(--accent, #9b5cff);
  outline-offset: 2px;
}
@media (max-width: 720px) {
  .campaign-create-btn {
    width: 100%;
    justify-content: center;
  }
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
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
  padding-left: 20px;
  padding-right: 20px;
  box-sizing: border-box;
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
.hb-wrapper {
  --accent: #9b5cff;
  --accent-weak: rgba(155, 92, 255, 0.35);
  margin-top: 12px;
  color: #f5f5f5;
}
.hb-hero {
  background: #0d0818;
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  min-height: 320px;
}
.hb-hero__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.hb-hero__body {
  padding: 14px 18px 18px;
  background: linear-gradient(180deg, rgba(0,0,0,0.55), rgba(0,0,0,0.9));
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.hb-hero__title {
  margin: 0 0 6px;
  font-size: clamp(26px, 4vw, 38px);
  color: var(--accent);
  text-align: center;
}
.hb-hero__links {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
}
.hb-link {
  text-decoration: none;
  font-size: 14px;
}
.hb-tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(160px, 1fr));
  gap: 10px;
  margin: 12px 0 18px;
}
.hb-tab {
  text-align: center;
  padding: 14px 10px;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  text-decoration: none;
  color: #fff;
  background: rgba(0,0,0,0.5);
}
.hb-tab.is-active {
  border-color: var(--accent);
  box-shadow: 0 4px 12px rgba(0,0,0,0.35);
}
.hb-card.hb-section {
  background: #161224;
  border-radius: 10px;
  border: 1px solid var(--accent-weak);
  padding: 14px;
}
.hb-section-title {
  text-align: center;
  color: var(--accent);
  margin: 0 0 12px;
}
.hb-entry {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 12px;
  margin-bottom: 12px;
  background: rgba(0,0,0,0.35);
}
.hb-entry h4 {
  margin: 0 0 6px;
}
.hb-empty {
  text-align: center;
  color: #ccc;
}
.hb-sections [data-section] { display: none; }
.hb-sections [data-section].is-active { display: block; }
.hb-note-form .hb-field {
  margin-bottom: 8px;
}
.hb-note-form label {
  display: block;
  margin-bottom: 4px;
}
.hb-note-form select,
.hb-note-form input[type="text"],
.hb-note-form textarea,
.hb-note-form .wp-editor-wrap {
  width: 100%;
  background: #161224;
  border: 1px solid var(--accent);
  color: #fff;
  box-sizing: border-box;
}
.hb-note-form input[type="text"],
.hb-note-form select {
  padding: 10px;
  border-radius: 8px;
}
.hb-note-form .wp-editor-wrap {
  border-radius: 8px;
  overflow: hidden;
}
.hb-section-block {
  margin-bottom: 12px;
}
@media (max-width: 720px) {
  .hb-tabs {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
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
    $wizard_page = get_page_by_path( 'crear-personaje' );
    $wizard_url  = $wizard_page ? add_query_arg( 'campaign_id', $campaign_id, get_permalink( $wizard_page->ID ) ) : '';
    if ( is_user_logged_in() && $wizard_url ) {
        echo '<div class="campaign-create-wrapper">';
        echo '<a class="campaign-create-btn drak-btn" href="' . esc_url( $wizard_url ) . '">CREAR PERSONAJE</a>';
        echo '</div>';
    }

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
        'static-weapons'     => [ 'title' => 'Armas' ],
        'static-armor'       => [ 'title' => 'Armaduras' ],
        'static-actions'     => [ 'title' => 'Acciones' ],
        'static-spells'      => [ 'title' => 'Hechizos' ],
        'static-races'       => [ 'title' => 'Razas' ],
        'static-languages'   => [ 'title' => 'Idiomas' ],
        'static-feats'       => [ 'title' => 'Dotes' ],
        'static-backgrounds' => [ 'title' => 'Trasfondos' ],
        'static-tools'       => [ 'title' => 'Herramientas' ],
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
    $static_sections = [
        'static-weapons'     => [ 'title' => 'Armas',       'file' => 'dnd-weapons.json',       'file_es' => 'dnd-weapons-es.json',       'root' => 'weapons' ],
        'static-armor'       => [ 'title' => 'Armaduras',   'file' => 'dnd-armors.json',        'file_es' => 'dnd-armors-es.json',        'root' => 'armors' ],
        'static-actions'     => [ 'title' => 'Acciones',    'file' => 'dnd-actions.json',       'file_es' => 'dnd-actions-es.json',       'root' => 'actions' ],
        'static-spells'      => [ 'title' => 'Hechizos',    'file' => 'dnd-spells.json',        'file_es' => 'dnd-spells-es.json',        'root' => 'spells' ],
        'static-races'       => [ 'title' => 'Razas',       'file' => 'dnd-races.json',         'file_es' => 'dnd-races-es.json',         'root' => 'races' ],
        'static-languages'   => [ 'title' => 'Idiomas',     'file' => 'dnd-languages.json',     'file_es' => 'dnd-languages-es.json',     'root' => 'languages' ],
        'static-feats'       => [ 'title' => 'Dotes',       'file' => 'dnd-feats.json',         'file_es' => 'dnd-feats-es.json',         'root' => 'feats' ],
        'static-backgrounds' => [ 'title' => 'Trasfondos',  'file' => 'dnd-backgrounds.json',   'file_es' => 'dnd-backgrounds-es.json',   'root' => 'backgrounds' ],
        'static-tools'       => [ 'title' => 'Herramientas','file' => 'dnd-tools.json',         'file_es' => 'dnd-tools-es.json',         'root' => 'tools' ],
    ];

    if ( isset( $static_sections[ $section ] ) ) {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'dnd5-renderer', get_stylesheet_directory_uri() . '/js/dnd5-renderer.js', [], null, true );
        }
        drak_campaign_render_static_wiki_section( $section, $static_sections[ $section ] );
        return;
    }

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

function drak_campaign_static_find_file( $filename ) {
    $dirs = [
        trailingslashit( get_stylesheet_directory() ) . 'data/',
        trailingslashit( get_stylesheet_directory() ) . 'jsons/',
    ];
    foreach ( $dirs as $dir ) {
        $path = $dir . ltrim( $filename, '/\\' );
        if ( file_exists( $path ) ) {
            return $path;
        }
    }
    return '';
}

function drak_campaign_static_pick_file( array $candidates ) {
    foreach ( $candidates as $file ) {
        if ( ! $file ) {
            continue;
        }
        $path = drak_campaign_static_find_file( $file );
        if ( $path ) {
            return $path;
        }
    }
    return '';
}

function drak_campaign_static_load_json( $path ) {
    if ( ! $path || ! file_exists( $path ) ) {
        return [];
    }
    $data = json_decode( file_get_contents( $path ), true );
    return is_array( $data ) ? $data : [];
}

function drak_campaign_static_get_name( $item ) {
    if ( isset( $item['name'] ) && is_array( $item['name'] ) ) {
        return $item['name']['es'] ?? $item['name']['en'] ?? '';
    }
    return $item['name'] ?? '';
}

function drak_campaign_render_static_card_meta( $item, $section_key ) {
    $meta = [];
    switch ( $section_key ) {
        case 'static-weapons':
            $meta[] = $item['category'] ?? '';
            $meta[] = trim( ( $item['dmg1'] ?? '' ) . ' ' . ( $item['dmgType'] ?? '' ) );
            break;
        case 'static-armor':
            $meta[] = $item['type'] ?? '';
            $meta[] = isset( $item['ac'] ) ? 'CA ' . $item['ac'] : '';
            if ( ! empty( $item['stealthDisadvantage'] ) ) {
                $meta[] = 'Desventaja sigilo';
            }
            break;
        case 'static-spells':
            $meta[] = 'Nivel ' . ( $item['level'] ?? 0 );
            $meta[] = $item['school'] ?? '';
            break;
        case 'static-races':
            if ( isset( $item['size'] ) && is_array( $item['size'] ) ) {
                $meta[] = implode( ', ', $item['size'] );
            }
            if ( isset( $item['speed']['walk'] ) ) {
                $meta[] = 'Vel ' . $item['speed']['walk'];
            }
            break;
        case 'static-languages':
            if ( isset( $item['type'] ) ) {
                $meta[] = $item['type'];
            }
            if ( isset( $item['script'] ) ) {
                $meta[] = 'Escritura: ' . $item['script'];
            }
            break;
        case 'static-feats':
            if ( ! empty( $item['prerequisite'] ) ) {
                $meta[] = 'Requisitos';
            }
            if ( ! empty( $item['ability'] ) ) {
                $meta[] = 'Atributos';
            }
            break;
        case 'static-backgrounds':
            if ( ! empty( $item['skillProficiencies'] ) ) {
                $meta[] = 'Pericias';
            }
            if ( ! empty( $item['languageProficiencies'] ) ) {
                $meta[] = 'Idiomas';
            }
            break;
        case 'static-tools':
            if ( isset( $item['category'] ) ) {
                $meta[] = $item['category'];
            }
            if ( isset( $item['type'] ) ) {
                $meta[] = $item['type'];
            }
            break;
        case 'static-actions':
            if ( isset( $item['group'] ) ) {
                $meta[] = $item['group'];
            }
            break;
    }
    return implode( ' · ', array_filter( $meta ) );
}

function drak_campaign_render_static_wiki_section( $section_key, $config ) {
    $title     = $config['title'] ?? ucfirst( $section_key );
    $file      = drak_campaign_static_pick_file( [ $config['file_es'] ?? '', $config['file'] ?? '' ] );
    $root      = $config['root'] ?? '';
    $search    = isset( $_GET['wiki_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wiki_search'] ) ) : '';
    $paged     = max( 1, (int) get_query_var( 'paged' ) ?: (int) ( $_GET['paged'] ?? 1 ) );
    $per_page  = 24;

    echo '<h3 class="campaign-section__title" style="text-align:center;">Wiki · ' . esc_html( $title ) . '</h3>';

    if ( ! $file || ! $root ) {
        echo '<p class="campaign-section__empty">Datos no disponibles.</p>';
        return;
    }

    $data = drak_campaign_static_load_json( $file );
    $items = $data[ $root ] ?? [];
    if ( ! is_array( $items ) || empty( $items ) ) {
        echo '<p class="campaign-section__empty">No hay datos para esta sección.</p>';
        return;
    }

    if ( $search ) {
        $items = array_values( array_filter( $items, function ( $item ) use ( $search ) {
            $name = strtolower( drak_campaign_static_get_name( $item ) );
            return strpos( $name, strtolower( $search ) ) !== false;
        } ) );
    }

    $total     = count( $items );
    $offset    = ( $paged - 1 ) * $per_page;
    $page_items = array_slice( $items, $offset, $per_page );
    $base_link = add_query_arg(
        array_filter(
            [
                'wiki_section' => $section_key,
                'wiki_view'    => 'archive',
                'wiki_search'  => $search ?: null,
            ]
        )
    );

    echo '<div class="wiki-archive" data-static-section="' . esc_attr( $section_key ) . '">';
    echo '<div class="wiki-archive-layout wiki-archive-layout--no-search">';
    echo '<div class="wiki-archive-cards"><div class="wiki-archive__list">';

    $detail_payload = [];
    foreach ( $page_items as $item ) {
        $name = drak_campaign_static_get_name( $item );
        $source = $item['source'] ?? '';
        $id = drak_campaign_static_get_id( $item, $name );
        $meta = drak_campaign_render_static_card_meta( $item, $section_key );
        if ( $id ) {
            $detail_payload[ $id ] = $item;
        }
        echo '<article class="wiki-card">';
        echo '<h3 class="wiki-card__title"><button type="button" class="wiki-card__title-btn" data-static-detail="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</button></h3>';
        if ( $meta ) {
            echo '<p class="wiki-card__excerpt">' . esc_html( $meta ) . '</p>';
        }
        if ( $source ) {
            echo '<p class="wiki-card__excerpt"><strong>Fuente:</strong> ' . esc_html( $source ) . '</p>';
        }
        echo '</article>';
    }

    echo '</div></div>'; // list/cards
    echo '</div>'; // layout

    $total_pages = (int) ceil( $total / $per_page );
    if ( $total_pages > 1 ) {
        $paginate_links = paginate_links( [
            'total'   => $total_pages,
            'current' => $paged,
            'base'    => add_query_arg( 'paged', '%#%', $base_link ),
            'format'  => '',
        ] );
        if ( $paginate_links ) {
            echo '<div class="wiki-archive__pagination">' . wp_kses_post( $paginate_links ) . '</div>';
        }
    }

    echo '</div>'; // archive

    if ( ! empty( $detail_payload ) ) {
        echo '<script>';
        echo 'window.DND5_STATIC_DETAIL = window.DND5_STATIC_DETAIL || {};';
        echo 'window.DND5_STATIC_DETAIL[' . wp_json_encode( $section_key ) . '] = ' . wp_json_encode( $detail_payload ) . ';';
        echo '</script>';
    }

    ?>
    <div class="wiki-static-modal" id="wiki-static-modal" hidden>
        <div class="wiki-static-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wiki-static-modal-title">
            <button type="button" class="wiki-static-modal__close" aria-label="Cerrar">&times;</button>
            <div class="wiki-static-modal__content">
                <h3 id="wiki-static-modal-title"></h3>
                <div class="wiki-static-modal__meta"></div>
                <div class="wiki-static-modal__body"></div>
            </div>
        </div>
        <div class="wiki-static-modal__backdrop"></div>
    </div>
    <style>
    .wiki-card__title-btn { background:none; border:none; color:inherit; padding:0; cursor:pointer; text-decoration:none; font:inherit; }
    .wiki-card__title-btn:hover { text-decoration:underline; }
    .wiki-static-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; }
    .wiki-static-modal[hidden] { display:none; }
    .wiki-static-modal__backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.75); }
    .wiki-static-modal__dialog { position:relative; z-index:1; max-width:880px; width:94%; max-height:92vh; overflow:auto; background:#0f0b1a; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:18px; color:#f5f3ff; box-shadow:0 10px 40px rgba(0,0,0,0.45); }
    .wiki-static-modal__close { position:absolute; top:10px; right:10px; border:none; background:transparent; color:#fff; font-size:22px; cursor:pointer; }
    .wiki-static-modal__meta { color:#d0cde3; margin-bottom:10px; display:flex; flex-wrap:wrap; gap:6px; }
    .wiki-static-chip { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; background:rgba(155,92,255,0.25); color:#f7f2ff; font-size:0.85rem; border:1px solid rgba(155,92,255,0.35); }
    .wiki-static-panel { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:12px; margin-bottom:12px; }
    .wiki-static-panel h4 { margin:0 0 8px; font-size:1rem; color:#f0d7ff; }
    .wiki-static-panel table { width:100%; border-collapse:collapse; font-size:0.9rem; margin:6px 0; }
    .wiki-static-panel th, .wiki-static-panel td { border:1px solid rgba(255,255,255,0.12); padding:6px 8px; text-align:left; }
    .wiki-static-panel ul { margin:0 0 0 18px; line-height:1.45; }
    .wiki-static-modal__body .dnd5-entry-block h4 { margin:0.5rem 0; font-size:1rem; color:#f0d7ff; }
    .wiki-static-modal__body .dnd5-entry-block p { line-height:1.5; margin:0.4rem 0; }
    .wiki-static-modal__body .dnd5-entry-block ul { margin:0.4rem 0 0.4rem 1.2rem; line-height:1.45; }
    .dnd5-tag { display:inline-block; padding:0 0.35rem; border-radius:0.35rem; background:rgba(153,51,255,0.25); color:#fff; font-size:0.85em; margin:0 0.1rem; }
    .dnd5-tag-spell, .dnd5-tag-action { background:rgba(57,255,20,0.25); color:#e6ffe1; }
    .wiki-static-meta-title { font-weight:700; text-transform:uppercase; font-size:0.8rem; letter-spacing:0.03em; color:#d7d2e8; margin-right:6px; }
    </style>
    <script>
    (function() {
      const initWhenReady = () => {
        const detailData = window.DND5_STATIC_DETAIL || {};
        const modal = document.getElementById('wiki-static-modal');
        if (!modal) return;
        const titleEl = modal.querySelector('#wiki-static-modal-title');
        const metaEl = modal.querySelector('.wiki-static-modal__meta');
        const bodyEl = modal.querySelector('.wiki-static-modal__body');
        const closeBtn = modal.querySelector('.wiki-static-modal__close');
        const backdrop = modal.querySelector('.wiki-static-modal__backdrop');

        function pickEntries(obj, section) {
          if (!obj || typeof obj !== 'object') return [];
          const res = [];
          // Para herramientas, si existe additionalEntries_es es el texto correcto.
          if (section === 'static-tools' && Array.isArray(obj.additionalEntries_es) && obj.additionalEntries_es.length) {
            return [...obj.additionalEntries_es];
          }
          // Prioridad ES general
          if (Array.isArray(obj.entries_es) && obj.entries_es.length) res.push(...obj.entries_es);
          if (Array.isArray(obj.additionalEntries_es) && obj.additionalEntries_es.length) res.push(...obj.additionalEntries_es);
          // Si no hay ES, usar base o EN
          if (!res.length && Array.isArray(obj.entries) && obj.entries.length) res.push(...obj.entries);
          if (!res.length && Array.isArray(obj.entries_en) && obj.entries_en.length) res.push(...obj.entries_en);
          if (!res.length && Array.isArray(obj.additionalEntries) && obj.additionalEntries.length) res.push(...obj.additionalEntries);
          if (!res.length && Array.isArray(obj.additionalEntries_en) && obj.additionalEntries_en.length) res.push(...obj.additionalEntries_en);
          return res;
        }
        function pickName(obj) {
          if (!obj) return '';
          if (obj.name && typeof obj.name === 'object') {
            return obj.name.es || obj.name.en || '';
          }
          return obj.name || '';
        }
        function escapeHtml(str) {
          return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }
        function toList(val) {
          if (val == null) return [];
          if (Array.isArray(val)) return val;
          if (typeof val === 'object') return Object.keys(val);
          return [val];
        }
        function renderChips(values) {
          return values.filter(Boolean).map((v) => `<span class="wiki-static-chip">${escapeHtml(v)}</span>`).join('');
        }
        function formatAbility(abilityArr) {
          if (!Array.isArray(abilityArr) || !abilityArr.length) return '';
          const parts = [];
          abilityArr.forEach((obj) => {
            if (!obj || typeof obj !== 'object') return;
            Object.entries(obj).forEach(([k, v]) => {
              parts.push(`${k.toUpperCase()} ${v >= 0 ? '+' : ''}${v}`);
            });
          });
          return parts.join(', ');
        }
        function formatPrereq(pr) {
          if (!pr) return '';
          if (Array.isArray(pr)) {
            return pr.map((p) => formatPrereq(p)).filter(Boolean).join('; ');
          }
          if (typeof pr === 'string') return pr;
          if (pr.ability) return formatAbility(pr.ability);
          if (pr.level) return `Nivel ${pr.level}`;
          return '';
        }
        function formatTime(times) {
          if (!Array.isArray(times)) return '';
          return times.map((t) => {
            if (typeof t === 'string') return t;
            const num = t.number ? `${t.number} ` : '';
            return `${num}${t.unit || ''}`.trim();
          }).filter(Boolean).join(', ');
        }
        function formatRange(range) {
          if (!range) return '';
          if (typeof range === 'string') return range;
          if (range.distance) {
            if (typeof range.distance === 'object') {
              return `${range.distance.amount || ''} ${range.distance.type || ''}`.trim();
            }
            return String(range.distance);
          }
          return range.type || '';
        }
        function formatDuration(durations) {
          if (!Array.isArray(durations)) return '';
          return durations.map((d) => d.type || '').filter(Boolean).join(', ');
        }
        function formatComponents(components) {
          if (!components || typeof components !== 'object') return '';
          const list = [];
          if (components.v) list.push('V');
          if (components.s) list.push('S');
          if (components.m) list.push('M' + (typeof components.m === 'string' ? ` (${components.m})` : ''));
          return list.join(', ');
        }
        function renderMetaChips(section, item) {
          const chips = [];
          if (item.source) chips.push('Fuente: ' + item.source);
          switch(section) {
            case 'static-weapons':
              if (item.category) chips.push('Categoría: ' + item.category);
              if (item.dmg1) chips.push('Daño: ' + item.dmg1 + (item.dmgType ? ' ' + item.dmgType : ''));
              if (item.properties && item.properties.length) chips.push('Propiedades: ' + item.properties.join(', '));
              break;
            case 'static-armor':
              if (item.type) chips.push('Tipo: ' + item.type);
              if (item.ac) chips.push('CA: ' + item.ac);
              if (item.stealthDisadvantage) chips.push('Desventaja sigilo');
              break;
            case 'static-spells':
              chips.push('Nivel: ' + (item.level ?? 0));
              if (item.school) chips.push('Escuela: ' + item.school);
              break;
            case 'static-races':
              if (item.size) chips.push('Tamaño: ' + [].concat(item.size).join(', '));
              if (item.speed && item.speed.walk) chips.push('Velocidad: ' + item.speed.walk);
              break;
            case 'static-languages':
              if (item.type) chips.push('Tipo: ' + item.type);
              if (item.script) chips.push('Escritura: ' + item.script);
              break;
            case 'static-feats':
              if (item.prerequisite && item.prerequisite.length) chips.push('Requisitos: ' + formatPrereq(item.prerequisite));
              break;
            case 'static-backgrounds':
              if (item.skillProficiencies && item.skillProficiencies.length) chips.push('Pericias');
              if (item.languageProficiencies && item.languageProficiencies.length) chips.push('Idiomas');
              break;
            case 'static-tools':
              if (item.category) chips.push('Categoría: ' + item.category);
              if (item.type) chips.push('Tipo: ' + item.type);
              break;
            case 'static-actions':
              if (item.group) chips.push('Grupo: ' + item.group);
              break;
          }
          return renderChips(chips);
        }

        function renderTable(section, item) {
          const rows = [];
          const addRow = (label, value) => {
            if (!value && value !== 0) return;
            rows.push(`<tr><th>${escapeHtml(label)}</th><td>${value}</td></tr>`);
          };
          switch(section) {
            case 'static-weapons':
              addRow('Tipo', escapeHtml(item.type || ''));
              addRow('Categoría', escapeHtml(item.category || ''));
              addRow('Daño (1M)', escapeHtml(item.dmg1 || ''));
              addRow('Daño (2M)', escapeHtml(item.dmg2 || ''));
              addRow('Tipo de daño', escapeHtml(item.dmgType || ''));
              addRow('Alcance', escapeHtml(item.range || ''));
              addRow('Munición', escapeHtml(item.ammoType || ''));
              if (item.properties && item.properties.length) {
                addRow('Propiedades', renderChips(item.properties));
              }
              addRow('Peso', escapeHtml(item.weight || ''));
              addRow('Valor', escapeHtml(item.value || ''));
              break;
            case 'static-armor':
              addRow('Tipo', escapeHtml(item.type || ''));
              addRow('CA', escapeHtml(item.ac ?? ''));
              addRow('Fuerza', escapeHtml(item.strength ?? ''));
              addRow('Sigilo', item.stealthDisadvantage ? 'Desventaja' : '—');
              addRow('Peso', escapeHtml(item.weight || ''));
              addRow('Valor', escapeHtml(item.value || ''));
              break;
            case 'static-spells':
              addRow('Nivel', escapeHtml(item.level ?? ''));
              addRow('Escuela', escapeHtml(item.school || ''));
              addRow('Tiempo', formatTime(item.time));
              addRow('Alcance', escapeHtml(formatRange(item.range)));
              addRow('Duración', escapeHtml(formatDuration(item.duration)));
              if (item.components) addRow('Componentes', escapeHtml(formatComponents(item.components)));
              if (item.classes) addRow('Clases', renderChips(item.classes.map((c) => c.name || c)));
              break;
            case 'static-races':
              if (item.ability) addRow('Atributos', escapeHtml(formatAbility(item.ability)));
              if (item.languageProficiencies) addRow('Idiomas', renderChips(item.languageProficiencies.map((l) => Object.keys(l)[0] || '')) );
              if (item.traitTags) addRow('Rasgos', renderChips(item.traitTags));
              break;
            case 'static-languages':
              addRow('Tipo', escapeHtml(item.type || ''));
              addRow('Escritura', escapeHtml(item.script || ''));
              if (item.typicalSpeakers) addRow('Hablantes típicos', renderChips(item.typicalSpeakers));
              break;
            case 'static-feats':
              if (item.prerequisite) addRow('Requisitos', escapeHtml(formatPrereq(item.prerequisite)));
              if (item.ability) addRow('Atributos', escapeHtml(formatAbility(item.ability)));
              break;
            case 'static-backgrounds':
              if (item.skillProficiencies) addRow('Pericias', renderChips(toList(item.skillProficiencies)));
              if (item.toolProficiencies) addRow('Herramientas', renderChips(toList(item.toolProficiencies)));
              if (item.languageProficiencies) addRow('Idiomas', renderChips(toList(item.languageProficiencies)));
              break;
            case 'static-tools':
              addRow('Categoría', escapeHtml(item.category || ''));
              addRow('Tipo', escapeHtml(item.type || ''));
              break;
            case 'static-actions':
              addRow('Grupo', escapeHtml(item.group || ''));
              if (item.time) addRow('Tiempo', renderChips(item.time));
              break;
          }
          if (!rows.length) return '';
          return `<div class="wiki-static-panel"><h4>Detalles</h4><table>${rows.join('')}</table></div>`;
        }

        function renderEntriesSafe(entries) {
          const r = window.DND5Render;
          if (r && typeof r.renderEntries === 'function') {
            return r.renderEntries(entries);
          }
          // Fallback simple
          if (!Array.isArray(entries)) return '';
          return entries.map((e) => {
            if (typeof e === 'string') return `<p>${escapeHtml(e)}</p>`;
            if (e && typeof e === 'object' && e.entry) return `<p>${escapeHtml(e.entry)}</p>`;
            return '';
          }).join('');
        }

        function openDetail(section, id) {
          const store = detailData[section] || {};
          const item = store[id];
          if (!item) return;
          titleEl.textContent = pickName(item) || id;
          metaEl.innerHTML = renderMetaChips(section, item);
          let entries = pickEntries(item, section);
          if ((!entries || !entries.length) && Array.isArray(item.typicalSpeakers) && item.typicalSpeakers.length) {
            entries = [
              {
                type: 'list',
                name: 'Hablantes típicos',
                items: item.typicalSpeakers,
              },
            ];
          }
          const entriesHtml = renderEntriesSafe(entries);
          const tableHtml = renderTable(section, item);
          bodyEl.innerHTML = tableHtml + (entriesHtml || '');
          modal.hidden = false;
        }
        function closeDetail() { modal.hidden = true; }
        document.addEventListener('click', function(evt) {
          const btn = evt.target.closest('[data-static-detail]');
          if (!btn) return;
          const section = btn.closest('[data-static-section]')?.getAttribute('data-static-section');
          const id = btn.getAttribute('data-static-detail');
          if (section && id) {
            evt.preventDefault();
            openDetail(section, id);
          }
        });
        closeBtn?.addEventListener('click', closeDetail);
        backdrop?.addEventListener('click', closeDetail);
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && !modal.hidden) {
            closeDetail();
          }
        });
      };

      if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initWhenReady();
      } else {
        document.addEventListener('DOMContentLoaded', initWhenReady);
      }
    })();
    </script>
    <?php
}

function drak_campaign_static_get_id( $item, $fallback = '' ) {
    if ( isset( $item['id'] ) && $item['id'] ) {
        return $item['id'];
    }
    if ( $fallback ) {
        return sanitize_title( $fallback );
    }
    return '';
}

function drak_campaign_get_homebrew_entries( $campaign_id, $section, $search = '' ) {
    return new WP_Query( [
        'post_type'      => 'homebrew_entry',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $search,
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
            [
                'key'   => 'homebrew_section',
                'value' => $section,
            ],
        ],
    ] );
}

function drak_campaign_render_homebrew( $campaign_id, $accent_color = '', $cover_id = 0 ) {
    $sections      = function_exists( 'drak_homebrew_sections' ) ? drak_homebrew_sections() : [
        'reglas'    => 'Reglas',
        'monstruos' => 'Manual de Monstruos',
        'forja'     => 'Forja',
        'tienda'    => 'Tienda',
    ];
    $tab           = isset( $_GET['hb_tab'] ) ? sanitize_key( wp_unslash( $_GET['hb_tab'] ) ) : 'reglas';
    $allowed_tabs  = array_merge( array_keys( $sections ), [ 'notas' ] );
    $tab           = in_array( $tab, $allowed_tabs, true ) ? $tab : 'reglas';
    $hb_header_url = 'https://adayfs.com/wp-content/uploads/2025/11/cabeceraDM.png';
    $accent      = $accent_color ?: (string) get_field( 'campaign_color', $campaign_id );
    $accent      = $accent ?: '#9b5cff';
    $accent_weak = $accent ? drak_campaign_hex_to_rgba( $accent, 0.35 ) : 'rgba(155, 92, 255, 0.35)';
    $search       = isset( $_GET['hb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['hb_search'] ) ) : '';
    ?>
    <div class="hb-wrapper" style="--accent: <?php echo esc_attr( $accent ); ?>; --accent-weak: <?php echo esc_attr( $accent_weak ); ?>;">
        <div class="hb-hero">
            <div class="hb-hero__media">
                <img src="<?php echo esc_url( $hb_header_url ); ?>" alt="Cabecera Homebrew">
            </div>
            <div class="hb-hero__body">
                <h2 class="hb-hero__title"><?php echo esc_html( get_the_title( $campaign_id ) ); ?> · Homebrew</h2>
                <div class="hb-hero__links">
                    <button class="drak-btn" type="button" data-hb-tab="notas">Añadir regla</button>
                </div>
            </div>
        </div>

        <div class="hb-tabs">
            <?php foreach ( $sections as $key => $label ) : ?>
                <button class="hb-tab drak-btn<?php echo $tab === $key ? ' is-active' : ''; ?>" type="button" data-hb-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <section class="hb-card hb-section hb-sections">
            <?php foreach ( $sections as $key => $label ) : ?>
                <div class="hb-section-block<?php echo $tab === $key ? ' is-active' : ''; ?>" data-section="<?php echo esc_attr( $key ); ?>">
                    <h3 class="hb-section-title"><?php echo esc_html( $label ); ?></h3>
                    <div class="hb-entries" data-section-list="<?php echo esc_attr( $key ); ?>">
                        <?php
                        $entries = drak_campaign_get_homebrew_entries( $campaign_id, $key, ( $tab === $key ? $search : '' ) );
                        if ( $entries->have_posts() ) :
                            while ( $entries->have_posts() ) :
                                $entries->the_post();
                                ?>
                                <article class="hb-entry">
                                    <h4><?php the_title(); ?></h4>
                                    <small><?php echo esc_html( get_the_date() ); ?></small>
                                    <div><?php the_excerpt(); ?></div>
                                    <a class="hb-link" href="<?php the_permalink(); ?>">Leer más</a>
                                </article>
                                <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                            echo '<p class="hb-empty">No hay entradas en esta sección.</p>';
                        endif;
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="hb-section-block<?php echo $tab === 'notas' ? ' is-active' : ''; ?>" data-section="notas">
                <h3 class="hb-section-title" style="margin-bottom:8px;">Notas</h3>
                <form method="post" class="hb-note-form" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                    <input type="hidden" name="action" value="drak_homebrew_add_entry">
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
                    <div class="hb-field">
                        <label for="hb_section">Sección</label>
                        <select id="hb_section" name="hb_section" required>
                            <?php foreach ( $sections as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hb-field">
                        <label for="hb_title">Título</label>
                        <input type="text" id="hb_title" name="hb_title" maxlength="120" required>
                    </div>
                    <div class="hb-field">
                        <?php
                        wp_editor(
                            '',
                            'drak_hb_content',
                            [
                                'textarea_name' => 'hb_content',
                                'media_buttons' => true,
                                'teeny'         => false,
                                'quicktags'     => true,
                                'editor_height' => 220,
                            ]
                        );
                        ?>
                    </div>
                    <?php wp_nonce_field( 'hb_save_note', 'hb_note_nonce' ); ?>
                    <button type="submit" class="hb-link drak-btn drak-btn--full">Guardar nota</button>
                    <p class="hb-empty hb-note-feedback" style="display:none;"></p>
                </form>
            </div>
        </section>
    </div>
    <script>
    (function(){
      const tabs = document.querySelectorAll('.hb-tab');
      const sections = document.querySelectorAll('.hb-section-block');
      const addBtn = document.querySelector('.hb-hero__links [data-hb-tab="notas"]');
      tabs.forEach(btn => {
        btn.addEventListener('click', () => {
          const target = btn.getAttribute('data-hb-tab');
          tabs.forEach(b => b.classList.remove('is-active'));
          btn.classList.add('is-active');
          sections.forEach(sec => {
            sec.classList.toggle('is-active', sec.getAttribute('data-section') === target);
          });
          if (history.replaceState) {
            history.replaceState(null, '', '#' + target);
          }
        });
      });
      if (addBtn) {
        addBtn.addEventListener('click', () => {
          const target = 'notas';
          tabs.forEach(b => b.classList.remove('is-active'));
          sections.forEach(sec => {
            sec.classList.toggle('is-active', sec.getAttribute('data-section') === target);
          });
        });
      }
      const hash = window.location.hash.replace('#','');
      if (hash) {
        const btn = document.querySelector('.hb-tab[data-hb-tab="'+hash+'"]');
        if (btn) { btn.click(); }
      }

      const form = document.querySelector('.hb-note-form');
      if (form) {
        form.addEventListener('submit', function(ev){
          ev.preventDefault();
          const ajaxUrl = form.getAttribute('data-ajax-url');
          const feedback = form.querySelector('.hb-note-feedback');
          if (window.tinymce) { tinymce.triggerSave(); }
          if (!ajaxUrl) { form.submit(); return; }
          const data = new FormData(form);
          fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
          }).then(res => res.json()).then(json => {
            if (json && json.success && json.data && json.data.html) {
              const section = form.querySelector('[name="hb_section"]').value;
              const list = document.querySelector('[data-section-list="'+section+'"]');
              if (list) { list.insertAdjacentHTML('afterbegin', json.data.html); }
              form.reset();
              if (window.tinymce) {
                const ed = tinymce.get('drak_hb_content');
                if (ed) { ed.setContent(''); }
              }
              if (feedback) { feedback.textContent = 'Nota guardada correctamente.'; feedback.style.display = 'block'; }
              const btnTarget = document.querySelector('.hb-tab[data-hb-tab="'+section+'"]');
              if (btnTarget) { btnTarget.click(); }
            } else {
              if (feedback) { feedback.textContent = (json && json.data && json.data.message) ? json.data.message : 'Error al guardar la nota.'; feedback.style.display = 'block'; }
            }
          }).catch(() => {
            if (feedback) { feedback.textContent = 'Error al guardar la nota.'; feedback.style.display = 'block'; }
          });
        });
      }
    })();
    </script>
    <?php
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
    $homebrew_url = trailingslashit( $base_url . 'homebrew' );
    $homebrew_ok  = function_exists( 'drak_homebrew_user_can_manage' ) ? drak_homebrew_user_can_manage() : false;
    if ( 'homebrew' === $section && ! $homebrew_ok ) {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
            exit;
        }
        wp_die(
            __( 'No tienes permiso para acceder a Homebrew.', 'temahijo' ),
            __( 'Acceso restringido', 'temahijo' ),
            [ 'response' => 403 ]
        );
    }
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
                        <?php if ( $homebrew_ok ) : ?>
                            <a class="campaign-action drak-btn<?php echo $section === 'homebrew' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $homebrew_url ); ?>">
                                <span class="campaign-action__title">Homebrew</span>
                            </a>
                        <?php endif; ?>
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
                        case 'homebrew':
                            drak_campaign_render_homebrew( $campaign_id, $color, $cover_id );
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
