# ADR-006 — Las Categorías las propone una IA, con el Modelo elegido en pantalla

- **Estado:** Aceptada
- **Fecha:** 2026-09-22 · **Revisada:** 2026-09-23

## Contexto

La Vitrina se navega por tema: una URL puede traer una Categoría y mostrar los Libros que
pertenecen a ella. Para eso hacen falta Categorías en todos los Libros.

Ninguna de las dos Fuentes las da de forma utilizable: Open Library trae `subjects` en
cantidades absurdas y desparejas, y Google Books unas pocas categorías gruesas. Y
escribirlas a mano para doscientos libros es trabajo que no va a ocurrir.

Queda una segunda pregunta, distinta de quién las escribe: **dónde vive la elección del
Modelo**. Los modelos cambian seguido —salen mejores, bajan de precio, uno deja de
servir— y esa elección se quiere poder probar: generar con uno, ver cómo quedaron las
Categorías, cambiar y comparar. En el `.env` eso significa editar un archivo en el
servidor y volver a desplegar por cada prueba.

## Decisión

Con el SDK oficial **`laravel/ai`**, un agente con **salida estructurada** propone las
Categorías de un Libro a partir de su Ficha.

- Se piden **en cola** (`->queue()`). **Nunca durante la captura**: una ráfaga de veinte
  libros no puede esperar a veinte respuestas de una API.
- Al agente se le pasa **la lista de Categorías que ya existen** y se le pide reutilizarlas
  cuando encajen, antes de inventar una nueva.
- Las Categorías **siempre se pueden editar, agregar y quitar a mano**, renombrar y
  fusionar.
- **El proveedor y el Modelo se eligen en una pantalla del back**, detrás del login, y se
  guardan en la base. No en el `.env`.
- **Las llaves de API siguen en el entorno.** No se guardan en la base ni se capturan en
  un formulario: la pantalla sólo ofrece los proveedores que ya tienen llave configurada,
  y dice cuáles no la tienen.
- **Se puede regenerar**: las Categorías de un Libro, o las de todos, cuando se cambia de
  Modelo o simplemente para actualizarlas.
- **Queda registrado con qué Modelo se generaron** las Categorías de cada Libro.

## Consecuencias

- **La fragmentación del vocabulario es el modo de falla, no el error de la IA.** Sin la
  lista existente en el prompt, doscientos libros producen «Ciencia ficción»,
  «Ciencia-ficción» y «Sci-fi» como tres Categorías distintas, y la navegación de la
  Vitrina se vuelve inútil. De ahí que renombrar y fusionar no sea un lujo.
- **Guardar el Modelo por Libro es lo que hace preciso el «regenerar porque cambié de
  Modelo»**: sin ese dato, la única opción es regenerar los doscientos a ciegas y pagarlos
  otra vez.
- **Pedirlas en cola exige un worker corriendo en producción.** Si no corre, los Libros se
  registran sin Categorías y nada avisa: eso tiene que ser visible en la interfaz.
- **Sin Modelo seleccionado, la interfaz dice que las Categorías todavía no se han
  generado** — no muestra al Libro como si no tuviera ninguna. Son dos estados distintos y
  confundirlos es prometer en presente algo que nadie configuró.
- **Ninguna prueba llama al proveedor real.** El SDK trae `fake()` y `assertPrompted()`, y
  con salida estructurada genera respuestas falsas que cumplen el esquema.
- **La IA propone; lo publicado es lo que yo dejé.** Ninguna Categoría llega a la Vitrina
  sin que haya podido revisarla.

## Qué cambió el 2026-09-23

La versión original decía que el proveedor se elegía «por configuración» y que la
decisión de cuál usar seguía abierta. Se movió a una pantalla del back con el Modelo
guardado en la base, más la regeneración por Libro y masiva. El motivo es que elegir
Modelo dejó de ser una decisión de arquitectura —que se toma una vez— y pasó a ser una
operación normal del sistema, que se hace varias veces y se quiere poder comparar.
