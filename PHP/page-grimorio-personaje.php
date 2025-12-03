<?php
/**
 * Template Name: Grimorio Personaje
 */

global $post;
get_header();
wp_enqueue_media();

$slug = get_query_var('personaje_slug');
$personaje = $slug ? get_page_by_path($slug, OBJECT, 'personaje') : null;

if (!$personaje) {
    echo '<p style="color: white; text-align: center;">Personaje no encontrado.</p>';
    get_footer();
    exit;
}

$personaje_slug = $personaje->post_name;

$nav_links = array(
    'hoja'       => home_url('/hoja-personaje/' . $personaje_slug),
    'inventario' => home_url('/inventario/' . $personaje_slug),
    'grimorio'   => home_url('/grimorio/' . $personaje_slug),
    'combate'    => home_url('/combate/' . $personaje_slug),
);

$nav_images = array(
    'hoja'       => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
    'inventario' => 'https://adayfs.com/wp-content/uploads/2025/11/mochila.webp',
    'grimorio'   => 'https://adayfs.com/wp-content/uploads/2025/11/grimorio.webp',
    'combate'    => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
);
?>

<div class="contenido-grimorio">

  <?php
    $imagen_url    = function_exists( 'drak_get_personaje_hero_image_url' )
      ? drak_get_personaje_hero_image_url( $personaje->ID, 'grimorio', $nav_images['grimorio'] )
      : get_the_post_thumbnail_url($personaje->ID, 'large');
    $personaje_url = get_permalink($personaje->ID);

  ?>

  <div class="personaje-hero">
    <div class="personaje-hero__image" data-hero-image style="min-height:350px; background-image:url('<?php echo esc_url( $imagen_url ?: $nav_images['grimorio'] ); ?>');"></div>
    <div class="personaje-hero__actions">
      <button type="button" class="personaje-hero__change" data-post-id="<?php echo esc_attr( $personaje->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-hero-context="grimorio">Cambiar imagen</button>
    </div>
  </div>

  <?php
    $grimorio_class_id    = get_field( 'clase', $personaje->ID );
    $grimorio_subclass_id = get_field( 'subclase', $personaje->ID );
    $grimorio_level       = intval( get_field( 'nivel', $personaje->ID ) );

    // Mostrar u ocultar módulos de hechizos según clase/subclase (marciales sin magia salvo excepciones).
    $class_raw = is_array( $grimorio_class_id ) ? ( $grimorio_class_id['value'] ?? $grimorio_class_id['label'] ?? '' ) : $grimorio_class_id;
    $sub_raw   = is_array( $grimorio_subclass_id ) ? ( $grimorio_subclass_id['value'] ?? $grimorio_subclass_id['label'] ?? '' ) : $grimorio_subclass_id;
    $class_slug = sanitize_title( $class_raw );
    $sub_slug   = $sub_raw ? sanitize_title( $sub_raw ) : '';

    $martial_classes = [
      'barbarian','barbarian-phb-classic',
      'fighter','fighter-phb-classic',
      'rogue','rogue-phb-classic',
      'monk','monk-phb-classic',
    ];
    $show_spellcasting_blocks = true;
    if ( in_array( $class_slug, $martial_classes, true ) ) {
        $show_spellcasting_blocks = false;
        // Excepciones: Eldritch Knight y Arcane Trickster sí muestran grimorio.
        if ( $class_slug === 'fighter' && strpos( $sub_slug, 'eldritch-knight' ) !== false ) {
            $show_spellcasting_blocks = true;
        }
        if ( $class_slug === 'rogue' && strpos( $sub_slug, 'arcane-trickster' ) !== false ) {
            $show_spellcasting_blocks = true;
        }
    }
  ?>

  <?php if ( function_exists( 'drak_recursos_render_block' ) ) : ?>
    <?php echo drak_recursos_render_block( $personaje->ID ); ?>
  <?php endif; ?>

  <?php if ( $show_spellcasting_blocks ) : ?>
    <?php $caster_stats = drak_get_grimorio_spellcasting_stats($personaje->ID); ?>
    <div class="grimorio-caster-stats">
      <div class="grimorio-caster-item">
        <span class="grimorio-caster-label">Atributo lanzador</span>
        <div class="personaje-nav-button grimorio-caster-badge">
          <span><?php echo esc_html($caster_stats['ability_short']); ?></span>
        </div>
        <p class="grimorio-caster-value"><?php echo esc_html($caster_stats['ability_display']); ?></p>
      </div>
      <div class="grimorio-caster-item">
        <span class="grimorio-caster-label">CD de conjuro</span>
        <div class="personaje-nav-button grimorio-caster-badge">
          <span><?php echo esc_html($caster_stats['spell_dc']); ?></span>
        </div>
        <p class="grimorio-caster-value">8 + Mod + Prof</p>
      </div>
      <div class="grimorio-caster-item">
        <span class="grimorio-caster-label">Ataque de conjuro</span>
        <div class="personaje-nav-button grimorio-caster-badge">
          <span><?php echo esc_html($caster_stats['spell_attack']); ?></span>
        </div>
        <p class="grimorio-caster-value">Mod + Prof</p>
      </div>
    </div>

    <?php echo drak_render_spell_search_module(); ?>

    <?php echo renderizar_grimorio_personaje($personaje->ID); ?>
  <?php endif; ?>

  <?php
    $auto_prepared = drak_get_auto_prepared_spells( $grimorio_class_id, $grimorio_subclass_id, $grimorio_level );
    $has_auto_prepared = false;
    foreach ( $auto_prepared as $group ) {
        foreach ( $group as $spells ) {
            if ( ! empty( $spells ) ) {
                $has_auto_prepared = true;
                break 2;
            }
        }
    }
    if ( $has_auto_prepared ) {
        echo drak_render_grimorio_auto_prepared_section( $auto_prepared, $grimorio_subclass_id );
    }
  ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.querySelector('.personaje-hero__change');
  const hero = document.querySelector('[data-hero-image]');
  if (!btn || !hero || !(window.wp && wp.media)) return;
  const ajaxUrl = btn.dataset.ajaxUrl;
  const postId = btn.dataset.postId;
  btn.addEventListener('click', () => {
    const frame = wp.media({
      title: 'Selecciona imagen del personaje',
      multiple: false,
      library: { type: 'image' },
      button: { text: 'Usar imagen' },
    });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      hero.style.backgroundImage = `url('${attachment.url}')`;
      if (!ajaxUrl || !postId) return;
      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'drak_set_personaje_image',
          post_id: postId,
          attachment_id: attachment.id,
          context: btn.dataset.heroContext || '',
        }),
      }).catch(() => {});
    });
    frame.open();
  });
});
</script>


<?php
get_footer();
