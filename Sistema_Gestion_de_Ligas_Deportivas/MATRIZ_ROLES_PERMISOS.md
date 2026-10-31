# Matriz Módulo / Acción de los 13 Roles del Sistema

Documento técnico de especificación de Control de Acceso Basado en Roles (RBAC) para el **Sistema de Gestión de Ligas Deportivas (SGLD)** de la Liga Municipal de Fútbol Amateur de Morelia, A.C.

- **Incidencias Jira asociadas:** `SCRUM-14` (`HU-071`), `SCRUM-15` (`HU-072`), `SCRUM-16` (`HU-073`), `SCRUM-120` (`S2-T06`), `SCRUM-123` (`S2-T09`), `SCRUM-130` (`S2-T16`).
- **Base normativa:** Especificación de Requisitos de Software (ERS), Sección 2.3 (Características y Responsabilidades de los Usuarios) y Requisito Funcional `RF-12.1`.
- **Estado de aprobación:** Aprobado por el Product Owner (@Medina281) para Roles de Gobernanza (HU-071) y Roles Operativos Deportivos (HU-072 / SCRUM-15).
- **Responsable de arquitectura:** Rafael Piedra Sánchez (Dev 2 — Backend Lead).

---

## 1. Catálogo Oficial de los 13 Roles del Sistema

De acuerdo con la sección 2.3 de la ERS, los usuarios autenticados del sistema se agrupan en cuatro categorías funcionales que abarcan 13 roles operativos:

### Categoría A: Administración del Sistema
1. **Administrador General:** Máxima autoridad técnica de la plataforma. Responsable del soporte, configuración de infraestructura, administración de cuentas de usuario, asignación de roles y consulta de la bitácora global de auditoría.

### Categoría B: Consejo Directivo y Gobernanza (HU-071)
2. **Presidente (Consejo Directivo):** Máxima autoridad institucional de la Liga. Supervisa la estructura general de torneos, valida convocatorias oficiales, revisa estados de clubes y consulta auditoría de operaciones críticas.
3. **Secretario (Consejo Directivo):** Responsable de la fe pública y documentación de la Liga. Gestiona convocatorias oficiales, minutas de asambleas, supervisa el padrón de afiliados y valida la documentación etaria. No registra clubes ni equipos directamente (facultad asignada al Delegado del Club en Sprint 3).
4. **Tesorero (Consejo Directivo):** Responsable financiero de la Liga. Administra conceptos de cobro (inscripciones, fianzas, credenciales), valida comprobantes de pago, emite recibos oficiales y genera reportes financieros. No interviene en decisiones disciplinarias ni operativas de juego.
5. **Comisión de la Rama Infantil:** Órgano colegiado encargado de velar por las categorías menores. Supervisa categorías y grupos infantiles, valida la autenticidad de actas/CURP para control de edad máxima y autoriza credenciales infantiles.

### Categoría C: Operación Deportiva, Arbitraje y Disciplina (HU-072)
6. **Programador de Partidos:** Responsable de la logística semanal. Registra disponibilidad de campos, gestiona bloqueos de canchas por clima o mantenimiento, calendariza jornadas y programa fechas, horarios y sedes de partidos.
7. **Capturista:** Personal administrativo de oficina. Captura resultados, estadísticas y cédulas arbitrales físicas entregadas en la sede de la Liga. No tiene facultades sancionatorias ni de dictamen.
8. **Árbitro en Campo (Árbitro):** Oficial de juego en terreno. Valida alineaciones antes del silbatazo, registra goles, amonestaciones, expulsiones e incidentes en la cédula digital y recaba firmas de capitanes.
9. **Comisión Arbitral:** Órgano rector del arbitraje. Administra el padrón de colegios y árbitros, define tarifas por partido y designa ternas arbitrales para cada encuentro programado.
10. **Tribunal de Penas (Comisión Disciplinaria):** Órgano sancionador autónomo. Recibe cédulas con incidentes, abre expedientes disciplinarios, tipifica faltas conforme al reglamento y emite dictámenes y suspensiones oficiales.

### Categoría D: Clubes y Jugadores (HU-073)
11. **Presidente del Club (Directivo de Club):** Representante legal del club afiliado. Administra los datos institucionales de su club, da de alta equipos por categoría y gestiona el tarjetón digital del club.
12. **Delegado del Club:** Enlace operativo ante la Liga. Gestiona altas, renovaciones y transferencias de jugadores para sus equipos, sube documentos probatorios y presenta alineaciones de su club.
13. **Jugador:** Deportista individual afiliado. Consulta su perfil personal, vigencia de su credencial digital, historial de partidos jugados, goles anotados y estado de sanciones disciplinarias.

