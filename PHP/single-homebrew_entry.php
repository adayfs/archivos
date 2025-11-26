<?php
require_once __DIR__ . '/single-campaign-helpers.php';

get_header();

$entry_id     = get_the_ID();
$campaign_id  = (int) get_field( 'campaign', $entry_id );
$section      = sanitize_key( get_field( 'homebrew_section', $entry_id ) ?: 'reglas' );
$sections     = function_exists( 'drak_homebrew_sections' ) ? drak_homebrew_sections() : [
    'reglas'    => 'Reglas',
    'monstruos' => 'Manual de Monstruos',
    'forja'     => 'Forja',
    'tienda'    => 'Tienda',
];
$campaign_url = $campaign_id ? trailingslashit( get_permalink( $campaign_id ) ) : '';
$homebrew_url = $campaign_id ? trailingslashit( $campaign_url . 'homebrew' ) : '';
$search_placeholder = isset( $sections[ $section ] ) ? 'Buscar en ' . $sections[ $section ] : 'Buscar en Homebrew';
$color       = $campaign_id ? (string) get_field( 'campaign_color', $campaign_id ) : '';
$accent      = $color ?: '#9b5cff';
$accent_dark = $color ? drak_campaign_hex_to_rgba( $color, 0.25 ) : 'rgba(155, 92, 255, 0.25)';
$can_manage  = function_exists( 'drak_homebrew_user_can_manage' ) ? drak_homebrew_user_can_manage() : false;
$edit_link   = ( $can_manage && current_user_can( 'edit_post', $entry_id ) ) ? get_edit_post_link( $entry_id ) : '';
$delete_link = ( $can_manage && current_user_can( 'delete_post', $entry_id ) ) ? get_delete_post_link( $entry_id, '', true ) : '';

$header_image_id   = get_post_thumbnail_id( $entry_id );
$header_image_html = $header_image_id ? wp_get_attachment_image( $header_image_id, 'large', false, [ 'class' => 'hb-entry__image-media' ] ) : '';
if ( ! $header_image_html ) {
    $header_image_html = '<img class="hb-entry__image-media" src="https://adayfs.com/wp-content/uploads/2025/11/cabeceraDM.png" alt="Homebrew">';
}

$recent_query = new WP_Query( [
    'post_type'      => 'homebrew_entry',
    'post_status'    => 'publish',
    'posts_per_page' => 5,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'post__not_in'   => [ $entry_id ],
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

$latest_per_section = [];
foreach ( $sections as $key => $label ) {
    $latest = new WP_Query( [
        'post_type'      => 'homebrew_entry',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'   => 'campaign',
                'value' => $campaign_id,
            ],
            [
                'key'   => 'homebrew_section',
                'value' => $key,
            ],
        ],
    ] );
    if ( $latest->have_posts() ) {
        $latest_per_section[ $key ] = get_permalink( $latest->posts[0] );
    }
    wp_reset_postdata();
}

$prev_post = drak_get_adjacent_homebrew_entry( $entry_id, 'prev', $section, $campaign_id );
$next_post = drak_get_adjacent_homebrew_entry( $entry_id, 'next', $section, $campaign_id );
?>

