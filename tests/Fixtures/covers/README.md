# Las imágenes que los catálogos mandan cuando no tienen portada

**Éstas sí son grabaciones crudas**, a diferencia de las de `../sources`, que están
escritas a mano con la forma de la respuesta. Cada archivo se bajó del catálogo real el
2026-09-23 y está byte por byte como llegó: si se reescribieran a mano, el hash cambiaría
y la prueba dejaría de probar lo único que importa acá, que es reconocer **este** archivo.

| Archivo | De dónde salió | Medidas | Bytes |
| --- | --- | --- | --- |
| `open-library-no-cover.gif` | `covers.openlibrary.org/b/isbn/9788437604176-L.jpg` | 1×1 | 43 |
| `google-books-not-available.png` | `books.google.com/books/content?id=pEjU0QEACAAJ&…&zoom=3` | 575×750 | 9 103 |
| `google-books-not-available-128x198.jpg` | el mismo volumen, `zoom=1` | 128×198 | 7 970 |
| `google-books-not-available-128x170.png` | volumen `3TIctgEACAAJ`, `zoom=1` | 128×170 | 1 269 |

Tres cosas que se comprobaron bajándolas, y que no se ven en la tabla:

- El de Open Library **no es un JPEG** aunque la URL termine en `.jpg`: es un GIF de 1×1
  transparente. Lo que decide es lo que traen los bytes, no la extensión.
- La hoja de 575×750 que dice «image not available» es **el mismo archivo para todos los
  volúmenes** —se verificó contra tres ISBN distintos—, y también **es PNG**, no JPEG.
  Por eso el hash la reconoce.
- El mosaico rojo de `zoom=1` —que es el que trae el enlace `thumbnail` del Record, o
  sea el que la captura descarga en la práctica— **viene con la forma del libro**: hay un
  archivo por proporción, así que la lista de hashes cubre los que se vieron y no todos.
  Los dos guardados acá salieron de libros distintos y son idénticos entre sí por
  proporción.

`App\Covers\Marker` tiene el hash de cada uno. Para agregar el marcador de otro catálogo:
bajarlo acá y poner su `sha256` en esa lista.
