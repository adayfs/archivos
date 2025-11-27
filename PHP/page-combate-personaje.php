<?php
/**
 * Template Name: Combate Personaje
 */

get_header();

$slug      = get_query_var( 'personaje_slug' );
$personaje = $slug ? get_page_by_path( $slug, OBJECT, 'personaje' ) : null;

if ( ! $personaje ) {
	echo '<p style="color: white; text-align: center;">Personaje no encontrado.</p>';
	get_footer();
	exit;
}

$personaje_slug = $personaje->post_name;

$nav_links = array(
	'hoja'      => home_url( '/hoja-personaje/' . $personaje_slug ),
	'inventario'=> home_url( '/inventario/' . $personaje_slug ),
	'grimorio'  => home_url( '/grimorio/' . $personaje_slug ),
	'combate'   => home_url( '/combate/' . $personaje_slug ),
);

$nav_images = array(
	'hoja'       => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
	'inventario' => 'https://adayfs.com/wp-content/uploads/2025/11/mochila.webp',
	'grimorio'   => 'https://adayfs.com/wp-content/uploads/2025/11/grimorio.webp',
	'combate'    => 'https://adayfs.com/wp-content/uploads/2025/11/hj-pj.webp',
);
?>

<div class="contenido-hoja-personaje">
  <h2 class="titulo-hoja-personaje">
    Combate de <?php echo esc_html( $personaje->post_title ); ?>
  </h2>

  <?php
    $imagen_url    = get_the_post_thumbnail_url( $personaje->ID, 'medium' );
    $personaje_url = get_permalink( $personaje->ID );
  ?>

  <div class="personaje-nav">
    <?php if ( $imagen_url ) : ?>
      <a href="<?php echo esc_url( $personaje_url ); ?>" class="personaje-avatar-link" aria-label="Volver a la ficha del personaje">
        <div class="personaje-avatar"
             style="background-image:url('<?php echo esc_url( $imagen_url ); ?>');"></div>
      </a>
    <?php endif; ?>
  </div>

  <div class="personaje-botones">
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['hoja'] ); ?>">Hoja de Personaje</a>
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['inventario'] ); ?>">Inventario</a>
    <a class="personaje-boton" href="<?php echo esc_url( $nav_links['grimorio'] ); ?>">Grimorio</a>
  </div>

  <?php echo renderizar_combate_personaje( $personaje->ID ); ?>
</div>

<?php
get_footer();
