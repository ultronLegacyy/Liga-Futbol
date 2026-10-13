# Estrategia de ramas y revisión de código (D-026)

- Estado: aprobada el 13/10/2026. El equipo (Daniel) delegó el diseño en el coordinador.
- Se configura en la subtarea S1-T01 (SCRUM-93) y rige desde el Sprint 1.
- Repositorio: `ultronLegacyy/Liga-Futbol` (privado). El código Laravel va en la raíz; la gestión Scrum sigue en `ers-daniel/`.

## La regla en cinco líneas

1. `main` guarda lo que se presentó en cada Sprint Review. Solo recibe PR desde `develop` (o un hotfix) y cada sprint lleva su etiqueta.
2. `develop` es la rama de integración diaria y la rama por defecto. Siempre debe funcionar.
3. Cada subtarea de Jira se trabaja en una rama corta que sale de `develop`: `tipo/SCRUM-NN-descripcion`. Dura uno o dos días.
4. Todo entra por pull request (PR) con la aprobación de otro integrante y la CI en verde. Nadie aprueba su propio PR.
5. Los PR hacia `develop` se fusionan con «Squash and merge»; el PR de `develop` a `main`, con «Create a merge commit».

## Por qué esta estrategia

Es un Git Flow simplificado, ajustado a sprints de 1 semana y a cuatro personas con 3.5 h diarias:
- `main` da una versión estable para la Review del viernes mientras `develop` sigue integrando.
- No hay ramas `release/`: el PR semanal de `develop` a `main` ya es la entrega del sprint.
- Una rama por subtarea produce PR pequeños: se revisan rápido y casi no generan conflictos. Además coincide con la asignación de Jira (cada subtarea tiene un responsable).
- El squash deja en `develop` un commit por subtarea, con su clave de Jira. El historial de `main` muestra un merge por sprint.

Se descartaron Git Flow completo (ramas `release/` innecesarias con entregas semanales) y trunk-based con solo `main` (no deja una versión estable para la Review mientras se integra).

## Ramas

| Rama | Sale de | Se fusiona en | Para qué | Duración |
|---|---|---|---|---|
| `main` | — | — | Incremento presentado en cada Review | Permanente |
| `develop` | `main` | `main`, al cierre del sprint | Integración diaria | Permanente |
| `feat/SCRUM-NN-…` | `develop` | `develop` | Funcionalidad nueva | 1–2 días |
| `fix/SCRUM-NN-…` | `develop` | `develop` | Corrección de un defecto encontrado en `develop` | Horas |
| `test/SCRUM-NN-…` | `develop` | `develop` | Pruebas | 1–2 días |
| `chore/SCRUM-NN-…` | `develop` | `develop` | Configuración, CI, dependencias, proyecto base | 1 día |
| `refactor/SCRUM-NN-…` | `develop` | `develop` | Reestructurar sin cambiar el comportamiento | 1 día |
| `docs/sprint-NN` | `develop` | `develop` | Gestión Scrum (`ers-daniel/`): backlog, sprint, trazabilidad | Un sprint (PR abierto el lunes, se fusiona el viernes) |
| `hotfix/vX.Y.Z-…` | `main` | `main` y después `develop` | Corrección urgente de lo ya entregado | Horas |

Nombres: en minúsculas, con guiones, sin acentos ni ñ. La clave de Jira va en mayúsculas para que Jira la reconozca. Se usa la clave de la **subtarea**; la historia se alcanza desde ella en Jira.

Ejemplos del Sprint 1:
- `chore/SCRUM-94-proyecto-base`
- `feat/SCRUM-98-fortify-sesiones`
- `test/SCRUM-105-auditoria-inmutable`
- `feat/SCRUM-109-vistas-temporadas`

Si dos subtareas de la misma persona no se pueden separar, van en una sola rama con la primera clave, y el PR menciona las dos.

## Commits y pull requests

- **Título del PR:** `tipo(SCRUM-NN): descripción en imperativo`. Por ejemplo: `feat(SCRUM-98): configurar Fortify con expiración de sesión por rol`. Con el squash, ese título se convierte en el commit que queda en `develop`.
- Tipos: `feat`, `fix`, `test`, `docs`, `refactor`, `chore`.
- Dentro de la rama los commits son libres (el squash los junta), pero conviene incluir la clave de Jira.
- Tamaño: 300–400 líneas cambiadas como máximo, sin contar archivos generados ni los `.lock`. Si crece más, se divide.
- La descripción sigue la plantilla del final de este documento.

## Revisión

