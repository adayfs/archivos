DOCUMENTO DE IMPLEMENTACIÓN
MÓDULO GENÉRICO “RECURSOS DE CLASE”

Objetivo inmediato
- Consumir el catálogo JSON en data/ y generar instancias de recurso en la ficha con ACF.
- Soportar consumo/recuperación manual (parcial), recálculo por nivel/atributos y descanso corto/largo.
- Dejar la puerta abierta a multiclase via mergeKey y classInstanceId.

1) Catálogo y mapeo
- Ubicación: data/recursos-clase*.json (el que subiste). Campos clave: id, classId, subclassId, type, recharge.rest, max.formula/progression, dice.dieSizeProgression, mergeKey, source.
- classId/subclassId deben mapear a los slugs de data/class/ (p.ej. “monk”, “battle-master”). Si difieren, añade alias en código.
- Evaluador de fórmula: inputs disponibles: classLevel (por classId), totalLevel, strMod/dexMod/.../chaMod, prof. Sólo operaciones +,-,*,/, paréntesis, floor/ceil/min/max. Si no hay formula, usar progression (último valor cuyo level <= nivel).
- dieSize: igual que progression; elegir último die cuyo level <= nivel.

2) ACF (propuesta de campos)
- Grupo “recursos_de_clase” (repeater):
  - resource_id (text, readonly en UI).
  - class_id (text).
  - subclass_id (text, nullable).
  - type (select: points|uses|dice, readonly).
  - current (number).
  - max (number).
  - die_size (text, nullable; solo type=dice).
  - merge_key (text, nullable).
  - source (text).
  - level_snapshot (number; nivel de la clase cuando se calculó max).
- Clamp al guardar: current entre 0 y max.

3) Ciclo de vida / hooks
- initFicha(personaje):
  - Leer catálogo y clases activas del PJ.
  - Para cada recurso aplicable: si no existe instancia ACF, crear con max calculado y current = max.
  - Para recursos no aplicables (clase quitada), marcar para ocultar o archivar (no borrar por ahora).
- onNivelClaseChange(classId, newLevel):
  - Recalcular max y die_size. Si current > nuevo max, clamp a max; no subir current si baja el max.
- onAtributoChange(relevantMods):
  - Recalcular recursos cuya fórmula use esos mods.
- onRest(type=short|long):
  - Para cada recurso: si recharge.rest contiene type y mode=full => current = max.
  - Si mode=custom => llamar a customRecharge(resourceId, type) (pluggable).
- consume(resourceId, amount=1):
  - Validar amount > 0. current = max(0, current - amount). Guardar inmediato.
- restore(resourceId, amount=1):
  - Validar amount > 0. current = min(max, current + amount). Guardar inmediato.
- Operaciones manuales/parciales: UI usa consume/restore; para edición directa de current, aplicar clamp en save.

4) Multiclase y merge
- Separar instancias por (resource_id, class_id, subclass_id, class_instance_id opcional).
- mergeKey: si dos recursos activos comparten mergeKey, mostrar como uno (current = suma?, no; mejor una instancia principal y marcar “fuente combinada”). Recomendación: en V1 mantener separados y solo fusionar visualmente con etiqueta “compartido”.

5) UI (bloque “Recursos de Clase”)
- Agrupar por display.group o classId; ordenar por display.order.
- Mostrar: nombre, current/max, die_size si aplica, tipo de recarga (short/long/custom).
- Controles: botones +/-1 y campo numérico editable; guardar inmediato vía AJAX.
- Estados: clamp visual (no negativos, no sobre max); tooltip con notas y fuente.

6) Endpoints / acciones AJAX (esqueleto)
- GET /recursos/{personajeId}: devuelve instancias (id interno ACF, resource_id, current, max, die_size).
- POST /recursos/{id}/consume {amount}
- POST /recursos/{id}/restore {amount}
- POST /recursos/rest {type: short|long}
- POST /recursos/recalc (opcional tras subida de nivel/cambio atributo).

7) Próximos pasos concretos
- Confirmar slugs reales para classId/subclassId y ajustar el catálogo.
- Implementar evaluador de fórmula simple y lector de progression/dieSizeProgression.
- Añadir grupo ACF repeater según la propuesta y exponerlo en la ficha.
- Conectar eventos existentes de “descanso” y “nivel cambiado” a onRest/onNivelClaseChange.
- Pilotar con 3 recursos ya definidos (Ki, Action Surge, Lay on Hands) y verificar consumo/manual/restauración.
