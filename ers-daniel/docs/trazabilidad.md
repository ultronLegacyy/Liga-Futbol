# Matriz de trazabilidad: SGLD

Relaciona cada requisito de la ERS con las épicas, historias, sprints y claves de Jira que lo implementan. La mantiene el agente Product Owner; el Product Owner humano la aprueba.

Reglas:
- Una fila por requisito de la ERS (RF-xx o RF-xx.y, RNF-xx, AF-xx, OR-xx, IU/IH/IC).
- Solo se registran épicas e historias que existan en `scrum/product-backlog.md`.
- Sin historia asignada: «sin cubrir». Fuera de la primera versión: «fuera de alcance», con referencia a la decisión correspondiente.
- La clave de Jira se anota cuando la incidencia exista.
- Estado «Propuesto»: historia refinada en el backlog, sin estimar ni aprobar por el equipo. Se actualizará a medida que el equipo apruebe, estime y desarrolle cada historia.
- Requisitos transversales (RNF-03, RNF-18, RNF-24, RNF-34, RNF-37 a RNF-39; IU-03, IU-04; IC-01, IC-05): no tienen una historia dedicada; se cubren mediante la Definition of Done propuesta en `scrum/product-backlog.md` y se verifican en cada historia con interfaz de usuario o cambios de código.
- Sprint «por asignar»: la historia es nueva o su alcance cambió el 2026-10-12 (división por tamaño del equipo, 2-4 h diarias por integrante); el Scrum Master la ubica en el próximo Sprint Planning.

## Requisitos → backlog

### Requisitos funcionales (RF)

