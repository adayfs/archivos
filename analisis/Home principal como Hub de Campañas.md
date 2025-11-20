Análisis – Home principal como Hub de Campañas
1. Objetivo
Convertir la home de adayfs.com en un hub de campañas:

Mostrar un título grande “ROLASSSSO.” en la parte superior.
Listar un botón/tarjeta por cada campaña creada (CPT campaign).
Cada botón debe enlazar a la página individual de esa campaña (single de campaign).
Esta home será la puerta de entrada a todas las campañas; la estructura interna de cada campaña se gestionará en sus propias plantillas (Personajes, Diario, Wiki, Galería).
La idea es que, al entrar en https://adayfs.com, el usuario vea todas las campañas disponibles y pueda elegir a cuál entrar.

2. Supuestos y contexto
Ya existe el CPT campaign creado en el plugin drak-campaigns.
Ya existe una página de WordPress titulada ROLASSSSO y está configurada como “Página de inicio” en:
Ajustes → Lectura → “Tu portada muestra → Una página estática”.
En fases posteriores se implementará la plantilla de campaña (single-campaign) con los iconos de:
Personajes
Diario de campaña
Wiki
Galería
En esta fase solo se aborda la home global que lista campañas.

3. Requisitos funcionales
3.1. Comportamiento general
Cuando un usuario visite https://adayfs.com, debe ver:

Un título grande con el texto literal: ROLASSSSO.
Debajo, una rejilla de tarjetas (botones) con una tarjeta por cada campaña publicada (campaign con estado publish).
Cada tarjeta de campaña debe mostrar, como mínimo:

Imagen principal de la campaña (portada).
Nombre de la campaña (título del CPT).
Estado (“En curso”, “En pausa”, “Terminada”) si está disponible.
Descripción breve si está disponible.
Al hacer clic en una tarjeta:

El usuario debe ser redirigido a la página individual de esa campaña (single del CPT campaign).
Si todavía no hay campañas creadas:

La home debe mostrar un mensaje tipo: “Todavía no hay campañas disponibles”.
3.2. Datos de campaña a utilizar
La tarjeta de cada campaña debe obtener la información de:

Título de la campaña → título del post campaign.
Imagen de portada:
Prioridad 1: campo ACF campaign_cover_image (del grupo Campaña – Metadatos).
Prioridad 2: imagen destacada del post campaign.
Si no existe ninguna de las dos, usar una imagen por defecto o un estilo neutro.
Estado de la campaña → valor del campo ACF campaign_status:
active → mostrar “En curso”.
paused → mostrar “En pausa”.
finished → mostrar “Terminada”.
Descripción breve → campo ACF campaign_summary (opcional; si está vacío, no mostrar ese bloque).
3.3. Orden y filtros
Listar únicamente campañas con estado de publicación publish.
Orden por defecto:
Orden alfabético por título de campaña, ascendente.
Número de campañas:
Sin límite (todas las campañas publicadas).
4. Requisitos de presentación (alto nivel)
4.1. Título “ROLASSSSO.”
El texto “ROLASSSSO.” debe aparecer:
En la parte superior de la página.
Como elemento dominante (tipografía grande).
Debe encajar con la estética global del sitio:
Fondo morado oscuro.
Estilo “dark fantasy”.
4.2. Rejilla de campañas
Las campañas deben mostrarse como una rejilla de tarjetas/botones.
Comportamiento responsive sugerido:
Escritorio: 3 tarjetas por fila (si el espacio lo permite).
Tablet: 2 tarjetas por fila.
Móvil: 1 tarjeta por fila.
Cada tarjeta debe ser clicable en su totalidad (no solo el título).
Elementos dentro de la tarjeta:

Imagen de portada:
Ocupa la parte superior de la tarjeta.
Mantener proporción cuadrada o rectángulo uniforme para todas.
Título:
Debajo de la imagen, en tipografía clara y legible.
Estado:
Pequeña etiqueta o texto que indique el estado (“En curso”, etc.) si el campo ACF está informado.
Descripción breve:
Texto corto opcional (un par de líneas máximo).
El estilo debe intentar recordar las tarjetas que ya existen en la home actual (Personajes, Diario, Wiki, Galería), aunque esta adaptación visual puede ir iterando.

5. Requisitos técnicos (alto nivel, sin código específico)
5.1. Plantilla para la home
La home debe usar una plantilla específica en el tema o tema hijo.

Opciones:

Implementar una plantilla global front-page.php para la home cuando se usa página estática.
Alternativamente, crear una plantilla de página personalizada y asignarla manualmente a la página ROLASSSSO.
En este caso la plantilla debe ignorar el contenido del editor y generar el layout descrito en este análisis.
La plantilla debe:

Detectar que está renderizando la home estática.
Lanzar una consulta a campaign para listar campañas.
Pintar el título “ROLASSSSO.” + la rejilla de tarjetas.
5.2. Consulta de campañas
Realizar una consulta a todos los posts de tipo campaign con:

Estado publish.
Sin límite de posts.
Orden alfabético por título.
Para cada campaña obtenida:

Recoger los metadatos ACF necesarios (campaign_cover_image, campaign_status, campaign_summary).
Preparar la URL de la campaña (enlace al single de campaign).
5.3. Manejo de estados ACF
La plantilla debe traducir el valor interno de campaign_status a texto legible:
active → “En curso”
paused → “En pausa”
finished → “Terminada”
Si el campo no está informado, simplemente no se muestra la etiqueta/estado.
5.4. Comportamiento sin campañas
Si la consulta de campañas devuelve cero resultados:
No mostrar rejilla vacía.
Mostrar un mensaje informativo del tipo:
“Todavía no hay campañas disponibles.”
Mantener el título “ROLASSSSO.” para que la página no quede completamente vacía.
6. Compatibilidad futura
La implementación de esta home debe tener en cuenta:

Que en fases posteriores se añadirá la plantilla single-campaign que reutilizará la estructura actual de la web (iconos de Personajes, Diario, Wiki, Galería) pero filtrada por campaña.
Que el campo ACF campaign en otros contenidos (personajes, posts de Diario, Wiki, etc.) será la clave para filtrar cuando se entre a la campaña concreta.
Que el rol dm y el resto de roles no necesitan permisos especiales para ver esta home; es una página pública.
7. Resumen
La home https://adayfs.com se convierte en un hub de campañas.
Se mantiene un diseño sencillo:
Título grande “ROLASSSSO.”.
Rejilla de tarjetas, una por campaña (CPT campaign).
Cada tarjeta usa los metadatos ACF de la campaña y enlaza a su single.
La solución se implementa a nivel de plantilla de tema (no con maquetador), para que sea sólida y fácil de extender cuando se añadan nuevas campañas y funcionalidades.