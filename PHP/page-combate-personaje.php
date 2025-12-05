<?php
/**
 * Template Name: Combate Personaje
 */

get_header();
// wp_enqueue_media(); // ya no se usa el selector de medios nativo

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

  <?php
    $imagen_url    = function_exists( 'drak_get_personaje_hero_image_url' )
      ? drak_get_personaje_hero_image_url( $personaje->ID, 'combate', $nav_images['combate'] )
      : get_the_post_thumbnail_url( $personaje->ID, 'large' );
    $personaje_url = get_permalink( $personaje->ID );
  ?>

  <div class="personaje-hero">
    <div class="personaje-hero__image" data-hero-image style="min-height:350px; background-image:url('<?php echo esc_url( $imagen_url ?: $nav_images['combate'] ); ?>');"></div>
    <div class="personaje-hero__actions">
      <button type="button" class="personaje-hero__change" data-post-id="<?php echo esc_attr( $personaje->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-hero-context="combate">Cambiar imagen</button>
    </div>
  </div>

  <?php echo renderizar_combate_personaje( $personaje->ID ); ?>
</div>

<?php
get_footer();
