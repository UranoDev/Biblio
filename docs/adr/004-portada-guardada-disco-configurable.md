# ADR-004 — La Portada se guarda, en un disco configurable

- **Estado:** Aceptada
- **Fecha:** 2026-09-22

## Contexto

Con la Vitrina, el acervo **es** una retícula de portadas: la Portada deja de ser un
adorno de la ficha y pasa a ser el elemento visual dominante de la cara pública.

Enlazar a la imagen que devuelve la Fuente no cuesta trabajo ni disco. Guardarla obliga a
descargarla, a decidir dónde vive y a lidiar con las descargas que fallan.

## Decisión

La Portada **se descarga al registrar el Libro y se guarda**, en el disco de Laravel que
indique `FILESYSTEM_DISK`: `local` hoy, `s3` el día que se prenda, **sin tocar código** —
ese día sólo se instala el driver (`league/flysystem-aws-s3-v3`) y se cambia el `.env`.

Se puede **subir o reemplazar una Portada a mano**. Si la descarga falla, el Libro queda
registrado sin Portada y el intento se puede repetir: una imagen que no llegó nunca
bloquea un registro.

## Consecuencias

- **Enlazar habría puesto a un tercero a servir tus imágenes a tus visitantes.** Open
  Library y Google Books limitan por volumen, y el día que limiten la Vitrina se ve rota
  —con huecos, no con un error—, que parece descuido tuyo y no falla de nadie más.
- **La Portada es la única parte de la Ficha que se pudre sola.** El título y el autor que
  copiaste siguen siendo correctos dentro de diez años; el enlace a la imagen, no.
- **La subida manual no es opcional:** la Captura manual no trae ninguna imagen, y algunas
  Fuentes devuelven miniaturas inservibles.
- Hay que decidir tamaños al guardar. Una retícula de doscientas portadas a tamaño
  original es una página lenta, y eso sólo se puede arreglar si la imagen es tuya.
- **En los respaldos, las Portadas son molestia y no pérdida**: se pueden volver a
  descargar de la Fuente. Lo irreemplazable es la base — Apuntes y Citas no están en
  ningún otro lado.
