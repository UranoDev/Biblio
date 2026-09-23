# mibiblio

Mi Biblioteca es el catálogo de los libros que tengo y de los que quiero tener. Existe
porque el inventario de una biblioteca personal se lleva de memoria hasta que deja de
caber en ella: se compra dos veces el mismo título, se presta y no se sabe a quién, y
lo que se subrayó hace cinco años se queda en el libro cerrado.

El registro tiene que costar poco, o no se hace. Por eso la entrada al sistema es el
**Escaneo**: se pasa el código de barras por el Escáner, el ISBN trae la **Ficha** desde
una **Fuente** pública —autores, editorial, año, **Portada**, **Descripción**— y lo
único que queda por
hacer a mano es lo que ninguna fuente sabe: si ya lo leí, cuándo, qué me dejó y qué
frases quiero conservar.

Eso último es el valor real. La Ficha la puede dar cualquier catálogo del mundo; los
**Apuntes** y las **Citas** no existen en ninguna otra parte.

Una parte del acervo se enseña: la **Vitrina** es la cara pública, de sólo lectura, donde
cualquiera puede ver qué libros hay, cuáles están leídos, qué Citas se guardaron y qué
falta por comprar. Lo íntimo —los Apuntes, las fechas, a quién se prestó— no sale de ahí.

## Las superficies

Son cinco, y pesan muy distinto:

- **La captura** — el Escaneo, de pie y frente a una pila de libros. Se usa en ráfagas de
  veinte libros, no de uno; cada paso extra se paga veinte veces.
- **El Alta por título** — para lo que no se puede escanear: los Libros Por comprar, que
  no están en la mano, y los viejos, que no traen código de barras.
- **El Acervo** — privado. Buscar y ver lo que tengo, con su estado de Lectura a la vista,
  y la ficha de cada Libro con lo mío: Apuntes, Citas, Lectura, préstamo.
- **La Vitrina** — pública. Los Libros y sus Citas, navegables por Categoría.
- **La configuración** — dentro del back: qué Modelo se usa y qué proveedores tienen
  llave. Se entra pocas veces, pero cada visita cambia lo que el sistema escribe.

## Language

### El Libro y su Ficha

**Libro**:
Una edición concreta, identificada por su ISBN; es la unidad que registro, que tengo o
que quiero tener.
_Avoid_: obra, título, ejemplar, volumen, item

**ISBN**:
El identificador impreso en el código de barras. Identifica la **edición**, no la obra:
dos ediciones del mismo título son dos ISBN y, por lo tanto, dos Libros. Es único en el
acervo: un mismo ISBN no se registra dos veces.
_Avoid_: código, código de barras (eso es el dibujo, no el número), SKU, clave

**Ficha**:
Los datos bibliográficos que llegan de una Fuente a partir del ISBN — autores,
editorial, año, edición, Portada y, cuando la hay, Descripción. La Ficha no la escribo yo.
_Avoid_: metadatos, datos, información, detalles

**Fuente**:
El catálogo público al que se le consulta un ISBN o un título para armar la Ficha.
_Avoid_: API, proveedor, servicio, catálogo

**Portada**:
La imagen de la cubierta de esa edición. Se descarga de la Fuente al registrar el Libro
y se guarda; también puede subirse a mano.
_Avoid_: imagen, carátula, cover, tapa

**Descripción**:
El texto que cuenta de qué va el Libro, como el de la contraportada. Puede venir de la
Fuente, haberla escrito la IA o haberla escrito yo, y **siempre se guarda de cuál de las
tres viene**.
_Avoid_: sinopsis, resumen (el resumen de lo que a mí me dejó es el Apunte), reseña,
contraportada

**Autor**:
Persona acreditada en la Ficha. Un Libro puede tener varios, y el orden en que vienen
es parte del dato.
_Avoid_: escritor, creador

### Cómo entra un Libro

**Escáner**:
El lector óptico con el que capturo el ISBN. Se comporta como un teclado: escribe el
número en el campo enfocado y manda Enter.
_Avoid_: lector (en este proyecto quien lee es una persona), pistola, scanner

**Escaneo**:
El acto de pasar el código de barras por el Escáner para obtener el ISBN. **Nunca se le
dice «lectura»**: Lectura es haber leído el Libro.
_Avoid_: lectura del código, lectura del ISBN, captura, scan

**Alta por título**:
Dar de alta un Libro escribiendo su título —y opcionalmente su autor—, consultando la
Fuente y eligiendo entre los Candidatos que devuelve. Es la vía para los Libros Por
comprar y para los que no traen código de barras.
_Avoid_: búsqueda (eso es dentro de mi Acervo), buscador, autocompletar

