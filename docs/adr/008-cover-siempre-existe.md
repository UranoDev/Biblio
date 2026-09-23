# ADR-008 — El Cover siempre existe: si no llega uno bueno, se genera

- **Estado:** Aceptada
- **Fecha:** 2026-09-23

## Contexto

El ADR-004 decidió guardar el Cover en vez de enlazarlo, y dio por hecho que llegaría
de la Source casi siempre, con la subida manual como salida de emergencia. **Eso resultó
falso, y está medido.**

Probando cuatro ISBN reales el 2026-09-23, ninguno dio un Cover utilizable, y cada uno
falló distinto:

| ISBN | Qué llegó |
| --- | --- |
| `9781544550749` | JPEG de 575×750 que dice «image not available» |
| `9786073106382` (edición mexicana) | nada: Google sin `imageLinks`, Open Library 1×1 px |
| `9781591845577` (la misma obra en inglés) | 313×500 real, pero es **el escaneo de la portadilla interior** |
| `0735619670` | la portada correcta, pero **162×198 y con marco blanco** |

Cuatro de cuatro. Con doscientos libros, y con acervo mayormente en español, el Cover
que llega de una Source pública **es la excepción, no la regla**.

La otra salida —fotografiar cada libro— es posible pero no durante la captura: la ráfaga
de doscientos libros no se detiene a encuadrar una foto por libro. Es trabajo posterior y
gradual.

Queda entonces una pregunta que no se puede posponer: **qué se ve en la retícula mientras
tanto.** Doscientos huecos punteados no son un acervo, son una lista de tareas.

## Decisión

**Todo Book tiene Cover.** Si ninguna Source entrega uno que pase los chequeos de calidad
(BIB-28), **se genera uno**: una pieza tipográfica con el título y los Authors sobre un
campo de color, en la proporción de un libro.

El color **se deriva del ISBN o del título**, de forma determinista: el mismo Book tiene
siempre el mismo color, y la retícula se ve variada sin ser aleatoria. Los colores salen
de la paleta de la dirección visual, no de tonos al azar: tienen que convivir con los
Covers reales que sí lleguen.

**El Cover lleva su Origin**, igual que la Description (ADR-007): `source`, `generated` u
`owner`. Un Cover generado **nunca se confunde con el real** — ni a la vista, porque es
tipográfico y no fotográfico, ni en los datos, porque el Origin lo dice.

El Origin `owner` es la foto que tomaré después, libro por libro, cuando haya tiempo.

## Consecuencias

- **La retícula siempre se ve completa.** Ésa era la razón de ser de esta decisión: un
  acervo con doscientos huecos no se puede mirar, y lo que no se mira no se usa.
- **El Cover generado es una cola de trabajo, no un parche.** «Los Books cuyo Cover es
  `generated`» es exactamente la lista de lo que falta fotografiar, ordenable y contable.
  Sin el Origin, esa lista no existiría y la sustitución gradual sería imposible de
  seguir.
- **La subida manual deja de ser la salida de emergencia.** Es la vía por la que va a
  entrar la mayoría de los Covers buenos, y con el tiempo. Merece estar donde se vea.
- **Un Cover generado no se regenera solo cuando llega uno real.** Si algún día se
  reintenta contra las Sources y llega uno bueno, reemplaza al generado; pero **nunca
  reemplaza a uno con Origin `owner`**, que es una foto que yo tomé. Misma regla que las
  Descriptions.
- Se genera al vuelo y no se guarda como archivo: no ocupa disco, se ve nítido a
  cualquier tamaño y se rehace solo si se corrige el título. El día que haga falta una
  imagen de verdad —compartir una Quote como imagen, una vista previa para redes— habrá
  que rasterizarlo, y eso es otra decisión.
- **El respaldo visual de «sin portada» desaparece** de la interfaz: ya no hay Books sin
  Cover. Lo que había en su lugar era un hueco punteado; ahora es una pieza diseñada.