*(Nota: El rol de Consulta Pública / Público General contemplado en el Sprint 1 no forma parte de este catálogo formal de 13 roles de cuentas autenticadas, ya que opera sin cuenta o en modo de sólo lectura abierta para el portal público).*

---

## 2. Nomenclatura Estándar de Permisos Atómicos

Los permisos se estructuran bajo la convención estricta:
`modulo.accion`

Donde:
- `modulo` representa el componente funcional (ej. `temporadas`, `canchas`, `usuarios`, `sanciones`).
- `accion` define la operación permitida: `ver`, `crear`, `editar`, `eliminar`, `activar`, `bloquear`, `asignar`, `firmar`, `validar`, `dictaminar`, `pagar`.

---

## 3. Matriz General Módulo / Acción para los 13 Roles

> **Guía de Estado:**
> - **Sí:** Permiso activo y sembrado en el ejecutable para este rol en el Sprint 2 (S2-T10).
> - **No:** Permiso denegado expresamente para este rol.
> - **Pendiente (Sprint 3 / HU-xxx):** Permiso normativo de la ERS cuyo módulo o política será implementado en historias futuras; no se siembra en Sprint 2 para preservar el principio de mínimo privilegio.
> - **Asterisco (`*`):** Permiso acotado por pertinencia (ej. propiedad de club en ClubPolicy o rama infantil).

| Módulo del Sistema | Permiso Atómico | Admin General | Presidente | Secretario | Tesorero | Com. Infantil | Programador | Capturista | Árbitro Campo | Com. Arbitral | Tribunal Penas | Pte. Club | Delegado Club | Jugador |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Gobernanza General** | `gobernanza.panel` | Sí | Sí | Sí | Sí | Sí | No | No | No | No | No | No | No | No |
| **Gestión de Ligas** | `ligas.ver` | Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `ligas.crear` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `ligas.editar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `ligas.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Usuarios y Cuentas** | `usuarios.ver` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.crear` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.editar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.asignar_rol` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Roles y Permisos** | `roles.ver` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `roles.crear` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `roles.editar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `roles.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `roles.asignar_permiso`| Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Auditoría Inmutable** | `auditoria.ver` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Temporadas** | `temporadas.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `temporadas.crear` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| | `temporadas.editar` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| | `temporadas.cerrar` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| | `temporadas.eliminar`| Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Convocatorias** | `convocatorias.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `convocatorias.crear`| Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `convocatorias.editar`| Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `convocatorias.eliminar`| Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Reglas de Temporada**| `reglas_temporada.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | No | No | No | Sí | Sí | No |
| | `reglas_temporada.editar`| Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| **Categorías y Grupos** | `categorias.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `categorias.crear` | Sí | Pendiente (HU-007) | Pendiente (HU-007) | No | Pendiente (HU-007) | No | No | No | No | No | No | No | No |
| | `categorias.editar` | Sí | Pendiente (HU-007) | Pendiente (HU-007) | No | Pendiente (HU-007) | No | No | No | No | No | No | No | No |
| | `categorias.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Clubes y Sedes** | `clubes.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | No | No | No | Sí* | Sí* | No |
| | `clubes.crear` | Sí | No | No | No | No | No | No | No | No | No | No | Pendiente (HU-012) | No |
| | `clubes.editar` | Sí | No | No | No | No | No | No | No | No | No | Pendiente (HU-012) | Pendiente (HU-012) | No |
| | `clubes.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Equipos** | `equipos.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí* | Sí* | Sí* |
| | `equipos.crear` | Sí | No | No | No | No | No | No | No | No | No | Pendiente (HU-013) | Pendiente (HU-013) | No |
| | `equipos.editar` | Sí | No | No | No | No | No | No | No | No | No | Pendiente (HU-013) | Pendiente (HU-013) | No |
| | `equipos.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Jugadores y Padrón** | `jugadores.ver` | Sí | No | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí* | Sí* | Sí* |
| | `jugadores.crear` | Sí | No | No | No | Pendiente (HU-009) | No | No | No | No | No | Pendiente (HU-009) | Pendiente (HU-009) | No |
| | `jugadores.editar` | Sí | No | No | No | Pendiente (HU-009) | No | No | No | No | No | Pendiente (HU-009) | Pendiente (HU-009) | No |
| | `jugadores.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `jugadores.transferir`| Sí | No | No | No | No | No | No | No | No | No | Pendiente (HU-009) | Pendiente (HU-009) | No |
| | `jugadores.validar_edad`| Sí | No | Sí | No | Sí* | No | No | No | No | No | No | No | No |
| **Canchas e Instalac.**| `campos.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `campos.crear` | Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| | `campos.editar` | Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| | `campos.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `campos.bloquear` | Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| **Arbitraje y Padrón** | `arbitros.ver` | Sí | No | No | No | No | Sí | No | Sí | Sí | Sí | No | No | No |
| | `arbitros.crear` | Sí | No | No | No | No | No | No | No | Sí | No | No | No | No |
| | `arbitros.editar` | Sí | No | No | No | No | No | No | No | Sí | No | No | No | No |
| | `arbitros.asignar` | Sí | No | No | No | No | Sí | No | No | Sí | No | No | No | No |
| **Jornadas y Partidos** | `partidos.ver` | Sí | No | No | No | No | Sí | Sí | Sí | Sí | Sí | No | No | No |
| | `partidos.programar` | Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| | `partidos.reprogramar`| Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| **Cédulas y Resultados**| `cedulas.ver` | Sí | No | No | No | No | Sí | Sí | Sí | Sí | Sí | No | No | No |
| | `cedulas.capturar` | Sí | No | No | No | No | No | Sí | Sí | No | No | No | No | No |
| | `cedulas.firmar` | No | No | No | No | No | No | No | Sí | No | No | No | No | No |
| | `resultados.validar`| Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| **Régimen Disciplin.** | `disciplina.ver` | Sí | No | No | No | No | No | No | Sí | No | Sí | No | No | No |
| | `disciplina.expedientes`| Sí | No | No | No | No | No | No | No | No | Sí | No | No | No |
| | `disciplina.sancionar`| Sí | No | No | No | No | No | No | No | No | Sí | No | No | No |
| **Finanzas y Pagos** | `finanzas.ver` | Sí | No | No | Sí | No | No | No | No | No | No | Pendiente (HU-018) | Pendiente (HU-018) | No |
| | `finanzas.cargos` | Sí | No | No | Sí | No | No | No | No | No | No | No | No | No |
| | `finanzas.pagos_validar`| Sí | No | No | Sí | No | No | No | No | No | No | No | No | No |
| | `finanzas.reportes` | Sí | Sí | No | Sí | No | No | No | No | No | No | No | No | No |