- Para fusionar hacen falta: 1 aprobación de otro integrante, la CI en verde y las conversaciones resueltas.
- Fusiona el autor, una vez aprobado.
- Plazo de revisión: la misma jornada, máximo 24 h. Si un PR bloquea a alguien, se avisa en el grupo y la subtarea se marca «Flagged» en Jira.

| Área del cambio | Revisor sugerido | Si el autor es el mismo revisor |
|---|---|---|
| Backend Laravel (`app/`, `routes/`, `database/`, `config/`) | Rafa | Gael |
| Vistas y estilos (`resources/`) | Medina | Daniel |
| Pruebas, CI y configuración (`tests/`, `.github/`) | Daniel | Gael |
| Gestión Scrum (`ers-daniel/`) | Medina (PO) | — |
| `develop` → `main` (cierre del sprint) | Medina (PO) | — |

El revisor comprueba:
- que se cumple lo que pide la subtarea y que hay pruebas;
- que no se suben credenciales;
- que las migraciones son nuevas (no editadas);
- en el caso de vistas, que están en español y se usan con teclado.

## El día a día

```bash
git switch develop && git pull                  # empezar siempre desde develop actualizado
git switch -c feat/SCRUM-98-fortify-sesiones    # una rama por subtarea
# ... trabajo y commits ...
git push -u origin feat/SCRUM-98-fortify-sesiones
# En GitHub: abrir el PR hacia develop con la plantilla

# Si develop avanzó mientras tanto:
git fetch origin && git merge origin/develop

# Después de fusionar el PR:
git switch develop && git pull && git branch -d feat/SCRUM-98-fortify-sesiones
```

Estado en Jira:
- La subtarea pasa a «En curso» al crear la rama y a «Listo» cuando su PR se fusiona.
- La historia está terminada cuando todas sus subtareas están fusionadas y cumple la Definition of Done.

## Reglas para no romper `develop`

- **Cada PR deja `develop` funcionando:** la CI pasa y la aplicación arranca. Una historia a medias puede tener partes en `develop`, pero no aparece en el menú hasta estar completa.
- **Migraciones:** nunca se edita una migración que ya está en `develop`; se crea una nueva.
- **Credenciales y variables:** `.env` nunca se sube, y cada variable nueva se agrega a `.env.example` (RNF-42). Lo mismo aplica al token del túnel de Cloudflare y a las contraseñas de SMTP.
- **Archivos `.lock`:** `composer.lock` y `package-lock.json` se suben. Si chocan en un conflicto, se conserva la versión de `develop` y se vuelve a ejecutar `composer require` o `npm install` del paquete propio.
- **Historial compartido:**
  - No se reescribe historial compartido: nada de `push --force` en `develop` ni en `main`.
  - Para actualizar la rama propia se usa `merge` de `develop` en lugar de `rebase`. Así no hace falta forzar el push.

## Cierre de cada sprint (viernes)

1. **Corte a la hora de la Review:** lo que no esté fusionado en `develop` no se presenta. Su rama sigue abierta para el siguiente sprint.
2. **PR de cierre:** Daniel (SM y despliegue) abre el PR `develop` → `main` con el título «Sprint N (vX.Y.Z)». Medina (PO) lo aprueba y se fusiona con merge commit.
3. **Etiqueta y release:** se crea la etiqueta en `main` y un GitHub Release con las notas de la Review.
4. **Demostración:** la Review se hace desde `main`, a través del túnel de Cloudflare.

| Sprint | S1 | S2 | S3 | S4 | S5 | S6 | S7 | S8 | S9 (entrega 11/12) |
|---|---|---|---|---|---|---|---|---|---|
| Etiqueta | v0.1.0 | v0.2.0 | v0.3.0 | v0.4.0 | v0.5.0 | v0.6.0 | v0.7.0 | v0.8.0 | v1.0.0 |

## Correcciones urgentes (hotfix)

Solo cuando algo ya entregado en `main` debe corregirse antes del siguiente viernes (por ejemplo, antes de una demostración a la Liga):
1. Crear `hotfix/v0.1.1-descripcion` desde `main`.
2. Abrir el PR hacia `main` y fusionarlo con merge commit.
3. Crear la etiqueta `v0.1.1`.
4. Abrir el PR `main` → `develop`, también con merge commit.

Un defecto que se encuentra durante el sprint en `develop` no es hotfix: se corrige en una rama `fix/` normal.

## Configuración (S1-T01, SCRUM-93)

