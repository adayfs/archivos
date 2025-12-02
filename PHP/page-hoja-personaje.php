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


<div class="contenido-hoja-personaje">
  <h2 class="titulo-hoja-personaje">
    Hoja de <?php echo esc_html($personaje->post_title); ?>
  </h2>

  <?php
    // Imagen destacada del personaje
    $imagen_url    = get_the_post_thumbnail_url($personaje->ID, 'medium');
    // Página principal del personaje (/personaje/slug)
    $personaje_url = get_permalink($personaje->ID);
  ?>

  <div class="personaje-nav">
    <?php if ($imagen_url) : ?>
      <a href="<?php echo esc_url($personaje_url); ?>" class="personaje-avatar-link" aria-label="Volver a la ficha del personaje">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url($imagen_url); ?>');"></div>
      </a>
    <?php endif; ?>
  </div>

  <div class="personaje-botones">
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['inventario'] ); ?>">Inventario</a>
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['grimorio'] ); ?>">Grimorio</a>
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['combate'] ); ?>">Mod Combate</a>
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


<?php
get_footer();
