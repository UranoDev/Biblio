# ADR-001 — El Escáner es un lector USB que se comporta como teclado

- **Estado:** Aceptada
- **Fecha:** 2026-09-21

## Contexto

La captura es la pantalla que decide si el sistema se usa. Ocurre de pie, con las dos
manos ocupadas —una con el libro y otra con el Escáner— y en ráfagas de veinte libros,
no de uno. Cada paso extra se paga veinte veces.

Había dos caminos para leer el código de barras:

- **Un lector USB** de los que se comportan como teclado: teclea el ISBN en el campo que
  tenga el foco y manda Enter. No necesita nada del navegador.
- **La cámara del teléfono**, leyendo el código con una librería de JavaScript. No
  requiere comprar nada, pero exige HTTPS y permiso de cámara, y es más lenta por libro:
  hay que encuadrar, esperar el enfoque y confirmar.

## Decisión

El Escáner es un **lector USB tipo teclado**. La pantalla de captura es un campo de ISBN
**siempre enfocado**, que acepta una línea terminada en Enter y recupera el foco solo
después de cada registro. No se depende de ninguna API de cámara, ni de HTTPS, ni de un
permiso que alguien tenga que conceder.

La cámara del teléfono no queda descartada, pero sería **otra ruta de captura** —con su
propio ADR—, no una variante de ésta.

## Consecuencias

- **El foco del campo es un requisito funcional, no un detalle de pulido.** Si el foco se
  va, el Escáner teclea al vacío y el libro no se registra sin que nada avise. Por eso el
  foco entra en la auditoría de accesibilidad y en las pruebas de la pantalla.
- **Los lectores que no mandan Enter se resuelven en el campo**, con un temporizador de
  inactividad sobre lo tecleado, y no pidiendo a la persona que oprima un botón: oprimir
  un botón por libro es justamente el paso que se paga veinte veces.
- Cualquier modal, aviso o confirmación que robe el foco durante la captura rompe la
  ráfaga. La pantalla de captura no puede tener ninguno.
