<?php
/**
 * Template Name: Grimorio Personaje
 */

global $post;
get_header();

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
);

$nav_images = array(
    'hoja'       => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
    'inventario' => 'https://adayfs.com/wp-content/uploads/2025/11/mochila.webp',
    'grimorio'   => 'https://adayfs.com/wp-content/uploads/2025/11/grimorio.webp',
);
?>

<div class="contenido-grimorio">
  <h2 class="titulo-grimorio">
    Grimorio de <?php echo esc_html($personaje->post_title); ?>
  </h2>

  <?php
    $imagen_url    = get_the_post_thumbnail_url($personaje->ID, 'medium');
    $personaje_url = get_permalink($personaje->ID);

  ?>

  <div class="personaje-nav">
    <a class="personaje-nav-link"
       href="<?php echo esc_url($nav_links['hoja']); ?>"
       aria-label="Ir a la Hoja de Personaje">
      <div class="personaje-nav-button"
           style="background-image:url('<?php echo esc_url($nav_images['hoja']); ?>');"></div>
    </a>

    <?php if ($imagen_url) : ?>
      <a href="<?php echo esc_url($personaje_url); ?>" class="personaje-avatar-link" aria-label="Volver a la ficha del personaje">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url($imagen_url); ?>');"></div>
      </a>
    <?php endif; ?>

    <a class="personaje-nav-link"
       href="<?php echo esc_url($nav_links['inventario']); ?>"
       aria-label="Ir al Inventario del personaje">
      <div class="personaje-nav-button"
           style="background-image:url('<?php echo esc_url($nav_images['inventario']); ?>');"></div>
    </a>
  </div>

  <?php echo renderizar_grimorio_personaje($personaje->ID); ?>
</div>

<?php
get_footer();
