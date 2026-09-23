# mibiblio

Mi Biblioteca es el catálogo de los libros que tengo y de los que quiero tener. Existe
porque el inventario de una biblioteca personal se lleva de memoria hasta que deja de
caber en ella: se compra dos veces el mismo título, se presta y no se sabe a quién, y
lo que se subrayó hace cinco años se queda en el libro cerrado.

El registro tiene que costar poco, o no se hace. Por eso la entrada al sistema es el
**Scan**: se pasa el código de barras por el Scanner, el ISBN trae el Record desde una
Source pública —autores, editorial, año, Cover, Description— y lo único que queda por
hacer a mano es lo que ninguna fuente sabe: si ya lo leí, cuándo, qué me dejó y qué
frases quiero conservar.

Eso último es el valor real. El Record lo puede dar cualquier catálogo del mundo; las
Notes y las Quotes no existen en ninguna otra parte.

Una parte del acervo se enseña: el **Showcase** es la cara pública, de sólo lectura,
donde cualquiera puede ver qué libros hay, cuáles están leídos, qué Quotes se guardaron
y qué falta por comprar. Lo íntimo —las Notes, las fechas, a quién se prestó— no sale de
ahí.

## El idioma

**El código va en inglés; lo que ve una persona va en español.**

En inglés: nombres de clases, componentes, rutas, variables, funciones, tablas, columnas,
enums, archivos y **comentarios**.

En español: absolutamente todo lo que aparece en pantalla —títulos, etiquetas, botones,
mensajes de error, textos de ayuda—, los mensajes de commit, los issues de YouTrack, este
glosario y los ADR.

Por eso cada término de abajo fija **las dos cosas**: cómo se llama en el código y cómo se
dice en la interfaz. No son sinónimos intercambiables — `Book` no se escribe nunca en
pantalla, y «Libro» no se escribe nunca en el código.

## Las superficies

Son cinco, y pesan muy distinto:

- **Scan** — la captura con el Scanner, de pie y frente a una pila de libros. Se usa en
  ráfagas de veinte libros, no de uno; cada paso extra se paga veinte veces.
- **TitleLookup** — para lo que no se puede escanear: los Books que están Wanted, que no
  están en la mano, y los viejos, que no traen código de barras.
- **Shelf** — privado. Buscar y ver lo que tengo, con su estado de Reading a la vista, y
  la ficha de cada Book con lo mío: Note, Quotes, Reading, préstamo.
- **Showcase** — público. Los Books y sus Quotes, navegables por Category.
- **Settings** — dentro del back: qué AiModel se usa y qué Providers tienen llave.

## Language

### El Book y su Record

**Book** · en la interfaz: «Libro»:
Una edición concreta, identificada por su ISBN; es la unidad que registro, que tengo o
que quiero tener.
_Avoid_: Edition, Item, Volume, Title; obra, ejemplar, volumen

**Isbn** · en la interfaz: «ISBN»:
El identificador impreso en el código de barras. Identifica la **edición**, no la obra:
dos ediciones del mismo título son dos ISBN y, por lo tanto, dos Books. Es único en el
sistema: un mismo ISBN no se registra dos veces.
_Avoid_: Code, Barcode (eso es el dibujo, no el número), Sku; clave

**Record** · en la interfaz: «ficha»:
Los datos bibliográficos que llegan de una Source a partir del ISBN — autores, editorial,
año, edición, Cover y, cuando la hay, Description. El Record no lo escribo yo.
_Avoid_: Metadata, Data, Info; metadatos, información

**Source** · en la interfaz: «fuente»:
El catálogo público al que se le consulta un ISBN o un título para armar el Record.
_Avoid_: Api, Provider (Provider es el proveedor de IA), Catalog; servicio

**Cover** · en la interfaz: «portada»:
La imagen de la cubierta de esa edición. Se descarga de la Source al registrar el Book y
se guarda; también puede subirse a mano.
_Avoid_: Image, Thumbnail (la miniatura es una variante del Cover, no otra cosa);
carátula, tapa

**Description** · en la interfaz: «descripción»:
El texto que cuenta de qué va el Book, como el de la contraportada. Puede venir de la
Source, haberla escrito la IA o haberla escrito yo, y **siempre se guarda de cuál de las
tres viene** (ver Origin).
_Avoid_: Summary, Synopsis, Blurb; sinopsis, resumen (el resumen de lo que a mí me dejó
es la Note), contraportada

**Origin** · en la interfaz: no se nombra, se muestra:
De dónde salió una Description: `source`, `ai` u `owner`. Es lo que permite que regenerar
nunca pise lo que escribí yo ni lo que dijo el editor.
_Avoid_: Provenance, Kind; procedencia como nombre de columna

**Author** · en la interfaz: «autor»:
Persona acreditada en el Record. Un Book puede tener varios, y el orden en que vienen es
parte del dato.
_Avoid_: Writer, Creator

