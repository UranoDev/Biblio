# ADR-005 — Un ISBN es único en el acervo; no se modelan ejemplares

- **Estado:** Aceptada
- **Fecha:** 2026-09-22

## Contexto

Los doscientos libros del acervo entran por Escaneo, en ráfagas. En una ráfaga así,
**reescanear un libro ya registrado no es un caso raro: es seguro que pasa**. Y una vez
que el sistema está en uso, escanear un libro es también la forma natural de preguntar
«¿ya lo tengo?».

Había que decidir dos cosas a la vez: qué ocurre al reescanear, y si el sistema guarda
que tienes dos copias del mismo libro.

## Decisión

**El ISBN es único en el acervo.** Al escanear uno ya registrado:

- **Si está en el Acervo** → aviso visible de que ya lo tienes, sin duplicar y **sin
  ventana de confirmación**, porque nada puede robar el foco durante la captura
  (ADR-001).
- **Si está Por comprar** → pasa al Acervo automáticamente, con aviso y con deshacer. Ésa
  es la vía normal de «ya lo compré».
- **Si no está** → registro normal.

**No se modelan ejemplares repetidos.** Si tienes dos copias, el sistema guarda una.

## Consecuencias

- **Nada de lo que se guarda es del ejemplar.** Las Citas, los Apuntes y la Lectura son de
  la edición. Un segundo ejemplar no tendría un solo campo propio que llenar: sería una
  fila que únicamente dice «hay dos».
- **No se puede saber que prestaste una de dos copias.** Con el préstamo como campo de
  texto eso es evidente y se vive con ello; con una entidad de préstamos sería una
  inconsistencia que da la cara.
- **El aviso de «ya lo tienes» es también una función de consulta**, no sólo un error. Por
  eso tiene que ser legible de un vistazo en medio de una ráfaga, y no puede interrumpirla.
- **El paso automático de Por comprar a Acervo supone que escaneas lo que ya es tuyo.** Es
  cierto porque el Escáner es USB y vive en el escritorio: en la librería consultas desde
  el teléfono, no escaneas. Si algún día se captura fuera de casa, esta suposición se cae
  y hay que revisarla.
