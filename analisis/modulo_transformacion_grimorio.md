# Módulo de Transformación para el Grimorio

## 1. Objetivo del módulo
Implementar un sistema dentro del Grimorio que permita activar una **transformación temporal** que consume un spell slot y modifica varias estadísticas del personaje. Este módulo debe integrarse a la lógica existente de consumo de spell slots.

## 2. Elementos que deben añadirse al Grimorio
### 2.1 Botón de Transformar
- Debe mostrarse en la interfaz del Grimorio.
- Al pulsarse, abre una **ventana modal** para seleccionar el nivel del spell slot.

### 2.2 Modal de selección
- La modal debe listar **solo los niveles de spell slots disponibles**.
- El jugador selecciona un nivel para activar la transformación.
- Al confirmar, se consume el slot según la lógica existente.

## 3. Estado de Transformación
Al activarse la transformación, debe aparecer un bloque dedicado compuesto por dos columnas:

### 3.1 Columna izquierda
- Espacio para una imagen representativa.
- Debe ocupar una columna completa.

### 3.2 Columna derecha
Mostrar todas las estadísticas alteradas durante la transformación:
- Strength ↔ Intelligence (intercambio de puntuaciones).
- Tamaño: Large.
- Distancia de salto duplicada.
- Capacidad de carga incrementada (contar como un tamaño más).
- Ataque natural: puños que infligen `1d10 + STR(mod) + nivel de Apothecary`.
- Restricción: no puede lanzar ni concentrar hechizos.
- Darkvision: 120 ft.
- Puntos de golpe temporales: `5 × nivel de Apothecary`.

### 3.3 Valores dependientes del nivel del slot
Los siguientes valores dependen del nivel del spell slot empleado:
- Velocidad: `velocidad_base + (5 × nivel del slot)`.
- AC: `13 + nivel del slot`.
- Regeneración por turno: `nivel del slot`.

## 4. Comportamiento dinámico
### 4.1 Activación
- Consumir el spell slot seleccionado.
- Calcular las estadísticas transformadas.
- Renderizar el bloque de transformación.
- Registrar un estado interno de "transformación activa".

### 4.2 Duración
- Dura 1 minuto o hasta que el personaje caiga a 0 HP o quede incapacitado.
- El jugador debe finalizar manualmente la transformación.

### 4.3 Finalización
- Restaurar las estadísticas base.
- Eliminar el bloque visual.
- Borrar el estado de transformación activa.

## 5. Dependencias y relaciones del sistema
El módulo debe consultar:
- Nivel de Apothecary.
- Estadísticas actuales del personaje.
- Velocidad base.
- AC base.
- Estado actual de spell slots.

El sistema de transformación debe integrarse a la lógica existente de:
- Identificación de slots libres.
- Consumo de slots.

## 6. Diseño del bloque de transformación
### 6.1 Estructura UI
- Contenedor visual estilo "estado activo".
- Dos columnas: imagen (izquierda) y estadísticas (derecha).

### 6.2 Datos mostrados
- Atributos transformados.
- AC modificada.
- PV temporales.
- Nuevo ataque natural.
- Velocidad ajustada.
- Regeneración.
- Detalles de salto y tamaño.
- Descripción de restricciones.
- Nivel del spell slot usado.

## 7. Restauración de estado
Al terminar la transformación:
- Revertir atributos.
- Restaurar estadísticas alteradas.
- Limpiar la interfaz.
- Mantener el slot consumido.

## 8. Validaciones necesarias
- No permitir la transformación si no hay slots disponibles.
- No permitir activar si ya está transformado.
- Asegurar restauración completa.

## 9. Consideraciones adicionales
- La imposibilidad de lanzar hechizos debe mostrarse, pero sin modificar la ficha base.
- El ataque natural debe mostrarse solo dentro del bloque de transformación.
- No debe alterarse permanentemente ninguna estadística del personaje.
