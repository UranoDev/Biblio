<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;

/**
 * El catálogo público al que se le consulta un ISBN o un título para armar la
 * Ficha.
 *
 * Éste es el seam del ADR-002: hay una implementación por catálogo y un
 * resolvedor que las consulta en orden. Cambiar el orden, agregar un catálogo
 * o contratar uno de pago no toca ninguna pantalla.
 *
 * Son dos operaciones porque son dos superficies: el Escaneo pregunta un ISBN
 * y espera una Ficha, el Alta por título pregunta un título y espera
 * Candidatos entre los cuales elegir. **El orden de la cascada no tiene por
 * qué ser el mismo en las dos** — vive en `config/fuentes.php`, con la
 * evidencia de por qué.
 */
interface Fuente
{
    /** Cuál Fuente es, para dejar registrado quién respondió por cada Libro. */
    public function identificador(): Enums\Fuente;

    public function porIsbn(Isbn $isbn): Respuesta;

    /**
     * Las ediciones que el catálogo tiene de ese título, para el Alta por
     * título. El autor es opcional y sirve para acotar.
     */
    public function porTitulo(string $titulo, ?string $autor = null): Candidatos;
}
