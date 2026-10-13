# Roadmap provisional S1–S9: SGLD

- Mantiene: agente `scrum-master`. Decide: equipo.
- Estado: **provisional**. Solo el Sprint 1 (`scrum/sprints/sprint-01.md`) está planificado en detalle; S2–S9 **no están comprometidos**. Se revisan y ajustan Sprint a Sprint, en cada Planning, con lo que el equipo reporte realmente Hecho.
- Fuentes: `scrum/product-backlog.md` (69 historias, Sprint sugerido inferido de los temas de cada épica y de las notas de riesgo de cada historia), `docs/PLANIFICACIÓN SCRUM.pdf` (objetivo, tareas técnicas y asignación Dev 1–4 por sprint), `docs/decisiones.md` (D-015 calendario, D-017 roles), `scrum/pendientes-validacion.md` (bloqueantes V-01 a V-10, preguntas PA-01 a PA-14).
- Calendario (D-015): S1 12–16 oct, S2 19–23 oct, S3 26–30 oct, S4 2–6 nov, S5 9–13 nov, S6 16–20 nov, S7 23–27 nov, S8 30 nov–4 dic, S9 7–11 dic de 2026. Entrega 11/12/2026.
- Capacidad del equipo: **desconocida** (V-02). Todo lo que sigue es una distribución de historias por tema y por dependencia técnica, no un compromiso de puntos ni de velocidad — no existe velocidad histórica.

## Cómo leer este roadmap

Para cada sprint: objetivo (del PDF del equipo, ajustado donde no cubre un requisito), historias sugeridas con su MoSCoW, carga relativa (número de historias y tamaño aproximado según las notas INVEST/riesgos del backlog, no story points), dependencias (incluidas las invertidas), riesgos técnicos, distribución de esfuerzo por Dev 1–4 (según el patrón que ya usa `PLANIFICACIÓN SCRUM.pdf`: Dev 1 Blade/frontend, Dev 2 migraciones/backend, Dev 3 servicios de lógica/algoritmos, Dev 4 QA/pruebas/despliegue) y una nota de viabilidad.

«Historias oversized» = historias que el propio backlog marca como demasiado grandes para un sprint («No cabe en un sprint tal como está redactada»); se cuentan como 2 unidades de carga aunque sigan siendo 1 ID hasta que el equipo las divida formalmente en Refinamiento.

## Sprint 1 — Cimientos (EP-01 parcial)

- Objetivo PDF: arquitectura base, seguridad y estructura deportiva (RF-01, RF-02, RF-12.1–RF-12.2).
- Historias candidatas: HU-001 a HU-008 (las 8 de EP-01 que no dependen de respaldos/instalación). Detalle completo en `scrum/sprints/sprint-01.md`.
- Carga: 8 historias Must; HU-001 marcada no-Pequeña (cubre 14 roles) — candidata a dividirse.
- Riesgo principal: ninguna decisión técnica base tomada aún (V-08); SMTP sin sprint asignado bloquea HU-002/HU-003 (V-09); solo quedan 4 días hábiles reales (martes–viernes) esta semana.
- Viabilidad: ver recomendación crítica al final de este documento y en `sprint-01.md`.

## Sprint 2 — Afiliación de clubes, equipos y tarjetón (EP-02 parcial, RF-03)

- Objetivo PDF: gestionar el padrón de clubes, asociar equipos a los grupos y generar el documento de afiliación.
- Historias sugeridas: HU-011 (club y directiva), HU-012 (equipos por categoría/grupo), HU-013 (identidad única de personas), HU-014 (alerta de duplicados), HU-015 (historial por temporada), HU-070 (Tarjetón Digital de afiliación del club).
- Carga: 6 historias Must, tamaño pequeño-medio.
- Dependencias: todas cuelgan de HU-005/HU-007 (S1). HU-014 y HU-015 dependen además de HU-012/HU-013 dentro del mismo sprint; HU-070 depende de HU-011 y HU-012 (orden interno a respetar).
- **Gap cubierto**: el Product Owner agregó HU-070 al backlog para el documento de afiliación («Tarjetón Digital») que el PDF del equipo pedía generar en este sprint; campos exactos del tarjetón `[POR DEFINIR]`.
- Dev: Dev 2 migraciones/controladores clubes-equipos-directivos; Dev 1 formularios dinámicos; Dev 3 regla de «reseteo histórico» al vender una franquicia; Dev 4 generación del PDF del Tarjetón Digital (HU-070).
- Riesgo técnico: la regla de reseteo histórico (qué datos se desvinculan al vender un club) no está detallada en un RF-xx.y, se infiere de la sección 2.4 de la ERS (ver backlog, HU-011).