---

## 4. Fronteras Inquebrantables de Seguridad (Reglas HTTP 403 Forbidden)

En cumplimiento de los criterios de aceptación de `HU-071`, `HU-072`, `HU-073` y los requisitos de calidad `RNF-25`, `RNF-29` y `RNF-32`:

1. **Gestión Exclusiva de Cuentas (Criterio 4 de HU-071):**
   - La creación, modificación, desactivación y asignación de roles a usuarios (`usuarios.*` y `roles.*`) es potestad exclusiva del **Administrador General**.
   - Si Presidente, Secretario, Tesorero, Comisión Infantil o cualquier rol operativo intentan abrir `/admin/usuarios` o ejecutar acciones sobre cuentas, el sistema responde **HTTP 403 Forbidden**.

2. **Aislamiento Disciplinario y Operativo del Tesorero (Criterio 2 de HU-071):**
   - El Tesorero tiene facultades limitadas estrictamente al aspecto económico (`finanzas.*`).
   - Se le deniega expresamente la consulta de clubes, equipos, campos, expedientes disciplinarios (`disciplina.*`), partidos y temporadas. Si intenta ejecutar cualquier acción fuera de tesorería, el sistema responde **HTTP 403 Forbidden**.

3. **Autonomía del Tribunal de Penas:**
   - La tipificación de sanciones y resolución de recursos de inconformidad corresponde exclusivamente al **Tribunal de Penas**.
   - Ningún capturista, árbitro ni directivo de club puede dictaminar sanciones; cualquier intento responde con **HTTP 403 Forbidden**.

4. **Prevención de Acceso Cruzado entre Clubes (ID Tampering - RNF-29):**
   - Los roles `Presidente del Club` y `Delegado del Club` tienen su alcance acotado al valor `club_id` asignado a su cuenta.
   - Si intentan leer o modificar datos de otro club manipulando parámetros en la URL o cuerpo de petición, el sistema emite **HTTP 403 Forbidden en el 100% de los casos**.

5. **Alcance Institucional del Secretario vs. Delegado de Club (Acuerdo Product Owner):**
   - El Secretario gestiona fe pública, minutas, convocatorias oficiales y supervisión documental/etaria.
   - La inscripción de clubes (`clubes.crear`, `clubes.editar`) y registro de equipos (`equipos.crear`, `equipos.editar`) queda expresamente denegada al Secretario y asignada al Delegado del Club (`HU-012` y `HU-013` en Sprint 3).
