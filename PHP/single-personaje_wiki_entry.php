<?php
get_header();

$entry_id   = get_the_ID();
$parent_id  = (int) get_field( 'parent_personaje_wiki', $entry_id );
$section    = sanitize_key( get_field( 'section', $entry_id ) ?: 'origen' );
$sections   = [
    'origen'   => 'Origen',
    'aventura' => 'Aventura',
];
$parent_url   = $parent_id ? get_permalink( $parent_id ) : '';
$parent_title = $parent_id ? get_the_title( $parent_id ) : '';
$campaign_id  = $parent_id ? (int) get_field( 'campaign', $parent_id ) : 0;
$color        = $campaign_id ? (string) get_field( 'campaign_color', $campaign_id ) : '';
$accent       = $color ?: '#9b5cff';
$accent_dark  = function_exists( 'drak_campaign_hex_to_rgba' ) ? drak_campaign_hex_to_rgba( $accent, 0.25 ) : 'rgba(155, 92, 255, 0.25)';
$back_link    = $parent_url ? $parent_url . '#' . $section : home_url( '/' );
$can_edit     = function_exists( 'drak_user_can_edit_personaje_wiki_entry' ) ? drak_user_can_edit_personaje_wiki_entry( $entry_id ) : false;
$updated      = isset( $_GET['pw_entry_updated'] );
?>