## Sprint 3 — Registro de jugadores, documentos y regla de salto (EP-02 resto, RF-04)

- Objetivo PDF: completar el padrón con la persona «jugador», carga de documentos y validación estricta de elegibilidad deportiva.
- Historias sugeridas: HU-016 (registro con documentos/foto), HU-017 (altas/bajas/renovaciones/transferencias), HU-018 (estados del jugador), HU-019 (elegibilidad — **parcial**, ver abajo), HU-020 (aviso de privacidad y consentimiento del tutor), HU-021 (validación de edad rama infantil contra CURP), HU-022 (protección de datos e historial de cambios).
- Carga: 7 historias Must, con una pieza crítica de seguridad de la información (documentos confidenciales fuera de carpetas públicas, RNF-30).
- **Dependencia invertida (PA-12)**: HU-019 (elegibilidad) necesita «sanción vigente» (HU-047, agendada en S7) y «cuota cubierta» (HU-050, agendada en S8). En S3 solo puede completarse la parte de documentos/registro incompleto; el resto queda marcado `[PENDIENTE]` hasta que existan esas piezas, salvo que el equipo decida reordenar el roadmap. Se recomienda que la demo del Sprint Review de S3 deje explícito que la elegibilidad es parcial.
- Dev: Dev 2 migraciones personas/jugadores/documentos; Dev 1 vista de «expediente del jugador» con insignias de estado; Dev 3 servicio `ReglaSaltoCategoria`; Dev 4 pruebas de la regla de salto y protección de endpoints de imágenes.
- Riesgo técnico: cifrado/almacenamiento de INE y CURP (LFPDPPP); lista exacta de documentos obligatorios por categoría sin confirmar con la Liga (nota de HU-016, Anexo B #12).

## Sprint 4 — Canchas, logística y programación manual (EP-03 parcial, RF-05, RF-06)

- Historias sugeridas: HU-023 (catálogo de canchas), HU-024 (disponibilidad y bloqueos), HU-025 (impedir cancha bloqueada/ocupada), HU-026 (padrón de árbitros), HU-027 (jornadas y partidos manuales), HU-028 (equipos que descansan), HU-029 (estados y reprogramación), HU-030 (publicar programación), HU-031 (conservar historial de partidos).
- Carga: 9 historias Must — el sprint más cargado en número de historias después de S8; la mayoría son pequeñas o medianas.
- **Dependencia invertida**: el criterio 2 de HU-027 («bloquear programación de un equipo con sanción vigente») depende de HU-047 (S7). Se marca pendiente/manual en S4–S6, tal como ya señala el propio backlog.
- **Rol sin confirmar**: HU-030 (publicar programación) asume que el Comité Ejecutivo aprueba, rol que no está en la sección 2.3 de la ERS (PA-01) — remitir al Product Owner antes de construir el flujo de aprobación.
- **Riesgo PA-09**: HU-026 (padrón de árbitros) depende de que la Comisión Arbitral exista formalmente; sin validar con la Liga.
- Dev: Dev 2 migraciones canchas/jornadas/partidos, endpoints y policies; Dev 1 panel tipo calendario/Kanban; Dev 3 validación de estados y prevención de doble programación; Dev 4 subida/validación de PDF de préstamo de cancha.

## Sprint 5 — Motor de fixture automático (EP-03 Should, RF-07)

- Historias sugeridas: HU-032 (generar automáticamente — **oversized**, dividir en «generar enfrentamientos» y «asignar cancha/horario»), HU-033 (detectar y explicar conflictos), HU-034 (ajuste manual), HU-035 (validar usabilidad ≤15 min).
- Carga: 4 historias Should, pero HU-032 cuenta como 2 unidades de carga por ser oversized y de alto riesgo algorítmico (INVEST: ni Estimable ni Pequeña).
- **Dependencia invertida**: el criterio «sin equipos con sanción» de HU-032/RF-07.2 depende de HU-047 (S7); PA-13 ya lo señala. Se recomienda una versión mínima adelantada de ese filtro, o documentar explícitamente la limitación en la demo de S5.
- Riesgo técnico mayor del roadmap completo: algoritmo de asignación con restricciones duras/blandas sobre ~400 partidos/40 canchas en ≤60 s (RNF-07/RNF-08). Es el único sprint dedicado por completo a un solo requisito; si se retrasa, arrastra retraso a S6 en adelante.
- Dev: Dev 3 motor (`FixtureConstraintEngine`); Dev 2 transacciones/endpoints internos (ver PA-06, verificar que sean rutas Blade/Ajax, no una API REST separada); Dev 1 vista de borrador con conflictos resaltados; Dev 4 pruebas de estrés y de «no bucles infinitos».

## Sprint 6 — Cédula arbitral digital y resultados (EP-04 parcial, RF-08)

- Historias sugeridas: HU-036 (captura móvil — **oversized**, dividir en «alineación y eventos» y «firmas y cierre»), HU-037 (captura por capturista), HU-038 (identificación automática), HU-039 (corrección con auditoría — rol `[POR DEFINIR]`, PA-02), HU-040 (validación oficial — rol `[POR DEFINIR]`, PA-02).
- Carga: 5 historias Must, HU-036 cuenta como 2 unidades por ser oversized.
- **Bloqueante de diseño**: HU-039 y HU-040 no tienen rol confirmado en la ERS (quién corrige, quién valida). Remitir a PA-02 antes de construir los formularios/permisos; si no se resuelve a tiempo, se recomienda un supuesto de trabajo documentado (no inventar un rol nuevo sin marcarlo `[POR DEFINIR]`).
- Riesgo técnico: experiencia de uso en campo (sol, conexión inestable, RNF-21 reenvío sin duplicar) — requiere prueba de campo real, no solo de escritorio.
- Dev: Dev 2 migraciones cédulas/eventos y máquina de estados del partido; Dev 1 vista móvil estricta (Blade/Tailwind, firmas con canvas); Dev 3 reglas de bloqueo de cierre (firmas faltantes, jugador inelegible, marcador no cuadra); Dev 4 certificación de la firma táctil en iOS/Android.

## Sprint 7 — Tablas, estadísticas, disciplina y 5 defaults (RF-09, RF-10)

- Historias sugeridas: HU-041 (tabla de posiciones), HU-042 (desempates), HU-043 (estadísticas e histórico por jornada), HU-044 (expediente disciplinario), HU-045 (audiencia/resolución/sanción), HU-046 (vigencia y boletín de castigados), HU-047 (bloqueo por sanción vigente, con efecto retroactivo sobre S3–S6).
- Carga: 7 historias Must, con lógica de negocio densa (motor de recálculo + motor disciplinario + automatismo de 5 defaults).
- **HU-048 y HU-049 (RF-10.5, inconformidades y recurso de segunda instancia) no aparecen en el plan técnico del equipo (`PLANIFICACIÓN SCRUM.pdf`) en ningún sprint.** El backlog las agrupa en EP-05 junto a HU-044–HU-047 por tema, pero no hay un sprint sugerido explícito por parte del equipo. Esto coincide con el conflicto de prioridad ya señalado (PA-03: AF-08 las marca Could, RF-10 completo es Must). **Se remite al equipo/Product Owner**: decidir si HU-048/HU-049 entran a S7 (si cabe), se mueven a un sprint posterior con capacidad libre, o se tratan formalmente como Could y se difieren.
- Dev: Dev 3 `RecalcularTablaService` y `BajaCincoDefaultsService` (Observers); Dev 2 migraciones tablas/expedientes/sanciones/inconformidades; Dev 1 vistas de clasificación y panel del Tribunal de Penas; Dev 4 pruebas exhaustivas del recálculo matemático (PJ/PG/PE/PP/GF/GC/DG).
- Riesgo: la «baja automática por 5 defaults» es una regla de negocio marcada «por confirmar con la Liga» en la sección 2.4 (ver `pendientes-validacion.md`, sección D).

## Sprint 8 — Tesorería, fases finales y asambleas (RF-11, RF-13, RF-14)

- Historias sugeridas: HU-050, HU-051, HU-052 (Tesorería, Should); HU-053, HU-054 (asambleas, Could); HU-055 a HU-061 (fases finales EP-07 completa, Could, 7 historias).
- Carga: **12 historias en una semana — el sprint más sobrecargado de todo el roadmap**, mezclando 3 épicas (EP-06 Should/Could y EP-07 Could completa). El propio backlog ya advierte que EP-07 «comparte sprint con el cierre de EP-06 por restricción de calendario, no por prioridad» y que es la primera candidata a recortar.
- **Dependencia invertida (PA-12)**: HU-050 (cuota cubierta) es insumo de HU-019 (S3); hasta S8 la elegibilidad del jugador por cuota queda parcial, tal como ya se señaló en S3.
- **Rol sin confirmar**: HU-055 a HU-061 asumen un rol «Comité Ejecutivo» que no está en la sección 2.3 (PA-01, mismo problema que HU-030).
- Recomendación de ajuste: si para fines de S7 el equipo ve que no va a alcanzar, recortar primero por aquí — HU-053/HU-054 (asamblea, Could, sin dependientes) y luego HU-058/HU-060/HU-061 (excepciones de ascenso/descenso, consolación, Copa — Could, con menos impacto en el cierre deportivo mínimo: clasificación a liguilla + campeón/subcampeón + informe final).
- Dev: Dev 2 migraciones cargos financieros/asambleas/llaves; Dev 1 estado de cuenta y panel de brackets; Dev 3 algoritmo de corte de fase regular y siembra de liguilla; Dev 4 validación de pagos parciales/totales.

## Sprint 9 — Portal público, reportes PDF, notificaciones y cierre (RF-12.3–RF-12.4, RF-15, RF-16)

- Historias sugeridas: HU-062, HU-063 (portal público); HU-064 a HU-067 (correo: avisos, recordatorios, comunicados, registro de envíos); HU-068, HU-069 (exportables PDF/imagen); HU-009, HU-010 (respaldos e instalación, EP-01 pero agendadas al cierre porque dependen de tener el sistema construido).
- Carga: 10 historias Must/Should, más 2 historias EP-01 de cierre (HU-009, HU-010).
- **Riesgo mayor de todo el roadmap**: en `PLANIFICACIÓN SCRUM.pdf`, la tarea de Dev 4 de este sprint («Configurar servicio SMTP en Laravel, optimizar el VPS, migrar BD MariaDB/PostgreSQL a producción, remover túneles y asociar el dominio web oficial») **está tachada en el documento fuente**. Eso significa que:
  1. El correo SMTP (HU-064 a HU-067, y también HU-002/HU-003 de S1) no tiene ninguna tarea de infraestructura asignada en ningún sprint del plan del equipo (V-09).
  2. El hospedaje de producción (VPS, dominio, migración de base de datos) tampoco tiene tarea asignada — y de él dependen los criterios de RNF-05 y RNF-20 de HU-062 (carga concurrente, disponibilidad), marcados en el backlog como «solo verificables en producción».
  3. Sin ese despliegue, la demo final del 11/12 sería sobre el entorno de desarrollo (túnel), no sobre producción — contradice el objetivo del propio sprint («Sistema desplegado... demostración final lista»).
- **Se remite al equipo**: decidir explícitamente qué pasa con esa tarea tachada — si se reactiva (y en qué sprint, ya que S9 no tiene margen) o si se acepta cerrar el proyecto académico sin hospedaje de producción real, dejando RNF-05/RNF-20/RNF-32 documentados como «no verificado en este ciclo». Ver V-09 y PA-07 en `scrum/pendientes-validacion.md`.
- Dev: Dev 2 vistas de solo lectura para el portal (Eager Loading); Dev 1 landing pública responsiva; Dev 3 filtro de privacidad LFPDPPP en datos de menores (RNF-31); Dev 4 — tarea original tachada, por reasignar.

## Dependencias invertidas (resumen transversal)

| Historia(s) que la necesitan | Sprint en que se usa | Historia que la provee | Sprint en que se construye | Efecto |
|---|---|---|---|---|
| HU-019 (elegibilidad, sanción) | S3 | HU-047 (bloqueo por sanción) | S7 | Elegibilidad parcial en S3–S6 |
| HU-019 (elegibilidad, cuota) | S3 | HU-050 (cuotas y pagos) | S8 | Elegibilidad parcial en S3–S7 |
| HU-027 (bloqueo de programación por sanción) | S4 | HU-047 | S7 | Validación manual en S4–S6 |
| HU-032 (motor automático, sin sanción) | S5 | HU-047 | S7 | Filtro de sanción ausente o manual en S5–S6 |
| HU-002/HU-003 (SMTP: aviso de bloqueo, recuperación de contraseña) | S1 | Configuración SMTP | **sin sprint asignado** (tarea tachada en S9) | Alcance reducido en S1 hasta que el equipo decida |
| HU-064 a HU-067 (correo institucional, RF-15) | S9 | Configuración SMTP | **sin sprint asignado** | Riesgo de no poder completarse en S9 |

## Evaluación crítica de viabilidad del plazo (11/12/2026)

1. **El roadmap depende de un supuesto no verificado: capacidad del equipo.** Sin V-02 resuelto, ningún sprint de S2 en adelante puede compararse contra un compromiso real; solo se puede comparar contra el tamaño relativo de las historias.
2. **Sprints desiguales.** S5 concentra el mayor riesgo algorítmico en una sola historia oversized (HU-032); S8 concentra el mayor volumen (12 historias, 3 épicas); S9 depende de una tarea de infraestructura que el propio equipo tachó de su plan. Estos tres sprints son los más expuestos a no completarse a tiempo.
3. **Funcionalidades en mayor riesgo de quedar pendientes para el 11/12**, de mayor a menor probabilidad de recorte, todas ya Could o con dependencia de infraestructura sin resolver:
   - Despliegue real en producción (VPS, dominio, HTTPS de producción, RNF-05/RNF-20/RNF-32) — tarea tachada, sin sprint.
   - Correo SMTP completo (HU-002, HU-003, HU-064 a HU-067) — sin sprint asignado a la infraestructura.
   - EP-07 completa (fases finales, HU-055 a HU-061) y HU-053/HU-054 (asambleas) — todas Could, concentradas en el sprint más cargado (S8).
   - HU-048/HU-049 (inconformidades y recurso) — sin sprint explícito en el plan del equipo, con conflicto de prioridad sin resolver (PA-03).
   - HU-009/HU-010 (respaldos e instalación) — Must por RNF, pero agendadas al cierre (S9), mismo sprint saturado.
4. **Ajustes propuestos (sin modificar requisitos aprobados, a decidir por el equipo/PO):**
   - Adelantar una decisión mínima sobre SMTP a S1–S2 (al menos para HU-003, que es Must de facto para cualquier usuario que olvide su contraseña), en vez de dejarla sin sprint.
   - Dividir formalmente en Refinamiento las historias oversized antes de que entren a su sprint: HU-001 (S1, por grupos de roles), HU-032 (S5), HU-036 (S6).
   - Mover HU-053/HU-054 (asamblea, Could, sin dependientes) fuera de S8 a un sprint con más holgura, o confirmarlas como primeras candidatas a diferir si S8 no alcanza.
   - Decidir explícitamente sobre PA-04 (9 vs. 10 semanas) y PA-05 (el umbral «≥80% de RF Media con prueba aprobada» de RNF-03 exige de facto completar los 4 RF Media/Should: RF-07, RF-13, RF-15, RF-16) antes de S5, porque ambos ya están comprometidos por el calendario actual.
5. **Conclusión:** el roadmap de 9 sprints es viable para el núcleo Must (EP-01 a EP-05, S1–S7) si la capacidad del equipo resulta suficiente y las decisiones técnicas pendientes (V-08) se toman esta semana. La parte Should/Could (EP-06 a EP-08, S8–S9) y el despliegue de producción son la parte real en riesgo; el equipo debe decidir desde ahora qué de eso se acepta perder antes de llegar a S8, en vez de descubrirlo esa misma semana.

## Historias sin sprint claramente asignado por el equipo

| ID | Motivo | Para quién |
|---|---|---|
| HU-048, HU-049 | RF-10.5 no aparece en ningún sprint de `PLANIFICACIÓN SCRUM.pdf`; conflicto de prioridad sin resolver (PA-03) | Equipo / Product Owner |
| Configuración SMTP (infraestructura, no es una HU) | Tarea tachada del Sprint 9 en el plan del equipo (V-09) | Equipo |
| Despliegue de producción (VPS, dominio, migración de BD) | Misma tarea tachada | Equipo |
