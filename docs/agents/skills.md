# Skills disponibles

Estas skills vienen de plugins instalados **a nivel usuario**, no del repo: están en
cualquier sesión de esta máquina sin instalar nada. Se invocan por nombre
(`/<skill>` o la tool `Skill`).

Este archivo **no lo genera `claude-tools`**. Es del proyecto, y `bootstrap.ps1` no lo
toca al reinstalar herramientas.

Abajo solo están las que aplican a Mi Biblioteca, con el gancho concreto de este
dominio. Que exista una skill no obliga a usarla: son para cuando el trabajo cae en su
terreno, no un checklist a recorrer.

## Dominio y diseño de código

| Skill | Cuándo |
| --- | --- |
| `mattpocock-skills:domain-modeling` | Cuando haya que afinar `CONTEXT.md`, fijar un término nuevo o registrar un ADR. Es la dueña del lenguaje ubicuo: el día que «lectura» se use para el Escaneo, se arregla aquí y no en el código. |
| `mattpocock-skills:grilling` | Antes de comprometerse con un plan grande. Quedan dos decisiones en `CONTEXT.md` § Lo que sigue abierto —qué proveedor de IA y si hace falta un límite de Citas públicas— y son el material que conviene estresar a preguntas antes de escribir código. |
| `mattpocock-skills:codebase-design` | Al decidir dónde va un seam. El caso vivo es la Fuente: cambiar de catálogo público, o consultar dos en cascada, no debe tocar la pantalla de captura ni la forma de la Ficha guardada. |
| `mattpocock-skills:research` | Para elegir proveedor de IA —costo por libro, calidad de las Categorías en español, y qué pide Ollama para correr en el VPS—, y para medir la cobertura real de cada Fuente en ediciones mexicanas. Preguntas de datos, no de opinión. |
| `mattpocock-skills:prototype` | Para la pantalla de captura antes de comprometerse: un Escaneo en ráfaga de veinte libros se siente distinto de como se lee en un ticket. |
| `mattpocock-skills:tdd` | Donde hay reglas verificables sin ambigüedad: dígito de control del ISBN-10 y del ISBN-13, la conversión entre los dos, y el adaptador de la Fuente contra respuestas grabadas. |
| `mattpocock-skills:code-review` | Antes de comitear un grupo de issues del loop. |
| `mattpocock-skills:diagnosing-bugs` | Cuando algo falla y la causa no es obvia. El sospechoso típico de este proyecto es el Escáner: teclea más rápido que cualquier persona y no siempre manda el Enter. |

## Producto y UX

El terreno de estas es **la pantalla de captura**. Es la superficie que decide si el
sistema se usa: ocurre de pie, con las dos manos ocupadas —una con el libro y otra con el
Escáner— y en ráfagas de veinte. Cualquier paso extra se paga veinte veces.

| Skill | Cuándo |
| --- | --- |
| `design:ux-copy` | Redactar lo poco que se le dice a quien captura. Debe usar los términos de `CONTEXT.md` y **distinguir los tres estados de Lectura**: una casilla de «leído» a secas pierde el «leído sin fecha». |
| `design:design-critique` | Revisar una pantalla ya hecha. La pregunta no es si se ve bien, sino si después del Escaneo se entiende en un vistazo qué se registró y qué falta. |
| `design:accessibility-review` | Auditar contra WCAG 2.1 AA. Aquí pesa el foco del teclado: si el campo del ISBN pierde el foco, el Escáner teclea al vacío. |
| `frontend-design:frontend-design` | Dirección visual al construir UI nueva, para que no salga con cara de plantilla. La Portada es el elemento visual dominante del Acervo: el diseño se arma alrededor de una retícula de portadas, no de una tabla. |
| `design:design-system` | Cuando aparezcan valores hardcodeados o nombres inconsistentes entre componentes. |

## Andamiaje del proyecto

`youtrack-new-project` ya corrió sobre este repo: creó el proyecto `BIB`, sus campos
`Triage`/`Cost` y `.youtrack-project.ps1`. No hay que volver a correrla salvo que se
rehaga el proyecto en el tracker.

Las herramientas de `docs/agents/`, `scripts/` y `.ralph/` se instalaron desde
[claude-tools](https://github.com/UranoDev/claude-tools); `.tooling-version` dice con qué
commit. Un arreglo a cualquiera de ellas va en ese repo, no en la copia de aquí.
