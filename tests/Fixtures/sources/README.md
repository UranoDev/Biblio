# Respuestas grabadas de las Fuentes

**Todos los archivos de esta carpeta son grabaciones.** Cada uno es lo que un catálogo
contestó a una petición concreta, escrito tal cual llegó —sin recortar campos, sin
reacomodar, sin reformatear— por `php artisan sources:record`. Ninguno está escrito a
mano.

Las pruebas de los adaptadores corren contra estos archivos y **nunca salen a internet**:
`Http::preventStrayRequests()` es global, en `tests/Pest.php`. Las únicas que sí salen son
las de contrato, y están abajo.

## Cómo se graba

El comando pide y guarda **una** respuesta. Se corre a mano cuando hace falta una nueva, y
el archivo se commitea.

```
php artisan sources:record <nombre> --source=open-library --isbn=<ISBN>
php artisan sources:record <nombre> --source=open-library --resource=works/OL274505W
php artisan sources:record <nombre> --source=open-library --title=<título> --author=<autor>
php artisan sources:record <nombre> --source=google-books --isbn=<ISBN>
php artisan sources:record <nombre> --source=google-books --title=<título> --author=<autor>
```

La petición que manda es **la que manda el adaptador**: los parámetros de la búsqueda
salen de `OpenLibrary::searchParameters()` y `GoogleBooks::searchParameters()`, las mismas
que usa `byTitle()`. Una grabación de una petición que nadie hace no prueba nada del
adaptador que la lee.

`--expect` dice qué estado HTTP se espera; con cualquier otro no se escribe nada. Así se
graba a propósito un error —`--expect=429`— sin que un 503 de paso termine guardado con
nombre de respuesta buena.

## Ninguna grabación lleva la llave

Una respuesta puede devolver la petición de vuelta —Google pone en `selfLink` la URL que
contestó— y con ella la llave. El comando la quita antes de escribir, junto con cualquier
cosa con forma de credencial, y la deja como `REDACTED`.

`tests/Feature/Sources/RecordingsTest.php` recorre la carpeta entera y falla si aparece
una llave de Google, un `key=` en una URL o algo con pinta de token. Es lo que se entera
el día que el comando deje de alcanzar.

## Qué hay grabado

Todo se grabó el **24 de septiembre de 2026**.

### Open Library

| Archivo | Se pidió | Qué caso trae |
| --- | --- | --- |
| `open-library-edition.json` | `--isbn=9788420471839` | La edición completa: editorial, año, nombre de edición, Cover y enlace a la obra. «Cien años de soledad». |
| `open-library-edition-with-subtitle.json` | `--isbn=9781837639618` | La única grabada con `subtitle`, y la única acreditada a más de un autor. |
| `open-library-edition-without-work.json` | `--isbn=9789972402203` | Una edición que no cuelga de ninguna obra: no hay de dónde sacar la Description. |
| `open-library-edition-without-cover.json` | `--isbn=9781937785499` | `covers: [-1]`, que es como Open Library anota que no tiene ninguna. |
| `open-library-edition-without-publisher.json` | `--isbn=9788178087368` | Una edición que es poco más que un título y un ISBN: sin editorial, sin fecha, sin Cover. |
| `open-library-work.json` | `--resource=works/OL274505W` | La obra de la edición principal, con la `description` como cadena. |
| `open-library-work-description-object.json` | `--resource=works/OL1731119W` | La misma `description`, pero como objeto con `value`. El catálogo guarda las dos formas. |
| `open-library-author-garcia-marquez.json` | `--resource=authors/OL27363A` | El autor de la edición principal. |
| `open-library-author-ayeva.json` | `--resource=authors/OL3121166A` | El primero de los dos de la edición con subtítulo. |
| `open-library-author-kasampalis.json` | `--resource=authors/OL7494961A` | El segundo, que es lo que deja probar el orden. |
| `open-library-search.json` | `--title="Pedro Páramo" --author=Rulfo` | El TitleLookup: 6 obras con 164 ediciones entre ellas, y una sola edición de cada una. |

### Google Books

| Archivo | Se pidió | Qué caso trae |
| --- | --- | --- |
| `google-books-volume.json` | `--isbn=9780307474728` | El volumen completo, con Description y con la Cover ofrecida por `http`. |
| `google-books-volume-without-cover.json` | `--isbn=9788439732471` | Un volumen sin `imageLinks` y sin `publisher`. |
| `google-books-search.json` | `--title="Pedro Paramo" --author=Rulfo` | El TitleLookup: 20 volúmenes, con y sin ISBN, con y sin editorial. |
| `google-books-search-with-markup.json` | `--title="Atomic Habits"` | De los títulos que se probaron, el único cuya Description llega de verdad con etiquetas. |
| `google-books-no-results.json` | un título que no existe | `totalItems: 0`, que no es lo mismo que no haber podido contestar. |
| `google-books-quota-exhausted.json` | `--isbn=9780060883287 --without-key --expect=429` | El 429 de la cuota compartida, tal como lo contesta Google. Se graba sin llave a propósito: la cuota anónima ya está agotada. |

## Lo que no está acá, y por qué

Un caso no tiene grabación: **un ISBN cuyo dígito de control no cuadra**. De los 20
volúmenes de `google-books-search.json`, todos los ISBN cuadran, y no apareció ninguno mal
en lo que se revisó. El adaptador igual lo contempla, así que la prueba existe —en
`GoogleBooksTest.php`, «descarta el ISBN cuyo dígito de control no cuadra»— y **arma el
cuerpo ahí mismo**, en el archivo de la prueba.

Esa es la regla: lo que no se pudo grabar no entra a esta carpeta disfrazado de grabación.
Se escribe en la prueba que lo necesita, donde nadie lo va a confundir con lo que contestó
un catálogo.

## Las pruebas de contrato

Viven en `tests/Feature/Sources/Contract/` y son las únicas del proyecto que salen a
internet. **No corren con `php artisan test`**: `phpunit.xml` excluye su grupo. Se corren
a mano pidiéndolo por nombre:

```
php artisan test --group=contract
```

No comparan contenido —el catálogo es un wiki y cambia, y eso no es una falla— sino
forma: que los campos que los adaptadores leen sigan existiendo y sigan llegando como el
día que se grabaron. Cuando una falla, lo que toca es volver a grabar y leer la grabación,
no aflojar la prueba.

## Qué apareció al grabar de verdad

Regrabar contra los catálogos vivos sirvió para lo que se esperaba: las respuestas
escritas a mano suponían cosas que no eran.

- La edición de `9788420471839` no trae `subtitle`, y la editorial, el número de páginas y
  la `key` eran otros. Se cambió la grabación; la forma del adaptador aguantó.
- **Google Books contesta la búsqueda por título mucho más pobre de lo que supuso la
  ampliación del ADR-002.** Buscando «Pedro Paramo» de Rulfo, de sus 20 volúmenes sólo 4
  traen editorial y sólo 4 traen Cover; Open Library, con 6 resultados, trae editorial en
  los 6 y Cover en 4. Eso toca la razón por la que Google Books quedó primero en el
  TitleLookup, así que no se resolvió acá: está en BIB-44.
