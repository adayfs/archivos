DOCUMENTO DE ANÁLISIS DE ALTO NIVEL
MÓDULO: HECHICERO (SORCERER) – GESTIÓN DE HECHIZOS Y METAMAGIA
INTEGRACIÓN CON “RECURSOS DE CLASE”

OBJETIVO
Definir a alto nivel cómo debe funcionar la magia del Hechicero en el proyecto (basado en D&D 5e), para que Codex implemente:

Su modelo de lanzador de conjuros (full caster con hechizos conocidos). 
a5e.tools

Su recurso especial de clase: Puntos de Hechicería (Sorcery Points), integrado en el módulo “Recursos de Clase”. 
5esrd.com

La conversión flexible entre puntos y espacios de conjuro (Flexible Casting). 
5esrd.com
+1

El sistema de Metamagia (aplicar efectos especiales a un conjuro gastando puntos de hechicería). 
Roll20
+1

La idea es que el Hechicero use la infraestructura general de lanzadores de conjuros de la web, más el nuevo módulo genérico de “Recursos de Clase”, en vez de inventar un sistema específico solo para él.

MODELO GENERAL DE HECHICERO

1.1. Tipo de lanzador

El Hechicero es un lanzador completo: tiene tabla de espacios de conjuro de 1º a 9º nivel, igual que Mago o Clérigo (slots por nivel, se recuperan con descanso largo). 
a5e.tools
+1

A diferencia del Mago/Clérigo, no prepara conjuros cada día: tiene una lista fija de hechizos conocidos que va creciendo con el nivel. 
a5e.tools
+1

Consecuencia para Codex:

Puede reutilizar el módulo de “caster de hechizos conocidos con slots estándar” (bardo + hechicero comparten este enfoque).

No debe usar el modelo “hechizos preparados” de mago/clérigo, ni el modelo “slots todos al mismo nivel” del warlock/apotecario.

1.2. Hechizos conocidos y espacios de conjuro

La tabla de Hechicero define:

Hechizos conocidos por nivel de clase (Spells Known).

Espacios de conjuro por nivel de conjuro (Spell Slots). 
a5e.tools
+1

El personaje solo puede lanzar:

Hechizos de la lista de Hechicero que tenga en su lista de hechizos conocidos.

Usando un espacio de conjuro de nivel igual o superior al nivel del hechizo. 
a5e.tools

Cuando sube de nivel, puede cambiar uno de sus hechizos conocidos por otro de la lista de Hechicero que cumpla las restricciones de nivel. 
a5e.tools
+1

Consecuencia para Codex:

En la hoja de personaje:

Mostrar la lista de hechizos conocidos de Hechicero, no una lista de preparados.

Controlar que el número de conocidos no supere el valor de la tabla.

Solo permitir aprender hechizos de nivel para los que tenga espacios de conjuro.

Ofrecer el cambio de un hechizo conocido al subir de nivel.

1.3. Espacios de conjuro y descansos

El Hechicero recupera todos los espacios de conjuro al terminar un descanso largo. 
a5e.tools
+1

No recupera slots en descanso corto (a diferencia de warlock/apotecario).

Consecuencia para Codex:

Se puede seguir usando la lógica general de slots estándar ya existente para otros lanzadores completos.

El módulo de descanso corto no toca los slots del Hechicero; el descanso largo sí los restaura.

1.4. Característica de lanzamiento

El Hechicero usa Carisma como habilidad de lanzamiento (DC y tiradas de ataque de hechizo). 
dndbeyond.com
+1

Esto probablemente ya está cubierto por la capa genérica de clases, pero hay que asegurarse de que el Sorcerer está marcado como “spellcastingAbility = CHA”.

RECURSOS DE CLASE: PUNTOS DE HECHICERÍA (FONT OF MAGIC)

A nivel 2, el Hechicero obtiene la característica Font of Magic, que introduce un recurso numérico: los Sorcery Points (Puntos de Hechicería). 
5esrd.com

Este recurso encaja directamente en el módulo genérico “Recursos de Clase” que hemos definido.

2.1. Naturaleza del recurso

Tipo: PUNTOS (no usos ni dados).

