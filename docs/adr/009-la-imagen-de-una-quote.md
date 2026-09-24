# ADR-009 — La imagen de una Quote es la misma tarjeta, rasterizada fuera

- **Estado:** Aceptada
- **Fecha:** 2026-09-23 · **Corregida:** 2026-09-23

## Contexto

La página de una Quote es la herramienta principal para compartir. Para que sirva hacen
falta dos cosas distintas, y por razones distintas:

- **El enlace** basta para X, Facebook y WhatsApp: leen `og:image` y arman la tarjeta
  solos al pegarlo.
- **Una imagen descargable** existe por Instagram, que **no lee etiquetas**. Ahí no hay
  forma de que una publicación tome la imagen de un enlace: hay que subirla.

Y hacía falta decidir cómo se dibuja esa imagen. Diseñar una tarjeta aparte para redes
significaría mantener el diseño dos veces, y en seis meses las dos versiones no se
parecerían. La alternativa —y lo que se decidió— es que **la imagen sea esta misma
página, rasterizada**: lo que ve quien recibe el enlace es lo mismo que verá al abrirlo.

Eso deja una sola pregunta: con qué se rasteriza.

## Decisión

**Con un servicio externo, [html2img](https://html2img.com), detrás de un seam.**

El generador es una interfaz con una implementación, igual que las Sources. Cambiarlo por
un navegador propio —Browsershot sobre el Chromium del VPS— no debe tocar ni la tarjeta ni
las imágenes ya generadas.

- **Los dos formatos se generan al guardar la Quote, en la cola**: horizontal 1200×630
  para `og:image`, vertical 1080×1350 para Instagram.
- **Se regeneran cuando cambia cualquier cosa que aparece en la imagen**: el texto de la
  Quote, su página, el título del Book, sus Authors o su Cover.
- **Se guardan en el disco configurado** (ADR-004) y **se sirven por una ruta que
  comprueba la visibilidad**, nunca como archivos sueltos.
- **La URL de la imagen lleva una firma de su contenido.**

## Por qué un servicio y no el navegador propio, teniendo root

**Corrección de la primera versión de este ADR.** Se escribió diciendo que el servicio
costaba «nueve dólares, una sola vez», y que ésa era la razón principal para preferirlo
sobre instalar Chromium. **El dato era falso.** html2img cobra **por mes**: los créditos
se reinician cada mes, no se acumulan, y no hay recarga automática. Con los dos formatos
por Quote, el acervo inicial de unas mil citas son **dos mil renders** — dos meses de
plan sólo para empezar.

Con el número correcto, la comparación real es **$108 al año, indefinidamente, contra una
instalación única en un VPS propio con root**. La decisión se volvió a mirar con ese dato
sobre la mesa y **se confirmó el servicio**, pero por otra razón:

**No tener Chromium en el servidor vale su renta.** Son 300 MB, una dependencia del
sistema que hay que mantener y actualizar, y un par de segundos de CPU por imagen en la
misma máquina que sirve el sitio. Pagar por no administrar eso es una decisión legítima, y
es la que se tomó a sabiendas.

Lo que **sigue siendo cierto** del razonamiento original, y es lo que hace reversible la
decisión: **el servicio es un generador, no un servidor.** Las imágenes se guardan en
disco propio y se sirven desde ahí. Si el servicio cierra, sube de precio o deja de
convenir, las imágenes existentes siguen funcionando y sólo cambia quién genera las
nuevas — un cambio detrás del seam, sin tocar la tarjeta.

**Se descartó Satori** —la opción gratis y sin navegador— por un detalle que lo vuelve
inservible para esta tarjeta: **no soporta `background-blend-mode`**, que es exactamente
el mecanismo con que el papel toma su tono cálido. Y no falla con un error: simplemente
se ve distinto. Tampoco lee WOFF2, que es el formato en que Bunny sirve las tipografías.

## Qué pasa cuando el renderizador no responde

Con una cuota mensual esto deja de ser hipotético: **se van a acabar los créditos**, y va
a pasar a mitad de una regeneración masiva. Así que el comportamiento ante la falla es
parte de la decisión, no un detalle de implementación.

- **Guardar una Quote nunca espera a la imagen.** La Quote queda guardada y la imagen se
  encola.
- **Sin imagen, `og:image` cae en el Cover del Book**, que siempre existe (ADR-008). Un
  enlace compartido nunca sale pelado: sale con la portada en vez de la tarjeta.
- **Al agotarse los créditos, la cola se detiene; no reintenta mil veces.** Un error de
  cuota no es un error transitorio: reintentar es quemar la cuota del mes siguiente en el
  instante en que renueve. Se para, se avisa, y se retoma cuando haya con qué.
- El botón de descarga dice que la imagen todavía no está lista, en vez de entregar un
  archivo roto.

## Consecuencias

- **Fotografiar un libro regenera todas las imágenes de sus citas.** El reemplazo gradual
  de Covers generados por fotos propias (ADR-008) no es un cambio cosmético: cada libro
  fotografiado invalida las imágenes compartibles de sus Quotes. Sin esa regla, la
  biblioteca se llena en silencio de imágenes viejas y sólo se descubre cuando alguien
  comparte una.
- **Generar al guardar, y no al vuelo, es lo que protege el momento que importa.** El
  primero en pedir una página compartida suele ser el robot de la red social, con prisa y
  con memoria: un enlace que salió sin imagen se queda sin imagen durante días.
- **Servir por ruta cuesta velocidad y compra corrección.** Un archivo estático es más
  rápido, pero sobrevive a que su Book se oculte. Es el mismo principio del ADR-003: lo
  que se equivoca hacia afuera se equivoca en silencio.
- **Las tipografías tienen que llegar al renderizador.** Si las busca por red y falla, la
  imagen sale con una tipografía de respaldo y nadie se entera.
- La llave del servicio vive en el entorno, como todas.