| Requisito ERS | Prioridad ERS | Épica | Historias | Sprint | Clave Jira | Estado |
|---|---|---|---|---|---|---|
| RF-01.1 | Alta/Esencial | EP-01 | HU-005 | S1 | | Propuesto |
| RF-01.2 | Alta/Esencial | EP-01 | HU-006 | S1 | | Propuesto |
| RF-01.3 | Alta/Esencial | EP-01 | HU-005 | S1 | | Propuesto |
| RF-01.4 | Alta/Esencial | EP-01 | HU-005 | S1 | | Propuesto |
| RF-02.1 | Alta/Esencial | EP-01 | HU-007 | S1 | | Propuesto |
| RF-02.2 | Alta/Esencial | EP-01 | HU-007 | S1 | | Propuesto |
| RF-02.3 | Alta/Esencial | EP-01 | HU-008 | S1 | | Propuesto |
| RF-02.4 | Alta/Esencial | EP-01 | HU-007 | S1 | | Propuesto |
| RF-03.1 | Alta/Esencial | EP-02 | HU-011, HU-012, HU-070 | S2 | | Propuesto |
| RF-03.2 | Alta/Esencial | EP-02, EP-03 | HU-013, HU-026 | S2, S4 | | Propuesto |
| RF-03.3 | Alta/Esencial | EP-02 | HU-014 | S2 | | Propuesto |
| RF-03.4 | Alta/Esencial | EP-02 | HU-015 | S2 | | Propuesto |
| RF-04.1 | Alta/Esencial | EP-02 | HU-016, HU-021 | S3 | | Propuesto |
| RF-04.2 | Alta/Esencial | EP-02 | HU-017 | S3 | | Propuesto |
| RF-04.3 | Alta/Esencial | EP-02 | HU-018 | S3 | | Propuesto |
| RF-04.4 | Alta/Esencial | EP-02 | HU-019, HU-021 | S3 (validación completa depende de HU-050, S8 — ver PA-12) | | Propuesto |
| RF-04.5 | Alta/Esencial | EP-02 | HU-022 | S3 | | Propuesto |
| RF-05.1 | Alta/Esencial | EP-03 | HU-023 | S4 | | Propuesto |
| RF-05.2 | Alta/Esencial | EP-03 | HU-024 | S4 | | Propuesto |
| RF-05.3 | Alta/Esencial | EP-03 | HU-025 | S4 | | Propuesto |
| RF-06.1 | Alta/Esencial | EP-03 | HU-026, HU-027 | S4 | | Propuesto |
| RF-06.2 | Alta/Esencial | EP-03 | HU-028 | S4 | | Propuesto |
| RF-06.3 | Alta/Esencial | EP-03 | HU-029 | S4 | | Propuesto |
| RF-06.4 | Alta/Esencial | EP-03 | HU-029 | S4 | | Propuesto |
| RF-06.5 | Alta/Esencial | EP-03 | HU-030 | S4 | | Propuesto |
| RF-06.6 | Alta/Esencial | EP-03 | HU-031 | S4 | | Propuesto |
| RF-07.1 | Media/Deseado | EP-03 | HU-032 | por asignar | | Propuesto (historia dividida el 2026-10-12) |
| RF-07.2 | Media/Deseado | EP-03 | HU-074 | por asignar | | Propuesto (antes HU-032; dividida el 2026-10-12; restricción de sanción depende de HU-047 — ver PA-13) |
| RF-07.3 | Media/Deseado | EP-03 | HU-033 | por asignar | | Propuesto (depende de HU-032/HU-074, divididas el 2026-10-12) |
| RF-07.4 | Media/Deseado | EP-03 | HU-034 | por asignar | | Propuesto (depende de HU-032/HU-074, divididas el 2026-10-12) |
| RF-08.1 | Alta/Esencial | EP-04 | HU-036, HU-037, HU-075 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12; HU-075 es la parte nueva) |
| RF-08.2 | Alta/Esencial | EP-04 | HU-038 | por asignar | | Propuesto (depende de HU-036/HU-075, divididas el 2026-10-12) |
| RF-08.3 | Alta/Esencial | EP-04 | HU-039 | por asignar | | Propuesto (rol por confirmar, PA-02; depende de HU-075) |
| RF-08.4 | Alta/Esencial | EP-04 | HU-040 | por asignar | | Propuesto (rol por confirmar, PA-02; depende de HU-075) |
| RF-09.1 | Alta/Esencial | EP-04 | HU-041 | S7 | | Propuesto |
| RF-09.2 | Alta/Esencial | EP-04 | HU-042 | S7 | | Propuesto |
| RF-09.3 | Alta/Esencial | EP-04 | HU-043 | S7 | | Propuesto |
| RF-09.4 | Alta/Esencial | EP-04 | HU-043 | S7 | | Propuesto |
| RF-10.1 | Alta/Esencial | EP-05 | HU-044 | S7 | | Propuesto |
| RF-10.2 | Alta/Esencial | EP-05 | HU-045 | S7 | | Propuesto |
| RF-10.3 | Alta/Esencial | EP-05 | HU-046 | S7 | | Propuesto |
| RF-10.4 | Alta/Esencial | EP-05 | HU-047 | S7 | | Propuesto |
| RF-10.5 | Alta/Esencial | EP-05 | HU-048, HU-049 | S7 | | Propuesto (conflicto de prioridad con AF-08, PA-03) |
| RF-11.1 | Baja/Opcional | EP-07 | HU-055 | S8 | | Propuesto |
| RF-11.2 | Baja/Opcional | EP-07 | HU-056 | S8 | | Propuesto |
| RF-11.3 | Baja/Opcional | EP-07 | HU-057 | S8 | | Propuesto |
| RF-11.4 | Baja/Opcional | EP-07 | HU-058 | S8 | | Propuesto |
| RF-11.5 | Baja/Opcional | EP-07 | HU-059 | S8 | | Propuesto |
| RF-11.6 | Baja/Opcional | EP-07 | HU-060 | S8 | | Propuesto |
| RF-11.7 | Baja/Opcional | EP-07 | HU-061 | S8 | | Propuesto |
| RF-12.1 | Alta/Esencial | EP-01 | HU-001, HU-071, HU-072, HU-073 | por asignar | | Propuesto (HU-001 dividida el 2026-10-12 por grupos de roles) |
| RF-12.2 | Alta/Esencial | EP-01 | HU-004 | S1 | | Propuesto |
| RF-12.3 | Alta/Esencial | EP-08 | HU-062, HU-077, HU-078 | por asignar | | Propuesto (HU-062 dividida el 2026-10-12 por contenido) |
| RF-12.4 | Alta/Esencial | EP-08 | HU-063 | por asignar | | Propuesto (depende de HU-062/HU-077/HU-078, divididas el 2026-10-12) |
| RF-13.1 | Media/Deseado | EP-06 | HU-050 | S8 | | Propuesto |
| RF-13.2 | Media/Deseado | EP-06 | HU-051 | S8 | | Propuesto |
| RF-13.3 | Media/Deseado | EP-06 | HU-052 | S8 | | Propuesto |
| RF-14.1 | Baja/Opcional | EP-06 | HU-053 | S8 | | Propuesto |
| RF-14.2 | Baja/Opcional | EP-06 | HU-054 | S8 | | Propuesto |
| RF-14.3 | Baja/Opcional | EP-06 | HU-054 | S8 | | Propuesto |
| RF-15.1 | Media/Deseado | EP-08 | HU-064 | S9 | | Propuesto |
| RF-15.2 | Media/Deseado | EP-08 | HU-065 | S9 | | Propuesto |
| RF-15.3 | Media/Deseado | EP-08 | HU-066 | S9 | | Propuesto |
| RF-15.4 | Media/Deseado | EP-08 | HU-067 | S9 | | Propuesto |
| RF-16.1 | Media/Deseado | EP-08 | HU-068 | S9 | | Propuesto |
| RF-16.2 | Media/Deseado | EP-08 | HU-069 | S9 | | Propuesto |

