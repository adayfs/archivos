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
);

$nav_images = array(
    'hoja'       => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
    'inventario' => 'https://adayfs.com/wp-content/uploads/2025/11/mochila.webp',
    'grimorio'   => 'https://adayfs.com/wp-content/uploads/2025/11/grimorio.webp',
);
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
    <a class="personaje-nav-link"
       href="<?php echo esc_url($nav_links['inventario']); ?>"
       aria-label="Ir al Inventario del personaje">
      <div class="personaje-nav-button"
           style="background-image:url('<?php echo esc_url($nav_images['inventario']); ?>');"></div>
    </a>

    <?php if ($imagen_url) : ?>
      <a href="<?php echo esc_url($personaje_url); ?>" class="personaje-avatar-link" aria-label="Volver a la ficha del personaje">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url($imagen_url); ?>');"></div>
      </a>
    <?php endif; ?>

    <a class="personaje-nav-link"
       href="<?php echo esc_url($nav_links['grimorio']); ?>"
       aria-label="Ir al Grimorio del personaje">
      <div class="personaje-nav-button"
           style="background-image:url('<?php echo esc_url($nav_images['grimorio']); ?>');"></div>
    </a>
  </div>

  <div class="hoja-toolbar">
    <button type="button" id="btn-sheet-modal" class="btn-hoja-edit">
      Editar Hoja.pj
    </button>
  </div>

  <?php echo renderizar_hoja_personaje($personaje->ID); ?>

  <section class="class-reference-module" id="class-reference-module" data-class-reference>
    <header class="class-reference-module__header">
      <h3>Referencia de clase</h3>
      <p>Consulta la progresión oficial de tu clase y los hechizos preparados por nivel.</p>
    </header>
    <div class="class-reference-module__body">
      <p class="class-reference-module__hint">Selecciona clase y subclase para cargar la información.</p>
    </div>
  </section>
</div>


<?php
get_footer();
