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
