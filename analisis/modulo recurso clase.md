DOCUMENTO DE ANÁLISIS DE ALTO NIVEL
MÓDULO GENÉRICO: “RECURSOS DE CLASE”

OBJETIVO
Crear un módulo genérico llamado “Recursos de Clase” que centralice la gestión de todos los recursos consumibles de las clases de personaje (y sus subclases) que funcionan como “pools” o “usos por descanso”: puntos de ki, rages, dados de superioridad, inspiración bárdica, puntos de hechicería, Channel Divinity, Lay on Hands, Wild Shape, etc.

Este módulo:
- Estará integrado en la hoja de personaje.
- Se mostrará en un bloque visualmente separado del resto (hechizos, inventario, etc.).
- Permitirá consumir y restaurar recursos de forma dinámica (sin recargar la página).
- Guardará los cambios inmediatamente (persistencia al momento).

La idea es tener UNA sola infraestructura que se adapte a las particularidades de cada clase.

--------------------------------------------------
1. CONCEPTO GENERAL DE “RECURSO DE CLASE”
--------------------------------------------------

Definimos “Recurso de Clase” como cualquier mecánica que:
- Tiene un máximo (puntos, usos o dados) que depende del nivel de clase, atributo o competencia.
- Se va gastando cuando el personaje activa ciertas habilidades.
- Se recarga parcial o totalmente al realizar un tipo de descanso (corto, largo) o al pasar un cierto intervalo.

Ejemplos:
- Gastar 1 punto de ki para hacer Flurry of Blows.
- Gastar 1 uso de Rage del Bárbaro.
- Gastar 1 dado de superioridad para un maniobra de Battle Master.
- Gastar puntos de hechicería para Metamagia o para crear slots de conjuro.
- Gastar usos de Channel Divinity, Bardic Inspiration, etc.

Este módulo NO define los efectos mecánicos de la habilidad (eso lo hacen otros módulos o las descripciones de rasgos), sino que:
- Proporciona la estructura genérica para:
  - Mostrar el recurso.
  - Saber cuántos quedan.
  - Gastar o recuperar usos/puntos/dados.
  - Aplicar las reglas de recarga.

--------------------------------------------------
2. TIPOS DE RECURSOS QUE DEBE SOPORTAR
--------------------------------------------------

El módulo debe ser suficientemente genérico para cubrir:

1) Recursos de “PUNTOS”
   - Ej.: Ki del monje, puntos de hechicería, Lay on Hands.
   - Tienen un valor máximo numérico (ej. 8 puntos).
   - Se consumen en cantidades variables (ej. “gasto 2 puntos de hechicería”).

2) Recursos de “USOS”
   - Ej.: Rages del bárbaro, usos de Channel Divinity, Action Surge, Second Wind, Indomitable, ciertos rasgos de subclase.
   - Se cuentan como usos discretos (ej. 2/3 usos por descanso).

3) Recursos de “DADOS”
   - Ej.: Superiority Dice (Fighter Battle Master), Bardic Inspiration (número de dados disponibles + tamaño del dado).
   - Tienen dos componentes:
     - Número de dados disponibles (usos).
     - Tamaño del dado (d6, d8, d10, d12) que escala con el nivel.

Cada recurso debe tener además:
- Tipo de recarga: descanso corto, descanso largo, ambos, u otro patrón (por ejemplo, “una vez por día” o “proficiency bonus por descanso largo”).
- Relación con la clase o subclase: qué clase lo otorga, en qué nivel aparece y cómo escala.

--------------------------------------------------
3. DETALLE POR CLASE (RECURSOS A INCLUIR)
--------------------------------------------------

El módulo “Recursos de Clase” debe ser capaz de representar, como mínimo, los siguientes recursos de las clases principales y sus particularidades:

3.1. Monje
- Recurso principal: PUNTOS DE KI.
- Características:
  - Máximo de puntos de ki = nivel de monje (regla estándar).
  - Se consumen puntos para activar diferentes técnicas (Flurry of Blows, Step of the Wind, etc.).
  - Se recuperan al completar un descanso corto o largo.
- El módulo debe:
  - Mostrar “Ki Points: actuales / máximo”.
  - Permitirá consumir puntos uno a uno o de forma configurable (ej. -2 de golpe).
  - Resetear al máximo en descansos cortos y largos.

3.2. Fighter / Guerrero
Recursos relevantes:

a) Action Surge
- Usos por descanso (normalmente 1, luego 2).
- Se recarga en descanso corto o largo.
- El módulo lo tratará como un recurso de USOS.

b) Second Wind
- Normalmente 1 uso por descanso corto o largo.
- También se gestiona como recurso de USOS.

c) Indomitable
- Usos por descanso largo (1, luego más con nivel).
- Recurso de USOS.

