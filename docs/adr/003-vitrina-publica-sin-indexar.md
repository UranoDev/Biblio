# ADR-003 — La Vitrina es pública, de sólo lectura y sin indexar

- **Estado:** Aceptada
- **Fecha:** 2026-09-22

## Contexto

El sistema es de un solo dueño y nadie más tiene cuenta. Pero el acervo tiene valor para
otros: enseñar qué has leído, compartir una Cita, y que quien quiera regalarte un libro
vea lo que está Por comprar.

Había tres caminos: todo detrás del login; una cara pública de sólo lectura; o todo
privado con enlaces sueltos para compartir una pieza a la vez. Y una vez elegido el
público, quedaban dos preguntas que no se pueden posponer: **qué sale** y **con qué
mecanismo se decide**.

## Decisión

Existe la **Vitrina**: una cara pública, sin sesión y de sólo lectura.

**Sale:** la Ficha, la Portada, la marca de Lectura, las Citas, las Categorías y los
Libros Por comprar.

**No sale:** la Fecha de lectura, el Apunte y el «prestado a».

La frontera es una **regla global por tipo de dato**, más un interruptor **Oculto** a
nivel de Libro. Las Citas heredan del Libro: si el Libro está Oculto, sus Citas tampoco
aparecen. **Un Libro nace visible.**

La Vitrina **no se indexa**.

## Consecuencias

- **Toda consulta que alimente la Vitrina filtra por Oculto.** Un filtro olvidado no rompe
  nada, no avisa, y se equivoca hacia afuera. Por eso cada superficie pública lleva su
  prueba de que un Libro Oculto no aparece en ella — incluidas las listas por Categoría.
- **No hay interruptor por Cita.** Si una Cita no debe verse, se oculta el Libro entero o
  se borra la Cita. Fue deliberado: cada bandera extra multiplica las consultas que deben
  acordarse de filtrar.
- **El default de no indexar se eligió por la asimetría**, no por prudencia genérica:
  prender la indexación después es quitar una etiqueta; sacar de Google lo ya indexado es
  lento, parcial y queda en cachés ajenos.
- **La Vitrina se escribe para quien no eres tú.** «Por comprar» es un nombre del glosario
  interno; en la cara pública necesita una palabra que un visitante reconozca.
- Un Apunte nunca es público. Esa es la razón por la que se puede escribir sin pensar en
  nadie: en el momento en que se escribe para afuera, deja de servir para lo que es.
