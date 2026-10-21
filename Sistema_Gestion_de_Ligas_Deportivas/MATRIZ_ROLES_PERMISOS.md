# Matriz Módulo / Acción de los 13 Roles del Sistema

Documento técnico de especificación de Control de Acceso Basado en Roles (RBAC) para el **Sistema de Gestión de Ligas Deportivas (SGLD)** de la Liga Municipal de Fútbol Amateur de Morelia, A.C.

- **Incidencias Jira asociadas:** `SCRUM-14` (`HU-071`), `SCRUM-15` (`HU-072`), `SCRUM-16` (`HU-073`), `SCRUM-120` (`S2-T06`), `SCRUM-123` (`S2-T09`).
- **Base normativa:** Especificación de Requisitos de Software (ERS), Sección 2.3 (Características y Responsabilidades de los Usuarios) y Requisito Funcional `RF-12.1`.
- **Acuerdo técnico:** PA-17 (Aprobación por Product Owner José Luis Medina Ramírez).
- **Responsable de arquitectura:** Rafael Piedra Sánchez (Dev 2 — Backend Lead).

---

## 1. Catálogo Oficial de los 13 Roles del Sistema

De acuerdo con la sección 2.3 de la ERS, los usuarios autenticados del sistema se agrupan en cuatro categorías funcionales que abarcan 13 roles operativos:

### Categoría A: Administración del Sistema
1. **Administrador General:** Máxima autoridad técnica de la plataforma. Responsable del soporte, configuración de infraestructura, administración de cuentas de usuario, asignación de roles y consulta de la bitácora global de auditoría.

### Categoría B: Consejo Directivo y Gobernanza (HU-071)
2. **Presidente (Consejo Directivo):** Máxima autoridad institucional de la Liga. Supervisa la estructura general de torneos, valida convocatorias oficiales, revisa estados de clubes y consulta auditoría de operaciones críticas.
3. **Secretario (Consejo Directivo):** Responsable de la fe pública y documentación de la Liga. Gestiona convocatorias oficiales, minutas de asambleas, altas institucionales de clubes y supervisa el padrón de afiliados.
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

| Módulo del Sistema | Permiso Atómico | Admin General | Presidente | Secretario | Tesorero | Com. Infantil | Programador | Capturista | Árbitro Campo | Com. Arbitral | Tribunal Penas | Pte. Club | Delegado Club | Jugador |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Gobernanza General** | `gobernanza.panel` | Sí | Sí | Sí | Sí | Sí | No | No | No | No | No | No | No | No |
| **Usuarios y Cuentas** | `usuarios.ver` | Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.crear` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.editar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.eliminar` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `usuarios.asignar_rol` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Roles y Permisos** | `roles.ver` | Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| | `roles.asignar_permiso`| Sí | No | No | No | No | No | No | No | No | No | No | No | No |
| **Auditoría Inmutable** | `auditoria.ver` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| **Temporadas** | `temporadas.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `temporadas.crear` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| | `temporadas.editar` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| | `temporadas.cerrar` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| **Convocatorias** | `convocatorias.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `convocatorias.crear`| Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `convocatorias.editar`| Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| **Reglas de Temporada**| `reglas.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | No | No | No | Sí | Sí | No |
| | `reglas.configurar` | Sí | Sí | No | No | No | No | No | No | No | No | No | No | No |
| **Categorías y Grupos** | `categorias.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `categorias.crear` | Sí | Sí | Sí | No | Sí* | No | No | No | No | No | No | No | No |
| | `categorias.editar` | Sí | Sí | Sí | No | Sí* | No | No | No | No | No | No | No | No |
| **Clubes y Sedes** | `clubes.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | No | No | No | Sí* | Sí* | No |
| | `clubes.crear` | Sí | Sí | Sí | No | No | No | No | No | No | No | No | No | No |
| | `clubes.editar` | Sí | Sí | Sí | No | No | No | No | No | No | No | Sí* | No | No |
| **Equipos** | `equipos.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí* | Sí* | Sí* |
| | `equipos.crear` | Sí | No | Sí | No | No | No | No | No | No | No | Sí* | Sí* | No |
| | `equipos.editar` | Sí | No | Sí | No | No | No | No | No | No | No | Sí* | Sí* | No |
| **Jugadores y Padrón** | `jugadores.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí* | Sí* | Sí* |
| | `jugadores.crear` | Sí | No | Sí | No | Sí* | No | No | No | No | No | Sí* | Sí* | No |
| | `jugadores.editar` | Sí | No | Sí | No | Sí* | No | No | No | No | No | Sí* | Sí* | No |
| | `jugadores.transferir`| Sí | No | Sí | No | No | No | No | No | No | No | Sí* | Sí* | No |
| | `jugadores.validar_edad`| Sí | No | Sí | No | Sí* | No | No | No | No | No | No | No | No |
| **Canchas e Instalac.**| `campos.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | No | Sí | Sí | Sí |
| | `campos.crear` | Sí | Sí | No | No | No | Sí | No | No | No | No | No | No | No |
| | `campos.editar` | Sí | Sí | No | No | No | Sí | No | No | No | No | No | No | No |
| | `campos.bloquear` | Sí | Sí | No | No | No | Sí | No | No | No | No | No | No | No |
| **Arbitraje y Padrón** | `arbitros.ver` | Sí | Sí | Sí | No | No | Sí | No | Sí | Sí | Sí | No | No | No |
| | `arbitros.crear` | Sí | No | Sí | No | No | No | No | No | Sí | No | No | No | No |
| | `arbitros.editar` | Sí | No | Sí | No | No | No | No | No | Sí | No | No | No | No |
| | `arbitros.asignar` | Sí | No | No | No | No | Sí | No | No | Sí | No | No | No | No |
| **Jornadas y Partidos** | `partidos.ver` | Sí | Sí | Sí | No | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí | Sí |
| | `partidos.programar` | Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| | `partidos.reprogramar`| Sí | No | No | No | No | Sí | No | No | No | No | No | No | No |
| **Cédulas y Resultados**| `cedulas.ver` | Sí | Sí | Sí | No | No | Sí | Sí | Sí* | No | Sí | Sí* | Sí* | Sí* |
| | `cedulas.capturar` | Sí | No | No | No | No | No | Sí | Sí* | No | No | No | No | No |
| | `cedulas.firmar` | No | No | No | No | No | No | No | Sí | No | No | No | Sí* | No |
| | `resultados.validar`| Sí | Sí | Sí | No | No | Sí | No | No | No | No | No | No | No |
| **Régimen Disciplin.** | `disciplina.ver` | Sí | Sí | Sí | No | Sí* | No | No | Sí | No | Sí | Sí* | Sí* | Sí* |
| | `disciplina.expedientes`| Sí | No | No | No | No | No | No | No | No | Sí | No | No | No |
| | `disciplina.sancionar`| Sí | No | No | No | No | No | No | No | No | Sí | No | No | No |
| **Finanzas y Pagos** | `finanzas.ver` | Sí | Sí | No | Sí | No | No | No | No | No | No | Sí* | Sí* | No |
| | `finanzas.cargos` | Sí | No | No | Sí | No | No | No | No | No | No | No | No | No |
| | `finanzas.pagos_validar`| Sí | No | No | Sí | No | No | No | No | No | No | No | No | No |
| | `finanzas.reportes` | Sí | Sí | No | Sí | No | No | No | No | No | No | No | No | No |

