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

## Ampliación del 2026-09-23 — El Alta por título consulta al revés

La cascada por ISBN y la del Alta por título **no son la misma**, y cada una vive en su
propia lista en `config/fuentes.php`.

Un Candidato es una edición (ADR-005) y tiene que traer Portada, autores, editorial y
año, porque sin eso no hay forma de distinguir la de bolsillo de la del 2015. Comprobado
contra los catálogos reales, buscando «Pedro Páramo» de Rulfo:

- **Open Library busca obras, no ediciones.** Devolvió 6 obras que entre todas suman 134
  ediciones, y de cada obra entrega **una sola**, elegida por ella: la de la primera no
  trae ISBN, y la de la segunda es una traducción al portugués. `editions.limit` se
  ignora, así que no hay manera de pedirle las demás sin una petición por obra.
- **Google Books busca volúmenes, y cada volumen es una edición**, con sus
  `industryIdentifiers`, su editorial y su año en la misma respuesta.

**Lo que no se pudo medir.** La comparación de arriba es medida **sólo del lado de Open
Library**. La búsqueda de Google Books no se pudo ejecutar ese día: su cuota anónima
estaba en cero —el mismo 429 de la decisión 16— y no había llave configurada. Lo que se
afirma de Google Books se dedujo de la forma de su respuesta **por ISBN**, que es otra
consulta, y por eso `tests/Fixtures/fuentes/google-books-busqueda.json` está escrita
sobre esa forma: **nadie en este proyecto ha visto nunca una respuesta real de la
búsqueda por título de Google Books**. Ver el README de esa carpeta.

Eso deja el orden elegido apoyado en evidencia despareja: sabemos que Open Library no
sirve para elegir edición, no que Google Books sí. **El día que haya llave, hay que
rehacer esa grabación contra la API y volver a mirar esta decisión** — si Google Books
resultara igual de pobre, la conclusión no cambia (sigue siendo mejor que obras), pero el
orden podría dar lo mismo.

Por eso el Alta por título consulta **Google Books primero y Open Library de respaldo**,
al revés que por ISBN. Open Library no se quita: una obra por renglón es poco, pero es
mejor que nada el día que la otra no pueda contestar — y con la búsqueda por título ese
día llega antes, porque es la operación que más rápido agota una cuota.