Máximo: definido por una tabla por nivel de Hechicero (en el PHB: normalmente coincide con el nivel de clase, hasta un límite). 
5esrd.com
+1

Recarga: se restauran todos los puntos al terminar un descanso largo. 
5esrd.com

Consecuencia para Codex:

En “Recursos de Clase”, crear un recurso para el Hechicero:

Nombre: “Puntos de Hechicería”.

Tipo: Puntos.

Máximo dependiente del nivel de Hechicero.

Recarga: descanso largo.

2.2. Flexible Casting: uso de los puntos para crear o absorber slots

La característica Flexible Casting permite convertir Sorcery Points en espacios de conjuro y viceversa. 
5esrd.com
+1

Crear espacios de conjuro:

Como acción adicional, el Hechicero puede gastar puntos de hechicería para crear un espacio de conjuro de 1º a 5º nivel.

El coste en puntos está definido en una tabla (por ejemplo, 2 puntos para slot de 1º nivel, 3 para 2º, 5 para 3º…) y solo se pueden crear slots hasta nivel 5. 
dnd-5e.fandom.com
+1

Los espacios de conjuro creados de esta forma desaparecen al final de un descanso largo, si no se han usado. 
dnd-5e.fandom.com
+1

Convertir espacios en puntos:

Como acción adicional, el Hechicero puede gastar un espacio de conjuro para recuperar puntos de hechicería.

Gana un número de puntos igual al nivel del espacio gastado. 
dnd-5e.fandom.com
+1

Conversión no simétrica:

Cuesta más puntos crear un slot que los puntos que se obtienen al destruir ese mismo slot; es un sistema “con pérdidas”. 
forums.giantitp.com
+1

Consecuencia para Codex (a nivel conceptual, sin código):

Flexible Casting es un “puente” entre:

El recurso de clase Puntos de Hechicería (módulo Recursos de Clase).

La tabla de espacios de conjuro general del personaje.

El sistema debe permitir:

Reducir Sorcery Points y aumentar temporalmente la reserva de slots disponibles (para slots creados con puntos), marcando que esos slots extra se pierden al finalizar descanso largo.

Reducir slots existentes y aumentar el contador actual de Sorcery Points.

El cálculo concreto de costes y límites se basa en la tabla oficial, pero toda esa lógica se centraliza en este módulo conceptual de conversión, no en cada hechizo.

2.3. Integración con descansos

Descanso corto:

No afecta a Sorcery Points ni a Flexible Casting.

Descanso largo:

Restaura los Sorcery Points al máximo. 
5esrd.com

Elimina cualquier espacio de conjuro creado con puntos que no se haya gastado. 
5esrd.com
+1

Consecuencia para Codex:

Cuando la hoja del personaje marca “descanso largo”, el módulo de Recursos de Clase debe:

Resetear Puntos de Hechicería.

Notificar al sistema de slots que descarte los slots marcados como “creados por Flexible Casting”.

METAMAGIA

A nivel 3 el Hechicero obtiene la característica Metamagic. 
5esrd.com
+1

3.1. Concepto general

El Hechicero aprende opciones de Metamagia (dos al nivel 3, más en niveles 10 y 17). 
5esrd.com
+1

Cuando lanza un hechizo, puede gastar Puntos de Hechicería para aplicar una de esas opciones y alterar el hechizo (rango, duración, número de objetivos, etc.). 
Roll20
+1

Regla importante:

Solo se puede aplicar una opción de Metamagia por conjuro…

…salvo Empowered Spell, que explícitamente permite combinarse con otra opción. 
Roll20
+1

Consecuencia para Codex:

El sistema de lanzado de hechizos debe permitir, en el flujo de lanzamiento:

Seleccionar opcionalmente una opción de Metamagia conocida (o ninguna).

Aplicar las restricciones de combinación (Empowered puede sumarse, las demás no).

Disminuir los Puntos de Hechicería según el coste de la opción elegida.

3.2. Opciones estándar de Metamagia

El sistema debe contemplar las opciones de Metamagia básicas del PHB, entre otras: 
Roll20
+1

Careful Spell (si se usa): protege aliados de ciertos efectos al obligarles a superar salvaciones, gastando puntos.

