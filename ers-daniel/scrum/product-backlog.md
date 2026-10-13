# Product Backlog: SGLD

- Responsable: Product Owner (humano). Lo mantiene el agente `product-owner`.
- Fuente de requisitos: `docs/ERS.pdf`. Trazabilidad en `docs/trazabilidad.md`.
- Estado: refinado el 2026-10-12 a partir de la ERS completa y de `docs/PLANIFICACIÓN SCRUM.pdf`; el mismo día se dividieron las historias más grandes para ajustarlas a la capacidad real del equipo. Pendiente de aprobación del equipo (orden, alcance y preguntas abiertas).

## Convenciones

- IDs: convención propuesta D-014 (pendiente de aprobación): épicas `EP-NN`, historias `HU-NNN`. Los IDs nuevos se asignan de forma consecutiva al final de la numeración usada (HU-071 en adelante desde el 2026-10-12), sin reutilizar ni renumerar los ya existentes; una historia dividida conserva su ID original con el alcance reducido.
- Tamaño de historia: cada integrante del equipo dispone de 2 a 4 horas diarias (10-20 h por sprint de 1 semana). Ninguna historia debería exigir más de 10-12 horas de trabajo de 1 o 2 personas dentro de un sprint; si una historia refinada no cumple esto (INVEST, criterio "Pequeña"), se divide y cada parte se redacta con el formato completo.
- Prioridad MoSCoW con la equivalencia de la ERS: Alta/Esencial = Must, Media/Deseado = Should, Baja/Opcional = Could.
- Cuando una historia cita varios requisitos con distinta prioridad, se usa la más restrictiva (si alguno es Alta, la historia es Must; si el más alto es Media, es Should; si todos son Baja, es Could). Se señala cuando esto genera un conflicto real entre tablas de la ERS (ver EP-05, HU-048/HU-049).
- Estimación: [POR DEFINIR por el equipo] (escala y técnica).
- Estados: Nuevo → Refinado → Listo (cumple la Definition of Ready) → En sprint → Hecho (cumple la Definition of Done). Todas las historias de este documento están en **Refinado**: tienen criterios de aceptación e INVEST revisado, pero faltan estimación y aprobación del equipo para pasar a Listo.
- Sprint sugerido: roadmap provisional de 9 sprints de 1 semana (S1 12–16 oct a S9 7–11 dic de 2026) basado en `docs/PLANIFICACIÓN SCRUM.pdf`, ajustado donde esa planificación no cubre un requisito o genera una dependencia invertida (se indica en cada historia). Lo confirma el Scrum Master en cada Sprint Planning.
- Requisitos no funcionales transversales: RNF-03 (cobertura global, no una historia; ver tabla de cobertura en `docs/trazabilidad.md`), RNF-18, RNF-24, RNF-34, RNF-37, RNF-38 y RNF-39, además de IU-03, IU-04, IC-01 e IC-05, se verifican en **todas** las historias con interfaz de usuario o con cambios de código, mediante la Definition of Done propuesta abajo, y no generan una historia propia.

### Definition of Ready (propuesta, pendiente de aprobación del equipo)

Una historia está Lista para entrar a un sprint cuando:
1. Está redactada como Como/quiero/para, con un rol que existe en la ERS (sección 2.3) o marcado `[POR DEFINIR]` con la pregunta concreta.
2. Cita al menos un ID de la ERS (RF-xx.y, RNF-xx, AF-xx, OR-01, IU/IH/IC).
3. Tiene entre 3 y 8 criterios de aceptación en formato Dado/Cuando/Entonces, verificables.
4. Se revisó contra INVEST; si no es Pequeña (no cabe en ≤10-12 h de 1-2 personas, con 2-4 h diarias por integrante), está dividida o la división está propuesta.
5. Sus dependencias están identificadas y, si son bloqueantes, la historia de la que depende está Hecha o En sprint.
6. El equipo la estimó con la técnica que acuerde.
7. No tiene preguntas abiertas críticas sin resolver que impidan comenzar (las preguntas menores quedan registradas y se resuelven durante el sprint).

### Definition of Done (propuesta, pendiente de aprobación del equipo; basada en RNF-24, RNF-37, RNF-38 y RNF-39)

Una historia está Hecha cuando:
1. El código está en la rama principal, revisado por otro integrante, sin errores de Laravel Pint (PSR-12) ni hallazgos de Larastan nivel 5 o superior (RNF-38).
2. Existen pruebas automatizadas del módulo de dominio afectado; cobertura ≥ 70 % de líneas en módulos de reglas, tablas, sanciones y programación, al menos una prueba por cada RF Alta/Esencial cubierto, y la suite corre en ≤ 5 minutos (RNF-37).
3. Se verificaron todos los criterios de aceptación de la historia, manualmente o con prueba automatizada.
4. No quedan defectos abiertos de severidad crítica o alta en lo entregado (RNF-24).
5. Las vistas o formularios nuevos están en español, con validación de datos, mensajes de error claros, navegación por teclado y adaptación a móvil/tableta/escritorio (IU-03, IU-04), y funcionan en las dos últimas versiones estables de Chrome, Edge, Firefox y Safari cuando aplique (RNF-10).
6. Las operaciones que crean, cambian o eliminan marcadores, sanciones, pagos o permisos quedan registradas en la bitácora de auditoría inmutable (RNF-33).
7. Los errores se registran con nivel, fecha, hora, usuario e identificador de solicitud, sin mostrar la traza al usuario final (RNF-39).
8. Toda comunicación nueva entre navegador y servidor va sobre HTTPS/TLS, sin exponer datos personales en texto plano (IC-01); las consultas a la base de datos no usan credenciales ni rutas públicas (IC-05).
9. Se actualizó la fila correspondiente en `docs/trazabilidad.md` (sprint, estado).
10. Se demostró en el Sprint Review ante el Product Owner.

## Épicas

| ID | Épica | Requisitos ERS | Prioridad | Estado |
|---|---|---|---|---|
| EP-01 | Core, Seguridad y Gobernanza | AF-01; RF-01, RF-02, RF-12.1–RF-12.2; RNF-23, RNF-25 a RNF-29, RNF-32, RNF-33, RNF-35, RNF-36, RNF-40 a RNF-42; IC-01, IC-04, IC-05 | Must | Refinada |
| EP-02 | Afiliación y Control de Padrón | AF-03; RF-03, RF-04; OR-01; RNF-02, RNF-30, RNF-43; IH-02 | Must | Refinada |
| EP-03 | Logística y Calendarización | AF-04, AF-05, AF-06; RF-05, RF-06, RF-07; RNF-07, RNF-08, RNF-16 (interacción), RNF-17, RNF-19 | Must (RF-05, RF-06) / Should (RF-07, AF-06) | Refinada |
| EP-04 | Operación de Juego y Resultados | AF-07; RF-08, RF-09; RNF-01, RNF-06, RNF-09, RNF-14, RNF-15, RNF-16, RNF-19, RNF-21, RNF-22, RNF-30, RNF-33; IU-06; IH-01, IH-02 | Must | Refinada |
| EP-05 | Régimen Disciplinario | AF-08; RF-10; RNF-33, RNF-44; IU-05 | Must (RF-10 es Alta/Esencial); conflicto con AF-08, que marca "inconformidades y recursos" como Could — ver preguntas abiertas | Refinada |
| EP-06 | Finanzas y Administración | AF-10, AF-14; RF-13, RF-14 | Should (RF-13) / Could (RF-14, AF-14) | Refinada |
| EP-07 | Fases Finales | AF-09; AF-02 (solo la parte de copa/torneos especiales); RF-11 | Could | Refinada |
| EP-08 | Difusión y Portal Público | AF-11, AF-12, AF-13; RF-12.3–RF-12.4, RF-15, RF-16; RNF-04, RNF-05, RNF-09 a RNF-13, RNF-20, RNF-31; IU-01; IC-03, IC-04 | Must (RF-12.3–RF-12.4) / Should (RF-15, RF-16, AF-12, AF-13) | Refinada |

Correcciones al mapeo de `PLANIFICACIÓN SCRUM.pdf` respecto de la ERS:
- RF-12 se divide entre dos épicas: RF-12.1–RF-12.2 (autenticación, RBAC, auditoría) quedan en EP-01; RF-12.3–RF-12.4 (contenido y búsqueda del portal público) se mueven a EP-08, porque coinciden con el alcance de AF-11. La planificación listaba RF-12 completo en ambas épicas sin dividirlo.
- AF-08 (Disciplina) se retira de la épica "Operación de Juego y Resultados" (EP-04 en este backlog); queda solo en EP-05, porque el catálogo de artículos, expedientes y sanciones es RF-10, no RF-08/RF-09.
- AF-02 (Temporadas, torneos y categorías) se reparte entre EP-01 (temporadas y categorías, RF-01/RF-02) y EP-07 (copa y torneos especiales, RF-11.7, Could), en vez de asignarse entera a una sola épica.

## Historias de usuario

