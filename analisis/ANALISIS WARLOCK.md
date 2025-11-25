DOCUMENTO DE ANÁLISIS DE ALTO NIVEL
MÓDULO: WARLOCK – PACT MAGIC, MYSTIC ARCANUM E INVOCACIONES
REUTILIZANDO EL MODELO DEL APOTECARIO + “RECURSOS DE CLASE”

OBJETIVO
Definir a alto nivel cómo debe funcionar la magia del Warlock en el proyecto (basado en D&D 5e), de forma que Codex pueda:

- Reutilizar el mismo “motor de slots de descanso corto” que ya hemos diseñado para el Apotecario (Pact Magic).
- Integrar los rasgos especiales del Warlock (Mystic Arcanum, Eldritch Invocations, Eldritch Master) en el módulo genérico de “Recursos de Clase”.
- Encajar el Warlock dentro del modelo de “lanzador de conjuros de hechizos conocidos” que ya tienes para el Hechicero y el Bardo.

La idea es que el Warlock no sea un caso aislado, sino otra configuración de los módulos que ya hemos definido: “Hechizos”, “Slots tipo Pact Magic” (Apotecario) y “Recursos de Clase”.

--------------------------------------------------
1. MODELO GENERAL DEL WARLOCK
--------------------------------------------------

1.1. Tipo de lanzador

- El Warlock es un lanzador de conjuros con un modelo especial llamado **Pact Magic**.  
- Su tabla de clase muestra:
  - Cuántos **espacios de conjuro** tiene para lanzar conjuros de **1.º a 5.º nivel**.
  - Qué **nivel tienen esos espacios**, y **todos los espacios son del mismo nivel**. :contentReference[oaicite:0]{index=0}  
- Igual que el Hechicero:
  - El Warlock **no prepara conjuros cada día**.  
  - Tiene un número de **hechizos conocidos** según su nivel, y puede lanzar cualquiera de ellos usando sus slots disponibles. :contentReference[oaicite:1]{index=1}  

Conclusión para Codex:
- El Warlock combina:
  - El modelo de **“hechizos conocidos”** (como Hechicero/Bardo).
  - Con un modelo de slots de conjuro **tipo Apotecario/warlock** (todos del mismo nivel + descanso corto/largo).

1.2. Recuperación de slots y descansos

- Pact Magic indica que el Warlock **recupera todos sus slots gastados** cuando termina un **descanso corto o largo**. :contentReference[oaicite:2]{index=2}  
- Esto lo diferencia de los lanzadores estándar (mago, clérigo, etc.), que solo recuperan slots en descanso largo.

Conclusión:
- El motor de slots “tipo Pact Magic/Apotecario” tiene que seguir claramente la regla “recarga en descanso corto O largo”, no solo en largo.

1.3. Característica de lanzamiento

- El Warlock usa **Carisma** como habilidad de lanzamiento (DC y tiradas de ataque de hechizo). :contentReference[oaicite:3]{index=3}  
- Esto ya encaja con el patrón general de la web (configurar en la clase qué atributo usa para la magia).

--------------------------------------------------
2. PACT MAGIC Y REUTILIZACIÓN DEL MODELO DEL APOTECARIO
--------------------------------------------------

El diseño que hicimos para el Apotecario (slots únicos por nivel de clase, todos del mismo nivel y con recarga en descanso corto) encaja casi 1:1 con Pact Magic del Warlock. :contentReference[oaicite:4]{index=4}  

2.1. Similitudes Apotecario – Warlock

Ambos comparten:

- Slots solo de **1.º a 5.º nivel**.
- **Todos los slots son del mismo nivel** en cada momento, indicado por la tabla de clase.
- Para lanzar un conjuro de nivel 1–5:
  - Gastan **1 slot**.
  - El conjuro se considera lanzado al **nivel del slot**, no al nivel base del conjuro (lo “upcastean” automáticamente). :contentReference[oaicite:5]{index=5}  
- Recuperan **todos los slots** al terminar un **descanso corto o largo**.

La gran diferencia es:

- Apotecario: **prepara conjuros** (lista flexible diaria).
- Warlock: **conoce conjuros** (lista fija de conocidos).

2.2. Qué debe reutilizar Codex

En lugar de duplicar lógica:

