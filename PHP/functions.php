<?php
/**
 * temaHijo Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package temaHijo
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_TEMAHIJO_VERSION', '1.0.0' );
require_once __DIR__ . '/recursos-clase.php';
require_once __DIR__ . '/diario-automation.php';
require_once __DIR__ . '/wiki-import.php';

/**
 * Carga datos de ítems priorizando items-es.json y devolviendo el array base.
 *
 * @return array
 */
function drak_load_items_data() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $paths = [
        get_stylesheet_directory() . '/data/items-es.json',
        dirname( __DIR__ ) . '/jsons/items-es.json',
        dirname( __DIR__ ) . '/5etools-src-main/data/items-es.json',
        get_stylesheet_directory() . '/data/items.json',
        dirname( __DIR__ ) . '/jsons/items.json',
        dirname( __DIR__ ) . '/5etools-src-main/data/items.json',
    ];

    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            $json = file_get_contents( $path );
            if ( $json ) {
                $decoded = json_decode( $json, true );
                if ( is_array( $decoded ) ) {
                    $cache = isset( $decoded['item'] ) && is_array( $decoded['item'] ) ? $decoded['item'] : $decoded;
                    return $cache;
                }
            }
        }
    }

    $cache = [];
    return $cache;
}

/**
 * ------------------------------------------------------------
 * Drive Image Picker (metabox + modal + AJAX)
 * ------------------------------------------------------------
 */

/**
 * Post types donde se mostrará la metabox.
 *
 * @return array
 */
function drak_drive_picker_targets() {
    return apply_filters( 'drak_drive_picker_targets', [ 'personaje', 'post', 'page', 'lugar', 'npc' ] );
}

/**
 * Registra la metabox.
 */
function drak_drive_picker_metabox() {
    foreach ( drak_drive_picker_targets() as $pt ) {
        add_meta_box(
            'drak_drive_picker',
            'Seleccionar Imagen Drive',
            'drak_drive_picker_render_box',
            $pt,
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'drak_drive_picker_metabox' );

/**
 * Render de la metabox.
 *
 * @param WP_Post $post Post actual.
 */
function drak_drive_picker_render_box( $post ) {
    $selected = (int) get_post_meta( $post->ID, 'drive_gallery_item_id', true );
    $image    = $selected ? drak_drive_picker_get_image_url( $selected ) : '';
    wp_nonce_field( 'drak_drive_picker_save', 'drak_drive_picker_nonce' );
    ?>
    <div class="drak-drive-picker" data-selected="<?php echo esc_attr( $selected ); ?>">
        <input type="hidden" name="drive_gallery_item_id" id="drive_gallery_item_id" value="<?php echo esc_attr( $selected ); ?>">
        <div class="drak-drive-picker__preview">
            <?php if ( $image ) : ?>
                <img src="<?php echo esc_url( $image ); ?>" alt="">
            <?php else : ?>
                <p class="drak-drive-picker__placeholder">No hay imagen seleccionada.</p>
            <?php endif; ?>
        </div>
        <button type="button" class="button drak-drive-picker__open">Abrir Galería Drive</button>
    </div>
    <?php

    static $modal_rendered = false;
    if ( ! $modal_rendered ) :
        $modal_rendered = true;
        ?>
        <div id="drak-drive-picker-modal" class="drak-drive-modal" aria-hidden="true" style="display:none;">
            <div class="drak-drive-modal__overlay" data-close></div>
            <div class="drak-drive-modal__content">
                <div class="drak-drive-modal__header">
                    <input type="search" class="drak-drive-modal__search" placeholder="Buscar por título...">
                    <button type="button" class="button drak-drive-modal__close" data-close>Cerrar</button>
                </div>
                <div class="drak-drive-modal__grid" data-grid></div>
                <div class="drak-drive-modal__loading">Cargando...</div>
                <div class="drak-drive-modal__empty" style="display:none;">Sin resultados.</div>
            </div>
        </div>
        <?php
    endif;
}

/**
 * Guardado del ID seleccionado.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function drak_drive_picker_save( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! isset( $_POST['drak_drive_picker_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['drak_drive_picker_nonce'] ) ), 'drak_drive_picker_save' ) ) {
        return;
    }
    if ( ! in_array( $post->post_type, drak_drive_picker_targets(), true ) ) {
        return;
    }
    if ( isset( $_POST['drive_gallery_item_id'] ) ) {
        update_post_meta( $post_id, 'drive_gallery_item_id', absint( $_POST['drive_gallery_item_id'] ) );
    }
}
add_action( 'save_post', 'drak_drive_picker_save', 10, 2 );

/**
 * Encola assets en admin cuando corresponde.
 */
function drak_drive_picker_assets( $hook ) {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, drak_drive_picker_targets(), true ) ) {
        return;
    }

    $theme_uri = get_stylesheet_directory_uri();

    wp_enqueue_style(
        'drak-drive-picker',
        $theme_uri . '/drak-drive-picker.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'drak-drive-picker',
        $theme_uri . '/drak-drive-picker.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );

    wp_localize_script(
        'drak-drive-picker',
        'DrakDrivePicker',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'drak_drive_picker_ajax' ),
            'texts'   => [
                'loading' => __( 'Cargando...', 'temahijo' ),
                'empty'   => __( 'Sin resultados.', 'temahijo' ),
                'error'   => __( 'Error cargando imágenes.', 'temahijo' ),
            ],
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'drak_drive_picker_assets' );

/**
 * AJAX: lista de imágenes desde galeria_item.
 */
function drak_drive_picker_list() {
    check_ajax_referer( 'drak_drive_picker_ajax', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'No autorizado.', 'temahijo' ) ], 403 );
    }

    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

    $query = new WP_Query(
        [
            'post_type'      => 'galeria_item',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            's'              => $search,
        ]
    );

    $items = [];
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $id  = get_the_ID();
            $url = drak_drive_picker_get_image_url( $id );
            if ( ! $url ) {
                continue;
            }
            $items[] = [
                'id'    => $id,
                'title' => get_the_title(),
                'url'   => $url,
            ];
        }
    }
    wp_reset_postdata();

    wp_send_json_success(
        [
            'items' => $items,
        ]
    );
}
add_action( 'wp_ajax_drak_drive_picker_list', 'drak_drive_picker_list' );

/**
 * AJAX público (logueados): lista de imágenes de galeria_item para el selector frontal.
 */
function drak_drive_picker_list_public() {
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	$query = new WP_Query(
		[
			'post_type'      => 'galeria_item',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search,
		]
	);

	$items = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$id  = get_the_ID();
			$url = drak_drive_picker_get_image_url( $id );
			if ( ! $url ) {
				continue;
			}
			$items[] = [
				'id'    => $id,
				'title' => get_the_title(),
				'url'   => $url,
			];
		}
	}
	wp_reset_postdata();

	wp_send_json_success( [ 'items' => $items ] );
}
add_action( 'wp_ajax_drak_drive_picker_list_public', 'drak_drive_picker_list_public' );
add_action( 'wp_ajax_nopriv_drak_drive_picker_list_public', 'drak_drive_picker_list_public' );

/**
 * Whitelist para Force Login (REST y AJAX del picker).
 */
add_filter(
	'v_forcelogin_rest_whitelist',
	static function ( $endpoints ) {
		$endpoints[] = 'drak';
		return $endpoints;
	}
);

add_filter(
	'v_forcelogin_whitelist',
	static function ( $urls ) {
		$urls[] = admin_url( 'admin-ajax.php?action=drak_drive_picker_list_public' );
		return $urls;
	}
);

/**
 * REST endpoint público para listar imágenes de galeria_item.
 */
/**
 * REST endpoint público para listar imágenes de galeria_item.
 */
function drak_register_drive_images_route() {
	register_rest_route(
		'drak/v1',
		'/drive-images',
		[
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => [
				's' => [
					'type' => 'string',
				],
			],
			'callback'            => static function ( WP_REST_Request $request ) {
				$search = sanitize_text_field( $request->get_param( 's' ) );

				$query = new WP_Query(
					[
						'post_type'      => 'galeria_item',
						'post_status'    => 'publish',
						'posts_per_page' => 20,
						'orderby'        => 'date',
						'order'          => 'DESC',
						's'              => $search,
					]
				);

				$items = [];
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$id  = get_the_ID();
						$url = drak_drive_picker_get_image_url( $id );
						if ( ! $url ) {
							continue;
						}
						$items[] = [
							'id'    => $id,
							'title' => get_the_title(),
							'url'   => $url,
						];
					}
				}
				wp_reset_postdata();

				return rest_ensure_response(
					[
						'success' => true,
						'data'    => [
							'items' => $items,
						],
					]
				);
			},
		]
	);
}
add_action( 'rest_api_init', 'drak_register_drive_images_route', 1 );

/**
 * Recupera la URL de imagen desde ACF/meta.
 *
 * @param int $post_id galeria_item ID.
 * @return string
 */
function drak_drive_picker_get_image_url( $post_id ) {
	$url = '';
	if ( function_exists( 'get_field' ) ) {
		$url = get_field( 'gallery_image_url', $post_id );
	} else {
		$url = get_post_meta( $post_id, 'gallery_image_url', true );
	}
	$url = is_string( $url ) ? trim( $url ) : '';
	if ( ! $url ) {
		return '';
	}

	$url = drak_drive_normalize_url( $url );

	return $url ? esc_url( $url ) : '';
}

/**
 * Normaliza URLs de Drive a formato directo uc?export=view&id=FILEID.
 *
 * @param string $url URL original.
 * @return string URL normalizada o la original si no se detecta ID.
 */
function drak_drive_normalize_url( $url ) {
	$url = trim( (string) $url );
	if ( ! $url ) {
		return '';
	}

	$id = '';
	$parts = wp_parse_url( $url );

	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query_vars );
		if ( ! empty( $query_vars['id'] ) ) {
			$id = $query_vars['id'];
		}
	}

	if ( ! $id && ! empty( $parts['path'] ) && preg_match( '#/file/d/([^/]+)/?#', $parts['path'], $m ) ) {
		$id = $m[1];
	}

	if ( ! $id && preg_match( '#id=([a-zA-Z0-9_-]{10,})#', $url, $m ) ) {
		$id = $m[1];
	}

	if ( $id ) {
		return 'https://drive.google.com/uc?export=view&id=' . rawurlencode( $id );
	}

	return $url;
}

/**
 * Encola assets del selector Drive en las vistas de personaje.
 */
function drak_enqueue_drive_hero_picker_front() {
    $theme_uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'drak-drive-hero-picker',
		$theme_uri . '/css/drak-drive-hero-picker.css',
		[],
		'1.0.0'
	);

	wp_enqueue_script(
		'drak-drive-hero-picker',
		$theme_uri . '/js/drak-drive-hero-picker.js',
		[],
		'1.0.0',
		true
	);

	wp_localize_script(
		'drak-drive-hero-picker',
		'DrakDriveHeroPicker',
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'drak_drive_picker_ajax' ),
			'restUrl' => get_rest_url( null, '/drak/v1/drive-images' ),
			'texts'   => [
				'title'   => __( 'Seleccionar imagen desde Drive', 'temahijo' ),
				'close'   => __( 'Cerrar', 'temahijo' ),
				'search'  => __( 'Buscar por título...', 'temahijo' ),
				'loading' => __( 'Cargando...', 'temahijo' ),
				'empty'   => __( 'Sin resultados.', 'temahijo' ),
				'error'   => __( 'Error cargando imágenes.', 'temahijo' ),
			],
		]
	);
}
add_action( 'wp_enqueue_scripts', 'drak_enqueue_drive_hero_picker_front' );

/**
 * Marca el footer con el modal (segundo fallback por si el JS externo no crea el DOM).
 */
function drak_drive_hero_picker_footer_markup() {
    // Solo en plantillas de personaje (slug o template dedicado).
    $templates = [
        'page-hoja-personaje.php',
        'page-inventario-personaje.php',
        'page-grimorio-personaje.php',
        'page-combate-personaje.php',
    ];
    $is_target = get_query_var( 'personaje_slug' ) ? true : false;
    $tpl       = get_page_template_slug();
    if ( in_array( basename( $tpl ), $templates, true ) ) {
        $is_target = true;
    }
    if ( ! $is_target ) {
        return;
    }

    $nonce = wp_create_nonce( 'drak_drive_picker_ajax' );
    ?>
    <div class="drive-picker-modal" id="drive-picker-modal-fallback" style="display:none;">
        <div class="drive-picker-modal__overlay" data-close></div>
        <div class="drive-picker-modal__content">
            <div class="drive-picker-modal__header">
                <input type="search" class="drive-picker-modal__search" placeholder="<?php esc_attr_e( 'Buscar por título...', 'temahijo' ); ?>">
                <button type="button" class="drive-picker-modal__close" data-close><?php esc_html_e( 'Cerrar', 'temahijo' ); ?></button>
            </div>
            <div class="drive-picker-modal__grid" data-grid></div>
            <div class="drive-picker-modal__loading"><?php esc_html_e( 'Cargando...', 'temahijo' ); ?></div>
            <div class="drive-picker-modal__empty" style="display:none;"><?php esc_html_e( 'Sin resultados.', 'temahijo' ); ?></div>
        </div>
    </div>
    <script>
    (function() {
      const cfg = {
        ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
        restUrl: <?php echo wp_json_encode( get_rest_url( null, '/drak/v1/drive-images' ) ); ?>,
        nonce: <?php echo wp_json_encode( $nonce ); ?>,
        texts: {
          loading: <?php echo wp_json_encode( __( 'Cargando...', 'temahijo' ) ); ?>,
          empty: <?php echo wp_json_encode( __( 'Sin resultados.', 'temahijo' ) ); ?>,
          error: <?php echo wp_json_encode( __( 'Error cargando imágenes.', 'temahijo' ) ); ?>,
        }
      };
      const modal = document.getElementById('drive-picker-modal-fallback');
      if (!modal) return;
      const grid = modal.querySelector('[data-grid]');
      const loading = modal.querySelector('.drive-picker-modal__loading');
      const empty = modal.querySelector('.drive-picker-modal__empty');
      const searchInput = modal.querySelector('.drive-picker-modal__search');

      function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
      }

      modal.addEventListener('click', (ev) => {
        if (ev.target.dataset.close !== undefined) {
          closeModal();
        }
      });

      function loadImages(term) {
        grid.innerHTML = '';
        empty.style.display = 'none';
        loading.style.display = 'block';
        const restUrl = cfg.restUrl;
        const fetchPromise = restUrl
          ? fetch(restUrl + (restUrl.includes('?') ? '&' : '?') + 's=' + encodeURIComponent(term || ''), { credentials: 'same-origin' })
          : fetch(cfg.ajaxUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'drak_drive_picker_list_public',
                nonce: cfg.nonce || '',
                s: term || ''
              })
            });

        fetchPromise
          .then(r => r.json())
          .then(data => {
            loading.style.display = 'none';
            const items = data?.data?.items || data?.items || [];
            if (!items.length) {
              empty.style.display = 'block';
              return;
            }
            items.forEach(item => {
              const img = document.createElement('img');
              img.src = item.url;
              img.alt = item.title;
              img.dataset.id = item.id;
              img.title = item.title;
              img.addEventListener('click', () => selectImage(item));
              grid.appendChild(img);
            });
          })
          .catch(() => {
            loading.textContent = cfg.texts.error;
          });
      }

      function selectImage(item) {
        const btn = modal.dataset.currentButton ? document.querySelector('[data-picker-btn="' + modal.dataset.currentButton + '"]') : null;
        const hero = btn ? btn.closest('.personaje-hero')?.querySelector('[data-hero-image]') : null;
        if (hero) {
          hero.style.backgroundImage = "url('" + item.url + "')";
        }
        const postId = btn ? btn.dataset.postId : '';
        const context = btn ? btn.dataset.heroContext || '' : '';
        if (postId) {
          const payload = new URLSearchParams({
            action: 'drak_set_personaje_drive_image',
            post_id: postId,
            gallery_item_id: item.id,
            context: context,
            nonce: cfg.nonce
          });
          fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload
          });
        }
        closeModal();
      }

      if (searchInput) {
        searchInput.addEventListener('input', () => loadImages(searchInput.value || ''));
      }

      document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.personaje-hero__change');
        if (!btn) return;
        ev.preventDefault();
        // marca botón actual para volver a él al guardar
        const btnId = 'btn-' + Math.random().toString(36).slice(2);
        btn.setAttribute('data-picker-btn', btnId);
        modal.dataset.currentButton = btnId;
        loadImages('');
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
      });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'drak_drive_hero_picker_footer_markup', 100 );
/**
 * Obtiene listado de pociones desde data/items.json (prefijo "Potion").
 *
 * @return array
 */
function drak_get_potion_options() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }
    $cache = [];
    $data  = drak_load_items_data();
    if ( ! is_array( $data ) ) {
        return $cache;
    }
    $names = [];
    foreach ( $data as $item ) {
        $name    = isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : '';
        $name_es = isset( $item['name_es'] ) && is_string( $item['name_es'] ) ? $item['name_es'] : '';
        $display = $name_es ? $name_es : $name;

        if ( ! $name && ! $name_es ) {
            continue;
        }
        if ( 0 === stripos( $name, 'Potion' ) || ( $name_es && 0 === stripos( $name_es, 'Poción' ) ) ) {
            $names[] = $display;
        }
    }
    $names = array_values( array_unique( $names ) );
    sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
    $cache = $names;
    return $cache;
}

/**
 * Obtiene listado de pergaminos (scrolls) desde items.json (prefijo "Scroll").
 *
 * @return array
 */
function drak_get_scroll_options() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }
    $cache = [];
    $data  = drak_load_items_data();
    if ( ! is_array( $data ) ) {
        return $cache;
    }

    $names = [];
    foreach ( $data as $item ) {
        $name    = isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : '';
        $name_es = isset( $item['name_es'] ) && is_string( $item['name_es'] ) ? $item['name_es'] : '';
        $display = $name_es ? $name_es : $name;

        if ( ! $name && ! $name_es ) {
            continue;
        }
        if ( 0 === stripos( $name, 'Scroll' ) || ( $name_es && 0 === stripos( $name_es, 'Pergamino' ) ) ) {
            $names[] = $display;
        }
    }
    $names = array_values( array_unique( $names ) );
    sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
    $cache = $names;
    return $cache;
}

/**
 * Obtiene descripciones de pociones: [ name => html ].
 */
function drak_get_potion_entries() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }
    $cache = [];
    $data  = drak_load_items_data();
    if ( ! is_array( $data ) ) {
        return $cache;
    }

    foreach ( $data as $item ) {
        $name    = isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : '';
        $name_es = isset( $item['name_es'] ) && is_string( $item['name_es'] ) ? $item['name_es'] : '';
        $display = $name_es ? $name_es : $name;

        if ( ! $name && ! $name_es ) {
            continue;
        }
        if ( 0 !== stripos( $name, 'Potion' ) && ( ! $name_es || 0 !== stripos( $name_es, 'Poción' ) ) ) {
            continue;
        }
        $entries_raw = $item['entries_es'] ?? $item['entries'] ?? [];
        if ( is_string( $entries_raw ) ) {
            $entries_raw = [ $entries_raw ];
        }
        if ( ! is_array( $entries_raw ) ) {
            $entries_raw = [];
        }
        $parts = [];
        foreach ( $entries_raw as $entry ) {
            if ( is_string( $entry ) ) {
                $parts[] = $entry;
            } elseif ( is_array( $entry ) && isset( $entry['entries'] ) && is_array( $entry['entries'] ) ) {
                foreach ( $entry['entries'] as $sub ) {
                    if ( is_string( $sub ) ) {
                        $parts[] = $sub;
                    }
                }
            }
        }
        if ( ! $parts ) {
            continue;
        }
        $html = '';
        foreach ( $parts as $p ) {
            $html .= '<p>' . esc_html( $p ) . '</p>';
        }
        $cache[ $display ]                     = $html;
        $cache[ sanitize_title( $display ) ]   = $html;
        if ( $name && $name !== $display ) {
            $cache[ $name ]                  = $html;
            $cache[ sanitize_title( $name ) ] = $html;
        }
    }

    return $cache;
}

/**
 * Devuelve entradas completas de items por nombre (html ya escapado).
 *
 * @return array [ name => html ]
 */
function drak_get_item_entries() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }
    $cache = [];
    $data  = drak_load_items_data();
    if ( ! is_array( $data ) ) {
        return $cache;
    }

    foreach ( $data as $item ) {
        $name    = isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : '';
        $name_es = isset( $item['name_es'] ) && is_string( $item['name_es'] ) ? $item['name_es'] : '';
        $display = $name_es ? $name_es : $name;

        if ( ! $name && ! $name_es ) {
            continue;
        }
        $entries_raw = $item['entries_es'] ?? $item['entries'] ?? [];
        if ( is_string( $entries_raw ) ) {
            $entries_raw = [ $entries_raw ];
        }
        if ( ! is_array( $entries_raw ) ) {
            $entries_raw = [];
        }
        $parts = [];
        foreach ( $entries_raw as $entry ) {
            if ( is_string( $entry ) ) {
                $parts[] = $entry;
            } elseif ( is_array( $entry ) && isset( $entry['entries'] ) && is_array( $entry['entries'] ) ) {
                foreach ( $entry['entries'] as $sub ) {
                    if ( is_string( $sub ) ) {
                        $parts[] = $sub;
                    }
                }
            }
        }
        if ( ! $parts ) {
            continue;
        }
        $html = '';
        foreach ( $parts as $p ) {
            $html .= '<p>' . esc_html( $p ) . '</p>';
        }
        $cache[ $display ]                       = $html;
        $cache[ sanitize_title( $display ) ]     = $html;
        if ( $name && $name !== $display ) {
            $cache[ $name ]                    = $html;
            $cache[ sanitize_title( $name ) ] = $html;
        }
    }

    return $cache;
}

/**
 * Backfill unico: asigna la campaña "cronicas-de-drakkenheim" a todos los diarios sin campaña.
 */
function drak_backfill_diario_campaign_once() {
    if ( get_option( 'drak_diario_campaign_backfill_done' ) ) {
        return;
    }

    $campaign = get_page_by_path( 'cronicas-de-drakkenheim', OBJECT, 'campaign' );
    if ( ! $campaign || empty( $campaign->ID ) ) {
        return;
    }
    $campaign_id = (int) $campaign->ID;

    $query = new WP_Query( [
        'post_type'      => 'diario',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future', 'auto-draft' ],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    if ( $query->have_posts() ) {
        foreach ( $query->posts as $post_id ) {
            $current = get_field( 'campaign', $post_id );
            $has_campaign = false;
            if ( is_array( $current ) ) {
                $has_campaign = ! empty( array_filter( $current ) );
            } elseif ( $current ) {
                $has_campaign = true;
            }
            if ( $has_campaign ) {
                continue;
            }
            if ( function_exists( 'update_field' ) ) {
                update_field( 'campaign', $campaign_id, $post_id );
            } else {
                update_post_meta( $post_id, 'campaign', $campaign_id );
            }
        }
    }

    update_option( 'drak_diario_campaign_backfill_done', 1, false );
}
add_action( 'init', 'drak_backfill_diario_campaign_once', 20 );

/**
 * Metabox manual para asignar Campaña y visibilidad en Diario (por si ACF no se muestra).
 */
function drak_diario_add_campaign_metabox() {
    add_meta_box(
        'drak-diario-campaign',
        'Asignación de campaña',
        'drak_diario_render_campaign_metabox',
        'diario',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes_diario', 'drak_diario_add_campaign_metabox' );

function drak_diario_render_campaign_metabox( $post ) {
    wp_nonce_field( 'drak_diario_campaign_save', 'drak_diario_campaign_nonce' );
    $current_campaign  = get_field( 'campaign', $post->ID );
    if ( is_array( $current_campaign ) ) {
        $current_campaign = array_filter( $current_campaign );
        $current_campaign = reset( $current_campaign );
    }
    if ( ! $current_campaign ) {
        $current_campaign = get_post_meta( $post->ID, 'campaign', true );
    }
    $current_campaign  = intval( $current_campaign );
    $current_visibility = get_post_meta( $post->ID, 'campaign_visibility', true );
    if ( ! $current_visibility ) {
        $current_visibility = 'public';
    }

    $campaigns = get_posts( [
        'post_type'      => 'campaign',
        'post_status'    => [ 'publish', 'private' ],
        'posts_per_page' => 200,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ] );
    ?>
    <p><label for="drak_diario_campaign_select"><strong>Campaña *</strong></label></p>
    <select name="drak_diario_campaign_select" id="drak_diario_campaign_select" style="width:100%;">
        <option value="">Selecciona</option>
        <?php foreach ( $campaigns as $cid ) : ?>
            <option value="<?php echo esc_attr( $cid ); ?>"<?php selected( $current_campaign, $cid ); ?>>
                <?php echo esc_html( get_the_title( $cid ) ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p style="margin-top:10px;"><label for="drak_diario_visibility"><strong>Visibilidad en campaña</strong></label></p>
    <select name="drak_diario_visibility" id="drak_diario_visibility" style="width:100%;">
        <option value="public"<?php selected( $current_visibility, 'public' ); ?>>Visible para todos los jugadores</option>
        <option value="dm_only"<?php selected( $current_visibility, 'dm_only' ); ?>>Solo DM/Admin</option>
    </select>
    <?php
}

function drak_diario_save_campaign_metabox( $post_id, $post, $update ) {
    if ( ! isset( $_POST['drak_diario_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['drak_diario_campaign_nonce'] ) ), 'drak_diario_campaign_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( 'diario' !== ( $post->post_type ?? '' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $campaign_id = isset( $_POST['drak_diario_campaign_select'] ) ? intval( $_POST['drak_diario_campaign_select'] ) : 0;
    $visibility  = isset( $_POST['drak_diario_visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['drak_diario_visibility'] ) ) : 'public';

    if ( $campaign_id ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( 'campaign', $campaign_id, $post_id );
        } else {
            update_post_meta( $post_id, 'campaign', $campaign_id );
        }
    }
    update_post_meta( $post_id, 'campaign_visibility', $visibility );
}
add_action( 'save_post_diario', 'drak_diario_save_campaign_metabox', 5, 3 );


/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'temahijo-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_TEMAHIJO_VERSION, 'all' );
    wp_enqueue_style('tema-principal', get_stylesheet_directory_uri() . '/style.css');
    wp_enqueue_style('estilos-personaje', get_stylesheet_directory_uri() . '/css/personaje.css');
    wp_enqueue_style('estilos-inventario', get_stylesheet_directory_uri() . '/css/inventario.css');
	wp_enqueue_style('estilos-hoja-personaje', get_stylesheet_directory_uri() . '/css/hoja-personaje.css');
    wp_enqueue_style('estilos-grimorio', get_stylesheet_directory_uri() . '/css/grimorio.css');

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

function enqueue_custom_scripts() {
    if (get_query_var('inventario_personaje') == 1 || is_page_template('page-inventario-personaje.php')) {
        wp_enqueue_script(
            'inventario-armas',
            get_stylesheet_directory_uri() . '/js/inventario-armas.js',
            array(),
            null,
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');

/**
 * Devuelve el ID de campaña en contexto (post de campaña o contenido asociado).
 *
 * @return int
 */
function drak_get_current_campaign_id() {
    if ( is_singular( 'campaign' ) ) {
        return get_the_ID();
    }

    $related_types = [ 'personaje', 'npc', 'lugar', 'faccion', 'personaje_wiki', 'diario', 'lore-entry', 'homebrew_entry' ];
    if ( is_singular( $related_types ) ) {
        $campaign = get_field( 'campaign', get_the_ID() );
        if ( is_array( $campaign ) ) {
            $campaign = array_filter( $campaign );
            $campaign = reset( $campaign );
        }
        return intval( $campaign );
    }

    $personaje_id = drak_get_request_personaje_id();
    if ( $personaje_id ) {
        $campaign = get_field( 'campaign', $personaje_id );
        if ( is_array( $campaign ) ) {
            $campaign = array_filter( $campaign );
            $campaign = reset( $campaign );
        }
        return intval( $campaign );
    }

    return 0;
}

/**
 * Pinta la cabecera de campaña en páginas asociadas (sin mostrar el botón de hub fuera del home de campaña).
 */
function drak_render_campaign_header_bar() {
    // Evita duplicar en el home de campaña, donde ya existe la cabecera original con botón.
    if ( is_singular( 'campaign' ) ) {
        return;
    }

    $campaign_id = drak_get_current_campaign_id();
    if ( ! $campaign_id ) {
        return;
    }

    $cover_id    = get_field( 'campaign_cover_image', $campaign_id );
    $cover_url   = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
    if ( ! $cover_url ) {
        $cover_url = get_the_post_thumbnail_url( $campaign_id, 'full' );
    }

    $title      = get_the_title( $campaign_id );
    $target_url = get_permalink( $campaign_id );
    $logo_id    = drak_get_campaign_logo_id( $campaign_id );
    $logo_html  = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, [ 'class' => 'campaign-hero__logo-img' ] ) : '';

    ?>
    <style>
    .campaign-hero-shell {
      --campaign-horizontal-padding: 53px;
      max-width: 1100px;
      margin: 16px auto 0;
      padding: 0 var(--campaign-horizontal-padding);
    }
    .campaign-hero {
      position: relative;
      padding: 154px 53px;
      background-size: cover;
      background-position: center;
      margin-top: 16px;
      border-radius: 12px;
      overflow: hidden;
    }
    .campaign-hero__overlay {
      position: absolute;
      inset: 0;
      background: transparent;
    }
    .campaign-hero__link {
      display: block;
      color: inherit;
      text-decoration: none;
      position: relative;
      z-index: 1;
    }
    .campaign-hero__inner {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 53px;
      position: relative;
    }
    .campaign-hero__content {
      position: relative;
      max-width: 1100px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
      text-align: center;
      padding-bottom: 80px;
    }
    .campaign-hero-logo {
      position: absolute;
      left: 50%;
      bottom: -140px;
      transform: translateX(-50%);
      max-width: 270px;
    }
    .campaign-hero-logo-img {
      display: block;
      width: 100%;
      height: auto;
    }
    .campaign-hero__home-link {
      position: absolute;
      bottom: -140px;
      left: -92px;
      padding: 4px 8px;
      border-radius: 8px;
      border: 1px solid var(--accent, #9b5cff);
      background: var(--accent-dark, rgba(155, 92, 255, 0.2));
      color: var(--text);
      text-decoration: none;
      display: inline-flex;
      gap: 6px;
      align-items: center;
      font-size: 10px;
    }
    .campaign-hero__title {
      margin: 0;
      font-size: clamp(38px, 6vw, 64px);
      letter-spacing: -0.02em;
      color: var(--accent, #9b5cff);
    }
    @media (max-width: 640px) {
      .campaign-hero-shell {
        margin-top: 8px;
        padding: 0 12px;
      }
      .campaign-hero {
        padding: 48px 16px;
      }
    }
    </style>
    <div class="campaign-hero-shell">
      <div class="campaign-hero"<?php echo $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : ''; ?>>
          <a class="campaign-hero__link" href="<?php echo esc_url( $target_url ); ?>" aria-label="<?php echo esc_attr( 'Ir al inicio de la campaña ' . $title ); ?>">
              <div class="campaign-hero__overlay"></div>
              <div class="campaign-hero__inner">
                <div class="campaign-hero__content">
                    <?php if ( $logo_html ) : ?>
                      <div class="campaign-hero-logo"><?php echo $logo_html; ?></div>
                    <?php endif; ?>
                </div>
              </div>
          </a>
      </div>
    </div>
    <?php
}
add_action( 'wp_body_open', 'drak_render_campaign_header_bar', 5 );

/**
 * Botonera de navegación entre Hoja / Inventario / Grimorio / Combate.
 * Se muestra justo debajo del hero de campaña (wp_body_open prioridad 6).
 */
function drak_render_personaje_nav_bar() {
    if (
        ! is_page_template( 'page-hoja-personaje.php' ) &&
        ! is_page_template( 'page-inventario-personaje.php' ) &&
        ! is_page_template( 'page-grimorio-personaje.php' ) &&
        ! is_page_template( 'page-combate-personaje.php' )
    ) {
        return;
    }

    $slug      = get_query_var( 'personaje_slug' );
    $personaje = $slug ? get_page_by_path( $slug, OBJECT, 'personaje' ) : null;
    if ( ! $personaje ) {
        return;
    }

    $personaje_slug = $personaje->post_name;
    $nav_links = [
        'hoja'       => home_url( '/hoja-personaje/' . $personaje_slug ),
        'inventario' => home_url( '/inventario/' . $personaje_slug ),
        'grimorio'   => home_url( '/grimorio/' . $personaje_slug ),
        'combate'    => home_url( '/combate/' . $personaje_slug ),
    ];

    $template = get_page_template_slug() ?: '';
    $active = 'hoja';
    if ( str_contains( $template, 'inventario' ) ) {
        $active = 'inventario';
    } elseif ( str_contains( $template, 'grimorio' ) ) {
        $active = 'grimorio';
    } elseif ( str_contains( $template, 'combate' ) ) {
        $active = 'combate';
    }
    ?>
    <div class="personaje-hero-bar">
      <div class="personaje-hero__tabs">
        <a class="personaje-hero__tab <?php echo $active === 'hoja' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $nav_links['hoja'] ); ?>">Hoja de Personaje</a>
        <a class="personaje-hero__tab <?php echo $active === 'inventario' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $nav_links['inventario'] ); ?>">Inventario</a>
        <a class="personaje-hero__tab <?php echo $active === 'grimorio' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $nav_links['grimorio'] ); ?>">Grimorio</a>
        <a class="personaje-hero__tab <?php echo $active === 'combate' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $nav_links['combate'] ); ?>">Mod Combate</a>
      </div>
    </div>
    <?php
}
add_action( 'wp_body_open', 'drak_render_personaje_nav_bar', 6 );

/**
 * =========================================================
 * LOGO DE CAMPAÑA (metadato _campaign_logo_id, sin ACF)
 * =========================================================
 */

/**
 * Añade el metabox "Logo de la campaña" al CPT campaign.
 */
add_action( 'add_meta_boxes', 'drak_add_campaign_logo_metabox' );

function drak_add_campaign_logo_metabox() {
    add_meta_box(
        'drak_campaign_logo',
        __( 'Logo de la campaña', 'textdomain' ),
        'drak_render_campaign_logo_metabox',
        'campaign',
        'side',
        'default'
    );
}

/**
 * Render del metabox con el media uploader.
 */
function drak_render_campaign_logo_metabox( $post ) {
    wp_nonce_field( 'drak_save_campaign_logo', 'drak_campaign_logo_nonce' );
    wp_enqueue_media();

    $logo_id  = intval( get_post_meta( $post->ID, '_campaign_logo_id', true ) );
    $logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, [ 'style' => 'max-width:100%;height:auto;display:block;margin-bottom:8px;' ] ) : '';
    ?>
    <div id="drak-campaign-logo-wrapper">
        <div class="drak-campaign-logo-preview">
            <?php if ( $logo_img ) echo $logo_img; ?>
        </div>

        <input type="hidden" id="drak_campaign_logo_id" name="drak_campaign_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">

        <p>
            <button type="button" class="button" id="drak_campaign_logo_upload">
                <?php echo $logo_id ? esc_html__( 'Cambiar logo', 'textdomain' ) : esc_html__( 'Subir logo', 'textdomain' ); ?>
            </button>
            <button type="button" class="button link-button" id="drak_campaign_logo_remove"><?php esc_html_e( 'Quitar logo', 'textdomain' ); ?></button>
        </p>
        <p class="description">
            <?php esc_html_e( 'Imagen que se mostrará como logo centrado en la parte inferior de la cabecera de la campaña.', 'textdomain' ); ?>
        </p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        let frame;
        const $wrapper = $('#drak-campaign-logo-wrapper');
        const $preview = $wrapper.find('.drak-campaign-logo-preview');
        const $input   = $('#drak_campaign_logo_id');

        $('#drak_campaign_logo_upload').on('click', function(e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: '<?php echo esc_js( __( 'Seleccionar logo de campaña', 'textdomain' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Usar esta imagen', 'textdomain' ) ); ?>' },
                multiple: false
            });
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                $preview.html('<img src="' + attachment.url + '" style="max-width:100%;height:auto;display:block;margin-bottom:8px;" />');
            });
            frame.open();
        });

        $('#drak_campaign_logo_remove').on('click', function(e) {
            e.preventDefault();
            $input.val('');
            $preview.empty();
        });
    });
    </script>
    <?php
}

/**
 * Guardado del metadato _campaign_logo_id.
 */
add_action( 'save_post_campaign', 'drak_save_campaign_logo_meta' );
function drak_save_campaign_logo_meta( $post_id ) {
    if ( ! isset( $_POST['drak_campaign_logo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['drak_campaign_logo_nonce'] ) ), 'drak_save_campaign_logo' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['drak_campaign_logo_id'] ) ) {
        $logo_id = intval( $_POST['drak_campaign_logo_id'] );
        if ( $logo_id > 0 ) {
            update_post_meta( $post_id, '_campaign_logo_id', $logo_id );
        } else {
            delete_post_meta( $post_id, '_campaign_logo_id' );
        }
    }
}

/**
 * Devuelve el ID del logo de una campaña (metadato _campaign_logo_id).
 */
function drak_get_campaign_logo_id( $post_id = null ) {
    $post_id = $post_id ?: get_the_ID();
    if ( ! $post_id ) {
        return 0;
    }
    $logo_id = intval( get_post_meta( $post_id, '_campaign_logo_id', true ) );
    return $logo_id > 0 ? $logo_id : 0;
}

/**
 * Renderiza el logo de campaña en la cabecera (si existe).
 */
function drak_render_campaign_logo( $post_id = null ) {
    $logo_id = drak_get_campaign_logo_id( $post_id );
    if ( ! $logo_id ) {
        return;
    }
    echo '<div class="campaign-hero__logo">';
    echo wp_get_attachment_image( $logo_id, 'medium', false, [ 'class' => 'campaign-hero__logo-img' ] );
    echo '</div>';
}

function drak_get_homebrew_capabilities() {
    return [
        'edit_post'              => 'edit_homebrew_entry',
        'read_post'              => 'read_homebrew_entry',
        'delete_post'            => 'delete_homebrew_entry',
        'edit_posts'             => 'edit_homebrew_entries',
        'edit_others_posts'      => 'edit_others_homebrew_entries',
        'publish_posts'          => 'publish_homebrew_entries',
        'read_private_posts'     => 'read_private_homebrew_entries',
        'delete_posts'           => 'delete_homebrew_entries',
        'delete_private_posts'   => 'delete_private_homebrew_entries',
        'delete_published_posts' => 'delete_published_homebrew_entries',
        'delete_others_posts'    => 'delete_others_homebrew_entries',
        'edit_private_posts'     => 'edit_private_homebrew_entries',
        'edit_published_posts'   => 'edit_published_homebrew_entries',
    ];
}

/**
 * Define y sincroniza el rol Dungeon Master con capacidades centradas en lectura.
 */
function drak_get_dm_capabilities() {
    return [
        'read'                 => true,
        'read_private_pages'   => true,
        'read_private_posts'   => true,
        'view_all_personajes'  => true,
        'edit_posts'           => false,
        'delete_posts'         => false,
        'publish_posts'        => false,
        'manage_options'       => false,
        'activate_plugins'     => false,
        'install_plugins'      => false,
    ];
}

function drak_register_dm_role() {
    $caps = drak_get_dm_capabilities();
    $role = get_role( 'dm' );

    if ( ! $role ) {
        add_role( 'dm', 'Dungeon Master', $caps );
        $role = get_role( 'dm' );
    } else {
        foreach ( $caps as $cap => $grant ) {
            if ( $grant && ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }

    if ( $role ) {
        $restricted_caps = [
            'edit_posts',
            'edit_others_posts',
            'publish_posts',
            'delete_posts',
            'delete_others_posts',
            'delete_published_posts',
            'delete_private_posts',
            'edit_published_posts',
            'edit_private_posts',
            'activate_plugins',
            'install_plugins',
            'manage_options',
            'create_posts',
            'upload_files',
        ];

        foreach ( $restricted_caps as $cap ) {
            $grant = $caps[ $cap ] ?? false;
            if ( ! $grant && $role->has_cap( $cap ) ) {
                $role->remove_cap( $cap );
            }
        }
    }

    $homebrew_caps      = drak_get_homebrew_capabilities();
    $homebrew_caps_list = array_values( $homebrew_caps );
    if ( $role ) {
        foreach ( $homebrew_caps_list as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }

    $admin_caps = array_merge( [ 'view_all_personajes' ], $homebrew_caps_list );
    $admin      = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( $admin_caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'drak_register_dm_role' );

// Mover extracto dentro de entry-content
add_action('wp_footer', function(){
  if (is_category()) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!document.body.classList.contains('archive')) return;
      document.querySelectorAll('.ast-article-post').forEach(function (article) {
        const exWrap = article.querySelector('.ast-excerpt-container');
        const entry  = article.querySelector('.entry-content.clear');
        if (exWrap) exWrap.classList.add('entry-content', 'clear');
        if (entry && entry.textContent.trim() === '') entry.remove();
      });
    });
    </script>
  <?php endif;
});

/** ========= Placeholders por categoría ========= **/

// Mapea slugs de categoría => ID de la imagen placeholder (Mediateca)
function drak_placeholder_map() {
    return array(
        'npc'     => 2872,  // <— pon aquí ID numérico
        'lugares' => 2873,
        'pj'      => 2872,
        'diario'  => 0,
        'default' => 0,
    );
}

// Devuelve el placeholder correcto en base a las categorías del post
function drak_pick_placeholder_for_post( $post_id ) {
    $map = drak_placeholder_map();
    $cats = wp_get_post_categories( $post_id, array('fields'=>'all') );
    foreach ( $cats as $c ) {
        $slug = get_category( $c )->slug;
        if ( isset($map[$slug]) && intval($map[$slug]) > 0 ) {
            return intval($map[$slug]);
        }
    }
    return isset($map['default']) ? intval($map['default']) : 0;
}

// 1) Al guardar post: si no hay miniatura, pone la de placeholder
add_action('save_post', function($post_id, $post, $update){
    if ( wp_is_post_revision($post_id) ) return;
    if ( $post->post_type !== 'post' ) return;
    if ( has_post_thumbnail($post_id) ) return;

    $ph = drak_pick_placeholder_for_post($post_id);
    if ( $ph ) {
        set_post_thumbnail( $post_id, $ph );
    }
}, 10, 3);

// 2) Endpoint manual: https://tusitio.com/?set_placeholders=1
add_action('template_redirect', function(){
    if ( ! isset($_GET['set_placeholders']) ) return;

    $count = 0;
    $map = drak_placeholder_map();

    $q = new WP_Query(array(
        'post_type'      => 'post',
        'post_status'    => array('publish','draft','pending','future','private'),
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_thumbnail_id', 'value' => '0', 'compare' => '=' ),
            array( 'key' => '_thumbnail_id', 'value' => '',  'compare' => '=' ),
        ),
        'fields'         => 'ids',
    ));

    foreach ( $q->posts as $pid ) {
        $ph = drak_pick_placeholder_for_post($pid);
        if ( $ph ) { set_post_thumbnail($pid, $ph); $count++; }
    }

    wp_die( sprintf('Placeholders aplicados a %d entradas.', $count) );
});

/**
 * Forzar que la miga "Wiki" enlace a /wiki/ (Astra breadcrumb nativo)
 */
add_filter( 'astra_the_breadcrumb', 'drak_fix_wiki_breadcrumb_to_page', 20 );
function drak_fix_wiki_breadcrumb_to_page( $html ) {

    // Localiza el enlace real de la categoría "wiki" (por si cambia el dominio o el slug)
    $term = get_term_by( 'slug', 'wiki', 'category' );
    if ( $term && ! is_wp_error( $term ) ) {
        $term_link = get_term_link( $term );               // p.ej. https://adayfs.com/category/wiki/
        $target    = home_url( '/wiki/' );                 // p.ej. https://adayfs.com/wiki/

        // Reemplaza el enlace en el HTML final del breadcrumb
        $html = str_replace( esc_url( $term_link ), esc_url( $target ), $html );

        // Por si hay variaciones con/ sin barra final (algunos sitios la quitan)
        $html = str_replace( untrailingslashit( esc_url( $term_link ) ), esc_url( $target ), $html );
    }

    return $html;
}

/**
 * 1) Cambiar el enlace del item "Wiki" en las migas de Astra.
 *    (Astra usa astra_breadcrumb_trail_items para construir el trail)
 */
add_filter( 'astra_breadcrumb_trail_items', 'drak_fix_wiki_breadcrumb_items', 20, 2 );
function drak_fix_wiki_breadcrumb_items( $items, $args ) {
	$target    = esc_url( home_url( '/wiki/' ) );

	// Si existe el término "wiki", cogemos su URL real por si cambia el dominio/estructura.
	$src_term  = get_term_by( 'slug', 'wiki', 'category' );
	$src_url   = $src_term && ! is_wp_error( $src_term ) ? esc_url( get_term_link( $src_term ) ) : '';

	foreach ( $items as &$it ) {
		// Caso ideal: encontramos exactamente la URL del término.
		if ( $src_url && strpos( $it, $src_url ) !== false ) {
			$it = str_replace( $src_url, $target, $it );
			continue;
		}
		// Fallback robusto: cualquier /category/wiki/ (con o sin barra final).
		if ( preg_match( '~href=["\']([^"\']+/category/wiki/?)["\']~i', $it ) ) {
			$it = preg_replace( '~href=["\']([^"\']+/category/wiki/?)["\']~i', 'href="'.$target.'"', $it );
		}
	}
	return $items;
}

/**
 * 2) Redirección SEO: si alguien entra a /category/wiki/, lo enviamos a /wiki/
 */
add_action( 'template_redirect', function () {
	if ( is_category( 'wiki' ) ) {
		wp_redirect( home_url( '/wiki/' ), 301 );
		exit;
	}
} );


// Mostrar metadatos en entradas de categoría 'quests'
add_filter('the_content', 'mostrar_campos_quest_en_contenido');
function mostrar_campos_quest_en_contenido($content) {
    if (is_single() && has_category('quests')) {
        $estado     = get_post_meta(get_the_ID(), 'estado_de_la_mision', true);
        $encargado  = get_post_meta(get_the_ID(), 'encargado', true);
        $ubicacion  = get_post_meta(get_the_ID(), 'ubicacion', true);

        $npc_url    = $encargado ? home_url('/wiki/npc/' . sanitize_title($encargado)) : '';
        $lugar_url  = $ubicacion ? home_url('/wiki/lugares/' . sanitize_title($ubicacion)) : '';

        ob_start();
        echo '<div class="quest-meta">';

        if ($estado) {
            $estado_clase = strtolower(str_replace(' ', '-', $estado));
            echo '<p class="estado estado-' . esc_attr($estado_clase) . '">Estado: ' . esc_html($estado) . '</p>';
        }

        if ($encargado) {
            echo '<p class="encargado">Encargado: <a href="' . esc_url($npc_url) . '">' . esc_html($encargado) . '</a></p>';
        }

        if ($ubicacion) {
            echo '<p class="ubicacion">Ubicación: <a href="' . esc_url($lugar_url) . '">' . esc_html($ubicacion) . '</a></p>';
        }

        echo '<hr></div>';
        return ob_get_clean() . $content;
    }

    return $content;
}

add_action('wp_head', function () {
    ?>
    <style>
    .estado-activa {
      color: #39ff14 !important;
    }
    .estado-completada {
      color: #66ccff !important;
    }
    .estado-fallida {
      color: #ff4c4c !important;
    }
    </style>
    <?php
});

add_action( 'init', function () {
    if ( ! isset( $_GET['spellcasting-log'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para ver este log.' );
    }

    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'spellcasting-debug.log';
    if ( ! file_exists( $log_file ) ) {
        wp_die( 'El log todavía no existe.' );
    }

    header( 'Content-Type: text/plain' );
    header( 'Content-Disposition: attachment; filename="spellcasting-debug.log"' );
    readfile( $log_file );
    exit;
} );

/**
 * Sanitiza valores provenientes de $_POST (strings o arrays) con soporte para defaults.
 */
function drak_get_post_value( $key, $default = '' ) {
	if ( ! isset( $_POST[ $key ] ) ) {
		return $default;
	}

    $value = wp_unslash( $_POST[ $key ] );

    if ( is_array( $value ) ) {
        return array_map( 'sanitize_text_field', $value );
    }

	return sanitize_text_field( $value );
}

/**
 * Normaliza un campo de arma ACF a un payload limpio para JS.
 */
function drak_get_weapon_payload( int $post_id, string $field_key = 'arma_principal' ): array {
	$raw = function_exists( 'get_field' ) ? get_field( $field_key, $post_id ) : null;

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		}
	}

	if ( ! is_array( $raw ) ) {
		return [];
	}

	$props = [];
	if ( isset( $raw['properties'] ) ) {
		$prop_raw = $raw['properties'];
		if ( is_string( $prop_raw ) ) {
			$prop_raw = array_map( 'trim', explode( ',', $prop_raw ) );
		}
		if ( is_array( $prop_raw ) ) {
			foreach ( $prop_raw as $prop ) {
				if ( $prop === '' || $prop === null ) {
					continue;
				}
				$props[] = sanitize_text_field( $prop );
			}
		}
	}

	return [
		'name'                 => sanitize_text_field( $raw['name'] ?? '' ),
		'slug'                 => sanitize_title( $raw['slug'] ?? '' ),
		'category'             => sanitize_text_field( $raw['category'] ?? '' ),
		'damage_dice'          => sanitize_text_field( $raw['damage_dice'] ?? '' ),
		'damage_type'          => sanitize_text_field( $raw['damage_type'] ?? '' ),
		'weight'               => sanitize_text_field( $raw['weight'] ?? '' ),
		'properties'           => $props,
		'is_magical'           => ! empty( $raw['es_magica'] ),
		'requires_attunement'  => ! empty( $raw['requiere_attunement'] ),
		'description'          => sanitize_text_field( $raw['descripcion'] ?? '' ),
	];
}

/**
 * Compat: mantiene la firma anterior para el arma principal.
 */
function drak_get_weapon_main_payload( int $post_id ): array {
	return drak_get_weapon_payload( $post_id, 'arma_principal' );
}

/**
 * Helper para sanitizar valores desde un array arbitrario (por ejemplo, $_POST recibido via AJAX).
 */
function drak_get_post_value_from_array( $array, $key, $default = '' ) {
	if ( ! isset( $array[ $key ] ) ) {
		return $default;
	}
	$value = wp_unslash( $array[ $key ] );
	if ( is_array( $value ) ) {
		return array_map( 'sanitize_text_field', $value );
	}
	return sanitize_text_field( $value );
}

/**
 * Procesa y guarda los campos del inventario (form o AJAX).
 */
function drak_process_inventory_submission( $post_id, $source ) {
	if ( ! drak_user_can_manage_personaje( $post_id ) ) {
		return new WP_Error( 'forbidden', 'No tienes permisos para actualizar este inventario.' );
	}

	$fuerza_score = intval( get_field( 'cs_fuerza', $post_id ) );
	$gold_cap = 500;
	if ( $fuerza_score >= 18 ) {
		$gold_cap = 2000;
	} elseif ( $fuerza_score >= 16 ) {
		$gold_cap = 1500;
	} elseif ( $fuerza_score >= 12 ) {
		$gold_cap = 1000;
	}

	$int_score = intval( get_field( 'cs_inteligencia', $post_id ) );
	$wis_score = intval( get_field( 'cs_sabiduria', $post_id ) );
	$dex_score = intval( get_field( 'cs_destreza', $post_id ) );
	$can_potion_slot_4 = ( $int_score > 16 || $wis_score > 16 || $dex_score > 16 );
	$can_scroll_extra  = ( $int_score > 16 || $wis_score > 16 );

	if ( isset( $source['golden_coins'] ) ) {
		$oro = max( 0, intval( drak_get_post_value_from_array( $source, 'golden_coins', 0 ) ) );
		$oro = min( $oro, $gold_cap );
		update_field( 'golden_coins', $oro, $post_id );
	}

	if ( isset( $source['arma_principal'] ) && is_array( $source['arma_principal'] ) ) {
		$arma_data = drak_get_post_value_from_array( $source, 'arma_principal', [] );
		update_field( 'arma_principal', $arma_data, $post_id );
	}
	if ( isset( $source['arma_secundaria'] ) && is_array( $source['arma_secundaria'] ) ) {
		$arma_data_secundaria = drak_get_post_value_from_array( $source, 'arma_secundaria', [] );
		update_field( 'arma_secundaria', $arma_data_secundaria, $post_id );
	}
	if ( isset( $source['armadura'] ) && is_array( $source['armadura'] ) ) {
		$armadura_data = drak_get_post_value_from_array( $source, 'armadura', [] );
		update_field( 'armadura', $armadura_data, $post_id );
	}

	$delerium_number_fields = [
		'delerium_contamination_level' => 6,
		'delerium_chips'               => null,
		'delerium_fragments'           => null,
		'delerium_shards'              => null,
		'delerium_crystals'            => null,
		'delerium_geodas'              => null,
	];

	foreach ( $delerium_number_fields as $field => $max_value ) {
		if ( ! isset( $source[ $field ] ) ) {
			continue;
		}
		$valor = intval( drak_get_post_value_from_array( $source, $field, 0 ) );
		$valor = max( 0, $valor );
		if ( $max_value !== null ) {
			$valor = min( $valor, $max_value );
		}
		update_field( $field, $valor, $post_id );
	}

	if ( isset( $source['delerium_mutations'] ) ) {
		$mutations = sanitize_textarea_field( wp_unslash( $source['delerium_mutations'] ) );
		update_field( 'delerium_mutations', $mutations, $post_id );
	}

	if ( isset( $source['delerium_madness'] ) ) {
		$madness = sanitize_textarea_field( wp_unslash( $source['delerium_madness'] ) );
		update_field( 'delerium_madness', $madness, $post_id );
	}

	// Slots de pociones
	for ( $i = 1; $i <= 4; $i++ ) {
		$field = 'potions_slot_' . $i;
		if ( ! isset( $source[ $field ] ) ) {
			continue;
		}
		if ( 4 === $i && ! $can_potion_slot_4 ) {
			update_field( $field, '', $post_id );
			continue;
		}
		$value = drak_get_post_value_from_array( $source, $field, '' );
		update_field( $field, $value, $post_id );
	}

	// Slots de pergaminos / mapas / documentos
	for ( $i = 1; $i <= 6; $i++ ) {
		$field = 'scrolls_slot_' . $i;
		if ( ! isset( $source[ $field ] ) ) {
			continue;
		}
		if ( $i > 4 && ! $can_scroll_extra ) {
			update_field( $field, '', $post_id );
			continue;
		}
		$value = drak_get_post_value_from_array( $source, $field, '' );
		update_field( $field, $value, $post_id );
	}

	if ( isset( $source['rope_slot'] ) ) {
		$value = drak_get_post_value_from_array( $source, 'rope_slot', '' );
		update_field( 'rope_slot', $value === 'rope' ? 'rope' : '', $post_id );
	}

	if ( isset( $source['ankward_slot'] ) ) {
		$value = sanitize_textarea_field( wp_unslash( $source['ankward_slot'] ) );
		update_field( 'ankward_slot', $value, $post_id );
	}

    if ( isset( $source['carcaj'] ) && ( is_array( $source['carcaj'] ) || is_string( $source['carcaj'] ) ) ) {
        $raw = is_array( $source['carcaj'] ) ? $source['carcaj'] : drak_normalize_carcaj_value( $source['carcaj'] );
        $type = sanitize_text_field( wp_unslash( $raw['type'] ?? '' ) );
        $amount = max( 0, intval( $raw['amount'] ?? 0 ) );
		$payload = [
			'type'   => $type,
			'amount' => $amount,
		];
		update_field( 'carcaj', $payload, $post_id );
		$ammo_state = [];
		if ( $type ) {
			$ammo_state[ 'quiver-' . $type ] = $amount;
		}
		update_post_meta( $post_id, 'combat_ammo_state', $ammo_state );
	}

	for ( $i = 1; $i <= 10; $i++ ) {
		$campo = 'mainpack_slot_' . $i;
		if ( isset( $source[ $campo ] ) ) {
			$valor = drak_get_post_value_from_array( $source, $campo, '' );
			update_field( $campo, $valor, $post_id );
		}
	}

	return true;
}

/**
 * Normaliza el campo de armadura guardado en el inventario.
 */
function drak_get_armor_payload( int $post_id ): array {
	$raw = function_exists( 'get_field' ) ? get_field( 'armadura', $post_id ) : null;

	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} elseif ( $raw !== '' ) {
			$raw = [ 'name' => $raw ];
		}
	}

	if ( ! is_array( $raw ) ) {
		return [];
	}

	return [
		'name'                  => sanitize_text_field( $raw['name'] ?? '' ),
		'slug'                  => sanitize_title( $raw['slug'] ?? '' ),
		'type'                  => sanitize_text_field( $raw['type'] ?? '' ),
		'ac'                    => isset( $raw['ac'] ) ? intval( $raw['ac'] ) : 0,
		'strength'              => sanitize_text_field( $raw['strength'] ?? '' ),
		'stealth_disadvantage'  => ! empty( $raw['stealth_disadvantage'] ?? $raw['stealthDisadvantage'] ),
		'weight'                => sanitize_text_field( $raw['weight'] ?? '' ),
		'value'                 => sanitize_text_field( $raw['value'] ?? '' ),
		'description'           => sanitize_text_field( $raw['description'] ?? $raw['descripcion'] ?? '' ),
	];
}

// RENDERIZAR INVENTARIO DE PERSONAJE____________________________________________________________________

function renderizar_inventario_personaje($post_id) {
    ob_start();

    if (!$post_id) return '';

    // Guardado del formulario
    if (isset($_POST['mainpack_guardar']) && isset($_POST['post_id']) && intval($_POST['post_id']) === $post_id) {
        if ( ! drak_user_can_manage_personaje( $post_id ) ) {
            echo '<div class="mensaje-confirmacion">❌ No tienes permisos para actualizar este inventario.</div>';
        } else {
            $result = drak_process_inventory_submission( $post_id, $_POST );
            if ( is_wp_error( $result ) ) {
                echo '<div class="mensaje-confirmacion">❌ ' . esc_html( $result->get_error_message() ) . '</div>';
            } else {
                echo '<div class="mensaje-confirmacion">✅ Inventario actualizado correctamente.</div>';
            }
        }
    }

    echo '<div class="mainpack-container">';
    echo '<form method="post" id="mainpack-formulario" class="formulario-inventario" data-ajax-url="' . esc_url( drak_get_admin_ajax_url() ) . '">';

    $delerium_contamination = min(6, max(0, intval(get_field('delerium_contamination_level', $post_id))));
    $delerium_counts = [
        'chips'     => max(0, intval(get_field('delerium_chips', $post_id))),
        'fragments' => max(0, intval(get_field('delerium_fragments', $post_id))),
        'shards'    => max(0, intval(get_field('delerium_shards', $post_id))),
        'crystals'  => max(0, intval(get_field('delerium_crystals', $post_id))),
        'geodas'    => max(0, intval(get_field('delerium_geodas', $post_id))),
    ];
    $delerium_mutations = get_field('delerium_mutations', $post_id);
    $delerium_madness   = get_field('delerium_madness', $post_id);

    $slot_equivalent = (int) ceil($delerium_counts['chips'] / 4);
    $slot_equivalent += (int) ceil($delerium_counts['fragments'] / 2);
    $slot_equivalent += $delerium_counts['shards'];
    $slot_equivalent += $delerium_counts['crystals'] * 2;
    $slot_equivalent += $delerium_counts['geodas'] * 20;

    echo '<section class="delerium-module">';
    echo '  <div class="delerium-header">';
    echo '    <div class="delerium-title">Delerium</div>';
    echo '    <div class="delerium-contamination">';
    echo '      <span class="delerium-contamination-label">Nivel de contaminación</span>';
    echo '      <div class="delerium-contamination-track" id="delerium-contamination-track">';
    for ($i = 1; $i <= 6; $i++) {
        $active = $i <= $delerium_contamination ? ' active' : '';
        echo '<span class="delerium-icon' . $active . '" data-index="' . $i . '"></span>';
    }
    echo '      </div>';
    echo '      <div class="delerium-contamination-input">';
    echo '        <label for="delerium_contamination" class="screen-reader-text">Nivel de contaminación</label>';
    echo '        <input type="number" id="delerium_contamination" name="delerium_contamination_level" min="0" max="6" value="' . esc_attr($delerium_contamination) . '">';
    echo '      </div>';
    echo '    </div>';
    echo '    <div class="delerium-slot-summary">';
    echo '      <span>Equivalente en slots pequeños:</span>';
    echo '      <strong id="delerium-slot-total">' . esc_html($slot_equivalent) . '</strong>';
    echo '    </div>';
    echo '  </div>';

    echo '  <div class="delerium-counts">';
    $labels = [
        'chips'     => 'Chip',
        'fragments' => 'Fragment',
        'shards'    => 'Shard',
        'crystals'  => 'Crystal',
        'geodas'    => 'Geoda',
    ];
    foreach ($labels as $key => $label) {
        $field_name = 'delerium_' . $key;
        $input_id = 'delerium_' . $key;
        echo '<div class="delerium-count">';
        echo '  <label for="' . esc_attr($input_id) . '">' . esc_html($label) . '</label>';
        echo '  <input type="number" id="' . esc_attr($input_id) . '" name="' . esc_attr($field_name) . '" min="0" value="' . esc_attr($delerium_counts[$key]) . '" class="delerium-amount-input">';
        echo '</div>';
    }
    echo '  </div>';

    echo '  <div class="delerium-notes">';
    echo '    <div>';
    echo '      <label for="delerium_mutations">Mutaciones</label>';
    echo '      <textarea id="delerium_mutations" name="delerium_mutations" rows="3">' . esc_textarea($delerium_mutations) . '</textarea>';
    echo '    </div>';
    echo '    <div>';
    echo '      <label for="delerium_madness">Locura</label>';
    echo '      <textarea id="delerium_madness" name="delerium_madness" rows="3">' . esc_textarea($delerium_madness) . '</textarea>';
    echo '    </div>';
    echo '  </div>';
    echo '</section>';

    // Slots específicos (pociones, pergaminos, cuerda, carcaj)
    $int_score = intval( get_field( 'cs_inteligencia', $post_id ) );
    $wis_score = intval( get_field( 'cs_sabiduria', $post_id ) );
    $dex_score = intval( get_field( 'cs_destreza', $post_id ) );
    $can_potion_slot_4 = ( $int_score > 16 || $wis_score > 16 || $dex_score > 16 );
    $can_scroll_extra  = ( $int_score > 16 || $wis_score > 16 );

    $potion_values = [];
    for ( $i = 1; $i <= 4; $i++ ) {
        $potion_values[ $i ] = get_field( 'potions_slot_' . $i, $post_id );
    }
    $scroll_values = [];
    for ( $i = 1; $i <= 6; $i++ ) {
        $scroll_values[ $i ] = get_field( 'scrolls_slot_' . $i, $post_id );
    }
    $rope_value     = get_field( 'rope_slot', $post_id );
    $ankward_value  = get_field( 'ankward_slot', $post_id );
    $carcaj_value_raw = get_field( 'carcaj', $post_id );
    $carcaj_value   = drak_normalize_carcaj_value( $carcaj_value_raw );
    $carcaj_type    = is_array( $carcaj_value ) ? ( $carcaj_value['type'] ?? '' ) : '';
    $carcaj_amount  = is_array( $carcaj_value ) ? intval( $carcaj_value['amount'] ?? 0 ) : 0;

    $render_special_slots = false; // se reubican más abajo
    if ( $render_special_slots ) {
    echo '<section class="inventory-special inventory-special--slots" data-inventory-special data-int="' . esc_attr( $int_score ) . '" data-wis="' . esc_attr( $wis_score ) . '" data-dex="' . esc_attr( $dex_score ) . '">';

    // Carcaj
    $slot_key = 'carcaj';
    $carcaj_display = $carcaj_type ? ( ucfirst( $carcaj_type ) . ' x ' . $carcaj_amount ) : '';
    $empty_class = empty( $carcaj_display ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="ammo">';
    echo '  <span class="slot-label">Carcaj / munición:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="carcaj_display" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $carcaj_display ) . '">';
    echo '  <input type="hidden" name="carcaj[type]" id="carcaj_type_hidden" value="' . esc_attr( $carcaj_type ) . '">';
    echo '  <input type="hidden" name="carcaj[amount]" id="carcaj_amount_hidden" value="' . esc_attr( $carcaj_amount ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';

    // Pociones (1-4)
    for ( $i = 1; $i <= 4; $i++ ) {
        $slot_key = 'potions_' . $i;
        $value    = $potion_values[ $i ] ?? '';
        $locked   = ( 4 === $i && ! $can_potion_slot_4 );
        $locked_attr = $locked ? ' data-locked="1"' : '';
        $empty_class = empty( $value ) ? ' empty' : '';
        echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="potion"' . $locked_attr . '>';
        echo '  <span class="slot-label">Poción ' . $i . ':</span>';
        echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
        echo '  <input type="hidden" name="potions_slot_' . $i . '" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $value ) . '">';
        echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '"' . ( $locked ? ' disabled' : '' ) . '>＋</button>';
        echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '"' . ( $locked ? ' disabled' : '' ) . '>−</button>';
        echo '</div>';
    }

    // Pergaminos / mapas / documentos (1-6)
    for ( $i = 1; $i <= 6; $i++ ) {
        $slot_key = 'scrolls_' . $i;
        $value    = $scroll_values[ $i ] ?? '';
        $locked   = ( $i > 4 && ! $can_scroll_extra );
        $locked_attr = $locked ? ' data-locked="1"' : '';
        $empty_class = empty( $value ) ? ' empty' : '';
        echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="scroll"' . $locked_attr . '>';
        echo '  <span class="slot-label">Pergamino ' . $i . ':</span>';
        echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
        echo '  <input type="hidden" name="scrolls_slot_' . $i . '" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $value ) . '">';
        echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '"' . ( $locked ? ' disabled' : '' ) . '>＋</button>';
        echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '"' . ( $locked ? ' disabled' : '' ) . '>−</button>';
        echo '</div>';
    }

    // Cuerda
    $slot_key = 'rope';
    $value    = $rope_value === 'rope' ? 'Cuerda' : '';
    $empty_class = empty( $value ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="rope">';
    echo '  <span class="slot-label">Cuerda:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="rope_slot" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $rope_value ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';

    // Slot extraño
    $slot_key = 'ankward';
    $empty_class = empty( $ankward_value ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="ankward">';
    echo '  <span class="slot-label">Slot extraño:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="ankward_slot" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $ankward_value ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';

    echo '</section>';
    echo '<hr>';
    }

    // Subclase (opcional)
    if ( $sub_features ) {
        $sub_name = $class_lookup['subclasses'][ $subclass_id ]['data']['name'] ?? $subclass_id;
        echo '<section class="character-extended__section">';
        echo '<h4 class="character-extended__section-title">Rasgos de subclase · ' . esc_html( $sub_name ) . '</h4>';
        foreach ( $sub_features as $feature ) {
            $name   = $feature['name'] ?? __( 'Rasgo', 'temahijo' );
            $level  = isset( $feature['level'] ) ? intval( $feature['level'] ) : null;
            $source = $feature['source'] ?? '';
            $meta   = [];
            if ( $level ) {
                $meta[] = sprintf( __( 'Nivel %d', 'temahijo' ), $level );
            }
            if ( $source ) {
                $meta[] = esc_html( $source );
            }
            $entries_raw = $feature['entries'] ?? [];
            $entries_out = drak_render_5e_entries_html( $entries_raw );

            echo '<article class="feature-card is-collapsed">';
            echo '  <header class="feature-card__header">';
            echo '    <h5 class="feature-card__title">' . esc_html( $name ) . '</h5>';
            echo '    <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Mostrar detalle', 'temahijo' ) . '">';
            echo '      <span class="feature-card__toggle-icon">▼</span>';
            echo '    </button>';
            echo '  </header>';
            echo '  <div class="feature-card__content">';
            if ( $meta ) {
                echo '    <div class="feature-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>';
            }
            echo '    <div class="feature-card__body">' . $entries_out . '</div>';
            echo '  </div>';
            echo '</article>';
        }
        echo '</section>';
    }
	
	// Slot especial: Oro
$fuerza_score_for_gold = intval( get_field( 'cs_fuerza', $post_id ) );
$gold_cap = 500;
if ( $fuerza_score_for_gold >= 18 ) {
    $gold_cap = 2000;
} elseif ( $fuerza_score_for_gold >= 16 ) {
    $gold_cap = 1500;
} elseif ( $fuerza_score_for_gold >= 12 ) {
    $gold_cap = 1000;
}
$gold = min( intval( get_field( 'golden_coins', $post_id ) ), $gold_cap );
echo '<h3 class="inventory-extra-header inventory-gold-cap">Capacidad máxima según FUE (' . intval( $fuerza_score_for_gold ) . '): ' . intval( $gold_cap ) . ' monedas</h3>';
echo '<div class="inventory-slot" data-slot="oro">';
echo '  <span class="slot-label">Oro:</span>';
echo '  <div class="slot-content" id="oro-display">';
echo      '<p class="slot-item" id="oro-valor">' . intval($gold) . ' monedas</p>';
echo '  </div>';
echo '  <input type="hidden" name="golden_coins" id="input_oro" value="' . intval($gold) . '">';
echo '  <input type="hidden" id="gold_cap" value="' . intval( $gold_cap ) . '">';
echo '  <button type="button" class="add-gold">＋</button>';
echo '  <button type="button" class="remove-gold">−</button>';
echo '</div>';

    for ($i = 1; $i <= 8; $i++) {
        $campo = 'mainpack_slot_' . $i;
        $valor = get_field($campo, $post_id);
        echo '<div class="inventory-slot" data-slot="' . $i . '">';
        echo '<span class="slot-label">Slot ' . $i . ':</span>';
		// Contenedor del contenido del slot (JS se encarga de pintar los <p>)
		echo '<div class="slot-content ' . (empty($valor) ? 'empty' : '') . '" id="texto_' . $i . '"></div>';
        echo '<input type="hidden" name="' . esc_attr($campo) . '" id="input_slot_' . $i . '" value="' . esc_attr($valor) . '">';
        echo '<button type="button" class="add-item" data-slot="' . $i . '" data-personaje="' . esc_attr($post_id) . '">＋</button>';
        echo '<button type="button" class="remove-item">−</button>';
        echo '</div>';
    }
// Verificar si INT o SAB >= 16 para mostrar los slots 9 y 10 extra
$inteligencia = (int) get_field('cs_inteligencia', $post_id);
$sabiduria = (int) get_field('cs_sabiduria', $post_id);

if ($inteligencia >= 16 || $sabiduria >= 16) {
$slot_boost_reason = '';
if ($inteligencia >= 16) {
    $slot_boost_reason = "Inteligencia (" . esc_html($inteligencia) . ")";
} elseif ($sabiduria >= 16) {
    $slot_boost_reason = "Sabiduría (" . esc_html($sabiduria) . ")";
}

if (!empty($slot_boost_reason)) {
    echo '<h3 class="inventory-extra-header">Slots extra por ' . $slot_boost_reason . '</h2>';
}	
    for ($i = 9; $i <= 10; $i++) {
        $slot = get_field("mainpack_slot_$i", $post_id);
        $slot_text = !empty($slot) ? esc_html($slot) : '(vacío)';
        $empty_class = empty($slot) ? ' empty' : '';
        ?>
        <div class="inventory-slot" data-slot="<?php echo $i; ?>">
            <span class="slot-label">Slot <?php echo $i; ?>:</span>
            <div class="slot-content<?php echo $empty_class; ?>" id="texto_<?php echo $i; ?>">
                <p class="slot-empty"><?php echo $slot_text; ?></p>
            </div>
            <input type="hidden" name="mainpack_slot_<?php echo $i; ?>" id="input_slot_<?php echo $i; ?>" value="<?php echo esc_attr($slot); ?>">
            <button type="button" class="add-item" data-slot="<?php echo $i; ?>" data-personaje="<?php echo esc_attr($post_id); ?>">+</button>
            <button type="button" class="remove-item" data-slot="<?php echo $i; ?>">−</button>
        </div>

        <?php
    }
	echo '<hr>';
}


	$arma_principal = drak_get_weapon_payload( $post_id, 'arma_principal' );
	$arma_secundaria = drak_get_weapon_payload( $post_id, 'arma_secundaria' );
	$armadura = drak_get_armor_payload( $post_id );

	echo '<div class="inventory-slot arma-principal-slot arma-slot">';
	echo '  <span class="slot-label">Arma Principal:</span>';
	echo '  <div id="arma-principal-display" class="slot-content">';
if (!empty($arma_principal) && !empty($arma_principal['name'])) {
    $meta = array_filter([
        $arma_principal['damage_dice'] ?? '',
        $arma_principal['damage_type'] ?? '',
    ]);
    echo '<p><strong>' . esc_html($arma_principal['name']) . '</strong>' . ($meta ? ' (' . esc_html(implode(' ', $meta)) . ')' : '') . '</p>';
} else {
    echo '<p>No hay arma asignada</p>';
}
	echo '  </div>';
	echo ' <button type="button" id="arma-principal-add" class="arma-btn-add" data-arma-slot="principal">+</button>';
	echo ' <button type="button"  id="arma-principal-remove" class="arma-principal-remove" data-arma-slot="principal">−</button>';
	echo '</div>';

	echo '<div class="inventory-slot arma-secundaria-slot arma-slot">';
	echo '  <span class="slot-label">Arma Secundaria:</span>';
echo '  <div id="arma-secundaria-display" class="slot-content">';
if (!empty($arma_secundaria) && !empty($arma_secundaria['name'])) {
    $meta = array_filter([
        $arma_secundaria['damage_dice'] ?? '',
        $arma_secundaria['damage_type'] ?? '',
    ]);
    echo '<p><strong>' . esc_html($arma_secundaria['name']) . '</strong>' . ($meta ? ' (' . esc_html(implode(' ', $meta)) . ')' : '') . '</p>';
} else {
    echo '<p>No hay arma secundaria asignada</p>';
}
	echo '  </div>';
	echo ' <button type="button" id="arma-secundaria-add" class="arma-btn-add" data-arma-slot="secundaria">+</button>';
	echo ' <button type="button"  id="arma-secundaria-remove" class="arma-secundaria-remove" data-arma-slot="secundaria">−</button>';
	echo '</div>';

	echo '<div class="inventory-slot armadura-slot">';
	echo '  <span class="slot-label">Armadura:</span>';
	echo '  <div id="armadura-display" class="slot-content">';
if (!empty($armadura) && !empty($armadura['name'])) {
    $meta = array_filter([
        $armadura['ac'] ? 'CA: ' . $armadura['ac'] : '',
        !empty($armadura['type']) ? 'Tipo: ' . $armadura['type'] : '',
        isset($armadura['stealth_disadvantage']) ? ('Sigilo: ' . ($armadura['stealth_disadvantage'] ? 'Desventaja' : '—')) : '',
    ]);
    echo '<p><strong>' . esc_html($armadura['name']) . '</strong></p>';
    if ($meta) {
        echo '<p class="armor-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
} else {
    echo '<p>No hay armadura equipada</p>';
}
	echo '  </div>';
	echo ' <button type="button" id="armadura-add" class="armadura-btn-add">+</button>';
	echo ' <button type="button"  id="armadura-remove" class="armadura-remove">−</button>';
	echo '</div>';

	// Campos ocultos: arma principal
	echo '<input type="hidden" name="arma_principal[name]" id="arma_name" value="' . esc_attr($arma_principal['name'] ?? '') . '">';
echo '<input type="hidden" name="arma_principal[slug]" id="arma_slug" value="' . esc_attr($arma_principal['slug'] ?? '') . '">';
echo '<input type="hidden" name="arma_principal[category]" id="arma_category" value="' . esc_attr($arma_principal['category'] ?? '') . '">';
echo '<input type="hidden" name="arma_principal[damage_dice]" id="arma_damage_dice" value="' . esc_attr($arma_principal['damage_dice'] ?? '') . '">';
echo '<input type="hidden" name="arma_principal[damage_type]" id="arma_damage_type" value="' . esc_attr($arma_principal['damage_type'] ?? '') . '">';
echo '<input type="hidden" name="arma_principal[weight]" id="arma_weight" value="' . esc_attr($arma_principal['weight'] ?? '') . '">';
$arma_props = isset($arma_principal['properties']) ? (is_array($arma_principal['properties']) ? implode(', ', $arma_principal['properties']) : $arma_principal['properties']) : '';
echo '<input type="hidden" name="arma_principal[properties]" id="arma_properties" value="' . esc_attr($arma_props) . '">';
echo '<input type="hidden" name="arma_principal[es_magica]" id="arma_es_magica" value="' . (!empty($arma_principal['is_magical']) ? '1' : '') . '">';
echo '<input type="hidden" name="arma_principal[requiere_attunement]" id="arma_requiere_attunement" value="' . (!empty($arma_principal['requires_attunement']) ? '1' : '') . '">';
echo '<input type="hidden" name="arma_principal[descripcion]" id="arma_descripcion" value="' . esc_attr($arma_principal['description'] ?? '') . '">';

	// Campos ocultos: arma secundaria
	echo '<input type="hidden" name="arma_secundaria[name]" id="arma2_name" value="' . esc_attr($arma_secundaria['name'] ?? '') . '">';
echo '<input type="hidden" name="arma_secundaria[slug]" id="arma2_slug" value="' . esc_attr($arma_secundaria['slug'] ?? '') . '">';
echo '<input type="hidden" name="arma_secundaria[category]" id="arma2_category" value="' . esc_attr($arma_secundaria['category'] ?? '') . '">';
echo '<input type="hidden" name="arma_secundaria[damage_dice]" id="arma2_damage_dice" value="' . esc_attr($arma_secundaria['damage_dice'] ?? '') . '">';
echo '<input type="hidden" name="arma_secundaria[damage_type]" id="arma2_damage_type" value="' . esc_attr($arma_secundaria['damage_type'] ?? '') . '">';
echo '<input type="hidden" name="arma_secundaria[weight]" id="arma2_weight" value="' . esc_attr($arma_secundaria['weight'] ?? '') . '">';
$arma2_props = isset($arma_secundaria['properties']) ? (is_array($arma_secundaria['properties']) ? implode(', ', $arma_secundaria['properties']) : $arma_secundaria['properties']) : '';
echo '<input type="hidden" name="arma_secundaria[properties]" id="arma2_properties" value="' . esc_attr($arma2_props) . '">';
echo '<input type="hidden" name="arma_secundaria[es_magica]" id="arma2_es_magica" value="' . (!empty($arma_secundaria['is_magical']) ? '1' : '') . '">';
echo '<input type="hidden" name="arma_secundaria[requiere_attunement]" id="arma2_requiere_attunement" value="' . (!empty($arma_secundaria['requires_attunement']) ? '1' : '') . '">';
echo '<input type="hidden" name="arma_secundaria[descripcion]" id="arma2_descripcion" value="' . esc_attr($arma_secundaria['description'] ?? '') . '">';

	// Campos ocultos: armadura
	echo '<input type="hidden" name="armadura[name]" id="armadura_name" value="' . esc_attr($armadura['name'] ?? '') . '">';
echo '<input type="hidden" name="armadura[slug]" id="armadura_slug" value="' . esc_attr($armadura['slug'] ?? '') . '">';
echo '<input type="hidden" name="armadura[type]" id="armadura_type" value="' . esc_attr($armadura['type'] ?? '') . '">';
echo '<input type="hidden" name="armadura[ac]" id="armadura_ac" value="' . esc_attr($armadura['ac'] ?? '') . '">';
echo '<input type="hidden" name="armadura[strength]" id="armadura_strength" value="' . esc_attr($armadura['strength'] ?? '') . '">';
echo '<input type="hidden" name="armadura[stealth_disadvantage]" id="armadura_stealth_disadvantage" value="' . (!empty($armadura['stealth_disadvantage']) ? '1' : '') . '">';
echo '<input type="hidden" name="armadura[weight]" id="armadura_weight" value="' . esc_attr($armadura['weight'] ?? '') . '">';
echo '<input type="hidden" name="armadura[value]" id="armadura_value" value="' . esc_attr($armadura['value'] ?? '') . '">';
echo '<input type="hidden" name="armadura[descripcion]" id="armadura_descripcion" value="' . esc_attr($armadura['description'] ?? '') . '">';

    // Slots rápidos (orden: munición, pociones, pergaminos, cuerda, slot extraño)
    echo '<section class="inventory-special inventory-special--slots" data-inventory-special data-int="' . esc_attr( $int_score ) . '" data-wis="' . esc_attr( $wis_score ) . '" data-dex="' . esc_attr( $dex_score ) . '">';

    // Carcaj
    $slot_key = 'carcaj';
    $carcaj_display = $carcaj_type ? ( ucfirst( $carcaj_type ) . ' x ' . $carcaj_amount ) : '';
    $empty_class = empty( $carcaj_display ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="ammo">';
    echo '  <span class="slot-label">Carcaj / munición:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="carcaj_display" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $carcaj_display ) . '">';
    echo '  <input type="hidden" name="carcaj[type]" id="carcaj_type_hidden" value="' . esc_attr( $carcaj_type ) . '">';
    echo '  <input type="hidden" name="carcaj[amount]" id="carcaj_amount_hidden" value="' . esc_attr( $carcaj_amount ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';
    echo '<hr>';

    // Pociones
    for ( $i = 1; $i <= 4; $i++ ) {
        $slot_key = 'potions_' . $i;
        $value    = $potion_values[ $i ] ?? '';
        $locked   = ( 4 === $i && ! $can_potion_slot_4 );
        $locked_attr = $locked ? ' data-locked="1"' : '';
        $empty_class = empty( $value ) ? ' empty' : '';
        if ( 4 === $i && ! $locked ) {
            $reason_attr = [ 'INT' => $int_score, 'WIS' => $wis_score, 'DEX' => $dex_score ];
            arsort( $reason_attr );
            $top = key( $reason_attr );
            $top_val = reset( $reason_attr );
            echo '<h3 class="inventory-extra-header">Slot extra de pociones por ' . esc_html( $top ) . ' (' . intval( $top_val ) . ')</h3>';
        }
        echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="potion"' . $locked_attr . '>';
        echo '  <span class="slot-label">Poción ' . $i . ':</span>';
        echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
        echo '  <input type="hidden" name="potions_slot_' . $i . '" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $value ) . '">';
        echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '"' . ( $locked ? ' disabled' : '' ) . '>＋</button>';
        echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '"' . ( $locked ? ' disabled' : '' ) . '>−</button>';
        echo '</div>';
    }

    echo '<hr>';

    // Pergaminos / mapas / documentos (1-6)
    for ( $i = 1; $i <= 6; $i++ ) {
        $slot_key = 'scrolls_' . $i;
        $value    = $scroll_values[ $i ] ?? '';
        $locked   = ( $i > 4 && ! $can_scroll_extra );
        $locked_attr = $locked ? ' data-locked="1"' : '';
        $empty_class = empty( $value ) ? ' empty' : '';
        if ( 5 === $i && ! $locked ) {
            $reason_attr = [ 'INT' => $int_score, 'WIS' => $wis_score ];
            arsort( $reason_attr );
            $top = key( $reason_attr );
            $top_val = reset( $reason_attr );
            echo '<h3 class="inventory-extra-header">Slots extra de pergaminos por ' . esc_html( $top ) . ' (' . intval( $top_val ) . ')</h3>';
        }
        echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="scroll"' . $locked_attr . '>';
        echo '  <span class="slot-label">Pergamino ' . $i . ':</span>';
        echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
        echo '  <input type="hidden" name="scrolls_slot_' . $i . '" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $value ) . '">';
        echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '"' . ( $locked ? ' disabled' : '' ) . '>＋</button>';
        echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '"' . ( $locked ? ' disabled' : '' ) . '>−</button>';
        echo '</div>';
    }

    echo '<hr>';

    // Cuerda
    $slot_key = 'rope';
    $value    = $rope_value === 'rope' ? 'Cuerda' : '';
    $empty_class = empty( $value ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="rope">';
    echo '  <span class="slot-label">Cuerda:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="rope_slot" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $rope_value ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';

    echo '<hr>';

    // Slot extraño
    $slot_key = 'ankward';
    $empty_class = empty( $ankward_value ) ? ' empty' : '';
    echo '<div class="inventory-slot" data-slot="' . esc_attr( $slot_key ) . '" data-slot-type="ankward">';
    echo '  <span class="slot-label">Slot extraño:</span>';
    echo '  <div class="slot-content' . $empty_class . '" id="texto_' . esc_attr( $slot_key ) . '"></div>';
    echo '  <input type="hidden" name="ankward_slot" id="input_slot_' . esc_attr( $slot_key ) . '" value="' . esc_attr( $ankward_value ) . '">';
    echo '  <button type="button" class="add-item" data-slot="' . esc_attr( $slot_key ) . '" data-personaje="' . esc_attr( $post_id ) . '">＋</button>';
    echo '  <button type="button" class="remove-item" data-slot="' . esc_attr( $slot_key ) . '">−</button>';
    echo '</div>';

    echo '</section>';
    echo '<hr>';

    echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
    echo '<input type="hidden" id="inventory_nonce" name="inventory_nonce" value="' . esc_attr( wp_create_nonce( 'save_inventory_' . $post_id ) ) . '">';
    echo '</form>';
    echo '</div>';

// MODAL DE ARMAS (principal/secundaria)
echo '<div id="armaModal" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-arma-popup">&times;</span>';
echo '    <h3>Seleccionar arma <span id="arma-modal-context-label">(principal)</span></h3>';
echo '    <p class="arma-modal-hint">Este mismo selector sirve para el arma secundaria.</p>';

echo '    <select id="arma-selector">';
echo '      <option value=\"\">Cargando armas...</option>';
echo '    </select>';

echo '    <div id="arma-preview" style="display:none;">';
echo '      <h4>Resumen:</h4>';
echo '      <div class="fila-dano">';
echo '        <p><strong>Daño:</strong> <span id="arma-damage"></span></p>';
echo '        <p><strong>Tipo de daño:</strong> <span id="arma-damage-type"></span></p>';
echo '      </div>';
echo '      <p><strong>Peso:</strong> <span id="arma-weight"></span></p>';
echo '      <p><strong>Propiedades:</strong></p>';
echo '      <ul id="propiedades-arma-lista"></ul>';
echo '      <div class="checkbox-group">';
echo '      <label><input type="checkbox" id="arma-magica"> ¿Es mágica?</label><br>';
echo '      <label><input type="checkbox" id="arma-attune"> ¿Requiere attunement?</label>';
echo '    </div>';
echo '    </div>';

echo '    <button id="arma-aplicar">Usar esta arma</button>';

echo '  </div>';
echo '</div>';

	
echo '<div id="armaModalEliminar" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <p id="arma-delete-copy">¿Estás seguro de que deseas eliminar esta arma?</p>';
echo '    <button id="confirmarEliminarArma" class="btn-danger">Eliminar</button>';
echo '    <button class="close-modal">Cancelar</button>';
echo '  </div>';
echo '</div>';

// MODAL DE ARMADURA
echo '<div id="armaduraModal" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-armadura-popup close-popup">&times;</span>';
echo '    <h3>Seleccionar armadura</h3>';
echo '    <select id="armadura-selector">';
echo '      <option value=\"\">Cargando armaduras...</option>';
echo '    </select>';
echo '    <div id="armadura-preview" style="display:none;">';
echo '      <h4>Resumen:</h4>';
echo '      <div class="fila-dano">';
echo '        <p><strong>CA base:</strong> <span id="armadura-ac"></span></p>';
echo '        <p><strong>Tipo:</strong> <span id="armadura-tipo"></span></p>';
echo '      </div>';
echo '      <p><strong>Sigilo:</strong> <span id="armadura-sigilo"></span></p>';
echo '      <p><strong>Requisito de FUE:</strong> <span id="armadura-fuerza"></span></p>';
echo '      <p><strong>Peso:</strong> <span id="armadura-peso"></span></p>';
echo '      <p id="armadura-descripcion"></p>';
echo '    </div>';
echo '    <button id="armadura-aplicar">Usar esta armadura</button>';
echo '  </div>';
echo '</div>';

echo '<div id="armaduraModalEliminar" class="modal-overlay" style="display: none;">';
echo '  <div class="modal-contenido">';
echo '    <p>¿Eliminar la armadura equipada?</p>';
echo '    <button id="confirmarEliminarArmadura" class="btn-danger">Eliminar</button>';
echo '    <button class="close-modal close-armadura-popup">Cancelar</button>';
echo '  </div>';
echo '</div>';

//MODAL: Añadir Oro
echo '<div id="modal-add-gold" class="modal-overlay" style="display:none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-popup">&times;</span>';
echo '    <h2>Añadir oro</h2>';
echo '    <form id="form-add-gold">';
echo '      <label for="gold-amount-add">Cantidad a añadir:</label>';
echo '      <input type="number" id="gold-amount-add" min="1" max="99999">';
echo '      <button type="submit">Añadir</button>';
echo '    </form>';
echo '  </div>';
echo '</div>';
//MODAL: Eliminar Oro
echo '<div id="modal-remove-gold" class="modal-overlay" style="display:none;">';
echo '  <div class="modal-contenido">';
echo '    <span class="close-popup">&times;</span>';
echo '    <h2>Eliminar oro</h2>';
echo '    <form id="form-remove-gold">';
echo '      <label for="gold-amount-remove">Cantidad a eliminar:</label>';
echo '      <input type="number" id="gold-amount-remove" min="1" max="99999">';
echo '      <button type="submit">Eliminar</button>';
echo '    </form>';
echo '  </div>';
echo '</div>';



    return ob_get_clean();
}
// RENDERIZAR HOJA DE PERSONAJE____________________________________________________________________

function renderizar_hoja_personaje($post_id) {
    if (!$post_id) return '';

    // Guardado del formulario
    if (isset($_POST['hoja_guardar']) && intval($_POST['post_id']) === $post_id) {
        $campos = [
            // Características + mods
            'cs_fuerza', 'cs_fuerza_mod',
            'cs_destreza', 'cs_destreza_mod',
            'cs_constitucion', 'cs_constitucion_mod',
            'cs_inteligencia', 'cs_inteligencia_mod',
            'cs_sabiduria', 'cs_sabiduria_mod',
            'cs_carisma', 'cs_carisma_mod',
            'cs_proeficiencia',
			
			'nivel',
            'clase',
            'subclase',
			'raza',
			
			'cs_iniciativa',
        	'cs_ac',
        	'cs_velocidad',
        	'cs_hp',
			'cs_hp_temp',


            // Habilidades
            'cs_skill_acrobacias',
            'cs_skill_atletismo',
            'cs_skill_juego_manos',
            'cs_skill_sigilo',
            'cs_skill_arcanos',
            'cs_skill_historia',
            'cs_skill_investigacion',
            'cs_skill_naturaleza',
            'cs_skill_religion',
            'cs_skill_trato_animales',
            'cs_skill_perspicacia',
            'cs_skill_medicina',
            'cs_skill_percepcion',
            'cs_skill_supervivencia',
            'cs_skill_engano',
            'cs_skill_intimidacion',
            'cs_skill_interpretacion',
            'cs_skill_persuasion',

            // Competencias en habilidades
            'cs_prof_acrobacias',
            'cs_prof_atletismo',
            'cs_prof_juego_manos',
            'cs_prof_sigilo',
            'cs_prof_arcanos',
            'cs_prof_historia',
            'cs_prof_investigacion',
            'cs_prof_naturaleza',
            'cs_prof_religion',
            'cs_prof_trato_animales',
            'cs_prof_perspicacia',
            'cs_prof_medicina',
            'cs_prof_percepcion',
            'cs_prof_supervivencia',
            'cs_prof_engano',
            'cs_prof_intimidacion',
            'cs_prof_interpretacion',
            'cs_prof_persuasion',
			
			        // Tiradas de salvación
        'cs_save_fuerza',
        'cs_save_destreza',
        'cs_save_constitucion',
        'cs_save_inteligencia',
        'cs_save_sabiduria',
        'cs_save_carisma',

        'cs_prof_save_fuerza',
        'cs_prof_save_destreza',
        'cs_prof_save_constitucion',
        'cs_prof_save_inteligencia',
        'cs_prof_save_sabiduria',
        'cs_prof_save_carisma',
		'prof_weapons',
		'prof_armors',
		'prof_languages',
		'prof_tools',
        'background',
        'spellcasting_hability',
        'spell_save_dc',
        'spell_attack_bonus',
        ];

        foreach ($campos as $campo) {
            if (isset($_POST[$campo])) {
                update_field($campo, drak_get_post_value($campo, ''), $post_id);
            } else {
                // Para las competencias (checkbox tipo circulito) que no envían valor si se desmarcan
                if (strpos($campo, 'cs_prof_') === 0) {
                    update_field($campo, '0', $post_id);
                }
            }
        }

        if (isset($_POST['skills_expertise'])) {
            update_post_meta(
                $post_id,
                'skills_expertise',
                sanitize_text_field(wp_unslash($_POST['skills_expertise']))
            );
        }
        if ( isset( $_POST['manual_skill_overrides'] ) ) {
            update_post_meta(
                $post_id,
                'manual_skill_overrides',
                sanitize_text_field( wp_unslash( $_POST['manual_skill_overrides'] ) )
            );
        }

        $class_from_post = drak_get_post_value( 'clase', '' );
        $level_from_post = intval( drak_get_post_value( 'nivel', 0 ) );
        $raw_theories    = drak_get_post_value( 'apothecary_theories', '' );
        $selected_theories = drak_sanitize_apothecary_theory_submission( $raw_theories, $class_from_post, $level_from_post );
        drak_save_apothecary_theory_selection( $post_id, $selected_theories );

        $hp_manual_flag = drak_get_post_value( 'cs_hp_manual_override', '' ) === '1' ? '1' : '';
        update_post_meta( $post_id, 'cs_hp_manual_override', $hp_manual_flag );
        $ini_manual_flag = drak_get_post_value( 'cs_iniciativa_manual_override', '' ) === '1' ? '1' : '';
        $ac_manual_flag  = drak_get_post_value( 'cs_ac_manual_override', '' ) === '1' ? '1' : '';
        $vel_manual_flag = drak_get_post_value( 'cs_velocidad_manual_override', '' ) === '1' ? '1' : '';
        update_post_meta( $post_id, 'cs_iniciativa_manual_override', $ini_manual_flag );
        update_post_meta( $post_id, 'cs_ac_manual_override', $ac_manual_flag );
        update_post_meta( $post_id, 'cs_velocidad_manual_override', $vel_manual_flag );

        drak_update_spellcasting_fields($post_id);

        $combat_attack_extra = intval( drak_get_post_value( 'combat_attack_extra', 0 ) );
        $combat_damage_extra = intval( drak_get_post_value( 'combat_damage_extra', 0 ) );
        $combat_notes        = drak_get_post_value( 'combat_notes', '' );
        update_post_meta( $post_id, 'combat_attack_extra', $combat_attack_extra );
        update_post_meta( $post_id, 'combat_damage_extra', $combat_damage_extra );
        update_post_meta( $post_id, 'combat_notes', sanitize_text_field( $combat_notes ) );

        echo '<div class="mensaje-confirmacion">✅ Hoja de personaje actualizada.</div>';
    }

    // Cargar valores actuales
    $keys = [
        'cs_fuerza',
        'cs_fuerza_mod',
        'cs_destreza',
        'cs_destreza_mod',
        'cs_constitucion',
        'cs_constitucion_mod',
        'cs_inteligencia',
        'cs_inteligencia_mod',
        'cs_sabiduria',
        'cs_sabiduria_mod',
        'cs_carisma',
        'cs_carisma_mod',
        'cs_proeficiencia',
		
			'nivel',
            'clase',
            'subclase',
			'raza',
            'background',
		
		'cs_iniciativa',
    	'cs_ac',
    	'cs_velocidad',
    	'cs_hp',
		'cs_hp_temp',

        'cs_skill_acrobacias',
        'cs_skill_atletismo',
        'cs_skill_juego_manos',
        'cs_skill_sigilo',
        'cs_skill_arcanos',
        'cs_skill_historia',
        'cs_skill_investigacion',
        'cs_skill_naturaleza',
        'cs_skill_religion',
        'cs_skill_trato_animales',
        'cs_skill_perspicacia',
        'cs_skill_medicina',
        'cs_skill_percepcion',
        'cs_skill_supervivencia',
        'cs_skill_engano',
        'cs_skill_intimidacion',
        'cs_skill_interpretacion',
        'cs_skill_persuasion',

        'cs_prof_acrobacias',
        'cs_prof_atletismo',
        'cs_prof_juego_manos',
        'cs_prof_sigilo',
        'cs_prof_arcanos',
        'cs_prof_historia',
        'cs_prof_investigacion',
        'cs_prof_naturaleza',
        'cs_prof_religion',
        'cs_prof_trato_animales',
        'cs_prof_perspicacia',
        'cs_prof_medicina',
        'cs_prof_percepcion',
        'cs_prof_supervivencia',
        'cs_prof_engano',
        'cs_prof_intimidacion',
        'cs_prof_interpretacion',
        'cs_prof_persuasion',
		
		'cs_save_fuerza',
        'cs_save_destreza',
        'cs_save_constitucion',
        'cs_save_inteligencia',
        'cs_save_sabiduria',
        'cs_save_carisma',

        'cs_prof_save_fuerza',
        'cs_prof_save_destreza',
        'cs_prof_save_constitucion',
        'cs_prof_save_inteligencia',
        'cs_prof_save_sabiduria',
        'cs_prof_save_carisma',
		
		      // NUEVO: competencias generales
        'prof_weapons',
        'prof_armors',
        'prof_tools',
        'prof_languages',
        'spellcasting_hability',
        'spell_save_dc',
        'spell_attack_bonus'

    ];

    $datos = [];
    foreach ($keys as $k) {
        $datos[$k] = get_field($k, $post_id);
    }
    $datos['skills_expertise'] = get_post_meta($post_id, 'skills_expertise', true);

    $apothecary_selection = drak_get_character_apothecary_theories( $post_id );
    $apothecary_selection_json = wp_json_encode( $apothecary_selection );
    if ( ! $apothecary_selection_json ) {
        $apothecary_selection_json = '[]';
    }
    $apothecary_selection_display = drak_format_apothecary_theories_display( $apothecary_selection );

    // Definimos grupos de habilidades por característica, ordenados por nº de habilidades (más a menos)
    $skill_groups = [
        'Sabiduría' => [
            ['label' => 'Trato con Animales', 'skill' => 'cs_skill_trato_animales', 'prof' => 'cs_prof_trato_animales'],
            ['label' => 'Perspicacia',        'skill' => 'cs_skill_perspicacia',    'prof' => 'cs_prof_perspicacia'],
            ['label' => 'Medicina',           'skill' => 'cs_skill_medicina',       'prof' => 'cs_prof_medicina'],
            ['label' => 'Percepción',         'skill' => 'cs_skill_percepcion',     'prof' => 'cs_prof_percepcion'],
            ['label' => 'Supervivencia',      'skill' => 'cs_skill_supervivencia',  'prof' => 'cs_prof_supervivencia'],
        ],
        'Inteligencia' => [
            ['label' => 'Arcanos',        'skill' => 'cs_skill_arcanos',       'prof' => 'cs_prof_arcanos'],
            ['label' => 'Historia',       'skill' => 'cs_skill_historia',      'prof' => 'cs_prof_historia'],
            ['label' => 'Investigación',  'skill' => 'cs_skill_investigacion', 'prof' => 'cs_prof_investigacion'],
            ['label' => 'Naturaleza',     'skill' => 'cs_skill_naturaleza',    'prof' => 'cs_prof_naturaleza'],
            ['label' => 'Religión',       'skill' => 'cs_skill_religion',      'prof' => 'cs_prof_religion'],
        ],
        'Carisma' => [
            ['label' => 'Engaño',         'skill' => 'cs_skill_engano',         'prof' => 'cs_prof_engano'],
            ['label' => 'Intimidación',   'skill' => 'cs_skill_intimidacion',   'prof' => 'cs_prof_intimidacion'],
            ['label' => 'Interpretación', 'skill' => 'cs_skill_interpretacion', 'prof' => 'cs_prof_interpretacion'],
            ['label' => 'Persuasión',     'skill' => 'cs_skill_persuasion',     'prof' => 'cs_prof_persuasion'],
        ],
        'Destreza' => [
            ['label' => 'Acrobacias',     'skill' => 'cs_skill_acrobacias',    'prof' => 'cs_prof_acrobacias'],
            ['label' => 'Juego de Manos', 'skill' => 'cs_skill_juego_manos',   'prof' => 'cs_prof_juego_manos'],
            ['label' => 'Sigilo',         'skill' => 'cs_skill_sigilo',        'prof' => 'cs_prof_sigilo'],
        ],
        'Fuerza' => [
            ['label' => 'Atletismo',      'skill' => 'cs_skill_atletismo',     'prof' => 'cs_prof_atletismo'],
        ],
    ];

    ob_start();
    ?>
    <div class="hoja-personaje-container">
      <form method="post" class="formulario-hoja-personaje">
        <input type="hidden" id="hoja_guardar" name="hoja_guardar" value="">
        <input type="hidden" id="post_id" name="post_id" value="<?php echo esc_attr($post_id); ?>">
        <input type="hidden" id="apothecary_theories" name="apothecary_theories" value="<?php echo esc_attr( $apothecary_selection_json ); ?>">
		  
		      <?php
	$nivel     = isset($datos['nivel']) ? intval($datos['nivel']) : '';
    $clase_val = isset($datos['clase']) ? $datos['clase'] : '';
    $sub_val   = isset($datos['subclase']) ? $datos['subclase'] : '';
	$raza_val  = isset($datos['raza'])  ? $datos['raza']  : '';
    $background_val = isset($datos['background']) ? $datos['background'] : '';

    // Valores actuales de los 4 básicos
    $init  = isset($datos['cs_iniciativa']) ? $datos['cs_iniciativa'] : '';
    $ac    = isset($datos['cs_ac']) ? $datos['cs_ac'] : '';
    $speed = isset($datos['cs_velocidad']) ? $datos['cs_velocidad'] : '';
	$hp = isset($datos['cs_hp']) ? $datos['cs_hp'] : '';
	$hp_temp = isset($datos['cs_hp_temp']) ? $datos['cs_hp_temp'] : '';
    $hp_manual_override = get_post_meta( $post_id, 'cs_hp_manual_override', true ) ? '1' : '';
    $ini_manual_override = get_post_meta( $post_id, 'cs_iniciativa_manual_override', true ) ? '1' : '';
    $ac_manual_override  = get_post_meta( $post_id, 'cs_ac_manual_override', true ) ? '1' : '';
    $vel_manual_override = get_post_meta( $post_id, 'cs_velocidad_manual_override', true ) ? '1' : '';
    $manual_skill_overrides = sanitize_text_field( get_post_meta( $post_id, 'manual_skill_overrides', true ) );
	
	$armas_val        = isset($datos['prof_weapons'])        ? $datos['prof_weapons']        : '';
	$armaduras_val    = isset($datos['prof_armors'])    ? $datos['prof_armors']    : '';
	$herramientas_val = isset($datos['prof_tools']) ? $datos['prof_tools'] : '';
	$idiomas_val      = isset($datos['prof_languages'])      ? $datos['prof_languages']      : '';
    $weapon_main      = drak_get_weapon_main_payload( $post_id );
    $combat_attack_extra = intval( get_post_meta( $post_id, 'combat_attack_extra', true ) );
    $combat_damage_extra = intval( get_post_meta( $post_id, 'combat_damage_extra', true ) );
    $combat_notes        = sanitize_text_field( get_post_meta( $post_id, 'combat_notes', true ) );
    $combat_ammo_state   = drak_get_combat_ammo_state( $post_id );
    $carcaj_raw          = drak_normalize_carcaj_value( get_field( 'carcaj', $post_id ) );
    $carcaj_payload      = [];
    if ( is_array( $carcaj_raw ) ) {
        $carcaj_payload = [
            'type'   => sanitize_key( $carcaj_raw['type'] ?? '' ),
            'amount' => max( 0, intval( $carcaj_raw['amount'] ?? 0 ) ),
        ];
    }
    if ( ! empty( $carcaj_payload['type'] ) ) {
        $combat_ammo_state[ 'quiver-' . $carcaj_payload['type'] ] = $carcaj_payload['amount'];
    }


    ?>
    <!-- BLOQUE PROGRESION: NIVEL / CLASE / SUBCLASE -->
    <div class="basicos-extra">
      <!-- Nivel, en su propia línea -->
      <div class="basic-item basic-item-level">
        <span class="basic-label">Nivel</span>
        <p class="basic-circle basic-circle-small" id="display_nivel">
          <?php echo $nivel !== '' ? esc_html($nivel) : ''; ?>
        </p>
      </div>

      <!-- Fila con Raza / Clase / Subclase / Trasfondo -->
  <div class="basicos-secundarios">
    <div class="basic-item basic-item-wide">
      <span class="basic-label">Raza</span>
      <p class="basic-text" id="display_raza"></p>
    </div>
    <div class="basic-item basic-item-wide">
      <span class="basic-label">Clase</span>
      <p class="basic-text" id="display_clase"></p>
    </div>

    <div class="basic-item basic-item-wide">
      <span class="basic-label">Subclase</span>
      <p class="basic-text" id="display_subclase"></p>
    </div>

    <div class="basic-item basic-item-wide">
      <span class="basic-label">Trasfondo</span>
      <p class="basic-text" id="display_background"></p>
    </div>
  </div>

    <?php $is_mutagenist = ( $sub_val === 'apothecary-mutagenist-scgtd-drakkenheim' ); ?>
  <div class="basic-item basic-item-wide basic-item-theories<?php echo $is_mutagenist ? '' : ' is-hidden'; ?>">
    <span class="basic-label">Teorías esotéricas</span>
    <p class="basic-text" id="display_apothecary_theories">
      <?php echo $apothecary_selection_display ? esc_html( $apothecary_selection_display ) : '—'; ?>
    </p>
  </div>
</div>

    <!-- BLOQUE: Iniciativa / CA / Vel / PV -->
    <div class="basicos-container">
      <div class="basicos-list">
        <div class="basic-item">
          <span class="basic-label">INI</span>
          <p class="basic-circle basic-circle--editable" id="display_cs_iniciativa" contenteditable="true" spellcheck="false" data-basic-edit="cs_iniciativa"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">CA</span>
          <p class="basic-circle basic-circle--editable" id="display_cs_ac" contenteditable="true" spellcheck="false" data-basic-edit="cs_ac"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">VEL</span>
          <p class="basic-circle basic-circle--editable" id="display_cs_velocidad" contenteditable="true" spellcheck="false" data-basic-edit="cs_velocidad"></p>
        </div>
        <div class="basic-item">
          <span class="basic-label">PV</span>
          <p class="basic-circle basic-circle--editable" id="display_cs_hp" contenteditable="true" spellcheck="false" data-basic-edit="cs_hp"></p>
        </div>
      </div>
    </div>

    <!-- Hidden que se guardan en ACF -->
    <input type="hidden" id="cs_iniciativa" name="cs_iniciativa" value="<?php echo esc_attr($init); ?>">
    <input type="hidden" id="cs_ac"         name="cs_ac"         value="<?php echo esc_attr($ac); ?>">
    <input type="hidden" id="cs_velocidad"  name="cs_velocidad"  value="<?php echo esc_attr($speed); ?>">
    <input type="hidden" id="cs_hp"         name="cs_hp"         value="<?php echo esc_attr($hp); ?>">
    <!-- Nivel / Clase / Subclase -->
    <input type="hidden" id="nivel"    name="nivel"    value="<?php echo esc_attr($nivel); ?>">
    <input type="hidden" id="clase"    name="clase"    value="<?php echo esc_attr($clase_val); ?>">
    <input type="hidden" id="subclase" name="subclase" value="<?php echo esc_attr($sub_val); ?>">
	<input type="hidden" id="raza"     name="raza"     value="<?php echo esc_attr($raza_val); ?>">
<input type="hidden" id="background" name="background" value="<?php echo esc_attr($background_val); ?>">
<input type="hidden" id="prof_weapons" name="prof_weapons" value="<?php echo esc_attr($armas_val); ?>">
<input type="hidden" id="prof_armors" name="prof_armors" value="<?php echo esc_attr($armaduras_val); ?>">
<input type="hidden" id="prof_tools" name="prof_tools" value="<?php echo esc_attr($herramientas_val); ?>">
<input type="hidden" id="prof_languages" name="prof_languages" value="<?php echo esc_attr($idiomas_val); ?>">
<input type="hidden" id="spellcasting_hability" name="spellcasting_hability" value="<?php echo esc_attr(isset($datos['spellcasting_hability']) ? $datos['spellcasting_hability'] : ''); ?>">
<input type="hidden" id="spell_save_dc" name="spell_save_dc" value="<?php echo esc_attr(isset($datos['spell_save_dc']) ? $datos['spell_save_dc'] : ''); ?>">
<input type="hidden" id="spell_attack_bonus" name="spell_attack_bonus" value="<?php echo esc_attr(isset($datos['spell_attack_bonus']) ? $datos['spell_attack_bonus'] : ''); ?>">



<input type="hidden" id="cs_hp_temp" name="cs_hp_temp" value="<?php echo esc_attr($hp_temp); ?>">
<input type="hidden" id="cs_hp_manual_override" name="cs_hp_manual_override" value="<?php echo esc_attr( $hp_manual_override ); ?>">
<input type="hidden" id="cs_iniciativa_manual_override" name="cs_iniciativa_manual_override" value="<?php echo esc_attr( $ini_manual_override ); ?>">
<input type="hidden" id="cs_ac_manual_override" name="cs_ac_manual_override" value="<?php echo esc_attr( $ac_manual_override ); ?>">
<input type="hidden" id="cs_velocidad_manual_override" name="cs_velocidad_manual_override" value="<?php echo esc_attr( $vel_manual_override ); ?>">

        <!-- CARACTERÍSTICAS -->
      <h3 class="subtitulo-hoja-personaje">Características</h3>

<?php
$filas = [
  'Fuerza'              => ['cs_fuerza', 'cs_fuerza_mod'],
  'Destreza'            => ['cs_destreza', 'cs_destreza_mod'],
  'Constitución'        => ['cs_constitucion', 'cs_constitucion_mod'],
  'Inteligencia'        => ['cs_inteligencia', 'cs_inteligencia_mod'],
  'Sabiduría'           => ['cs_sabiduria', 'cs_sabiduria_mod'],
  'Carisma'             => ['cs_carisma', 'cs_carisma_mod'],
  'Bonificador de competencia' => ['cs_proeficiencia', null],
];

foreach ($filas as $label => $keys_row) :
    $campo_valor = $keys_row[0];
    $campo_mod   = $keys_row[1];
    $valor       = isset($datos[$campo_valor]) ? $datos[$campo_valor] : '';
    $mod         = $campo_mod ? (isset($datos[$campo_mod]) ? $datos[$campo_mod] : '') : '';
?>
  <div class="fila-stat">
    <label class="stat-label">
      <?php echo esc_html($label); ?>
    </label>

    <p class="stat-main-display" id="display_<?php echo esc_attr($campo_valor); ?>"></p>

    <span class="stat-mod-text">
      <?php echo ($campo_valor === 'cs_proeficiencia') ? 'Bonus' : 'Mod'; ?>
    </span>

    <p
      class="stat-mod-display"
      id="display_<?php echo esc_attr($campo_mod ? $campo_mod : $campo_valor . '_mod'); ?>"
    ></p>

    <input
      type="hidden"
      id="<?php echo esc_attr($campo_valor); ?>"
      name="<?php echo esc_attr($campo_valor); ?>"
      value="<?php echo esc_attr($valor); ?>"
    >

    <?php if ($campo_mod): ?>
      <input
        type="hidden"
        id="<?php echo esc_attr($campo_mod); ?>"
        name="<?php echo esc_attr($campo_mod); ?>"
        value="<?php echo esc_attr($mod); ?>"
      >
    <?php endif; ?>
  </div>
<?php endforeach; ?>

        <!-- TIRADAS DE SALVACIÓN -->
        <h3 class="subtitulo-hoja-personaje">Tiradas de salvación</h3>

        <?php
        $saving_throws = [
          'Fuerza'       => ['save' => 'cs_save_fuerza',       'prof' => 'cs_prof_save_fuerza',       'mod' => 'cs_fuerza_mod'],
          'Destreza'     => ['save' => 'cs_save_destreza',     'prof' => 'cs_prof_save_destreza',     'mod' => 'cs_destreza_mod'],
          'Constitución' => ['save' => 'cs_save_constitucion', 'prof' => 'cs_prof_save_constitucion', 'mod' => 'cs_constitucion_mod'],
          'Inteligencia' => ['save' => 'cs_save_inteligencia', 'prof' => 'cs_prof_save_inteligencia', 'mod' => 'cs_inteligencia_mod'],
          'Sabiduría'    => ['save' => 'cs_save_sabiduria',    'prof' => 'cs_prof_save_sabiduria',    'mod' => 'cs_sabiduria_mod'],
          'Carisma'      => ['save' => 'cs_save_carisma',      'prof' => 'cs_prof_save_carisma',      'mod' => 'cs_carisma_mod'],
        ];
        ?>

        <div class="saves-list">
          <?php foreach ($saving_throws as $label => $cfg) :
              $save_field = $cfg['save'];
              $prof_field = $cfg['prof'];

              $save_val  = isset($datos[$save_field]) ? $datos[$save_field] : '';
              $raw_prof  = isset($datos[$prof_field]) ? $datos[$prof_field] : 0;
              $es_prof   = ($raw_prof == 1); // 1 o "1" => true
          ?>
            <div class="fila-save">
              <span class="save-label"><?php echo esc_html($label); ?></span>
 <!-- Valor de la tirada como <p> (no editable) -->
              <p class="save-display" id="display_<?php echo esc_attr($save_field); ?>"></p>

              <!-- Indicador visual de competencia -->
              <span class="skill-indicator" data-save-indicator="<?php echo esc_attr($save_field); ?>">
                <span class="skill-icon<?php echo $es_prof ? ' skill-icon--prof' : ''; ?>" data-save-icon="<?php echo esc_attr($save_field); ?>"></span>
              </span>


             
              <!-- Inputs ocultos -->
              <input
                type="hidden"
                id="<?php echo esc_attr($save_field); ?>"
                name="<?php echo esc_attr($save_field); ?>"
                value="<?php echo esc_attr($save_val); ?>"
              >
              <input
                type="hidden"
                id="prof_<?php echo esc_attr($save_field); ?>"
                name="<?php echo esc_attr($prof_field); ?>"
                value="<?php echo $es_prof ? '1' : '0'; ?>"
              >
            </div>
          <?php endforeach; ?>
        </div>

	<hr class="temp-pv-separator">	  
        <!-- HABILIDADES AGRUPADAS -->
        <h3 class="subtitulo-hoja-personaje">Habilidades</h3>

        <input type="hidden" id="skills_expertise" name="skills_expertise" value="<?php echo esc_attr(isset($datos['skills_expertise']) ? $datos['skills_expertise'] : ''); ?>">
        <div class="skills-list">
          <?php foreach ($skill_groups as $stat_label => $skills) : ?>
            <div class="skills-group">
              <h4 class="skills-group-title"><?php echo esc_html($stat_label); ?></h4>
              <hr class="skills-group-separator">

<?php foreach ($skills as $skill) :
    $s_field = $skill['skill'];
    $p_field = $skill['prof'];

    $s_val    = isset($datos[$s_field]) ? $datos[$s_field] : '';
    $raw_prof = isset($datos[$p_field]) ? $datos[$p_field] : 0;

    // ACF puede devolver 1, "1", true... lo normalizamos a booleano
    $es_prof = ($raw_prof == 1); // OJO: comparación no estricta a propósito
?>

  <div class="fila-skill">
    <div class="skill-toggles">
      <span class="skill-indicator" data-skill-indicator="<?php echo esc_attr($s_field); ?>">
        <span class="skill-icon<?php echo $es_prof ? ' skill-icon--prof' : ''; ?>" data-skill-icon="<?php echo esc_attr($s_field); ?>"></span>
        <small class="skill-source-label" data-skill-label="<?php echo esc_attr($s_field); ?>"></small>
      </span>
    </div>

    <!-- Nombre de la habilidad -->
    <span class="skill-label"><?php echo esc_html($skill['label']); ?></span>

    <!-- Modificador (columna roja, alineado a la derecha) -->
    <p class="skill-display" id="display_<?php echo esc_attr($s_field); ?>"></p>

    <input
      type="hidden"
      id="<?php echo esc_attr($s_field); ?>"
      name="<?php echo esc_attr($s_field); ?>"
      value="<?php echo esc_attr($s_val); ?>"
    >
    <input
      type="hidden"
      id="prof_<?php echo esc_attr($s_field); ?>"
      name="<?php echo esc_attr($p_field); ?>"
      value="<?php echo $es_prof ? '1' : '0'; ?>"
    >
	  
	  
	  
	  


	  
	  
	  
	  
	  
	  
	  
  </div>
				
				
<?php endforeach; ?>

            </div>
          <?php endforeach; ?>
        </div>
		  <hr class="temp-pv-separator">

		  	  <!-- BLOQUE: Competencias (armas, armaduras, herramientas, idiomas) -->
<div class="profs-container">
  <div class="profs-header">
    <h3 class="profs-title">Competencias</h3>
  </div>

  <div class="profs-list">
    <div class="prof-item">
      <span class="basic-label">Armas</span>
      <p class="basic-text" id="display_cs_armas"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Armaduras</span>
      <p class="basic-text" id="display_cs_armaduras"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Herramientas</span>
      <p class="basic-text" id="display_cs_herramientas"></p>
    </div>
    <div class="prof-item">
      <span class="basic-label">Idiomas</span>
      <p class="basic-text" id="display_cs_idiomas"></p>
    </div>

  </div>
</div>

  <div id="character-extended-module" class="character-extended">
  <div class="character-extended__tabs">
    <button type="button" class="character-extended__tab is-active" data-ext-tab="features">
      Feats &amp; Traits
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="spells">
      Conjuros
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="actions">
      Acciones
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="background">
      Trasfondo
    </button>
    <button type="button" class="character-extended__tab" data-ext-tab="tools">
      Herramientas
    </button>
  </div>
  <div id="character-extended-panel" class="character-extended__panel">
    <p class="character-extended__loading">Cargando datos...</p>
  </div>
</div>

        <!-- Modal para editar INI / CA / VEL / PV -->
        <div id="sheet-overlay" class="modal-overlay" style="display:none;">
  <div class="modal-contenido modal-contenido--sheet">
    <span class="close-sheet-popup">&times;</span>
    <div class="sheet-modal-scroll">
      <section class="sheet-section sheet-section--stats">
        <h3>Modificar características</h3>
        <div id="stats-fields">
<?php
  $stats_modal = [
    'Fuerza'       => 'cs_fuerza',
    'Destreza'     => 'cs_destreza',
    'Constitución' => 'cs_constitucion',
    'Inteligencia' => 'cs_inteligencia',
    'Sabiduría'    => 'cs_sabiduria',
    'Carisma'      => 'cs_carisma',
  ];
  foreach ($stats_modal as $label => $field) :
?>
          <div class="stats-modal-row">
            <label><?php echo esc_html($label); ?></label>
            <input
              type="number"
              class="stats-modal-input"
              data-stat="<?php echo esc_attr($field); ?>"
              min="0"
              max="99"
            >
          </div>
<?php endforeach; ?>
        </div>
        <div class="stats-expertise-manager">
          <label for="expertise-select">Añadir pericia</label>
          <div class="expertise-controls">
            <select id="expertise-select" class="basics-modal-input"></select>
            <button type="button" id="expertise-add" class="btn-basicos-mod">Añadir</button>
          </div>
          <ul id="expertise-list" class="expertise-list"></ul>
        </div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--basics">
        <h3>Datos básicos</h3>
        <div class="basics-modal-row">
          <label>Nivel</label>
          <input type="number" min="1" max="20" class="basics-modal-input" data-basic="nivel">
        </div>
        <div class="basics-modal-row">
          <label>Raza</label>
          <select id="modal-raza" class="basics-modal-input">
            <option value="">Cargando razas…</option>
          </select>
        </div>
        <div class="basics-modal-row">
          <label>Clase</label>
          <select id="modal-clase" class="basics-modal-input">
            <option value="">Cargando clases…</option>
          </select>
        </div>
        <div class="basics-modal-row">
          <label>Subclase</label>
          <select id="modal-subclase" class="basics-modal-input" disabled>
            <option value="">Selecciona una clase primero…</option>
          </select>
        </div>
        <div class="basics-modal-row basics-modal-row--background">
          <label>Trasfondo</label>
          <select id="modal-background" class="basics-modal-input">
            <option value="">Cargando trasfondos…</option>
          </select>
        </div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--esoterics<?php echo $is_mutagenist ? '' : ' is-hidden'; ?>" data-theory-section>
        <h3>Teorías esotéricas</h3>
        <p class="sheet-section__hint">Gestiona aquí las Esoteric Theories disponibles para la clase Apothecary.</p>
        <div id="esoteric-theories-container" class="theory-picker"></div>
      </section>

      <hr class="temp-pv-separator">

      <section class="sheet-section sheet-section--profs">
        <h3>Competencias</h3>
        <div class="profs-modal-section">
          <h4>Armas</h4>
          <div class="profs-modal-row">
            <select id="profs-weapons-select" class="basics-modal-input">
              <option value="">Cargando armas…</option>
            </select>
            <button type="button" id="profs-weapons-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-weapons-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Armaduras</h4>
          <div class="profs-modal-row">
            <select id="profs-armors-select" class="basics-modal-input">
              <option value="">Cargando armaduras…</option>
            </select>
            <button type="button" id="profs-armors-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-armors-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Herramientas</h4>
          <div class="profs-modal-row">
            <select id="profs-tools-select" class="basics-modal-input">
              <option value="">Cargando herramientas…</option>
            </select>
            <button type="button" id="profs-tools-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-tools-list" class="profs-modal-list"></ul>
        </div>
        <div class="profs-modal-section">
          <h4>Idiomas</h4>
          <div class="profs-modal-row">
            <select id="profs-languages-select" class="basics-modal-input">
              <option value="">Cargando idiomas…</option>
            </select>
            <button type="button" id="profs-languages-add" class="btn-basicos-mod btn-profs-add">Añadir</button>
          </div>
          <ul id="profs-languages-list" class="profs-modal-list"></ul>
        </div>
      </section>
    </div>
    <div class="sheet-modal-actions">
      <button type="button" id="sheet-apply" class="btn-primary">Aplicar cambios</button>
    </div>
  </div>
</div>


    <?php
    return ob_get_clean();
}




//FIN RENDERIZAR HOJA DE PERSONAJE____________________________________________________________________

/**
 * Render del módulo de combate en una página dedicada.
 */
function renderizar_combate_personaje( $post_id ) {
	if ( ! $post_id ) {
		return '';
	}

	if ( isset( $_POST['combate_guardar'] ) && intval( $_POST['post_id'] ) === $post_id ) {
		if ( drak_user_can_manage_personaje( $post_id ) ) {
			$attack_extra_main = intval( drak_get_post_value( 'combat_attack_extra_main', 0 ) );
			$damage_extra_main = intval( drak_get_post_value( 'combat_damage_extra_main', 0 ) );
			$attack_extra_off  = intval( drak_get_post_value( 'combat_attack_extra_off', 0 ) );
			$damage_extra_off  = intval( drak_get_post_value( 'combat_damage_extra_off', 0 ) );
			$ac_extra          = intval( drak_get_post_value( 'combat_ac_extra', 0 ) );
			$shield_extra      = intval( drak_get_post_value( 'combat_shield_extra', 0 ) );
			$temp_hp_extra     = intval( drak_get_post_value( 'combat_temp_hp_extra', 0 ) );
			$notes             = drak_get_post_value( 'combat_notes', '' );
			update_post_meta( $post_id, 'combat_attack_extra_main', $attack_extra_main );
			update_post_meta( $post_id, 'combat_damage_extra_main', $damage_extra_main );
			update_post_meta( $post_id, 'combat_attack_extra_off', $attack_extra_off );
			update_post_meta( $post_id, 'combat_damage_extra_off', $damage_extra_off );
			update_post_meta( $post_id, 'combat_ac_extra', $ac_extra );
			update_post_meta( $post_id, 'combat_shield_extra', $shield_extra );
			update_post_meta( $post_id, 'combat_temp_hp_extra', $temp_hp_extra );
			update_post_meta( $post_id, 'combat_notes', sanitize_text_field( $notes ) );
			echo '<div class="mensaje-confirmacion">✅ Módulo de combate actualizado.</div>';
		} else {
			echo '<div class="mensaje-confirmacion">❌ No tienes permisos para actualizar este personaje.</div>';
		}
	}

	$fields = [
		'cs_fuerza', 'cs_fuerza_mod',
		'cs_destreza', 'cs_destreza_mod',
		'cs_constitucion', 'cs_constitucion_mod',
		'cs_inteligencia', 'cs_inteligencia_mod',
		'cs_sabiduria', 'cs_sabiduria_mod',
		'cs_carisma', 'cs_carisma_mod',
		'cs_proeficiencia',
		'nivel', 'clase', 'subclase', 'raza', 'background',
		'cs_iniciativa', 'cs_ac', 'cs_velocidad', 'cs_hp', 'cs_hp_temp',
		'prof_weapons', 'prof_armors', 'prof_tools', 'prof_languages',
	];
	$datos = [];
	foreach ( $fields as $field ) {
		$datos[ $field ] = get_field( $field, $post_id );
	}

	$hp                = isset( $datos['cs_hp'] ) ? $datos['cs_hp'] : '';
	$hp_temp           = isset( $datos['cs_hp_temp'] ) ? $datos['cs_hp_temp'] : '';
	$hp_manual_override = get_post_meta( $post_id, 'cs_hp_manual_override', true ) ? '1' : '';
	$weapon_main       = drak_get_weapon_payload( $post_id, 'arma_principal' );
	$weapon_offhand    = drak_get_weapon_payload( $post_id, 'arma_secundaria' );
	$armor_equipped    = drak_get_armor_payload( $post_id );
	$attack_extra_main = intval( get_post_meta( $post_id, 'combat_attack_extra_main', true ) );
	$damage_extra_main = intval( get_post_meta( $post_id, 'combat_damage_extra_main', true ) );
	$attack_extra_off  = intval( get_post_meta( $post_id, 'combat_attack_extra_off', true ) );
	$damage_extra_off  = intval( get_post_meta( $post_id, 'combat_damage_extra_off', true ) );
	$ac_extra          = intval( get_post_meta( $post_id, 'combat_ac_extra', true ) );
	$shield_extra      = intval( get_post_meta( $post_id, 'combat_shield_extra', true ) );
	$temp_hp_extra     = intval( get_post_meta( $post_id, 'combat_temp_hp_extra', true ) );
	$notes             = sanitize_text_field( get_post_meta( $post_id, 'combat_notes', true ) );

	ob_start();
	?>
  <div class="hoja-personaje-container">
	  <form method="post" class="formulario-hoja-personaje">
	    <input type="hidden" name="combate_guardar" value="1">
	    <input type="hidden" id="post_id" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

      <?php if ( function_exists( 'drak_recursos_render_block' ) ) : ?>
        <div class="combat-card combat-card--resources">
          <?php echo drak_recursos_render_block( $post_id ); ?>
        </div>
      <?php endif; ?>

	    <section id="combat-module" class="combat-module">
	      <h3 class="subtitulo-hoja-personaje">Módulo de combate</h3>
	      <div class="combat-module__grid">
	        <div class="combat-card combat-card--temp">
            <div class="basicos-container basicos-container--combat">
              <div class="basicos-list">
                <div class="basic-item">
                  <span class="basic-label">INI</span>
                  <p class="basic-circle" id="combat_display_cs_iniciativa"><?php echo esc_html( $datos['cs_iniciativa'] ?? '' ); ?></p>
                </div>
                <div class="basic-item">
                  <span class="basic-label">CA</span>
                  <p class="basic-circle" id="combat_display_cs_ac"><?php echo esc_html( $datos['cs_ac'] ?? '' ); ?></p>
                </div>
                <div class="basic-item">
                  <span class="basic-label">VEL</span>
                  <p class="basic-circle" id="combat_display_cs_velocidad"><?php echo esc_html( $datos['cs_velocidad'] ?? '' ); ?></p>
                </div>
                <div class="basic-item">
                  <span class="basic-label">PV</span>
                  <p class="basic-circle" id="combat_display_cs_hp"><?php echo esc_html( $hp ); ?></p>
                </div>
              </div>
            </div>

	          <div class="temp-pv-block">
	            <div class="temp-pv-header">
	              <h4 class="combat-card__title">PV temporales</h4>
	              <div class="temp-pv-inline">
	                <label class="combat-temp-mod">
	                  Mod PV
	                  <input type="number" name="combat_temp_hp_extra" id="combat_temp_hp_extra" value="<?php echo esc_attr( $temp_hp_extra ); ?>">
	                </label>
	                <button type="button" id="btn-reset-temp-pv" class="btn-reset-temp-pv">RESET</button>
	              </div>
	            </div>
	            <p id="display_cs_temp_hp" class="basic-circle" contenteditable="true" spellcheck="false"><?php echo esc_html( $hp_temp ); ?></p>
	            <div class="temp-pv-slider-wrapper">
	              <input type="range" id="slider_temp_hp" min="0" max="999" value="<?php echo esc_attr( $hp_temp ); ?>">
	            </div>
	          </div>

          <div class="combat-armor-wrapper">
            <div class="combat-armor-row">
              <div class="combat-armor-box">
                <div class="combat-armor-box__header">
                  <span>CA</span>
                  <strong id="combat-armor-total">—</strong>
                </div>
                <div class="combat-armor-box__body">
                  <p class="combat-armor-base">Base: <span id="combat-armor-base">—</span></p>
                  <label class="combat-armor-mod">
                    Modificador
                    <input type="number" name="combat_ac_extra" id="combat_ac_extra" value="<?php echo esc_attr( $ac_extra ); ?>">
                  </label>
                </div>
              </div>
              <div class="combat-armor-box">
                <div class="combat-armor-box__header">
                  <span>Escudo</span>
                  <strong id="combat-shield-total">—</strong>
                </div>
                <div class="combat-armor-box__body">
                  <p class="combat-armor-base">Base: <span id="combat-shield-base">0</span></p>
                  <label class="combat-armor-mod">
                    Modificador
                    <input type="number" name="combat_shield_extra" id="combat_shield_extra" value="<?php echo esc_attr( $shield_extra ); ?>">
                  </label>
                </div>
              </div>
            </div>
            <div class="combat-armor-card" id="combat-armor-card" data-armor="<?php echo esc_attr( wp_json_encode( $armor_equipped ) ); ?>">
              <p class="combat-card__eyebrow">Armadura equipada</p>
              <h4 id="combat-armor-name"><?php echo ! empty( $armor_equipped['name'] ) ? esc_html( $armor_equipped['name'] ) : 'Sin armadura'; ?></h4>
              <p class="combat-armor-meta" id="combat-armor-meta">
                <?php
                if ( ! empty( $armor_equipped['name'] ) ) {
                    $meta = array_filter(
                        [
                            ! empty( $armor_equipped['ac'] ) ? 'CA base ' . $armor_equipped['ac'] : '',
                            ! empty( $armor_equipped['type'] ) ? 'Tipo: ' . $armor_equipped['type'] : '',
                            'Sigilo: ' . ( ! empty( $armor_equipped['stealth_disadvantage'] ) ? 'Desventaja' : '—' ),
                        ]
                    );
                    echo esc_html( implode( ' · ', $meta ) );
                }
                ?>
              </p>
            </div>
          </div>

	        </div>

	        <div
	          class="combat-card combat-card--attack"
	          id="combat-attack-card"
	        >
          <div class="combat-weapon-pair">
            <div class="combat-weapon-block" id="combat-weapon-main" data-weapon="<?php echo esc_attr( wp_json_encode( $weapon_main ) ); ?>">
              <div class="combat-card__header">
                <p class="combat-card__eyebrow">Arma principal</p>
                <h4 id="combat-main-weapon-name"><?php echo ! empty( $weapon_main['name'] ) ? esc_html( $weapon_main['name'] ) : 'Sin arma asignada'; ?></h4>
                <p class="combat-weapon-meta" id="combat-main-weapon-meta"></p>
              </div>
              <div class="combat-attack-grid">
                <div class="combat-attack-box">
                  <span class="combat-label">Tirada de ataque</span>
                  <p id="combat-main-attack-value" class="combat-value">—</p>
                  <small id="combat-main-attack-breakdown" class="combat-breakdown">Selecciona un arma en el inventario.</small>
                  </div>
                  <div class="combat-attack-box">
                    <span class="combat-label">Daño</span>
                    <p id="combat-main-damage-value" class="combat-value">—</p>
                    <small id="combat-main-damage-breakdown" class="combat-breakdown"></small>
                  </div>
                </div>
              <div class="combat-ammo is-hidden" id="combat-ammo-main">
                <div class="combat-ammo__row">
                  <div class="combat-ammo__label">
                    Munición
                  </div>
                  <div class="combat-ammo__status">
                    <span class="combat-ammo__value" id="combat-ammo-value-main">—</span>
                  </div>
                </div>
                <button type="button" class="combat-ammo__consume combat-ammo__consume--full" data-ammo-consume="main">Lanzar munición</button>
                <p class="combat-ammo__warning" id="combat-ammo-warning-main">Sin munición</p>
              </div>
              <div class="combat-weapon-modifiers">
                <label>
                  Bonificador al ataque
                  <input type="number" name="combat_attack_extra_main" id="combat_attack_extra_main" value="<?php echo esc_attr( $attack_extra_main ); ?>">
                </label>
                <label>
                  Bonificador al daño
                  <input type="number" name="combat_damage_extra_main" id="combat_damage_extra_main" value="<?php echo esc_attr( $damage_extra_main ); ?>">
                </label>
              </div>
              </div>

            <div class="combat-weapon-block" id="combat-weapon-offhand" data-weapon="<?php echo esc_attr( wp_json_encode( $weapon_offhand ) ); ?>">
              <div class="combat-card__header">
                <p class="combat-card__eyebrow">Arma secundaria</p>
                <h4 id="combat-off-weapon-name"><?php echo ! empty( $weapon_offhand['name'] ) ? esc_html( $weapon_offhand['name'] ) : 'Sin arma secundaria'; ?></h4>
                <p class="combat-weapon-meta" id="combat-off-weapon-meta"></p>
              </div>
              <div class="combat-attack-grid">
                <div class="combat-attack-box">
                  <span class="combat-label">Tirada de ataque</span>
                  <p id="combat-off-attack-value" class="combat-value">—</p>
                  <small id="combat-off-attack-breakdown" class="combat-breakdown">Selecciona un arma secundaria en el inventario.</small>
                  </div>
                  <div class="combat-attack-box">
                    <span class="combat-label">Daño</span>
                    <p id="combat-off-damage-value" class="combat-value">—</p>
                    <small id="combat-off-damage-breakdown" class="combat-breakdown"></small>
                  </div>
                </div>
              <div class="combat-ammo is-hidden" id="combat-ammo-off">
                <div class="combat-ammo__row">
                  <div class="combat-ammo__label">
                    Munición
                  </div>
                  <div class="combat-ammo__status">
                    <span class="combat-ammo__value" id="combat-ammo-value-off">—</span>
                  </div>
                </div>
                <button type="button" class="combat-ammo__consume combat-ammo__consume--full" data-ammo-consume="off">Lanzar munición</button>
                <p class="combat-ammo__warning" id="combat-ammo-warning-off">Sin munición</p>
              </div>
              <div class="combat-weapon-modifiers">
                <label>
                  Bonificador al ataque
                  <input type="number" name="combat_attack_extra_off" id="combat_attack_extra_off" value="<?php echo esc_attr( $attack_extra_off ); ?>">
                </label>
                <label>
                  Bonificador al daño
                  <input type="number" name="combat_damage_extra_off" id="combat_damage_extra_off" value="<?php echo esc_attr( $damage_extra_off ); ?>">
                </label>
              </div>
            </div>
            </div>

          <div class="combat-modifiers">
            <div class="combat-modifiers__row">
              <label for="combat_notes">Notas de talentos/maestrías</label>
              <textarea
                id="combat_notes"
                name="combat_notes"
                rows="2"
                placeholder="Añade aquí dotes o rasgos que modifiquen el ataque con esta arma."
              ><?php echo esc_textarea( $notes ); ?></textarea>
            </div>
          </div>
        </div>
        <div class="combat-card combat-card--conditions">
          <div class="combat-card__header">
            <p class="combat-card__eyebrow">Condiciones</p>
            <h4>Condición actual</h4>
          </div>
          <div class="combat-conditions">
            <label for="combat-condition-select">Selecciona condición</label>
            <div class="combat-condition-selector">
              <select id="combat-condition-select">
                <option value="">Cargando condiciones…</option>
              </select>
              <button type="button" id="combat-condition-add" class="weapon-switch-btn">Añadir</button>
            </div>
            <div id="combat-condition-preview" class="combat-condition-body">
              <p>Selecciona una condición para ver sus efectos y añádela.</p>
            </div>
            <div id="combat-condition-list" class="combat-condition-list"></div>
          </div>
        </div>
      </div>

    </section>

	    <div style="display:none;">
	      <?php
	      $abilities = [
		      'cs_fuerza', 'cs_destreza', 'cs_constitucion', 'cs_inteligencia', 'cs_sabiduria', 'cs_carisma',
	      ];
	      foreach ( $abilities as $field ) :
		      $mod_field = "{$field}_mod";
		      $val       = isset( $datos[ $field ] ) ? $datos[ $field ] : '';
		      $mod       = isset( $datos[ $mod_field ] ) ? $datos[ $mod_field ] : '';
		      ?>
	        <p id="display_<?php echo esc_attr( $field ); ?>"></p>
	        <p id="display_<?php echo esc_attr( $mod_field ); ?>"></p>
	        <input type="hidden" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $val ); ?>">
	        <input type="hidden" id="<?php echo esc_attr( $mod_field ); ?>" name="<?php echo esc_attr( $mod_field ); ?>" value="<?php echo esc_attr( $mod ); ?>">
	      <?php endforeach; ?>

	      <p id="display_cs_proeficiencia"></p>
	      <input type="hidden" id="cs_proeficiencia" name="cs_proeficiencia" value="<?php echo esc_attr( isset( $datos['cs_proeficiencia'] ) ? $datos['cs_proeficiencia'] : '' ); ?>">

	      <p id="display_cs_iniciativa"></p>
	      <p id="display_cs_ac"></p>
	      <p id="display_cs_velocidad"></p>
	      <p id="display_cs_hp"></p>

	      <input type="hidden" id="cs_iniciativa" name="cs_iniciativa" value="<?php echo esc_attr( isset( $datos['cs_iniciativa'] ) ? $datos['cs_iniciativa'] : '' ); ?>">
	      <input type="hidden" id="cs_ac" name="cs_ac" value="<?php echo esc_attr( isset( $datos['cs_ac'] ) ? $datos['cs_ac'] : '' ); ?>">
	      <input type="hidden" id="cs_velocidad" name="cs_velocidad" value="<?php echo esc_attr( isset( $datos['cs_velocidad'] ) ? $datos['cs_velocidad'] : '' ); ?>">
	      <input type="hidden" id="cs_hp" name="cs_hp" value="<?php echo esc_attr( $hp ); ?>">
	      <input type="hidden" id="cs_hp_temp" name="cs_hp_temp" value="<?php echo esc_attr( $hp_temp ); ?>">
	      <input type="hidden" id="cs_hp_manual_override" name="cs_hp_manual_override" value="<?php echo esc_attr( $hp_manual_override ); ?>">

	      <input type="hidden" id="nivel" name="nivel" value="<?php echo esc_attr( isset( $datos['nivel'] ) ? $datos['nivel'] : '' ); ?>">
	      <input type="hidden" id="clase" name="clase" value="<?php echo esc_attr( isset( $datos['clase'] ) ? $datos['clase'] : '' ); ?>">
	      <input type="hidden" id="subclase" name="subclase" value="<?php echo esc_attr( isset( $datos['subclase'] ) ? $datos['subclase'] : '' ); ?>">
	      <input type="hidden" id="raza" name="raza" value="<?php echo esc_attr( isset( $datos['raza'] ) ? $datos['raza'] : '' ); ?>">
	      <input type="hidden" id="background" name="background" value="<?php echo esc_attr( isset( $datos['background'] ) ? $datos['background'] : '' ); ?>">
	      <input type="hidden" id="prof_weapons" name="prof_weapons" value="<?php echo esc_attr( isset( $datos['prof_weapons'] ) ? $datos['prof_weapons'] : '' ); ?>">
	      <input type="hidden" id="prof_armors" name="prof_armors" value="<?php echo esc_attr( isset( $datos['prof_armors'] ) ? $datos['prof_armors'] : '' ); ?>">
	      <input type="hidden" id="prof_tools" name="prof_tools" value="<?php echo esc_attr( isset( $datos['prof_tools'] ) ? $datos['prof_tools'] : '' ); ?>">
	      <input type="hidden" id="prof_languages" name="prof_languages" value="<?php echo esc_attr( isset( $datos['prof_languages'] ) ? $datos['prof_languages'] : '' ); ?>">
	    </div>
	  </form>
	</div>
	<?php
	return ob_get_clean();
}





add_action('wp_footer', function () {
    if (!is_page()) return;

    $personaje_context_id = drak_get_request_personaje_id();
    $delerium_nonce = $personaje_context_id ? wp_create_nonce('delerium_module_' . $personaje_context_id) : '';
    $delerium_autosave = [
        'ajaxUrl' => $personaje_context_id ? esc_url_raw( drak_get_admin_ajax_url() ) : '',
        'nonce'   => $delerium_nonce,
        'postId'  => $personaje_context_id,
    ];
    ?>
<script>
window.DELERIUM_AUTOSAVE = <?php echo wp_json_encode( $delerium_autosave ); ?>;
	
document.addEventListener('DOMContentLoaded', function () {
    const overlay       = document.getElementById('item-form-overlay');
  const form          = document.getElementById('item-form');
  if (!overlay || !form) return; // No estamos en la página de inventario

  const nameInput     = document.getElementById('item-name');
  const nameSelect    = document.getElementById('item-name-select');
  const sizeSelect    = document.getElementById('item-size');
  const qtySelect     = document.getElementById('item-qty');
  const currentSlot   = document.getElementById('current-slot');
  const slotNumero    = document.getElementById('slot-numero');
  const defaultNameOptions = nameSelect ? Array.from(nameSelect.options).map((opt) => ({ value: opt.value, label: opt.textContent })) : [];
  const potionOptions = <?php echo wp_json_encode( drak_get_potion_options() ); ?>;
  const potionEntries = <?php echo wp_json_encode( drak_get_potion_entries() ); ?>;
  const itemEntries = <?php echo wp_json_encode( drak_get_item_entries() ); ?>;
  const scrollOptions = <?php echo wp_json_encode( drak_get_scroll_options() ); ?>;

  const deleteOverlay = document.getElementById('delete-form-overlay');
  const deleteForm    = document.getElementById('delete-form');
  const deleteContent = document.getElementById('delete-form-content');
  const inventoryForm = document.getElementById('mainpack-formulario');
  const inventoryNonce = document.getElementById('inventory_nonce');
  const inventoryAjaxUrl = inventoryForm?.dataset.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
  let inventorySaveTimer = null;

  function queueInventorySave() {
    if (!inventoryForm || !inventoryNonce || !inventoryAjaxUrl) return;
    clearTimeout(inventorySaveTimer);
    inventorySaveTimer = setTimeout(() => {
      const fd = new FormData(inventoryForm);
      fd.append('action', 'drak_save_inventory');
      fd.append('inventory_nonce', inventoryNonce.value);
      fetch(inventoryAjaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      }).catch(() => {});
    }, 500);
  }
  window.drakQueueInventorySave = queueInventorySave;

  function initSpecialSlotsV2() {
    const container = document.querySelector('[data-inventory-special]');
    if (!container) return;
    const intScore = parseInt(container.dataset.int || '0', 10) || 0;
    const wisScore = parseInt(container.dataset.wis || '0', 10) || 0;
    const dexScore = parseInt(container.dataset.dex || '0', 10) || 0;

    const canPotion4 = intScore > 16 || wisScore > 16 || dexScore > 16;
    const canScroll56 = intScore > 16 || wisScore > 16;

    const potion4 = container.querySelector('input[name="potions_slot_4"]');
    if (potion4) {
      potion4.disabled = !canPotion4;
      if (!canPotion4) potion4.value = '';
    }
    ['5', '6'].forEach((idx) => {
      const input = container.querySelector(`input[name="scrolls_slot_${idx}"]`);
      if (input) {
        input.disabled = !canScroll56;
        if (!canScroll56) input.value = '';
      }
    });

    container.querySelectorAll('input, select, textarea').forEach((el) => {
      el.addEventListener('change', queueInventorySave);
      el.addEventListener('input', queueInventorySave);
    });
  }
	
  // Convierte el string guardado ("Antorcha x 2 - Cuerda")
  // en una lista de <p> dentro del slot
  function buildSlotHTMLFromValue(value, type = 'generic') {
    const trimmed = (value || "").trim();
    if (!trimmed) {
      return '<p class="slot-empty">(vacío)</p>';
    }

    const parts = trimmed.split(" - "); // cada objeto del slot
    const htmlParts = parts.map(part => {
      const texto = part.trim();
      if (!texto) return "";
      if (type === 'potion' || type === 'scroll') {
        const safe = texto.replace(/"/g, '&quot;');
        return `<p class="slot-item slot-item--clickable" data-item-name="${safe}" data-item-type="${type}">${texto}</p>`;
      }
      return `<p class="slot-item">${texto}</p>`;
    }).filter(Boolean);

    return htmlParts.join("");
  }

  const slotMeta = {};

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function updateSlotView(slot) {
    const slotEl = document.querySelector(`.inventory-slot[data-slot="${slot}"]`);
    const type = slotEl?.dataset.slotType || 'generic';
    const container = document.getElementById(`texto_${slot}`);
    if (!container) return;

    let val = '';
    if (type === 'ammo') {
      const t = document.getElementById('carcaj_type_hidden')?.value || '';
      const amt = parseInt(document.getElementById('carcaj_amount_hidden')?.value || '0', 10) || 0;
      val = t ? `${capitalize(t)} x ${amt}` : '';
      const display = document.getElementById(`input_slot_${slot}`);
      if (display) display.value = val;
    } else if (type === 'rope') {
      const input = document.getElementById(`input_slot_${slot}`);
      val = input?.value === 'rope' ? 'Cuerda' : '';
    } else if (type === 'potion' || type === 'scroll' || type === 'ankward') {
      const input = document.getElementById(`input_slot_${slot}`);
      val = (input?.value || '').trim();
    } else {
      const input = document.getElementById(`input_slot_${slot}`);
      val = (input?.value || '').trim();
    }

    container.innerHTML = buildSlotHTMLFromValue(val, type);
    container.classList.toggle('empty', !val);
    queueInventorySave();
  }

  function updateAllSlots() {
    document.querySelectorAll('.inventory-slot[data-slot]').forEach((el) => {
      const key = el.dataset.slot;
      slotMeta[key] = el.dataset.slotType || 'generic';
      const locked = el.dataset.locked === '1';
      if (locked) {
        el.classList.add('slot-locked');
        el.querySelectorAll('button').forEach((btn) => (btn.disabled = true));
        const input = document.getElementById(`input_slot_${key}`);
        if (input) input.value = '';
      }
      updateSlotView(key);
    });
  }

  updateAllSlots();

  // Click en ítems (pociones/pergaminos) para ver info
  document.addEventListener('click', (ev) => {
    const slotItem = ev.target.closest('.slot-item--clickable');
    if (!slotItem) return;
    const slotContainer = ev.target.closest('.inventory-slot');
    if (!slotContainer) return;
    const type = slotContainer.dataset.slotType || '';
    if (type !== 'potion' && type !== 'scroll') return;
    const name = slotItem.dataset.itemName || slotItem.textContent.trim();
    if (!name) return;
    showItemInfo(name);
  });


  // Rellenar selector de cantidad 1..10 (si está vacío)
  if (qtySelect && !qtySelect.options.length) {
    for (let i = 1; i <= 10; i++) {
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = i;
      qtySelect.appendChild(opt);
    }
  }

  function toggleQtyField() {
    const isSmall = sizeSelect.value === 'pequeño';
    qtySelect.disabled = !isSmall;
  }

  sizeSelect.addEventListener('change', toggleQtyField);
  toggleQtyField();

  // Abrir modal de añadir ("+")
  let currentSlotType = 'generic';

  function populateSelectForType(type) {
    if (!nameSelect) return;
    const basePotionList = (Array.isArray(potionOptions) && potionOptions.length)
      ? potionOptions
      : ['Poción de curación', 'Poción de resistencia', 'Poción (genérica)'];
    const baseScrollList = (Array.isArray(scrollOptions) && scrollOptions.length)
      ? scrollOptions
      : ['Pergamino (genérico)', 'Mapa', 'Documento'];
    const optionsByType = {
      potion: basePotionList,
      scroll: baseScrollList,
      ammo: ['flechas', 'virotes', 'dardos', 'balas'],
    };
    const list = optionsByType[type] || [];
    nameSelect.innerHTML = '';
    const base = document.createElement('option');
    base.value = '';
    base.textContent = '-- Selecciona --';
    nameSelect.appendChild(base);
    if (!list.length && defaultNameOptions.length) {
      defaultNameOptions.forEach(({ value, label }) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        nameSelect.appendChild(opt);
      });
      return;
    }
    list.forEach((label) => {
      const opt = document.createElement('option');
      opt.value = label;
      opt.textContent = label;
      nameSelect.appendChild(opt);
    });
  }

  // Modal de info de ítems (pociones / pergaminos)
  let potionInfoModal = null;

  function showItemInfo(name) {
    const modal = ensureItemInfoModal();
    const body = modal.querySelector('#potion-info-body');
    const titleEl = modal.querySelector('#potion-info-title');
    if (!body || !titleEl) return;
    let entry = '';
    const lookup = (map) => {
      if (!map || typeof map !== 'object') return '';
      return map[name]
        || map[name.toLowerCase()]
        || map[name.replace(/\s+/g, '-').toLowerCase()]
        || map[name.replace(/\s+/g, '').toLowerCase()]
        || map[name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, '-')]
        || '';
    };
    entry = lookup(itemEntries) || lookup(potionEntries);
    if (!entry && potionEntries && typeof potionEntries === 'object') {
      const slug = name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, '-');
      entry = potionEntries[slug] || '';
    }
    titleEl.textContent = name;
    const formatted = entry ? format5eTags(entry) : '<p>No hay descripción disponible.</p>';
    body.innerHTML = formatted;
    modal.style.display = 'flex';
  }

  function ensureItemInfoModal() {
    if (potionInfoModal) return potionInfoModal;
    const overlayInfo = document.createElement('div');
    overlayInfo.className = 'modal-overlay';
    overlayInfo.id = 'potion-info-overlay';
    overlayInfo.style.display = 'none';
    overlayInfo.innerHTML = `
      <div class="modal-contenido">
        <span class="close-popup" id="potion-info-close">×</span>
        <h2 id="potion-info-title">Item</h2>
        <div id="potion-info-body"></div>
      </div>
    `;
    document.body.appendChild(overlayInfo);
    overlayInfo.addEventListener('click', (ev) => {
      if (ev.target === overlayInfo || ev.target.id === 'potion-info-close') {
        overlayInfo.style.display = 'none';
      }
    });
    potionInfoModal = overlayInfo;
    return overlayInfo;
  }

  function format5eTags(html) {
    if (!html) return '';
    const replacements = [
      {
        pattern: /\{@condition\s+([^|}]+)(?:\|[^}]+)?\}/giu,
        replacer: (m, name) => `<span class="dnd-tag dnd-tag--condition" title="Condición">${name.trim()}</span>`,
      },
      {
        pattern: /\{@variantrule\s+([^|}]+)(?:\|[^}]+)?(?:\|[^}]+)?\}/giu,
        replacer: (m, name) => `<span class="dnd-tag dnd-tag--variantrule" title="Regla opcional">${name.trim()}</span>`,
      },
      {
        pattern: /\{@dice\s+([^}]+)\}/giu,
        replacer: (m, roll) => `<span class="dnd-tag dnd-tag--dice" title="Tirada">${roll.trim()}</span>`,
      },
      {
        pattern: /\{#itemEntry\s+([^|}]+)(?:\|[^}]+)?\}/giu,
        replacer: (m, name) => {
          const key = name.trim();
          const slug = key.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, '-');
          const entry = (itemEntries && typeof itemEntries === 'object')
            ? (itemEntries[key] || itemEntries[key.toLowerCase()] || itemEntries[slug] || '')
            : '';
          if (entry) {
            return `<div class="dnd-item-entry">${entry}</div>`;
          }
          return `<span class="dnd-tag dnd-tag--item">${key}</span>`;
        },
      },
      {
        pattern: /\{@dc\s+([^}]+)\}/giu,
        replacer: (m, val) => `<span class="dnd-tag dnd-tag--dc" title="Dificultad">CD ${val.trim()}</span>`,
      },
      {
        pattern: /\{@action\s+([^|}]+)(?:\|[^}]+)?\}/giu,
        replacer: (m, name) => `<span class="dnd-tag dnd-tag--action" title="Acción">${name.trim()}</span>`,
      },
      {
        pattern: /\{@damage\s+([^}]+)\}/giu,
        replacer: (m, dmg) => `<span class="dnd-tag dnd-tag--damage" title="Daño">${dmg.trim()}</span>`,
      },
    ];
    let out = html;
    replacements.forEach(({ pattern, replacer }) => {
      out = out.replace(pattern, replacer);
    });
    return out;
  }

  function prepareModalForSlot(slot, type) {
    currentSlot.value = slot;
    currentSlotType = type || 'generic';
    if (slotNumero) slotNumero.textContent = slot;

    nameInput.value = '';
    if (nameSelect) nameSelect.value = '';
    sizeSelect.value = 'normal';
    qtySelect.value = '1';
    sizeSelect.disabled = type !== 'generic';
    qtySelect.disabled = type === 'potion' || type === 'scroll';
    nameInput.readOnly = type !== 'generic';

    if (type === 'ammo') {
      populateSelectForType('ammo');
      qtySelect.disabled = false;
      qtySelect.innerHTML = '';
      for (let i = 0; i <= 99; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i;
        qtySelect.appendChild(opt);
      }
      const currentType = document.getElementById('carcaj_type_hidden')?.value || '';
      const currentAmount = parseInt(document.getElementById('carcaj_amount_hidden')?.value || '0', 10) || 0;
      if (nameSelect) nameSelect.value = currentType;
      if (nameInput) nameInput.value = currentType;
      qtySelect.value = currentAmount;
      overlay.style.display = 'flex';
      return;
    } else if (type === 'potion' || type === 'scroll') {
      populateSelectForType(type);
      const input = document.getElementById('input_slot_' + slot);
      const currentVal = (input?.value || '').trim();
      if (nameSelect) nameSelect.value = currentVal;
      if (nameInput) nameInput.value = currentVal;
    } else {
      populateSelectForType('generic');
      // generic
      if (qtySelect && !qtySelect.options.length) {
        for (let i = 1; i <= 10; i++) {
          const opt = document.createElement('option');
          opt.value = i;
          opt.textContent = i;
          qtySelect.appendChild(opt);
        }
      }
    }

    toggleQtyField();
    overlay.style.display = 'flex';
  }

  document.querySelectorAll('.inventory-slot[data-slot] .add-item').forEach((btn) => {
    btn.addEventListener('click', function () {
      const slotEl = this.closest('.inventory-slot');
      const slot = slotEl.dataset.slot;
      const type = slotEl.dataset.slotType || 'generic';
      const locked = slotEl.dataset.locked === '1';
      if (locked) {
        alert('Este slot no está disponible (requiere INT/WIS/DEX > 16).');
        return;
      }
      if (type === 'rope') {
        const input = document.getElementById('input_slot_rope');
        if (input) input.value = 'rope';
        updateSlotView('rope');
        return;
      }
      if (type === 'ammo') {
        prepareModalForSlot(slot, 'ammo');
        return;
      }
      prepareModalForSlot(slot, type);
    });
  });

  // Cerrar modal de añadir
  const closeAdd = document.querySelector('.close-popup');
  if (closeAdd) {
    closeAdd.addEventListener('click', function () {
      overlay.style.display = 'none';
    });
  }

  // Si elige de la lista, rellenamos el nombre
  if (nameSelect) {
    nameSelect.addEventListener('change', function () {
      if (this.value) {
        nameInput.value = this.value;
      }
    });
  }

  // Enviar formulario de añadir
form.addEventListener('submit', function (e) {
    e.preventDefault();

    const chosenName = nameSelect ? (nameSelect.value || '') : '';
    const name = (nameInput.value || chosenName).trim();
    const size = sizeSelect.value;
    const qty  = parseInt(qtySelect.value, 10) || 0;
    const slot = currentSlot.value;
    const slotType = currentSlotType || 'generic';

    if (slotType === 'potion' || slotType === 'scroll') {
      if (!name) {
        alert('Introduce un nombre.');
        return;
      }
      const input = document.getElementById('input_slot_' + slot);
      input.value = name;
      updateSlotView(slot);
      overlay.style.display = 'none';
      return;
    }

    if (slotType === 'ammo') {
      const type = (nameSelect.value || nameInput.value || '').trim().toLowerCase();
      const amount = parseInt(qtySelect.value || '0', 10) || 0;
      if (!type) {
        alert('Selecciona un tipo de munición.');
        return;
      }
      const typeHidden = document.getElementById('carcaj_type_hidden');
      const amountHidden = document.getElementById('carcaj_amount_hidden');
      const display = document.getElementById('input_slot_carcaj');
      if (typeHidden) typeHidden.value = type;
      if (amountHidden) amountHidden.value = amount;
      if (display) display.value = `${type} x ${amount}`;
      updateSlotView(slot);
      overlay.style.display = 'none';
      return;
    }

    if (slotType === 'ankward') {
      if (!name) {
        alert('Introduce un objeto.');
        return;
      }
      const input = document.getElementById('input_slot_' + slot);
      input.value = name;
      updateSlotView(slot);
      overlay.style.display = 'none';
      return;
    }

    if (!name || !size || (size === 'pequeño' && !qty)) {
      alert('Todos los campos son obligatorios.');
      return;
    }

    const input = document.getElementById('input_slot_' + slot);
    const p     = document.getElementById('texto_' + slot);
    const currentVal = (input.value || '').trim();
    const items = currentVal ? currentVal.split(' - ') : [];

    const hayObjetoNormal = items.length === 1 && !items[0].includes('x');

   if (size === 'normal') {
      // Solo puede haber un objeto normal y nada más
      if (items.length > 0) {
        alert('Este slot ya tiene objetos. Elimina antes de añadir uno normal.');
        return;
      }
      input.value = name;
    } else {
      // Tamaño pequeño
      if (hayObjetoNormal) {
        alert('No puedes añadir objetos pequeños si ya hay uno normal.');
        return;
      }

      // Parsear objetos pequeños existentes
      const parsed = items
        .map(function (item) {
          const m = item.match(/^(.+?) x (\d+)$/);
          if (m) {
            return { nombre: m[1].trim(), cantidad: parseInt(m[2], 10) };
          }
          return null;
        })
        .filter(Boolean);

      const totalExistente = parsed.reduce(function (sum, it) {
        return sum + it.cantidad;
      }, 0);

      let existente = parsed.find(function (it) {
        return it.nombre === name;
      });

      let nuevoTotal = totalExistente + qty;
      if (nuevoTotal > 10) {
        alert('Máximo 10 objetos pequeños por slot.');
        return;
      }

      if (existente) {
        existente.cantidad += qty;
      } else {
        parsed.push({ nombre: name, cantidad: qty });
      }

      const nuevosItems = parsed.map(function (it) {
        return it.nombre + ' x ' + it.cantidad;
      });

      input.value = nuevosItems.join(' - ');
    }

    updateSlotView(slot);
    overlay.style.display = 'none';
    queueInventorySave();
  });

  // ----------------- ELIMINAR OBJETOS ("-") -----------------

  document.querySelectorAll('.remove-item').forEach((btn) => {
    btn.addEventListener('click', function () {
      const slotEl = this.closest('.inventory-slot');
      const slot = slotEl.dataset.slot;
      const slotType = slotEl.dataset.slotType || 'generic';
      const locked = slotEl.dataset.locked === '1';
      if (locked) return;

      if (['potion', 'scroll', 'ammo', 'rope', 'ankward'].includes(slotType)) {
        if (!confirm('¿Vaciar este slot?')) return;
        if (slotType === 'ammo') {
          const typeHidden = document.getElementById('carcaj_type_hidden');
          const amountHidden = document.getElementById('carcaj_amount_hidden');
          const display = document.getElementById('input_slot_carcaj');
          if (typeHidden) typeHidden.value = '';
          if (amountHidden) amountHidden.value = '0';
          if (display) display.value = '';
        } else if (slotType === 'rope') {
          const input = document.getElementById('input_slot_rope');
          if (input) input.value = '';
        } else {
          const input = document.getElementById('input_slot_' + slot);
          if (input) input.value = '';
        }
        updateSlotView(slot);
        return;
      }

      const input = document.getElementById('input_slot_' + slot);
      const currentVal = (input.value || '').trim();
      const items = currentVal ? currentVal.split(' - ') : [];

      deleteContent.innerHTML = '';
      deleteContent.dataset.slot = slot;

      if (!items.length) {
        deleteContent.innerHTML = '<p>No hay objetos en este slot.</p>';
        deleteOverlay.style.display = 'flex';
        return;
      }

      items.forEach(function (item) {
        const match = item.match(/^(.+?)\s*x\s*(\d+)$/);
        const wrapper = document.createElement('div');

        if (match) {
          const name = match[1].trim();
          const qty  = parseInt(match[2], 10);

          wrapper.innerHTML = `
            <label>${name}</label>
            <select data-name="${name}">
              ${Array.from({length: qty + 1}, (_, i) => `<option value="${i}">${i}</option>`).join('')}
            </select>
          `;
        } else {
          wrapper.innerHTML = `
            <label>${item}</label>
            <input type="checkbox" data-name="${item}"> Eliminar
          `;
        }

        deleteContent.appendChild(wrapper);
      });

      deleteOverlay.style.display = 'flex';
    });
  });

  const closeDelete = document.querySelector('.close-delete-popup');
  if (closeDelete) {
    closeDelete.addEventListener('click', function () {
      deleteOverlay.style.display = 'none';
    });
  }

  deleteForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const slot  = deleteContent.dataset.slot;
    const input = document.getElementById('input_slot_' + slot);
    const items = (input.value || '').split(' - ').filter(Boolean);

    const newItems = [];

    deleteContent.querySelectorAll('div').forEach(function (div) {
      const select   = div.querySelector('select');
      const checkbox = div.querySelector('input[type="checkbox"]');

      if (select) {
        const name      = select.dataset.name;
        const removeQty = parseInt(select.value, 10);
        const itemStr   = items.find(i => i.startsWith(name + ' x'));
        if (itemStr) {
          const currentQty = parseInt(itemStr.split('x')[1], 10);
          const remaining  = currentQty - removeQty;
          if (remaining > 0) {
            newItems.push(name + ' x ' + remaining);
          }
        }
      } else if (checkbox) {
        if (!checkbox.checked) {
          newItems.push(checkbox.dataset.name);
        }
      }
    });

       input.value = newItems.join(' - ');
    updateSlotView(slot);
    deleteOverlay.style.display = 'none';
    queueInventorySave();

  });

//MODAL ORO
const oroInput = document.getElementById('input_oro');
const oroValor = document.getElementById('oro-valor');
const btnAddGold = document.querySelector('.add-gold');
const btnRemoveGold = document.querySelector('.remove-gold');
const modalAdd = document.getElementById('modal-add-gold');
const modalRemove = document.getElementById('modal-remove-gold');
const formAdd = document.getElementById('form-add-gold');
const formRemove = document.getElementById('form-remove-gold');
const closeButtons = document.querySelectorAll('.modal-contenido .close-popup');
const goldCapEl = document.getElementById('gold_cap');
const goldCap = parseInt(goldCapEl?.value || '500', 10) || 500;

function updateGoldDisplay() {
  let value = parseInt(oroInput.value || '0');
  if (!Number.isFinite(value)) value = 0;
  if (value > goldCap) {
    value = goldCap;
    oroInput.value = value;
  }
  oroValor.textContent = value + ' monedas';
  queueInventorySave();
}

if (btnAddGold && modalAdd) {
  btnAddGold.addEventListener('click', () => {
    modalAdd.style.display = 'flex';
  });
}

if (btnRemoveGold && modalRemove) {
  btnRemoveGold.addEventListener('click', () => {
    modalRemove.style.display = 'flex';
  });
}

formAdd?.addEventListener('submit', e => {
  e.preventDefault();
  const cantidad = parseInt(document.getElementById('gold-amount-add').value || '0', 10);
  if (cantidad > 0) {
    oroInput.value = Math.min(goldCap, parseInt(oroInput.value || '0', 10) + cantidad);
    updateGoldDisplay();
  }
  modalAdd.style.display = 'none';
  formAdd.reset();
});

formRemove?.addEventListener('submit', e => {
  e.preventDefault();
  const cantidad = parseInt(document.getElementById('gold-amount-remove').value || '0', 10);
  if (cantidad > 0) {
    oroInput.value = Math.max(0, parseInt(oroInput.value || '0', 10) - cantidad);
    updateGoldDisplay();
  }
  modalRemove.style.display = 'none';
  formRemove.reset();
});

closeButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay').style.display = 'none';
  });
});

// ----------------- MODULO DELERIUM -----------------
const contaminationInput = document.getElementById('delerium_contamination');
const contaminationIcons = document.querySelectorAll('.delerium-icon');
const deleriumInputs = document.querySelectorAll('.delerium-amount-input');
const deleriumSlotTotal = document.getElementById('delerium-slot-total');
const deleriumTextareas = [
  document.getElementById('delerium_mutations'),
  document.getElementById('delerium_madness'),
].filter(Boolean);
const deleriumAutosaveConfig = window.DELERIUM_AUTOSAVE || null;
let deleriumSaveTimer = null;

function syncContaminationIcons(value) {
  const level = Math.max(0, Math.min(6, Number(value) || 0));
  contaminationIcons.forEach(icon => {
    const index = Number(icon.dataset.index || 0);
    if (index <= level) {
      icon.classList.add('active');
    } else {
      icon.classList.remove('active');
    }
  });
}
contaminationInput?.addEventListener('input', (event) => {
  syncContaminationIcons(event.target.value);
  queueDeleriumSave();
});
if (contaminationInput) {
  syncContaminationIcons(contaminationInput.value);
}

function calculateDeleriumSlots() {
  if (!deleriumSlotTotal) return;
  const values = {
    chips: Number(document.getElementById('delerium_chips')?.value || 0),
    fragments: Number(document.getElementById('delerium_fragments')?.value || 0),
    shards: Number(document.getElementById('delerium_shards')?.value || 0),
    crystals: Number(document.getElementById('delerium_crystals')?.value || 0),
    geodas: Number(document.getElementById('delerium_geodas')?.value || 0),
  };
  let total = Math.ceil(values.chips / 4);
  total += Math.ceil(values.fragments / 2);
  total += values.shards;
  total += values.crystals * 2;
  total += values.geodas * 20;
  deleriumSlotTotal.textContent = total;
}
deleriumInputs.forEach(input => {
  input.addEventListener('input', () => {
    calculateDeleriumSlots();
    queueDeleriumSave();
  });
});
deleriumTextareas.forEach(area => {
  area.addEventListener('input', queueDeleriumSave);
});
calculateDeleriumSlots();

function queueDeleriumSave() {
  if (!deleriumAutosaveConfig?.ajaxUrl || !deleriumAutosaveConfig?.postId || !deleriumAutosaveConfig?.nonce) {
    return;
  }
  if (deleriumSaveTimer) {
    clearTimeout(deleriumSaveTimer);
  }
  deleriumSaveTimer = setTimeout(saveDeleriumModule, 700);
}

function saveDeleriumModule() {
  const payload = new FormData();
  payload.append('action', 'drak_save_delerium_module');
  payload.append('post_id', deleriumAutosaveConfig.postId);
  payload.append('nonce', deleriumAutosaveConfig.nonce);
  payload.append('delerium_contamination_level', contaminationInput?.value || 0);
  payload.append('delerium_chips', document.getElementById('delerium_chips')?.value || 0);
  payload.append('delerium_fragments', document.getElementById('delerium_fragments')?.value || 0);
  payload.append('delerium_shards', document.getElementById('delerium_shards')?.value || 0);
  payload.append('delerium_crystals', document.getElementById('delerium_crystals')?.value || 0);
  payload.append('delerium_geodas', document.getElementById('delerium_geodas')?.value || 0);
  payload.append('delerium_mutations', document.getElementById('delerium_mutations')?.value || '');
  payload.append('delerium_madness', document.getElementById('delerium_madness')?.value || '');

  fetch(deleriumAutosaveConfig.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: payload,
  }).catch(() => {
    // without UI feedback; manual guard remains available
  });
}

updateGoldDisplay();

});
</script>




<?php
});

function drak_register_campaign_cpt() {
    $labels = [
        'name'               => 'Campañas',
        'singular_name'      => 'Campaña',
        'add_new'            => 'Añadir nueva',
        'add_new_item'       => 'Añadir campaña',
        'edit_item'          => 'Editar campaña',
        'new_item'           => 'Nueva campaña',
        'view_item'          => 'Ver campaña',
        'search_items'       => 'Buscar campañas',
        'not_found'          => 'No se encontraron campañas',
        'not_found_in_trash' => 'No hay campañas en la papelera',
        'menu_name'          => 'Campañas',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => ['slug' => 'campaign'],
        'show_in_rest'       => true,
        'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_icon'          => 'dashicons-shield-alt',
        'menu_position'      => 6,
        'capability_type'    => 'post',
        'publicly_queryable' => true,
    ];

    register_post_type( 'campaign', $args );
}
add_action( 'init', 'drak_register_campaign_cpt' );

/**
 * CPT wiki: npc, lugar, faccion (y opcional lore-entry).
 */
function drak_register_wiki_cpts() {
    $shared_supports = [ 'title', 'editor', 'thumbnail', 'excerpt' ];
    $defs = [
        'npc' => [
            'labels' => [
                'name'          => 'NPCs',
                'singular_name' => 'NPC',
                'menu_name'     => 'NPCs',
            ],
            'slug' => 'npc',
        ],
        'lugar' => [
            'labels' => [
                'name'          => 'Lugares',
                'singular_name' => 'Lugar',
                'menu_name'     => 'Lugares',
            ],
            'slug' => 'lugar',
        ],
        'faccion' => [
            'labels' => [
                'name'          => 'Facciones',
                'singular_name' => 'Facción',
                'menu_name'     => 'Facciones',
            ],
            'slug' => 'faccion',
        ],
        'lore-entry' => [
            'labels' => [
                'name'          => 'Entradas de lore',
                'singular_name' => 'Entrada de lore',
                'menu_name'     => 'Lore',
            ],
            'slug' => 'lore-entry',
        ],
        'personaje_wiki' => [
            'labels' => [
                'name'          => 'Personajes (Wiki)',
                'singular_name' => 'Personaje (Wiki)',
                'menu_name'     => 'Personajes (Wiki)',
            ],
            'slug' => 'personaje-wiki',
        ],
        'personaje_wiki_entry' => [
            'labels' => [
                'name'          => 'Entradas de Personaje-Wiki',
                'singular_name' => 'Entrada de Personaje-Wiki',
                'menu_name'     => 'Entradas Personaje-Wiki',
            ],
            'slug' => 'personaje-wiki-entry',
        ],
        'homebrew_entry' => [
            'labels' => [
                'name'          => 'Homebrew',
                'singular_name' => 'Entrada Homebrew',
                'menu_name'     => 'Homebrew',
            ],
            'slug' => 'homebrew-entry',
        ],
        'diario' => [
            'labels' => [
                'name'          => 'Diarios',
                'singular_name' => 'Diario',
                'menu_name'     => 'Diarios',
            ],
            'slug' => 'diario',
        ],
    ];

    foreach ( $defs as $type => $info ) {
        $args = [
            'labels'             => $info['labels'],
            'public'             => true,
            'show_ui'            => true,
            'has_archive'        => true,
            'rewrite'            => [ 'slug' => $info['slug'] ],
            'supports'           => $shared_supports,
            'show_in_rest'       => true,
            'menu_position'      => 7,
            'capability_type'    => 'post',
            'publicly_queryable' => true,
        ];
        if ( $type === 'homebrew_entry' ) {
            $args['capability_type']    = [ 'homebrew_entry', 'homebrew_entries' ];
            $args['map_meta_cap']       = true;
            $args['capabilities']       = drak_get_homebrew_capabilities();
            $args['has_archive']        = false;
            $args['public']             = true;
            $args['publicly_queryable'] = true;
        }
        register_post_type( $type, $args );
    }
}
add_action( 'init', 'drak_register_wiki_cpts' );

/**
 * Guarda una entrada de Personaje-Wiki desde el front.
 */
function drak_handle_personaje_wiki_note() {
    if ( ! isset( $_POST['pw_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pw_note_nonce'] ) ), 'pw_save_note' ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para añadir notas.' );
    }

    $parent_id = isset( $_POST['pw_id'] ) ? absint( $_POST['pw_id'] ) : 0;
    $section   = isset( $_POST['pw_section'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_section'] ) ) : '';
    $content   = isset( $_POST['pw_content'] ) ? wp_kses_post( wp_unslash( $_POST['pw_content'] ) ) : '';
    $title     = isset( $_POST['pw_title'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_title'] ) ) : '';
    $campaign  = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;

    if ( ! $parent_id || ! in_array( $section, [ 'origen', 'aventura' ], true ) || empty( $content ) ) {
        wp_die( 'Datos incompletos.' );
    }

    $entry_id = wp_insert_post( [
        'post_type'    => 'personaje_wiki_entry',
        'post_status'  => 'publish',
        'post_title'   => $title ? $title : sprintf( 'Nota %s', current_time( 'Y-m-d H:i' ) ),
        'post_content' => $content,
        'post_author'  => get_current_user_id(),
    ] );

    if ( is_wp_error( $entry_id ) ) {
        wp_die( 'No se pudo guardar la nota.' );
    }

    update_field( 'parent_personaje_wiki', $parent_id, $entry_id );
    update_field( 'section', $section, $entry_id );
    if ( $campaign ) {
        update_post_meta( $entry_id, 'pw_entry_campaign', $campaign );
    }

    $redirect = get_permalink( $parent_id );
    $redirect = add_query_arg( 'pw_note_saved', 1, $redirect );
    $redirect .= '#' . sanitize_key( $section );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'template_redirect', 'drak_handle_personaje_wiki_note' );

function drak_pw_render_entry_card( $post_id ) {
    $title   = get_the_title( $post_id );
    $date    = get_the_date( '', $post_id );
    $excerpt = get_the_excerpt( $post_id );
    ob_start();
    ?>
    <article class="pw-entry">
        <h4><?php echo esc_html( $title ); ?></h4>
        <small><?php echo esc_html( $date ); ?></small>
        <div><?php echo esc_html( $excerpt ); ?></div>
        <a class="pw-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">Leer más</a>
    </article>
    <?php
    return ob_get_clean();
}

function drak_pw_ajax_add_entry() {
    if ( ! isset( $_POST['pw_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pw_note_nonce'] ) ), 'pw_save_note' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ] );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Debes iniciar sesión.' ] );
    }

    $parent_id = isset( $_POST['pw_id'] ) ? absint( $_POST['pw_id'] ) : 0;
    $section   = isset( $_POST['pw_section'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_section'] ) ) : '';
    $content   = isset( $_POST['pw_content'] ) ? wp_kses_post( wp_unslash( $_POST['pw_content'] ) ) : '';
    $title     = isset( $_POST['pw_title'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_title'] ) ) : '';

    if ( ! $parent_id || ! in_array( $section, [ 'origen', 'aventura' ], true ) || empty( $content ) ) {
        wp_send_json_error( [ 'message' => 'Datos incompletos.' ] );
    }

    $entry_id = wp_insert_post( [
        'post_type'    => 'personaje_wiki_entry',
        'post_status'  => 'publish',
        'post_title'   => $title ? $title : sprintf( 'Nota %s', current_time( 'Y-m-d H:i' ) ),
        'post_content' => $content,
        'post_author'  => get_current_user_id(),
    ] );

    if ( is_wp_error( $entry_id ) ) {
        wp_send_json_error( [ 'message' => 'No se pudo guardar.' ] );
    }

    update_field( 'parent_personaje_wiki', $parent_id, $entry_id );
    update_field( 'section', $section, $entry_id );

    $html = drak_pw_render_entry_card( $entry_id );
    wp_send_json_success( [ 'html' => $html ] );
}
add_action( 'wp_ajax_drak_pw_add_entry', 'drak_pw_ajax_add_entry' );
add_action( 'wp_ajax_nopriv_drak_pw_add_entry', 'drak_pw_ajax_add_entry' );

function drak_handle_personaje_wiki_entry_form() {
    if ( ! isset( $_POST['pw_entry_action'], $_POST['pw_entry_nonce'] ) ) {
        return;
    }

    if ( ! is_singular( 'personaje_wiki_entry' ) ) {
        return;
    }

    $action = sanitize_key( wp_unslash( $_POST['pw_entry_action'] ) );
    if ( ! in_array( $action, [ 'update', 'delete' ], true ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pw_entry_nonce'] ) ), 'pw_entry_update' ) ) {
        wp_die( 'Nonce inválido.' );
    }

    $entry_id = isset( $_POST['pw_entry_id'] ) ? absint( $_POST['pw_entry_id'] ) : 0;
    if ( ! $entry_id || get_post_type( $entry_id ) !== 'personaje_wiki_entry' ) {
        wp_die( 'Entrada no válida.' );
    }

    if ( ! drak_user_can_edit_personaje_wiki_entry( $entry_id ) ) {
        wp_die( 'No tienes permiso para modificar esta nota.' );
    }

    $parent_id = (int) get_field( 'parent_personaje_wiki', $entry_id );
    $section   = isset( $_POST['pw_entry_section'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_entry_section'] ) ) : '';
    if ( ! in_array( $section, [ 'origen', 'aventura' ], true ) ) {
        $section = (string) get_field( 'section', $entry_id );
    }

    if ( 'delete' === $action ) {
        wp_trash_post( $entry_id );
        $redirect = $parent_id ? get_permalink( $parent_id ) : home_url( '/' );
        if ( $section ) {
            $redirect = add_query_arg( 'pw_tab', $section, $redirect ) . '#' . $section;
        }
        $redirect = add_query_arg( 'pw_entry_deleted', 1, $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    $title   = isset( $_POST['pw_entry_title'] ) ? sanitize_text_field( wp_unslash( $_POST['pw_entry_title'] ) ) : '';
    $content = isset( $_POST['pw_entry_content'] ) ? wp_kses_post( wp_unslash( $_POST['pw_entry_content'] ) ) : '';

    if ( empty( $content ) ) {
        wp_die( 'El contenido no puede estar vacío.' );
    }

    wp_update_post( [
        'ID'           => $entry_id,
        'post_title'   => $title ? $title : get_the_title( $entry_id ),
        'post_content' => $content,
    ] );

    if ( $section ) {
        update_field( 'section', $section, $entry_id );
    }

    $redirect = get_permalink( $entry_id );
    $redirect = add_query_arg( 'pw_entry_updated', 1, $redirect );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'template_redirect', 'drak_handle_personaje_wiki_entry_form', 5 );

function drak_homebrew_user_can_manage() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    if ( current_user_can( 'manage_options' ) || drak_current_user_is_dm() ) {
        return true;
    }
    return false;
}

function drak_homebrew_sections() {
    return [
        'reglas'    => 'Reglas',
        'monstruos' => 'Manual de Monstruos',
        'forja'     => 'Forja',
        'tienda'    => 'Tienda',
    ];
}

function drak_homebrew_render_entry_card( $post_id ) {
    $title   = get_the_title( $post_id );
    $date    = get_the_date( '', $post_id );
    $excerpt = get_the_excerpt( $post_id );
    $can_manage = drak_homebrew_user_can_manage() && current_user_can( 'edit_post', $post_id );
    $edit_link  = $can_manage ? get_edit_post_link( $post_id ) : '';
    $delete_link = $can_manage ? get_delete_post_link( $post_id, '', true ) : '';
    ob_start();
    ?>
    <article class="hb-entry">
        <h4><?php echo esc_html( $title ); ?></h4>
        <small><?php echo esc_html( $date ); ?></small>
        <div><?php echo esc_html( $excerpt ); ?></div>
        <a class="hb-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">Leer más</a>
        <?php if ( $can_manage ) : ?>
            <div class="hb-entry__actions">
                <?php if ( $edit_link ) : ?>
                    <a class="hb-btn hb-btn--ghost" href="<?php echo esc_url( $edit_link ); ?>">Editar</a>
                <?php endif; ?>
                <?php if ( $delete_link ) : ?>
                    <a class="hb-btn hb-btn--danger" href="<?php echo esc_url( $delete_link ); ?>" onclick="return confirm('¿Seguro que quieres enviar esta entrada a la papelera?');">Borrar</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}

function drak_handle_homebrew_note() {
    if ( ! isset( $_POST['hb_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hb_note_nonce'] ) ), 'hb_save_note' ) ) {
        return;
    }
    if ( ! drak_homebrew_user_can_manage() ) {
        wp_die( 'No tienes permiso para añadir notas de Homebrew.' );
    }

    $campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
    $section     = isset( $_POST['hb_section'] ) ? sanitize_text_field( wp_unslash( $_POST['hb_section'] ) ) : '';
    $content     = isset( $_POST['hb_content'] ) ? wp_kses_post( wp_unslash( $_POST['hb_content'] ) ) : '';
    $title       = isset( $_POST['hb_title'] ) ? sanitize_text_field( wp_unslash( $_POST['hb_title'] ) ) : '';

    $sections = drak_homebrew_sections();
    if ( ! $campaign_id || ! isset( $sections[ $section ] ) || empty( $content ) ) {
        wp_die( 'Datos incompletos para la nota de Homebrew.' );
    }

    $entry_id = wp_insert_post( [
        'post_type'    => 'homebrew_entry',
        'post_status'  => 'publish',
        'post_title'   => $title ? $title : sprintf( 'Entrada %s', current_time( 'Y-m-d H:i' ) ),
        'post_content' => $content,
        'post_author'  => get_current_user_id(),
    ] );

    if ( is_wp_error( $entry_id ) ) {
        wp_die( 'No se pudo guardar la entrada de Homebrew.' );
    }

    update_field( 'campaign', $campaign_id, $entry_id );
    update_field( 'homebrew_section', $section, $entry_id );

    $redirect = get_permalink( $campaign_id );
    $redirect = add_query_arg(
        [
            'campaign_section' => 'homebrew',
            'hb_note_saved'    => 1,
        ],
        $redirect
    );
    $redirect .= '#' . sanitize_key( $section );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'template_redirect', 'drak_handle_homebrew_note' );

function drak_homebrew_ajax_add_entry() {
    if ( ! isset( $_POST['hb_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hb_note_nonce'] ) ), 'hb_save_note' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ] );
    }
    if ( ! drak_homebrew_user_can_manage() ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }

    $campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
    $section     = isset( $_POST['hb_section'] ) ? sanitize_text_field( wp_unslash( $_POST['hb_section'] ) ) : '';
    $content     = isset( $_POST['hb_content'] ) ? wp_kses_post( wp_unslash( $_POST['hb_content'] ) ) : '';
    $title       = isset( $_POST['hb_title'] ) ? sanitize_text_field( wp_unslash( $_POST['hb_title'] ) ) : '';

    $sections = drak_homebrew_sections();
    if ( ! $campaign_id || ! isset( $sections[ $section ] ) || empty( $content ) ) {
        wp_send_json_error( [ 'message' => 'Datos incompletos.' ] );
    }

    $entry_id = wp_insert_post( [
        'post_type'    => 'homebrew_entry',
        'post_status'  => 'publish',
        'post_title'   => $title ? $title : sprintf( 'Entrada %s', current_time( 'Y-m-d H:i' ) ),
        'post_content' => $content,
        'post_author'  => get_current_user_id(),
    ] );

    if ( is_wp_error( $entry_id ) ) {
        wp_send_json_error( [ 'message' => 'No se pudo guardar.' ] );
    }

    update_field( 'campaign', $campaign_id, $entry_id );
    update_field( 'homebrew_section', $section, $entry_id );

    $html = drak_homebrew_render_entry_card( $entry_id );
    wp_send_json_success( [ 'html' => $html ] );
}
add_action( 'wp_ajax_drak_homebrew_add_entry', 'drak_homebrew_ajax_add_entry' );

/**
 * Redirige tras enviar a la papelera una entrada Homebrew al hub de campaña.
 */
function drak_homebrew_trash_redirect( $url, $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'homebrew_entry' ) {
        return $url;
    }

    $campaign_id = (int) get_field( 'campaign', $post_id );
    $section     = sanitize_key( get_field( 'homebrew_section', $post_id ) ?: '' );

    if ( ! $campaign_id ) {
        return home_url( '/campaign/' );
    }

    $base = trailingslashit( get_permalink( $campaign_id ) . 'homebrew' );
    if ( $section ) {
        $base = add_query_arg( 'hb_tab', $section, $base ) . '#' . $section;
    }

    return $base;
}
add_filter( 'wp_trash_post_redirect_url', 'drak_homebrew_trash_redirect', 10, 2 );

/**
 * Forza la redirección tras enviar a la papelera una entrada Homebrew, incluso desde el enlace frontal.
 */
function drak_homebrew_force_trash_redirect( $post_id ) {
    if ( wp_doing_ajax() ) {
        return;
    }
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'homebrew_entry' ) {
        return;
    }
    $campaign_id = (int) get_field( 'campaign', $post_id );
    $section     = sanitize_key( get_field( 'homebrew_section', $post_id ) ?: '' );
    if ( ! $campaign_id ) {
        return;
    }
    $base = trailingslashit( get_permalink( $campaign_id ) . 'homebrew' );
    if ( $section ) {
        $base = add_query_arg( 'hb_tab', $section, $base ) . '#' . $section;
    }
    wp_safe_redirect( $base );
    exit;
}
add_action( 'trashed_post', 'drak_homebrew_force_trash_redirect', 10, 1 );

/**
 * Si se cae en la URL ?deleted=1 de un homebrew_entry, redirige al hub de campaña.
 */
function drak_homebrew_deleted_redirect() {
    if ( ! isset( $_GET['deleted'] ) ) {
        return;
    }
    global $wp;
    $request = isset( $wp->request ) ? (string) $wp->request : '';
    if ( strpos( $request, 'homebrew-entry/' ) !== 0 ) {
        return;
    }
    $parts = explode( '/', trim( $request, '/' ) );
    $slug  = end( $parts );
    if ( ! $slug ) {
        return;
    }
    $post = get_page_by_path( $slug, OBJECT, 'homebrew_entry' );
    if ( ! $post ) {
        // Buscar en cualquier estado, incluyendo __trashed
        $candidates = [ $slug, $slug . '__trashed' ];
        $q          = new WP_Query( [
            'name'           => implode( ',', array_map( 'sanitize_title', $candidates ) ),
            'post_type'      => 'homebrew_entry',
            'post_status'    => [ 'any', 'trash', 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => 1,
        ] );
        if ( $q->have_posts() ) {
            $post = $q->posts[0];
        }
    }
    if ( ! $post ) {
        return;
    }
    $campaign_id = (int) get_field( 'campaign', $post->ID );
    $section     = sanitize_key( get_field( 'homebrew_section', $post->ID ) ?: '' );
    if ( ! $campaign_id ) {
        return;
    }
    $base = trailingslashit( get_permalink( $campaign_id ) . 'homebrew' );
    if ( $section ) {
        $base = add_query_arg( 'hb_tab', $section, $base ) . '#' . $section;
    }
    wp_safe_redirect( $base );
    exit;
}
add_action( 'template_redirect', 'drak_homebrew_deleted_redirect', 0 );
/**
 * Endpoints de secciones de campaña: /campaign/{slug}/(pj|diario|wiki|galeria)/
 */
function drak_register_campaign_section_rewrites() {
    $base = 'campaign';
    add_rewrite_rule(
        sprintf( '^%s/([^/]+)/(pj|diario|wiki|galeria|homebrew)/?$', $base ),
        'index.php?campaign=$matches[1]&campaign_section=$matches[2]',
        'top'
    );
}
add_action( 'init', 'drak_register_campaign_section_rewrites' );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'campaign_section';
    return $vars;
} );

function drak_register_personaje_cpt() {
    $labels = [
        'name'               => 'Personajes',
        'singular_name'      => 'Personaje',
        'add_new'            => 'Añadir nuevo',
        'add_new_item'       => 'Añadir nuevo personaje',
        'edit_item'          => 'Editar personaje',
        'new_item'           => 'Nuevo personaje',
        'view_item'          => 'Ver personaje',
        'search_items'       => 'Buscar personajes',
        'not_found'          => 'No se encontraron personajes',
        'not_found_in_trash' => 'No hay personajes en la papelera',
        'menu_name'          => 'Personajes',
    ];

    $args = [
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'personaje'],
        'show_in_rest'  => true,
        'supports'      => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
        'menu_icon'     => 'dashicons-groups',
        'menu_position' => 5,
        'capability_type' => 'post',
    ];

    register_post_type('personaje', $args);
}

function drak_get_full_caster_slots_table() {
    return [
        1 => [1 => 2],
        2 => [1 => 3],
        3 => [1 => 4, 2 => 2],
        4 => [1 => 4, 2 => 3],
        5 => [1 => 4, 2 => 3, 3 => 2],
        6 => [1 => 4, 2 => 3, 3 => 3],
        7 => [1 => 4, 2 => 3, 3 => 3, 4 => 1],
        8 => [1 => 4, 2 => 3, 3 => 3, 4 => 2],
        9 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 1],
        10 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
        11 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        12 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        13 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        14 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        15 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        16 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        17 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        18 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        19 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 1, 8 => 1, 9 => 1],
        20 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 2, 8 => 1, 9 => 1],
    ];
}

function drak_user_can_manage_personaje( $post_id ) {
    if ( current_user_can( 'edit_post', $post_id ) ) {
        return true;
    }

    $owner = get_field( 'jugador_asociado', $post_id );
    if ( $owner && intval( $owner ) === get_current_user_id() ) {
        return true;
    }

    return false;
}

/**
 * Checks if current user has DM role (capabilities are synced on init).
 */
function drak_current_user_is_dm() {
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) {
        return false;
    }

    return in_array( 'dm', (array) $user->roles, true );
}

/**
 * Determines if current user can view a personaje (read-only access for DM/admin/owner).
 */
function drak_user_can_view_personaje( $post_id ) {
    if ( drak_user_can_manage_personaje( $post_id ) ) {
        return true;
    }

    if ( current_user_can( 'view_all_personajes' ) || drak_current_user_is_dm() ) {
        return true;
    }

    return false;
}

function drak_get_personaje_wiki_owner_id( $pw_id ) {
    $pw_id = absint( $pw_id );
    if ( ! $pw_id ) {
        return 0;
    }

    $linked_personaje_id = (int) get_post_meta( $pw_id, 'linked_personaje_id', true );
    if ( $linked_personaje_id ) {
        $owner = (int) get_field( 'jugador_asociado', $linked_personaje_id );
        if ( $owner ) {
            return $owner;
        }
    }

    $direct_owner = (int) get_field( 'jugador_asociado', $pw_id );
    if ( $direct_owner ) {
        return $direct_owner;
    }

    return (int) get_post_field( 'post_author', $pw_id );
}

function drak_user_can_edit_personaje_wiki( $pw_id ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) || drak_current_user_is_dm() ) {
        return true;
    }

    if ( current_user_can( 'edit_post', $pw_id ) ) {
        return true;
    }

    $owner_id = drak_get_personaje_wiki_owner_id( $pw_id );
    return $owner_id > 0 && get_current_user_id() === $owner_id;
}

function drak_user_can_edit_personaje_wiki_entry( $entry_id ) {
    $entry_id = absint( $entry_id );
    if ( ! $entry_id || ! is_user_logged_in() ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) || drak_current_user_is_dm() ) {
        return true;
    }

    $post = get_post( $entry_id );
    if ( ! $post || $post->post_type !== 'personaje_wiki_entry' ) {
        return false;
    }

    if ( (int) $post->post_author === get_current_user_id() ) {
        return true;
    }

    if ( current_user_can( 'edit_post', $entry_id ) ) {
        return true;
    }

    $parent_id = (int) get_field( 'parent_personaje_wiki', $entry_id );
    if ( $parent_id ) {
        $owner_id = drak_get_personaje_wiki_owner_id( $parent_id );
        if ( $owner_id && $owner_id === get_current_user_id() ) {
            return true;
        }
    }

    return false;
}

/**
 * Determina si la petición actual quiere acceder a un personaje específico.
 *
 * @return int ID del personaje o 0 si no aplica.
 */
function drak_get_request_personaje_id() {
    if ( is_singular( 'personaje' ) ) {
        return get_queried_object_id();
    }

    if (
        is_page_template( 'page-hoja-personaje.php' )
        || is_page_template( 'page-inventario-personaje.php' )
        || is_page_template( 'page-grimorio-personaje.php' )
        || is_page_template( 'page-combate-personaje.php' )
    ) {
        $slug = get_query_var( 'personaje_slug' );
        if ( $slug ) {
            $personaje = get_page_by_path( $slug, OBJECT, 'personaje' );
            if ( $personaje ) {
                return (int) $personaje->ID;
            }
        }
    }

    return 0;
}

/**
 * Blindaje de acceso para cualquier vista pública de personajes.
 */
function drak_enforce_personaje_access_guard() {
    $personaje_id = drak_get_request_personaje_id();
    if ( ! $personaje_id ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        auth_redirect();
        exit;
    }

    if ( drak_user_can_view_personaje( $personaje_id ) ) {
        return;
    }

    wp_die(
        __( 'No tienes permiso para acceder a este personaje.', 'temahijo' ),
        __( 'Acceso restringido', 'temahijo' ),
        [ 'response' => 403 ]
    );
}
add_action( 'template_redirect', 'drak_enforce_personaje_access_guard', 0 );

function drak_enforce_homebrew_access_guard() {
  /*  if ( ! is_singular( 'homebrew_entry' ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        auth_redirect();
        exit;
    }

    if ( drak_homebrew_user_can_manage() ) {
        return;
    }

    wp_die(
        __( 'No tienes permiso para acceder a esta entrada de Homebrew.', 'temahijo' ),
        __( 'Acceso restringido', 'temahijo' ),
        [ 'response' => 403 ]
    );*/
}
add_action( 'template_redirect', 'drak_enforce_homebrew_access_guard', 0 );

/**
 * Evita que páginas protegidas se guarden en caché cuando contienen datos de personajes.
 */
function drak_disable_personaje_cache() {
    if ( ! drak_get_request_personaje_id() ) {
        return;
    }

    nocache_headers();
    header_remove( 'Expires' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
}
add_action( 'send_headers', 'drak_disable_personaje_cache' );

function drak_grimorio_decode_meta_array( $value ) {
    if ( is_array( $value ) ) {
        return $value;
    }
    if ( is_string( $value ) && $value !== '' ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return $decoded;
        }
    }
    return [];
}

function drak_grimorio_normalize_slots_array( $data ) {
    if ( ! is_array( $data ) ) {
        return [];
    }
    $clean = [];
    foreach ( $data as $level => $value ) {
        $level = intval( $level );
        if ( $level < 0 || $level > 9 ) {
            continue;
        }
        $clean[ $level ] = max( 0, intval( $value ) );
    }
    ksort( $clean );
    return $clean;
}

function drak_grimorio_normalize_spells_array( $data ) {
    if ( ! is_array( $data ) ) {
        return [];
    }
    $clean = [];
    foreach ( $data as $level => $list ) {
        $level = intval( $level );
        if ( $level < 0 || $level > 9 ) {
            continue;
        }
        $list      = is_array( $list ) ? $list : [];
        $sanitized = [];
        foreach ( $list as $spell_name ) {
            $spell_name = sanitize_text_field( (string) $spell_name );
            if ( '' === $spell_name ) {
                continue;
            }
            if ( ! in_array( $spell_name, $sanitized, true ) ) {
                $sanitized[] = $spell_name;
            }
        }
        if ( ! empty( $sanitized ) ) {
            $clean[ $level ] = $sanitized;
        }
    }
    ksort( $clean );
    return $clean;
}

function drak_grimorio_save_slots( $post_id, $data ) {
    $clean   = drak_grimorio_normalize_slots_array( $data );
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_field( 'grimorio_slots_used', $payload, $post_id );
    return $clean;
}

function drak_grimorio_save_prepared( $post_id, $data ) {
    $clean   = drak_grimorio_normalize_spells_array( $data );
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_field( 'grimorio_spells', $payload, $post_id );
    return $clean;
}

function drak_grimorio_get_slots( $post_id ) {
    $raw   = get_field( 'grimorio_slots_used', $post_id );
    $array = drak_grimorio_decode_meta_array( $raw );
    $clean = drak_grimorio_normalize_slots_array( $array );
    if ( is_array( $raw ) ) {
        drak_grimorio_save_slots( $post_id, $clean );
    }
    return $clean;
}

function drak_grimorio_get_prepared( $post_id ) {
    $raw   = get_field( 'grimorio_spells', $post_id );
    $array = drak_grimorio_decode_meta_array( $raw );
    $clean = drak_grimorio_normalize_spells_array( $array );
    if ( is_array( $raw ) ) {
        drak_grimorio_save_prepared( $post_id, $clean );
    }
    return $clean;
}

function drak_grimorio_get_transformation_state( $post_id ) {
    $state = get_post_meta( $post_id, 'grimorio_transformation_state', true );
    if ( ! is_array( $state ) ) {
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    $state['active']     = ! empty( $state['active'] );
    $state['slot_level'] = isset( $state['slot_level'] ) ? intval( $state['slot_level'] ) : null;
    $state['started_at'] = isset( $state['started_at'] ) ? intval( $state['started_at'] ) : null;

    if ( ! $state['active'] || ! $state['slot_level'] ) {
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    return $state;
}

function drak_grimorio_save_transformation_state( $post_id, $state ) {
    if ( empty( $state['active'] ) || empty( $state['slot_level'] ) ) {
        delete_post_meta( $post_id, 'grimorio_transformation_state' );
        return [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ];
    }

    $payload = [
        'active'     => true,
        'slot_level' => intval( $state['slot_level'] ),
        'started_at' => isset( $state['started_at'] ) ? intval( $state['started_at'] ) : time(),
    ];

    update_post_meta( $post_id, 'grimorio_transformation_state', $payload );

    return $payload;
}

function drak_get_class_detail_entry( $class_id ) {
    static $class_details = null;

    if ( $class_details === null ) {
        $class_details = [];
        $path          = drak_locate_theme_data_file( 'dnd-class-details.json' );

        if ( $path ) {
            $json = file_get_contents( $path );
            if ( $json !== false ) {
                $data = json_decode( $json, true );
                if ( isset( $data['classes'] ) && is_array( $data['classes'] ) ) {
                    $class_details = $data['classes'];
                }
            }
        }
    }

    return $class_details[ $class_id ] ?? null;
}

function drak_get_spellcasting_ability_for_class( $class_id ) {
    if ( ! $class_id ) {
        return null;
    }

    $entry = drak_get_class_detail_entry( $class_id );
    if ( ! $entry ) {
        return null;
    }

    $ability = $entry['spellcastingAbility'] ?? null;
    if ( ! is_string( $ability ) || $ability === '' ) {
        return null;
    }

    return strtolower( $ability );
}

function drak_set_class_reference_error( $message ) {
    $GLOBALS['drak_class_reference_error'] = $message;
    error_log( '[ClassRef] ' . $message );
}

function drak_get_class_reference_error() {
    return $GLOBALS['drak_class_reference_error'] ?? '';
}

function drak_load_5etools_class_file( $class_name ) {
    static $cache = [];

    if ( ! $class_name ) {
        return null;
    }

    $slug = sanitize_title( $class_name );
    if ( isset( $cache[ $slug ] ) ) {
        return $cache[ $slug ];
    }

    $candidates = [
        'class/class-' . $slug . '-es.json',
        'class-' . $slug . '-es.json',
        'class/class-' . $slug . '.json',
        'class-' . $slug . '.json',
    ];

    $path = '';
    foreach ( $candidates as $candidate ) {
        $found = drak_locate_theme_data_file( $candidate );
        if ( $found ) {
            $path = $found;
            break;
        }
    }

    if ( ! $path ) {
        $base = trailingslashit( get_stylesheet_directory() ) . '5etools-src-main/data/class/';
        foreach ( $candidates as $candidate ) {
            $alt = $base . basename( $candidate );
            if ( file_exists( $alt ) ) {
                $path = $alt;
                break;
            }
        }
    }

    if ( ! $path ) {
        drak_set_class_reference_error( sprintf( 'Archivo de clase no encontrado para "%s" (slug "%s").', $class_name, $slug ) );
        $cache[ $slug ] = null;
        return null;
    }

    $json = file_get_contents( $path );
    if ( $json === false ) {
        drak_set_class_reference_error( sprintf( 'No se pudo leer el archivo %s', $path ) );
        $cache[ $slug ] = null;
        return null;
    }

    $decoded = json_decode( $json, true );
    if ( ! is_array( $decoded ) ) {
        drak_set_class_reference_error( sprintf( 'JSON inválido en %s', $path ) );
        $cache[ $slug ] = null;
        return null;
    }

    $cache[ $slug ] = $decoded;
    return $cache[ $slug ];
}

function drak_find_class_json_entry( $class_id ) {
    if ( ! $class_id ) {
        return null;
    }

    $meta = drak_get_class_detail_entry( $class_id );
    if ( ! $meta ) {
        return null;
    }

    $file = drak_load_5etools_class_file( $meta['name'] ?? '' );
    if ( ! $file || empty( $file['class'] ) || ! is_array( $file['class'] ) ) {
        return null;
    }

    foreach ( $file['class'] as $entry ) {
        $entry_name   = strtolower( $entry['name'] ?? '' );
        $target_name  = strtolower( $meta['name'] ?? '' );
        $entry_source = $entry['source'] ?? '';
        $entry_edition = $entry['edition'] ?? 'classic';

        if ( $entry_name === $target_name
            && $entry_source === ( $meta['source'] ?? '' )
            && $entry_edition === ( $meta['edition'] ?? 'classic' ) ) {
            return [
                'meta'  => $meta,
                'entry' => $entry,
                'file'  => $file,
            ];
        }
    }

    if ( ! empty( $file['class'] ) ) {
        foreach ( $file['class'] as $entry ) {
            if ( strtolower( $entry['name'] ?? '' ) === strtolower( $meta['name'] ?? '' ) ) {
                return [
                    'meta'  => $meta,
                    'entry' => $entry,
                    'file'  => $file,
                ];
            }
        }

        drak_set_class_reference_error( sprintf( 'No hubo coincidencia exacta para la clase "%s" (%s %s). Se usará la primera entrada del JSON.', $meta['name'] ?? $class_id, $meta['source'] ?? 'sin fuente', $meta['edition'] ?? 'sin edición' ) );
        return [
            'meta'  => $meta,
            'entry' => $file['class'][0],
            'file'  => $file,
        ];
    }

    return null;
}

function drak_find_subclass_json_entry( $subclass_id ) {
    if ( ! $subclass_id ) {
        return null;
    }

    $lookup = drak_get_local_dnd_class_lookup();
    $record = $lookup['subclasses'][ $subclass_id ] ?? null;
    if ( ! $record ) {
        return null;
    }

    $class_id  = $record['class_id'] ?? '';
    $class_ref = drak_find_class_json_entry( $class_id );
    if ( ! $class_ref ) {
        return null;
    }

    $file        = $class_ref['file'];
    $sub_meta    = $record['data'] ?? [];
    $target_name = strtolower( $sub_meta['name'] ?? '' );
    $target_source = $sub_meta['source'] ?? '';
    $target_short = strtolower( $sub_meta['shortName'] ?? '' );
    $target_edition = $sub_meta['edition'] ?? ( $class_ref['meta']['edition'] ?? 'classic' );
    $target_class_name = strtolower( $sub_meta['className'] ?? ( $class_ref['meta']['name'] ?? '' ) );
    $target_class_source = $sub_meta['classSource'] ?? ( $class_ref['meta']['source'] ?? '' );

    foreach ( $file['subclass'] ?? [] as $entry ) {
        $entry_name  = strtolower( $entry['name'] ?? '' );
        $entry_short = strtolower( $entry['shortName'] ?? '' );
        $entry_source = $entry['source'] ?? '';
        $entry_class_name = strtolower( $entry['className'] ?? '' );
        $entry_class_source = $entry['classSource'] ?? '';
        $entry_edition = $entry['edition'] ?? ( $class_ref['meta']['edition'] ?? 'classic' );

        if (
            ( $entry_name === $target_name || $entry_short === $target_short )
            && $entry_source === $target_source
            && $entry_class_name === $target_class_name
            && $entry_class_source === $target_class_source
            && $entry_edition === $target_edition
        ) {
            return [
                'entry' => $entry,
                'meta'  => $sub_meta,
                'class' => $class_ref,
            ];
        }
    }

    foreach ( $file['subclass'] ?? [] as $entry ) {
        if ( strtolower( $entry['name'] ?? '' ) === $target_name ) {
            return [
                'entry' => $entry,
                'meta'  => $sub_meta,
                'class' => $class_ref,
            ];
        }
    }

    if ( ! empty( $file['subclass'] ) ) {
        drak_set_class_reference_error( sprintf( 'No hubo coincidencia exacta para la subclase "%s". Se usará la primera entrada disponible.', $sub_meta['name'] ?? $subclass_id ) );
        return [
            'entry' => $file['subclass'][0],
            'meta'  => $sub_meta,
            'class' => $class_ref,
        ];
    }

    return null;
}

function drak_strip_5e_markup( $text ) {
    if ( ! is_string( $text ) ) {
        return '';
    }

    $clean = preg_replace( '/\{@[^|}]+\|([^}|]+)(?:\|[^}]*)?\}/', '$1', $text );
    $clean = preg_replace( '/{[^}]+}/', '', $clean );

    return trim( $clean );
}

function drak_parse_spell_slot_label( $label ) {
    $plain = drak_strip_5e_markup( $label );
    if ( $plain === '' ) {
        $plain = (string) $label;
    }

    if ( preg_match( '/level=(\d+)/i', $label, $matches ) ) {
        return intval( $matches[1] );
    }

    if ( preg_match( '/(\d+)(?:st|nd|rd|th)/i', $plain, $matches ) ) {
        return intval( $matches[1] );
    }

    if ( preg_match( '/(\d+)/', $plain, $matches ) ) {
        return intval( $matches[1] );
    }

    return null;
}

function drak_extract_prepared_progression( $class_entry ) {
    if ( empty( $class_entry ) || ! is_array( $class_entry ) ) {
        return [];
    }

    if ( ! empty( $class_entry['preparedSpellsProgression'] ) && is_array( $class_entry['preparedSpellsProgression'] ) ) {
        $progression = [];
        foreach ( $class_entry['preparedSpellsProgression'] as $index => $value ) {
            $progression[ $index + 1 ] = intval( $value );
        }
        return $progression;
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = $group['colLabels'] ?? [];
        $rows   = $group['rows'] ?? [];

        if ( empty( $labels ) || empty( $rows ) ) {
            continue;
        }

        foreach ( $labels as $idx => $label ) {
            $plain = strtolower( drak_strip_5e_markup( $label ) );
            if ( false !== strpos( $plain, 'prepared spells' ) ) {
                $progression = [];
                foreach ( $rows as $row_index => $row_values ) {
                    $progression[ $row_index + 1 ] = intval( $row_values[ $idx ] ?? 0 );
                }
                return $progression;
            }
        }
    }

    return [];
}

function drak_extract_cantrip_progression( $class_entry ) {
    if ( empty( $class_entry['cantripProgression'] ) || ! is_array( $class_entry['cantripProgression'] ) ) {
        return [];
    }

    $progression = [];
    foreach ( $class_entry['cantripProgression'] as $index => $value ) {
        $progression[ $index + 1 ] = intval( $value );
    }

    return $progression;
}

function drak_extract_spell_slot_progression( $class_entry ) {
    if ( empty( $class_entry ) || ! is_array( $class_entry ) ) {
        return [];
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        if ( empty( $group['rowsSpellProgression'] ) || ! is_array( $group['rowsSpellProgression'] ) ) {
            continue;
        }

        $labels     = $group['colLabels'] ?? [];
        $slot_index = [];
        foreach ( $labels as $idx => $label ) {
            $slot_level = drak_parse_spell_slot_label( $label );
            if ( $slot_level !== null ) {
                $slot_index[ $idx ] = $slot_level;
            }
        }

        if ( empty( $slot_index ) ) {
            continue;
        }

        $progression = [];
        foreach ( $group['rowsSpellProgression'] as $row_idx => $row_values ) {
            $character_level = $row_idx + 1;
            foreach ( $slot_index as $col_idx => $slot_level ) {
                $progression[ $character_level ][ $slot_level ] = intval( $row_values[ $col_idx ] ?? 0 );
            }
        }

        if ( ! empty( $progression ) ) {
            return $progression;
        }
    }

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = $group['colLabels'] ?? [];
        $rows   = $group['rows'] ?? [];

        if ( empty( $labels ) || empty( $rows ) ) {
            continue;
        }

        $slots_idx = null;
        $level_idx = null;

        foreach ( $labels as $idx => $label ) {
            $plain = strtolower( drak_strip_5e_markup( $label ) );
            if ( $plain === 'spell slots' ) {
                $slots_idx = $idx;
            }
            if ( false !== strpos( $plain, 'slot level' ) ) {
                $level_idx = $idx;
            }
        }

        if ( $slots_idx === null || $level_idx === null ) {
            continue;
        }

        $progression = [];
        foreach ( $rows as $row_index => $row_values ) {
            $character_level = $row_index + 1;
            $slot_count      = intval( $row_values[ $slots_idx ] ?? 0 );
            $slot_level      = drak_parse_spell_slot_label( $row_values[ $level_idx ] ?? '' );

            if ( $slot_count > 0 && $slot_level ) {
                $progression[ $character_level ][ $slot_level ] = $slot_count;
            }
        }

        if ( ! empty( $progression ) ) {
            return $progression;
        }
    }

    return [];
}

function drak_parse_spell_token( $value ) {
    if ( is_array( $value ) ) {
        if ( isset( $value['choose'] ) ) {
            return null;
        }
        if ( isset( $value['item'] ) && is_array( $value['item'] ) ) {
            $value = reset( $value['item'] );
        } elseif ( isset( $value['entry'] ) ) {
            $value = $value['entry'];
        } else {
            $value = '';
        }
    }

    $raw = trim( (string) $value );
    if ( $raw === '' ) {
        return null;
    }

    $name   = $raw;
    $source = '';

    if ( preg_match( '/^\{@spell ([^}|]+)(?:\|([^}|]+))?(?:\|([^}]+))?\}$/i', $raw, $matches ) ) {
        $name   = $matches[1];
        $source = $matches[2] ?? '';
    } elseif ( strpos( $raw, '|' ) !== false ) {
        $parts  = explode( '|', $raw );
        $name   = $parts[0];
        $source = $parts[1] ?? '';
    }

    $name = drak_strip_5e_markup( $name );

    if ( $name === '' ) {
        return null;
    }

    return [
        'name'   => trim( $name ),
        'source' => strtoupper( trim( $source ) ),
        'raw'    => $raw,
    ];
}

function drak_collect_prepared_spells_from_additional( $additional, $max_level = null ) {
    if ( empty( $additional ) || ! is_array( $additional ) ) {
        return [];
    }

    $result = [];

    foreach ( $additional as $block ) {
        if ( empty( $block['prepared'] ) || ! is_array( $block['prepared'] ) ) {
            continue;
        }

        foreach ( $block['prepared'] as $level => $spells ) {
            $lvl = intval( $level );
            if ( $lvl <= 0 ) {
                continue;
            }
            if ( $max_level !== null && $lvl > $max_level ) {
                continue;
            }

            foreach ( (array) $spells as $entry ) {
                $spell = drak_parse_spell_token( $entry );
                if ( ! $spell ) {
                    continue;
                }
                $key = strtolower( $spell['name'] . '|' . $spell['source'] );
                if ( ! isset( $result[ $lvl ] ) ) {
                    $result[ $lvl ] = [];
                }
                if ( isset( $result[ $lvl ][ $key ] ) ) {
                    continue;
                }
                $result[ $lvl ][ $key ] = $spell;
            }
        }
    }

    foreach ( $result as $lvl => $spells ) {
        $result[ $lvl ] = array_values( $spells );
    }

    ksort( $result );

    return $result;
}

function drak_normalize_class_table_groups( $class_entry ) {
    $groups = [];

    foreach ( $class_entry['classTableGroups'] ?? [] as $group ) {
        $labels = array_map( 'drak_strip_5e_markup', $group['colLabels'] ?? [] );
        $rows   = [];
        $has_rows = false;

        if ( ! empty( $group['rows'] ) && is_array( $group['rows'] ) ) {
            foreach ( $group['rows'] as $row ) {
                $rows[] = array_map( 'drak_strip_5e_markup', $row );
            }
            $has_rows = true;
        } elseif ( ! empty( $group['rowsSpellProgression'] ) && is_array( $group['rowsSpellProgression'] ) ) {
            $labels = array_merge( [ 'Level' ], $labels );
            foreach ( $group['rowsSpellProgression'] as $idx => $row ) {
                $display = [ $idx + 1 ];
                foreach ( $row as $value ) {
                    $display[] = is_numeric( $value ) ? intval( $value ) : drak_strip_5e_markup( (string) $value );
                }
                $rows[] = $display;
            }
            $has_rows = true;
        }

        if ( ! $has_rows ) {
            continue;
        }

        $groups[] = [
            'title'    => $group['title'] ?? '',
            'subtitle' => $group['subtitle'] ?? '',
            'colLabels'=> $labels,
            'rows'     => $rows,
        ];
    }

    return $groups;
}

function drak_get_class_reference_map( $class_id ) {
    static $cache = [];

    if ( isset( $cache[ $class_id ] ) ) {
        return $cache[ $class_id ];
    }

    $class_ref = drak_find_class_json_entry( $class_id );
    if ( ! $class_ref ) {
        drak_set_class_reference_error( sprintf( 'No se encontró referencia para la clase "%s".', $class_id ) );
        $cache[ $class_id ] = null;
        return null;
    }

    $entry = $class_ref['entry'];

    $cache[ $class_id ] = [
        'meta'                => $class_ref['meta'],
        'table_groups'        => drak_normalize_class_table_groups( $entry ),
        'prepared_progression'=> drak_extract_prepared_progression( $entry ),
        'slot_progression'    => drak_extract_spell_slot_progression( $entry ),
        'cantrip_progression' => drak_extract_cantrip_progression( $entry ),
        'additional_prepared' => drak_collect_prepared_spells_from_additional( $entry['additionalSpells'] ?? [] ),
    ];

    return $cache[ $class_id ];
}

function drak_get_class_reference_payload( $class_id, $subclass_id = '', $apothecary_theories = [] ) {
    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return null;
    }

    $payload = [
        'class_id'            => $class_id,
        'class_meta'          => $class_data['meta'],
        'table_groups'        => $class_data['table_groups'],
        'prepared_progression'=> $class_data['prepared_progression'],
        'slot_progression'    => $class_data['slot_progression'],
        'class_prepared_spells'=> $class_data['additional_prepared'],
        'cantrip_progression' => $class_data['cantrip_progression'],
    ];

    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref ) {
            $payload['subclass'] = [
                'id'        => $subclass_id,
                'meta'      => $sub_ref['meta'],
                'prepared'  => drak_collect_prepared_spells_from_additional( $sub_ref['entry']['additionalSpells'] ?? [] ),
            ];
        }
    }

    if ( drak_is_apothecary_class( $class_id ) ) {
        $payload['esoteric_theories'] = drak_expand_apothecary_theories( $apothecary_theories );
    } else {
        $payload['esoteric_theories'] = [];
    }

    return $payload;
}

function drak_get_class_prepared_limit( $class_id, $level ) {
    if ( drak_is_apothecary_class( $class_id ) ) {
        return drak_get_apothecary_prepared_limit( $level );
    }

    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return 0;
    }

    $level = max( 1, min( 20, intval( $level ) ) );
    return intval( $class_data['prepared_progression'][ $level ] ?? 0 );
}

function drak_get_apothecary_prepared_limit( $level, $ability_scores = [] ) {
    $int_mod = 0;
    if ( isset( $ability_scores['int'] ) ) {
        $int_mod = floor( ( intval( $ability_scores['int'] ) - 10 ) / 2 );
    } else {
        $int_mod = drak_get_ability_modifier_from_request_or_meta( 'int' );
    }
    return max( 1, intval( $level ) + $int_mod );
}

function drak_get_class_spell_slots_for_level( $class_id, $level ) {
    if ( drak_is_apothecary_class( $class_id ) ) {
        // Slots tipo warlock/apotecario: manejados aparte, aquí devolvemos vacío para no renderizar matriz estándar.
        return [];
    }

    $class_data = drak_get_class_reference_map( $class_id );
    if ( ! $class_data ) {
        return [];
    }

    $level = max( 1, min( 20, intval( $level ) ) );
    return $class_data['slot_progression'][ $level ] ?? [];
}

function drak_filter_prepared_spell_list_by_level( $spell_map, $max_level ) {
    $result = [];
    foreach ( $spell_map as $lvl => $spells ) {
        if ( $max_level !== null && intval( $lvl ) > $max_level ) {
            continue;
        }
        $result[ intval( $lvl ) ] = $spells;
    }
    ksort( $result );
    return $result;
}

function drak_lookup_spell_reference( $name, $source = '' ) {
    static $index = null;

    if ( $index === null ) {
        $index = [];
        foreach ( drak_get_local_dnd_spells() as $spell ) {
            $spell_name   = strtolower( $spell['name'] ?? '' );
            $spell_source = strtoupper( $spell['source'] ?? '' );
            if ( $spell_name === '' ) {
                continue;
            }
            $index[ $spell_name . '|' . $spell_source ] = $spell;
            if ( $spell_source ) {
                $index[ $spell_name . '|' ] = $spell;
            }
        }
    }

    $key = strtolower( $name ) . '|' . strtoupper( $source );
    if ( isset( $index[ $key ] ) ) {
        return $index[ $key ];
    }

    $fallback = strtolower( $name ) . '|';
    return $index[ $fallback ] ?? null;
}

function drak_enrich_prepared_spell_map( $spell_map ) {
    $result = [];
    foreach ( $spell_map as $level => $spells ) {
        foreach ( $spells as $spell ) {
            $lookup = drak_lookup_spell_reference( $spell['name'], $spell['source'] );
            if ( $lookup ) {
                $spell['spell_level'] = intval( $lookup['level'] ?? 0 );
                $spell['spell_id']    = $lookup['id'] ?? '';
                $spell['source']      = $lookup['source'] ?? $spell['source'];
            } else {
                $spell['spell_level'] = 0;
                $spell['spell_id']    = '';
            }
            $result[ $level ][] = $spell;
        }
    }
    return $result;
}

function drak_get_auto_prepared_spells( $class_id, $subclass_id, $character_level, &$reference_payload = null, $options = [] ) {
    $payload = [
        'class'    => [],
        'subclass' => [],
    ];

    $class_data = drak_get_class_reference_map( $class_id );
    if ( $class_data && ! empty( $class_data['class_prepared_spells'] ) ) {
        $filtered = drak_filter_prepared_spell_list_by_level( $class_data['class_prepared_spells'], $character_level );
        $payload['class'] = drak_enrich_prepared_spell_map( $filtered );
    }

    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref ) {
            $spell_map = drak_collect_prepared_spells_from_additional( $sub_ref['entry']['additionalSpells'] ?? [] );
            $filtered  = drak_filter_prepared_spell_list_by_level( $spell_map, $character_level );
            $payload['subclass'] = drak_enrich_prepared_spell_map( $filtered );
        }
    }

    if ( func_num_args() >= 4 ) {
        $selected_theories = [];
        if ( is_array( $options ) && isset( $options['apothecary_theories'] ) ) {
            $selected_theories = $options['apothecary_theories'];
        }
        $reference_payload = drak_get_class_reference_payload( $class_id, $subclass_id, $selected_theories );
    }

    return $payload;
}

function drak_format_signed_number( $value ) {
    if ( $value === null || $value === '' ) {
        return '—';
    }
    $value = intval( $value );
    return $value > 0 ? '+' . $value : (string) $value;
}

function drak_spellcasting_log( $message ) {
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit( $upload_dir['basedir'] );
    $log_file   = $log_dir . 'spellcasting-debug.log';

    if ( ! file_exists( $log_file ) ) {
        @file_put_contents( $log_file, "=== Spellcasting Debug Log ===\n" );
    }

    $timestamp = date_i18n( 'Y-m-d H:i:s' );
    @file_put_contents( $log_file, '[' . $timestamp . '] ' . $message . "\n", FILE_APPEND );
}

function drak_get_ability_field_map() {
    return [
        'str' => ['field' => 'cs_fuerza',       'label' => 'Fuerza',       'short' => 'FUE'],
        'dex' => ['field' => 'cs_destreza',     'label' => 'Destreza',     'short' => 'DES'],
        'con' => ['field' => 'cs_constitucion', 'label' => 'Constitución', 'short' => 'CON'],
        'int' => ['field' => 'cs_inteligencia', 'label' => 'Inteligencia', 'short' => 'INT'],
        'wis' => ['field' => 'cs_sabiduria',    'label' => 'Sabiduría',    'short' => 'SAB'],
        'cha' => ['field' => 'cs_carisma',      'label' => 'Carisma',      'short' => 'CAR'],
    ];
}

function drak_get_ability_modifier_from_request_or_meta( $ability_key ) {
    $ability_key = strtolower( $ability_key );
    $map         = drak_get_ability_field_map();
    if ( ! isset( $map[ $ability_key ] ) ) {
        return 0;
    }

    // Si viene en la petición (por ejemplo, construyendo datos en vivo).
    if ( isset( $_POST[ $map[ $ability_key ]['field'] ] ) ) {
        $score = intval( $_POST[ $map[ $ability_key ]['field'] ] );
        return floor( ( $score - 10 ) / 2 );
    }

    // Intentar leer del meta del personaje si hay post_id.
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( $post_id && function_exists( 'get_field' ) ) {
        $raw = get_field( $map[ $ability_key ]['field'], $post_id );
        if ( $raw !== null && $raw !== '' ) {
            $score = intval( $raw );
            return floor( ( $score - 10 ) / 2 );
        }
    }

    return 0;
}

function drak_get_grimorio_spellcasting_stats( $post_id, $context = [] ) {
    $defaults = [
        'ability_key'      => null,
        'ability_label'    => '—',
        'ability_short'    => null,
        'ability_display'  => '—',
        'ability_modifier' => null,
        'spell_attack'     => '—',
        'spell_attack_value' => null,
        'spell_dc'         => '—',
    ];

    if ( ! $post_id || ! function_exists( 'get_field' ) ) {
        drak_spellcasting_log( '[Spellcasting] abort: invalid post or missing get_field' );
        return $defaults;
    }

    $class_id = $context['class_id'] ?? get_field( 'clase', $post_id );
    $ability_key = drak_get_spellcasting_ability_for_class( $class_id );
    if ( ! $ability_key ) {
        drak_spellcasting_log( '[Spellcasting] no spellcasting ability for post ' . $post_id . ' class=' . var_export( $class_id, true ) );
        return $defaults;
    }

    $ability_map = drak_get_ability_field_map();
    if ( ! isset( $ability_map[ $ability_key ] ) ) {
        return $defaults;
    }

    $score_field = $ability_map[ $ability_key ]['field'];
    if ( isset( $context['scores'][ $score_field ] ) ) {
        $score_value = $context['scores'][ $score_field ];
    } else {
        $score_raw   = get_field( $score_field, $post_id );
        $score_value = ( $score_raw === '' || $score_raw === null ) ? null : intval( $score_raw );
    }

    if ( isset( $context['prof_bonus'] ) ) {
        $prof_bonus = intval( $context['prof_bonus'] );
    } else {
        $prof_bonus = intval( get_field( 'cs_proeficiencia', $post_id ) );
    }

    $ability_mod = $score_value !== null ? floor( ( $score_value - 10 ) / 2 ) : null;

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] computed context post=%d class=%s ability=%s score=%s prof=%s mod=%s',
            $post_id,
            $class_id ?? 'n/a',
            $ability_key,
            var_export( $score_value, true ),
            var_export( $prof_bonus, true ),
            var_export( $ability_mod, true )
        )
    );

    $stats = [
        'ability_key'        => $ability_key,
        'ability_label'      => $ability_map[ $ability_key ]['label'],
        'ability_short'      => $ability_map[ $ability_key ]['short'],
        'ability_modifier'   => $ability_mod,
        'ability_display'    => $ability_map[ $ability_key ]['short'],
        'spell_attack'       => '—',
        'spell_attack_value' => null,
        'spell_dc'           => '—',
    ];

    if ( $ability_mod !== null ) {
        $attack_value = $prof_bonus + $ability_mod;
        $stats['ability_display'] = sprintf( '%s (%s)', $stats['ability_short'], drak_format_signed_number( $ability_mod ) );
        $stats['spell_attack']    = drak_format_signed_number( $attack_value );
        $stats['spell_attack_value'] = $attack_value;
        $stats['spell_dc']        = (string) ( 8 + $prof_bonus + $ability_mod );
    }

    return $stats;
}

function drak_update_spellcasting_fields( $post_id ) {
    if ( ! $post_id || ! function_exists( 'update_field' ) ) {
        return;
    }

    $ability_map = drak_get_ability_field_map();
    $context = [
        'class_id'   => isset( $_POST['clase'] ) ? drak_get_post_value( 'clase', get_field( 'clase', $post_id ) ) : get_field( 'clase', $post_id ),
        'scores'     => [],
        'prof_bonus' => isset( $_POST['cs_proeficiencia'] ) ? intval( drak_get_post_value( 'cs_proeficiencia', get_field( 'cs_proeficiencia', $post_id ) ) ) : intval( get_field( 'cs_proeficiencia', $post_id ) ),
    ];

    foreach ( $ability_map as $info ) {
        $field = $info['field'];
        if ( isset( $_POST[ $field ] ) ) {
            $context['scores'][ $field ] = intval( drak_get_post_value( $field, get_field( $field, $post_id ) ) );
        }
    }

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] save context post=%d class=%s scores=%s prof=%s',
            $post_id,
            var_export( $context['class_id'], true ),
            json_encode( $context['scores'] ),
            var_export( $context['prof_bonus'], true )
        )
    );

    $stats = drak_get_grimorio_spellcasting_stats( $post_id, $context );
    if ( ! $stats ) {
        drak_spellcasting_log( '[Spellcasting] stats not available for post ' . $post_id );
        return;
    }

    $ability_value = $stats['ability_short'] ?? '';
    $dc_value      = $stats['spell_dc'] !== '—' ? $stats['spell_dc'] : '';
    $attack_value  = $stats['spell_attack_value'] !== null ? $stats['spell_attack_value'] : '';

    drak_spellcasting_log(
        sprintf(
            '[Spellcasting] saving post=%d ability=%s dc=%s attack=%s',
            $post_id,
            var_export( $ability_value, true ),
            var_export( $dc_value, true ),
            var_export( $attack_value, true )
        )
    );

    update_field( 'spellcasting_hability', $ability_value, $post_id );
    update_field( 'spell_save_dc', $dc_value, $post_id );
    update_field( 'spell_attack_bonus', $attack_value, $post_id );
}

function renderizar_grimorio_personaje( $post_id ) {
    if ( ! $post_id ) {
        return '';
    }

    if ( isset( $_POST['grimorio_guardar'], $_POST['grimorio_nonce'] ) && wp_verify_nonce( $_POST['grimorio_nonce'], 'grimorio_guardar_' . $post_id ) && drak_user_can_manage_personaje( $post_id ) ) {
        $slots_posted = isset( $_POST['grimorio_slots_used'] ) ? $_POST['grimorio_slots_used'] : [];
        drak_grimorio_save_slots( $post_id, $slots_posted );

        $spells_posted = isset( $_POST['grimorio_spells'] ) ? $_POST['grimorio_spells'] : [];
        drak_grimorio_save_prepared( $post_id, $spells_posted );
    }

    $nivel      = intval( get_field( 'nivel', $post_id ) );
    $clase_id   = get_field( 'clase', $post_id );
    $slots_used = drak_grimorio_get_slots( $post_id );
    $prepared   = drak_grimorio_get_prepared( $post_id );
    $concentration = get_post_meta( $post_id, 'grimorio_concentration_state', true );
    $concentration = is_array( $concentration ) ? $concentration : [];
    $concentration_level = isset( $concentration['level'] ) ? intval( $concentration['level'] ) : null;
    $concentration_spell = isset( $concentration['spell'] ) ? (string) $concentration['spell'] : '';
    $concentration_spell_id = isset( $concentration['spell_id'] ) ? (string) $concentration['spell_id'] : '';
    $transformation_state = drak_grimorio_get_transformation_state( $post_id );

    $row = drak_get_class_spell_slots_for_level( $clase_id, $nivel );
    if ( empty( $row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $row      = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }
    ksort( $row );

    $subclass_id    = get_field( 'subclase', $post_id );
    $auto_prepared  = drak_get_auto_prepared_spells( $clase_id, $subclass_id, $nivel );
    $has_auto_prepared = false;
    foreach ( $auto_prepared as $group ) {
        foreach ( $group as $spells ) {
            if ( ! empty( $spells ) ) {
                $has_auto_prepared = true;
                break 2;
            }
        }
    }

    ob_start();
    $is_apothecary           = drak_is_apothecary_class( $clase_id );
    $apothecary_slots_state  = $is_apothecary ? drak_get_apothecary_slots_state( $post_id, $nivel ) : null;
    if ( $is_apothecary ) {
        // Asegura que no se usen slots estándar en el render.
        $row        = [];
        $slots_used = [];
    }
    ?>
    <div class="grimorio-formulario">
      <?php if ( drak_is_sorcerer_class( $clase_id ) ) : ?>
        <?php
          $sorcery_points_max     = isset( $sorcery_state['points_max'] ) ? intval( $sorcery_state['points_max'] ) : 0;
          $sorcery_points_current = isset( $sorcery_state['points_current'] ) ? intval( $sorcery_state['points_current'] ) : 0;
          $sorcery_flex_slots     = isset( $sorcery_state['flex_slots'] ) && is_array( $sorcery_state['flex_slots'] ) ? $sorcery_state['flex_slots'] : [];
          $sorcery_known          = isset( $sorcery_state['metamagic_known'] ) && is_array( $sorcery_state['metamagic_known'] ) ? $sorcery_state['metamagic_known'] : [];
        ?>
        <section class="grimorio-sorcery" id="grimorio-sorcery">
          <div class="grimorio-sorcery__head">
            <h3>Metamagia y Puntos de Hechicería</h3>
          </div>
          <div class="grimorio-sorcery__points-grid">
            <div class="grimorio-slot-column grimorio-slot-column--sorcery" data-sorcery="1" data-level="sp" data-max="<?php echo esc_attr( max( 0, $sorcery_points_max ) ); ?>">
              <header>
                <span>Puntos de Hechicería</span>
                <small><?php echo esc_html( max( 0, $sorcery_points_max ) ); ?> puntos</small>
              </header>
              <?php
                $spent_points = max( 0, $sorcery_points_max - $sorcery_points_current );
                $max_points   = max( 0, $sorcery_points_max );
              ?>
              <div class="grimorio-slot-checkboxes">
                <?php for ( $i = 1; $i <= $max_points; $i++ ) : ?>
                  <label>
                    <input type="checkbox" class="grimorio-slot-toggle" <?php checked( $i <= $spent_points ); ?>>
                    <span></span>
                  </label>
                <?php endfor; ?>
              </div>
            </div>
          </div>
          <div class="grimorio-sorcery__actions">
            <button type="button" id="grimorio-sorcery-convert">Convertir slots/puntos</button>
            <button type="button" id="grimorio-metamagic-manage-btn">Metamagia conocida</button>
          </div>
          <div class="grimorio-sorcery__metamagic" id="grimorio-sorcery-known">
            <?php if ( empty( $sorcery_known ) ) : ?>
              <p class="grimorio-sorcery__note">Aún no has añadido opciones de Metamagia.</p>
            <?php else : ?>
              <?php foreach ( $sorcery_known as $meta_id ) : ?>
                <span class="grimorio-sorcery__pill"><?php echo esc_html( $meta_id ); ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <p class="grimorio-sorcery__hint">
            Los puntos de hechicería se restauran al finalizar un descanso largo. Los espacios creados con Flexible Casting también se pierden al hacerlo.
          </p>
        </section>
      <?php endif; ?>

      <section class="grimorio-slot-grid">
        <h3>Espacios de conjuro</h3>
        <div class="grimorio-slots">
          <?php if ( $is_apothecary ) : ?>
            <?php
                $apothecary_slots_state = $apothecary_slots_state ?: drak_get_apothecary_slots_state( $post_id, $nivel );
                $ap_max   = isset( $apothecary_slots_state['max'] ) ? intval( $apothecary_slots_state['max'] ) : 0;
                $ap_curr  = isset( $apothecary_slots_state['current'] ) ? intval( $apothecary_slots_state['current'] ) : $ap_max;
                $ap_level = isset( $apothecary_slots_state['slot_level'] ) ? intval( $apothecary_slots_state['slot_level'] ) : 1;
            ?>
            <div class="grimorio-apothecary-slots">
              <div class="grimorio-apothecary-slots__row">
                <strong>Espacios de Apotecario:</strong>
                <span><?php echo esc_html( $ap_curr ); ?> / <?php echo esc_html( $ap_max ); ?></span>
              </div>
              <div class="grimorio-apothecary-slots__row">
                <strong>Nivel de los espacios:</strong>
                <span><?php echo esc_html( $ap_level ); ?></span>
              </div>
              <div class="grimorio-apothecary-slots__hint">Se recuperan al terminar un descanso corto o largo</div>
            </div>
          <?php else : ?>
          <div class="grimorio-slot-grid__inner">
          <?php foreach ( $row as $lvl => $max_slots ) :
              $max_slots = intval( $max_slots );
              if ( $max_slots <= 0 ) {
                  continue;
              }
              $used = isset( $slots_used[ $lvl ] ) ? intval( $slots_used[ $lvl ] ) : 0;
              ?>
              <div class="grimorio-slot-column" data-level="<?php echo esc_attr( $lvl ); ?>" data-max="<?php echo esc_attr( $max_slots ); ?>">
                <header>
                  <span>Nivel <?php echo esc_html( $lvl ); ?></span>
                  <small><?php echo esc_html( $max_slots ); ?> slots</small>
                </header>
                <div class="grimorio-slot-checkboxes">
                  <?php for ( $i = 1; $i <= $max_slots; $i++ ) :
                      $checked = $i <= $used ? 'checked' : '';
                      ?>
                      <label>
                        <input type="checkbox" class="grimorio-slot-toggle" <?php echo $checked; ?>>
                        <span></span>
                      </label>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="grimorio_slots_used[<?php echo esc_attr( $lvl ); ?>]" value="<?php echo esc_attr( $used ); ?>">
              </div>
          <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="grimorio-quick-actions">
        <button type="button" class="grimorio-reset-slots">Descanso largo</button>
        <button type="button" class="grimorio-reset-prepared">Reiniciar grimorio</button>
        <button type="button" class="grimorio-finish-concentration" <?php disabled( empty( $concentration_spell ) && empty( $concentration_spell_id ) ); ?>>
          Fin concentración
        </button>
      </section>
    </div>

    <?php
    $is_mutagenist_apothecary = ( 'apothecary-mutagenist-scgtd-drakkenheim' === $subclass_id );
    if ( $is_mutagenist_apothecary ) :
        ?>
        <section class="grimorio-transformation" id="grimorio-transformation">
          <div class="grimorio-transformation__head">
            <h3>Transformación temporal</h3>
            <p>Usa un espacio de conjuro para adoptar tu forma potenciada.</p>
          </div>
          <div class="grimorio-transformation__actions">
            <button type="button" class="grimorio-transform-start" id="grimorio-transform-start">Activar transformación</button>
            <button type="button" class="grimorio-transform-finish" id="grimorio-transform-finish" <?php disabled( empty( $transformation_state['active'] ) ); ?>>
              Finalizar transformación
            </button>
          </div>
          <div class="grimorio-transformation__body" id="grimorio-transformation-display"></div>
        </section>
    <?php endif; ?>

      <?php if ( drak_is_apothecary_class( $clase_id ) ) : ?>
        <section class="grimorio-prepared">
          <div class="grimorio-prepared__header">
            <h3>Conjuros de Apotecario</h3>
            <div class="grimorio-prepared__summary" id="grimorio-prepared-total"></div>
            <button type="button" class="grimorio-prepared__edit-btn">Editar conjuros</button>
          </div>

          <div class="grimorio-prepared__levels">
            <article class="grimorio-prepared-block grimorio-prepared-block--cantrips">
              <div class="grimorio-prepared-block__head">
                <div>
                  <span class="grimorio-prepared-block__label">Cantrips</span>
                </div>
                <span class="grimorio-prepared-block__counter" data-counter-for="0"></span>
                <button type="button" class="grimorio-prepared__edit-btn grimorio-cantrip-edit-btn" id="grimorio-cantrip-edit">Editar cantrips</button>
              </div>
              <ul class="grimorio-prepared-block__list">
                <li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay cantrips.</li>
              </ul>
            </article>

            <article class="grimorio-prepared-block grimorio-prepared-block--apothecary">
              <div class="grimorio-prepared-block__head">
                <div>
                  <span class="grimorio-prepared-block__label">Conjuros preparados</span>
                </div>
              </div>
              <ul class="grimorio-prepared-block__list"></ul>
            </article>

            <article class="grimorio-prepared-block grimorio-formulas-block">
              <div class="grimorio-prepared-block__head">
                <div>
                  <span class="grimorio-prepared-block__label">Fórmulas Mayores (6–9)</span>
                </div>
              </div>
              <ul class="grimorio-prepared-block__list"></ul>
            </article>
          </div>
        </section>
      <?php else : ?>
        <section class="grimorio-prepared">
          <div class="grimorio-prepared__header">
            <h3>Conjuros preparados</h3>
            <div class="grimorio-prepared__summary" id="grimorio-prepared-total"></div>
            <button type="button" class="grimorio-prepared__edit-btn">Editar conjuros</button>
          </div>

          <div class="grimorio-prepared__levels">
            <article class="grimorio-prepared-block grimorio-prepared-block--cantrips">
              <div class="grimorio-prepared-block__head">
                <div>
                  <span class="grimorio-prepared-block__label">Cantrips</span>
                </div>
                <span class="grimorio-prepared-block__counter" data-counter-for="0"></span>
                <button type="button" class="grimorio-prepared__edit-btn grimorio-cantrip-edit-btn" id="grimorio-cantrip-edit">Editar cantrips</button>
              </div>
              <ul class="grimorio-prepared-block__list" data-list-level="0">
                <li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay cantrips.</li>
              </ul>
            </article>

          <?php foreach ( $row as $lvl => $max_slots ) :
              if ( intval( $max_slots ) <= 0 ) {
                  continue;
              }
              $current = $prepared[ $lvl ] ?? [];
              ?>
              <article class="grimorio-prepared-block" data-level="<?php echo esc_attr( $lvl ); ?>" data-max="<?php echo esc_attr( $max_slots ); ?>">
                <div class="grimorio-prepared-block__head">
                  <div>
                    <span class="grimorio-prepared-block__label">Nivel <?php echo esc_html( $lvl ); ?></span>
                    <small><?php echo esc_html( $max_slots ); ?> huecos disponibles</small>
                  </div>
                  <span class="grimorio-prepared-block__counter" data-counter-for="<?php echo esc_attr( $lvl ); ?>">
                    <?php echo esc_html( count( array_filter( $current ) ) ); ?> / <?php echo esc_html( $max_slots ); ?>
                  </span>
                </div>
                <ul class="grimorio-prepared-block__list" data-list-level="<?php echo esc_attr( $lvl ); ?>">
                  <?php if ( empty( $current ) ) : ?>
                    <li class="grimorio-prepared-spell grimorio-prepared-spell--empty">Aún no hay conjuros preparados.</li>
                  <?php else : ?>
                    <?php foreach ( $current as $spell_name ) :
                        $spell_name = trim( (string) $spell_name );
                        if ( '' === $spell_name ) {
                            continue;
                        }
                        ?>
                        <?php
                          $is_concentration = ( null !== $concentration_level && intval( $concentration_level ) === intval( $lvl ) )
                            && ( $concentration_spell === $spell_name );
                        ?>
                        <li class="grimorio-prepared-spell <?php echo $is_concentration ? 'grimorio-prepared-spell--concentration' : ''; ?>"
                            data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                          <span class="grimorio-prepared-spell__name"><?php echo esc_html( $spell_name ); ?></span>
                          <button type="button"
                                  class="grimorio-cast-spell"
                                  data-level="<?php echo esc_attr( $lvl ); ?>"
                                  data-spell-id=""
                                  data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                            Lanzar spell
                          </button>
                        </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </ul>
              </article>
          <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    <div id="grimorio-spell-picker" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Seleccionar conjuros preparados</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">
            Marca los conjuros que quieres preparar en cada nivel. El límite total de conjuros preparados depende de tu clase y nivel.
          </p>
          <div id="grimorio-spell-picker-loading" class="grimorio-spell-picker__loading">
            Cargando lista de conjuros...
          </div>
          <div id="grimorio-spell-picker-levels" class="grimorio-spell-picker__levels" hidden></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-spell-picker-save">
            Guardar y cerrar
          </button>
        </footer>
      </div>
    </div>

    <div id="grimorio-sorcery-modal" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Flexible Casting</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body grimorio-sorcery-modal__body">
          <section class="grimorio-sorcery-modal__section">
            <h4>Convertir slot en puntos</h4>
            <p class="grimorio-spell-picker__hint">Ganas puntos iguales al nivel del slot gastado.</p>
            <select id="grimorio-sorcery-slot-select"></select>
            <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-sorcery-slot-confirm">Convertir</button>
          </section>
          <hr>
          <section class="grimorio-sorcery-modal__section">
            <h4>Crear slot con puntos</h4>
            <p class="grimorio-spell-picker__hint">Solo puedes crear slots de nivel 1 a 5.</p>
            <select id="grimorio-sorcery-create-select"></select>
            <div class="grimorio-sorcery-cost" id="grimorio-sorcery-create-hint"></div>
            <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-sorcery-create-confirm">Crear slot</button>
          </section>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Listo</button>
        </footer>
      </div>
    </div>

    <div id="grimorio-metamagic-manage-modal" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Metamagia conocida</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">Elige tus opciones de Metamagia según tu nivel.</p>
          <div id="grimorio-metamagic-options" class="grimorio-metamagic-options"></div>
          <div class="grimorio-metamagic-limit" id="grimorio-metamagic-limit"></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-metamagic-save">Guardar</button>
        </footer>
      </div>
    </div>

    <div id="grimorio-metamagic-cast" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Aplicar Metamagia</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">Selecciona una opción (Empowered puede combinarse con otra).</p>
          <div id="grimorio-metamagic-cast-options" class="grimorio-metamagic-options"></div>
          <div class="grimorio-metamagic-limit" id="grimorio-metamagic-cast-hint"></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-metamagic-apply">Lanzar con Metamagia</button>
        </footer>
      </div>
    </div>

    <div id="grimorio-info-modal" class="grimorio-modal grimorio-modal--small" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3 id="grimorio-info-title">Información</h3>
        </header>
        <div class="grimorio-modal__body" id="grimorio-info-content">
          <p>Información del conjuro.</p>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn grimorio-info-close" data-grimorio-close>Cerrar</button>
        </footer>
      </div>
    </div>

    <div id="grimorio-transformation-modal" class="grimorio-modal" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="grimorio-modal__dialog">
        <header class="grimorio-modal__header">
          <h3>Seleccionar nivel de slot</h3>
          <button type="button" class="grimorio-modal__close" data-grimorio-close>&times;</button>
        </header>
        <div class="grimorio-modal__body">
          <p class="grimorio-spell-picker__hint">Solo puedes transformar si tienes espacios de conjuro disponibles.</p>
          <div id="grimorio-transformation-levels" class="grimorio-transformation-levels"></div>
        </div>
        <footer class="grimorio-modal__footer">
          <button type="button" class="grimorio-modal__btn" data-grimorio-close>Cancelar</button>
          <button type="button" class="grimorio-modal__btn grimorio-modal__btn--primary" id="grimorio-transformation-confirm">
            Activar
          </button>
        </footer>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
function drak_render_grimorio_auto_prepared_section( $auto_prepared, $subclass_id ) {
    $subclass_label = '';
    if ( $subclass_id ) {
        $sub_ref = drak_find_subclass_json_entry( $subclass_id );
        if ( $sub_ref && ! empty( $sub_ref['meta']['name'] ) ) {
            $subclass_label = $sub_ref['meta']['name'];
        }
    }
    ob_start();
    ?>
  <section class="grimorio-auto-prepared">
      <div class="grimorio-auto-prepared__header">
        <h3>Conjuros siempre preparados</h3>
        <p>Estas opciones no consumen tus huecos de conjuros preparados.</p>
      </div>
      <?php
        $auto_groups = [
            'class'    => __( 'De tu clase', 'grimorio' ),
            'subclass' => $subclass_label ? $subclass_label : __( 'De tu subclase', 'grimorio' ),
        ];
        foreach ( $auto_groups as $group_key => $group_label ) :
            $group_spells = $auto_prepared[ $group_key ] ?? [];
            $group_has_spells = false;
            foreach ( $group_spells as $spell_list ) {
                if ( ! empty( $spell_list ) ) {
                    $group_has_spells = true;
                    break;
                }
            }
            if ( ! $group_has_spells ) {
                continue;
            }
        ?>
        <div class="grimorio-auto-prepared__group">
          <h4><?php echo esc_html( $group_label ); ?></h4>
          <?php foreach ( $group_spells as $unlock_level => $spells ) : ?>
            <?php if ( empty( $spells ) ) { continue; } ?>
            <div class="grimorio-auto-prepared__level">
              <span class="grimorio-auto-prepared__level-label">
                Disponible al nivel <?php echo esc_html( $unlock_level ); ?>
              </span>
              <ul class="grimorio-auto-prepared__list">
                <?php foreach ( $spells as $spell ) :
                    $spell_name   = $spell['name'] ?? '';
                    $spell_source = $spell['source'] ?? '';
                    $spell_level  = intval( $spell['spell_level'] ?? 0 );
                    $spell_id     = $spell['spell_id'] ?? '';
                    if ( '' === $spell_name ) {
                        continue;
                    }
                    ?>
                    <li class="grimorio-prepared-spell grimorio-prepared-spell--auto">
                      <div class="grimorio-prepared-spell__info">
                        <button type="button"
                                class="grimorio-prepared-spell__name"
                                data-spell-id="<?php echo esc_attr( $spell_id ); ?>"
                                data-spell-name="<?php echo esc_attr( $spell_name ); ?>"
                                data-spell-level="<?php echo esc_attr( $spell_level ); ?>">
                          <?php echo esc_html( $spell_name ); ?>
                        </button>
                        <?php if ( $spell_source ) : ?>
                          <small class="grimorio-prepared-spell__source"><?php echo esc_html( $spell_source ); ?></small>
                        <?php endif; ?>
                      </div>
                      <?php if ( $spell_level > 0 ) : ?>
                        <button type="button"
                                class="grimorio-cast-spell"
                                data-level="<?php echo esc_attr( $spell_level ); ?>"
                                data-spell-id="<?php echo esc_attr( $spell_id ); ?>"
                                data-spell-name="<?php echo esc_attr( $spell_name ); ?>">
                          Lanzar spell
                        </button>
                      <?php endif; ?>
                    </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function drak_render_spell_search_module() {
    if ( empty( drak_get_spellcasting_classes() ) ) {
        return '';
    }

    ob_start();
    ?>
    <section class="spell-search-module" data-spell-search>
      <div class="spell-search-module__intro">
        <h3>Buscador de conjuros</h3>
        <p>Encuentra rápidamente conjuros por nombre o cada catálogo de clase.</p>
      </div>
      <form class="spell-search__form" data-spell-search-form>
        <label class="spell-search__field">
          <span class="screen-reader-text">Nombre del conjuro</span>
          <input type="text" data-spell-search-input placeholder="Escribe el nombre del conjuro" autocomplete="off">
        </label>
        <button type="submit" class="spell-search__submit">Buscar</button>
      </form>
          <div class="spell-search__filters" data-spell-search-classes style="display:none;"></div>
          <div class="spell-search__suggestions" data-spell-search-suggestions hidden></div>

          <div class="grimorio-modal spell-search-modal" role="dialog" aria-modal="true" aria-hidden="true" data-spell-search-modal>
            <div class="grimorio-modal__dialog">
              <header class="grimorio-modal__header">
            <h3></h3>
                <button type="button" class="grimorio-modal__close" data-spell-search-close>&times;</button>
              </header>
              <div class="grimorio-modal__body" data-spell-search-results>
                <p>Aún no has realizado ninguna búsqueda.</p>
              </div>
          <footer class="grimorio-modal__footer">
            <button type="button" class="grimorio-modal__btn" data-spell-search-close>Cerrar</button>
          </footer>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key' => 'group_grimorio_personaje',
        'title' => 'Grimorio personaje',
        'fields' => [
            [
                'key' => 'field_grimorio_slots_used',
                'label' => 'Slots de conjuro usados',
                'name' => 'grimorio_slots_used',
                'type' => 'textarea',
                'instructions' => 'Almacén interno del módulo de grimorio (no editar manualmente).',
            ],
            [
                'key' => 'field_grimorio_spells',
                'label' => 'Conjuros preparados',
                'name' => 'grimorio_spells',
                'type' => 'textarea',
                'instructions' => 'Almacén interno del módulo de grimorio (no editar manualmente).',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'personaje',
                ],
            ],
        ],
        'position' => 'normal',
        'style'    => 'default',
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_campaign_metadata',
        'title'  => 'Campaña – Metadatos',
        'fields' => [
            [
                'key'          => 'field_campaign_short_title',
                'label'        => 'Título corto',
                'name'         => 'campaign_short_title',
                'type'         => 'text',
                'instructions' => 'Alias corto para menús o tarjetas.',
                'required'     => 0,
            ],
            [
                'key'           => 'field_campaign_system',
                'label'         => 'Sistema de juego',
                'name'          => 'campaign_system',
                'type'          => 'select',
                'choices'       => [
                    'D&D 5e'      => 'D&D 5e',
                    'Drakkenheim' => 'Drakkenheim',
                    'Otro'        => 'Otro',
                ],
                'allow_null'    => 1,
                'ui'            => 1,
                'return_format' => 'value',
                'placeholder'   => 'Selecciona o escribe el sistema',
            ],
            [
                'key'           => 'field_campaign_status',
                'label'         => 'Estado',
                'name'          => 'campaign_status',
                'type'          => 'select',
                'choices'       => [
                    'active'   => 'En curso',
                    'paused'   => 'En pausa',
                    'finished' => 'Terminada',
                ],
                'default_value' => 'active',
                'ui'            => 1,
            ],
            [
                'key'           => 'field_campaign_cover_image',
                'label'         => 'Imagen de portada',
                'name'          => 'campaign_cover_image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ],
            [
                'key'   => 'field_campaign_color',
                'label' => 'Color de campaña',
                'name'  => 'campaign_color',
                'type'  => 'color_picker',
            ],
            [
                'key'          => 'field_campaign_summary',
                'label'        => 'Descripción breve',
                'name'         => 'campaign_summary',
                'type'         => 'textarea',
                'instructions' => 'Pensada para usar en tarjetas del Hub.',
                'rows'         => 3,
                'new_lines'    => 'br',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'campaign',
                ],
            ],
        ],
        'position'                => 'normal',
        'style'                   => 'default',
        'label_placement'         => 'top',
        'instruction_placement'   => 'label',
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_campaign_assignment',
        'title'  => 'Asignación de campaña',
        'fields' => [
            [
                'key'           => 'field_campaign_relation',
                'label'         => 'Campaña',
                'name'          => 'campaign',
                'type'          => 'post_object',
                'post_type'     => [ 'campaign' ],
                'return_format' => 'id',
                'ui'            => 1,
                'multiple'      => 0,
                'required'      => 1,
                'allow_null'    => 0,
                'instructions'  => 'Selecciona a qué campaña pertenece este contenido.',
            ],
            [
                'key'           => 'field_campaign_visibility',
                'label'         => 'Visibilidad en campaña',
                'name'          => 'campaign_visibility',
                'type'          => 'select',
                'choices'       => [
                    'public'  => 'Visible para todos los jugadores',
                    'dm_only' => 'Solo visible para DM y Admin',
                ],
                'default_value' => 'public',
                'ui'            => 1,
                'allow_null'    => 0,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'personaje',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'post',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'npc',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'lugar',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'faccion',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'lore-entry',
                ],
            ],
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'personaje_wiki',
                ],
            ],
        ],
        'position'                => 'side',
        'style'                   => 'default',
        'label_placement'         => 'top',
        'instruction_placement'   => 'label',
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_personaje_wiki_meta',
        'title'  => 'Personaje-Wiki – Metadatos',
        'fields' => [
            [
                'key'           => 'field_pw_hero_image',
                'label'         => 'Imagen principal (hero)',
                'name'          => 'hero_image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'medium',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'personaje_wiki',
                ],
            ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_personaje_wiki_entry',
        'title'  => 'Personaje-Wiki – Entradas',
        'fields' => [
            [
                'key'           => 'field_pw_parent_personaje_wiki',
                'label'         => 'Personaje-Wiki',
                'name'          => 'parent_personaje_wiki',
                'type'          => 'post_object',
                'post_type'     => [ 'personaje_wiki' ],
                'required'      => 1,
                'return_format' => 'id',
            ],
            [
                'key'           => 'field_pw_section',
                'label'         => 'Sección',
                'name'          => 'section',
                'type'          => 'select',
                'choices'       => [
                    'origen'   => 'Origen',
                    'aventura' => 'Aventura',
                ],
                'required'      => 1,
                'ui'            => 1,
                'default_value' => 'origen',
            ],
            [
                'key'           => 'field_pw_entry_images',
                'label'         => 'Imágenes',
                'name'          => 'entry_images',
                'type'          => 'gallery',
                'return_format' => 'id',
                'preview_size'  => 'medium',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'personaje_wiki_entry',
                ],
            ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_homebrew_entry',
        'title'  => 'Homebrew – Entradas',
        'fields' => [
            [
                'key'           => 'field_hb_campaign',
                'label'         => 'Campaña',
                'name'          => 'campaign',
                'type'          => 'post_object',
                'post_type'     => [ 'campaign' ],
                'required'      => 1,
                'return_format' => 'id',
            ],
            [
                'key'           => 'field_hb_section',
                'label'         => 'Sección',
                'name'          => 'homebrew_section',
                'type'          => 'select',
                'choices'       => [
                    'reglas'    => 'Reglas',
                    'monstruos' => 'Manual de Monstruos',
                    'forja'     => 'Forja',
                    'tienda'    => 'Tienda',
                ],
                'required'      => 1,
                'ui'            => 1,
                'default_value' => 'reglas',
            ],
            [
                'key'           => 'field_hb_entry_images',
                'label'         => 'Imágenes',
                'name'          => 'entry_images',
                'type'          => 'gallery',
                'return_format' => 'id',
                'preview_size'  => 'medium',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'homebrew_entry',
                ],
            ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_personaje_feats',
        'title'  => 'Gestión de feats',
        'fields' => [
            [
                'key'           => 'field_personaje_feats',
                'label'         => 'Feats seleccionados',
                'name'          => 'feats',
                'type'          => 'repeater',
                'layout'        => 'table',
                'button_label'  => 'Añadir feat',
                'instructions'  => 'Solo se admiten feats de la fuente XPHB.',
                'sub_fields'    => [
                    [
                        'key'      => 'field_personaje_feat_id',
                        'label'    => 'ID de feat',
                        'name'     => 'feat_id',
                        'type'     => 'text',
                        'required' => 1,
                    ],
                    [
                        'key'           => 'field_personaje_feat_source',
                        'label'         => 'Fuente',
                        'name'          => 'feat_source',
                        'type'          => 'text',
                        'default_value' => 'XPHB',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'personaje',
                ],
            ],
        ],
        'position' => 'normal',
        'style'    => 'default',
    ] );
} );
add_action('init', 'drak_register_personaje_cpt');

// Ocultar barra de administración para usuarios no administradores
add_action('after_setup_theme', function () {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});

if ( is_admin() ) {
    add_filter( 'acf/load_value/name=grimorio_slots_used', 'drak_grimorio_acf_admin_format_json' );
    add_filter( 'acf/load_value/name=grimorio_spells', 'drak_grimorio_acf_admin_format_json' );
}

function drak_grimorio_acf_admin_format_json( $value ) {
    if ( is_array( $value ) ) {
        return wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
    if ( is_string( $value ) && $value !== '' ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }
    }
    return $value;
}


// Redirigir /pj al login si no está logueado
function redirigir_pj_si_no_logueado() {
    if (is_page('pj') && !is_user_logged_in()) {
        auth_redirect();
    }
}
add_action('template_redirect', 'redirigir_pj_si_no_logueado');

/**
 * Obtiene la consulta de personajes asociados a un usuario.
 */
function drak_get_user_personajes_query( $user_id ) {
    $args = [
        'post_type'      => 'personaje',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'jugador_asociado',
            'value'   => $user_id,
            'compare' => '=',
        ]],
    ];

    return new WP_Query( $args );
}

/**
 * HTML reutilizable de bienvenida para las vistas de personajes del usuario.
 */
function drak_render_personajes_welcome( WP_User $user ) {
    $html  = '<div class="bienvenida-usuario">';
    $html .= 'Bienvenido, <strong>' . esc_html( $user->display_name ) . '</strong>';

    $buttons = [];
    if ( function_exists( 'drak_gallery_get_upload_page_url' ) ) {
        $upload_url = drak_gallery_get_upload_page_url();
        if ( $upload_url ) {
            $buttons[] = '<a class="boton-subir-galeria" href="' . esc_url( $upload_url ) . '">' . esc_html__( 'Subir imagen a la galería', 'temahijo' ) . '</a>';
        }
    }
    if ( ! empty( $buttons ) ) {
        $html .= '<div class="bienvenida-acciones">' . implode( '', $buttons ) . '</div>';
    }

    $html .= '</div>';
    return $html;
}

// Shortcode para mostrar personajes asociados a un usuario
function mostrar_personajes_del_usuario() {
    if (!is_user_logged_in()) {
        wp_login_form(['redirect' => home_url('/pj/')]);
        return '';
    }

    $usuario = wp_get_current_user();
    $query   = drak_get_user_personajes_query( $usuario->ID );

    ob_start();

    echo drak_render_personajes_welcome( $usuario );

    if ($query->have_posts()) {
        echo '<div class="lista-personajes">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="tarjeta-personaje">';
            if (has_post_thumbnail()) {
                echo '<div class="avatar-personaje">';
                echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail(get_the_ID(), 'medium') . '</a>';
                echo '</div>';
            }
            echo '<h3 class="nombre-personaje"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No tienes personajes asignados todavía.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('personajes_usuario', 'mostrar_personajes_del_usuario');


function drak_render_galeria_personajes_usuario() {
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url(home_url('/pj')));
        exit;
    }

    $usuario = wp_get_current_user();

    $query = drak_get_user_personajes_query( $usuario->ID );

    ob_start();

    echo drak_render_personajes_welcome( $usuario );

    if ($query->have_posts()) {
      echo '<div class="galeria-personajes">';
while ($query->have_posts()) {
    $query->the_post();
    $img_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
    $link = get_permalink(get_the_ID());  // Enlace a single-personaje.php

    echo '<a href="' . esc_url($link) . '" class="personaje-card">';
    echo '<div class="imagen-personaje" style="background-image: url(' . esc_url($img_url) . ')"></div>';
    echo '<div class="nombre-personaje">' . esc_html(get_the_title()) . '</div>';
    echo '</a>';
}
echo '</div>';

    } else {
        echo '<p class="sin-personajes">No tienes personajes asignados todavía.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('galeria_personajes_usuario', 'drak_render_galeria_personajes_usuario');



add_action('add_meta_boxes', function() {
    add_meta_box('postimagediv', __('Imagen destacada'), 'post_thumbnail_meta_box', 'personaje', 'side', 'low');
});

/**
 * Busca el primer slug de página existente para mantener reglas de reescritura flexibles.
 */
function drak_locate_existing_page_slug( array $candidates, $fallback = '' ) {
    foreach ( $candidates as $slug ) {
        if ( get_page_by_path( $slug ) ) {
            return $slug;
        }
    }

    if ( $fallback ) {
        return $fallback;
    }

    return $candidates[0] ?? '';
}

/**
 * Registra todas las reglas de URLs amigables relacionadas con personajes.
 */
function drak_register_personaje_rewrites() {
    $inventory_page = drak_locate_existing_page_slug( ['inventario-personaje', 'inventario'], 'inventario-personaje' );
    $sheet_page     = drak_locate_existing_page_slug( ['hoja-personaje'], 'hoja-personaje' );
    $grimorio_page  = drak_locate_existing_page_slug( ['grimorio'], 'grimorio' );
    $combate_page   = drak_locate_existing_page_slug( ['combate'], 'combate' );

    $rules = [
        '^personaje/([^/]+)/inventario/?' => 'index.php?personaje=$matches[1]&inventario_personaje=1',
        '^inventario/([^/]+)/?$'          => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $inventory_page ),
        '^hoja-personaje/([^/]+)/?$'      => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $sheet_page ),
        '^grimorio/([^/]+)/?$'            => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $grimorio_page ),
        '^combate/([^/]+)/?$'             => sprintf( 'index.php?pagename=%s&personaje_slug=$matches[1]', $combate_page ),
    ];

    foreach ( $rules as $regex => $query ) {
        add_rewrite_rule( $regex, $query, 'top' );
    }
}
add_action( 'init', 'drak_register_personaje_rewrites' );

/**
 * Registra las query vars personalizadas usadas por las reglas anteriores.
 */
function drak_register_personaje_query_vars( $vars ) {
    $vars[] = 'inventario_personaje';
    $vars[] = 'personaje_slug';

    return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'drak_register_personaje_query_vars' );

function drak_register_campaign_query_var( $vars ) {
    $vars[] = 'campaign';

    return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'drak_register_campaign_query_var' );

/**
 * Permite filtrar listados por campaña via ?campaign={id o slug}.
 */
function drak_filter_queries_by_campaign( WP_Query $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $campaign = $query->get( 'campaign' );
    if ( ! $campaign ) {
        return;
    }

    $campaign_id = is_numeric( $campaign ) ? absint( $campaign ) : 0;
    if ( ! $campaign_id ) {
        $campaign_post = get_page_by_path( $campaign, OBJECT, 'campaign' );
        if ( $campaign_post ) {
            $campaign_id = $campaign_post->ID;
        }
    }

    if ( ! $campaign_id ) {
        return;
    }

    $targeted_types = [ 'post', 'personaje', 'npc', 'lugar', 'faccion', 'lore-entry' ];
    $queried_type   = $query->get( 'post_type' );
    if ( ! $queried_type ) {
        $queried_types = $query->is_post_type_archive( 'personaje' ) ? [ 'personaje' ] : [ 'post' ];
    } else {
        $queried_types = (array) $queried_type;
    }

    if ( empty( array_intersect( $queried_types, $targeted_types ) ) ) {
        return;
    }

    $meta_query   = $query->get( 'meta_query' );
    $meta_query   = is_array( $meta_query ) ? $meta_query : [];
    $meta_query[] = [
        'key'     => 'campaign',
        'value'   => $campaign_id,
        'compare' => '=',
    ];

    $query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'drak_filter_queries_by_campaign' );

function drak_get_static_data_base() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $candidates = [ 'data', 'jsons' ];
    foreach ( $candidates as $folder ) {
        $dir = trailingslashit( get_stylesheet_directory() ) . $folder;
        $uri = trailingslashit( get_stylesheet_directory_uri() ) . $folder;
        if ( file_exists( $dir . '/dnd-races.json' ) ) {
            $cache = [
                'dir' => trailingslashit( $dir ),
                'uri' => trailingslashit( $uri ),
            ];
            return $cache;
        }
    }

    $cache = [
        'dir' => trailingslashit( get_stylesheet_directory() ),
        'uri' => trailingslashit( get_stylesheet_directory_uri() ),
    ];

    return $cache;
}

function drak_static_data_uri( $filename ) {
    $base      = drak_get_static_data_base();
    $file_path = $base['dir'] . $filename;
    if ( file_exists( $file_path ) ) {
        return $base['uri'] . $filename;
    }

    return '';
}

/**
 * Devuelve el estado de munición por arma para el combate.
 *
 * @param int $post_id
 * @return array<string,int>
 */
function drak_normalize_carcaj_value( $raw ) {
    if ( is_string( $raw ) && $raw !== '' ) {
        $decoded = json_decode( $raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $raw = $decoded;
        }
    }
    return is_array( $raw ) ? $raw : [];
}

function drak_get_combat_ammo_state( $post_id ) {
    $raw = get_post_meta( $post_id, 'combat_ammo_state', true );
    $has_meta = ! empty( $raw );
    if ( is_string( $raw ) ) {
        $decoded = json_decode( $raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $raw = $decoded;
        }
    }
    $clean = is_array( $raw ) ? drak_sanitize_combat_ammo_state( $raw ) : [];

    if ( function_exists( 'get_field' ) ) {
        $carcaj_raw = get_field( 'carcaj', $post_id );
        $carcaj = drak_normalize_carcaj_value( $carcaj_raw );
        if ( is_array( $carcaj ) ) {
            $type   = sanitize_key( $carcaj['type'] ?? '' );
            $amount = max( 0, intval( $carcaj['amount'] ?? 0 ) );
            if ( $type ) {
                $clean[ 'quiver-' . $type ] = $amount;
            }
        }
    }

    return $clean;
}

/**
 * Limpia el array de munición asegurando enteros no negativos.
 *
 * @param array $state
 * @return array<string,int>
 */
function drak_sanitize_combat_ammo_state( $state ) {
    $clean = [];
    foreach ( $state as $key => $value ) {
        $k = is_string( $key ) ? sanitize_key( $key ) : '';
        if ( ! $k ) {
            continue;
        }
        $num = is_numeric( $value ) ? intval( $value ) : 0;
        $clean[ $k ] = max( 0, $num );
    }
    return $clean;
}

function guardar_hp_temporal() {
	
	    if (!isset($_POST['post_id']) || !isset($_POST['valor'])) {
        wp_send_json_error(['message' => 'Faltan parámetros.']);
    }
    $post_id = intval($_POST['post_id']);
    $valor = intval($_POST['valor']);

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    // Usa ACF para guardar correctamente el campo
    update_field('cs_hp_temp', $valor, $post_id);

    wp_send_json_success(['message' => 'HP temporal guardado con éxito']);
}

add_action('wp_ajax_guardar_hp_temporal', 'guardar_hp_temporal');
add_action('wp_ajax_nopriv_guardar_hp_temporal', 'guardar_hp_temporal');

/**
 * Actualiza la imagen destacada del personaje desde el selector de medios.
 */
function drak_set_personaje_image() {
	if ( ! isset( $_POST['post_id'], $_POST['attachment_id'] ) ) {
		wp_send_json_error( [ 'message' => 'Faltan parámetros.' ], 400 );
	}
	$post_id      = intval( wp_unslash( $_POST['post_id'] ) );
	$attachment_id = intval( wp_unslash( $_POST['attachment_id'] ) );
	$context      = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

	if ( ! $post_id || ! $attachment_id ) {
		wp_send_json_error( [ 'message' => 'Datos inválidos.' ], 400 );
	}

	if ( ! drak_user_can_manage_personaje( $post_id ) ) {
		wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
	}

	// Guarda la imagen por contexto para permitir hero diferentes por página.
	if ( $context ) {
		update_post_meta( $post_id, 'hero_image_' . $context, $attachment_id );
	} else {
		// Fallback: usa destacada si no se pasa contexto.
		set_post_thumbnail( $post_id, $attachment_id );
	}

	$url = wp_get_attachment_image_url( $attachment_id, 'large' );
	wp_send_json_success( [ 'image_url' => $url ] );
}
add_action( 'wp_ajax_drak_set_personaje_image', 'drak_set_personaje_image' );
add_action( 'wp_ajax_drak_set_personaje_drive_image', 'drak_set_personaje_drive_image' );

/**
 * Actualiza la imagen hero del personaje usando un item de galería (Drive).
 */
function drak_set_personaje_drive_image() {
	check_ajax_referer( 'drak_drive_picker_ajax', 'nonce' );

	if ( ! isset( $_POST['post_id'], $_POST['gallery_item_id'] ) ) {
		wp_send_json_error( [ 'message' => 'Faltan parámetros.' ], 400 );
	}

	$post_id        = intval( wp_unslash( $_POST['post_id'] ) );
	$gallery_item_id = intval( wp_unslash( $_POST['gallery_item_id'] ) );
	$context        = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

	if ( ! $post_id || ! $gallery_item_id ) {
		wp_send_json_error( [ 'message' => 'Datos inválidos.' ], 400 );
	}

	if ( ! drak_user_can_manage_personaje( $post_id ) ) {
		wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
	}

	$url = drak_drive_picker_get_image_url( $gallery_item_id );
	if ( ! $url ) {
		wp_send_json_error( [ 'message' => 'La imagen no tiene URL de Drive.' ], 400 );
	}

	$key_prefix = $context ? 'hero_image_drive_' . $context : 'hero_image_drive';

	update_post_meta( $post_id, $key_prefix . '_item_id', $gallery_item_id );
	update_post_meta( $post_id, $key_prefix . '_url', $url );

	wp_send_json_success( [ 'image_url' => $url ] );
}
add_action( 'wp_ajax_nopriv_drak_set_personaje_drive_image', 'drak_set_personaje_drive_image' );

/**
 * Devuelve la URL de la imagen hero para una vista concreta del personaje.
 *
 * @param int    $post_id
 * @param string $context (hoja|inventario|grimorio|combate)
 * @param string $fallback_url
 *
 * @return string
 */
function drak_get_personaje_hero_image_url( $post_id, $context, $fallback_url = '' ) {
	$context = sanitize_key( $context );
	// Prioridad: imagen desde galería (Drive).
	if ( $context ) {
		$drive_url = get_post_meta( $post_id, 'hero_image_drive_' . $context . '_url', true );
		if ( $drive_url ) {
			return esc_url( $drive_url );
		}
	}
	$drive_global = get_post_meta( $post_id, 'hero_image_drive_url', true );
	if ( $drive_global ) {
		return esc_url( $drive_global );
	}

	if ( $context ) {
		$meta_id = get_post_meta( $post_id, 'hero_image_' . $context, true );
		if ( $meta_id ) {
			$url = wp_get_attachment_image_url( $meta_id, 'large' );
			if ( $url ) {
				return $url;
			}
		}
	}
	$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( $thumb ) {
		return $thumb;
	}
	return $fallback_url;
}

/**
 * Guardado AJAX del módulo de combate (bonos y notas).
 */
function drak_guardar_modulo_combate() {
    if ( ! isset( $_POST['post_id'] ) ) {
        wp_send_json_error( [ 'message' => 'Falta post_id' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $attack_extra_main = isset( $_POST['attack_extra_main'] ) ? intval( $_POST['attack_extra_main'] ) : 0;
    $damage_extra_main = isset( $_POST['damage_extra_main'] ) ? intval( $_POST['damage_extra_main'] ) : 0;
    $attack_extra_off  = isset( $_POST['attack_extra_off'] ) ? intval( $_POST['attack_extra_off'] ) : 0;
    $damage_extra_off  = isset( $_POST['damage_extra_off'] ) ? intval( $_POST['damage_extra_off'] ) : 0;
    $ac_extra          = isset( $_POST['ac_extra'] ) ? intval( $_POST['ac_extra'] ) : 0;
    $shield_extra      = isset( $_POST['shield_extra'] ) ? intval( $_POST['shield_extra'] ) : 0;
    $temp_hp_extra     = isset( $_POST['temp_hp_extra'] ) ? intval( $_POST['temp_hp_extra'] ) : 0;
    $notes        = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';
    $ammo_state_raw = isset( $_POST['ammo_state'] ) ? wp_unslash( $_POST['ammo_state'] ) : '';
    $ammo_quiver_type = isset( $_POST['ammo_quiver_type'] ) ? sanitize_key( wp_unslash( $_POST['ammo_quiver_type'] ) ) : '';
    $ammo_quiver_amount = isset( $_POST['ammo_quiver_amount'] ) ? intval( $_POST['ammo_quiver_amount'] ) : null;
    $ammo_state = [];
    if ( is_string( $ammo_state_raw ) && $ammo_state_raw !== '' ) {
        $decoded = json_decode( $ammo_state_raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $ammo_state = $decoded;
        }
    } elseif ( is_array( $ammo_state_raw ) ) {
        $ammo_state = $ammo_state_raw;
    }
    $ammo_state = drak_sanitize_combat_ammo_state( $ammo_state );

    update_post_meta( $post_id, 'combat_attack_extra_main', $attack_extra_main );
    update_post_meta( $post_id, 'combat_damage_extra_main', $damage_extra_main );
    update_post_meta( $post_id, 'combat_attack_extra_off', $attack_extra_off );
    update_post_meta( $post_id, 'combat_damage_extra_off', $damage_extra_off );
    update_post_meta( $post_id, 'combat_ac_extra', $ac_extra );
    update_post_meta( $post_id, 'combat_shield_extra', $shield_extra );
    update_post_meta( $post_id, 'combat_temp_hp_extra', $temp_hp_extra );
    update_post_meta( $post_id, 'combat_notes', $notes );
    update_post_meta( $post_id, 'combat_ammo_state', $ammo_state );

    if ( $ammo_quiver_type ) {
        $payload = [
            'type'   => $ammo_quiver_type,
            'amount' => max( 0, $ammo_quiver_amount ?? ( $ammo_state[ 'quiver-' . $ammo_quiver_type ] ?? 0 ) ),
        ];
        update_field( 'carcaj', $payload, $post_id );
    }

    wp_send_json_success(
        [
            'attack_extra_main' => $attack_extra_main,
            'damage_extra_main' => $damage_extra_main,
            'attack_extra_off'  => $attack_extra_off,
            'damage_extra_off'  => $damage_extra_off,
            'ac_extra'          => $ac_extra,
            'shield_extra'      => $shield_extra,
            'temp_hp_extra'     => $temp_hp_extra,
            'notes'        => $notes,
            'ammo'         => $ammo_state,
        ]
    );
}
add_action( 'wp_ajax_guardar_modulo_combate', 'drak_guardar_modulo_combate' );
add_action( 'wp_ajax_nopriv_guardar_modulo_combate', 'drak_guardar_modulo_combate' );

/**
 * Guardado AJAX de INI / CA / VEL / PV desde la hoja de personaje.
 */
function drak_save_basic_stats() {
	if ( ! isset( $_POST['post_id'] ) ) {
		wp_send_json_error( [ 'message' => 'Falta post_id' ], 400 );
	}
	$post_id = intval( $_POST['post_id'] );
	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'save_basic_stats_' . $post_id ) ) {
		wp_send_json_error( [ 'message' => 'Nonce inválido' ], 403 );
	}
	if ( ! drak_user_can_manage_personaje( $post_id ) ) {
		wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
	}

	$fields = [
		'cs_iniciativa',
		'cs_ac',
		'cs_velocidad',
		'cs_hp',
	];
	foreach ( $fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_field( $field, drak_get_post_value( $field, '' ), $post_id );
		}
	}

	wp_send_json_success( [ 'message' => 'Básicos guardados' ] );
}
add_action( 'wp_ajax_drak_save_basic_stats', 'drak_save_basic_stats' );
add_action( 'wp_ajax_nopriv_drak_save_basic_stats', 'drak_save_basic_stats' );

/**
 * Evita que WordPress elimine el parámetro `paged` en las secciones estáticas de wiki
 * (static-*) dentro de la campaña, lo que provocaba redirección 301 y pérdida de página.
 */
function drak_disable_canonical_for_static_wiki( $redirect, $request ) {
    if ( is_singular( 'campaign' ) ) {
        $wiki_section = isset( $_GET['wiki_section'] ) ? sanitize_text_field( wp_unslash( $_GET['wiki_section'] ) ) : '';
        $wiki_view    = isset( $_GET['wiki_view'] ) ? sanitize_text_field( wp_unslash( $_GET['wiki_view'] ) ) : '';
        $paged        = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 0;
        if ( $paged > 1 && strpos( $wiki_section, 'static-' ) === 0 && 'archive' === $wiki_view ) {
            return false;
        }
    }
    return $redirect;
}
add_filter( 'redirect_canonical', 'drak_disable_canonical_for_static_wiki', 10, 2 );

/**
 * Definición por defecto de las rutas base para enlazar tags 5eTools a las
 * tarjetas internas (idiomas, razas, conjuros, etc.). Se puede sobreescribir
 * con el filtro `drak_dnd5_link_bases`.
 */
function drak_get_dnd5_link_bases() {
    $bases = [
        'language'         => home_url( '/wiki/idiomas' ),
        'race'             => home_url( '/wiki/razas' ),
        'background'       => home_url( '/wiki/trasfondos' ),
        'class'            => home_url( '/wiki/clases' ),
        'subclass'         => home_url( '/wiki/subclases' ),
        'classfeature'     => home_url( '/wiki/rasgos-de-clase' ),
        'subclassfeature'  => home_url( '/wiki/rasgos-de-subclase' ),
        'spell'            => home_url( '/grimorio' ),
        'feat'             => home_url( '/wiki/dotes' ),
        'tool'             => home_url( '/wiki/herramientas' ),
        'weapon'           => home_url( '/wiki/armas' ),
        'armor'            => home_url( '/wiki/armaduras' ),
        'item'             => home_url( '/wiki/objetos' ),
        'action'           => home_url( '/wiki/acciones' ),
        'condition'        => home_url( '/wiki/condiciones' ),
        'creature'         => home_url( '/wiki/bestiario' ),
        'npc'              => home_url( '/wiki/npc' ),
        'lugar'            => home_url( '/wiki/lugar' ),
        'faccion'          => home_url( '/wiki/faccion' ),
        'personaje'        => home_url( '/personaje' ),
        'personaje_wiki'   => home_url( '/wiki/personaje-wiki' ),
    ];

    return apply_filters( 'drak_dnd5_link_bases', $bases );
}


add_action('wp_enqueue_scripts', function () {
    $dnd5_link_bases = drak_get_dnd5_link_bases();

    wp_enqueue_script(
        'dice-highlighter',
        get_stylesheet_directory_uri() . '/js/dice-highlighter.js',
        [],
        null,
        true
    );

    if (is_page_template('page-hoja-personaje.php') || is_page_template('page-combate-personaje.php')) {
        $personaje_slug = get_query_var('personaje_slug');
        $personaje = $personaje_slug ? get_page_by_path($personaje_slug, OBJECT, 'personaje') : null;
        $post_id = $personaje ? $personaje->ID : 0;
        $combat_ammo_state = $post_id ? drak_get_combat_ammo_state( $post_id ) : [];
        $carcaj_payload = [];
        if ( $post_id && function_exists( 'get_field' ) ) {
            $carcaj_raw = drak_normalize_carcaj_value( get_field( 'carcaj', $post_id ) );
            if ( is_array( $carcaj_raw ) ) {
                $carcaj_payload = [
                    'type'   => sanitize_key( $carcaj_raw['type'] ?? '' ),
                    'amount' => max( 0, intval( $carcaj_raw['amount'] ?? 0 ) ),
                ];
            }
        }
        if ( ! empty( $carcaj_payload['type'] ) ) {
            $combat_ammo_state[ 'quiver-' . $carcaj_payload['type'] ] = $carcaj_payload['amount'];
        }

        wp_enqueue_script('dnd5-renderer', get_stylesheet_directory_uri() . '/js/dnd5-renderer.js', [], null, true);
        wp_localize_script('dnd5-renderer', 'DND5_LINK_BASES', $dnd5_link_bases);
        wp_enqueue_script('class-reference-js', get_stylesheet_directory_uri() . '/js/class-reference.js', ['jquery'], null, true);
        wp_enqueue_script('hoja-personaje-js', get_stylesheet_directory_uri() . '/js/hoja-personaje.js', ['jquery', 'class-reference-js', 'dnd5-renderer'], null, true);
        wp_localize_script('hoja-personaje-js', 'HP_TEMP_AJAX', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'post_id' => $post_id,
        ]);
        $basic_nonce = $post_id ? wp_create_nonce( 'save_basic_stats_' . $post_id ) : '';
        wp_localize_script('hoja-personaje-js', 'BASIC_AUTOSAVE', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'post_id'  => $post_id,
            'nonce'    => $basic_nonce,
        ]);
        $combat_config = [
            'weapon_main'    => drak_get_weapon_payload( $post_id, 'arma_principal' ),
            'weapon_offhand' => drak_get_weapon_payload( $post_id, 'arma_secundaria' ),
            'armor'          => drak_get_armor_payload( $post_id ),
            'attack_extra_main' => intval( get_post_meta( $post_id, 'combat_attack_extra_main', true ) ),
            'damage_extra_main' => intval( get_post_meta( $post_id, 'combat_damage_extra_main', true ) ),
            'attack_extra_off'  => intval( get_post_meta( $post_id, 'combat_attack_extra_off', true ) ),
            'damage_extra_off'  => intval( get_post_meta( $post_id, 'combat_damage_extra_off', true ) ),
            'ac_extra'          => intval( get_post_meta( $post_id, 'combat_ac_extra', true ) ),
            'shield_extra'      => intval( get_post_meta( $post_id, 'combat_shield_extra', true ) ),
            'temp_hp_extra'     => intval( get_post_meta( $post_id, 'combat_temp_hp_extra', true ) ),
            'notes'          => sanitize_text_field( get_post_meta( $post_id, 'combat_notes', true ) ),
            'ammo'           => $combat_ammo_state,
            'quiver'         => $carcaj_payload,
        ];
        wp_localize_script( 'hoja-personaje-js', 'COMBAT_CONFIG', $combat_config );
        $can_edit_sheet = $post_id ? drak_user_can_manage_personaje( $post_id ) : false;
        $feat_nonce     = $post_id ? wp_create_nonce( 'save_feats_' . $post_id ) : '';
        $feat_selected  = $post_id ? drak_get_character_feats( $post_id ) : [];
        wp_localize_script( 'hoja-personaje-js', 'FEAT_MANAGER', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'post_id'  => $post_id,
            'nonce'    => $feat_nonce,
            'selected' => $feat_selected,
            'can_edit' => is_user_logged_in() && $can_edit_sheet,
        ] );
        wp_localize_script('hoja-personaje-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        $esoteric_uri = ''; // usamos el catálogo ya incrustado para evitar 404 en prod
        wp_localize_script('hoja-personaje-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races-es.json' ) ?: drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds-es.json' ) ?: drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes-es.json' ) ?: drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details-es.json' ) ?: drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats-es.json' ) ?: drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => $esoteric_uri,
            'esotericTheoriesData' => $apothecary_theories,
            'conditions'    => drak_static_data_uri( 'dnd-conditions-es.json' ),
        ]);
        wp_localize_script('hoja-personaje-js', 'APOTHECARY_THEORY_CATALOG', $apothecary_theories );
        wp_enqueue_script('spell-search-js', get_stylesheet_directory_uri() . '/js/spell-search.js', ['jquery', 'dnd5-renderer'], null, true);
        wp_localize_script('spell-search-js', 'SPELL_SEARCH_CONFIG', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'classes'  => drak_get_spellcasting_classes(),
            'labels'   => [
                'placeholder' => __( 'Escribe el nombre del conjuro…', 'grimorio' ),
                'empty'       => __( 'No se encontraron resultados para tu búsqueda.', 'grimorio' ),
                'error'       => __( 'No se pudo completar la búsqueda. Inténtalo nuevamente.', 'grimorio' ),
            ],
        ]);

    }

    if (is_page_template('page-grimorio-personaje.php')) {
        $personaje_slug = get_query_var('personaje_slug');
        $personaje = $personaje_slug ? get_page_by_path($personaje_slug, OBJECT, 'personaje') : null;
        $post_id = $personaje ? $personaje->ID : 0;
        $nivel  = $personaje ? intval( get_field( 'nivel', $post_id ) ) : 0;
        $clase  = $personaje ? get_field( 'clase', $post_id ) : '';
        $subclase = $personaje ? get_field( 'subclase', $post_id ) : '';
        $slots  = drak_grimorio_get_slots( $post_id );
        $spells = drak_grimorio_get_prepared( $post_id );
        $subclase   = $personaje ? get_field( 'subclase', $post_id ) : '';
        $slot_row   = drak_get_class_spell_slots_for_level( $clase, $nivel );
        if ( empty( $slot_row ) ) {
            $fallback  = drak_get_full_caster_slots_table();
            $slot_row  = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
        }
        ksort( $slot_row );
        $concentration = get_post_meta( $post_id, 'grimorio_concentration_state', true );
        $concentration = is_array( $concentration ) ? $concentration : [];
        $apothecary_selection = drak_get_character_apothecary_theories( $post_id );
        $class_reference_payload = null;
        $auto_prepared = drak_get_auto_prepared_spells(
            $clase,
            $subclase,
            $nivel,
            $class_reference_payload,
            [
                'apothecary_theories' => $apothecary_selection,
            ]
        );
        $prepared_limit = drak_get_class_prepared_limit( $clase, $nivel );
        $ability_fields = drak_get_ability_field_map();
        $ability_scores = [];
        foreach ( $ability_fields as $ability_key => $meta ) {
            $raw                       = get_field( $meta['field'], $post_id );
            $ability_scores[ $ability_key ] = ( $raw === '' || $raw === null ) ? null : intval( $raw );
        }
        $base_ac_raw    = get_field( 'cs_ac', $post_id );
        $base_speed_raw = get_field( 'cs_velocidad', $post_id );
        $base_ac        = ( $base_ac_raw === '' || $base_ac_raw === null ) ? 0 : intval( $base_ac_raw );
        $base_speed     = is_numeric( $base_speed_raw ) ? intval( $base_speed_raw ) : intval( preg_replace( '/[^0-9]/', '', (string) $base_speed_raw ) );
        $base_speed     = max( 0, $base_speed );
        $transformation_state = drak_grimorio_get_transformation_state( $post_id );
        $transformation_nonce = wp_create_nonce( 'grimorio_transformation_' . $post_id );
        $sorcery_nonce        = wp_create_nonce( 'grimorio_sorcery_' . $post_id );
        $sorcery_state        = null;
        if ( drak_is_sorcerer_class( $clase ) ) {
            $sorcery_state = [
                'points_max'     => drak_get_sorcery_points_max( $post_id, $clase, $nivel ),
                'points_current' => 0,
                'flex_slots'     => drak_get_sorcery_flexible_slots( $post_id ),
                'metamagic_known'=> drak_get_sorcerer_metamagic_known( $post_id ),
                'metamagic_limit'=> drak_get_sorcerer_metamagic_limit( $nivel ),
                'slot_costs'     => drak_get_sorcery_slot_costs(),
            ];
            $sorcery_state['points_current'] = drak_get_sorcery_points_current( $post_id, $sorcery_state['points_max'] );
        }

        wp_enqueue_script('dnd5-renderer', get_stylesheet_directory_uri() . '/js/dnd5-renderer.js', [], null, true);
        wp_localize_script('dnd5-renderer', 'DND5_LINK_BASES', $dnd5_link_bases);
        wp_enqueue_script('class-reference-js', get_stylesheet_directory_uri() . '/js/class-reference.js', ['jquery'], null, true);
        wp_enqueue_script('hoja-personaje-js', get_stylesheet_directory_uri() . '/js/hoja-personaje.js', ['jquery', 'class-reference-js', 'dnd5-renderer'], null, true);
        wp_enqueue_script('grimorio-js', get_stylesheet_directory_uri() . '/js/grimorio.js', ['jquery', 'class-reference-js', 'hoja-personaje-js', 'dnd5-renderer'], null, true);
        $grimorio_apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        wp_localize_script('grimorio-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races-es.json' ) ?: drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds-es.json' ) ?: drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes-es.json' ) ?: drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details-es.json' ) ?: drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats-es.json' ) ?: drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => drak_static_data_uri( 'esotherics.json' ),
            'esotericTheoriesData' => $grimorio_apothecary_theories,
            'conditions'    => drak_static_data_uri( 'dnd-conditions-es.json' ),
        ]);
        wp_localize_script('grimorio-js', 'APOTHECARY_THEORY_CATALOG', $grimorio_apothecary_theories );
        wp_localize_script('grimorio-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $slots_nonce     = wp_create_nonce( 'grimorio_slots_' . $post_id );
        $prepared_nonce  = wp_create_nonce( 'grimorio_prepared_' . $post_id );
        $concentration_nonce = wp_create_nonce( 'grimorio_concentration_' . $post_id );

        $apothecary_spell_model = drak_is_apothecary_class( $clase );
        $apothecary_slots       = $apothecary_spell_model ? drak_get_apothecary_slots_state( $post_id, $nivel ) : null;
        $apothecary_formulas    = $apothecary_spell_model ? drak_get_apothecary_formulas_state( $post_id, $clase, $subclase, $nivel ) : [];
        $apothecary_always_prepared = $apothecary_spell_model ? drak_get_apothecary_always_prepared_spells( $clase, $subclase ) : [];
        $grimorio_data = [
            'ajax_url'       => drak_get_admin_ajax_url(),
            'post_id'        => $post_id,
            'level'          => $nivel,
            'class_id'       => $clase,
            'subclass_id'    => $subclase,
            'slots_used'     => $apothecary_spell_model ? [] : ( is_array( $slots ) ? $slots : [] ),
            'prepared'       => is_array( $spells ) ? $spells : [],
            'slot_limits'    => $apothecary_spell_model ? [] : array_map( 'intval', $slot_row ),
            'prepared_limit' => $apothecary_spell_model ? drak_get_apothecary_prepared_limit( $nivel, $ability_scores ) : $prepared_limit,
            'nonce'          => $slots_nonce,
            'prepared_nonce' => $prepared_nonce,
            'concentration'  => [
                'level'   => isset( $concentration['level'] ) ? intval( $concentration['level'] ) : null,
                'spell'   => $concentration['spell'] ?? '',
                'spell_id'=> $concentration['spell_id'] ?? '',
            ],
            'concentration_nonce' => $concentration_nonce,
            'auto_prepared_spells' => $auto_prepared,
            'class_reference'   => $class_reference_payload,
            'esoteric_theories' => $apothecary_selection,
            'abilities'         => $ability_scores,
            'base_ac'           => $base_ac,
            'base_speed'        => $base_speed,
            'apothecary_level'  => $nivel,
            'transformation'    => $transformation_state,
            'transformation_nonce' => $transformation_nonce,
            'sorcery'           => $sorcery_state,
            'sorcery_nonce'     => $sorcery_nonce,
        ];

        if ( $apothecary_spell_model && $apothecary_slots ) {
            $grimorio_data['spell_model']      = 'apothecary';
            $grimorio_data['apothecary_slots'] = $apothecary_slots;
            $grimorio_data['always_prepared']  = $apothecary_always_prepared;
            $grimorio_data['greater_formulas'] = $apothecary_formulas;
        }
        $grimorio_data['cantrips'] = isset( $spells[0] ) && is_array( $spells[0] ) ? $spells[0] : [];

        wp_localize_script('grimorio-js', 'GRIMORIO_DATA', $grimorio_data);
        wp_enqueue_script('spell-search-js', get_stylesheet_directory_uri() . '/js/spell-search.js', ['jquery', 'dnd5-renderer'], null, true);
        wp_localize_script('spell-search-js', 'SPELL_SEARCH_CONFIG', [
            'ajax_url' => drak_get_admin_ajax_url(),
            'classes'  => drak_get_spellcasting_classes(),
            'labels'   => [
                'placeholder' => __( 'Escribe el nombre del conjuro…', 'grimorio' ),
                'empty'       => __( 'No se encontraron resultados para tu búsqueda.', 'grimorio' ),
                'error'       => __( 'No se pudo completar la búsqueda. Inténtalo nuevamente.', 'grimorio' ),
            ],
        ]);
        wp_localize_script('hoja-personaje-js', 'DND5_API', [
            'ajax_url' => drak_get_admin_ajax_url(),
        ]);
        $sheet_apothecary_theories = array_values( drak_get_apothecary_theories_catalog() );
        $esoteric_uri = ''; // usamos el catálogo ya incrustado para evitar 404 en prod
        wp_localize_script('hoja-personaje-js', 'DND5_STATIC_DATA', [
            'races'        => drak_static_data_uri( 'dnd-races.json' ),
            'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds.json' ),
            'classList'    => drak_static_data_uri( 'dnd-classes.json' ),
            'classDetails' => drak_static_data_uri( 'dnd-class-details.json' ),
            'feats'        => drak_static_data_uri( 'dnd-feats.json' ),
            'esotericTheories' => $esoteric_uri,
            'esotericTheoriesData' => $sheet_apothecary_theories,
        ]);
        wp_localize_script('hoja-personaje-js', 'APOTHECARY_THEORY_CATALOG', $sheet_apothecary_theories );
    }

    if ( is_page_template( 'page-crear-personaje.php' ) ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'personaje-wizard-js',
            get_stylesheet_directory_uri() . '/js/personaje-wizard.js',
            [ 'jquery' ],
            null,
            true
        );
        wp_localize_script(
            'personaje-wizard-js',
            'PERSONAJE_WIZARD_API',
            [
                'ajax_url'  => drak_get_admin_ajax_url(),
                'endpoints' => [
                    'classes'       => 'drak_dnd5_get_classes',
                    'races'         => 'drak_dnd5_get_races',
                    'backgrounds'   => 'drak_dnd5_get_backgrounds',
                    'proficiencies' => 'drak_dnd5_get_proficiencies',
                    'create'        => 'drak_wizard_create_personaje',
                ],
                'static_data' => [
                    'classDetails' => drak_static_data_uri( 'dnd-class-details.json' ),
                    'races'        => drak_static_data_uri( 'dnd-races.json' ),
                    'backgrounds'  => drak_static_data_uri( 'dnd-backgrounds.json' ),
                    'languages'    => drak_static_data_uri( 'dnd-languages.json' ),
                ],
                'class_features_endpoint' => admin_url( 'admin-ajax.php?action=drak_get_class_features_for_wizard' ),
                'race_features_endpoint'  => admin_url( 'admin-ajax.php?action=drak_get_race_features_for_wizard' ),
                'labels' => [
                    'error_generic' => __( 'No se pudo completar la acción. Inténtalo de nuevo.', 'personaje' ),
                ],
            ]
        );
    }
});


/**
 * AJAX: lista de clases D&D 5e
 */

/**
 * Lee y cachea el JSON local con clases/subclases de D&D.
 */
function drak_locate_theme_data_file( $relative ) {
    $relative = ltrim( $relative, '/\\' );
    $candidates = [];
    $child_base = trailingslashit( get_stylesheet_directory() );
    $parent_base = function_exists( 'get_template_directory' ) ? trailingslashit( get_template_directory() ) : $child_base;

    $bases = [
        $child_base . 'data/',
        $child_base . 'jsons/',
        $child_base . '5etools-src-main/data/',
        $parent_base . 'data/',
        $parent_base . 'jsons/',
        $parent_base . '5etools-src-main/data/',
        $child_base,
        $parent_base,
    ];

    foreach ( $bases as $base ) {
        $path = $base . $relative;
        if ( file_exists( $path ) ) {
            return $path;
        }
    }

    return '';
}

function drak_get_local_dnd_classes_data() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $path = drak_locate_theme_data_file( 'dnd-classes-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-classes.json' );
    }
    if ( ! $path ) {
        $child_base = trailingslashit( get_stylesheet_directory() );
        $candidates = [
            $child_base . 'jsons/dnd-classes-es.json',
            $child_base . 'jsons/dnd-classes.json',
        ];
        foreach ( $candidates as $candidate ) {
            if ( file_exists( $candidate ) ) {
                $path = $candidate;
                break;
            }
        }
    }
    if ( ! $path ) {
        return null;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $cached = drak_localize_class_data( $data );
    return $cached;
}

function drak_localize_class_data( $data ) {
    if ( ! is_array( $data ) || empty( $data['classes'] ) ) {
        return $data;
    }

    $data['classes'] = array_map( static function ( $class ) {
        if ( isset( $class['name_es'] ) && $class['name_es'] !== '' ) {
            $class['name'] = $class['name_es'];
        }
        if ( isset( $class['shortName_es'] ) && $class['shortName_es'] !== '' ) {
            $class['shortName'] = $class['shortName_es'];
        }
        if ( isset( $class['subclassTitle_es'] ) && $class['subclassTitle_es'] !== '' ) {
            $class['subclassTitle'] = $class['subclassTitle_es'];
        }
        if ( ! empty( $class['subclasses'] ) && is_array( $class['subclasses'] ) ) {
            $class['subclasses'] = array_map( static function ( $subclass ) {
                if ( isset( $subclass['name_es'] ) && $subclass['name_es'] !== '' ) {
                    $subclass['name'] = $subclass['name_es'];
                }
                if ( isset( $subclass['shortName_es'] ) && $subclass['shortName_es'] !== '' ) {
                    $subclass['shortName'] = $subclass['shortName_es'];
                }
                return $subclass;
            }, $class['subclasses'] );
        }
        return $class;
    }, $data['classes'] );

    return $data;
}

/**
 * AJAX: lista de clases (desde dnd-classes.json)
 */

function drak_dnd5_get_classes() {
    $data = drak_get_local_dnd_classes_data();

    if (!$data || empty($data['classes']) || !is_array($data['classes'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-classes.json']);
    }

    $out  = [];
    $seen = [];

    foreach ($data['classes'] as $cls) {
        $name = $cls['name'] ?? '';
        if ($name === '') {
            continue;
        }

        // Usamos el slug del nombre para detectar duplicados
        $slug = sanitize_title($name);
        if (isset($seen[$slug])) {
            // Ya hemos añadido esta clase (aunque haya otra edición/libro)
            continue;
        }
        $seen[$slug] = true;

        $out[] = [
            'id'            => $cls['id']            ?? '',
            'name'          => $name,
            'source'        => $cls['source']        ?? '',
            'edition'       => $cls['edition']       ?? '',
            'subclassTitle' => $cls['subclassTitle'] ?? '',
        ];
    }

    wp_send_json_success(['classes' => $out]);
}


add_action('wp_ajax_drak_dnd5_get_classes', 'drak_dnd5_get_classes');
add_action('wp_ajax_nopriv_drak_dnd5_get_classes', 'drak_dnd5_get_classes');

/**
 * AJAX: subclases disponibles para una clase
 */
/**
 * AJAX: subclases para una clase concreta (desde dnd-classes.json)
 */

function drak_dnd5_get_subclasses() {
    // Usamos el mismo nombre de parámetro ('class_index'),
    // pero contiene el ID de nuestra clase local.
    $class_id = drak_get_post_value('class_index', '');

    if (!$class_id) {
        wp_send_json_error(['message' => 'Falta el parámetro class_index']);
    }

    $data = drak_get_local_dnd_classes_data();
    if (!$data || empty($data['classes']) || !is_array($data['classes'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-classes.json']);
    }

    // 1) Localizamos la clase por ID para conocer su "name"
    $target_name = '';
    foreach ($data['classes'] as $cls) {
        if (!empty($cls['id']) && $cls['id'] === $class_id) {
            $target_name = $cls['name'] ?? '';
            break;
        }
    }

    if ($target_name === '') {
        wp_send_json_error(['message' => 'Clase no encontrada en dnd-classes.json']);
    }

    // 2) Reunimos subclases de TODAS las clases con ese mismo nombre
    $subclasses     = [];
    $seen_sub_ids   = [];

    foreach ($data['classes'] as $cls) {
        if (($cls['name'] ?? '') !== $target_name) {
            continue;
        }

        if (empty($cls['subclasses']) || !is_array($cls['subclasses'])) {
            continue;
        }

        foreach ($cls['subclasses'] as $sc) {
            $sc_id = $sc['id'] ?? '';
            if ($sc_id && isset($seen_sub_ids[$sc_id])) {
                continue; // evitar duplicados exactos
            }
            if ($sc_id) {
                $seen_sub_ids[$sc_id] = true;
            }

            // Etiqueta tipo "Life (PHB)" usando shortName + source
            $label = $sc['shortName'] ?? $sc['name'] ?? '';
            if (!empty($sc['source'])) {
                $label .= ' (' . $sc['source'] . ')';
            }

            $subclasses[] = [
                'id'      => $sc_id,
                'name'    => $label,
                'source'  => $sc['source']  ?? '',
                'edition' => $sc['edition'] ?? '',
            ];
        }
    }

    wp_send_json_success(['subclasses' => $subclasses]);
}



add_action('wp_ajax_drak_dnd5_get_subclasses', 'drak_dnd5_get_subclasses');
add_action('wp_ajax_nopriv_drak_dnd5_get_subclasses', 'drak_dnd5_get_subclasses');

/**
 * (Opcional) AJAX: lista de armas
 * Usa una categoría de equipo de la API: /api/equipment-categories/{index}
 * El índice exacto depende de lo que quieras (por ejemplo "weapon",
 * "simple-weapons", "martial-weapons", etc.).
 */
function drak_dnd5_get_weapons() {
    $category_index = drak_get_post_value('category', 'weapon');

    $url      = 'https://www.dnd5eapi.co/api/equipment-categories/' . rawurlencode($category_index);
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error de conexión con la API de D&D 5e']);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        wp_send_json_error(['message' => 'La API devolvió un código ' . $code]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $weapons = [];

    // La respuesta de equipment-categories trae normalmente un array 'equipment'
    if (!empty($body['equipment']) && is_array($body['equipment'])) {
        foreach ($body['equipment'] as $item) {
            $weapons[] = [
                'index' => $item['index'] ?? '',
                'name'  => $item['name']  ?? '',
            ];
        }
    }

    wp_send_json_success(['weapons' => $weapons]);
}
add_action('wp_ajax_drak_dnd5_get_weapons', 'drak_dnd5_get_weapons');
add_action('wp_ajax_nopriv_drak_dnd5_get_weapons', 'drak_dnd5_get_weapons');

function drak_spell_matches_classes( $spell, $filters ) {
    if ( empty( $filters ) ) {
        return true;
    }
    if ( empty( $spell['classes'] ) || ! is_array( $spell['classes'] ) ) {
        return false;
    }
    foreach ( $spell['classes'] as $class ) {
        $id = $class['id'] ?? '';
        if ( $id && in_array( $id, $filters, true ) ) {
            return true;
        }
    }
    return false;
}

function drak_collect_spell_text( $entry, &$chunks ) {
    if ( $entry === null ) {
        return;
    }
    if ( is_string( $entry ) ) {
        $chunks[] = $entry;
        return;
    }
    if ( is_array( $entry ) ) {
        foreach ( $entry as $item ) {
            drak_collect_spell_text( $item, $chunks );
        }
    } elseif ( is_object( $entry ) ) {
        foreach ( get_object_vars( $entry ) as $value ) {
            drak_collect_spell_text( $value, $chunks );
        }
    }
}

function drak_build_spell_preview( $spell ) {
    $chunks = [];
    if ( ! empty( $spell['entries'] ) ) {
        drak_collect_spell_text( $spell['entries'], $chunks );
    }
    $text = trim( implode( ' ', $chunks ) );
    $text = preg_replace( '/\s+/', ' ', $text );
    if ( '' === $text ) {
        return '';
    }
    if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 220 ) {
        return mb_substr( $text, 0, 217 ) . '…';
    }
    return strlen( $text ) > 220 ? substr( $text, 0, 217 ) . '…' : $text;
}

function drak_build_spell_paragraphs( $spell ) {
    if ( empty( $spell['entries'] ) ) {
        return [];
    }
    $chunks = [];
    drak_collect_spell_text( $spell['entries'], $chunks );
    if ( empty( $chunks ) ) {
        return [];
    }
    $text = trim( implode( "\n", $chunks ) );
    if ( '' === $text ) {
        return [];
    }
    $parts = preg_split( '/\n+/u', $text );
    $paragraphs = [];
    foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( '' !== $part ) {
            $paragraphs[] = $part;
        }
    }
    return $paragraphs;
}

function drak_dnd5_search_spells() {
    if ( ! isset( $_POST['q'] ) && empty( $_POST['classes'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros faltantes.' ], 400 );
    }

    $query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 25;
    $limit = max( 1, min( 50, $limit ) );

    $raw_classes = $_POST['classes'] ?? [];
    if ( is_string( $raw_classes ) ) {
        $raw_classes = [ $raw_classes ];
    }
    $class_filters = array_filter( array_map( 'sanitize_text_field', (array) $raw_classes ) );

    $needle = trim( strtolower( remove_accents( $query ) ) );
    $matches = [];
    $total = 0;
    foreach ( drak_get_local_dnd_spells() as $spell ) {
        $name = $spell['name'] ?? '';
        if ( $needle !== '' ) {
            $haystack = strtolower( remove_accents( $name ) );
            if ( strpos( $haystack, $needle ) === false ) {
                continue;
            }
        } elseif ( empty( $class_filters ) ) {
            continue;
        }

        if ( ! drak_spell_matches_classes( $spell, $class_filters ) ) {
            continue;
        }

        $total++;
        if ( count( $matches ) >= $limit ) {
            continue;
        }

        $matches[] = [
            'id'       => $spell['id'] ?? '',
            'name'     => $name,
            'level'    => intval( $spell['level'] ?? 0 ),
            'school'   => $spell['school'] ?? '',
            'source'   => $spell['source'] ?? '',
            'classes'  => array_map( static function ( $cls ) {
                return [
                    'id'   => $cls['id'] ?? '',
                    'name' => $cls['name'] ?? '',
                ];
            }, $spell['classes'] ?? [] ),
            'preview'    => drak_build_spell_preview( $spell ),
            'paragraphs' => drak_build_spell_paragraphs( $spell ),
            'entries'    => $spell['entries'] ?? [],
        ];
    }

    wp_send_json_success(
        [
            'spells' => $matches,
            'total'  => $total,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_search_spells', 'drak_dnd5_search_spells' );
add_action( 'wp_ajax_nopriv_drak_dnd5_search_spells', 'drak_dnd5_search_spells' );

/**
 * Carga el JSON local de razas
 */
function drak_get_local_dnd_races_data() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $path = drak_locate_theme_data_file( 'dnd-races-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-races.json' );
    }
    if (!$path) {
        return null;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    if ( isset( $data['races'] ) && is_array( $data['races'] ) ) {
        foreach ( $data['races'] as &$race ) {
            if ( ! empty( $race['entries_es'] ) && is_array( $race['entries_es'] ) ) {
                $race['entries'] = $race['entries_es'];
            }
        }
        unset( $race );
    }

    $cached = $data;
    return $cached;
}

/**
 * AJAX: lista de razas (desde dnd-races.json)
 */
function drak_dnd5_get_races() {
    $data = drak_get_local_dnd_races_data();

    if (!$data || empty($data['races']) || !is_array($data['races'])) {
        wp_send_json_error(['message' => 'No se pudo cargar dnd-races.json']);
    }

    $out  = [];
    $seen = [];

    foreach ($data['races'] as $race) {
        $name_es = $race['name']['es'] ?? '';
        $name_en = $race['name']['en'] ?? '';

        if ($name_es === '' && $name_en === '') {
            continue;
        }

        // Evitar duplicados por nombre en español
        $slug = sanitize_title($name_es ?: $name_en);
        if (isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;

        $out[] = [
            'id'      => $race['id'] ?? '',
            'name'    => $name_es ?: $name_en, // mostramos siempre ES si existe
            'name_en' => $name_en,
            'source'  => $race['source'] ?? '',
        ];
    }

    usort($out, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    wp_send_json_success(['races' => $out]);
}
add_action('wp_ajax_drak_dnd5_get_races', 'drak_dnd5_get_races');
add_action('wp_ajax_nopriv_drak_dnd5_get_races', 'drak_dnd5_get_races');

/**
 * Carga un JSON de /data y devuelve el array del key indicado.
 */
function drak_get_local_dnd_list( $filename, $root_key ) {
    static $cache = [];

    if ( isset( $cache[ $filename ] ) ) {
        return $cache[ $filename ];
    }

    $path = drak_locate_theme_data_file( $filename );
    if ( ! $path ) {
        return [];
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );

    if ( ! is_array( $data ) || ! isset( $data[ $root_key ] ) || ! is_array( $data[ $root_key ] ) ) {
        return [];
    }

    $cache[ $filename ] = $data[ $root_key ];
    return $cache[ $filename ];
}

function drak_get_local_dnd_actions() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-actions-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-actions.json' );
    }
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['actions'] ) ) {
        $cache = [];
        return $cache;
    }

    $actions = is_array( $data['actions'] ?? null ) ? $data['actions'] : [];
    foreach ( $actions as &$action ) {
        if ( ! empty( $action['entries_es'] ) && is_array( $action['entries_es'] ) ) {
            $action['entries'] = $action['entries_es'];
        }
    }
    unset( $action );

    $cache = $actions;
    return $cache;
}

/**
 * Devuelve el catálogo completo de feats (prioriza ES si existe).
 *
 * @return array
 */
function drak_get_local_dnd_feats() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-feats-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-feats.json' );
    }
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['feats'] ) ) {
        $cache = [];
        return $cache;
    }

    $cache = is_array( $data['feats'] ?? null ) ? $data['feats'] : [];
    return $cache;
}

/**
 * Índice de feats por ID o slug normalizado.
 *
 * @return array<string,array>
 */
function drak_get_local_dnd_feats_map() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $cache = [];
    $feats = drak_get_local_dnd_feats();
    foreach ( $feats as $feat ) {
        if ( ! is_array( $feat ) ) {
            continue;
        }
        $raw_name = $feat['name']['es'] ?? $feat['name']['en'] ?? ( is_string( $feat['name'] ?? null ) ? $feat['name'] : '' );
        $name     = drak_strip_5e_markup( $raw_name ?: '' );
        $id       = isset( $feat['id'] ) ? (string) $feat['id'] : '';

        if ( $id ) {
            $cache[ $id ] = $feat;
        }

        $slug = sanitize_title( $id ?: $name );
        if ( $slug && ! isset( $cache[ $slug ] ) ) {
            $cache[ $slug ] = $feat;
        }
    }

    return $cache;
}

/**
 * Genera un resumen corto del feat a partir de sus entries.
 *
 * @param array $feat
 * @return string
 */
function drak_extract_feat_summary( $feat ) {
    $entries = $feat['entries'] ?? [];
    $text    = '';

    if ( is_string( $entries ) ) {
        $text = $entries;
    } elseif ( is_array( $entries ) ) {
        foreach ( $entries as $entry ) {
            if ( is_string( $entry ) && trim( $entry ) !== '' ) {
                $text = $entry;
                break;
            }
            if ( isset( $entry['entry'] ) && is_string( $entry['entry'] ) ) {
                $text = $entry['entry'];
                break;
            }
            if ( isset( $entry['entries'] ) ) {
                $nested = $entry['entries'];
                if ( is_string( $nested ) && trim( $nested ) !== '' ) {
                    $text = $nested;
                    break;
                }
                if ( is_array( $nested ) ) {
                    foreach ( $nested as $inner ) {
                        if ( is_string( $inner ) && trim( $inner ) !== '' ) {
                            $text = $inner;
                            break 2;
                        }
                    }
                }
            }
        }
    }

    $clean = drak_strip_5e_markup( $text );
    if ( $clean === '' ) {
        return '';
    }

    $limit  = 220;
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean ) : strlen( $clean );
    if ( $length > $limit ) {
        $slice = function_exists( 'mb_substr' ) ? mb_substr( $clean, 0, 200 ) : substr( $clean, 0, 200 );
        return rtrim( $slice ) . '…';
    }

    return $clean;
}

/**
 * Normaliza una lista de feats (ids + fuente) aplicando filtros y evitando duplicados.
 *
 * @param mixed $raw_list
 * @return array<int,array{feat_id:string,feat_source:string}>
 */
function drak_normalize_character_feats( $raw_list ) {
    $list = is_array( $raw_list ) ? $raw_list : [];
    $out  = [];
    $seen = [];

    foreach ( $list as $entry ) {
        $id     = '';
        $source = '';

        if ( is_array( $entry ) ) {
            $id     = $entry['feat_id'] ?? $entry['id'] ?? '';
            $source = $entry['feat_source'] ?? $entry['source'] ?? '';
        } elseif ( is_string( $entry ) ) {
            $id     = $entry;
            $source = 'XPHB';
        }

        $id     = sanitize_text_field( wp_unslash( $id ) );
        $source = strtoupper( sanitize_text_field( wp_unslash( $source ?: 'XPHB' ) ) );

        if ( $id === '' || $source !== 'XPHB' ) {
            continue; // Por ahora solo permitimos XPHB.
        }

        $key = strtolower( $id ) . '|' . $source;
        if ( isset( $seen[ $key ] ) ) {
            continue;
        }
        $seen[ $key ] = true;

        $out[] = [
            'feat_id'     => $id,
            'feat_source' => $source,
        ];
    }

    return array_values( $out );
}

/**
 * Lee el repeater de feats del personaje en formato normalizado.
 *
 * @param int $post_id
 * @return array<int,array{feat_id:string,feat_source:string}>
 */
function drak_get_character_feats( $post_id ) {
    if ( ! $post_id ) {
        return [];
    }

    $raw = get_field( 'feats', $post_id );
    if ( ! is_array( $raw ) ) {
        return [];
    }

    return drak_normalize_character_feats( $raw );
}

/**
 * Enriquecer feats con nombre/resumen para enviarlos al frontend.
 *
 * @param array $feats   Lista normalizada [{feat_id, feat_source}]
 * @param array $catalog Mapa de feats por id
 *
 * @return array<int,array{id:string,source:string,name:string,summary:string,entriesHtml:string,meta:string}>
 */
function drak_build_character_feat_payload( $feats, $catalog ) {
    $payload = [];

    foreach ( $feats as $feat ) {
        $id     = $feat['feat_id'] ?? '';
        $source = $feat['feat_source'] ?? '';
        if ( ! $id ) {
            continue;
        }

        $match   = $catalog[ $id ] ?? $catalog[ sanitize_title( $id ) ] ?? null;
        $rawName = '';
        if ( $match ) {
            $rawName = $match['name']['es'] ?? $match['name']['en'] ?? ( is_string( $match['name'] ?? null ) ? $match['name'] : '' );
        }
        $name    = $rawName ? drak_strip_5e_markup( $rawName ) : $id;
        $summary = $match ? drak_extract_feat_summary( $match ) : '';
        $entries = $match['entries_es'] ?? $match['entries'] ?? [];
        $body    = drak_render_5e_entries_html( $entries );
        $meta    = [];
        if ( ! empty( $match['source'] ) ) {
            $meta[] = $match['source'];
        }
        $meta_text = implode( ' · ', $meta );

        $payload[] = [
            'id'      => $id,
            'source'  => $source,
            'name'    => $name,
            'summary' => $summary,
            'entriesHtml' => $body,
            'meta'    => $meta_text,
        ];
    }

    return $payload;
}

/**
 * Lee el JSON con los rasgos de clase/subclase.
 */
function drak_get_local_dnd_class_features_data() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    // Prioriza el JSON en español del tema hijo.
    $path = trailingslashit( get_stylesheet_directory() ) . 'jsons/dnd-class-features-es.json';
    if ( ! file_exists( $path ) ) {
        $path = drak_locate_theme_data_file( 'dnd-class-features-es.json' );
    }
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-class-features.json' );
    }
    if ( ! $path ) {
        $cache = [
            'classFeatures'    => [],
            'subclassFeatures' => [],
        ];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) ) {
        $cache = [
            'classFeatures'    => [],
            'subclassFeatures' => [],
        ];
        return $cache;
    }

    $defaults = [
        'classFeatures'    => [],
        'subclassFeatures' => [],
    ];

    $cache = drak_localize_class_features_data( array_merge( $defaults, $data ) );
    return $cache;
}

function drak_localize_class_features_data( $data ) {
    foreach ( ['classFeatures', 'subclassFeatures'] as $key ) {
        if ( empty( $data[ $key ] ) ) {
            continue;
        }
        if ( 'subclassFeatures' === $key ) {
            foreach ( $data[ $key ] as $subKey => $list ) {
                if ( ! is_array( $list ) ) {
                    continue;
                }
                $data[ $key ][ $subKey ] = array_map( 'drak_localize_feature_entry', $list );
            }
        } else {
            $data[ $key ] = array_map( 'drak_localize_feature_entry', $data[ $key ] );
        }
    }
    return $data;
}

function drak_localize_feature_entry( $entry ) {
    if ( ! is_array( $entry ) ) {
        return $entry;
    }

    if ( isset( $entry['name_es'] ) && $entry['name_es'] !== '' ) {
        $entry['name'] = $entry['name_es'];
    }

    if ( ! empty( $entry['entries_es'] ) && is_array( $entry['entries_es'] ) ) {
        $entry['entries'] = $entry['entries_es'];
    }

    if ( ! empty( $entry['shortEntries_es'] ) && is_array( $entry['shortEntries_es'] ) ) {
        $entry['shortEntries'] = $entry['shortEntries_es'];
    }

    return $entry;
}

/**
 * Índices rápidos para clases y subclases.
 */
function drak_get_local_dnd_class_lookup() {
    static $lookup = null;
    if ( $lookup !== null ) {
        return $lookup;
    }

    $data = drak_get_local_dnd_classes_data();
    $result = [
        'classes'    => [],
        'subclasses' => [],
    ];

    if ( ! $data || empty( $data['classes'] ) ) {
        $lookup = $result;
        return $lookup;
    }

    foreach ( $data['classes'] as $class ) {
        $class_id = $class['id'] ?? '';
        if ( ! $class_id ) {
            continue;
        }

        $result['classes'][ $class_id ] = $class;

        if ( empty( $class['subclasses'] ) || ! is_array( $class['subclasses'] ) ) {
            continue;
        }

        foreach ( $class['subclasses'] as $sub ) {
            $sub_id = $sub['id'] ?? '';
            if ( ! $sub_id ) {
                continue;
            }

            $result['subclasses'][ $sub_id ] = [
                'class_id' => $class_id,
                'data'     => $sub,
            ];
        }
    }

    $lookup = $result;
    return $lookup;
}

function drak_get_local_dnd_backgrounds() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-backgrounds-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-backgrounds.json' );
    }
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['backgrounds'] ) ) {
        $cache = [];
        return $cache;
    }

    $backgrounds = is_array( $data['backgrounds'] ?? null ) ? $data['backgrounds'] : [];
    foreach ( $backgrounds as &$bg ) {
        if ( ! empty( $bg['entries_es'] ) && is_array( $bg['entries_es'] ) ) {
            $bg['entries'] = $bg['entries_es'];
        }
    }
    unset( $bg );

    $cache = $backgrounds;
    return $cache;
}

function drak_get_local_dnd_spells() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-spells-es.json' );
    if ( ! $path ) {
        $path = drak_locate_theme_data_file( 'dnd-spells.json' );
    }
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['spells'] ) ) {
        $cache = [];
        return $cache;
    }

    $spells = is_array( $data['spells'] ?? null ) ? $data['spells'] : [];
    $spells = array_map( 'drak_localize_spell_data', $spells );

    $apothecary_spells = drak_get_apothecary_spell_list();
    if ( ! empty( $apothecary_spells ) ) {
        $spells = drak_merge_apothecary_spell_list( $spells, $apothecary_spells );
    }

    $cache = $spells;
    return $cache;
}

function drak_localize_spell_data( $spell ) {
    if ( ! is_array( $spell ) ) {
        return $spell;
    }

    if ( ! empty( $spell['entries_es'] ) && is_array( $spell['entries_es'] ) ) {
        $spell['entries'] = $spell['entries_es'];
    }

    if ( ! empty( $spell['entriesHigherLevel_es'] ) && is_array( $spell['entriesHigherLevel_es'] ) ) {
        $spell['entriesHigherLevel'] = $spell['entriesHigherLevel_es'];
    }

    if ( ! empty( $spell['entriesAlt_es'] ) && is_array( $spell['entriesAlt_es'] ) ) {
        $spell['entriesAlt'] = $spell['entriesAlt_es'];
    }

    return $spell;
}

function drak_normalize_spell_name_for_matching( $name ) {
    $normalized = strtolower( trim( (string) $name ) );
    $normalized = preg_replace( '/\s+/', ' ', $normalized );
    return $normalized;
}

function drak_get_apothecary_class_ref() {
    return [
        'id'     => 'apothecary-scgtd-drakkenheim',
        'name'   => 'Apothecary',
        'source' => 'SCGTD',
    ];
}

function drak_get_apothecary_spell_list() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $path = drak_locate_theme_data_file( 'dnd-spells-apothecary.json' );
    if ( ! $path ) {
        $cache = [];
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['spells'] ) || ! is_array( $data['spells'] ) ) {
        $cache = [];
        return $cache;
    }

    $result = [];
    foreach ( $data['spells'] as $spell ) {
        if ( ! is_array( $spell ) || empty( $spell['name'] ) ) {
            continue;
        }
        $result[] = [
            'name'     => (string) $spell['name'],
            'level'    => isset( $spell['level'] ) ? intval( $spell['level'] ) : 0,
            'source'   => ! empty( $spell['source'] ) ? (string) $spell['source'] : 'SCGTD',
            'ritual'   => ! empty( $spell['ritual'] ),
            'homebrew' => ! empty( $spell['homebrew'] ),
        ];
    }

    $cache = $result;
    return $cache;
}

function drak_slugify_spell_name( $name ) {
    $slug = strtolower( (string) $name );
    $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
    $slug = trim( $slug, '-' );
    return $slug ? $slug . '-scgtd-homebrew' : 'apothecary-custom';
}

function drak_merge_apothecary_spell_list( $spells, $apothecary_list ) {
    $index = [];
    foreach ( $spells as $idx => $spell ) {
        $norm = drak_normalize_spell_name_for_matching( $spell['name'] ?? '' );
        if ( $norm !== '' && ! isset( $index[ $norm ] ) ) {
            $index[ $norm ] = $idx;
        }
    }

    $class_ref = drak_get_apothecary_class_ref();

    foreach ( $apothecary_list as $ap_spell ) {
        $norm = drak_normalize_spell_name_for_matching( $ap_spell['name'] ?? '' );
        if ( '' === $norm ) {
            continue;
        }

        if ( isset( $index[ $norm ] ) ) {
            $target_idx = $index[ $norm ];
            if ( empty( $spells[ $target_idx ]['classes'] ) || ! is_array( $spells[ $target_idx ]['classes'] ) ) {
                $spells[ $target_idx ]['classes'] = [];
            }

            $exists = false;
            foreach ( $spells[ $target_idx ]['classes'] as $cls ) {
                if ( isset( $cls['id'] ) && $cls['id'] === $class_ref['id'] ) {
                    $exists = true;
                    break;
                }
            }

            if ( ! $exists ) {
                $spells[ $target_idx ]['classes'][] = $class_ref;
            }
            // Propaga la marca de ritual si el apotecario lo tiene marcado.
            if ( ! empty( $ap_spell['ritual'] ) ) {
                if ( isset( $spells[ $target_idx ]['meta'] ) && is_array( $spells[ $target_idx ]['meta'] ) ) {
                    $spells[ $target_idx ]['meta']['ritual'] = true;
                } else {
                    $spells[ $target_idx ]['ritual'] = true;
                }
            }
            continue;
        }

        $new_spell = [
            'id'      => drak_slugify_spell_name( $ap_spell['name'] ),
            'name'    => $ap_spell['name'],
            'level'   => intval( $ap_spell['level'] ?? 0 ),
            'source'  => $ap_spell['source'] ?? 'SCGTD',
            'entries' => [],
            'classes' => [ $class_ref ],
        ];

        if ( ! empty( $ap_spell['ritual'] ) ) {
            $new_spell['meta'] = [ 'ritual' => true ];
            $new_spell['ritual'] = true;
        }
        if ( ! empty( $ap_spell['homebrew'] ) ) {
            $new_spell['homebrew'] = true;
        }

        $spells[] = $new_spell;
    }

    return $spells;
}

function drak_get_spellcasting_classes() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    $data = drak_get_local_dnd_classes_data();
    if ( ! $data || empty( $data['classes'] ) || ! is_array( $data['classes'] ) ) {
        $cache = [];
        return $cache;
    }

    $unique = [];
    foreach ( $data['classes'] as $class ) {
        $class_id = $class['id'] ?? '';
        if ( ! $class_id ) {
            continue;
        }
        $details = drak_get_class_detail_entry( $class_id );
        if ( empty( $details['spellcastingAbility'] ) ) {
            continue;
        }

        $slug = sanitize_title( $class['name'] ?? $class_id );
        if ( isset( $unique[ $slug ] ) ) {
            continue;
        }

        $unique[ $slug ] = [
            'id'    => $class_id,
            'name'  => $class['name'] ?? $class_id,
            'short' => $class['shortName'] ?? ( $class['name'] ?? $class_id ),
        ];
    }

    $cache = array_values( $unique );
    return $cache;
}

function drak_get_apothecary_class_ids() {
    return [ 'apothecary-scgtd-drakkenheim' ];
}

function drak_is_apothecary_class( $class_id ) {
    if ( ! $class_id ) {
        return false;
    }
    $class_id = strtolower( $class_id );
    if ( in_array( $class_id, drak_get_apothecary_class_ids(), true ) ) {
        return true;
    }
    // Fallback: cualquier id que empiece por "apothecary"
    return strpos( $class_id, 'apothecary' ) === 0;
}

function drak_is_sorcerer_class( $class_id ) {
    if ( ! $class_id ) {
        return false;
    }
    $id = strtolower( $class_id );
    if ( strpos( $id, 'sorcerer' ) === 0 ) {
        return true;
    }
    $details = drak_get_class_detail_entry( $class_id );
    $name    = strtolower( $details['name'] ?? '' );
    return in_array( $name, [ 'sorcerer', 'hechicero' ], true );
}

function drak_get_sorcery_slot_costs() {
    return [
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
        5 => 7,
    ];
}

function drak_get_sorcery_points_max( $post_id, $class_id = '', $level = 0 ) {
    $class_id = $class_id ?: get_field( 'clase', $post_id );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        return 0;
    }
    $lvl = $level ?: intval( get_field( 'nivel', $post_id ) );
    $lvl = max( 1, intval( $lvl ) );
    return min( 20, $lvl );
}

function drak_get_sorcery_points_current( $post_id, $max = null ) {
    $max = ( null === $max ) ? drak_get_sorcery_points_max( $post_id ) : intval( $max );
    if ( $max <= 0 ) {
        return 0;
    }
    $raw     = get_post_meta( $post_id, 'grimorio_sorcery_points', true );
    $current = is_numeric( $raw ) ? intval( $raw ) : $max;
    return max( 0, min( $current, $max ) );
}

function drak_save_sorcery_points_current( $post_id, $value, $max = null ) {
    $max     = ( null === $max ) ? drak_get_sorcery_points_max( $post_id ) : intval( $max );
    $current = max( 0, min( intval( $value ), $max ) );
    update_post_meta( $post_id, 'grimorio_sorcery_points', $current );
    return $current;
}

function drak_get_sorcery_flexible_slots( $post_id ) {
    $raw = get_post_meta( $post_id, 'grimorio_sorcery_flex_slots', true );
    if ( is_string( $raw ) && $raw !== '' ) {
        $decoded = json_decode( $raw, true );
    } else {
        $decoded = is_array( $raw ) ? $raw : [];
    }
    $clean = [];
    foreach ( (array) $decoded as $level => $count ) {
        $lvl = intval( $level );
        if ( $lvl < 1 || $lvl > 9 ) {
            continue;
        }
        $clean[ $lvl ] = max( 0, intval( $count ) );
    }
    ksort( $clean );
    return $clean;
}

function drak_save_sorcery_flexible_slots( $post_id, $slots ) {
    $clean = [];
    foreach ( (array) $slots as $level => $count ) {
        $lvl = intval( $level );
        if ( $lvl < 1 || $lvl > 9 ) {
            continue;
        }
        $clean[ $lvl ] = max( 0, intval( $count ) );
    }
    ksort( $clean );
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_post_meta( $post_id, 'grimorio_sorcery_flex_slots', $payload );
    return $clean;
}

function drak_reset_sorcery_resources( $post_id, $class_id = '', $level = 0 ) {
    $max_points = drak_get_sorcery_points_max( $post_id, $class_id, $level );
    if ( $max_points <= 0 ) {
        return [
            'points_current' => 0,
            'points_max'     => 0,
            'flex_slots'     => [],
        ];
    }

    drak_save_sorcery_points_current( $post_id, $max_points, $max_points );
    drak_save_sorcery_flexible_slots( $post_id, [] );

    $slots_used = drak_grimorio_get_slots( $post_id );
    $slot_row   = drak_get_class_spell_slots_for_level( $class_id, $level );
    if ( empty( $slot_row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $slot_row = $fallback[ max( 1, min( 20, $level ) ) ] ?? [];
    }
    $updated = false;
    foreach ( $slots_used as $lvl => $used ) {
        $base_cap = intval( $slot_row[ $lvl ] ?? 0 );
        if ( $base_cap <= 0 ) {
            continue;
        }
        if ( $used > $base_cap ) {
            $slots_used[ $lvl ] = $base_cap;
            $updated            = true;
        }
    }
    if ( $updated ) {
        drak_grimorio_save_slots( $post_id, $slots_used );
    }

    return [
        'points_current' => $max_points,
        'points_max'     => $max_points,
        'flex_slots'     => [],
    ];
}

function drak_get_sorcerer_metamagic_known( $post_id ) {
    $raw = get_post_meta( $post_id, 'grimorio_metamagic_known', true );
    if ( is_string( $raw ) && $raw !== '' ) {
        $decoded = json_decode( $raw, true );
    } else {
        $decoded = is_array( $raw ) ? $raw : [];
    }
    $clean = [];
    foreach ( (array) $decoded as $id ) {
        $id = sanitize_key( $id );
        if ( $id === '' ) {
            continue;
        }
        if ( ! in_array( $id, $clean, true ) ) {
            $clean[] = $id;
        }
    }
    return $clean;
}

function drak_save_sorcerer_metamagic_known( $post_id, $list, $limit = null ) {
    $clean = [];
    $limit = ( null === $limit ) ? PHP_INT_MAX : max( 0, intval( $limit ) );
    foreach ( (array) $list as $id ) {
        $id = sanitize_key( $id );
        if ( $id === '' ) {
            continue;
        }
        if ( in_array( $id, $clean, true ) ) {
            continue;
        }
        $clean[] = $id;
        if ( count( $clean ) >= $limit ) {
            break;
        }
    }
    $payload = empty( $clean ) ? '' : wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
    update_post_meta( $post_id, 'grimorio_metamagic_known', $payload );
    return $clean;
}

function drak_get_sorcerer_metamagic_limit( $level ) {
    $level = intval( $level );
    if ( $level < 3 ) {
        return 0;
    }
    if ( $level >= 17 ) {
        return 4;
    }
    if ( $level >= 10 ) {
        return 3;
    }
    return 2;
}

/**
 * Progresión tipo warlock: array nivel => [ 'slots' => n, 'slot_level' => m ].
 */
function drak_get_warlock_like_slot_progression() {
    return [
        1  => ['slots' => 1, 'slot_level' => 1],
        2  => ['slots' => 2, 'slot_level' => 1],
        3  => ['slots' => 2, 'slot_level' => 2],
        4  => ['slots' => 2, 'slot_level' => 2],
        5  => ['slots' => 2, 'slot_level' => 3],
        6  => ['slots' => 2, 'slot_level' => 3],
        7  => ['slots' => 2, 'slot_level' => 4],
        8  => ['slots' => 2, 'slot_level' => 4],
        9  => ['slots' => 2, 'slot_level' => 5],
        10 => ['slots' => 2, 'slot_level' => 5],
        11 => ['slots' => 3, 'slot_level' => 5],
        12 => ['slots' => 3, 'slot_level' => 5],
        13 => ['slots' => 3, 'slot_level' => 5],
        14 => ['slots' => 3, 'slot_level' => 5],
        15 => ['slots' => 3, 'slot_level' => 5],
        16 => ['slots' => 3, 'slot_level' => 5],
        17 => ['slots' => 4, 'slot_level' => 5],
        18 => ['slots' => 4, 'slot_level' => 5],
        19 => ['slots' => 4, 'slot_level' => 5],
        20 => ['slots' => 4, 'slot_level' => 5],
    ];
}

function drak_get_apothecary_slot_progression() {
    return drak_get_warlock_like_slot_progression();
}

function drak_get_apothecary_slots_state( $post_id, $level = null ) {
    $progression = drak_get_apothecary_slot_progression();
    $level       = $level ? intval( $level ) : intval( get_field( 'nivel', $post_id ) );
    $level       = max( 1, min( 20, $level ) );
    $prog        = $progression[ $level ] ?? [ 'slots' => 0, 'slot_level' => 1 ];
    $max         = intval( $prog['slots'] );
    $slot_level  = intval( $prog['slot_level'] );

    $stored = get_post_meta( $post_id, 'apothecary_slots_state', true );
    $stored = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
    $current = $max;
    if ( is_array( $stored ) && isset( $stored['current'] ) ) {
        $current = max( 0, min( intval( $stored['current'] ), $max ) );
    }

    return [
        'max'        => $max,
        'current'    => $current,
        'slot_level' => $slot_level,
        'recovery'   => 'short_rest',
    ];
}

function drak_save_apothecary_slots_state( $post_id, $state ) {
    update_post_meta( $post_id, 'apothecary_slots_state', wp_json_encode( $state ) );
    return $state;
}

function drak_reset_apothecary_formulas( $post_id ) {
    $stored = get_post_meta( $post_id, 'apothecary_formulas_state', true );
    $data   = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
    if ( ! is_array( $data ) ) {
        return;
    }
    foreach ( $data as &$formula ) {
        if ( isset( $formula['usesMax'] ) ) {
            $formula['usesCurrent'] = intval( $formula['usesMax'] );
        }
    }
    unset( $formula );
    update_post_meta( $post_id, 'apothecary_formulas_state', wp_json_encode( $data ) );
}

function drak_get_apothecary_formulas_state( $post_id, $class_id = '', $subclass_id = '', $level = 0 ) {
    $stored = get_post_meta( $post_id, 'apothecary_formulas_state', true );
    $data   = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
    if ( is_array( $data ) ) {
        return $data;
    }
    return [];
}

function drak_get_apothecary_always_prepared_spells( $class_id = '', $subclass_id = '' ) {
    // Si se quieren enlazar a las prácticas ocultas, mapear aquí. De momento vacío.
    return [];
}

function drak_esoteric_theory_id( $name ) {
    $base = sanitize_title( $name );
    if ( ! $base ) {
        $base = 'theory-' . substr( md5( (string) $name ), 0, 8 );
    }
    return 'apothecary-theory-' . $base;
}

function drak_get_apothecary_theories_catalog() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }

    $cache = [];
    $path  = drak_locate_theme_data_file( 'esotherics.json' );
    if ( ! $path ) {
        // Fallback: ruta directa al directorio jsons del tema hijo.
        $candidate = trailingslashit( get_stylesheet_directory() ) . 'jsons/esotherics.json';
        if ( file_exists( $candidate ) ) {
            $path = $candidate;
        }
    }
    if ( ! $path ) {
        // Fallback adicional: misma ruta pero usando __DIR__ por si falla el theme dir.
        $candidate = trailingslashit( dirname( __FILE__ ) ) . 'jsons/esotherics.json';
        if ( file_exists( $candidate ) ) {
            $path = $candidate;
        }
    }
    if ( ! $path ) {
        return $cache;
    }

    $json = file_get_contents( $path );
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || empty( $data['classFeature'] ) ) {
        return $cache;
    }

    foreach ( $data['classFeature'] as $entry ) {
        if ( empty( $entry['name'] ) ) {
            continue;
        }
        $id = isset( $entry['id'] ) && $entry['id'] !== '' ? $entry['id'] : drak_esoteric_theory_id( $entry['name'] );
        if ( isset( $cache[ $id ] ) ) {
            $id .= '-' . substr( md5( $entry['name'] . wp_rand() ), 0, 4 );
        }
        $entry['id']    = $id;
        $entry['level'] = isset( $entry['level'] ) ? intval( $entry['level'] ) : 0;
        $cache[ $id ]   = $entry;
    }

    return $cache;
}

function drak_parse_apothecary_theory_ids( $raw ) {
    if ( is_array( $raw ) ) {
        return array_values( array_filter( array_map( 'strval', $raw ) ) );
    }

    if ( ! is_string( $raw ) || $raw === '' ) {
        return [];
    }

    $raw = trim( $raw );
    if ( $raw === '' ) {
        return [];
    }

    if ( $raw[0] === '[' ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            return array_values( array_filter( array_map( 'strval', $decoded ) ) );
        }
    }

    $parts = array_map( 'trim', explode( ',', $raw ) );
    return array_values( array_filter( array_map( 'strval', $parts ) ) );
}

function drak_filter_apothecary_theory_selection( $ids, $class_id, $level ) {
    if ( ! drak_is_apothecary_class( $class_id ) ) {
        return [];
    }

    $level    = max( 0, intval( $level ) );
    $catalog  = drak_get_apothecary_theories_catalog();
    $selected = [];

    foreach ( $ids as $id ) {
        if ( ! isset( $catalog[ $id ] ) ) {
            continue;
        }
        $required_level = intval( $catalog[ $id ]['level'] ?? 0 );
        if ( $required_level > $level ) {
            continue;
        }
        $selected[ $id ] = true;
    }

    return array_keys( $selected );
}

function drak_save_apothecary_theory_selection( $post_id, $ids ) {
    $ids = array_values( $ids );
    if ( empty( $ids ) ) {
        delete_post_meta( $post_id, 'apothecary_theories' );
        return;
    }

    update_post_meta( $post_id, 'apothecary_theories', wp_json_encode( $ids ) );
}

function drak_get_character_apothecary_theories( $post_id ) {
    if ( ! $post_id ) {
        return [];
    }
    $raw = get_post_meta( $post_id, 'apothecary_theories', true );
    return drak_parse_apothecary_theory_ids( $raw );
}

function drak_format_apothecary_theories_display( $ids ) {
    $catalog = drak_get_apothecary_theories_catalog();
    $names   = [];
    foreach ( $ids as $id ) {
        if ( isset( $catalog[ $id ]['name'] ) ) {
            $names[] = $catalog[ $id ]['name'];
        }
    }
    return implode( ', ', $names );
}

function drak_expand_apothecary_theories( $ids ) {
    $catalog = drak_get_apothecary_theories_catalog();
    $out     = [];
    foreach ( $ids as $id ) {
        if ( ! isset( $catalog[ $id ] ) ) {
            continue;
        }
        $entry = $catalog[ $id ];
        $out[] = [
            'id'      => $entry['id'],
            'name'    => $entry['name'] ?? $entry['id'],
            'source'  => $entry['source'] ?? '',
            'page'    => $entry['page'] ?? '',
            'level'   => intval( $entry['level'] ?? 0 ),
            'entries' => $entry['entries'] ?? [],
        ];
    }
    return $out;
}

function drak_sanitize_apothecary_theory_submission( $raw_value, $class_id = '', $level = 0 ) {
    $ids = drak_parse_apothecary_theory_ids( $raw_value );
    if ( ! $class_id ) {
        return $ids;
    }
    return drak_filter_apothecary_theory_selection( $ids, $class_id, $level );
}

/**
 * Devuelve listas de armas, armaduras, herramientas e idiomas para el modal.
 */
function drak_dnd5_get_proficiencies() {
    $weapons   = drak_get_local_dnd_list( 'dnd-weapons.json',   'weapons' );
    $armors    = drak_get_local_dnd_list( 'dnd-armors.json',    'armors' );
    $tools     = drak_get_local_dnd_list( 'dnd-tools.json',     'tools' );
    $languages = drak_get_local_dnd_list( 'dnd-languages.json', 'languages' );

    // Nos quedamos solo con id + name (preferimos español si existe)
    $map = function( $item ) {
        $raw_name = isset( $item['name'] ) ? $item['name'] : '';

        if ( is_array( $raw_name ) ) {
            // Estructura tipo: "name": { "en": "...", "es": "..." }
            $name = $raw_name['es'] ?? $raw_name['en'] ?? '';
        } else {
            $name = $raw_name;
        }

        return [
            'id'   => isset( $item['id'] ) ? $item['id'] : '',
            'name' => $name,
        ];
    };


    $data = [
        'weapons'   => array_map( $map, $weapons ),
        'armors'    => array_map( $map, $armors ),
        'tools'     => array_map( $map, $tools ),
        'languages' => array_map( $map, $languages ),
    ];

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_drak_dnd5_get_proficiencies',        'drak_dnd5_get_proficiencies' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_proficiencies', 'drak_dnd5_get_proficiencies' );

function drak_dnd5_get_weapons_full() {
    $weapons = drak_get_local_dnd_list( 'dnd-weapons.json', 'weapons' );
    wp_send_json_success(
        [
            'weapons' => $weapons,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_get_weapons_full', 'drak_dnd5_get_weapons_full' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_weapons_full', 'drak_dnd5_get_weapons_full' );

function drak_dnd5_get_armors_full() {
    $armors = drak_get_local_dnd_list( 'dnd-armors-es.json', 'armors' );
    if ( empty( $armors ) ) {
        $armors = drak_get_local_dnd_list( 'dnd-armors.json', 'armors' );
    }
    wp_send_json_success(
        [
            'armors' => $armors,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_get_armors_full', 'drak_dnd5_get_armors_full' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_armors_full', 'drak_dnd5_get_armors_full' );

function drak_dnd5_get_actions() {
    $actions = drak_get_local_dnd_actions();
    wp_send_json_success( [ 'actions' => $actions ] );
}
add_action( 'wp_ajax_drak_dnd5_get_actions', 'drak_dnd5_get_actions' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_actions', 'drak_dnd5_get_actions' );

function drak_dnd5_get_esoteric_theories() {
    $catalog = drak_get_apothecary_theories_catalog();
    wp_send_json_success(
        [
            'theories' => array_values( $catalog ),
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_get_esoteric_theories', 'drak_dnd5_get_esoteric_theories' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_esoteric_theories', 'drak_dnd5_get_esoteric_theories' );

function drak_dnd5_get_backgrounds() {
    $backgrounds = drak_get_local_dnd_backgrounds();
    wp_send_json_success( [ 'backgrounds' => $backgrounds ] );
}
add_action( 'wp_ajax_drak_dnd5_get_backgrounds', 'drak_dnd5_get_backgrounds' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_backgrounds', 'drak_dnd5_get_backgrounds' );

function drak_dnd5_get_class_reference() {
    $class_id    = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $subclass_id = isset( $_POST['subclass_id'] ) ? sanitize_text_field( wp_unslash( $_POST['subclass_id'] ) ) : '';
    $raw_theories = isset( $_POST['apothecary_theories'] ) ? wp_unslash( $_POST['apothecary_theories'] ) : '';
    $selected_theories = drak_parse_apothecary_theory_ids( $raw_theories );

    if ( ! $class_id ) {
        wp_send_json_error( [ 'message' => 'Falta el parámetro class_id.' ], 400 );
    }

    $reference = drak_get_class_reference_payload( $class_id, $subclass_id, $selected_theories );
    if ( ! $reference ) {
        $debug = drak_get_class_reference_error();
        drak_set_class_reference_error( sprintf( 'drak_get_class_reference_payload devolvió vacío para class_id=%s subclass_id=%s', $class_id, $subclass_id ) );
        wp_send_json_error(
            [
                'message' => 'No se pudo generar la referencia de clase.',
                'debug'   => $debug,
            ],
            404
        );
    }

    wp_send_json_success( [ 'reference' => $reference ] );
}
add_action( 'wp_ajax_drak_dnd5_get_class_reference', 'drak_dnd5_get_class_reference' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_class_reference', 'drak_dnd5_get_class_reference' );

function drak_dnd5_get_spells() {
    $class_id = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $spells   = drak_get_local_dnd_spells();

    if ( ! $class_id ) {
        wp_send_json_success( [ 'spells' => [] ] );
    }

    $filtered = array_values( array_filter( $spells, function ( $spell ) use ( $class_id ) {
        if ( empty( $spell['classes'] ) || ! is_array( $spell['classes'] ) ) {
            return false;
        }

        foreach ( $spell['classes'] as $cls ) {
            if ( isset( $cls['id'] ) && $cls['id'] === $class_id ) {
                return true;
            }
        }

        return false;
    } ) );

    wp_send_json_success( [ 'spells' => $filtered ] );
}
add_action( 'wp_ajax_drak_dnd5_get_spells', 'drak_dnd5_get_spells' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_spells', 'drak_dnd5_get_spells' );

function drak_dnd5_activate_transformation() {
    if ( ! isset( $_POST['post_id'], $_POST['slot_level'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id    = intval( $_POST['post_id'] );
    $slot_level = intval( $_POST['slot_level'] );
    $nonce      = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( $slot_level <= 0 ) {
        wp_send_json_error( [ 'message' => 'Nivel de slot inválido.' ], 400 );
    }

    if ( ! wp_verify_nonce( $nonce, 'grimorio_transformation_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $current_state = drak_grimorio_get_transformation_state( $post_id );
    if ( ! empty( $current_state['active'] ) ) {
        wp_send_json_error( [ 'message' => 'La transformación ya está activa.' ], 400 );
    }

    $slots_used = drak_grimorio_get_slots( $post_id );
    $clean_slots = $slots_used;
    $nivel      = intval( get_field( 'nivel', $post_id ) );
    $clase_id   = get_field( 'clase', $post_id );

    $apothecary_slots = null;
    if ( drak_is_apothecary_class( $clase_id ) ) {
        $apothecary_slots = drak_get_apothecary_slots_state( $post_id, $nivel );
        $slot_level       = intval( $apothecary_slots['slot_level'] ?? $slot_level );

        if ( empty( $apothecary_slots ) || intval( $apothecary_slots['max'] ) <= 0 ) {
            wp_send_json_error( [ 'message' => 'No tienes espacios disponibles.' ], 400 );
        }
        if ( intval( $apothecary_slots['current'] ) <= 0 ) {
            wp_send_json_error( [ 'message' => 'No quedan espacios disponibles.' ], 400 );
        }

        $apothecary_slots['current'] = max( 0, intval( $apothecary_slots['current'] ) - 1 );
        drak_save_apothecary_slots_state( $post_id, $apothecary_slots );
    } else {
        $slot_row = drak_get_class_spell_slots_for_level( $clase_id, $nivel );
        if ( empty( $slot_row ) ) {
            $fallback = drak_get_full_caster_slots_table();
            $slot_row = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
        }

        $slot_cap = intval( $slot_row[ $slot_level ] ?? 0 );
        $used     = intval( $slots_used[ $slot_level ] ?? 0 );

        if ( $slot_cap <= 0 ) {
            wp_send_json_error( [ 'message' => 'No tienes espacios de ese nivel.' ], 400 );
        }

        if ( $used >= $slot_cap ) {
            wp_send_json_error( [ 'message' => 'No quedan espacios disponibles en ese nivel.' ], 400 );
        }

        $slots_used[ $slot_level ] = $used + 1;
        $clean_slots               = drak_grimorio_save_slots( $post_id, $slots_used );
    }

    $saved_state = drak_grimorio_save_transformation_state(
        $post_id,
        [
            'active'     => true,
            'slot_level' => $slot_level,
            'started_at' => time(),
        ]
    );

    wp_send_json_success(
        [
            'slots_used'     => $clean_slots ?? [],
            'apothecary_slots' => $apothecary_slots,
            'transformation' => $saved_state,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_activate_transformation', 'drak_dnd5_activate_transformation' );

function drak_dnd5_finish_transformation() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_transformation_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $state = drak_grimorio_save_transformation_state(
        $post_id,
        [
            'active'     => false,
            'slot_level' => null,
            'started_at' => null,
        ]
    );

    wp_send_json_success( [ 'transformation' => $state ] );
}
add_action( 'wp_ajax_drak_dnd5_finish_transformation', 'drak_dnd5_finish_transformation' );

function drak_dnd5_save_spell_slots() {
    if ( ! isset( $_POST['post_id'], $_POST['level'], $_POST['value'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $level   = intval( $_POST['level'] );
    $value   = intval( $_POST['value'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_slots_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $stored           = drak_grimorio_get_slots( $post_id );
    $stored[ $level ] = max( 0, $value );
    $clean            = drak_grimorio_save_slots( $post_id, $stored );

    wp_send_json_success( [ 'slots' => $clean ] );
}
add_action( 'wp_ajax_drak_dnd5_save_spell_slots', 'drak_dnd5_save_spell_slots' );

/**
 * Guardar slots del modelo Apotecario (pool único).
 */
function drak_dnd5_save_apothecary_slots() {
    if ( ! isset( $_POST['post_id'], $_POST['current'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $current = max( 0, intval( $_POST['current'] ) );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_slots_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $state = drak_get_apothecary_slots_state( $post_id );
    if ( $state ) {
        $state['current'] = min( $current, $state['max'] );
        drak_save_apothecary_slots_state( $post_id, $state );
        wp_send_json_success( [ 'apothecary_slots' => $state ] );
    }

    wp_send_json_error( [ 'message' => 'No se pudo actualizar los slots.' ], 400 );
}
add_action( 'wp_ajax_drak_dnd5_save_apothecary_slots', 'drak_dnd5_save_apothecary_slots' );

function drak_dnd5_save_prepared_spells() {
    if ( ! isset( $_POST['post_id'], $_POST['prepared'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_prepared_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $prepared_raw = wp_unslash( $_POST['prepared'] );
    $decoded      = json_decode( $prepared_raw, true );
    if ( ! is_array( $decoded ) ) {
        wp_send_json_error( [ 'message' => 'Formato inválido.' ], 400 );
    }
    $clean = drak_grimorio_save_prepared( $post_id, $decoded );

    wp_send_json_success( [ 'prepared' => $clean ] );
}
add_action( 'wp_ajax_drak_dnd5_save_prepared_spells', 'drak_dnd5_save_prepared_spells' );

/**
 * Reset de slots/fórmulas para apotecario (descanso corto/largo).
 */
function drak_dnd5_reset_apothecary_resources() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
    $is_long = ! empty( $_POST['is_long'] );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_slots_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $slots = drak_get_apothecary_slots_state( $post_id );
    if ( $slots ) {
        $slots['current'] = $slots['max'];
        drak_save_apothecary_slots_state( $post_id, $slots );
    }

    if ( $is_long ) {
        drak_reset_apothecary_formulas( $post_id );
    }

    wp_send_json_success(
        [
            'apothecary_slots'   => $slots,
            'greater_formulas'   => drak_get_apothecary_formulas_state( $post_id ),
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_reset_apothecary_resources', 'drak_dnd5_reset_apothecary_resources' );

function drak_dnd5_sorcery_slot_to_points() {
    if ( ! isset( $_POST['post_id'], $_POST['level'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $level   = max( 1, intval( $_POST['level'] ) );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_sorcery_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $class_id = get_field( 'clase', $post_id );
    $nivel    = intval( get_field( 'nivel', $post_id ) );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        wp_send_json_error( [ 'message' => 'El personaje no es un Hechicero.' ], 400 );
    }

    $points_max     = drak_get_sorcery_points_max( $post_id, $class_id, $nivel );
    $points_current = drak_get_sorcery_points_current( $post_id, $points_max );
    if ( $points_max <= 0 ) {
        wp_send_json_error( [ 'message' => 'No hay puntos de hechicería configurados.' ], 400 );
    }

    $slot_row = drak_get_class_spell_slots_for_level( $class_id, $nivel );
    if ( empty( $slot_row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $slot_row = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }
    $base_cap   = intval( $slot_row[ $level ] ?? 0 );
    $flex_slots = drak_get_sorcery_flexible_slots( $post_id );
    $extra      = intval( $flex_slots[ $level ] ?? 0 );
    $total_cap  = $base_cap + $extra;
    if ( $total_cap <= 0 ) {
        wp_send_json_error( [ 'message' => 'No tienes espacios disponibles en ese nivel.' ], 400 );
    }

    $slots_used = drak_grimorio_get_slots( $post_id );
    $used       = intval( $slots_used[ $level ] ?? 0 );
    if ( $used >= $total_cap ) {
        wp_send_json_error( [ 'message' => 'No quedan espacios de ese nivel para convertir.' ], 400 );
    }

    $slots_used[ $level ] = $used + 1;
    $clean_slots          = drak_grimorio_save_slots( $post_id, $slots_used );

    $points_current = min( $points_max, $points_current + $level );
    $points_current = drak_save_sorcery_points_current( $post_id, $points_current, $points_max );

    wp_send_json_success(
        [
            'points_current' => $points_current,
            'points_max'     => $points_max,
            'flex_slots'     => $flex_slots,
            'slots_used'     => $clean_slots,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_sorcery_slot_to_points', 'drak_dnd5_sorcery_slot_to_points' );

function drak_dnd5_sorcery_points_to_slot() {
    if ( ! isset( $_POST['post_id'], $_POST['level'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $level   = max( 1, intval( $_POST['level'] ) );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_sorcery_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }
    if ( $level > 5 ) {
        wp_send_json_error( [ 'message' => 'Solo puedes crear espacios hasta nivel 5.' ], 400 );
    }

    $class_id = get_field( 'clase', $post_id );
    $nivel    = intval( get_field( 'nivel', $post_id ) );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        wp_send_json_error( [ 'message' => 'El personaje no es un Hechicero.' ], 400 );
    }

    $points_max     = drak_get_sorcery_points_max( $post_id, $class_id, $nivel );
    $points_current = drak_get_sorcery_points_current( $post_id, $points_max );
    $costs          = drak_get_sorcery_slot_costs();
    $cost           = $costs[ $level ] ?? 0;
    if ( $cost <= 0 ) {
        wp_send_json_error( [ 'message' => 'Coste no definido para ese nivel.' ], 400 );
    }
    if ( $points_current < $cost ) {
        wp_send_json_error( [ 'message' => 'No tienes suficientes puntos de hechicería.' ], 400 );
    }

    $flex_slots               = drak_get_sorcery_flexible_slots( $post_id );
    $flex_slots[ $level ]     = max( 0, intval( $flex_slots[ $level ] ?? 0 ) ) + 1;
    $saved_flex               = drak_save_sorcery_flexible_slots( $post_id, $flex_slots );
    $points_current           = drak_save_sorcery_points_current( $post_id, $points_current - $cost, $points_max );
    $slot_row                 = drak_get_class_spell_slots_for_level( $class_id, $nivel );
    if ( empty( $slot_row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $slot_row = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }
    $base_cap    = intval( $slot_row[ $level ] ?? 0 );
    $total_limit = $base_cap + intval( $saved_flex[ $level ] ?? 0 );

    wp_send_json_success(
        [
            'points_current' => $points_current,
            'points_max'     => $points_max,
            'flex_slots'     => $saved_flex,
            'slot_limit'     => $total_limit,
            'level'          => $level,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_sorcery_points_to_slot', 'drak_dnd5_sorcery_points_to_slot' );

function drak_dnd5_sorcery_reset() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }
    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_sorcery_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $class_id = get_field( 'clase', $post_id );
    $nivel    = intval( get_field( 'nivel', $post_id ) );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        wp_send_json_error( [ 'message' => 'El personaje no es un Hechicero.' ], 400 );
    }

    $state      = drak_reset_sorcery_resources( $post_id, $class_id, $nivel );
    $slot_row   = drak_get_class_spell_slots_for_level( $class_id, $nivel );
    if ( empty( $slot_row ) ) {
        $fallback = drak_get_full_caster_slots_table();
        $slot_row = $fallback[ max( 1, min( 20, $nivel ) ) ] ?? [];
    }

    wp_send_json_success(
        [
            'points_current' => $state['points_current'],
            'points_max'     => $state['points_max'],
            'flex_slots'     => $state['flex_slots'],
            'slot_limits'    => array_map( 'intval', $slot_row ),
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_sorcery_reset', 'drak_dnd5_sorcery_reset' );

function drak_dnd5_sorcery_set_points() {
    if ( ! isset( $_POST['post_id'], $_POST['value'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }
    $post_id = intval( $_POST['post_id'] );
    $value   = intval( $_POST['value'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_sorcery_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }
    $class_id = get_field( 'clase', $post_id );
    $nivel    = intval( get_field( 'nivel', $post_id ) );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        wp_send_json_error( [ 'message' => 'El personaje no es un Hechicero.' ], 400 );
    }

    $max     = drak_get_sorcery_points_max( $post_id, $class_id, $nivel );
    $current = drak_save_sorcery_points_current( $post_id, $value, $max );

    wp_send_json_success(
        [
            'points_current' => $current,
            'points_max'     => $max,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_sorcery_set_points', 'drak_dnd5_sorcery_set_points' );

function drak_dnd5_save_metamagic_known() {
    if ( ! isset( $_POST['post_id'], $_POST['known'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }
    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_sorcery_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }
    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $class_id = get_field( 'clase', $post_id );
    $nivel    = intval( get_field( 'nivel', $post_id ) );
    if ( ! drak_is_sorcerer_class( $class_id ) ) {
        wp_send_json_error( [ 'message' => 'El personaje no es un Hechicero.' ], 400 );
    }

    $known_raw = json_decode( wp_unslash( $_POST['known'] ), true );
    if ( ! is_array( $known_raw ) ) {
        wp_send_json_error( [ 'message' => 'Formato inválido.' ], 400 );
    }

    $limit = drak_get_sorcerer_metamagic_limit( $nivel );
    $clean = drak_save_sorcerer_metamagic_known( $post_id, $known_raw, $limit );

    wp_send_json_success(
        [
            'known' => $clean,
            'limit' => $limit,
        ]
    );
}
add_action( 'wp_ajax_drak_dnd5_save_metamagic_known', 'drak_dnd5_save_metamagic_known' );

function drak_dnd5_save_concentration_state() {
    if ( ! isset( $_POST['post_id'], $_POST['state'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'grimorio_concentration_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $state_raw = json_decode( wp_unslash( $_POST['state'] ), true );
    if ( ! is_array( $state_raw ) ) {
        delete_post_meta( $post_id, 'grimorio_concentration_state' );
        wp_send_json_success( [ 'concentration' => null ] );
    }

    $level    = isset( $state_raw['level'] ) ? intval( $state_raw['level'] ) : null;
    $spell    = isset( $state_raw['spell'] ) ? sanitize_text_field( $state_raw['spell'] ) : '';
    $spell_id = isset( $state_raw['spell_id'] ) ? sanitize_text_field( $state_raw['spell_id'] ) : '';

    if ( null === $level || ( '' === $spell && '' === $spell_id ) ) {
        delete_post_meta( $post_id, 'grimorio_concentration_state' );
        wp_send_json_success( [ 'concentration' => null ] );
    }

    $payload = [
        'level'    => $level,
        'spell'    => $spell,
        'spell_id' => $spell_id,
    ];

    update_post_meta( $post_id, 'grimorio_concentration_state', $payload );

    wp_send_json_success( [ 'concentration' => $payload ] );
}
add_action( 'wp_ajax_drak_dnd5_save_concentration_state', 'drak_dnd5_save_concentration_state' );

/**
 * Crea un personaje básico desde el asistente.
 *
 * Requiere nombre e imagen; asocia campaña y usuario y devuelve la URL de la hoja.
 */
function drak_wizard_create_personaje() {
    $payload = null;

    if ( isset( $_POST['payload'] ) ) {
        $payload = json_decode( wp_unslash( $_POST['payload'] ), true );
    }

    if ( ! $payload && ( $raw = file_get_contents( 'php://input' ) ) ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            $payload = $decoded;
        }
    }

    if ( ! is_array( $payload ) ) {
        wp_send_json_error( [ 'message' => 'Payload inválido o ausente.' ], 400 );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Debes iniciar sesión.' ], 401 );
    }

    $current_user = wp_get_current_user();
    if ( ! user_can( $current_user, 'publish_posts' ) && ! user_can( $current_user, 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes para crear personajes.' ], 403 );
    }

    $name        = sanitize_text_field( $payload['name'] ?? '' );
    $campaign_id = intval( $payload['campaign_id'] ?? 0 );
    $image_id    = isset( $payload['image_id'] ) ? intval( $payload['image_id'] ) : 0;
    $image_url   = isset( $payload['image_url'] ) ? esc_url_raw( $payload['image_url'] ) : '';

    if ( $name === '' ) {
        wp_send_json_error( [ 'message' => 'El nombre del personaje es obligatorio.' ], 400 );
    }

    if ( $image_id <= 0 && ! $image_url ) {
        wp_send_json_error( [ 'message' => 'La imagen del personaje es obligatoria.' ], 400 );
    }

    $post_id = wp_insert_post( [
        'post_type'   => 'personaje',
        'post_title'  => $name,
        'post_status' => 'publish',
        'post_author' => $current_user->ID,
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'No se pudo crear el personaje.', 'debug' => $post_id->get_error_message() ], 500 );
    }

    if ( $campaign_id > 0 ) {
        update_field( 'campaign', $campaign_id, $post_id );
    }

    $featured_id = 0;
    if ( $image_id > 0 ) {
        set_post_thumbnail( $post_id, $image_id );
        $featured_id = $image_id;
    } elseif ( $image_url ) {
        $sideload_id = media_sideload_image( $image_url, $post_id, $name, 'id' );
        if ( ! is_wp_error( $sideload_id ) && $sideload_id ) {
            set_post_thumbnail( $post_id, $sideload_id );
            $featured_id = $sideload_id;
        }
    }

    // Crear el personaje-wiki asociado
    $wiki_id = wp_insert_post( [
        'post_type'   => 'personaje_wiki',
        'post_title'  => $name,
        'post_status' => 'publish',
        'post_author' => $current_user->ID,
    ] );

    if ( is_wp_error( $wiki_id ) || ! $wiki_id ) {
        wp_delete_post( $post_id, true );
        wp_send_json_error( [ 'message' => 'No se pudo crear la ficha de Personaje-Wiki asociada.' ], 500 );
    }

    if ( $campaign_id > 0 ) {
        update_field( 'campaign', $campaign_id, $wiki_id );
    }
    if ( $featured_id > 0 ) {
        update_field( 'hero_image', $featured_id, $wiki_id );
        set_post_thumbnail( $wiki_id, $featured_id );
    }

    update_post_meta( $post_id, 'personaje_wiki_id', $wiki_id );
    update_post_meta( $wiki_id, 'linked_personaje_id', $post_id );

    $slug = get_post_field( 'post_name', $post_id );
    $wiki_slug = get_post_field( 'post_name', $wiki_id );

    $sheet_url     = $slug ? trailingslashit( home_url( '/hoja-personaje/' . $slug ) ) : '';
    $inventory_url = $slug ? trailingslashit( home_url( '/inventario/' . $slug ) ) : '';
    $grimorio_url  = $slug ? trailingslashit( home_url( '/grimorio/' . $slug ) ) : '';
    $wiki_url      = $wiki_slug ? trailingslashit( home_url( '/personaje-wiki/' . $wiki_slug ) ) : '';

    $response = [
        'post_id'       => $post_id,
        'edit_url'      => get_permalink( $post_id ),
        'sheet_url'     => $sheet_url,
        'inventory_url' => $inventory_url,
        'grimorio_url'  => $grimorio_url,
        'redirect_url'  => $sheet_url,
        'personaje_wiki_id' => $wiki_id,
        'personaje_wiki_url' => $wiki_url,
    ];

    wp_send_json_success( $response );
}
add_action( 'wp_ajax_drak_wizard_create_personaje', 'drak_wizard_create_personaje' );
add_action( 'wp_ajax_nopriv_drak_wizard_create_personaje', 'drak_wizard_create_personaje' );

/**
 * AJAX: guardar feats seleccionados en el repeater ACF.
 */
function drak_save_character_feats() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Faltan parámetros.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! $post_id || ! wp_verify_nonce( $nonce, 'save_feats_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Solicitud no válida.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $raw_list = $_POST['feats'] ?? [];
    if ( is_string( $raw_list ) ) {
        $decoded = json_decode( wp_unslash( $raw_list ), true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $raw_list = $decoded;
        }
    }

    if ( ! is_array( $raw_list ) ) {
        $raw_list = [];
    }

    $clean_list = drak_normalize_character_feats( $raw_list );
    update_field( 'feats', $clean_list, $post_id );

    $catalog = drak_get_local_dnd_feats_map();
    $payload = drak_build_character_feat_payload( $clean_list, $catalog );

    wp_send_json_success(
        [
            'feats' => $payload,
        ]
    );
}
add_action( 'wp_ajax_drak_save_character_feats', 'drak_save_character_feats' );

/**
 * AJAX: rasgos combinados (raza + clase + subclase).
 */
function drak_dnd5_get_feature_traits() {
    $post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $class_id    = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $subclass_id = isset( $_POST['subclass_id'] ) ? sanitize_text_field( wp_unslash( $_POST['subclass_id'] ) ) : '';
    $race_id     = isset( $_POST['race_id'] ) ? sanitize_text_field( wp_unslash( $_POST['race_id'] ) ) : '';
    $raw_theories = isset( $_POST['apothecary_theories'] ) ? wp_unslash( $_POST['apothecary_theories'] ) : '';
    $selected_theories = drak_parse_apothecary_theory_ids( $raw_theories );
    if ( ! drak_is_apothecary_class( $class_id ) ) {
        $selected_theories = [];
    }

    $class_lookup     = drak_get_local_dnd_class_lookup();
    $features_data    = drak_get_local_dnd_class_features_data();
    $race_data        = drak_get_local_dnd_races_data();

    $class_entry   = $class_lookup['classes'][ $class_id ] ?? null;
    $subclass_meta = $class_lookup['subclasses'][ $subclass_id ] ?? null;
    if ( $subclass_meta && $class_id && $subclass_meta['class_id'] !== $class_id ) {
        $parent_class_id = $subclass_meta['class_id'];
        $current_name    = $class_entry['name'] ?? '';
        $parent_entry    = $class_lookup['classes'][ $parent_class_id ] ?? null;
        $parent_name     = $parent_entry['name'] ?? '';

        // Algunas clases existen en varias ediciones, así que permitimos la
        // subclase si el nombre base coincide; de lo contrario se descarta.
        if ( ! $current_name || ! $parent_name || $current_name !== $parent_name ) {
            $subclass_meta = null; // No pertenece a la clase seleccionada.
        }
    }

    $race_entry = null;
    if ( $race_id && $race_data && ! empty( $race_data['races'] ) ) {
        foreach ( $race_data['races'] as $race ) {
            if ( isset( $race['id'] ) && $race['id'] === $race_id ) {
                $race_entry = $race;
                break;
            }
        }
    }

    $race_payload = null;
    if ( $race_entry ) {
        $race_payload = [
            'id'      => $race_entry['id'],
            'name'    => $race_entry['name']['es'] ?? $race_entry['name']['en'] ?? $race_entry['id'],
            'source'  => $race_entry['source'] ?? '',
            'entries' => $race_entry['entries'] ?? ( $race_entry['entries_en'] ?? [] ),
        ];
    }

    $class_features_raw = $features_data['classFeatures'][ $class_id ] ?? [];
    $class_features     = array_values(
        array_filter(
            $class_features_raw,
            static function ( $feature ) {
                // Los rasgos de subclase no deberían mostrarse en el bloque de clase.
                return empty( $feature['subclassShortName'] ) && empty( $feature['subclassSource'] );
            }
        )
    );

    $class_payload = [
        'id'       => $class_id,
        'name'     => $class_entry['name'] ?? '',
        'source'   => $class_entry['source'] ?? '',
        'features' => $class_features,
    ];

    $subclass_payload = null;
    if ( $subclass_meta ) {
        $sub_data = $subclass_meta['data'];
        $subclass_features = $features_data['subclassFeatures'][ $subclass_id ] ?? [];

        // Si no hay entrada específica en subclassFeatures, intenta obtener los
        // rasgos desde la lista de la clase filtrando por el nombre corto.
        if ( empty( $subclass_features ) && ! empty( $class_features_raw ) ) {
            $short_name = $sub_data['shortName'] ?? '';
            if ( $short_name ) {
                $subclass_features = array_values(
                    array_filter(
                        $class_features_raw,
                        static function ( $feature ) use ( $short_name ) {
                            return isset( $feature['subclassShortName'] ) &&
                                strcasecmp( $feature['subclassShortName'], $short_name ) === 0;
                        }
                    )
                );
            }
        }

        $subclass_payload = [
            'id'       => $subclass_id,
            'name'     => $sub_data['name'] ?? '',
            'source'   => $sub_data['source'] ?? '',
            'features' => $subclass_features,
        ];
    }

    $selected_feats = [];
    if ( $post_id && drak_user_can_view_personaje( $post_id ) ) {
        $selected_feats = drak_get_character_feats( $post_id );
    }
    $feat_catalog         = drak_get_local_dnd_feats_map();
    $selected_feats_ready = drak_build_character_feat_payload( $selected_feats, $feat_catalog );

    wp_send_json_success([
        'race'     => $race_payload,
        'class'    => $class_payload,
        'subclass' => $subclass_payload,
        'esotericTheories' => drak_expand_apothecary_theories( $selected_theories ),
        'selectedFeats'    => $selected_feats_ready,
    ]);
}
add_action( 'wp_ajax_drak_dnd5_get_feature_traits', 'drak_dnd5_get_feature_traits' );
add_action( 'wp_ajax_nopriv_drak_dnd5_get_feature_traits', 'drak_dnd5_get_feature_traits' );

/**
 * Renderiza las secciones de rasgos de clase reutilizando el estilo de la hoja.
 *
 * @param string   $class_id     ID de la clase (coincide con los JSON).
 * @param int|null $level_filter Si es null muestra todos los niveles; si es int filtra por ese nivel.
 * @param bool     $echo         Si true imprime, si false devuelve el HTML.
 *
 * @return string|null
 */
function drak_render_class_features_section( $class_id, $level_filter = null, $echo = true, $subclass_id = '' ) {
    if ( ! $class_id ) {
        return '';
    }

    $features_data = drak_get_local_dnd_class_features_data();
    $class_lookup  = drak_get_local_dnd_class_lookup();
    $class_name    = $class_lookup['classes'][ $class_id ]['name'] ?? $class_id;
    $features      = $features_data['classFeatures'][ $class_id ] ?? [];
    $sub_features  = $subclass_id ? ( $features_data['subclassFeatures'][ $subclass_id ] ?? [] ) : [];

    if ( $subclass_id && $features ) {
        $features = array_values(
            array_filter(
                $features,
                static function ( $feat ) use ( $subclass_id ) {
                    if ( empty( $feat['subclassShortName'] ) && empty( $feat['subclassSource'] ) && empty( $feat['subclass'] ) ) {
                        return true;
                    }
                    $feat_sub = $feat['subclassShortName'] ?? '';
                    $feat_source = $feat['subclassSource'] ?? '';
                    $feat_id = $feat['subclass'] ?? '';
                    return $feat_id === $subclass_id || sanitize_title( $feat_sub ) === sanitize_title( $subclass_id );
                }
            )
        );
    }

    if ( $level_filter !== null ) {
        $features = array_filter(
            $features,
            static function ( $feat ) use ( $level_filter ) {
                return isset( $feat['level'] ) && intval( $feat['level'] ) === intval( $level_filter );
            }
        );
        if ( $sub_features ) {
            $sub_features = array_filter(
                $sub_features,
                static function ( $feat ) use ( $level_filter ) {
                    return isset( $feat['level'] ) && intval( $feat['level'] ) === intval( $level_filter );
                }
            );
        }
    }

    if ( $echo ) {
        ob_start();
    }

    if ( empty( $features ) && empty( $sub_features ) ) {
        echo '<p class="character-extended__empty">No hay rasgos disponibles.</p>';
        return $echo ? ob_get_clean() : null;
    }

    echo '<section class="character-extended__section">';
    echo '<h4 class="character-extended__section-title">Rasgos de clase · ' . esc_html( $class_name ) . '</h4>';

    foreach ( $features as $feature ) {
        $name   = $feature['name'] ?? __( 'Rasgo', 'temahijo' );
        $level  = isset( $feature['level'] ) ? intval( $feature['level'] ) : null;
        $source = $feature['source'] ?? '';
        $meta   = [];
        if ( $level ) {
            $meta[] = sprintf( __( 'Nivel %d', 'temahijo' ), $level );
        }
        if ( $source ) {
            $meta[] = esc_html( $source );
        }
        $entries_raw = $feature['entries'] ?? [];
        $entries_out = drak_render_5e_entries_html( $entries_raw );

        echo '<article class="feature-card is-collapsed">';
        echo '  <header class="feature-card__header">';
        echo '    <h5 class="feature-card__title">' . esc_html( $name ) . '</h5>';
        echo '    <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Mostrar detalle', 'temahijo' ) . '">';
        echo '      <span class="feature-card__toggle-icon">▼</span>';
        echo '    </button>';
        echo '  </header>';
        echo '  <div class="feature-card__content">';
        if ( $meta ) {
            echo '    <div class="feature-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>';
        }
        echo '    <div class="feature-card__body">' . $entries_out . '</div>';
        echo '  </div>';
        echo '</article>';
    }

    // Subclase (opcional)
    if ( $sub_features ) {
        $sub_name = $class_lookup['subclasses'][ $subclass_id ]['data']['name'] ?? $subclass_id;
        echo '<section class="character-extended__section">';
        echo '<h4 class="character-extended__section-title">Rasgos de subclase · ' . esc_html( $sub_name ) . '</h4>';
        foreach ( $sub_features as $feature ) {
            $name   = $feature['name'] ?? __( 'Rasgo', 'temahijo' );
            $level  = isset( $feature['level'] ) ? intval( $feature['level'] ) : null;
            $source = $feature['source'] ?? '';
            $meta   = [];
            if ( $level ) {
                $meta[] = sprintf( __( 'Nivel %d', 'temahijo' ), $level );
            }
            if ( $source ) {
                $meta[] = esc_html( $source );
            }
            $entries_raw = $feature['entries'] ?? [];
            $entries_out = drak_render_5e_entries_html( $entries_raw );

            echo '<article class="feature-card is-collapsed">';
            echo '  <header class="feature-card__header">';
            echo '    <h5 class="feature-card__title">' . esc_html( $name ) . '</h5>';
            echo '    <button type="button" class="feature-card__toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Mostrar detalle', 'temahijo' ) . '">';
            echo '      <span class="feature-card__toggle-icon">▼</span>';
            echo '    </button>';
            echo '  </header>';
            echo '  <div class="feature-card__content">';
            if ( $meta ) {
                echo '    <div class="feature-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>';
            }
            echo '    <div class="feature-card__body">' . $entries_out . '</div>';
            echo '  </div>';
            echo '</article>';
        }
        echo '</section>';
    }

    echo '</section>';

    if ( $echo ) {
        return ob_get_clean();
    }

    return null;
}

/**
 * Renderiza recursivamente estructuras de entries de 5etools a HTML simple.
 *
 * @param mixed $entries
 * @return string
 */
function drak_render_5e_entries_html( $entries ) {
    if ( is_string( $entries ) ) {
        return '<p>' . esc_html( drak_strip_5e_markup( $entries ) ) . '</p>';
    }

    if ( ! is_array( $entries ) ) {
        return '';
    }

    $out = '';
    foreach ( $entries as $entry ) {
        if ( is_string( $entry ) ) {
            $out .= '<p>' . esc_html( drak_strip_5e_markup( $entry ) ) . '</p>';
            continue;
        }

        if ( ! is_array( $entry ) ) {
            continue;
        }

        $type = $entry['type'] ?? '';
        $name = $entry['name'] ?? '';

        switch ( $type ) {
            case 'list':
            case 'itemlist':
                $items = $entry['items'] ?? $entry['entries'] ?? [];
                if ( empty( $items ) ) {
                    break;
                }
                if ( $name ) {
                    $out .= '<p><strong>' . esc_html( drak_strip_5e_markup( $name ) ) . ':</strong></p>';
                }
                $out .= '<ul>';
                foreach ( $items as $it ) {
                    $out .= '<li>' . drak_render_5e_entries_html( $it ) . '</li>';
                }
                $out .= '</ul>';
                break;

            case 'entries':
            case 'inset':
            case 'insetReadaloud':
                if ( $name ) {
                    $out .= '<p><strong>' . esc_html( drak_strip_5e_markup( $name ) ) . '</strong></p>';
                }
                $out .= drak_render_5e_entries_html( $entry['entries'] ?? [] );
                break;

            default:
                // Si no hay type pero sí entries, renderiza recursivamente.
                if ( isset( $entry['entries'] ) ) {
                    if ( $name ) {
                        $out .= '<p><strong>' . esc_html( drak_strip_5e_markup( $name ) ) . '</strong></p>';
                    }
                    $out .= drak_render_5e_entries_html( $entry['entries'] );
                } elseif ( isset( $entry['entry'] ) ) {
                    $out .= '<p>' . esc_html( drak_strip_5e_markup( (string) $entry['entry'] ) ) . '</p>';
                }
                break;
        }
    }

    if ( $out === '' ) {
        $out = '<p>' . esc_html__( 'Sin descripción.', 'temahijo' ) . '</p>';
    }

    return $out;
}

/**
 * Renderiza los rasgos de raza con el mismo estilo de la hoja.
 *
 * @param string $race_id
 * @param bool   $echo
 *
 * @return string|null
 */
function drak_render_race_features_section( $race_id, $echo = true ) {
    if ( ! $race_id ) {
        return '';
    }

    $race_data  = drak_get_local_dnd_races_data();
    $race_entry = null;
    foreach ( $race_data['races'] ?? [] as $race ) {
        if ( isset( $race['id'] ) && $race['id'] === $race_id ) {
            $race_entry = $race;
            break;
        }
    }

    if ( ! $race_entry ) {
        return '';
    }

    if ( $echo ) {
        ob_start();
    }

    $name        = $race_entry['name']['es'] ?? $race_entry['name']['en'] ?? $race_entry['name'] ?? $race_id;
    $entries_raw = $race_entry['entries'] ?? ( $race_entry['entries_en'] ?? [] );
    $entries_out = drak_render_5e_entries_html( $entries_raw );
    if ( $entries_out === '' ) {
        $entries_out = '<p>' . esc_html__( 'Sin descripción.', 'temahijo' ) . '</p>';
    }

    echo '<section class="character-extended__section">';
    echo '<h4 class="character-extended__section-title">Rasgos raciales · ' . esc_html( $name ) . '</h4>';
    echo $entries_out;
    echo '</section>';

    if ( $echo ) {
        return ob_get_clean();
    }

    return null;
}

function drak_get_class_features_for_wizard() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'No autorizado' ], 403 );
    }

    $class_id = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
    $level    = isset( $_POST['level'] ) ? intval( $_POST['level'] ) : 1;
    $subclass_id = isset( $_POST['subclass_id'] ) ? sanitize_text_field( wp_unslash( $_POST['subclass_id'] ) ) : '';

    if ( ! $class_id ) {
        wp_send_json_error( [ 'message' => 'Falta class_id' ], 400 );
    }

    $html = drak_render_class_features_section( $class_id, $level, true, $subclass_id );

    wp_send_json_success(
        [
            'html' => $html,
        ]
    );
}
add_action( 'wp_ajax_drak_get_class_features_for_wizard', 'drak_get_class_features_for_wizard' );
add_action( 'wp_ajax_nopriv_drak_get_class_features_for_wizard', 'drak_get_class_features_for_wizard' );

function drak_get_race_features_for_wizard() {
    $race_id = isset( $_POST['race_id'] ) ? sanitize_text_field( wp_unslash( $_POST['race_id'] ) ) : '';
    if ( ! $race_id ) {
        wp_send_json_error( [ 'message' => 'Falta race_id' ], 400 );
    }

    $html = drak_render_race_features_section( $race_id, true );
    if ( $html === '' ) {
        $html = '<p class="character-extended__empty">' . esc_html__( 'No se encontraron rasgos para esta raza.', 'temahijo' ) . '</p>';
    }

    wp_send_json_success(
        [
            'html' => $html,
        ]
    );
}
add_action( 'wp_ajax_drak_get_race_features_for_wizard', 'drak_get_race_features_for_wizard' );
add_action( 'wp_ajax_nopriv_drak_get_race_features_for_wizard', 'drak_get_race_features_for_wizard' );
function drak_get_admin_ajax_url() {
    static $cached = null;

    if ( $cached !== null ) {
        return $cached;
    }

    $cached = site_url( '/wp-admin/admin-ajax.php' );

    return $cached;
}

function drak_save_delerium_module() {
    if ( ! isset( $_POST['post_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }

    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

    if ( ! $post_id || ! wp_verify_nonce( $nonce, 'delerium_module_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    if ( ! drak_user_can_manage_personaje( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ], 403 );
    }

    $number_fields = [
        'delerium_contamination_level' => 6,
        'delerium_chips'               => null,
        'delerium_fragments'           => null,
        'delerium_shards'              => null,
        'delerium_crystals'            => null,
        'delerium_geodas'              => null,
    ];

    foreach ( $number_fields as $field => $max ) {
        if ( ! isset( $_POST[ $field ] ) ) {
            continue;
        }
        $value = intval( drak_get_post_value( $field, 0 ) );
        $value = max( 0, $value );
        if ( null !== $max ) {
            $value = min( $value, $max );
        }
        update_field( $field, $value, $post_id );
    }

    if ( isset( $_POST['delerium_mutations'] ) ) {
        $mutations = sanitize_textarea_field( wp_unslash( $_POST['delerium_mutations'] ) );
        update_field( 'delerium_mutations', $mutations, $post_id );
    }

    if ( isset( $_POST['delerium_madness'] ) ) {
        $madness = sanitize_textarea_field( wp_unslash( $_POST['delerium_madness'] ) );
        update_field( 'delerium_madness', $madness, $post_id );
    }

    wp_send_json_success( [ 'message' => 'Delerium actualizado' ] );
}
add_action( 'wp_ajax_drak_save_delerium_module', 'drak_save_delerium_module' );
add_action( 'wp_ajax_nopriv_drak_save_delerium_module', 'drak_save_delerium_module' );

/**
 * Guardado AJAX del inventario (autosave).
 */
function drak_save_inventory_ajax() {
    if ( ! isset( $_POST['post_id'], $_POST['inventory_nonce'] ) ) {
        wp_send_json_error( [ 'message' => 'Parámetros incompletos.' ], 400 );
    }
    $post_id = intval( $_POST['post_id'] );
    $nonce   = sanitize_text_field( wp_unslash( $_POST['inventory_nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'save_inventory_' . $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
    }

    $result = drak_process_inventory_submission( $post_id, $_POST );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ], 403 );
    }

    wp_send_json_success( [ 'message' => 'Inventario guardado' ] );
}
add_action( 'wp_ajax_drak_save_inventory', 'drak_save_inventory_ajax' );
add_action( 'wp_ajax_nopriv_drak_save_inventory', 'drak_save_inventory_ajax' );

/**
 * Live search para Wiki de campaña.
 */
function drak_wiki_live_search() {
    check_ajax_referer( 'drak_wiki_live_search', 'nonce' );

    $term        = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    $section_key = isset( $_POST['wiki_section'] ) ? sanitize_key( wp_unslash( $_POST['wiki_section'] ) ) : '';
    $campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

    $map = [
        'npc'            => 'npc',
        'lugar'          => 'lugar',
        'faccion'        => 'faccion',
        'personaje_wiki' => 'personaje_wiki',
        'diario'         => 'diario',
    ];

    if ( strlen( $term ) < 2 || ! $campaign_id || ! isset( $map[ $section_key ] ) ) {
        wp_send_json_success( [] );
    }

    $query = new WP_Query( [
        'post_type'      => $map[ $section_key ],
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        's'              => $term,
        'meta_query'     => [
            [
                'key'     => 'campaign',
                'value'   => $campaign_id,
                'compare' => '=',
            ],
        ],
    ] );

    $results = [];
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $results[] = [
                'id'        => get_the_ID(),
                'title'     => get_the_title(),
                'excerpt'   => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ), 20, '…' ),
                'permalink' => get_permalink(),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success( $results );
}
add_action( 'wp_ajax_drak_wiki_live_search', 'drak_wiki_live_search' );
add_action( 'wp_ajax_nopriv_drak_wiki_live_search', 'drak_wiki_live_search' );

/**
 * Live search para Homebrew (DM/Admin).
 */
function drak_homebrew_live_search() {
    check_ajax_referer( 'drak_homebrew_live_search', 'nonce' );

    if ( ! drak_homebrew_user_can_manage() ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ], 403 );
    }

    $term        = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    $section_key = isset( $_POST['homebrew_section'] ) ? sanitize_key( wp_unslash( $_POST['homebrew_section'] ) ) : '';
    $campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

    if ( strlen( $term ) < 2 || ! $campaign_id || ! $section_key ) {
        wp_send_json_success( [] );
    }

    $query = new WP_Query( [
        'post_type'      => 'homebrew_entry',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        's'              => $term,
        'meta_query'     => [
            [
                'key'     => 'campaign',
                'value'   => $campaign_id,
                'compare' => '=',
            ],
            [
                'key'     => 'homebrew_section',
                'value'   => $section_key,
                'compare' => '=',
            ],
        ],
    ] );

    $results = [];
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $results[] = [
                'id'        => get_the_ID(),
                'title'     => get_the_title(),
                'excerpt'   => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ), 20, '…' ),
                'permalink' => get_permalink(),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success( $results );
}
add_action( 'wp_ajax_drak_homebrew_live_search', 'drak_homebrew_live_search' );
add_action( 'wp_ajax_nopriv_drak_homebrew_live_search', 'drak_homebrew_live_search' );

/**
 * Obtiene la entrada anterior/siguiente dentro de la misma campaña y CPT.
 *
 * @param int    $post_id     ID actual.
 * @param string $direction   'prev' o 'next'.
 * @param string $post_type   Tipo de post.
 * @param int    $campaign_id ID de campaña.
 *
 * @return WP_Post|null
 */
function drak_get_adjacent_wiki_post( $post_id, $direction, $post_type, $campaign_id ) {
    $direction   = ( $direction === 'next' ) ? 'next' : 'prev';
    $post_id     = (int) $post_id;
    $campaign_id = (int) $campaign_id;

    if ( ! $post_id || ! $post_type || ! $campaign_id ) {
        return null;
    }

    $current = get_post( $post_id );
    if ( ! $current ) {
        return null;
    }

    $compare = $direction === 'next' ? '>' : '<';
    $order   = $direction === 'next' ? 'ASC' : 'DESC';

    $query = new WP_Query( [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date ID',
        'order'          => $order,
        'post__not_in'   => [ $post_id ],
        'meta_query'     => [
            [
                'key'     => 'campaign',
                'value'   => $campaign_id,
                'compare' => '=',
            ],
        ],
        'date_query'     => [
            [
                'column'    => 'post_date',
                'compare'   => $compare,
                'after'     => $direction === 'next' ? $current->post_date : '',
                'before'    => $direction === 'prev' ? $current->post_date : '',
                'inclusive' => false,
            ],
        ],
    ] );

    return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * Obtiene la entrada anterior/siguiente de Homebrew dentro de la misma campaña y sección.
 *
 * @param int    $post_id     ID actual.
 * @param string $direction   'prev' o 'next'.
 * @param string $section     Sección de homebrew (reglas/monstruos/forja/tienda).
 * @param int    $campaign_id ID de campaña.
 *
 * @return WP_Post|null
 */
function drak_get_adjacent_homebrew_entry( $post_id, $direction, $section, $campaign_id ) {
    $direction   = ( $direction === 'next' ) ? 'next' : 'prev';
    $post_id     = (int) $post_id;
    $campaign_id = (int) $campaign_id;
    $section     = sanitize_key( $section );

    if ( ! $post_id || ! $campaign_id || ! $section ) {
        return null;
    }

    $current = get_post( $post_id );
    if ( ! $current ) {
        return null;
    }

    $compare = $direction === 'next' ? '>' : '<';
    $order   = $direction === 'next' ? 'ASC' : 'DESC';

    $query = new WP_Query( [
        'post_type'      => 'homebrew_entry',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date ID',
        'order'          => $order,
        'post__not_in'   => [ $post_id ],
        'meta_query'     => [
            [
                'key'     => 'campaign',
                'value'   => $campaign_id,
                'compare' => '=',
            ],
            [
                'key'     => 'homebrew_section',
                'value'   => $section,
                'compare' => '=',
            ],
        ],
        'date_query'     => [
            [
                'column'    => 'post_date',
                'compare'   => $compare,
                'after'     => $direction === 'next' ? $current->post_date : '',
                'before'    => $direction === 'prev' ? $current->post_date : '',
                'inclusive' => false,
            ],
        ],
    ] );

    return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * Encola el JS de búsqueda predictiva en la vista de archivo de Wiki.
 */
function drak_enqueue_wiki_search_script() {
    $allowed     = [ 'npc', 'lugar', 'faccion', 'personaje_wiki', 'diario' ];
    $section     = '';
    $campaign_id = 0;

    if ( is_singular( 'campaign' ) ) {
        $current_section = get_query_var( 'campaign_section' );
        if ( $current_section === 'diario' ) {
            $section     = 'diario';
            $campaign_id = get_the_ID();
        } else {
            $section   = isset( $_GET['wiki_section'] ) ? sanitize_key( wp_unslash( $_GET['wiki_section'] ) ) : '';
            $wiki_view = isset( $_GET['wiki_view'] ) ? sanitize_key( wp_unslash( $_GET['wiki_view'] ) ) : '';

            if ( 'archive' !== $wiki_view || ! in_array( $section, $allowed, true ) || 'personaje_wiki' === $section ) {
                return;
            }
            $campaign_id = get_the_ID();
        }
    } elseif ( is_singular( $allowed ) ) {
        $section     = get_post_type();
        $campaign_id = (int) get_field( 'campaign', get_the_ID() );
        if ( ! $campaign_id ) {
            return;
        }
    } else {
        return;
    }

    wp_enqueue_script(
        'drak-wiki-search',
        get_stylesheet_directory_uri() . '/js/drak-wiki-search.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script(
        'drak-wiki-search',
        'drakWikiSearchData',
        [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'ajaxNonce'   => wp_create_nonce( 'drak_wiki_live_search' ),
            'wikiSection' => $section,
            'campaignId'  => $campaign_id,
            'minChars'    => 2,
        ]
    );
}
add_action( 'wp_enqueue_scripts', 'drak_enqueue_wiki_search_script', 20 );
    // Imagen destacada (prioridad: image_id desde media modal).
    $image_id = isset( $payload['image_id'] ) ? intval( $payload['image_id'] ) : 0;
    if ( $image_id > 0 ) {
        set_post_thumbnail( $post_id, $image_id );
    } elseif ( ! empty( $payload['image_url'] ) ) { // compat URL
        $image_id = media_sideload_image( esc_url_raw( $payload['image_url'] ), $post_id, $name, 'id' );
        if ( ! is_wp_error( $image_id ) && $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }
    } elseif ( ! empty( $_FILES['image_file'] ) && ! empty( $_FILES['image_file']['name'] ) ) { // compat upload directo
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_id = media_handle_upload( 'image_file', $post_id );
        if ( ! is_wp_error( $attach_id ) ) {
            set_post_thumbnail( $post_id, $attach_id );
        }
    }