d) Battle Master – Superiority Dice
- Recurso de DADOS:
  - Número de dados (que aumenta con el nivel).
  - Tamaño del dado (d8 → d10 → d12).
- Se recargan en descanso corto o largo.
- El módulo debe permitir mostrar tanto “nº de dados actuales / máximo” como “tamaño del dado” asociado.

3.3. Bárbaro
- Recurso principal: RAGES (rabias).
- Características:
  - Número de rages por día que aumenta con el nivel.
  - Se suelen recuperar al completar un descanso largo.
- El módulo lo tratará como recurso de USOS:
  - “Rages: actuales / máximo”.
  - Al indicar que ha terminado un descanso largo, se restauran.

3.4. Bardo
- Recurso principal: BARDIC INSPIRATION.
- Características:
  - Número de usos basado normalmente en el modificador de Carisma (y que en ediciones recientes puede pasar a corto/largo rest).
  - Tamaño del dado que escala con el nivel (d6 → d8 → d10 → d12).
- El módulo debe tratarlo como recurso de DADOS:
  - Guardar número de usos disponibles.
  - Guardar tamaño del dado.
  - Aplicar la recarga apropiada según el nivel/reglas (descanso corto o largo).

3.5. Clérigo
- Recurso principal: CHANNEL DIVINITY.
- Características:
  - Número de usos (1, luego más a niveles altos).
  - Se recarga normalmente en descanso corto o largo.
  - Las subclases (dominios) añaden nuevas maneras de gastar el mismo pool, pero comparten usos.
- El módulo debe representarlo como un recurso de USOS compartido:
  - “Channel Divinity: actuales / máximo”.
  - Las diferentes opciones de dominio usarán este mismo contador.

3.6. Paladín
Recursos relevantes:

a) Lay on Hands
- Recurso de PUNTOS:
  - Pool de curación = 5 × nivel de paladín (puntos de vida).
  - Se va gastando según lo que sane.
  - Se recarga al completar un descanso largo.
- El módulo mostrará “Lay on Hands: puntos actuales / puntos máximos”.

b) Channel Divinity (según juramento)
- Igual que el clérigo, pero con las opciones propias del Paladín.
- Recurso de USOS, con recarga según las reglas (habitualmente descanso corto o largo).

3.7. Druida
- Recurso principal: WILD SHAPE.
- Características:
  - Número de usos (habitualmente 2).
  - Se recarga en descanso corto.
  - Algunas subclases (ej. Moon Druid) añaden modificaciones, pero siguen siendo usos limitados.
- El módulo lo tratará como recurso de USOS:
  - “Wild Shape: actuales / máximo”.
  - Se restauran en descanso corto.

3.8. Sorcerer / Hechicero
- Recurso principal: PUNTOS DE HECHICERÍA (Sorcery Points).
- Características:
  - Máximo de puntos de hechicería que escala con el nivel de hechicero.
  - Se usan para Metamagia o para convertir slots.
  - Se reutilizan y convierten; el detalle de conversión es responsabilidad del sistema de magia, pero el pool de puntos lo controla este módulo.
  - Se recargan al completar un descanso largo.
- El módulo los mostrará como recurso de PUNTOS:
  - “Sorcery Points: actuales / máximo”.

3.9. Warlock
- Los slots de Pact Magic se gestionan en el módulo de hechizos.
- “Recursos de Clase” debe manejar:
  - Mystic Arcanum: cada arcanum es “1 uso por descanso largo”.
  - Rasgos o invocaciones con un número de usos por descanso (por ejemplo, habilidades que dicen “X veces por descanso corto/largo”).
- Se gestionan como recursos de USOS individuales:
  - Por ejemplo: “Mystic Arcanum (nivel 6): 0/1 usos por descanso largo”.

3.10. Recursos genéricos de subclase y otras clases
- Muchas subclases de cualquier clase añaden rasgos del tipo:
  - “Puedes usar esta habilidad X veces por descanso corto/largo” o
  - “Puedes usarla tantas veces como tu modificador de [atributo] por descanso largo”.
- El módulo debe poder crear recursos genéricos con:
  - Nombre del recurso (ej. “Eldritch Smite”, “Vengeful Assault”, “Rune dice”…).
  - Máximo calculado a partir de nivel, modificador de habilidad o competencia.
  - Tipo de recarga (corto, largo o ambos).
- Ejemplos no exhaustivos:
  - Ciertas habilidades de Rogue (por ejemplo, rasgos de nivel alto tipo “una vez por descanso”).
  - Rasgos de subclase de Artificer, Ranger, etc., con usos limitados por descanso.

--------------------------------------------------
4. COMPORTAMIENTO DEL MÓDULO (ALTO NIVEL)
--------------------------------------------------

4.1. Cálculo inicial y actualización automática
- El módulo debe calcular, en función del nivel de cada clase y subclase del personaje:
  - Qué recursos están activos.
  - Su valor máximo (puntos, usos o dados).