### Requisitos no funcionales (RNF)

| Requisito ERS | Prioridad ERS | Épica | Historias | Sprint | Clave Jira | Estado |
|---|---|---|---|---|---|---|
| RNF-01 | Alta | EP-04 | HU-041 | S7 | | Propuesto |
| RNF-02 | Alta | EP-02 | HU-019 | S3 | | Propuesto |
| RNF-03 | Alta | — (transversal) | Ver «Cobertura» abajo | — | | Propuesto |
| RNF-04 | Alta | EP-08 | HU-062, HU-077, HU-078 | por asignar | | Propuesto (HU-062 dividida el 2026-10-12) |
| RNF-05 | Media | EP-08 | HU-077 | por asignar | | Propuesto — solo verificable en producción (hospedaje por definir, sección 1.2); antes HU-062, dividida el 2026-10-12 |
| RNF-06 | Alta | EP-04 | HU-036, HU-041 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| RNF-07 | Media | EP-03 | HU-074 | por asignar | | Propuesto (antes HU-032; dividida el 2026-10-12) |
| RNF-08 | Media | EP-03 | HU-074 | por asignar | | Propuesto (antes HU-032; dividida el 2026-10-12) |
| RNF-09 | Media | EP-02, EP-08 | HU-016, HU-062 | por asignar | | Propuesto — corregido el 2026-10-12: aplica a fotos de jugador (HU-016) y al peso de las páginas del portal (HU-062), no a la cédula arbitral |
| RNF-10 | Alta | EP-01, EP-08 | HU-001, HU-062, HU-077, HU-078 | por asignar | | Propuesto (HU-001 y HU-062 divididas el 2026-10-12) |
| RNF-11 | Media | EP-08 | HU-068 | S9 | | Propuesto |
| RNF-12 | Media | EP-08 | HU-064, HU-067 | S9 | | Propuesto (depende de HU-079, SMTP, sin sprint asignado) |
| RNF-13 | Alta | EP-08 | HU-062, HU-077, HU-078 | por asignar | | Propuesto (HU-062 dividida el 2026-10-12) |
| RNF-14 | Alta | EP-04 | HU-075 | por asignar | | Propuesto (antes HU-036; dividida el 2026-10-12, mide el flujo completo HU-036+HU-075) |
| RNF-15 | Alta | EP-04 | HU-036 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| RNF-16 | Alta | EP-04 | HU-075 | por asignar | | Propuesto — corregido el 2026-10-12: antes mal aplicado a HU-025/HU-036 (cancha), en realidad es sobre el cierre de la cédula (HU-075) |
| RNF-17 | Media | EP-03 | HU-035 | S5 | | Propuesto |
| RNF-18 | Alta | — (transversal, DoD) | — | — | | Propuesto |
| RNF-19 | Media | EP-03, EP-05 | HU-033, HU-047 | S5, S7 | | Propuesto |
| RNF-20 | Media | EP-08 | HU-077 | por asignar | | Propuesto — solo verificable en producción; antes HU-062, dividida el 2026-10-12 |
| RNF-21 | Alta | EP-04 | HU-036 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| RNF-22 | Alta | EP-01, EP-04 | HU-004, HU-041 | S1, S7 | | Propuesto |
| RNF-23 | Alta | EP-01 | HU-009 | S9 | | Propuesto |
| RNF-24 | Alta | — (transversal, DoD) | — | — | | Propuesto |
| RNF-25 | Alta | EP-01 | HU-001 | S1 | | Propuesto |
| RNF-26 | Alta | EP-01 | HU-002 | S1 | | Propuesto |
| RNF-27 | Media | EP-01 | HU-002 | S1 | | Propuesto |
| RNF-28 | Alta | EP-01 | HU-002, HU-003 | S1, S2 | | Propuesto |
| RNF-29 | Alta | EP-01 | HU-001 | S1 | | Propuesto |
| RNF-30 | Alta | EP-02 | HU-016, HU-022 | S3 | | Propuesto |
| RNF-31 | Alta | EP-08 | HU-062 | S9 | | Propuesto |
| RNF-32 | Alta | EP-01 | HU-001 | S1 | | Propuesto |
| RNF-33 | Alta | EP-01, EP-04, EP-05 | HU-004, HU-039, HU-047 | S1, S6, S7 | | Propuesto |
| RNF-34 | Alta | — (transversal, DoD) | — | — | | Propuesto |
| RNF-35 | Media | EP-01 | HU-001 | S1 | | Propuesto |
| RNF-36 | Alta | EP-01 | HU-005, HU-006 | S1 | | Propuesto |
| RNF-37 | Media | — (transversal, DoD) | — | — | | Propuesto |
| RNF-38 | Media | — (transversal, DoD) | — | — | | Propuesto |
| RNF-39 | Media | — (transversal, DoD) | — | — | | Propuesto |
| RNF-40 | Media | EP-01 | HU-076 | por asignar | | Propuesto (antes HU-010; dividida el 2026-10-12) |
| RNF-41 | Alta | EP-01 | HU-010 | por asignar | | Propuesto (HU-010 dividida el 2026-10-12) |
| RNF-42 | Media | EP-01 | HU-010 | por asignar | | Propuesto (HU-010 dividida el 2026-10-12) |
| RNF-43 | Alta | EP-02 | HU-019 | S3 | | Propuesto |
| RNF-44 | Alta | EP-05 | HU-046 | S7 | | Propuesto |

