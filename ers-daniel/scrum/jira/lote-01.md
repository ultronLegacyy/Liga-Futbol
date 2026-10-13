# Lote 01 de Jira: épicas e historias (vista previa)

- Estado: **pendiente de autorización explícita del equipo**. No se ha escrito nada en Jira.
- Destino: sitio `ligafutbol.atlassian.net`, proyecto `SCRUM` («LIGA FUTBOL»).
- Operaciones: 8 épicas + 79 historias = **87 incidencias nuevas** (`createJiraIssue`). Ninguna edición ni borrado.
- No incluye: sprints, asignaciones, story points, subtareas ni cambios de estado. Todo eso va en el lote 02, después de confirmar capacidad, Dev 3/Dev 4 y la cuenta de Carlos Daniel, y de crear los sprints en la interfaz.
- Datos exactos de cada operación: `scrum/jira/lote-01.json`.

## Reglas de ejecución

1. Antes de crear, buscar duplicados con JQL `project = SCRUM AND labels = "<ID local>"`. Si ya existe, no se crea y se anota su clave.
2. Crear primero las 8 épicas y anotar sus claves. Después, las historias con `parent` = clave de su épica.
3. Prioridad Jira: Must → High, Should → Medium, Could → Low.
4. Etiquetas: ID local (`EP-NN` o `HU-NNN`), épica, `MoSCoW-<prioridad>` y los RF de nivel superior citados (`RF-NN`). `prioridad-por-confirmar` en HU-048 y HU-049 (PA-03).
   El sprint sugerido de la tabla sale de `scrum/sprints/roadmap.md` y es provisional; no se escribe en Jira.
5. Descripción en Markdown: historia, requisitos ERS completos, criterios de aceptación, prioridad, dependencias y riesgos. El sprint no va en la descripción: se asigna con el campo Sprint en el lote 02.
6. Verificación: al terminar, JQL `project = SCRUM AND labels in (EP-01 … HU-079)` debe devolver 87 incidencias, y cada historia debe tener su épica padre. Las claves se anotan en `docs/trazabilidad.md`.
7. SCRUM-3 y SCRUM-4 no se tocan (V-06).

## Épicas (8)

| # | Resumen | Prioridad | Etiquetas |
|---|---|---|---|
| 1 | EP-01 · Core, Seguridad y Gobernanza | High | EP-01, MoSCoW-Must |
| 2 | EP-02 · Afiliación y Control de Padrón | High | EP-02, MoSCoW-Must |
| 3 | EP-03 · Logística y Calendarización | High | EP-03, MoSCoW-Must |
| 4 | EP-04 · Operación de Juego y Resultados | High | EP-04, MoSCoW-Must |
| 5 | EP-05 · Régimen Disciplinario | High | EP-05, MoSCoW-Must |
| 6 | EP-06 · Finanzas y Administración | Medium | EP-06, MoSCoW-Should |
| 7 | EP-07 · Fases Finales | Low | EP-07, MoSCoW-Could |
| 8 | EP-08 · Difusión y Portal Público | High | EP-08, MoSCoW-Must |

## Historias (79)

