# Respuestas de las Fuentes usadas en las pruebas

Las pruebas de los adaptadores corren contra estos archivos y **nunca salen a internet**
(`Http::preventStrayRequests()` es global, en `tests/Pest.php`).

**Ninguno es una grabación cruda.** Todos están escritos a mano con la forma del
catálogo: traen los campos que el adaptador lee y tiran el resto. Una respuesta real de
Open Library, por ejemplo, incluye además `created`, `last_modified`, `latest_revision`,
`revision`, `source_records`, `identifiers`, `ocaid` y `classifications`, que aquí no
están porque a nadie le hacen falta.

Eso está bien para probar un adaptador y tiene un costo que conviene tener presente: **lo
que estas pruebas verifican es que el adaptador entiende la forma que nosotros creemos
que el catálogo manda.** Un campo que nadie previó pasa desapercibido.

Varias llevan a propósito rarezas reales del catálogo, que es lo que las hace útiles:
`covers: [-1, ...]` de Open Library —usa `-1` para «no hay»—, la `description` que a
veces es cadena y a veces objeto con `value`, y la de Google Books que viene con HTML
adentro.

## La excepción que sí es un problema: `google-books-busqueda.json`

Hay una diferencia grande entre las demás y ésta.

En las otras, alguien vio la respuesta real en algún momento y escribió la forma a partir
de ella. La consulta por ISBN de Open Library se verificó contra la API en vivo mientras
se planeaba BIB-5, y el adaptador se escribió sobre lo que se vio: que la edición **no**
trae descripción y hay que seguir el enlace a la obra, y que ahí `description` llega en
dos formas distintas.

De la **búsqueda por título de Google Books nadie ha visto nunca una respuesta real** en
este proyecto. El día que se construyó BIB-6, la cuota anónima estaba en cero y
`GOOGLE_BOOKS_API_KEY` no estaba configurada, así que no se pudo ejecutar ni una vez.
Este archivo se dedujo de la forma de `google-books-volumen.json`, que es la consulta por
ISBN, no la búsqueda.

Y eso pesa más de lo que parece, porque **la ampliación del ADR-002 eligió a Google Books
como primera Fuente del Alta por título apoyándose justamente en cómo se supone que
responde**.

El día que haya llave: ejecutar la búsqueda de verdad, rehacer este archivo, correr las
pruebas y volver a leer esa ampliación del ADR-002.