*Nota sobre permisos con asterisco (`*`): El acceso está acotado por políticas de pertinencia (ej. ClubPolicy restringe las operaciones estrictamente al propio club del usuario; la Comisión Infantil opera únicamente sobre la rama infantil).*

---

## 4. Fronteras Inquebrantables de Seguridad (Reglas HTTP 403 Forbidden)

En cumplimiento de los criterios de aceptación de `HU-071`, `HU-072`, `HU-073` y los requisitos de calidad `RNF-25`, `RNF-29` y `RNF-32`:

1. **Gestión Exclusiva de Cuentas (Criterio 4 de HU-071):**
   - La creación, modificación, desactivación y asignación de roles a usuarios (`usuarios.*` y `roles.*`) es potestad exclusiva del **Administrador General**.
   - Si Presidente, Secretario, Tesorero, Comisión Infantil o cualquier rol operativo intentan abrir `/admin/usuarios` o ejecutar acciones sobre cuentas, el sistema responde **HTTP 403 Forbidden**.

2. **Aislamiento Disciplinario del Tesorero (Criterio 2 de HU-071):**
   - El Tesorero tiene facultades limitadas estrictamente al aspecto económico (`finanzas.*`).
   - Si el Tesorero intenta consultar o alterar expedientes disciplinarios (`disciplina.*`), programar partidos o modificar temporadas, el sistema rechaza la solicitud de inmediato con **HTTP 403 Forbidden**.

3. **Autonomía del Tribunal de Penas:**
   - La tipificación de sanciones y resolución de recursos de inconformidad corresponde exclusivamente al **Tribunal de Penas**.
   - Ningún capturista, árbitro ni directivo de club puede dictaminar sanciones; cualquier intento responde con **HTTP 403 Forbidden**.

4. **Prevención de Acceso Cruzado entre Clubes (ID Tampering - RNF-29):**
   - Los roles `Presidente del Club` y `Delegado del Club` tienen su alcance acotado al valor `club_id` asignado a su cuenta.
   - Si intentan leer o modificar datos de otro club manipulando parámetros en la URL o cuerpo de petición, el sistema emite **HTTP 403 Forbidden en el 100% de los casos**.