<style>
.pw-entry-page {
  --accent: <?php echo esc_html( $accent ); ?>;
  --accent-dark: <?php echo esc_html( $accent_dark ); ?>;
  max-width: 1100px;
  margin: 32px auto 48px;
  padding: 0 16px;
  color: #f5f5f5;
}
.pw-entry-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 18px;
}
.pw-entry-main {
  background: linear-gradient(180deg, rgba(0,0,0,0.5), rgba(0,0,0,0.78));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 14px;
  padding: 18px 20px 22px;
  box-shadow: 0 10px 26px rgba(0,0,0,0.28);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pw-entry__breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #cfd0e6;
}
.pw-entry__breadcrumbs a { color: #fff; text-decoration: none; }
.pw-entry__title {
  margin: 0;
  text-align: center;
  font-size: clamp(24px, 4vw, 34px);
  color: var(--accent, #9b5cff);
}
.pw-entry__meta {
  display: flex;
  gap: 8px;
  justify-content: center;
  color: #cfd0e6;
  font-size: 14px;
}
.pw-entry__actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}
.pw-entry__actions .drak-btn--danger {
  border-color: #ff6b6b;
  background: rgba(255, 107, 107, 0.2);
}
.pw-entry-edit {
  margin-top: 6px;
  padding: 12px;
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 12px;
  background: rgba(15, 10, 22, 0.75);
}
.pw-entry-edit .pw-field {
  margin-bottom: 10px;
}
.pw-entry-edit label {
  display: block;
  margin-bottom: 4px;
}
.pw-entry-edit input[type="text"],
.pw-entry-edit select {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  background: #161224;
  border: 1px solid var(--accent, #9b5cff);
  color: #fff;
  box-sizing: border-box;
}
.pw-entry__content {
  font-size: 16px;
  line-height: 1.65;
}
.pw-entry__content p { margin-bottom: 1em; }
.pw-entry__content img {
  max-width: 100%;
  height: auto;
  display: block;
}
.pw-entry-alert {
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(60, 209, 121, 0.16);
  border: 1px solid rgba(60, 209, 121, 0.5);
  color: #d6ffe6;
  text-align: center;
}
.pw-entry-sidebar {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pw-entry-panel {
  background: linear-gradient(180deg, rgba(0,0,0,0.45), rgba(0,0,0,0.8));
  border: 1px solid var(--accent-dark, rgba(155,92,255,0.25));
  border-radius: 12px;
  padding: 12px;
}
.pw-entry-panel h3 {
  margin: 0 0 8px;
  color: var(--accent, #9b5cff);
  text-align: center;
}
.pw-entry-panel p {
  margin: 6px 0;
  color: #d8d8f0;
}
@media (max-width: 960px) {
  .pw-entry-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pw-entry-page">
  <div class="pw-entry-grid">
    <main class="pw-entry-main">
      <div class="pw-entry__breadcrumbs">
        <?php if ( $parent_url ) : ?>
          <a class="drak-btn" href="<?php echo esc_url( $back_link ); ?>">← Volver a <?php echo esc_html( $parent_title ); ?></a>
        <?php endif; ?>
      </div>

      <h1 class="pw-entry__title"><?php the_title(); ?></h1>

      <div class="pw-entry__meta">
        <span><?php echo esc_html( $sections[ $section ] ?? 'Nota' ); ?></span>
        <span>·</span>
        <span><?php echo esc_html( get_the_date() ); ?></span>
      </div>

      <?php if ( $updated ) : ?>
        <div class="pw-entry-alert">Nota actualizada correctamente.</div>
      <?php endif; ?>

      <?php if ( $can_edit ) : ?>
        <div class="pw-entry__actions">
          <button class="drak-btn" type="button" data-toggle-edit>Editar</button>
          <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar esta nota?');">
            <?php wp_nonce_field( 'pw_entry_update', 'pw_entry_nonce' ); ?>
            <input type="hidden" name="pw_entry_id" value="<?php echo esc_attr( $entry_id ); ?>">
            <input type="hidden" name="pw_entry_action" value="delete">
            <input type="hidden" name="pw_entry_section" value="<?php echo esc_attr( $section ); ?>">
            <button class="drak-btn drak-btn--danger" type="submit">Eliminar</button>
          </form>
        </div>

        <form method="post" class="pw-entry-edit" data-edit-form hidden>
          <div class="pw-field">
            <label for="pw_entry_title">Título</label>
            <input type="text" id="pw_entry_title" name="pw_entry_title" value="<?php echo esc_attr( get_the_title( $entry_id ) ); ?>">
          </div>
          <div class="pw-field">
            <label for="pw_entry_section">Sección</label>
            <select id="pw_entry_section" name="pw_entry_section">
              <?php foreach ( $sections as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $section, $key ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pw-field">
            <?php
            wp_editor(
                get_post_field( 'post_content', $entry_id ),
                'pw_entry_content_editor',
                [
                    'textarea_name' => 'pw_entry_content',
                    'media_buttons' => true,
                    'teeny'         => false,
                    'quicktags'     => true,
                    'editor_height' => 240,
                ]
            );
            ?>
          </div>
          <?php wp_nonce_field( 'pw_entry_update', 'pw_entry_nonce' ); ?>
          <input type="hidden" name="pw_entry_id" value="<?php echo esc_attr( $entry_id ); ?>">
          <input type="hidden" name="pw_entry_action" value="update">
          <button type="submit" class="drak-btn drak-btn--full">Guardar cambios</button>
        </form>
      <?php endif; ?>

      <div class="pw-entry__content">
        <?php the_content(); ?>
      </div>
    </main>

    <aside class="pw-entry-sidebar">
      <div class="pw-entry-panel">
        <h3>Nota de personaje</h3>
        <p><strong>Personaje:</strong> <?php echo $parent_title ? esc_html( $parent_title ) : 'Sin vínculo'; ?></p>
        <p><strong>Sección:</strong> <?php echo esc_html( $sections[ $section ] ?? 'Nota' ); ?></p>
        <?php if ( $parent_url ) : ?>
          <p><a class="drak-btn drak-btn--full" href="<?php echo esc_url( $back_link ); ?>">Ver personaje</a></p>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<?php
get_footer();
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-toggle-edit]');
  const form = document.querySelector('[data-edit-form]');
  if (toggle && form) {
    toggle.addEventListener('click', () => {
      form.hidden = !form.hidden;
      if (!form.hidden) {
        const firstInput = form.querySelector('input, textarea, select');
        if (firstInput) { firstInput.focus(); }
      }
    });
  }
  document.querySelectorAll('.pw-entry__content img').forEach((img) => {
    if (!img.getAttribute('loading')) img.setAttribute('loading', 'lazy');
    if (!img.getAttribute('decoding')) img.setAttribute('decoding', 'async');
    if (!img.getAttribute('referrerpolicy')) img.setAttribute('referrerpolicy', 'no-referrer');
  });
});
</script>
