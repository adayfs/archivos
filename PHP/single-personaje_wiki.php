<?php
get_header();

if ( ! function_exists( 'drak_front_get_dark_overlay' ) ) {
    function drak_front_get_dark_overlay( $hex, $alpha = 0.35 ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if ( strlen( $hex ) !== 6 ) {
            return 'rgba(0,0,0,' . floatval( $alpha ) . ')';
        }
        $int = hexdec( $hex );
        $r = ( $int >> 16 ) & 255;
        $g = ( $int >> 8 ) & 255;
        $b = $int & 255;
        return sprintf( 'rgba(%d,%d,%d,%.2f)', $r, $g, $b, max( 0, min( 1, $alpha ) ) );
    }
}

$pw_id       = get_the_ID();
$campaign_id = (int) get_field( 'campaign', $pw_id );
$main_img_id = (int) get_field( 'hero_image', $pw_id );
if ( ! $main_img_id ) {
    $thumb = get_post_thumbnail_id( $pw_id );
    if ( $thumb ) {
        $main_img_id = $thumb;
    }
}
$accent      = $campaign_id ? (string) get_field( 'campaign_color', $campaign_id ) : '#9b5cff';
$tab         = isset( $_GET['pw_tab'] ) ? sanitize_key( $_GET['pw_tab'] ) : 'origen';
$tab         = in_array( $tab, [ 'origen', 'aventura', 'galeria' ], true ) ? $tab : 'origen';

function drak_pw_get_entries( $pw_id, $section ) {
    return new WP_Query( [
        'post_type'      => 'personaje_wiki_entry',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'   => 'parent_personaje_wiki',
                'value' => $pw_id,
            ],
            [
                'key'   => 'section',
                'value' => $section,
            ],
        ],
    ] );
}
?>

