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
?>

<div class="contenido-grimorio">
  <h2 class="titulo-grimorio">
    Grimorio de <?php echo esc_html($personaje->post_title); ?>
  </h2>

  <?php
    $imagen_url    = get_the_post_thumbnail_url($personaje->ID, 'medium');
    $personaje_url = get_permalink($personaje->ID);

    if ($imagen_url) : ?>
      <a href="<?php echo esc_url($personaje_url); ?>" class="personaje-avatar-link">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url($imagen_url); ?>');"></div>
      </a>
  <?php endif; ?>

  <?php echo renderizar_grimorio_personaje($personaje->ID); ?>
</div>

<?php
get_footer();