| # | Resumen | Épica padre | Prioridad | Sprint sugerido | Etiquetas RF |
|---|---|---|---|---|---|
| 1 | HU-001 · Iniciar sesión segura y administrar el control de acceso por roles (RBAC) | EP-01 | High | S1 | RF-12 |
| 2 | HU-071 · Habilitar el acceso de los roles de gobernanza | EP-01 | High | sin sprint | RF-12 |
| 3 | HU-072 · Habilitar el acceso de los roles operativos deportivos | EP-01 | High | sin sprint | RF-12 |
| 4 | HU-073 · Habilitar el acceso de los roles de clubes y jugadores | EP-01 | High | sin sprint | RF-12 |
| 5 | HU-079 · Configurar el envío de correo autenticado (SMTP) | EP-01 | High | sin sprint | — |
| 6 | HU-002 · Proteger cuentas con segundo factor y bloqueo por fuerza bruta | EP-01 | High | S1 | — |
| 7 | HU-003 · Recuperar el acceso mediante correo electrónico | EP-01 | High | S1 | — |
| 8 | HU-004 · Registrar auditoría inmutable de operaciones críticas | EP-01 | High | S1 | RF-12 |
| 9 | HU-005 · Crear y administrar temporadas con convocatoria versionada | EP-01 | High | S1 | RF-01 |
| 10 | HU-006 · Configurar las reglas de la temporada (cuotas, fechas, clasificación, desempates, ascenso/descenso) | EP-01 | High | S1 | RF-01 |
| 11 | HU-007 · Crear categorías y grupos por temporada | EP-01 | High | S1 | RF-02 |
| 12 | HU-008 · Configurar el formato de competencia y los criterios de clasificación | EP-01 | High | S1 | RF-02 |
| 13 | HU-011 · Registrar un club y su directiva | EP-02 | High | S2 | RF-03 |
| 14 | HU-012 · Dar de alta equipos por categoría, grupo y temporada | EP-02 | High | S2 | RF-03 |
| 15 | HU-070 · Registrar el Tarjetón Digital de afiliación del club | EP-02 | High | S2 | RF-03 |
| 16 | HU-013 · Gestionar la identidad única y reutilizable de las personas | EP-02 | High | S2 | RF-03 |
| 17 | HU-014 · Validar y alertar duplicados de personas y equipos | EP-02 | High | S2 | RF-03 |
| 18 | HU-015 · Consultar el historial de equipos y personas por temporada | EP-02 | High | S2 | RF-03 |
| 19 | HU-016 · Registrar un jugador con documentos de identidad y fotografía | EP-02 | High | S3 | RF-04 |
| 20 | HU-017 · Gestionar altas, bajas, renovaciones y transferencias de jugadores | EP-02 | High | S3 | RF-04 |
| 21 | HU-018 · Gestionar los estados del jugador | EP-02 | High | S3 | RF-04 |
| 22 | HU-019 · Validar la elegibilidad del jugador antes de participar | EP-02 | High | S3 | RF-04 |
| 23 | HU-020 · Mostrar el aviso de privacidad y recabar el consentimiento del tutor | EP-02 | High | S3 | — |
| 24 | HU-021 · Validar la edad de jugadores de la rama infantil contra su CURP | EP-02 | High | S3 | RF-04 |
| 25 | HU-022 · Proteger los datos personales y el historial de cambios del jugador | EP-02 | High | S3 | RF-04 |
| 26 | HU-023 · Registrar canchas y sedes con su capacidad | EP-03 | High | S4 | RF-05 |
| 27 | HU-024 · Gestionar la disponibilidad y los bloqueos de canchas | EP-03 | High | S4 | RF-05 |
| 28 | HU-025 · Impedir asignar una cancha bloqueada u ocupada | EP-03 | High | S4 | RF-05 |
| 29 | HU-026 · Administrar el padrón de árbitros y asignarlos a los partidos | EP-03 | High | S4 | RF-03, RF-06 |
| 30 | HU-027 · Crear jornadas y partidos con asignación manual | EP-03 | High | S4 | RF-06 |
| 31 | HU-028 · Registrar los equipos que descansan en cada jornada | EP-03 | High | S4 | RF-06 |
| 32 | HU-029 · Gestionar los estados y la reprogramación de partidos | EP-03 | High | S4 | RF-06 |
| 33 | HU-030 · Publicar la programación semanal tras su revisión y aprobación | EP-03 | High | S4 | RF-06 |
| 34 | HU-031 · Conservar el historial de partidos con resultados | EP-03 | High | S4 | RF-06 |
| 35 | HU-036 · Capturar alineación y eventos del partido desde un dispositivo móvil | EP-04 | High | S6 | RF-08 |
| 36 | HU-075 · Recabar las firmas digitales de los capitanes y cerrar la cédula | EP-04 | High | sin sprint | RF-08 |
| 37 | HU-037 · Capturar un resultado desde una cédula física como capturista | EP-04 | High | S6 | RF-08 |
| 38 | HU-038 · Identificar automáticamente el partido, los equipos y las personas al capturar | EP-04 | High | S6 | RF-08 |
| 39 | HU-039 · Corregir un resultado capturado con auditoría y recálculo | EP-04 | High | S6 | RF-08 |
| 40 | HU-040 · Validar oficialmente un resultado antes de publicarlo | EP-04 | High | S6 | RF-08 |
| 41 | HU-041 · Calcular automáticamente la tabla de posiciones | EP-04 | High | S7 | RF-09 |
| 42 | HU-042 · Aplicar criterios de desempate configurables | EP-04 | High | S7 | RF-09 |
| 43 | HU-043 · Generar estadísticas de goleo, tarjetas y sanciones por jornada | EP-04 | High | S7 | RF-09 |
| 44 | HU-044 · Generar un expediente disciplinario desde las incidencias de la cédula | EP-05 | High | S7 | RF-10 |
| 45 | HU-045 · Registrar audiencia, resolución y sanción | EP-05 | High | S7 | RF-10 |
| 46 | HU-046 · Calcular la vigencia de la sanción y mantener el boletín de castigados | EP-05 | High | S7 | RF-10 |
| 47 | HU-047 · Bloquear la participación o la programación por sanción vigente | EP-05 | High | S7 | RF-10 |
| 48 | HU-048 · Registrar una inconformidad ante la Comisión Disciplinaria | EP-05 | High | sin sprint | RF-10 |
| 49 | HU-049 · Resolver un recurso de segunda instancia | EP-05 | High | sin sprint | RF-10 |
| 50 | HU-032 · Generar automáticamente los enfrentamientos de una jornada | EP-03 | Medium | S5 | RF-07 |
| 51 | HU-074 · Asignar cancha y horario a los enfrentamientos generados respetando restricciones | EP-03 | Medium | sin sprint | RF-07 |
| 52 | HU-033 · Detectar y explicar los conflictos de la programación generada | EP-03 | Medium | S5 | RF-07 |
| 53 | HU-034 · Ajustar manualmente la programación generada automáticamente | EP-03 | Medium | S5 | RF-07 |
| 54 | HU-035 · Validar la usabilidad de la programación semanal | EP-03 | Medium | S5 | — |
| 55 | HU-050 · Registrar cuotas y pagos de inscripción, arbitraje y canchas | EP-06 | Medium | S8 | RF-13 |
| 56 | HU-051 · Controlar adeudos y comprobantes de pago | EP-06 | Medium | S8 | RF-13 |
| 57 | HU-052 · Generar reportes financieros por temporada, club y concepto | EP-06 | Medium | S8 | RF-13 |
| 58 | HU-062 · Consultar la programación pública del portal sin necesidad de cuenta | EP-08 | High | S9 | RF-12 |
| 59 | HU-077 · Consultar resultados y tablas de posiciones en el portal público | EP-08 | High | sin sprint | RF-12 |
| 60 | HU-078 · Consultar el boletín de castigados y los comunicados en el portal público | EP-08 | High | sin sprint | RF-12 |
| 61 | HU-063 · Buscar información del portal por fecha, categoría y equipo | EP-08 | High | S9 | RF-12 |
| 62 | HU-064 · Enviar avisos de programación y cambios de sede u horario por correo | EP-08 | Medium | S9 | RF-15 |
| 63 | HU-065 · Enviar recordatorios de pago a clubes con adeudos | EP-08 | Medium | S9 | RF-15 |
| 64 | HU-066 · Enviar avisos institucionales y comunicados a grupos de usuarios | EP-08 | Medium | S9 | RF-15 |
| 65 | HU-067 · Registrar fecha, destinatarios y estado de cada envío de correo | EP-08 | Medium | S9 | RF-15 |
| 66 | HU-068 · Exportar programación, tablas y boletín de castigados en PDF e imagen | EP-08 | Medium | S9 | RF-16 |
| 67 | HU-069 · Generar cédulas oficiales, estados de cuenta y constancias en PDF | EP-08 | Medium | S9 | RF-16 |
| 68 | HU-009 · Respaldar y recuperar la información del sistema | EP-01 | High | S9 | — |
| 69 | HU-010 · Instalar el sistema en un equipo nuevo con manual técnico | EP-01 | High | S9 | — |
| 70 | HU-076 · Redactar y validar los manuales de usuario por rol | EP-01 | Medium | sin sprint | — |
| 71 | HU-053 · Registrar convocatoria y asistencia a asamblea de delegados | EP-06 | Low | S8 | RF-14 |
| 72 | HU-054 · Registrar acuerdos y comunicados de asamblea | EP-06 | Low | S8 | RF-14 |
| 73 | HU-055 · Clasificar equipos a la liguilla y generar las llaves | EP-07 | Low | S8 | RF-11 |
| 74 | HU-056 · Registrar avances, eliminaciones y determinar campeón y subcampeón | EP-07 | Low | S8 | RF-11 |
| 75 | HU-057 · Generar propuestas de ascenso y descenso versionadas | EP-07 | Low | S8 | RF-11 |
| 76 | HU-058 · Validar manualmente reglas de ascenso/descenso no automáticas | EP-07 | Low | S8 | RF-11 |
| 77 | HU-059 · Emitir el informe final y cerrar la temporada | EP-07 | Low | S8 | RF-11 |
| 78 | HU-060 · Gestionar liguilla de consolación, Campeón de Campeones y campeón de goleo | EP-07 | Low | S8 | RF-11 |
| 79 | HU-061 · Registrar el Torneo de Copa y torneos especiales | EP-07 | Low | S8 | RF-11 |

