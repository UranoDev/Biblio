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

En español: todo lo que **lee una persona usando el sistema** —títulos, etiquetas,
botones, avisos, errores de validación, textos de ayuda—, más los mensajes de commit, los
issues de YouTrack, este glosario y los ADR.

La línea divisoria no es «texto sí, código no», es **quién lo lee**. Un mensaje de
excepción o una línea de log los lee quien programa, al lado del código y mezclados con
los de Laravel: **van en inglés**. Un aviso en pantalla lo lee el dueño de la biblioteca:
va en español. Cuando una excepción interna sí acaba mostrándose, no se muestra su
mensaje — se traduce en la superficie, como hace `ScanOutcome` con sus etiquetas.

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
La imagen de la cubierta de esa edición. Se descarga de la Source al registrar el Book,
se puede subir a mano, y **si no llega ninguna que sirva, se genera** una pieza
tipográfica con el título y los Authors (ADR-008). Todo Book tiene Cover, y el suyo lleva
su Origin.
_Avoid_: Image, Thumbnail (la miniatura es una variante del Cover, no otra cosa);
carátula, tapa

**Description** · en la interfaz: «descripción»:
El texto que cuenta de qué va el Book, como el de la contraportada. Puede venir de la
Source, haberla escrito la IA, haberla escrito yo, o ser la de la Source **traducida** al
español por la IA, y **siempre se guarda de cuál de las cuatro viene** (ver Origin). La
que vino de la Source en otro idioma guarda también su texto original.
_Avoid_: Summary, Synopsis, Blurb; sinopsis, resumen (el resumen de lo que a mí me dejó
es la Note), contraportada

**Origin** · en la interfaz: no se nombra, se muestra:
De dónde salió algo que puede venir de varias partes. La Description lo lleva —`source`,
`ai`, `owner` o `translated`— y el Cover también —`source`, `generated` u `owner`—. Es lo
que permite que regenerar nunca pise lo que escribí yo ni lo que dijo el editor, y lo que
convierte «los Covers generados» en la lista de lo que falta fotografiar. `translated` no
es un matiz de los otros tres: dice que el contenido es del catálogo y que el español lo
escribió una máquina.
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

**PageLabel** · en la interfaz: «página»:
La página de una Quote tal como la imprime el libro: `29` en el cuerpo, `xiv` en las
preliminares. Se guarda como se escribió; el orden sale de dos cosas derivadas de la
etiqueta —si es preliminar y cuánto vale—, porque `xiv` no va entre la 13 y la 15 sino
antes de la 1.
_Avoid_: PageNumber (una preliminar no es un número), Folio; número de página

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

**AiBadge** · en la interfaz: no se nombra:
La marca que dice que una máquina escribió eso: hoy una Description generada,
mañana una traducida (BIB-45) o unas Categories. Es un trazo con su texto al
lado, y el texto es opcional — donde se repite basta el dibujo. Lo que nunca se
omite es el significado: sin palabras a la vista, la etiqueta se sigue anunciando
a un lector de pantalla. **Dice una sola cosa, que lo escribió una máquina**;
para avisar otra cosa va otro elemento, porque una marca que carga con todos los
avisos deja de significar alguno.
_Avoid_: AiNote, GeneratedMark; no confundir con **GenerationMark**, que es otra
cosa: aquél dice en qué va el Book con la IA —en la cola, falló— y vive en el
back office; éste habla del resultado y lo lee quien visita

**ImageRenderer** · en la interfaz: no se nombra:
Lo que convierte la tarjeta de una Quote en una imagen para compartir. Hoy es un servicio
externo —html2img— detrás de una interfaz; mañana puede ser un navegador propio, sin que
cambie nada más (ADR-009).
_Avoid_: Screenshot, Snapshot, Rasterizer; «el servicio» a secas, que ya significa otra
cosa en este glosario

**Provider** · en la interfaz: «proveedor»:
La empresa o el servidor detrás del AiModel — Gemini, DeepSeek, Ollama. Su llave de API
vive en el entorno, nunca en la base.
_Avoid_: Vendor, Service; Source (Source es el catálogo de libros)

**Settings** · en la interfaz: «Ajustes»:
La pantalla del back que reúne el perfil, la seguridad, la apariencia, el AiModel,
la regeneración, las imágenes para compartir y los trabajos fallidos. Se entra desde la
barra lateral, y cada subpantalla conserva su propio nombre (BIB-56).
_Avoid_: Configuration, Preferences, Options; «configuración», «preferencias», «opciones»

## Dirección visual