| Orden | ID | Historia | Épica | Requisitos ERS | MoSCoW | Estimación | Estado |
|---|---|---|---|---|---|---|---|
| 1 | HU-001 | Iniciar sesión segura y administrar el control de acceso por roles (RBAC) | EP-01 | RF-12.1; RNF-25, RNF-29, RNF-32, RNF-35; IU-02; IC-01 | Must | [POR DEFINIR] | Refinado |
| 2 | HU-071 | Habilitar el acceso de los roles de gobernanza | EP-01 | RF-12.1; IU-02 | Must | [POR DEFINIR] | Refinado |
| 3 | HU-072 | Habilitar el acceso de los roles operativos deportivos | EP-01 | RF-12.1; IU-02 | Must | [POR DEFINIR] | Refinado |
| 4 | HU-073 | Habilitar el acceso de los roles de clubes y jugadores | EP-01 | RF-12.1; IU-02 | Must | [POR DEFINIR] | Refinado |
| 5 | HU-079 | Configurar el envío de correo autenticado (SMTP) | EP-01 | Interfaz de software (servicio de correo); IC-03, IC-04 | Must | [POR DEFINIR] | Refinado |
| 6 | HU-002 | Proteger cuentas con segundo factor y bloqueo por fuerza bruta | EP-01 | RNF-26, RNF-27, RNF-28 | Must | [POR DEFINIR] | Refinado |
| 7 | HU-003 | Recuperar el acceso mediante correo electrónico | EP-01 | Interfaz de software (servicio de correo, recuperación de contraseña); RNF-26, RNF-28 | Must | [POR DEFINIR] | Refinado |
| 8 | HU-004 | Registrar auditoría inmutable de operaciones críticas | EP-01 | RF-12.2; RNF-22, RNF-33 | Must | [POR DEFINIR] | Refinado |
| 9 | HU-005 | Crear y administrar temporadas con convocatoria versionada | EP-01 | RF-01.1, RF-01.3, RF-01.4 | Must | [POR DEFINIR] | Refinado |
| 10 | HU-006 | Configurar las reglas de la temporada (cuotas, fechas, clasificación, desempates, ascenso/descenso) | EP-01 | RF-01.2; RNF-36 | Must | [POR DEFINIR] | Refinado |
| 11 | HU-007 | Crear categorías y grupos por temporada | EP-01 | RF-02.1, RF-02.2, RF-02.4 | Must | [POR DEFINIR] | Refinado |
| 12 | HU-008 | Configurar el formato de competencia y los criterios de clasificación | EP-01 | RF-02.3 | Must | [POR DEFINIR] | Refinado |
| 13 | HU-011 | Registrar un club y su directiva | EP-02 | RF-03.1 (club) | Must | [POR DEFINIR] | Refinado |
| 14 | HU-012 | Dar de alta equipos por categoría, grupo y temporada | EP-02 | RF-03.1 (equipos) | Must | [POR DEFINIR] | Refinado |
| 15 | HU-070 | Registrar el Tarjetón Digital de afiliación del club | EP-02 | RF-03.1 (tarjetón); AF-03 | Must | [POR DEFINIR] | Refinado |
| 16 | HU-013 | Gestionar la identidad única y reutilizable de las personas | EP-02 | RF-03.2 | Must | [POR DEFINIR] | Refinado |
| 17 | HU-014 | Validar y alertar duplicados de personas y equipos | EP-02 | RF-03.3 | Must | [POR DEFINIR] | Refinado |
| 18 | HU-015 | Consultar el historial de equipos y personas por temporada | EP-02 | RF-03.4 | Must | [POR DEFINIR] | Refinado |
| 19 | HU-016 | Registrar un jugador con documentos de identidad y fotografía | EP-02 | RF-04.1; IH-02 | Must | [POR DEFINIR] | Refinado |
| 20 | HU-017 | Gestionar altas, bajas, renovaciones y transferencias de jugadores | EP-02 | RF-04.2 | Must | [POR DEFINIR] | Refinado |
| 21 | HU-018 | Gestionar los estados del jugador | EP-02 | RF-04.3 | Must | [POR DEFINIR] | Refinado |
| 22 | HU-019 | Validar la elegibilidad del jugador antes de participar | EP-02 | RF-04.4; RNF-02, RNF-43 | Must | [POR DEFINIR] | Refinado |
| 23 | HU-020 | Mostrar el aviso de privacidad y recabar el consentimiento del tutor | EP-02 | OR-01 | Must | [POR DEFINIR] | Refinado |
| 24 | HU-021 | Validar la edad de jugadores de la rama infantil contra su CURP | EP-02 | RF-04.1, RF-04.4 (rama infantil) | Must | [POR DEFINIR] | Refinado |
| 25 | HU-022 | Proteger los datos personales y el historial de cambios del jugador | EP-02 | RF-04.5; RNF-30 | Must | [POR DEFINIR] | Refinado |
| 26 | HU-023 | Registrar canchas y sedes con su capacidad | EP-03 | RF-05.1 | Must | [POR DEFINIR] | Refinado |
| 27 | HU-024 | Gestionar la disponibilidad y los bloqueos de canchas | EP-03 | RF-05.2 | Must | [POR DEFINIR] | Refinado |
| 28 | HU-025 | Impedir asignar una cancha bloqueada u ocupada | EP-03 | RF-05.3 | Must | [POR DEFINIR] | Refinado |
| 29 | HU-026 | Administrar el padrón de árbitros y asignarlos a los partidos | EP-03 | RF-03.2 (rol árbitro), RF-06.1 | Must | [POR DEFINIR] | Refinado |
| 30 | HU-027 | Crear jornadas y partidos con asignación manual | EP-03 | RF-06.1 | Must | [POR DEFINIR] | Refinado |
| 31 | HU-028 | Registrar los equipos que descansan en cada jornada | EP-03 | RF-06.2 | Must | [POR DEFINIR] | Refinado |
| 32 | HU-029 | Gestionar los estados y la reprogramación de partidos | EP-03 | RF-06.3, RF-06.4 | Must | [POR DEFINIR] | Refinado |
| 33 | HU-030 | Publicar la programación semanal tras su revisión y aprobación | EP-03 | RF-06.5 | Must | [POR DEFINIR] | Refinado |
| 34 | HU-031 | Conservar el historial de partidos con resultados | EP-03 | RF-06.6 | Must | [POR DEFINIR] | Refinado |
| 35 | HU-036 | Capturar alineación y eventos del partido desde un dispositivo móvil | EP-04 | RF-08.1 (alineación y eventos); RNF-15, RNF-21; IU-06; IH-01 | Must | [POR DEFINIR] | Refinado |
| 36 | HU-075 | Recabar las firmas digitales de los capitanes y cerrar la cédula | EP-04 | RF-08.1 (reporte arbitral y firmas); RNF-14, RNF-16 | Must | [POR DEFINIR] | Refinado |
| 37 | HU-037 | Capturar un resultado desde una cédula física como capturista | EP-04 | RF-08.1 | Must | [POR DEFINIR] | Refinado |
| 38 | HU-038 | Identificar automáticamente el partido, los equipos y las personas al capturar | EP-04 | RF-08.2 | Must | [POR DEFINIR] | Refinado |
| 39 | HU-039 | Corregir un resultado capturado con auditoría y recálculo | EP-04 | RF-08.3; RNF-33 | Must | [POR DEFINIR] | Refinado |
| 40 | HU-040 | Validar oficialmente un resultado antes de publicarlo | EP-04 | RF-08.4 | Must | [POR DEFINIR] | Refinado |
| 41 | HU-041 | Calcular automáticamente la tabla de posiciones | EP-04 | RF-09.1; RNF-01, RNF-06, RNF-22 | Must | [POR DEFINIR] | Refinado |
| 42 | HU-042 | Aplicar criterios de desempate configurables | EP-04 | RF-09.2 | Must | [POR DEFINIR] | Refinado |
| 43 | HU-043 | Generar estadísticas de goleo, tarjetas y sanciones por jornada | EP-04 | RF-09.3, RF-09.4 | Must | [POR DEFINIR] | Refinado |
| 44 | HU-044 | Generar un expediente disciplinario desde las incidencias de la cédula | EP-05 | RF-10.1 | Must | [POR DEFINIR] | Refinado |
| 45 | HU-045 | Registrar audiencia, resolución y sanción | EP-05 | RF-10.2 | Must | [POR DEFINIR] | Refinado |
| 46 | HU-046 | Calcular la vigencia de la sanción y mantener el boletín de castigados | EP-05 | RF-10.3; RNF-44 | Must | [POR DEFINIR] | Refinado |
| 47 | HU-047 | Bloquear la participación o la programación por sanción vigente | EP-05 | RF-10.4; IU-05 | Must | [POR DEFINIR] | Refinado |
| 48 | HU-048 | Registrar una inconformidad ante la Comisión Disciplinaria | EP-05 | RF-10.5 (primera instancia) | Must / Could (conflicto, ver preguntas abiertas) | [POR DEFINIR] | Refinado |
| 49 | HU-049 | Resolver un recurso de segunda instancia | EP-05 | RF-10.5 (segunda instancia) | Must / Could (conflicto, ver preguntas abiertas) | [POR DEFINIR] | Refinado |
| 50 | HU-032 | Generar automáticamente los enfrentamientos de una jornada | EP-03 | RF-07.1 | Should | [POR DEFINIR] | Refinado |
| 51 | HU-074 | Asignar cancha y horario a los enfrentamientos generados respetando restricciones | EP-03 | RF-07.2; RNF-07, RNF-08 | Should | [POR DEFINIR] | Refinado |
| 52 | HU-033 | Detectar y explicar los conflictos de la programación generada | EP-03 | RF-07.3; RNF-19 | Should | [POR DEFINIR] | Refinado |
| 53 | HU-034 | Ajustar manualmente la programación generada automáticamente | EP-03 | RF-07.4 | Should | [POR DEFINIR] | Refinado |
| 54 | HU-035 | Validar la usabilidad de la programación semanal | EP-03 | RNF-17 | Should | [POR DEFINIR] | Refinado |
| 55 | HU-050 | Registrar cuotas y pagos de inscripción, arbitraje y canchas | EP-06 | RF-13.1 | Should | [POR DEFINIR] | Refinado |
| 56 | HU-051 | Controlar adeudos y comprobantes de pago | EP-06 | RF-13.2 | Should | [POR DEFINIR] | Refinado |
| 57 | HU-052 | Generar reportes financieros por temporada, club y concepto | EP-06 | RF-13.3 | Should | [POR DEFINIR] | Refinado |
| 58 | HU-062 | Consultar la programación pública del portal sin necesidad de cuenta | EP-08 | RF-12.3 (programación); IU-01; RNF-04, RNF-10, RNF-13 | Must | [POR DEFINIR] | Refinado |
| 59 | HU-077 | Consultar resultados y tablas de posiciones en el portal público | EP-08 | RF-12.3 (resultados, tablas, estadísticas); IU-01; RNF-04, RNF-10, RNF-13, RNF-31 | Must | [POR DEFINIR] | Refinado |
| 60 | HU-078 | Consultar el boletín de castigados y los comunicados en el portal público | EP-08 | RF-12.3 (sanciones autorizadas, comunicados); IU-01; RNF-04, RNF-10, RNF-13 | Must | [POR DEFINIR] | Refinado |
| 61 | HU-063 | Buscar información del portal por fecha, categoría y equipo | EP-08 | RF-12.4 | Must | [POR DEFINIR] | Refinado |
| 62 | HU-064 | Enviar avisos de programación y cambios de sede u horario por correo | EP-08 | RF-15.1; RNF-12; IC-03, IC-04 | Should | [POR DEFINIR] | Refinado |
| 63 | HU-065 | Enviar recordatorios de pago a clubes con adeudos | EP-08 | RF-15.2 | Should | [POR DEFINIR] | Refinado |
| 64 | HU-066 | Enviar avisos institucionales y comunicados a grupos de usuarios | EP-08 | RF-15.3; IC-03 | Should | [POR DEFINIR] | Refinado |
| 65 | HU-067 | Registrar fecha, destinatarios y estado de cada envío de correo | EP-08 | RF-15.4; RNF-12 | Should | [POR DEFINIR] | Refinado |
| 66 | HU-068 | Exportar programación, tablas y boletín de castigados en PDF e imagen | EP-08 | RF-16.1; RNF-11 | Should | [POR DEFINIR] | Refinado |
| 67 | HU-069 | Generar cédulas oficiales, estados de cuenta y constancias en PDF | EP-08 | RF-16.2; IH-03 | Should | [POR DEFINIR] | Refinado |
| 68 | HU-009 | Respaldar y recuperar la información del sistema | EP-01 | RNF-23 | Must | [POR DEFINIR] | Refinado |
| 69 | HU-010 | Instalar el sistema en un equipo nuevo con manual técnico | EP-01 | RNF-41, RNF-42 | Must | [POR DEFINIR] | Refinado |
| 70 | HU-076 | Redactar y validar los manuales de usuario por rol | EP-01 | RNF-40 | Should | [POR DEFINIR] | Refinado |
| 71 | HU-053 | Registrar convocatoria y asistencia a asamblea de delegados | EP-06 | RF-14.1 | Could | [POR DEFINIR] | Refinado |
| 72 | HU-054 | Registrar acuerdos y comunicados de asamblea | EP-06 | RF-14.2, RF-14.3 | Could | [POR DEFINIR] | Refinado |
| 73 | HU-055 | Clasificar equipos a la liguilla y generar las llaves | EP-07 | RF-11.1 | Could | [POR DEFINIR] | Refinado |
| 74 | HU-056 | Registrar avances, eliminaciones y determinar campeón y subcampeón | EP-07 | RF-11.2 | Could | [POR DEFINIR] | Refinado |
| 75 | HU-057 | Generar propuestas de ascenso y descenso versionadas | EP-07 | RF-11.3 | Could | [POR DEFINIR] | Refinado |
| 76 | HU-058 | Validar manualmente reglas de ascenso/descenso no automáticas | EP-07 | RF-11.4 | Could | [POR DEFINIR] | Refinado |
| 77 | HU-059 | Emitir el informe final y cerrar la temporada | EP-07 | RF-11.5 | Could | [POR DEFINIR] | Refinado |
| 78 | HU-060 | Gestionar liguilla de consolación, Campeón de Campeones y campeón de goleo | EP-07 | RF-11.6 | Could | [POR DEFINIR] | Refinado |
| 79 | HU-061 | Registrar el Torneo de Copa y torneos especiales | EP-07 | RF-11.7 | Could | [POR DEFINIR] | Refinado |

Nota sobre el orden: sigue la secuencia de épicas de `PLANIFICACIÓN SCRUM.pdf` (EP-01→EP-02→EP-03→EP-04→EP-05→EP-06→EP-08), que ya respeta "Must primero" salvo dependencias técnicas (por ejemplo, RF-07 "Should" se intercala en EP-03 porque depende de RF-05/RF-06; HU-009/HU-010 de EP-01, aunque Must, se dejan al final porque dependen de tener el sistema construido para poder respaldarlo e instalarlo). EP-07 (Could) comparte sprint con el cierre de EP-06 por restricción de calendario, no por prioridad; es la primera candidata a recortar si el tiempo no alcanza.

**Divisiones del 2026-10-12** (equipo con 2-4 h diarias por integrante; ninguna historia debería exigir más de 10-12 h de 1-2 personas en un sprint): HU-001 se dividió en HU-001 (infraestructura de RBAC) + HU-071, HU-072 y HU-073 (acceso por grupos de roles); HU-032 en HU-032 (enfrentamientos) + HU-074 (cancha/horario con restricciones); HU-036 en HU-036 (alineación y eventos) + HU-075 (firmas y cierre); HU-010 en HU-010 (instalación con manual técnico) + HU-076 (manuales de usuario por rol); HU-062 en HU-062 (programación pública) + HU-077 (resultados y tablas) + HU-078 (castigados y comunicados). Se agregó HU-079 (configurar el envío de correo autenticado, SMTP), historia habilitadora que hoy no tiene sprint asignado porque la planificación la dejaba hasta S9 con el texto tachado en el PDF fuente, y de la que dependen HU-002, HU-003 y todo RF-15 (HU-064 a HU-067). Las historias nuevas o con alcance modificado quedan con sprint "por asignar" en `docs/trazabilidad.md`; el Scrum Master redistribuye los sprints en el próximo Sprint Planning. Total del backlog: 79 historias.

## Detalle de historias

### HU-001 · Iniciar sesión segura y administrar el control de acceso por roles (RBAC)
- Épica: EP-01
- Requisitos ERS: RF-12.1, RNF-25, RNF-29, RNF-32, RNF-35, IU-02, IC-01
- Historia: Como Administrador General, quiero iniciar sesión de forma segura y administrar la infraestructura de control de acceso por roles (RBAC), para que cada usuario vea solo los módulos y acciones permitidos por su rol.
- Criterios de aceptación:
  1. Dado el Administrador General, cuando inicia sesión con credenciales correctas, entonces accede a todos los módulos y a la administración de roles y permisos.
  2. Dado un usuario autenticado de cualquier rol, cuando intenta acceder a una ruta de un módulo que no le corresponde, entonces el motor de permisos responde 403 y no expone la pantalla (válido para todos los roles, incluidos los que prueban HU-071 a HU-073).
  3. Dado el Administrador General u otro rol con privilegios, cuando pasan 30 minutos de inactividad, entonces su sesión expira; los demás roles expiran a las 8 horas (RNF-35).
  4. Dado cualquier envío de credenciales, cuando viaja entre navegador y servidor, entonces se transmite por HTTPS/TLS sin exponer datos en texto plano (IC-01).
  5. Dado un intento de acceso cruzado (cambiar un identificador en la URL para ver datos de otro usuario), cuando el usuario no tiene permiso, entonces el sistema responde 403 en el 100 % de los casos (RNF-29).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna (historia fundacional). La usan HU-071, HU-072 y HU-073, que habilitan y prueban el acceso de los demás grupos de roles sobre esta misma infraestructura.
