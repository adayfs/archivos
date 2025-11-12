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
?>


<div class="contenido-hoja-personaje">
  <h2 class="titulo-hoja-personaje">
    Hoja de <?php echo esc_html($personaje->post_title); ?>
  </h2>

  <?php
    // Imagen destacada del personaje
    $imagen_url    = get_the_post_thumbnail_url($personaje->ID, 'medium');
    // Página principal del personaje (/personaje/slug)
    $personaje_url = get_permalink($personaje->ID);

    if ($imagen_url) : ?>
      <a href="<?php echo esc_url($personaje_url); ?>" class="personaje-avatar-link">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url($imagen_url); ?>');"></div>
      </a>
  <?php endif; ?>

  <?php echo renderizar_hoja_personaje($personaje->ID); ?>
</div>


<?php
get_footer();