**Marginalia.** Un libro leído deja marcas en los márgenes, y el sistema se trata como
ese cuaderno: papel, letra de máquina para lo que se cita, mucho silencio alrededor, y la
portada asomándose apenas —recortada por el borde e inclinada **6 grados en contra del
reloj**, como un lomo que sobresale del librero.

La regla que manda sobre todas: **las portadas son el color.** Doscientos libros traen su
propio caos cromático y no se controla, así que el sistema es un escenario neutro. Por eso
no hay color de acento en la interfaz: el acento son los libros.

### El papel

El fondo es papel, no blanco: una textura tenue multiplicada sobre `#FAF6EE`. Blanco puro
ensucia los blancos de las portadas y deslumbra; el papel las hace ver intencionales.

Ningún neutro es puro y todos van corridos al cálido. Un gris neutro sobre papel cálido se
ve sucio, no sobrio — es el error clásico al agregarle textura a una paleta que no la
esperaba.

| Token | Valor | Para qué |
| --- | --- | --- |
| Papel | `#FAF6EE` + textura multiplicada | el fondo de todo |
| Tinta | `#1A1A18` | texto principal; nunca negro puro |
| Tinta tenue | `#6B6459` | secundarios, 4.6:1 sobre el papel |
| Regla | `#D4CEC0` | la línea vertical de la Quote |
| División | `#E2DDD1` | separadores entre bloques |
| Filo de Cover | `rgba(26,26,24,0.22)` | ver abajo |
| Enlace | `#8A5A2B` | lo único que se colorea |

### Las tipografías

**IBM Plex Mono** para las Quotes y para cualquier dato que se lea como dato —un ISBN, un
año—. **Instrument Sans** para todo lo demás: títulos, etiquetas, interfaz. Instrument Sans
ya está en el proyecto, servida por Bunny desde `vite.config.js`; la monoespaciada se
agrega por la misma vía.

La jerarquía no es de tamaño sino de peso: **la Quote pesa más que todo lo demás junto.**
El título del Book y los Authors son pie de foto, no encabezado. Si el título compite con
la cita, se perdió el efecto.

| Rol | Tipografía | Tamaño y forma |
| --- | --- | --- |
| Quote | Plex Mono 400 | 19 px / 1.78, con regla vertical a la izquierda |
| Quote corta | Plex Mono 400 | 24 px / 1.7 |
| Quote larga | Plex Mono 400 | 16 px / 1.82 |
| Título de Book | Instrument Sans 700 | 17 px, tracking `-0.01em` |
| Authors | Instrument Sans 400 | 11 px, mayúsculas, tracking `0.2em` |
| Encabezado de sección | Instrument Sans 400 | 10 px, mayúsculas, tracking `0.34em` |

**La Quote tiene tres tamaños, y no los elige la pantalla.** Una cita de seis palabras y
una de cuarenta van en la misma tarjeta: la corta puesta en 19 px flota en medio del
papel, y la larga en 19 px empuja al Libro fuera del cuadro. El tamaño se lee de la cita
—`App\Quotes\QuoteCard` cuenta los renglones, contando los que la cita trae por su
cuenta, que es lo que hace que un poema no se componga en tamaño de epigrama—. **La cita
larga baja de escalón; no se recorta nunca**: recortarla es recortar lo único que la
tarjeta existe para mostrar, y además la tarjeta se comparte como imagen (ADR-009), donde
un «leer completa» no lleva a ninguna parte.

### El Cover

Se muestra **recortado por el borde e inclinado 6 grados en contra del reloj**, con
sombra suave. Esos seis grados están medidos de la pieza que originó la dirección: lo
suficiente para que se lea como un objeto y no como un rectángulo pegado. Lo
que se ve es un fragmento grande, nunca la portada completa encogida: ésa es la diferencia
entre verse editorial y verse como ficha de catálogo.

**En la retícula del Acervo el Cover va completo**, con su inclinación, su sombra y su
filo, pero sin recortar. Es la única superficie donde la portada no es la imagen sino el
modo de encontrar un libro entre doscientos, y ahí reconocerla pide verla entera; además,
recortar un 15 % por lado muerde los márgenes de la pieza generada y se come su título. El
fragmento grande es de las superficies donde el Cover sí es la imagen —la ficha, la
tarjeta de una Quote—.

**Lo que se recorta es la fotografía, no la pieza generada.** En la tarjeta de una Quote
el Cover guardado se sale por el borde derecho y por abajo; la pieza generada va entera,
con su inclinación y su sombra. Es la misma razón por la que la retícula no la recorta:
recortar una fotografía se ve editorial y recortar tipografía nada más parte palabras, y
las que parte son el título que el pie de la tarjeta ya dice.

