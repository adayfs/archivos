<?php
/**
 * Template Name: Inventario Personaje
 */

get_header();

// Obtener slug de personaje desde URL
$slug = basename(get_permalink());
$personaje = get_page_by_path($slug, OBJECT, 'personaje');

if (!$personaje) {
    echo '<p style="color: white; text-align: center;">Personaje no encontrado.</p>';
    get_footer();
    exit;
}
?>

<div class="contenido-inventario">
  <h2 class="titulo-inventario">Inventario de <?php echo esc_html($personaje->post_title); ?></h2>
  <?php echo renderizar_inventario_personaje($personaje->ID); ?>
</div>

<style>
.contenido-inventario {
  max-width: 600px;
  margin: 3rem auto;
  padding: 2rem;
  background-color: rgba(0,0,0,0.4);
  border-radius: 1rem;
  color: white;
}

.titulo-inventario {
  text-align: center;
  margin-bottom: 2rem;
  font-size: 1.8rem;
  color: #ff69ff;
}
</style>

<?php get_footer(); ?>
