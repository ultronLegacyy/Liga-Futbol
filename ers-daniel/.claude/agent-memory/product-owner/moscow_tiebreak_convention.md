---
name: moscow-tiebreak-convention
description: Regla usada para asignar MoSCoW a una historia que cita requisitos de distinta prioridad en la ERS.
metadata:
  type: feedback
---

Cuando una historia del SGLD cita varios requisitos de la ERS (RF, RNF, AF) con distinta prioridad, se aplicó la regla "la más restrictiva gana": si alguno de los requisitos citados es Alta/Esencial, la historia es Must; si el más alto citado es Media/Deseado, es Should; solo si todos son Baja/Opcional, es Could.

**Por qué**: no hay una regla explícita en la ERS para este caso (una historia casi nunca mapea 1:1 a un solo requisito), y dejar la prioridad ambigua rompe la tabla de cobertura de `docs/trazabilidad.md`. Esta convención es una decisión del asistente (no del equipo), documentada así en `scrum/product-backlog.md` sección «Convenciones» del 2026-10-12.

**Cómo aplicarla**: úsala por defecto al redactar o refinar historias nuevas. Si genera un resultado que el equipo considera incorrecto en un caso concreto (como el conflicto AF-08/RF-10 de HU-048/HU-049, ver [[ers-internal-inconsistencies]]), no fuerces la regla: expón el conflicto como pregunta abierta en vez de decidir unilateralmente.