### Cómo entra un Book

**Scanner** · en la interfaz: «escáner»:
El lector óptico con el que capturo el ISBN. Se comporta como un teclado: escribe el
número en el campo enfocado y manda Enter.
_Avoid_: Reader (quien lee es una persona), Gun, Device; lector, pistola

**Scan** · en la interfaz: «escaneo»:
El acto de pasar el código de barras por el Scanner para obtener el ISBN. **Nunca se le
dice «lectura»**: Reading es haber leído el Book.
_Avoid_: Read, Capture; lectura del código, captura

**TitleLookup** · en la interfaz: «alta por título»:
Dar de alta un Book escribiendo su título —y opcionalmente su autor—, consultando la
Source y eligiendo entre los Candidates que devuelve. Es la vía para los Books que están
Wanted y para los que no traen código de barras.
_Avoid_: Search (eso es dentro de lo mío), TitleSearch, Autocomplete; búsqueda

**Candidate** · en la interfaz: «edición»:
Cada edición que la Source devuelve en un TitleLookup. Se muestra con Cover, autores,
editorial y año, porque sin eso no hay forma de distinguir una edición de otra.
_Avoid_: Result, Suggestion, Match; resultado, sugerencia

**ManualEntry** · en la interfaz: «captura manual»:
Registrar un Book escribiendo sus datos a mano, porque ninguna Source lo conoce. Es la
salida de emergencia de las otras dos vías.
_Avoid_: ManualCapture, Custom; alta manual

### Lo mío

**Reading** · en la interfaz: «lectura»:
El registro de que ya leí el Book. Puede llevar fecha o no llevarla: un Book leído hace
años, sin saber cuándo, sigue estando leído. Un Book **sin** Reading y uno **con** Reading
sin fecha son dos cosas distintas, y el sistema las distingue — por eso hay un
ReadingStatus con tres valores, no una casilla.
_Avoid_: Read como sustantivo, Finished; leído (como adjetivo suelto)

**Note** · en la interfaz: «apunte»:
Lo que yo escribo sobre el Book: el comentario o el resumen de lo más relevante de la
Reading. Son mis palabras, hay una sola por Book, y no sale en el Showcase.
_Avoid_: Review (no es pública ni califica), Comment, Annotation; reseña, comentario

**Quote** · en la interfaz: «cita»:
Un fragmento textual del Book que quiero conservar, con su página si la anoté. Son las
palabras del Book, no mías. Un Book puede no tener ninguna o tener muchas.
_Avoid_: Excerpt, Passage, Highlight; frase, subrayado, extracto

**LentTo** · en la interfaz: «prestado a»:
A quién salió el Book, en texto libre, con la fecha en que salió. No hay entidad de
personas ni historial: cuando vuelve, se borra. No sale en el Showcase.
_Avoid_: Loan, Borrower, Lending (suenan a entidad con estados); préstamo como entidad

### El Shelf y el Showcase

**Shelf** · en la interfaz: «acervo»:
Los Books que ya tengo, y la pantalla privada donde los veo.
_Avoid_: Collection, Inventory, Library (así se llama todo el sistema); colección

**Status** · en la interfaz: «situación»:
Si un Book está `owned` o `wanted`. Es lo mismo que registrar en los dos casos: un Book
que compro no se vuelve a capturar, nada más cambia de Status.
_Avoid_: State (State es el campo del tracker), Situation, Stage

**Wanted** · en la interfaz: «por comprar»:
El Book que registré porque lo quiero y que todavía no tengo.
_Avoid_: Wishlist, Desired, Pending (**pendiente** es ambiguo: puede ser no comprado o no
leído, y nunca queda claro cuál)

**Showcase** · en la interfaz: «vitrina»:
La cara pública del sistema, de sólo lectura y sin sesión. Muestra los Books con su
Record, su Cover, su Description, su marca de Reading, sus Quotes y sus Categories, más
lo que está Wanted.
_Avoid_: Profile, PublicSite, Catalog, Landing; perfil, catálogo

**Hidden** · en la interfaz: «oculto»:
El Book que no aparece en el Showcase. Sus Quotes tampoco: heredan del Book. Es la
excepción — un Book nace visible.
_Avoid_: Private, Archived, Draft, Unpublished; privado, archivado

**Search** · en la interfaz: «búsqueda»:
Buscar dentro de lo mío: un Book del Shelf por título o autor, o una Quote por sus
palabras. **Nunca** se usa para consultar la Source — eso es el TitleLookup.
_Avoid_: Filter, Query, Lookup (Lookup es contra la Source); filtro

### Las Categories y el AiModel

**Category** · en la interfaz: «categoría»:
El tema al que pertenece un Book. Las propone una IA a partir del Record y yo las corrijo;
un Book puede tener varias, y en el Showcase son el modo de navegar.
_Avoid_: Genre, Tag, Label, Topic; género, etiqueta

