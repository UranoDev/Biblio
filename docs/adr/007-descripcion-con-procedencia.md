# ADR-007 — La Descripción lleva su procedencia, y la escrita por IA se marca

- **Estado:** Aceptada
- **Fecha:** 2026-09-23

## Contexto

Un Libro sin **Descripción** —el texto de la contraportada— es una portada y un título.
En la Vitrina, que es donde alguien decide si le interesa, esa ausencia se nota.

Lo que las Fuentes dan, comprobado contra sus APIs el 2026-09-23:

- **Open Library no trae descripción en la edición.** El registro de la edición sólo
  apunta a su obra (`works: [{key: /works/OL45804W}]`), y la descripción vive ahí, a una
  petición más de distancia. Traerla no obliga a modelar el concepto de obra: es
  únicamente dónde está guardado el dato.
- **Google Books sí la trae** en `volumeInfo.description` — pero las peticiones anónimas
  comparten una cuota global que ese día ya estaba agotada (HTTP 429).
- Muchas ediciones, sobre todo las mexicanas, **no tienen descripción en ninguna de las
  dos**.

Y como ya hay una IA en el sistema para las Categorías, puede escribir una. Ahí está el
problema: un resumen escrito por un modelo y uno escrito por el editor del libro se ven
exactamente igual en pantalla, y no valen lo mismo.

## Decisión

La Descripción se guarda **con su procedencia**: `source`, `ai` u `owner`.

- Se pide a la IA **sólo si ninguna Fuente la trajo**. La Descripción de la Fuente no se
  pisa nunca.
- **En la Vitrina, una Descripción escrita por IA se marca como tal.** Quien la lee sabe
  que no es la contraportada.
- Editarla a mano la vuelve **mía**, y a partir de ahí ninguna regeneración la toca.
- **Regenerar Descripciones sólo alcanza a las de procedencia `ai`.**

## Consecuencias

- **No se mezcla lo que dijo el editor con lo que infirió un modelo.** Es la misma razón
  por la que una Cita y un Apunte no van juntos: la diferencia no es de formato, es de
  quién lo dijo.
- **Un resumen de IA puede estar equivocado** —confundir dos libros del mismo título,
  inventar una trama— y va a estar en la cara pública del sistema. Marcarlo no lo hace
  correcto, pero cambia lo que el lector supone al leerlo, y deja claro qué corregir.
- **La regla de regenerar sólo lo `ai` es lo que hace segura la regeneración masiva.** Sin
  ella, regenerar doscientos Libros borraría cada Descripción que escribí a mano.
- Para Open Library hay que seguir el enlace a la obra: **una petición más por Libro**, y
  hay que decidir si vale la pena en el momento del registro o al pedirle Categorías.
- **Google Books necesita llave propia** para ser confiable en la cascada. Sin ella,
  devuelve 429 cuando la cuota compartida se agota, y eso se ve como «ninguna Fuente lo
  conoce» cuando en realidad sí lo conocía.

## Qué se agregó el 2026-09-24

La regla «se pide a la IA sólo si ninguna Fuente la trajo» dejó a tres de los siete
Libros capturados con su Descripción en inglés, y no por un error: los catálogos traen
descripción para muchos libros, y para los editados en inglés la traen en inglés. Esas
Descripciones son perfectamente válidas y ninguna regeneración iba a tocarlas nunca. Con
doscientos libros, muchos técnicos, eso es la mayoría y no la excepción.

**Cuando la Descripción que trajo la Fuente no está en español, se le pide a la IA que la
traduzca.** Es una cuarta procedencia, `translated`, y no un matiz de las otras tres.

- **Se traduce, no se reescribe.** Dice lo que el catálogo dijo, en español. No es la
  ocasión de mejorarla ni de resumirla, y la instrucción del agente lo dice así.
- **Con el vocabulario de la materia.** Las Categorías del propio Libro viajan en la
  petición: «software construction» no se dice igual en el habla de quien programa que en
  la de quien vende, y una traducción literal suena a que no la escribió nadie del oficio.
- **El original se guarda.** Una traducción se puede rehacer; un texto tirado, no. Y
  rehacerla parte siempre del original, nunca de la traducción anterior: traducir una
  traducción aleja el texto del catálogo una vuelta más en cada corrida.
- **El idioma se decide antes de pedir nada**, contando palabras funcionales del texto. Lo
  que ya está en español no gasta cuota y no corre el riesgo de volver distinto. Sin
  evidencia la respuesta es «español»: equivocarse en esa dirección deja el texto como
  está, y equivocarse en la otra lo reescribe con un modelo.
- **La Vitrina la marca distinto de la escrita por IA.** El contenido es del catálogo y la
  redacción en español la hizo una máquina: una traducción puede errarle al matiz, no al
  hecho, y quien lee debería poder notar la diferencia.
- **Lo que no cambia:** la Descripción que escribí yo no se toca en ningún idioma, y la
  que la Fuente trajo en español sigue sin que ninguna regeneración la alcance.

La consecuencia que hay que tener presente es que **una Descripción traducida cuesta dos
peticiones y no una**: la de las Categorías y la suya. Sólo la pagan los Libros que la
necesitan, y la pantalla de regeneración dice cuántos son antes de lanzar.