**Candidato**:
Cada edición que la Fuente devuelve en un Alta por título. Se muestra con Portada,
autores, editorial y año, porque sin eso no hay forma de distinguir una edición de otra.
_Avoid_: resultado, sugerencia, opción, match

**Captura manual**:
Registrar un Libro escribiendo sus datos a mano, porque ninguna Fuente lo conoce. Es la
salida de emergencia de las otras dos vías.
_Avoid_: alta manual, registro manual, a mano

### Lo mío

**Lectura**:
El registro de que ya leí el Libro. Puede llevar Fecha de lectura o no llevarla: un
Libro leído hace años, sin saber cuándo, sigue estando leído. Un Libro **sin** Lectura y
uno **con** Lectura sin fecha son dos cosas distintas, y el sistema las distingue.
_Avoid_: leído (como adjetivo suelto, se confunde con la marca), review

**Fecha de lectura**:
Cuándo terminé de leerlo. Opcional, y su ausencia no significa que no lo haya leído.
No sale en la Vitrina.
_Avoid_: fecha de término, fecha de fin

**Apunte**:
Lo que yo escribo sobre el Libro: el comentario o el resumen de lo más relevante de la
Lectura. Son mis palabras, hay uno solo por Libro, y no sale en la Vitrina.
_Avoid_: reseña (no es pública ni califica), nota, comentario, review

**Cita**:
Un fragmento textual del Libro que quiero conservar, con su página si la anoté. Son las
palabras del Libro, no mías. Un Libro puede no tener ninguna o tener muchas.
_Avoid_: quote, frase, subrayado, extracto, fragmento

**Prestado a**:
A quién salió el Libro, en texto libre, con la fecha en que salió. No hay entidad de
personas ni historial: cuando vuelve, se borra. No sale en la Vitrina.
_Avoid_: préstamo (suena a entidad con estados), deudor, persona

### El Acervo y la Vitrina

**Acervo**:
Los Libros que ya tengo.
_Avoid_: colección, inventario, estante, biblioteca (así se llama todo el sistema)

**Por comprar**:
El Libro que registré porque lo quiero y que todavía no tengo.
_Avoid_: wishlist, deseados, pendiente (**pendiente** es ambiguo: puede ser no comprado
o no leído, y nunca queda claro cuál)

**Situación**:
Si un Libro está en el Acervo o Por comprar. Es lo mismo que registrar en los dos casos:
un Libro que compro no se vuelve a capturar, nada más cambia de Situación.
_Avoid_: estado (State es campo del tracker), lista, etapa

**Vitrina**:
La cara pública del sistema, de sólo lectura y sin sesión. Muestra los Libros con su
Ficha, su Portada, su marca de Lectura, sus Citas y sus Categorías, más lo que está Por
comprar.
_Avoid_: perfil, sitio público, catálogo, landing

**Oculto**:
El Libro que no aparece en la Vitrina. Sus Citas tampoco: heredan del Libro. Es la
excepción — un Libro nace visible.
_Avoid_: privado, archivado, borrador, despublicado

**Búsqueda**:
Buscar dentro de lo mío: un Libro del Acervo por título o autor, o una Cita por sus
palabras. **Nunca** se usa para consultar la Fuente — eso es el Alta por título.
_Avoid_: filtro, buscador, consulta

### Las Categorías y el Modelo

**Categoría**:
El tema al que pertenece un Libro. Las propone una IA a partir de la Ficha y yo las
corrijo; un Libro puede tener varias, y en la Vitrina son el modo de navegar.
_Avoid_: género, etiqueta, tag, tema, clasificación

**Modelo**:
Qué inteligencia artificial escribe las Categorías y las Descripciones. Se elige en una
pantalla del back y queda guardado con cada Libro que generó, para saber qué falta
regenerar cuando se cambia.
_Avoid_: IA (es la categoría general, no cuál), proveedor (el proveedor es la empresa; el
Modelo es el que responde), motor

## Decisiones tomadas

Las siete primeras tienen ADR en `docs/adr/`, porque son las costosas de revertir.

1. **El Escáner es un lector USB que se comporta como teclado** — ADR-001. La pantalla de
   captura es un campo de ISBN siempre enfocado que acepta una línea terminada en Enter.
   El foco es requisito funcional: si se pierde, el Escáner teclea al vacío.
2. **La Ficha se arma con Open Library primero y Google Books de respaldo** — ADR-002. Si
   ninguna Fuente conoce el ISBN, se ofrece Captura manual en vez de fallar.
3. **La Vitrina es pública, de sólo lectura y sin indexar** — ADR-003. Qué sale y qué no
   es una regla global por tipo de dato, más el interruptor Oculto por Libro.
4. **La Portada se guarda; el disco es configurable** — ADR-004. Local hoy, S3 el día que
   se prenda, sin tocar el código.