## Ejemplo de descripción (HU-001)

```markdown
**Historia:** Como Administrador General, quiero iniciar sesión de forma segura y administrar la infraestructura de control de acceso por roles (RBAC), para que cada usuario vea solo los módulos y acciones permitidos por su rol.

**Requisitos ERS:** RF-12.1, RNF-25, RNF-29, RNF-32, RNF-35, IU-02, IC-01

**Criterios de aceptación**
1. Dado el Administrador General, cuando inicia sesión con credenciales correctas, entonces accede a todos los módulos y a la administración de roles y permisos.
2. Dado un usuario autenticado de cualquier rol, cuando intenta acceder a una ruta de un módulo que no le corresponde, entonces el motor de permisos responde 403 y no expone la pantalla (válido para todos los roles, incluidos los que prueban HU-071 a HU-073).
3. Dado el Administrador General u otro rol con privilegios, cuando pasan 30 minutos de inactividad, entonces su sesión expira; los demás roles expiran a las 8 horas (RNF-35).
4. Dado cualquier envío de credenciales, cuando viaja entre navegador y servidor, entonces se transmite por HTTPS/TLS sin exponer datos en texto plano (IC-01).
5. Dado un intento de acceso cruzado (cambiar un identificador en la URL para ver datos de otro usuario), cuando el usuario no tiene permiso, entonces el sistema responde 403 en el 100 % de los casos (RNF-29).

**Prioridad MoSCoW:** Must

**Dependencias:** ninguna (historia fundacional). La usan HU-071, HU-072 y HU-073, que habilitan y prueban el acceso de los demás grupos de roles sobre esta misma infraestructura.

**Riesgos y supuestos:** historia dividida el 2026-10-12 por tamaño (cubría 14 roles); ahora construye el motor de permisos una sola vez para el Administrador General y lo reutilizan HU-071 a HU-073. RNF-32 (TLS/HSTS, SSL Labs A) depende del hospedaje, hoy en equipos propios (sección 1.2); solo se verifica totalmente en producción.

Fuente: `scrum/product-backlog.md` (HU-001) y `docs/trazabilidad.md`.
```