- El “tipo de slots” que definimos para el Apotecario (slots de descanso corto, único nivel de slot 1–5) debe generalizarse como un modelo **“Pact Magic / slots de descanso corto”**.
- Apotecario y Warlock pueden usar el mismo motor cambiando solo:
  - Cómo consiguen sus **listas de conjuros** (preparados vs conocidos).
  - El atributo de lanzamiento (INT vs CHA).
  - Sus recursos extra (Greater Formula vs Mystic Arcanum).

Conclusión:
- Warlock y Apotecario comparten el **mismo módulo de slots**, con distinta configuración de clase y distinta capa de “qué conjuros tiene disponibles”.

--------------------------------------------------
3. MYSTIC ARCANUM – HECHIZOS DE 6.º A 9.º NIVEL
--------------------------------------------------

A partir de nivel 11, el Warlock gana el rasgo **Mystic Arcanum**. :contentReference[oaicite:6]{index=6}  

3.1. Qué es Mystic Arcanum

- Nivel 11:
  - Obtiene **un conjuro de 6.º nivel** de la lista de Warlock como “arcanum”.  
  - Puede lanzarlo **una vez sin gastar slot**.  
  - Recupera ese uso al terminar un **descanso largo**. :contentReference[oaicite:7]{index=7}  
- Niveles posteriores:
  - Nivel 13 → añade un conjuro de **7.º nivel**.  
  - Nivel 15 → añade un conjuro de **8.º nivel**.  
  - Nivel 17 → añade un conjuro de **9.º nivel**.  
  - Cada uno se lanza **1 vez por descanso largo**, sin slots, de forma independiente. :contentReference[oaicite:8]{index=8}  

Para evitar confusión:

- Estos conjuros **no usan slots de Pact Magic**.
- Son “usos por día” separados, parecidos a las **Greater Formula** del Apotecario o a un recurso de clase con **1 uso por descanso largo** por cada nivel alto. :contentReference[oaicite:9]{index=9}  

3.2. Encaje con “Recursos de Clase”

Mystic Arcanum encaja perfectamente en el módulo “Recursos de Clase”:

- Cada nivel alto (6, 7, 8, 9) equivale a un recurso:
  - Nombre: “Mystic Arcanum (nivel X)” o directamente el nombre del conjuro elegido.
  - Tipo: **USOS** (no puntos).
  - Máximo: siempre 1 uso por descanso largo (por arcanum). :contentReference[oaicite:10]{index=10}  
- El módulo debe:
  - Mostrar estos recursos en el bloque de Recursos de Clase del Warlock.
  - Restaurarlos al máximo al marcar un **descanso largo**.
  - Permitir que el jugador los marque como gastados cuando lanza el conjuro correspondiente.

Conclusión:
- Mystic Arcanum se trata igual que las **Greater Formula del Apotecario**, reaprovechando el patrón de “hechizo de nivel alto con X usos por descanso largo, sin slots”.

--------------------------------------------------
4. ELDRITCH INVOCATIONS Y PACT BOON
--------------------------------------------------

4.1. Eldritch Invocations: qué son

- Las **Eldritch Invocations** son rasgos que el Warlock elige de una lista, con un número que escala con el nivel. :contentReference[oaicite:11]{index=11}  
- Pueden:
  - Dar habilidades pasivas (más daño, más alcance, visión especial…).  
  - Permitir lanzar ciertos conjuros “a voluntad” (sin slots).  
  - Permitir lanzar ciertos conjuros **1 vez por descanso corto/largo**, a veces usando slots y a veces sin ellos. :contentReference[oaicite:12]{index=12}  

Ejemplos típicos: :contentReference[oaicite:13]{index=13}  

- Mask of Many Faces → lanzar *disguise self* a voluntad (sin slots).  
- Mire the Mind → lanzar *slow* una vez usando slot de Warlock, recuperable en descanso largo.  
- Sculptor of Flesh → lanzar *polymorph* una vez usando slot de Warlock, descanso largo.  
- Devil’s Sight / Eldritch Mind, etc. → efectos constantes sin consumo.

4.2. Qué necesita el sistema

- Las invocaciones **no tienen todas el mismo patrón de recursos**:
  - Algunas son totalmente pasivas → no necesitan rastreo numérico.
  - Otras son “a voluntad” → tampoco necesitan contador (el límite lo impone el DM, no las reglas).
  - Varias dicen explícitamente: “una vez por descanso corto o largo” o “una vez por descanso largo” → esto SÍ encaja en **Recursos de Clase**.