5. **Un ISBN es único en el acervo y no se modelan ejemplares** — ADR-005. Nada de lo que
   se guarda es del ejemplar: Citas, Apuntes y Lectura son de la edición.
6. **Las Categorías las propone una IA, con el Modelo elegido en pantalla** — ADR-006. Se
   piden en cola, nunca durante la captura; el Modelo se guarda en la base y **las llaves
   de API siguen en el entorno**; se puede regenerar por Libro o en masa, y queda
   registrado con qué Modelo se generó cada uno.
7. **La Descripción lleva su procedencia, y la escrita por IA se marca** — ADR-007. Se le
   pide a la IA sólo si ninguna Fuente la trajo; editarla a mano la vuelve mía, y a partir
   de ahí ninguna regeneración la toca.
8. **El Libro es la edición, no la obra.** Es lo que el ISBN identifica y lo que la
   Portada retrata. No hay concepto de obra: el original y su traducción son dos Libros
   sin relación entre sí.
9. **La Lectura distingue tres estados, no dos:** no leído, leído sin fecha, y leído con
   fecha. Cualquier interfaz que sólo ofrezca una casilla de «leído» pierde el de en
   medio.
10. **Acervo y Por comprar son la misma entidad con distinta Situación**, no dos listas
    separadas. Al escanear un Libro que estaba Por comprar, pasa al Acervo solo, con
    deshacer.
11. **Las Citas son del Libro; los Apuntes son míos.** No se mezclan en la misma
    superficie ni en la misma tabla, porque no se buscan igual: una Cita se busca por sus
    palabras, un Apunte por el Libro al que pertenece.
12. **Un Apunte por Libro**, libre y editable en su lugar. Se eligió sobre varios apuntes
    fechados porque partir uno en varios después es trivial, y colapsar varios en uno
    pierde información.
13. **El préstamo es un campo, no una entidad**: «prestado a» en texto libre y la fecha de
    salida. Sin tabla de personas, sin estados, sin recordatorios.
14. **Una sola pantalla de Alta por título** sirve para los Libros Por comprar y para los
    que no traen código de barras. Es el mismo flujo —escribir el título, ver Candidatos,
    elegir edición, guardar— y lo único que cambia es la Situación con la que nace el
    Libro.
15. **Los doscientos libros del acervo entran escaneando, sin importador.** Un importador
    corre una sola vez en la vida del sistema y compite por tiempo con lo que se usa
    siempre.

**Qué Modelo se usa dejó de ser una decisión de arquitectura.** Se elige en una pantalla,
se cambia cuando convenga y se regenera lo que haga falta; lo único que sigue viviendo en
el entorno son las llaves de API, y ahí se quedan: no se guardan en la base ni se capturan
en un formulario.

16. **Google Books se consulta con llave propia**, leída del entorno. Comprobado el
    2026-09-23: sin llave, las peticiones comparten una cuota global que se agota y
    devuelve HTTP 429. Eso no puede tratarse como «no lo conoce» — mandaría a Captura
    manual un libro que la Fuente sí tenía.
17. **No hay límite de Citas públicas por Libro.** Un Libro puede tener ninguna o muchas,
    y todas se muestran en la Vitrina. Se consideró limitarlas —muchas citas del mismo
    libro dejan de ser una cita y se acercan a una reproducción— y se decidió que no:
    cuando algún Libro acumule tantas que incomode, se revisa entonces y no antes.

18. **El Alta por título consulta las Fuentes al revés que el Escaneo.** Google Books
    primero y Open Library de respaldo. Comprobado el 2026-09-23: la búsqueda de Open
    Library devuelve **obras** y de cada una entrega una sola edición elegida por ella,
    mientras que cada volumen de Google Books ya es una edición con su editorial, su año
    y su ISBN. Un Candidato es una edición, así que una obra por renglón no sirve para
    elegir. **Medido sólo del lado de Open Library**: la búsqueda de Google Books no se
    pudo ejecutar ese día por cuota agotada, y lo que se afirma de ella se dedujo de su
    respuesta por ISBN. Ver la ampliación del ADR-002.

## Lo que sigue abierto

**Nada por ahora.** Las decisiones que estaban aquí —qué Escáner, cuál Fuente, si la
Portada se guarda, uno o varios Apuntes, si hace falta el concepto de obra, préstamos,
qué proveedor de IA, la llave de Google Books y el límite de Citas— quedaron todas
cerradas y viven arriba, en § Decisiones tomadas, o en su ADR.

Cuando aparezca una decisión que no se pueda tomar sobre la marcha, va aquí, y la tarea
que dependa de ella se marca `ready-for-human` con la explicación en el comentario.