### Áreas funcionales (AF) y otros requisitos (OR)

| Requisito ERS | Prioridad ERS | Épica | Historias | Sprint | Clave Jira | Estado |
|---|---|---|---|---|---|---|
| AF-01 | Must | EP-01 | HU-001, HU-004, HU-071, HU-072, HU-073 | por asignar | | Propuesto (HU-001 dividida el 2026-10-12) |
| AF-02 | Must (copa/torneos especiales: Could) | EP-01, EP-07 | HU-005, HU-007, HU-061 | S1, S8 | | Propuesto |
| AF-03 | Must | EP-02 | HU-011 a HU-022, HU-070 | S2, S3 | | Propuesto |
| AF-04 | Must | EP-03 | HU-023, HU-024 | S4 | | Propuesto |
| AF-05 | Must | EP-03 | HU-026 a HU-031 | S4 | | Propuesto |
| AF-06 | Should | EP-03 | HU-032, HU-074, HU-033, HU-034 | por asignar | | Propuesto (HU-032 dividida el 2026-10-12) |
| AF-07 | Must | EP-04 | HU-036 a HU-043, HU-075 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| AF-08 | Must (inconformidades/recursos: Could) | EP-05 | HU-044 a HU-049 | S7 | | Propuesto — conflicto de prioridad, ver PA-03 |
| AF-09 | Could | EP-07 | HU-055 a HU-060 | S8 | | Propuesto |
| AF-10 | Should | EP-06 | HU-050, HU-051, HU-052 | S8 | | Propuesto |
| AF-11 | Must | EP-08 | HU-062, HU-063, HU-077, HU-078 | por asignar | | Propuesto (HU-062 dividida el 2026-10-12) |
| AF-12 | Should | EP-08 | HU-068, HU-069 | S9 | | Propuesto |
| AF-13 | Should | EP-08 | HU-064 a HU-067 | S9 | | Propuesto (depende de HU-079, SMTP, sin sprint asignado) |
| AF-14 | Could | EP-06 | HU-053, HU-054 | S8 | | Propuesto |
| OR-01 | — (obligación legal) | EP-02 | HU-020 | S3 | | Propuesto |