- Además, algunas invocaciones permiten **lanzar conjuros usando slots de Warlock**; en esos casos:
  - El coste real es el slot (gestiona el módulo de slots).
  - La parte “una vez por descanso” es un límite adicional que debe tener un contador.

Conclusión para Codex:
- Para cada invocación que tenga texto del tipo “una vez por descanso corto/largo”, el sistema debe:
  - Crear un **recurso de USOS** asociado a esa invocación dentro de “Recursos de Clase”.
  - Restaurarlo según el tipo de descanso (corto, largo o ambos).
- Las invocaciones puramente pasivas o “a voluntad” no requieren recursos.

4.3. Pact Boon

- El Warlock elige un **Pact Boon** (Chain, Blade, Tome, etc.), que da un paquete de características (arma pacto, familiar especial, libro de rituales…). :contentReference[oaicite:14]{index=14}  
- Por sí mismo, el Pact Boon no suele llevar contador de recursos; son rasgos permanentes.
- Sin embargo:
  - Muchas invocaciones exigen tener un Pact Boon concreto (“prerequisito: Pact of the Blade”) y añaden recursos o efectos sobre ese pacto (ataques adicionales, formas de lanzar conjuros, etc.). :contentReference[oaicite:15]{index=15}  

Conclusión:
- Pact Boon se gestiona en la parte de “rasgos de clase/subclase”.
- Los recursos que añaden invocaciones asociadas al Pact Boon se gestionan vía “Recursos de Clase” tal y como se ha descrito.

--------------------------------------------------
5. OTROS RASGOS RELEVANTES: ELDRITCH MASTER
--------------------------------------------------

- A nivel 20, el Warlock obtiene **Eldritch Master**:
  - Puede pasar 1 minuto implorando a su patrón para recuperar **todos los slots de Pact Magic**, una vez por descanso largo. :contentReference[oaicite:16]{index=16}  
- Esto es, en la práctica, otro **recurso de uso 1/descanso largo** que afecta a los slots.

Conclusión:
- Eldritch Master debe representarse en “Recursos de Clase” como:
  - Un recurso con 1 uso que, cuando el jugador marca como gastado, significa que ya ha usado su “recarga extra” de slots.
  - En descanso largo, se restaura este uso.

--------------------------------------------------
6. INTEGRACIÓN EN LA HOJA DE PERSONAJE
--------------------------------------------------

Para el Warlock, la hoja de personaje debería organizarse así:

6.1. Bloque de Hechizos (Warlock)

- Mostrar lista de:
  - Cantrips conocidos.
  - Hechizos conocidos de nivel 1–5 (lista fija, no de preparados). :contentReference[oaicite:17]{index=17}  
- Mostrar el bloque de **Pact Magic**:
  - “Espacios de Warlock: X / X” (slots actuales / máximos).
  - “Nivel de slot actual: N” (tal como en el Apotecario). :contentReference[oaicite:18]{index=18}  
  - Nota: se recuperan en descanso corto o largo.

6.2. Bloque de Recursos de Clase (Warlock)

Dentro de “Recursos de Clase” el Warlock tendrá, al menos:

- Pacto de Magic y slots ya están gestionados por el módulo de slots compartido con Apotecario.
- Recursos adicionales que sí van en “Recursos de Clase”:
  - **Mystic Arcanum (6.º, 7.º, 8.º, 9.º)** → cada uno con 1 uso por descanso largo. :contentReference[oaicite:19]{index=19}  
  - **Invocaciones con límite de usos** → recursos de USOS por descanso corto, largo o ambos (según el texto de cada invocación). :contentReference[oaicite:20]{index=20}  
  - **Eldritch Master** → 1 uso por descanso largo para recargar slots extra. :contentReference[oaicite:21]{index=21}  

Visualmente:

- Bloque separado con título “Recursos de Clase – Warlock”.
- Agrupación por tipo:
  - “Hechizos de nivel alto (Mystic Arcanum)”.
  - “Invocaciones con usos limitados”.
  - “Otros rasgos (Eldritch Master, etc.)”.
- Interacción:
  - El jugador puede marcar usos gastados / restaurados.
  - Los descansos (corto/largo) disparan la lógica de recarga de recursos y slots.

--------------------------------------------------
7. RESUMEN DE TAREAS PARA CODEX
--------------------------------------------------