<style>
.pw-wrapper {
  --accent: #8224e3;
  --accent-weak: rgba(130, 36, 227, 0.35);
  min-height: 900px;
  width: 100%;
  max-width: 1000px;
  margin: 30px auto;
  padding: 0 20px 40px;
  color: #f5f5f5;
}
.pw-hero {
  background: #0d0818;
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  min-height: 520px;
}
.pw-hero__media {
  flex: 1 1 auto;
  min-height: 360px;
  display: flex;
}
.pw-hero__media img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}
.pw-hero__body {
  padding: 16px 20px 20px;
  background: linear-gradient(180deg, rgba(0,0,0,0.55), rgba(0,0,0,0.9));
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 0 0 auto;
}
.pw-hero__title {
  margin: 0 0 6px;
  font-size: clamp(28px, 4vw, 42px);
  color: var(--accent);
  text-align: center;
}
.pw-hero__links {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-start;
  align-items: center;
}
.pw-link {
  text-decoration: none;
  font-size: 14px;
}
.pw-tabs {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin: 20px 0;
}
.pw-tab {
  text-align: center;
  padding: 14px 10px;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  text-decoration: none;
  color: #fff;
  background: rgba(0,0,0,0.5);
}
.pw-tab.is-active {
  border-color: var(--accent);
  box-shadow: 0 4px 12px rgba(0,0,0,0.35);
}
.pw-section-title {
  text-align: center;
  color: var(--accent);
  margin: 0 0 12px;
}
.pw-entry {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 12px;
  margin-bottom: 12px;
  background: rgba(0,0,0,0.4);
}
.pw-entry h4 {
  margin: 0 0 6px;
}
.pw-note-panel {
  margin-top: 12px;
  border: 1px dashed var(--accent);
  padding: 12px;
  border-radius: 10px;
  background: rgba(0,0,0,0.35);
}
.pw-note-panel form input,
.pw-note-panel form select,
.pw-note-panel form textarea {
  width: 100%;
  margin-bottom: 8px;
}
.pw-gallery-empty,
.pw-empty {
  text-align: center;
  color: #ccc;
}
.pw-sections [data-section] { display: none; }
.pw-sections [data-section].is-active { display: block; }
.pw-note-form .pw-field {
  margin-bottom: 8px;
}
.pw-note-form label {
  display: block;
  margin-bottom: 4px;
}
.pw-note-form select,
.pw-note-form input[type="text"],
.pw-note-form textarea,
.pw-note-form .wp-editor-wrap {
  width: 100%;
  background: #161224;
  border: 1px solid #fe00f1;
  color: #fff;
  box-sizing: border-box;
}
.pw-note-form input[type="text"],
.pw-note-form select {
  padding: 10px;
  border-radius: 8px;
}
.pw-note-form .wp-editor-wrap {
  border-radius: 8px;
  overflow: hidden;
}
.pw-card.pw-section {
  background: #161224;
  min-height: 600px;
  overflow-y: auto;
}
@media (max-width: 720px) {
  .pw-wrapper {
    margin: 20px auto;
    padding: 0 12px 32px;
  }
  .pw-hero {
    min-height: 380px;
  }
  .pw-hero__media {
    min-height: 220px;
  }
  .pw-hero__title {
    font-size: clamp(22px, 7vw, 30px);
  }
  .pw-link {
    font-size: 13px;
    padding: 8px 10px;
  }
  .pw-tabs {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
}
</style>

<div class="pw-wrapper">
  <div class="pw-hero">
    <?php if ( $main_img_id ) : ?>
      <div class="pw-hero__media"><?php echo wp_get_attachment_image( $main_img_id, 'large' ); ?></div>
    <?php endif; ?>
    <div class="pw-hero__body">
      <h1 class="pw-hero__title"><?php the_title(); ?></h1>
      <div class="pw-hero__links">
        <?php if ( $campaign_id ) : ?>
          <a class="pw-link drak-btn" href="<?php echo esc_url( get_permalink( $campaign_id ) ); ?>">← Volver a la campaña</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="pw-tabs">
    <button class="pw-tab drak-btn <?php echo $tab === 'origen' ? 'is-active' : ''; ?>" type="button" data-pw-tab="origen">Origen</button>
    <button class="pw-tab drak-btn <?php echo $tab === 'aventura' ? 'is-active' : ''; ?>" type="button" data-pw-tab="aventura">Aventura</button>
    <button class="pw-tab drak-btn <?php echo $tab === 'galeria' ? 'is-active' : ''; ?>" type="button" data-pw-tab="galeria">Galería</button>
    <button class="pw-tab drak-btn" type="button" data-pw-tab="notas">Notas</button>
  </div>

  <section class="pw-card pw-section pw-sections">
    <div class="pw-section-block <?php echo $tab === 'origen' ? 'is-active' : ''; ?>" data-section="origen">
      <h3 class="pw-section-title">Origen</h3>
      <div class="pw-entries" data-section-list="origen">
      <?php
      $entries = drak_pw_get_entries( $pw_id, 'origen' );
      if ( $entries->have_posts() ) :
        while ( $entries->have_posts() ) :
          $entries->the_post();
          ?>
          <article class="pw-entry">
            <h4><?php the_title(); ?></h4>
            <small><?php echo esc_html( get_the_date() ); ?></small>
            <div><?php the_excerpt(); ?></div>
            <a class="pw-link" href="<?php the_permalink(); ?>">Leer más</a>
          </article>
          <?php
        endwhile;
        wp_reset_postdata();
      else :
        echo '<p class="pw-empty">No hay entradas en esta sección.</p>';
      endif;
      ?>
      </div>
    </div>
    <div class="pw-section-block <?php echo $tab === 'aventura' ? 'is-active' : ''; ?>" data-section="aventura">
      <h3 class="pw-section-title">Aventura</h3>
      <div class="pw-entries" data-section-list="aventura">
      <?php
      $entries = drak_pw_get_entries( $pw_id, 'aventura' );
      if ( $entries->have_posts() ) :
        while ( $entries->have_posts() ) :
          $entries->the_post();
          ?>
          <article class="pw-entry">
            <h4><?php the_title(); ?></h4>
            <small><?php echo esc_html( get_the_date() ); ?></small>
            <div><?php the_excerpt(); ?></div>
            <a class="pw-link" href="<?php the_permalink(); ?>">Leer más</a>
          </article>
          <?php
        endwhile;
        wp_reset_postdata();
      else :
        echo '<p class="pw-empty">No hay entradas en esta sección.</p>';
      endif;
      ?>
      </div>
    </div>
    <div class="pw-section-block <?php echo $tab === 'galeria' ? 'is-active' : ''; ?>" data-section="galeria">
      <h3 class="pw-section-title">Galería</h3>
      <div class="pw-gallery">
        <?php
        if ( function_exists( 'drak_render_gallery_for_post' ) ) {
            ob_start();
            drak_render_gallery_for_post( $pw_id );
            $gallery_html = trim( ob_get_clean() );
            echo $gallery_html ? $gallery_html : '<p class="pw-gallery-empty">Este personaje aún no tiene imágenes en la galería.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<p class="pw-gallery-empty">La galería no está disponible.</p>';
        }
        ?>
      </div>
    </div>
    <div class="pw-section-block" data-section="notas">
      <h3 class="pw-section-title" style="margin-bottom:8px;">Notas</h3>
      <form method="post" class="pw-note-form" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
        <input type="hidden" name="action" value="drak_pw_add_entry">
        <input type="hidden" name="pw_id" value="<?php echo esc_attr( $pw_id ); ?>">
        <div class="pw-field">
          <label for="pw_section">Sección</label>
          <select id="pw_section" name="pw_section" required>
            <option value="origen">Origen</option>
            <option value="aventura">Aventura</option>
          </select>
        </div>
        <div class="pw-field">
          <label for="pw_title">Título</label>
          <input type="text" id="pw_title" name="pw_title" maxlength="120" required>
        </div>
        <div class="pw-field">
          <?php
          wp_editor(
              '',
              'drak_pw_content',
              [
                  'textarea_name' => 'pw_content',
                  'media_buttons' => true,
                  'teeny'         => false,
                  'quicktags'     => true,
                  'editor_height' => 250,
              ]
          );
          ?>
        </div>
        <?php wp_nonce_field( 'pw_save_note', 'pw_note_nonce' ); ?>
        <button type="submit" class="pw-link drak-btn drak-btn--full">Guardar nota</button>
        <p class="pw-empty pw-note-feedback" style="display:none;"></p>
      </form>
    </div>
  </section>

  <?php
  $prev = get_adjacent_post( false, '', true, '', false );
  $next = get_adjacent_post( false, '', false, '', false );
  if ( $prev || $next ) :
  ?>
    <div class="pw-nav-adjacent">
      <div class="pw-nav-prev">
        <?php if ( $prev ) : ?>
          <a href="<?php echo esc_url( get_permalink( $prev->ID ) ); ?>">← Personaje anterior</a>
        <?php endif; ?>
      </div>
      <div class="pw-nav-next" style="text-align:right;">
        <?php if ( $next ) : ?>
          <a href="<?php echo esc_url( get_permalink( $next->ID ) ); ?>">Personaje siguiente →</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php
get_footer();
?>
<script>
(function(){
  const tabs = document.querySelectorAll('.pw-tab');
  const sections = document.querySelectorAll('.pw-section-block');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-pw-tab');
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
  const hash = window.location.hash.replace('#','');
  if (hash) {
    const btn = document.querySelector('.pw-tab[data-pw-tab="'+hash+'"]');
    if (btn) { btn.click(); }
  }

  const form = document.querySelector('.pw-note-form');
  if (form) {
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      const ajaxUrl = form.getAttribute('data-ajax-url');
      const feedback = form.querySelector('.pw-note-feedback');
      if (window.tinymce) { tinymce.triggerSave(); }
      if (!ajaxUrl) { form.submit(); return; }
      const data = new FormData(form);
      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
      }).then(res => res.json()).then(json => {
        if (json && json.success && json.data && json.data.html) {
          const section = form.querySelector('[name="pw_section"]').value;
          const list = document.querySelector('[data-section-list="'+section+'"]');
          if (list) { list.insertAdjacentHTML('afterbegin', json.data.html); }
          form.reset();
          if (window.tinymce) {
            const ed = tinymce.get('drak_pw_content');
            if (ed) { ed.setContent(''); }
          }
          if (feedback) { feedback.textContent = 'Nota guardada correctamente.'; feedback.style.display = 'block'; }
          const btnTarget = document.querySelector('.pw-tab[data-pw-tab="'+section+'"]');
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