### Interfaces (IU, IH, IC)

| Requisito ERS | Prioridad ERS | Épica | Historias | Sprint | Clave Jira | Estado |
|---|---|---|---|---|---|---|
| IU-01 | — | EP-08 | HU-062, HU-077, HU-078 | por asignar | | Propuesto (HU-062 dividida el 2026-10-12) |
| IU-02 | — | EP-01 | HU-001, HU-071, HU-072, HU-073 | por asignar | | Propuesto (HU-001 dividida el 2026-10-12) |
| IU-03 | — | — (transversal, DoD) | — | — | | Propuesto |
| IU-04 | — | — (transversal, DoD) | — | — | | Propuesto |
| IU-05 | — | EP-05 | HU-047 | S7 | | Propuesto |
| IU-06 | — | EP-04 | HU-036 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| IU-07 | — | sin cubrir | — | — | | Pendiente de validar con la Liga (PA-10) |
| IH-01 | — | EP-04 | HU-036 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| IH-02 | — | EP-02, EP-04 | HU-016, HU-036 | por asignar | | Propuesto (HU-036 dividida el 2026-10-12) |
| IH-03 | — | EP-08 | HU-068, HU-069 | S9 | | Propuesto |
| IH-04 | — | sin cubrir | — | — | | Pendiente de la fase de arquitectura (sección 4.5), no genera historia funcional |
| IC-01 | — | — (transversal, DoD) | — | — | | Propuesto |
| IC-02 | — | restricción de diseño, sin historia propia | — | — | | Propuesto — ver conflicto PA-06 (terminología "API" en la planificación) |
| IC-03 | — | EP-01, EP-08 | HU-079, HU-064, HU-065, HU-066 | por asignar | | Propuesto (HU-079 es historia nueva habilitadora, sin sprint fijo) |
| IC-04 | — | EP-01, EP-08 | HU-079, HU-064 | por asignar | | Propuesto (HU-079 es historia nueva habilitadora, sin sprint fijo) |
| IC-05 | — | — (transversal, DoD) | — | — | | Propuesto |

## Cobertura

Se calcula cuando existan historias. La ERS pide que, al cierre de las 10 semanas (el calendario aprobado por el equipo es de 9 semanas; ver pregunta PA-04), el 100 % de los RF Alta/Esencial y al menos el 80 % de los Media/Deseado tengan prueba de aceptación aprobada. El conteo es por inciso (RF-xx.y), que es la unidad verificable de la matriz.

| Prioridad ERS | Requisitos (incisos RF) | Con historia | Con prueba de aceptación aprobada |
|---|---|---|---|
| Alta/Esencial (Must) | 43 | 43 | 0 |
| Media/Deseado (Should) | 13 | 13 | 0 |
| Baja/Opcional (Could) | 10 | 10 | 0 |
| **Total** | **66** | **66** | **0** |

Notas sobre la cobertura:
- Los 66 incisos de RF-01 a RF-16 tienen al menos una historia en `scrum/product-backlog.md`. Ninguno queda «sin cubrir».
- RF Media/Deseado son solo 4 requisitos (RF-07, RF-13, RF-15, RF-16, con 13 incisos). El umbral «≥ 80 % con prueba aprobada» de RNF-03, aplicado a solo 4 requisitos, matemáticamente exige completar los 4 (3/4 = 75 % no alcanza el 80 %); ver PA-05.
- Las 44 filas de RNF, las 14 de AF, OR-01 y las interfaces IU/IH/IC también tienen cobertura, salvo IU-07 e IH-04, marcadas explícitamente como pendientes (no son requisitos funcionales con prueba de aceptación en el sentido de RNF-03, sino de diseño/arquitectura).
- «Con prueba de aceptación aprobada» está en 0 en todas las filas porque el desarrollo aún no inicia; esta columna se actualizará sprint a sprint conforme el equipo marque historias como Hecho con sus pruebas aprobadas.
