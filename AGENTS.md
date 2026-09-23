## Agent skills

### Issue tracker

YouTrack, project BIB (`uranodev.youtrack.cloud`), via the `mcp__youtrack__*` tools — configured at user scope, so no per-repo setup. See `docs/agents/issue-tracker.md`.

Two conventions that are easy to miss: ordered subtasks need formal **`depends on`** links (this instance has no `blocked by`), and every issue hangs off an umbrella `Type: Epic` issue via `parentIssue` / `subtask of`.

### Triage labels

Five canonical triage roles mapped to a dedicated `Triage` enum custom field on BIB; category (bug/enhancement) uses the native `Type` field. `State` stays reserved for engineering workflow. See `docs/agents/triage-labels.md`.

### Stack

Laravel 13 con Livewire 4 y Flux, Fortify para autenticación, Pest 5 para pruebas, y
Tailwind 4 sobre Vite 8 (`vite-plus`). La base de datos es MySQL, esquema `mibiblio`, en
el MySQL de Laragon.

Para levantarlo: `php artisan serve --port=8022`, ya declarado en `.claude/launch.json`.
Las pruebas corren con `php artisan test`; el formato, con `./vendor/bin/pint`.

Las Categorías se generan con el SDK oficial `laravel/ai` (ver ADR-006); lo instala el
primer grupo del backlog, junto con el `git init` y el repositorio en GitHub, que el
despliegue necesita.

La Vitrina —la cara pública— vive en `biblio.urano.dev`; el despliegue es Plesk sobre el
VPS, con clave de despliegue de sólo lectura, igual que `reviews`.

El `.gitignore` del skeleton de Laravel ignora `/AGENTS.md`, `/CLAUDE.md` y `/.claude`.
Esas tres líneas **se quitaron a propósito**: los docs para agentes viajan con el repo.
No las vuelvas a agregar.

### Redacción de la interfaz

El texto de una pantalla dice qué hace esa pantalla: **sin aforismos**, sin argumentar la
decisión de diseño —eso vive en su ADR— y **sin prometer en presente lo que depende de
una configuración que quizá nadie hizo**. Ninguna prueba lee la prosa, así que se relee
antes de comitear. La regla es global (`~/.claude/CLAUDE.md`), no de este repo.

### Catálogo de skills

`docs/agents/skills.md` lista las skills que aplican a este dominio con su gancho
concreto: modelo de dominio, la pantalla de captura, y el sistema visual del Acervo.
