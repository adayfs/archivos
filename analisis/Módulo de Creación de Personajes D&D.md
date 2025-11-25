# Módulo de Creación de Personajes (D&D 5e)  
**Análisis funcional de alto nivel**

## 1. Objetivo

Implementar un **asistente paso a paso (wizard)** para crear personajes de D&D 5e a **nivel 1**, usando como fuente de verdad los **JSON de reglas** ya presentes en el proyecto (clases, subclases, razas/especies, trasfondos, conjuros, feats, etc.).

El resultado final debe ser un **Personaje completo** integrado en el flujo actual:

- Hoja de personaje (módulo extendido).
- Grimorio / sistema de conjuros.
- Módulo de inventario.
- Resto de componentes ya existentes de la campaña.

El diseño debe facilitar que, en una fase posterior, el mismo modelo pueda servir como base para un **módulo de subida de nivel**.

---

## 2. Flujo general del wizard

Al pulsar “Crear nuevo personaje”, se inicia un flujo guiado de varios pasos. A alto nivel:

1. **Datos básicos**
   - Nombre del personaje.
   - Imagen / avatar (opcional).
   - Cualquier metadato mínimo que ya use la hoja (campaña, sistema, etc.).
   - El nivel inicial se fija en 1 (pero se guarda en el modelo).

2. **Selección de clase**
   - Listado de clases provenientes de los JSON del proyecto (incluyendo homebrew).
   - Al elegir una clase, se muestra un resumen básico (rasgos principales, dados de golpe, competencias, equipo inicial, etc.).
   - Se generan los formularios de elección propios de nivel 1 de esa clase:
     - Habilidades a elegir (skill proficiencies).
     - Rasgos configurables (por ejemplo “orden sagrada”, estilos, etc.).
     - Subclase si la clase la exige a nivel 1.

3. **Magia de clase (si aplica)**
   - Para clases lanzadoras de conjuros (y similares), se inicializan:
     - Cantrips conocidos a nivel 1.
     - Conjuros de nivel 1 preparados/conocidos, según el modelo de la clase.
     - Cualquier rasgo que otorgue conjuros “siempre preparados”.
   - Esta información debe integrarse con el **grimorio** y el sistema actual de spell slots.

4. **Trasfondo (Background)**
   - Selector de background basado en los JSON correspondientes.
   - Aplicar:
     - Competencias de habilidades.
     - Competencias de herramientas e idiomas.
     - Rasgos narrativos/mecánicos propios del background.
     - **Aumentos de característica** asociados al background (según la edición que se esté usando).
     - **Origin Feat** u otro feat inicial si el background lo define.

5. **Especie / Raza**
   - Selector de especie/raza con datos del JSON del proyecto (incluyendo contenido de ambientación).
   - Aplicar al personaje:
     - Tamaño, velocidad, sentidos especiales (visión en la oscuridad, etc.).
     - Rasgos raciales.
     - Cualquier elección interna de la especie (idioma extra, cantrip innato, subvariante, etc.).

6. **Puntuaciones de característica**
   - Asignación del **standard array** a STR/DEX/CON/INT/WIS/CHA (u otro método que se quiera soportar más adelante).
   - Aplicar los aumentos de característica del background (y raza/especie si corresponde).
   - Calcular modificadores finales, tiradas de salvación y habilidades en función de:
     - Puntuaciones finales.
     - Proficiency bonus del nivel 1.
     - Competencias otorgadas por clase, background, feats, etc.

7. **Equipamiento inicial**
   - Opciones de equipo inicial de la clase (paquetes tipo A/B, o elección por categorías).
   - Equipo del background.
   - Cualquier selección adicional necesaria (por ejemplo “elige un arma simple”, “elige un instrumento”, etc.).
   - Volcado del resultado al **módulo de inventario** que ya existe en el proyecto:
     - Objetos.
     - Oro inicial.

8. **Resumen y creación**
   - Pantalla de resumen con todas las elecciones:
     - Clase y rasgos iniciales.
     - Trasfondo, feats, competencias.
     - Especie/raza y rasgos raciales.
     - Puntuaciones finales de característica, modificadores, salvaciones y habilidades.
     - Conjuros (cantrips + preparados/conocidos) y espacios de conjuro.
     - Equipo inicial e inventario.
   - Al confirmar:
     - Se crea/actualiza la entidad de **Personaje** con todos los datos.
     - Se redirige a la **Hoja de personaje** ya inicializada.

---

## 3. Integración con el proyecto actual

A nivel de arquitectura, el módulo debe apoyarse en lo que ya existe:

- **JSON de reglas**  
  El wizard sólo interpreta y presenta información que ya está en los JSON de clases, background, razas, conjuros, etc., sin duplicar reglas a mano.

- **Hoja de personaje**  
  El resultado del wizard debe rellenar los mismos campos y estructuras que ahora se completan manualmente en la hoja (atributos, rasgos, salvaciones, habilidades, etc.).

- **Grimorio**  
  Cantrips y conjuros preparados/conocidos se deben registrar usando la misma lógica y estructuras que ya están en el grimorio (para que todo el sistema de slots, lanzamientos, etc., funcione sin cambios).

- **Inventario**  
  El equipo inicial se crea directamente con el módulo de inventario actualmente en uso (slots, objetos, oro, etc.).

- **Modelo de nivel**  
  Aunque inicialmente sólo se use nivel 1, es importante que las consultas a clase, subclase, conjuro, etc., se hagan siempre “en función del nivel actual”, de cara a reaprovechar la lógica en un futuro módulo de subida de nivel.

---

## 4. Reglas globales importantes

- **Evitar duplicidades de habilidades**
  - Si varias fuentes otorgan la misma habilidad (clase, background, feats…), debe respetarse la filosofía de 5e: permitir sustituir o elegir otra habilidad válida en lugar de acumular duplicados.
  - En la UI, lo ideal es que una habilidad ya elegida no vuelva a ofrecerse en otros selectores, o que se gestione explícitamente una sustitución.

- **Atributos y aumentos**
  - El flujo debe aplicar en orden:
    1. Array base de características.
    2. Aumentos del background (y especie/raza si el diseño lo requiere).
    3. Cualquier ajuste adicional que pueda llegar desde feats u otras fuentes.
  - Todo esto se consolida en las puntuaciones finales que usa la hoja.

- **Magia y conjuros**
  - El wizard sólo inicializa:
    - Lista de conjuros (cantrips y nivel 1).
    - Estado de “preparado” o equivalente.
    - Espacios de conjuro correspondientes al nivel 1.
  - El comportamiento en juego (gasto de slots, cambios de conjuros preparados, etc.) se sigue gestionando por el sistema ya existente.

- **Robustez y extensibilidad**
  - El módulo debería ser capaz de trabajar tanto con contenido oficial como con homebrew ya añadido al proyecto, siempre que los JSON respeten la estructura base.
  - La lógica de “qué desbloquea la clase en nivel X” debería estar centralizada para poder reutilizarla en el futuro módulo de subida de nivel.

---

## 5. Consideraciones futuras

Aunque este documento se centra en **nivel 1**, se recomienda que la implementación:

- Permita reusar el mismo motor de reglas para:
  - Subir de nivel (añadir rasgos, conjuros, ASI, dotes, etc. en niveles superiores).
  - Reconfigurar algunas elecciones de personaje (p.ej., cambiar conjuros preparados entre descansos largos).
- Mantenga el diseño del wizard relativamente desacoplado de la UI concreta (tabs, layout, etc.), para encajarlo sin fricción en la estructura visual actual de la campaña.