<style>
.hb-entry-page {
  --accent: <?php echo esc_html( $accent ); ?>;
  --accent-dark: <?php echo esc_html( $accent_dark ); ?>;
  max-width: 1200px;
  margin: 32px auto 48px;
  padding: 0 16px;
  color: #f5f5f5;
}
.hb-entry-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 18px;
}
.hb-entry-main {
  background: linear-gradient(180deg, rgba(0,0,0,0.5), rgba(0,0,0,0.75));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 16px;
  padding: 18px 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.hb-entry__title {
  margin: 0;
  font-size: clamp(26px, 4vw, 36px);
  text-align: center;
  color: var(--accent, #9b5cff);
}
.hb-entry__actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.hb-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 12px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  border: 1px solid transparent;
}
.hb-btn--ghost {
  border-color: var(--accent, #9b5cff);
  color: #f5f5f5;
  background: rgba(155, 92, 255, 0.15);
}
.hb-btn--danger {
  border-color: #ff6b6b;
  color: #fff;
  background: rgba(255, 107, 107, 0.2);
}
.hb-entry__image {
  border-radius: 14px;
  border: 1px solid var(--accent, #9b5cff);
  overflow: hidden;
  min-height: 260px;
  background: #0d0818;
}
.hb-entry__content {
  font-size: 16px;
  line-height: 1.65;
}
.hb-entry__content p {
  margin-bottom: 1em;
}
.hb-entry__meta {
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #cfd0e6;
}
.hb-entry__meta-dot { opacity: 0.6; }
.hb-entry-nav {
  margin-top: 8px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.hb-entry-nav a {
  flex: 1;
  text-align: center;
}
.hb-entry-sidebar {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.hb-panel {
  background: linear-gradient(180deg, rgba(0,0,0,0.45), rgba(0,0,0,0.8));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 14px;
  padding: 14px 16px;
  box-shadow: 0 10px 24px rgba(0,0,0,0.28);
}
.hb-panel__title {
  margin: 0 0 12px;
  color: var(--accent, #9b5cff);
  text-align: center;
}
.hb-search {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hb-search input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  background: #161224;
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 10px;
  color: #f5f5f5;
  box-sizing: border-box;
}
.hb-search button {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: #f5f5f5;
  cursor: pointer;
}
.hb-search__suggestions {
  margin-top: 8px;
  background: linear-gradient(180deg, rgba(0,0,0,0.4), rgba(0,0,0,0.7));
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 12px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hb-search__item {
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(22, 18, 36, 0.8);
  border: 1px solid var(--accent-dark, rgba(155, 92, 255, 0.35));
  color: #f5f5f5;
  text-decoration: none;
}
.hb-search__item strong {
  display: block;
  color: var(--accent, #9b5cff);
  margin-bottom: 4px;
}
.hb-search__item span {
  display: block;
  color: var(--muted, #cfd0e6);
  font-size: 13px;
}
.hb-search__empty {
  margin: 0;
  color: #cfd0e6;
  font-size: 13px;
}
.hb-section-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hb-section-nav a {
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  background: rgba(22, 18, 36, 0.8);
  color: #f5f5f5;
  text-decoration: none;
  transition: border-color 0.15s ease, transform 0.1s ease;
}
.hb-section-nav a.is-active {
  border-color: var(--accent, #9b5cff);
  background: rgba(22, 18, 36, 0.95);
}
.hb-section-nav a:hover { transform: translateY(-1px); }
.hb-recent {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hb-recent__item {
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(22, 18, 36, 0.8);
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  color: #f5f5f5;
  text-decoration: none;
}
.hb-recent__item strong {
  display: block;
  color: var(--accent, #9b5cff);
  margin-bottom: 4px;
}
.hb-recent__item span {
  display: block;
  color: #cfd0e6;
  font-size: 13px;
}
.hb-empty {
  margin: 0;
  color: #cfd0e6;
  text-align: center;
}
@media (max-width: 960px) {
  .hb-entry-grid { grid-template-columns: 1fr; }
}
</style>

<div class="hb-entry-page">
  <div class="hb-entry-grid">
    <main class="hb-entry-main">
      <h1 class="hb-entry__title"><?php the_title(); ?></h1>
      <?php if ( $edit_link || $delete_link ) : ?>
        <div class="hb-entry__actions">
          <?php if ( $edit_link ) : ?>
            <a class="hb-btn hb-btn--ghost" href="<?php echo esc_url( $edit_link ); ?>">Editar</a>
          <?php endif; ?>
          <?php if ( $delete_link ) : ?>
            <a class="hb-btn hb-btn--danger" href="<?php echo esc_url( $delete_link ); ?>" onclick="return confirm('¿Enviar esta entrada a la papelera?');">Borrar</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="hb-entry__content">
        <?php the_content(); ?>
      </div>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          document.querySelectorAll('.hb-entry__content img').forEach((img) => {
            if (!img.getAttribute('loading')) img.setAttribute('loading', 'lazy');
            if (!img.getAttribute('decoding')) img.setAttribute('decoding', 'async');
            if (!img.getAttribute('referrerpolicy')) img.setAttribute('referrerpolicy', 'no-referrer');
          });
        });
      </script>
      <div class="hb-entry__meta">
        <span><?php echo esc_html( $sections[ $section ] ?? 'Homebrew' ); ?></span>
        <span class="hb-entry__meta-dot">·</span>
        <span><?php echo esc_html( get_the_date() ); ?></span>
      </div>
      <div class="hb-entry-nav">
        <?php if ( $prev_post ) : ?>
          <a class="drak-btn" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">← <?php echo esc_html( get_the_title( $prev_post ) ); ?></a>
        <?php endif; ?>
        <?php if ( $next_post ) : ?>
          <a class="drak-btn" style="text-align:right;" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>"><?php echo esc_html( get_the_title( $next_post ) ); ?> →</a>
        <?php endif; ?>
      </div>
    </main>

    <aside class="hb-entry-sidebar">
      <div class="hb-panel">
        <h3 class="hb-panel__title">Buscar</h3>
        <form class="hb-search" method="get" action="<?php echo esc_url( $homebrew_url ?: home_url( '/' ) ); ?>">
          <input type="hidden" name="campaign_section" value="homebrew">
          <input type="hidden" name="hb_tab" value="<?php echo esc_attr( $section ); ?>">
          <input type="text" id="drak-hb-search-input" name="hb_search" placeholder="<?php echo esc_attr( $search_placeholder ); ?>" autocomplete="off">
          <button type="submit" class="drak-btn drak-btn--full">Buscar</button>
          <div class="hb-search__suggestions" aria-live="polite"></div>
        </form>
      </div>

      <div class="hb-panel">
        <h3 class="hb-panel__title">Homebrew</h3>
        <div class="hb-section-nav">
          <?php foreach ( $sections as $key => $label ) : ?>
            <?php
            $target = $latest_per_section[ $key ] ?? '';
            if ( ! $target && $homebrew_url ) {
                $target = add_query_arg( 'hb_tab', $key, $homebrew_url ) . '#' . $key;
            }
            ?>
            <a class="<?php echo $section === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $target ?: '#' ); ?>"><?php echo esc_html( $label ); ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="hb-panel">
        <h3 class="hb-panel__title">Últimas entradas</h3>
        <div class="hb-recent">
          <?php if ( $recent_query->have_posts() ) : ?>
            <?php
            while ( $recent_query->have_posts() ) :
                $recent_query->the_post();
                $excerpt = get_the_excerpt();
                if ( ! $excerpt ) {
                    $excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 22, '…' );
                } else {
                    $excerpt = wp_trim_words( $excerpt, 22, '…' );
                }
                ?>
                <a class="hb-recent__item" href="<?php the_permalink(); ?>">
                  <strong><?php the_title(); ?></strong>
                  <?php if ( $excerpt ) : ?>
                    <span><?php echo esc_html( $excerpt ); ?></span>
                  <?php endif; ?>
                </a>
            <?php endwhile; wp_reset_postdata(); ?>
          <?php else : ?>
            <p class="hb-empty">Sin entradas recientes.</p>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </div>
</div>

<?php
get_footer();
?>
<script>
(function(){
  const input = document.getElementById('drak-hb-search-input');
  const suggestions = document.querySelector('.hb-search__suggestions');
  const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
  const nonce = '<?php echo esc_js( wp_create_nonce( 'drak_homebrew_live_search' ) ); ?>';
  const section = '<?php echo esc_js( $section ); ?>';
  const campaignId = '<?php echo esc_js( $campaign_id ); ?>';
  if (!input || !suggestions || !ajaxUrl || !nonce || !section || !campaignId) {
    return;
  }

  const minChars = 2;
  const render = (items) => {
    suggestions.innerHTML = '';
    if (!items || !items.length) {
      const p = document.createElement('p');
      p.className = 'hb-search__empty';
      p.textContent = 'Sin resultados…';
      suggestions.appendChild(p);
      return;
    }
    const frag = document.createDocumentFragment();
    items.forEach((item) => {
      const a = document.createElement('a');
      a.className = 'hb-search__item';
      a.href = item.permalink;
      const title = document.createElement('strong');
      title.textContent = item.title || '';
      const excerpt = document.createElement('span');
      excerpt.textContent = item.excerpt || '';
      a.appendChild(title);
      a.appendChild(excerpt);
      frag.appendChild(a);
    });
    suggestions.appendChild(frag);
  };

  let timer = null;
  const debounce = (fn, delay = 300) => (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };

  const search = debounce((term) => {
    if (!term || term.length < minChars) {
      suggestions.innerHTML = '';
      return;
    }
    const body = new FormData();
    body.append('action', 'drak_homebrew_live_search');
    body.append('nonce', nonce);
    body.append('term', term);
    body.append('homebrew_section', section);
    body.append('campaign_id', campaignId);
    fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body,
    }).then(res => res.json()).then(json => {
      if (json && json.success) {
        render(json.data || []);
      } else {
        render([]);
      }
    }).catch(() => render([]));
  }, 350);

  input.addEventListener('input', (ev) => {
    search(ev.target.value.trim());
  });

  input.addEventListener('focus', () => {
    const val = input.value.trim();
    if (val.length >= minChars) {
      search(val);
    }
  });

  document.addEventListener('click', (ev) => {
    if (ev.target === input || suggestions.contains(ev.target)) {
      return;
    }
    suggestions.innerHTML = '';
  });
})();
</script>