1. Marcar al Warlock como:
   - Lanzador de **hechizos conocidos** (no preparados), igual que el Hechicero/Bardo. :contentReference[oaicite:22]{index=22}  
   - Con slots tipo **Pact Magic / Apotecario** (slots de 1.º–5.º, todos al mismo nivel, recarga en descanso corto/largo). :contentReference[oaicite:23]{index=23}  

2. Reutilizar el módulo de slots de descanso corto diseñado para el Apotecario:
   - Ajustar la tabla de progresión a la del Warlock.
   - Cambiar solo:
     - El atributo de lanzamiento (Carisma).
     - Que el Warlock usa “hechizos conocidos” en vez de “preparados”.

3. Integrar **Mystic Arcanum** en “Recursos de Clase”:
   - Un recurso por nivel de arcanum (6.º, 7.º, 8.º, 9.º).
   - 1 uso por descanso largo cada uno.

4. Integrar **Eldritch Invocations** con límite de usos:
   - Para cada invocación que diga “una vez por descanso corto/largo”:
     - Crear un recurso de USOS asociado.
     - Configurar su recarga correspondiente.

5. Integrar **Eldritch Master** como recurso:
   - 1 uso por descanso largo que, cuando se usa, recarga todos los slots de Pact Magic.

6. Ajustar la UI de la hoja de personaje del Warlock:
   - Bloque de Hechizos: lista de conjuros conocidos + slots Pact Magic.
   - Bloque de Recursos de Clase: Mystic Arcanum, invocaciones con usos, Eldritch Master, etc.
   - Todo con guardado dinámico e inmediato, igual que el resto de “Recursos de Clase”.

Con este análisis, Codex puede implementar al Warlock aprovechando al máximo el modelo del Apotecario para los slots y el módulo genérico de “Recursos de Clase” para todo lo demás, sin necesidad de crear un sistema nuevo desde cero.

--------------------------------------------------
8. INTEGRACIÓN EN EL MÓDULO “RECURSOS DE CLASE”
--------------------------------------------------

Objetivo: activar al Warlock como una clase soportada por el módulo genérico de Recursos de Clase sin crear lógica ad-hoc.

- **Registrar clase**: añadir `warlock` al registro de clases soportadas por Recursos de Clase, indicando:
  - Tipo: `uses` (no puntos) para todos los recursos que añade la clase.
  - Recarga por defecto: descanso corto o largo para slots (ya manejado por Pact Magic) y descanso largo para el resto.
- **Recursos base auto-generados** (por nivel):
  - Mystic Arcanum nivel 6/7/8/9 → `uses_max: 1`, `recharge: long_rest`, etiqueta “Mystic Arcanum (nivel X)” con referencia al conjuro elegido en la hoja.
  - Eldritch Master → `uses_max: 1`, `recharge: long_rest`, etiqueta “Eldritch Master (recarga de slots)”.
- **Recursos desde invocaciones**:
  - Cuando una invocación tenga texto “una vez por descanso corto”, “una vez por descanso largo” o similar, crear un recurso:
    - `label`: nombre de la invocación.
    - `uses_max`: según el rasgo (normalmente 1).
    - `recharge`: `short_rest`, `long_rest` o `short_or_long` según indique la invocación.
  - Las invocaciones “a voluntad” o pasivas no crean recurso.
- **UI/UX dentro del bloque Recursos de Clase**:
  - Mostrar subtítulo “Warlock” y agrupar fichas:
    - Grupo “Mystic Arcanum” (cada nivel en su fila con toggle de uso).
    - Grupo “Invocaciones con usos” (una fila por invocación limitada).
    - Grupo “Otros rasgos” (Eldritch Master).
  - Cada fila hereda los estilos del módulo: título, contador `usos actuales / máximo`, botón de gasto/restauración, texto de recarga (corto/largo).
- **Persistencia y descansos**:
  - Guardar recursos de Warlock igual que el resto del módulo (AJAX/REST ya usado por otras clases).
  - En descanso corto: restaurar recursos con `recharge` corto o corto_largo.
  - En descanso largo: restaurar todos los recursos del Warlock (incluye Mystic Arcanum y Eldritch Master).
- **Sincronización con slots Pact Magic**:
  - Eldritch Master solo marca el uso; la recarga de slots sigue usando el motor de Pact Magic.
  - Mystic Arcanum no consume slots; se usa el contador del recurso.
