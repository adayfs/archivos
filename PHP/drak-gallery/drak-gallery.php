<?php
/**
 * Plugin Name: Drak Gallery
 * Description: Gestiona la galería de imágenes para personajes, lugares y NPC con formularios de subida y renderizado modular.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: drak-gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRAK_GALLERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'DRAK_GALLERY_URL', plugin_dir_url( __FILE__ ) );
define( 'DRAK_GALLERY_VERSION', '1.0.0' );

/**
 * Registra Custom Post Type.
 */
function drak_gallery_register_cpt() {
	$labels = array(
		'name'               => __( 'Galería', 'drak-gallery' ),
		'singular_name'      => __( 'Elemento de galería', 'drak-gallery' ),
		'add_new'            => __( 'Añadir imagen', 'drak-gallery' ),
		'add_new_item'       => __( 'Añadir nuevo elemento', 'drak-gallery' ),
		'edit_item'          => __( 'Editar elemento', 'drak-gallery' ),
		'new_item'           => __( 'Nuevo elemento', 'drak-gallery' ),
		'all_items'          => __( 'Galería', 'drak-gallery' ),
		'view_item'          => __( 'Ver elemento', 'drak-gallery' ),
		'search_items'       => __( 'Buscar en galería', 'drak-gallery' ),
		'not_found'          => __( 'No se encontraron elementos', 'drak-gallery' ),
		'not_found_in_trash' => __( 'No hay elementos en la papelera', 'drak-gallery' ),
		'menu_name'          => __( 'Galería', 'drak-gallery' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'capability_type'    => 'post',
		'supports'           => array( 'title', 'thumbnail', 'author' ),
		'has_archive'        => false,
		'show_in_rest'       => false,
	);

	register_post_type( 'galeria_item', $args );
}
add_action( 'init', 'drak_gallery_register_cpt' );

/**
 * Define los campos ACF mediante código.
 */
function drak_gallery_register_acf_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_drak_gallery',
			'title'    => __( 'Asociaciones de galería', 'drak-gallery' ),
			'fields'   => array(
				array(
					'key'               => 'field_gallery_type',
					'label'             => __( 'Tipo de asociación', 'drak-gallery' ),
					'name'              => 'gallery_type',
					'type'              => 'checkbox',
					'choices'           => array(
						'personaje'      => __( 'Personaje', 'drak-gallery' ),
						'lugar'          => __( 'Lugar', 'drak-gallery' ),
						'npc'            => __( 'NPC', 'drak-gallery' ),
						'faccion'        => __( 'Facción', 'drak-gallery' ),
						'personaje_wiki' => __( 'Personaje (Wiki)', 'drak-gallery' ),
					),
					'layout'            => 'horizontal',
					'required'          => 1,
					'return_format'     => 'value',
					'allow_custom'      => 0,
					'min'               => 1,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
				),
				array(
					'key'               => 'field_gallery_personajes',
					'label'             => __( 'Personajes relacionados', 'drak-gallery' ),
					'name'              => 'gallery_personajes',
					'type'              => 'relationship',
					'post_type'         => array( 'personaje' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_gallery_type',
								'operator' => '==',
								'value'    => 'personaje',
							),
						),
					),
					'return_format'     => 'id',
					'multiple'          => 1,
					'filters'           => array( 'search' ),
				),
				array(
					'key'               => 'field_gallery_facciones',
					'label'             => __( 'Facciones relacionadas', 'drak-gallery' ),
					'name'              => 'gallery_facciones',
					'type'              => 'relationship',
					'post_type'         => array( 'faccion' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_gallery_type',
								'operator' => '==',
								'value'    => 'faccion',
							),
						),
					),
					'return_format'     => 'id',
					'multiple'          => 1,
					'filters'           => array( 'search' ),
				),
				array(
					'key'               => 'field_gallery_personajes_wiki',
					'label'             => __( 'Personajes (Wiki) relacionados', 'drak-gallery' ),
					'name'              => 'gallery_personajes_wiki',
					'type'              => 'relationship',
					'post_type'         => array( 'personaje_wiki', 'personaje-lore', 'personaje_lore', 'personaje-wiki' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_gallery_type',
								'operator' => '==',
								'value'    => 'personaje_wiki',
							),
						),
					),
					'return_format'     => 'id',
					'multiple'          => 1,
					'filters'           => array( 'search' ),
				),
				array(
					'key'           => 'field_gallery_campaigns',
					'label'         => __( 'Campañas asociadas', 'drak-gallery' ),
					'name'          => 'gallery_campaigns',
					'type'          => 'relationship',
					'post_type'     => array( 'campaign' ),
					'return_format' => 'id',
					'multiple'      => 1,
					'filters'       => array( 'search' ),
				),
				array(
					'key'               => 'field_gallery_lugares',
					'label'             => __( 'Lugares relacionados', 'drak-gallery' ),
					'name'              => 'gallery_lugares',
					'type'              => 'relationship',
					'post_type'         => array( 'lugar' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_gallery_type',
								'operator' => '==',
								'value'    => 'lugar',
							),
						),
					),
					'return_format'     => 'id',
					'multiple'          => 1,
					'filters'           => array( 'search' ),
				),
				array(
					'key'               => 'field_gallery_npcs',
					'label'             => __( 'NPCs relacionados', 'drak-gallery' ),
					'name'              => 'gallery_npcs',
					'type'              => 'relationship',
					'post_type'         => array( 'npc' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_gallery_type',
								'operator' => '==',
								'value'    => 'npc',
							),
						),
					),
					'return_format'     => 'id',
					'multiple'          => 1,
					'filters'           => array( 'search' ),
				),
				array(
					'key'           => 'field_gallery_description',
					'label'         => __( 'Descripción', 'drak-gallery' ),
					'name'          => 'gallery_description',
					'type'          => 'textarea',
					'required'      => 0,
					'rows'          => 3,
					'new_lines'     => 'br',
					'default_value' => '',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'galeria_item',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'drak_gallery_register_acf_fields' );

