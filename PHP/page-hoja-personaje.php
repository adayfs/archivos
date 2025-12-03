<?php
/**
 * Template Name: Hoja Personaje
 */

get_header();

// Igual que en el inventario: cogemos el slug del personaje de la URL
$slug = get_query_var('personaje_slug');
$personaje = get_page_by_path($slug, OBJECT, 'personaje');

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

$can_edit_sheet = is_user_logged_in() && drak_user_can_manage_personaje( $personaje->ID );
?>


<?php wp_enqueue_media(); ?>

<div class="contenido-hoja-personaje">

  <?php
    // Imagen destacada del personaje
    $imagen_url    = function_exists( 'drak_get_personaje_hero_image_url' )
      ? drak_get_personaje_hero_image_url( $personaje->ID, 'hoja', $nav_images['hoja'] )
      : get_the_post_thumbnail_url($personaje->ID, 'large');
    // Página principal del personaje (/personaje/slug)
    $personaje_url = get_permalink($personaje->ID);
  ?>

  <div class="personaje-hero">
    <div class="personaje-hero__image" data-hero-image style="min-height:350px; background-image:url('<?php echo esc_url( $imagen_url ?: $nav_images['hoja'] ); ?>');"></div>
    <div class="personaje-hero__actions">
      <button type="button" class="personaje-hero__change" data-post-id="<?php echo esc_attr( $personaje->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-hero-context="hoja">Cambiar imagen</button>
    </div>
  </div>

  <?php if ( $can_edit_sheet ) : ?>
    <div class="hoja-toolbar">
      <button type="button" id="btn-sheet-modal" class="btn-hoja-edit">
        Editar Hoja.pj
      </button>
      <button type="button" id="btn-feat-modal" class="btn-hoja-edit">
        Añadir feat
      </button>
    </div>
  <?php endif; ?>

  <?php echo renderizar_hoja_personaje($personaje->ID); ?>

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