**Sobre el Cover de un libro leído va el sello**, arriba a la derecha: doble aro, tinta,
la palabra `LEÍDO` adentro, y girado con la portada para que el texto quede paralelo a
ella. Va arriba porque la pieza generada cuelga su título y sus autores de abajo. Trae su
propio campo de papel, traslúcido: la tinta oscura sobre una fotografía oscura no se ve, y
el contraste no puede depender de qué libro tocó — se mide contra lo más oscuro que ese
campo puede quedar, que es sobre una portada negra.

**Todo Cover lleva un filo de un píxel.** No es adorno: las portadas claras —y son
comunes— se disuelven en el papel sin él. Comprobado con portadas reales.

Y como el Cover siempre existe (ADR-008), la retícula nunca tiene huecos: lo que antes
era un espacio punteado ahora es una pieza tipográfica generada, con el color derivado del
ISBN y sacado de esta misma paleta.

El campo de esa pieza son diez tonos de tela y cartón —`#C8B99C`, `#D2C2A7`, `#BFA88F`,
`#BDB9A6`, `#D3B9A3`, `#B9A492`, `#CABBAB`, `#AD9C86`, `#C9AE8C`, `#B8AE9A`—, todos
corridos al cálido como el resto de los neutros y todos lo bastante claros para que la
Tinta se lea encima. No son acentos: el acento sigue siendo la portada real que llegue, y
estos tonos tienen que poder estar a su lado sin pelearse. Viven en `GeneratedCover`
porque el dibujo se arma en el servidor; lo que los ata a esta paleta es la prueba, que
los mide contra los tokens de la hoja de estilo.

## Decisiones tomadas

Las nueve primeras tienen ADR en `docs/adr/`, porque son las costosas de revertir.

1. **El Scanner es un lector USB que se comporta como teclado** — ADR-001. La pantalla de
   captura es un campo de ISBN siempre enfocado que acepta una línea terminada en Enter.
   El foco es requisito funcional: si se pierde, el Scanner teclea al vacío.
2. **El Record se arma con Open Library primero y Google Books de respaldo** — ADR-002. Si
   ninguna Source conoce el ISBN, se ofrece ManualEntry en vez de fallar.
3. **El Showcase es público, de sólo lectura y se indexa** — ADR-003 y su ampliación del
   2026-09-24. Es la raíz del sitio. Qué sale y qué no es una regla global por tipo de
   dato, más el interruptor Hidden por Book; ninguna pantalla pública escribe su propia
   consulta, todas salen de `App\Showcase\Showcase`, que empieza por `visible`.
4. **El Cover se guarda; el disco es configurable** — ADR-004. Local hoy, S3 el día que se
   prenda, sin tocar el código.
5. **Un ISBN es único y no se modelan ejemplares** — ADR-005. Nada de lo que se guarda es
   del ejemplar: Quotes, Note y Reading son de la edición.
6. **Las Categories las propone una IA, con el AiModel elegido en pantalla** — ADR-006. Se
   piden en cola, nunca durante la captura; el AiModel vive en la base y las llaves de API
   en el entorno; se puede regenerar por Book o en masa.
7. **El Cover siempre existe: si no llega uno bueno, se genera** — ADR-008. Medido: de
   cuatro ISBN reales, ninguno dio un Cover utilizable. El generado es tipográfico, su
   color se deriva del ISBN, y su Origin lo distingue del real — con eso, «los Covers
   generados» son la cola de lo que falta fotografiar.
8. **La imagen de una Quote es la misma tarjeta, rasterizada fuera** — ADR-009. La
   genera un servicio externo detrás de un seam, los dos formatos al guardar y en la cola,
   y se regenera cuando cambia cualquier cosa que aparece en ella — incluida la Cover, que
   es lo que vuelve cara la sustitución gradual por fotos propias.
9. **La Description lleva su Origin, y la escrita por IA se marca** — ADR-007. Se le pide a
   la IA sólo si ninguna Source la trajo; editarla la vuelve `owner`, y desde entonces
   ninguna regeneración la toca.
10. **El Book es la edición, no la obra.** No hay concepto de Work: el original y su
   traducción son dos Books sin relación entre sí.
11. **El Reading distingue tres estados, no dos:** sin leer, leído sin fecha, y leído con
   fecha. Cualquier interfaz que sólo ofrezca una casilla de «leído» pierde el de en medio.
12. **Shelf y Wanted son la misma entidad con distinto Status**, no dos listas separadas.
    Al escanear un Book que estaba Wanted, pasa a `owned` solo, con deshacer.
13. **Las Quotes son del Book; las Notes son mías.** No se mezclan en la misma superficie
    ni en la misma tabla, porque no se buscan igual.