Situación al 13/10/2026 (verificada con `gh`):
- Repositorio privado de cuenta personal.
- Rama por defecto: `main`, que solo tiene el `README.md` y un archivo vacío `main`.
- La gestión Scrum está en la rama `ers-daniel`.
- Colaboradores: `ultronLegacyy` y `rafa11512`.
- **GitHub responde «Upgrade to GitHub Pro or make this repository public» al consultar la protección de ramas.**

Pasos:
1. **Activar GitHub Pro gratis** con el GitHub Student Developer Pack (education.github.com), en la cuenta dueña del repositorio. Sin Pro, las reglas de los pasos 6 y 7 no se aplican en un repositorio privado y solo se cumplirían por acuerdo.
2. **Invitar como colaboradores a Medina y a Gael:** Settings → Collaborators.
3. **Llevar la gestión Scrum a `main`:**
   - Subir los cambios pendientes de `ers-daniel`.
   - Abrir el PR `ers-daniel` → `main` y fusionarlo con merge commit. Es la única vez que una rama personal entra en `main`.
   - Después, borrar la rama `ers-daniel`. La gestión continúa en `docs/sprint-NN`.
   - La carpeta `ers-daniel/` conserva su nombre por ahora: renombrarla cambia la ruta con la que Claude Code guarda las sesiones y la memoria. Se puede renombrar después, con aprobación.
4. **Crear `develop`** desde `main` y hacerla la rama por defecto: Settings → General → Default branch.
5. **Ajustar los pull requests** en Settings → General → Pull Requests:
   - permitir «merge commits» y «squash merging»;
   - desactivar «rebase merging»;
   - activar «Automatically delete head branches».
6. **Ruleset para `main`** (Settings → Rules → Rulesets):
   - exigir PR con 1 aprobación;
   - exigir que las conversaciones estén resueltas;
   - exigir que pasen los checks de la CI (cuando exista S1-T04);
   - bloquear force push;
   - restringir el borrado;
   - método de fusión: solo merge commit;
   - sin excepciones, ni siquiera para el administrador.
7. **Ruleset para `develop`:** lo mismo, con dos métodos de fusión: squash (el habitual) y merge commit (solo para `main` → `develop` después de un hotfix).
8. **Plantilla de PR:** agregar `.github/pull_request_template.md` (abajo) en el PR del proyecto base o en el de la CI.
9. **Proyecto base:** el proyecto Laravel va en la raíz (S1-T02, rama `chore/SCRUM-94-proyecto-base`).
   - Como la raíz no está vacía, se crea en una carpeta temporal y se copia.
   - El archivo vacío `main` de la raíz parece un error y puede quitarse en ese PR.
10. **CI (S1-T04):** el workflow corre en todos los `pull_request` hacia `develop` y `main`, **sin `paths-ignore`**. Un check obligatorio que no se ejecuta deja el PR bloqueado, por ejemplo los PR de `docs/sprint-NN`.

**Ruta crítica del martes:** pasos 1–5 (Daniel) → PR del proyecto base de Rafa (SCRUM-94) revisado y fusionado → los demás abren sus ramas. Hasta que el proyecto base esté en `develop`, nadie más puede empezar código.

**Seguridad:** el historial de este repositorio conserva los archivos de credenciales de V-01 (commit `90111af`), y todos los colaboradores pueden verlos. Antes de invitar a más personas, confirmar que esas credenciales ya se rotaron (cerrar sesión e iniciar sesión de nuevo en Claude Code).

## Plantilla de PR (`.github/pull_request_template.md`)

```markdown
## Qué cambia

## Cómo probarlo

## Jira
Subtarea: SCRUM-NN · Historia: HU-NNN

## Lista de verificación
- [ ] El título sigue `tipo(SCRUM-NN): descripción`
- [ ] La CI está en verde (Pint, Larastan nivel 5, PHPUnit)
- [ ] Agregué o actualicé pruebas
- [ ] Sin credenciales ni `.env`; variables nuevas en `.env.example`
- [ ] Solo migraciones nuevas; no edité ninguna existente
- [ ] Vistas en español, con validación y usables con teclado (si aplica)
- [ ] Las operaciones críticas quedan en la auditoría (si aplica)
```

## Integración con Jira (opcional)

La aplicación gratuita «GitHub for Jira» (en Jira: Apps → Explore more apps) muestra en cada incidencia sus ramas, commits y PR. Los reconoce cuando la clave `SCRUM-NN` aparece en el nombre de la rama o en el título. La instala el administrador del sitio. La estrategia funciona igual sin ella.
