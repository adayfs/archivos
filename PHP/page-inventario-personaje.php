<?php
/**
 * Template Name: Inventario Personaje
 */

get_header();

// Obtener slug de personaje desde URL
$slug = get_query_var('personaje_slug');
$personaje = get_page_by_path($slug, OBJECT, 'personaje');

if (!$personaje) {
    echo '<p style="color: white; text-align: center;">Personaje no encontrado.</p>';
    get_footer();
    exit;
}
?>

<div class="contenido-inventario">
  <h2 class="titulo-inventario">
    Inventario de <?php echo esc_html($personaje->post_title); ?>
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

  <?php echo renderizar_inventario_personaje($personaje->ID); ?>
</div>



<!-- Modal principal para añadir objeto -->
<div id="item-form-overlay" class="modal-overlay" style="display:none;">
  <div class="modal-contenido">
    <span class="close-popup">&times;</span>
    <h2>Añadir objeto al slot <span id="slot-numero"></span></h2>

    <form id="item-form">
      <!-- Slot que estamos editando -->
      <input type="hidden" id="current-slot">

      <label for="item-name">Nombre del objeto:</label>
      <input type="text" id="item-name">

      <label for="item-name-select">O elegir de la lista:</label>
      <select id="item-name-select">
        <option value="">-- Selecciona --</option>
  <option value="Antorcha">Antorcha</option>
  <option value="Ración">Ración</option>
  <option value="Cuerda">Cuerda</option>
  <option value="Vela">Vela</option>
  <option value="Botiquín de curandero">Botiquín de curandero</option>
  <option value="Cantimplora">Cantimplora</option>
  <option value="Yesca y pedernal">Yesca y pedernal</option>
  <option value="Saco">Saco</option>
  <option value="Tiza">Tiza</option>
  <option value="Mapa">Mapa</option>
  <option value="Poción de curación">Poción de curación</option>
  <option value="Pitón">Pitón</option>
  <option value="Garfio de escalada">Garfio de escalada</option>
  <option value="Palanca">Palanca</option>
  <option value="Lámpara">Lámpara</option>
        <!-- Añade aquí los objetos que quieras -->
      </select>

      <label for="item-size">Tamaño:</label>
      <select id="item-size">
        <option value="normal">Normal</option>
        <option value="pequeño">Pequeño</option>
      </select>

      <div class="form-group cantidad">
        <label for="item-qty">Cantidad (solo si es pequeño):</label>
        <select id="item-qty" disabled></select>
      </div>

      <button type="submit">Añadir</button>
    </form>
  </div>
</div>



<!-- Modal secundario para eliminar objetos -->
<div id="delete-form-overlay" class="modal-overlay" style="display: none;">
  <div class="modal-contenido">
    <span class="close-delete-popup">&times;</span>
    <h2>Eliminar objetos del slot</h2>
    <form id="delete-form">
      <div id="delete-form-content"></div>
      <button type="submit">Eliminar seleccionados</button>
    </form>
  </div>
</div>

<?php get_footer(); ?>