Distant Spell: gasta puntos para aumentar el rango de un hechizo (doblarlo o convertir toque en 30 pies).

Empowered Spell: permite repetir dados de daño hasta un máximo ligado a Carisma; se puede combinar con otra Metamagia.

Extended Spell: dobla la duración del hechizo (hasta un límite de 24 horas).

Heightened Spell: da desventaja al objetivo en su primera tirada de salvación contra el hechizo, gastando varios puntos.

Quickened Spell: cambia el tiempo de lanzamiento de 1 acción a acción adicional.

Subtle Spell: permite lanzar el hechizo sin componentes verbales ni somáticos.

Twinned Spell: cuando un hechizo solo afectaría a un objetivo y no puede ya afectar a varios, permite duplicar el objetivo gastando puntos igual al nivel del hechizo (1 si es truco).

(La lista exacta de opciones y costes debe estar parametrizada; aquí solo indicamos los efectos generales que el motor debe ser capaz de representar.)

Consecuencia para Codex:

Para cada opción de Metamagia se requiere:

Coste en Puntos de Hechicería.

Restricciones de elegibilidad (por ejemplo, Twinned solo cuando el hechizo a ese nivel no puede afectar a más de un objetivo, Quickened solo si el tiempo es 1 acción, etc.).

Efecto narrativo/mecánico sobre el hechizo (cambio de rango, tiempo de lanzamiento, duración, número de objetivos, componentes, etc.).

El módulo de Metamagia debe apoyarse en:

El módulo de hechizos (para conocer rango, duración, tiempo de lanzamiento original, número de objetivos, etc.).

El módulo de Recursos de Clase para el gasto de Puntos de Hechicería.

3.3. Gestión de Metamagia conocida

El número de opciones de Metamagia conocidas depende del nivel (2 al 3, luego se añaden más, pudiendo cambiar alguna al subir de nivel). 
5esrd.com
+1

Estas opciones se comportan como “elecciones permanentes” de clase, similares a invocaciones o rasgos de subclase.

Consecuencia para Codex:

En la configuración del personaje, el Hechicero debe tener:

Un listado de opciones de Metamagia disponibles globalmente (catálogo).

Un campo para las opciones de Metamagia elegidas, con límite según nivel de Hechicero.

Al subir de nivel, la UI debe permitir:

Añadir nuevas opciones cuando la tabla lo indique.

Sustituir alguna opción antigua, si se sigue las reglas de cambio permitidas.

INTEGRACIÓN SORCERER + RECURSOS DE CLASE + HECHIZOS

4.1. Relación entre Hechizos, Slots y Puntos de Hechicería

Desde el punto de vista del sistema, el Hechicero combina tres “bloques” de recursos: 
Role-playing Games Stack Exchange
+1

Hechizos conocidos:

Lista fija que solo cambia al subir de nivel.

Se gestiona igual que cualquier caster de “hechizos conocidos”.

Espacios de conjuro estándar:

Tabla de slots por nivel, compartida con otros full casters.

Se recuperan en descanso largo.

Puntos de Hechicería (Recurso de Clase):

Pool numérico propio de la clase.

Se gasta en:

Metamagia.

Flexible Casting para crear slots.

Se recarga en descanso largo.

Flexible Casting es el enlace entre 2 y 3; Metamagia es la principal forma de gastar 3 sobre 1 y 2.

4.2. Vista en la hoja de personaje

En la hoja de personaje, para el Hechicero:

Bloque de Hechizos:

Mostrar la lista de trucos y hechizos conocidos de la clase.

Mostrar los espacios de conjuro disponibles por nivel.

Bloque de Recursos de Clase:

Mostrar claramente “Puntos de Hechicería: actuales / máximo”, con indicación de que se recargan en descanso largo.

Flujo de lanzamiento de hechizo:

Seleccionar hechizo conocido.

Elegir el nivel de slot con el que se va a lanzar (si hay varias opciones).

Opcionalmente, elegir Metamagia disponible (respetando las restricciones de combinación).

Consumir:

Espacio de conjuro.

Puntos de Hechicería si se ha aplicado Metamagia.

Interacción con Flexible Casting:

Proporcionar en la UI acciones para:

“Convertir slot en Puntos de Hechicería” (reduciendo slots y aumentando puntos).

“Convertir Puntos de Hechicería en slot” (reduciendo puntos y aumentando slots marcados como “creados por puntos”).

4.3. Multiclase (nota conceptual)

En multiclase, los slots de conjuro se calculan con las reglas estándar de 5e; el Hechicero aporta como lanzador completo a ese cálculo. 
Role-playing Games Stack Exchange
+1

Los Puntos de Hechicería dependen únicamente del nivel de Hechicero (no de otras clases). 
5esrd.com
+1

Flexible Casting opera sobre los slots del personaje, independientemente de qué clase los aporte, siempre que el DM use las reglas oficiales. 
Role-playing Games Stack Exchange
+1

Para Codex, basta con que el módulo de Sorcerer sea compatible con la lógica general de multiclase; no es necesario resolver todos los casos avanzados en esta fase, pero sí tener en cuenta que Puntos de Hechicería y slots no escalan con la misma fórmula.

ANEXO: IMPLEMENTACIÓN EN EL GRIMORIO (RECURSOS DE CLASE)

- Datos que vienen del módulo Recursos de Clase: “Puntos de Hechicería” (tipo Puntos, máx. por nivel de Hechicero, recarga en descanso largo) + bandera de “slot creado por Flexible Casting” para distinguir qué slots se pierden en descanso largo.
- Vista Grimorio → bloque “Puntos de Hechicería”: muestra actual/máximo y dos acciones rápidas:
  - “Convertir slot en puntos”: selector de nivel de slot disponible → resta ese slot y suma puntos iguales al nivel.
  - “Crear slot con puntos”: selector 1–5, muestra coste según tabla → valida que hay puntos suficientes, resta puntos y añade slot marcado como “creado con puntos”.
- Flujo de lanzamiento en Grimorio:
  - Paso 1: elegir hechizo conocido y nivel de slot (incluye slots creados con puntos).
  - Paso 2 (opcional): elegir Metamagia conocida respetando la regla “solo 1, salvo Empowered que puede sumarse”.
  - Paso 3: validar coste en Puntos de Hechicería de la Metamagia y descontarlos; consumir el slot elegido; guardar en el historial del conjuro qué Metamagia se aplicó.
- Descanso largo (hook global del Grimorio):
  - Resetear Puntos de Hechicería al máximo.
  - Eliminar o marcar como no disponibles todos los slots etiquetados como “creados con puntos”.
  - No tocar slots estándar.
- Gestión de Metamagia conocida en la edición de personaje:
  - Catálogo fijo de opciones (nombre, coste, restricción, efecto narrativo).
  - Campo “Metamagias aprendidas” con límite dinámico por nivel de Hechicero (2 al 3, +1 al 10, +1 al 17) y opción de sustituir una al subir de nivel.
  - Estas opciones alimentan el selector del flujo de lanzamiento.

RESUMEN DE TAREAS PARA CODEX

Marcar al Hechicero como:

Lanzador completo con slots estándar (modelo ya existente).

Caster de “hechizos conocidos” (no preparados).

Integrar Puntos de Hechicería en el módulo “Recursos de Clase”:

Recurso de tipo PUNTOS, con máximo según nivel de Hechicero.

Recarga en descanso largo.

Implementar la lógica conceptual de Flexible Casting:

Gastar puntos → crear slots temporales de nivel 1–5 (que desaparecen en descanso largo).

Gastar slots → recuperar puntos.

Implementar el sistema de Metamagia:

Gestión de opciones conocidas según nivel.

Elección de Metamagia al lanzar hechizos.

Consumo de Puntos de Hechicería y validación de restricciones por opción.

Regla de combinación (solo una opción por conjuro, salvo Empowered Spell).

Ajustar la UI de hoja de personaje:

Bloque de Hechizos: lista de conocidos + slots estándar.

Bloque de Recursos de Clase: Puntos de Hechicería, con controles para consumo manual y Flexible Casting.

Flujo de lanzamiento que permita elegir opcionalmente Metamagia, todo con guardado dinámico e inmediato.
