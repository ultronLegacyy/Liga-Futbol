---
name: pendientes-ya-documentados
description: scrum/pendientes-validacion.md ya centraliza los bloqueantes (V-01 a V-10) y las preguntas abiertas (PA-01 a PA-14) del backlog; léelo antes de volver a derivarlos
metadata:
  type: reference
---

`scrum/pendientes-validacion.md` (mantenido junto con el agente product-owner) ya trae, a la fecha 2026-10-12:
- Sección A: V-01 a V-10, bloqueantes antes de comprometer el Sprint 1 (credenciales expuestas en el repo público, capacidad del equipo sin declarar, asignación Dev3/Dev4 sin confirmar, decisiones técnicas base sin tomar, SMTP sin sprint, DoD/DoR sin aprobar, etc.).
- Sección B: PA-01 a PA-14, resumen de las preguntas abiertas del Product Backlog, con a quién corresponde resolverlas.
- Secciones C, D, E: validación pendiente con la Liga (Anexo B), inconsistencias de la ERS, y BPMN.

**Cómo aplicar**: cuando se prepare un Sprint Backlog, una agenda de ceremonia o un roadmap, citar estos IDs (V-NN, PA-NN) en vez de redactar de nuevo el mismo bloqueante con palabras distintas — mantiene consistencia entre lo que escribe este agente y lo que escribe `product-owner`. Si se detecta un bloqueante nuevo que no está en ese archivo (como el gap de HU-048/HU-049 sin sprint, encontrado el 2026-10-12, ver [[roadmap-sprint01-2026-10-12]]), señalarlo en el documento de salida pero recordar que la actualización formal de `pendientes-validacion.md` la hace quien lo mantiene — no se debe escribir ahí directamente desde este rol salvo instrucción explícita, porque está fuera de `scrum/sprints/` y `scrum/ceremonias/`.
