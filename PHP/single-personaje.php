<?php
get_header();

$imagen_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
$titulo = get_the_title();
?>

<div class="personaje-container">
  <div class="personaje-imagen" style="background-image: url('<?php echo esc_url($imagen_url); ?>');"></div>

  <div class="personaje-botones">
    <a class="personaje-boton" href="<?php echo home_url('/hoja-personaje/' . get_post_field('post_name', get_the_ID())); ?>">
  Hoja de Personaje
</a>


<a class="personaje-boton" href="<?php echo home_url('/inventario/' . get_post_field('post_name', get_the_ID())); ?>">Inventario</a>




    <a class="personaje-boton" href="<?php echo home_url('/grimorio/' . get_post_field('post_name', get_the_ID())); ?>">Grimorio</a>
  </div>
  </div>
</div>

<?php if ( function_exists( 'drak_render_gallery_for_post' ) ) : ?>
  <div class="personaje-galeria">
    <?php drak_render_gallery_for_post( get_the_ID() ); ?>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const boton = document.querySelector('.toggle-inventario');
  const bloque = document.querySelector('.bloque-inventario');
  if (boton && bloque) {
    boton.addEventListener('click', function () {
      bloque.style.display = bloque.style.display === 'none' ? 'block' : 'none';
    });
  }
});
</script>


<?php
get_footer();
