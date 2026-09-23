# ADR-002 — La Ficha se arma con Open Library primero y Google Books de respaldo

- **Estado:** Aceptada
- **Fecha:** 2026-09-21 · **Ampliada:** 2026-09-23

## Contexto

Del Escaneo sale un ISBN, y de ahí tiene que salir la Ficha: autores, editorial, año,
edición y Portada. Ninguna Fuente sola cubre todo, y **las ediciones mexicanas son el
punto flaco de las gratuitas** — es justo el caso que más va a aparecer en este acervo.

Tres caminos: una sola Fuente gratuita, dos gratuitas en cascada, o un catálogo de pago
con mejor cobertura a cambio de llave, cuota y costo mensual.

## Decisión

Se consulta **Open Library primero** y, si no devuelve esa edición, **Google Books**. Si
ninguna la conoce, la pantalla **ofrece Captura manual** en lugar de fallar: un ISBN
desconocido no bloquea el registro.

La Fuente queda **detrás de un seam**: la forma de la Ficha guardada no depende de cuál
respondió, y cambiar el orden, agregar un catálogo o contratar uno de pago más adelante
no toca la pantalla de captura.

## Consecuencias

- **La Captura manual es una vía normal, no un estado de error.** Con Fuentes gratuitas va
  a usarse seguido, así que se diseña para eso y no como pantalla de última hora.
- **Queda registrado cuál Fuente respondió** por cada Libro. Cuando un dato salga flojo
  —autor incompleto, año equivocado— se necesita saber de dónde vino antes de decidir si
  se corrige a mano o se cambia el orden de consulta.
- No se contrata catálogo de pago por ahora. Si la Captura manual se vuelve la regla en
  vez de la excepción, ése es el síntoma que reabre la decisión.
- Lo que la Fuente entrega como Portada sigue siendo una URL suya: **si la Portada se
  guarda o se enlaza es una decisión aparte**, todavía abierta.

## Ampliación del 2026-09-23 — Google Books se consulta con llave propia

Comprobado contra la API: sin llave, las peticiones anónimas comparten una cuota global
y devuelven **HTTP 429** cuando se agota. Se decidió usar **llave propia**, leída del
entorno.

Lo que importa no es el límite, sino cómo se traduce el error: **«no me dejaron
preguntar» no es «no existe ese libro»**. Confundirlos manda a Captura manual un libro
que la Fuente sí tenía, y quien captura no tiene forma de notar la diferencia. El
adaptador distingue los dos casos aunque haya llave, porque una cuota también se agota.

Sin llave configurada, el respaldo sigue funcionando hasta que la cuota compartida se
acabe; a partir de ahí avisa que la Fuente no pudo responder, en vez de mentir.
