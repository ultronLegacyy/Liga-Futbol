---
name: roadmap-sprint01-2026-10-12
description: Qué se creó en el primer roadmap S1-S9 y Sprint Backlog del Sprint 1, y los gaps/bloqueantes detectados al compararlo con PLANIFICACIÓN SCRUM.pdf
metadata:
  type: project
---

El 2026-10-12 se creó `scrum/sprints/roadmap.md` (roadmap provisional S1–S9, solo S1 detallado) y `scrum/sprints/sprint-01.md` (Sprint Backlog detallado de S1), a partir de `scrum/product-backlog.md` (69 historias) y `docs/PLANIFICACIÓN SCRUM.pdf` (objetivo y tareas técnicas por sprint, legible como texto — ver [[pdf-planificacion-legible]]).

Mapeo de sprint por épica/RF confirmado leyendo el PDF página por página (no estaba como columna explícita en el backlog, hay que inferirlo de las notas de riesgo de cada historia + el PDF):
- S1: RF-01, RF-02, RF-12.1–12.2 (EP-01 núcleo) — HU-001 a HU-008.
- S2: RF-03 (EP-02 parcial, afiliación) — HU-011 a HU-015.
- S3: RF-04 (EP-02 resto, jugadores) — HU-016 a HU-022.
- S4: RF-05, RF-06 (EP-03 parcial, logística/programación manual) — HU-023 a HU-031.
- S5: RF-07 (EP-03 Should, motor automático) — HU-032 a HU-035.
- S6: RF-08 (EP-04 parcial, cédula arbitral) — HU-036 a HU-040.
- S7: RF-09, RF-10 (tablas + disciplina) — HU-041 a HU-047 (HU-048/049 sin sprint claro, ver abajo).
- S8: RF-11, RF-13, RF-14 (tesorería + fases finales + asambleas) — HU-050 a HU-061, 12 historias, el sprint más sobrecargado.
- S9: RF-12.3–12.4, RF-15, RF-16 (portal + correo + exportables + cierre) — HU-062 a HU-069, HU-009, HU-010.

Gaps/bloqueantes encontrados al comparar backlog vs. PDF del equipo (no inventados, remitidos al equipo/PO en el roadmap):
1. **Tarea de Dev 4 del Sprint 9 está tachada en el PDF fuente**: SMTP, VPS, migración a PostgreSQL y dominio oficial. Esto deja sin sprint asignado tanto la configuración de correo (afecta HU-002, HU-003 en S1 y HU-064–HU-067 en S9) como el despliegue de producción (afecta RNF-05/RNF-20/RNF-32 de HU-062). Ya estaba parcialmente registrado como V-09/PA-07 en `scrum/pendientes-validacion.md`.
2. **HU-048/HU-049 (RF-10.5, inconformidades y recurso) no aparecen en ningún sprint del PDF del equipo**, aunque el backlog las agrupa temáticamente en EP-05/S7. Coincide con el conflicto de prioridad ya señalado como PA-03. Gap nuevo, no estaba en `pendientes-validacion.md` antes de esta tarea — se agregó al roadmap como «sin sprint asignado», debería trasladarse también a `pendientes-validacion.md` si el equipo lo confirma.
3. **«Tarjetón Digital» (S2 del PDF) no tiene HU equivalente clara en el backlog**: el PDF pide generar un PDF de afiliación con firmas en S2, pero la HU más cercana (HU-069) es de EP-08 y está mucho más adelante (S9). Remitido al Product Owner en el roadmap, sección Sprint 2.

Sprint 1: capacidad del equipo y decisiones técnicas base (versiones PHP/Laravel/MariaDB, túnel, paquete de roles, ramas — V-08) seguían sin resolver al momento de escribir el Sprint Backlog. Se propuso un núcleo mínimo (HU-005, HU-007, HU-001 reducida a pocos roles, HU-004) y opcionales (HU-006, HU-008, HU-002, HU-003, estas dos últimas bloqueadas por SMTP).

**Cómo aplicar**: antes de tocar roadmap.md o sprint-NN.md de sprints posteriores, revisar si estos gaps ya se resolvieron en `docs/decisiones.md` o `scrum/pendientes-validacion.md`; si siguen abiertos, mantener la misma redacción de «remitido al equipo/PO», no inventar la resolución.