- Riesgos y supuestos: historia dividida el 2026-10-12 por tamaño (cubría 14 roles); ahora construye el motor de permisos una sola vez para el Administrador General y lo reutilizan HU-071 a HU-073. RNF-32 (TLS/HSTS, SSL Labs A) depende del hospedaje, hoy en equipos propios (sección 1.2); solo se verifica totalmente en producción.
- Notas / [POR DEFINIR]: ninguna.

### HU-071 · Habilitar el acceso de los roles de gobernanza
- Épica: EP-01
- Requisitos ERS: RF-12.1, IU-02
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema reconozca mi rol y los de Presidente, Tesorero y Comisión de la Rama Infantil al iniciar sesión, para que cada quien acceda solo a los módulos que le corresponden.
- Criterios de aceptación:
  1. Dado un usuario con rol Presidente (Consejo Directivo), Secretario (Consejo Directivo), Tesorero (Consejo Directivo) o Comisión de la Rama Infantil, cuando inicia sesión, entonces ve solo los módulos y acciones de su rol, conforme a sus responsabilidades de la sección 2.3.
  2. Dado uno de estos roles, cuando intenta una acción fuera de su ámbito (por ejemplo, el Tesorero editando un expediente disciplinario), entonces el sistema la rechaza con 403.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-001 (infraestructura RBAC).
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-072 · Habilitar el acceso de los roles operativos deportivos
- Épica: EP-01
- Requisitos ERS: RF-12.1, IU-02
- Historia: Como Programador de Partidos, quiero que el sistema reconozca mi rol y los de Capturista, Árbitro en Campo, Comisión Arbitral y Tribunal de Penas al iniciar sesión, para que cada quien acceda solo a sus módulos operativos.
- Criterios de aceptación:
  1. Dado un usuario con rol Programador de Partidos, Capturista, Árbitro en Campo, Comisión Arbitral o Tribunal de Penas (Comisión Disciplinaria), cuando inicia sesión, entonces ve solo los módulos y acciones de su rol.
  2. Dado uno de estos roles, cuando intenta una acción fuera de su ámbito (por ejemplo, un capturista aprobando una sanción), entonces el sistema la rechaza con 403.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-001.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-073 · Habilitar el acceso de los roles de clubes y jugadores
- Épica: EP-01
- Requisitos ERS: RF-12.1, IU-02
- Historia: Como Delegado del Club, quiero que el sistema reconozca mi rol y los de Presidente del Club y Jugador al iniciar sesión, para acceder solo a la información de mi club o a mi propio historial.
- Criterios de aceptación:
  1. Dado un usuario con rol Presidente del Club, Delegado del Club o Jugador, cuando inicia sesión, entonces ve solo los módulos y acciones de su rol.
  2. Dado un Delegado o Jugador, cuando intenta consultar información de otro club, entonces el sistema la rechaza con 403, consistente con RNF-29.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-001.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-079 · Configurar el envío de correo autenticado (SMTP)
- Épica: EP-01
- Requisitos ERS: interfaz de software (servicio de correo electrónico, Tabla 7: notificaciones y recuperación de contraseña); IC-03, IC-04
- Historia: Como Administrador General, quiero configurar el servicio de correo electrónico saliente con autenticación, para que el sistema pueda enviar notificaciones, comunicados y la recuperación de contraseña.
- Criterios de aceptación:
  1. Dado el servicio de correo electrónico, cuando el sistema envía un mensaje, entonces lo hace por SMTP con autenticación hacia el servicio configurado (IC-03).
  2. Dado un correo enviado por el sistema, cuando se genera, entonces se produce en versión de texto plano y en HTML (Tabla 7, interfaces de software).
  3. Dado las credenciales del servicio de correo, cuando se configuran, entonces se almacenan fuera del código fuente, por variables de entorno (IC-04, consistente con RNF-42).
  4. Dado un cambio de entorno (desarrollo/producción), cuando se despliega, entonces el servicio de correo se reconfigura sin tocar código (RNF-42).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna técnica previa; la necesitan HU-002, HU-003, HU-064, HU-065, HU-066 y HU-067.
- Riesgos y supuestos: historia agregada el 2026-10-12 porque la planificación dejaba esta configuración hasta S9, con el texto tachado en el PDF fuente; al ser prerequisito de historias agendadas antes (HU-002, HU-003), queda sin sprint asignado ("por asignar" en `docs/trazabilidad.md`) para que el Scrum Master la adelante.
- Notas / [POR DEFINIR]: confirmar qué proveedor SMTP usará el equipo en desarrollo (por ejemplo, un servicio de pruebas) antes de tener hospedaje de producción definido (sección 1.2).

### HU-002 · Proteger cuentas con segundo factor y bloqueo por fuerza bruta
- Épica: EP-01
- Requisitos ERS: RNF-26, RNF-27, RNF-28
- Historia: Como Administrador General, quiero que las cuentas con privilegios usen un segundo factor y que el sistema bloquee tras varios intentos fallidos, para proteger las cuentas críticas de ataques de fuerza bruta.
- Criterios de aceptación:
  1. Dado un administrador, un integrante de la Comisión Disciplinaria, de Tesorería, de Secretaría o un programador de partidos, cuando intenta operar sin un segundo factor (TOTP o código por correo), entonces el sistema lo impide.
  2. Dado cualquier usuario, cuando falla 5 veces consecutivas al iniciar sesión, entonces la cuenta se bloquea 15 minutos y se avisa al titular por correo.
  3. Dado un intento de acceso, cuando viene repetidamente desde una misma dirección IP, entonces el sistema también limita esos intentos.
  4. Dado una contraseña nueva, cuando se guarda, entonces se almacena con Argon2id (o bcrypt costo ≥ 12), mínimo 15 caracteres si solo usa contraseña y 8 con doble factor, máximo 64, sin reglas de composición ni caducidad periódica, y se rechaza si está en una lista de contraseñas filtradas conocidas (RNF-26).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-001 (login/RBAC) y de HU-079 (SMTP) para el aviso de bloqueo por correo (RNF-28).
- Riesgos y supuestos: la planificación agendaba la configuración de correo hasta S9; ahora que existe HU-079 como historia habilitadora sin sprint fijo, se recomienda adelantarla junto con esta.
- Notas / [POR DEFINIR]: ninguna.

### HU-003 · Recuperar el acceso mediante correo electrónico
- Épica: EP-01
- Requisitos ERS: Tabla de interfaces de software (servicio de correo — "recuperación de contraseña"); RNF-26, RNF-28
- Historia: Como usuario con cuenta (por ejemplo, Delegado del Club), quiero recuperar mi acceso por correo electrónico si olvido mi contraseña, para seguir usando el sistema sin depender del Administrador General.
- Criterios de aceptación:
  1. Dado que olvidé mi contraseña, cuando solicito recuperarla con mi correo registrado, entonces recibo un enlace de un solo uso con vigencia limitada.
  2. Dado un enlace de recuperación vencido, cuando intento usarlo, entonces el sistema lo rechaza y ofrece solicitar uno nuevo.
  3. Dado un enlace vigente, cuando defino una nueva contraseña, entonces se aplican las mismas reglas de RNF-26 y la sesión anterior queda invalidada.
  4. Dado un correo remitente del dominio del Sistema, cuando llega al usuario, entonces llega a bandeja de entrada (no a spam) por tener SPF, DKIM y DMARC configurados (RNF-12).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-079 (SMTP) y de HU-001; la ERS no tiene un inciso RF explícito para "recuperar contraseña", solo lo menciona la tabla de interfaces de software — ver pregunta abierta.
- Riesgos y supuestos: mismo riesgo de calendario que HU-002; al quedar HU-079 sin sprint fijo, esta historia tampoco puede completarse antes de que el Scrum Master la ubique.
- Notas / [POR DEFINIR]: no hay un RF-xx.y explícito para este flujo; se infiere de la tabla de interfaces de software y de RNF-26/RNF-28. Confirmar con el equipo si se agrega un inciso a RF-12 o RF-15 en una próxima revisión de la ERS.

### HU-004 · Registrar auditoría inmutable de operaciones críticas
- Épica: EP-01
- Requisitos ERS: RF-12.2, RNF-22, RNF-33
- Historia: Como Administrador General, quiero que el sistema registre de forma inmutable quién cambió qué, cuándo, por qué y con qué valores en las operaciones críticas, para poder auditar el uso del sistema.
- Criterios de aceptación:
  1. Dado un cambio a un marcador, una sanción, un pago o un permiso, cuando se guarda, entonces se registra usuario, fecha, hora, IP, valor anterior, valor nuevo y motivo.
  2. Dado un registro de auditoría ya creado, cuando cualquier usuario intenta modificarlo o borrarlo, entonces la aplicación no lo permite.
  3. Dado el cierre de una cédula o la actualización de una tabla, cuando ocurre, entonces se guarda en una sola transacción; si se simula una falla a la mitad, no quedan datos parciales (RNF-22).
  4. Dado el histórico de auditoría, cuando se consulta, entonces conserva la temporada actual y las 3 anteriores.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna; la usan como base HU-039, HU-045, HU-047.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-005 · Crear y administrar temporadas con convocatoria versionada
- Épica: EP-01
- Requisitos ERS: RF-01.1, RF-01.3, RF-01.4
- Historia: Como Administrador General, quiero crear y administrar temporadas con su convocatoria asociada, para conservar el histórico de reglas de cada ciclo anual.
- Criterios de aceptación:
  1. Dado un nombre, fecha de inicio, fecha de fin y un documento de convocatoria, cuando registro una temporada, entonces queda creada con el documento adjunto.
  2. Dado un cambio a las reglas de una temporada, cuando se guarda, entonces se versiona y se registra usuario, fecha y motivo del cambio (RF-01.3).
  3. Dado una temporada cerrada, cuando alguien intenta modificarla o eliminarla, entonces el sistema lo impide y solo permite consultarla (RF-01.4).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna; de ella dependen HU-007, HU-011 y, en general, toda la estructura deportiva.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-006 · Configurar las reglas de la temporada (cuotas, fechas, clasificación, desempates, ascenso/descenso)
- Épica: EP-01
- Requisitos ERS: RF-01.2, RNF-36
- Historia: Como Administrador General, quiero configurar por temporada las cuotas, fechas límite, reglas de clasificación, desempates y ascenso/descenso, para que cada ciclo pueda tener sus propias reglas sin tocar código.
- Criterios de aceptación:
  1. Dado que estoy configurando una temporada, cuando defino cuotas, fechas límite, reglas de clasificación, desempates y reglas de ascenso/descenso, entonces quedan guardadas como datos de esa temporada.
  2. Dado un cambio a una de esas reglas, cuando se guarda, entonces no requiere modificar el código del sistema (RNF-36).
  3. Dado que una temporada ya cerró, cuando se consulta qué reglas aplicaron, entonces el sistema muestra exactamente las vigentes en ese momento.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-005.
- Riesgos y supuestos: las reglas de desempate detalladas (HU-042) y de ascenso/descenso (HU-057) se registran aquí como parámetros, pero su ejecución ocurre en EP-04 y EP-07, en sprints posteriores.
- Notas / [POR DEFINIR]: ninguna.

### HU-007 · Crear categorías y grupos por temporada
- Épica: EP-01
- Requisitos ERS: RF-02.1, RF-02.2, RF-02.4
- Historia: Como Administrador General, quiero crear categorías (por ejemplo, Primera Fuerza, Reserva, Juvenil) y dividirlas en grupos, para reflejar la estructura deportiva de cada temporada.
- Criterios de aceptación:
  1. Dado el catálogo de categorías, cuando creo una nueva, entonces no requiere modificar el código del sistema (RF-02.1).
  2. Dado una categoría de una temporada, cuando le agrego uno o varios grupos, entonces queda registrada esa relación (RF-02.2).
  3. Dado un equipo de una temporada, cuando lo doy de alta, entonces queda asignado a una sola categoría/grupo (RF-02.4).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-005.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-008 · Configurar el formato de competencia y los criterios de clasificación
- Épica: EP-01
- Requisitos ERS: RF-02.3
- Historia: Como Administrador General, quiero configurar el formato de competencia y los criterios de clasificación de cada categoría/grupo, para que el sistema calcule la tabla según las reglas vigentes.
- Criterios de aceptación:
  1. Dado un grupo de una categoría, cuando configuro su formato de competencia (por ejemplo, todos contra todos), entonces queda guardado.
  2. Dado ese mismo grupo, cuando configuro sus criterios de clasificación, entonces quedan disponibles para HU-041 y HU-042.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-007.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-009 · Respaldar y recuperar la información del sistema
- Épica: EP-01
- Requisitos ERS: RNF-23
- Historia: Como Administrador General, quiero que el sistema respalde automáticamente la base de datos y los documentos, para recuperar la información ante una pérdida del servidor.
- Criterios de aceptación:
  1. Dado el entorno en operación, cuando pasa un día, entonces existe un respaldo incremental de la base de datos y los documentos.
  2. Dado el entorno en operación, cuando pasa una semana, entonces existe un respaldo completo.
  3. Dado cualquier respaldo, cuando se genera, entonces se guarda fuera del equipo servidor y se conserva 30 días.
  4. Dado un respaldo, cuando se restaura en una prueba mensual, entonces se cumple RPO ≤ 24 horas y RTO ≤ 4 horas.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de tener el esquema de base de datos y el almacenamiento documental en operación (prácticamente, de todo lo anterior).