14. **Una Note por Book**, libre y editable en su lugar. Partir una en varias después es
    trivial; colapsar varias en una pierde información.
15. **El préstamo es un campo, no una entidad**: `lent_to` en texto libre y `lent_on`. Sin
    tabla de personas, sin estados, sin recordatorios.
16. **Una sola pantalla de TitleLookup** sirve para los Books que están Wanted y para los
    que no traen código de barras. Lo único que cambia es el Status con el que nace.
17. **Los doscientos libros del acervo entran escaneando, sin importador.** Un importador
    corre una sola vez en la vida del sistema y compite con lo que se usa siempre.
18. **Google Books se consulta con llave propia**, leída del entorno. Sin llave, la cuota
    es compartida y devuelve 429 — que no puede tratarse como «no lo conoce».
19. **No hay límite de Quotes públicas por Book.** Un Book puede tener ninguna o muchas, y
    todas se muestran en el Showcase.
20. **El TitleLookup consulta las Sources al revés que el Scan.** Google Books primero,
    Open Library de respaldo, porque la búsqueda de Open Library devuelve obras y de cada
    una entrega una sola edición elegida por ella. Medido de los dos lados el 2026-09-23;
    ver la ampliación del ADR-002.
21. **El código va en inglés y la interfaz en español**, con cada término fijando las dos
    formas (ver § El idioma). El sistema se renombró completo al tomar esta decisión,
    antes de tener datos: dos idiomas conviviendo era peor que cualquiera de los dos.
22. **La dirección visual es Marginalia** (ver § Dirección visual): papel en vez de
    blanco, Plex Mono para las Quotes, Instrument Sans para el resto, Cover recortado e
    inclinado con su filo, y **sin color de acento en la interfaz** — el acento son las
    portadas.
23. **Las pruebas no usan respuestas inventadas.** Lo que se guarda en `tests/Fixtures` es
    una grabación real del catálogo, sin recortar, y hay pruebas de contrato aparte que
    verifican que la Source sigue respondiendo como el día que se grabó.
24. **El Reading se ve como un sello sobre el Cover y una etiqueta arriba del título.**
    El sello —doble aro, la palabra `LEÍDO`— dice que el libro se leyó y **no lleva el
    año**: soltarlo es lo que dejó la palabra en un tamaño que se lee de lejos. La
    etiqueta dice el estado completo —`Por leer`, `Leído`, `Leído, 2024`—, y ahí vive el
    año. En la retícula va el sello y nada más, así que ahí «leído en 2019» y «leído sin
    fecha» se ven igual: la retícula contesta si lo leí y la ficha contesta cuándo.
25. **La página de una Quote es una etiqueta, y el orden se deriva de ella.** Se captura
    `29` o `xiv` en un solo campo y se guarda como se escribió; al guardar se derivan dos
    columnas —si es preliminar y cuánto vale— y de ésas sale el orden, nunca de la
    etiqueta. Una preliminar va antes de la página 1, no entre la 13 y la 15.
26. **La Description que la Source trajo en otro idioma se traduce, no se reescribe** —
    BIB-45, ampliación del ADR-007. El idioma se decide contando palabras funcionales
    antes de pedir nada, así que una que ya está en español no cuesta una llamada. La
    traducción se le pide con las Categories del Book como vocabulario de la materia,
    **el texto original se conserva**, y rehacerla parte siempre de ese original y nunca
    de la traducción anterior. Su Origin es `translated`, que la Vitrina marca distinto
    de la escrita por IA.

Cuando aparezca una decisión que no se pueda tomar sobre la marcha, se abre otra vez una
sección de lo que sigue abierto al final de este archivo, y la tarea que dependa de ella
se marca `ready-for-human` con la explicación en el comentario.

## Lo que sigue abierto

**Qué Fuente va primero en el TitleLookup** (BIB-44). La decisión 20 puso a Google Books
primero apoyándose en que cada volumen trae editorial, año, ISBN y Cover en la misma
respuesta. Al grabar las respuestas reales (BIB-24) se midió lo contrario: buscando «Pedro
Paramo» de Rulfo, de sus 20 volúmenes sólo 4 traen editorial y sólo 4 traen Cover, mientras
que Open Library trae editorial en sus 6 resultados y Cover en 4. Dieciséis de veinte
Candidates llegan sin los dos datos con los que una persona elige una edición.

El defecto de Open Library sigue siendo real —busca obras y de cada una entrega una sola
edición, elegida por ella—, así que no es cambiar el orden y ya. Toca el ADR-002, que ya
está tomado, y las dos grabaciones que sostienen la medición están en el repositorio.