**AiModel** · en la interfaz: «modelo»:
Qué inteligencia artificial escribe las Categories y las Descriptions. Se elige en una
pantalla del back y queda guardado con cada Book que generó, para saber qué falta
regenerar cuando se cambia. **Se llama `AiModel` y no `Model`**, porque en Laravel un
Model es otra cosa y confundirlos no tiene arreglo después.
_Avoid_: Model a secas, Engine, Llm; IA (es la categoría general, no cuál)

**Provider** · en la interfaz: «proveedor»:
La empresa o el servidor detrás del AiModel — Gemini, DeepSeek, Ollama. Su llave de API
vive en el entorno, nunca en la base.
_Avoid_: Vendor, Service; Source (Source es el catálogo de libros)

## Decisiones tomadas

Las siete primeras tienen ADR en `docs/adr/`, porque son las costosas de revertir.

1. **El Scanner es un lector USB que se comporta como teclado** — ADR-001. La pantalla de
   captura es un campo de ISBN siempre enfocado que acepta una línea terminada en Enter.
   El foco es requisito funcional: si se pierde, el Scanner teclea al vacío.
2. **El Record se arma con Open Library primero y Google Books de respaldo** — ADR-002. Si
   ninguna Source conoce el ISBN, se ofrece ManualEntry en vez de fallar.
3. **El Showcase es público, de sólo lectura y sin indexar** — ADR-003. Qué sale y qué no
   es una regla global por tipo de dato, más el interruptor Hidden por Book.
4. **El Cover se guarda; el disco es configurable** — ADR-004. Local hoy, S3 el día que se
   prenda, sin tocar el código.
5. **Un ISBN es único y no se modelan ejemplares** — ADR-005. Nada de lo que se guarda es
   del ejemplar: Quotes, Note y Reading son de la edición.
6. **Las Categories las propone una IA, con el AiModel elegido en pantalla** — ADR-006. Se
   piden en cola, nunca durante la captura; el AiModel vive en la base y las llaves de API
   en el entorno; se puede regenerar por Book o en masa.
7. **La Description lleva su Origin, y la escrita por IA se marca** — ADR-007. Se le pide a
   la IA sólo si ninguna Source la trajo; editarla la vuelve `owner`, y desde entonces
   ninguna regeneración la toca.
8. **El Book es la edición, no la obra.** No hay concepto de Work: el original y su
   traducción son dos Books sin relación entre sí.
9. **El Reading distingue tres estados, no dos:** sin leer, leído sin fecha, y leído con
   fecha. Cualquier interfaz que sólo ofrezca una casilla de «leído» pierde el de en medio.
10. **Shelf y Wanted son la misma entidad con distinto Status**, no dos listas separadas.
    Al escanear un Book que estaba Wanted, pasa a `owned` solo, con deshacer.
11. **Las Quotes son del Book; las Notes son mías.** No se mezclan en la misma superficie
    ni en la misma tabla, porque no se buscan igual.
12. **Una Note por Book**, libre y editable en su lugar. Partir una en varias después es
    trivial; colapsar varias en una pierde información.
13. **El préstamo es un campo, no una entidad**: `lent_to` en texto libre y `lent_on`. Sin
    tabla de personas, sin estados, sin recordatorios.
14. **Una sola pantalla de TitleLookup** sirve para los Books que están Wanted y para los
    que no traen código de barras. Lo único que cambia es el Status con el que nace.
15. **Los doscientos libros del acervo entran escaneando, sin importador.** Un importador
    corre una sola vez en la vida del sistema y compite con lo que se usa siempre.
16. **Google Books se consulta con llave propia**, leída del entorno. Sin llave, la cuota
    es compartida y devuelve 429 — que no puede tratarse como «no lo conoce».
17. **No hay límite de Quotes públicas por Book.** Un Book puede tener ninguna o muchas, y
    todas se muestran en el Showcase.
18. **El TitleLookup consulta las Sources al revés que el Scan.** Google Books primero,
    Open Library de respaldo, porque la búsqueda de Open Library devuelve obras y de cada
    una entrega una sola edición elegida por ella. Medido de los dos lados el 2026-09-23;
    ver la ampliación del ADR-002.
19. **El código va en inglés y la interfaz en español**, con cada término fijando las dos
    formas (ver § El idioma). El sistema se renombró completo al tomar esta decisión,
    antes de tener datos: dos idiomas conviviendo era peor que cualquiera de los dos.
20. **Las pruebas no usan respuestas inventadas.** Lo que se guarda en `tests/Fixtures` es
    una grabación real del catálogo, sin recortar, y hay pruebas de contrato aparte que
    verifican que la Source sigue respondiendo como el día que se grabó.

## Lo que sigue abierto

**Nada por ahora.**

Cuando aparezca una decisión que no se pueda tomar sobre la marcha, va aquí, y la tarea
que dependa de ella se marca `ready-for-human` con la explicación en el comentario.