- Este cálculo debe actualizarse automáticamente cuando:
  - El personaje sube de nivel.
  - Se modifica su clase o subclase.
  - Cambian atributos relevantes (ej. Carisma para Inspiración Bárdica, si se aplica).

4.2. Consumo manual por el usuario
- Desde la hoja de personaje, el jugador debe poder:
  - Reducir el valor actual de un recurso (gasto de puntos/uso/dado).
  - Opcionalmente aumentarlo, para corregir errores o reflejar efectos que devuelvan usos.
- Los cambios deben:
  - Reflejarse visualmente de inmediato.
  - Guardarse de forma inmediata (sin recargar página).

4.3. Interacción con habilidades
- Cada rasgo/habilidad de clase que consuma recursos debe estar vinculado a uno de estos recursos.
- Cuando el usuario marque que activa una habilidad:
  - La interfaz puede ofrecer una opción de “gastar el recurso asociado”.
  - El consumo real sigue siendo decisión del jugador (no se obliga), pero el flujo debe ser cómodo.

4.4. Descansos
- Al marcar un descanso corto o largo en la hoja de personaje:
  - El sistema debe llamar a este módulo para:
    - Restaurar los recursos que se recuperan con descanso corto (Ki, Wild Shape, ciertos usos de Fighter, algunos Channel Divinity, etc.).
    - Restaurar los recursos que se recuperan con descanso largo (Rages, Lay on Hands, Sorcery Points, Mystic Arcanum, etc.).
- La lógica de recarga se define en la configuración del recurso:
  - “Se recarga en descanso corto”.
  - “Se recarga en descanso largo”.
  - “Se recarga en ambos”.

--------------------------------------------------
5. INTEGRACIÓN EN LA HOJA DE PERSONAJE (UI / UX)
--------------------------------------------------

5.1. Bloque visual separado
- El módulo “Recursos de Clase” debe aparecer como un bloque separado en la hoja de personaje.
- Requisitos visuales:
  - Título claro: “Recursos de Clase”.
  - Agrupación por clase/subclase, por ejemplo:
    - Monje → Ki.
    - Bárbaro → Rages.
    - Bardo → Bardic Inspiration.
    - Etc.
  - Cada recurso en una línea o tarjeta con:
    - Nombre del recurso.
    - Valor actual / máximo.
    - Si aplica, información adicional (tamaño del dado, tipo de recarga).
- Este bloque no debe mezclarse con inventario, hechizos, ni estadísticas básicas, para que el jugador identifique rápidamente qué “motores de habilidades” tiene activos.

5.2. Interacción rápida
- El jugador debe poder ajustar los recursos con acciones muy sencillas (por ejemplo, botones de incremento/decremento o campo editable con guardado automático).
- Los cambios deben guardarse al momento (AJAX o mecanismo existente de autosave), sin necesidad de recargar página ni pulsar un botón de “guardar”.

5.3. Mensajes y ayudas
- Para cada recurso, puede mostrarse una breve descripción:
  - “Se recarga al finalizar un descanso corto”.
  - “Se recarga al finalizar un descanso largo”.
  - “Máximo = nivel de clase + modificador de Carisma” (cuando aplique).
- Esto sirve de ayuda rápida al jugador sin tener que abrir el texto completo de la habilidad.

--------------------------------------------------
6. PERSISTENCIA Y DINAMISMO
--------------------------------------------------

- Todos los cambios en los recursos (consumos, restauraciones manuales, ajustes por nivel) deben persistir en el backend inmediatamente.
- El módulo debe:
  - Escuchar los cambios de estado relevantes del personaje (subida de nivel, cambio de clase/subclase, cambio de atributos).
  - Recalcular automáticamente los máximos cuando corresponda (por ejemplo, nuevo nivel de monje → más ki; nuevo nivel de hechicero → más sorcery points; nivel de bardo → cambia dado de inspiración).
  - Mantener el valor actual cuando se modifican los máximos, ajustando solo si el actual supera el nuevo máximo.

--------------------------------------------------
7. EXTENSIBILIDAD
--------------------------------------------------

- El diseño del módulo debe permitir:
  - Añadir nuevos recursos de clase para homebrew (por ejemplo, clases propias como el Apothecary con sus fórmulas mayores si se decide gestionarlas aquí).
  - Configurar para cada recurso:
    - Nombre.
    - Tipo (puntos, usos, dados).
    - Fórmula de máximo (en función de nivel, atributo, competencia, etc.).
    - Tipo de recarga (corto, largo, ambos, o personalizado).
- Así, cualquier clase futura (oficial u original de Drakkenheim) que introduzca “X veces por descanso” o “pool de Y puntos” podrá integrarse sin crear un módulo nuevo; solo habrá que definir su recurso dentro de “Recursos de Clase”.

FIN DEL DOCUMENTO
