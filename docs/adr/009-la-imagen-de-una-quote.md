# ADR-009 — La imagen de una Quote es la misma tarjeta, rasterizada fuera

- **Estado:** Aceptada
- **Fecha:** 2026-09-23

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

Tres razones, en orden de peso:

1. **El volumen es diminuto y el gasto es de una sola vez.** Mil citas son mil créditos:
   nueve dólares. Después, un puñado al mes conforme se capturan citas nuevas, dentro de
   la capa gratuita. No es una suscripción, es una compra chica.
2. **El servicio es un generador, no un servidor.** Las imágenes se guardan en disco
   propio y se sirven desde ahí. Si el servicio cierra, las imágenes existentes siguen
   funcionando y sólo cambia quién genera las nuevas. Ése era el riesgo que parecía
   descalificarlo, y con caché no existe.
3. **Chromium en el VPS son 300 MB, una dependencia que mantener, y un par de segundos de
   CPU por imagen** en el mismo servidor que sirve el sitio.

**Se descartó Satori** —la opción gratis y sin navegador— por un detalle que lo vuelve
inservible para esta tarjeta: **no soporta `background-blend-mode`**, que es exactamente
el mecanismo con que el papel toma su tono cálido. Y no falla con un error: simplemente
se ve distinto. Tampoco lee WOFF2, que es el formato en que Bunny sirve las tipografías.

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
