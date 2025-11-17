Implementación técnica del Módulo de Galería en WordPress

(Este archivo se entrega directamente a Codex para que programe el módulo completo)

📑 Índice

Objetivo

Arquitectura general

Custom Post Type

Campos ACF (versión para implementar por código)

Shortcode: Formulario de subida

Procesamiento del formulario

Shortcode: Galería global

Renderizado de galería en fichas

Librerías, JS y CSS

Rutas, seguridad y validaciones

1. 🎯 Objetivo

Implementar en WordPress un sistema que permita:

Subir imágenes desde un formulario externo al admin.

Asociarlas obligatoriamente a Personajes, Lugares y/o NPC.

Mostrar todas las imágenes en una galería general con filtros.

Mostrar imágenes asociadas en las fichas de Personajes/Lugares/NPC mediante carrusel.

Todo debe estar integrado en un plugin independiente llamado “drak-gallery”.

2. 🏗 Arquitectura general

El plugin debe implementar:

Custom Post Type galeria_item

Campos ACF (vía código)

Shortcode [drak_gallery_upload]

Procesador de subida vía admin_post

Shortcode [drak_gallery]

Funciones de render para Personajes/Lugares/NPC

Carrusel (Swiper.js o similar)

Carga condicional de scripts y estilos

Seguridad: Nonce, roles, validación MIME, sanitización

3. 🧬 Custom Post Type: galeria_item

Registrar en init:

post_type = 'galeria_item'

supports = ['title', 'thumbnail', 'author']

public = false

publicly_queryable = true

show_ui = true

capability_type = post

Debe aparecer en el admin para moderación.

4. 🧱 Campos ACF definidos por código

Usar acf_add_local_field_group() para crear el grupo:

Grupo: galeria_asociaciones
Asignarlo al Post Type galeria_item.

Campos:

4.1. gallery_type

Tipo: Checkbox

Opciones: personaje, lugar, npc

Obligatorio

Mínimo 1 selección

4.2. gallery_personajes

Relationship / Post Object multiple

Post Type destino: personaje

Condición: Solo visible si gallery_type incluye personaje.

4.3. gallery_lugares

Relationship / Post Object multiple

Post Type destino: lugar

Condición: Solo si gallery_type contiene lugar.

4.4. gallery_npcs

Relationship / Post Object multiple

Post Type destino: npc

Condición: Solo si contiene npc.

4.5. gallery_description

Texto (opcional)

5. 🧩 Shortcode [drak_gallery_upload]

Genera un formulario con:

Input de archivo image/*.

Checkbox de tipo:

personaje

lugar

npc

→ Validación mínima: 1 marcado.

Panel condicional con selectores para cada tipo marcado.

Nonce obligatorio.

Campos opcionales: título y descripción.

El formulario debe enviarse a:

admin-post.php?action=drak_gallery_upload

6. ⚙️ Procesamiento del formulario

En el action handler drak_gallery_upload:

Validar usuario logueado.

Validar nonce.

Validar archivo:

Tamaño

MIME (image/jpeg, png, webp…)

Subir archivo:

wp_handle_upload()

Crear attachment con wp_insert_attachment()

Crear galeria_item:

post_type = galeria_item

post_status = pending

post_author = user_id

Usar el título proporcionado o uno automático.

Asociar featured image:

set_post_thumbnail()

Guardar los campos ACF con:

update_field()

Redirigir a una página de confirmación.

7. 🖼️ Shortcode [drak_gallery]

Debe mostrar:

7.1. Cabecera con:

Buscador (input text)

Filtros:

Checkbox Personaje

Checkbox Lugar

Checkbox NPC

7.2. Grid de imágenes

Cada imagen:

Miniatura (featured image)

Data atributos:

data-has-personaje="1/0"

data-has-lugar="1/0"

data-has-npc="1/0"

Click → modal con:

Imagen completa

Autor

Fecha

Descripción

Lista de personajes/lugares/NPC vinculados

7.3. Filtrado por JS

No recargar la página.

8. 🔗 Renderizado en fichas de Personaje/Lugar/NPC

Crear función:

function drak_render_gallery_for_post( $post_id )


Debe:

Detectar si el post es Personaje / Lugar / NPC.

Consultar galeria_item asociados (WP_Query + meta_query).

Mostrar:

0 → nada

1 → imagen simple

2+ → carrusel Swiper

Esta función se insertará en plantillas tipo:

single-personaje.php
single-lugar.php
single-npc.php

9. 🎡 Librerías JS y CSS

Incluir:

Swiper.js para el carrusel

CSS propio para:

Grid

Modal

Formularios

Carga condicional solo en:

Página de galería

Página de subida

Fichas con galería

10. 🔐 Seguridad y validaciones

Validación MIME

Sanitización de strings

Sanitizar arrays

Validar IDs existan

Validar roles

Nonces en toda acción

Comprobación de permisos para editar/moderar