- Riesgos y supuestos: el lugar de respaldo "fuera del equipo servidor" depende de dónde se aloje el sistema (hospedaje de producción, sección 1.2, aún por definir).
- Notas / [POR DEFINIR]: ninguna.

### HU-010 · Instalar el sistema en un equipo nuevo con manual técnico
- Épica: EP-01
- Requisitos ERS: RNF-41, RNF-42
- Historia: Como Administrador General, quiero contar con un manual técnico de instalación y poder instalar el sistema en un equipo nuevo en poco tiempo, para operarlo sin depender todo el tiempo del equipo de desarrollo.
- Criterios de aceptación:
  1. Dado el manual técnico de instalación, cuando una persona del equipo lo sigue en un equipo con PHP y MariaDB ya definidos, entonces instala el sistema con migraciones y datos de ejemplo en ≤ 1 hora (RNF-41).
  2. Dado un cambio de entorno (base de datos, correo, dominio, túnel), cuando se despliega, entonces se define por variables de entorno, sin tocar el código (RNF-42).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de tener el esquema de base de datos y el sistema prácticamente completo.
- Riesgos y supuestos: historia dividida el 2026-10-12 por tamaño; antes incluía también los manuales de usuario por rol (ahora en HU-076), que pueden avanzarse de forma incremental desde que cada módulo esté Hecho, sin esperar al cierre.
- Notas / [POR DEFINIR]: ninguna.

### HU-076 · Redactar y validar los manuales de usuario por rol
- Épica: EP-01
- Requisitos ERS: RNF-40
- Historia: Como Administrador General, quiero contar con manuales de usuario por rol, revisados por alguien que no participó en construir ese módulo, para que cada integrante de la Liga pueda usar el sistema sin depender del equipo de desarrollo.
- Criterios de aceptación:
  1. Dado un rol del sistema, cuando se redacta su manual de usuario, entonces lo hace alguien distinto de quien usa ese módulo a diario.
  2. Dado un manual ya redactado, cuando lo revisa un integrante que no participó en su redacción, entonces confirma que se entiende sin ayuda adicional (RNF-40).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de que cada módulo correspondiente esté Hecho; se puede avanzar de forma incremental conforme cada épica se completa, en vez de esperar al cierre en S9.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-011 · Registrar un club y su directiva
- Épica: EP-02
- Requisitos ERS: RF-03.1 (club)
- Historia: Como Presidente del Club, quiero registrar mi club y su directiva, para afiliarme formalmente a la temporada.
- Criterios de aceptación:
  1. Dado los datos del club (nombre, colores) y de su directiva, cuando los registro, entonces el club queda dado de alta para la temporada.
  2. Dado un club ya registrado, cuando se vende o cambia de nombre, entonces el sistema aplica el reseteo histórico (desvincula y reinicia el historial deportivo, conservando el histórico anterior) según la restricción de la sección 2.4.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-005 (temporada) y HU-007 (categorías). La afiliación formal del club se completa con HU-070 (Tarjetón Digital, sección 2.3), que depende de esta historia; HU-011 no depende de HU-070.
- Riesgos y supuestos: la regla exacta del reseteo histórico (qué datos se desvinculan) no está detallada en un RF-xx.y; se infiere de la restricción de negocio de la sección 2.4.
- Notas / [POR DEFINIR]: ninguna.

### HU-012 · Dar de alta equipos por categoría, grupo y temporada
- Épica: EP-02
- Requisitos ERS: RF-03.1 (equipos)
- Historia: Como Delegado del Club, quiero dar de alta los equipos de mi club en las categorías habilitadas, para inscribirlos a la temporada.
- Criterios de aceptación:
  1. Dado un club afiliado, cuando doy de alta un equipo en una categoría y grupo, entonces queda registrado para esa temporada.
  2. Dado un jugador, cuando intenta inscribirse en un equipo de una categoría más de 1 nivel superior a la de su club de origen, entonces el sistema bloquea el alta (restricción de salto de 1 categoría, sección 2.4; ver HU-019).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-011. La usa HU-070 (Tarjetón Digital), que la cita como insumo; HU-012 no depende de HU-070.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-070 · Registrar el Tarjetón Digital de afiliación del club
- Épica: EP-02
- Requisitos ERS: RF-03.1 (tarjetón); AF-03; sección 2.2; sección 2.3 (Presidente del Club)
- Historia: Como Presidente del Club, quiero generar el Tarjetón Digital de afiliación de mi club, con los datos de la directiva y sus firmas, para formalizar mi afiliación a la temporada.
- Criterios de aceptación:
  1. Dado un club con su directiva ya registrada (HU-011) y sus equipos dados de alta (HU-012), cuando genero el Tarjetón Digital, entonces el documento incluye los datos del club, de la directiva y las firmas correspondientes (RF-03.1; sección 2.2: "emisión del Tarjetón Digital de afiliación con firmas de la directiva").
  2. Dado un Tarjetón Digital generado, cuando se consulta, entonces queda vinculado a esa temporada y disponible para el club (sección 2.3: "Afiliación formal del club mediante el Tarjetón Digital").
  3. Dado un cambio posterior en los datos de la directiva o del club, cuando ocurre, entonces [POR DEFINIR] si el sistema genera una nueva versión del Tarjetón o lo deja sin efecto hasta reemitirlo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-011 (club y directiva) y HU-012 (equipos).
- Riesgos y supuestos: la ERS no detalla el formato exacto del Tarjetón Digital (campos, diseño, tipo de firma); se infiere únicamente lo que dicen AF-03, la sección 2.2 y RF-03.1.
- Notas / [POR DEFINIR]: confirmar con el equipo o la Liga el formato y los campos exactos del Tarjetón Digital y el tipo de firma de la directiva (relacionado con PA-11, credencial de jugador).

### HU-013 · Gestionar la identidad única y reutilizable de las personas
- Épica: EP-02
- Requisitos ERS: RF-03.2
- Historia: Como Secretario (Consejo Directivo), quiero manejar a cada persona como una identidad única y reutilizable, con los roles que le correspondan (jugador, delegado, árbitro, directivo), para no duplicar datos entre temporadas.
- Criterios de aceptación:
  1. Dado que una persona ya existe en el sistema, cuando se le asigna un nuevo rol (por ejemplo, de jugador a delegado), entonces se reutiliza su identidad en vez de crear un registro nuevo.
  2. Dado una persona con varios roles, cuando se consulta, entonces el sistema distingue claramente cada rol que tiene y en qué temporada.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna; la usan HU-016, HU-022 y HU-026.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-014 · Validar y alertar duplicados de personas y equipos
- Épica: EP-02
- Requisitos ERS: RF-03.3
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema valide y alerte sobre posibles duplicados de personas y de nombres de equipos, para mantener limpio el padrón.
- Criterios de aceptación:
  1. Dado el registro de una persona con datos similares a otra ya existente (nombre, CURP), cuando se guarda, entonces el sistema muestra una alerta antes de confirmar.
  2. Dado el registro de un equipo con un nombre muy similar a otro de la misma categoría, cuando se guarda, entonces el sistema alerta del posible duplicado.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-012 y HU-013.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-015 · Consultar el historial de equipos y personas por temporada
- Épica: EP-02
- Requisitos ERS: RF-03.4
- Historia: Como Secretario (Consejo Directivo), quiero consultar el historial de equipos y personas a través de las temporadas, para dar seguimiento a cada club y jugador en el tiempo.
- Criterios de aceptación:
  1. Dado un equipo, cuando consulto su historial, entonces veo en qué temporadas, categorías y grupos participó.
  2. Dado una persona, cuando consulto su historial, entonces veo los roles y equipos que tuvo en cada temporada.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-012 y HU-013.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-016 · Registrar un jugador con documentos de identidad y fotografía
- Épica: EP-02
- Requisitos ERS: RF-04.1, IH-02, RNF-30
- Historia: Como Delegado del Club, quiero registrar a un jugador con su identificación, documentos, fotografía, categoría y equipo, para inscribirlo a la temporada.
- Criterios de aceptación:
  1. Dado un jugador nuevo, cuando lo registro con INE o CURP (según categoría), constancia de estudios cuando aplique, fotografía y comprobante de domicilio, entonces queda vinculado a su equipo, categoría y temporada.
  2. Dado un documento confidencial (INE, CURP), cuando se sube, entonces se guarda fuera de carpetas públicas y no es indexable (RNF-30).
  3. Dado una fotografía de jugador, cuando se sube desde la cámara del dispositivo o se carga un archivo, entonces el sistema la acepta (IH-02).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-012 y HU-013.
