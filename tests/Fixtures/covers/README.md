# Las imágenes que los catálogos mandan cuando no tienen portada, y una que sí lo es

**Éstas sí son grabaciones crudas**, a diferencia de las de `../sources`, que están
escritas a mano con la forma de la respuesta. Cada archivo se bajó del catálogo real —el
2026-09-23, salvo los dos últimos, del 24— y está byte por byte como llegó: si se
reescribieran a mano cambiarían el hash y la medición, y la prueba dejaría de probar lo
único que importa acá, que es reconocer **este** archivo.

| Archivo | De dónde salió | Medidas | Bytes |
| --- | --- | --- | --- |
| `open-library-no-cover.gif` | `covers.openlibrary.org/b/isbn/9788437604176-L.jpg` | 1×1 | 43 |
| `google-books-not-available.png` | `books.google.com/books/content?id=pEjU0QEACAAJ&…&zoom=3` | 575×750 | 9 103 |
| `google-books-not-available-128x198.jpg` | el mismo volumen, `zoom=1` | 128×198 | 7 970 |
| `google-books-not-available-128x170.png` | volumen `3TIctgEACAAJ`, `zoom=1` | 128×170 | 1 269 |
| `internet-archive-placeholder.jpg` | `covers.openlibrary.org/b/id/15092702-L.jpg` | 320×220 | 5 045 |
| `open-library-minimal-cover.jpg` | `covers.openlibrary.org/b/id/14656983-L.jpg` | 386×500 | 26 764 |
| `amazon-empty-pixel.gif` | `images-na.ssl-images-amazon.com/images/P/1621364739.01.LZZZZZZZ.jpg` (2026-10-19) | 1×1 | 43 |
| `amazon-6070725360.jpg` | `images-na.ssl-images-amazon.com/images/P/6070725360.01.LZZZZZZZ.jpg` (2026-10-19) | 295×500 | 37 750 |

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

El del templete apareció en producción el 2026-09-24, capturando de verdad: Open Library
sirve sus portadas desde Internet Archive, y cuando el archivo ya no está devuelve el logo
de Internet Archive con un 200. Es **apaisado**, 320×220 — desde que el mínimo bajó a
64 px (también el 2026-09-24) su tamaño ya no lo rechaza por su cuenta, así que lo único
que lo detiene es estar en esta lista. Se queda aquí para que una variante más grande
tampoco se cuele.

La última **no es un marcador: es una portada de verdad**, la de «Show Me the Numbers»
(Stephen Few, ISBN 9780970601971). Está acá porque casi se rechaza como si lo fuera: fondo
blanco casi entero, el título arriba, el autor abajo y una sola figura al centro. Medida el
2026-09-24 sobre este binario, en la rejilla de 32×32 de `App\Covers\Image`:

| | Color parejo | Colores |
| --- | --- | --- |
| Esta portada | 0.915 (937/1024) | 27 |
| `google-books-not-available.png` | 0.929 | 4 |
| `internet-archive-placeholder.jpg` | 0.916 | 9 |

La proporción sola no alcanza —0.915 está del lado de los marcadores, no del 0.60 que
medían las portadas de la muestra del 2026-09-23—, y por eso `isFlat()` cuenta también de
cuántos colores está hecha la imagen: los marcadores llegan a 9 y una docena de portadas
reales, las más minimalistas incluidas, no bajan de 20 (BIB-53).

`App\Covers\Marker` tiene el hash de cada uno de los marcadores. Para agregar el de otro
catálogo: bajarlo acá y poner su `sha256` en esa lista.
