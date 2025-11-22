<?php
/**
 * Plantilla compartida para entradas de Wiki: NPC, Lugar, Facción.
 */

require_once __DIR__ . '/single-campaign-helpers.php';

get_header();

$entry_id   = get_the_ID();
$post_type  = get_post_type( $entry_id );
$allowed    = [ 'npc', 'lugar', 'faccion', 'diario' ];

if ( ! in_array( $post_type, $allowed, true ) ) {
    get_template_part( 'single' );
    get_footer();
    return;
}

$campaign_id    = (int) get_field( 'campaign', $entry_id );
$campaign_link  = $campaign_id ? get_permalink( $campaign_id ) : '';
$campaign_color = $campaign_id ? (string) get_field( 'campaign_color', $campaign_id ) : '';
$accent         = $campaign_color ?: '#9b5cff';
$accent_dark    = $campaign_color ? drak_campaign_hex_to_rgba( $campaign_color, 0.25 ) : 'rgba(155, 92, 255, 0.25)';

$header_image_id   = (int) get_field( 'wiki_header_image', $entry_id );
if ( ! $header_image_id ) {
    $header_image_id = get_post_thumbnail_id( $entry_id );
}
$header_image_html = $header_image_id ? wp_get_attachment_image( $header_image_id, 'large', false, [ 'class' => 'wiki-entry__image-media' ] ) : '';

$section_labels = [
    'npc'            => 'NPC',
    'lugar'          => 'Lugares',
    'faccion'        => 'Facciones',
    'personaje_wiki' => 'Personajes',
    'diario'         => 'Diario',
];

$search_placeholder = isset( $section_labels[ $post_type ] ) ? 'Buscar en ' . $section_labels[ $post_type ] : 'Buscar en la Wiki';
$wiki_base          = $campaign_link ? trailingslashit( $campaign_link ) . 'wiki/' : '';
$diary_base         = $campaign_link ? trailingslashit( $campaign_link ) . 'diario/' : '';
$search_action      = ( 'diario' === $post_type && $diary_base ) ? $diary_base : ( $campaign_link ?: home_url( '/' ) );