- Riesgos y supuestos: la lista exacta de documentos obligatorios no está cerrada (Anexo B, pregunta 12).
- Notas / [POR DEFINIR]: confirmar con la Liga la lista final de documentos obligatorios por categoría (Anexo B #12).

### HU-017 · Gestionar altas, bajas, renovaciones y transferencias de jugadores
- Épica: EP-02
- Requisitos ERS: RF-04.2
- Historia: Como Delegado del Club, quiero dar de alta, baja, renovar o transferir jugadores conforme a las reglas de la temporada, para mantener actualizada mi plantilla.
- Criterios de aceptación:
  1. Dado un jugador activo, cuando lo doy de baja, entonces queda registrado el motivo y la fecha.
  2. Dado un jugador de una temporada anterior, cuando lo renuevo, entonces conserva su identidad única y se actualiza su vínculo a la nueva temporada.
  3. Dado un jugador de otro club, cuando se transfiere, entonces se aplican las reglas de la temporada vigente (incluida la restricción de salto de 1 categoría).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-018 · Gestionar los estados del jugador
- Épica: EP-02
- Requisitos ERS: RF-04.3
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema maneje los estados del jugador (activo, suspendido, baja, inelegible), para reflejar su situación real en cada momento.
- Criterios de aceptación:
  1. Dado un jugador registrado, cuando se consulta, entonces el sistema muestra uno de los 4 estados definidos.
  2. Dado un cambio de estado (por ejemplo, de activo a suspendido), cuando ocurre, entonces queda registrado quién lo generó y por qué (sanción, baja voluntaria, etc.).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016; lo usan HU-019 y HU-047.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-019 · Validar la elegibilidad del jugador antes de participar
- Épica: EP-02
- Requisitos ERS: RF-04.4, RNF-02, RNF-43
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema valide la elegibilidad del jugador (documentos completos, cuota cubierta, sin sanción vigente) antes de que participe, para evitar alineaciones indebidas.
- Criterios de aceptación:
  1. Dado un jugador suspendido, con cuota pendiente, con registro incompleto o en categoría superior a la permitida, cuando se intenta alinear, entonces el sistema lo bloquea y muestra el motivo y la regla que lo origina.
  2. Dado un conjunto de casos de prueba (suspendido, cuota cubierta, registro incompleto, alineación en categoría superior), cuando se evalúan, entonces el resultado es correcto en el 100 % de los casos (RNF-02).
  3. Dado un caso donde no se puede determinar la elegibilidad por datos incompletos o error interno, cuando ocurre, entonces el sistema lo marca "no verificado", alerta al árbitro y al delegado, y nunca lo marca como habilitado (RNF-43).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016, HU-018, HU-047 (sanción vigente) y HU-050 (cuota cubierta, RF-13.1). La validación completa de "cuota cubierta" no puede terminarse hasta que exista Tesorería (HU-050, agendada en S8), aunque RF-04 completo está agendado en S3: conflicto de calendario señalado en «Preguntas abiertas».
- Riesgos y supuestos: hasta que exista HU-050, se recomienda una versión parcial que valide documentos y sanciones, y deje la cuota como pendiente de validación.
- Notas / [POR DEFINIR]: ninguna.

### HU-020 · Mostrar el aviso de privacidad y recabar el consentimiento del tutor
- Épica: EP-02
- Requisitos ERS: OR-01
- Historia: Como Delegado del Club, quiero mostrar el aviso de privacidad y recabar el consentimiento digital del tutor al registrar a un jugador menor de edad (rama infantil y categoría Juvenil), para cumplir con la protección de datos personales.
- Criterios de aceptación:
  1. Dado el registro de cualquier jugador, cuando se completa, entonces se muestra el aviso de privacidad antes de guardar.
  2. Dado un jugador de la rama infantil o de categoría Juvenil, cuando se registra, entonces el sistema exige y registra el consentimiento digital del tutor antes de habilitarlo.
  3. Dado un registro sin consentimiento del tutor cuando corresponde, cuando se intenta guardar, entonces el sistema lo impide.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-021 · Validar la edad de jugadores de la rama infantil contra su CURP
- Épica: EP-02
- Requisitos ERS: RF-04.1, RF-04.4 (rama infantil)
- Historia: Como Comisión de la Rama Infantil, quiero validar la edad de los jugadores infantiles contra su CURP, para asegurar que cumplen el criterio de edad de su categoría (Tabla 4).
- Criterios de aceptación:
  1. Dado un jugador de la rama infantil, cuando se registra, entonces el sistema compara el año de nacimiento del CURP con el rango de la categoría elegida.
  2. Dado un jugador cuya edad no corresponde a la categoría, cuando se intenta registrar o alinear, entonces el sistema lo bloquea y explica el motivo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016 y de las categorías/criterios de edad de HU-007.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-022 · Proteger los datos personales y el historial de cambios del jugador
- Épica: EP-02
- Requisitos ERS: RF-04.5, RNF-30
- Historia: Como Administrador General, quiero que el sistema proteja los datos personales del jugador y registre todo cambio en un historial, para cumplir la normativa de protección de datos y mantener trazabilidad.
- Criterios de aceptación:
  1. Dado un dato personal del jugador (documentos, domicilio), cuando se almacena, entonces no queda accesible por URL pública ni indexable (RNF-30).
  2. Dado un cambio a los datos de un jugador, cuando se guarda, entonces queda registrado en su historial con usuario, fecha y motivo.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-016 y HU-004.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-023 · Registrar canchas y sedes con su capacidad
- Épica: EP-03
- Requisitos ERS: RF-05.1
- Historia: Como Programador de Partidos, quiero registrar canchas y sedes con sus características y capacidad de partidos consecutivos, para contar con el catálogo que usará la programación.
- Criterios de aceptación:
  1. Dado el catálogo de canchas (alrededor de 40 en la U.D.C. y campos particulares), cuando registro una nueva, entonces queda disponible con sus características y capacidad.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-024 · Gestionar la disponibilidad y los bloqueos de canchas
- Épica: EP-03
- Requisitos ERS: RF-05.2
- Historia: Como Programador de Partidos, quiero registrar la disponibilidad por fecha y hora de cada cancha y bloquearla por mantenimiento o clima, para evitar programar en canchas no disponibles.
- Criterios de aceptación:
  1. Dado una cancha, cuando registro su disponibilidad por fecha y hora, entonces queda disponible para la programación.
  2. Dado un bloqueo por mantenimiento o lluvia, cuando lo registro, entonces la cancha deja de estar disponible en ese periodo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-023.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-025 · Impedir asignar una cancha bloqueada u ocupada
- Épica: EP-03
- Requisitos ERS: RF-05.3
- Historia: Como Programador de Partidos, quiero que el sistema impida asignar una cancha bloqueada o ya ocupada en el mismo horario, para no generar conflictos de programación.
- Criterios de aceptación:
  1. Dado un intento de programar un partido en una cancha bloqueada o ya ocupada en ese horario, cuando lo intento, entonces el sistema lo impide y muestra el motivo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-024.
- Riesgos y supuestos: corregido el 2026-10-12: el AC sobre "cierre de cédula" citaba RNF-16 por error; RNF-16 es específicamente sobre el cierre de la cédula arbitral (jugador no registrado/suspendido, goles que no suman el marcador, tarjeta a jugador no alineado), no sobre canchas. Ese requisito se reasignó a HU-075.
- Notas / [POR DEFINIR]: ninguna.

### HU-026 · Administrar el padrón de árbitros y asignarlos a los partidos
- Épica: EP-03
- Requisitos ERS: RF-03.2 (rol árbitro), RF-06.1
- Historia: Como Comisión Arbitral, quiero administrar el padrón de árbitros colegiados y su disponibilidad, y asignarlos a los partidos programados, para completar la programación semanal.
- Criterios de aceptación:
  1. Dado un árbitro colegiado, cuando se registra como persona con rol árbitro, entonces queda disponible para asignarse a partidos.
  2. Dado un partido en programación, cuando se le asigna un árbitro disponible, entonces queda registrado junto con equipo local, visitante, cancha, fecha y hora (RF-06.1).
  3. Dado un árbitro ya asignado a un partido en el mismo horario, cuando se intenta asignarlo a otro, entonces el sistema lo impide.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-013 (identidad de persona con rol árbitro).
- Riesgos y supuestos: [AMBIGÜEDAD] la ERS no tiene un inciso RF explícito para administrar el padrón y la disponibilidad de árbitros; el glosario (Tabla 3) dice que la existencia formal de la Comisión Arbitral en la Liga "está por validar". Ver preguntas abiertas.
- Notas / [POR DEFINIR]: confirmar con la Liga si la Comisión Arbitral existe formalmente y cómo gestiona hoy la disponibilidad de árbitros (Anexo B).

### HU-027 · Crear jornadas y partidos con asignación manual
- Épica: EP-03
- Requisitos ERS: RF-06.1
- Historia: Como Programador de Partidos, quiero crear jornadas y partidos indicando equipo local, visitante, cancha, fecha, hora y árbitro, para elaborar la programación semanal.
- Criterios de aceptación:
  1. Dado un grupo de una categoría, cuando creo una jornada, entonces puedo agregarle partidos con equipo local, visitante, cancha, fecha, hora y árbitro.
  2. Dado un equipo con sanción vigente que le impide jugar, cuando se intenta programar, entonces el sistema lo bloquea (depende de HU-047, ver riesgo).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-012, HU-025 y HU-026. El criterio 2 depende de HU-047 (bloqueo por sanción), agendada en S7, mientras esta historia está en S4.
- Riesgos y supuestos: dependencia invertida señalada por el equipo: hasta S7 no existe el bloqueo automático por sanción; en S4-S6 esa validación debe quedar marcada como pendiente o aplicarse manualmente.
- Notas / [POR DEFINIR]: ninguna.

### HU-028 · Registrar los equipos que descansan en cada jornada
- Épica: EP-03
- Requisitos ERS: RF-06.2
- Historia: Como Programador de Partidos, quiero registrar qué equipos descansan en cada jornada, para que la tabla y la programación reflejen correctamente los descansos.
- Criterios de aceptación:
  1. Dado un grupo con número impar de equipos, cuando genero la jornada, entonces puedo marcar qué equipo descansa.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-029 · Gestionar los estados y la reprogramación de partidos
- Épica: EP-03
- Requisitos ERS: RF-06.3, RF-06.4
- Historia: Como Programador de Partidos, quiero manejar los estados del partido (borrador, programado, confirmado, en juego, finalizado, suspendido, cancelado, reprogramado) y registrar el motivo de cada cambio, para dar seguimiento a cada partido.
- Criterios de aceptación:
  1. Dado un partido, cuando cambia de estado, entonces el sistema solo permite las transiciones válidas de ese flujo.
  2. Dado un cambio de estado o una reprogramación, cuando ocurre, entonces se registra usuario, fecha y motivo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-030 · Publicar la programación semanal tras su revisión y aprobación
- Épica: EP-03
- Requisitos ERS: RF-06.5
- Historia: Como Programador de Partidos, quiero publicar la programación solo después de revisarla y aprobarla, para evitar publicar partidos con conflictos.
- Criterios de aceptación:
  1. Dado un borrador de programación, cuando se revisa y aprueba, entonces pasa a estado publicado y queda visible en el portal (ver HU-062).
  2. Dado un borrador con conflictos sin resolver, cuando se intenta publicar, entonces el sistema lo impide.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027, HU-029 y, si se usa, HU-032/HU-074/HU-033.
- Riesgos y supuestos: ¿quién aprueba la programación antes de publicarla? (Anexo B, pregunta 1, supuesto de trabajo: un programador la crea y el Comité Ejecutivo la aprueba; rol "Comité Ejecutivo" no está en la sección 2.3, ver preguntas abiertas).
- Notas / [POR DEFINIR]: confirmar el rol que aprueba la programación antes de publicarla.

### HU-031 · Conservar el historial de partidos con resultados
- Épica: EP-03
- Requisitos ERS: RF-06.6
- Historia: Como Programador de Partidos, quiero que el sistema no permita eliminar partidos que ya tienen historial, para no perder información oficial.
- Criterios de aceptación:
  1. Dado un partido ya jugado o con cédula registrada, cuando alguien intenta eliminarlo, entonces el sistema lo impide.
  2. Dado un partido en borrador sin historial, cuando se elimina, entonces el sistema sí lo permite.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-032 · Generar automáticamente los enfrentamientos de una jornada
- Épica: EP-03
- Requisitos ERS: RF-07.1
- Historia: Como Programador de Partidos, quiero que el sistema genere automáticamente los enfrentamientos de una jornada a partir de la temporada, categoría, grupo y equipos, para no definirlos manualmente uno por uno.
- Criterios de aceptación:
  1. Dado un grupo con sus equipos, cuando genero los enfrentamientos de una jornada, entonces el sistema propone qué equipo juega contra cuál, sin equipos duplicados en la misma jornada (RF-07.1).
  2. Dado un grupo con número impar de equipos, cuando se generan los enfrentamientos, entonces el sistema asigna correctamente el descanso (ver HU-028).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-012 (equipos) y HU-007 (categorías/grupos). La usa HU-074 (asignación de cancha/horario).
- Riesgos y supuestos: historia dividida el 2026-10-12 por tamaño; antes incluía también la asignación de cancha/horario y las restricciones (ahora en HU-074).
- Notas / [POR DEFINIR]: ninguna.

### HU-074 · Asignar cancha y horario a los enfrentamientos generados respetando restricciones
- Épica: EP-03
- Requisitos ERS: RF-07.2, RNF-07, RNF-08
- Historia: Como Programador de Partidos, quiero que el sistema asigne cancha, fecha y hora a los enfrentamientos ya generados, respetando las restricciones duras y blandas, para completar la propuesta de programación automática.
- Criterios de aceptación:
  1. Dado un conjunto de enfrentamientos generados (HU-032), cuando se les asigna cancha, fecha y hora, entonces no hay canchas ocupadas o no disponibles, ni equipos con sanción que lo impida (RF-07.2).
  2. Dado un grupo de hasta 50 equipos, cuando se calendariza, entonces el sistema responde en ≤ 10 segundos; una jornada completa de todas las categorías (≈ 400 partidos, 40 canchas) se ejecuta en ≤ 60 segundos en segundo plano (RNF-07).
  3. Dado el volumen real de la Liga (hasta 30,000 personas, 800 equipos, 400 partidos por fin de semana, 5 temporadas conservadas), cuando se usa el motor, entonces no degrada los tiempos de RNF-06 (RNF-08).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-032, HU-024, HU-025, HU-026 y HU-047 (sanciones; dependencia invertida ya señalada, ver PA-13).
- Riesgos y supuestos: historia dividida el 2026-10-12 por tamaño (antes era parte de HU-032). La restricción "sin equipos con sanción" (RF-07.2) depende de HU-047, agendada en un sprint posterior a esta; ver PA-13.
- Notas / [POR DEFINIR]: ninguna.

### HU-033 · Detectar y explicar los conflictos de la programación generada
- Épica: EP-03
- Requisitos ERS: RF-07.3, RNF-19
- Historia: Como Programador de Partidos, quiero que el sistema detecte y explique los conflictos que impiden generar una solución completa, para poder resolverlos.
- Criterios de aceptación:
  1. Dado que el motor no puede generar una solución completa (más partidos que canchas disponibles, por ejemplo), cuando ocurre, entonces el sistema reporta el déficit sin bloquearse.
  2. Dado un conflicto detectado, cuando se muestra, entonces explica la regla que lo origina y qué lo causa (RNF-19).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-032 y HU-074.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-034 · Ajustar manualmente la programación generada automáticamente
- Épica: EP-03
- Requisitos ERS: RF-07.4
- Historia: Como Programador de Partidos, quiero ajustar manualmente la programación que el sistema generó automáticamente, para corregir casos que el motor no resolvió bien.
- Criterios de aceptación:
  1. Dado un borrador generado automáticamente, cuando lo ajusto manualmente (cambio de cancha, horario o enfrentamiento), entonces el sistema guarda el motivo del ajuste.
  2. Dado un ajuste manual, cuando se guarda, entonces queda registrado quién lo hizo y por qué.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-032 y HU-074.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-035 · Validar la usabilidad de la programación semanal
- Épica: EP-03
- Requisitos ERS: RNF-17
- Historia: Como Programador de Partidos, quiero que elaborar la programación de una jornada sea mucho más rápido que el proceso manual actual, para liberar tiempo de las oficinas de la Liga.
- Criterios de aceptación:
  1. Dado un grupo de 10 equipos, cuando un programador arma y publica su jornada, entonces lo logra en ≤ 15 minutos (frente a las horas del proceso actual descrito en 1.2).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027 a HU-034 (incluye HU-074).
- Riesgos y supuestos: requiere una prueba de usabilidad con un programador real, similar a la de HU-036/HU-075.
- Notas / [POR DEFINIR]: ninguna.

### HU-036 · Capturar alineación y eventos del partido desde un dispositivo móvil
- Épica: EP-04
- Requisitos ERS: RF-08.1 (alineación y eventos), RNF-15, RNF-21, IU-06, IH-01
- Historia: Como Árbitro en Campo, quiero capturar el marcador, las alineaciones, amonestaciones, expulsiones e incidencias desde mi celular durante la jornada, para no depender de la cédula de papel.
- Criterios de aceptación:
  1. Dado un partido asignado, cuando lo abro desde mi celular con navegador actualizado, entonces puedo registrar marcador, alineaciones, amonestaciones, expulsiones e incidencias (RF-08.1, IH-01).
  2. Dado que estoy en la cancha, cuando capturo estos datos, entonces la interfaz permite hacerlo durante la jornada, desde el dispositivo móvil (IU-06).
  3. Dado que la alineación ya está precargada desde la plantilla del equipo, cuando marco asistencia y número de camiseta, entonces no necesito volver a escribir nombres (RNF-15).
  4. Dado que falla la conexión al guardar estos datos, cuando ocurre, entonces se conservan y se pueden reenviar sin duplicarse (RNF-21).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-013, HU-016, HU-026 y HU-027. La usa HU-075 (firmas y cierre de cédula).
- Riesgos y supuestos: historia dividida el 2026-10-12 por tamaño; antes incluía también las firmas digitales y el cierre de la cédula (ahora en HU-075).
- Notas / [POR DEFINIR]: ninguna.

### HU-075 · Recabar las firmas digitales de los capitanes y cerrar la cédula
- Épica: EP-04
- Requisitos ERS: RF-08.1 (reporte arbitral y firmas), RNF-14, RNF-16
- Historia: Como Árbitro en Campo, quiero recabar las firmas digitales de los capitanes y cerrar la cédula al finalizar el partido, para dejar constancia oficial del encuentro.
- Criterios de aceptación:
  1. Dado un partido con marcador, alineaciones e incidencias ya capturados (HU-036), cuando el partido termina, entonces puedo registrar el reporte arbitral y recabar la firma digital de cada capitán.
  2. Dado un intento de cerrar la cédula sin las firmas de ambos capitanes, cuando ocurre, entonces el sistema lo impide.
  3. Dado un grupo de 5 árbitros de prueba sin capacitación previa, cuando usan un instructivo de una página, entonces al menos 4 de 5 completan el flujo completo (HU-036 + esta historia: 2 alineaciones, 4 goles, 3 tarjetas y firmas) en ≤ 5 minutos (RNF-14).
  4. Dado el cierre de la cédula, cuando se intenta guardar, entonces el sistema detecta antes de guardar el 100 % de estos casos: jugador no registrado, jugador suspendido, goles que no suman el marcador y tarjeta a jugador no alineado; el cierre muestra un resumen para confirmar (RNF-16).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-036.
- Riesgos y supuestos: RNF-14 mide el flujo completo (HU-036 + HU-075 juntas); la prueba de usabilidad debe cubrir ambas historias de punta a punta. RNF-16 se reasignó aquí el 2026-10-12 desde HU-025, donde estaba mal aplicado (ver nota en HU-025).
- Notas / [POR DEFINIR]: ninguna.

### HU-037 · Capturar un resultado desde una cédula física como capturista
- Épica: EP-04
- Requisitos ERS: RF-08.1
- Historia: Como Capturista, quiero registrar en el sistema los resultados y cédulas físicas que llegan en papel, para digitalizar la información cuando el árbitro no pudo capturarla en campo.
- Criterios de aceptación:
  1. Dado un formulario rápido de captura, cuando transcribo una cédula física, entonces puedo registrar marcador, alineaciones, amonestaciones, expulsiones e incidencias con las mismas validaciones que la captura móvil.
  2. Dado un dato inválido o incompleto, cuando intento guardar, entonces el sistema muestra un mensaje de error claro.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-013, HU-016 y HU-027.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-038 · Identificar automáticamente el partido, los equipos y las personas al capturar
- Épica: EP-04
- Requisitos ERS: RF-08.2
- Historia: Como Capturista, quiero que el sistema identifique automáticamente el partido, los equipos y las personas involucradas al abrir una cédula, para no tener que buscarlos manualmente.
- Criterios de aceptación:
  1. Dado un partido programado, cuando abro su cédula, entonces el sistema ya muestra equipos, jugadores de la plantilla y árbitro asignado sin que los capture a mano.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-027 y HU-036/HU-037.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-039 · Corregir un resultado capturado con auditoría y recálculo
- Épica: EP-04
- Requisitos ERS: RF-08.3, RNF-33
- Historia: Como [POR DEFINIR: usuario autorizado distinto del capturista, ver Anexo B pregunta 4], quiero corregir un resultado ya capturado con auditoría del cambio y recálculo de la tabla, para remediar errores de captura.
- Criterios de aceptación:
  1. Dado un resultado ya validado, cuando un usuario autorizado lo corrige, entonces el sistema exige un motivo y registra el cambio en la auditoría (RNF-33).
  2. Dado un resultado corregido, cuando se guarda, entonces se recalcula automáticamente la tabla de posiciones afectada.
  3. Dado un usuario sin autorización explícita (por ejemplo, el mismo capturista), cuando intenta corregir, entonces el sistema lo impide.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-004, HU-037, HU-041.
- Riesgos y supuestos: el Anexo B (pregunta 4) solo da un supuesto de trabajo ("un usuario autorizado distinto del capturista, con auditoría"); la ERS no define el rol exacto.
- Notas / [POR DEFINIR]: confirmar con el equipo o la Liga qué rol de la sección 2.3 puede corregir un resultado ya validado.

### HU-040 · Validar oficialmente un resultado antes de publicarlo
- Épica: EP-04
- Requisitos ERS: RF-08.4
- Historia: Como [POR DEFINIR, ver Anexo B pregunta 5], quiero validar oficialmente un resultado capturado antes de que se considere definitivo, para que la tabla solo se actualice con información confirmada.
- Criterios de aceptación:
  1. Dado un resultado capturado, cuando no ha sido validado, entonces no se considera oficial ni actualiza la tabla de posiciones.
  2. Dado un resultado capturado, cuando se valida, entonces pasa a oficial y dispara el cálculo de HU-041.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-036/HU-037 y HU-075 (cierre de cédula con firmas); la usa HU-041.
- Riesgos y supuestos: el Anexo B (pregunta 5) solo dice "al ser validado; solo entonces se actualiza la tabla", sin precisar el rol que valida.
- Notas / [POR DEFINIR]: confirmar con el equipo o la Liga qué rol valida oficialmente un resultado.

### HU-041 · Calcular automáticamente la tabla de posiciones
- Épica: EP-04
- Requisitos ERS: RF-09.1, RNF-01, RNF-06, RNF-22
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema calcule automáticamente PJ, PG, PE, PP, GF, GC, DG, puntos y posición tras validar un resultado, para no tener que calcularlo a mano.
- Criterios de aceptación:
  1. Dado un resultado oficial, cuando se guarda, entonces el sistema recalcula PJ, PG, PE, PP, GF, GC, DG, puntos y posición según las reglas de la temporada.
  2. Dado al menos 3 categorías y 2 jornadas de datos reales, cuando se compara la tabla generada con el cálculo manual, entonces coincide en el 100 % de las celdas (RNF-01).
  3. Dado el guardado de una cédula o el registro de un resultado, cuando ocurre, entonces responde en ≤ 2 segundos en el 95 % de los casos, con una base de 30,000 personas y 3 temporadas simuladas (RNF-06).
  4. Dado el cálculo de la tabla, cuando se ejecuta, entonces es una operación atómica: si se simula una falla a la mitad, no quedan datos parciales (RNF-22).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-040 y HU-008 (formato/criterios).
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-042 · Aplicar criterios de desempate configurables
- Épica: EP-04
- Requisitos ERS: RF-09.2
- Historia: Como Administrador General, quiero que el sistema aplique los criterios de desempate configurados para cada temporada, para resolver empates de puntos en la tabla sin intervención manual.
- Criterios de aceptación:
  1. Dado dos o más equipos empatados en puntos, cuando se calcula la posición, entonces el sistema aplica en orden los criterios de desempate configurados en HU-006.
  2. Dado un cambio en el orden de los criterios de desempate, cuando se guarda, entonces no requiere modificar código.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-006 y HU-041.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-043 · Generar estadísticas de goleo, tarjetas y sanciones por jornada
- Épica: EP-04
- Requisitos ERS: RF-09.3, RF-09.4
- Historia: Como Secretario (Consejo Directivo), quiero que el sistema genere estadísticas de goleadores, tarjetas y sanciones, y conserve el histórico de la tabla por jornada, para publicarlas y consultarlas después.
- Criterios de aceptación:
  1. Dado un conjunto de resultados oficiales, cuando se consultan las estadísticas, entonces se muestran goleadores, tarjetas y sanciones acumuladas.
  2. Dado el cierre de cada jornada, cuando ocurre, entonces se conserva una copia histórica de la tabla de esa jornada.
  3. Dado un dato oficial (resultado validado) y un indicador calculado (posición, puntos), cuando se consultan, entonces el sistema los distingue claramente (RF-09.4).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-041.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-044 · Generar un expediente disciplinario desde las incidencias de la cédula
- Épica: EP-05
- Requisitos ERS: RF-10.1
- Historia: Como Tribunal de Penas (Comisión Disciplinaria), quiero que el sistema genere un expediente a partir de las incidencias registradas en la cédula, con personas, equipos, evidencias y artículos presuntamente infringidos, para iniciar el proceso disciplinario sin volver a capturar datos.
- Criterios de aceptación:
  1. Dado una cédula con incidencias (tarjetas, expulsiones, reportes), cuando se cierra, entonces el sistema genera automáticamente un expediente con personas, equipos y evidencias asociadas.
  2. Dado un expediente generado, cuando se revisa, entonces muestra los artículos del catálogo que presuntamente se infringieron.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-036/HU-037 y HU-075 (cierre de la cédula con sus incidencias) y del catálogo de artículos (ver nota).
- Riesgos y supuestos: requiere que exista el catálogo de artículos reglamentarios; si no se ha cargado, se marca [POR DEFINIR] su origen (Reglamento de Sanciones del Sector Amateur de la FMF, REF-05).
- Notas / [POR DEFINIR]: confirmar quién carga y mantiene el catálogo de artículos del Reglamento de Sanciones.

### HU-045 · Registrar audiencia, resolución y sanción
- Épica: EP-05
- Requisitos ERS: RF-10.2
- Historia: Como Tribunal de Penas (Comisión Disciplinaria), quiero registrar la audiencia, la resolución y la sanción de un expediente, para dictaminar los casos disciplinarios.
- Criterios de aceptación:
  1. Dado un expediente abierto, cuando registro la audiencia y la resolución, entonces el sistema guarda la sanción dictaminada (por ejemplo, partidos de suspensión).
  2. Dado un expediente resuelto, cuando se consulta, entonces muestra el historial completo de la resolución.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-044.
- Riesgos y supuestos: "¿Quién resuelve las sanciones?" ya está definido en el Anexo A/B como la Comisión Disciplinaria.
- Notas / [POR DEFINIR]: ninguna.

### HU-046 · Calcular la vigencia de la sanción y mantener el boletín de castigados
- Épica: EP-05
- Requisitos ERS: RF-10.3, RNF-44
- Historia: Como Tribunal de Penas (Comisión Disciplinaria), quiero que el sistema calcule la vigencia de cada sanción y mantenga la lista de castigados, para publicar el boletín oficial cada semana.
- Criterios de aceptación:
  1. Dado una sanción dictaminada (por ejemplo, 2 partidos), cuando se registra, entonces el sistema calcula hasta qué fecha o partido sigue vigente.
  2. Dado un jugador con sanción cumplida, cuando se consulta, entonces ya no aparece en el boletín de castigados.
  3. Dado un expediente aún no validado por la Comisión Disciplinaria, cuando se consulta el portal, entonces esa sanción no aparece publicada; toda publicación registra quién la autorizó (RNF-44).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-045.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-047 · Bloquear la participación o la programación por sanción vigente
- Épica: EP-05
- Requisitos ERS: RF-10.4, IU-05
- Historia: Como Programador de Partidos, quiero que el sistema bloquee automáticamente la programación o la participación de un jugador o equipo con sanción vigente, para no incumplir el reglamento disciplinario.
- Criterios de aceptación:
  1. Dado un jugador con sanción vigente, cuando se intenta alinear, entonces el sistema lo impide.
  2. Dado un equipo con sanción que le impide jugar, cuando se intenta programar, entonces el sistema lo impide.
  3. Dado un bloqueo por sanción, cuando se muestra, entonces explica el motivo y la regla que lo origina (IU-05).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-046; la usan HU-019, HU-027 y HU-074 (que, en el roadmap provisional, quedaban en sprints anteriores — dependencia invertida ya señalada).
- Riesgos y supuestos: dependencia invertida: hasta que esta historia esté Hecha, el bloqueo por sanción en programación (HU-027, HU-074) y en elegibilidad (HU-019) debe tratarse como pendiente o validarse de forma manual.
- Notas / [POR DEFINIR]: ninguna.

### HU-048 · Registrar una inconformidad ante la Comisión Disciplinaria
- Épica: EP-05
- Requisitos ERS: RF-10.5 (primera instancia)
- Historia: Como Delegado del Club, quiero presentar una inconformidad por escrito ante la Comisión Disciplinaria, con fianza, para impugnar un reporte arbitral, una alineación indebida o un resultado.
- Criterios de aceptación:
  1. Dado un partido con un hecho que quiero impugnar, cuando presento la inconformidad, entonces el sistema la registra con la evidencia y el comprobante de fianza.
  2. Dado una inconformidad registrada, cuando el Tribunal de Penas la resuelve, entonces la resolución queda en un historial inmutable.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-045.
- Riesgos y supuestos: conflicto de prioridad: RF-10 completo es Alta/Esencial (Must), pero la tabla de AF-08 marca "inconformidades y recursos" como Could. Ver preguntas abiertas.
- Notas / [POR DEFINIR]: el Anexo B (pregunta 8) deja los plazos "por definir"; confirmar con la Liga.

### HU-049 · Resolver un recurso de segunda instancia
- Épica: EP-05
- Requisitos ERS: RF-10.5 (segunda instancia)
- Historia: Como [POR DEFINIR: Mesa Directiva o Asamblea de Presidentes, ninguno de los dos está en la sección 2.3], quiero resolver en segunda instancia un recurso cuando la Comisión Disciplinaria falla en contra de un club, para dar una vía de apelación.
- Criterios de aceptación:
  1. Dado un fallo de la Comisión Disciplinaria en contra de un club, cuando el club presenta un recurso, entonces el sistema lo registra como segunda instancia.
  2. Dado un recurso resuelto, cuando se consulta, entonces queda en un historial inmutable junto con la inconformidad original.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-048.
- Riesgos y supuestos: mismo conflicto de prioridad que HU-048. Además, "Mesa Directiva" y "Asamblea de Presidentes" (RF-10.5) no son roles de la sección 2.3; el más cercano sería "Presidente (Consejo Directivo)", pero no es exacto.
- Notas / [POR DEFINIR]: confirmar con el equipo qué rol de la sección 2.3 resuelve el recurso de segunda instancia.

### HU-050 · Registrar cuotas y pagos de inscripción, arbitraje y canchas
- Épica: EP-06
- Requisitos ERS: RF-13.1
- Historia: Como Tesorero (Consejo Directivo), quiero registrar las cuotas de inscripción (club, equipo, jugadores) y los pagos de arbitraje y canchas, para habilitar la participación de clubes, equipos y jugadores.
- Criterios de aceptación:
  1. Dado un club, equipo o jugador, cuando registro su cuota de inscripción, entonces queda asociada a la temporada correspondiente.
  2. Dado un pago de arbitraje o de cancha, cuando lo registro, entonces queda vinculado al club o equipo que lo generó.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna directa; la usa HU-019 (elegibilidad por cuota cubierta).
- Riesgos y supuestos: al estar agendada en S8 y ser insumo de HU-019 (S3), se repite la dependencia invertida ya señalada.
- Notas / [POR DEFINIR]: ninguna.

### HU-051 · Controlar adeudos y comprobantes de pago
- Épica: EP-06
- Requisitos ERS: RF-13.2
- Historia: Como Tesorero (Consejo Directivo), quiero controlar los adeudos y los comprobantes de pago de cada club, para saber quién está al corriente.
- Criterios de aceptación:
  1. Dado un cargo pendiente, cuando se registra un pago parcial o total, entonces el sistema actualiza el estado del adeudo.
  2. Dado un comprobante de pago, cuando se sube, entonces queda vinculado al cargo correspondiente.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-050.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-052 · Generar reportes financieros por temporada, club y concepto
- Épica: EP-06
- Requisitos ERS: RF-13.3
- Historia: Como Tesorero (Consejo Directivo), quiero generar reportes financieros por temporada, club y concepto, para rendir cuentas a la directiva.
- Criterios de aceptación:
  1. Dado un periodo y un filtro (temporada, club o concepto), cuando genero el reporte, entonces el sistema muestra los montos correspondientes.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-050 y HU-051.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-053 · Registrar convocatoria y asistencia a asamblea de delegados
- Épica: EP-06
- Requisitos ERS: RF-14.1
- Historia: Como Secretario (Consejo Directivo), quiero registrar la convocatoria a asamblea y la asistencia de los delegados, para documentar las reuniones formales de la Liga.
- Criterios de aceptación:
  1. Dado una asamblea convocada, cuando registro la lista de asistentes, entonces queda guardada junto con la convocatoria.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: ninguna.
- Riesgos y supuestos: prioridad Baja/Could; candidata a diferir si el tiempo no alcanza.
- Notas / [POR DEFINIR]: ninguna.

### HU-054 · Registrar acuerdos y comunicados de asamblea
- Épica: EP-06
- Requisitos ERS: RF-14.2, RF-14.3
- Historia: Como Secretario (Consejo Directivo), quiero registrar los acuerdos y comunicados de una asamblea, indicando si modifican el reglamento de la temporada, para dejar constancia formal.
- Criterios de aceptación:
  1. Dado un acuerdo de asamblea, cuando lo registro, entonces indico si modifica el reglamento de la temporada.
  2. Dado un acuerdo registrado, cuando se intenta convertir en regla permanente sin autorización, entonces el sistema lo impide (RF-14.3).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-053.
- Riesgos y supuestos: prioridad Baja/Could; candidata a diferir.
- Notas / [POR DEFINIR]: ninguna.

### HU-055 · Clasificar equipos a la liguilla y generar las llaves
- Épica: EP-07
- Requisitos ERS: RF-11.1
- Historia: Como [POR DEFINIR: "Comité Ejecutivo", actor en el Anexo A, no está en la sección 2.3; se usa Presidente (Consejo Directivo) como aproximación], quiero clasificar equipos a la liguilla y generar las llaves según las reglas configuradas, para iniciar las fases finales de la temporada.
- Criterios de aceptación:
  1. Dado el cierre de la fase regular, cuando se clasifica a la liguilla, entonces se toman los primeros lugares según el formato configurado (por ejemplo, 1° vs 8°, 2° vs 7°, 3° vs 6°, 4° vs 5°).
  2. Dado el cruce generado, cuando se consulta, entonces conserva la versión de las reglas usadas (RNF-36).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-041 (tabla final) y HU-006 (reglas de clasificación).
- Riesgos y supuestos: prioridad Baja/Could; todo EP-07 es la primera candidata a recortar si el tiempo no alcanza para el 11/12.
- Notas / [POR DEFINIR]: confirmar el rol exacto de la sección 2.3 que ejecuta y aprueba esta clasificación (ver preguntas abiertas).

### HU-056 · Registrar avances, eliminaciones y determinar campeón y subcampeón
- Épica: EP-07
- Requisitos ERS: RF-11.2
- Historia: Como [POR DEFINIR, ver HU-055], quiero registrar los avances y eliminaciones de la liguilla y determinar al campeón y subcampeón, para cerrar el torneo de liga.
- Criterios de aceptación:
  1. Dado un resultado de liguilla, cuando se registra, entonces el equipo ganador avanza a la siguiente llave.
  2. Dado el partido final, cuando se resuelve, entonces el sistema determina campeón y subcampeón.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-055.
- Riesgos y supuestos: mismos de HU-055.
- Notas / [POR DEFINIR]: mismo rol pendiente que HU-055.

### HU-057 · Generar propuestas de ascenso y descenso versionadas
- Épica: EP-07
- Requisitos ERS: RF-11.3
- Historia: Como [POR DEFINIR, ver HU-055], quiero generar propuestas de ascenso y descenso guardando la versión de las reglas utilizadas, para aplicar los cambios de categoría de la siguiente temporada.
- Criterios de aceptación:
  1. Dado el cierre de la temporada, cuando se generan las propuestas de ascenso/descenso, entonces se basan en las reglas configuradas en HU-006.
  2. Dado un cambio futuro a esas reglas, cuando ocurre, entonces no afecta la versión ya usada para esta propuesta.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-041 y HU-006.
- Riesgos y supuestos: mismos de HU-055.
- Notas / [POR DEFINIR]: mismo rol pendiente que HU-055.

### HU-058 · Validar manualmente reglas de ascenso/descenso no automáticas
- Épica: EP-07
- Requisitos ERS: RF-11.4
- Historia: Como [POR DEFINIR, ver HU-055], quiero validar manualmente los casos de ascenso o descenso que la regla configurada no puede resolver de forma automática, para cerrar esos casos excepcionales.
- Criterios de aceptación:
  1. Dado un caso de ascenso/descenso que la regla automática no puede resolver, cuando se presenta, entonces el sistema lo marca para validación humana en vez de decidirlo solo.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-057.
- Riesgos y supuestos: el Anexo B (pregunta 10) deja abiertas las excepciones de ascenso/descenso.
- Notas / [POR DEFINIR]: confirmar con la Liga qué excepciones de ascenso/descenso existen.

### HU-059 · Emitir el informe final y cerrar la temporada
- Épica: EP-07
- Requisitos ERS: RF-11.5
- Historia: Como [POR DEFINIR, ver HU-055], quiero emitir el informe final y cerrar la temporada, para dejarla en consulta y evitar modificaciones posteriores.
- Criterios de aceptación:
  1. Dado el cierre de todas las fases, cuando se emite el informe final, entonces la temporada pasa a estado cerrado (consistente con HU-005, RF-01.4).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-056, HU-057 y HU-058.
- Riesgos y supuestos: mismos de HU-055.
- Notas / [POR DEFINIR]: mismo rol pendiente que HU-055.

### HU-060 · Gestionar liguilla de consolación, Campeón de Campeones y campeón de goleo
- Épica: EP-07
- Requisitos ERS: RF-11.6
- Historia: Como [POR DEFINIR, ver HU-055], quiero gestionar la liguilla de consolación (campeón alterno), el partido de Campeón de Campeones y el campeón de goleo de cada categoría, para completar el cierre deportivo de la temporada.
- Criterios de aceptación:
  1. Dado el grupo de equipos de media tabla, cuando se arma la liguilla de consolación, entonces determina un campeón alterno.
  2. Dado el campeón de liga y el campeón de liguilla, cuando se juega el partido de Campeón de Campeones, entonces el sistema registra su resultado.
  3. Dado el cierre de la temporada, cuando se consulta, entonces el sistema muestra el campeón de goleo de cada categoría.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-055, HU-056 y HU-043 (goleo).
- Riesgos y supuestos: mismos de HU-055.
- Notas / [POR DEFINIR]: mismo rol pendiente que HU-055.

### HU-061 · Registrar el Torneo de Copa y torneos especiales
- Épica: EP-07
- Requisitos ERS: RF-11.7
- Historia: Como [POR DEFINIR, ver HU-055], quiero registrar el Torneo de Copa y otros torneos especiales (por ejemplo, de verano), cada uno con su propio calendario y tabla, para administrarlos igual que el torneo de liga.
- Criterios de aceptación:
  1. Dado un torneo especial (Copa, de verano), cuando se registra, entonces tiene su propio calendario y tabla, independientes del torneo de liga.
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-005, HU-007 y HU-027 a HU-034 (reutiliza programación y motor automático).
- Riesgos y supuestos: coherente con AF-02, que marca "copa y torneos especiales" como Could; mismos de HU-055.
- Notas / [POR DEFINIR]: mismo rol pendiente que HU-055.

### HU-062 · Consultar la programación pública del portal sin necesidad de cuenta
- Épica: EP-08
- Requisitos ERS: RF-12.3 (programación), IU-01, RNF-04, RNF-10, RNF-13
- Historia: Como Público General (aficionado/prensa), quiero consultar la programación semanal en el portal público sin necesidad de una cuenta, para saber cuándo y dónde juega cada equipo.
- Criterios de aceptación:
  1. Dado el portal público, cuando lo visito sin iniciar sesión, entonces veo la programación de partidos (fecha, hora, cancha, equipos), sin privilegios de edición (RF-12.3, IU-01).
  2. Dado un celular de gama media con red 4G simulada, cuando cargo la página de programación, entonces LCP ≤ 2.5 s, INP ≤ 200 ms y CLS ≤ 0.1 en el percentil 75 (RNF-04).
  3. Dado el navegador usado, cuando es una de las dos últimas versiones estables de Chrome, Edge, Firefox o Safari (o Chrome Android/Safari iOS), entonces la programación se ve correctamente, con ancho mínimo de 360 px sin desplazamiento horizontal (RNF-10).
  4. Dado esta página y su navegación, cuando se evalúa, entonces cumple WCAG 2.2 nivel AA (contraste ≥ 4.5:1, objetivos táctiles ≥ 24×24 px, navegación completa por teclado, sin CAPTCHA, Lighthouse ≥ 90) (RNF-13).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-030 (programación publicada).
- Riesgos y supuestos: historia dividida el 2026-10-12 por contenido; antes incluía también resultados/tablas y castigados/comunicados (ahora en HU-077 y HU-078). RNF-05 y RNF-20 (rendimiento y disponibilidad bajo carga) solo se pueden verificar en producción; aplican al portal completo, no a esta historia en particular — se marcan en HU-077, que es la primera en incorporar datos de alto volumen (tablas y estadísticas).
- Notas / [POR DEFINIR]: ninguna.

### HU-077 · Consultar resultados y tablas de posiciones en el portal público
- Épica: EP-08
- Requisitos ERS: RF-12.3 (resultados, tablas, estadísticas), IU-01, RNF-04, RNF-10, RNF-13, RNF-31
- Historia: Como Público General (aficionado/prensa), quiero consultar los resultados, las tablas de posiciones y las estadísticas en el portal público sin necesidad de una cuenta, para seguir el desempeño de los equipos.
- Criterios de aceptación:
  1. Dado el portal público, cuando lo visito sin iniciar sesión, entonces veo resultados, tablas de posiciones y estadísticas (goleo, tarjetas), sin privilegios de edición (RF-12.3, IU-01).
  2. Dado el portal, cuando se muestra un dato de jugador en las estadísticas, entonces nunca incluye fecha de nacimiento, CURP, domicilio, teléfono ni fotografía de un menor de edad; de los menores solo se muestra nombre y equipo (RNF-31).
  3. Dado un celular de gama media con red 4G simulada, cuando cargo la tabla de posiciones, entonces LCP ≤ 2.5 s, INP ≤ 200 ms y CLS ≤ 0.1 en el percentil 75 (RNF-04).
  4. Dado el navegador usado, cuando es una de las dos últimas versiones estables de Chrome, Edge, Firefox o Safari, entonces la página se ve correctamente, con ancho mínimo de 360 px (RNF-10); la página cumple además WCAG 2.2 nivel AA (RNF-13).
  5. [Marcado "solo en producción", no verificable en el entorno de desarrollo] Dado 200 usuarios virtuales concurrentes durante 10 minutos, el 95 % de las respuestas tarda ≤ 1 s y 0 % devuelve error 5xx (RNF-05); la disponibilidad es ≥ 99.5 % mensual y ≥ 99.9 % los viernes 18:00 a martes 23:59 (RNF-20).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-040 (resultado oficial), HU-041 (tabla) y HU-043 (estadísticas).
- Riesgos y supuestos: historia dividida el 2026-10-12 por contenido (antes era parte de HU-062). Puede publicarse en cuanto HU-041/HU-043 estén Hechas, sin esperar al sprint de cierre.
- Notas / [POR DEFINIR]: ninguna.

### HU-078 · Consultar el boletín de castigados y los comunicados en el portal público
- Épica: EP-08
- Requisitos ERS: RF-12.3 (sanciones autorizadas, comunicados), IU-01, RNF-04, RNF-10, RNF-13
- Historia: Como Público General (aficionado/prensa), quiero consultar el boletín de castigados y los comunicados oficiales en el portal público sin necesidad de una cuenta, para enterarme de las sanciones y los avisos de la Liga.
- Criterios de aceptación:
  1. Dado el portal público, cuando lo visito sin iniciar sesión, entonces veo el boletín de castigados y los comunicados ya autorizados, sin privilegios de edición (RF-12.3, IU-01).
  2. Dado un expediente disciplinario aún no validado por la Comisión Disciplinaria, cuando se consulta el portal, entonces esa sanción no aparece publicada (consistente con RNF-44, ver HU-046).
  3. Dado un celular de gama media con red 4G simulada, cuando cargo el boletín de castigados, entonces LCP ≤ 2.5 s, INP ≤ 200 ms y CLS ≤ 0.1 en el percentil 75 (RNF-04).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-046 (boletín de castigados).
- Riesgos y supuestos: historia dividida el 2026-10-12 por contenido (antes era parte de HU-062). Puede publicarse en cuanto HU-046 esté Hecha, sin esperar al sprint de cierre. Los "comunicados" de RF-12.3 no tienen una historia de autoría/publicación propia en el backlog (RF-15.3/HU-066 es el envío por correo, no necesariamente la publicación en el portal) — ver PA-15.
- Notas / [POR DEFINIR]: confirmar si los "comunicados" del portal público (RF-12.3) son los mismos que se envían por correo (RF-15.3, HU-066) o requieren un módulo de publicación propio (PA-15).

### HU-063 · Buscar información del portal por fecha, categoría y equipo
- Épica: EP-08
- Requisitos ERS: RF-12.4
- Historia: Como Público General (aficionado/prensa), quiero buscar en el portal por fecha, categoría y equipo, para encontrar rápido la información que me interesa.
- Criterios de aceptación:
  1. Dado el buscador del portal, cuando filtro por fecha, categoría o equipo, entonces los resultados se ajustan al filtro, sin importar si el contenido viene de HU-062, HU-077 o HU-078.
  2. Dado el flujo interno de publicación, cuando un contenido pasa de borrador a revisión y a publicado, entonces solo el estado "publicado" es visible en el portal (RF-12.4).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-062, HU-077 y HU-078.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-064 · Enviar avisos de programación y cambios de sede u horario por correo
- Épica: EP-08
- Requisitos ERS: RF-15.1, RNF-12, IC-03, IC-04
- Historia: Como Delegado del Club, quiero recibir por correo electrónico avisos de programación y de cambios de sede u horario de mis partidos, para enterarme sin tener que consultar el portal todos los días.
- Criterios de aceptación:
  1. Dado un partido programado o reprogramado de mi equipo, cuando se publica o cambia de sede/horario, entonces recibo un correo con los datos actualizados.
  2. Dado el dominio remitente del Sistema, cuando envía ese correo, entonces tiene SPF, DKIM y DMARC configurados, llega a la bandeja de entrada de Gmail y Outlook con los tres en "pass", en versión HTML y de texto plano (RNF-12).
  3. Dado el envío por SMTP, cuando ocurre, entonces usa autenticación hacia el servicio de correo configurado y las credenciales del servicio se almacenan fuera del código (IC-03, IC-04).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-029/HU-030 (cambios de programación) y de HU-079 (SMTP).
- Riesgos y supuestos: la planificación agendaba el correo hasta S9; con HU-079 como historia habilitadora sin sprint fijo, el Scrum Master puede adelantarla.
- Notas / [POR DEFINIR]: ninguna.

### HU-065 · Enviar recordatorios de pago a clubes con adeudos
- Épica: EP-08
- Requisitos ERS: RF-15.2
- Historia: Como Delegado del Club, quiero recibir recordatorios de pago por correo cuando mi club tenga adeudos, para regularizarlos a tiempo.
- Criterios de aceptación:
  1. Dado un club con un adeudo vencido o próximo a vencer, cuando el sistema genera el recordatorio, entonces se envía por correo al delegado correspondiente.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-051 y HU-064 (infraestructura de correo).
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-066 · Enviar avisos institucionales y comunicados a grupos de usuarios
- Épica: EP-08
- Requisitos ERS: RF-15.3, IC-03
- Historia: Como Secretario (Consejo Directivo), quiero enviar avisos institucionales y comunicados por correo a los grupos de usuarios que yo elija, para difundir información oficial.
- Criterios de aceptación:
  1. Dado un comunicado institucional, cuando lo envío, entonces puedo elegir el grupo de usuarios destinatario (por ejemplo, todos los delegados de una categoría).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-064 (infraestructura de correo).
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-067 · Registrar fecha, destinatarios y estado de cada envío de correo
- Épica: EP-08
- Requisitos ERS: RF-15.4, RNF-12
- Historia: Como Administrador General, quiero que el sistema registre la fecha, los destinatarios y el estado de cada envío de correo, para dar seguimiento a los avisos enviados.
- Criterios de aceptación:
  1. Dado cualquier correo enviado por el sistema (avisos, recordatorios, comunicados, recuperación de contraseña), cuando se envía, entonces se registra fecha, destinatarios y estado (enviado/fallido).
- INVEST: Independiente [x] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-064.
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-068 · Exportar programación, tablas y boletín de castigados en PDF e imagen
- Épica: EP-08
- Requisitos ERS: RF-16.1, RNF-11
- Historia: Como Secretario (Consejo Directivo), quiero exportar la programación, las tablas de posiciones y el boletín de castigados en PDF y en imagen, para enviarlos a la prensa y a los portales locales.
- Criterios de aceptación:
  1. Dado la programación, la tabla o el boletín, cuando los exporto, entonces obtengo un archivo PDF y una imagen.
  2. Dado el PDF exportado, cuando se abre en un lector común, entonces se ve en tamaño carta legible sin necesidad de ajustes (RNF-11).
  3. Dado la imagen exportada, cuando se usa en redes sociales o portales locales, entonces tiene un ancho de 1080 px.
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-030 (programación), HU-041 (tabla) y HU-046 (boletín).
- Riesgos y supuestos: ninguno adicional.
- Notas / [POR DEFINIR]: ninguna.

### HU-069 · Generar cédulas oficiales, estados de cuenta y constancias en PDF
- Épica: EP-08
- Requisitos ERS: RF-16.2, IH-03
- Historia: Como Tesorero (Consejo Directivo), quiero generar en PDF las cédulas oficiales, los estados de cuenta por club y las constancias, para entregarlos impresos o por correo cuando se requieran.
- Criterios de aceptación:
  1. Dado un partido con cédula cerrada, cuando genero el PDF oficial, entonces incluye marcador, alineaciones e incidencias.
  2. Dado un club, cuando genero su estado de cuenta, entonces el PDF refleja sus cargos y pagos.
  3. Dado cualquiera de estos reportes, cuando se imprime con cualquier impresora configurada en el equipo del usuario, entonces se imprime correctamente (IH-03).
- INVEST: Independiente [ ] · Negociable [x] · Valiosa [x] · Estimable [x] · Pequeña [x] · Testeable [x]
- Dependencias: depende de HU-040 (cédula oficial) y HU-051 (estado de cuenta).
- Riesgos y supuestos: [AMBIGÜEDAD] AF-03 menciona "credenciales" de jugador, pero ningún inciso RF-03.x/RF-04.x detalla su emisión; el documento más cercano es esta constancia en PDF. Ver preguntas abiertas.
- Notas / [POR DEFINIR]: confirmar si la "credencial" de jugador es un documento distinto de la constancia/cédula aquí descrita.

## Preguntas abiertas

| ID | Pregunta | Requisito ERS | Para quién | Estado |
|---|---|---|---|---|
| PA-01 | El Anexo A asigna "Comité Ejecutivo" como actor de RF-11, pero ese rol no aparece en la tabla de usuarios de la sección 2.3. ¿Qué rol de 2.3 ejecuta y aprueba las fases finales (HU-055 a HU-061)? | RF-11, sección 2.3 | Equipo | Abierta |
| PA-02 | ¿Qué rol valida oficialmente un resultado (RF-08.4) y quién, además del capturista, puede corregirlo (RF-08.3)? El Anexo B (preguntas 4 y 5) solo da un supuesto de trabajo, no un rol de 2.3. | RF-08.3, RF-08.4 | Equipo / Liga | Abierta |
| PA-03 | AF-08 marca "inconformidades y recursos" como Could, pero RF-10 completo (que los incluye en RF-10.5) tiene prioridad Alta/Esencial. ¿Cuál prevalece para HU-048 y HU-049? | RF-10.5, AF-08 | Equipo | Abierta |
| PA-04 | El calendario aprobado es de 9 semanas, pero RNF-03 y la sección 2.2 de la ERS hablan de 10 semanas. ¿Se ajusta el texto de RNF-03/2.2 o se solicita una semana adicional? | RNF-03, sección 2.2 | Equipo | Abierta |
| PA-05 | Con solo 4 RF Media/Deseado (RF-07, RF-13, RF-15, RF-16), el umbral "≥ 80 % con prueba aprobada" de RNF-03 matemáticamente exige completar los 4 (3/4 = 75 % < 80 %). ¿Se asume ese compromiso como Must de facto, o se ajusta el umbral de RNF-03? | RNF-03 | Equipo | Abierta |
| PA-06 | `PLANIFICACIÓN SCRUM.pdf` usa "endpoints tipo API" (Sprint 5) y "API interna" (Sprint 9). ¿Son rutas internas de Laravel consumidas por las vistas Blade (permitido), o una API REST publicada (prohibido por IC-02 y D-003)? | IC-02 | Equipo | Abierta |
| PA-07 | `PLANIFICACIÓN SCRUM.pdf` (Sprint 9, texto que aparece tachado en el PDF fuente) propone VPS, dominio oficial y migrar a PostgreSQL. La ERS fija MariaDB (2.4) y deja el hospedaje de producción por definir (1.2). ¿Se descarta esa migración o se actualiza la ERS? | sección 1.2, sección 2.4 | Equipo | Abierta |
| PA-08 | El esquema de datos de la planificación incluye una tabla `ligas` (estructura multi-liga), pero D-002 fija una sola liga para esta versión. ¿Se simplifica el esquema o se documenta como preparación a futuro sin exponerlo como funcionalidad del backlog? | sección 2.4 (restricción de una sola liga) | Equipo | Abierta |
| PA-09 | ¿Existe formalmente la Comisión Arbitral con padrón de árbitros y control de disponibilidad (HU-026), o se sigue gestionando fuera del sistema? La ERS no tiene un inciso RF explícito para administrar esa disponibilidad; el glosario dice que su existencia formal "está por validar". | RF-03.2, RF-06.1 | Liga | Abierta |
| PA-10 | ¿Quién define el estilo visual del sistema (colores, logotipo, tipografía) si la Liga no lo ha especificado, y en qué sprint se valida con ella (IU-07)? | IU-07 | Liga | Abierta |
| PA-11 | AF-03 menciona "credenciales" de jugador, distintas del Tarjetón Digital de afiliación del club (ya cubierto por HU-070). Ningún inciso RF-03.x o RF-04.x detalla la emisión de la credencial de jugador; lo más cercano es RF-16.2 (cédulas/constancias en PDF, HU-069). ¿La credencial del jugador es un documento distinto que falta especificar? | RF-03, AF-03, RF-16.2 | Equipo / Liga | Abierta |
| PA-12 | RF-04.4 (elegibilidad por "cuota cubierta", HU-019) depende de RF-13 (Tesorería, HU-050), agendada en S8, mientras RF-04 completo está en S3. ¿Se acepta una validación parcial de elegibilidad hasta S8, o se reordenan los sprints? | RF-04.4, RF-13 | Equipo | Abierta |
| PA-13 | RF-07.2 (motor automático, S5) y RF-06.1 (programación manual, S4) usan la restricción "sin equipos con sanción vigente", pero esa restricción se construye en RF-10 (S7). ¿Se difiere la restricción hasta S7 o se adelanta una versión mínima de RF-10.4 (HU-047)? | RF-07.2, RF-10.4 | Equipo | Abierta |
| PA-14 | Los 12 puntos del Anexo B siguen sin validar con la Liga; varias historias (HU-026, HU-039, HU-040, HU-047, HU-048, HU-049, HU-058) dependen directamente de esas respuestas. ¿Cuándo se programa esa validación con la Liga? | Anexo B | Liga | Abierta |
| PA-15 | RF-12.3 menciona "comunicados" entre lo que muestra el portal público (HU-078), pero el backlog solo tiene una historia de envío de comunicados por correo (RF-15.3, HU-066). ¿Los comunicados del portal son los mismos que se envían por correo, o el portal necesita un módulo de publicación propio? | RF-12.3, RF-15.3 | Equipo | Abierta |