/**
 * Registra assets y los carga de forma condicional.
 */
function drak_gallery_register_assets() {
	wp_register_style(
		'drak-gallery',
		DRAK_GALLERY_URL . 'assets/css/drak-gallery.css',
		array(),
		DRAK_GALLERY_VERSION
	);

	wp_register_script(
		'drak-gallery-frontend',
		DRAK_GALLERY_URL . 'assets/js/drak-gallery.js',
		array( 'jquery' ),
		DRAK_GALLERY_VERSION,
		true
	);

	wp_register_script(
		'drak-gallery-upload',
		DRAK_GALLERY_URL . 'assets/js/drak-gallery-upload.js',
		array( 'jquery' ),
		DRAK_GALLERY_VERSION,
		true
	);

	wp_register_style(
		'swiper',
		'https://unpkg.com/swiper@9/swiper-bundle.min.css',
		array(),
		'9.4.1'
	);

	wp_register_script(
		'swiper',
		'https://unpkg.com/swiper@9/swiper-bundle.min.js',
		array(),
		'9.4.1',
		true
	);

	wp_register_script(
		'drak-gallery-slider',
		DRAK_GALLERY_URL . 'assets/js/drak-gallery-slider.js',
		array( 'swiper' ),
		DRAK_GALLERY_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'drak_gallery_register_assets' );

/**
 * Capacidad mínima para subir imágenes.
 *
 * @return string
 */
function drak_gallery_upload_capability() {
	$cap = apply_filters( 'drak_gallery_upload_capability', 'read' );
	return $cap ? $cap : 'read';
}

/**
 * Comprueba si el usuario actual puede subir a la galería.
 *
 * @return bool
 */
function drak_gallery_user_can_upload() {
	return current_user_can( drak_gallery_upload_capability() );
}

/**
 * Asegura que el JS frontal esté cargado y localizado.
 */
function drak_gallery_ensure_frontend_script() {
	static $localized = false;

	wp_enqueue_style( 'drak-gallery' );

	if ( ! $localized ) {
		wp_localize_script(
			'drak-gallery-frontend',
			'drakGalleryData',
			array(
				'labels' => drak_gallery_type_labels(),
			)
		);
		$localized = true;
	}

	wp_enqueue_script( 'drak-gallery-frontend' );
}

/**
 * Devuelve el HTML del modal, asegurando que solo se imprima una vez.
 *
 * @return string
 */
function drak_gallery_get_modal_markup() {
	static $rendered = false;

	if ( $rendered ) {
		return '';
	}

	$rendered = true;

	ob_start();
	?>
	<div class="drak-gallery-modal" id="drak-gallery-modal" aria-hidden="true" role="dialog">
		<div class="drak-gallery-modal__overlay" data-modal-close></div>
		<div class="drak-gallery-modal__content">
			<button class="drak-gallery-modal__close" type="button" data-modal-close>&times;</button>
			<button class="drak-gallery-modal__nav drak-gallery-modal__nav--prev" type="button" data-modal-prev aria-label="<?php esc_attr_e( 'Imagen anterior', 'drak-gallery' ); ?>">&lsaquo;</button>
			<button class="drak-gallery-modal__nav drak-gallery-modal__nav--next" type="button" data-modal-next aria-label="<?php esc_attr_e( 'Imagen siguiente', 'drak-gallery' ); ?>">&rsaquo;</button>
			<div class="drak-gallery-modal__image">
				<img src="" alt="">
			</div>
			<div class="drak-gallery-modal__info">
				<h3 class="drak-gallery-modal__title"></h3>
				<p class="drak-gallery-modal__desc"></p>
				<div class="drak-gallery-modal__links"></div>
				<div class="drak-gallery-modal__meta"></div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Obtiene mapa de tipos permitidos.
 */
function drak_gallery_allowed_types() {
	return array_keys( drak_gallery_type_labels() );
}

/**
 * Etiquetas traducibles para tipos.
 *
 * @return array
 */
function drak_gallery_type_labels() {
	$labels = array(
		'personaje' => __( 'Personaje', 'drak-gallery' ),
		'lugar'     => __( 'Lugar', 'drak-gallery' ),
		'npc'       => __( 'NPC', 'drak-gallery' ),
	);

	if ( post_type_exists( 'faccion' ) ) {
		$labels['faccion'] = __( 'Facción', 'drak-gallery' );
	}
	if ( post_type_exists( 'personaje_wiki' ) || post_type_exists( 'personaje-lore' ) || post_type_exists( 'personaje_lore' ) ) {
		$labels['personaje_wiki'] = __( 'Personaje (Wiki)', 'drak-gallery' );
	}

	return $labels;
}

function drak_gallery_get_current_campaign_id() {
	$campaign = get_query_var( 'campaign' );
	if ( $campaign ) {
		if ( is_numeric( $campaign ) ) {
			return absint( $campaign );
		}
		$post = get_page_by_path( $campaign, OBJECT, 'campaign' );
		if ( $post ) {
			return $post->ID;
		}
	}

	if ( is_singular( 'campaign' ) ) {
		return get_queried_object_id();
	}

	global $post;
	if ( $post && $post->post_type === 'campaign' ) {
		return $post->ID;
	}

	return 0;
}

function drak_gallery_get_campaign_options() {
	$campaigns = get_posts(
		array(
			'post_type'      => 'campaign',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	$options = '';
	foreach ( $campaigns as $cid ) {
		$options .= sprintf(
			'<option value="%1$d">%2$s</option>',
			intval( $cid ),
			esc_html( get_the_title( $cid ) )
		);
	}
	return $options;
}

/**
 * Localiza la URL pública del formulario de subida.
 *
 * @return string
 */
function drak_gallery_get_upload_page_url() {
	return drak_gallery_locate_shortcode_page_url(
		'drak_gallery_upload',
		'drak_gallery_upload_page_id',
		'drak_gallery_upload_page_url',
		'DRAK_GALLERY_UPLOAD_PAGE_ID'
	);
}

/**
 * Localiza la URL pública de la página que contiene la galería global.
 *
 * @return string
 */
function drak_gallery_get_gallery_page_url() {
	return drak_gallery_locate_shortcode_page_url(
		'drak_gallery',
		'drak_gallery_page_id',
		'drak_gallery_page_url',
		'DRAK_GALLERY_PAGE_ID'
	);
}

/**
 * Inserta un botón en la portada hacia la galería completa.
 */
function drak_gallery_get_home_button_markup() {
	$gallery_url = drak_gallery_get_gallery_page_url();
	if ( ! $gallery_url ) {
		return '';
	}

	wp_enqueue_style( 'drak-gallery' );

	$image = apply_filters(
		'drak_gallery_home_button_image',
		'https://adayfs.com/wp-content/uploads/2025/11/botongaleria.png'
	);

	$img_tag = $image ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr__( 'Ver galería completa', 'drak-gallery' ) . '">' : '';

	return sprintf(
		'<div class="drak-home-gallery-cta"><a class="drak-home-gallery-cta__button" href="%1$s" aria-label="%2$s">%3$s</a></div>',
		esc_url( $gallery_url ),
		esc_attr__( 'Ir a la galería', 'drak-gallery' ),
		$img_tag
	);
}

/**
 * Inserta el botón de galería al final del contenido de la portada.
 *
 * @param string $content Contenido de la página.
 * @return string
 */
function drak_gallery_append_home_button_to_content( $content ) {
	if ( ! ( is_front_page() || is_home() ) ) {
		return $content;
	}

	if ( ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$should_render = apply_filters(
		'drak_gallery_show_home_button',
		true
	);

	if ( ! $should_render ) {
		return $content;
	}

	$button = drak_gallery_get_home_button_markup();
	if ( ! $button ) {
		return $content;
	}

	return $content . $button;
}
add_filter( 'the_content', 'drak_gallery_append_home_button_to_content', 25 );

/**
 * Reubica el botón dentro del contenedor de Elementor si existe.
 */
function drak_gallery_move_home_button() {
	if ( ! ( is_front_page() || is_home() ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var target = document.querySelector('.elementor-element.elementor-element-f6f7e27 .e-con-inner, .elementor-element.elementor-element-f6f7e27');
		var button = document.querySelector('.drak-home-gallery-cta');
		if (target && button) {
			target.appendChild(button);
			button.classList.add('drak-home-gallery-cta--elementor');
		}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'drak_gallery_move_home_button', 60 );

/**
 * Ayudante para localizar páginas con shortcodes concretos.
 *
 * @param string $shortcode    Shortcode a buscar.
 * @param string $option_name  Nombre de opción donde cachear el ID.
 * @param string $filter_name  Filtro para permitir override manual.
 * @param string $constant     Constante opcional con ID de página forzado.
 *
 * @return string
 */
function drak_gallery_locate_shortcode_page_url( $shortcode, $option_name, $filter_name = '', $constant = '' ) {
	$shortcode = trim( $shortcode, '[]' );

	if ( $filter_name ) {
		$filtered = apply_filters( $filter_name, '' );
		if ( $filtered ) {
			return esc_url( $filtered );
		}
	}

	if ( $constant && defined( $constant ) ) {
		$forced_id = absint( constant( $constant ) );
		if ( $forced_id ) {
			$url = get_permalink( $forced_id );
			if ( $url ) {
				return $url;
			}
		}
	}

	$cached_id = absint( get_option( $option_name, 0 ) );
	if ( $cached_id ) {
		$page = get_post( $cached_id );
		if ( $page && 'publish' === $page->post_status && has_shortcode( $page->post_content, $shortcode ) ) {
			return get_permalink( $page );
		}
		delete_option( $option_name );
	}

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 25,
			's'              => '[' . $shortcode,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, $shortcode ) ) {
			update_option( $option_name, $page->ID );
			return get_permalink( $page );
		}
	}

	return home_url();
}

/**
 * Renderiza formulario de subida.
 */
function drak_gallery_render_upload_form() {
	if ( ! is_user_logged_in() ) {
		return '<p class="drak-gallery-notice">' . esc_html__( 'Debes iniciar sesión para subir imágenes.', 'drak-gallery' ) . '</p>';
	}

	wp_enqueue_style( 'drak-gallery' );
	wp_enqueue_script( 'drak-gallery-upload' );

	$nonce = wp_create_nonce( 'drak_gallery_upload' );
	$current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$current_url  = esc_url_raw( home_url( $current_path ) );
	$redirect     = esc_url_raw( add_query_arg( 'drak_gallery_status', 'success', $current_url ) );
	$options = drak_gallery_get_selector_options();
	ob_start();
	?>
	<form class="drak-gallery-upload-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="drak_gallery_upload">
		<input type="hidden" name="drak_gallery_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<input type="hidden" name="drak_gallery_redirect" value="<?php echo esc_attr( $redirect ); ?>">

		<div class="drak-field">
			<label for="drak-gallery-title"><?php esc_html_e( 'Título (opcional)', 'drak-gallery' ); ?></label>
			<input type="text" id="drak-gallery-title" name="drak_gallery_title" maxlength="120">
		</div>

		<div class="drak-field">
			<label for="drak-gallery-description"><?php esc_html_e( 'Descripción (opcional)', 'drak-gallery' ); ?></label>
			<textarea id="drak-gallery-description" name="drak_gallery_description" rows="4"></textarea>
		</div>

		<div class="drak-field">
			<label><?php esc_html_e( 'Tipo de asociación', 'drak-gallery' ); ?> *</label>
			<div class="drak-checkbox-group">
				<?php foreach ( drak_gallery_type_labels() as $type_key => $label ) : ?>
					<label>
						<input type="checkbox" name="gallery_type[]" value="<?php echo esc_attr( $type_key ); ?>" class="drak-gallery-type-checkbox">
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="drak-field drak-conditional-panel" data-type="personaje">
			<label for="drak-gallery-personajes"><?php esc_html_e( 'Personajes relacionados', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-personajes" name="gallery_personajes[]" multiple>
				<?php echo $options['personaje']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<div class="drak-field drak-conditional-panel" data-type="lugar">
			<label for="drak-gallery-lugares"><?php esc_html_e( 'Lugares relacionados', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-lugares" name="gallery_lugares[]" multiple>
				<?php echo $options['lugar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<div class="drak-field drak-conditional-panel" data-type="npc">
			<label for="drak-gallery-npcs"><?php esc_html_e( 'NPC relacionados', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-npcs" name="gallery_npcs[]" multiple>
				<?php echo $options['npc']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<?php if ( isset( $options['faccion'] ) ) : ?>
		<div class="drak-field drak-conditional-panel" data-type="faccion">
			<label for="drak-gallery-facciones"><?php esc_html_e( 'Facciones relacionadas', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-facciones" name="gallery_facciones[]" multiple>
				<?php echo $options['faccion']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>
		<?php endif; ?>

		<?php if ( isset( $options['personaje_wiki'] ) ) : ?>
		<div class="drak-field drak-conditional-panel" data-type="personaje_wiki">
			<label for="drak-gallery-personajes-wiki"><?php esc_html_e( 'Personajes (Wiki) relacionados', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-personajes-wiki" name="gallery_personajes_wiki[]" multiple>
				<?php echo $options['personaje_wiki']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>
		<?php endif; ?>

		<?php $campaign_id = drak_gallery_get_current_campaign_id(); ?>
		<?php if ( $campaign_id ) : ?>
			<input type="hidden" name="gallery_campaigns[]" value="<?php echo esc_attr( $campaign_id ); ?>">
		<?php else : ?>
		<div class="drak-field">
			<label for="drak-gallery-campaign"><?php esc_html_e( 'Campaña', 'drak-gallery' ); ?></label>
			<select id="drak-gallery-campaign" name="gallery_campaigns[]" multiple>
				<?php echo drak_gallery_get_campaign_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="drak-field">
			<label for="drak-gallery-image"><?php esc_html_e( 'Imagen (JPG, PNG, WebP, máximo 8MB)', 'drak-gallery' ); ?> *</label>
			<input type="file" id="drak-gallery-image" name="drak_gallery_image" accept="image/*" required>
		</div>

		<button type="submit" class="drak-button"><?php esc_html_e( 'Enviar imagen', 'drak-gallery' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'drak_gallery_upload', 'drak_gallery_render_upload_form' );

/**
 * Opciones de selectores para tipos.
 */
function drak_gallery_get_selector_options() {
	$types = array(
		'personaje' => array(),
		'lugar'     => array(),
		'npc'       => array(),
	);

	if ( post_type_exists( 'faccion' ) ) {
		$types['faccion'] = array();
	}
	if ( post_type_exists( 'personaje_wiki' ) || post_type_exists( 'personaje-lore' ) || post_type_exists( 'personaje_lore' ) || post_type_exists( 'personaje-wiki' ) ) {
		$types['personaje_wiki'] = array();
	}

	foreach ( $types as $post_type => &$html ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$options = '';
		foreach ( $posts as $post_id ) {
			$options .= sprintf(
				'<option value="%1$d">%2$s</option>',
				intval( $post_id ),
				esc_html( get_the_title( $post_id ) )
			);
		}

		$html = $options;
	}

	return $types;
}

/**
 * Maneja la subida del formulario.
 */
function drak_gallery_handle_upload() {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Debes iniciar sesión para subir imágenes.', 'drak-gallery' ) );
	}

	if ( ! drak_gallery_user_can_upload() ) {
		wp_die( esc_html__( 'No tienes permisos para subir archivos.', 'drak-gallery' ) );
	}

	if ( ! isset( $_POST['drak_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['drak_gallery_nonce'] ) ), 'drak_gallery_upload' ) ) {
		wp_die( esc_html__( 'Nonce inválido.', 'drak-gallery' ) );
	}

	$allowed_types = drak_gallery_allowed_types();
	$selected_types = isset( $_POST['gallery_type'] ) ? array_map( 'sanitize_text_field', (array) $_POST['gallery_type'] ) : array();
	$selected_types = array_values( array_intersect( $allowed_types, $selected_types ) );
	if ( empty( $selected_types ) ) {
		wp_die( esc_html__( 'Selecciona al menos un tipo válido.', 'drak-gallery' ) );
	}

	$associations = array(
		'personaje' => drak_gallery_clean_ids( $_POST['gallery_personajes'] ?? array() ),
		'lugar'     => drak_gallery_clean_ids( $_POST['gallery_lugares'] ?? array() ),
		'npc'       => drak_gallery_clean_ids( $_POST['gallery_npcs'] ?? array() ),
	);
	if ( post_type_exists( 'faccion' ) ) {
		$associations['faccion'] = drak_gallery_clean_ids( $_POST['gallery_facciones'] ?? array() );
	}
	if ( post_type_exists( 'personaje_wiki' ) || post_type_exists( 'personaje-lore' ) || post_type_exists( 'personaje_lore' ) || post_type_exists( 'personaje-wiki' ) ) {
		$associations['personaje_wiki'] = drak_gallery_clean_ids( $_POST['gallery_personajes_wiki'] ?? array() );
	}

	foreach ( $associations as $type => &$ids ) {
		$ids = drak_gallery_validate_ids( $ids, $type );
	}
	unset( $ids );

	foreach ( $selected_types as $type ) {
		if ( empty( $associations[ $type ] ) ) {
			wp_die( esc_html__( 'Cada tipo seleccionado debe incluir al menos una relación.', 'drak-gallery' ) );
		}
	}

	if ( ! isset( $_FILES['drak_gallery_image'] ) ) {
		wp_die( esc_html__( 'Debes seleccionar una imagen.', 'drak-gallery' ) );
	}

	$file = $_FILES['drak_gallery_image'];
	if ( $file['error'] !== UPLOAD_ERR_OK ) {
		wp_die( esc_html__( 'Error al subir la imagen.', 'drak-gallery' ) );
	}

	if ( $file['size'] > 8 * 1024 * 1024 ) {
		wp_die( esc_html__( 'La imagen supera el límite de 8MB.', 'drak-gallery' ) );
	}

	$mime = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
	$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
	if ( empty( $mime['type'] ) || ! in_array( $mime['type'], $allowed_mimes, true ) ) {
		wp_die( esc_html__( 'Formato de imagen no admitido.', 'drak-gallery' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
		)
	);

	if ( isset( $upload['error'] ) ) {
		wp_die( esc_html( $upload['error'] ) );
	}

	$attachment = array(
		'post_mime_type' => $mime['type'],
		'post_title'     => sanitize_file_name( $file['name'] ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
	$attach_data   = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	wp_update_attachment_metadata( $attachment_id, $attach_data );

	$title = isset( $_POST['drak_gallery_title'] ) ? sanitize_text_field( wp_unslash( $_POST['drak_gallery_title'] ) ) : '';
	$description = isset( $_POST['drak_gallery_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['drak_gallery_description'] ) ) : '';
	if ( empty( $title ) ) {
		$title = sprintf( 'Galería %s', current_time( 'Y-m-d H:i' ) );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'galeria_item',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_author' => get_current_user_id(),
		)
	);

	if ( is_wp_error( $post_id ) ) {
		wp_die( esc_html__( 'No fue posible crear el elemento de galería.', 'drak-gallery' ) );
	}

	set_post_thumbnail( $post_id, $attachment_id );

	drak_gallery_update_field( 'gallery_type', $selected_types, $post_id );
	drak_gallery_update_field( 'gallery_personajes', $associations['personaje'], $post_id );
	drak_gallery_update_field( 'gallery_lugares', $associations['lugar'], $post_id );
	drak_gallery_update_field( 'gallery_npcs', $associations['npc'], $post_id );
	if ( isset( $associations['faccion'] ) ) {
		drak_gallery_update_field( 'gallery_facciones', $associations['faccion'], $post_id );
	}
	if ( isset( $associations['personaje_wiki'] ) ) {
		drak_gallery_update_field( 'gallery_personajes_wiki', $associations['personaje_wiki'], $post_id );
	}
	$campaigns = drak_gallery_clean_ids( $_POST['gallery_campaigns'] ?? array() );
	if ( ! empty( $campaigns ) ) {
		drak_gallery_update_field( 'gallery_campaigns', $campaigns, $post_id );
	}
	drak_gallery_update_field( 'gallery_description', $description, $post_id );

	$redirect = isset( $_POST['drak_gallery_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['drak_gallery_redirect'] ) ) : '';
	$redirect = $redirect ? $redirect : add_query_arg( 'drak_gallery_status', 'success', home_url() );

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_drak_gallery_upload', 'drak_gallery_handle_upload' );
add_action( 'admin_post_nopriv_drak_gallery_upload', 'drak_gallery_handle_upload' );

/**
 * Limpia IDs de selectores.
 *
 * @param array $raw_ids Raw IDs.
 * @return array
 */
function drak_gallery_clean_ids( $raw_ids ) {
	$ids = array_filter(
		array_map(
			static function ( $id ) {
				return absint( $id );
			},
			(array) $raw_ids
		)
	);

	return array_values( $ids );
}

/**
 * Shortcode de galería pública.
 */
function drak_gallery_render_grid( $atts = array() ) {
	drak_gallery_ensure_frontend_script();

	$atts        = shortcode_atts( array( 'campaign' => 0 ), $atts );
	$campaign_id = intval( $atts['campaign'] );
	if ( ! $campaign_id ) {
		$campaign_id = drak_gallery_get_current_campaign_id();
	}

	$args = array(
		'post_type'      => 'galeria_item',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( $campaign_id ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'gallery_campaigns',
				'value'   => '"' . $campaign_id . '"',
				'compare' => 'LIKE',
			),
		);
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return '<p class="drak-gallery-notice">' . esc_html__( 'No hay imágenes disponibles todavía.', 'drak-gallery' ) . '</p>';
	}

	ob_start();
	?>
	<div class="drak-gallery-wrapper" data-drak-gallery>
		<div class="drak-gallery-controls">
			<input type="text" class="drak-gallery-search" placeholder="<?php esc_attr_e( 'Buscar...', 'drak-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Buscar en galería', 'drak-gallery' ); ?>">
			<div class="drak-gallery-filters">
				<?php foreach ( drak_gallery_type_labels() as $type_key => $label ) : ?>
					<label><input type="checkbox" data-filter="<?php echo esc_attr( $type_key ); ?>" checked> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="drak-gallery-grid">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();

				$thumb_id = get_post_thumbnail_id();
				if ( ! $thumb_id ) {
					// Elementos sin imagen destacada no deben renderizar huecos.
					continue;
				}

				$thumb = wp_get_attachment_image_url( $thumb_id, 'medium' );
				$full  = wp_get_attachment_image_url( $thumb_id, 'full' );

				if ( ! $thumb || ! $full ) {
					continue;
				}

				$has         = drak_gallery_get_associations( get_the_ID() );
				$detailed    = drak_gallery_get_associations( get_the_ID(), true );
				$author      = get_the_author();
				$date        = get_the_date();
				$description = wp_strip_all_tags( (string) drak_gallery_get_field( 'gallery_description', get_the_ID() ) );
				$data_search = strtolower( get_the_title() . ' ' . $description );
				?>
				<div class="drak-gallery-card"
					data-search="<?php echo esc_attr( $data_search ); ?>"
					<?php foreach ( drak_gallery_type_labels() as $t_key => $t_label ) : ?>
						data-has-<?php echo esc_attr( $t_key ); ?>="<?php echo ! empty( $has[ $t_key ] ) ? '1' : '0'; ?>"
					<?php endforeach; ?>
					data-title="<?php echo esc_attr( get_the_title() ); ?>"
					data-author="<?php echo esc_attr( $author ); ?>"
					data-date="<?php echo esc_attr( $date ); ?>"
					data-description="<?php echo esc_attr( $description ); ?>"
					data-full="<?php echo esc_url( $full ); ?>"
					data-rel="<?php echo esc_attr( wp_json_encode( $detailed ) ); ?>"
				>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
				</div>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
	<?php
	$content = ob_get_clean();
	$content .= drak_gallery_get_modal_markup();

	return $content;
}
add_shortcode( 'drak_gallery', 'drak_gallery_render_grid' );

/**
 * Recupera asociaciones de un item.
 *
 * @param int $post_id Item.
 * @return array
 */
function drak_gallery_get_associations( $post_id, $detailed = false ) {
	$raw = array(
		'personaje' => (array) drak_gallery_get_field( 'gallery_personajes', $post_id ),
		'lugar'     => (array) drak_gallery_get_field( 'gallery_lugares', $post_id ),
		'npc'       => (array) drak_gallery_get_field( 'gallery_npcs', $post_id ),
	);
	if ( drak_gallery_get_field( 'gallery_facciones', $post_id ) !== null ) {
		$raw['faccion'] = (array) drak_gallery_get_field( 'gallery_facciones', $post_id );
	}
	if ( drak_gallery_get_field( 'gallery_personajes_wiki', $post_id ) !== null ) {
		$raw['personaje_wiki'] = (array) drak_gallery_get_field( 'gallery_personajes_wiki', $post_id );
	}

	foreach ( $raw as &$ids ) {
		$ids = array_values(
			array_filter(
				array_map(
					static function ( $id ) {
						return absint( $id );
					},
					$ids
				)
			)
		);
	}
	unset( $ids );

	if ( ! $detailed ) {
		return $raw;
	}

	$result = array(
		'personaje' => array(),
		'lugar'     => array(),
		'npc'       => array(),
	);

	foreach ( $raw as $type => $ids ) {
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}
			$result[ $type ][] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
			);
		}
	}

	return $result;
}

/**
 * Helper compatible si ACF no está activo.
 */
function drak_gallery_get_field( $field, $post_id ) {
	if ( function_exists( 'get_field' ) ) {
		return get_field( $field, $post_id );
	}

	$value = get_post_meta( $post_id, $field, true );
	return maybe_unserialize( $value );
}

/**
 * Guardado seguro cuando ACF no está disponible.
 *
 * @param string $field Field name.
 * @param mixed  $value Value.
 * @param int    $post_id Post ID.
 */
function drak_gallery_update_field( $field, $value, $post_id ) {
	if ( function_exists( 'update_field' ) ) {
		update_field( $field, $value, $post_id );
		return;
	}

	update_post_meta( $post_id, $field, $value );
}

/**
 * Verifica que los IDs existan y pertenezcan al tipo correcto.
 *
 * @param array  $ids IDs a validar.
 * @param string $type Tipo de post esperado.
 *
 * @return array
 */
function drak_gallery_validate_ids( $ids, $type ) {
	$valid = array();

	foreach ( (array) $ids as $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			continue;
		}

		if ( $type === get_post_type( $id ) ) {
			$valid[] = $id;
		}
	}

	return array_values( array_unique( $valid ) );
}

/**
 * Renderiza galería asociada para fichas.
 *
 * @param int $post_id Post ID.
 */
function drak_render_gallery_for_post( $post_id ) {
	$post_type = get_post_type( $post_id );
	$meta_query = array();

	if ( $post_type === 'personaje_wiki' ) {
		$meta_query[] = array(
			'key'     => 'gallery_personajes_wiki',
			'value'   => '"' . $post_id . '"',
			'compare' => 'LIKE',
		);
		$campaign_id = (int) get_post_meta( $post_id, 'campaign', true );
		if ( $campaign_id ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'gallery_campaigns',
					'value'   => '"' . $campaign_id . '"',
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'gallery_campaigns',
					'compare' => 'NOT EXISTS',
				),
			);
		}
	} else {
		$map = array(
			'lugar'   => 'gallery_lugares',
			'npc'     => 'gallery_npcs',
			'faccion' => 'gallery_facciones',
		);

		if ( ! isset( $map[ $post_type ] ) ) {
			return;
		}

		$meta_query[] = array(
			'key'     => 'gallery_type',
			'value'   => $post_type,
			'compare' => 'LIKE',
		);
		$meta_query[] = array(
			'key'     => $map[ $post_type ],
			'value'   => '"' . $post_id . '"',
			'compare' => 'LIKE',
		);
		$campaign_id = (int) get_post_meta( $post_id, 'campaign', true );
		if ( $campaign_id ) {
			$meta_query[] = array(
				'key'     => 'gallery_campaigns',
				'value'   => '"' . $campaign_id . '"',
				'compare' => 'LIKE',
			);
		}
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'galeria_item',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => $meta_query,
		)
	);

	if ( ! $query->have_posts() ) {
		return;
	}

	drak_gallery_ensure_frontend_script();

	ob_start();
	echo '<div class="drak-gallery-grid personaje-galeria-grid">';
	while ( $query->have_posts() ) {
		$query->the_post();
		$has        = drak_gallery_get_associations( get_the_ID() );
		$detailed   = drak_gallery_get_associations( get_the_ID(), true );
		$thumb      = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
		$author     = get_the_author();
		$date       = get_the_date();
		$description = wp_strip_all_tags( (string) drak_gallery_get_field( 'gallery_description', get_the_ID() ) );
		$data_search = strtolower( get_the_title() . ' ' . $description );
		?>
		<div class="drak-gallery-card"
			data-search="<?php echo esc_attr( $data_search ); ?>"
			<?php foreach ( drak_gallery_type_labels() as $t_key => $t_label ) : ?>
				data-has-<?php echo esc_attr( $t_key ); ?>="<?php echo ! empty( $has[ $t_key ] ) ? '1' : '0'; ?>"
			<?php endforeach; ?>
			data-title="<?php echo esc_attr( get_the_title() ); ?>"
			data-author="<?php echo esc_attr( $author ); ?>"
			data-date="<?php echo esc_attr( $date ); ?>"
			data-description="<?php echo esc_attr( $description ); ?>"
			data-full="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'full' ) ); ?>"
			data-rel="<?php echo esc_attr( wp_json_encode( $detailed ) ); ?>"
		>
			<?php if ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
			<?php endif; ?>
		</div>
		<?php
	}
	echo '</div>';
	wp_reset_postdata();

	echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo drak_gallery_get_modal_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