$recent_args = [
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => 8,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

if ( $campaign_id ) {
    $recent_args['meta_query'] = [
        [
            'key'   => 'campaign',
            'value' => $campaign_id,
        ],
    ];
}

$recent_args['post__not_in'] = [ $entry_id ];
$recent_query                = new WP_Query( $recent_args );

$prev_post = drak_get_adjacent_wiki_post( $entry_id, 'prev', $post_type, $campaign_id );
$next_post = drak_get_adjacent_wiki_post( $entry_id, 'next', $post_type, $campaign_id );
?>

<style>
.wiki-entry {
  --accent: <?php echo esc_html( $accent ); ?>;
  --accent-dark: <?php echo esc_html( $accent_dark ); ?>;
  max-width: 1200px;
  margin: 32px auto 48px;
  padding: 0 16px;
  color: #f5f5f5;
}
.wiki-entry__grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 18px;
}
.wiki-entry__main {
  background: linear-gradient(180deg, rgba(0,0,0,0.5), rgba(0,0,0,0.75));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 16px;
  padding: 18px 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.wiki-entry__title {
  margin: 0;
  font-size: clamp(26px, 4vw, 36px);
  text-align: center;
  color: var(--accent, #9b5cff);
}
.wiki-entry__image {
  border-radius: 14px;
  border: 1px solid var(--accent, #9b5cff);
  overflow: hidden;
  height: 320px;
  background: #0d0818;
}
.wiki-entry__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.wiki-entry__content {
  font-size: 16px;
  line-height: 1.65;
}
.wiki-entry__content p {
  margin-bottom: 1em;
}
.wiki-entry__meta {
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #cfd0e6;
}
.wiki-entry__meta-dot {
  opacity: 0.6;
}
.wiki-entry-nav {
  margin-top: 8px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.wiki-entry-nav a {
  flex: 1;
  text-align: center;
}
.wiki-entry__sidebar {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.wiki-panel {
  background: linear-gradient(180deg, rgba(0,0,0,0.45), rgba(0,0,0,0.8));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 14px;
  padding: 14px 16px;
  box-shadow: 0 10px 24px rgba(0,0,0,0.28);
}
.wiki-panel__title {
  margin: 0 0 12px;
  color: var(--accent, #9b5cff);
  text-align: center;
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
  color: #f5f5f5;
  box-sizing: border-box;
}
.wiki-search button {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: #f5f5f5;
  cursor: pointer;
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
  color: #f5f5f5;
  text-decoration: none;
}
.drak-wiki-search__item strong {
  display: block;
  color: var(--accent, #9b5cff);
  margin-bottom: 4px;
}
.drak-wiki-search__item span {
  display: block;
  color: #aaa9c4;
  font-size: 13px;
}
.drak-wiki-search__empty {
  margin: 0;
  color: #aaa9c4;
  font-size: 13px;
}
.wiki-entry__nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.wiki-entry__nav-link {
  display: block;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  background: rgba(22, 18, 36, 0.7);
  color: #f5f5f5;
  text-decoration: none;
  transition: border-color 0.2s ease, transform 0.2s ease;
}
.wiki-entry__nav-link:hover {
  border-color: var(--accent, #9b5cff);
  transform: translateY(-1px);
}
.wiki-entry__nav-link.is-active {
  border-color: var(--accent, #9b5cff);
  background: rgba(155, 92, 255, 0.12);
}
.wiki-entry__recent {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.wiki-entry__recent-item {
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  background: rgba(22, 18, 36, 0.65);
  color: #f5f5f5;
  text-decoration: none;
}
.wiki-entry__recent-item strong {
  display: block;
  margin-bottom: 4px;
  color: var(--accent, #9b5cff);
}
.wiki-entry__recent-item span {
  display: block;
  color: #cfd0e6;
  font-size: 13px;
}
.wiki-entry__recent-empty {
  margin: 0;
  color: #aaa9c4;
  text-align: center;
  font-size: 13px;
}
@media (max-width: 960px) {
  .wiki-entry__grid {
    grid-template-columns: 1fr;
  }
  .wiki-entry__image {
    height: 220px;
  }
}
</style>

<div class="wiki-entry wiki-entry--single">
  <div class="wiki-entry__grid">
    <div class="wiki-entry__main">
      <h1 class="wiki-entry__title"><?php the_title(); ?></h1>

      <?php if ( $header_image_html ) : ?>
        <div class="wiki-entry__image">
          <?php echo $header_image_html; ?>
        </div>
      <?php endif; ?>

      <div class="wiki-entry__content">
        <?php the_content(); ?>
      </div>

      <div class="wiki-entry__meta">
        <span class="wiki-entry__meta-item">Por <?php echo esc_html( get_the_author() ); ?></span>
        <span class="wiki-entry__meta-dot">·</span>
        <span class="wiki-entry__meta-item"><?php echo esc_html( get_the_date() ); ?></span>
      </div>

      <?php if ( $prev_post || $next_post ) : ?>
        <div class="wiki-entry-nav">
          <?php if ( $prev_post ) : ?>
            <a class="drak-btn" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">← <?php echo esc_html( $section_labels[ $post_type ] ?? 'Entrada' ); ?> anterior</a>
          <?php endif; ?>
          <?php if ( $next_post ) : ?>
            <a class="drak-btn" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>"><?php echo esc_html( $section_labels[ $post_type ] ?? 'Entrada' ); ?> siguiente →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="wiki-entry__sidebar">
      <div class="wiki-panel wiki-panel--search">
        <h3 class="wiki-panel__title">Buscar</h3>
        <form method="get" class="wiki-search" action="<?php echo esc_url( $search_action ); ?>">
          <input type="hidden" name="wiki_section" value="<?php echo esc_attr( $post_type ); ?>">
          <input type="hidden" name="wiki_view" value="archive">
          <input type="text" id="drak-wiki-search-input" name="wiki_search" autocomplete="off" placeholder="<?php echo esc_attr( $search_placeholder ); ?>">
          <button type="submit" class="drak-btn drak-btn--full">Buscar</button>
          <div class="drak-wiki-search__suggestions" aria-live="polite"></div>
        </form>
      </div>

      <?php if ( 'diario' !== $post_type ) : ?>
        <div class="wiki-panel wiki-panel--nav">
          <h3 class="wiki-panel__title">Wiki</h3>
          <nav class="wiki-entry__nav" aria-label="Navegación de Wiki">
            <?php foreach ( $section_labels as $key => $label ) : ?>
              <?php
              if ( 'diario' === $key ) {
                  continue;
              }
              $url = $wiki_base ? add_query_arg(
                  [
                      'wiki_section' => $key,
                      'wiki_view'    => 'archive',
                  ],
                  $wiki_base
              ) : '';
              $is_active = $post_type === $key;
              ?>
              <a class="wiki-entry__nav-link<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ?: '#' ); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                <?php echo esc_html( $label ); ?>
              </a>
            <?php endforeach; ?>
          </nav>
        </div>
      <?php endif; ?>

      <div class="wiki-panel wiki-panel--recent">
        <h3 class="wiki-panel__title"><?php echo 'diario' === $post_type ? 'Últimas sesiones' : 'Últimas entradas'; ?></h3>
        <?php if ( $recent_query->have_posts() ) : ?>
          <div class="wiki-entry__recent">
            <?php
            while ( $recent_query->have_posts() ) :
                $recent_query->the_post();
                ?>
              <a class="wiki-entry__recent-item" href="<?php the_permalink(); ?>">
                <strong><?php the_title(); ?></strong>
                <span><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ?: get_the_content() ), 18, '…' ) ); ?></span>
              </a>
            <?php endwhile; ?>
          </div>
          <?php wp_reset_postdata(); ?>
        <?php else : ?>
          <p class="wiki-entry__recent-empty">No hay entradas recientes.</p>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<?php
get_footer();
